<?php
/**
 * Section modal (new or existing). Variables: $program, $section|null, $blocks, $kinds, $default_kind
 * @var \Majors\View\Layout $t
 */
$s = $section ?? ['id' => 0, 'kind' => $default_kind, 'label' => '', 'headline' => '', 'body' => '', 'links' => [], 'image' => null, 'block_id' => null, 'shared' => false];
$isNew = empty($s['id']);
?>
<div id="editSectionModal" title="<?= $isNew ? 'New section' : 'Edit section' ?>">
	<form id="editSectionForm">
		<input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
		<input type="hidden" name="section_id" value="<?= (int) $s['id'] ?>">
		<div class="ma-row">
			<div class="ma-col-4"><label for="s_kind">Kind</label>
				<select id="s_kind" name="kind"><?php foreach ($kinds as $k): ?><option value="<?= $k ?>"<?= $s['kind'] === $k ? ' selected' : '' ?>><?= $k === 'teaser' ? 'Card (two across)' : 'Feature (full width, with photo)' ?></option><?php endforeach; ?></select></div>
			<div class="ma-col-8"><label for="s_block">Use a shared block</label>
				<select id="s_block" name="block_id"><option value="">— this page's own text —</option>
<?php foreach ($blocks as $b): ?>
					<option value="<?= (int) $b['id'] ?>"<?= (int) ($s['block_id'] ?? 0) === (int) $b['id'] ? ' selected' : '' ?>><?= $t->e($b['headline'] ?: $b['slug']) ?> (<?= (int) $b['uses'] ?> pages)</option>
<?php endforeach; ?>
				</select>
				<span class="help-block">Pick a block to show the same text as the other pages that use it. Leave it on "own text" to write for this page only.</span></div>
		</div>
		<div class="ma-own-text"<?= !empty($s['block_id']) ? ' hidden' : '' ?>>
			<div class="ma-row">
				<div class="ma-col-4 ma-feature-only"<?= $s['kind'] !== 'feature' ? ' hidden' : '' ?>><label for="s_label">Band label</label><input type="text" id="s_label" name="label" value="<?= $t->e($s['label']) ?>" maxlength="100" placeholder="Inside the Program"></div>
				<div class="ma-col-8"><label for="s_headline">Headline</label><input type="text" id="s_headline" name="headline" value="<?= $t->e($s['headline']) ?>" maxlength="255"></div>
			</div>
			<label for="s_body">Text</label>
			<textarea id="s_body" name="body" class="ma-html" rows="6"><?= $t->e($s['body']) ?></textarea>
			<label>Links</label>
			<div class="ma-links-edit">
<?php foreach (array_merge($s['links'], [['text' => '', 'href' => '']]) as $l): ?>
				<div class="ma-link-row"><input type="text" name="links[text][]" value="<?= $t->e($l['text'] ?? '') ?>" placeholder="Link text" maxlength="200"><input type="text" name="links[href][]" value="<?= $t->e($l['href'] ?? '') ?>" placeholder="https://… or /academics/…" maxlength="500"><button type="button" class="<?= $t->cls('button.small') ?> ma-link-remove">− Remove</button></div>
<?php endforeach; ?>
				<button type="button" class="<?= $t->cls('button.small') ?> ma-link-add">+ Add link</button>
			</div>
		</div>
		<div class="ma-row ma-feature-only"<?= $s['kind'] !== 'feature' ? ' hidden' : '' ?>>
			<div class="ma-col-6"><label for="s_img">Photo (URL)</label><input type="text" id="s_img" name="image_url" value="<?= $t->e($s['image']['url'] ?? '') ?>" maxlength="255"></div>
			<div class="ma-col-6"><label for="s_img_alt">Photo alt text</label><input type="text" id="s_img_alt" name="image_alt" value="<?= $t->e($s['image']['alt'] ?? '') ?>"></div>
		</div>
		<div class="modal-buttons">
			<button type="submit" class="<?= $t->cls('button') ?>">Save section</button>
			<button type="button" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editSectionModal">Cancel</button>
		</div>
	</form>
</div>
