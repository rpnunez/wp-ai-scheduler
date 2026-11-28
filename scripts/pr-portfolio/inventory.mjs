import { execFile } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

const QUERY = `
query($owner: String!, $repo: String!, $cursor: String) {
  repository(owner: $owner, name: $repo) {
    pullRequests(states: OPEN, first: 15, after: $cursor, orderBy: {field: UPDATED_AT, direction: DESC}) {
      pageInfo { hasNextPage endCursor }
      nodes {
        number
        title
        url
        isDraft
        createdAt
        updatedAt
        additions
        deletions
        changedFiles
        mergeable
        reviewDecision
        labels(first: 20) { nodes { name } }
        commits { totalCount }
        comments { totalCount }
        reviews(first: 50) {
          totalCount
          nodes { state submittedAt }
        }
        reviewThreads(first: 100) {
          totalCount
          nodes { isResolved }
        }
      }
    }
  }
}
`;

function parseOrigin(url) {
  const match = String(url).match(/github\.com[:/]([^/]+)\/([^/.]+)/);
  if (!match) {
    throw new Error(`Cannot parse owner/repo from origin: ${url}`);
  }
  return { owner: match[1], repo: match[2] };
}

async function gh(args) {
  let lastError;
  for (let attempt = 1; attempt <= 3; attempt += 1) {
    try {
      const { stdout } = await execFileAsync('gh', args, { maxBuffer: 20 * 1024 * 1024 });
      return stdout;
    } catch (error) {
      lastError = error;
      const text = `${error.stderr || ''}${error.message || ''}`;
      if (!/rate limit/i.test(text) || attempt === 3) {
        throw error;
      }
      await new Promise((resolve) => setTimeout(resolve, 5000));
    }
  }
  throw lastError;
}

function mapNode(node) {
  const threads = node.reviewThreads?.nodes || [];
  const reviews = node.reviews?.nodes || [];
  const reviewStates = { APPROVED: 0, CHANGES_REQUESTED: 0, COMMENTED: 0 };
  let latestReviewState = null;
  let latestAt = '';
  for (const review of reviews) {
    if (reviewStates[review.state] !== undefined) {
      reviewStates[review.state] += 1;
    }
    if (review.submittedAt && review.submittedAt >= latestAt) {
      latestAt = review.submittedAt;
      latestReviewState = review.state;
    }
  }
  return {
    number: node.number,
    title: node.title,
    url: node.url,
    isDraft: node.isDraft,
    createdAt: node.createdAt,
    updatedAt: node.updatedAt,
    additions: node.additions,
    deletions: node.deletions,
    changedFiles: node.changedFiles,
    mergeable: node.mergeable,
    reviewDecision: node.reviewDecision,
    labels: (node.labels?.nodes || []).map((label) => label.name),
    commitCount: node.commits?.totalCount || 0,
    issueComments: node.comments?.totalCount || 0,
    reviewCount: node.reviews?.totalCount || 0,
    resolvedThreads: threads.filter((thread) => thread.isResolved).length,
    unresolvedThreads: threads.filter((thread) => !thread.isResolved).length,
    threadCountTruncated: (node.reviewThreads?.totalCount || 0) > threads.length,
    reviewStates,
    latestReviewState,
    hasApproval: reviews.some((review) => review.state === 'APPROVED'),
  };
}

async function collect() {
  const origin = (await execFileAsync('git', ['remote', 'get-url', 'origin'])).stdout.trim();
  const { owner, repo } = parseOrigin(origin);
  const byNumber = new Map();
  let cursor = '';
  for (let page = 0; page < 20; page += 1) {
    const stdout = await gh([
      'api',
      'graphql',
      '-f',
      `query=${QUERY}`,
      '-f',
      `owner=${owner}`,
      '-f',
      `repo=${repo}`,
      '-f',
      `cursor=${cursor}`,
    ]);
    const payload = JSON.parse(stdout);
    if (payload.errors) {
      throw new Error(JSON.stringify(payload.errors));
    }
    const connection = payload.data.repository.pullRequests;
    for (const node of connection.nodes) {
      byNumber.set(node.number, mapNode(node));
    }
    if (!connection.pageInfo.hasNextPage) {
      break;
    }
    cursor = connection.pageInfo.endCursor;
  }
  return [...byNumber.values()];
}

const outputPath = process.argv[2];
if (!outputPath) {
  console.error('Usage: node scripts/pr-portfolio/inventory.mjs <output.json>');
  process.exit(1);
}

try {
  const rows = await collect();
  writeFileSync(outputPath, JSON.stringify(rows, null, 2));
  console.log(`Wrote ${rows.length} pull requests to ${outputPath}`);
} catch (error) {
  console.error(error.stderr || error.message);
  process.exit(1);
}
