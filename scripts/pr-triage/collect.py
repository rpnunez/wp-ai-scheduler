#!/usr/bin/env python3
"""Collect triage metrics for every open pull request.

Read-only: queries GitHub through the authenticated `gh` CLI and measures
real merge conflicts locally with `git merge-tree`. Nothing on GitHub is
modified. PR heads are fetched into the private `refs/pr-triage/*` namespace.

Usage (from the repo root):
    python scripts/pr-triage/collect.py [--prs 2448,2450] [--out .artifacts/pr-triage]

Outputs:
    <out>/prs.json  full per-PR data (raw GitHub data + derived metrics)
    <out>/prs.csv   one flattened row per PR
"""

import argparse
import csv
import json
import os
import re
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone

# Logins treated as bots/agents. Bot reviews still count toward review rounds;
# the human/bot split is reported for information only.
BOT_LOGINS = {
	'copilot', 'copilot-pull-request-reviewer', 'copilot-swe-agent',
	'chatgpt-codex-connector', 'gemini-code-assist', 'google-labs-jules',
	'coderabbitai', 'sourcery-ai', 'claude', 'cursor', 'devin-ai-integration',
	'qodana', 'github-actions', 'dependabot', 'greptile-apps', 'openai-code-agent',
}

# Agent reviews are frequently posted as plain PR comments (by the repo owner
# on behalf of a local agent, or by bots). A long comment whose heading area
# matches this is treated as a review event and counts toward review rounds.
REVIEW_COMMENT_RE = re.compile(
	r'(?i)\b(review(ed|s)?|findings?|matrix[- ]review|approve_with_nits|audit|greptile_security_summary)\b')
REVIEW_COMMENT_EXCLUDE_RE = re.compile(r'(?i)sunset|not available for your account')
REVIEW_TRIGGER_RE = re.compile(r'(?i)^\s*(/\w+\s+review|@\w[\w-]*\s+review)\b')

PAGE_INFO = 'pageInfo{hasNextPage endCursor}'
ACTOR = 'author{login __typename}'

CONNECTIONS = {
	'commits': 'commits(first:100, after:$after){totalCount ' + PAGE_INFO
		+ ' nodes{commit{oid committedDate authoredDate messageHeadline'
		+ ' authors(first:5){nodes{name user{login}}}}}}',
	'comments': 'comments(first:100, after:$after){totalCount ' + PAGE_INFO
		+ ' nodes{createdAt body ' + ACTOR + '}}',
	'reviews': 'reviews(first:100, after:$after){totalCount ' + PAGE_INFO
		+ ' nodes{state submittedAt ' + ACTOR + ' commit{oid} comments{totalCount}}}',
	'reviewThreads': 'reviewThreads(first:100, after:$after){totalCount ' + PAGE_INFO
		+ ' nodes{isResolved isOutdated path resolvedBy{login}'
		+ ' comments(first:50){totalCount nodes{' + ACTOR + '}}}}',
	'files': 'files(first:100, after:$after){totalCount ' + PAGE_INFO
		+ ' nodes{path additions deletions}}',
}

PR_FIELDS = '''
id number title url isDraft createdAt updatedAt body
additions deletions changedFiles mergeable mergeStateStatus reviewDecision
baseRefName headRefName headRefOid
author{login __typename}
labels(first:50){nodes{name}}
closingIssuesReferences(first:20){nodes{number}}
participants(first:1){totalCount}
lastCommit: commits(last:1){nodes{commit{oid committedDate statusCheckRollup{state}}}}
forcePushes: timelineItems(first:1, itemTypes:[HEAD_REF_FORCE_PUSHED_EVENT]){filteredCount}
reviewRequests: timelineItems(first:1, itemTypes:[REVIEW_REQUESTED_EVENT]){filteredCount}
'''

