<?php
/**
 * Current design: a shaded section-wrap. Dark themes map to the "actions" shade.
 * Variables: $theme, $body, optional $class, $attrs
 * @var \Majors\View\Layout $t
 */
$theme = $theme ?? 'neutral-200';
$token = in_array($theme, ['neutral-900', 'neutral-700'], true) ? 'section.actions' : 'section.shade';
$attrs = $attrs ?? '';
?>
<section class="<?= $t->cls($token) ?> majors-slab<?= !empty($class) ? ' ' . $t->e($class) : '' ?>"<?= $attrs !== '' ? ' ' . $attrs : '' ?>>
<?= $body ?>
</section>
