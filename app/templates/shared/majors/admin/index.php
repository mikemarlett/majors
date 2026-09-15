<?php
/**
 * Majors admin: program inventory.
 * Variables: $user, $csrf, $programs (adminList rows), $program_url
 * @var \Majors\View\Layout $t
 */
?>
<section class="<?= $t->cls('section') ?>">
	<p>Every academic program and the state of its marketing page. Filter the table below; click a program to preview its public page.
	The page editor is the next phase of this project — until then, content changes go through the web team.</p>
	<p><label for="programFilter" class="<?= $t->cls('sr_only') ?>">Filter programs</label>
	<input type="search" id="programFilter" placeholder="Filter by name, college, department…" autocomplete="off" style="max-width:24rem"> <span id="programCount" class="help-block" style="display:inline"></span></p>
	<div class="<?= $t->cls('table_wrap') ?>">
		<table class="<?= $t->cls('table') ?> ma-users-table" id="programs_table">
			<caption class="<?= $t->cls('sr_only') ?>">Academic programs</caption>
			<thead><tr>
				<th scope="col">Program</th><th scope="col">Type</th><th scope="col">College</th><th scope="col">Department</th>
				<th scope="col">Level</th><th scope="col">Online</th><th scope="col">Page content</th><th scope="col">Similar</th><th scope="col">Updated</th>
			</tr></thead>
			<tbody>
<?php foreach ($programs as $p): ?>
				<tr>
					<td><a href="<?= $t->e($program_url . (int) $p['id']) ?>" target="_blank" rel="noopener"><?= $t->e($p['academic_program']) ?></a><?php if (!empty($p['note'])): ?><br><small><?= $t->e($p['note']) ?></small><?php endif; ?></td>
					<td><?= $t->e($p['program_type']) ?></td>
					<td><?= $t->e($p['college']) ?></td>
					<td><?= $t->e($p['department']) ?></td>
					<td><?= !empty($p['graduate']) ? 'Graduate' : 'Undergraduate' ?></td>
					<td><?= !empty($p['online_only']) ? 'Online only' : (!empty($p['online_learning']) ? 'Available' : '') ?></td>
					<td><?php if (empty($p['content_id'])): ?><span class="ma-danger">none</span><?php else: ?><?= !empty($p['has_description']) ? 'text' : '<span class="ma-danger">no text</span>' ?>, <?= !empty($p['has_image']) ? 'image' : '<span class="ma-danger">no image</span>' ?><?php endif; ?></td>
					<td><?= (int) $p['similar_count'] ?></td>
					<td><?= $t->e($p['content_timestamp'] ? substr((string) $p['content_timestamp'], 0, 10) : ($p['timestamp'] ? substr((string) $p['timestamp'], 0, 10) : '')) ?></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
