<?php
declare(strict_types=1);

namespace AtomTool\Storage;

use AtomTool\Support\Uuid;

/**
 * Per-upload manifest reader/writer.
 *
 * Each uploaded CALM file gets a stable identity (uploadId) and a small,
 * human-readable JSON manifest stored ALONGSIDE the XML in the uploads area:
 *
 *   uploads/<uploadId>.xml    the CALM bytes
 *   uploads/<uploadId>.json   this manifest (metadata + its runs)
 *
 * The manifest is the join between friendly data (originalName) and the
 * GUID-named artefacts a run produces (outputs/<runId>.csv, reports/<runId>.
 * preflight.txt). There is deliberately NO central index — each upload is a
 * self-contained unit, so deletes are two-file operations and nothing drifts.
 *
 * Manifest shape:
 * {
 *   "uploadId":     "3f2a…",
 *   "originalName": "sarahs_april_exports_final_FINAL1.xml",
 *   "sizeBytes":    482113,
 *   "uploadedAt":   "2026-09-09T10:06:59+00:00",
 *   "runs": [
 *     {
 *       "runId":    "9b7c…",
 *       "pipeline": "description",
 *       "ranAt":    "2026-09-09T10:07:12+00:00",
 *       "output":   "9b7c….csv",
 *       "report":   "9b7c….preflight.txt",
 *       "coverage": { "percentMapped": 31.6, "unmappedCount": 26, "records": 158 }
 *     }
 *   ]
 * }
 */
final class Manifest
{
    private const SUFFIX = '.json';

    public function __construct(
        private readonly Storage $storage,
    ) {
    }

    /**
     * Create a manifest for a freshly stored upload and persist it.
     *
     * @return array<string,mixed> the created manifest
     */
    public function createUpload(string $uploadId, string $originalName, int $sizeBytes): array
    {
        $manifest = [
            'uploadId'     => $uploadId,
            'originalName' => $originalName,
            'sizeBytes'    => $sizeBytes,
            'uploadedAt'   => date('c'),
            'runs'         => [],
        ];
        $this->save($manifest);
        return $manifest;
    }

    /**
     * Append a run to an upload's manifest and persist it.
     *
     * @param array<string,mixed> $run
     */
    public function addRun(string $uploadId, array $run): void
    {
        $manifest = $this->load($uploadId);
        if ($manifest === null) {
            return;
        }
        $manifest['runs'][] = $run;
        $this->save($manifest);
    }

    /**
     * Load one upload's manifest, or null if it doesn't exist.
     *
     * @return array<string,mixed>|null
     */
    public function load(string $uploadId): ?array
    {
        if ($this->storage->get(Storage::AREA_UPLOADS, $uploadId . self::SUFFIX) === null) {
            return null;
        }
        $stream = $this->storage->openRead(Storage::AREA_UPLOADS, $uploadId . self::SUFFIX);
        try {
            $json = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * List all upload manifests, most-recently-uploaded first.
     *
     * @return list<array<string,mixed>>
     */
    public function listUploads(): array
    {
        $uploads = [];
        foreach ($this->storage->list(Storage::AREA_UPLOADS) as $file) {
            if (!str_ends_with($file->name, self::SUFFIX)) {
                continue; // skip the .xml files; manifests are the source of truth
            }
            $uploadId = substr($file->name, 0, -strlen(self::SUFFIX));
            $manifest = $this->load($uploadId);
            if ($manifest !== null) {
                $uploads[] = $manifest;
            }
        }

        usort(
            $uploads,
            static fn(array $a, array $b): int =>
            strcmp((string) ($b['uploadedAt'] ?? ''), (string) ($a['uploadedAt'] ?? ''))
        );

        return $uploads;
    }

    /**
     * The most recent run for an upload (any pipeline), or null if none.
     *
     * @return array<string,mixed>|null
     */
    public function latestRun(string $uploadId): ?array
    {
        $manifest = $this->load($uploadId);
        if ($manifest === null) {
            return null;
        }
        $runs = is_array($manifest['runs'] ?? null) ? $manifest['runs'] : [];
        if ($runs === []) {
            return null;
        }
        usort(
            $runs,
            static fn(array $a, array $b): int =>
            strcmp((string) ($b['ranAt'] ?? ''), (string) ($a['ranAt'] ?? ''))
        );
        return $runs[0];
    }

    /**
     * Delete an upload and everything it produced: its XML, its manifest, and
     * every run's output CSV and preflight report. Flat keys only.
     */
    public function deleteUpload(string $uploadId): void
    {
        $manifest = $this->load($uploadId);
        if ($manifest !== null) {
            foreach (($manifest['runs'] ?? []) as $run) {
                if (!empty($run['output'])) {
                    $this->storage->delete(Storage::AREA_OUTPUTS, (string) $run['output']);
                }
                if (!empty($run['report'])) {
                    $this->storage->delete(Storage::AREA_REPORTS, (string) $run['report']);
                }
            }
        }
        $this->storage->delete(Storage::AREA_UPLOADS, $uploadId . self::SUFFIX);
        $this->storage->delete(Storage::AREA_UPLOADS, $uploadId . '.xml');
    }

    /**
     * Convenience: a fresh UUID for a new upload or run.
     */
    public function newId(): string
    {
        return Uuid::v4();
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function save(array $manifest): void
    {
        $this->storage->storeString(
            Storage::AREA_UPLOADS,
            (string) $manifest['uploadId'] . self::SUFFIX,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}