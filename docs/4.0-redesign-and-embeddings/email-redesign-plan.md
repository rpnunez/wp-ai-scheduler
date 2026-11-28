# AIPS Email Redesign — Implementation Plan

Source design: `Email/Emails.dc.html` (Claude Design handoff, project `AI Post Scheduler UI redesign`).
`support.js` is the Claude Design prototype runtime (React-based `DCLogic` harness) — nothing to port.
Its only contribution is the design prop `cellBorder`, which resolves to `1px solid #e0e0e0`.

---

## 1. What the design actually specifies

The file holds two sections. The **later turn** (`Turn 2 · 1b status header with metadata cells`,
mockups `3a`–`3f`) is the chosen direction; the lower `Email mockups` section (`1a`, `1b`, `2b`, `2c`)
is earlier exploration that still defines two reusable patterns. Combined, there are **two header
modes and six body compositions**.

### Header modes

| Mode | Used by | Composition |
|---|---|---|
| **Status banner** (`1b`, `3a`–`3d`) | Per-post events | `#eef1fd` band, bottom border `#dde3fb`, 28/32 padding; 44px circle `#3858e9` with white `✓` glyph; title (20px Bricolage 600) + subtitle `Site · Today, 2:42 PM`; optional right-aligned status pill |
| **Quiet brand bar** (`1a`, `2b`, `2c`) | Digests, summaries, alerts | 18/32 row, bottom border `#eee`, 13px: 10px blue square + `AI Post Scheduler` on the left, site name `#757575` on the right |

Mockups `3e`/`3f` use **no header at all** — they open directly on the eyebrow + headline block.
So header chrome is one of three: `banner`, `brand`, `none`.

### Body blocks (the component inventory)

1. **Eyebrow + headline** — 11px/600 uppercase, `letter-spacing:.12em`, tone-coloured
   (`#3858e9` info, `#b32d2e` danger) + 24px/1.25 Bricolage headline.
   Variants seen: `POST READY FOR REVIEW`, `DAILY DIGEST · NOV 5, 2026`,
   `WEEKLY SUMMARY · NOV 2–8, 2026`, `GENERATION FAILED`, `QUOTA ALERT · EMBEDDING`.
2. **Post headline only** (`3a`–`3d`) — 20px/1.3 Bricolage, no eyebrow (the banner carries the status).
3. **Meta cells** — 2-column grid, 8px gutter; each cell `#fafafa`, `1px solid #e0e0e0`, radius 6,
   padding 12/16; 12px `#757575` label over 13px/600 value with `overflow-wrap:anywhere`.
   Observed label sets: `Category / Generated / Schedule|Template|Author topic / Length`,
   `Schedule / Topic / Attempts / Next retry`, `Request type / AI model / Error code / Usage`.
4. **Callout** — tone-tinted box (danger: bg `#fcecec`, border `#f3c6c6`), bold tone-coloured
   title + `#50575e` detail line.
5. **Stat tiles** — 3-up counters: 28px Bricolage number + 12px label. Neutral tile is white with
   `#e0e0e0` border; the "Ready for review" tile is amber (`#fdf3df` / `#fbe3b0` / `#996300`).
6. **Item rows** (`2b`) — `GENERATED TODAY` caption, then title/time rows separated by `1px solid #eee`.
7. **Featured image** (`3d`) — 16:9, radius 6, `1px solid #e0e0e0`, directly under the headline.
8. **Content preview** (`3b`–`3d`) — `EXCERPT` caption + excerpt, then `CONTENT PREVIEW` caption with
   ~3 blocks (paragraph, 16px Bricolage subhead, paragraph) clipped at `max-height:190px` under an
   80px white fade, followed by `Read the full post in the editor →`.
9. **Actions** — primary `#3858e9`/white/600, padding 10/18, radius 4; secondary `1px solid #c3c4c7`,
   padding 9/16, `#1e1e1e`. Pairs seen: Review post + Open review queue · Edit post + View post ·
   Review AI settings + View history · View history entry + Retry now · Open history (solo).
10. **Footer** — `#fafafa`, border-top `#eee`, 18/32, 12px `#757575`:
    `Sent by AI Post Scheduler on {Site} · ` + `Notification settings` link.

### Status pills

`Published` → `#e7f5ea` / `#1a7f37`. `Draft` → `#fdf3df` / `#996300`.
3px/10px padding, radius 10, 12px/600, `white-space:nowrap`.

### Design tokens

