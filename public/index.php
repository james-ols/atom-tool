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

$app = $factory(new AtomTool\Config(
    customerCode: 'dev',
    customerRoot: __DIR__ . '/..',
));

$app->run();