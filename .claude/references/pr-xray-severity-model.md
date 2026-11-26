# PR Xray Severity, Confidence, and Status Model

## Severity

| Value | Meaning |
|---|---|
| `critical` | Likely security compromise, destructive data loss, or plugin-wide failure with no reasonable mitigation. |
| `high` | Major feature failure, authorization flaw, unsafe migration, or serious regression on a supported path. |
| `medium` | Meaningful incorrect behavior with limited scope or a realistic reliability risk. |
| `low` | Minor defect, narrow compatibility issue, or maintainability problem with concrete impact. |
| `informational` | Verified observation that does not require a change. |

Severity reflects impact and likelihood, not reviewer confidence or fix difficulty.

## Confidence

| Value | Meaning |
|---|---|
| `high` | Demonstrated by a complete code path, executed test, or reproducible behavior. |
| `medium` | Strongly supported, but one runtime or environmental fact remains unverified. |
| `low` | Plausible concern requiring additional evidence. |

## Status

| Value | Meaning |
|---|---|
| `confirmed` | Evidence demonstrates the failure or violation. |
| `probable` | Evidence strongly indicates the issue, but runtime confirmation is unavailable. |
| `needs-runtime-verification` | Static review cannot decide the behavior. |
| `test-gap` | Material behavior lacks meaningful regression coverage. |
| `informational` | Non-actionable verified context. |
| `rejected-during-adjudication` | Adjudication found the evidence or reasoning insufficient. |

## Verdicts

- **Ready:** no accepted blocking findings and required verification completed.
- **Ready with non-blocking follow-ups:** only accepted low/informational work remains.
- **Needs changes:** accepted actionable findings must be fixed before merge.
- **Blocked pending verification:** missing evidence prevents a responsible readiness decision.
- **Not ready:** critical risk, fundamental requirement failure, or change-set integrity problem prevents merge.
