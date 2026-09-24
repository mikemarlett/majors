/* Majors editor: program form, ordered sections (with shared blocks), similar programs, shared-blocks page.
 * Talks to _admin/ajax.php?action=…; the CSRF token rides in the X-CSRF-Token header. */
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

	// ---- new program ----
	$('#newProgramForm').on('submit', function (e) {
		e.preventDefault();
		$.post(url('new_program'), $(this).serialize()).done(function (r) {
			if (r.success && r.redirect) { window.location = r.redirect; } else { fail('Could not create the program', r); }
		}).fail(function (xhr) { fail('Could not create the program', null, xhr); });
	});

	// ---- program form ----
	var $pf = $('#programForm');
	if ($pf.length) {
		enhance($pf);
		$pf.on('submit', function (e) {
			e.preventDefault();
			syncEditors($pf);
			status($pf, 'Saving…', true);
			$.post(url('save_program'), $pf.serialize()).done(function (r) {
				if (r.success) { status($pf, 'Saved.', true); } else { status($pf, r.message || 'Not saved', false); }
			}).fail(function (xhr) { status($pf, (xhr.responseJSON && xhr.responseJSON.message) || 'Not saved (HTTP ' + xhr.status + ')', false); });
		});
	}

	// ---- sections ----
	var programId = $('#sectionList').data('program-id') || $pf.data('program-id');
	function initSortable() {
		if (!$.fn.sortable) { return; }
		$('#sectionList').sortable({ handle: '.drag-handle', axis: 'y', placeholder: 'sortable-placeholder', update: function () {
			var order = $('#sectionList .ma-section').map(function () { return $(this).data('section-id'); }).get();
			$.post(url('save_section_order'), { program_id: programId, order: order }).fail(function (xhr) { fail('Could not save the order', null, xhr); });
		} });
	}
	function replaceSections(html) { $('#sectionsWrap').html(html); initSortable(); }
	initSortable();

	function openSection(data) {
		$.ajax({ url: url('get_section_form'), method: 'POST', data: $.extend({ program_id: programId }, data), dataType: 'html' }).done(function (html) {
			openDialog('editSectionModal', html, 820, function () {
				var $m = $(this);
				function refresh() {
					var shared = $m.find('#s_block').val() !== '';
					$m.find('.ma-own-text').prop('hidden', shared);
					$m.find('.ma-feature-only').prop('hidden', $m.find('#s_kind').val() !== 'feature');
				}
				$m.find('#s_block, #s_kind').on('change', refresh);
				refresh();
			});
		}).fail(function (xhr) { fail('Could not open the section', null, xhr); });
	}
	$(document).on('click', '.add-section', function () { openSection({ kind: $(this).data('kind') }); });
	$(document).on('click', '.edit-section', function () { openSection({ section_id: $(this).data('section-id') }); });
	$(document).on('submit', '#editSectionForm', function (e) {
		e.preventDefault();
		syncEditors(this);
		$.post(url('save_section'), $(this).serialize()).done(function (r) {
			if (r.success) { $('#editSectionModal').dialog('close'); replaceSections(r.html); } else { fail('Could not save the section', r); }
		}).fail(function (xhr) { fail('Could not save the section', null, xhr); });
	});
	$(document).on('click', '.delete-section', function () {
		if (!confirm('Remove this section from the page?')) { return; }
		$.post(url('delete_section'), { program_id: programId, section_id: $(this).data('section-id') }).done(function (r) {
			if (r.success) { replaceSections(r.html); } else { fail('Could not remove the section', r); }
		}).fail(function (xhr) { fail('Could not remove the section', null, xhr); });
	});
	$(document).on('click', '.detach-section', function () {
		if (!confirm('Copy the shared text into this page so it can be edited here? Later changes to the shared block will no longer reach this page.')) { return; }
		$.post(url('detach_section'), { program_id: programId, section_id: $(this).data('section-id') }).done(function (r) {
			if (r.success) { replaceSections(r.html); } else { fail('Could not customize the section', r); }
		}).fail(function (xhr) { fail('Could not customize the section', null, xhr); });
	});

	// ---- similar programs ----
	var $sim = $('#f_similar');
	if ($sim.length && $.fn.select2) {
		$sim.select2({ width: '100%', minimumInputLength: 2, ajax: { url: cfg.search, dataType: 'json', delay: 200,
			data: function (params) { return { q: params.term }; }, processResults: function (r) { return { results: r.results || [] }; } } });
	}
	$('#similarForm').on('submit', function (e) {
		e.preventDefault();
		var $f = $(this);
		$.post(url('save_similar'), $f.serialize()).done(function (r) { status($f, r.success ? 'Saved.' : (r.message || 'Not saved'), !!r.success); })
			.fail(function (xhr) { status($f, 'Not saved (HTTP ' + xhr.status + ')', false); });
	});

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
