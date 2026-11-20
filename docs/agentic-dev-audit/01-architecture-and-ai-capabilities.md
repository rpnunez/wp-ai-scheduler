# 01 — Architecture & AI Capabilities Audit

**Scope:** Part 1 (Repository & Architecture Discovery) and Part 3 (Production AI Engineering) of the agentic development audit.
**Method:** Direct repository inspection (file counts, grep/read of key classes, CI workflow files, Composer/Makefile tooling). Read-only; no code was modified.
**Measured:** 2026-11-14, against branch `ccr-b711ef3a-s6b7nf` (HEAD at the time of measurement, descended from `main`).

---

## 1. Repository shape and engineering maturity

AI Post Scheduler (AIPS) is a WordPress plugin, not a generic web app. It targets PHP ≥8.2 and WordPress ≥5.8, currently at version **3.7.24** (`ai-post-scheduler/ai-post-scheduler.php` header; `AGENTS.md:8-9`).

Scale (measured via `find`/`wc -l`, excluding vendor):

| Area | Count |
|---|---|
| PHP files under `includes/` | 324 |
| PHP LOC (`ai-post-scheduler/**/*.php`, excl. vendor) | ~234,834 |
| JS files under `assets/js/` | 53–64 (two passes counted slightly differently depending on glob depth) |
| JS LOC under `assets/js/` | ~40,569 |
| PHPUnit test files (`tests/Test_*.php`) | 234 |

This is a large, long-lived codebase with a deliberate internal layering, not a single-file plugin. The naming-convention breakdown of `includes/` (39 controllers, 47 repositories, 46 services, plus context/processor/provider classes) reflects a consistent architectural vocabulary applied across hundreds of files.

There is **no `package.json` anywhere in the repository** (confirmed via `find`) — the JS layer has no build step, no npm dependency tree, and no TypeScript. This matters for Part 4 (full-stack assessment): the frontend is hand-written jQuery, not a modern SPA toolchain. See `04-javascript-fullstack-assessment.md` for the full breakdown.

## 2. Request-context boot architecture

