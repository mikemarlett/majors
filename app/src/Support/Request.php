<?php

declare(strict_types=1);

namespace Majors\Support;

/**
 * Typed access to the current HTTP request. Replaces the `$vars` / `$$var`
 * extraction loops at the bottom of the legacy function files: every input is
 * read explicitly, by name, with a type.
 */
final class Request
{
    /**
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     */
    public function __construct(
        private readonly array $get,
        private readonly array $post,
        private readonly array $server,
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        return (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v   = $this->server[$key] ?? null;
        return $v === null ? null : (string) $v;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->get);
    }

    /** POST wins over GET, like $_REQUEST but explicit. */
    public function raw(string $key): mixed
    {
        return $this->post[$key] ?? $this->get[$key] ?? null;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->raw($key);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /** Non-null only when the value is a non-empty string. */
    public function strOrNull(string $key): ?string
    {
        $v = $this->str($key);
        return $v === '' ? null : $v;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->raw($key);
        if (is_int($v)) {
            return $v;
        }
        if (is_string($v) && is_numeric(trim($v))) {
            return (int) trim($v);
        }
        return $default;
    }

    /** Positive integer or null (ids). */
    public function id(string $key): ?int
    {
        $v = $this->int($key);
        return $v > 0 ? $v : null;
    }

    /** @return array<mixed> */
    public function arr(string $key): array
    {
        $v = $this->raw($key);
        return is_array($v) ? $v : [];
    }

    /** One of an allowed set, else the default. */
    public function enum(string $key, array $allowed, string $default): string
    {
        $v = $this->str($key);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public function get(string $key): mixed
    {
        return $this->get[$key] ?? null;
    }

    public function post(string $key): mixed
    {
        return $this->post[$key] ?? null;
    }

    /** @return array<string,mixed> */
    public function allPost(): array
    {
        return $this->post;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $this->header('Accept'), 'application/json');
    }
}
