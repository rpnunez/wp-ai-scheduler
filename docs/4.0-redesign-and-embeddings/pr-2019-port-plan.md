# Porting ideas from PR #2019 into `feat/content-intelligence-linking`

Date: 2026-09-24. Target branch: `feat/content-intelligence-linking` (PR #2128). Source: PR #2019 (`implement_scheduler_issue_2005`), which is not merged or cherry-picked; these ideas are rebuilt on our link index.

## Decisions (from the review of #2019)

| # | Decision |
|---|---|
| P1 | Port **(1)** writing suggestions (links from the post being edited to other posts), **(2)** the WordPress Posts/Pages list column plus the Orphans view, and **(3)** crawl depth. |
| P2 | Skip Elementor support for now. |
| P3 | Everything stays **admin-only** (`manage_options`), like the rest of Content Intelligence. |
| P4 | Build on `aips_link_index`, `AIPS_Link_Insertion_Engine`, the relationships table and `decode_embedding()`. Do not add `aips_content_links`, `AIPS_Link_Graph_Service` or #2019's REST routes. |
| P5 | Once these land, close #2019 with a comment that points to #2128. |

No schema change and no version bump are needed. Depth is stored in post meta plus one summary option.

## Order

The slices are independent, so they can be built and tested in this order:

1. **S1: Posts list column and Orphans view.** Smallest; exercises index counts in the core WordPress list screens.
2. **S2: Crawl depth.** A backend service, then the Link Report, Posts list and editor panel displays.
3. **S3: Writing suggestions backend.** A service plus AJAX endpoints.
4. **S4: Writing suggestions UI.** Block Editor and Classic Editor panels.
5. **S5: Docs and wrap-up.** CHANGELOG, HOOKS, tracker, PR description, and the comment that closes #2019.

---

## S1: "Internal Links" column and "Orphans" view in the WordPress Posts and Pages lists

**What the admin sees**
- **Posts → All Posts** and **Pages → All Pages** (and any post type in the link index) get an **Internal Links** column showing `In 3 · Out 5`.
  - It adds an **Orphan** badge when a published post has no internal links pointing to it.
  - Each number links to the Link Report drill-down for that post.
  - Once S2 lands, the column also shows `Depth 2`.
- The column sorts by inbound links.
- A view link, **Orphans (N)**, is added next to All | Published | Drafts. It lists published posts with no inbound internal links.
- While the link index has never been built, the column shows "—" with a tooltip linking to Content → Link Report to run a scan. The view is hidden then.

**Code**
- New `includes/class-aips-post-list-link-columns.php` (`AIPS_Post_List_Link_Columns`), constructed in `boot_admin()` only.
  - Exits early unless the user has `manage_options`, the link index is enabled, and the screen's post type is in `AIPS_Link_Index_Service::get_post_types()`.
  - Hooks per indexed type: `manage_{type}_posts_columns`, `manage_{type}_posts_custom_column`, `manage_edit-{type}_sortable_columns`, `views_edit-{type}` and `pre_get_posts`.
  - Counts are loaded in one query per page: `the_posts` on the main `edit.php` query calls `AIPS_Link_Index_Repository::get_counts_for_posts($ids)` (already a batch query) and memoizes the result.
- **Sorting and the Orphans view need SQL, so it lives in the repository** (`composer lint:repository-boundary`):
  - `AIPS_Link_Index_Repository::get_list_table_clauses(string $mode): array`. Mode `sort_inbound` or `orphans`; returns `join`/`where`/`orderby` fragments.
  - It joins a derived table: `SELECT target_post_id, COUNT(*) c FROM aips_link_index WHERE link_type='internal' AND source_post_id <> target_post_id GROUP BY target_post_id`.
  - The column class adds these fragments through `posts_clauses`, only for the main query on `edit.php`.
  - `count_orphans(array $post_types): int` feeds the view label. It reuses the same derived table; the Link Report summary already has similar logic that it can share.
- Admin-only styles go into the existing `admin-link-report.css` or a small inline `admin_print_styles-edit.php` block, with no new files for a few rules.

**Risks and checks**
- `posts_clauses` must only apply to our query: `is_admin() && $query->is_main_query() && $query->get('aips_links')` or `orderby=aips_inbound`.
- Performance: the derived table is grouped over the indexed `target_link` key. Check `EXPLAIN` on a site with about 5k posts.

**Smoke test:** column values match the Link Report; the Orphans view count matches the Link Report orphan card; sorting works both ways; editors (non-admins) see no column and no view.

---

## S2: Crawl depth (clicks from the home page)

**What it means:** depth is how many clicks a visitor, or a search engine crawler, needs from the home page to reach a post by following links. Posts more than 3 clicks deep are crawled less often and rank worse. #2019 only started from the static front page's content, which on most blogs leaves everything "unreachable". We use the places the home page actually links from.

**Starting points (depth 1)**
- The static front page's content links, when `show_on_front = page`. The front page itself is depth 0.
- Items in **menus assigned to theme locations** (`get_nav_menu_locations()` → `wp_get_nav_menu_items()`, post and page objects only).
- When the home page is the blog (`show_on_front = posts`): the latest `posts_per_page` posts and sticky posts.
- Filter `aips_link_depth_roots` (post IDs) so themes and sites can add hubs, such as a "Start here" page.

**Walking the links:** a breadth-first search over `aips_link_index` internal edges between published, in-scope posts, including render-time links (link rules and silo "In this guide" lists, already in the index).
- Depth = hops from a starting point. Posts never reached are **unreachable**.
- Deep = depth ≥ 4 by default (filter `aips_link_depth_deep_threshold`).

**Code**
- `AIPS_Link_Index_Repository::get_internal_edges_chunk(int $after_id, int $limit)`: streams `(source_post_id, target_post_id)` pairs by `id` so large sites don't load one huge result.
- New `includes/class-aips-link-depth-service.php` (`AIPS_Link_Depth_Service`), a container singleton:
  - `compute(): array` builds the adjacency list (int arrays), runs BFS from the roots, and writes the results:
    - `_aips_link_depth` post meta (`-1` = unreachable);
    - option `aips_link_depth_summary`: counts per depth, deep count, unreachable count, roots used, computed time and duration.
  - Only posts whose value changed are written. Posts that left the scope get their meta deleted.
  - `get_depth(int $post_id): ?int` and `get_summary(): array`.
  - `schedule()` sets a single `aips_link_depth_compute` event, debounced about 10 minutes. It is scheduled when a link scan finishes, when a post is published or unpublished, when menus are saved (`wp_update_nav_menu`), and when the front page settings change. A daily fallback runs too.
  - The cron handler is registered in `boot_cron()`.
- **Link Report**:
  - A **Depth** column, sortable through a `LEFT JOIN` on `_aips_link_depth` inside `get_report_page()`, which lives in the repository.
  - New filters "Deep (4+ clicks)" and "Unreachable".
  - A **Depth** stat card with the average and the deep/unreachable counts, plus a **Recalculate** button (AJAX `aips_link_report_compute_depth`, admin-only, registered in `AIPS_Ajax_Registry`).
  - The drill-down shows one shortest path (for example "Home → Guide → This post"). The BFS stores a parent map for the drill-down only when it is asked for on demand; it is not persisted.
- **Posts list column (S1)** adds `Depth N`, or `Unreachable` with a warning style.
- **Editor panel** (existing Gutenberg and Classic panels) shows the depth next to the inbound and outbound counts.

**Risks and checks**
- Memory: about 50k posts with 500k edges is fine as int arrays (~tens of MB). There is a guard: when edges exceed a limit (filter `aips_link_depth_max_edges`, default 2M), the run is skipped and the summary says so.
- Pages missing from the index (the front page, or pages not in the index's post types) are still handled as roots. Their outbound links need the page to be indexed, so the Link Index settings note that the front page is indexed even when pages are excluded. Implement this by always including `page_on_front` in the scan.

**Smoke test:** a small site with a static front page linking A, A linking B, and B linking C gives depths 1, 2 and 3; a menu item gives depth 1; a post with no path is unreachable; silo list links count; recalculation runs after a scan; summary counts are correct.

---

## S3: Writing suggestions (links from this post to others), backend

**What it does:** while an admin edits a post, AIPS suggests existing posts that this post should link to. For each one it shows where the link would go: the phrase in the current draft that would become the link text.

**Where candidate posts come from** (in order, merged and deduplicated):
1. **Saved relationships:** `AIPS_Relationships_Repository::get_related('post', $post_id)`, for posts already saved and indexed. No AI call.
2. **Live draft embedding:** only on an explicit **Refresh from draft** click, or on first open when the post has no stored embedding.
   - It embeds the draft's plain text (first ~1,500 words) with `AIPS_Embeddings_Service::generate_embedding()`.
   - It compares that against stored post vectors using `AIPS_Embeddings_Repository::get_all_for_similarity()` + `decode_embedding()` and `AIPS_Similarity_Evaluator::find_top_matches()`. This is the binary-safe path; #2019's `json_decode` would fail here.
   - The result is cached in a transient keyed by `md5(draft text)` for 30 minutes, so re-opening or small edits don't call the API again.
3. **Keyword fallback / search box:** a `WP_Query` search on the admin's query, or on the draft's strongest title-like phrases when embeddings are unavailable.

**Placing each candidate**
- Anchor phrases come from `AIPS_Inbound_Links_Service::get_anchor_phrases($target)`: GSC queries, focus keywords and title phrases. They are matched in the draft with `AIPS_Link_Insertion_Engine::find_phrase_occurrences()`, with the same safety rules as everywhere else (no headings, no existing links, no code or shortcodes).
- Candidates the draft already links to are dropped (`has_link_to`).
- Score = similarity, plus an anchor bonus, plus a **link-equity boost** for posts that need links (orphans +0.10, fewer than 3 inbound +0.05, from index counts). The card says why ("Orphan: nobody links here yet").
- The post-type filter and the minimum similarity (default 0.60) are request parameters; results are capped at 15.

**Code**
- New `includes/class-aips-writing-suggestions-service.php` (`AIPS_Writing_Suggestions_Service`):
  - `suggest(int $post_id, string $html, array $args): array` returns each candidate's `id`, `title`, `url`, `post_type`, `similarity`, `score`, `inbound`, `is_orphan`, `anchor` (text, occurrence index, snippet with `[[anchor]]`) and `source` (`related|draft|keyword`).
  - `insert(string $html, int $target_id, string $anchor, int $occurrence): array|WP_Error` wraps the insertion engine on the HTML it is given and returns the new HTML plus the changed range.
  - Links carry `data-aips-link="editor"` and the configured `rel`/`target` from the auto-link policy.
- AJAX in `AIPS_Link_Report_Controller` (admin-only, nonce, registered in `AIPS_Ajax_Registry`):
  - `aips_link_report_writing_suggestions`: `post_id`, `content`, `query`, `post_type`, `min_similarity`, `refresh`.
  - `aips_link_report_writing_insert`: `content` (a block's HTML or the whole Classic content), `target_id`, `anchor`, `occurrence`.
  - Content is read with `wp_unslash()` and only used to find phrases; it is never saved server-side. The editor saves it as usual.
- Filters: `aips_writing_suggestions` (the final list) and `aips_writing_suggestions_equity_boost`.

**Risks and checks**
- **AI cost:** embedding calls only happen on an explicit refresh or once per draft hash, never on every keystroke.
- **Large sites:** comparing the draft vector against all stored vectors is O(N). Use the existing cap and pre-filtering that `find_top_matches` uses, and skip the draft path when there are more than 20k vectors (fall back to relationships and keywords; filter `aips_writing_suggestions_max_vectors`).
- **Unsaved new posts:** `post_id` may be an auto-draft. The endpoint accepts it (`edit_post` check plus `manage_options`) and excludes it from the candidates.

---

## S4: Writing suggestions UI (Block Editor and Classic Editor)

Both editors extend the **existing** Internal Links panels (`AIPS_Internal_Links_Editor_Panel`, `admin-internal-links-gutenberg.js`, `admin-internal-links-metabox.js`); there is no second sidebar.

**Block Editor**
- The panel gets two sections:
  - **Link from this post** (new);
  - **Links to this post**, the existing inbound suggestions.
- The top line shows `In 3 · Out 5 · Depth 2` (depth from S2).
- **Link from this post** has:
  - A search box, a post-type select and a minimum-similarity slider, in a collapsed "Filters" area with a reset.
  - **Refresh from draft**, plus a debounced auto-refresh (3 s after typing stops) that only uses the cached/related path, not new embeddings.
  - Cards showing title, similarity, an orphan/low-inbound badge, the snippet with the anchor highlighted, and **Insert link**, **Copy URL** and **Open** actions.
- **Insert link:**
  - Finds the block containing the anchor: `core/paragraph`, `core/list-item` and `core/quote` only, walking inner blocks with `wp.data.select('core/block-editor').getBlocks()`.
  - Sends that block's `content` to `aips_link_report_writing_insert`, then applies the result with `dispatch('core/block-editor').updateBlockAttributes()`, so it's one Ctrl+Z step.
  - If the phrase is no longer there, it shows a notice and refreshes.
- Nothing is saved until the admin saves the post. The link index updates on save as usual.

**Classic Editor**
- The meta box gets the same **Link from this post** tab.
- Insert uses TinyMCE when it's active: send `editor.getContent()`, receive the new HTML, then `editor.undoManager.transact(() => editor.setContent(html))` so it can be undone.
- On the Text tab it rewrites the `#content` textarea.
- The copy and open actions are the same.

**Code:** templates are in the existing panel (Classic uses `AIPS.Templates`; Gutenberg uses `wp.element`). New l10n strings go in the panel's localize call. Everything remains `manage_options`-gated via `is_supported()`.

**Smoke test:**
- Suggestions come from saved relationships, the draft (with a mocked embedding) and the keyword fallback.
- Already-linked targets are dropped, and orphans are boosted.
- Insert changes only the target block, and undo reverts it.
- It works in both editors, and editors (non-admins) see nothing.

---

## S5: Docs and wrap-up

- CHANGELOG (3.7.7 section); `docs/HOOKS.md` for `aips_link_depth_roots`, `aips_link_depth_deep_threshold`, `aips_link_depth_max_edges`, `aips_writing_suggestions`, `aips_writing_suggestions_equity_boost` and `aips_writing_suggestions_max_vectors`.
- Tracker: add a "PR #2019 port" section with S1–S4 status.
- Update the #2128 description; post a closing comment on #2019 that lists what was ported and what was deliberately not (the separate table, REST routes, Elementor, dashboard cards, the seeder).

## What is deliberately not ported from #2019

| Item | Why |
|---|---|
| `aips_content_links` table, `AIPS_Link_Graph_Service`, `AIPS_Content_Links_Repository` | This would duplicate `aips_link_index`, and two link stores would disagree. |
| REST routes under `aips/v1/editor/*` | Admin-only AJAX through `AIPS_Ajax_Registry` matches the rest of the plugin (P3). |
| `find_insertion_locations_for_text()` | `AIPS_Link_Insertion_Engine` is stricter and supports undo. |
| Elementor adapter and editor registry | Skipped for now (P2). |
| Dashboard link-health cards, Generated Posts column, SEO Link Graph tab | These duplicate the Link Report. The Generated Posts table is being reworked in the admin redesign. |
| Wikipedia seeder | Makes outside network calls; the Docker smoke tests cover what it was for. |
| Content Indexer tab fixes | Replaced by the admin redesign on `refactor/admin-ia`. |
| Version bump to 3.6.6 | A 3.6.6 migration would never run on sites already at 3.7.x, and no schema change is needed. |
