<?php
declare(strict_types=1);

/**
 * Standalone development entry point for atom-tool itself.
 *
 * In production, a customer fork (e.g. atom-tool-gb166) has its own
 * public/index.php that requires ../atom-tool/bootstrap.php and constructs
 * a Config with its own customerCode and customerRoot. This file exists so
 * the engine can be run and tested in isolation.
 */

$factory = require __DIR__ . '/../bootstrap.php';

// Composer autoloader (provides the AWS SDK for S3 storage). Guarded so the
// engine still runs on a plain checkout without vendor/ (local-only, no S3).
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

// Small helper: read an env parameter with a fallback.
$env = static function (string $key, string $default = ''): string {
    $v = getenv($key);
    return $v === false ? $default : $v;
};

$app = $factory(new AtomTool\Config(
    customerCode: 'dev',
    customerRoot: __DIR__ . '/..',
    // Dev credentials: user "admin", password "dev".
    adminUser: 'admin',
    adminPasswordHash: '$2y$12$ibF9WWMLTofDLXZPhOTV4ecTyl8Swtr9ATprLy00FWrKc3B/O1QDO',
    sessionSecret: 'dev-secret-not-for-production',
));

$app->run();