PR_QUERY = ('query($owner:String!, $name:String!, $number:Int!, $after:String){'
	'repository(owner:$owner, name:$name){pullRequest(number:$number){'
	+ PR_FIELDS + ' '.join(CONNECTIONS.values()) + '}}}')

PAGE_QUERY = ('query($id:ID!, $after:String){node(id:$id){... on PullRequest{%s}}}')


def run(cmd, stdin=None, check=True):
	proc = subprocess.run(cmd, input=stdin, capture_output=True, text=True,
		encoding='utf-8', errors='replace')
	if check and proc.returncode != 0:
		raise RuntimeError('%s failed (%d): %s' % (' '.join(cmd[:4]), proc.returncode, proc.stderr.strip()))
	return proc


def gh_graphql(query, variables, retries=3):
	body = json.dumps({'query': query, 'variables': variables})
	last = None
	for attempt in range(retries):
		proc = run(['gh', 'api', 'graphql', '--input', '-'], stdin=body, check=False)
		if proc.returncode == 0:
			data = json.loads(proc.stdout)
			if data.get('errors'):
				last = data['errors']
			else:
				return data['data']
		else:
			last = proc.stderr.strip()
		time.sleep(2 * (attempt + 1))
	raise RuntimeError('GraphQL failed: %s' % last)


def detect_repo(remote):
	url = run(['git', 'remote', 'get-url', remote]).stdout.strip()
	m = re.search(r'github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$', url)
	if not m:
		sys.exit('Cannot parse GitHub repo from remote %s: %s' % (remote, url))
	return m.group(1), m.group(2)


def login_of(actor):
	return (actor or {}).get('login') or 'ghost'


def is_bot(actor):
	if not actor:
		return False
	login = (actor.get('login') or '').lower()
	return (actor.get('__typename') == 'Bot' or login.endswith('[bot]')
		or login.replace('[bot]', '') in BOT_LOGINS)


def parse_dt(value):
	return datetime.fromisoformat(value.replace('Z', '+00:00')) if value else None


def fetch_pr(owner, name, number):
	data = gh_graphql(PR_QUERY, {'owner': owner, 'name': name, 'number': number, 'after': None})
	pr = data['repository']['pullRequest']
	for conn, fragment in CONNECTIONS.items():
		block = pr[conn]
		while block['pageInfo']['hasNextPage']:
			page = gh_graphql(PAGE_QUERY % fragment,
				{'id': pr['id'], 'after': block['pageInfo']['endCursor']})['node'][conn]
			pr[conn]['nodes'].extend(page['nodes'])
			block = page
		pr[conn]['pageInfo'] = {'hasNextPage': False}
	return pr


