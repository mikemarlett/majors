<?php
/**
 * Public Degree Maps page body: header, intro, filters, view switch, results.
 * Variables: $title, $results, $order, $year, $years, $colleges, $college, $showing_map,
 *            $nav_file, $search_url, $self_url
 * @var \Majors\View\Layout $t
 */
$nav_html = (isset($nav_file) && is_file($nav_file)) ? (string) file_get_contents($nav_file) : '';
?>
<?= $t->partial('partials/page_header', ['title' => $title, 'nav_html' => $nav_html, 'noprint' => true]) ?>

<section class="<?= $t->cls('section.feature') ?> noprint">
	<div class="<?= $t->cls('landing_panel') ?>">
		<p class="<?= $t->cls('landing_panel.text') ?>">Use the search field below to search the degree maps by name, college, department, or degree type. Choose a catalog year and college to narrow the list.</p>
	</div>
</section>

<section class="<?= $t->cls('section.shade') ?> noprint">
	<div class="<?= $t->cls('filters') ?> dm-filters" id="dm-filters" data-search-url="<?= $t->e($search_url) ?>" data-self-url="<?= $t->e($self_url) ?>">
		<form id="search_form" class="<?= $t->cls('filters.search') ?>" method="post" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="searchList">Search</label>
			<input id="searchList" name="searchList" type="search" placeholder="Search Degree Maps" autocomplete="off">
			<button type="submit">Search</button>
		</form>
		<form id="search_academic_year" class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selected_year">Select Catalog Year</label>
			<select id="selected_year" name="selected_year">
				<optgroup label="Catalog Year">
<?php foreach ($years as $y): ?>
					<option value="<?= (int) $y ?>"<?= (int) $y === (int) $year ? ' selected' : '' ?>><?= (int) $y - 1 ?> - <?= (int) $y ?></option>
<?php endforeach; ?>
				</optgroup>
			</select>
			<input type="hidden" name="order" value="<?= $t->e($order) ?>">
		</form>
		<form id="search_select" class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selected_college">Select College</label>
			<select id="selected_college" name="selected_college">
				<optgroup label="College">
					<option value="all">All Colleges</option>
<?php foreach ($colleges as $c): ?>
					<option value="<?= $t->e($c) ?>"<?= $college === $c ? ' selected' : '' ?>><?= $t->e($c) ?></option>
<?php endforeach; ?>
				</optgroup>
			</select>
			<input type="hidden" name="order" value="<?= $t->e($order) ?>">
			<input type="hidden" name="selected_year" value="<?= (int) $year ?>">
		</form>
	</div>
</section>

<div class="<?= $t->cls('wrapper') ?> dm-page">
	<div class="dm-view-switch noprint">
		<h2 class="<?= $t->cls('heading5') ?>">Select View:</h2>
		<p>
<?php $byCollege = $order === 'college'; ?>
			<a href="<?= $t->e($self_url) ?>?order=college&amp;selected_year=<?= (int) $year ?>" id="college" data-order="college"<?= $byCollege ? ' aria-current="true"' : '' ?>><?= $byCollege ? '<strong>' : '' ?>All Programs by College<?= $byCollege ? '</strong>' : '' ?></a>
			&nbsp;&nbsp;|&nbsp;&nbsp;
			<a href="<?= $t->e($self_url) ?>?order=alpha&amp;selected_year=<?= (int) $year ?>" id="alpha" data-order="alpha"<?= !$byCollege ? ' aria-current="true"' : '' ?>><?= !$byCollege ? '<strong>' : '' ?>All Programs by Alphabetical Listing<?= !$byCollege ? '</strong>' : '' ?></a>
		</p>
	</div>

	<div id="search_results" class="<?= $t->cls('divided_list') ?> dm-results" aria-live="polite">
<?= $results ?>
	</div>
</div>
