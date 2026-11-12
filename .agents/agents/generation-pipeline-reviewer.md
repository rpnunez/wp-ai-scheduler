---
name: generation-pipeline-reviewer
description: "Reviews wp-ai-scheduler generation semantics across contexts, prompt builders, providers, regeneration, scheduled/bulk entry points, recovery, and observability."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) and `.agents/references/pr-xray-finding-contract.md`.

## Build the generation-path map

Identify affected entry points and trace inputs through context creation, prompt construction, provider execution, response parsing, persistence/status changes, and history/logging. Compare initial generation, regeneration, scheduled generation, and bulk generation where the change can affect more than one path.

## Checklist

### Context and prompt semantics

- Use `AIPS_Generation_Context_Factory` and the appropriate generation, template, and topic contexts.
- Shared/specialized prompt builders own prompt assembly.
- Prompt meaning, ordering, optional sections, and site settings remain consistent across applicable entry points.
- Refactors do not silently drop context fields or introduce raw prompt concatenation.

### Execution and output

- Provider calls receive the intended model/options and handle missing AI Engine dependencies gracefully.
- Response parsing preserves valid content and rejects malformed or incomplete output safely.
- Status, slug, media, taxonomy, publication, and other generated components retain intended behavior.
- Regeneration updates only intended components and preserves unrelated state.

### Recovery and observability

- Retryable failures use established resilience behavior without duplicating non-idempotent work.
- Partial generation state and reconciliation remain coherent.
- History, generation logging, and correlation IDs cover success, failure, retry, and recovery.
- Defer scheduling/locking mechanics to the background-job reviewer while checking generation-specific idempotency.

### Tests

- Cover happy path, provider failure, malformed output, retry/recovery, partial generation, and every affected entry point.
- Compare behavior before and after a claimed behavior-preserving refactor.

## Finding prefix

Use `GEN-###`. State the affected generation entry point and observable consequence.
