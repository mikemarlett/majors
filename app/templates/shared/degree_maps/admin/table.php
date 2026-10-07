<?php
/**
 * Degree Maps admin listing: every map of one catalog year in a sortable, filterable
 * table with its approval state. Advisor admins and super admins get checkboxes and
 * the Approve / Withdraw bulk actions (map-list.js). The toolbar's search box,
 * college select and approval select filter the rows on the client; each row carries
 * what the script filters and sorts on as data-* attributes, never cell text.
 *
 * Variables: $rows (header rows + can_edit, duplicate), $user, $year, $can_approve (bool),
 *            $college (initial college filter or null), $status ('all'|'approved'|'pending'),
 *            $view_base (admin url prefix taking the map id), $public_base (public viewer prefix)
 * @var \Majors\View\Layout $t
 */
use Majors\DegreeMaps\MapRepository;

$total    = count($rows);
$approved = count(array_filter($rows, [MapRepository::class, 'isApproved']));
$label    = ($year - 1) . '-' . $year;
$when     = static fn (?string $ts): string => $ts ? date('M j, Y', (int) strtotime($ts)) : '';
$sortable = static fn (string $key, string $label, string $type = 'text'): string =>
    '<button type="button" class="ma-sort" data-sort="' . $key . '" data-type="' . $type . '">' . $label . '</button>';
?>
<div class="ma-maps" id="maps_panel" data-year="<?= (int) $year ?>" data-college="<?= $t->e($college ?? '') ?>" data-status="<?= $t->e($status) ?>" data-can-approve="<?= $can_approve ? 1 : 0 ?>">
	<div class="ma-bulk" id="maps_bulk">
		<span class="ma-panel-count" id="maps_count" aria-live="polite" data-total="<?= $total ?>" data-approved="<?= $approved ?>"><?= $total ?> map<?= $total === 1 ? '' : 's' ?> for <?= $label ?>, <?= $approved ?> approved</span>
<?php if ($can_approve): ?>
		<span class="ma-bulk__selected" id="maps_selected" aria-live="polite">0 selected</span>
		<button type="button" id="bulkApprove" class="<?= $t->cls('button.small') ?>" disabled>Approve selected</button>
		<button type="button" id="bulkWithdraw" class="<?= $t->cls('button.small') ?> ma-bulk__withdraw" disabled>Withdraw selected</button>
		<span class="ma-bulk__hint">Approved maps are on the public site. Tick the maps, or the box in the heading for every map shown.</span>
<?php else: ?>
		<span class="ma-bulk__hint">Approved maps are on the public site; an advisor admin approves the rest when they are ready.</span>
<?php endif; ?>
	</div>
	<div class="<?= $t->cls('table_wrap') ?> ma-panel-wrap">
		<table class="<?= $t->cls('table') ?> ma-panel-table ma-maps-table" id="maps_table">
			<caption class="<?= $t->cls('sr_only') ?>">Degree maps for <?= $label ?></caption>
			<thead><tr>
<?php if ($can_approve): ?>
				<th scope="col" class="ma-cell-check"><input type="checkbox" id="maps_select_all" aria-label="Select every map shown"></th>
<?php endif; ?>
				<th scope="col" aria-sort="ascending"><?= $sortable('name', 'Degree map') ?></th>
				<th scope="col"><?= $sortable('type', 'Degree') ?></th>
				<th scope="col"><?= $sortable('college', 'College') ?></th>
				<th scope="col"><?= $sortable('status', 'Status') ?></th>
				<th scope="col"><?= $sortable('saved', 'Saved', 'date') ?></th>
				<th scope="col"><span class="<?= $t->cls('sr_only') ?>">Actions</span></th>
			</tr></thead>
			<tbody>
<?php if ($rows === []): ?>
				<tr class="ma-maps__empty"><td colspan="<?= $can_approve ? 7 : 6 ?>">No degree maps for <?= $label ?> yet. Clone last year's maps forward, or use New Map.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $m):
    $id        = (int) $m['id'];
    $isApproved = MapRepository::isApproved($m);
    $changed   = MapRepository::changedSinceApproval($m);
    $by        = trim((string) ($m['approved_by'] ?? ''));
    $at        = $when($m['approved_at'] ?? null);
    $meta      = trim(($by !== '' ? 'by ' . $by : '') . ($at !== '' ? ($by !== '' ? ', ' : '') . $at : ''));
    $saved     = substr((string) ($m['timestamp'] ?? ''), 0, 10);
    $dept      = trim((string) ($m['department'] ?? ''));
    $search    = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', implode(' ', [
        (string) $m['major'], (string) $m['degree_type'], (string) $m['college'], $dept, (string) ($m['note'] ?? ''),
    ]))));
?>
				<tr data-id="<?= $id ?>"
					data-name="<?= $t->e(mb_strtolower((string) $m['major'])) ?>"
					data-title="<?= $t->e($m['degree_type'] . ' in ' . $m['major']) ?>"
					data-type="<?= $t->e($m['degree_type']) ?>"
					data-college="<?= $t->e($m['college']) ?>"
					data-status="<?= $isApproved ? 'approved' : 'pending' ?>"
					data-changed="<?= $changed ? 1 : 0 ?>"
					data-saved="<?= $t->e($saved) ?>"
					data-search="<?= $t->e($search) ?>">
<?php if ($can_approve): ?>
					<td class="ma-cell-check"><input type="checkbox" class="ma-row-check" value="<?= $id ?>" aria-label="Select <?= $t->e($m['degree_type'] . ' in ' . $m['major']) ?>"></td>
<?php endif; ?>
					<td class="ma-cell-program"><a href="<?= $t->e($view_base . $id) ?>"><?= $t->e($m['major']) ?></a><?php
                        if (!empty($m['duplicate'])): ?> <span class="dm-flag" title="Another map with the same name, degree type and college exists for this catalog year (id <?= $id ?>)">duplicate</span><?php endif;
                        if (!empty($m['note'])): ?><span class="ma-sub ma-sub--note"><?= $t->e(mb_substr((string) $m['note'], 0, 90)) ?><?= mb_strlen((string) $m['note']) > 90 ? '…' : '' ?></span><?php endif; ?></td>
					<td class="ma-cell-type"><?= $t->e($m['degree_type']) ?></td>
					<td class="ma-cell-college"><?= $t->e($m['college']) ?><?php if ($dept !== ''): ?><span class="ma-sub"><?= $t->e($dept) ?></span><?php endif; ?></td>
					<td class="ma-cell-status"><span class="ma-state <?= $isApproved ? 'ma-state--approved' : 'ma-state--pending' ?>"><?= $isApproved ? 'Approved' : 'Not approved' ?></span><?php
                        if ($meta !== ''): ?><span class="ma-sub"><?= $isApproved ? '' : 'withdrawn ' ?><?= $t->e($meta) ?></span><?php endif;
                        if ($changed): ?><span class="ma-issue ma-issue--warn" title="Saved again after it was approved; the change is already public">edited since approval</span><?php endif; ?></td>
					<td class="ma-cell-updated"><?= $t->e($saved) ?></td>
					<td class="ma-cell-edit"><?php if (!empty($m['can_edit'])): ?><a class="<?= $t->cls('button.small') ?>" href="<?= $t->e($view_base . $id . '&editMap=Edit') ?>">Edit</a> <?php endif; ?><a class="ma-maps__preview" href="<?= $t->e($public_base . $id) ?>" target="_blank" rel="noopener"><?= $isApproved ? 'Public page' : 'Preview' ?></a></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
