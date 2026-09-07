# Feature Slicing Skill

Use this skill to break a feature into implementation slices that are independently testable and mergeable.

## Scope
- Planning and decomposition for plugin features across DB, backend, AJAX, UI, and tests.

## Required workflow
1. **Define outcomes first**
   - Write user-visible outcomes and technical acceptance criteria.
2. **Slice by dependency chain**
   - Suggested order:
     1) schema/repository,
     2) services/business logic,
     3) AJAX/controller wiring,
     4) admin UI,
     5) telemetry/observability polish.
3. **Keep slices vertical when possible**
   - Each slice should include minimal code + tests needed for confidence.
4. **Set merge gates per slice**
   - Explicit tests to pass.
   - Rollback/fallback notes for risky changes.
5. **Cross-check overlap**
   - Confirm no open PR already owns the same slice before starting.

## Guardrails
- Avoid giant PRs mixing unrelated concerns.
- Keep SQL in repositories and routing in `AIPS_Ajax_Registry`.
- Ensure each slice can be reviewed in isolation.

## Useful references
- `docs/DEV_HANDBOOK.md`
- `docs/DEVELOPMENT_GUIDELINES.md`
- `ai-post-scheduler/tests/`
