<?php
/**
 * Footnotes modal: sortable list of textareas. Order is taken from the list order on save.
 * Variables: $map, $footnotes (list)
 * @var \Majors\View\Layout $t
 */
$grip  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path fill-rule="evenodd" d="M6.97 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.25 4.81V16.5a.75.75 0 0 1-1.5 0V4.81L3.53 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5Zm9.53 4.28a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V7.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"/></svg>';
$trash = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd"/></svg>';
$plus  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="ma-icon" aria-hidden="true"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 9a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25V15a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V9Z" clip-rule="evenodd"/></svg>';
?>
<div id="editFootnotesModal" title="Edit Degree Footnotes">
	<form method="post" id="degree_footnotes_form" name="degree_footnotes_form">
		<input type="hidden" name="degree_map_id" value="<?= (int) $map['id'] ?>">
		<p class="help-block">Drag to renumber. A footnote with order 0 prints as an un-numbered "Note". Footnotes are attached to courses in each course's editor.</p>
		<ul id="footnotesContainer" class="footnotes-list">
<?php foreach ($footnotes as $f): ?>
			<li class="footnote-container" data-footnote-id="<?= (int) $f['id'] ?>" data-order="<?= (int) $f['order'] ?>">
				<span class="drag-handle" title="Drag to reorder"><?= $grip ?></span>
				<div class="content">
					<input type="hidden" name="footnotes[<?= (int) $f['id'] ?>][id]" value="<?= (int) $f['id'] ?>">
					<label class="sr-only" for="footnote_<?= (int) $f['id'] ?>_note">Footnote <?= (int) $f['order'] ?></label>
					<textarea class="footnote-note" name="footnotes[<?= (int) $f['id'] ?>][note]" id="footnote_<?= (int) $f['id'] ?>_note" rows="2"><?= $t->e($f['note']) ?></textarea>
				</div>
				<a role="button" href="#" title="Remove footnote <?= (int) $f['order'] ?>" class="remove-footnote-btn <?= $t->cls('button') ?>" data-footnote-id="<?= (int) $f['id'] ?>"><?= $trash ?><span class="sr-only">Remove</span></a>
			</li>
<?php endforeach; ?>
		</ul>
		<div class="new-footnote-button-area" data-degree-map-id="<?= (int) $map['id'] ?>">
			<a href="#" id="addFootnoteBtn" class="<?= $t->cls('button') ?> new_footnote" title="Add new footnote"><?= $plus ?><span class="sr-only">Add</span></a>
			<div class="content">Add New Footnote</div>
		</div>
		<div class="modal-buttons">
			<button type="button" id="SaveFootnotesBtn" class="ui-button-primary <?= $t->cls('button') ?>">Save Footnotes</button>
			<button type="button" class="<?= $t->cls('button') ?> cancelBtn" data-modal="editFootnotesModal">Cancel</button>
		</div>
	</form>
</div>
