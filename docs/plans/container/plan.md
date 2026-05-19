# AIPS Container Improvement and Adoption Plan

## Objective
Strengthen AIPS_Container so it is a reliable, testable, and consistently used dependency resolution layer across the plugin, while preserving backward compatibility during migration.

## Scope
- Improve AIPS_Container capabilities, guardrails, and observability.
- Standardize container registration for core services and high-churn dependencies.
- Replace ad hoc direct instantiation patterns with container-based resolution in prioritized classes.
- Add migration-safe patterns for constructors and runtime boot flows.
- Expand automated test coverage for container behavior and usage.

## Current State Summary
Observed in the codebase:
- AIPS_Container exists and supports singleton and transient bindings, has(), make(), makeIfExists(), clear(), and introspection methods.
- Core bindings are registered in AI_Post_Scheduler::register_container_bindings() and are loaded from boot_common().
- Adoption is partial. Many classes already call AIPS_Container::get_instance(), but many still construct dependencies directly using new AIPS_Logger, new AIPS_History_Service, new AIPS_AI_Service, and new AIPS_Resilience_Service.
- There is a mixed style: has()/make() with fallback new is common, but consistency and policy are not enforced.
- Existing tests validate baseline container behavior and initial binding registration.

## Target Architecture
### Container Responsibilities
- Single source of truth for service lifetime and interface-to-implementation resolution.
- Deterministic runtime behavior across admin, ajax, cron, and frontend contexts.
- Safe migration support through fallback resolution where needed.
- Introspection support for diagnostics and test assertions.

### Usage Policy
- Constructors should prefer explicit dependency injection parameters first.
- If a dependency parameter is omitted, resolve through AIPS_Container.
- Fallback direct instantiation is temporary and only used in migration-safe wrappers.
- New feature code should not introduce fresh direct new calls for core cross-cutting services.

## Guiding Principles
- Backward compatible changes first, strictness later.
- Migrate highest-risk and highest-frequency execution paths first.
- Keep boot-time behavior stable and avoid request-context regressions.
- Use interface aliases wherever practical to reduce coupling.
- Add tests before tightening enforcement.

## Workstreams

## Workstream 1: Strengthen Container API and Safety
### Tasks
1. Add a dedicated registration helper surface on AIPS_Container for clarity and policy:
   - singleton_alias(abstract, concreteOrFactory)
   - bind_alias(abstract, concreteOrFactory)
2. Add protection against accidental double-registration drift:
   - Optional strict mode for duplicate binding keys.
   - Clear warning path in non-strict mode.
3. Improve error messages in make() to include:
   - Requested id.
   - Whether a similarly named binding exists.
   - Current registered count summary.
4. Add lightweight resolution diagnostics hooks (non-invasive):
   - Resolve attempt count by id.
   - Resolve hit source (singleton cache, singleton factory, transient factory, fallback).

### Acceptance Criteria
- Container can register aliases and services without ambiguous behavior.
- Duplicate registration behavior is deterministic and test-covered.
- Runtime exceptions from missing bindings are actionable.
- Diagnostics can be queried in tests and optionally exposed to debug tools.

## Workstream 2: Binding Catalog and Registration Governance
### Tasks
1. Split binding registration into focused methods in bootstrap:
   - register_core_bindings()
   - register_domain_bindings()
   - register_runtime_bindings_if_needed()
2. Create a canonical binding catalog document in docs:
   - Abstract id
   - Concrete target
   - Lifetime
   - Context availability
3. Register missing high-value services currently often instantiated directly:
   - AIPS_Resilience_Service
   - Selected controllers/services used in cron and diagnostics paths
4. Ensure aliases exist for all interface types used in constructors.

### Acceptance Criteria
- Binding registration is organized and discoverable.
- Developers can verify expected bindings from one catalog.
- High-frequency dependencies are available from container in all required contexts.

## Workstream 3: Usage Migration by Priority Tiers

### Tier 1: Critical Runtime Paths (first)
Targets:
- Scheduler and generation orchestration paths
- Author topics and author post automation paths
- Bulk batch and job dispatching paths

Tasks:
1. Replace direct new calls for logger/history/ai/resilience with container resolution.
2. Normalize constructor pattern:
   - Parameter override
   - Else container make or makeIfExists
   - Else temporary fallback for compatibility
3. Ensure no behavior change in cron execution and retries.

Acceptance Criteria:
- Tier 1 classes no longer directly instantiate core cross-cutting services unless explicitly justified.
- Existing cron and generation tests pass.
- No regression in hook execution ordering.

### Tier 2: Admin and AJAX Controllers
Targets:
- Research, templates, taxonomy, internal links, settings, system status related controllers and services.

Tasks:
1. Migrate constructors and helper methods to container-first resolution.
2. Remove local ad hoc service creation in action handlers.
3. Preserve nonce/capability and response behavior.

