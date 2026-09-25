<?php
/**
 * In-place editor: "Add a section" bar after the last section (rendered only
 * when editing). It carries data-ma-part="content" so the content part exists
 * even on a page without sections.
 * Variables: $ed, $empty (bool: the page has no sections yet)
 * @var \Majors\View\Layout $t
 */
if (empty($ed) || !$ed->on) {
    return;
}
?>
<div class="ma-add-bar noprint" data-ma-part="content" data-ma-add-bar>
	<span class="ma-add-bar__label"><?= !empty($empty) ? 'This page has no sections yet. Add one:' : 'Add a section:' ?></span>
	<button type="button" class="ma-btn ma-btn--accent ma-btn--small" data-ma-act="add" data-kind="teaser" data-after="0">+ Card</button>
	<button type="button" class="ma-btn ma-btn--accent ma-btn--small" data-ma-act="add" data-kind="feature" data-after="0">+ Feature (Inside the Program)</button>
	<button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-act="add-shared" data-after="0">+ Shared text…</button>
</div>
