#!/usr/bin/env node
/*
 * USMSmart accessibility audit.
 *
 * Logs in, runs axe-core over the dashboard and the in-page dialogs, then
 * prints every WCAG 2.1 A/AA violation it finds. Exits 1 when anything at
 * "serious" or "critical" impact is reported, so it can gate a release.
 *
 * Axe is downloaded from cdnjs on first run and cached in the temp folder.
 *
 * Usage (from the project root):
 *   USM_USER=admin USM_PASS=secret node tools/axe-audit.js
 *
 * Optional env:
 *   USM_BASE_URL   default http://localhost/USM/
 *   USM_OUT        JSON report directory, default <temp>/usm-axe
 *   USM_HEADED=1   show the browser window
 *   CHROME_PATH    default "C:/Program Files/Google/Chrome/Application/chrome.exe"
 *
 * Requires Node 22+ (global WebSocket / fetch) and a running Apache + MySQL.
 */

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const baseUrl = process.env.USM_BASE_URL || 'http://localhost/USM/';
const user = process.env.USM_USER || '';
const pass = process.env.USM_PASS || '';
const outDir = process.env.USM_OUT || path.join(os.tmpdir(), 'usm-axe');
const chromePath = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const headed = process.env.USM_HEADED === '1';
const port = Number(process.env.USM_CDP_PORT || 9352);
const axeUrl = process.env.USM_AXE_URL || 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js';
const axeCache = path.join(os.tmpdir(), 'axe-core-4.10.2.min.js');
const TAGSET = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

if (!user || !pass) {
  console.error('Set USM_USER and USM_PASS, e.g. USM_USER=admin USM_PASS=secret node tools/axe-audit.js');
  process.exit(2);
}

fs.mkdirSync(outDir, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'usm-axe-profile-'));

const child = spawn(chromePath, [
  headed ? '--new-window' : '--headless=new',
  '--disable-gpu',
  '--hide-scrollbars',
  '--no-first-run',
  '--no-default-browser-check',
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${profile}`,
  'about:blank',
], { stdio: 'ignore' });

async function loadAxe() {
  if (fs.existsSync(axeCache)) return fs.readFileSync(axeCache, 'utf8');
  const response = await fetch(axeUrl);
  if (!response.ok) throw new Error(`axe-core download failed: HTTP ${response.status}`);
  const source = await response.text();
  fs.writeFileSync(axeCache, source);
  return source;
}

async function main() {
  let page;
  for (let i = 0; i < 60 && !page; i++) {
    try {
      page = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page');
    } catch (e) { /* Chrome is still starting */ }
    if (!page) await sleep(250);
  }
  if (!page) throw new Error('Chrome did not expose a debugging target');

  const ws = new WebSocket(page.webSocketDebuggerUrl);
  let nextId = 0;
  const pending = new Map();
  await new Promise((resolve, reject) => {
    ws.addEventListener('open', resolve);
    ws.addEventListener('error', reject);
  });
  ws.addEventListener('message', (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
  });
  const send = (method, params = {}) => new Promise((resolve) => {
    const id = ++nextId;
    pending.set(id, resolve);
    ws.send(JSON.stringify({ id, method, params }));
  });
  const evaluate = async (expression) => {
    const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (res.result?.exceptionDetails) throw new Error(res.result.exceptionDetails.exception?.description || res.result.exceptionDetails.text || 'evaluate failed');
    return res.result?.result?.value;
  };
  const waitFor = async (expression, timeout = 20000) => {
    const until = Date.now() + timeout;
    while (Date.now() < until) {
      if (await evaluate(expression)) return true;
      await sleep(250);
    }
    return false;
  };

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });

  await send('Page.navigate', { url: baseUrl });
  if (!(await waitFor(`!!document.getElementById('authForm')`))) throw new Error('Login form not found');

  const axeSource = await loadAxe();
  await evaluate(axeSource + '\n;true');

  const run = async (label) => evaluate(`(async () => {
    const result = await axe.run(document, {
      runOnly: { type: 'tag', values: ${JSON.stringify(TAGSET)} },
      resultTypes: ['violations']
    });
    return result.violations.map(v => ({
      id: v.id, impact: v.impact, help: v.help, helpUrl: v.helpUrl,
      nodes: v.nodes.slice(0, 8).map(n => ({ target: n.target.join(' '), summary: (n.failureSummary || '').split('\\n').slice(0, 2).join(' ') }))
    }));
  })()`);

  const report = {};
  let blocking = 0;
  const record = (name, violations) => {
    report[name] = violations;
    const serious = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    blocking += serious.length;
    console.log(`\n${name}: ${violations.length} violation(s)${serious.length ? `, ${serious.length} serious/critical` : ''}`);
    for (const v of violations) {
      console.log(`  [${v.impact}] ${v.id} — ${v.help}`);
      for (const node of v.nodes) console.log(`      ${node.target}${node.summary ? ' — ' + node.summary : ''}`);
    }
  };

  record('login', await run('login'));

  await evaluate(`(()=>{
    document.querySelector('input[name=username]').value=${JSON.stringify(user)};
    document.getElementById('authPassword').value=${JSON.stringify(pass)};
    document.getElementById('authForm').requestSubmit();
    return true;
  })()`);
  if (!(await waitFor(`!!document.getElementById('usm-header-profile')`, 25000))) throw new Error('Login failed; check USM_USER / USM_PASS');
  await sleep(2500);

  // The form submit navigates, so the injected copy of axe is gone.
  await evaluate(axeSource + '\n;true');

  const states = [
    ['dashboard-dark', `document.documentElement.classList.add('dark')`],
    ['dashboard-light', `document.documentElement.classList.remove('dark')`],
    ['profile', `document.getElementById('usm-header-profile').click()`],
    ['manual', `typeof window.openUserManual==='function' && window.openUserManual()`],
    ['scanner', `typeof window.openScannerModal==='function' && window.openScannerModal()`],
    ['confirm', `typeof window.openConfirmModal==='function' && window.openConfirmModal()`],
  ];

  for (const [name, opener] of states) {
    if (opener) {
      await evaluate(opener + ';true');
      await sleep(name === 'scanner' ? 1200 : 500);
    }
    record(name, await run(name));
    if (opener) {
      await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
      await sleep(400);
    }
  }

  // The scanner never opened a camera in headless mode; make sure Escape closed it.
  const scannerClosed = await evaluate(`document.getElementById('scanner-modal').classList.contains('hidden')`);
  console.log(`\nEscape closes the scanner dialog: ${scannerClosed ? 'yes' : 'no'}`);

  const file = path.join(outDir, 'axe-report.json');
  fs.writeFileSync(file, JSON.stringify({ url: baseUrl, states: report, scannerClosed }, null, 2));
  console.log(`report: ${file}`);

  ws.close();
  child.kill();
  if (blocking || !scannerClosed) {
    console.error(`\n${blocking} serious/critical violation group(s) found.`);
    process.exit(1);
  }
  console.log('\nNo serious or critical violations.');
}

main().catch((error) => {
  console.error(error.message || error);
  child.kill();
  process.exit(1);
});
