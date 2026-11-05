#!/usr/bin/env node
/*
 * USMSmart smoke test.
 *
 * Logs in with a real account, visits every segment and opens the read-only
 * modals, then reports console errors, failed requests, payload sizes and
 * screenshots. Exits 1 when anything failed.
 *
 * Nothing is clicked that writes to the database.
 *
 * Usage (from the project root):
 *   USM_USER=admin USM_PASS=secret node tools/smoke-test.js
 *
 * Optional env:
 *   USM_BASE_URL   default http://localhost/USM/
 *   USM_OUT        screenshot directory, default <temp>/usm-smoke
 *   USM_HEADED=1   show the browser window
 *   CHROME_PATH    default "C:/Program Files/Google/Chrome/Application/chrome.exe"
 *   USM_TABS       comma list of tab button ids to click
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
const outDir = process.env.USM_OUT || path.join(os.tmpdir(), 'usm-smoke');
const chromePath = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const headed = process.env.USM_HEADED === '1';
const port = Number(process.env.USM_CDP_PORT || 9350);

if (!user || !pass) {
  console.error('Set USM_USER and USM_PASS, e.g. USM_USER=admin USM_PASS=secret node tools/smoke-test.js');
  process.exit(2);
}

fs.mkdirSync(outDir, { recursive: true });

const sleep = ms => new Promise(r => setTimeout(r, ms));
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'usm-smoke-profile-'));
const errors = [];
const failedRequests = [];
const results = [];

const chromeArgs = [
  headed ? '--new-window' : '--headless=new',
  '--disable-gpu',
  '--hide-scrollbars',
  '--no-first-run',
  '--no-default-browser-check',
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${profile}`,
  'about:blank',
];

const child = spawn(chromePath, chromeArgs, { stdio: 'ignore' });

async function connect() {
  let targets = [];
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/list`);
      targets = await res.json();
      if (targets.some(t => t.type === 'page')) break;
    } catch (e) { /* Chrome is still starting */ }
    await sleep(250);
  }
  const page = targets.find(t => t.type === 'page');
  if (!page) throw new Error('Chrome did not expose a debugging target');

  const ws = new WebSocket(page.webSocketDebuggerUrl);
  let nextId = 0;
  const pending = new Map();
  await new Promise((resolve, reject) => {
    ws.addEventListener('open', resolve);
    ws.addEventListener('error', reject);
  });
  ws.addEventListener('message', ev => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      pending.get(msg.id)(msg);
      pending.delete(msg.id);
      return;
    }
    if (msg.method === 'Runtime.exceptionThrown') {
      errors.push('exception: ' + (msg.params.exceptionDetails.exception?.description || msg.params.exceptionDetails.text));
    }
    if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error') {
      errors.push('log: ' + msg.params.entry.text);
    }
    if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') {
      errors.push('console: ' + msg.params.args.map(a => a.value || a.description || '').join(' ').slice(0, 300));
    }
    if (msg.method === 'Network.responseReceived') {
      const r = msg.params.response;
      if (r.status >= 400) failedRequests.push(`${r.status} ${r.url}`);
    }
    if (msg.method === 'Network.loadingFailed') {
      failedRequests.push(`FAILED ${msg.params.errorText} ${msg.params.type}`);
    }
  });

  const send = (method, params = {}) => new Promise(resolve => {
    const id = ++nextId;
    pending.set(id, resolve);
    ws.send(JSON.stringify({ id, method, params }));
  });
  return { ws, send };
}

