import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { scoreAll } from './score.mjs';

const ORDER = ['merge-track', 'salvage', 'close-candidate', 'review-then-decide'];

export function buildReport(inventory, generatedAt) {
  const scored = scoreAll(inventory.map((pr) => ({ ...pr, now: generatedAt })));
  const byNumber = new Map(inventory.map((pr) => [pr.number, pr]));

  const rows = scored.map((row) => {
    const source = byNumber.get(row.number);
    return {
      ...row,
      title: source.title,
      url: source.url,
      labels: source.labels,
      mergeable: source.mergeable,
      isDraft: source.isDraft,
      reviewRounds: source.reviewCount,
      latestReviewState: source.latestReviewState,
      hasApproval: source.hasApproval,
      behind: source.mergeable === 'CONFLICTING',
    };
  });

  rows.sort((a, b) => {
    const threads = b.signals.unresolvedThreads - a.signals.unresolvedThreads;
    if (threads !== 0) {
      return threads;
    }
    return b.signals.commitCount - a.signals.commitCount;
  });

  const counts = Object.fromEntries(ORDER.map((name) => [name, 0]));
  for (const row of rows) {
    counts[row.disposition] += 1;
  }

  return {
    generatedAt,
    disclaimer: 'This ranking is not merge approval.',
    counts,
    rows,
  };
}

export function renderMarkdown(report) {
  const lines = [
    '# PR portfolio disposition',
    '',
    report.disclaimer,
    '',
    `Generated: ${report.generatedAt}`,
    '',
    '| Disposition | Count |',
    '| --- | ---: |',
  ];
  for (const name of ORDER) {
    lines.push(`| ${name} | ${report.counts[name]} |`);
  }
  lines.push('');

  for (const name of ORDER) {
    lines.push(`## ${name}`, '');
    const section = report.rows.filter((row) => row.disposition === name);
    if (section.length === 0) {
      lines.push('None.', '');
      continue;
    }
    for (const row of section) {
      lines.push(
        `- #${row.number} ${row.title} — commits ${row.signals.commitCount}, comments ${row.signals.issueComments}, reviews ${row.reviewRounds}, threads ${row.signals.resolvedThreads} resolved / ${row.signals.unresolvedThreads} unresolved, mergeable ${row.mergeable}, labels ${row.labels.join(', ') || '(none)'}. ${row.reasons.join('; ')}. ${row.url}`
      );
    }
    lines.push('');
  }
  return `${lines.join('\n')}\n`;
}

