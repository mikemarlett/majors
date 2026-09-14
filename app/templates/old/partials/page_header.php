<?php
/**
 * Old-design page header: title bar plus the section menu.
 * Variables: $title, $nav_html (li items), $noprint (bool)
 * @var \Majors\View\Layout $t
 */
$nav_html = $nav_html ?? '';
$noprint  = $noprint ?? true;
?>
<header class="<?= $t->cls('page_header') ?><?= $noprint ? ' noprint' : '' ?>">
	<div class="<?= $t->cls('page_header.bar') ?>">
		<div class="<?= $t->cls('page_header.title') ?>">
			<h1 class="<?= $t->cls('headline') ?>"><span class="<?= $t->cls('headline.head') ?>"><?= $t->e($title) ?></span></h1>
		</div>
<?php if ($nav_html !== ''): ?>
		<div class="<?= $t->cls('section_nav') ?>">
			<div class="<?= $t->cls('section_nav.toggle') ?>">
				<div class="<?= $t->cls('section_nav.wrapper') ?>">
					<button class="<?= $t->cls('section_nav.button') ?>">Section Menu <?= $t->icon('design--menu', 'icon', 'Open Section Links') ?></button>
				</div>
			</div>
			<nav>
				<ul>
<?= $nav_html ?>
				</ul>
			</nav>
		</div>
<?php endif; ?>
	</div>
</header>
