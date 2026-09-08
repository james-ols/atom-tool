<?php
declare(strict_types=1);

namespace AtomTool\Storage;

use RuntimeException;

/**
 * Filesystem-backed Storage. Files live under <root>/<area>/.
 * The root is per-customer, e.g. atom-tool-gb166/storage/.
 */
final class LocalStorage implements Storage
{
    private const AREAS = [
        Storage::AREA_UPLOADS,
        Storage::AREA_OUTPUTS,
        Storage::AREA_REPORTS,
    ];

    public function __construct(
        private readonly string $root,
    ) {
        foreach (self::AREAS as $area) {
            $dir = $this->areaPath($area);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Cannot create storage directory: {$dir}");
            }
        }
    }

    public function storeStream(string $area, string $name, mixed $stream): StoredFile
    {
        $this->assertArea($area);
        $target = $this->filePath($area, $name);
        $out = fopen($target, 'wb');
        if ($out === false) {
            throw new RuntimeException("Cannot open target for write: {$target}");
        }
        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
        }
        return $this->statOrFail($area, basename($target));
    }

    public function storeString(string $area, string $name, string $contents): StoredFile
    {
        $this->assertArea($area);
        $target = $this->filePath($area, $name);
        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException("Cannot write file: {$target}");
        }
        return $this->statOrFail($area, basename($target));
    }

    public function list(string $area): array
    {
        $this->assertArea($area);
        $dir = $this->areaPath($area);
        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (!is_file($full)) {
                continue;
            }
            $files[] = new StoredFile(
                name: $entry,
                sizeBytes: (int) filesize($full),
                modifiedAt: (int) filemtime($full),
            );
        }
        usort($files, static fn(StoredFile $a, StoredFile $b) => $b->modifiedAt <=> $a->modifiedAt);
        return $files;
    }

    public function get(string $area, string $name): ?StoredFile
    {
        $this->assertArea($area);
        $safe = $this->sanitise($name);
        $full = $this->areaPath($area) . '/' . $safe;
        if (!is_file($full)) {
            return null;
        }
        return new StoredFile(
            name: $safe,
            sizeBytes: (int) filesize($full),
            modifiedAt: (int) filemtime($full),
        );
    }

    public function openRead(string $area, string $name)
    {
        $this->assertArea($area);
        $full = $this->filePath($area, $name);
        $handle = fopen($full, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open for read: {$full}");
        }
        return $handle;
    }

    public function delete(string $area, string $name): void
    {
        $this->assertArea($area);
        $safe = $this->sanitise($name);
        $full = $this->areaPath($area) . '/' . $safe;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function clearArea(string $area): void
    {
        $this->assertArea($area);
        $dir = $this->areaPath($area);
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . '/' . $entry;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    public function clearAll(): void
    {
        foreach (self::AREAS as $area) {
            $this->clearArea($area);
        }
    }

    private function areaPath(string $area): string
    {
        return rtrim($this->root, '/') . '/' . $area;
    }

    private function filePath(string $area, string $name): string
    {
        return $this->areaPath($area) . '/' . $this->sanitise($name);
    }

    /**
     * Reduce a client-supplied name to a safe basename: strip directories,
     * strip null bytes, keep [A-Za-z0-9._-], collapse the rest to underscore.
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
            throw new RuntimeException("File vanished after write: {$area}/{$name}");
        }
        return $meta;
    }
}