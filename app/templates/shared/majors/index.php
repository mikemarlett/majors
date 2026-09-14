<?php
/**
 * Degree Programs listing page body.
 * Variables: $title, $nav_items (label=>href), $results, $filter, $order, $search, $college, $colleges, $self_url, $search_url
 * @var \Majors\View\Layout $t
 */
$nav_html = '';
foreach ($nav_items as $label => $href) {
    $nav_html .= '<li><a href="' . $t->e($href) . '">' . $t->e($label) . '</a></li>' . "\n";
}
$filters = ['all' => 'All Programs', 'undergrad' => 'Undergraduate Degrees', 'graduate' => 'Graduate Degrees', 'online' => 'Online', 'minors' => 'Minors', 'certificates' => 'Certificates', 'badges' => 'Badges'];
?>
<?= $t->partial('partials/page_header', ['title' => $title, 'nav_html' => $nav_html, 'noprint' => true]) ?>

<section class="<?= $t->cls('section.shade') ?> noprint">
	<div class="<?= $t->cls('filters') ?> dm-filters" id="majors-filters" data-search-url="<?= $t->e($search_url) ?>" data-self-url="<?= $t->e($self_url) ?>">
		<form class="<?= $t->cls('filters.search') ?>" id="degreeSearchForm" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="searchDegrees">Search</label>
			<input id="searchDegrees" name="search" type="search" placeholder="Search Degrees" value="<?= $t->e($search) ?>" autocomplete="off">
			<button type="submit"><?= $t->icon('design--search', 'icon', 'Search') ?><span class="<?= $t->cls('sr_only') ?>">Search</span></button>
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
</section>

<div class="<?= $t->cls('wrapper') ?> dm-page">
	<div id="search_results" class="<?= $t->cls('divided_list') ?> dm-results" aria-live="polite">
<?= $results ?>
	</div>
</div>
