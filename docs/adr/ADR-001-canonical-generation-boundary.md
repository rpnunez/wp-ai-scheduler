---
title: "ADR-001: Canonical Generation Boundary and Legacy Adapter Strategy"
status: "Accepted"
date: "2026-03-28"
authors: "Core Plugin Team"
tags: ["architecture", "decision", "generation", "adapter", "refactoring"]
supersedes: ""
superseded_by: ""
---

# ADR-001: Canonical Generation Boundary and Legacy Adapter Strategy

## Status

**Accepted**

---

## Context

The `AIPS_Generator` class is the heart of the wp-ai-scheduler plugin's content generation pipeline. Over time, as new generation flows were added (author/topic-driven generation, scheduled generation, MCP bridge generation), the generator's public API accumulated inline branching to accommodate both legacy raw-template callers and newer context-aware callers.

A canonical input contract — the `AIPS_Generation_Context` interface — was introduced to unify these flows. However, the boundary between "legacy input territory" and "canonical generation territory" was never formally declared. As a result:

- `AIPS_Generator::generate_post()` contains an `instanceof` type-check that silently converts raw stdClass template objects into `AIPS_Template_Context` adapters, hiding the boundary inside the generator itself.
- The adapter instantiation responsibility (`new AIPS_Template_Context(...)`) is scattered — some callers create the context, others delegate that responsibility to the generator.
- `AIPS_Generator::generate_title()` exposes a legacy public signature that accepts raw template objects directly, bypassing the context contract entirely.
- Three active callsites still pass raw stdClass objects to the generator, meaning they are unaware of (and not enforcing) the canonical contract.
- The private method `generate_post_from_context()` is the true canonical implementation, but it is invisible from the outside, making the architecture unclear to contributors.

This ambiguity increases complexity, widens the test surface, and raises change risk: any modification to generation logic must account for both code paths, and the path a given call takes is determined invisibly inside the generator.

The canonical input model (`AIPS_Generation_Context`) and its first adapter (`AIPS_Template_Context`) already exist. This ADR formally declares where the canonical boundary sits, who owns adapter instantiation, and what must happen to make the codebase consistent with this boundary.

---

## Decision

### 1. `AIPS_Generation_Context` Is the Canonical Generation Input

`AIPS_Generation_Context` (defined in `ai-post-scheduler/includes/interface-aips-generation-context.php`) is the **single, authoritative contract** for all input to the generation pipeline. No generation logic inside `AIPS_Generator` shall branch on input type or accept any type other than this interface.

The private method `AIPS_Generator::generate_post_from_context(AIPS_Generation_Context $context)` is the **canonical generation entry point**. All public entry points on `AIPS_Generator` must resolve to a concrete `AIPS_Generation_Context` instance and delegate to this method.

### 2. `AIPS_Template_Context` Is the Sole Authorized Legacy Adapter

`AIPS_Template_Context` (defined in `ai-post-scheduler/includes/class-aips-template-context.php`) is the **only class authorized** to accept a raw stdClass template database row and translate it into a `AIPS_Generation_Context`. No other class — including `AIPS_Generator` — may perform this translation directly.

`AIPS_Template_Context` is explicitly a **legacy adapter**, not a first-class context type. Its role is to bridge the gap between the existing template-row data model and the canonical context interface. It will remain until all template-aware callsites are refactored to construct contexts from structured data rather than raw stdClass rows.

### 3. Adapter Instantiation Is the Caller's Responsibility

The caller that holds a raw stdClass template object is responsible for wrapping it in `AIPS_Template_Context` before invoking the generator. The generator must not instantiate adapters internally.

This makes the boundary explicit: the moment a caller constructs an `AIPS_Template_Context`, it is crossing from legacy territory into canonical territory.

### 4. `AIPS_Generator::generate_post()` Transitional Posture

The public method `AIPS_Generator::generate_post($template_or_context, $voice = null, $topic = null)` shall be retained for backward compatibility. However, the inline `new AIPS_Template_Context(...)` fallback inside the generator body is a **boundary violation** under this ADR and must be removed once all callsites have been migrated to pass a context object.

During the migration period, this inline fallback is tolerated but must be marked with a deprecation notice in code comments.

### 5. `AIPS_Generator::generate_title()` Transitional Posture

The legacy public method `AIPS_Generator::generate_title($template, $voice, $topic, ...)` contains a similar inline adapter instantiation. This method shall eventually be replaced by a context-accepting signature. During the migration period, it is tolerated but marked deprecated.

---

## Canonical Input Model

