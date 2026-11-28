import { test } from 'node:test';
import assert from 'node:assert/strict';
import { scorePullRequest } from './score.mjs';

const base = {
  number: 1,
  labels: [],
  commitCount: 1,
  issueComments: 0,
  reviewCount: 0,
  resolvedThreads: 0,
  unresolvedThreads: 0,
  mergeable: 'MERGEABLE',
  updatedAt: '2026-11-27T00:00:00Z',
  now: '2026-11-28T00:00:00Z',
};

test('keep-merge label wins over conflicts and low effort', () => {
  const row = scorePullRequest({
    ...base,
    labels: ['bug-fix'],
    mergeable: 'CONFLICTING',
    commitCount: 1,
  });
  assert.equal(row.disposition, 'merge-track');
});

test('eight commits without a keep label is salvage', () => {
  const row = scorePullRequest({ ...base, commitCount: 8, mergeable: 'CONFLICTING' });
  assert.equal(row.disposition, 'salvage');
});

test('six commits plus three comments is salvage', () => {
  const row = scorePullRequest({ ...base, commitCount: 6, issueComments: 2, reviewCount: 1 });
  assert.equal(row.disposition, 'salvage');
});

test('thin stale conflicting PR is a close candidate', () => {
  const row = scorePullRequest({
    ...base,
    commitCount: 2,
    mergeable: 'CONFLICTING',
    updatedAt: '2026-10-01T00:00:00Z',
  });
  assert.equal(row.disposition, 'close-candidate');
});

test('recent thin PR without conflict is not closed', () => {
  const row = scorePullRequest({ ...base, commitCount: 2 });
  assert.equal(row.disposition, 'review-then-decide');
});
