# Settings System Architecture & Developer Guide

## 1. Overview & Purpose

The Settings Subsystem in **AI Post Scheduler (AIPS)** provides a centralized, declarative, schema-driven architecture for registering, rendering, validating, and persisting plugin configuration options.

Historically, adding or modifying a setting required touching multiple disconnected files:
- Defining defaults in `AIPS_Config::get_default_options()`
- Procedural `register_setting()`, `add_settings_section()`, and `add_settings_field()` registrations in `AIPS_Settings::register_settings()`
- Writing procedural HTML callbacks in `AIPS_Settings_UI` (over 120 methods with 80% duplicated markup)
- Hardcoding tab layout cards and tables in `templates/admin/settings.php`
- Hardcoding validation arrays in `AIPS_Settings_AJAX`

The new architecture eliminates this redundancy by establishing **`AIPS_Settings_Registry`** as the single source of truth. A single `AIPS_Setting_Definition` automatically provides:
- The default option value for `AIPS_Config`
- Schema registration with the WordPress Settings API via `AIPS_Settings_Manager`
- Accessible (ARIA-compliant) UI markup rendered via `AIPS_Settings_Form_Renderer`
- Dynamic section and tab placement in `templates/admin/settings.php`
- Dynamic validation and type coercion in `AIPS_Settings_AJAX`
- Conditional client-side display logic based on setting dependencies

---

## 2. Architecture & Core Classes

```
                          ┌──────────────────────────┐
                          │  AIPS_Settings_Registry  │ (Single source of truth)
                          └─────────────┬────────────┘
                                        │
           ┌────────────────────────────┼────────────────────────────┐
           ▼                            ▼                            ▼
┌──────────────────────┐    ┌──────────────────────┐    ┌──────────────────────┐
│AIPS_Setting_Definit'n│    │ AIPS_Settings_Manager│    │ AIPS_Settings_Form_  │
│(Strongly typed schema│    │(WordPress API bridge)│    │ Renderer (UI Engine) │
└──────────┬───────────┘    └───────────┬──────────┘    └──────────┬───────────┘
           │                            │                          │
           ▼                            ▼                          ▼
┌──────────────────────┐    ┌──────────────────────┐    ┌──────────────────────┐
│     AIPS_Config      │    │ WordPress Settings   │    │templates/admin/      │
│(Default option values│    │ API (`wp_ajax`, etc.)│    │settings.php (Dynamic)│
└──────────────────────┘    └──────────────────────┘    └──────────────────────┘
```

### 2.1 `AIPS_Setting_Definition`
Located at `ai-post-scheduler/includes/class-aips-setting-definition.php`.
Represents an immutable value object defining a single setting's metadata:
- `key`: Option name in `wp_options` (or array key if `parent_key` is set).
- `type`: Data type (`string`, `boolean`, `integer`, `float`, `array`, `null`).
- `default`: Default value fallback.
- `tab`: Parent tab identifier (e.g., `settings-general`, `settings-ai`).
- `section`: Parent section identifier (e.g., `aips_ai_config_section`).
- `label`: Human-readable label displayed in settings tables.
- `description`: Contextual help text displayed under the field.
- `control_type`: UI input control (`toggle`, `text`, `number`, `select`, `multiselect`, `secret`, `textarea`, `slider`, `action`).
- `control_options`: Control-specific parameters (min/max, choices, placeholder, user/taxonomy dropdown configs).
- `sanitize_callback`: Callable or function name for sanitizing value.
- `validate_callback`: Validation logic before save.
- `dependencies`: Array of conditions `[ 'other_key' => expected_value ]` for dynamic visibility toggling.
- `parent_key`: When this setting is a sub-field of an array option (e.g., `aips_notification_preferences`).
- `render_callback`: Custom renderer closure for bespoke controls.
- `priority`: Ordering weight within the section.

### 2.2 `AIPS_Settings_Registry`
Located at `ai-post-scheduler/includes/class-aips-settings-registry.php`.
Singleton registry providing:
- Tab definitions (`register_tab()`, `get_tabs()`)
- Section definitions (`register_section()`, `get_sections()`, `get_sections_by_tab()`)
- Setting definitions (`register_setting()`, `get_setting()`, `get_settings()`, `get_settings_by_section()`)
- Option defaults derivation: `get_defaults()` dynamically derives default arrays for `AIPS_Config`.

