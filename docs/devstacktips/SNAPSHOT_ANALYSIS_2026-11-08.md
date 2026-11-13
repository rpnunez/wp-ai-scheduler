# DevStackTips snapshot analysis (2026-11-08)

Source: `export-aips-snapshot.php` run on devstacktips.com at 2026-11-08 05:54 UTC. AIPS 3.7.24 (DB 3.7.24): the same build as this branch, so slugs, post images and the new AI Edit are all available on production. WordPress 7.1.4, PHP 8.3, table prefix `tgnLUT_`. Text AI goes through the WP AI Client (Google connector, failover on). Embeddings: `gemini-embedding-2`, 1024 dimensions.

Stats cover the last 90 days (W33–W45): 1,089 AIPS posts published, 9 drafts, 11 private.

---

## Top findings, ranked by impact on content quality

### 1. No template has a voice (plugin bug)
All 11 templates have `voice_id = NULL`, although the 8 voices exist (IDs 1–8) and v2 assigned one to each template.

Cause: `AIPS.editTemplate()` (`assets/js/admin.js:991`) fills every field in the edit wizard **except `#voice_id`**. Opening a template and pressing Save therefore writes `voice_id = 0`, which is stored as NULL. Every template was saved from the admin between 2026-08-22 and 2026-08-28, which wiped all 11. The 8 hand-written voices (title/content/excerpt instructions) have been unused by scheduled posts since then.

Fix: one line in `editTemplate` (`$('#voice_id').val(t.voice_id || 0)`) plus a reset in `openTemplateModal`. v3 then puts the voices back.

### 2. Every template post uses the same article structure
Template posts get their structure from the **schedule** (`article_structure_id` → `rotation_pattern` → global default). All 11 schedules have neither set, so every template post uses the default **#7 "Evergreen How-To Guide"**. That includes Framework Comparison, Security News and Architecture Deep Dive.

v2 passes `article_structure_id` in the **template** payload, but the templates table has no such column, so the value is silently dropped. Structures 8–17 are only used by authors.

v3: set `article_structure_id` on each schedule (or a `rotation_pattern` over 2–3 suitable structures).

### 3. Sources are silently off for 8 templates and 1 author
`include_sources = 1` with an empty `source_group_ids` injects **nothing**: `build_sources_selection()` returns early with diagnostic `no_groups`. Only **Security News** (group 178: OWASP, Snyk, SecurityWeek) and **PHP Framework Deep Dive** (group 179: Laravel News, Symfony Blog) really use sources.

Affected: AI for Developers, Beginner How-To, Intermediate Backend, Security Guide, Framework Comparison, Architecture, Refactoring, Test Strategy, and the Security Expert author. Your site guidelines say a "Sources" section is mandatory, so the AI is probably inventing or generalising citations on those posts. All 5 sources fetch successfully every day.

v3: assign the right group to each template, and add 1–2 groups (e.g. backend/architecture, AI/devtools) so every template has real sources.

### 4. Topic auto-approval is stalled for 10 of 11 authors
Ten authors use `inherit`, which resolves to the global mode **`similarity`** (fallback `rejected`). Similarity mode needs embeddings. When the embeddings API is in cooldown or over quota, it **skips approval, so topics stay pending**. In addition:
- `aips_indexer_queue_paused = 1`, with roughly 370 posts and roughly 250 topics waiting to be indexed.
- Google embedding credits ran out on 2026-09-15 (28 errors), plus 429 "resource exhausted" errors.

Result: about 270 topics are pending with **average scores of 91–93**, higher than the approved ones. Devon Miller, the only author on `score` mode (≥ 65), has 364 approved topics and the highest output of any author.

v3: set per-author `score` mode (thresholds below) or `smart_split`, and decide whether to unpause the indexer queue now that credits are restored.

### 5. Authors fail far more than templates

| Source | 90-day runs | Failed |
|---|---|---|
| Templates (11) | 413 | 31 (7%), plus 29 partial |
| Authors 1–8 (v2) | 533 | 281 (53%) |
| Hand-added authors (9–11) | 94 | 8 (9%) |

Failures cluster in W36–W40 (the embedding-credit and rate-limit period). W41–W44 had almost none, and W45 had 13. The top error, **"Post generation failed before a usable Post Content could be created" (363×, last seen today)**, is a wrapper message, so the real cause sits in `aips_history_log`. 23 failures say outright "Rate limit exceeded".

The structural risk is batching. The v2 authors make **5–6 posts per weekly run**, and every author fires on roughly the same weekday and time. With the rate limit at 40 requests per 6 minutes, and each post costing title + content + excerpt + featured image (+ slug and images in v3), one run alone can hit the limit. The three daily authors at 1–2 posts per run barely fail.

v3: switch authors to daily runs of 1 post (same weekly volume), stagger run times, and pull a sample of failed history logs before finalising.

### 6. Developer Tooling template: 28 of 39 runs "partial"
That rate is unique to this template. It is the only one with `featured_image_source = unsplash` (with `generate_featured_image = 0`) and the only active one with `include_sources = 0`. The cause needs a look at one partial run's log. Most likely suspect: the Unsplash config, or the 4-post batch.

