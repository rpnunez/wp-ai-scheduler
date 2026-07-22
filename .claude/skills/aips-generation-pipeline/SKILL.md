---
name: aips-generation-pipeline
description: Use when changing the AI content-generation flow in the AI Post Scheduler plugin (ai-post-scheduler/) — anything touching AIPS_Generator, AIPS_Template_Context, AIPS_Topic_Context, AIPS_Generation_Context, prompt assembly, or generation logging/history.
---

# Generation pipeline workflow

The core content-generation flow is built on context objects, not ad hoc parameter
lists, and every path through it must stay observable.

## Required workflow

1. **Use the context objects, not raw parameters.** `AIPS_Template_Context`,
   `AIPS_Topic_Context`, and `AIPS_Generation_Context` (built via
   `AIPS_Generation_Context_Factory`) carry generation state. Adding a new input to
   generation means extending a context object and its factory, not threading a new
   parameter through every call site.
2. **Drive generation through `AIPS_Generator`.** Don't call the underlying AI Engine
   integration directly from a controller or service — go through the generator so
   logging/retry/observability stay consistent.
3. **Assemble prompts through shared builders.** Never concatenate prompt strings in
   a caller; use the existing shared prompt builder(s) so template/voice/structure
   rules apply uniformly.
4. **Preserve observability.** Any new or changed generation path must keep emitting
   through `AIPS_Generation_Logger`, `AIPS_History_Service`, and
   `AIPS_Correlation_Id` — these are what let a failed/partial generation be traced
   and reconciled later (see `AIPS_Partial_Generation_State_Reconciler` /
   `AIPS_Partial_Generation_Notifications` in `docs/AI_AGENT_REFERENCE.md`).
5. **Check resilience wiring.** If the change can fail transiently (external API
   calls), confirm it goes through `AIPS_Resilience_Service::retry_with_backoff()`
   rather than a bespoke retry loop.

## Guardrails

- A generation-pipeline change almost always warrants the `generation-pipeline` PR
  label — see `aips-pr-prep`.
- Don't bypass the context/factory pattern even for a "quick" one-off generation
  path — inconsistent context objects are what break partial-generation recovery.

## Reference files

- `ai-post-scheduler/includes/` — `class-aips-generation-context*.php`,
  `class-aips-template-context.php`, `class-aips-topic-context.php`,
  `class-aips-generator.php`, `class-aips-generation-logger.php`,
  `class-aips-history-service.php`, `class-aips-correlation-id.php`,
  `class-aips-resilience-service.php`
