/* Shared-blocks page (_admin/blocks.php): the block dialog with CKEditor 5 classic on the text.
 * Program pages are edited in place by inplace.js. Talks to _admin/ajax.php?action=…; the CSRF token rides in the X-CSRF-Token header. */
(function ($) {
	'use strict';
	var cfg = window.MajorsAdmin || {};
	if (!cfg.ajax) { return; }
	$.ajaxSetup({ headers: { 'X-CSRF-Token': cfg.csrf } });
	var url = function (action) { return cfg.ajax + '?action=' + encodeURIComponent(action); };
	var editors = {};

	function fail(prefix, r, xhr) {
		var msg = (r && r.message) || (xhr && xhr.responseJSON && xhr.responseJSON.message) || (xhr && xhr.status ? 'HTTP ' + xhr.status : 'Request failed');
		if (xhr && xhr.status === 401) { alert('Your session has expired. Please sign in again.'); window.location.reload(); return; }
		alert(prefix + ': ' + msg);
	}
	function status($form, text, ok) {
		var $s = $form.find('.ma-save-status').first();
		$s.text(text).toggleClass('ma-danger', !ok);
		if (ok) { setTimeout(function () { $s.text(''); }, 4000); }
	}

	// ---- rich text (CKEditor 5 when it loaded; plain textarea otherwise) ----
	function enhance(scope) {
		if (!window.ClassicEditor) { return; }
		$(scope).find('textarea.ma-html').each(function () {
			var ta = this;
			if (ta._ck || editors[ta.id]) { return; }
			ta._ck = true;
			window.ClassicEditor.create(ta, {
				toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
				heading: { options: [{ model: 'paragraph', title: 'Paragraph' }, { model: 'heading3', view: 'h3', title: 'Heading' }] }
			}).then(function (ed) { editors[ta.id] = ed; }).catch(function () { ta._ck = false; });
		});
	}
	function syncEditors(scope) {
		$(scope).find('textarea.ma-html').each(function () { var ed = editors[this.id]; if (ed) { ed.updateSourceElement(); } });
	}
	function destroyEditors(scope) {
		$(scope).find('textarea.ma-html').each(function () { var ed = editors[this.id]; if (ed) { ed.destroy(); delete editors[this.id]; } });
	}

	// ---- repeatable link rows ----
	$(document).on('click', '.ma-link-add', function () {
		var $row = $(this).siblings('.ma-link-row').last().clone();
		$row.find('input').val('');
		$(this).before($row);
		$row.find('input').first().focus();
	});
	$(document).on('click', '.ma-link-remove', function () {
		var $rows = $(this).closest('.ma-links-edit').find('.ma-link-row');
		if ($rows.length > 1) { $(this).closest('.ma-link-row').remove(); } else { $(this).closest('.ma-link-row').find('input').val(''); }
	});

	// ---- dialogs ----
	function openDialog(id, html, width, onOpen) {
		$('.ui-dialog-content').each(function () { try { $(this).dialog('close'); } catch (e) { /* not a dialog */ } });
		$('#' + id).remove();
		$('body').append(html);
		$('#' + id).dialog({ modal: true, width: Math.min(width, window.innerWidth - 40), open: function () { enhance(this); if (onOpen) { onOpen.call(this); } },
			close: function () { destroyEditors(this); $(this).dialog('destroy').remove(); } });
	}
	$(document).on('click', '.cancelBtn', function (e) { e.preventDefault(); var id = $(this).data('modal'); if (id) { $('#' + id).dialog('close'); } });

	// ---- shared blocks page ----
	function openBlock(id) {
		$.ajax({ url: url('get_block_form'), method: 'POST', data: id ? { block_id: id } : {}, dataType: 'html' }).done(function (html) { openDialog('editBlockModal', html, 820); })
			.fail(function (xhr) { fail('Could not open the block', null, xhr); });
	}
	$('#newBlock').on('click', function () { openBlock(null); });
	$(document).on('click', '.edit-block', function () { openBlock($(this).data('block-id')); });
	$(document).on('submit', '#editBlockForm', function (e) {
		e.preventDefault();
		syncEditors(this);
		$.post(url('save_block'), $(this).serialize()).done(function (r) {
			if (r.success) { window.location.reload(); } else { fail('Could not save the block', r); }
		}).fail(function (xhr) { fail('Could not save the block', null, xhr); });
	});
	$(document).on('click', '.delete-block', function () {
		if (!confirm('Delete this block? (Only possible when no page uses it.)')) { return; }
		$.post(url('delete_block'), { block_id: $(this).data('block-id') }).done(function (r) {
			if (r.success) { window.location.reload(); } else { fail('Could not delete the block', r); }
		}).fail(function (xhr) { fail('Could not delete the block', null, xhr); });
	});
})(window.jQuery);
