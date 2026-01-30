# Copy Session JSON Feature

## Overview

The "Copy Session JSON" feature provides a comprehensive JSON export of post generation sessions for debugging, business intelligence, and development purposes. This feature is accessible from the "Generated Posts" page's "View Session" modal.

## Purpose

This JSON export is designed for:
- **Debugging**: Deep dive into what happened during post generation
- **Business Intelligence**: Analyze generation patterns, AI usage, performance metrics
- **Development**: Test and verify generation workflows
- **Auditing**: Complete audit trail of post generation activities
- **Integration**: Export data to external analytics or monitoring systems

## Accessing the Feature

1. Navigate to **AI Post Scheduler > Generated Posts**
2. Click **View Session** button for any generated post
3. In the modal header, click **Copy Session JSON** button
4. The complete JSON is copied to your clipboard
5. A success notification appears confirming the copy

## JSON Structure

The exported JSON contains the following top-level keys:

### metadata
Information about the JSON export itself:
```json
{
  "generated_at": "2026-01-28 10:00:00",
  "generated_by": "AI Post Scheduler",
  "version": "1.7.0",
  "wordpress_version": "6.4",
  "site_url": "http://example.com",
  "user_id": 1,
  "user_login": "admin"
}
```

### post_id
The WordPress post ID (integer or null if generation failed)

### wp_post
Complete WordPress post object including:
- All standard WP_Post fields (ID, title, content, status, dates, etc.)
- Post meta (all custom fields)
- Categories (with full term objects)
- Tags (with full term objects)
- Featured image (with all sizes and URLs)
- Permalink
- Edit link

Example:
```json
{
  "ID": 123,
  "post_title": "My Generated Post",
  "post_content": "Full post content...",
  "post_status": "publish",
  "post_date": "2026-01-28 10:00:00",
  "post_meta": {
    "_edit_last": ["1"],
    "custom_field": ["value"]
  },
  "categories": [...],
  "tags": [...],
  "featured_image": {
    "id": 456,
    "url": "http://example.com/image.jpg",
    "sizes": {
      "thumbnail": "...",
      "medium": "...",
      "large": "...",
      "full": "..."
    }
  },
  "permalink": "http://example.com/my-post/",
  "edit_link": "http://example.com/wp-admin/post.php?post=123&action=edit"
}
```

### history
The main history record from the database:
```json
{
  "id": 1,
  "uuid": "abc123-def456-...",
  "post_id": 123,
  "template_id": 5,
  "status": "completed",
  "prompt": "The AI prompt used...",
  "generated_title": "My Generated Post",
  "generated_content": "Full content...",
  "error_message": null,
  "created_at": "2026-01-28 10:00:00",
  "completed_at": "2026-01-28 10:05:30"
}
```

### history_containers
Array of history containers with nested logs. Each container represents a logical grouping of operations (e.g., a post generation session).

**Container Structure:**
```json
{
  "uuid": "abc123-def456-...",
  "type": "post_generation",
  "status": "completed",
  "created_at": "2026-01-28 10:00:00",
  "completed_at": "2026-01-28 10:05:30",
  "metadata": {
    "template_id": 5,
    "post_id": 123
  },
  "logs": [...],
  "statistics": {
    "total_logs": 12,
    "log_types": {
      "5": 4,  // AI_REQUEST
      "6": 4   // AI_RESPONSE
    },
    "errors": 0,
    "warnings": 0,
    "ai_requests": 4,
    "ai_responses": 4
  }
}
```

**Log Entry Structure:**
Each log entry within a container includes:
```json
{
  "id": 1,
  "log_type": "title_request",
  "history_type_id": 5,
  "history_type_label": "AI Request",
  "timestamp": "2026-01-28 10:00:05",
  "details": {
    "input": {
      "prompt": "Generate a compelling title for...",
      "options": {...}
    },
    "context": {
      "component": "title"
    }
  }
}
```

**Note**: Base64-encoded content (marked with `output_encoded: true`) is automatically decoded in the JSON output.

## Log Types

The `history_type_id` field uses the following constants:

| ID | Label | Description |
|----|-------|-------------|
| 1 | Log | General log entry |
| 2 | Error | Error occurred |
| 3 | Warning | Warning message |
| 4 | Info | Informational message |
| 5 | AI Request | Request sent to AI service |
| 6 | AI Response | Response received from AI service |
| 7 | Debug | Debug information |
| 8 | Activity | User or system activity |
| 9 | Session Metadata | Session metadata |

## Use Cases

### Debugging Failed Generations
When a post generation fails, export the JSON to see:
- The exact AI prompts sent
- AI responses received
- Error messages with full context
- Timing information for each step
- Template and voice settings used