async function main() {
  const { ws, send } = await connect();
  const evaluate = async expression => {
    const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (res.result?.exceptionDetails) throw new Error(res.result.exceptionDetails.text || 'evaluate failed');
    return res.result?.result?.value;
  };
  const screenshot = async name => {
    const res = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const file = path.join(outDir, name + '.png');
    fs.writeFileSync(file, Buffer.from(res.result.data, 'base64'));
    return file;
  };
  const waitFor = async (expression, timeout = 15000) => {
    const until = Date.now() + timeout;
    while (Date.now() < until) {
      if (await evaluate(expression)) return true;
      await sleep(300);
    }
    throw new Error('Timed out waiting for: ' + expression);
  };
  const check = (name, ok, detail = '') => {
    results.push({ name, ok, detail });
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
  };

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Log.enable');
  await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });

  // --- Login -------------------------------------------------------------
  await send('Page.navigate', { url: baseUrl });
  await waitFor(`!!document.getElementById('authForm')`);
  const loginBytes = await evaluate(`(()=>{const n=performance.getEntriesByType('navigation')[0];return n?{decoded:n.decodedBodySize,transfer:n.transferSize}:null;})()`);
  console.log(`login payload: ${loginBytes?.decoded ?? '?'} B decoded / ${loginBytes?.transfer ?? '?'} B transferred`);
  check('login form is present', true);

  await evaluate(`(()=>{
    document.querySelector('input[name=username]').value=${JSON.stringify(user)};
    document.getElementById('authPassword').value=${JSON.stringify(pass)};
    document.getElementById('authForm').requestSubmit();
    return true;
  })()`);
  const signedIn = await waitFor(`!!document.getElementById('usm-header-profile')`, 25000).catch(() => false);
  check('login accepted', signedIn);
  if (!signedIn) {
    await screenshot('login-failed');
    throw new Error('Login failed; check USM_USER / USM_PASS');
  }
  await sleep(2500);

  const dash = await evaluate(`(()=>{
    const n=performance.getEntriesByType('navigation')[0];
    return {
      decoded:n.decodedBodySize, transfer:n.transferSize,
      htmlChars:document.documentElement.outerHTML.length,
      scrollWidth:document.documentElement.scrollWidth, innerWidth,
      globals:['filterMasterTable','openMyProfile','sendSyswebRecords'].every(g=>typeof window[g]==='function'),
      scannerLoaded: typeof window.Html5Qrcode !== 'undefined'
    };
  })()`);
  console.log(`dashboard payload: ${dash.decoded} B decoded / ${dash.transfer} B transferred`);
  check('dashboard globals present', dash.globals);
  check('no horizontal overflow at 1440px', dash.scrollWidth <= dash.innerWidth, `${dash.scrollWidth}/${dash.innerWidth}`);

  // --- Tabs --------------------------------------------------------------
  for (const btn of ['btn-tab-analytics', 'btn-tab-sysweb', 'btn-tab-transporter']) {
    const exists = await evaluate(`!!document.getElementById(${JSON.stringify(btn)})`);
    if (!exists) { check(`tab ${btn} exists`, false); continue; }
    await evaluate(`document.getElementById(${JSON.stringify(btn)}).click()`);
    await sleep(1500);
    const view = btn.replace('btn-tab-', 'view-');
    const visible = await evaluate(`(()=>{const v=document.getElementById(${JSON.stringify(view)});if(!v)return false;const s=getComputedStyle(v);return s.display!=='none'&&s.visibility!=='hidden'&&v.getBoundingClientRect().height>0;})()`);
    check(`tab ${btn} renders`, visible);
    await screenshot('tab-' + btn.replace('btn-tab-', ''));
  }

  const charts = await evaluate(`(()=>({chart:typeof Chart!=='undefined'?Chart.instances?Object.keys(Chart.instances).length:0:0}))()`);
  check('Chart.js available', charts.chart > 0, `${charts.chart} chart(s)`);
  check('scanner library deferred until needed', dash.scannerLoaded === false, dash.scannerLoaded ? 'loaded on page load' : 'not loaded yet');
  check('analytics KPI icon is a 34px tile', await evaluate(`(()=>{const i=document.querySelector('#view-analytics .analytics-kpi-icon');if(!i)return true;const s=getComputedStyle(i);return s.display==='grid'&&s.width==='34px';})()`));

  // --- Read-only modals --------------------------------------------------
  await evaluate(`document.getElementById('btn-tab-transporter').click()`);
  await sleep(500);

  const openAndClose = async (openFn, closeFn, label) => {
    if (!(await evaluate(`typeof window[${JSON.stringify(openFn)}]==='function'`))) {
      check(label + ' exists', false);
      return;
    }
    await evaluate(`${openFn}()`);
    await sleep(700);
    const open = await evaluate(`(()=>{const panels=[...document.querySelectorAll('.fixed.inset-0,.fixed')].filter(e=>getComputedStyle(e).display!=='none'&&e.getBoundingClientRect().height>200);return panels.length>0;})()`);
    check(label + ' opens', open);
    if (typeof closeFn === 'string') {
      await evaluate(`${closeFn}()`).catch(() => {});
      await sleep(300);
    }
  };

  await openAndClose('openMyProfile', 'closeMyProfile', 'profile modal');
  await openAndClose('openUserManual', 'closeUserManual', 'user manual');
  await openAndClose('openScannerModal', 'closeScannerModal', 'scanner modal (loads QR library on demand)');
  await sleep(1000);
  const scannerLoaded = await evaluate(`typeof window.Html5Qrcode !== 'undefined'`);
  check('scanner library loads when scanner opens', scannerLoaded);

  // --- Dialog keyboard behaviour -----------------------------------------
  const keyboard = await evaluate(`(async () => {
    const trigger = document.getElementById('usm-header-profile');
    const dialog = document.getElementById('my-profile-modal');
    trigger.focus();
    window.openMyProfile();
    await new Promise(r => setTimeout(r, 400));
    const opened = !dialog.classList.contains('hidden');
    const focusInside = dialog.contains(document.activeElement);
    const focusables = dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])');
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    last.focus();
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
    const wrapped = document.activeElement === first;
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    await new Promise(r => setTimeout(r, 400));
    return { opened, focusInside, wrapped, closed: dialog.classList.contains('hidden'), returned: document.activeElement === trigger };
  })()`);
  check('dialog takes focus when it opens', keyboard.opened && keyboard.focusInside);
  check('Tab wraps inside the dialog', keyboard.wrapped);
  check('Escape closes the dialog', keyboard.closed);
  check('focus returns to the trigger', keyboard.returned);
  check('skip link is keyboard reachable', await evaluate(`(()=>{const a=document.querySelector('.usm-skip');if(!a)return false;a.focus();return document.activeElement===a;})()`));

  // --- Mobile ------------------------------------------------------------
  // Apply the phone metrics first, then reload: a fresh navigation is what a
  // real phone performs, and it lets Chrome apply the page's viewport meta at
  // the emulated width instead of re-fitting the desktop page already on screen.
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await send('Page.reload');
  await waitFor(`!!document.getElementById('usm-header-profile')`, 20000);
  await sleep(1800);
  const mobile = await evaluate(`(()=>{
    const wide=[...document.querySelectorAll('body *')].filter(el=>{
      const r=el.getBoundingClientRect();
      return r.width>0 && getComputedStyle(el).display!=='none' && r.right>window.innerWidth+1;
    }).slice(0,5).map(el=>el.tagName.toLowerCase()+'.'+String(el.className).split(' ').slice(0,3).join('.'));
    return {scrollWidth:document.documentElement.scrollWidth, innerWidth, scale:window.visualViewport?.scale, wide};
  })()`);
  check('no horizontal overflow at 390px', mobile.scrollWidth <= mobile.innerWidth, `${mobile.scrollWidth}/${mobile.innerWidth}${mobile.wide?.length ? ' widest: ' + mobile.wide.join(', ') : ''}`);
  console.log('mobile screenshot: ' + await screenshot('mobile'));
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
  await sleep(400);

  // Both themes on the chart-heavy view, where missing utilities are obvious.
  await evaluate(`(()=>{localStorage.setItem('theme','light');document.documentElement.classList.remove('dark');document.getElementById('btn-tab-analytics').click();return true;})()`);
  await sleep(1600);
  console.log('light screenshot: ' + await screenshot('desktop-light-analytics'));
  await evaluate(`(()=>{localStorage.setItem('theme','dark');document.documentElement.classList.add('dark');return true;})()`);
  await sleep(600);
  console.log('desktop screenshot: ' + await screenshot('desktop-dark-analytics'));

  // --- Report ------------------------------------------------------------
  const uniqueErrors = [...new Set(errors)];
  const uniqueFailures = [...new Set(failedRequests)];
  check('no console errors or exceptions', uniqueErrors.length === 0, uniqueErrors.slice(0, 5).join(' | '));
  check('no failed requests', uniqueFailures.length === 0, uniqueFailures.slice(0, 5).join(' | '));

  ws.close();
  return results.every(r => r.ok);
}

let code = 1;
main()
  .then(ok => { code = ok ? 0 : 1; })
  .catch(err => { console.error('\nsmoke test aborted:', err.message); code = 1; })
  .finally(() => {
    child.kill();
    setTimeout(() => {
      fs.rmSync(profile, { recursive: true, force: true });
      process.exit(code);
    }, 300);
  });
