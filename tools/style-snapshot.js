#!/usr/bin/env node
/*
 * Computed-style snapshot for visual regression checks.
 *
 * Logs in, walks several views in light and dark mode, and writes the
 * computed styles of every element to a JSON file. Run it before and after a
 * CSS change, then diff the two files: tools/style-diff.js.
 *
 * Usage:
 *   USM_USER=... USM_PASS=... node tools/style-snapshot.js baseline.json
 */
const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const baseUrl = process.env.USM_BASE_URL || 'http://localhost/USM/';
const user = process.env.USM_USER || '';
const pass = process.env.USM_PASS || '';
const outFile = process.argv[2] || path.join(os.tmpdir(), 'usm-style-snapshot.json');
const chromePath = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const port = Number(process.env.USM_CDP_PORT || 9351);

if (!user || !pass) {
  console.error('Set USM_USER and USM_PASS.');
  process.exit(2);
}

const sleep = ms => new Promise(r => setTimeout(r, ms));
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'usm-style-profile-'));
const child = spawn(chromePath, ['--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });

const PROPS = ['display', 'position', 'color', 'backgroundColor', 'backgroundImage', 'borderTopWidth', 'borderTopColor', 'borderTopLeftRadius', 'boxShadow', 'opacity', 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textAlign', 'whiteSpace', 'flexDirection', 'flexWrap', 'alignItems', 'justifyContent', 'gap', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'width', 'height', 'overflowX', 'overflowY', 'visibility'];

async function main() {
  let targets = [];
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/list`);
      targets = await res.json();
      if (targets.some(t => t.type === 'page')) break;
    } catch (e) { /* starting */ }
    await sleep(250);
  }
  const page = targets.find(t => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  let nextId = 0;
  const pending = new Map();
  await new Promise((resolve, reject) => {
    ws.addEventListener('open', resolve);
    ws.addEventListener('error', reject);
  });
  ws.addEventListener('message', ev => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
  });
  const send = (method, params = {}) => new Promise(resolve => {
    const id = ++nextId;
    pending.set(id, resolve);
    ws.send(JSON.stringify({ id, method, params }));
  });
  const evaluate = async expression => {
    const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (res.result?.exceptionDetails) throw new Error(res.result.exceptionDetails.text);
    return res.result?.result?.value;
  };
  const waitFor = async (expression, timeout = 20000) => {
    const until = Date.now() + timeout;
    while (Date.now() < until) {
      if (await evaluate(expression)) return true;
      await sleep(300);
    }
    throw new Error('Timed out: ' + expression);
  };

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });

  await send('Page.navigate', { url: baseUrl });
  await waitFor(`!!document.getElementById('authForm')`);
  await evaluate(`(()=>{document.querySelector('input[name=username]').value=${JSON.stringify(user)};document.getElementById('authPassword').value=${JSON.stringify(pass)};document.getElementById('authForm').requestSubmit();return true;})()`);
  await waitFor(`!!document.getElementById('usm-header-profile')`, 25000);
  await sleep(3000);

  const snapshot = async () => evaluate(`(()=>{
    const props=${JSON.stringify(PROPS)};
    const pathOf=el=>{const parts=[];let n=el;while(n&&n.nodeType===1&&n!==document.documentElement){let i=1,s=n;while((s=s.previousElementSibling))if(s.tagName===n.tagName)i++;parts.unshift(n.tagName.toLowerCase()+':nth-of-type('+i+')');n=n.parentElement;}return parts.join('>');};
    const out={};
    for(const el of document.querySelectorAll('body *')){
      if(['SCRIPT','STYLE','LINK','CANVAS','VIDEO','IMG'].includes(el.tagName))continue;
      const s=getComputedStyle(el);
      if(s.display==='none'||s.visibility==='hidden')continue;
      const rec={};
      for(const p of props)rec[p]=s[p];
      out[pathOf(el)]=rec;
    }
    return out;
  })()`);

  const views = ['btn-tab-transporter', 'btn-tab-analytics', 'btn-tab-sysweb'];
  const data = {};
  for (const theme of ['light', 'dark']) {
    await evaluate(`(()=>{localStorage.setItem('theme',${JSON.stringify(theme)});document.documentElement.classList.toggle('dark',${theme === 'dark'});return true;})()`);
    await sleep(700);
    for (const btn of views) {
      const has = await evaluate(`!!document.getElementById(${JSON.stringify(btn)})`);
      if (!has) continue;
      await evaluate(`document.getElementById(${JSON.stringify(btn)}).click()`);
      await sleep(1200);
      data[theme + ':' + btn] = await snapshot();
    }
    // Modals are part of the visual contract too.
    await evaluate(`document.getElementById('btn-tab-transporter').click()`);
    await sleep(600);
    await evaluate(`openMyProfile()`);
    await sleep(700);
    data[theme + ':profile'] = await snapshot();
    await evaluate(`closeMyProfile()`);
    await sleep(400);
  }

  fs.writeFileSync(outFile, JSON.stringify(data, null, 1));
  const total = Object.values(data).reduce((n, v) => n + Object.keys(v).length, 0);
  console.log(`wrote ${outFile} (${Object.keys(data).length} views, ${total} element records)`);
  ws.close();
}

let failed = false;
main().catch(err => { failed = true; console.error(err.message); }).finally(() => {
  child.kill();
  setTimeout(() => { fs.rmSync(profile, { recursive: true, force: true }); process.exit(failed ? 1 : 0); }, 300);
});
