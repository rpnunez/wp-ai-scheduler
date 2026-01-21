# AI Post Scheduler - Current Understanding

## What the plugin is
AI Post Scheduler is a WordPress plugin that automates AI-assisted content creation and publishing using the Meow Apps AI Engine. It lets admins build reusable prompt templates and voices, schedule posts (one-off or recurring), research trending topics with AI, bulk schedule topics, and monitor generation history and activity. It stores its data in custom database tables and exposes hooks to extend generation and scheduling workflows.

## Primary use cases
- Maintain an automated content calendar with AI-generated posts.
- Create reusable templates and voices to standardize tone and structure.
- Bulk plan and schedule posts from brainstormed or trending topics.
- Monitor generation outcomes, retry failures, and inspect logs.
- Generate featured images via AI prompts, Unsplash, or media library.

## Core workflow (generation + scheduling)
1. **Templates/Voices** define prompts, tone, and optional featured image behavior.
2. **Schedules** are created (manual or bulk) with frequency, start time, and optional topic.
3. **Cron** runs `aips_generate_scheduled_posts` hourly and processes due schedules.
4. **Generator** builds prompts, calls AI, generates content/title/excerpt, creates the post, and updates history.
5. **History** stores a permanent record of each generation, including a serialized session log.

## Key features (from README + code/docs)
- **Template Builder** with dynamic variables (`{{topic}}`, `{{date}}`, etc.) and test generation.
- **Voices** (writing personas) for title/content/excerpt guidance.
- **Article Structures & Prompt Sections** to assemble structured, varied outlines. Rotation patterns are supported.
- **Scheduling** (hourly, 4h, 6h, 12h, daily, weekly, bi-weekly, monthly, once, and specific weekdays).
- **Planner** to brainstorm topics with AI, edit, select, and bulk-schedule them.
- **Trending Topics Research** with scoring, keywords, library, filters, and bulk scheduling; includes daily automated research.
- **Featured Image Sources**: AI prompt, Unsplash keyword search, or media-library selection.
- **SEO metadata application** (Yoast/RankMath) when those plugins are active.
- **Activity Feed** for schedule/post events.
- **Seeder** to populate demo data quickly.
- **System Status** for DB tables, cron health, and dependency checks.
- **Developer Mode** with dev tools (if enabled).
- **Data Management** for export/import (JSON/MySQL) via admin tooling.
- **Authors Feature** for a topic-first workflow with approvals and feedback loops (documented as partially complete UI).

## Admin UI pages (registered in `AIPS_Settings`)
- Dashboard
- Activity
- Schedule
- Templates
- Authors
- Voices
- Research (Trending Topics)
- Article Structures
- Seeder
- System Status
- Settings
- Dev Tools (optional)

## AI pipeline details
- **AIPS_Generator** orchestrates generation, logs calls, and updates history.
- **AIPS_Prompt_Builder** centralizes content/title/excerpt prompt building.
- **AIPS_Template_Processor** handles variable replacement and validation.
- **AIPS_AI_Service** wraps AI Engine calls and supports resilience patterns.
- **AIPS_Image_Service** handles AI image generation, Unsplash, and media library selection.
- **AIPS_Post_Creator** persists posts and applies SEO meta when supported.

## Observability & resilience
- **Generation Session** (runtime) captures detailed AI call logs, timing, and errors.
- **History** persists generation records and JSON session snapshots.
- **Retry/Backoff, Circuit Breaker, Rate Limiting** (via `AIPS_Resilience_Service` and config).
- **Logging** via `AIPS_Logger`.
- **Hooks** documented in `ai-post-scheduler/HOOKS.md`.

## Data model (custom tables)
At a high level, the plugin uses tables for:
- Templates, Schedules, Voices
- History (generation records + session log JSON)
- Article Structures & Prompt Sections
- Trending Topics
- Activity feed
- Authors, Author Topics, and Author Topic Logs

## Extensibility
The plugin exposes WordPress actions/filters for generation, scheduling, research, and prompt building (see `HOOKS.md`). This enables custom integrations, analytics, and workflow automation without modifying core code.

## Dependencies and requirements
- WordPress 5.8+
- PHP 8.2+
- Meow Apps AI Engine (required for AI generation)

## Notable implementation notes
- Schedules are “claimed” before generation to prevent concurrent runs.
- One-off schedules are deleted on success and deactivated on failure.
- Template rotation supports different structure selection patterns.
- Authors feature is documented as partially complete in UI wiring.

