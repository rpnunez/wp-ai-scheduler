# Bulk Generation for Non-Post Objects — Implementation Spec

**Status:** Draft for review — no implementation started
**Roadmap ref:** P2-1 (competitive positioning brief §4)
**Date:** 2026-08-25

---

## 1. What this is

Extend AI generation beyond `wp_posts` to the other WordPress objects that carry
editorial text and have no generation story today:

| Object | What gets generated | WP storage |
|---|---|---|
| Taxonomy terms | Description, plus mapped term meta | `term_taxonomy.description`, `termmeta` |
| Author archives | Biography, plus mapped user meta | `usermeta.description`, `usermeta` |
| CPT archive pages | Archive intro/description | Option or CPT-archive meta (see §6, open question) |
| Templates | Default field values seeded at template level | `aips_templates` |

Each runs as a queued bulk job with a review step, the same as post generation.

### Why this one, and why now

Three reasons it earns priority over higher-defensibility roadmap items:

1. **It doesn't depend on the ledger.** P2-2/P2-3/P2-4/P2-6 all block on provenance
   and outcome-signal substrate that has no PR yet. This one blocks on nothing.
2. **No competitor has it.** The surveyed set is post-centric by data model. Yoast
   ships the bulk *editing* UI for terms but generates nothing; the AI plugins
   generate posts only.
3. **It is the most demoable item on the roadmap.** "Generate descriptions for all
   142 of your empty category terms" is a single screenshot.

Paired with #1991 (SEO write-through), it gives a coherent launch story: *write-through
to every major SEO plugin, and generate for every object that has a title.*

---

## 2. Verification pass — what already exists

Confirmed against `main` at `b87553a`. **This is the part that makes the estimate credible:**
most of the machinery exists and is already object-agnostic.

### 2a. The bulk queue is already object-agnostic ✅

`AIPS_Bulk_Batch_Processor` (`includes/class-aips-bulk-batch-processor.php`) is a
strategy registry over a cron-sliced queue:

- `register(string $job_type, callable $handler)` — `class-aips-bulk-batch-processor.php:132`
- Handler contract `fn($item, $job_id, $job): int|WP_Error` — `:119-130`
- `process()` slices `$job->items` and dispatches per item — `:164`, `:273-275`

`AIPS_Bulk_Batch_Job_Store` persists `job_type` + `items_json` + `options_json`
(`class-aips-bulk-batch-job-store.php:105-122`). **Nothing in the storage layer is
post-specific** — items are arbitrary JSON, and the job type is just a string key.

Existing registrations in `boot_cron()` (`ai-post-scheduler.php:683`, `:690`, `:721`):
`author_topic_post`, `planner_post`, `trending_topic_post`.

**The only post-centricity is cosmetic**, at `class-aips-bulk-batch-processor.php:288-296`:

```php
$post_result = is_array( $result ) ? $result : (int) $result;
$history->record(
    'activity',
    sprintf( __( 'Post %s generated successfully', ... ), ... ),
    null, null,
    array( 'item' => $item, 'post_id' => $post_result )
);
```

A hardcoded `"Post %s generated successfully"` string and a `post_id` meta key. The
return value itself is handled loosely enough that a `term_id` or `user_id` already
flows through without error — it would just be mislabelled in history. **Slice 1 fixes
exactly this and nothing else.**

### 2b. A meta-field generation engine already exists ✅

#1914 landed the Integrations framework with `AIPS_Integration_Interface`
(`includes/interface-aips-integration-interface.php`):

```php
public function get_field_groups($post_type = null);   // :65
public function get_fields($group_id, $args = array()); // :84
public function write_field_value($post_id, $field_key, $value); // :102
public function validate_field_key($field_key);         // :123
```

With `AIPS_Integration_Native_Meta` and `AIPS_Integration_ACF` implementations, an
`AIPS_Integration_Field_Prompt_Builder`, and — importantly — a reserved-key denylist
that refuses to overwrite `_wp_*`, `_edit_*`, `_thumbnail_id`, `_aips*`
(`class-aips-integration-native-meta.php:188`).

