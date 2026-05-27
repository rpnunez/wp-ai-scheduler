# AI Assistance — Developer Integration Guide

## Overview

The **AI Assistance** system provides a reusable, form-agnostic way to add per-field AI suggestion buttons to any admin form in the plugin. Clicking a ✨ **AI Suggest** button sends the field's metadata to the AI engine, fills the field with the suggestion, and persists the result to the `aips_ai_assistance` table. A 🕐 history button appears after the first suggestion and opens a modal showing past AI responses grouped by the current browser session and all-time.

It is currently wired to the **Authors** admin form. This guide explains every component you must touch to add it to a new form (e.g. Templates, Article Structures, Structure Sections).

---

## Architecture

```
Browser                         PHP (AJAX)
──────────────────────────────────────────────────────────────────
AIPS.AIAssistance.init()
  └─ injectButtons()            ← reads AIPS_FIELD_MAPS[formContext]
  └─ bindEvents()

  [user clicks ✨ Suggest]
  └─ onAssistClick()
       $.post(aips_ai_field_assist) ──► AIPS_AI_Assistance_Controller
                                            └─ AIPS_AI_Assistance_Service
                                                 ├─ build_prompt()
                                                 ├─ AIPS_AI_Service::generate_text()
                                                 └─ AIPS_AI_Assistance_Repository::create()
                                        ◄── { response, record_id }
  └─ $field.val(response)

  [user clicks 🕐 History]
  └─ onHistoryClick()
       $.post(aips_get_field_assist_history) ──► AIPS_AI_Assistance_Controller
                                                     └─ AIPS_AI_Assistance_Repository
                                                          ├─ get_by_session_and_field()
                                                          └─ get_by_field()
                                                 ◄── { session: [], alltime: [] }
  └─ renderHistoryTab()
```

**Key classes and files:**

| File | Responsibility |
|------|---------------|
| `includes/class-aips-ai-assistance-repository.php` | DB reads/writes for `aips_ai_assistance` |
| `includes/class-aips-ai-assistance-service.php` | Prompt construction + AI call |
| `includes/class-aips-ai-assistance-controller.php` | AJAX handlers (registered via `AIPS_Ajax_Registry`) |
| `assets/js/ai-assistance.js` | `AIPS.AIAssistance` JS module |
| `assets/css/ai-assistance.css` | Button, modal, and history-item styles |

The controller, service, and repository are **shared** across all forms. Only the field map (JS), the asset enqueue call (PHP), and the HTML template snippets (PHP template) are form-specific.

---

## Step-by-step: Adding AI Assistance to a new form

### Step 1 — Define the field map in JavaScript

Open `assets/js/ai-assistance.js` and add a new key to `AIPS_FIELD_MAPS`. The key is the **form context** identifier — a short, lowercase string unique to this form (e.g. `'templates'`, `'structures'`). Each child key is the **HTML `id`** of a field element on that form page.

```js
var AIPS_FIELD_MAPS = {
    authors: {
        // … existing author fields …
    },

    // ── NEW: example for a Templates form ─────────────────────────────
    templates: {
        template_name: {
            fieldName:        'Template Name',
            description:      'The internal name used to identify this template',
            influence:        'Used as a label in the admin; not included in the generated post',
            expectedResponse: 'A short, descriptive template name (3–6 words)',
        },
        prompt_template: {
            fieldName:        'Prompt Template',
            description:      'The master prompt used to generate post content',
            influence:        'Directly drives what the AI writes for every generated post',
            expectedResponse: 'A detailed prompt in imperative form (e.g. "Write a 600-word blog post about…")',
        },
        // … add one entry per field you want to support …
    },
};
```

**Field map entry properties:**

| Property | Required | Description |
|----------|----------|-------------|
| `fieldName` | ✅ | Human-readable label sent to the AI as the field name. |
| `description` | ✅ | What this field is for — used in the prompt. |
| `influence` | ✅ | How this field affects AI content generation — improves suggestion quality. |
| `expectedResponse` | ✅ | Format hint for the AI (length, format, examples). |

> **Tip:** The more specific `description`, `influence`, and `expectedResponse` are, the better the AI suggestions will be. Look at the existing `authors` entries as a reference.

---

### Step 2 — Set `formContext` for the new page

`AIPS.AIAssistance.formContext` defaults to `'authors'`. Each page using the module must override it **before** `init()` is called. The cleanest pattern is to call `init()` from your page-specific JS file after overriding `formContext`:

```js
// In your page JS file (e.g. assets/js/templates.js), inside $(document).ready():
if (typeof AIPS !== 'undefined' && AIPS.AIAssistance) {
    AIPS.AIAssistance.formContext = 'templates';
    AIPS.AIAssistance.init();
}
```

Because `ai-assistance.js` already calls `init()` in its own `$(document).ready()`, you must **prevent the default init from running** when you want a different context. The simplest way is to check whether the module has already been initialised and skip the default, or override the `formContext` synchronously before the default `ready()` fires by loading your page script with a lower `priority` than the `ai-assistance.js` registration. In practice, the cleanest solution is to move the `init()` call out of `ai-assistance.js` and into each page-specific script:

1. Remove (or guard) the automatic `init()` call at the bottom of `ai-assistance.js`.
2. Call `AIPS.AIAssistance.formContext = '<context>'; AIPS.AIAssistance.init();` from each page script.

> For the Authors page this is already handled: `formContext` defaults to `'authors'` and the default `init()` runs automatically. Once you move the `init()` call to page scripts, remove the default call from `ai-assistance.js`.

---

### Step 3 — Enqueue assets on the new admin page

Open `includes/class-aips-admin-assets.php` and find the `enqueue_*_assets()` method that handles your target page (e.g. `enqueue_templates_assets()`). Add the CSS and JS enqueues, plus the localization object, matching the pattern used for the Authors page:

```php
// Enqueue AI Assistance CSS
wp_enqueue_style(
    'aips-ai-assistance-style',
    AIPS_PLUGIN_URL . 'assets/css/ai-assistance.css',
    array( 'aips-admin-style' ),
    AIPS_VERSION
);

// Enqueue AI Assistance JS
// Dependencies: jquery, aips-utilities-script, aips-templates-script, plus
// your page-specific script that sets formContext before calling init().
wp_enqueue_script(
    'aips-ai-assistance-script',
    AIPS_PLUGIN_URL . 'assets/js/ai-assistance.js',
    array( 'jquery', 'aips-utilities-script', 'aips-templates-script', 'aips-your-page-script' ),
    AIPS_VERSION,
    true
);

// Localize strings + nonce
wp_localize_script( 'aips-ai-assistance-script', 'aipsAIAssistanceL10n', array(
    'nonce'           => wp_create_nonce( 'aips_ajax_nonce' ),
    'loading'         => __( 'Loading...', 'ai-post-scheduler' ),
    'suggesting'      => __( 'Suggesting...', 'ai-post-scheduler' ),
    'suggested'       => __( 'AI suggestion applied.', 'ai-post-scheduler' ),
    'errorSuggesting' => __( 'Could not get AI suggestion. Please try again.', 'ai-post-scheduler' ),
    'valueApplied'    => __( 'Value applied from history.', 'ai-post-scheduler' ),
    'noHistory'       => __( 'No AI suggestions found for this field yet.', 'ai-post-scheduler' ),
    'aiUnavailable'   => __( 'AI Engine is not available.', 'ai-post-scheduler' ),
    'thisSession'     => __( 'This Session', 'ai-post-scheduler' ),
    'allTime'         => __( 'All Time', 'ai-post-scheduler' ),
) );
```

> The `aipsAIAssistanceL10n` object **must not be registered more than once per page** (WordPress will silently drop the second call). If your page already registers this object through a shared helper, skip the `wp_localize_script` call.

---

### Step 4 — Add the HTML template snippets to the page template

Open your page's PHP template (e.g. `templates/admin/templates.php` or `templates/admin/structures.php`) and append the three HTML blocks **at the bottom**, before the closing tag. Copy them verbatim from `templates/admin/authors.php` (lines 917–970) or paste the canonical versions below:

#### 4a — Button template

