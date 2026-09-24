<?php
/**
 * The ordered sections of a program (re-rendered after every change).
 * Variables: $program, $sections
 * @var \Majors\View\Layout $t
 */
?>
<ol id="sectionList" class="ma-sections" data-program-id="<?= (int) $program['id'] ?>">
<?php if ($sections === []): ?>
	<li class="ma-sections__empty">No sections yet. Add a card (Curriculum, Careers, Admission…) or a feature (Inside the Program).</li>
<?php endif; ?>
<?php foreach ($sections as $s): ?>
	<li class="ma-section" data-section-id="<?= (int) $s['id'] ?>">
		<span class="drag-handle" title="Drag to reorder">☰</span>
		<div class="ma-section__body">
			<div class="ma-section__meta"><span class="ma-section__kind"><?= $s['kind'] === 'feature' ? $t->e($s['label'] !== '' ? $s['label'] : 'Inside the Program') : ($s['kind'] === 'similar' ? 'Similar programs (from the import; managed below)' : 'Card') ?></span><?php if ($s['shared']): ?> <span class="ma-section__shared" title="Shared block: edit it under Shared blocks to change every page that uses it">shared block</span><?php endif; ?><?php if ($s['image']): ?> · image<?php endif; ?></div>
			<strong><?= $t->e($s['headline']) ?></strong>
			<div class="ma-section__excerpt"><?= $t->e(mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($s['body'])) ?? ''), 0, 160)) ?><?= mb_strlen(strip_tags($s['body'])) > 160 ? '…' : '' ?></div>
<?php if ($s['links']): ?>
			<div class="ma-section__links"><?php foreach ($s['links'] as $l): ?><span>→ <?= $t->e($l['text']) ?></span> <?php endforeach; ?></div>
<?php endif; ?>
		</div>
		<div class="ma-section__actions">
<?php if ($s['kind'] !== 'similar'): ?>
			<button type="button" class="<?= $t->cls('button.small') ?> edit-section" data-section-id="<?= (int) $s['id'] ?>"><?= $s['shared'] ? 'Swap block' : 'Edit' ?></button>
<?php if ($s['shared']): ?>
			<button type="button" class="<?= $t->cls('button.small') ?> detach-section" data-section-id="<?= (int) $s['id'] ?>" title="Copy the block's text into this page so it can be changed here only">Customize</button>
<?php endif; ?>
<?php endif; ?>
			<button type="button" class="<?= $t->cls('button.small') ?> ma-danger delete-section" data-section-id="<?= (int) $s['id'] ?>">Remove</button>
		</div>
	</li>
<?php endforeach; ?>
</ol>
