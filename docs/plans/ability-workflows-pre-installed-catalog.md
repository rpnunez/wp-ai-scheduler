# Ability Workflows: Feature Analysis & Pre-Installed Workflow Catalog

Analysis of the "Ability Workflows" feature (branch `codex/create-ability-service-adapter`,
PR #1879, "Add Ability Workflows: multi-step automation built on WordPress Abilities") and a
proposed catalog of pre-installed workflows to ship with it.

## 1. What the feature actually is

Ability Workflows lets an admin chain calls to **Abilities** — the WordPress Abilities API's
unit of "a named, invocable, schema-described capability" — into a multi-step automation, with
data passed between steps and branching logic.

Core pieces (all under `ai-post-scheduler/includes/class-aips-ability-workflow-*.php`):

- **`AIPS_Ability_Service`** — a single adapter that discovers whichever ability *provider* is
  active on the site: WP core's Abilities API (`wp_get_abilities`/`wp_invoke_ability`), AI
  Engine's `mwai` abilities, or anything registered through the `aips_ability_provider` filter.
  It does not define abilities itself — it is purely a consumer/adapter.
- **`AIPS_Ability_Catalog_Service`** — normalizes whatever the active provider returns into a
  stable shape (`name`, `label`, `description`, `category`, `input_schema`, `output_schema`,
  `is_destructive`, `is_available`) for the builder UI and the executor.
- **`AIPS_Ability_Workflow`** — a workflow: name, status (`draft`/`active`/`paused`/`archived`),
  `trigger_type` (`manual` is fully wired; `scheduled` exists in the UI/schema but is marked
  "coming soon" — nothing dispatches it yet), and settings (`max_steps`, `max_runtime_seconds`,
  `allow_destructive_abilities`, `log_payloads`).
- **`AIPS_Ability_Workflow_Step`** — one step: `ability_name`, `depends_on` (other step keys),
  `input_map` (destination field → literal or `{{token}}` template), `condition_tree`
  (AND/OR rule tree — the step is skipped if it evaluates false), `output_alias` (name later
  steps use to reference this step's output), `on_success`/`on_failure` strategy
  (`continue`/`stop`/`skip`), and `retry_policy` (`attempts`, `backoff_seconds`).
- **`AIPS_Ability_Workflow_Variable_Resolver`** — resolves `{{trigger.x}}` (the payload that
  kicked the run off) and `{{steps.<output_alias>.output.field}}` (an earlier step's result)
  tokens inside `input_map` values and condition operands.
- **`AIPS_Ability_Workflow_Condition_Evaluator`** — evaluates the AND/OR rule tree
  (`equals`, `not_equals`, `contains`, `greater_than`, `less_than`, `is_empty`, `in`, …) against
  the resolved variable bag. An empty tree always passes.
- **`AIPS_Ability_Workflow_Executor`** — runs a queued run via a dedicated cron single-event
  hook (`aips_run_ability_workflow`), self-rescheduling when it hits its time budget or a
  step's retry backoff, rather than being modeled as a flat `AIPS_Bulk_Batch_Processor` job
  (steps run in dependency order with conditional branching, which a flat batch can't express).
  It persists a `AIPS_Ability_Workflow_Run` + one `AIPS_Ability_Workflow_Step_Run` per step for
  a full audit trail, correlated via `AIPS_Correlation_ID` and `AIPS_History_Service`.
- **`AIPS_Ability_Workflow_Document_Validator`** — save-time structural validation: no
  duplicate `step_key`s, `depends_on`/token references must point at *earlier* steps only,
  condition trees and retry policies must be well-formed, ability must currently be available
  (unless explicitly skipped, e.g. in tests).

Admin surface: a new (feature-flagged, `aips_enable_ability_workflows`) "Ability Workflows"
list page plus a drag-and-drop step builder page, both AJAX-driven per the plugin's usual
`AIPS.Templates`-render pattern, backed by `AIPS_Ability_Workflows_Controller`,
`AIPS_Ability_Workflow_Runs_Controller`, and `AIPS_Ability_Catalog_Controller`.

### Important caveat for "pre-installed" workflows

**AIPS does not currently register any abilities of its own.** It only *consumes* abilities
exposed by whatever provider is active (WP core, AI Engine/`mwai`, or a custom
`aips_ability_provider` filter implementation). That means on a fresh install with no other
ability-providing plugin active, the catalog is empty and no workflow — pre-installed or
otherwise — has anything to invoke.

**Recommendation:** before/alongside shipping pre-installed workflows, register a first-party
`aips/*` ability namespace (via `aips_ability_provider` or `wp_register_ability()` once WP
core's Abilities API is available) that exposes the plugin's own capabilities — generate a
post, regenerate a component, score SEO, publish, notify, run internal linking, fetch trending
topics — as abilities. The workflows below assume that namespace exists; they're written
against a small, coherent set of `aips/*` abilities (plus one `wordpress/*` core read/write
ability) so the catalog is useful out of the box, independent of whether AI Engine is
installed.

Also note: `trigger_type: scheduled` is schema/UI-ready but not dispatched yet. Pre-installed
workflows should ship with `trigger_type: manual` (surfaced as "Run Now" and as an action other
code/cron can call via `AIPS_Ability_Workflow_Executor::dispatch_run()`) until scheduled
dispatch (`aips_dispatch_scheduled_ability_workflows`, referenced in
`docs/AI_AGENT_REFERENCE.md` but not yet implemented) ships as a fast-follow.

## 2. Ways to use Ability Workflows

- **Content pipelines** — chain generation → QA/scoring → conditional publish so nothing goes
  live without passing a bar, without a human clicking through multiple screens.
- **Gated automation** — use `condition_tree` as a quality/policy gate (SEO score, word count,
  moderation flag) so a workflow only takes a destructive action (publish, delete, email) when
  a threshold is met; `on_failure: skip` lets the rest of the chain continue non-destructively.
- **Cross-plugin orchestration** — a single workflow can mix `aips/*` abilities with WP core
  abilities and (if installed) AI Engine `mwai/*` abilities, letting AIPS act as an
  orchestration layer over multiple ability providers rather than a silo.
- **Maintenance/rescue jobs** — find stale/incomplete content and route it through
  regeneration + re-scoring + notification, replacing a bespoke cron job with a declarative,
  editable-in-admin chain.
- **Human-in-the-loop escalation** — instead of hard-failing, route low-confidence results to a
  notification step so an editor is pulled in only when needed (`on_failure: continue` +
  a conditional notify step).
- **Auditable automation** — every run persists per-step inputs/outputs
  (`log_payloads`) and a correlation ID through `AIPS_History_Service`, so these chains are
  inspectable after the fact — useful for anything client-facing or compliance-adjacent.
- **Reusable building blocks** — because steps are just ability calls with `input_map`
  templating, the same small ability set can be recombined into many workflows without new
  code, which is exactly what the catalog below demonstrates.

## 3. Proposed `aips/*` ability palette (prerequisite)

| Ability | Purpose | Key output fields |
|---|---|---|
| `aips/fetch-trending-topics` | Pull topics from configured trending sources | `topics[]` (each: `title`, `score`) |
| `aips/generate-post` | Generate an AI draft post from a topic/template | `post_id`, `title`, `word_count` |
| `aips/regenerate-component` | Regenerate one component of an existing post (`seo_meta`, `featured_image`, `body`, `intro`) | `post_id`, `component`, `success` |
| `aips/analyze-post-seo` | Score a post's SEO health | `post_id`, `seo_score`, `issues[]` |
| `aips/create-internal-links` | Run the internal linking pass on a post | `post_id`, `links_added` |
| `aips/publish-post` | Transition a post to `publish` | `post_id`, `status` |
| `aips/send-notification` | Send an AIPS notification (email/admin/Slack per configured channel) | `sent` |
| `wordpress/get-post` *(core)* | Read a post's current state | `post` (`post_status`, `post_modified`, …) |

Every `input_map` value below of the form `{{...}}` is a live token resolved by
`AIPS_Ability_Workflow_Variable_Resolver` — `{{trigger.x}}` from the run's trigger payload,
`{{steps.<output_alias>.output.x}}` from an earlier step.

---

## 4. The six pre-installed workflows

### 4.1 Two-step workflows

#### A. "Trending Topic → Draft"
*Turn today's top trending topic into a draft post in one action.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Notes |
|---|---|---|---|---|---|---|
| 1 | `fetch_trending` | `aips/fetch-trending-topics` | — | — | `stop` | `input_map`: `category = {{trigger.category}}` |
| 2 | `draft` | `aips/generate-post` | `fetch_trending` | `steps.fetch_trending.output.topics` `is_not_empty` | `stop` | `input_map`: `topic = {{steps.fetch_trending.output.topics.0.title}}` |

- **Trigger:** `manual` ("Run Now" from the trending-topics screen, or an editor's daily habit).
- **Settings:** `allow_destructive_abilities = false` (nothing destructive here), `log_payloads = true`.
- **Why 2 steps works:** if there are no trending topics, step 2's condition fails and the step
  is skipped rather than generating a post from nothing — the condition *is* the safety net,
  no third step needed.

#### B. "SEO Health Check + Auto-Fix"
*Score a post's SEO and regenerate just the metadata if it's weak.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Notes |
|---|---|---|---|---|---|---|
| 1 | `score` | `aips/analyze-post-seo` | — | — | `stop` | `input_map`: `post_id = {{trigger.post_id}}` |
| 2 | `fix_meta` | `aips/regenerate-component` | `score` | `steps.score.output.seo_score` `less_than` `70` | `skip` | `input_map`: `post_id = {{trigger.post_id}}`, `component = "seo_meta"` |

- **Trigger:** `manual`, called from the post-editor "Check SEO" action (`trigger.post_id` = current post).
- **Settings:** `allow_destructive_abilities = false`, `max_runtime_seconds = 60`.
- **Why 2 steps works:** a single conditional step is the fix — if the score already clears the
  bar, step 2 is skipped and the run completes as a no-op check.

### 4.2 Three-step workflows

#### C. "Draft → Score → Publish (or Hold)"
*Fully automated draft-to-publish gate: only publish if the SEO score clears the bar.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Retry |
|---|---|---|---|---|---|---|
| 1 | `draft` | `aips/generate-post` | — | — | `stop` | `attempts: 2, backoff_seconds: 30` |
| 2 | `score` | `aips/analyze-post-seo` | `draft` | — | `stop` | — |
| 3 | `publish` | `aips/publish-post` | `score` | `steps.score.output.seo_score` `greater_than` `70` | `skip` | — |

- **`input_map` highlights:** step 2 `post_id = {{steps.draft.output.post_id}}`; step 3
  `post_id = {{steps.draft.output.post_id}}`.
- **Trigger:** `manual`, kicked off from "Generate & Publish" in the campaign/planner UI.
- **Settings:** `allow_destructive_abilities = true` (publishing is a state change but not
  flagged destructive; kept explicit here since `publish-post` is a live-site action).
- **Behavior when the gate fails:** step 3 is skipped, the post stays a `draft`, and the run
  still completes `succeeded` (skips are not failures) — an editor finds it in the drafts list.

#### D. "New Post → Internal Links → Notify Author"
*Post-publish enrichment: only link a post once it's actually live, then tell the author.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Notes |
|---|---|---|---|---|---|---|
| 1 | `read_post` | `wordpress/get-post` | — | — | `stop` | `input_map`: `post_id = {{trigger.post_id}}` |
| 2 | `link` | `aips/create-internal-links` | `read_post` | `steps.read_post.output.post.post_status` `equals` `"publish"` | `continue` | `output_alias: link` |
| 3 | `notify` | `aips/send-notification` | `link` | — | `continue` | `input_map`: `message = "Added {{steps.link.output.links_added}} internal links to {{steps.read_post.output.post.post_title}}"` |

- **Trigger:** `manual` today (natural fit for a `publish_post` hook once event triggers ship —
  see §5).
- **`on_failure: continue`** on step 2 means a linking failure still reaches the notify step
  (with `links_added` unset/0), so the author always hears back instead of the run silently
  dying.

### 4.3 Four-step workflows

#### E. "Full Auto-Publish Pipeline"
*End-to-end: trending topic in, gated publish out, no editor involvement unless the gate fails.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Retry |
|---|---|---|---|---|---|---|
| 1 | `fetch_trending` | `aips/fetch-trending-topics` | — | — | `stop` | — |
| 2 | `draft` | `aips/generate-post` | `fetch_trending` | `steps.fetch_trending.output.topics` `is_not_empty` | `stop` | `attempts: 2, backoff_seconds: 30` |
| 3 | `score` | `aips/analyze-post-seo` | `draft` | — | `stop` | — |
| 4 | `publish` | `aips/publish-post` | `score` | `steps.score.output.seo_score` `greater_than` `70` | `skip` | — |

- **Trigger:** `manual` for now; this is the canonical candidate to promote to `trigger_type:
  scheduled` (e.g. daily) once scheduled dispatch ships.
- **Settings:** `max_steps = 20`, `max_runtime_seconds = 180` (topic fetch + generation can be
  slow), `allow_destructive_abilities = true`.
- **Design note:** steps 2 and 4 both use conditions as gates rather than a fifth
  "decide" step — the condition tree on the step itself *is* the branch, so a 4-ability chain
  stays a 4-step workflow instead of growing a step per decision.

#### F. "Stale Draft Rescue"
*Find a draft that's gone stale, regenerate it, re-score it, and tell an editor either way.*

| # | Step key | Ability | `depends_on` | Condition | `on_failure` | Notes |
|---|---|---|---|---|---|---|
| 1 | `read_post` | `wordpress/get-post` | — | — | `stop` | `input_map`: `post_id = {{trigger.post_id}}` |
| 2 | `regenerate` | `aips/regenerate-component` | `read_post` | `steps.read_post.output.post.post_status` `equals` `"draft"` | `skip` | `input_map`: `post_id = {{trigger.post_id}}`, `component = "body"` |
| 3 | `score` | `aips/analyze-post-seo` | `regenerate` | — | `continue` | `input_map`: `post_id = {{trigger.post_id}}` |
| 4 | `notify` | `aips/send-notification` | `score` | — | `continue` | `input_map`: `message = "Rescued draft '{{steps.read_post.output.post.post_title}}' — SEO score now {{steps.score.output.seo_score}}"` |

- **Trigger:** `manual` today, invoked per-post from a "stale drafts" admin list (`trigger.post_id`);
  the natural next step is a scheduled sweep that enumerates stale drafts and calls
  `dispatch_run()` once per post — no change to the workflow itself, just how it's invoked.
- **`on_failure: continue`** on steps 3–4 guarantees the editor is notified even if scoring
  fails, rather than the rescue attempt vanishing silently.

---

## 5. Fast-follow suggestions

1. **Register `aips/*` abilities** (§3) — without this, none of the above can actually run;
   it's the real prerequisite, not the workflow definitions themselves.
2. **Wire up `trigger_type: scheduled`** (`aips_dispatch_scheduled_ability_workflows`, already
   named in `docs/AI_AGENT_REFERENCE.md` but not implemented) so workflow E can run daily and F
   can run as a periodic sweep without external cron/webhook glue.
3. **Event triggers** (e.g. `on publish_post`, `on post_status transitions to draft-stale`)
   would let workflow D fire itself instead of needing a manual/external call per post.
4. Ship the six workflows above as installed-but-`draft` rows on activation (matching the
   existing `status` enum), so admins review and flip them to `active` rather than
   automations silently going live on upgrade.
