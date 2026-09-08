<?php
declare(strict_types=1);

/**
 * AtoM Tool engine bootstrap.
 *
 * Required by a customer fork's entry point. Registers the PSR-4 autoloader
 * for the AtomTool\ namespace and returns a factory closure that builds an
 * Application from a Config.
 *
 * Example use from a customer fork:
 *
 *   $factory = require __DIR__ . '/../atom-tool/bootstrap.php';
 *   $app = $factory(new AtomTool\Config(
 *       customerCode: 'gb166',
 *       customerRoot: __DIR__ . '/..',
 *   ));
 *   $app->run();
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'AtomTool\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

return static function (AtomTool\Config $config): AtomTool\Application {
    return new AtomTool\Application($config);
};