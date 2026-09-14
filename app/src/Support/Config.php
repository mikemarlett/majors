<?php

declare(strict_types=1);

namespace Majors\Support;

/**
 * Configuration: config/app.php merged with an optional, gitignored
 * config/app.local.php (per-server overrides). Dotted keys: get('auth.provider').
 */
final class Config
{
    /** @param array<string,mixed> $values */
    public function __construct(private array $values)
    {
    }

    public static function load(string $dir): self
    {
        $base  = require $dir . '/app.php';
        $local = is_file($dir . '/app.local.php') ? require $dir . '/app.local.php' : [];
        if (!is_array($base) || !is_array($local)) {
            throw new \RuntimeException('config/app.php and app.local.php must return arrays.');
        }
        return new self(array_replace_recursive($base, $local));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->get($key, $default);
        return is_scalar($v) ? (string) $v : $default;
    }

    /** The value if it is one of $allowed, else $default. */
    public function enumString(string $key, array $allowed, string $default): string
    {
        $v = $this->string($key, $default);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
