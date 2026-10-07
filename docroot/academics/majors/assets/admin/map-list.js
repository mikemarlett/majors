/*
 * Degree Maps admin (degree_maps/admin/maps.php): approval.
 *
 * On a map's page: the Approve / Withdraw button in the Actions row.
 * On the listing: the table filters on the toolbar's search box, college and
 * approval selects, sorts by any column, and (for advisor admins and super
 * admins) approves or withdraws the ticked rows at once. Everything the script
 * filters and sorts on comes from data-* attributes on each <tr>. Vanilla JS;
 * uses MaUI (ma-ui.js) for ajax and toasts. The catalog-year select reloads
 * the page, as the public page does: the table holds one year.
 */
(function () {
	'use strict';
	var MaUI = window.MaUI;
	var cfg  = window.MajorsAdmin || {};
	if (!MaUI) { return; }

	function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }
	function shortDate(at) {
		var d = at ? new Date(String(at).replace(' ', 'T')) : null;
		if (!d || isNaN(d.getTime())) { return ''; }
		return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
	}

	/* ---- one map: the Actions row ---------------------------------------- */
	var approveBtn = document.getElementById('approveMap');
	if (approveBtn) {
		approveBtn.addEventListener('click', function () {
			var id   = approveBtn.dataset.mapId;
			var next = approveBtn.dataset.approved === '1' ? 0 : 1;
			if (!next && !window.confirm('Withdraw this map from the public site? Students will no longer be able to open it until it is approved again.')) { return; }
			approveBtn.disabled = true;
			MaUI.ajax('set_approval', { degree_map_id: id, approved: next }).then(function (res) {
				approveBtn.dataset.approved = String(next);
				approveBtn.textContent = next ? 'Withdraw from public site' : 'Approve for public site';
				var line = document.getElementById('map_approval');
				if (line) {
					line.dataset.approved = String(next);
					var meta = (res.by ? ' by ' + res.by : '') + (res.at ? ' on ' + shortDate(res.at) : '');
					line.innerHTML = next
						? '<strong>Approved</strong>' + MaUI.esc(meta) + ': this map is on the public site.'
						: '<strong>Not approved</strong> (withdrawn' + MaUI.esc(meta) + '): students cannot see this map. Approve it when it is ready.';
				}
				var link = document.getElementById('publicPageLink');
				if (link) { link.textContent = next ? 'Public page' : 'Preview public page'; }
				MaUI.toast(res.message || 'Saved');
			}, function (err) {
				MaUI.toast(err.message || 'Could not save.', { kind: 'error' });
			}).then(function () { approveBtn.disabled = false; });
		});
	}

	/* ---- the listing ------------------------------------------------------ */
	var panel = document.getElementById('maps_panel');
	var table = document.getElementById('maps_table');
	if (!panel || !table) { return; }

	var filters   = document.getElementById('dm-filters');
	var selfUrl   = filters ? filters.getAttribute('data-self-url') : cfg.self;
	var qEl       = document.getElementById('searchList');
	var yearEl    = document.getElementById('selected_year');
	var collegeEl = document.getElementById('selected_college');
	var statusEl  = document.getElementById('selected_status');
	var countEl   = document.getElementById('maps_count');
	var selEl     = document.getElementById('maps_selected');
	var allBox    = document.getElementById('maps_select_all');
	var btnApprove  = document.getElementById('bulkApprove');
	var btnWithdraw = document.getElementById('bulkWithdraw');
	var canApprove  = panel.dataset.canApprove === '1';
	var tbody   = table.tBodies[0];
	var rows    = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]'));
	var headers = Array.prototype.slice.call(table.tHead.querySelectorAll('th'));
	rows.forEach(function (tr, i) { tr._index = i; });
	var sort = { key: 'name', dir: 'asc' };

	/* initial filter values come from the page's own query string via the panel */
	if (collegeEl && panel.dataset.college) { collegeEl.value = panel.dataset.college; if (collegeEl.value !== panel.dataset.college) { collegeEl.value = 'all'; } }
	if (statusEl && panel.dataset.status) { statusEl.value = panel.dataset.status; }

	/* ---- filtering ------------------------------------------------------- */
	function applyFilters() {
		var terms   = (qEl ? qEl.value : '').trim().toLowerCase().split(/\s+/).filter(Boolean);
		var college = collegeEl && collegeEl.value !== 'all' ? collegeEl.value : '';
		var status  = statusEl ? statusEl.value : 'all';
		var shown = 0;
		rows.forEach(function (tr) {
			var d = tr.dataset, hit = true;
			if (college && d.college !== college) { hit = false; }
			if (hit && status !== 'all' && d.status !== status) { hit = false; }
			if (hit) {
				for (var i = 0; i < terms.length; i++) {
					if ((d.search || '').indexOf(terms[i]) === -1) { hit = false; break; }
				}
			}
			tr.hidden = !hit;
			if (!hit) { var cb = tr.querySelector('.ma-row-check'); if (cb) { cb.checked = false; } }
			if (hit) { shown++; }
		});
		updateCount(shown);
		updateSelection();
		writeQuery();
	}

	function updateCount(shown) {
		if (!countEl) { return; }
		var total = rows.length;
		var approved = rows.filter(function (tr) { return tr.dataset.status === 'approved'; }).length;
		var year = panel.dataset.year ? (panel.dataset.year - 1) + '-' + panel.dataset.year : '';
		var text = plural(total, 'map') + (year ? ' for ' + year : '') + ', ' + approved + ' approved';
		if (shown !== undefined && shown !== total) { text += ', ' + shown + ' shown'; }
		countEl.textContent = text;
	}

	/* the filter state lives in the query string so a view can be bookmarked (never the hash: the old theme's footcode runs $(location.hash)) */
	function writeQuery() {
		var p = new URLSearchParams();
		if (yearEl) { p.set('selected_year', yearEl.value); }
		if (collegeEl && collegeEl.value && collegeEl.value !== 'all') { p.set('selected_college', collegeEl.value); }
		if (statusEl && statusEl.value && statusEl.value !== 'all') { p.set('selected_status', statusEl.value); }
		try { window.history.replaceState(null, '', selfUrl + '?' + p.toString()); } catch (e) { /* ignore */ }
	}

	/* ---- sorting --------------------------------------------------------- */
	function headerFor(key) {
		for (var i = 0; i < headers.length; i++) {
			var b = headers[i].querySelector('[data-sort]');
			if (b && b.dataset.sort === key) { return headers[i]; }
		}
		return null;
	}
	function applySort() {
		var th = headerFor(sort.key);
		if (!th) { return; }
		var btn = th.querySelector('[data-sort]');
		var key = btn.dataset.sort, type = btn.dataset.type || 'text';
		var sign = sort.dir === 'desc' ? -1 : 1;
		var sorted = rows.slice().sort(function (a, b) {
			var x = a.dataset[key] || '', y = b.dataset[key] || '', c;
			if (type === 'date') { c = x < y ? -1 : (x > y ? 1 : 0); }
			else { c = x.localeCompare(y, undefined, { sensitivity: 'base', numeric: true }); }
			if (c === 0 && key !== 'name') { c = (a.dataset.name || '').localeCompare(b.dataset.name || '', undefined, { sensitivity: 'base' }); }
			return c !== 0 ? c * sign : a._index - b._index;
		});
		sorted.forEach(function (tr) { tbody.appendChild(tr); });
		headers.forEach(function (h) {
			if (h === th) { h.setAttribute('aria-sort', sort.dir === 'desc' ? 'descending' : 'ascending'); }
			else { h.removeAttribute('aria-sort'); }
		});
	}
	table.tHead.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-sort]');
		if (!btn) { return; }
		var key = btn.dataset.sort;
		if (sort.key === key) { sort.dir = sort.dir === 'asc' ? 'desc' : 'asc'; } else { sort = { key: key, dir: 'asc' }; }
		applySort();
	});

	/* ---- toolbar events -------------------------------------------------- */
	var debounce;
	if (qEl) {
		qEl.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(applyFilters, 150); });
		var form = document.getElementById('search_form');
		if (form) { form.addEventListener('submit', function (e) { e.preventDefault(); clearTimeout(debounce); applyFilters(); }); }
	}
	if (collegeEl) { collegeEl.addEventListener('change', applyFilters); }
	if (statusEl) { statusEl.addEventListener('change', applyFilters); }
	if (yearEl) {
		yearEl.addEventListener('change', function () {
			var p = new URLSearchParams({ selected_year: yearEl.value });
			if (collegeEl && collegeEl.value !== 'all') { p.set('selected_college', collegeEl.value); }
			if (statusEl && statusEl.value !== 'all') { p.set('selected_status', statusEl.value); }
			window.location.href = selfUrl + '?' + p.toString();
		});
	}

	/* ---- selection + bulk approval ---------------------------------------- */
	function selected() {
		return rows.filter(function (tr) { var cb = tr.querySelector('.ma-row-check'); return cb && cb.checked && !tr.hidden; });
	}
	function updateSelection() {
		if (!canApprove) { return; }
		var sel = selected();
		if (selEl) { selEl.textContent = sel.length + ' selected'; }
		var anyPending = sel.some(function (tr) { return tr.dataset.status !== 'approved'; });
		var anyApproved = sel.some(function (tr) { return tr.dataset.status === 'approved'; });
		if (btnApprove) { btnApprove.disabled = !anyPending; }
		if (btnWithdraw) { btnWithdraw.disabled = !anyApproved; }
		if (allBox) {
			var shown = rows.filter(function (tr) { return !tr.hidden; });
			allBox.checked = shown.length > 0 && sel.length === shown.length;
			allBox.indeterminate = sel.length > 0 && sel.length < shown.length;
		}
	}
	if (allBox) {
		allBox.addEventListener('change', function () {
			rows.forEach(function (tr) { var cb = tr.querySelector('.ma-row-check'); if (cb && !tr.hidden) { cb.checked = allBox.checked; } });
			updateSelection();
		});
	}
	tbody.addEventListener('change', function (e) {
		if (e.target.classList.contains('ma-row-check')) { updateSelection(); }
	});

	function statusCell(tr, approved, by, at) {
		var cell = tr.querySelector('.ma-cell-status');
		if (!cell) { return; }
		var meta = (by ? 'by ' + by : '') + (at ? (by ? ', ' : '') + shortDate(at) : '');
		cell.innerHTML = '<span class="ma-state ' + (approved ? 'ma-state--approved' : 'ma-state--pending') + '">' + (approved ? 'Approved' : 'Not approved') + '</span>'
			+ (meta ? '<span class="ma-sub">' + (approved ? '' : 'withdrawn ') + MaUI.esc(meta) + '</span>' : '');
		tr.dataset.status = approved ? 'approved' : 'pending';
		tr.dataset.changed = '0';
		var preview = tr.querySelector('.ma-maps__preview');
		if (preview) { preview.textContent = approved ? 'Public page' : 'Preview'; }
	}

	function bulk(approved) {
		var sel = selected();
		if (!sel.length) { return; }
		var targets = sel.filter(function (tr) { return (tr.dataset.status === 'approved') !== approved; });
		if (!targets.length) { return; }
		if (!approved && !window.confirm('Withdraw ' + plural(targets.length, 'map') + ' from the public site? Students will no longer be able to open them until they are approved again.')) { return; }
		btnApprove.disabled = btnWithdraw.disabled = true;
		MaUI.ajax('set_approval', { 'ids[]': targets.map(function (tr) { return tr.dataset.id; }), approved: approved ? 1 : 0 }).then(function (res) {
			targets.forEach(function (tr) {
				statusCell(tr, approved, res.by, res.at);
				var cb = tr.querySelector('.ma-row-check'); if (cb) { cb.checked = false; }
			});
			applyFilters();
			var hidden = targets.filter(function (tr) { return tr.hidden; }).length;
			MaUI.toast((res.message || 'Saved') + (hidden ? ' ' + plural(hidden, 'map') + ' now hidden by the Approval filter.' : ''));
		}, function (err) {
			MaUI.toast(err.message || 'Could not save.', { kind: 'error' });
			updateSelection();
		});
	}
	if (btnApprove) { btnApprove.addEventListener('click', function () { bulk(true); }); }
	if (btnWithdraw) { btnWithdraw.addEventListener('click', function () { bulk(false); }); }

	/* ---- boot ------------------------------------------------------------ */
	applySort();
	applyFilters();
})();
