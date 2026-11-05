#!/usr/bin/env node
/*
 * Diff two snapshots produced by tools/style-snapshot.js.
 *
 *   node tools/style-diff.js before.json after.json
 *
 * Element paths that exist only in one file are reported separately from
 * property changes. Exit code is 1 when anything differs.
 */
const fs = require('fs');

const [beforeFile, afterFile] = process.argv.slice(2);
if (!beforeFile || !afterFile) {
  console.error('Usage: node tools/style-diff.js before.json after.json');
  process.exit(2);
}
const before = JSON.parse(fs.readFileSync(beforeFile, 'utf8'));
const after = JSON.parse(fs.readFileSync(afterFile, 'utf8'));

let changes = 0;
for (const view of new Set([...Object.keys(before), ...Object.keys(after)])) {
  const a = before[view] || {};
  const b = after[view] || {};
  const missing = Object.keys(a).filter(k => !(k in b));
  const added = Object.keys(b).filter(k => !(k in a));
  if (missing.length) {
    changes += missing.length;
    console.log(`\n${view}: ${missing.length} element(s) disappeared, e.g. ${missing.slice(0, 3).join(' | ')}`);
  }
  if (added.length) {
    changes += added.length;
    console.log(`\n${view}: ${added.length} element(s) appeared, e.g. ${added.slice(0, 3).join(' | ')}`);
  }
  for (const key of Object.keys(a)) {
    if (!(key in b)) continue;
    const diffs = [];
    for (const prop of Object.keys(a[key])) {
      if (a[key][prop] !== b[key][prop]) diffs.push(`${prop}: ${a[key][prop]} -> ${b[key][prop]}`);
    }
    if (diffs.length) {
      changes += diffs.length;
      console.log(`\n${view} ${key}\n  ` + diffs.slice(0, 6).join('\n  '));
    }
  }
}
console.log(changes ? `\n${changes} difference(s)` : '\nNo style differences');
process.exit(changes ? 1 : 0);