```
container      600px · #fff · 1px solid #e0e0e0 · radius 8
page bg        #f0f0f1
brand          #3858e9   link hover #2145d4   tint #eef1fd   tint border #dde3fb
text           #1e1e1e   subtitle #50575e   dim #757575   body copy #3c434a
danger         #b32d2e   bg #fcecec   border #f3c6c6
warning        #996300   bg #fdf3df   border #fbe3b0
success        #1a7f37   bg #e7f5ea
cell           bg #fafafa · border 1px solid #e0e0e0 · radius 6 · padding 12 16
footer         bg #fafafa · border-top #eee · padding 18 32
headings       'Bricolage Grotesque' 500/600, fallback Arial, sans-serif
body           system-ui / Arial, sans-serif · 14px/1.5
```

---

## 2. What AIPS has today

```
templates/email/email_layout.php          shared shell: inline <style> block, coloured <h1> banner,
                                          body slot, one-line footer
templates/email/template-alert.php        "alert-box" body, 5 tokens
templates/email/template-event.php        same body, different intro sentence
templates/email/template-post-generated.php  bespoke presentation table
templates/email/template-summarized-digest.php + summarized-digest.php  grouped digest tables

includes/class-aips-notification-template.php    value object: type, subject, body, header_title,
                                                 header_color + {{token}} str_replace
includes/class-aips-notification-templates.php   registry; renders body partial into layout ONCE at
                                                 construction, stores the full HTML document string
includes/class-aips-notification-senders.php     21 senders → payload + vars
includes/class-aips-notifications.php            dispatch, channel resolution, dedupe, digest queue,
                                                 wp_mail, build_standard_notification_vars(),
                                                 build_details_html()
includes/class-aips-notification-registry.php    type → label / default_mode / level / dedupe_window
```

Three structural problems for this redesign:

- **`build_details_html()` dumps the raw payload** as `<ul><li><strong>Key:</strong> value</li></ul>`
  — `ucwords(str_replace('_',' ', $key))` over whatever the sender passed. The design needs
  *curated, ordered, four-at-a-time* meta cells with real labels. Raw key dumping cannot produce that.
- **One action per email.** `{{action_url}}` / `{{action_label}}` are singular; the design wants a
  primary + secondary pair for most types.
- **Templates are flat HTML strings with CSS classes.** The layout's `<style>` block is stripped by
  Gmail, so `.button`, `.alert-box`, `.post-item` already render unstyled there today. Everything in
  the new design must be inline.

### Data the design shows that AIPS does not currently pass

| Design field | Status |
|---|---|
| Category | derivable — post terms |
| Length (`1,842 words · 7 min`) | derivable — `str_word_count` + 225 wpm |
| `Generated` (`Scheduled · 2:42 PM`) | needs source mode + site-tz time formatting |
| Template / Schedule / Author topic name | in payload for some senders, absent for others |
| Status pill (`Published` / `Draft`) | have `post_status_label` |
| Content preview blocks | derivable — truncate `post_content` |
| Digest item rows with times | **not in `daily_digest` payload** — must be added |
| Attempts / Next retry | **not in failure payloads** — must be added or cell omitted |
| AI model / Error code / Usage | sometimes present in `quota_alert` meta, not guaranteed |
| `Notification settings` footer link | derivable — `get_page_url('settings', ['tab'=>'notifications'])` |

Any cell whose value is missing must drop out of the grid rather than render empty — the grid
re-flows to whatever cells exist (2 or 4 read best; 3 leaves a gap, so pad or drop to 2).

---

## 3. Email-client translation (the non-negotiable part)

The prototype is flexbox, CSS grid, `aspect-ratio`, `linear-gradient`, `border-radius`, and a Google
Font. Outlook (Word engine) supports none of the first four; Gmail strips `<style>`. The port must be:

- **Tables, not flex/grid.** Outer 100% table → centered 600px table → one `<tr>` per block.
  Meta cells become a table of 2-cell rows with an 8px spacer column.
- **Inline styles on every element.** The `<style>` block survives only as progressive enhancement
  (mobile media queries, `prefers-color-scheme`), never as the only source of a style.
- **No `aspect-ratio`.** Featured image gets `width:100%;max-width:536px;height:auto;display:block`.
- **No gradient fade.** The `3b`–`3d` fade is cosmetic clipping of overflow. Replace with
  **server-side truncation** to ~3 blocks / ~400 chars plus the `Read the full post →` link. This is
  more robust and conveys the same "there's more" signal.
- **Rounded corners degrade.** `border-radius` is simply ignored by Outlook — acceptable, square
  corners there.
- **Bricolage Grotesque will not load** in Gmail or Outlook. Link it for clients that honour webfonts
  and declare `font-family:'Bricolage Grotesque',Arial,Helvetica,sans-serif` so the rest fall back
  cleanly. No image-based text.
