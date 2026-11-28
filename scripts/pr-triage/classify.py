#!/usr/bin/env python3
"""Score, classify, and report on the data produced by collect.py.

Usage (from the repo root):
    python scripts/pr-triage/classify.py [--in .artifacts/pr-triage]

Outputs (into the same directory):
    triage.json, triage.csv     per-PR scores, bucket, action, rationale
    pr_metrics_report.md        the base metrics table for every PR
    pr_triage_report.md         buckets, duplicate clusters, merge waves

Nothing on GitHub is modified; label/close suggestions are listed only.
"""

import argparse
import csv
import json
import os
import re
from collections import Counter
from difflib import SequenceMatcher

# --- Policy -----------------------------------------------------------------
MANDATORY_LABELS = {'chances-high', 'fix', 'bugfix', 'bug-fix'}
HIGH_EFFORT_COMMITS = 5        # >= this many commits => high effort (keep)
HIGH_EFFORT_ROUNDS = 2         # OR >= this many review rounds (bots count)
HEAVY_CONFLICT_FILES = 8       # conflict files at/above this => heavy rebase
HEAVY_BEHIND = 300             # commits behind main at/above this => heavy
MINOR_CONFLICT_FILES = 3       # <= this => minor rebase
DUP_JACCARD = 0.5              # file-set similarity => duplicate candidate
DUP_TITLE_SIM = 0.55           # title similarity (with DUP_TITLE_JACCARD files)
DUP_TITLE_JACCARD = 0.2
OVERLAP_JACCARD = 0.25         # reported as overlapping (rebase interplay)
HOT_FILE_SHARE = 0.25          # ignore files touched by > 25% of PRs for overlap
ALWAYS_IGNORE = {'CHANGELOG.md', 'ai-post-scheduler/CHANGELOG.md', 'README.md',
	'ai-post-scheduler/readme.txt', 'ai-post-scheduler/ai-post-scheduler.php'}

BUCKETS = {
	'A': 'Keep - mandatory label',
	'B': 'Merge now',
	'C': 'Keep - high effort (finish / rebase)',
	'D': 'Possible duplicate / superseded',
	'E': 'Close candidate',
	'F': 'Needs review (low effort, moderate cost)',
}
SUGGESTED_LABELS = {
	'A': [], 'B': ['ready-to-merge'], 'C': [], 'D': ['chances-low', 'duplicate'],
	'E': ['wontfix'], 'F': ['chances-low', 'review-needed'],
}


def clamp(x):
	return max(0.0, min(1.0, x))


def effort_score(p):
	return round(100 * (
		0.35 * clamp(p['commits'] / 10)
		+ 0.25 * clamp(p['review_rounds'] / 4)
		+ 0.15 * clamp(p['threads_resolved'] / 10)
		+ 0.15 * clamp((p['comments'] + p['thread_comments']) / 20)
		+ 0.10 * clamp(p['participants'] / 4)))


def cost_score(p):
	return round(100 * (
		0.30 * clamp(p.get('conflict_files_count', 0) / 10)
		+ 0.10 * clamp(p.get('conflict_hunks', 0) / 30)
		+ 0.20 * clamp(p.get('behind_by', 0) / 400)
		+ 0.10 * (1 if p.get('pr_files_deleted_in_main') else 0)
		+ 0.10 * clamp(p['threads_unresolved_active'] / 10)
		+ 0.10 * (1 if p['ci_state'] in ('FAILURE', 'ERROR') else 0)
		+ 0.10 * clamp((p['additions'] + p['deletions']) / 10000)))


def norm_title(t):
	t = re.sub(r'\[[^\]]*\]|\(#?\d+\)|#\d+', ' ', t.lower())
	t = re.sub(r'^(feat|fix|refactor|chore|docs|test)(\([^)]*\))?:', ' ', t)
	return re.sub(r'[^a-z0-9 ]+', ' ', t).strip()


def series_key(t):
	m = re.search(r'#(\d+)\s*-?\s*part\s*\d+', t, re.I)
	return m.group(1) if m else None


