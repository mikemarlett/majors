<?php
/**
 * Design-system alert (Organism GlobalAlert): a themed slab with the level icon,
 * a heading and prose. Levels: info | warning | danger | success.
 * Variables: $level, $title, $body (HTML), optional $class, $attrs
 * @var \Majors\View\Layout $t
 */
$level = in_array($level ?? 'info', ['info', 'warning', 'danger', 'success'], true) ? $level : 'info';
ob_start();
?>
			<div class="flex flex-col gap-4 sm:flex-row sm:gap-6 majors-alert">
				<div aria-hidden="true" class="<?= $level ?> [&amp;.info]:mask-alert-info [&amp;.success]:mask-alert-success [&amp;.warning]:mask-alert-warning [&amp;.danger]:mask-alert-danger bg-theme-heading-color shrink-0 size-11 sm:size-16 majors-alert__icon"></div>
				<div class="flex flex-col gap-4 majors-alert__text">
					<div class="max-w-4xl"><h2 data-style-level="4" class="nc-heading majors-alert__title"><?= $t->e($title) ?></h2></div>
					<div class="prose max-w-4xl"><?= $body ?></div>
				</div>
			</div>
<?php
$inner = (string) ob_get_clean();
echo $t->partial('partials/slab', ['theme' => $level, 'body' => $inner, 'class' => trim('majors-alert-slab ' . ($class ?? '')), 'attrs' => $attrs ?? '', 'component' => 'global-alert']);