- **Buttons as padded table cells** with `mso-padding-alt`, not styled `<a>` only.
- **Add a preheader** (hidden preview text) — currently missing, and every client shows one.
- **Dark mode**: `prefers-color-scheme` block for clients that honour it; `#fafafa` cells and
  `#eef1fd` banner are the ones that invert badly.

---

## 4. Proposed architecture

The redesign needs *structured data*, so the fix is a **view model** between senders and templates.
Senders keep producing payloads; a factory turns a payload into a typed view; a renderer composes
partials. This matches the plugin's layer rules (templates presentational, services orchestrating,
no SQL anywhere near here) and makes every block reusable.

### New classes (`includes/`)

| File | Role |
|---|---|
| `class-aips-email-theme.php` | Every token from §1 as one static map, exposed through the `aips_email_theme` filter. Partials read tokens; no partial hardcodes a hex value. |
| `class-aips-email-view.php` | Immutable value object: `header_mode`, `header_icon`, `header_title`, `header_subtitle`, `status_pill`, `eyebrow` + `eyebrow_tone`, `headline`, `callout`, `meta_cells[]`, `featured_image`, `excerpt`, `preview_blocks[]`, `preview_link`, `stat_tiles[]`, `item_rows[]`, `actions[]`, `footer_link`, `preheader`. Fluent `with_*()` builders; `to_array()` for tests. |
| `class-aips-email-view-factory.php` | Payload → view. Owns the derivations: word count + read time, category from terms, source-mode label, site-tz time formatting, status → pill tone, content-preview truncation, cell curation per type. |
| `class-aips-email-renderer.php` | `render( AIPS_Email_View $view ): string` — runs the layout with partials; `render_text( $view ): string` for the plain-text alternative. |

### New templates (`templates/email/`)

```
layout.php                        document shell: doctype, preheader, page bg, 600px table,
                                  <style> enhancements, footer
partials/header-banner.php        status banner  (1b / 3a–3d)
partials/header-brand.php         quiet brand bar (1a / 2b / 2c)
partials/eyebrow-headline.php     eyebrow + headline
partials/status-pill.php
partials/meta-cells.php           2-up table grid, stacks at ≤480px
partials/callout.php              tone-aware
partials/stat-tiles.php           3-up counters
partials/item-rows.php            digest list
partials/featured-image.php
partials/content-preview.php      excerpt + preview blocks + read-more
partials/actions.php              primary / secondary buttons
partials/footer.php
```

### Back-compatibility — *as built*

The implementation went one step simpler than planned. `AIPS_Notifications::dispatch_notification()`
composes a view for **every** type and hands it to `send_email_notification()`, which renders it.
If no view was composed, or the layout fails to render, it falls back to the template's existing
`{{token}}` body. No per-template view-builder flag was needed.

Consequences, all of them good:

- Third-party templates registered through `aips_notification_templates` keep working unchanged.
- The legacy templates stay in place as a genuine safety net rather than dead code.
- **The existing notification tests did not need changing.** They assert on the legacy template
  bodies, which still exist and still render the same way; only the *send* path changed.
- Subjects still come from the template, so inbox rules matching `[Site] Title` keep matching.

`build_details_html()` is kept (hook-visible behaviour) but is no longer used by built-in types.

### Type → composition map

| Type(s) | Header | Body blocks | Actions |
|---|---|---|---|
| `post_generated` | banner + pill | headline · featured image · meta cells · excerpt · preview · read-more | Edit post / View post |
| `post_ready_for_review` | banner | headline · meta cells | Review post / Open review queue |
| `manual_generation_completed`, `template_generated`, `partial_generation_completed`, `post_rejected` | banner | headline · meta cells | type-specific pair |
| `generation_failed`, `scheduler_error`, `system_error`, `integration_error` | none | eyebrow(danger) · headline · callout · meta cells | View history entry / (secondary per type) |
| `quota_alert` | none | eyebrow(danger, `· {request_type}`) · headline · callout · meta cells | Review AI settings / View history |
| `daily_digest`, `weekly_summary`, `monthly_report` | brand (daily) / none (weekly, per `3e`) | eyebrow + date range · headline count · stat tiles · item rows | Open history / Open generated posts |
| `summarized_digest` | brand | eyebrow · headline · grouped item rows | Open generated posts |

**Open question — `Retry now`.** Mockup `2c` offers it as a secondary button. A state-changing action
triggered by a GET from an email body needs a long-lived nonce in the URL and is a real security
surface. Recommendation: render it as a deep link to the history entry (where a proper Retry button
already lives), not as a direct-action URL.

---

## 5. Slices

Each slice is independently testable and mergeable, in the plugin's DB → service → AJAX → UI order
(there is no schema change here, so it starts at service).

