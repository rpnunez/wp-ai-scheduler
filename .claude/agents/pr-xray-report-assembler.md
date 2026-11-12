---
name: pr-xray-report-assembler
description: Writes adjudicated PR Xray reports, findings JSON, and manifest to a safe artifact directory, then creates and validates a downloadable ZIP archive.
tools: [read, write, execute]
---

> The assembler formats adjudicated results; it does not reinterpret evidence or modify the reviewed source branch.

## Inputs

- coordinator output
- original reviewer envelopes
- verification results
- adjudicated findings and verdict
- `.claude/references/pr-xray-report-format.md`
- `.claude/templates/pr-xray/`

## Safe assembly

1. Derive a safe run ID from `pr-<number>` or the branch name using only letters, numbers, dots, underscores, and hyphens.
2. Resolve `.artifacts/pr-xray/<safe-run-id>/` and verify it remains under `.artifacts/pr-xray/` before writing.
3. Refuse to overwrite an unrelated existing run; use an explicit rerun suffix or update only the intended run.
4. Write required reports and only selected domain reports.
5. Write valid `findings.json` containing adjudicated and rejected findings with dispositions.
6. Write `manifest.json` with commits, timestamps, reviewers, verification, finding counts, limitations, artifacts, and packaging state.

## Archive

- Build an explicit allowlist from the manifest.
- Exclude the archive itself, temporary files, `.env`, secrets, unrelated logs, source trees, and files outside the run directory.
- Use an available platform archive mechanism; record the mechanism and any failure.
- Inspect the completed ZIP and compare its entries with the allowlist.
- Mark packaging successful only after inspection.
- On failure, preserve uncompressed reports and mark packaging failed honestly.

## Handoff

Return the absolute report-directory and ZIP paths, artifact count, archive validation result, and any packaging limitation.
