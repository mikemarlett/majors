<?php

declare(strict_types=1);

namespace Majors\Support;

/** Small HTML helpers shared by templates and renderers. */
final class Html
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Build an attribute string from a map; null/false values are skipped, true is bare. */
    public static function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $out .= $value === true ? ' ' . $name : ' ' . $name . '="' . self::e($value) . '"';
        }
        return $out;
    }

    /**
     * <select> with options. $options is value => label; $selected compared as strings.
     */
    public static function select(string $name, array $options, mixed $selected = null, array $attrs = [], ?string $placeholder = null): string
    {
        $html = '<select name="' . self::e($name) . '"' . self::attrs($attrs) . '>';
        $html .= self::options($options, $selected, $placeholder);
        return $html . '</select>';
    }

    public static function options(array $options, mixed $selected = null, ?string $placeholder = null): string
    {
        $html = '';
        if ($placeholder !== null) {
            $none = ($selected === null || $selected === '');
            $html .= '<option value="" disabled' . ($none ? ' selected' : '') . '>' . self::e($placeholder) . '</option>';
        }
        foreach ($options as $value => $label) {
            $isSel = $selected !== null && (string) $value === (string) $selected;
            $html .= '<option value="' . self::e($value) . '"' . ($isSel ? ' selected' : '') . '>' . self::e($label) . '</option>';
        }
        return $html;
    }

    /**
     * Rich text from the editors, reduced to what the program pages need:
     * paragraphs, line breaks, emphasis, links, lists and small headings.
     * Anything else is unwrapped (its text and allowed children stay);
     * script/style/iframe/object/embed/form controls go with their content.
     * Every attribute that is not on the per-tag list is dropped (style,
     * class, id, data-*, on*), and link targets go through safeUrl().
     * Word-paste debris (spans with styles, data-ccp-* attributes) disappears.
     */
    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $allowed = [
            'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
            'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
            'a' => ['href', 'title', 'target', 'rel'],
        ];
        $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base'];
        $doc  = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ma-clean-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $doc->getElementById('ma-clean-root');
        if ($root === null) {
            return '';
        }
        $walk = static function (\DOMNode $node) use (&$walk, $allowed, $drop): void {
            for ($child = $node->firstChild; $child !== null; $child = $next) {
                $next = $child->nextSibling;
                if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    $node->removeChild($child);
                    continue;
                }
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                $tag = strtolower($child->tagName);
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!isset($allowed[$tag])) {                       // unwrap: keep the children in place
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (!in_array($name, $allowed[$tag], true)) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'a') {
                    $href = self::safeUrl($child->getAttribute('href'));
                    if ($href === '') {                             // no usable target: keep the words, lose the link
                        while ($child->firstChild !== null) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    $child->setAttribute('href', $href);
                    if ($child->getAttribute('target') !== '_blank') {
                        $child->removeAttribute('target');
                        $child->removeAttribute('rel');
                    } else {
                        $child->setAttribute('rel', 'noopener');
                    }
                }
            }
        };
        $walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /**
     * A link target the pages may emit: relative (/, #, ?, or no scheme) or
     * http, https, mailto, tel. Anything else (javascript:, data:, vbscript:,
     * control characters hiding a scheme) becomes ''.
     */
    public static function safeUrl(string $url): string
    {
        $url = preg_replace('/[\x00-\x1f\x7f]+/', '', $url) ?? '';   // browsers ignore these inside a scheme ("java\tscript:")
        $url = str_replace(' ', '%20', trim($url));
        if ($url === '') {
            return '';
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
            return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true) ? $url : '';
        }
        return mb_substr($url, 0, 500);
    }

    public static function ordinal(int $n): string
    {
        if (!in_array($n % 100, [11, 12, 13], true)) {
            switch ($n % 10) {
                case 1:
                    return $n . 'st';
                case 2:
                    return $n . 'nd';
                case 3:
                    return $n . 'rd';
            }
        }
        return $n . 'th';
    }
}