The `AIPS_Generation_Context` interface defines the following method contract:

| Method | Returns | Purpose |
|---|---|---|
| `get_type()` | `string` | Context type discriminator (`'template'`, `'topic'`, etc.) |
| `get_id()` | `int|string|null` | Context identifier (template ID, topic ID, etc.) |
| `get_name()` | `string` | Human-readable display label |
| `get_content_prompt()` | `string` | AI prompt for post body content |
| `get_title_prompt()` | `string` | AI prompt for post title |
| `get_image_prompt()` | `string|null` | AI prompt for featured image generation |
| `should_generate_featured_image()` | `bool` | Whether to generate a featured image |
| `get_featured_image_source()` | `string` | Image source strategy (`'ai'`, `'unsplash'`, `'media_library'`, etc.) |
| `get_unsplash_keywords()` | `string` | Keywords for Unsplash image search |
| `get_media_library_ids()` | `string` | Comma-separated WordPress media library attachment IDs |
| `get_post_status()` | `string` | WordPress post status on creation |
| `get_post_category()` | `int|string` | WordPress category ID or comma-separated IDs |
| `get_post_tags()` | `string` | Comma-separated WordPress tags |
| `get_post_author()` | `int` | WordPress author user ID |
| `get_article_structure_id()` | `int|null` | Optional article structure override |
| `get_voice_id()` | `int|null` | Optional voice profile ID |
| `get_voice()` | `object|null` | Optional voice profile object |
| `get_topic()` | `string|null` | Optional topic string for dynamic prompts |
| `get_creation_method()` | `string` | Origin of the generation request (`'manual'`, `'scheduled'`) |
| `get_include_sources()` | `bool` | Whether to inject source references into prompts |
| `get_source_group_ids()` | `array` | Source group IDs for content injection |
| `to_array()` | `array` | Serialization of the context for logging/storage |

---

## Adapter Contract and Ownership

### Adapter: `AIPS_Template_Context`

`AIPS_Template_Context` implements `AIPS_Generation_Context` and accepts:

- A raw `stdClass` template database row (required)
- An optional voice profile object
- An optional topic string
- An optional creation method string

It is the **translation layer** between the existing template data model and the canonical interface. It must faithfully expose all interface methods, deriving values from template row fields.

**Who may instantiate `AIPS_Template_Context`:**

- Any caller that holds a raw template stdClass object and needs to pass it to the generator
- Specifically: `AIPS_Planner`, `mcp-bridge.php` (template/schedule paths), `AIPS_Schedule_Processor`
- **Not** `AIPS_Generator` itself (boundary violation)

### Context: `AIPS_Topic_Context`

`AIPS_Topic_Context` implements `AIPS_Generation_Context` natively, without wrapping a legacy data structure. It represents the author/topic-driven generation flow and is already fully compliant with this ADR. It serves as the reference implementation for any future native context types.

---

## Legacy Entrypoints Inventory

The following is the complete inventory of callsites that invoke generation logic, classified by their compliance status at the time this ADR was written.

### Non-Compliant Callsites (Must Be Migrated)

#### NC-001 — `AIPS_Planner::ajax_generate_posts`

- **File**: `ai-post-scheduler/includes/class-aips-planner.php`, line 215
- **Violation**: Passes a raw `stdClass` template object directly to `$generator->generate_post($template, null, $topic)`
- **Evidence**: Source comment reads "Using legacy signature which generates a context inside `AIPS_Generator`"
- **Required Fix**: Wrap template in `new AIPS_Template_Context($template, null, $topic)` at the callsite before invoking the generator

#### NC-002 — `mcp-bridge.php` (template/schedule path)

- **File**: `ai-post-scheduler/mcp-bridge.php`, lines 924–931
- **Violation**: When `template_id` or `schedule_id` parameters are provided (as opposed to `author_topic_id`), passes a raw template stdClass object to `$generator->generate_post($template, $voice, $topic)`
- **Evidence**: Source comment reads `// TODO: Apply overrides to context if needed`
- **Required Fix**: Wrap template in `new AIPS_Template_Context($template, $voice, $topic)` at the callsite; apply any override logic to the context object rather than to the raw template

#### NC-003 — `AIPS_Generator::generate_title()` (internal legacy path)

- **File**: `ai-post-scheduler/includes/class-aips-generator.php`, line 329
- **Violation**: The public method `generate_title($template, $voice, $topic, $content, $options, $ai_variables)` accepts a raw template stdClass and instantiates `new AIPS_Template_Context(...)` internally — boundary violation mirroring NC-001/NC-002
- **Required Fix**: Either (a) deprecate `generate_title()` in favour of a context-accepting method, or (b) remove the internal adapter instantiation and require callers to pass a context

