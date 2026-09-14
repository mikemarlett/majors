/*
 * Degree Maps public page: live search + filter changes without a reload.
 * Vanilla JS so it works under either site design (no jQuery dependency).
 * Endpoint: the data-search-url on #dm-filters (degree_maps/search.php).
 */
(function () {
	'use strict';

	var filters = document.getElementById('dm-filters');
	var results = document.getElementById('search_results');
	if (!filters || !results) { return; }

	var searchUrl = filters.getAttribute('data-search-url');
	var selfUrl   = filters.getAttribute('data-self-url');
	var linkBase  = filters.getAttribute('data-link-base'); // admin page: results link back into the admin
	var input     = document.getElementById('searchList');
	var yearSel   = document.getElementById('selected_year');
	var collegeSel = document.getElementById('selected_college');
	var orderLinks = document.querySelectorAll('.dm-view-switch a[data-order]');
	var order = (document.querySelector('.dm-view-switch a[aria-current="true"]') || {}).getAttribute
		? document.querySelector('.dm-view-switch a[aria-current="true"]').getAttribute('data-order') : 'alpha';
	var timer = null;

	function params() {
		var p = new URLSearchParams();
		p.set('searchList', input ? input.value.trim() : '');
		p.set('selected_year', yearSel ? yearSel.value : '');
		p.set('selected_college', collegeSel ? collegeSel.value : 'all');
		p.set('order', order || 'alpha');
		if (linkBase) { p.set('link_base', linkBase); }
		return p;
	}

	function pushState() {
		if (!window.history || !window.history.replaceState) { return; }
		var q = new URLSearchParams();
		if (yearSel) { q.set('selected_year', yearSel.value); }
		if (order && order !== 'alpha') { q.set('order', order); }
		if (collegeSel && collegeSel.value && collegeSel.value !== 'all') { q.set('selected_college', collegeSel.value); }
		window.history.replaceState(null, '', selfUrl + (q.toString() ? '?' + q.toString() : ''));
	}

	function initTooltips() {
		if (window.tippy) {
			window.tippy('.dm-footnote-link', { allowHTML: true, placement: 'bottom', trigger: 'mouseenter focus' });
		}
	}

	function run() {
		results.setAttribute('aria-busy', 'true');
		fetch(searchUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
			body: params().toString(),
			credentials: 'same-origin'
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			results.innerHTML = data.results || '<p>No results</p>';
			results.removeAttribute('aria-busy');
			initTooltips();
			if (data.map_id) {
				// A single hit renders the map itself; land on its real URL so the admin actions apply to it.
				if (linkBase) { window.location.href = linkBase + data.map_id; return; }
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', selfUrl + '?degree_map_id=' + data.map_id);
				}
			} else {
				pushState();
			}
		})
		.catch(function () {
			results.removeAttribute('aria-busy');
			results.innerHTML = '<p>Sorry, the search is not available right now.</p>';
		});
	}

	var form = document.getElementById('search_form');
	if (form) {
		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
	}
	if (input) {
		input.addEventListener('input', function () {
			clearTimeout(timer);
			var v = input.value.trim();
			if (v.length === 0 || v.length >= 2) { timer = setTimeout(run, 300); }
		});
	}
	if (yearSel) {
		yearSel.addEventListener('change', function () {
			// The college list depends on the year; a full reload keeps it honest.
			var q = new URLSearchParams({ selected_year: yearSel.value, order: order || 'alpha' });
			window.location.href = selfUrl + '?' + q.toString();
		});
	}
	if (collegeSel) { collegeSel.addEventListener('change', run); }
	Array.prototype.forEach.call(orderLinks, function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			order = a.getAttribute('data-order');
			Array.prototype.forEach.call(orderLinks, function (b) {
				var on = b === a;
				b.toggleAttribute('aria-current', on);
				b.innerHTML = on ? '<strong>' + b.textContent + '</strong>' : b.textContent;
			});
			run();
		});
	});

	initTooltips();
})();
