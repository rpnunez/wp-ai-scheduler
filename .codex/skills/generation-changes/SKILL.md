# Generation Changes Skill

Use this skill for post generation/regeneration workflows, prompts, and scheduling execution logic.

## Scope
- Generation context architecture, prompt builders, generation orchestration, and recovery flows.

## Required workflow
1. **Use context-based generation**
   - Prefer `AIPS_Generation_Context` + factory (`AIPS_Generation_Context_Factory`).
   - Avoid introducing new raw-template-only paths when context abstractions apply.
2. **Prompt assembly discipline**
   - Reuse shared/specialized builders (`AIPS_Prompt_Builder*`).
   - Keep prompt composition isolated from transport/execution code.
3. **Lifecycle observability**
   - Record meaningful operations via `AIPS_History_Service` and generation logging.
   - Use structured logs and correlation IDs where relevant.
4. **Resilience and cron safety**
   - Use `AIPS_Resilience_Service::retry_with_backoff()` for retryable operations.
   - Ensure cron handlers remain idempotent for slice/batch retries.
5. **Validation**
   - Cover happy path + failure/retry + partial-generation recovery where affected.

## Guardrails
- Keep scheduling orchestration in services/schedulers.
- Keep SQL in repositories.
- Respect existing batch queue/job slicing patterns.

## Useful files
- `ai-post-scheduler/includes/class-aips-generation-context-factory.php`
- `ai-post-scheduler/includes/class-aips-generator.php`
- `ai-post-scheduler/includes/class-aips-author-post-generator.php`
- `ai-post-scheduler/includes/class-aips-resilience-service.php`
- `ai-post-scheduler/includes/class-aips-partial-generation-state-reconciler.php`
