# PR Xray Finding Contract

Every PR Xray domain reviewer must return a review envelope, even when it finds no defects.

## Review envelope

```yaml
reviewer: generation-pipeline
status: completed
scope:
  files_reviewed: []
  acceptance_criteria_reviewed: []
checks:
  inspected: []
  executed: []
  unavailable: []
assumptions: []
coverage_gaps: []
findings: []
```

`status` is `completed`, `partial`, `failed`, or `not_applicable`. A clean review uses an empty `findings` list and still records its scope and checks.

## Finding fields

```yaml
id: GEN-001
reviewer: generation-pipeline
title: Regeneration bypasses the shared prompt builder
severity: high
confidence: high
status: confirmed
category: behavior-regression
file: ai-post-scheduler/includes/example.php
line: 142
evidence: Direct prompt concatenation occurs on the regeneration path.
impact: Regenerated posts receive different instructions from initial generation.
recommendation: Route regeneration through the shared prompt builder.
verification:
  method: static-analysis
  result: confirmed
acceptance_criteria:
  - AC-3
related_findings: []
```

## Requirements

- IDs use the reviewer's documented prefix and a three-digit sequence.
- `severity`, `confidence`, and `status` use the shared severity model.
- `file` is repository-relative; `line` is one-based. Use `null` only for cross-cutting findings.
- Evidence states what was directly observed. Do not substitute a general rule for evidence.
- Impact describes a concrete failure scenario or user/developer consequence.
- Recommendation describes the outcome required, without prescribing unrelated refactors.
- `verification.method` names the evidence source, such as `static-analysis`, `test-execution`, `browser-reproduction`, or `documentation-comparison`.
- A finding cannot be `confirmed` when its verification result is unavailable or inferred.
- Test gaps are findings only when they leave material behavior unprotected; otherwise record them as coverage gaps.

## Adjudication disposition

The adjudicator adds one of:

- `accepted`
- `merged-into:<finding-id>`
- `severity-adjusted`
- `requires-verification`
- `rejected`

Rejected findings retain their original text and receive a concise rejection reason.
