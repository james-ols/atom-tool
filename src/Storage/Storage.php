<?php
declare(strict_types=1);

namespace AtomTool\Storage;

/**
 * Per-customer storage abstraction. Implementations: LocalStorage (dev + frog),
 * S3Storage (production, later). All methods are scoped to one of the fixed
 * sub-areas: uploads, outputs, reports.
 */
interface Storage
{
    public const AREA_UPLOADS = 'uploads';
    public const AREA_OUTPUTS = 'outputs';
    public const AREA_REPORTS = 'reports';

    /**
     * Store bytes from an already-open stream (e.g. an uploaded file handle).
     * $name is the target basename; implementations must sanitise it.
     */
    public function storeStream(string $area, string $name, mixed $stream): StoredFile;

    /**
     * Convenience for small payloads (CSV output, JSON report).
     */
    public function storeString(string $area, string $name, string $contents): StoredFile;

    /**
     * List files in an area, most-recent first.
     * @return list<StoredFile>
     */
    public function list(string $area): array;

    /**
     * Return metadata for one file, or null if it doesn't exist.
     */
    public function get(string $area, string $name): ?StoredFile;

    /**
     * Open a readable stream for a stored file (for streaming parsers).
     * @return resource
     */
    public function openRead(string $area, string $name);

    /**
     * Delete one file. No error if it doesn't exist.
     */
    public function delete(string $area, string $name): void;

    /**
     * Delete everything in an area.
     */
    public function clearArea(string $area): void;

    /**
     * Delete everything for this customer (uploads + outputs + reports).
     */
    public function clearAll(): void;
}