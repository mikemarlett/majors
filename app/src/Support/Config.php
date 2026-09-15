<?php

declare(strict_types=1);

namespace Majors\Support;

/**
 * Configuration, layered so one app root (/data/www/config/majors) can serve
 * several sites on the same box (www-dev and www-test share /data/www/config):
 *
 *   config/app.php              defaults (in git)
 *   config/app.local.php        this box, every site (gitignored)
 *   config/app.<site>.php       one site, e.g. app.www-dev.php (gitignored)
 *
 * <site> is the first label of the request host (www, www-dev, www-test); for
 * CLI scripts set MAJORS_SITE=www-test, or it falls back to the docroot name
 * (main → www, main-dev → www-dev, main-test → www-test), then 'local'.
 * Dotted keys: get('auth.provider').
 */
final class Config
{
    /** @param array<string,mixed> $values */
    public function __construct(private array $values, private readonly string $site = 'local')
    {
    }

    public static function load(string $dir, ?string $site = null): self
    {
        $site   = $site ?? self::detectSite();
        $values = self::file($dir . '/app.php', true);
        foreach ([$dir . '/app.local.php', $dir . '/app.' . $site . '.php'] as $layer) {
            $values = array_replace_recursive($values, self::file($layer, false));
        }
        return new self($values, $site);
    }

    /**
     * Which site this request (or CLI run) belongs to.
     * Only [a-z0-9-] survives, so a hostile Host header cannot pick a path.
     */
    public static function detectSite(): string
    {
        $env = getenv('MAJORS_SITE');
        if (is_string($env) && $env !== '') {
            return self::clean($env);
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $label = strtolower(explode('.', explode(':', $host)[0])[0]);
            if (in_array($label, ['localhost', '127'], true)) {
                return 'local';
            }
            return self::clean($label);
        }
        $docroot = basename(rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
        return match ($docroot) {
            'main'      => 'www',
            'main-dev'  => 'www-dev',
            'main-test' => 'www-test',
            ''          => 'local',
            default     => self::clean($docroot),
        };
    }

    public function site(): string
    {
        return $this->site;
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

    /** @return array<string,mixed> */
    private static function file(string $path, bool $required): array
    {
        if (!is_file($path)) {
            if ($required) {
                throw new \RuntimeException("Missing config file: {$path}");
            }
            return [];
        }
        $v = require $path;
        if (!is_array($v)) {
            throw new \RuntimeException("{$path} must return an array.");
        }
        return $v;
    }

    private static function clean(string $s): string
    {
        $s = preg_replace('/[^a-z0-9-]/', '', strtolower($s)) ?? '';
        return $s === '' ? 'local' : $s;
    }
}
