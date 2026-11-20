# 06 — Resume Bullets and Interview Stories

**Scope:** Part 7 of the audit.
**Grounding:** Every bullet below is traceable to evidence in `01`–`05`. Bullets marked **[needs confirmation]** rely on an inference this audit could not fully verify from the repository alone (e.g., intent behind a private session) and should be confirmed with the candidate before use.

Target role: Full Stack Software Engineer (TypeScript/React), OneImaging.

---

## Set A — Agentic Software Engineering (6–8 bullets)

1. Designed and shipped a custom multi-agent code-review pipeline ("PR Xray") for a 234K-line production codebase — a coordinator/specialist/adjudicator architecture with 12 role-scoped subagents (security, database migration, background jobs, localization, generation pipeline, bootstrap/dependency) enforcing least-privilege, read-only tool access per reviewer, preceded by a written design doc and implementation plan.
2. Directed five distinct AI coding-agent integrations (Claude, Cursor Agent, Google Labs Jules, GitHub Copilot, OpenAI Codex) across a single repository's development lifecycle, reviewing and merging AI-authored commits against a CI-enforced architectural-boundary gate.
3. Converted an architectural convention (strict controller/repository/service/template layering) into an automated, CI-blocking static-analysis check (`composer lint:repository-boundary`), ensuring AI-agent-authored and human-authored code are held to an identical standard before merge.
4. Established a containerized, repeatable test protocol specifically for AI-agent sessions (`make test-session`) that provisions an isolated test database and guarantees clean teardown — a safeguard against agent-driven infrastructure drift.
5. Drove a multi-PR correctness fix from root-cause isolation through a sequence of independently reviewable, mergeable pull requests, including a same-PR self-correction where a test assertion bug was caught and fixed within the same review cycle. **[needs confirmation: candidate's own role vs. agent's role in authoring the fix chain, beyond the Co-Authored-By attribution found]**
6. Reviewed and merged an AI-agent-authored feature PR (topic-silo content linking) that was revised in place after a concurrency/race-condition gap was identified, adding an atomic claim operation and a dedicated regression test before merge.
7. Built reusable, scoped review-automation workflows (e.g., a security sweep that fans out one reviewer per API endpoint class) rather than relying on ad hoc one-off review prompts, formalizing the review process itself as versioned, documented tooling.
8. Maintained a living, versioned instruction system (`AGENTS.md`/`CLAUDE.md`) read by every coding-agent session, codifying architecture rules, test protocol, and feature-delivery process as enforceable documentation rather than tacit team knowledge.

---

## Set B — AI Application Engineering (6–8 bullets)

1. Built a provider-abstraction layer for LLM integrations (factory pattern with configurable selection order and a null-object fallback) decoupling a WordPress content-generation plugin's business logic from any single AI backend, supporting both a third-party AI engine and the WordPress AI Client library.
2. Designed a resilience-wrapped AI service layer separating retry/circuit-breaker/rate-limiting policy from generation logic, including a model-behavior-specific tuning constant compensating for reasoning-model hidden-token consumption.
3. Implemented an 8-category AI error-classification system (rate limit, context length, auth, timeout, provider down, content policy, JSON parsing, general) with severity and secret-redaction handling, surfaced in an admin diagnostics UI.
4. Built a quota- and cooldown-aware embeddings subsystem for semantic similarity and topic recommendation, including its own rate limiter and repository layer, running as a managed background job rather than a blocking request-time call.
5. Designed a context-object-driven generation and regeneration pipeline that reconstructs an exact prior generation request from persisted history, enabling reliable re-generation rather than one-shot, unrepeatable AI calls.
6. Profiled and reduced input-token usage across a multi-step LLM generation pipeline, identifying and fixing three distinct root causes of prompt bloat with quantified per-post token savings and accompanying regression tests.
7. Built a managed background-process framework enforcing per-run AI budget limits and provider cooldowns for AI-backed scheduled jobs, preventing uncontrolled API cost/rate-limit exposure from automated content generation.
8. Instrumented the AI generation pipeline with structured logging, correlation IDs, and a persisted history service, enabling traceable, auditable AI-call observability across a scheduled/background execution model.

---

## Set C — Full-Stack JavaScript / Transferable Skills (4–6 bullets)

1. Architected and maintained a ~40,000-line vanilla JavaScript admin application using a consistent namespaced-module pattern, a custom auto-escaping client-side templating engine, and a state-management discipline for reconciling multiple synchronized views of the same selection state.
2. Designed a REST API endpoint (versioned namespace, explicit permission callback, typed/sanitized argument schema) for anonymous-visitor analytics tracking, demonstrating REST API design fundamentals transferable to Express/Next.js route handlers.
3. Implemented adaptive real-time-style UI polling (WordPress Heartbeat API) with distinct active/idle intervals, reducing unnecessary client-server traffic — the same backoff/polling-strategy thinking required for WebSocket/SSE or polling-based React dashboards.
4. Enforced a strict backend/frontend separation (presentation-only templates, no business logic or SQL in the view layer) across dozens of server-to-client data-bridging call sites, backed by a CI-enforced architectural boundary check.
5. Wrote regression tests for client-side state bugs using Node's built-in test runner, demonstrating JavaScript testing discipline independent of any specific framework.

