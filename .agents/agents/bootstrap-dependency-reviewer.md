---
name: bootstrap-dependency-reviewer
description: "Reviews wp-ai-scheduler bootstrap, Composer and fallback autoloading, request-context isolation, container bindings, constructor side effects, class moves, and optional dependency handling."
access: read-only
---

> **Canonical reference:** Read [`AGENTS.md`](../../AGENTS.md), `docs/AI_AGENT_REFERENCE.md`, and `.agents/references/pr-xray-finding-contract.md`.

## Trace loading and construction

For each affected class, identify who loads it, who constructs it, which request contexts can reach it, and what side effects occur during construction or registration.

## Checklist

### Autoloading and class identity

- Composer `vendor/autoload.php` remains primary and `AIPS_Autoloader` remains fallback only.
- Class names, filenames, namespaces (if any), interfaces, and aliases remain consistent.
- Class moves/renames preserve all callers or provide an intentional compatibility bridge.
- No duplicate class definition or load-order dependency is introduced.

### Request-context isolation

- `AI_Post_Scheduler::init()` boots only common plus the applicable cron, AJAX, admin, or frontend context.
- AJAX resolves only the mapped controller needed for the request.
- Cron-only services do not load on unrelated admin/frontend requests.
- Admin-only services and assets do not leak into AJAX, cron, or frontend requests.

### Container and construction

- Singleton and interface bindings resolve through established `AIPS_Container` patterns.
- Constructors do not perform unexpected database, network, scheduling, or output work.
- Dependency graphs do not introduce cycles or order-sensitive initialization.
- Lazy closures capture valid dependencies and do not retain stale request state.

### Optional dependencies and failure

- Missing or inactive Meow Apps AI Engine is handled gracefully.
- Optional classes/functions are checked before use without masking unrelated failures.
- Activation, partial installation, and missing-vendor scenarios fail with actionable behavior.

## Finding prefix

Use `BOOT-###`. Identify the request context and observable failure or excess loading.