### Partially Compliant Callsites

#### PC-001 — `AIPS_Schedule_Processor::process_schedule()`

- **File**: `ai-post-scheduler/includes/class-aips-schedule-processor.php`, lines 300–323
- **Status**: Caller does construct `AIPS_Template_Context` before calling the generator (correct boundary ownership). However, it constructs a synthetic stdClass template internally from schedule data, rather than working directly with structured data.
- **Improvement Path**: Refactor to build context data directly from schedule fields without constructing an intermediate synthetic template object

### Fully Compliant Callsites

#### FC-001 — `AIPS_Author_Post_Generator`

- **File**: `ai-post-scheduler/includes/class-aips-author-post-generator.php`
- **Status**: Fully migrated. Uses `AIPS_Topic_Context` natively; no raw templates involved.

---

## Consequences

### Positive

- **POS-001**: Establishes a single, declared architectural boundary for generation input. Contributors can reason about the generation pipeline without tracing `instanceof` branches to understand which code path executes.
- **POS-002**: Eliminates dual-path complexity inside `AIPS_Generator`. Once callsites are migrated, `generate_post_from_context()` is the only execution path, reducing test surface.
- **POS-003**: Makes adapter responsibility explicit. Callers that wrap templates in `AIPS_Template_Context` are visibly crossing a boundary, making the legacy/canonical distinction readable in code.
- **POS-004**: Enables exhaustive unit testing of `generate_post_from_context()` via mock `AIPS_Generation_Context` implementations, without needing to construct or understand template stdClass structures.
- **POS-005**: Provides a clear migration path: the number of non-compliant callsites is finite, enumerated, and individually addressable without coordinated refactoring.

### Negative

- **NEG-001**: Three callsites require migration work before the generator's inline fallback can be removed. The codebase will remain in a partially compliant state until that work is completed.
- **NEG-002**: `AIPS_Template_Context` as a legacy adapter is a permanent fixture until the template row data model itself is replaced or abstracted. Its presence means two levels of indirection exist in the template-driven path.
- **NEG-003**: The transitional `generate_post()` method signature (`$template_or_context`) is ambiguous and misleading to new contributors who may not read this ADR. The deprecation notice in code mitigates but does not eliminate this risk.
- **NEG-004**: `AIPS_Schedule_Processor` constructs a synthetic stdClass as an intermediate step, which is a code smell even though it ultimately passes a context to the generator. Full remediation requires a deeper refactor of schedule-to-context mapping.

---

## Alternatives Considered

### Alternative A: Leave Inline Branching in Place

- **ALT-001**: **Description**: Accept the status quo — `AIPS_Generator::generate_post()` continues to handle both raw templates and context objects via `instanceof` branching. Document this as intentional.
- **ALT-002**: **Rejection Reason**: Does not resolve the scattered adapter responsibility problem. As new generation flows are added, the branching logic grows, increasing long-term complexity. Makes it impossible to test the generation boundary in isolation.

### Alternative B: Eliminate `AIPS_Template_Context`; Migrate All Callers to Native Contexts

- **ALT-003**: **Description**: Remove `AIPS_Template_Context` entirely. Require all template-driven callers to construct a native context object directly from template fields, without an adapter wrapper.
- **ALT-004**: **Rejection Reason**: Requires simultaneous migration of all legacy callsites — a coordinated, high-risk change. The template row data model is shared across many callsites and is not trivially replaced. The adapter pattern is the correct incremental approach; the adapter can be removed once the data model is unified.

### Alternative C: Expose `generate_post_from_context()` as the Public API Immediately

- **ALT-005**: **Description**: Rename `generate_post_from_context()` to `generate_post()`, require it to accept only `AIPS_Generation_Context`, and immediately break all non-compliant callsites.
- **ALT-006**: **Rejection Reason**: Would require all three non-compliant callsites (`AIPS_Planner`, `mcp-bridge.php`, `generate_title()`) to be fixed in a single coordinated commit. While the cleanest end state, this approach creates a high-pressure coordinated change that risks introducing regressions. The transitional posture described in this ADR achieves the same architectural outcome incrementally.

### Alternative D: Generator Factory Pattern

- **ALT-007**: **Description**: Introduce a `AIPS_Generator_Factory` class that accepts raw template objects and returns a pre-configured generator or context object. Callers use the factory instead of constructing contexts directly.
- **ALT-008**: **Rejection Reason**: Adds an additional abstraction layer without meaningful benefit over `AIPS_Template_Context`. The adapter already serves this purpose. A factory would also relocate, not eliminate, the boundary ambiguity.

