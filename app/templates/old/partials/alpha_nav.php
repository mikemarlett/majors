<?php
/**
 * Letter index for an A–Z listing: the 2018 design's "alpha filters" molecule
 * (molecules/filtration/alpha-filters in the pattern library), a row of round
 * letter buttons. The theme's own stylesheet styles .alpha-filters__list .button;
 * the only addition is the dimmed state for a letter with nothing to jump to.
 *
 * Variables: $links (letter => anchor id, only the letters that have a section;
 *            '#' for a numeric section), optional $label
 * @var \Majors\View\Layout $t
 */
$label = $label ?? 'Jump to a letter';
?>
<nav class="alpha-filters majors-alpha-nav noprint" aria-label="<?= $t->e($label) ?>">
	<div class="alpha-filters__list">
<?php foreach (array_merge(range('A', 'Z'), ['#']) as $letter): ?>
<?php if (isset($links[$letter])): ?>
		<a href="#<?= $t->e($links[$letter]) ?>" role="button" class="button"<?= $letter === '#' ? ' aria-label="Numeric"' : '' ?>><?= $letter ?></a>
<?php elseif ($letter !== '#'): ?>
		<span class="button" aria-disabled="true"><?= $letter ?></span>
<?php endif; ?>
<?php endforeach; ?>
	</div>
</nav>