def analyze_overlap(prs):
	counts = Counter(f for p in prs for f in set(p['files']))
	limit = max(2, HOT_FILE_SHARE * len(prs))
	hot = {f for f, c in counts.items() if c > limit} | ALWAYS_IGNORE
	sets = {p['number']: set(p['files']) - hot for p in prs}
	titles = {p['number']: norm_title(p['title']) for p in prs}
	series = {p['number']: series_key(p['title']) for p in prs}
	edges = []
	nums = [p['number'] for p in prs]
	for i, a in enumerate(nums):
		for b in nums[i + 1:]:
			sa, sb = sets[a], sets[b]
			shared = sa & sb
			jac = len(shared) / len(sa | sb) if (sa | sb) else 0.0
			tsim = SequenceMatcher(None, titles[a], titles[b]).ratio()
			same_series = series[a] and series[a] == series[b]
			dup = not same_series and len(shared) >= 2 and (
				jac >= DUP_JACCARD or (tsim >= DUP_TITLE_SIM and jac >= DUP_TITLE_JACCARD))
			if dup or jac >= OVERLAP_JACCARD:
				edges.append({'a': a, 'b': b, 'jaccard': round(jac, 2), 'title_sim': round(tsim, 2),
					'shared': len(shared), 'duplicate': dup, 'series': bool(same_series)})
	return edges, hot


def components(nodes, edges):
	parent = {n: n for n in nodes}

	def find(x):
		while parent[x] != x:
			parent[x] = parent[parent[x]]
			x = parent[x]
		return x
	for e in edges:
		parent[find(e['a'])] = find(e['b'])
	groups = {}
	for n in nodes:
		groups.setdefault(find(n), []).append(n)
	return [sorted(g, reverse=True) for g in groups.values() if len(g) > 1]


def rationale(p):
	bits = ['%d commits' % p['commits'], '%d review rounds' % p['review_rounds']]
	if p['threads']:
		bits.append('%d/%d threads resolved' % (p['threads_resolved'], p['threads']))
	if p['threads_unresolved_active']:
		bits.append('%d active unresolved' % p['threads_unresolved_active'])
	bits.append('%d behind' % p.get('behind_by', 0))
	if p.get('merge_clean'):
		bits.append('merges clean')
	else:
		bits.append('%d conflict files/%d hunks' % (p.get('conflict_files_count', 0), p.get('conflict_hunks', 0)))
	if p.get('pr_files_deleted_in_main'):
		bits.append('%d files deleted in main' % len(p['pr_files_deleted_in_main']))
	if p['ci_state'] in ('FAILURE', 'ERROR'):
		bits.append('CI failing')
	return ', '.join(bits)


