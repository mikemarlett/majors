<?php

declare(strict_types=1);

namespace Majors\View;

/**
 * Section navigation for the new design.
 *
 * The redesign renders its section menu with the site's own PHP helper,
 * <docroot>/_resources/php/section-nav.php, which walks the CMS-published
 * _nav.ounav files (see that file's header). When it is present we use it, so
 * our pages get exactly the menu every other page in the section has. When it
 * is absent (local sandbox, or the old design) we fall back to a plain list of
 * the links the controller supplied.
 */
final class SectionNav
{
    private static bool $loaded = false;

    public function __construct(private readonly string $docroot)
    {
    }

    public function available(): bool
    {
        return is_file($this->docroot . '/_resources/php/section-nav.php');
    }

    /**
     * @return array{desktop:string,mobile:string}|null rendered fragments for the current URL, or null when unavailable
     */
    public function render(string $currentUrl): ?array
    {
        if (!$this->available()) {
            return null;
        }
        try {
            if (!self::$loaded) {
                require_once $this->docroot . '/_resources/php/section-nav.php';
                self::$loaded = true;
            }
            if (!function_exists('build_section_nav_model') || !function_exists('render_section_nav_desktop')) {
                return null;
            }
            $model = build_section_nav_model($currentUrl);
            return [
                'desktop' => (string) render_section_nav_desktop($model),
                'mobile'  => function_exists('render_section_nav_mobile') ? (string) render_section_nav_mobile($model) : '',
            ];
        } catch (\Throwable $e) {
            error_log('[majors] section-nav.php failed: ' . $e->getMessage());
            return null;
        }
    }
}