def github_metrics(pr, now):
	m = {}
	m['number'] = pr['number']
	m['title'] = pr['title']
	m['url'] = pr['url']
	m['author'] = login_of(pr['author'])
	m['author_is_bot'] = is_bot(pr['author'])
	m['labels'] = sorted(l['name'] for l in pr['labels']['nodes'])
	m['draft'] = pr['isDraft']
	m['created_at'] = pr['createdAt']
	m['updated_at'] = pr['updatedAt']
	m['age_days'] = (now - parse_dt(pr['createdAt'])).days
	m['days_since_update'] = (now - parse_dt(pr['updatedAt'])).days
	m['additions'] = pr['additions']
	m['deletions'] = pr['deletions']
	m['changed_files'] = pr['changedFiles']
	m['gh_mergeable'] = pr['mergeable']
	m['gh_merge_state'] = pr['mergeStateStatus']
	m['review_decision'] = pr['reviewDecision'] or ''
	m['head_ref'] = pr['headRefName']
	m['head_oid'] = pr['headRefOid']
	m['base_ref'] = pr['baseRefName']
	m['closing_issues'] = [i['number'] for i in pr['closingIssuesReferences']['nodes']]
	m['participants'] = pr['participants']['totalCount']
	m['force_pushes'] = pr['forcePushes']['filteredCount']
	m['review_requests'] = pr['reviewRequests']['filteredCount']
	last = (pr['lastCommit']['nodes'] or [{}])[0].get('commit') or {}
	m['ci_state'] = ((last.get('statusCheckRollup') or {}).get('state')) or 'NONE'

	# Commits
	commits = [n['commit'] for n in pr['commits']['nodes']]
	m['commits'] = pr['commits']['totalCount']
	authors = set()
	for c in commits:
		for a in c['authors']['nodes']:
			authors.add(((a.get('user') or {}).get('login')) or a.get('name') or '?')
	m['commit_authors'] = sorted(authors)
	dates = sorted(parse_dt(c['committedDate']) for c in commits if c.get('committedDate'))
	m['first_commit_at'] = dates[0].isoformat() if dates else ''
	m['last_commit_at'] = dates[-1].isoformat() if dates else ''
	m['commit_span_days'] = (dates[-1] - dates[0]).days if dates else 0
	commit_dates = {c['oid']: parse_dt(c['committedDate']) for c in commits}

	# Conversation comments
	comments = pr['comments']['nodes']
	m['comments'] = pr['comments']['totalCount']
	m['comments_bot'] = sum(1 for c in comments if is_bot(c['author']))
	m['comments_human'] = m['comments'] - m['comments_bot']
	m['comment_participants'] = len({login_of(c['author']) for c in comments})

	# Reviews. A "round" is a distinct head commit that received >= 1 review:
	# push -> review -> push -> review == 2 rounds. Bot reviews count.
	reviews = [r for r in pr['reviews']['nodes'] if r['state'] != 'PENDING']
	m['reviews'] = len(reviews)
	m['reviews_bot'] = sum(1 for r in reviews if is_bot(r['author']))
	m['reviews_human'] = m['reviews'] - m['reviews_bot']
	for state in ('APPROVED', 'CHANGES_REQUESTED', 'COMMENTED', 'DISMISSED'):
		m['reviews_' + state.lower()] = sum(1 for r in reviews if r['state'] == state)
	m['reviewers'] = sorted({login_of(r['author']) for r in reviews})
	round_keys = {}
	for r in reviews:
		key = (r.get('commit') or {}).get('oid') or ('unknown-' + (r['submittedAt'] or '')[:10])
		round_keys.setdefault(key, []).append(r)
	m['review_rounds_formal'] = len(round_keys)
	m['review_rounds_human'] = sum(1 for rs in round_keys.values() if any(not is_bot(r['author']) for r in rs))
	m['review_rounds_bot'] = sum(1 for rs in round_keys.values() if any(is_bot(r['author']) for r in rs))

	# Agent reviews posted as comments: attribute each to the head commit that
	# existed when it was posted, and merge into the round set.
	ordered = sorted((d, oid) for oid, d in commit_dates.items() if d)
	agent_keys = set()
	m['review_comments_agent'] = 0
	m['review_triggers'] = 0
	for c in comments:
		body = c.get('body') or ''
		if REVIEW_TRIGGER_RE.match(body):
			m['review_triggers'] += 1
			continue
		if len(body) < 80 or REVIEW_COMMENT_EXCLUDE_RE.search(body) or not REVIEW_COMMENT_RE.search(body[:300]):
			continue
		m['review_comments_agent'] += 1
		at = parse_dt(c['createdAt'])
		prior = [oid for d, oid in ordered if d <= at]
		agent_keys.add(prior[-1] if prior else 'pre-' + c['createdAt'][:10])
	m['review_rounds_comment'] = len(agent_keys - set(round_keys))
	all_keys = set(round_keys) | agent_keys
	m['review_rounds'] = len(all_keys)
	# Rounds that were followed by new commits (feedback iterated on).
	head = pr['headRefOid']
	m['review_rounds_addressed'] = sum(1 for k in all_keys if k != head)
	m['review_inline_comments'] = sum(r['comments']['totalCount'] for r in reviews)
	last_review = max((parse_dt(r['submittedAt']) for r in reviews if r['submittedAt']), default=None)
	m['last_review_at'] = last_review.isoformat() if last_review else ''
	m['commits_after_last_review'] = sum(1 for d in commit_dates.values() if last_review and d and d > last_review)

	# Review threads
	threads = pr['reviewThreads']['nodes']
	m['threads'] = pr['reviewThreads']['totalCount']
	m['threads_resolved'] = sum(1 for t in threads if t['isResolved'])
	m['threads_unresolved'] = m['threads'] - m['threads_resolved']
	m['threads_outdated'] = sum(1 for t in threads if t['isOutdated'])
	# Unresolved but outdated threads usually mean the code moved on.
	m['threads_unresolved_active'] = sum(1 for t in threads if not t['isResolved'] and not t['isOutdated'])
	m['threads_bot_started'] = sum(1 for t in threads
		if t['comments']['nodes'] and is_bot(t['comments']['nodes'][0]['author']))
	m['thread_comments'] = sum(t['comments']['totalCount'] for t in threads)
	m['threads_with_replies'] = sum(1 for t in threads if t['comments']['totalCount'] > 1)

	m['files'] = sorted(f['path'] for f in pr['files']['nodes'])
	body = pr.get('body') or ''
	m['body_excerpt'] = body[:3000]
	return m


