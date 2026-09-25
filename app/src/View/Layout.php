<?php

declare(strict_types=1);

namespace Majors\View;

use Majors\Support\Html;
use RuntimeException;

/**
 * Template renderer with a design switch.
 *
 * A template name like 'degree_maps/map' is looked up in
 * templates/<design>/ first and templates/shared/ second, so a design only
 * needs to override the pieces that speak its chrome's vocabulary (page
 * header, section wrappers, filter bars, buttons). Data-bearing templates
 * live in shared/ and ask for chrome classes through $t->cls('token'), which
 * reads templates/<design>/classmap.php.
 *
 * Inside a template, $t is this Layout and every entry of $vars is a local.
 */
final class Layout
{
    /**
     * Which site includes each design's layout renders. The new theme's pages
     * never show alert.php or analytics.inc, and alert.php makes a network
     * call on every request, so the new design must not even load them.
     */
    private const CHROME_FRAGMENTS = [
        'old' => ['headcode', 'header', 'alert', 'footer', 'footcode', 'analytics'],
        'new' => ['headcode', 'header', 'footer', 'footcode'],
    ];

    /** @var array<string,string>|null */
    private ?array $classmap = null;

    public function __construct(
        private readonly string $templatesDir,
        private readonly string $design,
        private readonly Theme $theme,
        private readonly string $baseUrl,
        private readonly string $assetsDir,
        private readonly array $site = [],
        private readonly string $docroot = '',
    ) {
    }

    public function design(): string
    {
        return $this->design;
    }

    public function theme(): Theme
    {
        return $this->theme;
    }

    /**
     * Site-wide values from config (logo, sprite, contact...). A value may be
     * one string for both designs or ['old' => ..., 'new' => ...] when the
     * asset lives somewhere else under the redesign.
     */
    public function site(string $key, string $default = ''): string
    {
        $v = $this->site[$key] ?? $default;
        if (is_array($v)) {
            $v = $v[$this->design] ?? $v['old'] ?? $default;
        }
        return is_scalar($v) ? (string) $v : $default;
    }

