<?php
/** Shared blocks page. Variables: $blocks, $csrf, $program_url @var \Majors\View\Layout $t */ ?>
<section class="<?= $t->cls('section') ?>">
	<p>Text that many program pages show word for word lives here once. Change a block and every page that uses it changes. Pages that need their own version use <em>Customize</em> on the section instead.</p>
	<p class="ma-links"><a href="<?= $t->e($t->url('_admin/index.php')) ?>">← All programs</a></p>
	<div class="ma-actions"><button type="button" class="<?= $t->cls('button.accent') ?>" id="newBlock">+ New block</button></div>
	<div class="<?= $t->cls('table_wrap') ?>">
		<table class="<?= $t->cls('table') ?> ma-users-table" id="blocks_table">
			<thead><tr><th scope="col">Headline</th><th scope="col">Name</th><th scope="col">Text</th><th scope="col">Used on</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead>
			<tbody>
<?php foreach ($blocks as $b): ?>
				<tr data-block-id="<?= (int) $b['id'] ?>">
					<td><strong><?= $t->e($b['headline']) ?></strong></td>
					<td><code><?= $t->e($b['slug']) ?></code></td>
					<td><?= $t->e(mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $b['body'])) ?? ''), 0, 120)) ?>…</td>
					<td><?= (int) $b['uses'] ?> page<?= (int) $b['uses'] === 1 ? '' : 's' ?></td>
					<td><?= $t->e(substr((string) ($b['updated_at'] ?? ''), 0, 10)) ?></td>
					<td><button type="button" class="<?= $t->cls('button.small') ?> edit-block" data-block-id="<?= (int) $b['id'] ?>">Edit</button> <?php if ((int) $b['uses'] === 0): ?><button type="button" class="<?= $t->cls('button.small') ?> ma-danger delete-block" data-block-id="<?= (int) $b['id'] ?>">Delete</button><?php endif; ?></td>
				</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</section>
