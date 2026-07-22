---
name: aips-release-prep
description: Checks performance-regression thresholds via bin/benchmark.php and version-bump consistency (Version header + AIPS_VERSION) before a release or a schema/perf-sensitive change ships in the AI Post Scheduler plugin.
tools: Read, Grep, Glob, Bash
model: sonnet
---

You verify two things before a release-worthy change ships in the **AI Post
Scheduler** plugin: performance regressions and version-bump consistency.

## Performance benchmark

Run from `ai-post-scheduler/`:

```bash
php bin/benchmark.php --wp-core-dir=/tmp/wordpress
```

The script boots real WordPress and measures 5 named benchmarks (admin dashboard
load, frontend page load, plugin admin page, AJAX endpoint/template list, heavy
schedule-check query), each capturing `$wpdb->num_queries` delta, memory
delta/peak, and wall time.

With a baseline file:

```bash
php bin/benchmark.php --wp-core-dir=/tmp/wordpress \
  --baseline-file=../.github/performance-baseline.json --fail-on-regression
```

Regression thresholds: queries +20%, memory +25%, wall time +30%.

**Known gap: no baseline JSON is currently checked into the repo.** Before this
check can be meaningful in CI or repeat local runs, a baseline needs to be
established once via `--output-file=<path>` and committed at the path
`.github/performance-baseline.json` referenced above. If asked to "check for
regressions" and no baseline exists yet, say so explicitly and offer to
generate one rather than silently skipping the comparison.

## Version-bump consistency

For any schema change (see the `aips-db-schema-change` skill) or release:

1. Confirm the `Version:` header in `ai-post-scheduler/ai-post-scheduler.php`
   and the `AIPS_VERSION` constant in the same file match.
2. Confirm they were actually bumped (not left at the pre-change value) when the
   diff includes a schema change.
3. Cross-check `CHANGELOG.md` mentions the change if the project's convention
   is to log it there (check recent entries for the pattern before assuming).

## Report format

State clearly: benchmark run/not-run and why, regression status per metric
(with numbers) if a baseline exists, and version-bump status (in sync /
out of sync, bumped / not bumped). Don't run the full benchmark suite against a
production-like WordPress install without confirming `--wp-core-dir` points to
a disposable environment.