export function renderHtml(report) {
  const payload = JSON.stringify(report).replace(/</g, '\\u003c');
  return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PR portfolio disposition</title>
<style>
  :root { color-scheme: light dark; --line: #ccc; --bg: #fff; --ink: #1a1a1a; --muted: #555; --chip: #f3f3f3; }
  @media (prefers-color-scheme: dark) {
    :root { --line: #444; --bg: #161616; --ink: #f2f2f2; --muted: #aaa; --chip: #2a2a2a; }
  }
  body { margin: 0; font: 14px/1.4 system-ui, sans-serif; background: var(--bg); color: var(--ink); }
  header, .controls { padding: 16px 20px 0; }
  h1 { font-size: 1.3rem; margin: 0 0 4px; }
  .disclaimer { color: var(--muted); margin: 0 0 8px; }
  .controls { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: end; padding-bottom: 12px; }
  label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); }
  input[type="search"], select { font: inherit; padding: 6px 8px; min-width: 220px; }
  .chips { display: flex; flex-wrap: wrap; gap: 8px; }
  .chips label { flex-direction: row; align-items: center; gap: 6px; background: var(--chip); padding: 4px 8px; border-radius: 999px; color: var(--ink); }
  .meta { padding: 0 20px 8px; color: var(--muted); }
  .table-wrap { overflow: auto; padding: 0 20px 24px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border-bottom: 1px solid var(--line); text-align: left; padding: 6px 8px; vertical-align: top; }
  th { cursor: pointer; white-space: nowrap; position: sticky; top: 0; background: var(--bg); }
  th[data-sort]:after { content: ''; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  a { color: inherit; }
  .tag { display: inline-block; margin: 0 4px 4px 0; padding: 1px 6px; border-radius: 999px; background: var(--chip); font-size: 12px; }
</style>
</head>
<body>
<header>
  <h1>PR portfolio disposition</h1>
  <p class="disclaimer"></p>
</header>
<div class="controls">
  <label>Search
    <input id="q" type="search" placeholder="Number, title, label, reason">
  </label>
  <label>Mergeable
    <select id="mergeable">
      <option value="">Any</option>
      <option value="MERGEABLE">MERGEABLE</option>
      <option value="CONFLICTING">CONFLICTING</option>
      <option value="UNKNOWN">UNKNOWN</option>
    </select>
  </label>
  <label>Threads
    <select id="threads">
      <option value="">Any</option>
      <option value="open">Unresolved threads</option>
      <option value="clear">No unresolved threads</option>
    </select>
  </label>
  <div class="chips" id="dispositions"></div>
</div>
<p class="meta" id="meta"></p>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th class="num" data-key="number">#</th>
        <th data-key="title">Title</th>
        <th data-key="disposition">Disposition</th>
        <th class="num" data-key="commits">Commits</th>
        <th class="num" data-key="comments">Comments</th>
        <th class="num" data-key="reviews">Reviews</th>
        <th class="num" data-key="resolved">Resolved</th>
        <th class="num" data-key="unresolved">Unresolved</th>
        <th data-key="mergeable">Mergeable</th>
        <th data-key="labels">Labels</th>
        <th data-key="reasons">Reasons</th>
      </tr>
    </thead>
    <tbody id="rows"></tbody>
  </table>
</div>
<script id="report-data" type="application/json">${payload}</script>
<script>
const report = JSON.parse(document.getElementById('report-data').textContent);
document.querySelector('.disclaimer').textContent = report.disclaimer + ' Generated ' + report.generatedAt;
const ORDER = ${JSON.stringify(ORDER)};
const state = { key: 'unresolved', dir: -1, q: '', mergeable: '', threads: '', dispositions: new Set(ORDER) };
const chipBox = document.getElementById('dispositions');
for (const name of ORDER) {
  const label = document.createElement('label');
  const input = document.createElement('input');
  input.type = 'checkbox';
  input.checked = true;
  input.addEventListener('change', () => {
    if (input.checked) state.dispositions.add(name);
    else state.dispositions.delete(name);
    render();
  });
  label.append(input, document.createTextNode(name + ' (' + report.counts[name] + ')'));
  chipBox.append(label);
}
document.getElementById('q').addEventListener('input', (event) => { state.q = event.target.value.trim().toLowerCase(); render(); });
document.getElementById('mergeable').addEventListener('change', (event) => { state.mergeable = event.target.value; render(); });
document.getElementById('threads').addEventListener('change', (event) => { state.threads = event.target.value; render(); });
document.querySelectorAll('th[data-key]').forEach((th) => {
  th.addEventListener('click', () => {
    const key = th.dataset.key;
    state.dir = state.key === key ? -state.dir : (['number','commits','comments','reviews','resolved','unresolved'].includes(key) ? -1 : 1);
    state.key = key;
    render();
  });
});
function value(row, key) {
  const map = {
    number: row.number,
    title: row.title,
    disposition: row.disposition,
    commits: row.signals.commitCount,
    comments: row.signals.issueComments,
    reviews: row.reviewRounds,
    resolved: row.signals.resolvedThreads,
    unresolved: row.signals.unresolvedThreads,
    mergeable: row.mergeable || '',
    labels: (row.labels || []).join(' '),
    reasons: (row.reasons || []).join(' ')
  };
  return map[key];
}
function render() {
  const q = state.q;
  let rows = report.rows.filter((row) => {
    if (!state.dispositions.has(row.disposition)) return false;
    if (state.mergeable && row.mergeable !== state.mergeable) return false;
    if (state.threads === 'open' && !(row.signals.unresolvedThreads > 0)) return false;
    if (state.threads === 'clear' && row.signals.unresolvedThreads > 0) return false;
    if (!q) return true;
    const hay = [row.number, row.title, row.disposition, (row.labels || []).join(' '), (row.reasons || []).join(' ')].join(' ').toLowerCase();
    return hay.includes(q);
  });
  rows.sort((a, b) => {
    const av = value(a, state.key);
    const bv = value(b, state.key);
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * state.dir;
    return String(av).localeCompare(String(bv)) * state.dir;
  });
  const body = document.getElementById('rows');
  body.replaceChildren();
  for (const row of rows) {
    const tr = document.createElement('tr');
    const cells = [
      row.number, row.title, row.disposition, row.signals.commitCount, row.signals.issueComments,
      row.reviewRounds, row.signals.resolvedThreads, row.signals.unresolvedThreads, row.mergeable || ''
    ];
    cells.forEach((text, index) => {
      const td = document.createElement('td');
      if (index === 0 || index >= 3 && index <= 7) td.className = 'num';
      if (index === 0) {
        const link = document.createElement('a');
        link.href = row.url;
        link.textContent = '#' + text;
        td.append(link);
      } else {
        td.textContent = text;
      }
      tr.append(td);
    });
    const labels = document.createElement('td');
    for (const name of row.labels || []) {
      const tag = document.createElement('span');
      tag.className = 'tag';
      tag.textContent = name;
      labels.append(tag);
    }
    const reasons = document.createElement('td');
    reasons.textContent = (row.reasons || []).join('; ');
    tr.append(labels, reasons);
    body.append(tr);
  }
  document.getElementById('meta').textContent = rows.length + ' shown of ' + report.rows.length;
  document.querySelectorAll('th[data-key]').forEach((th) => {
    const mark = th.dataset.key === state.key ? (state.dir < 0 ? ' \\u2193' : ' \\u2191') : '';
    th.textContent = th.dataset.label || th.textContent.replace(/ [\\u2191\\u2193]$/, '');
    if (!th.dataset.label) th.dataset.label = th.textContent;
    th.textContent = th.dataset.label + mark;
  });
}
render();
</script>
</body>
</html>
`;
}

export function writeReports(inventory, directory, generatedAt = new Date().toISOString()) {
  const report = buildReport(inventory, generatedAt);
  writeFileSync(join(directory, 'disposition.json'), JSON.stringify(report, null, 2));
  writeFileSync(join(directory, 'disposition.md'), renderMarkdown(report));
  writeFileSync(join(directory, 'disposition.html'), renderHtml(report));
  return report;
}

const invokedDirectly = process.argv[1] && process.argv[1].endsWith('report.mjs');
if (invokedDirectly) {
  const inputPath = process.argv[2];
  if (!inputPath) {
    console.error('Usage: node scripts/pr-portfolio/report.mjs <inventory.json>');
    process.exit(1);
  }
  const inventory = JSON.parse(readFileSync(inputPath, 'utf8'));
  const report = writeReports(inventory, dirname(inputPath));
  console.log(`Wrote disposition for ${report.rows.length} pull requests`);
  console.log(JSON.stringify(report.counts));
}
