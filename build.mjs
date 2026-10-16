#!/usr/bin/env node
/**
 * Builds the SVG Filter Kit demo site.
 *
 *   node build.mjs [--main <dir>] [--readme]
 *
 * Reads the module (branch `main`, default ../main), inlines every <filter>
 * exactly like inc/loader.php does, renders one card per filter from its
 * <title>, <desc> and data-* attributes, and writes dist/.
 *
 * --readme  rewrites the filter table in <main>/README.md between the
 *           <!-- filters:start --> and <!-- filters:end --> markers.
 *           Without it, an out-of-sync table only produces a warning.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const argv = process.argv.slice(2);
const opt = name => { const i = argv.indexOf(name); return i >= 0 ? argv[i + 1] : undefined; };
const mainDir = path.resolve(opt('--main') ?? path.join(here, '../main')); // --main: relative to the current directory
const dist = path.join(here, 'dist');

const CATEGORIES = ['color', 'texture', 'distortion', 'shape', 'light', 'fill', 'glass', 'motion'];
const SUPPORT = { all: ['ok', 'All browsers'], caveats: ['warn', 'Caveats'], chromium: ['bad', 'Chromium only'] };
const COSTS = ['low', 'medium', 'high'];
const DEMOS = ['scene', 'type', 'goo', 'glass', 'spot', 'melt'];

const read = rel => fs.readFileSync(path.join(mainDir, rel), 'utf8');
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const unxml = s => s.replace(/&(lt|gt|quot|apos|amp);/g, (_, e) => ({ lt: '<', gt: '>', quot: '"', apos: "'", amp: '&' })[e]);
const inline = s => esc(s).replace(/`([^`]+)`/g, '<code>$1</code>'); // `code` in metadata texts
const fail = msg => { console.error(`build.mjs: ${msg}`); process.exit(1); };

/* ---------- Read filters ---------- */

// Same pattern as svgf_collect() in inc/loader.php, so the site sees exactly what WordPress sees.
const FILTER_RE = /<filter\b[^>]*\bid="([^"]+)"[^>]*>[\s\S]*?<\/filter>/g;

