<?php
/**
 * Full-bleed themed band (the design system's Generic Slab): coloured edge to
 * edge, content inside the site container. Outside the sidebar grid this is
 * the whole viewport; inside it, conditional-container makes it the column.
 * Variables: $theme (neutral-200|neutral-900|info|warning|danger|yellow), $body (HTML),
 *            optional $class (extra classes on <section>), $attrs (raw attribute string), $component
 * @var \Majors\View\Layout $t
 */
$theme     = $theme ?? 'neutral-200';
$class     = trim('generic-slab majors-slab ' . ($class ?? ''));
$attrs     = $attrs ?? '';
$component = $component ?? 'majors-slab';
?>
<section data-nc-component="<?= $t->e($component) ?>" data-tw-theme="<?= $t->e($theme) ?>" class="<?= $t->e($class) ?>"<?= $attrs !== '' ? ' ' . $attrs : '' ?>>
	<div class="generic-slab-inner relative py-6 majors-slab__inner">
		<div class="generic-slab-content-outer-wrapper relative conditional-container theme-not-default:container">
<?= $body ?>
		</div>
	</div>
</section>
