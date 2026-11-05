#!/usr/bin/env node
/*
 * Tailwind coverage check.
 *
 * Collects every class token written in the PHP views, index.php and the
 * dashboard JavaScript, then verifies that a rule for it exists in the
 * compiled assets/tailwind.css or the hand-written stylesheets.
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
const cssFiles = ['assets/tailwind.css', 'assets/usmsmart-dashboard.css', 'assets/usmsmart-login.css'];
const css = cssFiles.map(f => fs.readFileSync(path.join(root, f), 'utf8')).join('\n');

const tokens = new Set();
const dynamic = new Set();

function collect(text) {
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
  // classList.add/remove/toggle('a','b') with literal arguments.
  const re2 = /classList\.(?:add|remove|toggle)\(([^)]*)\)/g;
  while ((m = re2.exec(text))) {
    for (const lit of m[1].match(/'[^']+'|"[^"]+"/g) || []) {
      const token = lit.slice(1, -1);
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
for (const token of tokens) {
  const escaped = '.' + escapeClass(token);
  if (!css.includes(escaped)) missing.push(token);
}

console.log(`checked ${tokens.size} class tokens across ${sources.length} source files`);
if (dynamic.size) {
  console.log(`skipped ${dynamic.size} dynamic expression(s), e.g. ${[...dynamic].slice(0, 5).join(' | ')}`);
}
if (missing.length) {
  console.log(`\nMISSING (${missing.length}):`);
  for (const token of missing.sort()) console.log('  ' + token);
  process.exit(1);
}
console.log('all class tokens have a matching rule');