const filters = [];
const sources = new Map(); // rel path -> file content
for (const name of fs.readdirSync(path.join(mainDir, 'filters')).filter(f => f.endsWith('.svg')).sort()) {
  const rel = `filters/${name}`;
  const svg = read(rel);
  sources.set(rel, svg);
  for (const { 0: block, 1: id, index: blockStart } of svg.matchAll(FILTER_RE)) {
    if (filters.some(f => f.id === id)) continue; // duplicate ID: first file wins, like the loader
    const open = block.slice(0, block.indexOf('>') + 1);
    const attr = Object.fromEntries([...open.matchAll(/([\w:-]+)="([^"]*)"/g)].map(([, k, v]) => [k, unxml(v)]));
    const text = tag => { const m = block.match(new RegExp(`<${tag}>([\\s\\S]*?)</${tag}>`)); return m ? unxml(m[1].trim()) : ''; };
    const f = {
      id, file: rel, block,
      title: text('title'), desc: text('desc'),
      category: attr['data-category'], support: attr['data-support'], cost: attr['data-cost'], demo: attr['data-demo'],
      demoText: attr['data-demo-text'], demoBg: attr['data-demo-bg'], note: attr['data-note'],
      prims: primitives(block),
      tweaks: tweaks(block, blockStart, `${rel} #${id}`),
    };
    const bad = [
      !f.title && '<title>', !f.desc && '<desc>',
      !CATEGORIES.includes(f.category) && `data-category (${CATEGORIES.join('|')})`,
      !SUPPORT[f.support] && `data-support (${Object.keys(SUPPORT).join('|')})`,
      !COSTS.includes(f.cost) && `data-cost (${COSTS.join('|')})`,
      !DEMOS.includes(f.demo) && `data-demo (${DEMOS.join('|')})`,
    ].filter(Boolean);
    if (bad.length) fail(`${rel} #${id}: missing or invalid ${bad.join(', ')}`);
    filters.push(f);
  }
}
if (!filters.length) fail(`no filters found in ${mainDir}/filters`);
for (const f of filters) for (const t of f.tweaks) { // offsets drive "Copy with my values"
  if (unxml(sources.get(f.file).slice(t.start, t.end)) !== t.value) fail(`${f.file} #${f.id}: offset mismatch for ${t.attr}`);
}
filters.sort((a, b) => CATEGORIES.indexOf(a.category) - CATEGORIES.indexOf(b.category)); // stable: file order within a category

/** Top-level primitives of a filter, e.g. "feTurbulence + <animate> → feDisplacementMap". */
function primitives(block) {
  const out = [];
  let depth = 0;
  for (const [, close, tag, self] of block.matchAll(/<(\/?)([\w:-]+)[^>]*?(\/?)>/g)) {
    if (tag === 'filter') continue;
    if (close) { depth--; continue; }
    if (depth === 0 && tag.startsWith('fe')) out.push(tag);
    if (depth === 1 && tag === 'animate' && !out.at(-1).endsWith('>')) out[out.length - 1] += ' + <animate>';
    if (!self) depth++;
  }
  return out.reduce((acc, p) => {
    const last = acc.at(-1);
    if (last && last.name === p) last.n++; else acc.push({ name: p, n: 1 });
    return acc;
  }, []).map(({ name, n }) => (n > 1 ? `${name} ×${n}` : name)).join(' → ');
}

/**
 * Tuning hints: data-tweak-<attr>="[min, max] description" on a primitive.
 * index: position in filter.querySelectorAll('*'); start/end: the value's offsets in the file.
 */
function tweaks(block, blockStart, where) {
  const out = [];
  let index = -1;
  for (const m of block.matchAll(/<([\w:-]+)\b([^>]*?)\/?>/g)) { // opening tags only
    if (m[1] === 'filter') continue;
    index++;
    const attrsAt = blockStart + m.index + 1 + m[1].length;
    for (const [, attr, hint] of m[2].matchAll(/\sdata-tweak-([\w:-]+)="([^"]*)"/g)) {
      const a = new RegExp(`\\s${attr}="([^"]*)"`).exec(m[2]);
      if (!a) fail(`${where}: data-tweak-${attr} on <${m[1]}>, which has no ${attr}`);
      const [, min, max, text] = unxml(hint).match(/^(?:\[\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\]\s*)?([\s\S]*)$/);
      const start = attrsAt + a.index + a[0].indexOf('"') + 1;
      out.push({ el: m[1], attr, value: unxml(a[1]), text, min, max, index, start, end: start + a[1].length });
    }
  }
  return out;
}

/* ---------- Render ---------- */

const codePanel = rel => {
  const content = sources.get(rel) ?? read(rel);
  return `<details class="code"><summary>${esc(rel)}</summary><div class="codebox">`
    + `<pre>${esc(content)}</pre><button class="copy" type="button">Copy</button></div></details>`;
};

/**
 * Live control for a hint: color picker for #hex, slider for one number (unit kept),
 * text field for lists. data-* tell the page JS which primitive and file range it edits.
 */
