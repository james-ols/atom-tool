<?php
declare(strict_types=1);

namespace AtomTool;

/**
 * Customer configuration handed to the engine at bootstrap time.
 *
 * The customer fork constructs this and passes it to the Application. The
 * engine treats all fields as authoritative — no fallbacks, no discovery.
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
}