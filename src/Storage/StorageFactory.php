<?php
declare(strict_types=1);

namespace AtomTool\Storage;

use AtomTool\Config;
use RuntimeException;

/**
 * Builds the Storage backend the engine should use, based on Config.
 *
 * Keeps backend selection in one place so the Application stays ignorant of
 * which driver is active. Add a new driver here and nowhere else.
 */
final class StorageFactory
{
    public static function fromConfig(Config $config): Storage
    {
        return match ($config->storageDriver) {
            'local' => new LocalStorage($config->storageRoot()),
            's3'    => self::makeS3($config),
            default => throw new RuntimeException(
                "Unknown storageDriver '{$config->storageDriver}' (expected 'local' or 's3')."
            ),
        };
    }

    private static function makeS3(Config $config): S3Storage
    {
        if ($config->s3Bucket === '' || $config->s3Region === '') {
            throw new RuntimeException(
                "storageDriver 's3' requires both s3Bucket and s3Region to be set."
            );
        }

        return new S3Storage(
            bucket:   $config->s3Bucket,
            region:   $config->s3Region,
            prefix:   $config->s3Prefix(),
            endpoint: $config->s3Endpoint,
        );
    }
}