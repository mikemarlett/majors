<?php
/**
 * Degree Maps admin page body: flash + results. The search bar and the Actions
 * row are degree_maps/admin/toolbar.php, rendered by the layout in the top slot.
 * Variables: $results (HTML), $flash (string|null)
 * @var \Majors\View\Layout $t
 */
?>

<?php if (!empty($flash)): ?>
<div class="<?= $t->cls('section.shade') ?> noprint"><p class="ma-flash"><?= $t->e($flash) ?></p></div>
<?php endif; ?>

<div class="<?= $t->cls('wrapper') ?> dm-page">
	<div id="search_results" class="<?= $t->cls('divided_list') ?> dm-results" aria-live="polite">
<?= $results ?>
	</div>
</div>