`AI_Post_Scheduler::init()` (hooked on WordPress's `init` action; the singleton itself is constructed on `plugins_loaded` priority 5 via `aips_init()`) always calls `boot_common()` and then exactly one context-specific boot method, per `CLAUDE.md`:

| Context | Boot method | What loads |
|---|---|---|
| WP-Cron | `boot_cron()` | Lazy closures for schedulers, batch processors, embeddings, sources, notifications |
| AJAX | `boot_ajax()` | One controller resolved from `AIPS_Ajax_Registry` |
| Admin | `boot_admin()` | Admin menu, assets, settings, onboarding, notifications, reconciler |
| Frontend | `boot_frontend()` | Admin toolbar node only |

**Confidence note:** the boot dispatch structure is documented identically in `AGENTS.md`/`CLAUDE.md` and is consistent with the AJAX-registry and container evidence found directly in code (below); the exact method bodies of `boot_cron()`/`boot_admin()`/`boot_frontend()` were not individually re-read line-by-line in this pass. Treat the table as **documented architecture, corroborated but not exhaustively re-verified**.

The practical effect — only the classes needed for the current request type are ever instantiated — is a deliberate performance/isolation decision uncommon in smaller WordPress plugins, and it is the kind of request-lifecycle thinking that transfers directly to designing middleware/route-scoped dependency graphs in a Node/Express or Next.js backend.

## 3. AJAX routing and dependency injection

- **AJAX registry:** every `wp_ajax_*` action is registered in `AIPS_Ajax_Registry::$map` (`includes/class-aips-ajax-registry.php`), mapping `action_name → ControllerClass`. `boot_ajax()` reads `$_REQUEST['action']`, resolves the controller via the registry, and constructs it — the constructor registers the actual WordPress hook. CLAUDE.md states explicitly: "Never add ad hoc AJAX registrations outside the registry." This is a single, auditable routing table across 39 controller classes — functionally analogous to a central route table in Express (`app.use('/api/:action', dispatch)`), enforced by convention and, per the reviewer-agent list, by a dedicated `ajax-controller-reviewer` subagent.
- **DI container:** `AIPS_Container::get_instance()->make(ClassName::class)` resolves registered singletons and interface aliases. Confirmed in code: `AIPS_AI_Service::__construct()` (`includes/class-aips-ai-service.php:85-101`) pulls `AIPS_Logger_Interface` from the container with a static-instance fallback, and is itself composed from `AIPS_Config`, `AIPS_Resilience_Service`, and an `AIPS_AI_Provider_Interface` — a real interface-aliasing DI pattern (not just a service locator calling concrete classes).

## 4. Layer separation — enforced, not just documented

CLAUDE.md states the rule (controllers → hooks/validation; repositories → all `$wpdb` SQL; services → business logic, no direct DB; templates → presentation only). Two pieces of hard evidence that this is **mechanically enforced**, not aspirational:

1. `ai-post-scheduler/composer.json` defines `composer lint:repository-boundary`, backed by a custom static-analysis script (`tools/check-repository-boundary.php`) that fails the build if `$wpdb` is referenced outside a repository class.
2. `.github/workflows/ci-pr.yml` runs this lint as a required CI step ("Enforce repository boundary (no direct wpdb in controllers/services)") on every pull request, alongside the full PHPUnit suite.

This is a concrete example of translating an architectural rule into an automated, CI-gated check — the same discipline that underlies lint rules like `eslint-plugin-boundaries` or dependency-cruiser gates in a TypeScript monorepo.

## 5. Schema and migrations

Per CLAUDE.md: schema changes go through `AIPS_DB_Manager::get_schema()` + WordPress's `dbDelta`, versioned by bumping the plugin's `Version:` header and `AIPS_VERSION`; the runtime migration entry point is `AIPS_DB_Migrations::check_and_run()`. **Not independently re-read line-by-line in this pass** — flagged as documented-but-not-re-verified. A recent commit (`fix(db): move index-consolidation migration to 3.7.22`, attributed to Cursor Agent per the git history research) is corroborating evidence that this migration-by-version mechanism is live and actively maintained.

## 6. CI/CD pipelines

`.github/workflows/` contains 21 workflow definitions. Notable ones, each independently confirmed:

- **`ci-pr.yml`** — runs on PR open/sync/reopen/ready-for-review; spins up a MySQL 8.0 service container, installs Composer deps and the WordPress PHPUnit test library (`scripts/install-wp-tests.sh`), runs the repository-boundary lint, conditionally runs PHPCS/PHPStan, runs the full PHPUnit suite (`composer test -- --colors=always -v`), and builds/uploads a plugin zip artifact.
- **`phpunit-tests.yml`** — separate workflow on push/PR to `main`/`develop`; runs PHPUnit under Xdebug for coverage, uploads HTML coverage and the PHPUnit result cache, and has a `test-summary` gate job that fails the run if the test job itself didn't succeed.
- **`docker-ai-api-tests.yml`** — a workflow dedicated to Docker-based tests that touch the AI API surface, kept separate from the plain unit-test workflow (evidence of deliberately isolating tests with external dependencies/cost from the fast unit suite).
- **`feature-agent.yml`** — a scheduled (weekly cron + manual dispatch) job titled "Docs - Update Feature Report," running a Python feature scanner across full git history with `contents: write, pull-requests: write` permissions, to keep `docs/FEATURE_LIST.md`-style documentation current automatically.
- **Agent-runtime workflows** — four GitHub-native "coding agent" workflow entries are registered and `state: active`: `dynamic/agents/anthropic-code-agent` ("Claude"), `dynamic/agents/openai-code-agent` ("OpenAI Codex"), `dynamic/copilot-swe-agent/copilot` ("Copilot cloud agent"), `dynamic/copilot-pull-request-reviewer/copilot-pull-request-reviewer` ("Copilot code review"). This is **the strongest single piece of evidence** that multiple distinct AI coding-agent products were formally wired into this repository's GitHub automation, independent of local CLI usage. (Full discussion in `02-agentic-development-workflow.md`.)
- Several conventional CI workflows (`ci-pr.yml`, `phpunit-tests.yml`, `docker-tests.yml`, `qodana_code_quality.yml`, `ci-load-smoke.yml`, `gutenberg-unit-test.yml`) show `disabled_manually` in the live workflow list as of the measurement date — i.e., they exist and have run historically but are not currently enabled. **Do not claim these are currently green/running in production** without re-checking; they are present in history, not necessarily active today.

## 7. Testing infrastructure

- 234 `Test_*.php` files under `ai-post-scheduler/tests/`. Sampled filenames show targeted unit coverage across the AI provider/service layer (`Test_AIPS_AI_Provider_Factory.php`, `Test_AIPS_AI_Service_With_Provider.php`, `Test_AIPS_AI_Error_Classifier.php`), background processing (`Test_AIPS_Background_Process_Manager.php`, `Test_AIPS_Background_Process_Adapters.php`, `Test_AIPS_Background_Process_Review_Fixes.php` — the last filename literally documents a review-driven fix cycle), batching (`Test_AIPS_Batch_Slicer.php`, `Test_AIPS_Batch_Queue_Service.php`), concurrency (`Test_AIPS_Author_Post_Concurrency.php`), and five separate cache-driver test files.
- Composer scripts: `test`, `test:coverage`, `test:verbose`, each gated by `test:setup` (`bin/prepare-wp-tests.sh`), plus `lint:repository-boundary`.
- A custom Docker-based test harness (`make test-session`) is the house standard for agents specifically — it runs the suite in a container, against a separate `wp_tests` DB, and **stops the containers it started** (so an agent session doesn't leave infrastructure running). This convention, documented in both `CLAUDE.md` and `TESTING.md`'s "Agent test protocol," is itself evidence of the repo being deliberately shaped for safe, repeatable agent-driven test execution.

## 8. Docker development environment

A single `docker-compose.yml` with a Makefile exposing roughly 30 targets: `start-dev`, `up/down/stop/start/restart`, `shell`/`wp-shell`/`db-shell`, the test targets above, `sql-batch`/`sql-batch-unpack` (read-only SQL diagnostics tooling for dev/prod), `db-backup`/`db-restore`, `xdebug-*` toggles, and `plugin-activate/deactivate/list`. This is unusually thorough tooling for a single-developer WordPress plugin, and it correlates with the agent-oriented testing protocol above: the Makefile exists in large part to give both the human and any AI agent a safe, scriptable, idempotent way to stand up and tear down the exact same environment.

---

## 9. Production AI engineering — how AIPS itself uses AI

This section is about what the *plugin* does with AI at runtime, independent of how it was built.

### 9.1 Provider abstraction layer

`AIPS_AI_Provider_Factory` (`includes/class-aips-ai-provider-factory.php`, 217 lines):

- A `REGISTRY` constant maps named providers to concrete classes: `'meow' => AIPS_Meow_AI_Provider`, `'wp_ai_client' => AIPS_WP_AI_Client_Provider` (lines 34-37) — two real backend integrations: the **Meow Apps AI Engine** plugin (stated as AIPS's primary AI dependency in `AGENTS.md:5`) and the **WordPress AI Client** library, each behind its own provider class under `includes/providers/`.
- Provider selection order is explicit: explicit id → configured option (`aips_ai_provider`) → first available in registry order → `AIPS_Null_AI_Provider` fallback (lines 45-80). The **null-object pattern** here means no caller in the rest of the codebase has to special-case "no AI configured" — a deliberate resilience/API-design choice, directly analogous to a null-object or `Optional<T>`-style default in a TypeScript service layer.
- Per-request instance caching (`self::$instances`) avoids redundant provider construction within one page load, with an explicit `reset_cache()` used for test isolation — evidence of test-driven design intent in the factory itself.
- `available_providers()` / `unavailable_reasons()` / `has_available_provider()` back a settings-page dropdown that surfaces live availability and *why* a provider is unavailable, rather than a flat enabled/disabled toggle.

### 9.2 AI service layer

`AIPS_AI_Service` (`includes/class-aips-ai-service.php`), implementing `AIPS_AI_Service_Interface`:

- Composed from `AIPS_Logger_Interface` (via the DI container, with fallback), `AIPS_Config`, `AIPS_Resilience_Service`, and the active `AIPS_AI_Provider_Interface` (lines 85-101) — transport, logging, config, and resilience are each independently injectable and independently testable.
- `generate_text()` checks `$this->provider->is_available()` before calling out, logs the attempt, and fires `emit_integration_error_notification()` on failure (lines 131-137) — structured, observable error handling rather than silent failure.
- The class docblock explicitly delegates "retry logic, circuit breaker, and rate limiting" to `AIPS_Resilience_Service` — a named resilience abstraction kept separate from the AI call itself, so retry/backoff policy can change without touching generation logic.
- A `SHORT_FORM_MIN_TOKENS = 1200` constant with an explanatory comment about reasoning models consuming hidden tokens against `max_tokens` is a small but telling detail: it is a non-obvious, model-behavior-specific tuning value, indicating real operational experience running against reasoning-capable LLM endpoints, not just a naive wrapper around a chat completion call.
- The same service handles both chat/generation and embeddings calls (an `OPTIONAL_QUERY_OPTION_KEYS` whitelist includes `embeddings_env_id`, `env_id`, `api_key`).

### 9.3 Error classification and quality controls

`AIPS_AI_Error_Classifier` (`includes/class-aips-ai-error-classifier.php`) defines 8 named error categories — `rate_limit`, `context_length`, `auth`, `timeout`, `provider_down`, `content_policy`, `json_parsing`, `general` — each carrying severity, UI badge styling, and icon metadata for the admin screen, backed by dedicated tests (`Test_AIPS_AI_Error_Classifier.php`, `Test_AIPS_AI_Errors_Diagnostics.php`). A `REDACTED_SECRET` placeholder constant confirms secret redaction in error display is a designed concern, not an afterthought. This is a genuine production error taxonomy for an external-API integration — the kind of structured-error design that matters equally in a Node service calling a third-party LLM or payment API.

### 9.4 Embeddings and semantic similarity

Six classes implement a layered embeddings subsystem:

- `AIPS_Embeddings_Service` — composes `AIPS_AI_Service_Interface`, `AIPS_Logger_Interface`, `AIPS_Config`, `AIPS_Embeddings_Rate_Limiter`, `AIPS_Embeddings_Repository`, `AIPS_Author_Topics_Repository`, and `AIPS_Similarity_Evaluator`, with an in-memory embedding cache to avoid redundant calls within a request. Docblock: "semantic similarity features for topic expansion and recommendation."
- `AIPS_Embeddings_Cron`, `AIPS_Embeddings_Background_Process`, and `AIPS_Author_Embeddings_Process` move embeddings generation out of the request path entirely, into a quota- and cooldown-aware managed background job (see §9.6).
- This subsystem reuses the same `AIPS_AI_Service` abstraction rather than standing up a parallel embeddings-only client — a deliberate "one integration point, many consumers" design.

### 9.5 Generation pipeline and regeneration

`AIPS_Generation_Context_Factory` (`includes/class-aips-generation-context-factory.php`) injects `AIPS_History_Repository`, `AIPS_Template_Repository`, `AIPS_Author_Topics_Repository`, `AIPS_Authors_Repository`, and `AIPS_Voices_Repository`. Its `create_from_history_id()` method rebuilds the original generation context (template-based or topic-based) from a stored history record — this is the concrete mechanism behind **regeneration**: re-running generation against the exact parameters of a past run rather than a fresh ad hoc call. CLAUDE.md describes the surrounding context-object pattern (`AIPS_Template_Context`, `AIPS_Topic_Context`, `AIPS_Generation_Context`, driven by `AIPS_Generator`, observed via `AIPS_Generation_Logger` + `AIPS_History_Service` + `AIPS_Correlation_Id`); this factory's behavior corroborates that description structurally, though the individual context classes were not re-read line by line in this pass.

### 9.6 Background execution and scheduling

Seven classes directly implement managed background jobs: `AIPS_Background_Process_Manager`, `AIPS_Background_Processes_Controller`, `AIPS_Background_Process_Assets`, `AIPS_Background_Process_Base`, `AIPS_Background_Process_Repository`, `AIPS_Embeddings_Background_Process`, `AIPS_Managed_Background_Process`. Per CLAUDE.md, managed jobs run one `aips_bg_process_tick` cron slice at a time, track run state in `aips_background_processes`, and respect quota/cooldown and per-run AI-budget limits — i.e., AI-backed background work (embeddings indexing) is deliberately throttled against cost and provider rate limits, not just scheduled. Separate adapters (`AIPS_Content_Indexer_Queue_Process`, `AIPS_Link_Index_Scan_Process`, `AIPS_Bulk_Generation_Process`) plug pipelines that already self-schedule into the same manager, and the UI (admin bar pulse, Internal Links card, Diagnostics → Background Processes) is driven by the WordPress Heartbeat API rather than separate polling — this matches the frontend evidence in `04-javascript-fullstack-assessment.md`.

### 9.7 What was not independently re-verified

- Exact bodies of `boot_cron()` / `boot_admin()` / `boot_frontend()`.
- `AIPS_DB_Manager::get_schema()` / `AIPS_DB_Migrations` contents (documented only).
- `AIPS_Topic_Context` / `AIPS_Template_Context` class bodies.
- A dedicated "topic ranking/scoring" class — no filename matching "ranking" or "scoring" was found in this pass. If the resume needs a specific citable "topic scoring" claim, it requires a targeted follow-up grep; absent that, Part 3's topic-research claims should be limited to what's confirmed (embeddings-based similarity/recommendation via `AIPS_Similarity_Evaluator`, not an explicit ranking algorithm).
- Several named CI workflows (`pr-triage-daily.yml`, `auto-label-by-author.yml`, `standardize-agent-labels.yml`, `release-plugin-zip.yml`, `qodana_code_quality.yml`, `cleanup-vendor.yml`) were identified by name but not opened in full.

---

## Summary judgment

AIPS is a mature, deliberately layered WordPress plugin (controllers/repositories/services/templates, DI container, centralized AJAX routing, CI-enforced boundaries) with a genuinely production-grade AI integration layer: a provider-abstraction factory with graceful degradation, a resilience-wrapped AI service, a named error taxonomy, a quota-aware embeddings/background-job subsystem, and a context-object-driven generation/regeneration pipeline. The architecture and the AI-engineering layer are both real and citable — not aspirational documentation. The clearest gaps for a full-stack-JS narrative are covered in `04-javascript-fullstack-assessment.md`: there is no TypeScript, React, Next.js, Node server, Express, or PostgreSQL anywhere in this repository.
