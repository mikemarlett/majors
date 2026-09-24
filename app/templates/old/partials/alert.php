<?php
/**
 * Current design: the site's alert-bar. Levels: info (grey) | warning/danger (emergency red).
 * Variables: $level, $title, $body (HTML), optional $class, $attrs
 * @var \Majors\View\Layout $t
 */
$level  = $level ?? 'info';
$strong = in_array($level, ['warning', 'danger'], true);
$attrs  = $attrs ?? '';
?>
	<div class="<?= $t->cls($strong ? 'alert.emergency' : 'alert') ?><?= !empty($class) ? ' ' . $t->e($class) : '' ?>"<?= $attrs !== '' ? ' ' . $attrs : '' ?>>
		<div class="<?= $t->cls('alert.wrapper') ?>">
			<div class="<?= $t->cls('alert.icon') ?>"><?= $t->icon($strong ? 'design--exclamation-triangle' : 'design--info', 'icon', $strong ? 'Warning' : 'Note') ?></div>
			<div class="<?= $t->cls('alert.message') ?>">
				<div class="<?= $t->cls('headline') ?>"><span class="<?= $t->cls('headline.head') ?>"><?= $t->e($title) ?></span></div>
<?= $body ?>
			</div>
		</div>
	</div>