### 2.3 `AIPS_Settings_Form_Renderer`
Located at `ai-post-scheduler/includes/class-aips-settings-form-renderer.php`.
Central UI rendering engine. It handles all HTML markup generation with accessibility best practices:
- **Toggles / Switches**: Semantic checkboxes with `role="switch"` and `aria-checked`.
- **Text & Number**: Styled inputs with min, max, step, and placeholders.
- **Select & Multiselect**: Standard dropdowns, callable dynamic option providers, and WordPress native selectors (`wp_dropdown_categories`, `wp_dropdown_users`).
- **Secrets**: Password inputs with toggleable reveal buttons (Dashicons eye toggle). Set `control_options => array( 'mask_stored_value' => true )` for write-only secrets: the stored value is never printed into the page, and the setting's sanitize callback must keep the stored value when the field is submitted blank (see `AIPS_Cloudflare_AI_Gateway_Config::sanitize_api_token()`).
- **Sliders**: Range inputs with real-time value indicators.
- **Actions**: Trigger buttons (e.g., Flush Notification Digest).
- **Zero Inline JS & CSS**: All event handling is delegated in `admin-settings.js`, styles are tokenized in `admin.css`.

### 2.4 `AIPS_Settings_Manager`
Located at `ai-post-scheduler/includes/class-aips-settings-manager.php`.
Orchestrates WordPress Settings API interactions:
- Calls `register_setting()` with sanitized schema arguments.
- Calls `add_settings_section()`.
- Calls `add_settings_field()` with `render_field_callback()`, which invokes `AIPS_Settings_Form_Renderer`.
- Skips standalone option registration for sub-fields with `parent_key`, instead resolving their values from the parent array.

### 2.5 `AIPS_Settings_UI`
Located at `ai-post-scheduler/includes/class-aips-settings-ui.php`.
Retains backward-compatible hooks, all 55 `sanitize_*()` methods, and bespoke multi-step or interactive callbacks:
- AI Connectors move up / down ordering
- Google Search Console service account JSON tester & property picker
- Embeddings Quota meters
- Autolink composite thresholds & placement controls
- Magic `__call()`: Catches any legacy call to `{$key}_field_callback()` or `{$section}_section_callback()` and delegates directly to `AIPS_Settings_Form_Renderer`.

### 2.6 Dynamic Admin Template
Located at `ai-post-scheduler/templates/admin/settings.php`.
Iterates dynamically over tabs and sections:
```php
foreach ( $sections as $section ) {
    ?>
    <div class="aips-card aips-settings-section" id="<?php echo esc_attr( $section['id'] ); ?>">
        <div class="aips-card-header">
            <h2 class="aips-card-title"><?php echo esc_html( $section['title'] ); ?></h2>
        </div>
        <div class="aips-card-body">
            <table class="form-table" role="presentation">
                <?php do_settings_fields( 'aips-settings', $section['id'] ); ?>
            </table>
        </div>
    </div>
    <?php
}
```

---

## 3. How to Add a New Setting

To add a new setting to the plugin, you **only** need to declare it in `AIPS_Settings_Registry::register_default_definitions()`.

### Step 1: Open `class-aips-settings-registry.php`
Navigate to `register_default_definitions()` and call `$this->register_setting()` with `AIPS_Setting_Definition::create()`.

### Example 1: Simple Toggle (Switch)
```php
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_my_new_feature_enabled' )
        ->set_type( 'boolean' )
        ->set_default( false )
        ->set_tab( 'settings-general' )
        ->set_section( 'aips_general_section' )
        ->set_label( __( 'Enable My New Feature', 'ai-post-scheduler' ) )
        ->set_description( __( 'Toggle this option to enable enhanced scheduling logic.', 'ai-post-scheduler' ) )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_TOGGLE )
        ->set_priority( 25 )
);
```

### Example 2: Number with Min / Max
```php
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_batch_retry_limit' )
        ->set_type( 'integer' )
        ->set_default( 3 )
        ->set_tab( 'settings-advanced' )
        ->set_section( 'aips_advanced_queue_section' )
        ->set_label( __( 'Batch Retry Limit', 'ai-post-scheduler' ) )
        ->set_description( __( 'Maximum number of retries before marking a job failed.', 'ai-post-scheduler' ) )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_NUMBER )
        ->set_control_options( array(
            'min'  => 1,
            'max'  => 10,
            'step' => 1,
        ) )
);
```

### Example 3: Select Dropdown with Static or Callable Options
```php
// Static array options
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_article_tone' )
        ->set_type( 'string' )
        ->set_default( 'informative' )
        ->set_tab( 'settings-ai' )
        ->set_section( 'aips_ai_writing_section' )
        ->set_label( __( 'Default Tone', 'ai-post-scheduler' ) )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_SELECT )
        ->set_control_options( array(
            'options' => array(
                'informative' => __( 'Informative', 'ai-post-scheduler' ),
                'casual'      => __( 'Casual', 'ai-post-scheduler' ),
                'technical'   => __( 'Technical', 'ai-post-scheduler' ),
            ),
        ) )
);

// Callable runtime options
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_ai_provider' )
        ->set_type( 'string' )
        ->set_default( 'openai' )
        ->set_tab( 'settings-ai' )
        ->set_section( 'aips_ai_config_section' )
        ->set_label( __( 'AI Provider', 'ai-post-scheduler' ) )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_SELECT )
        ->set_control_options( array(
            'options' => array( 'AIPS_AI_Provider_Factory', 'get_provider_select_options' ),
        ) )
);
```

