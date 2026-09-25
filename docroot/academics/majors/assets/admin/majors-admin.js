/*
 * Majors control panel (_admin/index.php): the program table sorts by any
 * column, the toolbar filters combine, and two things save in place — the
 * status select and the similar-programs popover. Everything the script
 * sorts and filters on comes from data-* attributes on each <tr>; it never
 * parses cell text. Filter and sort state live in the URL hash so a view
 * can be bookmarked. Uses MaUI (ma-ui.js): ajax, popover, toast, el.
 */
(function () {
	'use strict';
	var table   = document.getElementById('programs_table');
	var toolbar = document.getElementById('panel_toolbar');
	var MaUI    = window.MaUI;
	if (!table || !toolbar || !MaUI) { return; }

	var tbody   = table.tBodies[0];
	var rows    = Array.prototype.slice.call(tbody.rows);
	var headers = Array.prototype.slice.call(table.tHead.querySelectorAll('th'));
	var countEl = document.getElementById('panel_count');
	var resetEl = document.getElementById('panel_reset');
	rows.forEach(function (tr, i) { tr._panelIndex = i; });   // ties keep the server's order

	var FILTERS  = ['q', 'level', 'credential', 'college', 'status', 'attention'];
	var DEFAULTS = { q: '', level: '', credential: '', college: '', status: 'active', attention: '' };
	var DEFAULT_SORT = { key: 'name', dir: 'asc' };
	var controls = {};
	FILTERS.forEach(function (k) { controls[k] = toolbar.querySelector('[data-filter="' + k + '"]'); });
	var sort = { key: DEFAULT_SORT.key, dir: DEFAULT_SORT.dir };

	/* Credential "Other / not set" = anything outside the curated list the template put on the select
	   (falls back to the select's own options). */
	var knownCredentials = {};
	(function () {
		var sel = controls.credential, list = [];
		if (!sel) { return; }
		try { list = JSON.parse(sel.dataset.curated || '[]'); } catch (e) { list = []; }
		if (!list.length) { Array.prototype.forEach.call(sel.options, function (o) { if (o.value && o.value !== 'other') { list.push(o.value); } }); }
		list.forEach(function (c) { knownCredentials[c] = true; });
	})();

	/* ---- filtering ------------------------------------------------------- */
	function readFilters() {
		var f = {};
		FILTERS.forEach(function (k) { f[k] = controls[k] ? controls[k].value : DEFAULTS[k]; });
		return f;
	}

	function matches(tr, f, terms) {
		var d = tr.dataset;
		if (f.level && d.level !== f.level) { return false; }
		if (f.credential === 'other') { if (knownCredentials[d.credential || '']) { return false; } }
		else if (f.credential && d.credential !== f.credential) { return false; }
		if (f.college && d.college !== f.college) { return false; }
		if (f.status !== 'all' && d.status !== f.status) { return false; }
		if (f.attention && (' ' + (d.attention || '') + ' ').indexOf(' ' + f.attention + ' ') === -1) { return false; }   // tokens from the server
		var hay = d.search || '';
		for (var i = 0; i < terms.length; i++) {
			if (hay.indexOf(terms[i]) === -1) { return false; }
		}
		return true;
	}

	function applyFilters() {
		var f = readFilters();
		var terms = f.q.trim().toLowerCase().split(/\s+/).filter(Boolean);
		var shown = 0;
		rows.forEach(function (tr) {
			var hit = matches(tr, f, terms);
			tr.hidden = !hit;
			if (hit) { shown++; }
		});
		if (countEl) { countEl.textContent = shown + ' of ' + rows.length + ' program' + (rows.length === 1 ? '' : 's'); }
		writeHash(f);
		return shown;
	}

	/* ---- sorting --------------------------------------------------------- */
	function headerFor(key) {
		for (var i = 0; i < headers.length; i++) {
			var b = headers[i].querySelector('[data-sort]');
			if (b && b.dataset.sort === key) { return headers[i]; }
		}
		return null;
	}

	function compare(a, b, key, type) {
		var x = a.dataset[key] || '', y = b.dataset[key] || '';
		if (type === 'number') { return (Number(x) || 0) - (Number(y) || 0); }
		if (type === 'date') { return x < y ? -1 : (x > y ? 1 : 0); }
		return x.localeCompare(y, undefined, { sensitivity: 'base', numeric: true });
	}

	function applySort() {
		var th = headerFor(sort.key);
		if (!th) { sort = { key: DEFAULT_SORT.key, dir: DEFAULT_SORT.dir }; th = headerFor(sort.key); }
		var btn  = th.querySelector('[data-sort]');
		var key  = btn.dataset.sort, type = btn.dataset.type || 'text', then = btn.dataset.then || '';
		var sign = sort.dir === 'desc' ? -1 : 1;
		function cmp(a, b, k, t) {
			if (t === 'text') {                             // blanks sink to the bottom either way
				var ea = !a.dataset[k], eb = !b.dataset[k];
				if (ea !== eb) { return ea ? 1 : -1; }
			}
			return compare(a, b, k, t) * sign;
		}
		var sorted = rows.slice().sort(function (a, b) {
			var c = cmp(a, b, key, type);
			if (c === 0 && then) { c = cmp(a, b, then, 'text'); }   // e.g. Type: credential, then the type code
			return c !== 0 ? c : a._panelIndex - b._panelIndex;
		});
		sorted.forEach(function (tr) { tbody.appendChild(tr); });
		headers.forEach(function (h) {
			if (h === th) { h.setAttribute('aria-sort', sort.dir === 'desc' ? 'descending' : 'ascending'); }
			else { h.removeAttribute('aria-sort'); }
		});
	}

	/* ---- URL hash: the view is bookmarkable ------------------------------- */
	function writeHash(f) {
		var p = new URLSearchParams();
		FILTERS.forEach(function (k) { if (f[k] !== DEFAULTS[k] && f[k] !== '') { p.set(k, f[k]); } });
		if (sort.key !== DEFAULT_SORT.key || sort.dir !== DEFAULT_SORT.dir) { p.set('sort', sort.key); p.set('dir', sort.dir); }
		var h = p.toString();
		if (window.location.hash.replace(/^#/, '') === h) { return; }
		try {
			window.history.replaceState(null, '', window.location.pathname + window.location.search + (h ? '#' + h : ''));
		} catch (e) { /* ignore */ }
	}

	function readHash() {
		var p = new URLSearchParams(window.location.hash.replace(/^#/, ''));
		FILTERS.forEach(function (k) {
			var c = controls[k];
			if (!c) { return; }
			var v = p.has(k) ? p.get(k) : DEFAULTS[k];
			c.value = v;
			if (c.tagName === 'SELECT' && c.value !== v) { c.value = DEFAULTS[k]; }   // unknown option
		});
		var key = p.get('sort');
		sort = key && headerFor(key) ? { key: key, dir: p.get('dir') === 'desc' ? 'desc' : 'asc' } : { key: DEFAULT_SORT.key, dir: DEFAULT_SORT.dir };
	}

	/* ---- toolbar events -------------------------------------------------- */
	var debounce;
	if (controls.q) {
		controls.q.addEventListener('input', function () { clearTimeout(debounce); debounce = setTimeout(applyFilters, 150); });
		controls.q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(debounce); applyFilters(); } });
	}
	FILTERS.forEach(function (k) {
		if (k !== 'q' && controls[k]) { controls[k].addEventListener('change', applyFilters); }
	});
	if (resetEl) {
		resetEl.addEventListener('click', function () {
			FILTERS.forEach(function (k) { if (controls[k]) { controls[k].value = DEFAULTS[k]; } });
			sort = { key: DEFAULT_SORT.key, dir: DEFAULT_SORT.dir };
			applySort();
			applyFilters();
			if (controls.q) { controls.q.focus(); }
		});
	}
	table.tHead.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-sort]');
		if (!btn) { return; }
		var key = btn.dataset.sort;
		if (sort.key === key) { sort.dir = sort.dir === 'asc' ? 'desc' : 'asc'; } else { sort = { key: key, dir: 'asc' }; }
		applySort();
		writeHash(readFilters());
	});
	window.addEventListener('hashchange', function () { readHash(); applySort(); applyFilters(); });

	/* ---- status: saves on change ----------------------------------------- */
	tbody.addEventListener('change', function (e) {
		var sel = e.target.closest('[data-status-select]');
		if (!sel) { return; }
		var tr = sel.closest('tr');
		var prev = tr.dataset.status, next = sel.value;
		if (next === prev) { return; }
		sel.disabled = true;
		MaUI.ajax('save_program', { program_id: tr.dataset.id, status: next }).then(function () {
			tr.dataset.status = next;
			tr.classList.toggle('is-retired', next === 'retired');
			applyFilters();
			MaUI.toast(tr.hidden ? 'Saved. ' + (tr.dataset.title || 'The program') + ' is now hidden by the Status filter.' : 'Saved');
		}, function (err) {
			sel.value = prev;
			MaUI.toast(err.message || 'Could not save.', { kind: 'error' });
		}).then(function () { sel.disabled = false; });
	});

	/* ---- similar programs: popover with remove + search-to-add ------------ */
	tbody.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-similar-btn]');
		if (!btn) { return; }
		e.preventDefault();
		openSimilar(btn, btn.closest('tr'));
	});

	function openSimilar(btn, tr) {
		var id = tr.dataset.id, name = tr.dataset.title || '';
		var list = [];
		try { list = JSON.parse(tr.dataset.similarJson || '[]') || []; } catch (err) { list = []; }
		var busy = false, timer, seq = 0, lastResults = [], searched = false;

		var listEl   = MaUI.el('ul', { class: 'ma-similar__list' });
		var input    = MaUI.el('input', { type: 'search', class: 'ma-similar__search', placeholder: 'Type at least 2 letters…', autocomplete: 'off' });
		var results  = MaUI.el('ul', { class: 'ma-similar__results' });
		var status   = MaUI.el('div', { class: 'ma-similar__status ma-help', 'aria-live': 'polite' });
		var inputId  = 'similar_q_' + id;
		input.id = inputId;
		var content  = MaUI.el('div', { class: 'ma-similar' },
			listEl,
			MaUI.el('div', { class: 'ma-field ma-similar__add-field' }, MaUI.el('label', { for: inputId, text: 'Add a program…' }), input),
			results, status);
		var pop = MaUI.popover(btn, { title: 'Similar programs — ' + name, content: content, width: 420, className: 'ma-popover--similar' });

		function renderList() {
			listEl.innerHTML = '';
			if (!list.length) {
				listEl.appendChild(MaUI.el('li', { class: 'ma-similar__empty', text: 'No similar programs yet.' }));
			}
			list.forEach(function (s) {
				listEl.appendChild(MaUI.el('li', { class: 'ma-similar__item' },
					MaUI.el('span', { class: 'ma-similar__name', text: s.name }),
					MaUI.el('button', { type: 'button', class: 'ma-similar__remove', title: 'Remove', 'aria-label': 'Remove ' + s.name, text: '×',
						onclick: function () { save(list.filter(function (x) { return x.id !== s.id; })); } })));
			});
			pop.reposition();
		}

		function renderResults(items) {
			lastResults = items;
			results.innerHTML = '';
			var listed = {};
			list.forEach(function (s) { listed[String(s.id)] = true; });
			var shown = items.filter(function (r) { return String(r.id) !== String(id) && !listed[String(r.id)]; });
			shown.forEach(function (r) {
				results.appendChild(MaUI.el('li', {},
					MaUI.el('button', { type: 'button', class: 'ma-similar__add', text: r.text, onclick: function () {
						save(list.concat([{ id: Number(r.id), name: String(r.text).replace(/\s*\([^()]*\)\s*$/, '') }]));
					} })));
			});
			if (searched && !shown.length) {
				results.appendChild(MaUI.el('li', { class: 'ma-similar__empty', text: 'No matches.' }));
			}
			pop.reposition();
		}

		function save(next) {
			if (busy) { return; }
			busy = true;
			status.textContent = 'Saving…';
			MaUI.ajax('save_similar', { program_id: id, 'similar[]': next.map(function (s) { return s.id; }) }).then(function (res) {
				list = (Array.isArray(res.similar) ? res.similar : next).map(function (s) { return { id: Number(s.id), name: String(s.name) }; });
				tr.dataset.similarJson = JSON.stringify(list);
				tr.dataset.similar = String(list.length);
				btn.textContent = String(list.length);
				btn.setAttribute('aria-label', 'Similar programs: ' + list.length);
				btn.classList.toggle('is-empty', list.length === 0);
				status.textContent = '';
				renderList();
				renderResults(lastResults);
				MaUI.toast('Saved');
			}, function (err) {
				status.textContent = '';
				MaUI.toast(err.message || 'Could not save.', { kind: 'error' });
			}).then(function () { busy = false; });
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var q = input.value.trim();
			if (q.length < 2) { seq++; searched = false; status.textContent = ''; renderResults([]); return; }
			timer = setTimeout(function () {
				var my = ++seq;
				status.textContent = 'Searching…';
				MaUI.ajax('program_search', { q: q }, { method: 'GET' }).then(function (res) {
					if (my !== seq) { return; }
					status.textContent = '';
					searched = true;
					renderResults(res.results || []);
				}, function (err) {
					if (my !== seq) { return; }
					status.textContent = '';
					MaUI.toast(err.message || 'Search failed.', { kind: 'error' });
				});
			}, 250);
		});
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });

		renderList();
	}

	/* ---- boot ------------------------------------------------------------ */
	readHash();
	applySort();
	applyFilters();
})();
