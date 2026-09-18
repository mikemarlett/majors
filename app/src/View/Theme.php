<?php

declare(strict_types=1);

namespace Majors\View;

/**
 * Live main-site chrome, published by Modern Campus as flat fragments in an
 * includes directory. We never keep a second copy of the header/footer; we
 * splice the live files in exactly as the CMS templates do:
 *
 *   headcode.inc   -> inside <head>
 *   header.inc     -> right after <body> opens
 *   alert.php      -> top of <main> (old design only; absent in the new theme)
 *   footer.inc     -> before </body>
 *   footcode.inc   -> after the footer
 *   analytics.inc  -> after footcode (old design only)
 *
 * The directory moves between designs (old: _resources/includes, new:
 * _resources/_theme/includes), so it is injected from config. Fragments are
 * captured with include() + output buffering so embedded PHP still runs;
 * missing files degrade to '' so a local dev server renders without the CMS.
 */
final class Theme
{
    private const FRAGMENTS = [
        'headcode'  => 'headcode.inc',
        'header'    => 'header.inc',
        'alert'     => 'alert.php',
        'footer'    => 'footer.inc',
        'footcode'  => 'footcode.inc',
        'analytics' => 'analytics.inc',
    ];

    /** @var array<string,string>|null */
    private ?array $loaded = null;

    public function __construct(private readonly string $includesDir)
    {
    }

    public function includesDir(): string
    {
        return $this->includesDir;
    }

    /** One fragment, captured on first use (each include runs at most once per request). */
    public function fragment(string $name): string
    {
        if (!isset(self::FRAGMENTS[$name])) {
            return '';
        }
        if (!array_key_exists($name, $this->loaded ?? [])) {
            $path = rtrim($this->includesDir, '/') . '/' . self::FRAGMENTS[$name];
            $this->loaded[$name] = is_file($path) ? $this->capture($path) : '';
        }
        return $this->loaded[$name];
    }

    /**
     * Only the named fragments are captured; the rest come back as ''. A design
     * that never renders alert.php must not include it: the theme's alert.php
     * fetches an RSS feed over the network on every request and hangs when
     * the server cannot reach it (www-dev, 2026-09-17: 60 s → 504).
     *
     * @param string[] $names
     * @return array<string,string> every fragment name, loaded or ''
     */
    public function fragments(array $names): array
    {
        $out = [];
        foreach (array_keys(self::FRAGMENTS) as $name) {
            $out[$name] = in_array($name, $names, true) ? $this->fragment($name) : '';
        }
        return $out;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        return $this->fragments(array_keys(self::FRAGMENTS));
    }

    public function isAvailable(): bool
    {
        return is_file(rtrim($this->includesDir, '/') . '/headcode.inc');
    }

    private function capture(string $path): string
    {
        // Site includes may fetch things (alert.php reads an RSS feed): never let
        // one unreachable host hold the whole page for a minute.
        $prevTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '5');
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            error_log('Theme fragment failed: ' . $path . ' — ' . $e->getMessage());
        } finally {
            ini_set('default_socket_timeout', (string) $prevTimeout);
        }
        return (string) ob_get_clean();
    }
}
