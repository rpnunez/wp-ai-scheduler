# View Session Modal - Reusable Component

This directory contains legacy PHP partials. Active admin pages now consume Twig modal partials.

## Files

- `view-session-modal.php` - Legacy PHP modal partial retained for compatibility.

## Usage

To add View Session functionality to a new admin page:

### 1. Enqueue the JavaScript

In `includes/class-aips-admin-assets.php`, add the view-session script:

```php
// Your Page Scripts
if (strpos($hook, 'your-page-slug') !== false) {
    // Enqueue View Session module
    wp_enqueue_script(
        'aips-admin-view-session',
        AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
        array('aips-admin-script'),
        AIPS_VERSION,
        true
    );
    
    // Your page-specific scripts can depend on it
    wp_enqueue_script(
        'your-page-script',
        AIPS_PLUGIN_URL . 'assets/js/your-page.js',
        array('aips-admin-script', 'aips-admin-view-session'),
        AIPS_VERSION,
        true
    );
}
```

### 2. Include the Modal Template

For Twig-native pages, include the Twig partial:

```twig
{% include 'partials/view-session-modal.html.twig' %}
```

### 3. Add View Session Buttons

Add buttons/links with the required class and data attribute:

```php
<button class="button button-small aips-view-session" 
        data-history-id="<?php echo esc_attr($item->id); ?>">
    <?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
</button>
```

### 4. Done!

The View Session modal will automatically work. When users click the button:
- The modal opens and shows loading state
- Session data is fetched via AJAX (`aips_get_post_session` action)
- Logs and AI calls are displayed in tabs
- Users can copy or download the session JSON

## Features Included

- ✅ Session metadata display (title, dates)
- ✅ Tabbed interface (Logs / AI)
- ✅ Formatted log entries with color coding (errors, warnings)
- ✅ AI request/response pairs grouped by component
- ✅ Copy session JSON to clipboard
- ✅ Download session JSON as file
- ✅ Expandable AI component details
- ✅ Modal close handlers (button, overlay, ESC key)
- ✅ Secure XSS prevention
- ✅ Responsive and accessible

## Dependencies

- jQuery (included in WordPress)
- WordPress admin styles
- `AIPS_History_Type` constants (passed via `history_type_map`)
- AJAX nonce for security (passed via `ajax_nonce`)

## AJAX Endpoint

The component uses the existing `aips_get_post_session` AJAX endpoint defined in `AIPS_Generated_Posts_Controller`:

```php
add_action('wp_ajax_aips_get_post_session', array($this, 'ajax_get_post_session'));
```

No additional backend setup is required.

## CSS Styles

All required styles are already defined in `assets/css/admin.css`:
- `.aips-modal`
- `.aips-modal-overlay`
- `.aips-tab-nav`
- `.aips-ai-component`
- `.aips-log-entry`
- And more...

No additional CSS needed.

## Examples

See these pages for working examples:
- `templates/admin/twig/pages/content.html.twig`
- `templates/admin/twig/pages/history.html.twig`
