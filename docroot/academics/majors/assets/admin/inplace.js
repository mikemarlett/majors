/*
 * Majors in-place editor. The page is the public program page rendered with
 * editing markers (see app/src/Majors/EditMarks.php):
 *
 *   [data-ma-text=field]   single-line text, edited with contenteditable
 *   [data-ma-html=field]   rich text, edited with CKEditor 5 (balloon build) in place
 *   [data-ma-form=name]    click opens a popover form (values in data-ma-json)
 *   [data-ma-scope]        program | section (with [data-section]) | block (with [data-block]) | similar
 *   [data-ma-part]         card | content (several roots) | similar — swapped after every save
 *   [data-ma-act]          tool-strip and add-bar actions
 *
 * Every change saves itself (blur / Enter / popover Save); the server answers
 * with the re-rendered parts, which replace the page's, so what is on screen
 * is always the real page. Text saves get an Undo in the toast.
 * Needs ma-ui.js (window.MaUI) and window.MajorsAdmin (config).
 */
(function () {
	'use strict';
	var cfg = window.MajorsAdmin || {};
	var U = window.MaUI;
	if (!U) { return; }
	var el = U.el, ajax = U.ajax, toast = U.toast;

	/* ---------------------------------------------------------------- new program */
	var np = document.querySelector('[data-ma-new-program]');
	if (np) {
		var echo = np.querySelector('[data-ma-basename-echo]');
		var baseInput = np.querySelector('[name=basename]');
		var touched = false;
		var timer = null;
		function preview() {
			clearTimeout(timer);
			timer = setTimeout(function () {
				var q = { academic_program: np.academic_program.value, program_type: np.program_type.value, credential: np.credential.value };
				if (touched && baseInput.value.trim() !== '') { q.basename = baseInput.value; }
				if (!q.academic_program.trim() && !q.basename) { echo.textContent = '…'; return; }
				ajax('basename_preview', q, { method: 'GET' }).then(function (r) {
					echo.textContent = r.basename;
					if (!touched) { baseInput.value = r.basename; }
				}).catch(function () { /* preview only */ });
			}, 250);
		}
		['academic_program', 'program_type', 'credential'].forEach(function (n) { np[n].addEventListener('input', preview); np[n].addEventListener('change', preview); });
		var gradBox = np.querySelector('[data-ma-graduate]');
		np.credential.addEventListener('change', function () { if (gradBox) { gradBox.checked = /Master|Doctor|\bGraduate|Postbacc/i.test(np.credential.value); } });
		baseInput.addEventListener('input', function () { touched = baseInput.value.trim() !== ''; preview(); });
		np.addEventListener('submit', function (e) {
			e.preventDefault();
			var err = np.querySelector('[data-ma-new-error]');
			err.textContent = '';
			ajax('new_program', U.serialize(np)).then(function (r) {
				if (r.redirect) { window.location = r.redirect; }
			}).catch(function (ex) { err.textContent = ex.message; });
		});
		if (!cfg.programId) { return; }
	}

	if (!cfg.programId) { return; }
	var PID = cfg.programId;
	var statusEl = document.querySelector('[data-ma-status]');
	var busy = 0;

	function setStatus(text, kind) {
		if (!statusEl) { return; }
		statusEl.textContent = text || '';
		statusEl.className = 'ma-edit-bar__status' + (kind ? ' is-' + kind : '');
	}
	function working(on) {
		busy += on ? 1 : -1;
		document.body.classList.toggle('ma-busy', busy > 0);
		if (busy > 0) { setStatus('Saving…', 'busy'); }
	}

	/* ---------------------------------------------------------------- parts */
	function nodesFrom(html) {
		var tpl = document.createElement('template');
		tpl.innerHTML = (html || '').trim();
		return Array.prototype.slice.call(tpl.content.children);
	}
	/* Replace the page's parts with the server's re-rendered ones. The old design puts everything in
	   `content`; the new one splits card/content/similar — so the parts are recognised by their marker, not by slot. */
	function swap(res) {
		if (!res || !res.parts) { return; }
		var fresh = nodesFrom(res.parts.top).concat(nodesFrom(res.parts.content), nodesFrom(res.parts.bottom));
		var by = { card: [], content: [], similar: [] };
		fresh.forEach(function (n) { var k = n.getAttribute('data-ma-part'); if (by[k]) { by[k].push(n); } });
		['card', 'similar'].forEach(function (k) {
			var old = document.querySelector('[data-ma-part="' + k + '"]');
			if (old && by[k][0] && old.outerHTML !== by[k][0].outerHTML) { old.replaceWith(by[k][0]); }
		});
		var olds = Array.prototype.slice.call(document.querySelectorAll('[data-ma-part="content"]'));
		var same = olds.length === by.content.length && olds.every(function (n, i) { return n.outerHTML === by.content[i].outerHTML; });
		if (olds.length && by.content.length && !same) {
			by.content.forEach(function (n) { olds[0].parentNode.insertBefore(n, olds[0]); });
			olds.forEach(function (n) { n.parentNode.removeChild(n); });
		}
		if (res.title) {
			document.title = 'Editing: ' + res.title;
			var t = document.querySelector('[data-ma-title]'); if (t) { t.textContent = res.title; }
			var h1 = document.querySelector('main h1'); if (h1) { h1.textContent = 'Details: ' + res.title; }
		}
		prime();
	}
	/* keyboard reach for everything clickable */
	function prime() {
		document.querySelectorAll('[data-ma-text], [data-ma-html], [data-ma-form]').forEach(function (n) {
			if (!n.hasAttribute('tabindex')) { n.setAttribute('tabindex', '0'); }
			var what = n.getAttribute('data-ma-text') || n.getAttribute('data-ma-html') || n.getAttribute('data-ma-form') || '';
			if (!n.title) { n.title = 'Click or press Enter to change the ' + what.replace(/[_-]/g, ' '); }
			n.setAttribute('aria-roledescription', 'editable');
		});
		document.querySelectorAll('[data-ma-part="similar"] a[href]').forEach(function (a) { a.setAttribute('tabindex', '-1'); });
	}

	/* ---------------------------------------------------------------- scopes and saving */
	function scopeOf(node) {
		var s = node.closest('[data-ma-scope]');
		if (!s) { return { kind: 'program' }; }
		var kind = s.getAttribute('data-ma-scope');
		var sec = node.closest('[data-section]');
		return { kind: kind, el: s, sectionId: sec ? Number(sec.getAttribute('data-section')) : 0, blockId: Number(s.getAttribute('data-block') || 0), uses: Number(s.getAttribute('data-ma-uses') || 0) };
	}
	/* save(scope, fields) → Promise(res); fields is an object or URLSearchParams */
	function save(scope, fields, opts) {
		opts = opts || {};
		var body = fields instanceof URLSearchParams ? fields : new URLSearchParams();
		if (!(fields instanceof URLSearchParams)) {
			Object.keys(fields).forEach(function (k) {
				if (Array.isArray(fields[k])) { fields[k].forEach(function (v) { body.append(k, v); }); } else { body.append(k, fields[k]); }
			});
		}
		body.set('program_id', PID);
		var action = 'save_program';
		if (scope.kind === 'section') { action = 'save_section_fields'; body.set('section_id', scope.sectionId); }
		if (scope.kind === 'block') { action = 'save_block_fields'; body.set('block_id', scope.blockId); }
		if (scope.kind === 'similar') { action = 'save_similar'; }
		working(true);
		return ajax(action, body).then(function (res) {
			working(false);
			if (opts.apply && res.fields) { applyFields(opts.apply, res); } else { swap(res); }
			setStatus('Saved', 'ok');
			if (!opts.quiet) { toast(opts.note || (res.message && scope.kind === 'block' ? res.message : 'Saved'), { undo: opts.undo }); }
			return res;
		}).catch(function (ex) {
			working(false);
			setStatus('Not saved', 'error');
			toast(ex.message || 'Not saved', { kind: 'error' });
			throw ex;
		});
	}
	/* A text/rich-text save updates just its element (the page keeps whatever else is being edited); the title follows a rename. */
	function applyFields(apply, res) {
		var node = apply.node, field = apply.field, val = res.fields[field];
		if (!document.contains(node) || val === undefined) { swap(res); return; }
		if (apply.kind === 'html') {
			node.innerHTML = val !== '' ? val : '<p class="ma-ph-text">Click to write the text.</p>';
		} else {
			node.textContent = val !== '' ? val : (apply.placeholder || '');
		}
		if (val === '') { node.setAttribute('data-ma-empty', '1'); } else { node.removeAttribute('data-ma-empty'); }
		if (res.title) {
			document.title = 'Editing: ' + res.title;
			var t = document.querySelector('[data-ma-title]'); if (t) { t.textContent = res.title; }
			var h1 = document.querySelector('main h1'); if (h1) { h1.textContent = 'Details: ' + res.title; }
		}
	}
	function act(action, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (k) { if (data[k] != null) { body.append(k, data[k]); } });
		body.set('program_id', PID);
		working(true);
		return ajax(action, body).then(function (res) { working(false); swap(res); setStatus('Saved', 'ok'); return res; })
			.catch(function (ex) { working(false); setStatus('Not saved', 'error'); toast(ex.message || 'Failed', { kind: 'error' }); throw ex; });
	}

	/* Shared text: ask before editing. Resolves with a scope to edit in (block, or the detached section). */
	function resolveShared(node, scope) {
		if (scope.kind !== 'block') { return Promise.resolve(scope); }
		return new Promise(function (resolve, reject) {
			var n = scope.uses;
			var settled = false;
			function done(fn) { settled = true; pop.close(); fn(); }
			var box = el('div', {},
				el('p', { text: 'This text is shared: the same words appear on ' + n + ' page' + (n === 1 ? '' : 's') + '.' }),
				el('div', { class: 'ma-btn-row ma-btn-row--stack' },
					el('button', { type: 'button', class: 'ma-btn ma-btn--accent', text: 'Customize this page only (gives it its own copy)', onclick: function () {
						done(function () {
							var sid = scope.sectionId;
							act('detach_section', { section_id: sid }).then(function () {
								var sec = document.querySelector('[data-section="' + sid + '"]');
								resolve(sec ? scopeOf(sec) : null);
							}).catch(reject);
						});
					} }),
					el('button', { type: 'button', class: 'ma-btn ma-btn--danger', text: 'Change the shared text on all ' + n + ' pages', onclick: function () {
						if (n >= 20 && !window.confirm('This changes the text on ' + n + ' program pages at once. Continue?')) { return; }
						done(function () { resolve(scope); });
					} }),
					el('button', { type: 'button', class: 'ma-btn ma-btn--ghost', text: 'Cancel', onclick: function () { done(function () { reject(new Error('cancelled')); }); } })
				)
			);
			var pop = U.popover(node, { title: 'Shared text', content: box, width: 440, onClose: function () { if (!settled) { reject(new Error('cancelled')); } } });
		});
	}
	/* the same field on the same section after a detach + swap */
	function sameField(scope, kind, field) {
		var sec = scope.el && scope.el.closest ? document.querySelector('[data-section="' + scope.sectionId + '"]') : null;
		return sec ? sec.querySelector('[data-ma-' + kind + '="' + field + '"]') : null;
	}

	/* ---------------------------------------------------------------- text */
	var supportsPlain = (function () { try { var d = document.createElement('div'); d.contentEditable = 'plaintext-only'; return d.contentEditable === 'plaintext-only'; } catch (e) { return false; } })();
	var activeText = null;
	function startText(node) {
		if (activeText) { activeText.commit(); }
		var scope0 = scopeOf(node);
		resolveShared(node, scope0).then(function (scope) {
			if (!scope) { return; }
			if (scope !== scope0) { node = sameField(scope, 'text', node.getAttribute('data-ma-text')) || node; }
			var field = node.getAttribute('data-ma-text');
			var wasEmpty = node.hasAttribute('data-ma-empty');
			var placeholder = wasEmpty ? node.textContent : '';
			var old = wasEmpty ? '' : node.textContent;
			if (wasEmpty) { node.textContent = ''; }
			node.setAttribute('contenteditable', supportsPlain ? 'plaintext-only' : 'true');
			node.setAttribute('spellcheck', 'true');
			node.classList.add('ma-editing');
			node.focus();
			var range = document.createRange(); range.selectNodeContents(node); range.collapse(false);
			var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
			var done = false;
			var teardown = function () {
				done = true;
				node.removeAttribute('contenteditable');
				node.removeAttribute('spellcheck');
				node.classList.remove('ma-editing');
				node.removeEventListener('keydown', onKey);
				node.removeEventListener('blur', onBlur);
				node.removeEventListener('paste', onPaste);
				activeText = null;
			};
			function restore() { node.textContent = wasEmpty ? placeholder : old; }
			function cancel() { if (done) { return; } teardown(); restore(); }
			function commit() {
				if (done) { return; }
				var val = node.textContent.replace(/\s+/g, ' ').trim();
				teardown();
				if (val === old.trim()) { restore(); return; }
				var f = {}; f[field] = val;
				var note = null;
				if (field === 'headline' && /^(Curriculum|Admission to the program|How to enroll|Careers)$/.test(old.trim()) && val !== old.trim()) {
					note = 'Saved. Note: degree maps are listed on the card headed "Curriculum", and the search summary reads the standard headlines.';
				}
				var undo = function () { var g = {}; g[field] = old; save(scope, g, { quiet: true, apply: { node: node, field: field, kind: 'text', placeholder: placeholder } }); };
				save(scope, f, { undo: undo, note: note, apply: { node: node, field: field, kind: 'text', placeholder: placeholder } }).catch(function () { restore(); });
			}
			function onKey(e) {
				if (e.isComposing || e.keyCode === 229) { return; }   // IME composition in progress
				if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); node.blur(); }
				else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); node.textContent = old; node.blur(); }
			}
			function onBlur() { commit(); }
			function onPaste(e) {
				if (supportsPlain) { return; }
				e.preventDefault();
				document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain').replace(/\s+/g, ' '));
			}
			function onDocDown(e) { if (!node.contains(e.target)) { commit(); } }
			node.addEventListener('keydown', onKey);
			node.addEventListener('blur', onBlur);
			node.addEventListener('paste', onPaste);
			document.addEventListener('mousedown', onDocDown, true);
			var teardown0 = teardown;
			teardown = function () { document.removeEventListener('mousedown', onDocDown, true); teardown0(); };
			activeText = { commit: commit, cancel: cancel };
		}).catch(function () { /* cancelled */ });
	}

	/* ---------------------------------------------------------------- rich text */
	var activeHtml = null;
	function startHtml(node) {
		if (!window.BalloonEditor) { toast('The text editor did not load. Reload the page and try again.', { kind: 'error' }); return; }
		if (activeHtml) { activeHtml.commit(); }
		var scope0 = scopeOf(node);
		resolveShared(node, scope0).then(function (scope) {
			if (!scope) { return; }
			if (scope !== scope0) { node = sameField(scope, 'html', node.getAttribute('data-ma-html')) || node; }
			var field = node.getAttribute('data-ma-html');
			var wasEmpty = node.hasAttribute('data-ma-empty');
			var oldHtml = node.innerHTML;
			if (wasEmpty) { node.innerHTML = ''; }
			node.classList.add('ma-editing', 'ma-editing--html');
			window.BalloonEditor.create(node, {
				removePlugins: ['Image', 'ImageCaption', 'ImageStyle', 'ImageToolbar', 'ImageUpload', 'EasyImage', 'CKBox', 'CKFinder', 'CKFinderUploadAdapter', 'CloudServices', 'MediaEmbed', 'AutoMediaEmbed', 'Table', 'TableToolbar', 'BlockQuote', 'Indent', 'TextTransformation', 'PictureEditing'],
				toolbar: ['bold', 'italic', 'link', '|', 'undo', 'redo'],
				blockToolbar: ['heading', '|', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
				heading: { options: [{ model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' }, { model: 'heading3', view: 'h3', title: 'Sub-heading', class: 'ck-heading_heading3' }, { model: 'heading4', view: 'h4', title: 'Small sub-heading', class: 'ck-heading_heading4' }] },
				ui: { poweredBy: { position: 'border' } },
				link: { defaultProtocol: 'https://', decorators: { openInNewTab: { mode: 'manual', label: 'Open in new tab', attributes: { target: '_blank', rel: 'noopener' } } } }
			}).then(function (ed) {
				node._ck = ed;
				var initial = ed.getData();
				var done = false;
				ed.editing.view.focus();
				function onDocDown(e) {
					if (asking || node.contains(e.target) || e.target.closest('.ck-body-wrapper, .ck-balloon-panel, .ck-block-toolbar-button')) { return; }
					commit();
				}
				document.addEventListener('mousedown', onDocDown, true);
				function finish(html) {
					done = true;
					activeHtml = null;
					document.removeEventListener('mousedown', onDocDown, true);
					return ed.destroy().then(function () {
						node._ck = null;
						node.classList.remove('ma-editing', 'ma-editing--html');
						node.innerHTML = html;
					});
				}
				var asking = false;
				var applyTo = { node: node, field: field, kind: 'html' };
				function commit() {
					if (done || asking) { return; }
					var html = ed.getData();
					if (html === initial) { finish(oldHtml); return; }
					finish(html).then(function () {
						var f = {}; f[field] = html;
						var undo = function () { var g = {}; g[field] = wasEmpty ? '' : oldHtml; save(scope, g, { quiet: true, apply: applyTo }); };
						save(scope, f, { undo: undo, apply: applyTo }).catch(function () { node.innerHTML = oldHtml; });
					});
				}
				function cancel() {
					if (done) { return; }
					if (ed.getData() !== initial) {
						asking = true;
						var ok = window.confirm('Discard your changes to this text?');
						asking = false;
						if (!ok) { ed.editing.view.focus(); return; }
					}
					finish(oldHtml);
				}
				ed.ui.focusTracker.on('change:isFocused', function (evt, name, isFocused) {
					if (!isFocused) { setTimeout(function () { if (!ed.ui.focusTracker.isFocused && !asking) { commit(); } }, 150); }
				});
				ed.keystrokes.set('Esc', function (data, stop) { stop(); cancel(); });
				activeHtml = { commit: commit, cancel: cancel };
			}).catch(function (e) {
				node.classList.remove('ma-editing', 'ma-editing--html');
				node.innerHTML = oldHtml;
				toast('Could not start the editor: ' + (e && e.message ? e.message : e), { kind: 'error' });
			});
		}).catch(function () { /* cancelled */ });
	}

	/* ---------------------------------------------------------------- popover forms */
	var FORMS = {
		identity: { title: 'Program type', fields: [
			{ name: 'credential', label: 'Credential', type: 'text', list: cfg.credentials || [], help: 'Major, Minor, Master\'s, Doctorate, Graduate Certificate…' },
			{ name: 'program_type', label: 'Degree / type (BS, MA, MACC…)', type: 'text', maxlength: 60 },
			{ name: 'graduate', label: 'Graduate program (listed under Graduate Degrees)', type: 'check' },
			{ name: 'is_stem', label: 'STEM program (shows the STEM tag)', type: 'check' }
		] },
		crumbs: { title: 'College and department', fields: [
			{ name: 'college', label: 'College', type: 'text', list: cfg.colleges || [] },
			{ name: 'college_url', label: 'College page', type: 'text', placeholder: '/academics/…' },
			{ name: 'department', label: 'Department', type: 'text', list: cfg.departments || [] },
			{ name: 'department_url', label: 'Department page', type: 'text', placeholder: '/academics/…' }
		], help: 'Only entries with both a name and a link show on the page.' },
		facts: { title: 'Program details', fields: [
			{ name: 'degree_title', label: 'Degree', type: 'text', placeholder: 'PhD, MS, Graduate Certificate…', maxlength: 120 },
			{ name: 'modality', label: 'Modality', type: 'select', options: cfg.modalities || ['', 'On Campus', 'Online', 'Hybrid'] },
			{ name: 'credit_hours', label: 'Credit hours', type: 'text', placeholder: '30 or 30–36', maxlength: 40 },
			{ name: 'entry_terms', label: 'Entry term', type: 'text', placeholder: 'Fall, Spring · Any', maxlength: 80 }
		], help: 'Only the filled-in facts show; leave blank what does not apply.' },
		coordinator: { title: 'Program coordinator', fields: [
			{ name: 'coordinator_name', label: 'Name', type: 'text', maxlength: 120 },
			{ name: 'coordinator_email', label: 'Email', type: 'email', maxlength: 120 },
			{ name: 'coordinator_phone', label: 'Phone', type: 'text', maxlength: 40 }
		], help: 'Clear all three to remove the line.' },
		buttons: { title: 'Buttons', list: 'buttons', labels: ['Button text', 'Link'] },
		links: { title: 'Links', list: 'links', labels: ['Link text', 'Link'] },
		image: { title: 'Photo', image: true, fields: [
			{ name: 'image_alt', label: 'Alt text (what the photo shows, for screen readers)', type: 'text' },
			{ name: 'image_caption', label: 'Caption', type: 'text' },
			{ name: 'image_credit', label: 'Credit', type: 'text', maxlength: 255 }
		] },
		'section-image': { title: 'Photo', image: true, fields: [
			{ name: 'image_alt', label: 'Alt text (what the photo shows, for screen readers)', type: 'text' }
		] }
	};

	function listEditor(name, rows, labels) {
		var wrap = el('div', { class: 'ma-list-edit' });
		function addRow(r) {
			var row = el('div', { class: 'ma-list-edit__row' },
				el('input', { type: 'text', name: name + '[text][]', value: r.text || '', placeholder: labels[0], maxlength: 200, 'aria-label': labels[0] }),
				el('input', { type: 'text', name: name + '[href][]', value: r.href || '', placeholder: 'https://… or /academics/…', maxlength: 500, 'aria-label': labels[1] }),
				el('button', { type: 'button', class: 'ma-btn ma-btn--ghost ma-btn--small', text: '−', title: 'Remove', 'aria-label': 'Remove this row', onclick: function () { row.remove(); if (!wrap.querySelector('.ma-list-edit__row')) { addRow({}); } } }));
			wrap.insertBefore(row, add);
			return row;
		}
		var add = el('button', { type: 'button', class: 'ma-btn ma-btn--ghost ma-btn--small ma-list-edit__add', text: '+ Add another', onclick: function () { addRow({}).querySelector('input').focus(); } });
		wrap.appendChild(add);
		(rows && rows.length ? rows : [{}]).forEach(addRow);
		return wrap;
	}

	var imageCache = null;
	function imagePicker(urlInput, preview) {
		var box = el('div', { class: 'ma-picker' });
		var filter = el('input', { type: 'search', placeholder: 'Filter by file name…', 'aria-label': 'Filter photos', class: 'ma-picker__filter' });
		var grid = el('div', { class: 'ma-pick-grid', text: 'Loading…' });
		box.appendChild(el('div', { class: 'ma-field' }, filter));
		box.appendChild(grid);
		function render() {
			var q = filter.value.trim().toLowerCase();
			grid.innerHTML = '';
			var shown = 0;
			(imageCache || []).forEach(function (img) {
				if (q && img.name.toLowerCase().indexOf(q) === -1) { return; }
				if (shown++ >= 120) { return; }
				var b = el('button', { type: 'button', class: urlInput.value === img.url ? 'is-current' : '', title: img.name, onclick: function () {
					urlInput.value = img.url; preview.src = imgUrl(img.url); preview.hidden = false;
					grid.querySelectorAll('.is-current').forEach(function (x) { x.classList.remove('is-current'); });
					b.classList.add('is-current');
				} }, el('img', { src: imgUrl(img.url), alt: '', loading: 'lazy' }), el('span', { text: img.name }));
				grid.appendChild(b);
			});
			if (!shown) { grid.appendChild(el('div', { class: 'ma-pick-empty', text: imageCache && imageCache.length ? 'No photo matches.' : 'No photos on this server (the published photos live on www). Paste the address of a photo into the field above instead.' })); }
			else if (shown >= 120) { grid.appendChild(el('div', { class: 'ma-pick-empty', text: 'Showing the first 120 — type to narrow the list.' })); }
		}
		filter.addEventListener('input', render);
		if (imageCache) { render(); } else {
			ajax('list_images', {}, { method: 'GET' }).then(function (r) { imageCache = r.images || []; render(); }).catch(function (ex) { grid.textContent = 'Could not list the photos: ' + ex.message; });
		}
		return box;
	}
	function imgUrl(u) { return (cfg.imageBase && u.charAt(0) === '/') ? cfg.imageBase.replace(/\/$/, '') + u : u; }

	function openForm(node) {
		var name = node.getAttribute('data-ma-form');
		var spec = FORMS[name];
		if (!spec) { return; }
		var values = {};
		try { values = JSON.parse(node.getAttribute('data-ma-json') || '{}'); } catch (e) { values = {}; }
		var scope0 = scopeOf(node);
		resolveShared(node, scope0).then(function (scope) {
			if (!scope) { return; }
			if (scope !== scope0) { node = sameField(scope, 'form', name) || node; }
			var form = el('form', { class: 'ma-form' });
			var pop;
			if (spec.list) {
				form.appendChild(listEditor(spec.list, values[spec.list] || [], spec.labels));
			}
			if (spec.image) {
				var urlInput = el('input', { type: 'text', name: 'image_url', value: values.image_url || '', placeholder: '/academics/majors/_images/… or https://…', maxlength: 255, autocomplete: 'off' });
				var preview = el('img', { class: 'ma-preview', alt: '', hidden: !values.image_url, src: values.image_url ? imgUrl(values.image_url) : '' });
				urlInput.addEventListener('change', function () { preview.src = urlInput.value ? imgUrl(urlInput.value) : ''; preview.hidden = !urlInput.value; });
				var pickerWrap = el('div', { hidden: true });
				form.appendChild(preview);
				form.appendChild(el('div', { class: 'ma-field' }, el('label', { text: 'Photo (address)' }), urlInput));
				form.appendChild(el('div', { class: 'ma-btn-row ma-btn-row--tight' },
					el('button', { type: 'button', class: 'ma-btn ma-btn--ghost ma-btn--small', text: 'Browse photos on the site', onclick: function () {
						pickerWrap.hidden = !pickerWrap.hidden;
						if (!pickerWrap.hidden && !pickerWrap.firstChild) { pickerWrap.appendChild(imagePicker(urlInput, preview)); }
						pop.reposition();
					} }),
					values.image_url ? el('button', { type: 'button', class: 'ma-btn ma-btn--ghost ma-btn--small', text: 'Remove photo', onclick: function () { urlInput.value = ''; preview.hidden = true; form.requestSubmit(); } }) : null));
				form.appendChild(pickerWrap);
			}
			(spec.fields || []).forEach(function (f) { form.appendChild(U.field(Object.assign({}, f, { value: values[f.name] }))); });
			if (spec.help) { form.appendChild(el('div', { class: 'ma-help', text: spec.help })); }
			var err = el('div', { class: 'ma-error' });
			form.appendChild(err);
			form.appendChild(el('div', { class: 'ma-btn-row' },
				el('button', { type: 'submit', class: 'ma-btn ma-btn--accent', text: 'Save' }),
				el('button', { type: 'button', class: 'ma-btn ma-btn--ghost', text: 'Cancel', onclick: function () { pop.close(); } })));
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var body = U.serialize(form);
				if (spec.list && !body.has(spec.list + '[text][]')) { body.append(spec.list + '[text][]', ''); body.append(spec.list + '[href][]', ''); }
				save(scope, body).then(function () { pop.close(); }).catch(function (ex) { err.textContent = ex.message; });
			});
			pop = U.popover(node, { title: spec.title, content: form, width: spec.image ? 520 : 440, className: spec.image ? 'ma-popover--wide' : '' });
		}).catch(function () { /* cancelled */ });
	}

	/* ---------------------------------------------------------------- sections */
	/* Many blocks share a headline (one "Admission to the program" per college), so the picker shows college and text. */
	function blockSelect(onPick, anchor, title) {
		var blocks = cfg.blocks || [];
		var filter = el('input', { type: 'search', placeholder: 'Filter by headline, college or text…', 'aria-label': 'Filter shared text' });
		var list = el('div', { class: 'ma-results ma-results--blocks' });
		function render() {
			var q = filter.value.trim().toLowerCase();
			list.innerHTML = '';
			blocks.forEach(function (b) {
				var hay = (b.headline + ' ' + b.colleges + ' ' + b.excerpt).toLowerCase();
				if (q && hay.indexOf(q) === -1) { return; }
				list.appendChild(el('button', { type: 'button', class: 'ma-result ma-result--block', onclick: function () { pop.close(); onPick(b.id); } },
					el('strong', { text: b.headline }), el('span', { class: 'ma-result__meta', text: (b.colleges ? b.colleges + ' · ' : '') + b.uses + ' page' + (b.uses === 1 ? '' : 's') }),
					el('span', { class: 'ma-result__excerpt', text: b.excerpt })));
			});
			if (!list.firstChild) { list.appendChild(el('div', { class: 'ma-pick-empty', text: blocks.length ? 'Nothing matches.' : 'There are no shared blocks yet. Create one under Shared blocks first.' })); }
		}
		filter.addEventListener('input', render);
		var box = el('div', {},
			el('p', { text: 'Shared text shows the same words on every page that uses it; change it once under Shared blocks and all of them change.' }),
			el('div', { class: 'ma-field' }, filter), list,
			el('div', { class: 'ma-btn-row' }, el('button', { type: 'button', class: 'ma-btn ma-btn--ghost', text: 'Cancel', onclick: function () { pop.close(); } })));
		var pop = U.popover(anchor, { title: title, content: box, width: 520, className: 'ma-popover--wide' });
		render();
	}
	function handleAct(btn, e) {
		e.preventDefault();
		var a = btn.getAttribute('data-ma-act');
		var tools = btn.closest('[data-ma-tools]');
		var sid = tools ? Number(tools.getAttribute('data-ma-section')) : 0;
		var sec = sid ? document.querySelector('[data-section="' + sid + '"]') : null;
		var shared = sec && sec.getAttribute('data-ma-scope') === 'block';
		if (a === 'move') { act('move_section', { section_id: sid, dir: btn.getAttribute('data-dir') }); return; }
		if (a === 'add') { act('add_section', { kind: btn.getAttribute('data-kind'), after: btn.getAttribute('data-after') || 0 }).then(function (r) { scrollToSection(r.section_id); }); return; }
		if (a === 'add-shared') { blockSelect(function (bid) { act('add_section', { kind: 'teaser', after: btn.getAttribute('data-after') || 0, block_id: bid }).then(function (r) { scrollToSection(r.section_id); }); }, btn, 'Add shared text'); return; }
		if (a === 'add-after') {
			var box = el('div', { class: 'ma-btn-row ma-btn-row--stack' },
				el('button', { type: 'button', class: 'ma-btn ma-btn--accent', text: '+ Card (two across)', onclick: function () { pop.close(); act('add_section', { kind: 'teaser', after: sid }).then(function (r) { scrollToSection(r.section_id); }); } }),
				el('button', { type: 'button', class: 'ma-btn ma-btn--accent', text: '+ Feature (full width, with photo)', onclick: function () { pop.close(); act('add_section', { kind: 'feature', after: sid }).then(function (r) { scrollToSection(r.section_id); }); } }),
				el('button', { type: 'button', class: 'ma-btn', text: '+ Shared text…', onclick: function () { pop.close(); blockSelect(function (bid) { act('add_section', { kind: 'teaser', after: sid, block_id: bid }).then(function (r) { scrollToSection(r.section_id); }); }, btn, 'Add shared text'); } }));
			var pop = U.popover(btn, { title: 'Add after this section', content: box, width: 320 });
			return;
		}
		if (a === 'section-menu') {
			var items = el('div', { class: 'ma-btn-row ma-btn-row--stack' });
			if (shared) {
				items.appendChild(el('button', { type: 'button', class: 'ma-btn', text: 'Customize: give this page its own copy', onclick: function () { menu.close(); act('detach_section', { section_id: sid }); } }));
			} else {
				items.appendChild(el('button', { type: 'button', class: 'ma-btn', text: 'Use shared text instead…', onclick: function () { menu.close(); blockSelect(function (bid) { act('swap_section_block', { section_id: sid, block_id: bid }); }, btn, 'Use shared text'); } }));
			}
			items.appendChild(el('button', { type: 'button', class: 'ma-btn ma-btn--danger', text: 'Remove this section from the page', onclick: function () {
				menu.close();
				act('delete_section', { section_id: sid }).then(function (r) {
					var rm = r.removed || {};
					toast(shared ? 'Section removed (the shared text stays on the other pages).' : 'Section removed.', { undo: function () {
						var body = { kind: rm.kind, after: rm.after, label: rm.label, headline: rm.headline, body: rm.body, image_url: rm.image_url, image_alt: rm.image_alt, block_id: rm.block_id || null };
						var links = []; try { links = JSON.parse(rm.links || '[]'); } catch (e) { links = []; }
						var q = new URLSearchParams();
						Object.keys(body).forEach(function (k) { if (body[k] != null) { q.append(k, body[k]); } });
						(links.length ? links : [{ text: '', href: '' }]).forEach(function (l) { q.append('links[text][]', l.text || ''); q.append('links[href][]', l.href || ''); });
						q.set('program_id', PID);
						working(true);
						ajax('add_section', q).then(function (res) { working(false); swap(res); setStatus('Saved', 'ok'); scrollToSection(res.section_id); })
							.catch(function (ex) { working(false); toast(ex.message, { kind: 'error' }); });
					} });
				});
			} }));
			var menu = U.popover(btn, { title: 'This section', content: items, width: 320 });
			return;
		}
		if (a === 'settings') { openSettings(btn); return; }
	}
	function scrollToSection(id) {
		var s = id ? document.querySelector('[data-section="' + id + '"]') : null;
		if (s) { s.scrollIntoView({ behavior: 'smooth', block: 'center' }); s.classList.add('ma-flash'); setTimeout(function () { s.classList.remove('ma-flash'); }, 1600); }
	}

	/* ---------------------------------------------------------------- similar programs */
	function similarIds() {
		return Array.prototype.map.call(document.querySelectorAll('[data-ma-similar]'), function (n) { return Number(n.getAttribute('data-ma-similar')); }).filter(Boolean);
	}
	function saveSimilar(ids, previous) {
		var body = new URLSearchParams();
		ids.forEach(function (id) { body.append('similar[]', id); });
		if (!ids.length) { body.append('similar[]', ''); }
		return save({ kind: 'similar' }, body, { quiet: true }).then(function () {
			toast('Similar programs saved', { undo: previous ? function () { saveSimilar(previous); } : null });
		});
	}
	function openSimilarAdd(btn) {
		var input = el('input', { type: 'search', placeholder: 'Type a program name…', 'aria-label': 'Find a program', autocomplete: 'off' });
		var list = el('div', { class: 'ma-results' });
		var box = el('div', {}, el('div', { class: 'ma-field' }, input), list);
		var pop = U.popover(btn, { title: 'Add a similar program', content: box, width: 420 });
		var t = null;
		input.addEventListener('input', function () {
			clearTimeout(t);
			var q = input.value.trim();
			if (q.length < 2) { list.innerHTML = ''; return; }
			t = setTimeout(function () {
				ajax('program_search', { q: q }, { method: 'GET' }).then(function (r) {
					list.innerHTML = '';
					var have = similarIds();
					(r.results || []).filter(function (x) { return x.id !== PID && have.indexOf(x.id) === -1; }).slice(0, 12).forEach(function (x) {
						list.appendChild(el('button', { type: 'button', class: 'ma-result', text: x.text, onclick: function () { pop.close(); saveSimilar(have.concat([x.id])); } }));
					});
					if (!list.firstChild) { list.appendChild(el('div', { class: 'ma-pick-empty', text: 'No other program matches.' })); }
				}).catch(function (ex) { list.textContent = ex.message; });
			}, 200);
		});
	}

	/* ---------------------------------------------------------------- page settings */
	function openSettings(btn) {
		ajax('get_settings_form', { program_id: PID }, { method: 'GET' }).then(function (html) {
			var pop = U.popover(btn, { title: 'Page settings', content: html, width: 640, className: 'ma-popover--wide', sticky: true });
			var form = pop.el.querySelector('form');
			var base = form.querySelector('[name=basename]');
			var echo = form.querySelector('[data-ma-basename-echo]');
			if (base && echo) { base.addEventListener('input', function () { echo.textContent = base.value; }); }
			form.querySelector('[data-ma-cancel]').addEventListener('click', function () { pop.close(); });
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var body = U.serialize(form);
				var newBase = base ? base.value.trim() : '';
				save({ kind: 'program' }, body, { quiet: true }).then(function () {
					pop.close();
					toast('Settings saved');
					var retired = document.querySelector('[data-ma-retired]');
					if (retired) { retired.hidden = form.querySelector('[name=status]').value !== 'retired'; }
					var here = new URLSearchParams(window.location.search).get('program') || '';
					if (newBase && here && newBase !== here) {
						// the page name changed: move to the new address so a reload keeps working
						window.location = window.location.pathname + '?program=' + encodeURIComponent(newBase);
					}
				}).catch(function (ex) { var er = form.querySelector('.ma-error') || form.appendChild(el('div', { class: 'ma-error' })); er.textContent = ex.message; });
			});
		}).catch(function (ex) { toast(ex.message, { kind: 'error' }); });
	}

	/* ---------------------------------------------------------------- wiring */
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!(t instanceof Element)) { return; }
		if (t.closest('.ma-popover, .ma-toasts, .ck-body-wrapper, .ma-edit-bar a')) { return; }
		var actBtn = t.closest('[data-ma-act]');
		if (actBtn) { handleAct(actBtn, e); return; }
		var rm = t.closest('[data-ma-similar-remove]');
		if (rm) { e.preventDefault(); var id = Number(rm.getAttribute('data-ma-similar-remove')); var before = similarIds(); saveSimilar(before.filter(function (x) { return x !== id; }), before); return; }
		if (t.closest('[data-ma-similar-add]')) { e.preventDefault(); openSimilarAdd(t.closest('[data-ma-similar-add]')); return; }
		if (t.closest('[data-ma-part="similar"] a')) { e.preventDefault(); toast('Use × to remove a program, or the tile to add one.'); return; }
		if (t.closest('[data-ma-static]')) { e.preventDefault(); toast('Degree maps are linked to the program from the degree-maps admin.'); return; }
		if (t.closest('.ma-editing')) { return; }   // clicks inside an open editor
		var textEl = t.closest('[data-ma-text]');
		if (textEl) { e.preventDefault(); startText(textEl); return; }
		var htmlEl = t.closest('[data-ma-html]');
		if (htmlEl) { e.preventDefault(); startHtml(htmlEl); return; }
		var formEl = t.closest('[data-ma-form]');
		if (formEl) { e.preventDefault(); openForm(formEl); return; }
	});
	document.addEventListener('keydown', function (e) {
		if ((e.key !== 'Enter' && e.key !== ' ') || e.defaultPrevented) { return; }   // an editor's own Enter handler already used the key
		var t = e.target;
		if (!(t instanceof Element) || t.closest('.ma-editing, .ma-popover, input, textarea, select, button, a')) { return; }
		if (t.matches('[data-ma-text], [data-ma-html], [data-ma-form], [data-ma-ph]')) { e.preventDefault(); t.click(); }
	});
	window.addEventListener('beforeunload', function (e) {
		if (busy > 0) { e.preventDefault(); e.returnValue = ''; }
	});
	prime();
	document.body.classList.add('ma-ready');
})();
