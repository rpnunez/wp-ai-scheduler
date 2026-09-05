# Generation Pipeline Sweep Workflow

Fan out agents across the AI generation pipeline to verify context object usage, prompt builder isolation, observability logging, and retry backoff resilience.

## Purpose

Audit all content generation classes, prompt builders, context factories, and retry services against the architectural rules in `AGENTS.md` and `CLAUDE.md`:
1. Generation requests use context objects (`AIPS_Generation_Context`, `AIPS_Template_Context`, `AIPS_Topic_Context`) via `AIPS_Generation_Context_Factory`.
2. Prompts are constructed using shared `AIPS_Prompt_Builder*` classes—never string concatenation.
3. Operations log events using `AIPS_History_Service`, `AIPS_Generation_Logger`, and `AIPS_Correlation_Id`.
4. Retryable calls utilize `AIPS_Resilience_Service::retry_with_backoff()`.

## Phases

### Phase 1 — Pipeline Component Discovery
Glob and categorize generation components:
- Context objects (`class-aips-*-context*.php`)
- Prompt builders (`class-aips-prompt-builder*.php`)
- Generation engine and services (`class-aips-generator.php`, `class-aips-generation-*.php`)
- Resilience and logging handlers (`class-aips-resilience-service.php`, `class-aips-history-service.php`)

### Phase 2 — Parallel Component Audit
For each pipeline component:
1. Check context object utilization and factory instantiation patterns.
2. Search for ad hoc prompt string concatenation in callers and flag violations.
3. Verify correlation ID passing across async or batch generation boundaries.
4. Verify resilience backoff wrappers around external API endpoints.

### Phase 3 — Consolidated Report
Produce a Markdown report:
- Pipeline Architecture Compliance Table.
- Prompt Assembly Violation List (file, line number, issue).
- Observability and Tracing Coverage.
- Recommended Action Items.

## Useful references
- `AGENTS.md` — canonical generation pipeline rules
- `.claude/agents/generation-pipeline-reviewer.md` — review checklist
- `.claude/skills/generation-changes/SKILL.md` — implementation guide
