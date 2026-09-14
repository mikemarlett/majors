<?php
/**
 * A–Z or by-college list of maps.
 * Variables: $groups (Listing::byAlpha/byCollege), $link_base (url prefix that takes the map id)
 * @var \Majors\View\Layout $t
 */
?>
<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php if ($groups === []): ?>
	<p class="<?= $t->cls('heading3') ?>">No degree maps found.</p>
<?php endif; ?>
<?php foreach ($groups as $group): ?>
	<hr>
	<div class="<?= $t->cls('alpha_list.items') ?>">
		<header><h2 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($group['key']) ?>"><?= $t->e($group['label']) ?></h2></header>
	</div>
	<ul>
<?php foreach ($group['items'] as $m): ?>
		<li><a href="<?= $t->e($link_base . (int) $m['id']) ?>"><?= $t->e($m['major']) ?></a> — <?= $t->e($m['degree_type']) ?></li>
<?php endforeach; ?>
	</ul>
<?php endforeach; ?>
</div>
