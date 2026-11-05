#!/usr/bin/env node
/*
 * Tailwind coverage check.
 *
 * Collects every class token written in the PHP views, index.php and the
 * dashboard JavaScript, then verifies that a rule for it exists in the
 * compiled assets/tailwind.css, the hand-written stylesheets or the vendor
 * icon sheet. Classes that exist only as JS hooks are listed in hookOnly.
 *
 * Run after changing markup or adding classes in JS:
 *   node tools/tailwind-coverage.js
 *
 * Exit code 1 lists classes with no matching rule, which is what a missing
 * utility looks like after replacing the Play CDN with a compiled sheet.
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const sources = [
  'index.php',
  'views/login.php',
  'views/dashboard.php',
  'assets/usmsmart-dashboard.js',
  'assets/usmsmart-login.css',
  'assets/usmsmart-dashboard.css',
];
const cssFiles = [
  'assets/tailwind.css',
  'assets/usmsmart-dashboard.css',
  'assets/usmsmart-login.css',
  'assets/vendor/font-awesome.css',
];
const css = cssFiles.map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');

const tokens = new Set();
const dynamic = new Set();

// Class names used only as JS/CSS hooks: their appearance comes from the
// utility classes at each call site, so no rule of their own is expected.
const hookOnly = new Set([
  'usm-src-badge',
  'usm-stats-gate-label',
  'sysweb-kind-btn',
  'sysweb-seg-btn',
]);

// Class attributes in views embed PHP; in JS they embed template literals.
// Removing both keeps bare words such as "echo" out of the token set.
function stripExpressions(text) {
  return text
    .replace(/<\?(?:php|=)?[\s\S]*?\?>/g, ' ')
    .replace(/\$\{[^}]*\}/g, ' ');
}

function collect(rawText) {
  const text = stripExpressions(rawText);
  // class="..." and class='...' with optional escaping used inside JS strings.
  const re = /class=\\*["']([^"'\\]*(?:\\.[^"'\\]*)*)\\*["']/g;
  let m;
  while ((m = re.exec(text))) {
    for (const token of m[1].split(/\s+/)) {
      if (!token) continue;
      if (/[<>${}?=()]/.test(token)) { dynamic.add(token); continue; }
      tokens.add(token);
    }
  }
  // classList.add/remove('a','b') with literal arguments. For toggle the
  // second argument is the force flag, not a class name.
  const re2 = /classList\.(add|remove|toggle)\(([^)]*)\)/g;
  while ((m = re2.exec(text))) {
    const args = m[2].split(',');
    const limit = m[1] === 'toggle' ? 1 : args.length;
    for (let i = 0; i < limit; i++) {
      const lit = args[i].trim().match(/^'([^']+)'$|^"([^"]+)"$/);
      if (!lit) continue;
      const token = lit[1] || lit[2];
      if (!token || /[<>${}?=()]/.test(token)) continue;
      tokens.add(token);
    }
  }
}

for (const file of sources) collect(fs.readFileSync(path.join(root, file), 'utf8'));

function escapeClass(token) {
  return token.replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, ch => '\\' + ch);
}

const missing = [];
let hooks = 0;
for (const token of tokens) {
  if (hookOnly.has(token)) { hooks++; continue; }
  const escaped = '.' + escapeClass(token);
  if (!css.includes(escaped)) missing.push(token);
}

console.log(`checked ${tokens.size} class tokens across ${sources.length} source files`);
if (hooks) console.log(`ignored ${hooks} hook-only class(es)`);
if (dynamic.size) {
  console.log(`skipped ${dynamic.size} dynamic expression(s), e.g. ${[...dynamic].slice(0, 5).join(' | ')}`);
}
if (missing.length) {
  console.log(`\nMISSING (${missing.length}):`);
  for (const token of missing.sort()) console.log('  ' + token);
  process.exit(1);
}
console.log('all class tokens have a matching rule');
