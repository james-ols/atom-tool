<?php
declare(strict_types=1);

namespace AtomTool\Http;

/**
 * Read-only view over the current HTTP request.
 *
 * Constructed once at the top of Application::run() from PHP superglobals.
 * Nothing in the engine should touch $_SERVER/$_GET/$_POST/$_COOKIE directly
 * after this point.
 */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     * @param array<string, string> $cookies
     * @param array<string, mixed>  $files
     * @param array<string, mixed>  $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $cookies,
        public readonly array $files,
        public readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            post: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER,
        );
    }

    public function queryParam(string $name, ?string $default = null): ?string
    {
        return isset($this->query[$name]) ? (string) $this->query[$name] : $default;
    }

    public function postParam(string $name, ?string $default = null): ?string
    {
        return isset($this->post[$name]) ? (string) $this->post[$name] : $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return isset($this->cookies[$name]) ? (string) $this->cookies[$name] : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }
}