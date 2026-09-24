# Enhancements Tracker: Content Intelligence Internal Linking

This tracker follows [`content-intelligence-plan.md`](./content-intelligence-plan.md).
- **Branch:** `feat/content-intelligence-linking`, PR [#2128](https://github.com/rpnunez/wp-ai-scheduler/pull/2128), into `refactor/admin-ia`
- **Plugin version:** 3.7.6
- **Last updated:** 2026-09-24

**Status key:**
- ✅ Done
- 🟡 Partial: done differently or with part still open (see Notes)
- 🔄 In progress
- ⬜ Not started
- ⏸ Deferred by decision

Verification: every ✅ item was smoke-tested against the Docker MariaDB 10.6 test database, and the targeted PHPUnit files pass. Browser checks are still pending (see [Remaining verification](#remaining-verification)). Pre-existing failures in the full suite are tracked in [#2129](https://github.com/rpnunez/wp-ai-scheduler/issues/2129).

---

## Overview

| Area | Progress |
|---|---|
| Wave 1: slices 0, 1, 2, 7, 11 | ✅ 5 / 5 |
| Wave 2: slices 3, 6 | ✅ 2 / 2 |
| Wave 3: slices 4, 5, 8, 9 | ✅ 3 / 4, 🟡 1 (slice 5) |
| Wave 4: slices 10, 12 | ✅ 2 / 2 |
| Then: slice 13, then slice 14 | ✅ 13, 🟡 14 |
| Tier 2: items 6–10 | ✅ 3, 🟡 1 (item 6), ⬜ 1 (item 9) |
| Tier 3: items 11–15 | ✅ item 13 (T3-1). ⬜ Items 11, 14, 12 and 15. Next: T3-2 |

---

## Wave 1: 0, 1, 2, 7, 11

| Slice | Title | Status | Where it lives | Notes |
|---|---|---|---|---|
| 0 | Restore the missing similarity-evaluator methods and register orphaned AJAX actions | ✅ | `class-aips-similarity-evaluator.php`, `class-aips-ajax-registry.php` (merge `ea23de12`, plus `67736b3e`) | Restored `detect_post_clusters`, `get_orphan_posts`, `set_pillar_post`, `rename_post_cluster` and `generate_gap_suggestions`. Registered the 5 internal-links actions and `aips_save_author_topic`. Also repaired the test loader and container leaks (`f0638662`, `08a575f5`). |
| 1 | `aips_link_index` table and repository | ✅ | `class-aips-link-index-repository.php`, `class-aips-db-manager.php` (`ccf849de`) | Released in schema 3.7.5, not the planned 3.6.8. The repository also grew the report, summary, broken-link and "inbound below N" queries. |
| 2 | Link extractor and cached URL resolver | ✅ | `class-aips-link-extractor.php`, `class-aips-link-url-resolver.php` (`916a5de4`) | Quote-aware parsing that ignores comments and block JSON. Resolves `?p=`, attachments and old slugs. Cached in the `aips_link_resolve` group. Guards against `url_to_postid` returning IDs that don't exist. |
| 7 | HTML-safe link insertion engine | ✅ | `class-aips-link-insertion-engine.php` (`eb06172f`) | Skips headings, existing links, code, buttons, shortcodes and HTML blocks. Marker attribute `data-aips-link` (D3). Undo with `ok`, `not_found` or `ambiguous`. |
| 11 | Auto-link guardrails and `AIPS_Autolink_Policy` | ✅ | `class-aips-autolink-policy.php`, Settings → Internal Linking (`56c62d84`) | Not implemented: `aips_autolink_llm_polish` (D6). |

## Wave 2: 3, 6

| Slice | Title | Status | Where it lives | Notes |
|---|---|---|---|---|
| 3 | Index links on save, plus backfill | ✅ | `class-aips-link-index-service.php`, `ai-post-scheduler.php` (`fd525b6c`, `2aeb1da9`, `f17c3b5e`, `9dafacec`) | **Changed from the plan:** backfill is its own chain of `aips_link_index_scan_tick` cron events, not a `AIPS_Bulk_Batch_Processor` strategy. Scans can be paused, resumed and cancelled, with speed settings: batch size 50 and a 20s delay. Three scan modes: new or never-scanned, updated in the last N days, and full rescan. "Index built" is detected through the `_aips_link_index_hash` meta. |
| 6 | Suggestion columns on `aips_internal_links` | ✅ | `class-aips-db-manager.php`, `class-aips-internal-links-repository.php` | New columns `origin`, `confidence`, `anchor_source`, `match_context`, `batch_id`, `before_snippet`, `after_snippet` and `applied_at` (schema 3.7.5), plus status `reverted`. Regenerating suggestions is origin-aware. |

## Wave 3: 4, 5, 8, 9

| Slice | Title | Status | Where it lives | Notes |
|---|---|---|---|---|
| 4 | Link Report | ✅ | `class-aips-link-report-controller.php`, `templates/admin/link-report.php`, `assets/js/admin-link-report.js` (`e3b3e808`) | Lives in the Content hub rail below Content Indexer (D8), not on its own page. Stat cards, filters, sorting, drill-down and scan controls. |
| 5 | Existing features use the index | 🟡 | `class-aips-internal-links-service.php`, `class-aips-similarity-evaluator.php` (`f96a469a`) | ✅ Outbound suggestions skip targets that are already linked. ✅ Topic Clusters orphans use link counts once the index is built. 🟡 Link counts appear in the new **Internal Links editor panel**, not the Post Insights metabox. ⬜ `AIPS_Relationships_Repository::count_incoming_internal_links` (the `LIKE` scan) is not deprecated yet; it is still the fallback before the index is built. |
| 8 | Inbound suggester | ✅ | `class-aips-inbound-links-service.php` (`2ff9df8b`, `b7cf6b94`) | **Changed from the plan:** anchor selection lives inside the service (`get_anchor_phrases`) rather than in separate keyphrase-extractor and anchor-selector classes. Candidates come from embeddings first, then keywords (D1 option b). Suggestions are offered only below 3 inbound links (`aips_inbound_suggest_below`). Search Console queries come first once connected. |
| 9 | Insertion ledger with undo | ✅ | `class-aips-inbound-links-service.php`, `class-aips-autolink-run-service.php` | **Changed from the plan:** there are no separate `aips_link_insertions` / `aips_link_batches` tables. Undo data (`before_snippet`, `after_snippet`, `batch_id`, `applied_at`) lives on the `aips_internal_links` rows, and run history lives in an option. The kses-in-cron fix acts as the admin who started the run. Locked posts are skipped. Conflicts are detected. ⬜ The legacy AI inserter (`AIPS_Internal_Link_Inserter_Service::apply_insertion`) is still not routed through the engine and cannot be undone. |

## Wave 4: 10, 12

| Slice | Title | Status | Where it lives | Notes |
|---|---|---|---|---|
| 10 | Inbound suggestions UI | ✅ | Link Report "Suggest Links" (`2ff9df8b`), the Internal Links page origin filter (`3e0d7aef`), and editor panels (`0ab8a9d3`: `class-aips-internal-links-editor-panel.php`, `admin-internal-links-metabox.js`, `admin-internal-links-gutenberg.js`) | Insert, dismiss and undo everywhere. The edited post is always the target in the editor panel. |
| 12 | Bulk auto-link job | ✅ | `class-aips-autolink-run-service.php` (`5e9cfc24`) | A tick worker (`aips_autolink_run_tick`) rather than a batch-processor strategy. Scope: orphans, or fewer than 3 inbound links. Dry run by default unless Bulk Auto-Linking is on. Pause, resume and cancel. |

## Then: 13, then 14

| Slice | Title | Status | Where it lives | Notes |
|---|---|---|---|---|
| 13 | Auto-link launcher, review queue and history | ✅ | "Automatic Linking" section in the Link Report (`5e9cfc24`) | The review queue is the Internal Links page filtered to inbound + pending. **Undo run** undoes a whole run. There's no separate launcher modal or review screen. |
| 14 | Observability, auditor unification, docs | 🟡 | `CHANGELOG.md`, `docs/HOOKS.md`, `AGENTS.md` | ✅ Changelog and hooks docs. ⬜ Batch processor message per strategy. ⬜ History types for scans and runs. ⬜ Notification when a run finishes or has conflicts. ⬜ `AIPS_Content_Auditor_Scanner::build_link_graph` should read the link index. ⬜ `docs/FEATURE_LIST.md` and `docs/AI_AGENT_REFERENCE.md`. |

## Additions beyond the slice plan (user requests)

| Item | Status | Notes |
|---|---|---|
| Settings → Internal Linking tab | ✅ | Four cards: Link Index, Keyword Link Rules, Link Click Tracking and Internal Link Automation. |
| Scan pause, resume and cancel, plus a "recently updated" scan | ✅ | `f17c3b5e` |
| Suggest Links only for posts with fewer than 3 inbound links | ✅ | `b7cf6b94` |
| Separate Internal Links editor panel (Classic and Block) | ✅ | `0ab8a9d3` |
| Admin-only access | ✅ | Every endpoint requires `manage_options`. |

---

## Tier 2: parity features

| # | Feature | Status | Where it lives | Notes |
|---|---|---|---|---|
| 6 | Target keywords (SEO plugins and Search Console) | 🟡 | `class-aips-gsc-client.php`, `class-aips-gsc-keywords-service.php`, `class-aips-gsc-controller.php`, `class-aips-secret-encryption.php`, `admin-gsc.js` (`63550a2e`) | ✅ Yoast and Rank Math focus keyphrases. ✅ Search Console, which connects with a service account JSON key (encrypted) and a property, on Settings → API Keys. It syncs daily (`aips_gsc_sync`), keeps the top 10 queries per post, uses them first as anchors with a higher score, and labels them "Search query". ⬜ AIOSEO keyphrases. ⬜ Checking a real property. |
| 7 | Keyword → URL link rules | ✅ | `class-aips-link-rules-service.php`, `class-aips-link-rules-controller.php`, `templates/admin/link-rules.php`, `admin-link-rules.js` (`c962cc0d`) | Applied at render time (`the_content` priority 9), cached, and counted by the link index. There's a Content → Link Rules tab. |
| 8 | Broken-link fixer | ✅ | `class-aips-broken-links-service.php` (`a1bb7769`) | Suggests the closest live post (from the slug and anchor), re-points or unlinks, fixes every URL variant at once, and can be undone. |
| 9 | Update links when a slug changes, plus a URL changer | ⬜ | | Planned approach: on `post_updated` with a changed permalink, re-point the stored links to the old URL (reusing the broken-link repoint); a bulk "change URL" tool; undo. |
| 10 | Click tracking | ✅ / ⏸ | `class-aips-link-click-tracking-service.php`, `class-aips-link-clicks-repository.php`, `link-click-tracker.js`, `aips_link_clicks` table (schema 3.7.6) (`da0df6c9`) | Opt-in. A daily count per link, with no personal data. Shown in the Link Report. ⏸ Using clicks to rank suggestions is deferred (decision, 2026-09-24). |

---

## Tier 3: features nobody else has together

Order: 13 → 11 → 14 → 12 → 15. The slices are defined in the plan, section 2.

| Slice | Feature (#) | Status | Notes |
|---|---|---|---|
| T3-1 | Linking at generation time, both directions (13) | ✅ | `class-aips-publish-linking-service.php` and `AIPS_Autolink_Run_Service::run_now()`. Triggered by `aips_post_generated` and by `transition_post_status` → publish, when the post has the `_aips_generated_post` meta. A background `aips_publish_linking` event runs about two minutes later. Modes: off, review (default) or apply. Outbound suggestions are optional. Each publish is recorded as a "New post: …" run, so it can be undone. Links are inserted as the post author or an administrator (kses-safe). The setting is under Settings → Internal Linking → Internal Link Automation. |
| T3-2 | Silo model (11) | ⬜ | The member ↔ pillar link matrix and a gap score, from the link index and clusters. |
| T3-3 | Silo UI and Fix silo (11) | ⬜ | Content hub "Silos" rail item. |
| T3-4 | Abilities API (14) | ⬜ | `wp_register_ability` when it's available. |
| T3-5 | MCP bridge link tools (14) | ⬜ | `mcp-bridge.php`, the schema and the docs. |
| T3-6 | Redirects store (12) | ⬜ | Blocked by D13. |
| T3-7 | Consolidation flow (12) | ⬜ | Depends on T3-6. |
| T3-8 | Cross-site linking (15) | ⬜ | Blocked by D12. |

---

## Decisions

| # | Decision | Status |
|---|---|---|
| D1 | Existing archives | 🟡 Option (b), the keyword fallback, is done. Option (a), a one-time archive backfill allowance with a cost estimate, is ⬜ open. |
| D2 | Which links count | ✅ Post content only, for the configured post types. |
| D3 | Marker attribute | ✅ `data-aips-link` |
| D4 | `post_modified` and revisions | 🟡 WordPress defaults apply. Revision clean-up is open. |
| D5 | Defaults | ✅ Auto-apply off; apply at 0.85, review at 0.70; caps of 3 new links per post per run and 15 links per post. |
| D6 | AI anchor polish | ⏸ Not done. |
| D7 | Paragraph-chunk embeddings | ⏸ Not done; n-grams are used. |
| D8 | Screen placement | ✅ Content hub rail, the Settings tab and editor panels. |
| D9 | Undo retention | 🟡 No expiry yet. |
| D10 | Undo after edits | ✅ Treated as a conflict and skipped. |
| D11 | Default rel/target | ✅ None. |
| D12 | Cross-site model | ⬜ Open |
| D13 | Redirect storage | ⬜ Open |
| D14 | Publish-linking default | ✅ `review` (only suggestions). Easy to change in Settings. |

---

## Findings from the original code review

| # | Finding | Status |
|---|---|---|
| 1 | Registry gap in internal-links AJAX | ✅ Fixed (slice 0) |
| 2 | Missing similarity-evaluator methods (fatal errors) | ✅ Fixed (slice 0) |
| 3 | Legacy `apply_insertion` is not HTML-safe | 🟡 New flows use the engine. The legacy AI path is still there. |
| 4 | Affiliate inserter's `str_replace` | ⬜ Not moved onto the engine |
| 5 | Internal-links data model collisions | ✅ Origin-aware (slice 6) |
| 6 | Relationships recomputed on every save | ✅ `aips_content_indexer_skip_post_save` during link-only edits |
| 7 | kses in cron | ✅ Runs act as the admin who started them |
| 8 | Concurrent editing | ✅ `wp_check_post_lock` skip |
| 9 | `url_to_postid` cost | ✅ Resolver cache |
| 10 | Gutenberg JSON and skip blocks | ✅ Extractor and engine |
| 11 | Revision bloat | ⬜ Open (D4) |
| 12 | Batch processor behaviour | 🟡 Linking no longer uses the processor. The hard-coded message is still there (slice 14). |
| 13 | Indexer queue drops items on a rate limit | ⬜ Open |
| 14 | Embedding cache and usage-history bloat | ⬜ Open |
| 15 | Memory in relationship recompute (about 5,000 posts) | ⬜ Open |
| 16 | Content Auditor link graph uses a 200-post sample | ⬜ Open (slice 14) |
| 17 | Embeddings unique key blocks chunks | ⏸ Only matters for D7 |
| 18 | Overlap with open PRs | ✅ Consolidated into #2128 |
| 19 | Schema housekeeping | ✅ New tables are added to the table list and the datetime map |

---

## Schema history on this branch

| Version | Change |
|---|---|
| 3.7.5 | Added the `aips_link_index` table, and suggestion and undo columns on `aips_internal_links` |
| 3.7.6 | Added the `aips_link_clicks` table (daily click counts) |

## Remaining verification

- Browser checks:
  - Content → Link Report and Link Rules.
  - Settings → Internal Linking and Settings → API Keys.
  - Classic and Block editor panels.
  - The frontend click beacon.
- Search Console against a real property. `devstacktips.com` uses a Domain property, `sc-domain:devstacktips.com`.
- Full-suite comparison against the base once [#2129](https://github.com/rpnunez/wp-ai-scheduler/issues/2129) is addressed.
