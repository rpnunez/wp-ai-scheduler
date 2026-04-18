# AI Post Scheduler — Feature Reference

**Plugin Version:** 1.7.3  
**Total Classes:** 128  
**Total Lines of Code:** ~44,000  
**Feature Categories:** 9  
**Admin Pages:** 15  
**Custom DB Tables:** 13  
**Cron Jobs:** 6  
**AJAX Endpoints:** 50+  
**PHPUnit Test Cases:** 62+

---

## Table of Contents

1. [Core Generation](#1-core-generation)
2. [Content Management](#2-content-management)
3. [Author & Topic Workflows](#3-author--topic-workflows)
4. [Scheduling & Automation](#4-scheduling--automation)
5. [AI Integration](#5-ai-integration)
6. [Post Review & Publishing](#6-post-review--publishing)
7. [Research](#7-research)
8. [History & Observability](#8-history--observability)
9. [Data, Settings & Admin UI](#9-data-settings--admin-ui)
10. [MCP Bridge](#10-mcp-bridge)
11. [Cron Jobs](#cron-jobs)
12. [Database Tables](#database-tables)

---

## 1. Core Generation

The generation pipeline produces WordPress posts using AI. It supports both template-driven and author/topic-driven workflows.

### Template-Based Generation (`AIPS_Generator`)
- Generates post title, content, and excerpt from a template prompt
- Featured image generation via AI (DALL-E / Meow AI Engine) or Unsplash
- Template variable processing (see [Content Management](#2-content-management))
- Retry logic with exponential backoff and circuit breaker for API failures

### Context Architecture
Generation is abstracted through a context interface, making the pipeline provider-agnostic:

| Class | Purpose |
|-------|---------|
| `AIPS_Generation_Context` | Interface defining the generation contract |
| `AIPS_Template_Context` | Wraps template-based generation configuration |
| `AIPS_Topic_Context` | Wraps author/topic-based generation configuration |
| `AIPS_Generation_Context_Factory` | Reconstructs generation contexts for regeneration flows |

### Generation Session Tracking
| Class | Purpose |
|-------|---------|
| `AIPS_Generation_Session` | Tracks a generation session (multi-post runs) |
| `AIPS_Generation_Result` | DTO carrying the outcome of a single generation |
| `AIPS_Bulk_Generation_Result` | Aggregates results across a bulk run |
| `AIPS_Generation_Execution_Runner` | Orchestrates multi-post generation with locking |
| `AIPS_Generation_Logger` | Writes structured log entries during generation |

### Author Post Generation
- `AIPS_Author_Post_Generator` — generates posts from approved author topics
- `AIPS_Author_Topics_Generator` — generates new topic ideas for an author using AI
- `AIPS_Author_Suggestions_Service` — generates full author profile suggestions from site context

### Partial Generation Recovery
When a generation session completes partially (e.g., title generated but content fails):

| Class | Purpose |
|-------|---------|
| `AIPS_Partial_Generation_State_Reconciler` | Identifies posts with incomplete generated components |
| `AIPS_Partial_Generation_Notifications` | Surfaces incomplete generations to the admin bar |
| `AIPS_Component_Regeneration_Service` | Regenerates individual components (title, content, image) using prior context |
| `AIPS_Session_To_JSON` | Exports generation sessions to JSON; cleans old export files via cron |

---

## 2. Content Management

### Template System
Templates are reusable prompts for generating posts.

- Create, edit, clone, and delete templates
- Prompts for post title, content, and excerpt
- Voice assignment for tone/style
- Post settings: status, category, tags, author
- Featured image configuration (AI, Unsplash, or none)
- Test Generate (preview without saving)
- View all posts generated from a template

**Admin page:** AI Post Scheduler → Templates  
**Key classes:** `AIPS_Templates`, `AIPS_Templates_Controller`, `AIPS_Template_Repository`, `AIPS_Template_Data`

### Template Variables (`AIPS_Template_Processor`)
Built-in system variables replaceable inside any prompt:

| Variable | Value |
|----------|-------|
| `{{date}}` | Current date (Y-m-d) |
| `{{year}}` | Current year |
| `{{month}}` | Current month |
| `{{day}}` | Current day |
| `{{time}}` | Current time (H:i) |
| `{{site_name}}` | WordPress site name |
| `{{site_description}}` | WordPress site tagline |
| `{{random_number}}` | Random integer |
| `{{topic}}` | Topic assigned to the schedule/author topic |
| `{{title}}` | Previously generated title (for content/excerpt prompts) |

**AI Variables:** Custom placeholders like `{{ProductAngle}}` are resolved by a secondary AI call before the main generation.

### Voices (Writing Styles)
Define reusable writing personas that shape tone and style.

- Create, edit, delete voice profiles
- Assign to templates and author personas
- Voice text injected into the generation prompt

**Key classes:** `AIPS_Voices`, `AIPS_Voices_Repository`

### Article Structures
Define post structure blueprints injected into generation prompts.

- Ordered sections with headings and guidance
- Assigned per template or generation context
- Preview and test within the admin

**Key classes:** `AIPS_Article_Structure_Manager`, `AIPS_Article_Structure_Repository`, `AIPS_Prompt_Builder_Article_Structure_Section`

### Prompt Sections
Reusable prompt blocks merged into generation prompts.

- Named, categorized prompt fragments
- Tag-based filtering for discovery
- Referenced by templates or authors

**Key classes:** `AIPS_Prompt_Sections_Controller`, `AIPS_Prompt_Section_Repository`

### Prompt Builders
Orchestrate prompt assembly from components:

| Class | Scope |
|-------|-------|
| `AIPS_Prompt_Builder` | Shared/base prompt assembly |
| `AIPS_Prompt_Builder_Topic` | Author/topic prompt composition |
| `AIPS_Prompt_Builder_Authors` | Author profile suggestion prompts |
| `AIPS_Prompt_Builder_Post_Title` | Title generation prompts |
| `AIPS_Prompt_Builder_Post_Content` | Content generation prompts |
| `AIPS_Prompt_Builder_Post_Excerpt` | Excerpt generation prompts |
| `AIPS_Prompt_Builder_Post_Featured_Image` | Image description prompts |
| `AIPS_Prompt_Builder_Taxonomy` | Taxonomy/category prompt enrichment |

---

## 3. Author & Topic Workflows

A persona-driven content pipeline where AI authors generate topic ideas, users approve them, and posts are auto-generated.

### Author Profiles
- Full author persona: bio, expertise, writing style, voice
- Strategy fields: target audience, expertise level, content goals, excluded topics, preferred content length, language, max posts per topic
- Per-author generation settings (post status, categories, schedule frequency)
- AI-generated profile suggestions via `AIPS_Author_Suggestions_Service`

**Key classes:** `AIPS_Authors_Controller`, `AIPS_Authors_Repository`

### Author Topics
1. AI generates topic ideas for each author on a schedule
2. Topics surface in the Author Topics page for review
3. Approved topics are queued for post generation
4. Posts are generated and linked back to the topic log

**Approval workflow:** Pending → Approved / Rejected (with optional feedback)

**Key classes:** `AIPS_Author_Topics_Controller`, `AIPS_Author_Topics_Repository`, `AIPS_Author_Topic_Logs_Repository`, `AIPS_Feedback_Repository`

### Search & Filtering
- Author and topic tables have client-side search
- Filter by status (pending/approved/rejected), author, and date range

---

## 4. Scheduling & Automation

### Template Schedules
- Attach a schedule to any template: hourly, daily, weekly, bi-weekly, monthly, or day-specific (every Monday, etc.)
- Set start time, topic, post quantity per run
- Enable/disable without deleting
- Next run time computed and stored at schedule creation/update
- Locking prevents duplicate runs during overlapping cron triggers

**Key classes:** `AIPS_Scheduler`, `AIPS_Schedule_Processor`, `AIPS_Schedule_Controller`, `AIPS_Schedule_Repository`, `AIPS_Schedule_Entry`

### Interval Calculator (`AIPS_Interval_Calculator`)
- All frequency definitions and `next_run` math are centralized here
- Supports: hourly, every N hours, daily, weekly, bi-weekly, monthly, day-specific
- Merges custom intervals with WordPress's native cron schedule list

### Author Topics Scheduler (`AIPS_Author_Topics_Scheduler`)
- Runs hourly to generate new topics for authors whose generation schedule is due
- Per-author frequency settings (daily, weekly, etc.)
- Respects per-author max-topics-per-run limits

### Author Post Generator (`AIPS_Author_Post_Generator`)
- Runs hourly to publish posts from the approved-topics queue
- Processes up to a configured batch size per cron run

### Unified Schedule View (`AIPS_Unified_Schedule_Service`)
Aggregates all schedule types (template, author topics, author posts) into one normalized list for the Schedule admin page. Fields across types are normalized for display.

### Schedule Calendar
Visual calendar showing upcoming scheduled generation runs.

**Admin pages:** AI Post Scheduler → Schedule, AI Post Scheduler → Schedule Calendar

---

## 5. AI Integration

### AI Service Layer (`AIPS_AI_Service`)
Central orchestration layer between the plugin and Meow Apps AI Engine:

- Abstracts the `Meow_MWAI_Core` runtime dependency
- Handles text generation (chat completions) and image generation
- Encapsulates API request/response formatting

### Resilience Service (`AIPS_Resilience_Service`)
Extracted from `AIPS_AI_Service` to separate reliability concerns:

- Circuit breaker (trips after N consecutive failures; auto-resets)
- Rate limiter (request-per-minute cap)
- Retry logic with exponential backoff

### AI Edit (`AIPS_AI_Edit_Controller`, `AIPS_Component_Regeneration_Service`)
Post-generation editing via AI:

- Regenerate individual post components: title, content, excerpt, or featured image
- Regeneration uses the original generation context (template or topic) for consistency
- Revision history: each regeneration creates a new WordPress revision
- Revision viewer to compare and restore prior versions

**Admin surface:** Accessible from the Generated Posts and Post Review interfaces

---

## 6. Post Review & Publishing

### Generated Posts (`AIPS_Generated_Posts_Controller`)
Central view for all AI-generated content:

- **Generated Posts tab:** All published/draft posts from the plugin
- **Pending Review tab:** Posts awaiting manual approval before publishing
- **Partial Generations tab:** Posts where one or more components failed to generate
- Per-tab search, filter by template/author/status, and bulk actions
- Post preview without leaving the admin
- "Clear Filters" button appears contextually when filters are active

### Post Review Flow (`AIPS_Post_Review`)
For templates configured to generate draft posts:

- Review queue with inline post preview
- Approve (publish), reject (trash), or edit before approving
- Email notification system for notifying editors of pending reviews
- Bulk approve/reject

### Export Session to JSON (`AIPS_Session_To_JSON`)
- Export a full generation session to JSON for external auditing
- Files protected with `.htaccess` + `index.php` sentinel
- Old export files auto-deleted via `aips_cleanup_export_files` cron

### Post Manager (`AIPS_Post_Manager`)
Abstraction layer for WordPress post CRUD operations used by the generation pipeline. Legacy alias: `AIPS_Post_Creator`.

---

## 7. Research

AI-assisted content planning and trending topic discovery.

### Trending Topics (`AIPS_Research_Controller`, `AIPS_Trending_Topics_Repository`)
- Run research queries to surface trending topics in a niche
- Topics scored and stored for review
- Select topics to assign to templates or author pipelines
- "Select All" respects active search filter (bulk action UX)
- Library view with persistent topic storage

### Research Planner (`AIPS_Planner`)
A scratchpad interface for planning content strategies:

- Brainstorm and organize topic lists
- Copy selected topics to clipboard
- Clear list with confirmation dialog
- Filter with client-side search

**Admin pages:** AI Post Scheduler → Research

---

## 8. History & Observability

### History System (`AIPS_History_Service`, `AIPS_History_Container`)
Structured lifecycle logging for all meaningful operations:

- Each recorded event has a type, status, metadata, and linked post ID
- Types: Template Generation, Author Topic Generation, Author Post Generation, Manual Regeneration, Component Regeneration, Research Run, etc.
- Full event log available in the History admin page
- Bulk delete by status
- Filterable by type, date range, and status

**Key classes:** `AIPS_History_Service`, `AIPS_History_Container`, `AIPS_History_Repository`, `AIPS_Generation_Logger`, `AIPS_History_Type`

### Notifications (`AIPS_Notifications_Repository`, `AIPS_Admin_Bar`)
- Notification system surfaced in the WordPress admin toolbar
- Notification types: partial generation alerts, review queue alerts, system errors
- Read/dismiss individual or bulk notifications
- Live notification count in the toolbar dropdown

### System Status (`AIPS_DB_Manager`)
- System Status admin page showing plugin health
- DB table status (exists, row counts)
- AI Engine dependency check
- Version and environment info

**Admin page:** AI Post Scheduler → System Status

---

## 9. Data, Settings & Admin UI

### Settings (`AIPS_Settings`, `AIPS_Settings_UI`, `AIPS_Settings_AJAX`)
- Central plugin settings page
- Content strategy options (site-wide content goals, audience, tone)
- AI Engine configuration (model selection, temperature, max tokens)
- Post review settings (enable/disable, email notifications)
- Developer mode toggle (shows Dev Tools page)
- All settings backed by `AIPS_Config` with declared defaults

### DB Manager & Upgrades (`AIPS_DB_Manager`, `AIPS_Upgrades`)
- Schema defined in `AIPS_DB_Manager::get_schema()`; applied via `dbDelta`
- Version-based upgrade runner in `AIPS_Upgrades`
- Repair and reinstall tools in System Status
- Data export (MySQL dump) and import via `AIPS_Data_Management`

### Seeder (`AIPS_Seeder_Admin`)
- Seed demo data (templates, voices, structures, authors) for quick setup
- Useful for fresh installs and CI/testing environments

**Admin page:** AI Post Scheduler → Seeder

### Admin UI (`AIPS_Admin_Assets`, `AIPS_Admin_Bar`, `AIPS_Admin_Menu`)
- All admin CSS/JS enqueued centrally by `AIPS_Admin_Assets`
- Page-specific asset bundles for heavy pages
- Responsive admin bar integration with notification dropdown
- Admin bar visible on both admin and frontend (for `manage_options` users)
- Consistent UI patterns: toast notifications, confirmation dialogs, empty states, client-side search/filter

**JS globals:**
- `AIPS.Templates.render(id, data)` — safe HTML rendering from `<script type="text/html">` templates
- `AIPS.Utilities.showToast(message, type, opts)` — styled toasts
- `AIPS.Utilities.confirm(message, heading, buttons)` — accessible modal dialogs

### Dashboard (`AIPS_Dashboard_Controller`)
- At-a-glance stats: posts generated, schedules active, pending reviews, recent errors
- Quick-access links to key admin pages
- Recent activity feed

### Dev Tools (`AIPS_Dev_Tools`)
Available only when `aips_developer_mode` is enabled in Settings:
- Manual cron trigger
- Cache and transient clearing
- AJAX registry inspector
- Raw DB query tools

---

## 10. MCP Bridge

`ai-post-scheduler/mcp-bridge.php` exposes a subset of plugin functionality via a JSON-RPC–style interface for MCP (Model Context Protocol) integration.

- Schema defined in `mcp-bridge-schema.json`
- Not auto-loaded in the standard plugin runtime — loaded on demand
- Enables external AI agents and tools to query and trigger plugin operations
- Validated by `validate-mcp-bridge.php` (CLI-only, no WordPress required)

Full MCP documentation: [docs/mcp/](mcp/)

---

## Cron Jobs

All cron hooks are registered and cleared centrally via `AI_Post_Scheduler::get_cron_events()`.

| Hook | Schedule | Purpose |
|------|----------|---------|
| `aips_generate_scheduled_posts` | hourly | Run due template schedules |
| `aips_generate_author_topics` | hourly | Generate topics for due authors |
| `aips_generate_author_posts` | hourly | Generate posts from approved topics |
| `aips_scheduled_research` | daily | Run research/trending topic collection |
| `aips_send_review_notifications` | daily | Send pending-review email notifications |
| `aips_cleanup_export_files` | daily | Delete old session JSON export files |

---

## Database Tables

All tables use the WordPress prefix. Schema source of truth: `AIPS_DB_Manager::get_schema()`.

| Table | Purpose |
|-------|---------|
| `aips_history` | Generation history records |
| `aips_history_log` | Structured history log entries |
| `aips_templates` | Prompt templates |
| `aips_schedule` | Template schedule records |
| `aips_voices` | Voice definitions |
| `aips_article_structures` | Article structure blueprints |
| `aips_prompt_sections` | Reusable prompt section blocks |
| `aips_trending_topics` | Research/trending topic results |
| `aips_authors` | Author personas and generation settings |
| `aips_author_topics` | Generated topics and approval workflow |
| `aips_author_topic_logs` | Topic-level history and post linkage |
| `aips_topic_feedback` | Approval/rejection feedback metadata |
| `aips_notifications` | Admin toolbar/system notifications |