### Example 4: Setting with Visibility Dependency
To display a setting only when another toggle is active:
```php
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_advanced_cache_ttl' )
        ->set_type( 'integer' )
        ->set_default( 3600 )
        ->set_tab( 'settings-advanced' )
        ->set_section( 'aips_cache_section' )
        ->set_label( __( 'Cache TTL (seconds)', 'ai-post-scheduler' ) )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_NUMBER )
        ->set_dependencies( array(
            'aips_enable_cache_system' => true,
        ) )
);
```

### Example 5: Sub-field Stored Inside an Array Option (`parent_key`)
When multiple settings are stored together under a single option (such as `aips_notification_preferences`):
```php
$this->register_setting(
    AIPS_Setting_Definition::create( 'notification_event_post_published' )
        ->set_type( 'string' )
        ->set_default( 'instant' )
        ->set_tab( 'settings-notifications' )
        ->set_section( 'aips_notifications_content_section' )
        ->set_label( __( 'Post Published', 'ai-post-scheduler' ) )
        ->set_parent_key( 'aips_notification_preferences' )
        ->set_control_type( AIPS_Setting_Definition::CONTROL_SELECT )
        ->set_control_options( array(
            'options' => array( 'AIPS_Notifications', 'get_channel_mode_options' ),
        ) )
);
```

---

## 4. How to Add a New "Setting Group" (Section)

Sections group related settings into a card within a Tab.

### Step 1: Register Section in `class-aips-settings-registry.php`
Inside `register_default_sections()`:
```php
$this->register_section(
    'aips_seo_indexing_section',               // Section ID
    __( 'Search Indexing', 'ai-post-scheduler' ), // Title
    'settings-seo',                            // Parent Tab ID
    20,                                        // Priority / Order
    __( 'Configure crawl frequency and sitemap submission.', 'ai-post-scheduler' ) // Description
);
```

### Step 2: Assign Settings to the Section
Set `->set_section( 'aips_seo_indexing_section' )` and `->set_tab( 'settings-seo' )` on each setting definition.

The section card is rendered automatically by `templates/admin/settings.php`. No manual HTML editing is needed!

---

## 5. How to Add a New Setting Tab (Page)

Tabs appear on the vertical rail navigation on the left of the Settings page.

### Step 1: Register Tab in `class-aips-settings-registry.php`
Inside `register_default_tabs()`:
```php
$this->register_tab(
    'settings-seo',                            // Tab ID (hash matches #tab-settings-seo)
    __( 'SEO & Discovery', 'ai-post-scheduler' ), // Label
    'dashicons-search',                        // Dashicon class
    55                                         // Priority / Order
);
```

### Step 2: Add Sections and Settings under the Tab
Add sections with `$tab = 'settings-seo'` and their associated settings.
The rail navigation and tab switching operate dynamically.

---

## 6. Reading and Persisting Options

### Reading Settings
Always read settings via `AIPS_Config::get_instance()->get_option()` or `aips_get_option()`:
```php
$is_enabled = AIPS_Config::get_instance()->get_option( 'aips_my_new_feature_enabled' );
```
Because `AIPS_Config::get_default_options()` derives directly from `AIPS_Settings_Registry`, default fallbacks work automatically everywhere without duplicating default values.

### Persisting Settings
1. **Standard Form Submission**: Handled automatically via WordPress Settings API (`options.php`).
2. **Instant AJAX Persistence**: Handled automatically via `AIPS_Settings_AJAX` (`aips_save_setting_field` action). The AJAX handler inspects `AIPS_Settings_Registry` to validate the key and cast the value according to `AIPS_Setting_Definition::get_type()`.

---

## 7. Custom / Niche Renderers

For complex UI controls (e.g., drag-and-drop connectors or quota meters):
```php
$this->register_setting(
    AIPS_Setting_Definition::create( 'aips_connectors_ordering' )
        ->set_tab( 'settings-ai' )
        ->set_section( 'aips_ai_config_section' )
        ->set_label( __( 'Connector Priority', 'ai-post-scheduler' ) )
        ->set_render_callback( array( $this, 'render_connectors_custom_control' ) )
);
```
Or define a method in `AIPS_Settings_UI` and reference it via standard WordPress callback conventions.