Acceptance Criteria:
- Controller/service wiring is consistent across admin and ajax paths.
- Existing UI actions continue to function with same response contracts.

### Tier 3: Diagnostics and Supporting Utilities
Targets:
- Diagnostics providers and low-frequency helper classes.

Tasks:
1. Resolve resilience/logger/history through bindings.
2. Remove isolated direct-instantiation islands.

Acceptance Criteria:
- Diagnostics output remains stable.
- Utility classes align with the same dependency policy.

## Workstream 4: Enforcement and Developer Experience
### Tasks
1. Add a static check script rule set for includes directory:
   - Flag new AIPS_Logger / AIPS_History_Service / AIPS_AI_Service / AIPS_Resilience_Service direct instantiation.
   - Allow exceptions only in approved legacy compatibility zones.
2. Add a lightweight architectural test that fails when new violations are introduced.
3. Update developer docs with constructor template and migration guidance.

### Acceptance Criteria
- New violations are caught in CI or local test runs.
- Team has clear examples and policy for dependency resolution.

## Phased Timeline

### Phase 0: Baseline and Inventory (0.5 to 1 day)
- Snapshot current direct instantiation counts and container usage counts.
- Finalize migration priority list and owner mapping.

Exit Criteria:
- Approved inventory and migration sequence.

### Phase 1: Container Hardening (1 to 2 days)
- Implement Workstream 1 changes.
- Add corresponding unit tests.

Exit Criteria:
- Container API updates merged with full test pass.

### Phase 2: Binding Governance (1 day)
- Implement Workstream 2 and publish binding catalog.

Exit Criteria:
- All required bindings and aliases available.

### Phase 3: Tiered Migration (3 to 5 days)
- Execute Tier 1, then Tier 2, then Tier 3.
- Validate each tier before proceeding.

Exit Criteria:
- Priority classes migrated and regression tested.

### Phase 4: Enforcement Rollout (1 day)
- Add checks, tests, and documentation updates.

Exit Criteria:
- Guardrails active and documented.

## Implementation Sequencing Details
1. Harden container and tests first.
2. Expand bindings before migrating classes.
3. Migrate Tier 1 in small pull requests grouped by runtime domain.
4. After Tier 1 stabilizes, migrate Tier 2 by controller domain.
5. Migrate Tier 3 and then enable stricter enforcement.
6. Remove temporary fallbacks where coverage is sufficient.

## Testing Strategy
### Unit Tests
- AIPS_Container API additions: alias registration, duplicate handling, diagnostics counters.
- Binding registration tests: expected ids, scopes, and interface mappings.

### Integration Tests
- Boot path checks for common, cron, ajax, admin contexts.
- Constructor wiring checks in migrated classes.

### Regression Tests
- Scheduled generation workflows.
- Author topics and author posts workflows.
- Bulk job dispatch/retry behavior.
- Admin ajax endpoints for migrated controllers.

### Non-Functional Verification
- Confirm no meaningful startup performance regressions.
- Validate telemetry/logging remains coherent after service wiring changes.

## Rollout and Deployment Considerations
- Prefer incremental merges to reduce blast radius.
- Release behind normal plugin versioning with changelog notes.
- Keep migration-safe fallbacks through at least one release cycle.
- Monitor cron failures, ajax error rates, and logging anomalies after deployment.

## Risk Register and Mitigations
1. Risk: Hidden constructor side effects when changing resolution path.
   Mitigation: Migrate class-by-class with focused regression tests and small PRs.
2. Risk: Missing binding in a request context.
   Mitigation: Binding catalog plus boot-context integration tests.
3. Risk: Circular dependency introduction.
   Mitigation: Add detection and clear runtime diagnostics in container resolution path.
4. Risk: Performance overhead from over-instrumentation.
   Mitigation: Keep diagnostics lightweight and disable expensive tracing by default.
5. Risk: Inconsistent adoption after initial migration.
   Mitigation: CI enforcement rules and documentation templates.

## Definition of Done
- Container API improvements merged and fully tested.
- Binding catalog documented and aligned with runtime needs.
- Priority tiers migrated with no critical regressions.
- Direct new calls for core cross-cutting services reduced to approved exception list.
- CI or test guardrails prevent reintroduction of new violations.

## Deliverables
- Updated container implementation and tests.
- Updated bootstrap binding registration structure.
- Migration pull requests by tier.
- Binding catalog documentation.
- Enforcement checks and developer guidelines update.

## Suggested First PR Slice
1. Container hardening plus tests only.
2. Binding governance refactor only.
3. Tier 1 migration for job dispatcher and scheduler-adjacent classes.
4. Tier 1 migration for author automation classes.
5. Enforcement script and docs update.
