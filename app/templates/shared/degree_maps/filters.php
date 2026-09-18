<?php
/**
 * Degree Maps search/select bar. Rendered by the layout in the full-width
 * "top" slot (new design: right under the page-title band, above the sidebar
 * grid; old design: top of the main column).
 *
 * Variables: $order, $year, $years, $colleges, $college, $search_url, $self_url
 * @var \Majors\View\Layout $t
 */
$new = $t->design() === 'new';
?>
<?php if ($new): ?>
<div class="dm-help noprint" data-accordion="true">
	<div data-accordion-panel="true">
		<h2 data-accordion-heading="true">Help</h2>
		<section data-accordion-section="true">
			<div data-accordion-section-contents="true">
				<p>Use the search field to find a degree map by name, college, department, or degree type. Choose a catalog year and a college to narrow the list, or switch between the alphabetical and by-college views below. Each map is printable as a single letter-size page.</p>
			</div>
		</section>
	</div>
</div>
<?php else: ?>
<section class="<?= $t->cls('section.feature') ?> noprint">
	<div class="<?= $t->cls('landing_panel') ?>">
		<p class="<?= $t->cls('landing_panel.text') ?>">Use the search field below to search the degree maps by name, college, department, or degree type. Choose a catalog year and college to narrow the list.</p>
	</div>
</section>
<?php endif; ?>

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