def git_fetch(remote, base, numbers):
	refspecs = ['+refs/heads/%s:refs/remotes/%s/%s' % (base, remote, base)]
	refspecs += ['+refs/pull/%d/head:refs/pr-triage/%d' % (n, n) for n in numbers]
	for i in range(0, len(refspecs), 40):
		run(['git', 'fetch', '--no-tags', '--quiet', remote] + refspecs[i:i + 40])


def git_metrics(m, remote, base):
	main = '%s/%s' % (remote, base)
	ref = 'refs/pr-triage/%d' % m['number']
	g = {}
	oid = run(['git', 'rev-parse', '--verify', '--quiet', ref], check=False).stdout.strip()
	g['git_fetched'] = bool(oid)
	if not oid:
		return g
	g['git_head_matches'] = oid == m['head_oid']
	behind, ahead = run(['git', 'rev-list', '--left-right', '--count', '%s...%s' % (main, ref)]).stdout.split()
	g['behind_by'] = int(behind)
	g['ahead_by'] = int(ahead)
	mb = run(['git', 'merge-base', main, ref], check=False).stdout.strip()
	g['merge_base'] = mb
	g['merge_base_date'] = run(['git', 'show', '-s', '--format=%cI', mb]).stdout.strip() if mb else ''

	# Real conflict measurement (no worktree changes).
	proc = run(['git', 'merge-tree', '--write-tree', main, ref], check=False)
	lines = proc.stdout.splitlines()
	conflict_files = set()
	types = {}
	tree = lines[0].strip() if lines else ''
	if proc.returncode == 1:
		i = 1
		while i < len(lines) and lines[i].strip():
			parts = lines[i].split('\t', 1)
			if len(parts) == 2:
				conflict_files.add(parts[1])
			i += 1
		for line in lines[i:]:
			t = re.match(r'CONFLICT \(([^)]+)\)', line)
			if t:
				types[t.group(1)] = types.get(t.group(1), 0) + 1
	elif proc.returncode != 0:
		g['merge_tree_error'] = proc.stderr.strip()[:300]
	hunks = 0
	for path in conflict_files:
		blob = run(['git', 'cat-file', '-p', '%s:%s' % (tree, path)], check=False)
		if blob.returncode == 0:
			hunks += sum(1 for l in blob.stdout.splitlines() if l.startswith('<<<<<<< '))
	g['merge_clean'] = proc.returncode == 0
	g['conflict_files'] = sorted(conflict_files)
	g['conflict_files_count'] = len(conflict_files)
	g['conflict_hunks'] = hunks
	g['conflict_types'] = types

	# How much has main moved underneath this PR's files?
	pr_files = set(m['files'])
	deleted, renamed, changed = [], [], set()
	if mb:
		diff = run(['git', 'diff', '--name-status', '-M', mb, main]).stdout.splitlines()
		for line in diff:
			parts = line.split('\t')
			status = parts[0][:1]
			if status == 'D' and parts[1] in pr_files:
				deleted.append(parts[1])
			elif status == 'R' and parts[1] in pr_files:
				renamed.append('%s -> %s' % (parts[1], parts[2]))
			if parts[-1] in pr_files or parts[1] in pr_files:
				changed.add(parts[1])
		log = run(['git', 'log', '--format=%x01%H', '--name-only', '%s..%s' % (mb, main)]).stdout
		touching = 0
		for chunk in log.split('\x01')[1:]:
			if pr_files.intersection(l.strip() for l in chunk.splitlines()[1:] if l.strip()):
				touching += 1
		g['main_commits_touching_pr_files'] = touching
	g['pr_files_changed_in_main'] = len(changed)
	g['pr_files_deleted_in_main'] = sorted(deleted)
	g['pr_files_renamed_in_main'] = sorted(renamed)
	return g


