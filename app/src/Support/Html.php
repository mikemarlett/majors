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