### Performance Analysis
Use the statistics section to analyze:
- Total time taken (from created_at to completed_at)
- Number of AI calls made
- Error rate
- Time between requests

### Business Intelligence
Extract insights from multiple exports:
- Most common generation patterns
- Template effectiveness
- Voice performance
- Content quality metrics
- Cost analysis (AI token usage)

### Integration Testing
Use exported JSON to:
- Create test fixtures
- Validate generation workflows
- Reproduce issues in development
- Document generation patterns

## Implementation Details

### PHP Class: AIPS_Session_To_JSON

Location: `includes/class-aips-session-to-json.php`

**Main Methods:**
- `generate_session_json($history_id)` - Generate complete session data array
- `generate_json_string($history_id, $pretty_print)` - Generate formatted JSON string

The class is designed to be easily extensible. To modify the JSON structure:

1. Edit the `generate_session_json()` method to add new top-level keys
2. Add helper methods for new data sources
3. Update the statistics calculation in `calculate_container_statistics()`

### AJAX Endpoint

Action: `aips_get_session_json`
Controller: `AIPS_Generated_Posts_Controller`
Method: `ajax_get_session_json()`

**Security:**
- Requires `manage_options` capability
- Validates AJAX nonce
- Sanitizes all inputs

### JavaScript

Location: `assets/js/admin-generated-posts.js`

**Key Functions:**
- `handleCopySessionJSON()` - Fetches and copies JSON to clipboard
- `copyToClipboard()` - Modern clipboard API implementation
- `fallbackCopyToClipboard()` - Fallback for older browsers
- `showNotification()` - User feedback display

### Browser Compatibility

The clipboard functionality uses:
1. **Modern**: `navigator.clipboard.writeText()` (Chrome 63+, Firefox 53+, Edge 79+)
2. **Fallback**: `document.execCommand('copy')` (IE 11+, older browsers)

## Extending the Feature

### Adding Custom Data to JSON

To include additional data in the JSON export:

1. Edit `class-aips-session-to-json.php`
2. Add a new method to fetch your data:
```php
private function get_custom_data($history_item) {
    // Fetch and return your custom data
    return array(
        'custom_field' => 'value'
    );
}
```

3. Add it to the session structure in `generate_session_json()`:
```php
$session_data = array(
    'metadata' => $this->get_metadata(),
    'post_id' => $history_item->post_id,
    'wp_post' => $this->get_wp_post_data($history_item->post_id),
    'history' => $this->format_history_item($history_item),
    'history_containers' => $this->get_history_containers($history_item),
    'custom_data' => $this->get_custom_data($history_item), // Add this
);
```

### Adding Export Formats

To add additional export formats (CSV, XML, etc.):

1. Create a new converter class: `class-aips-session-to-csv.php`
2. Add a new AJAX endpoint in the controller
3. Add a new button in the modal template
4. Add JavaScript handler for the new format

## Troubleshooting

### Button Not Working
- Check browser console for JavaScript errors
- Verify AJAX nonce is present in the page
- Ensure user has `manage_options` capability

### Clipboard Copy Fails
- Some browsers require HTTPS for clipboard API
- Ensure site is on HTTPS or use the fallback method
- Check browser console for security errors

### JSON Too Large
For very large sessions (many AI calls):
- JSON may be several MB in size
- Browser may struggle with very large clipboard operations
- Consider adding pagination or filtering options

### Missing Data in JSON
- Ensure the history record has completed successfully
- Check that all related data exists in the database
- Verify the post hasn't been deleted

## Future Enhancements

Potential improvements for future versions:

1. **Filtering Options**: Allow selecting which sections to export
2. **Multiple Formats**: Add CSV, XML, or other export formats
3. **Batch Export**: Export multiple sessions at once
4. **Scheduled Exports**: Automatic exports via WP-Cron
5. **Cloud Integration**: Direct export to S3, Google Drive, etc.
6. **Comparison Tool**: Compare two sessions side-by-side
7. **Analytics Dashboard**: Built-in analytics from exported data
8. **API Endpoint**: REST API for external integrations

## Related Files

- `includes/class-aips-session-to-json.php` - Main converter class
- `includes/class-aips-generated-posts-controller.php` - Controller with AJAX handler
- `assets/js/admin-generated-posts.js` - Frontend JavaScript
- `assets/css/admin.css` - Styling
- `templates/admin/generated-posts.php` - UI template
- `tests/test-session-to-json.php` - Unit tests

## Version History

- **v1.7.0** (2026-01-28): Initial implementation of Copy Session JSON feature
