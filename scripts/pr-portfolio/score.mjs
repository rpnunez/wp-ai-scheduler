const KEEP_LABELS = new Set(['chances-high', 'fix', 'bugfix', 'bug-fix']);
const DAY_MS = 24 * 60 * 60 * 1000;

export function scorePullRequest(pr) {
  const labels = pr.labels || [];
  const keep = labels.some((name) => KEEP_LABELS.has(name));
  const discussion = (pr.issueComments || 0) + (pr.reviewCount || 0);
  const updated = Date.parse(pr.updatedAt);
  const now = Date.parse(pr.now);
  const ageDays = Number.isFinite(updated) && Number.isFinite(now)
    ? (now - updated) / DAY_MS
    : 0;
  const reasons = [];

  let disposition;
  if (keep) {
    disposition = 'merge-track';
    reasons.push('keep-merge label');
  } else if (pr.commitCount >= 8 || (pr.commitCount >= 6 && discussion >= 3)) {
    disposition = 'salvage';
    reasons.push('commit and discussion effort');
  } else if (
    pr.commitCount <= 3 &&
    (pr.issueComments || 0) === 0 &&
    (pr.reviewCount || 0) === 0 &&
    (pr.unresolvedThreads || 0) === 0 &&
    (pr.mergeable === 'CONFLICTING' || ageDays > 21)
  ) {
    disposition = 'close-candidate';
    reasons.push(pr.mergeable === 'CONFLICTING' ? 'thin and conflicting' : 'thin and stale');
  } else {
    disposition = 'review-then-decide';
    reasons.push('no keep label and effort below salvage');
  }

  if (pr.mergeable === 'CONFLICTING') {
    reasons.push('merge conflicts');
  }
  if ((pr.unresolvedThreads || 0) > 0) {
    reasons.push(`${pr.unresolvedThreads} unresolved review threads`);
  }

  return {
    number: pr.number,
    disposition,
    reasons,
    signals: {
      commitCount: pr.commitCount,
      issueComments: pr.issueComments || 0,
      reviewCount: pr.reviewCount || 0,
      resolvedThreads: pr.resolvedThreads || 0,
      unresolvedThreads: pr.unresolvedThreads || 0,
      ageDays: Math.round(ageDays),
      mergeable: pr.mergeable,
    },
  };
}

export function scoreAll(list) {
  return list.map(scorePullRequest);
}
