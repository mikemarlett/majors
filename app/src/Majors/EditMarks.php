<?php

declare(strict_types=1);

namespace Majors\Majors;

use Majors\Support\Html;

/**
 * Editing markers for the in-place editor. The program templates ask this
 * object for the attributes and placeholders the editor needs; when editing
 * is off every method returns '' so the public output is untouched.
 *
 *   data-ma-text="field"        the element's text is the value (single line)
 *   data-ma-html="field"        the element's inner HTML is the value (rich text)
 *   data-ma-form="name"         click opens the named popover form; data-ma-json carries its current values
 *   data-ma-ph                  a placeholder for something empty (rendered only when editing)
 */
final class EditMarks
{
    /** @param array<int,int> $blockUses block id => number of sections using it */
    public function __construct(public readonly bool $on, public readonly array $blockUses = [])
    {
    }

    /**
     * @param string|null $value pass the current value so an empty one is flagged (the client clears the placeholder text on focus)
     * @param string $placeholder what the client shows when the value is emptied later (the same words show() prints for an empty value)
     */
    public function text(string $field, ?string $value = null, string $placeholder = ''): string
    {
        if (!$this->on) {
            return '';
        }
        return ' data-ma-text="' . Html::e($field) . '"' . ($value === '' ? ' data-ma-empty="1"' : '') . ($placeholder !== '' ? ' data-ma-placeholder="' . Html::e($placeholder) . '"' : '');
    }

    public function html(string $field, ?string $value = null): string
    {
        return $this->on ? ' data-ma-html="' . Html::e($field) . '"' . ($value === '' ? ' data-ma-empty="1"' : '') : '';
    }

    /** The escaped value, or (when editing) the placeholder label for an empty one. */
    public function show(string $value, string $placeholder): string
    {
        return $value !== '' ? Html::e($value) : ($this->on ? Html::e($placeholder) : '');
    }

    /** Trusted HTML as is, or (when editing) a placeholder paragraph for an empty value. */
    public function showHtml(string $value, string $placeholder): string
    {
        return $value !== '' ? $value : ($this->on ? '<p class="ma-ph-text">' . Html::e($placeholder) . '</p>' : '');
    }

    /** @param array<string,mixed> $values current values for the form */
    public function form(string $name, array $values = []): string
    {
        if (!$this->on) {
            return '';
        }
        return ' data-ma-form="' . Html::e($name) . '" data-ma-json="' . Html::e(json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"';
    }

    /**
     * A dashed placeholder for an empty optional thing. $marks is the
     * attribute string the real thing would carry (from text()/html()/form()).
     */
    public function placeholder(string $label, string $marks, string $class = ''): string
    {
        if (!$this->on) {
            return '';
        }
        return '<div class="ma-ph' . ($class !== '' ? ' ' . Html::e($class) : '') . '" data-ma-ph role="button" tabindex="0"' . $marks . '>' . Html::e($label) . '</div>';
    }

    /** Attributes for a section root: which save action its fields belong to. */
    public function scope(array $section): string
    {
        if (!$this->on) {
            return '';
        }
        $a = ' data-ma-kind="' . Html::e((string) $section['kind']) . '"';
        if (!empty($section['block_id'])) {
            $a .= ' data-ma-scope="block" data-block="' . (int) $section['block_id'] . '" data-ma-uses="' . (int) ($this->blockUses[(int) $section['block_id']] ?? 0) . '"';
        } else {
            $a .= ' data-ma-scope="section"';
        }
        return $a;
    }

    public function uses(int $blockId): int
    {
        return $this->blockUses[$blockId] ?? 0;
    }
}
