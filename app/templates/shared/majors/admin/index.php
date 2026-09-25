<?php
/**
 * Majors control panel: every program in one sortable, filterable table.
 * Status and similar programs are editable right here (majors-admin.js);
 * Edit opens the in-place editor, the name previews the public page.
 *
 * Every row carries what the JS sorts and filters on as data-* attributes,
 * so the script never parses cell text.
 *
 * Variables: $user, $csrf, $programs (adminList rows), $credentials, $colleges
 * @var \Majors\View\Layout $t
 */
$flagLabels = [
    'online_learning' => ['online', 'Online'],
    'online_only'     => ['online_only', 'Online only'],
    'is_stem'         => ['stem', 'STEM'],
    'minor'           => ['minor', 'Minor'],
    'certificate'     => ['certificate', 'Cert'],
    'badge'           => ['badge', 'Badge'],
];
$attention = [
    ''               => 'Everything',
    'no_description' => 'No description',
    'no_image'       => 'No photo',
    'no_sections'    => 'No sections',
    'no_similar'     => 'No similar programs',
    'online'         => 'Online',
];
$total  = count($programs);
$active = count(array_filter($programs, static fn ($p) => ($p['status'] ?? 'active') !== 'retired'));
$sortable = static fn (string $key, string $label, string $type = 'text'): string =>
    '<button type="button" class="ma-sort" data-sort="' . $key . '" data-type="' . $type . '">' . $label . '</button>';
?>
<section class="<?= $t->cls('section') ?>">
	<p>Every academic program and the state of its page. Sort and filter the table; <strong>Edit</strong> opens the page editor, the name previews the public page. Status and similar programs can be changed right here.</p>
	<div class="ma-actions"><a class="<?= $t->cls('button.accent') ?>" href="<?= $t->e($t->url('_admin/program.php')) ?>?new=1">+ New program</a> <a class="<?= $t->cls('button') ?>" href="<?= $t->e($t->url('_admin/blocks.php')) ?>">Shared blocks</a></div>

	<div class="ma-panel-toolbar" id="panel_toolbar" role="group" aria-label="Filter programs">
		<label class="ma-panel-toolbar__field ma-panel-toolbar__search"><span>Search</span>
			<input type="search" id="panel_q" data-filter="q" placeholder="Name, college, department, note…" autocomplete="off"></label>
		<label class="ma-panel-toolbar__field"><span>Level</span>
			<select data-filter="level"><option value="">All</option><option value="undergrad">Undergraduate</option><option value="graduate">Graduate</option></select></label>
		<label class="ma-panel-toolbar__field"><span>Credential</span>
			<select data-filter="credential"><option value="">All</option><?php foreach ($credentials as $c): ?><option value="<?= $t->e($c) ?>"><?= $t->e($c) ?></option><?php endforeach; ?></select></label>
		<label class="ma-panel-toolbar__field"><span>College</span>
			<select data-filter="college"><option value="">All</option><?php foreach ($colleges as $c): ?><option value="<?= $t->e($c) ?>"><?= $t->e($c) ?></option><?php endforeach; ?></select></label>
		<label class="ma-panel-toolbar__field"><span>Status</span>
			<select data-filter="status"><option value="active" selected>Active</option><option value="retired">Retired</option><option value="all">All</option></select></label>
		<label class="ma-panel-toolbar__field"><span>Needs attention</span>
			<select data-filter="attention"><?php foreach ($attention as $v => $label): ?><option value="<?= $t->e($v) ?>"><?= $t->e($label) ?></option><?php endforeach; ?></select></label>
		<div class="ma-panel-toolbar__meta"><span class="ma-panel-count" id="panel_count" aria-live="polite"><?= $active ?> of <?= $total ?> programs</span> <button type="button" class="ma-panel-reset" id="panel_reset">Reset</button></div>
	</div>

	<div class="<?= $t->cls('table_wrap') ?>">
		<table class="<?= $t->cls('table') ?> ma-panel-table" id="programs_table">
			<caption class="<?= $t->cls('sr_only') ?>">Academic programs</caption>
			<thead><tr>
				<th scope="col" aria-sort="ascending"><?= $sortable('name', 'Program') ?></th>
				<th scope="col"><?= $sortable('credential', 'Credential') ?></th>
				<th scope="col"><?= $sortable('type', 'Type') ?></th>
				<th scope="col"><?= $sortable('college', 'College') ?></th>
				<th scope="col"><?= $sortable('department', 'Department') ?></th>
				<th scope="col"><?= $sortable('level', 'Level') ?></th>
				<th scope="col">Flags</th>
				<th scope="col"><?= $sortable('sections', 'Sections', 'number') ?></th>
				<th scope="col"><?= $sortable('similar', 'Similar', 'number') ?></th>
				<th scope="col"><?= $sortable('status', 'Status') ?></th>
				<th scope="col"><?= $sortable('updated', 'Updated', 'date') ?></th>
				<th scope="col"><span class="<?= $t->cls('sr_only') ?>">Actions</span></th>
			</tr></thead>
			<tbody>
