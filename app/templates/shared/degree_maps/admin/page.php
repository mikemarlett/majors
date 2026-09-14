<?php
/**
 * Degree Maps admin page body.
 * Variables: $user, $csrf, $mode ('list'|'view'|'edit'), $map (header or null), $results (HTML), $order, $year, $years, $colleges, $college,
 *            $can_edit (bool), $can_clone (bool), $editable_year (bool), $self_url, $search_url, $flash (string|null)
 * @var \Majors\View\Layout $t
 */
?>
<?= $t->partial('partials/page_header', ['title' => 'Edit Degree Maps', 'noprint' => true]) ?>

<?php if (!empty($flash)): ?>
<div class="<?= $t->cls('section.shade') ?> noprint"><p class="ma-flash"><?= $t->e($flash) ?></p></div>
<?php endif; ?>

<section class="<?= $t->cls('section.shade') ?> noprint">
	<div class="<?= $t->cls('filters') ?> dm-filters" id="dm-filters" data-search-url="<?= $t->e($search_url) ?>" data-self-url="<?= $t->e($self_url) ?>" data-link-base="<?= $t->e($self_url) ?>?degree_map_id=">
		<form id="search_form" class="<?= $t->cls('filters.search') ?>" method="post" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="searchList">Search</label>
			<input id="searchList" name="searchList" type="search" placeholder="Search Degree Maps" autocomplete="off">
			<button type="submit">Search</button>
		</form>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selected_year">Catalog Year</label>
			<select id="selected_year" name="selected_year"><optgroup label="Catalog Year">
<?php foreach ($years as $y): ?>
				<option value="<?= (int) $y ?>"<?= (int) $y === (int) $year ? ' selected' : '' ?>><?= (int) $y - 1 ?> - <?= (int) $y ?><?= (int) $y > \Majors\DegreeMaps\MapRepository::currentAcademicYear() ? ' (editable)' : '' ?></option>
<?php endforeach; ?>
			</optgroup></select>
			<input type="hidden" name="order" value="<?= $t->e($order) ?>">
		</form>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selected_college">College</label>
			<select id="selected_college" name="selected_college"><optgroup label="College">
				<option value="all">All Colleges</option>
<?php foreach ($colleges as $c): ?>
				<option value="<?= $t->e($c) ?>"<?= $college === $c ? ' selected' : '' ?>><?= $t->e($c) ?></option>
<?php endforeach; ?>
			</optgroup></select>
			<input type="hidden" name="order" value="<?= $t->e($order) ?>">
			<input type="hidden" name="selected_year" value="<?= (int) $year ?>">
		</form>
	</div>
</section>

<section class="<?= $t->cls('section.actions') ?> noprint">
	<div class="<?= $t->cls('landing_panel.quick') ?>">
		<h2 class="<?= $t->cls('landing_panel.headline') ?>">Actions</h2>
		<form id="map_actions" class="<?= $t->cls('landing_panel.buttons') ?> ma-actions" method="post" action="<?= $t->e($self_url) ?>">
			<input type="hidden" name="_csrf" value="<?= $t->e($csrf ?? '') ?>">
			<input type="hidden" name="degree_map_id" value="<?= (int) ($map['id'] ?? 0) ?>">
			<button id="newMap" class="<?= $t->cls('button.accent') ?>" type="button">New Map</button>
<?php if ($map): ?>
<?php if ($mode === 'view' && $can_edit): ?>
			<button name="editMap" value="Edit" class="<?= $t->cls('button.accent') ?>" type="submit">Edit Map</button>
<?php elseif ($mode === 'edit'): ?>
			<button name="viewMap" value="View" class="<?= $t->cls('button.accent') ?>" type="submit">View Map</button>
<?php endif; ?>
<?php if (!$editable_year && $can_clone): ?>
			<button id="cloneMap" class="<?= $t->cls('button.accent') ?>" type="button" data-map-id="<?= (int) $map['id'] ?>">Clone to Next Year</button>
<?php endif; ?>
			<a class="<?= $t->cls('button') ?>" href="<?= $t->e($t->url('degree_maps/maps.php')) ?>?degree_map_id=<?= (int) $map['id'] ?>" target="_blank" rel="noopener">Public page</a>
<?php if ($user->isSuperAdmin()): ?>
			<button id="deleteMap" class="<?= $t->cls('button.subtle') ?> ma-danger" type="button" data-map-id="<?= (int) $map['id'] ?>" data-map-title="<?= $t->e($map['degree_type'] . ' in ' . $map['major'] . ' (' . $map['academic_year'] . ')') ?>">Delete Map</button>
<?php endif; ?>
<?php endif; ?>
		</form>
<?php if ($map && $mode === 'view' && !$can_edit): ?>
		<p class="help-block">
<?php if (!$editable_year): ?>
			Maps for the current and past catalog years are read-only. Clone this map to make next year's version.
<?php else: ?>
			This map belongs to another college; you can view it but not edit it.
<?php endif; ?>
		</p>
<?php endif; ?>
	</div>
</section>

<div class="<?= $t->cls('wrapper') ?> dm-page">
	<div id="search_results" class="<?= $t->cls('divided_list') ?> dm-results" aria-live="polite">
<?= $results ?>
	</div>
</div>
