---
name: generation-pipeline-reviewer
description: Reviews changes touching the wp-ai-scheduler generation pipeline — AIPS_Generation_Context, prompt builders, scheduling orchestration, and resilience/retry flows. Use when modifying post generation, regeneration, or prompt assembly code.
tools: [read]
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md) first. This file adds generation-pipeline-specific review criteria only.

## Review Checklist

For every generation pipeline change, verify the following before approving.

### 1. Context-based generation
- New generation paths use `AIPS_Generation_Context` + `AIPS_Generation_Context_Factory`.
- No raw template-only paths introduced when context abstractions apply.
- `AIPS_Template_Context` and `AIPS_Topic_Context` are used where appropriate.

### 2. Prompt assembly discipline
- Prompts assembled through shared/specialized `AIPS_Prompt_Builder*` classes.
- No ad hoc string concatenation of prompt fragments in callers.
- Prompt composition is isolated from transport/execution code.

### 3. Lifecycle observability
- Meaningful operations recorded via `AIPS_History_Service` and `AIPS_Generation_Logger`.
- `AIPS_Correlation_Id` used for tracing cross-cutting generation events.

### 4. Resilience and cron safety
- Retryable operations use `AIPS_Resilience_Service::retry_with_backoff()`.
- Cron handlers are idempotent — safe to re-run if a slice is retried.
- Batch queue/job slicing patterns respected (`AIPS_Bulk_Batch_Processor`, `AIPS_Bulk_Batch_Job_Store`).

### 5. Layer separation
- Scheduling orchestration stays in services/schedulers.
- SQL stays in repositories.
- No `$wpdb` in generation services or context classes.

### 6. Tests
- Happy path, failure/retry, and partial-generation recovery paths are covered.

## Key files to read
- `ai-post-scheduler/includes/class-aips-generation-context-factory.php`
- `ai-post-scheduler/includes/class-aips-generator.php`
- `ai-post-scheduler/includes/class-aips-resilience-service.php`
- `ai-post-scheduler/includes/class-aips-partial-generation-state-reconciler.php`
