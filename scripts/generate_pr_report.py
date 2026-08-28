#!/usr/bin/env python3
"""
On-Demand GitHub Pull Request Analyzer & Interactive HTML Report Generator
Repository: wp-ai-scheduler (or any configured gh repository)

Usage:
    python scripts/generate_pr_report.py --count 40 --orderby updated-desc --output pr_report.html
"""

import argparse
from datetime import datetime, timezone
import json
import os
import re
import subprocess
import sys

def parse_arguments():
    parser = argparse.ArgumentParser(
        description="Fetch, analyze, and generate an interactive HTML PR report."
    )
    parser.add_argument(
        "-n", "--count", type=int, default=40,
        help="Number of PRs to analyze (default: 40)"
    )
    parser.add_argument(
        "-o", "--orderby", type=str, default="updated-desc",
        choices=["updated-desc", "updated-asc", "created-desc", "created-asc", "number-desc", "number-asc"],
        help="Order PRs by field and direction (default: updated-desc)"
    )
    parser.add_argument(
        "-f", "--output", type=str, default="pr_report.html",
        help="Output HTML file path (default: pr_report.html)"
    )
    parser.add_argument(
        "-s", "--state", type=str, default="open",
        choices=["open", "closed", "merged", "all"],
        help="PR state to filter by (default: open)"
    )
    return parser.parse_args()

