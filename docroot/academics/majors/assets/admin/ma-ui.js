/*
 * Majors admin UI helpers shared by the in-place editor and the control panel:
 * ajax (fetch + CSRF header + JSON envelope), anchored popovers, toasts with
 * Undo, a DOM builder. No dependencies. Reads window.MajorsAdmin = {ajax, csrf}.
 */
(function () {
	'use strict';
	var cfg = window.MajorsAdmin || {};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/* el('div', {class: 'x', onclick: fn, dataset: {...}}, child, 'text', [more]) */
	function el(tag, attrs) {
		var node = document.createElement(tag);
		attrs = attrs || {};
		Object.keys(attrs).forEach(function (k) {
			var v = attrs[k];
			if (v == null || v === false) { return; }
			if (k === 'class') { node.className = v; }
			else if (k === 'text') { node.textContent = v; }
			else if (k === 'html') { node.innerHTML = v; }
			else if (k === 'dataset') { Object.keys(v).forEach(function (d) { node.dataset[d] = v[d]; }); }
			else if (k.indexOf('on') === 0 && typeof v === 'function') { node.addEventListener(k.slice(2), v); }
			else if (v === true) { node.setAttribute(k, ''); }
			else { node.setAttribute(k, v); }
		});
		for (var i = 2; i < arguments.length; i++) { append(node, arguments[i]); }
		return node;
	}
	function append(node, child) {
		if (child == null || child === false) { return; }
		if (Array.isArray(child)) { child.forEach(function (c) { append(node, c); }); }
		else if (typeof child === 'string' || typeof child === 'number') { node.appendChild(document.createTextNode(String(child))); }
		else { node.appendChild(child); }
	}

	/* ---- ajax ------------------------------------------------------------ */
	function toBody(data) {
		if (data instanceof FormData || data instanceof URLSearchParams) { return data; }
		var p = new URLSearchParams();
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (Array.isArray(v)) { v.forEach(function (x) { p.append(k, x); }); }
			else if (v != null) { p.append(k, v); }
		});
		return p;
	}
	/* ajax('save_program', {program_id: 1, learn_how: '...'}) → Promise resolving to the JSON envelope; rejects with Error(message) (+ .status, .data) */
	function ajax(action, data, opts) {
		opts = opts || {};
		var method = (opts.method || 'POST').toUpperCase();
		var url = cfg.ajax + '?action=' + encodeURIComponent(action);
		var init = { method: method, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf || '', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
		var body = toBody(data);
		if (method === 'GET') {
			var q = body instanceof URLSearchParams ? body.toString() : new URLSearchParams(body).toString();
			if (q) { url += '&' + q; }
		} else {
			init.body = body;
		}
		return fetch(url, init).then(function (res) {
			var ct = res.headers.get('Content-Type') || '';
			var isJson = ct.indexOf('application/json') !== -1;
			return (isJson ? res.json() : res.text()).then(function (payload) {
				if (res.status === 401) {
					exported.signedOut = true;                     // pages drop their "unsaved work" prompt
					window.alert('Your session has expired. Please sign in again.');
					var login = isJson && payload && payload.login;
					if (login) { window.location.href = login + (login.indexOf('?') === -1 ? '?' : '&') + 'return=' + encodeURIComponent(window.location.pathname + window.location.search); }
					else { window.location.reload(); }
					var e401 = new Error('Signed out'); e401.status = 401; throw e401;
				}
				if (!isJson) {
					if (!res.ok) { var e0 = new Error('HTTP ' + res.status); e0.status = res.status; throw e0; }
					return payload;   // HTML fragment
				}
				if (!res.ok || payload.success === false) {
					var e = new Error(payload.message || payload.error || ('HTTP ' + res.status));
					e.status = res.status; e.data = payload;
					throw e;
				}
				return payload;
			});
		});
	}

	/* ---- toasts ---------------------------------------------------------- */
	var toastHost = null;
	function toast(message, opts) {
		opts = opts || {};
		if (!toastHost) { toastHost = el('div', { class: 'ma-toasts', 'aria-live': 'polite' }); document.body.appendChild(toastHost); }
		var t = el('div', { class: 'ma-toast' + (opts.kind === 'error' ? ' ma-toast--error' : '') }, el('span', { text: message }));
		var timer;
		function close() { clearTimeout(timer); if (t.parentNode) { t.parentNode.removeChild(t); } }
		if (opts.undo) {
			t.appendChild(el('button', { type: 'button', class: 'ma-toast__undo', text: 'Undo', onclick: function () { close(); opts.undo(); } }));
		}
		t.appendChild(el('button', { type: 'button', class: 'ma-toast__close', 'aria-label': 'Dismiss', text: '×', onclick: close }));
		toastHost.appendChild(t);
		timer = setTimeout(close, opts.timeout || (opts.kind === 'error' ? 12000 : (opts.undo ? 15000 : 4000)));
		return { close: close };
	}

	/* ---- popovers -------------------------------------------------------- */
	var open = [];
	function closePopovers(except) {
		open.slice().forEach(function (p) { if (p !== except) { p.close(); } });
	}
	/* popover(anchorEl, {title, content: Node|string, width, onClose, className}) → {el, close, reposition} */
	function popover(anchor, opts) {
		opts = opts || {};
		closePopovers();
		var box = el('div', { class: 'ma-popover ' + (opts.className || ''), role: 'dialog', 'aria-modal': 'false', tabindex: '-1' });
		if (opts.title) { box.appendChild(el('div', { class: 'ma-popover__title' }, el('span', { text: opts.title }), el('button', { type: 'button', class: 'ma-popover__close', 'aria-label': 'Close', text: '×', onclick: function () { api.close(); } }))); }
		var body = el('div', { class: 'ma-popover__body' });
		if (typeof opts.content === 'string') { body.innerHTML = opts.content; } else if (opts.content) { body.appendChild(opts.content); }
		box.appendChild(body);
		if (opts.width) { box.style.width = Math.min(opts.width, window.innerWidth - 24) + 'px'; }
		document.body.appendChild(box);
		var closed = false;
		var api = {
			el: box,
			body: body,
			close: function () {
				if (closed) { return; }
				closed = true;
				document.removeEventListener('mousedown', onDown, true);
				document.removeEventListener('keydown', onKey, true);
				window.removeEventListener('resize', api.reposition);
				window.removeEventListener('scroll', api.reposition, true);
				if (box.parentNode) { box.parentNode.removeChild(box); }
				open = open.filter(function (p) { return p !== api; });
				if (opts.onClose) { opts.onClose(); }
				if (anchor && typeof anchor.focus === 'function' && document.contains(anchor) && !opts.noRefocus) { try { anchor.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
			},
			reposition: function () {
				if (!document.contains(anchor)) { return; }
				var r = anchor.getBoundingClientRect();
				var w = box.offsetWidth, h = box.offsetHeight;
				var top = r.bottom + 8, left = r.left;
				if (left + w > window.innerWidth - 12) { left = Math.max(12, window.innerWidth - 12 - w); }
				if (top + h > window.innerHeight - 12 && r.top - 8 - h > 12) { top = r.top - 8 - h; box.classList.add('ma-popover--above'); } else { box.classList.remove('ma-popover--above'); }
				if (top + h > window.innerHeight - 12) { top = Math.max(12, window.innerHeight - 12 - h); }
				box.style.top = (top + window.scrollY) + 'px';
				box.style.left = (left + window.scrollX) + 'px';
			}
		};
		function onDown(e) {
			if (box.contains(e.target) || (anchor && anchor.contains(e.target)) || e.target.closest('.ck-body-wrapper')) { return; }
			if (opts.sticky) { return; }
			api.close();
		}
		function onKey(e) {
			if (e.key !== 'Escape') { return; }
			if (!box.contains(e.target) && e.target instanceof Element && e.target.closest('.ma-editing')) { return; }   // an in-place editor behind a sticky popover handles its own Escape
			e.stopPropagation(); api.close();
		}
		setTimeout(function () {
			document.addEventListener('mousedown', onDown, true);
			document.addEventListener('keydown', onKey, true);
		}, 0);
		window.addEventListener('resize', api.reposition);
		window.addEventListener('scroll', api.reposition, true);
		open.push(api);
		api.reposition();
		var first = box.querySelector('input:not([type=hidden]), textarea, select, button.ma-btn, [contenteditable]');
		(first || box).focus({ preventScroll: true });
		return api;
	}

	/* ---- forms ----------------------------------------------------------- */
	/* field({name, label, type: text|url|email|textarea|select|check|hidden, value, options, placeholder, list, help}) */
	function field(f) {
		var id = 'maf_' + f.name.replace(/[^a-z0-9_]/gi, '_') + '_' + Math.random().toString(36).slice(2, 7);
		if (f.type === 'hidden') { return el('input', { type: 'hidden', name: f.name, value: f.value == null ? '' : f.value }); }
		if (f.type === 'check') {
			return el('label', { class: 'ma-field ma-field--check' },
				el('input', { type: 'hidden', name: f.name, value: '0' }),
				el('input', { type: 'checkbox', name: f.name, value: '1', checked: !!Number(f.value), id: id }),
				el('span', { text: f.label }));
		}
		var wrap = el('div', { class: 'ma-field' + (f.className ? ' ' + f.className : '') }, el('label', { for: id, text: f.label }));
		var input;
		if (f.type === 'textarea') {
			input = el('textarea', { id: id, name: f.name, rows: f.rows || 3, placeholder: f.placeholder }, f.value == null ? '' : String(f.value));
		} else if (f.type === 'select') {
			input = el('select', { id: id, name: f.name });
			(f.options || []).forEach(function (o) {
				var val = typeof o === 'string' ? o : o.value, lab = typeof o === 'string' ? (o === '' ? '—' : o) : o.label;
				input.appendChild(el('option', { value: val, selected: String(val) === String(f.value == null ? '' : f.value), text: lab }));
			});
		} else {
			input = el('input', { type: f.type || 'text', id: id, name: f.name, value: f.value == null ? '' : f.value, placeholder: f.placeholder, maxlength: f.maxlength, autocomplete: 'off' });
			if (f.list && f.list.length) {
				var dl = el('datalist', { id: id + '_list' });
				f.list.forEach(function (o) { dl.appendChild(el('option', { value: o })); });
				input.setAttribute('list', dl.id);
				wrap.appendChild(dl);
			}
		}
		wrap.appendChild(input);
		if (f.help) { wrap.appendChild(el('div', { class: 'ma-help', text: f.help })); }
		return wrap;
	}

	/* Serialise a form: single checkboxes post 0/1 (the hidden 0 is overridden by a checked 1);
	   checkbox groups named foo[] post every ticked value. */
	function serialize(form) {
		var out = new URLSearchParams();
		var fd = new FormData(form);
		var seen = {};
		fd.forEach(function (v, k) {
			if (!/\[\]$/.test(k) && form.querySelector('input[type=checkbox][name="' + k.replace(/"/g, '\\"') + '"]')) {
				seen[k] = v;   // last value wins: '1' when checked comes after the hidden '0'
			} else {
				out.append(k, v);
			}
		});
		Object.keys(seen).forEach(function (k) { out.append(k, seen[k]); });
		return out;
	}

	/* ---- listings popover (in-place editor and control panel) ------------ */
	/* listingsPopover(anchor, programId, onSaved(res)) — the program's listing lines, edited in a popover. */
	function listingsPopover(anchor, programId, onSaved) {
		return ajax('get_listings_form', { program_id: programId }, { method: 'GET' }).then(function (html) {
			var pop = popover(anchor, { title: 'Listings', content: html, width: 760, className: 'ma-popover--wide', sticky: true });
			var form = pop.el.querySelector('form');
			var rows = form.querySelector('[data-ma-listing-rows]');
			var tpl = form.querySelector('[data-ma-listing-template]');
			var none = form.querySelector('[data-ma-listing-none]');
			var err = form.querySelector('[data-ma-listing-error]');
			var n = 0;
			function refresh() {
				rows.querySelectorAll('[data-ma-listing-row]').forEach(function (row) {
					var cert = row.querySelector('[data-ma-cert-toggle]');
					row.querySelectorAll('[data-ma-cert-only]').forEach(function (f) { f.hidden = !(cert && cert.checked); });
				});
				none.hidden = rows.querySelector('[data-ma-listing-row]') !== null;
				pop.reposition();
			}
			form.addEventListener('change', refresh);
			form.addEventListener('click', function (e) {
				var t = e.target instanceof Element ? e.target : null;
				if (!t) { return; }
				if (t.closest('[data-ma-listing-add]')) {
					e.preventDefault();
					var holder = document.createElement('div');
					holder.innerHTML = tpl.innerHTML.replace(/__KEY__/g, 'n' + (++n));
					var row = holder.firstElementChild;
					rows.appendChild(row);
					refresh();
					row.querySelector('input').focus();
				} else if (t.closest('[data-ma-listing-remove]')) {
					e.preventDefault();
					t.closest('[data-ma-listing-row]').remove();
					refresh();
				} else if (t.closest('[data-ma-cancel]')) {
					e.preventDefault();
					pop.close();
				}
			});
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				err.textContent = '';
				ajax('save_listings', serialize(form)).then(function (res) {
					pop.close();
					toast(res.message || 'Listings saved');
					if (onSaved) { onSaved(res); }
				}).catch(function (ex) { err.textContent = ex.message; });
			});
			refresh();
			return pop;
		}).catch(function (ex) { toast(ex.message, { kind: 'error' }); });
	}

	var exported = { esc: esc, el: el, ajax: ajax, toast: toast, popover: popover, closePopovers: closePopovers, field: field, serialize: serialize, listingsPopover: listingsPopover, signedOut: false };
	window.MaUI = exported;
})();