**S1 · Foundation** — theme, view model, renderer, `layout.php`, and the partials for header, footer,
eyebrow, meta cells, actions. All existing types render through the new layout using a generic view
built from the current `title/message/details/action` vars, so every email immediately looks
redesigned even before per-type curation. Golden-file test over the rendered HTML; assert
inline-style presence and absence of `flex`/`grid`.

**S2 · Post events** — `post_generated` + `post_ready_for_review` view builders: status banner,
status pill, curated meta cells (category, generated, template/schedule/author-topic, length),
featured image, excerpt, truncated content preview. Also adds the derivation helpers
(word count, read time, category, site-tz time).

**S3 · Alerts** — the five error types: eyebrow, callout, curated cells. Introduces the
`payload → cells` curation map and the "drop missing cells" rule. Includes the payload additions for
`attempts` / `next_retry` where the calling code can supply them, and omission where it cannot.

**S4 · Digests** — stat tiles and item rows; extends `daily_digest`/`weekly_summary`/`monthly_report`
payloads with the item list the design shows, and re-skins `summarized_digest`.

**S5 · Polish** — plain-text alternative part via `phpmailer_init`, preheader copy per type, mobile
stacking media queries, dark-mode block, and (recommended) a **Settings → Notifications "Send test
email"** control with a type picker, so the design can be verified in real clients without waiting
for a real event.

---

## 6. Tests

- `Test_AIPS_Email_Theme` — token map shape; filter applies.
- `Test_AIPS_Email_View` — immutability; `with_*` returns new instances; `to_array()` shape.
- `Test_AIPS_Email_View_Factory` — per-type composition: correct header mode, cell labels and order,
  missing values drop cells, word-count/read-time maths, status→pill tone, preview truncation.
- `Test_AIPS_Email_Renderer` — every block renders; no `display:flex`, `display:grid`, or
  `aspect-ratio` in output; 600px container; preheader present; all styles inline; escaping holds for
  hostile titles and payload values.
- Existing suites: **no changes required.** `Test_AIPS_Notification_Template` (`.email-container` /
  `.email-header`) and `Test_AIPS_Notification_Digest` (`alert-box`) assert on the legacy template
  bodies, which the renderer-first/legacy-fallback design leaves intact. The break predicted here
  did not materialise.
- Regression: `Test_AIPS_Notifications`, `Test_AIPS_Notification_Senders`,
  `Test_AIPS_Notification_Registry` must pass untouched (dispatch, dedupe, channels, digest queue are
  not in scope).

Run as: `make test-session ARGS="tests/Test_AIPS_Email_Renderer.php tests/Test_AIPS_Email_View_Factory.php tests/Test_AIPS_Notification_Template.php tests/Test_AIPS_Notification_Digest.php tests/Test_AIPS_Notification_Senders.php"`

---

## 7. Risks

| Risk | Mitigation |
|---|---|
| Outlook ignores radius/flex/grid | table port; square corners accepted there |
| Gmail strips `<style>` | every style inline; `<style>` is enhancement only |
| Bricolage Grotesque unavailable in most clients | declared fallback stack, no text-as-image |
| Fade-out clipping unreproducible | server-side truncation + read-more link |
| Meta cells with missing data | cells drop out; grid re-flows |
| `{{token}}` third-party templates | legacy render path retained behind the view-builder check |
| HTML emails bloat with full post content | preview is truncated, not the whole post (current
  `post_generated` sends the entire `post_content` — this slims it) |


---

## 8. Build notes (post-implementation)

**Decisions taken** (confirmed with the user before building): all five slices in one pass; the
design's "Retry now" resolves to the history entry rather than a nonced action URL; `post_generated`
truncates to a preview; and a Settings → Notifications "Send Test Email" control with a type picker
was added.

**Verification.** This container has neither Docker nor a MySQL server, and installing one was
denied, so `make test-session` could not run. The PHPUnit suites were written
(`Test_AIPS_Email_View`, `Test_AIPS_Email_Renderer`, `Test_AIPS_Email_View_Factory`) and **must be
run in a Docker-capable environment before merge.** In their place, the logic was verified here
with a standalone harness that stubs WordPress: 152 assertions over the theme, view model,
renderer, and factory, plus an end-to-end render of all 25 registered notification types asserting
no unsupported CSS, no unreplaced `{{tokens}}`, and a footer on every one. A full-page screenshot of
the seven design compositions was checked against the mockups.

**Known gaps carried forward.** `attempts` and `next_retry` cells only appear when the calling code
supplies them — the factory drops them otherwise, and populating those payloads at the call sites in
the retry/scheduler code is follow-up work, not part of this change.