### 7. New features: unused or switched off
| Feature | State |
|---|---|
| AI slugs | 0 of 11 templates, 0 of 11 authors |
| In-content post images | 0 configs (`aips_post_images` empty) |
| Featured images | 6 of 11 templates; **0 of 11 authors** |
| Per-template title prompts | All 11 share one generic line |
| AI Edit / AI assistance | Enabled, guidance **blank**, used twice |
| Affiliate links | 0 rules, although a site goal is "drive affiliate revenue" |
| Related posts | Disabled (silo guide is on) |
| `aips_allowed_image_sources`, `aips_max_post_images_limit` | Not stored (defaults) |
| Telemetry | Off (0 rows) |
| Trending topics research | 0 rows (research niches are configured) |

Already on and working (keep as is): embeddings, link index (23k rows), autolinking (1,147 internal links, apply mode, thresholds 0.70/0.85), silo guide, duplicate scan (daily, 94 groups), notification digests, retry/circuit breaker/rate limiting, cache.

---

## Drift: live vs the v2 seed (v3 must keep these)

- **Authors:** 3 added by hand: *Marcus Vance* (cloud-native/distributed backend, daily), *Elena Rostova* (frontend architecture/DX, daily), *Devon Miller (multi post)* (DevSecOps, daily, 2 posts per topic, score ≥ 65 approval). These are the best performers. Their custom `topic_generation_prompt`s should become the pattern for the v2 authors.
- **Templates:** `post_status = publish` (v2 seeded draft), `post_quantity` 3–4 (v2 seeded 1), Developer Tooling switched to Unsplash, AI for Developers turned sources on.
- **Settings:** about 150 options tuned in the admin (embeddings limits, autolink, silo, duplicate scan, digests, token limits, temperature 0.7, `aips_default_post_status = publish`, rate limit 40 per 360 s, history retention 60 days, auto-approval globals). v3 only changes the settings it means to change.
- **Post slices:** 19 (v2 seeded 4). 15 are AI-agent themed (beginner onboarding … production rollout mistakes), plus 4 footer/disclaimer slices.
- **Structures:** 17 (v2: 11; IDs 1–6 are the plugin defaults).
- **Sources:** 5 (one v2 source, ID 4, was deleted).
- **Authors without a category:** Code Quality & Refactoring Craftsman, Marcus Vance, Elena Rostova, Devon Miller. Their posts go to the default category (172 Backend Development).
- **Stray data:** author_id 999901 has 3 runs (stress-test leftovers). 46 runs have no template or author.

**Volume:** templates are 11 weekly schedules × 3–4 posts ≈ 41 a week. Authors are 8 × 4–6 weekly plus 3 daily (1 + 1 + 2 a day) ≈ 70 a week. That is **≈ 110 planned vs ≈ 70–100 completed** a week recently. v2's `DISTRIBUTION_CONFIG` (42 a week) no longer describes the site.

---

## Approved v3 decisions (implemented in `scripts/setup-devstacktips-content.php` v3)

1. **Voices:** the template edit-form bug is fixed, and v3 puts the v2 voice back on each template.
2. **Structures on schedules:** comparison → #9, security guide → #11, security news → #14, architecture → #15, refactoring → #16, testing → #17, tooling → #12, AI → #13, backend → #8, beginner → #7, PHP ecosystem → #8. No `rotation_pattern`: it cycles through every active structure (listicles included), not a chosen subset.
3. **Sources:** every template with sources on gets a group. Two new groups: *Backend & Architecture* and *AI & Dev Tooling*.
4. **Topic approval:** v2 authors use `score` mode, min score 85, fallback `pending`. Devon Miller keeps his own settings. The indexer queue pause is left to you.
5. **Author cadence:** v2 authors run daily with 1 post per run, staggered 25 minutes apart. Topic generation goes up to 8 a week to match.
6. **Slugs on everywhere,** with a `slug_prompt` per content type (3–6 words, keyword first, no dates or stop words).
7. **Images (no Unsplash: no API key):** featured AI images on for every template and author, in one house style. Developer Tooling moves from Unsplash to AI. In-content AI diagrams on tutorial, backend, architecture, refactoring and testing templates (1–2, `after_section_2` / `after_section_4`). None on news, briefings or comparisons. `aips_allowed_image_sources` = ai_prompt and media_library; `aips_max_post_images_limit` = 3.
8. **Title prompts per template.**
9. **AI Edit:** `aips_ai_assistance_guidance` is written from the site voice and guidelines.
10. **Not changed (your call later):** affiliate link rules, related posts block, telemetry.
11. **Rollout safety:** `wp eval-file … apply drafts` saves template posts as drafts. Plain `apply` keeps your `publish` status.

## AIPS code fixes shipped with v3
- **Template edit form now keeps the voice** (`assets/js/admin.js`).
- **The real failure reason is kept:** a failed content call keeps the provider error in the run's error message and on the returned `WP_Error` (`class-aips-generator.php`).
- **Author batches stop on system-wide errors:** a run stops after `rate_limit_exceeded` or `circuit_breaker_open` instead of failing every remaining topic (`class-aips-author-post-generator.php`).
- **Topic approval no longer rejects unmeasured topics:** unmeasurable relevance stays `pending` whatever the fallback. Each topic-generation run re-checks up to 20 pending topics that were never evaluated or couldn't be measured (`class-aips-similarity-evaluator.php`, `class-aips-author-topics-generator.php`).

## Still unknown (needs one more look)
- The real cause behind the earlier "failed before a usable Post Content" errors and the Developer Tooling partials. New runs record the provider error, so History shows it from now on.
