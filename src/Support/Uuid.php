<?php
declare(strict_types=1);

namespace AtomTool\Support;

use Random\RandomException;
use RuntimeException;

/**
 * Minimal, dependency-free UUID generator.
 *
 * Produces RFC 4122 version 4 (random) UUIDs, formatted 8-4-4-4-12, e.g.
 *   3f2a1b9c-6d4e-4a7b-9c2f-0e1a2b3c4d5e
 *
 * Used to give uploads and runs collision-proof identities that are safe as
 * flat storage keys (no directories) and recognisable to humans as GUIDs.
 */
final class Uuid
{
    private function __construct()
    {
    }

    public static function v4(): string
    {
        try {
            $bytes = random_bytes(16);
        } catch (RandomException $e) {
            throw new RuntimeException('Could not generate UUID: ' . $e->getMessage(), 0, $e);
        }

        // Set version (4) and variant (RFC 4122) bits.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}