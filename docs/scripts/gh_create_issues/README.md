## Usage

**Basic — create all issues in the CSV**
./scripts/gh_create_issues.sh --issuesfile issues.csv

**Target a specific repo, apply a default milestone and project**
./scripts/gh_create_issues.sh \
  --issuesfile issues.csv \
  --repo rpnunez/wp-ai-scheduler \
  --milestone "Sprint 1" \
  --project 3

**Dry run first — see every gh command that would be executed without running it**
./scripts/gh_create_issues.sh --issuesfile issues.csv --dry-run

## Key behaviours

- Per-row milestone and project columns override the global flags — useful for mixed-sprint CSV files
- Milestones are auto-created via the API if they don't exist yet
- Project assignment uses gh project item-add (requires gh extension install cli/gh-projects)
- --dry-run prints every command that would run, with [dry-run] prefix, without touching GitHub
- Final summary shows created/skipped/failed counts; exits with code 1 if any row failed