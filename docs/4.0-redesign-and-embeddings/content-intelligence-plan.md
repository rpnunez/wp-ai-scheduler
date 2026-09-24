# Content Intelligence: Internal Linking Plan (4.0 redesign + embeddings)

**Status as of 2026-09-24.** Plugin version 3.7.6 on branch `feat/content-intelligence-linking`, PR [#2128](https://github.com/rpnunez/wp-ai-scheduler/pull/2128). The PR goes into `refactor/admin-ia`, which goes into `main` through [#2120](https://github.com/rpnunez/wp-ai-scheduler/pull/2120).

The implementation tracker is in [`enhancements-plan.md`](./enhancements-plan.md). It records the status of each slice, wave, tier item, decision and finding. This document holds the plan itself, meaning the goals, the scope and the order. Update the tracker as work lands, and update this plan when the scope or the order changes.

---

## 1. Goal

AIPS is turning from a post generator into a **Content Intelligence** plugin. What people pay for in this market is the Link Whisper loop: **find the gaps → suggest → insert in bulk → report → fix**. AIPS already has a better relevance engine than Link Whisper: real embeddings and a precomputed relationships table, where Link Whisper mostly does keyword matching. The job is to build the linking workflow on top of that engine, then add features no competitor has as a set: silos, consolidation, linking at generation time, and agent/MCP access.

**Positioning:** "semantic internal linking you can trust". That means relevant suggestions, automation behind confidence thresholds, and undo for everything.

**Market notes that shape the plan:**
- What sells is a visible result ("1,000 links built", "orphans fixed") and a report that comes with a one-click fix.
- Suggestions alone belong in the free tier. Keyword auto-link rules and related posts on their own are commodities.
- Semantic search is becoming a commodity; WP Engine made Smart Search AI free in Aug 2026. Don't sell vectors; sell the workflow.
- Common complaints about Link Whisper: irrelevant suggestions, too much manual review, and a license locked to one site.

---

## 2. Scope by tier

### Tier 1: the Link Whisper loop, on our engine (**done**)
1. A link index of the real `<a href>` links in each post. It drives real orphan detection and the Link Report.
2. Inbound suggestions: which existing posts should link *to* this one.
3. Anchor text chosen locally from n-grams, SEO focus keywords and Search Console queries, with no AI call per link.
4. Bulk auto-linking behind confidence thresholds, with a review queue, guardrails, and undo per link and per run.
5. Link suggestions in the editor. This became a separate Internal Links panel in the Classic and Block editors.

Tier 1 was delivered as slices 0–14 (section 3).

### Tier 2: parity features
| # | Feature | Status |
|---|---|---|
| 6 | Target keywords from Yoast / Rank Math focus keyphrases and Search Console queries, used for anchors and ranking | **Done.** AIOSEO keyphrases are still open. |
| 7 | Keyword → URL link rules for internal links | **Done.** Rules are applied at render time. |
| 8 | Broken-link fixer that suggests the closest live post | **Done** |
| 9 | Update links automatically when a slug changes, plus a bulk URL changer | Open |
| 10 | Click tracking on internal links | **Done.** Using click data to rank suggestions is **deferred** (decision, 2026-09-24). |

### Tier 3: features nobody else has together (**in progress**: T3-1 is done)
| # | Feature | Summary |
|---|---|---|
| 13 | **Linking at generation time, in both directions** | When an AIPS-generated post is published, it gets outbound links *and* inbound links from older posts. This happens automatically or goes to review, under the same policy and undo as runs. |
| 11 | **Silo builder** | Built on clusters and pillars. It checks that every cluster member links to its pillar and that the pillar links back, gives each silo a gap score, and offers "Fix silo" (suggestions, then apply or review, with undo). |
| 14 | **WordPress Abilities / MCP tools** | For example `aips/find-related`, `aips/suggest-links`, `aips/link-report` and `aips/orphans`, registered through the Abilities API when it's available, and added to the MCP bridge. This positions AIPS as the embeddings layer that WordPress core doesn't have. |
| 12 | **Cannibalization → consolidation** | From a Cannibalization Shield pair: choose the primary post, redirect the secondary URL, re-point every inbound link to the primary, and unpublish the secondary. The whole action can be undone. |
| 15 | **Cross-site linking** | Link between sites you own. This drives agency-tier purchases. It needs product decisions first (see D12). |

**Tier 3 order (updated 2026-09-24):** 13 → 12 (redirects first, then consolidation) → 11. Items **14** (Abilities / MCP) and **15** (cross-site linking) are **on hold**.
- 13 is the natural meeting point of the generator and the new focus, and it reuses the inbound service, the policy and undo as they are.
- 11 builds on clusters and pillars that already exist.
- 14 is mostly thin wrappers around existing services.
- 12 needs redirect storage and a merge flow.
- 15 needs decisions and infrastructure (D12).

### Tier 3 slices
| Slice | Feature | Contents |
|---|---|---|
| T3-1 | Generation-time linking | Settings: `aips_publish_linking_mode` (`off`\|`review`\|`apply`, default `review`) and `aips_publish_linking_outbound` (bool). A hook on the first publish of AIPS-generated posts (`transition_post_status` → `publish`, with `_aips_generated_post`) schedules a single cron event, so the publish request stays fast. The worker generates inbound suggestions for the new post and applies or queues them through `AIPS_Autolink_Policy`. When outbound linking is on, it also queues outbound suggestions. Each publish is recorded as a run, so it can be undone as a whole from the Link Report run history. |
| T3-2 | Silo model | For every cluster with a pillar, a `AIPS_Silo_Service` computes the member ↔ pillar link matrix from the link index: which members lack a link to the pillar and which the pillar lacks a link to. It also computes a gap score. No schema; this is read-only. |
| T3-3 | Silo UI | A "Silos" rail item in the Content hub: a card per silo with its gap score and a list of missing links, plus Fix silo, which creates suggestions and applies them per policy or sends them to review. Fixes are undoable through the existing run and suggestion undo. |
| T3-4 | Abilities API | `wp_register_ability` guarded by `function_exists`: find-related, suggest-links, link-report summary, orphans and apply-suggestion (manage_options). |
| T3-5 | MCP bridge tools | The same operations as MCP bridge tools, plus schema updates in `mcp-bridge-schema.json` and `docs/MCP_BRIDGE.md`. |
| T3-6 | Redirects module | The `aips_redirects` table records every AIPS redirect and which provider serves it: Redirection, Yoast SEO Premium, Rank Math, or built in. There's a built-in `template_redirect` handler, a "move all to provider" action, and the Content → Redirects tab. |
| T3-7 | Consolidation flow | From a cannibalization pair: preview, then (optionally) an AI draft merged into the primary as a *draft revision*, then redirect, then re-point inbound links (reusing the broken-link repoint), then set the secondary to draft. Recorded so it can be undone. |
| T3-8 | Cross-site linking | Deferred until D12 is decided. |

---

## 3. Tier 1 slice plan (reference)

This is the original slice plan, kept for traceability. Where the implementation departs from it, the tracker says so.

| # | Slice | Depends on |
|---|---|---|
| 0 | Fixes: restore the missing similarity-evaluator methods and register the orphaned internal-links AJAX actions | none |
| 1 | `aips_link_index` table and repository | none |
| 2 | Link extractor and cached URL → post resolver | none |
| 3 | Index on save/delete, and a backfill of existing posts | 1, 2 |
| 4 | Link Report page | 3 |
| 5 | Existing features use the index (outbound suggestions, orphans, editor counts) | 0, 3 |
| 6 | Suggestion columns on `aips_internal_links` (origin, confidence, anchor_source, context, batch) | 1 |
| 7 | HTML-safe link insertion engine | none |
| 8 | Inbound suggester with local anchor selection | 3, 6, 7 |
| 9 | Insertion ledger with single and batch undo, and conflict detection | 6, 7 |
| 10 | Inbound suggestions UI | 4, 8, 9 |
| 11 | Auto-link guardrail settings and the policy evaluator | none |
| 12 | Bulk auto-link job, with a dry run | 8, 9, 11 |
| 13 | Auto-link launcher, review queue and run history with undo | 12 |
| 14 | Observability, content-auditor unification, docs | 4, 13 |

**Waves (the slices in each wave can run in parallel):**
- **Wave 1:** 0, 1, 2, 7, 11.
- **Wave 2:** 3, 6.
- **Wave 3:** 4, 5, 8, 9.
- **Wave 4:** 10, 12.
- **Then:** 13, then 14.

---

## 4. Decisions

| # | Decision | Outcome |
|---|---|---|
| D1 | How to cover existing archives given the embedding scope and budget | **(b) is implemented:** embeddings first, with a keyword fallback that makes no API calls. **(a) is still open:** the one-time archive backfill allowance with a cost estimate, which was chosen earlier. |
| D2 | Which post types and which links count | Post content only. Post types are configurable (default: post and page). |
| D3 | Marker attribute on inserted links | Yes: `data-aips-link="{id}"` (rule links use `rule-{id}`). |
| D4 | Should auto-link change `post_modified` and create revisions? | It follows the `wp_update_post` default: it updates the date and creates a revision. Revision clean-up is still open. |
| D5 | Defaults | Auto-apply is off by default. Apply at 0.85 or above, review at 0.70 or above. At most 3 new links per post per run and 15 internal links per post in total. |
| D6 | AI "polish" of anchors during bulk runs | Not done. Anchors are chosen locally only. |
| D7 | Paragraph-chunk embeddings for choosing anchors | Not done; n-grams are used for now. |
| D8 | Where the screens live | Content hub rail items (Link Report, Link Rules), the Settings → Internal Linking tab, and editor panels. |
| D9 | How long undo data is kept | Undo snippets live on the `aips_internal_links` rows, and run history is kept in an option. No expiry yet. |
| D10 | Undo when the text has been edited since | Conflict: the post is skipped and reported; it is never force-edited. |
| D11 | Default `rel` / `target` on inserted links | None by default. Both are configurable. |
| — | Access | Admin-only (`manage_options`) for now. |
| — | Click data in suggestion ranking | Deferred. |
| D12 | Cross-site linking | **On hold** (2026-09-24). |
| D15 | Abilities / MCP tools (item 14) | **On hold** (2026-09-24). |
| D16 | *Open:* Silo "Fix silo" direction | Options: members → pillar only; both directions in the text; or members → pillar in the text plus a render-time "In this guide" list on the pillar. See the tracker. |
| D13 | Redirects | Use Redirection, Yoast SEO Premium or Rank Math when one is installed, and fall back to AIPS's own table otherwise. AIPS records every redirect it creates, so redirects can be moved when you switch providers. In effect this is a lightweight redirect plugin. |
| D14 | Generation-time default | `review` (suggestions only). It can be switched to `apply` in Settings. |

---

## 5. Known risks and technical debt

These are open findings from the original review. The tracker records which ones are fixed.

- **The indexer queue drops items** when a rate limit hits mid-slice (`process_pending_indexer_queue`).
- **Embedding cache bloat:** `aips_raw_emb_*` transients and the per-call `aips_embeddings_usage_history` option.
- **Memory:** `recompute_relationships_for_post` loads every vector, so it passes 100 MB at about 5,000 posts. This is a ceiling for scaling the archive.
- **Content Auditor:** `build_link_graph` still reads a 200-post sample instead of the link index.
- **Bulk batch processor:** its success message is hard-coded to "Post %s generated" for every strategy.
- **Legacy inserters:** the old `AIPS_Internal_Link_Inserter_Service::apply_insertion` (the AI "Find Locations" path) is not HTML-safe and cannot be undone. It should move onto `AIPS_Link_Insertion_Engine`.

---

## 6. How to charge for it (unchanged)

- **Free:** bring your own key, unlimited indexing, the Link Report, orphans, one-at-a-time suggestions and related posts. The diagnosis is free.
- **Pro:** $99 / $199 / $299 / $499 for 1 / 3 / 10 / 50 sites, with the same features in every tier. Includes bulk and inbound linking, auto-approve, undo, the silo builder, consolidation, the broken-link fixer, Search Console keywords, click tracking and link rules.
- **Optional "AIPS Cloud" credits** for hosted embeddings and anchor polishing, so no API key is needed.
