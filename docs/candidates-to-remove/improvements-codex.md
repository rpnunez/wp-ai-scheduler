# AI Post Scheduler - Improvements Tracker (Codex)

Updated: 2026-03-08

This document tracks practical improvements for each major feature category.  
It is intentionally non-technical and focused on product quality, UX, efficiency, and outcomes.

---

## 1. Content Generation

### What this category does
This is the core content engine: templates, AI generation, writing voices, and article structures that shape how posts are produced.

### Suggestions & Improvements
- [ ] Add a "Template Test Drive" mode that generates a sample post without publishing or saving.
- [ ] Add inline prompt guidance so users can improve prompts while writing them.
- [ ] Show a simple quality checklist before generation (clarity, tone, target audience, CTA).
- [ ] Add one-click "Improve this section" actions for title, intro, body, and conclusion.
- [ ] Add stronger template organization with folders, tags, and favorites.
- [ ] Add a consistency checker so voice, tone, and structure stay aligned across a full article.
- [ ] Let users save reusable "prompt blocks" for intros, CTAs, FAQs, and summaries.
- [ ] Add a side-by-side compare mode for two generated versions.

---

## 2. Scheduling & Automation

### What this category does
This category automates when content gets generated and published, including recurring schedules and manual run controls.

### Suggestions & Improvements
- [ ] Add schedule presets (Daily Blog, Weekly Deep Dive, Weekend Roundup).
- [ ] Add natural-language schedule setup (example: "every weekday at 8 AM").
- [ ] Add conflict warnings when too many jobs are stacked in a short window.
- [ ] Add smart pacing so heavy generation jobs spread out automatically.
- [ ] Add schedule-level success/failure notifications with clear next actions.
- [ ] Add a visual calendar view for drag-and-drop rescheduling.
- [ ] Add "quiet hours" and skip rules for holidays or specific dates.
- [ ] Add a "dry run" option to preview what will generate before a schedule starts.

---

## 3. Content Planning

### What this category does
This category helps decide what to publish next through topic planning, trend research, and bulk topic scheduling.

### Suggestions & Improvements
- [ ] Add topic clustering so similar ideas are grouped before scheduling.
- [ ] Add content-gap suggestions (topics not yet covered on the site).
- [ ] Add freshness indicators so users can prioritize timely topics.
- [ ] Add "build a monthly plan" wizard with balanced content types.
- [ ] Add duplicate/similarity warnings before topic approval.
- [ ] Add a planner score that balances evergreen, timely, and strategic topics.
- [ ] Add reusable planning boards by niche, campaign, or season.
- [ ] Add a "from research to schedule in one flow" shortcut.

---

## 4. Monitoring & Management

### What this category does
This area tracks what happened (history/activity), gives a dashboard view, and helps users review generated output confidently.

### Suggestions & Improvements
- [ ] Add a cleaner executive dashboard with "what needs attention now."
- [ ] Add failure triage views that group errors by root cause and urgency.
- [ ] Add post-level quality signals before users publish.
- [ ] Add "quick recover" actions (retry, regenerate section, switch template).
- [ ] Add trend views for quality, approval rate, and manual edit effort.
- [ ] Add workflow statuses (Generated, Needs Review, Approved, Ready to Publish).
- [ ] Add alerts for repeated failures in the same template, author, or schedule.
- [ ] Add a compact daily digest summary for admins.

---

## 5. System Tools

### What this category does
These tools cover setup and maintenance, including settings, system status, backups, import/export, and migration support.

### Suggestions & Improvements
- [ ] Add a first-run setup wizard for faster onboarding.
- [ ] Add settings profiles (Blogger, News, Agency, E-commerce).
- [ ] Add full JSON import/export parity with clear validation messages.
- [ ] Add selective backup/restore by feature area (templates only, schedules only, etc.).
- [ ] Add safer "pre-flight checks" before destructive actions.
- [ ] Add one-click environment diagnostics with plain-language recommendations.
- [ ] Add scheduled backup automation with retention controls.
- [ ] Add site-to-site migration assistant for smoother handoffs.

---

## 6. Developer Features

### What this category does
This category supports development workflows with seed data, diagnostics, extension points, and faster iteration.

### Suggestions & Improvements
- [ ] Add clearer hook/event reference examples for common extension use cases.
- [ ] Add sample extension recipes (custom prompt filters, custom schedule logic, etc.).
- [ ] Add a safer sandbox mode for testing generation changes without affecting live content.
- [ ] Add richer seed profiles (small demo, realistic medium, stress-test large).
- [ ] Add a diagnostics page focused on integration health and troubleshooting steps.
- [ ] Add a compatibility checklist for third-party plugin/theme interactions.
- [ ] Add more "why this failed" debug hints in plain language.
- [ ] Add a lightweight feature flag panel for controlled rollouts.

---

## Suggested New Features

### 1. Content Quality Gate
Automatically score generated posts before publish (readability, clarity, SEO readiness, usefulness) and require a minimum score.

### 2. Campaign & Series Builder
Create multi-post campaigns (for launches, seasonal themes, tutorials) with linked posts, shared messaging, and planned sequencing.

### 3. Content Repurposing Studio
Convert a single article into social posts, email drafts, summaries, FAQs, and short-form versions in one workflow.

### 4. Audience Persona Packs
Generate and tune content by audience persona (beginner, technical buyer, local customer, etc.) for more targeted messaging.

### 5. SEO Intent Mapper
Map topics to search intent (informational, comparison, transactional) and generate content structure that matches each intent.

### 6. Internal Linking Assistant
Automatically suggest internal links to existing posts/pages and generate context-aware anchor text.

### 7. Human-in-the-Loop Review Flows
Add configurable review lanes (writer -> editor -> approver) with comments, approvals, and accountability.

### 8. Brand Voice Training Workspace
Train and validate voice profiles against real examples so outputs stay aligned with a brand’s style.

### 9. Multi-Site Content Orchestration
Plan and generate across multiple WordPress sites with shared templates, per-site tone rules, and staggered scheduling.

### 10. Performance Learning Loop
Use published post outcomes (engagement/conversion signals) to recommend better templates, structures, and schedule choices.

### 11. Content Compliance Guardrails
Run policy checks before publish (claims, risky language, mandatory disclaimers, legal/industry constraints).

### 12. Source-Aware Research  Citations
Generate posts with source snippets and optional citation inserts to improve trustworthiness and editorial confidence.

---

## Notes for Prioritization

- Prioritize improvements that reduce manual editing and review effort first.
- Prioritize features that increase predictability and content quality at publish time.
- Prioritize workflows that let non-technical users go from idea -> approved post quickly.