```php
<!-- AI Assistance: Combined Assist + History Button Template -->
<script type="text/html" id="aips-tmpl-ai-assist-btn">
<div class="aips-ai-assist-btn-group">
    <button type="button"
        class="aips-btn aips-btn-sm aips-btn-ghost aips-ai-assist-btn"
        data-field-id="{{fieldId}}"
        title="<?php esc_attr_e( 'Get AI suggestion', 'ai-post-scheduler' ); ?>"
        aria-label="<?php esc_attr_e( 'Get AI suggestion for this field', 'ai-post-scheduler' ); ?>">
        <span class="aips-ai-sparkle" aria-hidden="true">&#10024;</span>
        <span class="aips-ai-assist-btn-label"><?php esc_html_e( 'AI Suggest', 'ai-post-scheduler' ); ?></span>
    </button>
    <button type="button"
        class="aips-btn aips-btn-sm aips-btn-ghost aips-ai-assist-history-btn"
        data-field-id="{{fieldId}}"
        style="display:none"
        title="<?php esc_attr_e( 'View AI suggestion history', 'ai-post-scheduler' ); ?>"
        aria-label="<?php esc_attr_e( 'View AI suggestion history for this field', 'ai-post-scheduler' ); ?>">
        <span class="dashicons dashicons-backup" aria-hidden="true"></span>
        <span class="screen-reader-text"><?php esc_html_e( 'View history', 'ai-post-scheduler' ); ?></span>
    </button>
</div>
</script>
```

#### 4b — History modal

```php
<!-- AI Assistance: History Modal -->
<div id="aips-ai-assist-history-modal" class="aips-modal" style="display:none;"
     role="dialog" aria-modal="true" aria-labelledby="aips-ai-assist-history-modal-title">
    <div class="aips-modal-content aips-modal-large">
        <button type="button" class="aips-modal-close"
            aria-label="<?php esc_attr_e( 'Close', 'ai-post-scheduler' ); ?>">&times;</button>
        <h2 id="aips-ai-assist-history-modal-title"><?php esc_html_e( 'AI Suggestion History', 'ai-post-scheduler' ); ?></h2>
        <p id="aips-ai-assist-history-field-label" class="description"></p>
        <div class="aips-tab-nav" id="aips-ai-assist-history-tabs">
            <a href="#" class="aips-tab-link active"
               data-tab="aips-ai-assist-history-session"><?php esc_html_e( 'This Session', 'ai-post-scheduler' ); ?></a>
            <a href="#" class="aips-tab-link"
               data-tab="aips-ai-assist-history-alltime"><?php esc_html_e( 'All Time', 'ai-post-scheduler' ); ?></a>
        </div>
        <div id="aips-ai-assist-history-session-tab" class="aips-tab-content">
            <p class="description"><?php esc_html_e( 'Loading...', 'ai-post-scheduler' ); ?></p>
        </div>
        <div id="aips-ai-assist-history-alltime-tab" class="aips-tab-content" style="display:none;">
            <p class="description"><?php esc_html_e( 'Loading...', 'ai-post-scheduler' ); ?></p>
        </div>
    </div>
</div>
```

#### 4c — History item template

```php
<!-- AI Assistance: History Item Template -->
<script type="text/html" id="aips-tmpl-ai-assist-history-item">
<div class="aips-ai-assist-history-item">
    <div class="aips-ai-assist-history-response">{{response}}</div>
    <div class="aips-ai-assist-history-meta">{{created_at}}</div>
    <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-assist-history-use"
        data-field-id="{{fieldId}}"
        data-record-id="{{id}}">
        <?php esc_html_e( 'Use This Value', 'ai-post-scheduler' ); ?>
    </button>
</div>
</script>
```

> **Important:** These three template IDs (`aips-tmpl-ai-assist-btn`, `aips-ai-assist-history-modal`, `aips-tmpl-ai-assist-history-item`) are global and must appear **only once per page**. Do not duplicate them if two features share a page.

---

### Step 5 — Ensure field HTML IDs match the field map

The JS module uses `$('#' + fieldId)` to locate each field. Confirm that every `id` key in your field map exactly matches the `id` attribute of the corresponding `<input>` or `<textarea>` in your page template.

**Button injection** is automatic: `injectButtons()` iterates over the field map and appends the button group HTML immediately after each field (or before a `.description` paragraph if one exists). No manual HTML changes are needed in the form rows.

**Button positioning**: the parent element wrapping the field receives the class `aips-ai-assist-wrap` automatically if it has the class `.form-group`. If your form uses a different wrapper class, either add `.form-group` to it or adjust the selector in `injectButtons()`.

---

### Step 6 — No backend changes required

The controller, service, and repository are completely form-agnostic. The `form_context` value from the JS field map is stored verbatim in the `aips_ai_assistance` table and used as the scope for history queries. As long as:

