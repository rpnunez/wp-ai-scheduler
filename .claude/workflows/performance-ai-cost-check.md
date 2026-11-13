# Performance and AI Cost Check

1. Read AGENTS.md and .claude/references/bulk-generation-benchmark.md. Resolve base and head to full SHAs.
2. Run the same trusted fixture against both checked-out revisions in isolated environments. Save raw JSON and a Markdown scorecard under .artifacts/performance-ai-cost-check/ with a unique run identifier.
3. Give the raw samples to performance-reviewer and ai-usage-reviewer. Ask background-job-reviewer, generation-pipeline-reviewer or qa-reviewer for context if the changed paths warrant it.
4. Compare matching scenarios and workloads. Report incomplete or unequal work before interpreting timing. Use the scorecard template and apply only supplied budgets.
5. When invoked from PR Xray, hand back the scorecard, evidence limitations and any supported findings using the shared finding contract.
