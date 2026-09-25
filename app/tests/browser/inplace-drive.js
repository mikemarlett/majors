/*
 * Browser check for the Majors in-place editor: drives headless Chromium over
 * the DevTools protocol (no puppeteer), signs in through the dev provider,
 * clicks through text/rich-text/popover/section/similar/settings/photo flows
 * on a real program and screenshots each step. Needs chromium and `ws`:
 *
 *   cd app/tests/browser && npm install --no-save ws@8
 *   php -S 0.0.0.0:8091 -t /data/www/main-dev &            # or any server with dev auth
 *   node inplace-drive.js http://127.0.0.1:8091 accountancy_macc_20 /tmp/shots
 *
 * The run edits the given program and puts everything back (Undo / restore),
 * apart from the section it adds and removes. Exit code 1 on any failure.
 */
const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const WebSocket = require('ws');

const [base, basename, shots] = process.argv.slice(2);
const port = 9222 + Math.floor(Math.random() * 500);
const chrome = spawn(process.env.CHROMIUM || '/usr/bin/chromium', ['--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${port}`, '--window-size=1400,1200', 'about:blank'], { stdio: 'ignore' });
const results = [];
function ok(name, cond, extra) { results.push({ name, ok: !!cond, extra }); console.log((cond ? '  ok   ' : '  FAIL ') + name + (extra ? ' — ' + extra : '')); }
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

async function getJson(url) { return new Promise((res, rej) => http.get(url, r => { let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d))); }).on('error', rej)); }