- `form_context` is a non-empty string,
- `field_key` matches a real field `id`, and
- `session_id` is non-empty,

…the two AJAX endpoints (`aips_ai_field_assist`, `aips_get_field_assist_history`) will work without modification.

---

## Prompt customisation (optional)

The default prompt in `AIPS_AI_Assistance_Service::build_prompt()` is written for the Authors form and mentions "AI author persona". If you need context-specific language (e.g. "Template" instead of "Author persona"), you can:

1. Override `build_prompt()` in a subclass of `AIPS_AI_Assistance_Service`.
2. Pass context-specific extra fields from the JS `onAssistClick()` handler (they are forwarded to the service in `$field_config`).
3. Add optional context keys to the JS `onAssistClick()` post payload (e.g. `template_type`, `structure_name`) and read them in `build_prompt()`.

Example: reading an additional `template_type` value from the page:

```js
// In onAssistClick(), extend the $.post payload:
template_type: $('#template_type').val() || '',
```

```php
// In build_prompt(), add a conditional line:
if ( ! empty( $field_config['template_type'] ) ) {
    $lines[] = 'Template Type: ' . $field_config['template_type'];
}
```

---

## Security notes

- All AJAX requests are gated behind `check_ajax_referer( 'aips_ajax_nonce', 'nonce' )` and `current_user_can( 'manage_options' )`.
- History item responses are rendered via `AIPS.Templates.render()` (HTML-escaped), **not** `renderRaw()`, preventing XSS from AI-generated content.
- Raw response text is stored in an in-memory JS object (`_historyRecordCache`) keyed by record ID and never placed in DOM attributes.

---

## Testing

When adding AI Assistance to a new form, cover these cases in your PHPUnit test:

1. **Nonce failure** — `ajax_field_assist()` rejects a missing/invalid nonce.
2. **Capability failure** — rejects a non-admin user (subscriber role).
3. **Empty `session_id`** — `ajax_field_assist()` returns `invalid_request`.
4. **Missing required params** — `form_context` / `field_key` / `field_name` all absent.
5. **Happy path** — AI response is returned and a record is persisted to `aips_ai_assistance`.
6. **AI error propagation** — when `AIPS_AI_Service::generate_text()` returns a `WP_Error`, the controller surfaces the error message.
7. **History retrieval** — `ajax_get_field_assist_history()` returns session-scoped and all-time records correctly separated.

See `tests/Test_AIPS_AI_Assistance_Controller.php` for the full reference implementation.

---

## Checklist

Use this checklist when adding AI Assistance to a new form:

- [ ] Add a new `formContext` key + field entries to `AIPS_FIELD_MAPS` in `ai-assistance.js`
- [ ] Set `AIPS.AIAssistance.formContext = '<context>'` and call `init()` from the page JS
- [ ] Enqueue `aips-ai-assistance-style` and `aips-ai-assistance-script` in the correct `enqueue_*_assets()` method
- [ ] Add `wp_localize_script` for `aipsAIAssistanceL10n` (if not already present on the page)
- [ ] Add the three template/modal HTML blocks to the page PHP template
- [ ] Verify field `id` attributes match the field map keys
- [ ] Smoke-test: click ✨ on a field → suggestion applied → 🕐 appears → history modal shows the record
- [ ] Add PHPUnit coverage for the new `form_context` value (or extend existing tests)

---

## Reference: `aips_ai_assistance` table schema

```sql
CREATE TABLE {prefix}aips_ai_assistance (
    id             bigint(20)            NOT NULL AUTO_INCREMENT,
    session_id     varchar(64)           NOT NULL,
    user_id        bigint(20)            DEFAULT NULL,
    form_context   varchar(100)          NOT NULL,
    field_key      varchar(100)          NOT NULL,
    request_object longtext              NOT NULL,
    prompt         text                  NOT NULL,
    response       longtext              NOT NULL,
    created_at     bigint(20) unsigned   NOT NULL DEFAULT 0,
    PRIMARY KEY  (id),
    KEY session_id (session_id),
    KEY form_context_field (form_context, field_key),
    KEY user_id (user_id),
    KEY created_at (created_at)
);
```

`created_at` stores a Unix timestamp (bigint) aligned with the DateTime refactor; use `AIPS_DateTime::fromTimestampOrNull()` to convert to display format.

---

*Feature introduced in v2.5.1. Reference implementation: Authors form.*
