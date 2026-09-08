<?php
declare(strict_types=1);

namespace AtomTool\Http;

/**
 * Literal method+path router. No regex, no parameters — the whole app has
 * a handful of routes and static paths are enough. Handlers receive the
 * Request and must return a Response.
 */
final class Router
{
    /** @var array<string, callable(Request): Response> */
    private array $routes = [];

    /** @var (callable(Request): Response)|null */
    private $fallback = null;

    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): self
    {
        $this->routes[$this->key($method, $path)] = $handler;
        return $this;
    }

    /**
     * Handler used when no route matches. If none is set, a plain 404 is returned.
     */
    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $key = $this->key($request->method, $request->path);
        if (isset($this->routes[$key])) {
            return ($this->routes[$key])($request);
        }
        if ($this->fallback !== null) {
            return ($this->fallback)($request);
        }
        return Response::notFound();
    }

    private function key(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . $path;
    }
}