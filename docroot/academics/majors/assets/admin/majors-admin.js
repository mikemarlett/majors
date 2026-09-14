/* Majors admin: client-side filter for the program inventory table. */
(function () {
	'use strict';
	var input = document.getElementById('programFilter');
	var table = document.getElementById('programs_table');
	var count = document.getElementById('programCount');
	if (!input || !table) { return; }
	var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
	function apply() {
		var q = input.value.trim().toLowerCase();
		var shown = 0;
		rows.forEach(function (tr) {
			var hit = q === '' || tr.textContent.toLowerCase().indexOf(q) !== -1;
			tr.hidden = !hit;
			if (hit) { shown++; }
		});
		if (count) { count.textContent = shown + ' of ' + rows.length + ' programs'; }
	}
	input.addEventListener('input', apply);
	apply();
})();
