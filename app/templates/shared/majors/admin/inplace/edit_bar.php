<?php
/**
 * In-place editor: the sticky strip under the admin bar.
 * Variables: $program, $public_url, $blocks_url, $index_url
 * @var \Majors\View\Layout $t
 */
$p = $program;
?>
<div class="ma-edit-bar noprint" data-ma-edit-bar>
	<div class="ma-edit-bar__inner">
		<span class="ma-edit-bar__what">Editing <strong data-ma-title><?= $t->e(\Majors\Majors\ProgramRenderer::title($p)) ?></strong><?php if (($p['status'] ?? 'active') === 'retired'): ?> <span class="ma-edit-bar__retired" data-ma-retired>retired — not listed publicly</span><?php else: ?> <span class="ma-edit-bar__retired" data-ma-retired hidden>retired — not listed publicly</span><?php endif; ?></span>
		<span class="ma-edit-bar__hint">Click anything on the page to change it. Changes save as you go.</span>
		<span class="ma-edit-bar__status" data-ma-status aria-live="polite"></span>
		<nav class="ma-edit-bar__nav" aria-label="Editor">
			<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="settings">Page settings</button>
			<a class="ma-btn ma-btn--ghost ma-btn--small" href="<?= $t->e($public_url) ?>" target="_blank" rel="noopener">View public page</a>
			<a class="ma-btn ma-btn--ghost ma-btn--small" href="<?= $t->e($blocks_url) ?>">Shared blocks</a>
			<a class="ma-btn ma-btn--ghost ma-btn--small" href="<?= $t->e($index_url) ?>">All programs</a>
		</nav>
	</div>
</div>
