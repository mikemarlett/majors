<?php
/**
 * Public Degree Maps page body: view switch + results. The search/select bar
 * is degree_maps/filters.php, rendered by the layout in the full-width top slot.
 * Variables: $results, $order, $year, $showing_map, $self_url
 * @var \Majors\View\Layout $t
 */
?>

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
