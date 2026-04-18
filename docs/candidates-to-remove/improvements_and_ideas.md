# AI Post Scheduler - Improvements and Ideas

Updated: 2026-03-08

This document combines and reconciles:
- `docs/improvements.md` (detailed, technical plain-language recommendations)
- `docs/improvements-codex.md` (practical product-quality tracker)

It is organized as a single planning source with:
1. Unified feature-area improvements
2. A practical checklist tracker
3. Consolidated new feature ideas
4. Prioritization notes

---

## 1. Content Generation

### What this area covers
Templates, voices, article structures, prompt processing, generation pipeline, and regeneration tools.

### Unified improvements
- Add a **Template Test Drive / Dry Run** mode that performs real generation without publishing or saving.
- Add inline prompt guidance and reusable **prompt blocks** (intro, CTA, FAQ, summary).
- Add a pre-generation quality checklist (clarity, audience fit, tone, CTA, structure).
- Add one-click section improvement actions (title, intro, body, conclusion).
- Add side-by-side comparison for regenerated variants and A/B-style content previews.
- Add template organization with folders/tags/favorites and usage analytics.
- Add template versioning for revision tracking and performance comparison.
- Add consistency checks for voice/tone/structure alignment across full output.
- Add smart partial recovery so failures in one step (for example image generation) do not discard successful text output.
- Add more mid-generation hooks/filters between pipeline steps for advanced customization.

### Practical tracker
- [ ] Template Test Drive mode
- [ ] Inline prompt guidance
- [ ] Pre-generation quality checklist
- [ ] One-click section improvement actions
- [ ] Template folders/tags/favorites
- [ ] Voice/tone/structure consistency checker
- [ ] Reusable prompt blocks
- [ ] Side-by-side compare mode

---

## 2. Scheduling and Automation

### What this area covers
Cron-driven generation, schedule creation/management, run controls, and automation safety.

### Unified improvements
- Add true **multiple posts per run** support with per-schedule post count controls.
- Add schedule presets (Daily Blog, Weekly Deep Dive, Weekend Roundup).
- Add natural-language schedule setup (for example "every weekday at 8 AM").
- Add conflict detection/warnings for stacked schedules.
- Add smart pacing to spread heavy generation workloads.
- Add missed-run recovery for low-traffic sites.
- Add conditional scheduling (weekday-only, publish caps, custom rules).
- Add pause/resume state separate from enabled/disabled.
- Add per-schedule inline run history with success/failure details.
- Add quiet hours and skip rules (holidays/specific dates).
- Add schedule-level notifications with clear next actions.
- Add dry-run preview of what a schedule will generate before activation.

### Practical tracker
- [ ] Schedule presets
- [ ] Natural-language schedule builder
- [ ] Conflict warnings
- [ ] Smart pacing
- [ ] Success/failure notifications with next actions
- [ ] Calendar drag-and-drop rescheduling
- [ ] Quiet hours and skip rules
- [ ] Schedule dry run

---

## 3. Content Planning and Research

### What this area covers
Topic research, planning boards, author topics, trend intake, and scheduling handoff.

### Unified improvements
- Add topic clustering and duplicate/similarity warnings.
- Add content-gap detection against existing site coverage.
- Add freshness signals for timely prioritization.
- Add a monthly plan wizard with balanced content mix.
- Add planner scoring (evergreen/timely/strategic balance).
- Add reusable planning boards by niche/campaign/season.
- Add one-flow path from research to approved schedule.
- Optionally enrich research with external data sources (for example trend/news feeds).

### Practical tracker
- [ ] Topic clustering
- [ ] Content-gap suggestions
- [ ] Freshness indicators
- [ ] Monthly plan wizard
- [ ] Duplicate/similarity warnings
- [ ] Planner score
- [ ] Reusable planning boards
- [ ] Research-to-schedule shortcut

---

## 4. Monitoring, Review, and Management

### What this area covers
Dashboard views, history/activity, post review workflow, quality confidence signals, and operator recovery.

### Unified improvements
- Add an executive dashboard with priority-at-a-glance KPIs.
- Add failure triage grouped by root cause and urgency.
- Add post-level quality signals and confidence indicators before publish.
- Add quick recover actions (retry, regenerate section, switch template).
- Add quality/approval/edit-effort trends over time.
- Add explicit workflow statuses (Generated, Needs Review, Approved, Ready to Publish).
- Add repeated-failure alerts by template/author/schedule.
- Add compact daily digest summary for administrators.
- Add inline lightweight editing in review queues.
- Add review deadline reminders.

