<?php
/**
 * Degree Programs listing page body: the results. The search/select bar is
 * majors/filters.php, rendered by the layout in the full-width top slot; the
 * page header and section menu are the layout's too.
 * Variables: $results (HTML from majors/listing)
 * @var \Majors\View\Layout $t
 */
?>

<div class="<?= $t->cls('wrapper') ?> dm-page">
	<div id="search_results" class="<?= $t->cls('divided_list') ?> dm-results" aria-live="polite">
<?= $results ?>
	</div>
</div>
