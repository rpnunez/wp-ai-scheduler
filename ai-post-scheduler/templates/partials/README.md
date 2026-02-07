# View Session Modal - Reusable Component

This directory contains the reusable View Session modal component that can be included in any admin page.

## Files

- `view-session-modal.php` - The modal HTML and required JavaScript constants

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

At the end of your admin page template, include the partial:

```php
</div> <!-- End of your page content -->

<?php
// Include the View Session modal partial
include AIPS_PLUGIN_DIR . 'templates/partials/view-session-modal.php';
?>
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
- `AIPS_History_Type` constants (automatically included in partial)
- AJAX nonce for security (automatically included in partial)

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
- `templates/admin/generated-posts.php`
- `templates/admin/post-review.php`
