# PR Xray Report Format

## Output directory

Write to `.artifacts/pr-xray/<safe-run-id>/`, where the run ID is a sanitized `pr-<number>` or branch name. Never use unresolved variables, traversal segments, or absolute user input as a directory name.

## Required files

- `00-executive-summary.md`
- `01-pr-intent-and-acceptance-criteria.md`
- `02-change-scope-and-risk.md`
- `03-overlap-analysis.md`
- `20-cross-reviewer-adjudication.md`
- `21-verification-results.md`
- `findings.json`
- `manifest.json`

Create numbered domain reports only for selected reviewers. The manifest records all reviewers, including skipped, failed, partial, and unavailable reviewers.

## Domain report names

- `10-ajax-review.md`
- `11-database-review.md`
- `12-generation-pipeline-review.md`
- `13-background-jobs-review.md`
- `14-localization-review.md`
- `15-bootstrap-dependencies-review.md`
- `16-qa-and-test-review.md`

## Archive

Create `<safe-run-id>-xray.zip` after all other files are finalized. Build it from an explicit allowlist in `manifest.json`; never archive the directory recursively without filtering. Exclude the ZIP itself, `.env`, secrets, unrelated logs, source files, and temporary files. Inspect the resulting archive and compare its entries with the manifest before reporting success.

If ZIP creation fails, preserve the uncompressed reports, mark packaging failed in the manifest, and report the failure honestly.

## Verification language

Reports use these exact distinctions:

- **Inspected:** read or statically traced.
- **Executed:** command or test actually ran, with its result recorded.
- **Inferred:** supported by evidence but not reproduced.
- **Unavailable:** required environment or access was missing.
- **Failed:** attempted verification did not complete successfully.