---

## Five strongest bullets overall for OneImaging (recommended)

1. *(Set A #1)* Designed and shipped a custom multi-agent code-review pipeline — coordinator/specialist/adjudicator architecture with least-privilege tool access per reviewer, preceded by a written design doc and implementation plan.
2. *(Set B #1)* Built a provider-abstraction layer for LLM integrations with a factory pattern and null-object fallback, decoupling business logic from any single AI backend.
3. *(Set A #3)* Converted an architectural convention into a CI-blocking static-analysis check, ensuring AI-agent-authored and human-authored code meet an identical standard.
4. *(Set B #6)* Profiled and reduced LLM input-token usage across a generation pipeline with quantified, per-root-cause savings and regression tests.
5. *(Set C #2)* Designed a REST API endpoint with versioned namespace, explicit permission handling, and typed argument validation.

**Why these five:** they are the most concretely evidenced (direct file/PR citations), the least dependent on inference about private agent sessions, and together they tell a coherent story — architecting AI-agent workflows, building production AI integrations responsibly (cost/resilience-aware), enforcing engineering discipline via CI, and demonstrating transferable API-design fundamentals — without resting on any technology (React/TS/Node/Express/Postgres) this project doesn't actually contain.

---

## Five technical interview stories (STAR format)

### Story 1 — Designing least-privilege boundaries in a multi-agent review system

- **Situation:** PR review and triage across a large, actively AI-agent-contributed codebase had no consistent, repeatable structure.
- **Task:** Build a review pipeline that could reliably catch domain-specific issues (security, migrations, background jobs) without giving every reviewing agent unrestricted write access.
- **Action:** Designed a coordinator → specialist(s) → adjudicator → report-assembler pipeline (12 named subagents), writing the design and implementation plan as explicit documents before building, and granting write/execute access to exactly one agent in the chain (the report assembler) while every reviewer stayed read-only.
- **Result:** A reusable, auditable review system (PR #2362) now documented as live tooling in the project's own contributor instructions, applied to subsequent PRs.
- **Evidence:** PR #2362; `.claude/agents/*`; `.claude/docs/2026-11-11-pr-xray-design.md`.

### Story 2 — Making an architectural rule unbypassable

- **Situation:** A strict controller/repository/service/template layering rule existed only as written documentation, which any contributor (human or AI agent) could unintentionally violate.
- **Task:** Make the rule self-enforcing rather than relying on review vigilance.
- **Action:** Wrote a custom static-analysis script that scans for `$wpdb` usage outside repository classes and wired it into CI as a required, blocking step on every pull request.
- **Result:** Layer violations are now caught automatically before merge, regardless of who (or what) authored the change.
- **Evidence:** `composer.json` `lint:repository-boundary`; `.github/workflows/ci-pr.yml`.

### Story 3 — Decoupling a feature from a single AI vendor

- **Situation:** A content-generation feature depended on a single third-party AI engine, creating a hard dependency risk and no graceful behavior when AI wasn't configured.
- **Task:** Make the AI integration swappable and safe to run with no provider configured at all.
- **Action:** Built a provider factory with an explicit selection order (explicit id → configured setting → first available → null-object fallback), so the rest of the codebase never has to null-check for "AI unavailable," and added a settings UI surfacing live availability per provider.
- **Result:** A second AI backend (the WordPress AI Client library) was added without touching consuming code, and the plugin degrades gracefully with no provider configured.
- **Evidence:** `AIPS_AI_Provider_Factory` (`includes/class-aips-ai-provider-factory.php`).

### Story 4 — Catching a concurrency bug before it shipped

- **Situation:** A newly implemented campaign-content feature let posts claim "pillar" status within a topic silo, but concurrent generation could let two posts race to claim the same slot.
- **Task:** Close the race condition before merge, not after a production incident.
- **Action:** Identified the gap during a second implementation pass, added an atomic `claim_pillar_post_if_unset()` operation, and wrote a dedicated concurrency-safety regression test rather than relying on manual verification.
- **Result:** The feature merged with the race condition closed and covered by a permanent automated test.
- **Evidence:** PR #2320; `Test_AIPS_Campaign_Silo_Safety.php`.

### Story 5 — Turning cost awareness into an engineering task

- **Situation:** A multi-step LLM generation pipeline was consuming more input tokens per post than necessary, directly increasing API cost and latency at scale.
- **Task:** Find and fix the specific causes of token bloat, not just "optimize prompts" generically.
- **Action:** Profiled prompt assembly across the pipeline, isolated three distinct root causes, and fixed each independently with quantified before/after token estimates and accompanying unit tests.
- **Result:** Measurable per-post token reduction across three separate code paths, shipped with regression coverage so the savings don't silently regress.
- **Evidence:** PR #2342 ("perf(prompts): eliminate input token bloat across generation pipeline").