def fetch_prs(count, orderby, state):
    print(f"Fetching {count} PRs (state: {state}, orderby: {orderby})...")
    
    # Map orderby flag to gh search query parameters
    sort_mapping = {
        "updated-desc": "sort:updated-desc",
        "updated-asc": "sort:updated-asc",
        "created-desc": "sort:created-desc",
        "created-asc": "sort:created-asc",
        "number-desc": "sort:created-desc", # best proxy for PR ID
        "number-asc": "sort:created-asc"
    }

    sort_query = sort_mapping.get(orderby, "sort:updated-desc")
    search_query = f"is:{state} {sort_query}" if state != "all" else sort_query

    cmd = [
        "gh", "pr", "list",
        "--limit", str(count),
        "--search", search_query,
        "--json", "number,title,isDraft,updatedAt,createdAt,url,labels,additions,deletions,changedFiles,mergeable,body,author,headRefName,baseRefName"
    ]

    try:
        result = subprocess.run(cmd, capture_output=True, text=True, check=True, encoding="utf-8")
        prs = json.loads(result.stdout)
    except subprocess.CalledProcessError as e:
        print(f"Error executing gh CLI: {e.stderr}", file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(f"Failed to fetch PRs: {e}", file=sys.stderr)
        sys.exit(1)

    # Sort python list if specific sorting needed
    if orderby == "number-desc":
        prs.sort(key=lambda x: x.get("number", 0), reverse=True)
    elif orderby == "number-asc":
        prs.sort(key=lambda x: x.get("number", 0))

    return prs

def format_relative_time(iso_str):
    if not iso_str:
        return ""
    try:
        dt = datetime.fromisoformat(iso_str.replace("Z", "+00:00"))
        now = datetime.now(timezone.utc)
        diff = now - dt
        seconds = int(diff.total_seconds())

        if seconds < 0:
            return "Just now"
        
        minutes = seconds // 60
        hours = minutes // 60
        days = hours // 24

        if seconds < 60:
            return "Just now"
        elif minutes < 60:
            return f"{minutes} minute{'s' if minutes != 1 else ''} ago"
        elif hours < 24:
            rem_min = minutes % 60
            if rem_min > 0:
                return f"{hours} hour{'s' if hours != 1 else ''} and {rem_min} minute{'s' if rem_min != 1 else ''} ago"
            else:
                return f"{hours} hour{'s' if hours != 1 else ''} ago"
        elif days == 1:
            return "Yesterday"
        elif days < 7:
            return f"{days} days ago"
        else:
            return dt.strftime("%b %d, %Y")
    except Exception:
        return iso_str[:10]

def format_created_date(iso_str):
    if not iso_str:
        return ""
    try:
        dt = datetime.fromisoformat(iso_str.replace("Z", "+00:00"))
        return dt.strftime("%b %d, %Y")
    except Exception:
        return iso_str[:10]

def clean_text(text):
    if not text:
        return ""
    text = text.replace('\r\n', ' ').replace('\n', ' ')
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def extract_summary(body, title):
    if not body or len(body.strip()) < 10:
        return title
    
    lines = body.splitlines()
    bullets = []
    in_summary = False
    
    for line in lines:
        l = line.strip()
        if re.search(r'#(?:#)?\s*(?:Summary|Motivation|Overview|Description|What changed)', l, re.IGNORECASE):
            in_summary = True
            continue
        if in_summary and l.startswith('#'):
            in_summary = False
            
        if l.startswith('- ') or l.startswith('* ') or l.startswith('1. '):
            clean_b = re.sub(r'^[-*1-9.]+\s*', '', l)
            clean_b = re.sub(r'\*\*([^*]+)\*\*', r'\1', clean_b)
            if len(clean_b) > 10 and not clean_b.startswith("Ran ") and not clean_b.startswith("Verified"):
                bullets.append(clean_b)
                if len(" ".join(bullets)) > 160:
                    break
        elif in_summary and len(l) > 15 and not l.startswith('<') and not l.startswith('---'):
            bullets.append(l)
            if len(" ".join(bullets)) > 160:
                break
                
    res = " ".join(bullets).strip()
    if not res or len(res) < 15:
        paragraphs = [p.strip() for p in body.split('\n\n') if p.strip() and not p.strip().startswith('#') and not p.strip().startswith('---')]
        if paragraphs:
            res = paragraphs[0]
            
    res = clean_text(res)
    if not res or len(res) < 10:
        res = title
    if len(res) > 200:
        res = res[:197] + "..."
    return res

def classify_prs(prs):
    processed = []
    
    for pr in prs:
        num = pr.get('number')
        title = clean_text(pr.get('title'))
        is_draft = pr.get('isDraft', False)
        status = "Draft" if is_draft else "Open"
        url = pr.get('url', '')
        additions = pr.get('additions', 0)
        deletions = pr.get('deletions', 0)
        changed_files = pr.get('changedFiles', 0)
        mergeable = pr.get('mergeable', 'UNKNOWN')
        labels = [l.get('name', '') for l in pr.get('labels', [])]
        body = pr.get('body', '') or ''
        author = pr.get('author', {}).get('login', 'unknown')
        updated_at = pr.get('updatedAt', '')
        created_at = pr.get('createdAt', '')
        
        summary = extract_summary(body, title)
        updated_rel = format_relative_time(updated_at)
        created_fmt = format_created_date(created_at)

        title_lower = title.lower()
        labels_lower = [l.lower() for l in labels]
        
        # 1. Type
        if is_draft and ("recipe" in title_lower or "proposal" in title_lower or "catalog" in title_lower):
            pr_type = "Draft Feature"
        elif "new-feature" in labels_lower or title_lower.startswith("feat:") or "add " in title_lower or "implement" in title_lower:
            pr_type = "New Feature"
        elif "refactor" in title_lower or "rewrite" in title_lower or "decouple" in title_lower or "clean up" in title_lower:
            pr_type = "Refactor"
        elif "qa" in title_lower or "test" in title_lower or "tests" in labels_lower:
            pr_type = "Testing & QA"
        elif "build" in title_lower or "infra" in title_lower or "tooling" in title_lower or "script" in title_lower or "skills" in title_lower:
            pr_type = "Infrastructure & Tooling"
        else:
            pr_type = "Enhancement"

        # 2. Subtype
        if "fix" in title_lower or "bug" in labels_lower or "timezone" in title_lower or "n+1" in title_lower:
            subtype = "Bug Fix"
        elif "palette" in title_lower or "accessibility" in title_lower or "a11y" in title_lower or "ui/ux" in labels_lower or "adminbar" in title_lower or "ux" in title_lower:
            subtype = "UI / UX & Accessibility"
        elif "cache" in title_lower or "query" in title_lower or "queries" in title_lower or "db" in title_lower or "performance" in labels_lower or "indexes" in title_lower:
            subtype = "Performance & Database"
        elif "ai provider" in title_lower or "capability" in title_lower or "ability" in title_lower or "prompt" in title_lower or "generator" in title_lower:
            subtype = "AI Engine & Architecture"
        elif "notification" in title_lower or "bridge" in title_lower or "integration" in title_lower or "crud" in title_lower or "quality gate" in title_lower or "affiliate" in title_lower or "enhancements" in title_lower:
            subtype = "Plugin Features & API"
        elif "telemetry" in title_lower or "diagnostics" in title_lower or "dev tools" in title_lower:
            subtype = "Observability & Dev Tools"
        elif "clean" in title_lower or "stale" in title_lower or "remove" in title_lower:
            subtype = "Repo Maintenance"
        else:
            subtype = "General Enhancement"

        # 3. Current Status
        if mergeable == "CONFLICTING":
            curr_status = "Has merge conflicts"
        elif is_draft:
            curr_status = "In Draft"
        elif "ready-to-merge" in labels_lower:
            curr_status = "Ready to merge"
        elif "testing-needed" in labels_lower:
            curr_status = "Needs testing"
        elif "review-needed" in labels_lower:
            curr_status = "Needs code review"
        elif mergeable == "MERGEABLE":
            curr_status = "Mergeable"
        else:
            curr_status = "Pending check"

        # 4. Risk of Merging
        total_changes = additions + deletions
        if mergeable == "CONFLICTING":
            risk = "High"
            risk_detail = f"High (Merge conflicts present)"
            risk_score = 3
        elif total_changes > 5000 or changed_files > 30:
            risk = "High"
            risk_detail = f"High ({changed_files} files, +{additions}/-{deletions})"
            risk_score = 3
        elif total_changes > 1000 or changed_files > 15:
            risk = "Medium"
            risk_detail = f"Medium ({changed_files} files, +{additions}/-{deletions})"
            risk_score = 2
        else:
            risk = "Low"
            risk_detail = f"Low ({changed_files} files, +{additions}/-{deletions})"
            risk_score = 1

        # 5. Recommended Action
        if mergeable == "CONFLICTING":
            rec_action = "Rebase & resolve conflicts"
        elif is_draft:
            rec_action = "Keep in Draft"
        elif "ready-to-merge" in labels_lower and mergeable == "MERGEABLE":
            rec_action = "Approve and merge"
        elif "testing-needed" in labels_lower:
            rec_action = "Execute integration/QA testing"
        elif "review-needed" in labels_lower:
            rec_action = "Conduct code review"
        elif total_changes < 300 and mergeable == "MERGEABLE":
            rec_action = "Quick review & merge"
        else:
            rec_action = "Review & run test suite"

        processed.append({
            "number": num,
            "id_str": f"#{num}",
            "url": url,
            "title": title,
            "status": status,
            "summary": summary,
            "type": pr_type,
            "subtype": subtype,
            "current_status": curr_status,
            "risk": risk,
            "risk_score": risk_score,
            "risk_detail": risk_detail,
            "rec_action": rec_action,
            "changed_files": changed_files,
            "additions": additions,
            "deletions": deletions,
            "mergeable": mergeable,
            "author": author,
            "updated_at": updated_at,
            "updated_rel": updated_rel,
            "created_at": created_at,
            "created_fmt": created_fmt,
            "labels": labels
        })
        
    return processed

def generate_html_report(prs_data, output_filepath, count_req, orderby_req):
    json_data = json.dumps(prs_data, ensure_ascii=False)

    # Unique filter options
    statuses = sorted(list(set(p['status'] for p in prs_data)))
    types = sorted(list(set(p['type'] for p in prs_data)))
    subtypes = sorted(list(set(p['subtype'] for p in prs_data)))
    curr_statuses = sorted(list(set(p['current_status'] for p in prs_data)))
    risks = ["Low", "Medium", "High"]
    rec_actions = sorted(list(set(p['rec_action'] for p in prs_data)))

    html_content = f"""<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Open PR Analysis Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {{
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --border-color: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-color: #6366f1;
            --accent-hover: #4f46e5;
            --badge-green-bg: #064e3b;
            --badge-green-txt: #6ee7b7;
            --badge-amber-bg: #78350f;
            --badge-amber-txt: #fcd34d;
            --badge-red-bg: #7f1d1d;
            --badge-red-txt: #fca5a5;
            --badge-blue-bg: #1e3a8a;
            --badge-blue-txt: #93c5fd;
            --badge-purple-bg: #4c1d95;
            --badge-purple-txt: #c084fc;
        }}

        * {{ box-sizing: border-box; margin: 0; padding: 0; }}
        body {{
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            padding: 2rem;
            line-height: 1.5;
        }}

        .container {{
            max-width: 1540px;
            margin: 0 auto;
        }}

        header {{
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }}

        .title-group h1 {{
            font-size: 1.875rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a5b4fc, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }}

        .title-group p {{
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }}

        .actions-group {{
            display: flex;
            gap: 1rem;
            align-items: center;
        }}

        .btn {{
            background-color: var(--accent-color);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        }}

        .btn:hover {{
            background-color: var(--accent-hover);
            transform: translateY(-1px);
        }}

        /* Metrics Bar */
        .metrics-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }}

        .metric-card {{
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 1.25rem;
            border-radius: 0.75rem;
        }}

        .metric-card .label {{
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            font-weight: 600;
        }}

        .metric-card .value {{
            font-size: 1.75rem;
            font-weight: 700;
            margin-top: 0.25rem;
            color: var(--text-primary);
        }}

        /* Filter Controls */
        .filter-panel {{
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }}

        .filter-panel h2 {{
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }}

        .filters-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }}

        .filter-group {{
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }}

        .filter-group label {{
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
        }}

        .filter-group select, .filter-group input {{
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            outline: none;
            transition: border-color 0.2s;
        }}

        .filter-group select:focus, .filter-group input:focus {{
            border-color: var(--accent-color);
        }}

        /* Table Design */
        .table-container {{
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            overflow-x: auto;
        }}

        table {{
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }}

        th {{
            background-color: rgba(15, 23, 42, 0.6);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            transition: color 0.2s;
        }}

        th:hover {{
            color: var(--text-primary);
            background-color: rgba(30, 41, 59, 0.8);
        }}

        th .sort-icon {{
            margin-left: 0.375rem;
            font-size: 0.75rem;
            opacity: 0.4;
        }}

        th.active-sort .sort-icon {{
            opacity: 1;
            color: var(--accent-color);
        }}

        td {{
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }}

        tr:hover {{
            background-color: rgba(51, 65, 85, 0.3);
        }}

        .pr-link {{
            color: #818cf8;
            font-weight: 600;
            text-decoration: none;
        }}

        .pr-link:hover {{
            text-decoration: underline;
        }}

        .pr-title {{
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }}

        .pr-summary {{
            color: var(--text-secondary);
            font-size: 0.8125rem;
            max-width: 380px;
        }}

        .pr-created {{
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.375rem;
            font-weight: 500;
        }}

        .updated-cell {{
            white-space: nowrap;
            font-size: 0.8125rem;
            color: #cbd5e1;
            font-weight: 500;
        }}

        /* Badges */
        .badge {{
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }}

        .badge-open {{ background-color: var(--badge-green-bg); color: var(--badge-green-txt); }}
        .badge-draft {{ background-color: var(--badge-amber-bg); color: var(--badge-amber-txt); }}
        .badge-conflict {{ background-color: var(--badge-red-bg); color: var(--badge-red-txt); }}
        .badge-ready {{ background-color: var(--badge-green-bg); color: var(--badge-green-txt); }}
        .badge-testing {{ background-color: var(--badge-blue-bg); color: var(--badge-blue-txt); }}
        .badge-review {{ background-color: var(--badge-purple-bg); color: var(--badge-purple-txt); }}

        .risk-low {{ color: #4ade80; font-weight: 600; }}
        .risk-medium {{ color: #facc15; font-weight: 600; }}
        .risk-high {{ color: #f87171; font-weight: 600; }}

        .empty-state {{
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
        }}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="title-group">
                <h1>GitHub Open Pull Requests Report</h1>
                <p>Analyzed {count_req} PRs | Ordered by: {orderby_req} | Generated on demand</p>
            </div>
            <div class="actions-group">
                <button class="btn" id="exportCsvBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Filtered to CSV
                </button>
            </div>
        </header>

        <!-- Dynamic Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="label">Visible PRs</div>
                <div class="value" id="metricTotal">0</div>
            </div>
            <div class="metric-card">
                <div class="label">Mergeable</div>
                <div class="value" id="metricMergeable" style="color: #4ade80;">0</div>
            </div>
            <div class="metric-card">
                <div class="label">Merge Conflicts</div>
                <div class="value" id="metricConflicts" style="color: #f87171;">0</div>
            </div>
            <div class="metric-card">
                <div class="label">High Risk</div>
                <div class="value" id="metricHighRisk" style="color: #facc15;">0</div>
            </div>
        </div>

        <!-- Filters Panel -->
        <div class="filter-panel">
            <h2>Filter & Search</h2>
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchFilter">Search Text</label>
                    <input type="text" id="searchFilter" placeholder="Search ID, title, summary...">
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="">All Statuses</option>
                        {"".join(f'<option value="{s}">{s}</option>' for s in statuses)}
                    </select>
                </div>
                <div class="filter-group">
                    <label for="typeFilter">Type</label>
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        {"".join(f'<option value="{t}">{t}</option>' for t in types)}
                    </select>
                </div>
                <div class="filter-group">
                    <label for="subtypeFilter">Subtype</label>
                    <select id="subtypeFilter">
                        <option value="">All Subtypes</option>
                        {"".join(f'<option value="{st}">{st}</option>' for st in subtypes)}
                    </select>
                </div>
                <div class="filter-group">
                    <label for="currStatusFilter">Current Status</label>
                    <select id="currStatusFilter">
                        <option value="">All Current Statuses</option>
                        {"".join(f'<option value="{cs}">{cs}</option>' for cs in curr_statuses)}
                    </select>
                </div>
                <div class="filter-group">
                    <label for="riskFilter">Risk Level</label>
                    <select id="riskFilter">
                        <option value="">All Risk Levels</option>
                        {"".join(f'<option value="{r}">{r}</option>' for r in risks)}
                    </select>
                </div>
                <div class="filter-group">
                    <label for="actionFilter">Recommended Action</label>
                    <select id="actionFilter">
                        <option value="">All Actions</option>
                        {"".join(f'<option value="{ra}">{ra}</option>' for ra in rec_actions)}
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th data-sort="number">PR ID <span class="sort-icon">▲</span></th>
                        <th data-sort="updated_at" class="active-sort">Last Updated <span class="sort-icon">▼</span></th>
                        <th data-sort="title">Title & Summary <span class="sort-icon">▲</span></th>
                        <th data-sort="status">Status <span class="sort-icon">▲</span></th>
                        <th data-sort="type">Type <span class="sort-icon">▲</span></th>
                        <th data-sort="subtype">Subtype <span class="sort-icon">▲</span></th>
                        <th data-sort="current_status">Current Status <span class="sort-icon">▲</span></th>
                        <th data-sort="risk_score">Risk <span class="sort-icon">▲</span></th>
                        <th data-sort="rec_action">Recommended Action <span class="sort-icon">▲</span></th>
                    </tr>
                </thead>
                <tbody id="prTableBody">
                    <!-- Javascript populates filtered rows -->
                </tbody>
            </table>
            <div id="emptyState" class="empty-state" style="display: none;">
                No pull requests match the selected filter criteria.
            </div>
        </div>
    </div>

    <script>
        const prsData = {json_data};

        // Sorting state
        let currentSortKey = 'updated_at';
        let currentSortDir = 'desc';

        // DOM elements
        const searchInput = document.getElementById('searchFilter');
        const statusSelect = document.getElementById('statusFilter');
        const typeSelect = document.getElementById('typeFilter');
        const subtypeSelect = document.getElementById('subtypeFilter');
        const currStatusSelect = document.getElementById('currStatusFilter');
        const riskSelect = document.getElementById('riskFilter');
        const actionSelect = document.getElementById('actionFilter');
        
        const tableBody = document.getElementById('prTableBody');
        const emptyState = document.getElementById('emptyState');
        const exportCsvBtn = document.getElementById('exportCsvBtn');

        const metricTotal = document.getElementById('metricTotal');
        const metricMergeable = document.getElementById('metricMergeable');
        const metricConflicts = document.getElementById('metricConflicts');
        const metricHighRisk = document.getElementById('metricHighRisk');

        let filteredPrs = [...prsData];

        function sortData(data) {{
            return data.sort((a, b) => {{
                let valA = a[currentSortKey];
                let valB = b[currentSortKey];

                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return currentSortDir === 'asc' ? -1 : 1;
                if (valA > valB) return currentSortDir === 'asc' ? 1 : -1;
                return 0;
            }});
        }}

        function updateSortHeaders() {{
            document.querySelectorAll('th[data-sort]').forEach(th => {{
                const key = th.getAttribute('data-sort');
                const icon = th.querySelector('.sort-icon');
                if (key === currentSortKey) {{
                    th.classList.add('active-sort');
                    icon.textContent = currentSortDir === 'asc' ? '▲' : '▼';
                }} else {{
                    th.classList.remove('active-sort');
                    icon.textContent = '▲';
                }}
            }});
        }}

        function renderTable() {{
            const search = searchInput.value.toLowerCase();
            const status = statusSelect.value;
            const type = typeSelect.value;
            const subtype = subtypeSelect.value;
            const currStatus = currStatusSelect.value;
            const risk = riskSelect.value;
            const action = actionSelect.value;

            filteredPrs = prsData.filter(pr => {{
                const matchesSearch = !search || 
                    pr.id_str.toLowerCase().includes(search) || 
                    pr.title.toLowerCase().includes(search) || 
                    pr.summary.toLowerCase().includes(search) || 
                    pr.author.toLowerCase().includes(search);

                const matchesStatus = !status || pr.status === status;
                const matchesType = !type || pr.type === type;
                const matchesSubtype = !subtype || pr.subtype === subtype;
                const matchesCurrStatus = !currStatus || pr.current_status === currStatus;
                const matchesRisk = !risk || pr.risk === risk;
                const matchesAction = !action || pr.rec_action === action;

                return matchesSearch && matchesStatus && matchesType && matchesSubtype && matchesCurrStatus && matchesRisk && matchesAction;
            }});

            filteredPrs = sortData(filteredPrs);

            // Update Metrics
            metricTotal.textContent = filteredPrs.length;
            metricMergeable.textContent = filteredPrs.filter(p => p.mergeable === 'MERGEABLE').length;
            metricConflicts.textContent = filteredPrs.filter(p => p.mergeable === 'CONFLICTING').length;
            metricHighRisk.textContent = filteredPrs.filter(p => p.risk === 'High').length;

            tableBody.innerHTML = '';
            
            if (filteredPrs.length === 0) {{
                emptyState.style.display = 'block';
                return;
            }} else {{
                emptyState.style.display = 'none';
            }}

            filteredPrs.forEach(pr => {{
                const tr = document.createElement('tr');

                const statusBadge = pr.status === 'Draft' 
                    ? '<span class="badge badge-draft">Draft</span>' 
                    : '<span class="badge badge-open">Open</span>';

                let currBadge = `<span class="badge badge-review">${{pr.current_status}}</span>`;
                if (pr.mergeable === 'CONFLICTING') {{
                    currBadge = `<span class="badge badge-conflict">⚠️ Conflicts</span>`;
                }} else if (pr.current_status === 'Ready to merge') {{
                    currBadge = `<span class="badge badge-ready">Ready to merge</span>`;
                }} else if (pr.current_status === 'Needs testing') {{
                    currBadge = `<span class="badge badge-testing">Needs testing</span>`;
                }}

                const riskClass = pr.risk === 'High' ? 'risk-high' : (pr.risk === 'Medium' ? 'risk-medium' : 'risk-low');

                tr.innerHTML = `
                    <td><a class="pr-link" href="${{pr.url}}" target="_blank">${{pr.id_str}}</a></td>
                    <td class="updated-cell">${{escapeHtml(pr.updated_rel)}}</td>
                    <td>
                        <div class="pr-title">${{escapeHtml(pr.title)}}</div>
                        <div class="pr-summary">${{escapeHtml(pr.summary)}}</div>
                        <div class="pr-created">Created: ${{escapeHtml(pr.created_fmt)}}</div>
                    </td>
                    <td>${{statusBadge}}</td>
                    <td>${{escapeHtml(pr.type)}}</td>
                    <td>${{escapeHtml(pr.subtype)}}</td>
                    <td>${{currBadge}}</td>
                    <td class="${{riskClass}}">${{escapeHtml(pr.risk_detail)}}</td>
                    <td>${{escapeHtml(pr.rec_action)}}</td>
                `;

                tableBody.appendChild(tr);
            }});
        }}

        function escapeHtml(text) {{
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }}

        function exportToCsv() {{
            if (!filteredPrs || filteredPrs.length === 0) {{
                alert('No PRs to export');
                return;
            }}

            const headers = ['PR ID', 'Last Updated', 'Created Date', 'Title', 'Status', 'Summary', 'Type', 'Subtype', 'Current Status', 'Risk', 'Recommended Action', 'URL'];
            
            const rows = filteredPrs.map(pr => [
                pr.id_str,
                `"${{pr.updated_rel}}"`,
                `"${{pr.created_fmt}}"`,
                `"${{pr.title.replace(/"/g, '""')}}"`,
                pr.status,
                `"${{pr.summary.replace(/"/g, '""')}}"`,
                `"${{pr.type}}"`,
                `"${{pr.subtype}}"`,
                `"${{pr.current_status}}"`,
                `"${{pr.risk_detail.replace(/"/g, '""')}}"`,
                `"${{pr.rec_action.replace(/"/g, '""')}}"`,
                pr.url
            ]);

            const csvContent = "data:text/csv;charset=utf-8," + [headers.join(','), ...rows.map(r => r.join(','))].join('\\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "filtered_prs_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }}

        // Event listeners
        [searchInput, statusSelect, typeSelect, subtypeSelect, currStatusSelect, riskSelect, actionSelect].forEach(el => {{
            el.addEventListener('input', renderTable);
            el.addEventListener('change', renderTable);
        }});

        // Sort click listeners on <th>
        document.querySelectorAll('th[data-sort]').forEach(th => {{
            th.addEventListener('click', () => {{
                const key = th.getAttribute('data-sort');
                if (currentSortKey === key) {{
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                }} else {{
                    currentSortKey = key;
                    currentSortDir = (key === 'updated_at' || key === 'number' || key === 'risk_score') ? 'desc' : 'asc';
                }}
                updateSortHeaders();
                renderTable();
            }});
        }});

        exportCsvBtn.addEventListener('click', exportToCsv);

        // Initial setup
        updateSortHeaders();
        renderTable();
    </script>
</body>
</html>
"""

    with open(output_filepath, "w", encoding="utf-8") as f:
        f.write(html_content)

    print(f"Report successfully generated at: {os.path.abspath(output_filepath)}")

def main():
    args = parse_arguments()
    raw_prs = fetch_prs(args.count, args.orderby, args.state)
    classified_prs = classify_prs(raw_prs)
    generate_html_report(classified_prs, args.output, args.count, args.orderby)

if __name__ == "__main__":
    main()
