# Research Feature — Analysis & Improvement Roadmap

## Current State — What's Working

The existing architecture is clean: a controller/service/repository split is respected, gap analysis is separated into `AIPS_Content_Auditor`, and the cron hook integration follows the plugin's established patterns. The fallback parser for malformed AI JSON is a nice defensive touch.

---

## Current State — Design Smells & Gaps

### 1. The AI doesn't actually know what's trending

`build_research_prompt` asks the AI to reason about current trends, but AI models have a knowledge cutoff and no internet access. The output is plausible-sounding but not grounded in real-time signal. This is the most significant limitation — you're getting informed guesses, not actual trend data.

### 2. No "inbox" state — auto-research fires and forgets

`run_scheduled_research` saves topics directly into the live library with no `pending` status. Admin gets no notification, has no visibility that new topics arrived, and can't review before they become schedulable. This means scheduled research silently accumulates noise.

### 3. Deduplication is implemented but not called

`AIPS_Trending_Topics_Repository::topic_exists()` exists but `save_research_batch` doesn't invoke it. Repeated runs on the same niche will produce duplicate topic rows.

### 4. Only one action on a topic: schedule it

The workflow ends at "schedule for generation using existing template." There's no path to create a post immediately, assign to an Author, seed a new Template, or generate an Author persona from the topic signal.

### 5. Research configuration is not manageable from the UI

The `aips_research_niches` option is a raw `get_option` with no admin UI to add/remove/configure niche profiles. Scheduled research is effectively unconfigurable from the plugin.

### 6. Template re-instantiates the controller at render time

`research.php` line 15: `$research_controller = new AIPS_Research_Controller()` — this is the legacy anti-pattern the plugin's own conventions warn against. The constructor registers AJAX hooks again on every page render.
---

## Recommended Improvements

### Phase 1 — Fix existing foundations (low risk, high value)

#### 1.1 Add `status` column to `aips_trending_topics`

```sql
status ENUM('pending', 'approved', 'dismissed', 'scheduled') NOT NULL DEFAULT 'pending'
```

Manual research runs → status `approved` (admin triggered it intentionally).  
Scheduled/auto runs → status `pending` (needs review).  
This single addition unlocks the whole review-inbox concept.

#### 1.2 Fire a notification when scheduled research produces new topics

In `run_scheduled_research`, after saving, push a record via `AIPS_Notifications_Repository` so the admin bar badge lights up. Follow the existing `notification_context` meta pattern.

#### 1.3 Fix deduplication — call `topic_exists()` inside `save_research_batch`

This is a correctness bug. The method exists; it's just not wired up.

#### 1.4 Move controller instantiation out of `research.php`

`AIPS_Research_Controller` is already instantiated during bootstrap. The template should call `get_research_stats()` through the already-bootstrapped instance via a static accessor or a global reference, not re-instantiate it.

---

### Phase 2 — The Research Inbox (medium effort, very high value)

A dedicated **Research Inbox** tab on the Research page showing `pending` topics with per-item actions:

| Action | What it does |
|---|---|
| **Approve** | Sets `status = approved`, topic becomes visible in the library |
| **Generate Post Now** | One-click post generation using a chosen template |
| **Schedule for Generation** | Existing flow, unchanged |
| **Assign to Author** | Adds this topic to an existing `aips_author_topics` queue |
| **Create Author from Topic** | AI generates an author persona aligned to the topic's niche (calls `AIPS_Author_Suggestions_Service`) |
| **Create Template from Topic** | AI drafts a new Template structure suited to this content type |
| **Dismiss** | Sets `status = dismissed`, removes from inbox |

This makes the auto-research cron genuinely useful — it now produces a curated queue the admin acts on rather than a growing unreviewed library.
---

### Phase 3 — Multiple Research Sources (high value, requires new architecture)

The fundamental fix for "AI guessing trends" is to bring in real-world signal. Introduce an interface:

```php
// interface-aips-research-source.php
interface AIPS_Research_Source {
    public function get_label(): string;
    public function fetch( string $niche, int $count, array $options = array() ): array|WP_Error;
}
```