**This is the "meta-adapter layer" the brief predicted, already built — but scoped to
post IDs.** The generalization needed is mechanical: `$post_id` → `(object_type, object_id)`.

### 2c. Taxonomy generation exists but solves a *different* problem ⚠️

`AIPS_Taxonomy_Controller` (683 lines) already generates taxonomy items with a full
approve/reject/delete queue and `wp_insert_term` on approval
(`class-aips-taxonomy-controller.php:286-301`).

**But it generates new term _names_ suggested from a set of posts** — see
`generate_taxonomy_items()` at `:178-243`, which builds a prompt from post titles and
excerpts, splits the response by line, and inserts each line as a `name`. The
`aips_taxonomy` table stores `name`, `taxonomy_type`, `status`, `base_post_ids`.

It does **not**:
- generate descriptions or meta for *existing* terms
- run through the bulk batch queue (it is synchronous AJAX)
- cover users, CPT archives, or templates

So P2-1 is genuinely net-new, but it inherits a proven review-queue pattern and can
reuse `AIPS_Prompt_Builder_Taxonomy`.

### 2d. Reusable review-queue precedent ✅

The `pending → approve/reject → commit` shape, with bulk variants, is established in
both the taxonomy and author-topics controllers. Ten registry entries for taxonomy
alone (`class-aips-ajax-registry.php:202-211`). The new work should follow this
shape rather than inventing one.

### Summary of the gap

| Needed | Status |
|---|---|
| Queued, sliced, resumable bulk jobs | **Exists**, object-agnostic |
| AI field generation + write with safety denylist | **Exists**, post-scoped only |
| Review/approve queue pattern | **Exists** (taxonomy, author topics) |
| Prompt builders for terms | **Exists** (`AIPS_Prompt_Builder_Taxonomy`) |
| Object-type abstraction over term/user/CPT/template | **Missing** — this is the work |
| Target selection UI (which terms? which are empty?) | **Missing** |
| Per-object-type prompt configuration | **Missing** |

---

## 3. Outcomes and acceptance criteria

### User-visible outcomes

1. An admin can select a taxonomy, filter to terms with empty descriptions, and queue
   description generation for all of them.
2. Generated values land in a review queue, not directly on the live object.
3. Approve (single or bulk) writes to the term/user; reject discards.
4. The same flow works for author biographies.
5. A job survives a page close — it is cron-sliced and resumable, with visible progress.

### Technical acceptance criteria

- **AC1** — Bulk job history correctly labels non-post results (no "Post 42 generated"
  for a term).
- **AC2** — Generation never writes to a reserved meta key, for any object type — the
  `AIPS_Integration_Native_Meta` denylist applies to term and user meta too.
- **AC3** — All SQL lives in repositories; controllers hold none.
- **AC4** — Every new `wp_ajax_*` action is registered in `AIPS_Ajax_Registry::$map`
  with nonce + capability checks.
- **AC5** — Capability checks are object-appropriate: `manage_categories` for terms,
  `edit_users` for user bios — **not** a blanket `manage_options`.
- **AC6** — A job for 500 terms completes without timeout, in cron slices.
- **AC7** — Re-running a job over already-generated targets does not silently
  double-write; targets are skipped or explicitly re-queued.

---

## 4. Architecture

### The one decision that matters: object identity

Everything else follows from how a generation target is addressed. Today it's an
`int $post_id` threaded through the integration layer.

**Proposal — a value object:**

```php
final class AIPS_Object_Ref {
    public function __construct(
        private string $type,   // 'post' | 'term' | 'user' | 'cpt_archive' | 'template'
        private int    $id,
        private string $subtype = ''  // 'category', 'post_tag', post type name, ...
    ) {}

    public function get_type(): string;
    public function get_id(): int;
    public function get_subtype(): string;
    public function to_key(): string;   // "term:42:category" — stable, JSON-safe
    public static function from_key(string $key): self;
}
```

`to_key()`/`from_key()` matter because job items must be JSON-serialisable for
`items_json` — `AIPS_Bulk_Batch_Job_Store::create()` encodes items directly
(`class-aips-bulk-batch-job-store.php:119`).

### Object-type adapters

One adapter per object type, resolved from a registry:

