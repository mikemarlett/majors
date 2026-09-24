<?php
/**
 * Majors admin: program inventory.
 * Variables: $user, $csrf, $programs (adminList rows), $program_url
 * @var \Majors\View\Layout $t
 */
?>
<section class="<?= $t->cls('section') ?>">
	<p>Every academic program and the state of its page. Filter the table; <strong>Edit</strong> opens the editor, the name previews the public page.</p>
	<div class="ma-actions"><a class="<?= $t->cls('button.accent') ?>" href="<?= $t->e($t->url('_admin/program.php')) ?>?new=1">+ New program</a> <a class="<?= $t->cls('button') ?>" href="<?= $t->e($t->url('_admin/blocks.php')) ?>">Shared blocks</a></div>
	<p><label for="programFilter" class="<?= $t->cls('sr_only') ?>">Filter programs</label>
	<input type="search" id="programFilter" placeholder="Filter by name, college, department…" autocomplete="off" style="max-width:24rem"> <span id="programCount" class="help-block" style="display:inline"></span></p>
	<div class="<?= $t->cls('table_wrap') ?>">
		<table class="<?= $t->cls('table') ?> ma-users-table" id="programs_table">
			<caption class="<?= $t->cls('sr_only') ?>">Academic programs</caption>
			<thead><tr>
				<th scope="col">Program</th><th scope="col">Type</th><th scope="col">College</th><th scope="col">Department</th>
				<th scope="col">Level</th><th scope="col">Status</th><th scope="col">Sections</th><th scope="col">Similar</th><th scope="col">Updated</th><th scope="col"></th>
			</tr></thead>
			<tbody>
<?php foreach ($programs as $p): ?>
				<tr>
					<td><a href="<?= $t->e($program_url . (int) $p['id']) ?>" target="_blank" rel="noopener"><?= $t->e($p['academic_program']) ?></a><?php if (!empty($p['note'])): ?><br><small><?= $t->e($p['note']) ?></small><?php endif; ?></td>
					<td><?= $t->e($p['program_type']) ?></td>
					<td><?= $t->e($p['college']) ?></td>
					<td><?= $t->e($p['department']) ?></td>
					<td><?= !empty($p['graduate']) ? 'Graduate' : 'Undergraduate' ?></td>
					<td><?= ($p['status'] ?? 'active') === 'retired' ? '<span class="ma-danger">retired</span>' : 'active' ?></td>
					<td><?= (int) ($p['section_count'] ?? 0) ?><?= empty($p['has_description']) ? ' <span class="ma-danger">no text</span>' : '' ?><?= empty($p['has_image']) ? ' <span class="ma-warning">no photo</span>' : '' ?></td>
					<td><?= (int) $p['similar_count'] ?></td>
					<td><?= $t->e(substr((string) ($p['timestamp'] ?? ''), 0, 10)) ?></td>
					<td><a class="<?= $t->cls('button.small') ?>" href="<?= $t->e($t->url('_admin/program.php')) ?>?id=<?= (int) $p['id'] ?>">Edit</a></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