Then implement concrete sources. The AI service becomes one source among several:

#### `AIPS_AI_Research_Source` (current behavior, refactored)

Unchanged prompt-based generation. Good for evergreen topic brainstorming.

#### `AIPS_RSS_Research_Source`

Admin configures RSS feed URLs per niche. The source fetches the feeds, extracts titles/descriptions from the last N days, then passes them to the AI: *"Given these recent titles from industry feeds, identify the 10 most interesting angles that haven't been over-covered."* Now you have real-world signal + AI synthesis.

#### `AIPS_Site_Gap_Research_Source`

Queries published `wp_posts` titles, passes them to AI with the prompt *"Given these existing post titles, identify 10 high-value related topics that are not yet covered."* This is a self-referential gap analysis that feeds directly into the content strategy. `AIPS_Content_Auditor` already has scaffolding for this — it should become a proper source.

#### `AIPS_Seasonal_Research_Source`

Uses a calendar of industry-relevant dates + upcoming seasons. Generates a research run automatically 2–3 weeks ahead of seasonal windows without manual triggering.

#### `AIPS_Sitemap_Competitor_Source` *(future)*

Admin enters a competitor sitemap URL. Source parses it, extracts slugs/titles, passes to AI for gap analysis.

The controller/service stays unchanged in shape — `AIPS_Research_Service` becomes a dispatcher that routes to the appropriate source by name.

---

### Phase 4 — Research Profiles (replaces raw `aips_research_niches` option)

A new `aips_research_profiles` table stores structured per-niche configuration:

| Column | Purpose |
|---|---|
| `niche` | The niche string |
| `source` | Which source to use (`ai`, `rss`, `site_gap`, `seasonal`) |
| `frequency` | How often to auto-run |
| `topic_count` | Topics to fetch per run |
| `auto_approve_threshold` | Topics with `score >=` this are auto-approved; others stay `pending` |
| `default_template_id` | Pre-fill the template when scheduling from Inbox |
| `default_author_id` | Route approved topics to a specific Author |
| `notify_on_results` | Whether to push an admin bar notification |
| `rss_feeds` | JSON array of feed URLs (used by RSS source) |
| `is_active` | Enable/disable without deleting |

A new **Research Settings** tab on the Research page manages these profiles with the same CRUD UI pattern used by Templates and Voices.

---

## Summary of New / Changed Classes

| Class | Type | Action |
|---|---|---|
| `AIPS_Research_Source` | Interface | New — defines the source contract |
| `AIPS_AI_Research_Source` | Service | New — wraps current `AIPS_Research_Service` behavior |
| `AIPS_RSS_Research_Source` | Service | New — RSS fetch + AI synthesis |
| `AIPS_Site_Gap_Research_Source` | Service | New — audit existing posts for gaps |
| `AIPS_Seasonal_Research_Source` | Service | New — calendar-driven research |
| `AIPS_Research_Service` | Service | Refactor into source dispatcher |
| `AIPS_Research_Profiles_Repository` | Repository | New — replaces `get_option('aips_research_niches')` |
| `AIPS_Research_Controller` | Controller | Extend: add inbox AJAX, profile CRUD, remove render-time instantiation |
| `AIPS_Trending_Topics_Repository` | Repository | Fix: call `topic_exists()` in `save_research_batch`, add `status` filter |
| `AIPS_DB_Manager` | Manager | Add `status` column migration + `aips_research_profiles` table |

---

## Recommended Sequencing

1. **Phase 1** first — deduplication fix + status column + notification are contained changes with no UX risk.
2. **Phase 2** (Inbox) before Phase 3, because it gives the auto-research system a meaningful output path. The Inbox is the highest-value UX change relative to effort.
3. **Phase 3** sources can ship incrementally — AI source first (just a rename/refactor), RSS source second, gap source third.
4. **Phase 4** profiles replace the settings option once at least two sources are live and the admin needs per-niche configuration.