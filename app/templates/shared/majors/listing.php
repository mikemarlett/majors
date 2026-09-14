<?php
/**
 * Programs listing (A–Z or by college) with the results header.
 * Variables: $groups, $headline, $order, $link_base, $results_url (nullable), $all_url
 * @var \Majors\View\Layout $t
 */
?>
<header class="<?= $t->cls('section.header') ?> majors-results-header">
	<h2><?= $t->e($headline) ?></h2>
	<div class="<?= $t->cls('button_collection') ?>">
		<a href="<?= $t->e($all_url) ?>" role="button" class="<?= $t->cls('button') ?>">All Degree Programs <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php if ($results_url): ?>
		<a href="<?= $t->e($results_url) ?>" role="button" class="<?= $t->cls('button') ?>">Link to These Results <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php endif; ?>
	</div>
</header>
<?php if ($groups === []): ?>
<p class="<?= $t->cls('heading3') ?>">No programs match.</p>
<?php else: ?>
<nav class="majors-jump noprint <?= $t->cls('button_collection') ?>" aria-label="Jump to">
<?php foreach ($groups as $g): ?>
	<a href="#<?= $t->e($g['key']) ?>" role="button" class="<?= $t->cls('button.small') ?>"><?= $t->e($g['label']) ?></a>
<?php endforeach; ?>
</nav>
<hr>
<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php foreach ($groups as $g): ?>
	<div class="<?= $t->cls('alpha_list.items') ?>">
		<header class="<?= $t->cls('section.header') ?>"><h3 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($g['key']) ?>"><?= $t->e($g['label']) ?></h3></header>
	</div>
	<ul>
<?php foreach ($g['items'] as $p): ?>
		<li><a href="<?= $t->e($link_base . (int) $p['id']) ?>"><?= $t->e($p['academic_program']) ?></a> — <?= $t->e($p['program_type']) ?></li>
<?php endforeach; ?>
	</ul>
<?php endforeach; ?>
</div>
<?php endif; ?>