<?php foreach ($programs as $p):
    $name      = (string) $p['academic_program'];
    $sortTitle = trim((string) ($p['sort_title'] ?? ''));
    $note      = trim((string) ($p['note'] ?? ''));
    $retired   = ($p['status'] ?? 'active') === 'retired';
    $graduate  = !empty($p['graduate']);
    $updated   = substr((string) ($p['timestamp'] ?? ''), 0, 10);
    $sections  = (int) ($p['section_count'] ?? 0);
    $similar   = array_values(array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => (string) $s['name']], $p['similar'] ?? []));
    $flags     = [];
    foreach ($flagLabels as $col => [$token, $label]) {
        if (!empty($p[$col])) {
            $flags[$token] = $label;
        }
    }
    $search = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', implode(' ', [
        $name, $sortTitle, (string) ($p['basename'] ?? ''), (string) ($p['college'] ?? ''), (string) ($p['department'] ?? ''),
        (string) ($p['credential'] ?? ''), (string) ($p['program_type'] ?? ''), $note,
    ]))));
?>
				<tr<?= $retired ? ' class="is-retired" hidden' : '' ?> data-id="<?= (int) $p['id'] ?>"
					data-name="<?= $t->e(mb_strtolower($sortTitle !== '' ? $sortTitle : $name)) ?>"
					data-title="<?= $t->e($name) ?>"
					data-credential="<?= $t->e($p['credential'] ?? '') ?>"
					data-type="<?= $t->e($p['program_type'] ?? '') ?>"
					data-college="<?= $t->e($p['college'] ?? '') ?>"
					data-department="<?= $t->e($p['department'] ?? '') ?>"
					data-level="<?= $graduate ? 'graduate' : 'undergrad' ?>"
					data-status="<?= $retired ? 'retired' : 'active' ?>"
					data-sections="<?= $sections ?>"
					data-similar="<?= count($similar) ?>"
					data-similar-json="<?= $t->e(json_encode($similar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
					data-updated="<?= $t->e($updated) ?>"
					data-flags="<?= $t->e(implode(' ', array_keys($flags))) ?>"
					data-has-description="<?= empty($p['has_description']) ? 0 : 1 ?>"
					data-has-image="<?= empty($p['has_image']) ? 0 : 1 ?>"
					data-search="<?= $t->e($search) ?>">
					<td class="ma-col-program"><a href="<?= $t->e($t->programUrl($p)) ?>" target="_blank" rel="noopener"><?= $t->e($name) ?></a><?php
                        if ($sortTitle !== '' && $sortTitle !== $name): ?><span class="ma-program__sub"><?= $t->e($sortTitle) ?></span><?php endif;
                        if ($note !== ''): ?><span class="ma-program__sub ma-program__note"><?= $t->e($note) ?></span><?php endif; ?></td>
					<td><?= $t->e($p['credential'] ?? '') ?></td>
					<td><?= $t->e($p['program_type'] ?? '') ?></td>
					<td><?= $t->e($p['college'] ?? '') ?></td>
					<td><?= $t->e($p['department'] ?? '') ?></td>
					<td><?= $graduate ? 'Graduate' : 'Undergraduate' ?></td>
					<td><?php if ($flags): ?><span class="ma-flag-list"><?php foreach ($flags as $token => $label): ?><span class="ma-flag ma-flag--<?= $t->e($token) ?>"><?= $t->e($label) ?></span><?php endforeach; ?></span><?php endif; ?></td>
					<td class="ma-col-sections"><?= $sections ?><?= empty($p['has_description']) ? '<span class="ma-danger">no text</span>' : '' ?><?= empty($p['has_image']) ? '<span class="ma-warning">no photo</span>' : '' ?></td>
					<td><button type="button" class="ma-similar-btn<?= $similar ? '' : ' is-empty' ?>" data-similar-btn aria-label="Similar programs: <?= count($similar) ?>" title="Edit similar programs"><?= count($similar) ?></button></td>
					<td class="ma-col-status"><select class="ma-status-select" data-status-select aria-label="Status of <?= $t->e($name) ?>"><option value="active"<?= $retired ? '' : ' selected' ?>>Active</option><option value="retired"<?= $retired ? ' selected' : '' ?>>Retired</option></select></td>
					<td><?= $t->e($updated) ?></td>
					<td class="ma-col-edit"><a class="<?= $t->cls('button.small') ?>" href="<?= $t->e($t->editUrl($p)) ?>">Edit</a></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
