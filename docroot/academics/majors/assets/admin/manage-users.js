/* Manage Users (super admins). Same one-endpoint convention as map-edit.js. */
(function ($) {
	'use strict';
	if (!$ || !window.MajorsAdmin) { return; }
	var cfg = window.MajorsAdmin;
	var url = function (action) { return cfg.ajax + '?action=' + encodeURIComponent(action); };
	$.ajaxSetup({ headers: { 'X-CSRF-Token': cfg.csrf } });

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
	function fail(prefix, r, xhr) {
		var msg = (r && (r.message || r.error)) || (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Request failed.';
		if (xhr && xhr.status === 401) { window.location.reload(); return; }
		window.alert(prefix + ': ' + msg);
	}

	var ROLE_LABEL = { advisor: 'Advisor', marketing: 'Marketing', super_admin: 'Super admin', none: 'Disabled' };

	function loadUsers() {
		$.getJSON(url('get_users')).done(function (r) {
			if (!r.success) { $('#user-list').html('<p>Error: ' + esc(r.message) + '</p>'); return; }
			var tableClass = $('#user-list').data('table-class') || '';
			var html = '<table class="' + esc(tableClass) + ' ma-users-table"><thead><tr><th>Name</th><th>myWSU ID</th><th>Role</th><th>Colleges</th><th>Last sign-in</th><th>Actions</th></tr></thead><tbody>';
			r.users.forEach(function (u) {
				html += '<tr class="' + (u.is_active ? '' : 'inactive') + '">' +
					'<td><a href="mailto:' + esc(u.email) + '">' + esc(u.first_name + ' ' + u.last_name) + '</a><br><small>' + esc(u.email) + '</small></td>' +
					'<td>' + esc(u.netid) + '</td>' +
					'<td>' + esc(ROLE_LABEL[u.role] || u.role) + (u.is_active ? '' : ' (inactive)') + '</td>' +
					'<td>' + esc(u.role === 'super_admin' ? 'All' : (u.colleges.join(', ') || '—')) + '</td>' +
					'<td>' + esc(u.last_login ? u.last_login.substring(0, 10) : 'never') + '</td>' +
					'<td><button type="button" class="button button--small edit-user-btn" data-user-id="' + u.id + '">Edit</button> ' +
					'<button type="button" class="button button--small delete-user-btn" data-user-id="' + u.id + '" data-user-name="' + esc(u.first_name + ' ' + u.last_name) + '">Delete</button></td></tr>';
			});
			html += '</tbody></table>';
			$('#user-list').html(html);
		}).fail(function (xhr) { fail('Could not load users', null, xhr); });
	}

	function openForm(userId) {
		$.ajax({ url: url('get_user_form'), method: 'POST', data: userId ? { user_id: userId } : {}, dataType: 'html' })
			.done(function (html) {
				$('#user-form-modal').html(html).prop('hidden', false).dialog({
					modal: true, title: userId ? 'Edit User' : 'Add New User', width: Math.min(700, window.innerWidth - 40),
					close: function () { $(this).dialog('destroy').prop('hidden', true); }
				});
			})
			.fail(function (xhr) { fail('Could not load the form', null, xhr); });
	}

	$('#add-user-btn').on('click', function () { openForm(null); });
	$(document).on('click', '.edit-user-btn', function () { openForm($(this).data('user-id')); });
	$(document).on('click', '#cancel-user-btn', function () { $('#user-form-modal').dialog('close'); });

	$(document).on('click', '.delete-user-btn', function () {
		if (!window.confirm('Remove ' + $(this).data('user-name') + ' from the access list?')) { return; }
		$.post(url('delete_user'), { user_id: $(this).data('user-id') }, null, 'json')
			.done(function (r) { if (r.success) { loadUsers(); } else { fail('Error', r); } })
			.fail(function (xhr) { fail('Error deleting user', null, xhr); });
	});

	$(document).on('submit', '#user-form', function (e) {
		e.preventDefault();
		$.post(url('save_user'), $(this).serialize(), null, 'json')
			.done(function (r) { if (r.success) { loadUsers(); $('#user-form-modal').dialog('close'); } else { fail('Error', r); } })
			.fail(function (xhr) { fail('Error saving user', null, xhr); });
	});

	loadUsers();
})(window.jQuery);