---

## Implementation Notes

- **IMP-001**: The inline fallback in `AIPS_Generator::generate_post()` must be annotated with a `@deprecated` PHPDoc tag and an inline comment referencing this ADR (`// ADR-001: boundary violation — migrate caller to pass AIPS_Generation_Context`). This makes the violation visible during code review.
- **IMP-002**: `AIPS_Planner::ajax_generate_posts` (NC-001) is the lowest-risk migration: it already has access to the template object and topic string needed to construct `AIPS_Template_Context`. This should be the first migration completed.
- **IMP-003**: `mcp-bridge.php` (NC-002) migration should include applying any template override logic to the context object immediately after construction, resolving the existing `// TODO` comment in that file.
- **IMP-004**: `generate_title()` (NC-003) migration is a breaking API change if any external code calls this method. Check for external callers before removing the legacy signature. A new `generate_title_from_context(AIPS_Generation_Context $context, ...)` method should be introduced alongside the legacy method before the legacy method is removed.
- **IMP-005**: Success criterion for full compliance: `AIPS_Generator` contains zero instantiations of `AIPS_Template_Context` and zero `instanceof AIPS_Generation_Context` checks. At that point, `generate_post()` becomes a thin public alias for `generate_post_from_context()`.
- **IMP-006**: After NC-001, NC-002, and NC-003 are resolved, `generate_post_from_context()` should be elevated from `private` to `protected` (or remain `private` with a public `generate_post(AIPS_Generation_Context $context)` alias) to enable subclass-based testing without reflection.

---

## Follow-on Implementation Issues

The following discrete work items follow from this ADR and should be tracked as individual issues or pull requests:

| ID | Description | Callsite | Effort |
|---|---|---|---|
| FOL-001 | Migrate `AIPS_Planner::ajax_generate_posts` to construct `AIPS_Template_Context` at callsite | NC-001 | Low |
| FOL-002 | Migrate `mcp-bridge.php` template/schedule path to construct `AIPS_Template_Context` at callsite; apply overrides to context | NC-002 | Medium |
| FOL-003 | Deprecate `AIPS_Generator::generate_title()` legacy signature; introduce `generate_title_from_context()` | NC-003 | Medium |
| FOL-004 | Remove inline `instanceof AIPS_Generation_Context` branching from `AIPS_Generator::generate_post()` once FOL-001 and FOL-002 are complete | Generator | Low |
| FOL-005 | Remove inline `new AIPS_Template_Context(...)` from `AIPS_Generator::generate_post()` once FOL-001 and FOL-002 are complete | Generator | Low |
| FOL-006 | Refactor `AIPS_Schedule_Processor::process_schedule()` to build context data directly from schedule fields without constructing intermediate synthetic stdClass | PC-001 | High |
| FOL-007 | Add boundary-level integration tests using mock `AIPS_Generation_Context` implementations to verify `generate_post_from_context()` in isolation | Testing | Medium |
| FOL-008 | Once all callsites are compliant, evaluate removing `AIPS_Template_Context` adapter if the template row data model is unified into a structured object | Future | High |

---

## References

- **REF-001**: `AIPS_Generation_Context` interface — `ai-post-scheduler/includes/interface-aips-generation-context.php`
- **REF-002**: `AIPS_Template_Context` adapter — `ai-post-scheduler/includes/class-aips-template-context.php`
- **REF-003**: `AIPS_Topic_Context` (reference native implementation) — `ai-post-scheduler/includes/class-aips-topic-context.php`
- **REF-004**: `AIPS_Generator` (generation entry point) — `ai-post-scheduler/includes/class-aips-generator.php`
- **REF-005**: `AIPS_Planner` (NC-001 callsite) — `ai-post-scheduler/includes/class-aips-planner.php`
- **REF-006**: MCP bridge (NC-002 callsite) — `ai-post-scheduler/mcp-bridge.php`
- **REF-007**: `AIPS_Schedule_Processor` (PC-001 callsite) — `ai-post-scheduler/includes/class-aips-schedule-processor.php`
- **REF-008**: Martin Fowler — *Strangler Fig Application* pattern (incremental migration strategy)
- **REF-009**: Ports and Adapters (Hexagonal Architecture) — the `AIPS_Generation_Context` interface acts as the "port"; `AIPS_Template_Context` is an "adapter" in the classical sense
