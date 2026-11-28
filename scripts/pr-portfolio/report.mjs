import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { scoreAll } from './score.mjs';

const ORDER = ['merge-track', 'salvage', 'close-candidate', 'review-then-decide'];

const inputPath = process.argv[2];
if (!inputPath) {
  console.error('Usage: node scripts/pr-portfolio/report.mjs <inventory.json>');
  process.exit(1);
}

const inventory = JSON.parse(readFileSync(inputPath, 'utf8'));
const generatedAt = new Date().toISOString();
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

const disclaimer = 'This ranking is not merge approval.';
const report = { generatedAt, disclaimer, counts, rows };
const directory = dirname(inputPath);
writeFileSync(join(directory, 'disposition.json'), JSON.stringify(report, null, 2));

const lines = [
  '# PR portfolio disposition',
  '',
  disclaimer,
  '',
  `Generated: ${generatedAt}`,
  '',
  '| Disposition | Count |',
  '| --- | ---: |',
];
for (const name of ORDER) {
  lines.push(`| ${name} | ${counts[name]} |`);
}
lines.push('');

for (const name of ORDER) {
  lines.push(`## ${name}`, '');
  const section = rows.filter((row) => row.disposition === name);
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

writeFileSync(join(directory, 'disposition.md'), `${lines.join('\n')}\n`);
console.log(`Wrote disposition for ${rows.length} pull requests`);
console.log(JSON.stringify(counts));