CSV_SKIP = {'files', 'body_excerpt', 'conflict_files', 'url'}


def write_csv(path, rows):
	keys = []
	for r in rows:
		for k in r:
			if k not in keys and k not in CSV_SKIP:
				keys.append(k)
	with open(path, 'w', newline='', encoding='utf-8') as fh:
		w = csv.DictWriter(fh, fieldnames=keys, extrasaction='ignore')
		w.writeheader()
		for r in rows:
			w.writerow({k: (';'.join(map(str, v)) if isinstance(v, list)
				else json.dumps(v) if isinstance(v, dict) else v) for k, v in r.items() if k in keys})


def main():
	ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
	ap.add_argument('--remote', default='origin')
	ap.add_argument('--base', default='main')
	ap.add_argument('--prs', help='Comma-separated PR numbers (default: all open PRs)')
	ap.add_argument('--out', default='.artifacts/pr-triage')
	ap.add_argument('--workers', type=int, default=6)
	ap.add_argument('--skip-git', action='store_true', help='Skip local fetch/conflict analysis')
	args = ap.parse_args()

	owner, name = detect_repo(args.remote)
	if args.prs:
		numbers = [int(n) for n in args.prs.split(',') if n.strip()]
	else:
		out = run(['gh', 'pr', 'list', '-R', '%s/%s' % (owner, name), '--state', 'open',
			'--base', args.base, '--limit', '1000', '--json', 'number']).stdout
		numbers = sorted((p['number'] for p in json.loads(out)), reverse=True)
	print('Collecting %d PRs from %s/%s ...' % (len(numbers), owner, name), file=sys.stderr)

	now = datetime.now(timezone.utc)
	with ThreadPoolExecutor(max_workers=args.workers) as pool:
		raw = list(pool.map(lambda n: fetch_pr(owner, name, n), numbers))
	rows = [github_metrics(pr, now) for pr in raw]

	if not args.skip_git:
		print('Fetching PR heads and measuring conflicts ...', file=sys.stderr)
		git_fetch(args.remote, args.base, numbers)
		for m in rows:
			m.update(git_metrics(m, args.remote, args.base))

	os.makedirs(args.out, exist_ok=True)
	payload = {
		'repo': '%s/%s' % (owner, name),
		'base': args.base,
		'generated_at': now.isoformat(),
		'prs': rows,
	}
	with open(os.path.join(args.out, 'prs.json'), 'w', encoding='utf-8') as fh:
		json.dump(payload, fh, indent=1)
	write_csv(os.path.join(args.out, 'prs.csv'), rows)
	print('Wrote %d PRs to %s' % (len(rows), args.out), file=sys.stderr)


if __name__ == '__main__':
	main()