### Practical tracker
- [ ] Attention-first executive dashboard
- [ ] Root-cause failure triage
- [ ] Pre-publish quality signals
- [ ] Quick recover actions
- [ ] Quality and approval trend views
- [ ] Workflow statuses
- [ ] Repeated-failure alerts
- [ ] Daily digest summary

---

## 5. System Tools and Data Management

### What this area covers
Settings, diagnostics, backup/import/export, migration flows, and operational safety.

### Unified improvements
- Add first-run onboarding wizard.
- Add settings profiles (Blogger, News, Agency, E-commerce).
- Complete JSON import/export with clear validation and compatibility warnings.
- Add selective backup/restore by feature area.
- Add export/import summary and conflict resolution options (merge/skip/overwrite).
- Add pre-flight checks before destructive actions.
- Add one-click diagnostics with plain-language recommendations.
- Add scheduled backups with retention controls.
- Add site-to-site migration assistant.

### Practical tracker
- [ ] First-run setup wizard
- [ ] Settings profiles
- [ ] Full JSON import/export parity
- [ ] Selective backup/restore
- [ ] Pre-flight safety checks
- [ ] One-click diagnostics
- [ ] Scheduled backups with retention
- [ ] Migration assistant

---

## 6. Developer Experience and Extensibility

### What this area covers
Hooks/events, diagnostics, seed data, debug guidance, extension recipes, and controlled rollouts.

### Unified improvements
- Add richer hook/event examples for common extension patterns.
- Add extension recipes (custom prompt filters, custom scheduling logic).
- Add a safer sandbox mode for generation testing.
- Add richer seed profiles (small demo, medium realistic, large stress).
- Add integration health diagnostics and troubleshooting guidance.
- Add clearer "why this failed" hints in plain language.
- Add compatibility checklist for third-party themes/plugins.
- Add lightweight feature-flag controls.

### Practical tracker
- [ ] Hook/event examples
- [ ] Extension recipes
- [ ] Sandbox mode
- [ ] Rich seed profiles
- [ ] Integration diagnostics page
- [ ] Compatibility checklist
- [ ] Plain-language failure hints
- [ ] Feature flag panel

---

## 7. User Interface and Workflow UX

### What this area covers
Admin navigation, consistency across pages, editing ergonomics, and operator speed.

### Unified improvements
- Add consistent search/filter controls across all list pages.
- Add drag-and-drop ordering for key list entities.
- Add side-by-side AI edit comparison and safer commit UX.
- Add keyboard shortcuts for common actions.
- Improve bulk action feedback with per-item success/failure summary.
- Continue evolving calendar UX for full drag-and-drop planning.

---

## 8. AI Integration and Quality Controls

### What this area covers
Provider integration, model control, cost visibility, confidence checks, and response behavior.

### Unified improvements
- Add per-template model selection.
- Add token usage tracking per run and aggregate.
- Add multi-provider abstraction/fallback support.
- Add self-evaluation quality scoring and low-score routing to review.
- Add streaming response preview where provider supports it.
- Add source-aware/citation-capable generation options.

---

## 9. Database and Performance Operations

### What this area covers
Schema growth, retention, indexing, query performance, and operational observability.

### Unified improvements
- Add database health dashboard (size, row counts, cleanup status).
- Add configurable retention policies per data domain.
- Add soft-delete/archive capability where appropriate.
- Add slow-query visibility in debug/dev tools.
- Review and optimize indexes for high-growth tables.

---

## Consolidated New Feature Ideas

1. Content Quality Gate / AI Content Quality Grader
2. SEO Optimization Assistant and Intent Mapper
3. Campaign and Series Builder (pillar/cluster strategy)
4. Content Repurposing Studio/Engine (article to social/email/FAQ variants)
5. Audience Persona Packs / Persona Targeting
6. Internal Linking Assistant (suggestions or auto-insert)
7. Human-in-the-loop review flows (writer -> editor -> approver)
8. Brand Voice Training Workspace
9. Multi-language generation from a single run
10. Multi-site content orchestration
11. Performance learning loop from post outcomes
12. Content compliance guardrails and policy checks
13. Source-aware research with optional citation insertion
14. Competitor and niche content analysis
15. Unified content calendar with manual+AI post integration
16. Slack/email digest notifications for review queues
17. A/B content variant testing with winner promotion
18. Plugin audit log for administrative action traceability

---

## Prioritization Notes

- Prioritize improvements that reduce manual editing and review time first.
- Prioritize features that increase predictability and quality at publish time.
- Prioritize workflows that help non-technical users move from idea to approved post quickly.
- Prioritize operational safety and diagnostics for reliable automation at scale.

---

## Source Note

This file is a merged planning artifact based on:
- `docs/improvements.md`
- `docs/improvements-codex.md`
