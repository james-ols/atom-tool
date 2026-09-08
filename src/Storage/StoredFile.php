<?php
declare(strict_types=1);

namespace AtomTool\Storage;

/**
 * Metadata for a single stored file. Storage implementations return these
 * from list() and get(); consumers use them without caring where the bytes live.
 */
final class StoredFile
{
    public function __construct(
        public readonly string $name,       // basename, e.g. "acme.xml"
        public readonly int $sizeBytes,
        public readonly int $modifiedAt,    // unix ts
    ) {
    }
}
