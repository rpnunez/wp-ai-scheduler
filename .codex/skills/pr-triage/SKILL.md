# PR Triage Skill

Use this skill to evaluate overlap risk, readiness, and review posture for incoming work.

## Scope
- Duplicate/overlapping work detection.
- Risk triage and review checklist preparation.

## Required workflow
1. **Check current open PRs/issues**
   - Pull open PRs before implementation planning.
   - Identify overlap by feature area, files, and acceptance criteria.
2. **Classify PR risk**
   - Mark changes as low/medium/high risk based on schema changes, cron behavior, AJAX surface, and user-impacting UI changes.
3. **Review checklist**
   - Security: nonce/capability/sanitization/escaping.
   - Architecture: registry usage, repository boundaries, context patterns.
   - Reliability: retries, idempotency, batch behavior.
   - Tests: required coverage additions.
4. **Decision output**
   - Recommend: proceed, split, defer, or close as duplicate.
   - Provide concise rationale and next steps.

## Guardrails
- De-duplication is mandatory unless explicitly overridden.
- Prefer small, sliceable PRs over broad mixed-scope changes.

## Useful references
- `AGENTS.md`
- `docs/DEVELOPMENT_GUIDELINES.md`
- `.github/agents/PR Oracle v2.agent.md`
