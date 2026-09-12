<?php
declare(strict_types=1);

namespace AtomTool\Storage;

use RuntimeException;

/**
 * S3-backed Storage for production (AWS Lightsail).
 *
 * Objects are laid out to mirror LocalStorage's areas:
 *   s3://<bucket>/<prefix>/uploads/<name>
 *   s3://<bucket>/<prefix>/outputs/<name>
 *   s3://<bucket>/<prefix>/reports/<name>
 *
 * This implementation uses PHP's stream layer against the `s3://` wrapper that
 * the AWS SDK registers (Aws\S3\S3Client::register()). Credentials are NOT
 * handled here: on Lightsail they come from the instance/container IAM role
 * (or environment), which the SDK's default provider chain resolves. Keeping
 * auth out of this class means no secrets ever touch the engine code.
 *
 * The customer's bootstrap is responsible for creating an S3Client and calling
 * register() before any request is served, so the `s3://` wrapper is live.
 * If it isn't, every operation fails fast with a clear message.
 *
 * NOTE: This is a first-pass fleshing-out for the upcoming Lightsail test. It
 * favours correctness and clarity over micro-optimising S3 round-trips.
 */
final class S3Storage implements Storage
{
    private const AREAS = [
        Storage::AREA_UPLOADS,
        Storage::AREA_OUTPUTS,
        Storage::AREA_REPORTS,
    ];

    public function __construct(
        private readonly string $bucket,
        private readonly string $region,
        private readonly string $prefix,
        private readonly string $endpoint = '',
    ) {
        // The SDK registers the wrapper; we don't create clients here.
        if (!in_array('s3', stream_get_wrappers(), true)) {
            throw new RuntimeException(
                'The "s3://" stream wrapper is not registered. The customer bootstrap '
                . 'must create an Aws\S3\S3Client and call ->register() before serving '
                . 'requests (and the aws/aws-sdk-php package must be installed).'
            );
        }
    }

    public function storeStream(string $area, string $name, mixed $stream): StoredFile
    {
        $this->assertArea($area);
        $uri = $this->uri($area, $name);
        $out = fopen($uri, 'wb', false, $this->context());
        if ($out === false) {
            throw new RuntimeException("Cannot open S3 target for write: {$uri}");
        }
        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
        }
        return $this->statOrFail($area, $this->sanitise($name));
    }

    public function storeString(string $area, string $name, string $contents): StoredFile
    {
        $this->assertArea($area);
        $uri = $this->uri($area, $name);
        if (file_put_contents($uri, $contents, 0, $this->context()) === false) {
            throw new RuntimeException("Cannot write S3 object: {$uri}");
        }
        return $this->statOrFail($area, $this->sanitise($name));
    }

    public function list(string $area): array
    {
        $this->assertArea($area);
        $dir = $this->areaUri($area);

        $files = [];
        $handle = @opendir($dir, $this->context());
        if ($handle === false) {
            // An empty/absent prefix simply lists as nothing.
            return $files;
        }
        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dir . '/' . $entry;
                // Skip pseudo-directories; we only track flat files per area.
                if (is_dir($full)) {
                    continue;
                }
                $files[] = new StoredFile(
                    name: $entry,
                    sizeBytes: (int) @filesize($full),
                    modifiedAt: (int) @filemtime($full),
                );
            }
        } finally {
            closedir($handle);
        }

        usort($files, static fn (StoredFile $a, StoredFile $b) => $b->modifiedAt <=> $a->modifiedAt);
        return $files;
    }

    public function get(string $area, string $name): ?StoredFile
    {
        $this->assertArea($area);
        $safe = $this->sanitise($name);
        $uri = $this->areaUri($area) . '/' . $safe;

        // clearstatcache so repeated get()s after a write see fresh metadata.
        clearstatcache(true, $uri);
        if (!@file_exists($uri)) {
            return null;
        }
        return new StoredFile(
            name: $safe,
            sizeBytes: (int) @filesize($uri),
            modifiedAt: (int) @filemtime($uri),
        );
    }

    public function openRead(string $area, string $name)
    {
        $this->assertArea($area);
        $uri = $this->uri($area, $name);
        $handle = fopen($uri, 'rb', false, $this->context());
        if ($handle === false) {
            throw new RuntimeException("Cannot open S3 object for read: {$uri}");
        }
        return $handle;
    }

    public function delete(string $area, string $name): void
    {
        $this->assertArea($area);
        $uri = $this->uri($area, $name);
        if (@file_exists($uri)) {
            @unlink($uri, $this->context());
        }
    }

    public function clearArea(string $area): void
    {
        $this->assertArea($area);
        foreach ($this->list($area) as $file) {
            $this->delete($area, $file->name);
        }
    }

    public function clearAll(): void
    {
        foreach (self::AREAS as $area) {
            $this->clearArea($area);
        }
    }

    /**
     * A stream context carrying per-request S3 options (region, and an optional
     * custom endpoint for LocalStack/MinIO testing). Credentials are resolved
     * by the SDK's default provider chain (IAM role / environment), not here.
     *
     * @return resource
     */
    private function context()
    {
        $s3 = ['region' => $this->region];
        if ($this->endpoint !== '') {
            $s3['endpoint'] = $this->endpoint;
            $s3['use_path_style_endpoint'] = true; // MinIO/LocalStack friendly
        }
        return stream_context_create(['s3' => $s3]);
    }

    private function uri(string $area, string $name): string
    {
        return $this->areaUri($area) . '/' . $this->sanitise($name);
    }

    private function areaUri(string $area): string
    {
        $prefix = $this->prefix !== '' ? $this->prefix . '/' : '';
        return 's3://' . $this->bucket . '/' . $prefix . $area;
    }

    /**
     * Same sanitising rule as LocalStorage: reduce to a safe basename.
     */
    private function sanitise(string $name): string
    {
        $base = basename(str_replace("\0", '', $name));
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? '';
        $safe = ltrim($safe, '.');
        if ($safe === '') {
            throw new RuntimeException('Invalid file name');
        }
        return $safe;
    }

    private function assertArea(string $area): void
    {
        if (!in_array($area, self::AREAS, true)) {
            throw new RuntimeException("Unknown storage area: {$area}");
        }
    }

    private function statOrFail(string $area, string $name): StoredFile
    {
        $meta = $this->get($area, $name);
        if ($meta === null) {
            throw new RuntimeException("Object vanished after write: {$area}/{$name}");
        }
        return $meta;
    }
}