def classify(prs, edges):
	by_num = {p['number']: p for p in prs}
	for p in prs:
		p['effort_score'] = effort_score(p)
		p['cost_score'] = cost_score(p)
		p['mandatory'] = bool(MANDATORY_LABELS & set(p['labels']))
		p['high_effort'] = p['commits'] >= HIGH_EFFORT_COMMITS or p['review_rounds'] >= HIGH_EFFORT_ROUNDS
		p['ready'] = (bool(p.get('merge_clean')) and not p['draft']
			and p['ci_state'] not in ('FAILURE', 'ERROR') and p['threads_unresolved_active'] == 0)
		# Being far behind only matters when the PR no longer merges cleanly.
		p['heavy_cost'] = (p.get('conflict_files_count', 0) >= HEAVY_CONFLICT_FILES
			or (p.get('behind_by', 0) >= HEAVY_BEHIND and not p.get('merge_clean'))
			or bool(p.get('pr_files_deleted_in_main')))
		p['overlaps'] = []
		p['duplicate_of'] = []

	def rank(p):  # higher wins a duplicate pair
		return (p['mandatory'], p['effort_score'], -p['cost_score'], p['number'])
	for e in edges:
		a, b = by_num[e['a']], by_num[e['b']]
		a['overlaps'].append(b['number'])
		b['overlaps'].append(a['number'])
		if e['duplicate']:
			loser, winner = (a, b) if rank(a) < rank(b) else (b, a)
			loser['duplicate_of'].append(winner['number'])

	for p in prs:
		if p['mandatory']:
			bucket = 'A'
		elif p['duplicate_of']:
			bucket = 'D'
		elif p['ready']:
			bucket = 'B'
		elif p['high_effort']:
			bucket = 'C'
		elif p['heavy_cost']:
			bucket = 'E'
		else:
			bucket = 'F'
		p['bucket'] = bucket

		if p['ready']:
			action = 'final review + merge'
		elif not p.get('merge_clean'):
			n = p.get('conflict_files_count', 0)
			action = ('minor rebase' if n <= MINOR_CONFLICT_FILES
				else 'heavy rebase/port' if n >= HEAVY_CONFLICT_FILES else 'rebase')
		elif p['threads_unresolved_active']:
			action = 'resolve review threads'
		elif p['ci_state'] in ('FAILURE', 'ERROR'):
			action = 'fix CI'
		else:
			action = 'undraft + review'
		if bucket == 'D':
			action = 'compare with #%s, close the weaker' % ', #'.join(map(str, p['duplicate_of']))
		elif bucket == 'E':
			action = 'close with comment (branch kept)'
		p['action'] = action
		p['suggested_labels'] = [l for l in SUGGESTED_LABELS[bucket] if l not in p['labels']]
		p['rationale'] = rationale(p)

		if bucket in ('A', 'B', 'C'):
			if p['ready']:
				p['wave'] = 1
			elif p.get('conflict_files_count', 0) <= MINOR_CONFLICT_FILES and not p.get('pr_files_deleted_in_main'):
				p['wave'] = 2
			else:
				p['wave'] = 3
		else:
			p['wave'] = None
	return prs


def md_escape(s):
	return str(s).replace('|', '\\|')


def short(s, n=48):
	return s if len(s) <= n else s[:n - 1] + '…'


def link(p):
	return '[#%d](%s)' % (p['number'], p['url'])


def metrics_report(data, prs):
	out = ['# Open PR Metrics - %s' % data['repo'], '',
		'Generated %s against `%s`. %d open PRs.' % (data['generated_at'][:16], data['base'], len(prs)), '',
		'Legend: **Rnd** review rounds (distinct reviewed head commits, bots included; h/b = human/bot), '
		'**Rev A/CR/C** approved / changes-requested / commented reviews, **Thr** resolved/total threads '
		'(active unresolved), **Cmt** conversation comments human/bot, **Conf** conflicting files/hunks '
		'vs current main, **Eff/Cost** 0-100 scores.', '',
		'| PR | Title | Labels | Age d | Commits | Rnd (h/b/c) | Rev A/CR/C | Thr res/tot (act) | Cmt h/b | Behind | Conf | Del-in-main | CI | +/- | Eff | Cost |',
		'|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|']
	for p in sorted(prs, key=lambda x: -x['number']):
		conf = 'clean' if p.get('merge_clean') else '%d/%d' % (p.get('conflict_files_count', 0), p.get('conflict_hunks', 0))
		out.append('| %s | %s | %s | %d | %d | %d (%d/%d/%d) | %d/%d/%d | %d/%d (%d) | %d/%d | %s | %s | %d | %s | +%d/-%d | %d | %d |' % (
			link(p), md_escape(short(p['title'])), md_escape(', '.join(p['labels'])), p['age_days'],
			p['commits'], p['review_rounds'], p['review_rounds_human'], p['review_rounds_bot'], p['review_rounds_comment'],
			p['reviews_approved'], p['reviews_changes_requested'], p['reviews_commented'],
			p['threads_resolved'], p['threads'], p['threads_unresolved_active'],
			p['comments_human'], p['comments_bot'], p.get('behind_by', '?'), conf,
			len(p.get('pr_files_deleted_in_main', [])), p['ci_state'], p['additions'], p['deletions'],
			p['effort_score'], p['cost_score']))
	tot = lambda k: sum(p.get(k, 0) for p in prs)
	out += ['', '## Totals', '',
		'- Commits: %d; review rounds: %d; reviews: %d (%d bot)' % (tot('commits'), tot('review_rounds'), tot('reviews'), tot('reviews_bot')),
		'- Review threads: %d resolved / %d unresolved (%d active)' % (tot('threads_resolved'), tot('threads_unresolved'), tot('threads_unresolved_active')),
		'- Merge clean against main: %d / %d' % (sum(1 for p in prs if p.get('merge_clean')), len(prs)),
		'- High effort (>=%d commits or >=%d rounds): %d' % (HIGH_EFFORT_COMMITS, HIGH_EFFORT_ROUNDS, sum(1 for p in prs if p['high_effort'])),
		'']
	return '\n'.join(out)


