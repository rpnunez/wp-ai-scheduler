---
applyTo: "ai-post-scheduler/includes/class-aips-*scheduler*.php,ai-post-scheduler/includes/class-aips-*cron*.php,ai-post-scheduler/includes/class-aips-*generator*.php,ai-post-scheduler/includes/class-aips-*prompt*.php,ai-post-scheduler/includes/class-aips-generation-*.php"
---

Lane: **Cron + generation pipeline** (`cron`, `generation-pipeline`)

- Keep scheduling/retry logic idempotent and safe for duplicate trigger execution.
- Keep generation flow changes scoped and covered by targeted tests.
- Record important lifecycle events using existing history/logging services.
- Prefer repository/service boundaries; avoid direct SQL in scheduler/controller classes.
- Apply `cron` and/or `generation-pipeline` labels for lane ownership.