```php
interface AIPS_Generation_Object_Adapter_Interface {
    public function get_type(): string;
    public function get_label(): string;
    public function get_generatable_fields(): array;      // 'description' => label, ...
    public function read_context(AIPS_Object_Ref $ref): array;  // name, existing text, related posts
    public function write_field(AIPS_Object_Ref $ref, string $field, $value);
    public function can_edit(AIPS_Object_Ref $ref): bool;  // capability check
    public function query_targets(array $args): array;     // filtering + "empty only"
}
```

Implementations: `..._Term`, `..._User`, `..._CPT_Archive`, `..._Template`.

**`read_context()` is where quality comes from.** A term description generated from the
term name alone will be generic. Generated from the term name *plus* the titles of the
posts in that term, it is specific. That context assembly is the adapter's job, and it
should reuse `AIPS_Content_Digest` from #1905 for bounded summarization — which is a
reason to land #1905 first.

### Relationship to the existing Integration layer

**Do not fork it.** `AIPS_Integration_Interface::write_field_value($post_id, ...)`
should widen to accept an `AIPS_Object_Ref`, with a back-compat shim accepting a bare
int as `post:{id}`. The reserved-key denylist is the single most valuable thing in that
class and must apply to term and user meta unchanged.

This is the main cross-cutting edit and the main review risk — it touches #1914 code
that just landed. Worth a focused review pass on its own (Slice 3).

> **There will be two post-scoped interfaces to widen, not one.** #1991 (open) adds
> `AIPS_SEO_Provider_Interface` with `write_post_seo($post_id, ...)` /
> `read_post_seo()` / `delete_post_seo()`, in parallel with #1914's
> `AIPS_Integration_Interface::write_field_value($post_id, ...)`. Both bake a bare post
> ID into their contract, and both need the same generalization for Slice 7 (Q1).
>
> Since #1991 is **unmerged**, this is much cheaper to address there than here: taking
> an `AIPS_Object_Ref` (or at minimum an `(type, id)` pair) in its interface now costs
> a signature change on an unreviewed branch, versus a breaking change to a shipped
> public contract later. Worth raising on #1991 before it merges — flagged as a
> suggestion, not a merge blocker, since its post-scoped behavior is correct for
> everything it does today.

---

## 5. Slices

Following the house dependency chain (schema → service → AJAX → UI → polish). Each is
independently mergeable and independently valuable.

### Slice 0 — Queue de-post-ification
**Size:** XS · **Risk:** very low · **Depends on:** nothing

Fix `class-aips-bulk-batch-processor.php:288-296` so history records object type and a
generic label instead of hardcoded `post_id` / `"Post %s generated"`. Add an optional
`result_label` to job options, defaulting to current behavior.

**Merge gate:** existing bulk-batch tests pass unchanged; new test asserting a `term`
job records `object_type: term`.

*Ship this first regardless of the rest — it's a latent correctness wart today.*

### Slice 1 — `AIPS_Object_Ref` + adapter registry + term adapter
**Size:** M · **Risk:** low · **Depends on:** Slice 0