def triage_report(data, prs, edges, hot):
	by_num = {p['number']: p for p in prs}
	out = ['# Open PR Triage - %s' % data['repo'], '',
		'Generated %s. Read-only analysis; no labels, closes, or merges were applied.' % data['generated_at'][:16], '',
		'## Policy', '',
		'- **Mandatory keep:** any label in `%s`.' % '`, `'.join(sorted(MANDATORY_LABELS)),
		'- **High effort keep:** >= %d commits **or** >= %d review rounds (bot/agent reviews count).' % (HIGH_EFFORT_COMMITS, HIGH_EFFORT_ROUNDS),
		'- **Ready:** merges clean with `%s`, not draft, CI not failing, no active unresolved threads.' % data['base'],
		'- **Heavy cost:** >= %d conflicting files, >= %d commits behind, or touches files deleted in main.' % (HEAVY_CONFLICT_FILES, HEAVY_BEHIND),
		'- **Duplicate candidate:** file-set Jaccard >= %.2f, or similar title with Jaccard >= %.2f (series PRs exempt). The weaker PR (no mandatory label, lower effort) goes to D.' % (DUP_JACCARD, DUP_TITLE_JACCARD),
		'- Bucket order: A mandatory → D duplicate → B ready → C high effort → E heavy cost → F.',
		'', '## Summary', '', '| Bucket | Meaning | Count |', '|---|---|---|']
	for b, label in BUCKETS.items():
		out.append('| %s | %s | %d |' % (b, label, sum(1 for p in prs if p['bucket'] == b)))

	for b, label in BUCKETS.items():
		group = sorted((p for p in prs if p['bucket'] == b), key=lambda x: (x['cost_score'], -x['effort_score']))
		if not group:
			continue
		out += ['', '## %s. %s (%d)' % (b, label, len(group)), '',
			'| PR | Title | Eff | Cost | Next action | Suggested labels | Why |', '|---|---|---|---|---|---|---|']
		for p in group:
			out.append('| %s | %s | %d | %d | %s | %s | %s |' % (
				link(p), md_escape(short(p['title'], 55)), p['effort_score'], p['cost_score'],
				p['action'], ', '.join('`%s`' % l for l in p['suggested_labels']) or '-', md_escape(p['rationale'])))

	clusters = components([p['number'] for p in prs], edges)
	out += ['', '## Overlap & duplicate clusters', '',
		'Edges use file-set Jaccard after ignoring %d hot/shared files (touched by >%d%% of PRs, plus changelog/bootstrap). '
		'**dup** marks duplicate candidates; others only overlap and will need rebasing after each other lands.' % (len(hot), int(HOT_FILE_SHARE * 100)), '']
	if not clusters:
		out.append('_No overlap clusters found._')
	for i, c in enumerate(sorted(clusters, key=len, reverse=True), 1):
		winner = max((by_num[n] for n in c), key=lambda p: (p['mandatory'], p['effort_score'], -p['cost_score']))
		out.append('**Cluster %d** - %s; strongest: #%d' % (i, ', '.join('#%d (%s)' % (n, by_num[n]['bucket']) for n in c), winner['number']))
		for e in edges:
			if e['a'] in c:
				out.append('- #%d ↔ #%d: jaccard %.2f, %d shared files, title sim %.2f%s%s' % (
					e['a'], e['b'], e['jaccard'], e['shared'], e['title_sim'],
					' **dup**' if e['duplicate'] else '', ' (series)' if e['series'] else ''))
		out.append('')

	out += ['## Merge waves (A + B + C)', '',
		'Within each wave, land the cheapest first; after each merge, re-run `collect.py` - conflicts shift.', '']
	names = {1: 'Wave 1 - ready to merge', 2: 'Wave 2 - minor rebase / threads / CI',
		3: 'Wave 3 - heavy rebase or port'}
	for w, title in names.items():
		group = sorted((p for p in prs if p['wave'] == w), key=lambda x: (x['cost_score'], len(x['overlaps'])))
		out += ['### %s (%d)' % (title, len(group)), '']
		for i, p in enumerate(group, 1):
			ov = (' - overlaps %s' % ', '.join('#%d' % n for n in p['overlaps'])) if p['overlaps'] else ''
			out.append('%d. %s %s [%s] - %s%s' % (i, link(p), md_escape(short(p['title'], 60)), p['bucket'], p['action'], ov))
		out.append('')

	closes = [p for p in prs if p['bucket'] in ('D', 'E')]
	out += ['## Proposed actions requiring sign-off', '',
		'- Close (%d): %s' % (len(closes), ', '.join('#%d' % p['number'] for p in sorted(closes, key=lambda x: x['number'])) or '-'),
		'- Label `chances-low` (%d): %s' % (sum(1 for p in prs if 'chances-low' in p['suggested_labels']),
			', '.join('#%d' % p['number'] for p in prs if 'chances-low' in p['suggested_labels']) or '-'),
		'- Label `ready-to-merge` (%d): %s' % (sum(1 for p in prs if 'ready-to-merge' in p['suggested_labels']),
			', '.join('#%d' % p['number'] for p in prs if 'ready-to-merge' in p['suggested_labels']) or '-'),
		'']
	return '\n'.join(out)


