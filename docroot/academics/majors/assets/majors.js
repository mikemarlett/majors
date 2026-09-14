/* Degree Programs listing: filter changes and typed searches update the list in place. */
(function () {
	'use strict';
	var filters = document.getElementById('majors-filters');
	var results = document.getElementById('search_results');
	if (!filters || !results) { return; }

	var searchUrl = filters.getAttribute('data-search-url');
	var selfUrl   = filters.getAttribute('data-self-url');
	var input   = document.getElementById('searchDegrees');
	var type    = document.getElementById('selectDegreeType');
	var college = document.getElementById('selectCollege');
	var order   = document.getElementById('selectOrder');
	var timer   = null;

	function params() {
		var p = new URLSearchParams();
		if (input && input.value.trim()) { p.set('search', input.value.trim()); }
		if (type && type.value !== 'all') { p.set('filter', type.value); }
		if (college && college.value !== 'all') { p.set('college', college.value); }
		if (order && order.value !== 'alpha') { p.set('order', order.value); }
		return p;
	}

	function run() {
		var p = params();
		results.setAttribute('aria-busy', 'true');
		fetch(searchUrl + '?' + p.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				results.innerHTML = data.results || '<p>No results</p>';
				results.removeAttribute('aria-busy');
				if (data.title) { document.title = data.title; }
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', selfUrl + (p.toString() ? '?' + p.toString() : ''));
				}
			})
			.catch(function () {
				results.removeAttribute('aria-busy');
				results.innerHTML = '<p>Sorry, the search is not available right now.</p>';
			});
	}

	var form = document.getElementById('degreeSearchForm');
	if (form) { form.addEventListener('submit', function (e) { e.preventDefault(); run(); }); }
	if (input) {
		input.addEventListener('input', function () {
			clearTimeout(timer);
			var v = input.value.trim();
			if (v.length === 0 || v.length >= 2) { timer = setTimeout(run, 300); }
		});
	}
	[type, college, order].forEach(function (el) { if (el) { el.addEventListener('change', run); } });
})();