Value object, adapter interface, container-registered registry, and the term adapter
with `read_context()` (term name + post titles in term, digested via #1905) and
`query_targets()` supporting "description is empty".

No UI, no AJAX. Pure unit-testable core.

**Merge gate:** unit tests for `to_key`/`from_key` round-trip incl. malformed input;
term adapter target query against a seeded fixture; capability check returns false for
a subscriber.

### Slice 2 — `bulk_object_generation` job type + generation service
**Size:** M · **Risk:** medium · **Depends on:** Slice 1

Register the strategy in `boot_cron()` alongside the existing three. Service resolves
adapter → builds context → prompt builder → AI → **writes to a pending-review store,
not the live object**.

New table `aips_object_generation_queue` (`object_type`, `object_id`, `object_subtype`,
`field_key`, `generated_value`, `status`, `job_id`, `created_at`) via
`AIPS_DB_Manager::get_schema()` + a repository class. Bump `AIPS_VERSION`.

**Merge gate:** `db-migration-reviewer` pass; end-to-end test queueing 3 terms and
asserting 3 pending rows and zero live writes.

### Slice 3 — Widen the Integration layer to `AIPS_Object_Ref`
**Size:** M · **Risk:** medium-high · **Depends on:** Slice 1

`write_field_value()` accepts `AIPS_Object_Ref` with an int back-compat shim. Reserved-key
denylist applies to `termmeta`/`usermeta`. Field discovery gains term/user scope.

**Merge gate:** every existing integration test passes unmodified via the shim; new
tests asserting `_aips_foo` and `_wp_bar` are refused on term and user meta. **Wants a
dedicated review pass — this is the riskiest slice.**

### Slice 4 — AJAX controller
**Size:** S · **Risk:** low · **Depends on:** Slice 2

`AIPS_Object_Generation_Controller`, registered in `AIPS_Ajax_Registry::$map`:
`aips_query_generation_targets`, `aips_queue_object_generation`,
`aips_get_object_generation_queue`, `aips_approve_object_generation`,
`aips_reject_object_generation`, plus bulk approve/reject.

Capability checks delegate to `$adapter->can_edit()` (AC5) — **not** blanket
`manage_options`.

**Merge gate:** `ajax-controller-reviewer` pass; nonce + capability negative tests per action.

### Slice 5 — Admin UI
**Size:** L · **Risk:** medium · **Depends on:** Slice 4

Target picker (object type → subtype → filter, "only empty" default), queue-with-progress,
and a review table with inline diff of current vs generated. `AIPS.Templates` for all
HTML, `AIPS.Utilities.confirm/showToast`, no `location.reload()`.

**Includes the Q4 cost acknowledgment:** resolved target count always shown before
queueing; above a configurable threshold (default 50) an explicit acknowledgment is
required, displaying estimated API call count with a layout slot reserved for estimated
cost once P0-3 lands. Warn-and-proceed, never block.

**Merge gate:** `admin-ui-changes` skill; browser verification (`needs-browser-test`);
test asserting a job above the threshold cannot be queued without acknowledgment, and one
below it queues without an extra click.

> **Coordination:** overlaps the Content Hub redesign in #1971. Land #1971 first, or
> build this as a standalone page and fold it in later. Do not build against the
> panels #1971 removes.

### Slice 6 — User/author adapter
**Size:** S · **Risk:** low · **Depends on:** Slices 1–5

Second adapter proves the abstraction. Should require **zero** changes to slices 0–5 —
if it doesn't, the abstraction is wrong and that's the signal to fix it before adding more.

### Slice 7 — CPT archive + template adapters
**Size:** M · **Risk:** medium · **Depends on:** Slice 6 **and #1991 merged**

Per Q1, CPT archive descriptions route through #1991's SEO provider abstraction. This is
the only slice that depends on #1991, and it is last — so #1991 does **not** need to merge
to start this work. It needs to merge before this slice, on its own timeline and merits.

Also carries the second-interface widening described in §4.

**Recommended first milestone: Slices 0–6.** That is a complete, demoable, shippable
feature covering the two highest-value object types. Slice 7 can follow.

---

## 6. Questions

### Q1 — Where does a CPT archive description live? — **RESOLVED**

**Decision: route through #1991's SEO provider abstraction.** It's where users would
look for it, and #1991 already owns that write-through surface for Yoast/Rank Math/Native.

**Consequence for Slice 7:** it gains a hard dependency on #1991 being merged. Slices 0–6
remain unaffected.

**Caveat found while confirming this** (see §4 note): #1991's
`AIPS_SEO_Provider_Interface` is post-scoped — `write_post_seo($post_id, ...)`,
`read_post_seo()`, `delete_post_seo()`. A CPT *archive* is not a post and has no ID to
pass. So this decision does not hand Slice 7 a ready-made surface; it requires the same
object-ref widening that Slice 3 applies to the Integration layer. That's fine and still
the right destination — but it means Slice 7 is "widen a second interface", not "call an
existing method".

### Q2 — Dedicated review queue, or reuse the editorial queue? — open

Spec assumes a dedicated table (Slice 2). If #1971's Content Hub becomes the universal
review surface, this could be a tab there instead. Worth deciding alongside #1971. Does
not block Slices 0–4.

### Q3 — Term hierarchy — open

Should a child term's context include its parent's description? Probably yes for quality;
adds a recursion bound to `read_context()`. Cheap to add in Slice 1, expensive to retrofit.

### Q4 — Cost visibility — **RESOLVED**

**Decision: warn, then let the user proceed on explicit acknowledgment. Do not block.**

The plugin exists to let users do exactly this, so the ceiling is informational, not a
hard cap. Requirements for Slice 5:

- Always show the resolved target count before queueing ("This will generate descriptions
  for 512 terms").
- Above a configurable threshold (default 50), require an explicit acknowledgment —
  a checkbox or typed confirmation — showing estimated API call count.
- Once P0-3 (token/cost attribution) lands, add estimated cost to the same dialog. The
  dialog is designed with a slot for it now so nothing needs restructuring later.
- Once P0-4 (budget caps) lands, the acknowledgment becomes the override path for a
  soft cap rather than a standalone gate.

**On the global install-time acknowledgment idea** *(raised: a checkbox at install that
gates all plugin use until enabled)* — recommend **against** gating plugin access on it,
for three reasons:

1. It works against P1-4, where time-to-first-post without friction is classified a
   table stake. A blocking interstitial before the plugin is even viewable is friction
   applied at exactly the wrong moment — before the user has seen any value.
2. It provides little real protection. A one-time click at install is ~forgotten by the
   time someone queues a 500-term job three weeks later. Consent given far from the risk
   is weak consent.
3. It reads as a liability disclaimer rather than a safety feature, which is not the
   impression the onboarding path should leave.

**Recommended instead:** a non-blocking cost notice during onboarding (dismissible,
recorded), plus the per-run acknowledgment above scaled to job size. That puts the
friction at the moment of actual risk and in proportion to it, which is both safer and
less costly to adoption. If a global acknowledgment is still wanted for legal comfort,
suggest recording it during onboarding without gating plugin visibility on it.

### Q5 — Idempotency (AC7) — open

Does a target with an existing pending row get skipped, replaced, or queued twice?
Proposal: skip by default, with an explicit "regenerate" that supersedes the pending row.

---

## 7. Overlap check

Per the feature-slicing guardrail, checked against all open PRs:

| PR | Relationship |
|---|---|
| #1991 SEO write-through | **Complementary, and a Slice 7 dependency** per Q1. Owns SEO fields on posts and attachments; this owns non-post objects. No file conflict — #1991 is +3,782/−3 across 25 files, none of which this spec touches. See §4 on widening its interface before it merges. |
| #1914 (merged) Integrations/meta | **Direct dependency.** Slice 3 widens its interface. |
| #1905 Content digest | **Should land first.** `read_context()` wants bounded digests. |
| #1971 Content Hub | **UI coordination.** See Slice 5. |
| #1973 Generation pipeline | **Watch.** If the pipeline lands, Slice 2's service may fit better as a stage. Doesn't block. |
| #1719 Existing-post scan | Adjacent (both operate on existing objects), no code overlap — that one is post-only. |

**No open PR owns any slice here.** Nearest neighbor is `AIPS_Taxonomy_Controller` on
`main`, which solves a different problem (§2c) and is left untouched.

---

## 8. What needs to merge before this starts

Short answer: **nothing, to start.** Slice 0 depends on no open PR.

| PR | Needed by | When |
|---|---|---|
| #1914 Integrations/meta | Slice 3 | **Already merged** ✅ |
| #1905 Content digest | Slice 1 (`read_context()` bounded summarization) | Before Slice 1 — the one worth merging soon |
| #1971 Content Hub | Slice 5 (UI placement) | Before Slice 5, or build standalone |
| #1991 SEO abstraction | Slice 7 only | Before Slice 7 — last slice, no rush |

**#1905 is the only near-term merge that unblocks anything**, and even it can be
deferred: Slice 1 could ship with naive truncation in `read_context()` and swap in the
digest later, at the cost of a follow-up edit.

## 9. Recommended next step

Review the §4 object-identity decision, then start **Slice 0** — small, independently
correct, and unblocks the rest. Q1 and Q4 are resolved; Q2/Q3/Q5 do not block Slices 0–4.
