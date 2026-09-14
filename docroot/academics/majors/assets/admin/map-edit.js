/*
 * Degree Maps admin: modals, drag-and-drop, saves. jQuery + jQuery UI + select2.
 * All server calls go through one endpoint (window.MajorsAdmin.ajax) with
 * ?action=<name>; the CSRF token rides in the X-CSRF-Token header.
 * Port of the legacy map_edit_functions.js.
 */
(function ($) {
	'use strict';
	if (!$ || !window.MajorsAdmin) { return; }

	var cfg = window.MajorsAdmin;
	var url = function (action) { return cfg.ajax + '?action=' + encodeURIComponent(action); };
	var mapId = function () { var v = $('#degree_map_id').val(); return v ? parseInt(v, 10) : cfg.mapId; };

	$.ajaxSetup({ headers: { 'X-CSRF-Token': cfg.csrf } });

	function fail(prefix, response, xhr) {
		var msg = (response && (response.message || response.error)) || (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Request failed.';
		if (xhr && xhr.status === 401) {
			window.alert('Your session has expired. Please sign in again.');
			window.location.reload();
			return;
		}
		window.alert(prefix + ': ' + msg);
	}

	function call(action, data, done, type) {
		return $.ajax({
			url: url(action),
			method: 'POST',
			data: data || {},
			dataType: type || 'json'
		}).done(done).fail(function (xhr) { fail('Error', xhr.responseJSON, xhr); });
	}

	function initTippy() {
		if (window.tippy) {
			window.tippy('.dm-footnote-link', { allowHTML: true, placement: 'bottom', trigger: 'mouseenter focus' });
		}
	}

	function refreshEditor() {
		var id = mapId();
		if (!id) { return; }
		$.ajax({ url: url('get_degree_map'), method: 'GET', data: { degree_map_id: id }, dataType: 'html' })
			.done(function (html) {
				$('#search_results').html(html);
				initSortable();
				initTippy();
			})
			.fail(function (xhr) { fail('Could not reload the map', xhr.responseJSON, xhr); });
	}

	function openDialog(id, html, width, onOpen) {
		$('.ui-dialog-content').each(function () { try { $(this).dialog('close'); } catch (e) { /* not a dialog */ } });
		$('#' + id).remove();
		$('body').append(html);
		$('#' + id).dialog({ modal: true, width: Math.min(width, window.innerWidth - 40), open: onOpen || null,
			close: function () { $(this).dialog('destroy').remove(); } });
	}

	$(document).on('click', '.cancelBtn', function (e) {
		e.preventDefault();
		var id = $(this).data('modal');
		if (id) { $('#' + id).dialog('close'); }
	});

	// ---- drag and drop ------------------------------------------------------
	function initSortable() {
		if (!$.fn.sortable) { return; }
		$('.semester-list').sortable({
			connectWith: '.semester-list',
			handle: '.drag-handle',
			items: '> li.course-item:not(.course-item--add)',
			placeholder: 'sortable-placeholder',
			update: function (event, ui) {
				// Fire once per drop (the receiving list), not once per list involved.
				if (ui.sender) { return; }
				var order = [];
				$('.semester-list').each(function () {
					var semester = $(this).data('semester');
					var year = $(this).data('year');
					$(this).find('li.course-item[data-course-id]').each(function (index) {
						order.push({ id: $(this).data('course-id'), order: index + 1, semester: semester, year: year });
					});
				});
				call('save_course_order', { degree_map_id: mapId(), updatedOrder: JSON.stringify(order) }, function (r) {
					if (!r.success) { fail('Error updating course order', r); }
					refreshEditor();
				});
			}
		}).disableSelection();
	}

	function initSortableFootnotes() {
		if (!$.fn.sortable) { return; }
		$('.footnotes-list').sortable({ handle: '.drag-handle', placeholder: 'sortable-placeholder' }).disableSelection();
	}

	// ---- courses ----------------------------------------------------------------
	function initCourseWidgets() {
		$('#course_info').autocomplete({
			minLength: 2,
			source: function (request, response) {
				$.getJSON(url('course_autocomplete'), { term: request.term }, response).fail(function () { response([]); });
			},
			select: function (event, ui) {
				$('#course_info').val(ui.item.value);
				$('#hours').val(ui.item.hours);
				$('#scbcrse_subj_code').val(ui.item.scbcrse_subj_code);
				$('#scbcrse_crse_numb').val(ui.item.scbcrse_crse_numb);
				return false;
			}
		});
		if ($.fn.select2) {
			$('#editCourseModal .select2').select2({ width: '100%', placeholder: 'Select footnotes', allowClear: true, dropdownParent: $('#editCourseModal') });
		}
		if ($.fn.accordion) {
			$('#advanced').accordion({ collapsible: true, active: false, heightStyle: 'content' });
		}
	}

	function openCourseModal(courseId, year, semester) {
		var data = { degree_map_id: mapId(), year: year, semester: semester };
		if (courseId && courseId !== 'new') { data.id = courseId; }
		call('edit_course', data, function (r) {
			if (!r.modal) { fail('Error', r); return; }
			openDialog('editCourseModal', r.modal, 800, initCourseWidgets);
			$('#saveCourseBtn').on('click', saveCourse);
			$('#deleteCourseBtn').on('click', function () {
				if (!window.confirm('Delete this course from the map?')) { return; }
				call('delete_course', { course_id: courseId }, function () {
					$('#editCourseModal').dialog('close');
					refreshEditor();
				});
			});
		});
	}

	function saveCourse() {
		call('save_course', $('#editCourseForm').serialize(), function (r) {
			if (r.success) { $('#editCourseModal').dialog('close'); refreshEditor(); } else { fail('Error saving course', r); }
		});
	}

	$(document).on('click', '.edit_course', function (e) {
		e.preventDefault();
		var list = $(this).closest('.semester-list');
		openCourseModal($(this).data('course-id'), list.data('year'), list.data('semester'));
	});
	$(document).on('click', '.new_course', function (e) {
		e.preventDefault();
		openCourseModal('new', $(this).attr('data-year'), $(this).attr('data-semester'));
	});
	$(document).on('keypress', '#editCourseForm', function (e) {
		if (e.which === 13 && !$(e.target).is('textarea')) { e.preventDefault(); saveCourse(); }
	});

	// ---- footnotes -------------------------------------------------------------
	$(document).on('click', '#edit_map_footnotes', function (e) {
		e.preventDefault();
		call('edit_map_footnotes', { degree_map_id: $(this).data('map-id') }, function (r) {
			if (!r.modal) { fail('Error', r); return; }
			openDialog('editFootnotesModal', r.modal, 1000, initSortableFootnotes);
			$('#SaveFootnotesBtn').on('click', saveFootnotes);
		});
	});

	$(document).on('click', '#addFootnoteBtn', function (e) {
		e.preventDefault();
		var id = 'new_' + Date.now();
		var $tpl = $('#footnotesContainer .footnote-container').first();
		var html;
		if ($tpl.length) {
			html = $tpl.clone();
			html.attr('data-footnote-id', id).attr('data-order', '');
			html.find('input[type=hidden]').attr('name', 'footnotes[' + id + '][id]').val(id);
			html.find('textarea').attr('name', 'footnotes[' + id + '][note]').attr('id', 'footnote_' + id + '_note').val('');
			html.find('label').attr('for', 'footnote_' + id + '_note').text('New footnote');
			html.find('.remove-footnote-btn').attr('data-footnote-id', id);
		} else {
			html = $('<li class="footnote-container" data-footnote-id="' + id + '">' +
				'<span class="drag-handle" title="Drag to reorder">⇅</span>' +
				'<div class="content"><input type="hidden" name="footnotes[' + id + '][id]" value="' + id + '">' +
				'<label class="sr-only" for="footnote_' + id + '_note">New footnote</label>' +
				'<textarea class="footnote-note" name="footnotes[' + id + '][note]" id="footnote_' + id + '_note" rows="2"></textarea></div>' +
				'<a role="button" href="#" class="remove-footnote-btn button" data-footnote-id="' + id + '">Remove</a></li>');
		}
		$('#footnotesContainer').append(html);
		html.find('textarea').trigger('focus');
	});

	$(document).on('click', '.remove-footnote-btn', function (e) {
		e.preventDefault();
		var id = String($(this).data('footnote-id'));
		var $li = $(this).closest('.footnote-container');
		if (id.indexOf('new') === 0) { $li.remove(); return; }
		if (!window.confirm('Remove this footnote? It will be detached from any courses that use it.')) { return; }
		call('delete_footnote', { remove_footnote: id, degree_map_id: mapId() }, function (r) {
			if (r.success) { $li.remove(); } else { fail('Error deleting footnote', r); }
		});
	});

	function saveFootnotes() {
		// Serialize in list order so the server renumbers 1..n by position.
		var data = [{ name: 'degree_map_id', value: mapId() }];
		$('#footnotesContainer .footnote-container').each(function (i) {
			var id = $(this).attr('data-footnote-id');
			data.push({ name: 'footnotes[' + id + '][id]', value: id });
			data.push({ name: 'footnotes[' + id + '][note]', value: $(this).find('textarea').val() });
		});
		call('save_map_footnotes', $.param(data), function (r) {
			if (r.success) { $('#editFootnotesModal').dialog('close'); refreshEditor(); } else { fail('Error saving footnotes', r); }
		});
	}

	// ---- map details -------------------------------------------------------------
	function bindDepartmentList() {
		$(document).off('change', '#college').on('change', '#college', function () {
			var college = $(this).val();
			if (!college) { $('#department').html('<option value="">None selected</option>'); return; }
			$.ajax({ url: url('get_departments'), method: 'POST', data: { college: college, department: $('#department').val() }, dataType: 'html' })
				.done(function (html) { $('#department').html(html); });
		});
	}

	function validateMapForm() {
		var ok = true, missing = [];
		$('.is-invalid').removeClass('is-invalid');
		$('#degree_map_form').find('[name=major],[name=degree_type],[name=college]').each(function () {
			if (!$(this).val() || !String($(this).val()).trim()) { ok = false; missing.push($(this).attr('name')); $(this).addClass('is-invalid'); }
		});
		if (!ok) { window.alert('Please fill out all required fields: ' + missing.join(', ')); }
		return ok;
	}

	$(document).on('click', '#SaveMapBtn', function (e) {
		e.preventDefault();
		if (!validateMapForm()) { return; }
		var isNew = !parseInt($('#degree_map_form [name=degree_map_id]').val(), 10);
		call('save_map_details', $('#degree_map_form').serialize(), function (r) {
			if (!r.success) { fail('Error saving map details', r); return; }
			if (isNew && r.degree_map_id) {
				window.location.href = cfg.self + '?degree_map_id=' + r.degree_map_id + '&editMap=Edit';
				return;
			}
			$('#editMapModal').dialog('close');
			refreshEditor();
		});
	});

	$(document).on('click', '#edit_map_details', function (e) {
		e.preventDefault();
		call('edit_map', { degree_map_id: $(this).data('map-id') }, function (r) {
			if (!r.modal) { fail('Error', r); return; }
			openDialog('editMapModal', r.modal, 800, bindDepartmentList);
		});
	});

	$(document).on('click', '#newMap', function (e) {
		e.preventDefault();
		var college = $('#selected_college').val();
		if (!college || college === 'all') { college = ''; }
		call('new_map', { college: college }, function (r) {
			if (!r.modal) { fail('Error', r); return; }
			openDialog('editMapModal', r.modal, 800, bindDepartmentList);
		});
	});

	// ---- hours --------------------------------------------------------------------
	$(document).on('click', '#edit_map_hours', function (e) {
		e.preventDefault();
		call('edit_map_hours', { degree_map_id: $(this).data('map-id') }, function (r) {
			if (!r.modal) { fail('Error', r); return; }
			openDialog('editHoursModal', r.modal, 1000);
			$('#SaveHoursBtn').on('click', function () {
				call('save_map_hours', $('#map_hours_form').serialize(), function (r2) {
					if (r2.success) { $('#editHoursModal').dialog('close'); refreshEditor(); } else { fail('Error saving hours', r2); }
				});
			});
		});
	});

	// ---- clone / delete (page actions) -------------------------------------------
	$(document).on('click', '#cloneMap', function (e) {
		e.preventDefault();
		var id = $(this).data('map-id');
		if (!window.confirm('Copy this map into the next catalog year? You can then edit the copy.')) { return; }
		call('clone_degree_map', { degree_map_id: id }, function (r) {
			if (r.success && r.degree_map_id) {
				window.location.href = cfg.self + '?degree_map_id=' + r.degree_map_id + '&editMap=Edit' + (r.existing ? '&flash=' + encodeURIComponent(r.message) : '');
			} else { fail('Could not clone', r); }
		});
	});

	$(document).on('click', '#deleteMap', function (e) {
		e.preventDefault();
		var id = $(this).data('map-id'), title = $(this).data('map-title');
		var typed = window.prompt('This permanently deletes "' + title + '" and all of its courses, footnotes and hours.\n\nType DELETE to confirm:');
		if (typed !== 'DELETE') { return; }
		call('delete_degree_map', { degree_map_id: id }, function (r) {
			if (r.success) { window.location.href = cfg.self + '?flash=' + encodeURIComponent(r.message); } else { fail('Could not delete', r); }
		});
	});

	$(function () {
		initSortable();
		initTippy();
	});
})(window.jQuery);
