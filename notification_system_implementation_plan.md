# Copilot Chat Conversation Export: Notification system implementation plan

**User:** @rpnunez
**Thread URL:** https://github.com/copilot/c/f74d816c-d840-425f-84a3-9f6112c3f5ca

## @rpnunez

Now that we have a centralized Notification system, introduced in PR # 892, create a list of potential candidates for notifications, such as when a scheduled Template generates posts, and whether they should be sent to both the DB & Email (or which one), whether it should be sent immediately/daily/weekly, along with other more "summary"-based notifications, such as "Posts Generated this Week", etc.

## Copilot

Summary: I analyzed the plugin surface (scheduler, generator, history, partial-generation, session, author topics, DB manager, notifications) and compiled a prioritized list of candidate notifications, with recommended channels (DB and/or Email), default frequency, recipients, priority, brief content shape, a suggested option/setting key, and example email subject lines. Below you'll find actionable notification definitions you can wire into the centralized Notification system introduced in PR #892.

What I did: I reviewed where events are generated (schedulers, generator, partial-generation state reconciler, history, DB manager, session services, author/topic services, admin tools) and translated likely useful user/admin signals into notification candidates with recommended default behavior and settings keys.

What’s next: implement these notification types in the Notification service, add toggles and frequency choices to Settings (class-aips-settings.php + UI), and add calls at the event emitters (scheduler, generator, author-suggestions, upgrades, db-manager, history-service). I can draft the implementation plan and/or open a PR with the definitions and example wiring if you want.

Notification candidates (grouped by type)

1) Immediate Action / Generation lifecycle (user- or author-facing)
- Name: Template Generated Post(s)
  - Trigger: A scheduled Template finishes generating N posts (complete generation).
  - Channels: DB (immediate) + Email (digest by default; immediate optional)
  - Frequency: DB: immediate; Email: default = daily digest (opt-in immediate)
  - Recipients: template author(s) and site admin(s)
  - Priority: high
  - Content: count, titles, post IDs/links, generation context (template, voice, date), any warnings
  - Setting key: aips.notification.template_generated (email: enabled/digest/instant; db: enabled)
  - Example email subject: "[AI Scheduler] 5 posts generated from 'Weekly Tips' template"
  - Notes: Email immediate can become noisy; recommend DB immediate + daily email digest by default.

- Name: Manual Generation Completed (user-initiated)
  - Trigger: A user requests generation via UI and generation finishes.
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: requesting user (author) and admin (option)
  - Priority: high
  - Content: post previews, direct edit/review links, generation metadata
  - Setting key: aips.notification.manual_generation_completed
  - Example email subject: "[AI Scheduler] Your generated post is ready — 'Draft title'"

- Name: Partial Generation Completed / Partial Content Ready
  - Trigger: Partial generation reached a saved checkpoint or component completed (e.g., only intro generated).
  - Channels: DB (immediate); Email optional (immediate or daily)
  - Frequency: immediate
  - Recipients: requesting user / assigned reviewer
  - Priority: medium
  - Content: component name, what was generated, percent complete, link to session
  - Setting key: aips.notification.partial_generation_completed
  - Example email subject: "[AI Scheduler] Partial content ready for 'Template X'"

- Name: Generation Failed
  - Trigger: Generation fails (API error, out-of-quota, timeout).
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: site admin(s), affected author
  - Priority: critical
  - Content: error message, trace/ID, template/session ID, suggested actions
  - Setting key: aips.notification.generation_failed
  - Example email subject: "[AI Scheduler] Generation failed for 'Template X' — action required"

- Name: Generation Partially Completed With Warnings
  - Trigger: Generation completed but had warnings (low-quality parts, token truncation).
  - Channels: DB + Email (immediate / digest option)
  - Recipients: author, admin (option)
  - Priority: high/medium
  - Content: what parts were affected, links to review, truncated sections
  - Setting key: aips.notification.generation_warning

2) Publishing & Review lifecycle (editor/reviewer-facing)
- Name: Generated Post Ready for Review
  - Trigger: Generated post created and marked "needs review" (or assigned to reviewer).
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: assigned reviewer(s), post author, admin
  - Priority: high
  - Content: post excerpt, link to review UI, deadlines if any
  - Setting key: aips.notification.post_ready_for_review
  - Example email subject: "[AI Scheduler] Review requested: 'Draft title'"

- Name: Generated Post Published (auto or manual)
  - Trigger: Auto-publish job publishes generated post or manual publish completes
  - Channels: DB (immediate); Email (optional; daily digest by default to admin)
  - Frequency: immediate for DB; email digest daily/weekly
  - Recipients: author, admin
  - Priority: medium
  - Content: post link, publish date/time, template used
  - Setting key: aips.notification.post_published

- Name: Generated Post Rejected by Reviewer
  - Trigger: Reviewer rejects generated content or requests re-generation
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: author, assigned generator/editor
  - Priority: high
  - Content: rejection reason, link to comments, next steps
  - Setting key: aips.notification.post_rejected

