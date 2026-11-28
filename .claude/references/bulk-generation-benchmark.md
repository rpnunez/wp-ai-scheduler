# Offline bulk-generation benchmark

Use detached checkouts at the requested SHAs. From a trusted checkout run:

```text
php .claude/scripts/performance-ai-cost-check/bulk.php <absolute-checkout-path> <success|retry|incomplete>
```

Use the same PHP binary and fixture for both revisions. Run each scenario at least five times per revision in fresh processes, alternating revisions. Record exact SHAs, PHP version, scenario and raw samples. Use trusted target revisions in an isolated environment with outbound networking disabled. The fixture loads only the bulk service and substitutes WordPress/history boundaries and generation callbacks. It exercises synchronous bulk runs. Retry is injected inside the mock callback. Callback and simulated attempt counts do not measure production dispatch, cron, database queries, provider tokens or charges.

Expected counts per run for success: 5 completed, 5 callbacks, 5 simulated attempts. Retry: 5 completed, 5 callbacks, 6 simulated attempts. Incomplete: 4 completed, 1 failed, 5 callbacks. Compare median and observed range; suppress performance conclusions for incomplete or unequal work.
