<?php
/**
 * In-place editor: the tool strip on one section (rendered only when editing).
 * Variables: $s (section), $ed (\Majors\Majors\EditMarks)
 * @var \Majors\View\Layout $t
 */
if (empty($ed) || !$ed->on) {
    return;
}
$shared = !empty($s['block_id']);
$uses   = $shared ? $ed->uses((int) $s['block_id']) : 0;
$kind   = $s['kind'] === 'feature' ? 'Feature' : 'Card';
?>
<div class="ma-tools noprint" data-ma-tools data-ma-section="<?= (int) $s['id'] ?>">
	<span class="ma-tools__kind"><?= $t->e($kind) ?></span>
<?php if ($shared): ?>
	<span class="ma-tools__shared" title="This text comes from a shared block. Editing it changes every page that uses it; Customize gives this page its own copy.">Shared text · <?= (int) $uses ?> page<?= $uses === 1 ? '' : 's' ?></span>
<?php endif; ?>
	<span class="ma-tools__spacer"></span>
	<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="move" data-dir="up" title="Move up" aria-label="Move this section up"<?= !empty($s['first']) ? ' disabled' : '' ?>>↑</button>
	<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="move" data-dir="down" title="Move down" aria-label="Move this section down"<?= !empty($s['last']) ? ' disabled' : '' ?>>↓</button>
	<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="add-after" title="Add a section after this one">+ Add after</button>
	<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="section-menu" title="More" aria-label="More actions for this section">⋯</button>
</div>
