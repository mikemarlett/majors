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

    public function fragment(string $name): string
    {
        $this->loaded ??= $this->load();
        return $this->loaded[$name] ?? '';
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $this->loaded ??= $this->load();
        return $this->loaded;
    }

    public function isAvailable(): bool
    {
        return is_file(rtrim($this->includesDir, '/') . '/headcode.inc');
    }

    /** @return array<string,string> */
    private function load(): array
    {
        $out = [];
        foreach (self::FRAGMENTS as $name => $file) {
            $path       = rtrim($this->includesDir, '/') . '/' . $file;
            $out[$name] = is_file($path) ? $this->capture($path) : '';
        }
        return $out;
    }

    private function capture(string $path): string
    {
        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            error_log('Theme fragment failed: ' . $path . ' — ' . $e->getMessage());
        }
        return (string) ob_get_clean();
    }
}
