<?php
declare(strict_types=1);

namespace AtomTool;

/**
 * Customer configuration handed to the engine at bootstrap time.
 *
 * The customer fork constructs this and passes it to the Application. The
 * engine treats all fields as authoritative — no fallbacks, no discovery.
 *
 * Storage: `storageDriver` selects the backend. 'local' (default) uses the
 * filesystem under storageRoot(); 's3' uses an S3 bucket. On AWS Lightsail the
 * S3 settings come from container environment parameters, wired in the
 * customer's public/index.php — the engine itself reads none of the env.
 */
final class Config
{
    public function __construct(
        public readonly string $customerCode,
        public readonly string $customerRoot,
        public readonly string $engineRoot = __DIR__ . '/..',
        public readonly string $adminUser = 'admin',
        public readonly string $adminPasswordHash = '',
        public readonly string $sessionSecret = '',
        public readonly ?string $storageRoot = null,

        // ---- Storage backend selection ----
        // 'local' | 's3'
        public readonly string $storageDriver = 'local',

        // ---- S3 settings (used only when storageDriver === 's3') ----
        public readonly string $s3Bucket = '',
        public readonly string $s3Region = '',
        // Key prefix within the bucket, e.g. 'gb166'. Areas nest beneath it:
        //   <prefix>/uploads/<name>, <prefix>/outputs/<name>, ...
        // Defaults to customerCode when left blank (see s3Prefix()).
        public readonly string $s3Prefix = '',
        // Optional custom endpoint (e.g. for LocalStack / MinIO in testing).
        // Blank = real AWS S3.
        public readonly string $s3Endpoint = '',
    ) {
    }

    /**
     * Absolute path to the customer's mapping.php file.
     */
    public function mappingPath(): string
    {
        return $this->customerRoot . '/mapping.php';
    }

    /**
     * Absolute path to the customer's DTD directory (or single DTD).
     */
    public function dtdPath(): string
    {
        return $this->customerRoot;
    }

    /**
     * Absolute path to the AtoM CSV template directory.
     */
    public function templatesPath(): string
    {
        return $this->customerRoot;
    }

    /**
     * Absolute path to the engine's root (for VERSION, views, assets).
     */
    public function engineRoot(): string
    {
        return $this->engineRoot;
    }

    /**
     * Absolute path to the per-customer storage root.
     * Defaults to <customerRoot>/storage if not overridden.
     */
    public function storageRoot(): string
    {
        return $this->storageRoot ?? $this->customerRoot . '/storage';
    }

    /**
     * The S3 key prefix for this customer's data. Falls back to customerCode
     * so each customer's objects live under their own namespace by default.
     */
    public function s3Prefix(): string
    {
        $prefix = trim($this->s3Prefix) !== '' ? $this->s3Prefix : $this->customerCode;
        return trim($prefix, '/');
    }
}