def main():
	ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
	ap.add_argument('--in', dest='inp', default='.artifacts/pr-triage')
	args = ap.parse_args()
	with open(os.path.join(args.inp, 'prs.json'), encoding='utf-8') as fh:
		data = json.load(fh)
	prs = data['prs']
	edges, hot = analyze_overlap(prs)
	classify(prs, edges)

	keep = ['number', 'title', 'bucket', 'wave', 'action', 'effort_score', 'cost_score', 'mandatory',
		'high_effort', 'ready', 'heavy_cost', 'duplicate_of', 'overlaps', 'suggested_labels', 'rationale', 'url']
	rows = [{k: p.get(k) for k in keep} for p in prs]
	with open(os.path.join(args.inp, 'triage.json'), 'w', encoding='utf-8') as fh:
		json.dump({'edges': edges, 'prs': rows}, fh, indent=1)
	with open(os.path.join(args.inp, 'triage.csv'), 'w', newline='', encoding='utf-8') as fh:
		w = csv.DictWriter(fh, fieldnames=keep)
		w.writeheader()
		for r in rows:
			w.writerow({k: ';'.join(map(str, v)) if isinstance(v, list) else v for k, v in r.items()})
	with open(os.path.join(args.inp, 'pr_metrics_report.md'), 'w', encoding='utf-8') as fh:
		fh.write(metrics_report(data, prs))
	with open(os.path.join(args.inp, 'pr_triage_report.md'), 'w', encoding='utf-8') as fh:
		fh.write(triage_report(data, prs, edges, hot))
	counts = Counter(p['bucket'] for p in prs)
	print('Buckets: ' + ', '.join('%s=%d' % (b, counts.get(b, 0)) for b in BUCKETS))


if __name__ == '__main__':
	main()
