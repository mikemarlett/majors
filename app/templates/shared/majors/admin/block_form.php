<?php
/**
 * Shared block modal. Variables: $block|null, $users (programs using it), $program_url
 * @var \Majors\View\Layout $t
 */
$b = $block ?? ['id' => 0, 'slug' => '', 'headline' => '', 'body' => '', 'links' => '[]', 'note' => ''];
$links = json_decode((string) ($b['links'] ?? '[]'), true) ?: [];
?>
<div id="editBlockModal" title="<?= empty($b['id']) ? 'New shared block' : 'Edit shared block' ?>">
	<form id="editBlockForm">
		<input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>">
<?php if ($users): ?>
		<p class="ma-warning">Used on <?= count($users) ?> page<?= count($users) === 1 ? '' : 's' ?> — saving changes all of them:
			<?php foreach (array_slice($users, 0, 12) as $u): ?><a href="<?= $t->e($program_url . $u['id']) ?>" target="_blank" rel="noopener"><?= $t->e($u['academic_program']) ?></a>, <?php endforeach; ?><?= count($users) > 12 ? '…' : '' ?></p>
<?php endif; ?>
		<div class="ma-row">
			<div class="ma-col-8"><label for="b_headline">Headline</label><input type="text" id="b_headline" name="headline" value="<?= $t->e($b['headline']) ?>" maxlength="255"></div>
			<div class="ma-col-4"><label for="b_slug">Name (for the picker)</label><input type="text" id="b_slug" name="slug" value="<?= $t->e($b['slug']) ?>" maxlength="80" placeholder="from the headline if blank"></div>
		</div>
		<label for="b_body">Text</label>
		<textarea id="b_body" name="body" class="ma-html" rows="6"><?= $t->e($b['body']) ?></textarea>
		<label>Links</label>
		<div class="ma-links-edit">
<?php foreach (array_merge($links, [['text' => '', 'href' => '']]) as $l): ?>
			<div class="ma-link-row"><input type="text" name="links[text][]" value="<?= $t->e($l['text'] ?? '') ?>" placeholder="Link text" maxlength="200"><input type="text" name="links[href][]" value="<?= $t->e($l['href'] ?? '') ?>" placeholder="https://…" maxlength="500"><button type="button" class="<?= $t->cls('button.small') ?> ma-link-remove">− Remove</button></div>
<?php endforeach; ?>
			<button type="button" class="<?= $t->cls('button.small') ?> ma-link-add">+ Add link</button>
		</div>
		<label for="b_note">Note (for editors)</label>
		<input type="text" id="b_note" name="note" value="<?= $t->e($b['note'] ?? '') ?>" maxlength="255">
		<div class="modal-buttons">
			<button type="submit" class="<?= $t->cls('button.accent') ?>">Save block</button>
			<button type="button" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editBlockModal">Cancel</button>
		</div>
	</form>
</div>
