<?php
/**
 * Degree Programs search/select bar: one full-bleed slab rendered by the layout
 * in the "top" slot (the same place the Degree Maps page keeps its bar). The
 * ids are what majors.js listens to.
 *
 * Current design: the search button is the sprite's magnifier, as on the CMS
 * page. Redesign: a text button, styled with the Degree Maps bar's rules.
 *
 * Variables: $filters (value => label), $filter, $order, $search, $college, $colleges, $self_url, $search_url
 * @var \Majors\View\Layout $t
 */
$filters = $filters ?? ['all' => 'All Programs', 'undergrad' => 'Undergrad Majors & Minors', 'graduate' => 'Graduate Degrees', 'online' => 'Online', 'minors' => 'Minors', 'certificates' => 'Certificates'];
$new     = $t->design() === 'new';
ob_start();
?>
	<div class="<?= $t->cls('filters') ?> dm-filters" id="majors-filters" data-search-url="<?= $t->e($search_url) ?>" data-self-url="<?= $t->e($self_url) ?>">
		<form class="<?= $t->cls('filters.search') ?>" id="degreeSearchForm" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="searchDegrees">Search</label>
			<input id="searchDegrees" name="search" type="search" placeholder="Search Degrees" value="<?= $t->e($search) ?>" autocomplete="off">
<?php if ($new): ?>
			<button type="submit">Search</button>
<?php else: ?>
			<button type="submit"><?= $t->icon('design--search', 'icon', 'Search') ?><span class="<?= $t->cls('sr_only') ?>">Search</span></button>
<?php endif; ?>
		</form>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selectDegreeType">Degree Type</label>
			<select id="selectDegreeType" name="filter"><optgroup label="Degree Program Type">
<?php foreach ($filters as $k => $label): ?>
				<option value="<?= $k ?>"<?= $k === $filter ? ' selected' : '' ?>><?= $t->e($label) ?></option>
<?php endforeach; ?>
			</optgroup></select>
		</form>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selectCollege">College</label>
			<select id="selectCollege" name="college"><optgroup label="In College">
				<option value="all">All Colleges</option>
<?php foreach ($colleges as $c): ?>
				<option value="<?= $t->e($c) ?>"<?= $c === $college ? ' selected' : '' ?>><?= $t->e($c) ?></option>
<?php endforeach; ?>
			</optgroup></select>
		</form>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selectOrder">Order</label>
			<select id="selectOrder" name="order"><optgroup label="View Order">
				<option value="alpha"<?= $order === 'alpha' ? ' selected' : '' ?>>Alphabetical</option>
				<option value="college"<?= $order === 'college' ? ' selected' : '' ?>>By College</option>
			</optgroup></select>
		</form>
	</div>
<?php echo $t->partial('partials/slab', ['theme' => 'neutral-200', 'class' => 'noprint majors-slab--filters', 'body' => (string) ob_get_clean()]); ?>