3) Scheduler & Infrastructure alerts (admin-facing)
- Name: Scheduler Missed Run / Cron Problems
  - Trigger: scheduled tasks missed or unprocessed items detected
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: site admin(s)
  - Priority: critical
  - Content: description, last successful run, affected templates/IDs, logs
  - Setting key: aips.notification.scheduler_error
  - Example email subject: "[AI Scheduler] Missed schedule run detected"

- Name: API Quota Approaching / Quota Exceeded
  - Trigger: Meow_MWAI_Core or configured provider usage approaching threshold or exceeded
  - Channels: DB + Email (immediate)
  - Frequency: immediate; also daily summary if still failing
  - Recipients: site admin(s)
  - Priority: critical
  - Content: usage numbers, limit, affected templates, next steps to increase quota
  - Setting key: aips.notification.quota_alert
  - Example email subject: "[AI Scheduler] API usage at 90%"

- Name: AI Engine Integration/Authentication Issue
  - Trigger: auth token invalid, unreachable AI Engine, 5xx errors
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: site admin(s)
  - Priority: critical
  - Setting key: aips.notification.integration_error

- Name: DB Migration/Upgrade Completed (or Failed)
  - Trigger: upgrade/migration process finishes (success or failure)
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: site admin(s), user who initiated upgrade
  - Priority: medium/critical (on failure)
  - Setting key: aips.notification.upgrade_status
  - Example email subject: "[AI Scheduler] Database migration succeeded (v1.8.0)"

4) Digest & Summary Reports (admin & authors)
- Name: Daily Digest — Posts Generated Today
  - Trigger: daily scheduler (cron)
  - Channels: Email (daily), DB (summary optionally stored)
  - Frequency: daily
  - Recipients: site admin(s) and optionally all authors
  - Priority: low/medium
  - Content: number of posts generated, top templates, failed items count, quick links
  - Setting key: aips.notification.digest_daily_generated
  - Example email subject: "[AI Scheduler] Today: 12 posts generated"

- Name: Weekly Summary — Posts Generated This Week
  - Trigger: weekly scheduler
  - Channels: Email weekly; DB summary (keep last 4)
  - Frequency: weekly
  - Recipients: admin(s), optionally authors
  - Priority: low
  - Content: weekly totals, success/failure rates, top-performing templates, API usage
  - Setting key: aips.notification.summary_weekly_generated
  - Example email subject: "[AI Scheduler] Weekly summary: 72 posts generated"

- Name: Monthly Report — Generation & Usage
  - Trigger: monthly schedule
  - Channels: Email monthly
  - Frequency: monthly
  - Recipients: site admin(s)
  - Priority: low
  - Content: monthly totals, costs/usage, token usage, quality indicators, suggestions
  - Setting key: aips.notification.report_monthly

- Name: Posts Generated by Author (Weekly)
  - Trigger: weekly schedule
  - Channels: Email weekly (author only) + DB optional
  - Frequency: weekly
  - Recipients: each author (their own count)
  - Priority: low
  - Content: count, links to drafts, top templates used
  - Setting key: aips.notification.author_weekly_activity

5) Quality / UX & Suggestion notifications (author-facing)
- Name: Author Topic Suggestions Ready
  - Trigger: author-suggestion service posts new suggestions
  - Channels: DB (immediate) + Email (weekly by default)
  - Frequency: immediate for DB; weekly email digest
  - Recipients: affected author(s)
  - Priority: low/medium
  - Content: suggested topics, confidence score, quick create links
  - Setting key: aips.notification.author_suggestions

- Name: New Template Added / Template Changed
  - Trigger: admin or seeder adds/edits templates accessible to author
  - Channels: DB + Email (optional)
  - Frequency: immediate or digest option
  - Recipients: authors who use templates, admins
  - Priority: low
  - Setting key: aips.notification.template_change

6) Operational / housekeeping (admin-only)
- Name: History Cleanup Completed
  - Trigger: scheduled or manual history pruning completes
  - Channels: DB-only (with optional email)
  - Frequency: on-run
  - Recipients: admin(s)
  - Priority: low
  - Setting key: aips.notification.history_cleanup

- Name: Component Regeneration Completed
  - Trigger: component regeneration service completes
  - Channels: DB (immediate) + Email optional
  - Frequency: immediate
  - Recipients: admin(s), affected author
  - Priority: medium
  - Setting key: aips.notification.component_regeneration

- Name: Seeder / Dev Tools Completed
  - Trigger: seeder finishes, dev-tools operations complete
  - Channels: DB-only by default; Email optional
  - Frequency: immediate
  - Recipients: admin(s)
  - Priority: low
  - Setting key: aips.notification.seeder_complete

7) Audit & Security (admin)
- Name: Notification Settings Changed
  - Trigger: admin user updates notification settings
  - Channels: DB-only (audit) + Email to admin (optional)
  - Frequency: immediate
  - Recipients: admin(s)
  - Priority: medium
  - Content: which setting changed, who changed it, timestamp
  - Setting key: aips.notification.settings_changed

- Name: Unexpected System Error (uncaught exception)
  - Trigger: plugin-level uncaught exception or fatal error (captured by an error-handler)
  - Channels: DB + Email (immediate)
  - Frequency: immediate
  - Recipients: site admin(s)/devs
  - Priority: critical
  - Setting key: aips.notification.system_error