function control(f, t) {
  const target = `data-filter="${f.id}" data-index="${t.index}" data-attr="${esc(t.attr)}" data-start="${t.start}" data-end="${t.end}" aria-label="${esc(`${t.attr} (${t.el})`)}"`;
  if (/^#[0-9a-f]{6}$/i.test(t.value)) return `<input type="color" value="${t.value.toLowerCase()}" ${target}>`;
  const num = t.value.match(/^(-?\d*\.?\d+)([a-z%]*)$/i);
  if (num) {
    const v = Number(num[1]);
    const [min, max] = t.min !== undefined ? [Number(t.min), Number(t.max)] : [Math.min(0, v * 3), Math.max(1, v * 3)];
    let step = 10 ** (Math.floor(Math.log10(max - min)) - 2);
    if (Math.abs((v - min) / step - Math.round((v - min) / step)) > 1e-6) step = 'any'; // never snap the file's value
    return `<input type="range" min="${min}" max="${max}" step="${step}" value="${v}" data-unit="${num[2]}" ${target}>`;
  }
  return `<input type="text" value="${esc(t.value)}" spellcheck="false" ${target}>`;
}

const tunePanel = f => !f.tweaks.length ? '' : `<details class="tune"><summary>Tune it (${f.tweaks.length})</summary><dl>`
  + f.tweaks.map(t => `<div><dt><code>${esc(t.attr)}</code> <span class="el">${esc(t.el)}</span></dt>`
    + `<dd>${inline(t.text)}<span class="meta">now <code class="val">${esc(t.value)}</code>${t.min !== undefined ? ` · range ${t.min} to ${t.max}` : ''}</span>`
    + `${control(f, t)}</dd></div>`).join('')
  + '</dl><div class="tune-actions"><button type="button" class="reset">Reset</button>'
  + `<button type="button" class="copy-tuned">Copy ${esc(path.basename(f.file))} with these values</button></div></details>\n    `;

const SCENE_LABEL = '<span class="scene-label">53.54°N 9.98°E</span>';
// Before/after slider: the subject twice, unfiltered (left of --pos) and filtered (right of it).
const compare = (plain, filtered) => `<div class="layer before" aria-hidden="true">${plain}</div><div class="layer after">${filtered}</div>`;

function stage(f) {
  const fx = `style="filter:url(#${f.id})"`;
  const word = esc(f.demoText || f.title);
  const blobs = '<span class="blob"></span>'.repeat(4);
  switch (f.demo) {
    case 'scene': return `<div class="stage" data-compare>${compare('<div class="scene"></div>', `<div class="scene fxed" ${fx}></div>`)}${SCENE_LABEL}</div>`;
    case 'type': return `<div class="stage type-stage${f.demoBg === 'dark' ? ' dark' : ''}" data-compare>${compare(`<div class="word">${word}</div>`, `<div class="word fxed" ${fx}>${word}</div>`)}</div>`;
    case 'goo': return `<div class="stage goo-stage" data-compare>${compare(`<div class="goo">${blobs}</div>`, `<div class="goo fxed" ${fx}>${blobs}</div>`)}</div>`;
    // Glass clips the panes themselves: a clipped wrapper would become the backdrop root and hide the backdrop.
    case 'glass': return `<div class="stage glass-stage" data-demo="glass" data-compare style="--glass-filter:url(#${f.id})"><div class="scene"></div><div class="stripes"></div>`
      + `<div class="movers"><span class="mover m1"></span><span class="mover m2"></span></div>`
      + `<div class="pane before" aria-hidden="true">Liquid</div><div class="pane liquid after">Liquid</div><span class="support-badge">Checking…</span></div>`;
    case 'spot': return `<div class="stage spot-stage" data-demo="spot" data-filter="${f.id}"><div class="spot-surface fxed" ${fx}><span>Move here</span></div></div>`;
    case 'melt': return `<div class="stage melt-stage" data-demo="melt" data-filter="${f.id}"><div class="scene fxed" ${fx}></div>${SCENE_LABEL}</div>`;
  }
}
const HINTS = { spot: 'Hover or drag on the swatch', melt: 'Hover, or tap on touch screens' };

function card(f) {
  const [cls, label] = SUPPORT[f.support];
  const className = f.demo === 'glass' ? '.glass--liquid' : `.${f.id}`;
  return `<article class="card" id="${f.id}-card">
  ${stage(f)}
  <div class="card-body">
    <h3>${esc(f.title)} <code>${className}</code></h3>
    <p>${inline(f.desc)}</p>
    <div class="prims">${esc(f.prims)}</div>
    <div class="chips"><span class="chip ${cls}">${label}</span><span class="chip cost">Cost: ${f.cost}</span></div>${
    f.note ? `\n    <p class="note">${inline(f.note)}</p>` : ''}${
    HINTS[f.demo] ? `\n    <div class="hint">${HINTS[f.demo]}</div>` : ''}
    ${tunePanel(f)}${codePanel(f.file)}
  </div>
</article>`;
}

const version = (read('svg-filters.php').match(/define\(\s*'SVGF_VERSION',\s*'([^']+)'/) || [])[1] || '?';
const defs = '<svg id="svgf-defs" aria-hidden="true" focusable="false" width="0" height="0" '
  + 'style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">'
  + '<defs>' + filters.map(f => f.block).join('\n') + '</defs></svg>';

let html = fs.readFileSync(path.join(here, 'index.html'), 'utf8');
if (/<filter\b/.test(html)) fail('index.html contains hard-coded <filter> markup; filters come from the module only');

const fill = (key, value) => {
  const marker = `<!-- build:${key} -->`;
  if (!html.includes(marker)) fail(`index.html has no ${marker}`);
  html = html.replaceAll(marker, value);
};
fill('defs', defs);
fill('version', esc(version));
fill('total', String(filters.length));
for (const cat of CATEGORIES) fill(`cards:${cat}`, filters.filter(f => f.category === cat).map(card).join('\n'));
html = html.replace(/<!-- build:file:([^ ]+) -->/g, (_, rel) => codePanel(rel));
// Nav counts: articles per section, including static cards in the template.
html = html.replace(/<!-- build:count:(\w+) -->/g, (_, cat) => {
  const sec = html.match(new RegExp(`<section class="cat" id="${cat}">[\\s\\S]*?</section>`));
  if (!sec) fail(`no section #${cat} for its nav count`);
  return String(sec[0].match(/<article\b/g)?.length ?? 0).padStart(2, '0');
});
const leftover = html.match(/<!-- build:[^>]*-->/);
if (leftover) fail(`unfilled placeholder ${leftover[0]}`);

fs.rmSync(dist, { recursive: true, force: true });
fs.mkdirSync(dist, { recursive: true });
fs.writeFileSync(path.join(dist, 'index.html'), html);
fs.cpSync(path.join(here, 'fonts'), path.join(dist, 'fonts'), { recursive: true });
console.log(`dist/index.html: ${filters.length} filters, module ${version}`);

/* ---------- README filter table ---------- */

const table = ['| File | Class | Category | Description | Tunable | Notes |', '|---|---|---|---|---|---|',
  ...filters.map(f => {
    const notes = [f.support !== 'all' && SUPPORT[f.support][1] + '.', f.note].filter(Boolean).join(' ');
    const tunable = [...new Set(f.tweaks.map(t => `\`${t.attr}\``))].join(', ');
    return `| ${path.basename(f.file)} | \`${f.id}\` | ${f.category} | ${f.desc} | ${tunable} | ${notes} |`;
  })].join('\n');
const readmePath = path.join(mainDir, 'README.md');
const readme = fs.existsSync(readmePath) ? fs.readFileSync(readmePath, 'utf8') : '';
const TABLE_RE = /(<!-- filters:start -->\n)([\s\S]*?)(<!-- filters:end -->)/;
const current = readme.match(TABLE_RE);
if (current) {
  // Ignore cell padding, so a Markdown formatter re-aligning the table doesn't count as a change.
  const norm = s => s.trim().replace(/ *\| */g, '|').replace(/-{3,}/g, '---');
  if (argv.includes('--readme')) {
    fs.writeFileSync(readmePath, readme.replace(TABLE_RE, (_, a, b, c) => a + table + '\n' + c));
    console.log('README.md filter table updated');
  } else if (norm(current[2]) !== norm(table)) {
    console.warn('::warning file=README.md::Filter table is out of sync with the SVG metadata. Run: node build.mjs --readme');
  }
}
