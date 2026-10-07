<?php
/**
 * Degree Maps admin toolbar: the search/select bar and the Actions row, as two
 * full-bleed slabs (light, then dark) rendered by the layout in the top slot.
 * In list mode the controls filter the table on the client (map-list.js) and the
 * approval select appears; on a map's page the search goes to the server.
 * Variables: $user, $mode ('list'|'view'|'edit'), $map (header or null), $order, $year, $years, $colleges, $college,
 *            $status ('all'|'approved'|'pending'), $can_edit (bool), $can_clone (bool), $can_approve (bool),
 *            $editable_year (bool), $self_url, $search_url
 * @var \Majors\View\Layout $t
 */
use Majors\DegreeMaps\MapRepository;

ob_start();
?>
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
				<option value="<?= (int) $y ?>"<?= (int) $y === (int) $year ? ' selected' : '' ?>><?= (int) $y - 1 ?> - <?= (int) $y ?><?= (int) $y > MapRepository::currentAcademicYear() ? ' (editable)' : '' ?></option>
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
<?php if ($mode === 'list'): ?>
		<form class="<?= $t->cls('filters.select') ?>" method="get" action="<?= $t->e($self_url) ?>">
			<label class="<?= $t->cls('sr_only') ?>" for="selected_status">Approval</label>
			<select id="selected_status" name="selected_status"><optgroup label="Approval">
				<option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Approved or not</option>
				<option value="approved"<?= $status === 'approved' ? ' selected' : '' ?>>Approved (public)</option>
				<option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Not approved</option>
			</optgroup></select>
			<input type="hidden" name="selected_year" value="<?= (int) $year ?>">
		</form>
<?php endif; ?>
	</div>
<?php echo $t->partial('partials/slab', ['theme' => 'neutral-200', 'class' => 'noprint majors-slab--filters', 'body' => (string) ob_get_clean()]); ?>
<?php ob_start(); ?>
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
<?php $isApproved = MapRepository::isApproved($map); ?>
<?php if ($can_approve): ?>
			<button id="approveMap" class="<?= $t->cls('button') ?>" type="button" data-map-id="<?= (int) $map['id'] ?>" data-approved="<?= $isApproved ? 1 : 0 ?>"><?= $isApproved ? 'Withdraw from public site' : 'Approve for public site' ?></button>
<?php endif; ?>
			<a class="<?= $t->cls('button') ?>" id="publicPageLink" href="<?= $t->e($t->url('degree_maps/maps.php')) ?>?degree_map_id=<?= (int) $map['id'] ?>" target="_blank" rel="noopener"><?= $isApproved ? 'Public page' : 'Preview public page' ?></a>
<?php if ($user->isSuperAdmin()): ?>
			<button id="deleteMap" class="<?= $t->cls('button.subtle') ?> ma-danger" type="button" data-map-id="<?= (int) $map['id'] ?>" data-map-title="<?= $t->e($map['degree_type'] . ' in ' . $map['major'] . ' (' . $map['academic_year'] . ')') ?>">Delete Map</button>
<?php endif; ?>
<?php endif; ?>
		</form>
<?php if ($map): ?>
<?php
    $by   = trim((string) ($map['approved_by'] ?? ''));
    $at   = !empty($map['approved_at']) ? date('M j, Y', (int) strtotime((string) $map['approved_at'])) : '';
    $meta = trim(($by !== '' ? ' by ' . $by : '') . ($at !== '' ? ' on ' . $at : ''));
?>
		<p class="help-block ma-approval" id="map_approval" data-approved="<?= $isApproved ? 1 : 0 ?>">
<?php if ($isApproved): ?>
			<strong>Approved</strong><?= $meta !== '' ? ' ' . $t->e($meta) : '' ?>: this map is on the public site.
<?php if (MapRepository::changedSinceApproval($map)): ?> It has been saved again since, and the change is already public.<?php endif; ?>
<?php else: ?>
			<strong>Not approved</strong><?= $meta !== '' ? ' (withdrawn ' . $t->e($meta) . ')' : '' ?>: students cannot see this map. <?= $can_approve ? 'Approve it when it is ready.' : 'An advisor admin or a super admin approves it when it is ready; you can preview the public page meanwhile.' ?>
<?php endif; ?>
		</p>
<?php endif; ?>
<?php if ($map && $mode === 'view' && !$can_edit): ?>
		<p class="help-block">
<?php if (!$editable_year): ?>
			Maps for the current and past catalog years are published snapshots and are read-only. Clone this map to make next year's version; if a published map truly needs a correction, ask the web team.
<?php else: ?>
			This map belongs to another college; you can view it but not edit it.
<?php endif; ?>
		</p>
<?php elseif ($map && $mode === 'edit' && !$editable_year): ?>
		<p class="ma-flash ma-danger"><strong>Careful:</strong> this is a published map for a current or past catalog year. Only super admins can change it, and the change is visible to students immediately.</p>
<?php endif; ?>
	</div>
<?php echo $t->partial('partials/slab', ['theme' => 'neutral-900', 'class' => 'noprint majors-slab--actions', 'body' => (string) ob_get_clean()]); ?>
