# PR #1877 Review and Implementation Plan

## Review scope

This review compares PR #1877's ability adapter and workflow runtime with the
WordPress Abilities API and the WordPress AI plugin's `ai/title-generation`
ability. It focuses on interoperability, authorization, destructive-operation
safety, failure recovery, and the minimum coverage needed before the feature is
enabled by default.

Reference material:

- [PR #1877](https://github.com/rpnunez/wp-ai-scheduler/pull/1877)
- [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/)
- [WordPress AI Title Generation](https://github.com/WordPress/ai/blob/develop/docs/experiments/title-generation.md)
- [WordPress AI plugin](https://github.com/WordPress/ai)

## Findings

### P0: asynchronous runs lose the initiating user's authorization context

`WP_Ability::execute()` validates input and calls the ability's permission
callback. Workflow runs are dispatched to cron, where the current user is
normally user `0`. Although a run records `created_by`, the executor does not
restore or explicitly validate that identity before invoking an ability.

This prevents permission-aware abilities from working as intended. In
particular, `ai/title-generation` requires `edit_post` when `context` is a post
ID and `edit_posts` otherwise, so a valid workflow started by an administrator
will normally fail when the queued job executes. It also leaves the intended
security principal for scheduled and retried workflows undefined.

### P0: WordPress destructive annotations are not mapped to the safety gate

WordPress stores behavioral annotations below `meta.annotations`, including
`destructive`. The adapter currently exposes the metadata but the catalog only
checks top-level `is_destructive` or `destructive` values. Consequently, a
WordPress ability marked with `meta.annotations.destructive = true` is treated
as non-destructive and bypasses the workflow's `allow_destructive_abilities`
gate.

Unknown or absent destructive annotations should be handled conservatively.
The builder must distinguish `true`, `false`, and unknown rather than silently
equating unknown with safe.

### P1: WordPress ability categories are discarded

`WP_Ability` exposes its category through `get_category()`, not through
`get_meta()`. The adapter currently reads `meta.category`, so WordPress
abilities—including `ai/title-generation`—fall into the catalog's `general`
category. Category filtering and the builder's ability organization therefore
do not reflect the registered Abilities API data.

### P1: the supported-provider contract is broader than the tested contract

The adapter contains speculative Meow function/class/object discovery paths in
addition to the canonical WordPress Abilities API. Provider call signatures,
list shapes, option handling, and permission behavior differ, while the
workflow layer assumes a single normalized contract. Each supported provider
needs a contract test fixture. Undocumented probes should otherwise be removed
or isolated behind an explicitly filtered provider adapter.

### P1: the title-generation contract needs an integration test

The generic invocation path is structurally compatible with
`ai/title-generation`: it must pass an input object containing `content` and/or
string `context`, and preserve the returned `{ "title": "..." }` object for
later workflow variables. There is no integration test proving discovery,
schema display, authorization, invocation, output persistence, or references
such as `{{steps.generated_title.output.title}}` across an asynchronous run.

The test must also cover the documented errors: `post_not_found`,
`content_not_provided`, insufficient capabilities, and no supported text
generation provider. Those errors must remain machine-readable instead of
being reduced to message-only snapshots.

### P2: repeated discovery adds cost and can produce inconsistent execution

One step is discovered by `get_ability()`, discovered again by
`is_available()`, and only then executed. Apart from extra work, a dynamic
registry can change between those calls. The WordPress provider can resolve a
single `WP_Ability` by name and execute that same instance. Catalog snapshots
should be cached only within one request/run pass and invalidated between cron
invocations so late registrations remain visible.

### P2: options appear supported but are silently ignored by WordPress

`invoke()` publicly accepts `$options`, while the WordPress adapter discards
them. Either define portable options and implement them for every provider, or
reject non-empty unsupported options with a clear `WP_Error`. Silent omission
makes workflow behavior provider-dependent.

## Implementation plan

### Phase 1 — establish the WordPress contract and safety model

1. Add adapter contract tests using a minimal `WP_Ability`-compatible fixture.
   Assert name, label, description, category, input/output schemas, metadata,
   annotations, successful output, `WP_Error`, thrown exception, and malformed
   output behavior.
2. Normalize `get_category()` and `meta.annotations` in
   `AIPS_Ability_Service::wp_ability_to_array()`. Preserve the raw metadata, but
   expose a stable annotation structure to the catalog.
3. Change `AIPS_Ability_Catalog_Service` to derive destructiveness from the
   normalized WordPress annotation. Represent an absent annotation as unknown
   and require explicit approval (or an administrator-configured policy) before
   an unknown state-changing ability can run.
4. Prefer direct `wp_get_ability($name)` lookup for WordPress invocation and
   avoid a full registry listing merely to prove existence. Keep list discovery
   for the builder/catalog endpoint.

### Phase 2 — define and enforce an execution principal

1. Treat the run's immutable `created_by` as the initiating principal for
   manual runs. For recurring/system triggers, require a separately configured
   service user rather than defaulting to user `0` or the workflow's last
   editor.
2. Before every initial, continuation, and retry invocation, verify that the
   user still exists and still has the plugin-level capability required to run
   workflows. Establish the user only for the bounded execution scope and
   restore the previous current user afterward.
3. Continue relying on `WP_Ability::execute()` for ability-specific permission
   checks; do not bypass its permission callback. A title workflow with a post
   ID must therefore re-check `edit_post` and the post type's REST visibility at
   execution time.
4. Persist an authorization failure as a structured terminal step/run error.
   Do not retry permission denials, missing posts, invalid input, or unsupported
   providers; reserve retries for explicitly transient errors.
5. Record the principal ID and trigger type in lifecycle logs without recording
   credentials or full post content.

### Phase 3 — prove `ai/title-generation` end to end

1. Add an integration fixture registered as `ai/title-generation` with the
   documented input/output schemas and permission behavior. Where the official
   WordPress AI plugin is available, add an opt-in compatibility test against
   the real ability.
2. Verify content-only input, numeric post-ID context, content overriding post
   content, and free-form context. Confirm the exact output remains an array
   with a `title` key and can feed a downstream step.
3. Verify users lacking `edit_posts`/`edit_post` cannot run it and that changing
   capabilities after dispatch but before cron execution is honored.
4. Verify all documented errors remain `WP_Error` codes in step-run persistence
   and the admin UI, with sensitive provider details redacted.
5. Manually test with the WordPress AI experiment enabled and a connected text
   provider, including Editorial Guidelines (`site` and `copy`). Guidelines are
   applied by the ability and must not be duplicated by the scheduler.

### Phase 4 — harden provider and runtime behavior

1. Document the normalized provider interface and add one contract suite per
   retained provider. Remove unverified discovery candidates.
2. Define portable invocation options or reject unsupported options.
3. Add request-local catalog caching and tests for registry changes between
   separate cron invocations.
4. Classify errors as permanent or transient and test retry/backoff,
   continuation scheduling failures, cancellation, duplicate cron delivery,
   and crash recovery.
5. Add concurrency protection so two workers cannot execute the same pending
   step simultaneously. Verify state transitions with atomic repository
   updates.

### Phase 5 — release controls

1. Keep Ability Workflows behind its feature flag until Phases 1–4 pass.
2. Run focused PHP syntax and JavaScript syntax checks on every touched file,
   then the workflow unit suite and WordPress integration suite from the plugin
   root.
3. Perform manual tests with WordPress AI enabled and disabled, the title
   experiment enabled and disabled, no provider configured, and at least two
   user roles.
4. Update the feature reference, hooks, migrations, privacy/security notes, and
   changelog with the supported WordPress/WordPress AI versions and the chosen
   execution-principal policy.
5. Enable by default only after destructive gating, authorization restoration,
   idempotency/concurrency protection, and the title-generation compatibility
   matrix are verified.

## Acceptance criteria

- `ai/title-generation` is listed with its registered category and schemas.
- A queued title workflow succeeds for an authorized initiating user and fails
  after that authorization is revoked.
- Post-ID context enforces `edit_post`, missing posts return `post_not_found`,
  and content-less requests return `content_not_provided`.
- The output remains `{ "title": "..." }` and is usable by downstream variable
  references without provider-specific extraction.
- WordPress abilities annotated destructive cannot run unless the workflow and
  current authorization policy explicitly allow them.
- Permission/input errors are not retried; transient provider errors follow the
  configured retry policy.
- Duplicate cron delivery cannot execute a step more than once concurrently.
- No provider path bypasses `WP_Ability::execute()` validation or permission
  checks.

## Implementation status

The initial hardening implementation now:

- reads WordPress categories from `get_category()` and normalizes behavioral
  annotations from `meta.annotations`;
- enforces the normalized destructive annotation in the existing workflow
  safety gate;
- restores and re-authorizes the immutable run principal before cron execution,
  with an explicit filter for a configured system-run service user;
- preserves structured error codes in step-run records and prevents retries for
  known authorization/input failures;
- uses an expiring, ownership-token run lock to prevent concurrent duplicate
  cron execution;
- uses direct WordPress registry lookup for invocation, rejects unsupported
  WordPress invocation options, and caches catalog discovery for one request;
  and
- includes focused adapter/catalog, Title Generation contract, principal, and
  run-lock regression coverage.

Manual compatibility testing against a live WordPress AI provider and the
release matrix remain deployment checks because they require a configured
WordPress installation, credentials, and provider connectivity.
