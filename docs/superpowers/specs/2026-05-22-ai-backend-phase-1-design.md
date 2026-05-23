# AI Backend Phase 1 Design

**Date:** 2026-05-22

**Issue:** [#1625](https://github.com/rpnunez/wp-ai-scheduler/issues/1625)

**Goal**

Introduce an internal AI backend seam so the plugin can support multiple AI providers in subsequent phases, while keeping current runtime behavior unchanged for the Meow AI Engine path.

## Scope

Phase 1 only covers contract and factory groundwork.

Included:

- Keep `AIPS_AI_Service_Interface` as the public contract used by callers.
- Add backend identifiers/constants and a small resolver/helper for selecting the active backend.
- Add `AIPS_AI_Service_Factory` that returns an `AIPS_AI_Service_Interface` implementation.
- Refactor `AIPS_AI_Service` into a facade that delegates public calls to a resolved backend implementation.
- Move the current Meow-specific generation logic into a dedicated backend implementation class.
- Add targeted PHPUnit coverage for factory resolution and facade delegation.

Excluded:

- No user-facing backend settings UI.
- No production use of any backend other than the existing Meow behavior.
- No migration of call sites away from `AIPS_AI_Service_Interface`.
- No behavior changes to prompts, retry policy, logging payloads, or admin flows beyond what is required to preserve current behavior through delegation.

## Current State

The repo already contains `AIPS_AI_Service_Interface`, and many consumers already type-hint against it. `AIPS_AI_Service` currently contains all runtime AI logic, including:

- availability checks against the Meow AI Engine global
- text, JSON, and image generation
- retry/circuit-breaker integration
- call logging and notification dispatch
- convenience accessors for call log and resilience state

That means the contract seam exists, but the implementation seam does not. Phase 1 should separate those responsibilities without requiring call-site rewrites.

## Recommended Approach

Use `AIPS_AI_Service` as the stable facade and make the factory default to the Meow-backed implementation.

Why this approach:

- It preserves every current constructor fallback like `new AIPS_AI_Service()` and avoids broad churn.
- It lets the rest of the plugin continue depending on `AIPS_AI_Service_Interface` without knowing about provider classes.
- It matches the issue requirement that runtime behavior remain unchanged and that only hidden/minimal backend selection exist in Phase 1.

Alternatives considered:

1. Replace all call sites with a factory-resolved backend directly.
   Rejected because it widens the refactor and increases regression risk.

2. Leave `AIPS_AI_Service` concrete and only add a factory for future use.
   Rejected because it does not establish the delegation seam the issue explicitly asks for.

3. Expose backend settings now.
   Rejected because the issue marks UI/backend selection as out of scope for Phase 1.

## Target Architecture

### Public contract

`AIPS_AI_Service_Interface` remains the single interface used by consumers in this phase.

### Backend identifiers

Add a lightweight backend identifier source, likely as constants or helper methods on the factory, with Meow as the only production backend currently resolved by default.

Expected identifiers:

- `meow`

### Factory

`AIPS_AI_Service_Factory` becomes responsible for:

- resolving the configured or default backend id
- instantiating the matching backend implementation
- returning `AIPS_AI_Service_Interface`
- falling back to the Meow backend when no override is present

The resolver should stay internal and conservative. A hidden filter or internal config value is acceptable if needed for tests, but the default behavior must resolve to Meow with no UI involvement.

### Facade

`AIPS_AI_Service` should become a facade with these responsibilities:

- preserve the existing singleton/static entry point if still used
- resolve the concrete backend via `AIPS_AI_Service_Factory`
- delegate all interface methods to the backend instance
- preserve auxiliary methods that callers may rely on, such as call-log and resilience status accessors, by forwarding them when the backend supports them

The facade should not duplicate the Meow business logic after the refactor.

### Meow backend implementation

Create a dedicated class to host the current logic that lives inside `AIPS_AI_Service`. This class should:

- implement `AIPS_AI_Service_Interface`
- keep the existing Meow AI Engine integration behavior
- retain the current retry/logging/error/notification behavior
- remain the default backend returned by the factory

The class name should be explicit about its provider role and fit the repo naming conventions.

## File-Level Changes

Likely files to create:

- `ai-post-scheduler/includes/class-aips-ai-service-factory.php`
- `ai-post-scheduler/includes/class-aips-meow-ai-service.php` or equivalent provider-specific class
- `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`

Likely files to modify:

- `ai-post-scheduler/includes/class-aips-ai-service.php`
- `ai-post-scheduler/tests/bootstrap.php`
- any container/bootstrap file if the new classes need explicit loading in limited test mode

Likely files not to modify in Phase 1:

- admin templates
- settings UI
- AJAX registry
- schema/migration code
- generation call sites across the plugin except where constructor wiring is unavoidable

## Behavioral Requirements

Phase 1 must preserve these behaviors:

- `new AIPS_AI_Service()` continues to work.
- Existing consumers resolved through `AIPS_AI_Service_Interface` continue to work.
- When Meow AI Engine is unavailable, the same error path remains intact.
- Text, JSON, and image generation outputs remain behaviorally unchanged.
- Existing logging, call-log capture, quota alerts, and integration-error notifications still fire through the Meow-backed implementation.

Phase 1 must also ensure:

- no user-facing indication of multiple backends appears yet
- no backend switcher is added to settings
- no alternate provider is used in production by default

## Testing Strategy

Tests should stay narrow and seam-focused.

Required coverage:

- factory returns an `AIPS_AI_Service_Interface` implementation
- factory defaults to the Meow backend
- hidden/internal override behavior, if introduced, resolves the expected backend id
- `AIPS_AI_Service` facade delegates interface calls to the resolved backend
- existing constructor patterns still succeed in limited test mode

Do not try to fully re-test Meow generation behavior in Phase 1 unless the refactor requires adapting an existing test. The priority is proving the seam and proving that delegation preserves the contract.

Primary verification command from the issue:

`composer test -- tests/Test_AIPS_AI_Backend_Factory.php`

Secondary verification:

- run any additional targeted AI-service tests affected by the refactor
- run focused bootstrap/container tests if class loading or container bindings change

## Risks

1. Consumer breakage from overly aggressive constructor or container changes.
   Mitigation: keep public contract and fallback construction paths unchanged.

2. Lost helper behavior if `AIPS_AI_Service` only delegates interface methods and forgets compatibility methods such as call-log accessors.
   Mitigation: preserve compatibility wrappers on the facade for known helper methods.

3. Limited-mode PHPUnit failures if new classes are not added to `tests/bootstrap.php`.
   Mitigation: update bootstrap explicitly and verify the targeted test file.

4. Silent behavior drift if the provider-specific class changes logging or error handling while being extracted.
   Mitigation: move code with minimal logic changes and keep tests focused on the seam.

## Success Criteria

Phase 1 is complete when:

- a dedicated backend resolver/factory exists
- `AIPS_AI_Service` is a facade rather than the sole concrete implementation
- the Meow logic lives in a provider-specific implementation class
- no UI or settings changes are introduced
- targeted PHPUnit coverage for the seam passes