(async () => {
  let targets;
  for (let i = 0; i < 40; i++) { try { targets = await getJson(`http://127.0.0.1:${port}/json`); break; } catch (e) { await sleep(250); } }
  const page = targets.find(t => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise(r => ws.on('open', r));
  let id = 0; const pending = new Map(); const events = [];
  const consoleErrors = [];
  ws.on('message', m => {
    const msg = JSON.parse(m);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
    else if (msg.method) {
      events.push(msg);
      if (msg.method === 'Runtime.exceptionThrown') consoleErrors.push('exception: ' + JSON.stringify(msg.params.exceptionDetails.exception && msg.params.exceptionDetails.exception.description || msg.params.exceptionDetails.text).slice(0, 300));
      if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') consoleErrors.push('console.error: ' + msg.params.args.map(a => a.value || a.description).join(' ').slice(0, 300));
      if (msg.method === 'Page.javascriptDialogOpening') { send('Page.handleJavaScriptDialog', { accept: true }); }
    }
  });
  function send(method, params) { return new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params: params || {} })); }); }
  async function ev(expr, opts) {
    const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true, ...(opts || {}) });
    if (r.result.exceptionDetails) throw new Error('eval: ' + (r.result.exceptionDetails.exception && r.result.exceptionDetails.exception.description || r.result.exceptionDetails.text));
    return r.result.result.value;
  }
  async function shot(name) { const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }); fs.writeFileSync(`${shots}/${name}.png`, Buffer.from(r.result.data, 'base64')); }
  async function nav(url) { await send('Page.navigate', { url }); await sleep(2500); }
  async function click(selector, opts) {
    const box = await ev(`(function(){var n=document.querySelector(${JSON.stringify(selector)}); if(!n) return null; n.scrollIntoView({block:'center'}); var r=n.getBoundingClientRect(); return {x:r.left+Math.min(r.width/2, 40), y:r.top+Math.min(r.height/2, 20), w:r.width, h:r.height};})()`);
    if (!box) throw new Error('no element ' + selector);
    await sleep(150);
    const b2 = await ev(`(function(){var r=document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect(); return {x:r.left+Math.min(r.width/2, 40), y:r.top+Math.min(r.height/2, 20)};})()`);
    await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: b2.x, y: b2.y });
    await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: b2.x, y: b2.y, button: 'left', clickCount: 1 });
    await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: b2.x, y: b2.y, button: 'left', clickCount: 1 });
    await sleep((opts && opts.wait) || 400);
  }
  async function type(text) { for (const ch of text) { await send('Input.dispatchKeyEvent', { type: 'keyDown', text: ch, key: ch }); await send('Input.dispatchKeyEvent', { type: 'keyUp', key: ch }); } }
  async function key(k, code) { await send('Input.dispatchKeyEvent', { type: 'keyDown', key: k, code: code || k, windowsVirtualKeyCode: k === 'Enter' ? 13 : k === 'Escape' ? 27 : k === 'Tab' ? 9 : 0 }); await send('Input.dispatchKeyEvent', { type: 'keyUp', key: k, code: code || k, windowsVirtualKeyCode: k === 'Enter' ? 13 : k === 'Escape' ? 27 : k === 'Tab' ? 9 : 0 }); }

  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Emulation.setFocusEmulationEnabled', { enabled: true });
  await send('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1200, deviceScaleFactor: 1, mobile: false });
  const ret = encodeURIComponent(`/academics/majors/_admin/program.php?program=${basename}`);
  await nav(`${base}/academics/majors/auth/login.php?as=mia.marketing@wichita.edu&return=${ret}`);
  await sleep(1500);
  ok('editor page loaded', await ev(`!!document.querySelector('[data-ma-edit-bar]')`));
  ok('inplace.js ready', await ev(`document.body.classList.contains('ma-ready')`));
  ok('balloon editor loaded', await ev(`typeof window.BalloonEditor === 'function'`));
  ok('no console errors at load', consoleErrors.length === 0, consoleErrors.join(' | '));

  // 1. text edit: learn_how → Enter saves → parts swapped
  const learn0 = await ev(`document.querySelector('[data-ma-text="learn_how"]').textContent.trim()`);
  await ev(`window.__probe = document.querySelector('[data-ma-html="description"]'); true`);
  await click('[data-ma-text="learn_how"]');
  ok('learn_how becomes editable on click', await ev(`document.querySelector('[data-ma-text="learn_how"]').isContentEditable`));
  await ev(`(function(){var n=document.querySelector('[data-ma-text="learn_how"]'); n.textContent='Learn how (CDP test)'; return true;})()`);
  await key('Enter');
  await sleep(1500);
  ok('text saved in place', (await ev(`document.querySelector('[data-ma-text="learn_how"]').textContent.trim()`)) === 'Learn how (CDP test)' && !(await ev(`document.querySelector('[data-ma-text="learn_how"]').isContentEditable`)));
  ok('text save did not re-render the page (node identity kept)', await ev(`window.__probe === document.querySelector('[data-ma-html="description"]')`));
  ok('toast with Undo shown', await ev(`!!document.querySelector('.ma-toast .ma-toast__undo')`));
  await shot('1-after-text-save');
  await click('.ma-toast__undo'); await sleep(1500);
  ok('undo restored the old text', (await ev(`document.querySelector('[data-ma-text="learn_how"]').textContent.trim()`)) === learn0, await ev(`document.querySelector('[data-ma-text="learn_how"]').textContent.trim()`));

  // 2. Escape cancels a text edit
  await click('[data-ma-text="academic_program"]');
  await ev(`(function(){var n=document.querySelector('[data-ma-text="academic_program"]'); n.textContent='SHOULD NOT SAVE'; return true;})()`);
  await key('Escape'); await sleep(800);
  ok('Escape restores the name', (await ev(`document.querySelector('[data-ma-text="academic_program"]').textContent.trim()`)) !== 'SHOULD NOT SAVE');

  // 3. rich text: description → balloon editor → type → click elsewhere → saved
  const desc0 = await ev(`document.querySelector('[data-ma-html="description"]').innerHTML`);
  await click('[data-ma-html="description"]', { wait: 1200 });
  ok('balloon editor attached to the description', await ev(`!!document.querySelector('[data-ma-html="description"].ck-editor__editable')`));
  await shot('2-rich-editing');
  await ev(`(function(){var n=document.querySelector('[data-ma-html="description"]'); n._ck.model.change(function(w){ n._ck.model.insertContent(w.createText(' CDPRICH'), n._ck.model.document.selection.getLastPosition()); }); return true;})()`);
  await click('[data-ma-edit-bar] .ma-edit-bar__hint', { wait: 2000 });
  ok('rich text saved after blur', (await ev(`document.querySelector('[data-ma-html="description"]').innerHTML`)).indexOf('CDPRICH') !== -1);
  ok('editor detached after save', !(await ev(`!!document.querySelector('[data-ma-html="description"].ck-editor__editable')`)));
  await click('.ma-toast__undo'); await sleep(1500);
  ok('undo restored the description', (await ev(`document.querySelector('[data-ma-html="description"]').innerHTML`)).indexOf('CDPRICH') === -1);

  // 4. popover form: facts (graduate pages) or coordinator placeholder — fall back to the crumbs form on undergraduate pages
  const formSel = (await ev(`!!document.querySelector('[data-ma-form="facts"]')`)) ? '[data-ma-form="facts"]' : '[data-ma-form="crumbs"]';
  const formField = formSel.indexOf('facts') !== -1 ? 'credit_hours' : 'college_url';
  await click(formSel);
  ok('popover form opened (' + formField + ')', await ev(`!!document.querySelector('.ma-popover input[name=${formField}]')`));
  await shot('3-form-popover');
  const val0 = await ev(`document.querySelector('.ma-popover input[name=${formField}]').value`);
  await ev(`(function(){var i=document.querySelector('.ma-popover input[name=${formField}]'); i.value='${formField === 'credit_hours' ? '77' : '/academics/cdp-test/'}'; return true;})()`);
  await click('.ma-popover button[type=submit]', { wait: 1500 });
  ok('form saved and the page shows it', (await ev(`(document.querySelector('${formSel}')||{}).outerHTML||''`)).indexOf(formField === 'credit_hours' ? '77' : '/academics/cdp-test/') !== -1);
  ok('popover closed after save', !(await ev(`!!document.querySelector('.ma-popover')`)));
  await click(formSel);
  await ev(`(function(){var i=document.querySelector('.ma-popover input[name=${formField}]'); i.value=${JSON.stringify(val0)}; return true;})()`);
  await click('.ma-popover button[type=submit]', { wait: 1500 });

  // 5. section tools: add a card after the first section, then remove it
  const n0 = await ev(`document.querySelectorAll('[data-section]').length`);
  await click('[data-ma-tools] [data-ma-act="add-after"]');
  ok('add-after menu opened', await ev(`!!document.querySelector('.ma-popover')`));
  await click('.ma-popover .ma-btn--accent', { wait: 1800 });
  const n1 = await ev(`document.querySelectorAll('[data-section]').length`);
  ok('card added after the first section', n1 === n0 + 1, `${n0} → ${n1}`);
  await shot('4-card-added');
  const newId = await ev(`(function(){var s=Array.from(document.querySelectorAll('[data-section]')).map(function(n){return Number(n.getAttribute('data-section'));}); return Math.max.apply(null, s);})()`);
  await click(`[data-section="${newId}"] [data-ma-act="section-menu"]`);
  await click('.ma-popover .ma-btn--danger', { wait: 1800 });
  ok('new card removed again', (await ev(`document.querySelectorAll('[data-section]').length`)) === n0);
  ok('removal toast offers Undo', await ev(`!!document.querySelector('.ma-toast .ma-toast__undo')`));
  await click('.ma-toast__undo'); await sleep(2000);
  ok('undo re-created the card', (await ev(`document.querySelectorAll('[data-section]').length`)) === n0 + 1);
  const newId2 = await ev(`(function(){var s=Array.from(document.querySelectorAll('[data-section]')).map(function(n){return Number(n.getAttribute('data-section'));}); return Math.max.apply(null, s);})()`);
  await click(`[data-section="${newId2}"] [data-ma-act="section-menu"]`);
  await click('.ma-popover .ma-btn--danger', { wait: 1800 });
  ok('removed for good', (await ev(`document.querySelectorAll('[data-section]').length`)) === n0);
  ok('first section cannot move up, last cannot move down', await ev(`(function(){var t=document.querySelectorAll('[data-ma-tools]'); return t[0].querySelector('[data-dir=up]').disabled && t[t.length-1].querySelector('[data-dir=down]').disabled;})()`));

  // 6. shared section prompt
  const hasShared = await ev(`!!document.querySelector('[data-ma-scope="block"] [data-ma-text="headline"]')`);
  if (hasShared) {
    await click('[data-ma-scope="block"] [data-ma-text="headline"]');
    ok('shared-text prompt shown, Customize first', await ev(`(function(){var p=document.querySelector('.ma-popover'); return !!p && p.textContent.indexOf('shared') !== -1 && /Customize/.test(p.querySelector('.ma-btn--accent').textContent);})()`));
    await shot('5-shared-prompt');
    await key('Escape'); await sleep(400);
    ok('shared prompt cancelled, headline not editable', !(await ev(`document.querySelector('[data-ma-scope="block"] [data-ma-text="headline"]').isContentEditable`)));
  }

  // 7. similar: add tile opens search
  await click('[data-ma-similar-add]');
  ok('similar search popover', await ev(`!!document.querySelector('.ma-popover input[type=search]')`));
  await ev(`(function(){var i=document.querySelector('.ma-popover input[type=search]'); i.value='engineering'; i.dispatchEvent(new Event('input')); return true;})()`);
  await sleep(1200);
  ok('search results listed', (await ev(`document.querySelectorAll('.ma-popover .ma-result').length`)) > 0);
  await shot('6-similar-search');
  await key('Escape'); await sleep(300);

  // 8. settings
  await click('[data-ma-act="settings"]', { wait: 1200 });
  ok('settings popover (page name locked for imported pages, editable otherwise)', await ev(`!!document.querySelector('.ma-popover [data-ma-settings-form]') && (!!document.querySelector('.ma-popover [name=basename]') || !!document.querySelector('.ma-popover .ma-static'))`));
  await shot('7-settings');
  await key('Escape'); await sleep(300);

  // 9. image popover with browse
  await click('[data-ma-form="image"]', { wait: 800 });
  ok('photo popover', await ev(`!!document.querySelector('.ma-popover input[name=image_url]')`));
  const browse = await ev(`(function(){var b=Array.from(document.querySelectorAll('.ma-popover button')).find(function(x){return /Browse/.test(x.textContent)}); if(b){b.click(); return true;} return false;})()`);
  await sleep(2500);
  ok('photo browser lists images', browse && (await ev(`document.querySelectorAll('.ma-popover .ma-pick-grid button').length`)) > 0);
  await shot('8-photo-browser');
  await key('Escape'); await sleep(300);

  ok('no console errors during the run', consoleErrors.length === 0, consoleErrors.join(' | '));
  const fails = results.filter(r => !r.ok).length;
  console.log(`\nPASS=${results.length - fails} FAIL=${fails}`);
  ws.close(); chrome.kill('SIGKILL');
  process.exit(fails ? 1 : 0);
})().catch(e => { console.error('driver error:', e.message); chrome.kill('SIGKILL'); process.exit(2); });
