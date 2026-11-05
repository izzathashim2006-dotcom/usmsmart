#!/usr/bin/env node
/*
 * Rebuilds the self-hosted vendor stylesheets in assets/vendor/:
 *
 *   font-awesome.css  Font Awesome Free 6.4.0, trimmed to the icon classes this
 *                     app actually references, woff2 only (solid + regular).
 *   fonts.css         Plus Jakarta Sans (300-700) and JetBrains Mono (400,500),
 *                     latin and latin-ext subsets only.
 *   fonts/*.woff2     The font files both sheets point at.
 *
 * Needs network access (cdnjs + fonts.gstatic.com). Run after adding a new
 * icon class or a new font weight, then reload the dashboard:
 *
 *   node tools/build-vendor-css.js
 *
 * Font Awesome icons: CC BY 4.0. Fonts: SIL OFL 1.1. See
 * https://fontawesome.com/license/free and https://openfontlicense.org
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'assets', 'vendor');
const FONT_DIR = path.join(OUT, 'fonts');
const FA_CSS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
const FA_FONT_BASE = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/';
const GOOGLE_CSS_URL = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// Utility/base classes that carry no glyph of their own.
const BASE = new Set(`fa fas far fab fa-solid fa-regular fa-brands fa-fw fa-ul fa-li fa-spin fa-pulse
fa-spin-pulse fa-spin-reverse fa-rotate-90 fa-rotate-180 fa-rotate-270 fa-rotate-by fa-flip
fa-flip-horizontal fa-flip-vertical fa-flip-both fa-2xs fa-xs fa-sm fa-lg fa-xl fa-2xl
fa-1x fa-2x fa-3x fa-4x fa-5x fa-6x fa-7x fa-8x fa-9x fa-10x fa-stack fa-stack-1x fa-stack-2x
fa-inverse fa-layers fa-beat fa-bounce fa-fade fa-shake fa-beat-fade fa-pull-left fa-pull-right
fa-border sr-only`.split(/\s+/));

function usedIconClasses() {
  const roots = [
    path.join(ROOT, 'index.php'),
    ...fs.readdirSync(path.join(ROOT, 'views')).map((f) => path.join(ROOT, 'views', f)),
    ...fs.readdirSync(path.join(ROOT, 'assets')).filter((f) => /\.(js|css)$/.test(f)).map((f) => path.join(ROOT, 'assets', f)),
  ];
  const found = new Set();
  for (const file of roots) {
    if (!/\.(php|js|css)$/.test(file)) continue;
    const text = fs.readFileSync(file, 'utf8');
    for (const match of text.matchAll(/\bfa-[a-z0-9-]+\b/g)) {
      if (!BASE.has(match[0])) found.add(match[0]);
    }
  }
  return found;
}

// Split a stylesheet into top-level rules (@font-face, @keyframes and @media
// blocks stay whole) so individual rule bodies can be dropped.
function splitRules(css) {
  const rules = [];
  let depth = 0;
  let buf = '';
  for (const ch of css) {
    buf += ch;
    if (ch === '{') depth++;
    else if (ch === '}') {
      depth--;
      if (depth === 0) { rules.push(buf.trim()); buf = ''; }
    }
  }
  if (buf.trim()) rules.push(buf.trim());
  return rules;
}

function classesIn(selector) {
  return (selector.match(/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/g) || []).map((c) => c.slice(1));
}

async function buildFontAwesome() {
  const icons = usedIconClasses();
  const css = await (await fetch(FA_CSS_URL)).text();
  const kept = [];

  for (const rule of splitRules(css)) {
    if (rule.startsWith('@font-face')) {
      const weight = (rule.match(/font-weight:\s*(\d+)/) || [])[1];
      const family = (rule.match(/font-family:\s*"([^"]+)"/) || [])[1];
      if (family !== 'Font Awesome 6 Free' || (weight !== '900' && weight !== '400')) continue;
      const file = weight === '900' ? 'fa-solid-900.woff2' : 'fa-regular-400.woff2';
      kept.push(rule
        .replace(/src:[^}]+/, `src:url(./fonts/${file}) format("woff2")`)
        .replace(/font-display:\s*block/, 'font-display:swap'));
      continue;
    }
    if (rule.startsWith('@keyframes') || rule.startsWith('@-webkit-keyframes')) { kept.push(rule); continue; }
    if (rule.startsWith('@media')) {
      const innerStart = rule.indexOf('{') + 1;
      const inner = rule.slice(innerStart, -1);
      const innerKept = splitRules(inner).map(pruneSelector).filter(Boolean);
      if (innerKept.length) kept.push(rule.slice(0, innerStart) + innerKept.join('') + '}');
      continue;
    }
    const pruned = pruneSelector(rule);
    if (pruned) kept.push(pruned);
  }

  function pruneSelector(rule) {
    const brace = rule.indexOf('{');
    if (brace < 0) return '';
    const selectors = rule.slice(0, brace).split(',').map((s) => s.trim()).filter((sel) => {
      if (!sel) return false;
      const classes = classesIn(sel);
      if (!classes.length) return true;
      const iconish = classes.some((c) => c.startsWith('fa-') || c === 'fa' || c === 'fas' || c === 'far' || c === 'fab');
      if (!iconish) return true;
      return classes.every((c) => icons.has(c) || BASE.has(c));
    });
    return selectors.length ? selectors.join(',') + rule.slice(brace) : '';
  }

  const header = `/* Font Awesome Free 6.4.0 — generated subset, ${icons.size} icon classes.
   Only glyphs referenced by this app are present; woff2 only.
   Regenerate with: node tools/build-vendor-css.js
   Icons CC BY 4.0, fonts SIL OFL 1.1, code MIT — https://fontawesome.com/license/free */\n`;
  fs.writeFileSync(path.join(OUT, 'font-awesome.css'), header + kept.join('\n') + '\n');

  for (const file of ['fa-solid-900.woff2', 'fa-regular-400.woff2']) {
    const response = await fetch(FA_FONT_BASE + file);
    if (!response.ok) throw new Error(`${file}: HTTP ${response.status}`);
    fs.writeFileSync(path.join(FONT_DIR, file), Buffer.from(await response.arrayBuffer()));
  }
  return icons.size;
}

async function buildWebFonts() {
  const css = await (await fetch(GOOGLE_CSS_URL, { headers: { 'User-Agent': UA } })).text();
  const blocks = css.match(/\/\*[^*]*\*\/\s*@font-face\s*\{[^}]*\}/g) || [];
  // Google serves one variable file per family+subset and repeats it for every
  // requested weight, so collect the weights into a single range per file.
  const variants = new Map();

  for (const block of blocks) {
    const subset = (block.match(/\/\*\s*([a-z-]+)\s*\*\//) || [])[1];
    if (subset !== 'latin' && subset !== 'latin-ext') continue;
    const family = (block.match(/font-family:\s*'([^']+)'/) || [])[1];
    const weight = (block.match(/font-weight:\s*(\d+)/) || [])[1];
    const style = (block.match(/font-style:\s*([a-z]+)/) || [])[1] || 'normal';
    const url = (block.match(/url\((https:[^)]+)\)/) || [])[1];
    const unicode = (block.match(/unicode-range:\s*([^;]+);/) || [])[1];
    if (!family || !weight || !url) continue;

    const key = `${family}|${subset}`;
    const entry = variants.get(key);
    if (entry) {
      entry.weights.push(Number(weight));
      if (entry.url !== url) throw new Error(`${key}: unexpected second URL`);
    } else {
      variants.set(key, { family, subset, style, url, unicode, weights: [Number(weight)] });
    }
  }

  // Google returns the same variable file for every requested weight, so the
  // @font-face can expose the font's real axis instead of the sampled weights.
  const AXIS = { 'Plus Jakarta Sans': [200, 800], 'JetBrains Mono': [100, 800] };

  const faces = [];
  let files = 0;
  for (const { family, subset, style, url, unicode, weights } of variants.values()) {
    const slug = family.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    const file = `${slug}-${subset}.woff2`;
    const response = await fetch(url, { headers: { 'User-Agent': UA } });
    if (!response.ok) throw new Error(`${file}: HTTP ${response.status}`);
    fs.writeFileSync(path.join(FONT_DIR, file), Buffer.from(await response.arrayBuffer()));
    files++;

    const axis = AXIS[family] || [Math.min(...weights), Math.max(...weights)];
    const weight = weights.length > 1 || AXIS[family] ? `${axis[0]} ${axis[1]}` : String(weights[0]);
    faces.push(`@font-face {
  font-family: '${family}';
  font-style: ${style};
  font-weight: ${weight};
  font-display: swap;
  src: url(./fonts/${file}) format('woff2');
  unicode-range: ${unicode};
}`);
  }

  const header = `/* Plus Jakarta Sans + JetBrains Mono — generated, latin and latin-ext only.
   Regenerate with: node tools/build-vendor-css.js
   Fonts SIL OFL 1.1 — https://openfontlicense.org */\n`;
  fs.writeFileSync(path.join(OUT, 'fonts.css'), header + faces.join('\n') + '\n');
  return files;
}

(async () => {
  fs.mkdirSync(FONT_DIR, { recursive: true });
  const icons = await buildFontAwesome();
  const files = await buildWebFonts();
  const size = (f) => fs.statSync(path.join(OUT, f)).size;
  console.log(`font-awesome.css  ${size('font-awesome.css').toLocaleString()} B  (${icons} icons)`);
  console.log(`fonts.css         ${size('fonts.css').toLocaleString()} B  (${files} faces)`);
})();