    /** Render a template to a string. */
    public function render(string $name, array $vars = []): string
    {
        $file = $this->resolve($name);
        $t    = $this;
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Alias that reads better inside templates. */
    public function partial(string $name, array $vars = []): string
    {
        return $this->render($name, $vars);
    }

    /**
     * Full page: wrap already-rendered content in templates/<design>/layout.php.
     *
     * The layout owns the page header band and the section menu, because the
     * two designs place them differently (the new one puts the menu in a
     * sidebar grid around the content). Pass:
     *   page_header  h1 text (null = no header band)
     *   nav_items    label => href for the section menu (old: printed as a list;
     *                new: used only when the site's own section-nav renderer is absent)
     *   nav_html     pre-rendered <li>…</li> items (e.g. from the CMS _nav.ounav)
     *   header_print whether the header band prints (default false: .noprint)
     *
     * @param array{title?:string,description?:string,head?:string[],foot?:string[],body_class?:string,
     *               user?:mixed,csrf?:string,page_header?:?string,nav_items?:array<string,string>,nav_html?:string,header_print?:bool} $opts
     */
    public function page(string $content, array $opts = []): string
    {
        $navHtml = $opts['nav_html'] ?? '';
        foreach ($opts['nav_items'] ?? [] as $label => $href) {
            $navHtml .= '<li><a href="' . $this->e($href) . '">' . $this->e($label) . '</a></li>' . "\n";
        }
        $sectionNav = null;
        if ($this->design === 'new' && ($navHtml !== '' || !empty($opts['nav_items']))) {
            $sectionNav = (new SectionNav($this->docroot))->render((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        }
        return $this->render('layout', [
            'content'      => $content,
            'top'          => $opts['top'] ?? '',   // full-width strip under the page title (new design); before the content (old)
            'bottom'       => $opts['bottom'] ?? '', // full-width strip after the sidebar grid (new design); after the content (old)
            'title'        => $opts['title'] ?? $this->site('site_name', 'Wichita State University'),
            'description'  => $opts['description'] ?? '',
            'head'         => $opts['head'] ?? [],
            'foot'         => $opts['foot'] ?? [],
            'body_class'   => $opts['body_class'] ?? '',
            'user'         => $opts['user'] ?? null,
            'csrf'         => $opts['csrf'] ?? null,
            'page_header'  => $opts['page_header'] ?? null,
            'nav_html'     => $navHtml,
            'section_nav'  => $sectionNav,
            'header_print' => (bool) ($opts['header_print'] ?? false),
            'chrome'       => $this->theme->fragments(self::CHROME_FRAGMENTS[$this->design] ?? self::CHROME_FRAGMENTS['old']),
        ]);
    }

    public function exists(string $name): bool
    {
        return $this->find($name) !== null;
    }

    public function e(mixed $value): string
    {
        return Html::e($value);
    }

    /** Chrome class(es) for a design token; '' when the design has no mapping. */
    public function cls(string $token, string $fallback = ''): string
    {
        $this->classmap ??= $this->loadClassmap();
        return $this->classmap[$token] ?? $fallback;
    }

    /** Public URL under the app's docroot folder, e.g. url('degree_maps/maps.php'). */
    public function url(string $path = ''): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Public URL of a program page. Programs are addressed by their basename
     * (the CMS page's name, unique); only a row without one falls back to ?id=.
     *
     * @param array<string,mixed> $p a program row (basename, id)
     */
    public function programUrl(array $p): string
    {
        $b = (string) ($p['basename'] ?? '');
        return $this->url('index.php') . ($b !== '' ? '?program=' . rawurlencode($b) : '?id=' . (int) ($p['id'] ?? 0));
    }

    /** The in-place editor for a program (same key as the public page). */
    public function editUrl(array $p): string
    {
        $b = (string) ($p['basename'] ?? '');
        return $this->url('_admin/program.php') . ($b !== '' ? '?program=' . rawurlencode($b) : '?id=' . (int) ($p['id'] ?? 0));
    }

    /** A content image URL, prefixed with site.image_base when this box doesn't have the CMS-published photos. */
    public function img(string $url): string
    {
        $base = $this->site('image_base', '');
        return $base !== '' && str_starts_with($url, '/') ? rtrim($base, '/') . $url : $url;
    }

    /**
     * A <script> that sets window.<name> to the given array. JSON inside an
     * inline script is encoded with the HTML-safe flags, so a program name
     * containing "</script>" can never break out of it.
     */
    public function jsConfig(string $name, array $config): string
    {
        return '<script>window.' . $name . ' = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ';</script>';
    }

    /** Cache-busted URL to a file in docroot/academics/majors/assets/. */
    public function asset(string $path): string
    {
        $file = rtrim($this->assetsDir, '/') . '/' . ltrim($path, '/');
        $v    = is_file($file) ? '?v=' . filemtime($file) : '';
        return $this->url('assets/' . ltrim($path, '/')) . $v;
    }

    /** Inline SVG sprite reference used by both designs' icon sets. */
    public function icon(string $symbol, string $class = 'icon', string $title = ''): string
    {
        $sprite = $this->site('sprite', '/_resources/images/sprites/svg-sprite-custom-symbol.svg');
        $titleEl = $title !== '' ? '<title>' . $this->e($title) . '</title>' : '';
        $aria    = $title !== '' ? ' role="img"' : ' aria-hidden="true"';
        return '<svg class="' . $this->e($class) . '"' . $aria . '>' . $titleEl
            . '<use xlink:href="' . $this->e($sprite) . '#' . $this->e($symbol) . '"></use></svg>';
    }

    private function resolve(string $name): string
    {
        $file = $this->find($name);
        if ($file === null) {
            throw new RuntimeException("Template not found: {$name} (design {$this->design})");
        }
        return $file;
    }

    private function find(string $name): ?string
    {
        $name = str_replace(['..', "\0"], '', $name);
        foreach ([$this->design, 'shared'] as $dir) {
            $file = $this->templatesDir . '/' . $dir . '/' . $name . '.php';
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    /** @return array<string,string> */
    private function loadClassmap(): array
    {
        $file = $this->templatesDir . '/' . $this->design . '/classmap.php';
        $map  = is_file($file) ? require $file : [];
        return is_array($map) ? $map : [];
    }
}
