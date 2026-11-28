import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { writeReports } from './report.mjs';

test('html report includes filters and escaped row data', () => {
  const directory = mkdtempSync(join(tmpdir(), 'pr-portfolio-'));
  const report = writeReports([
    {
      number: 7,
      title: 'Keep <script>alert(1)</script>',
      url: 'https://example.test/pull/7',
      labels: ['chances-high'],
      commitCount: 2,
      issueComments: 0,
      reviewCount: 0,
      resolvedThreads: 0,
      unresolvedThreads: 0,
      mergeable: 'MERGEABLE',
      updatedAt: '2026-11-27T00:00:00Z',
      isDraft: false,
      latestReviewState: null,
      hasApproval: false,
    },
  ], directory, '2026-11-28T00:00:00.000Z');

  assert.equal(report.rows[0].disposition, 'merge-track');
  const html = readFileSync(join(directory, 'disposition.html'), 'utf8');
  assert.match(html, /id="q"/);
  assert.match(html, /data-key="commits"/);
  assert.match(html, /id="dispositions"/);
  assert.match(html, /This ranking is not merge approval\./);
  assert.doesNotMatch(html, /<script>alert/);
  assert.match(html, /\\u003c\/script>/);
  assert.match(readFileSync(join(directory, 'disposition.md'), 'utf8'), /#7 Keep/);
});
