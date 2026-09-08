<?php
declare(strict_types=1);

namespace AtomTool;

/**
 * Top-level engine facade. Holds the Config and runs the HTTP request.
 *
 * Later this will delegate to Http\Router. For now it emits a placeholder
 * page so we can verify the bootstrap pipeline works end-to-end.
 */
final class Application
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function run(): void
    {
        $version = trim((string) @file_get_contents($this->config->engineRoot() . '/VERSION'));
        $customer = htmlspecialchars($this->config->customerCode, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <title>AtoM Tool</title>
        </head>
        <body style="font-family: sans-serif; padding: 2rem;">
          <h1>AtoM Tool is running</h1>
          <p>Engine version: <strong>{$version}</strong></p>
          <p>Customer: <strong>{$customer}</strong></p>
        </body>
        </html>
        HTML;
    }

    public function config(): Config
    {
        return $this->config;
    }
}