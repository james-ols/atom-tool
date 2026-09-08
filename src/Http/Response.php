<?php
declare(strict_types=1);

namespace AtomTool\Http;

/**
 * Buffered HTTP response. Handlers build one and return it; the Application
 * calls send() once at the end. Keeps side effects (header/echo) in one place.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<int, array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    private string $body = '';

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $options See setcookie() options array.
     */
    public function cookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public static function html(string $html, int $status = 200): self
    {
        return (new self())
            ->status($status)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->body($html);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self())
            ->status($status)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return (new self())
            ->status($status)
            ->header('Location', $location);
    }

    public static function text(string $text, int $status = 200): self
    {
        return (new self())
            ->status($status)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->body($text);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::text($message, 404);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        foreach ($this->cookies as $c) {
            setcookie($c['name'], $c['value'], $c['options']);
        }
        echo $this->body;
    }
}