Implementation notes and recommended schema fields
- Central DB notification record schema (fields to include)
  - id, type_key (e.g., aips.template_generated), title, message (html/text), level (info/warn/error), related_ids (post/template/session IDs), url (deep link), recipients (user ids or role), meta (json), created_at, read_at
- Email templates: use token placeholders:
  - {{site_name}}, {{recipient_name}}, {{count}}, {{template_name}}, {{post_list}} (array of title+link), {{error_details}}, {{action_link}}
- Default channel rules
  - Operational/critical alerts: DB + Email immediate by default (admin-only)
  - Generation success: DB immediate + Email digest (daily) for authors; option to enable instant email per-user/template
  - Summaries: email only (DB optional archive)
- Opt-in controls
  - Settings page: group by category (Generation, Review, Scheduler, Reports, System) with toggles per notification and frequency choices (Instant / Daily digest / Weekly digest / Off). Store per-site default and per-user override where applicable.
  - Suggested setting keys: aips.notification.<type>.email_mode = instant|daily|weekly|off ; aips.notification.<type>.db_enabled = true|false
- Recipient resolution
  - Use template author(s) if available, else post author (user ID). Fall back to site admin email list. Support multiple recipients (comma-separated or array).
- Rate limiting & suppression
  - Suppress duplicate emails for the same template/session within a short window.
  - Combine many per-post immediate events into a single digest email for the recipient.
- Hooks to wire notifications (places to call)
  - class-aips-scheduler.php — when schedule runs and finishes jobs
  - class-aips-generator.php & class-aips-author-post-generator.php — generation success/failure/partial
  - class-aips-partial-generation-state-reconciler.php — partial notifications
  - class-aips-history-service.php — historical/digest aggregation
  - class-aips-db-manager.php & class-aips-upgrades.php — migrations, db changes
  - class-aips-author-suggestions-service.php — suggestions ready
  - class-aips-component-regeneration-service.php — regeneration completed
  - class-aips-notifications-repository.php — store/read notifications
  - admin templates to display prefs: templates/admin/settings.php (or sections.php)
- Example notification type keys (canonical list)
  - aips.template_generated
  - aips.manual_generation_completed
  - aips.partial_generation_completed
  - aips.generation_failed
  - aips.post_ready_for_review
  - aips.post_published
  - aips.post_rejected
  - aips.scheduler_error
  - aips.quota_alert
  - aips.integration_error
  - aips.upgrade_status
  - aips.digest_daily_generated
  - aips.summary_weekly_generated
  - aips.report_monthly
  - aips.author_suggestions
  - aips.template_change
  - aips.history_cleanup
  - aips.component_regeneration
  - aips.seeder_complete
  - aips.settings_changed
  - aips.system_error

Minimal example notification payload (object shape)
- {
  type_key: "aips.template_generated",
  title: "3 posts generated from 'Tech Roundup'",
  message: "3 drafts were created. Click to review.",
  level: "info",
  related_ids: { template_id: 123, posts: [321,322,323], session_id: "s-abc" },
  url: "wp-admin/admin.php?page=aips-generated-posts&filter=session:s-abc",
  recipients: [user_id],
  meta: { template_name: "Tech Roundup", voice: "news", warnings: [] },
  created_at: "2026-03-24T12:34:00Z"
  }

Defaults and UX recommendations
- Always write DB notifications for important events (so users see them in the admin UI) and use email for events that require immediate human attention or digest summaries.
- Default: DB immediate for most events; email only for critical and digest reports (daily/weekly) unless user opts into instant email.
- Provide per-user overrides for email frequency for author-facing notifications.
- Provide an "unsubscribe from email" link in every automated email (per WordPress email best practices).
- Expose a "Notifications Activity" admin screen to browse DB notifications (search, filter by type, date, mark read/unread).
- Provide an API/hook so third-party integrators (Slack, webhook) can also consume notifications later.

Prioritization (starter defaults)
- Highest priority (DB + Email immediate): generation_failed, quota_alert, integration_error, scheduler_error, system_error
- High (DB immediate; Email digest default): template_generated, manual_generation_completed, post_ready_for_review, post_rejected, partial_generation_completed
- Medium/Low (Digest or DB-only): daily digest, weekly summary, monthly report, history_cleanup, seeder_complete, template_change, author_suggestions

Wrapping up / Next steps I can take for you
- I can produce the code definitions for the notification registry (PHP array or class constants), implement the settings UI (adds options + per-user overrides), and wire a first set of notifications (template_generated, generation_failed, digest job). If you want, I can draft a PR that:
  1) Adds a notification-types registry (includes keys & defaults),
  2) Adds Settings UI controls (site-level + per-user),
  3) Wires notifications into class-aips-generator.php and class-aips-scheduler.php,
  4) Adds a daily digest cron job that compiles DB notifications into an email digest.

Tell me which of the above you want implemented first (e.g., generate the registry + wiring to generator errors and template generated success), and I’ll draft the change list and code.
