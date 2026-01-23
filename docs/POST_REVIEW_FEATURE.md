# Post Review Feature Documentation

## Overview

The Post Review feature provides a dedicated admin page for reviewing and managing AI-generated posts that are configured to be set to "Draft" status before publishing. This allows administrators to manually review posts, make edits if needed, and then publish them when ready.

## Features

### 1. Post Review Admin Page

A new "Post Review" menu item has been added to the AI Post Scheduler admin menu, positioned between "Activity" and "Schedule" for easy access.

### 2. Draft Posts Listing

The Post Review page displays all generated posts that meet the following criteria:
- Post status is "draft"
- Post was successfully generated (status = 'completed' in history)
- Post has an associated history record

### 3. Individual Post Actions

Each draft post in the listing has the following action buttons:

- **Edit**: Opens the post in the WordPress editor for manual editing
- **View Logs**: Opens a modal displaying the generation logs, including:
  - The AI prompt used
  - Generation log details
  - Any error messages (if applicable)
- **Publish**: Immediately publishes the post and removes it from the review queue
- **Re-generate**: Deletes the current post and regenerates it using the same template
- **Delete**: Permanently deletes the post and removes it from the review queue

### 4. Bulk Operations

The page includes checkboxes for each post and bulk action controls:

- **Select All**: Checkbox in the table header to select/deselect all posts
- **Bulk Publish**: Publish multiple selected posts at once
- **Bulk Delete**: Delete multiple selected posts at once

### 5. Filtering and Search

- **Search**: Search posts by title
- **Template Filter**: Filter posts by the template used to generate them
- **Pagination**: Navigate through large numbers of draft posts

### 6. Statistics

The page displays the total count of draft posts awaiting review at the top.

## Technical Implementation

### Classes

#### AIPS_Post_Review_Repository
- **Location**: `includes/class-aips-post-review-repository.php`
- **Purpose**: Database abstraction layer for querying draft posts
- **Key Methods**:
  - `get_draft_posts($args)`: Retrieves paginated draft posts with filtering
  - `get_draft_count()`: Returns the total count of draft posts

#### AIPS_Post_Review
- **Location**: `includes/class-aips-post-review.php`
- **Purpose**: Controller for the Post Review page, handles AJAX requests
- **Key Methods**:
  - `ajax_publish_post()`: Publishes a single post
  - `ajax_bulk_publish_posts()`: Publishes multiple posts
  - `ajax_regenerate_post()`: Regenerates a post
  - `ajax_delete_draft_post()`: Deletes a single post
  - `ajax_bulk_delete_draft_posts()`: Deletes multiple posts

### Templates

#### post-review.php
- **Location**: `templates/admin/post-review.php`
- **Purpose**: Renders the Post Review admin page UI
- **Features**:
  - Draft posts table with action buttons
  - Search and filter controls
  - Bulk action controls
  - Log viewer modal

### Assets

#### admin-post-review.js
- **Location**: `assets/js/admin-post-review.js`
- **Purpose**: Handles client-side interactions and AJAX requests
- **Key Features**:
  - Select all/deselect all functionality
  - Individual action handlers (publish, delete, regenerate, view logs)
  - Bulk action handlers
  - Modal display for log viewing
  - User notifications

#### admin.css
- **Location**: `assets/css/admin.css`
- **Purpose**: Styling for the Post Review page
- **New Styles Added**:
  - `.aips-post-review-stats`: Statistics display
  - `.aips-action-buttons`: Action button layout
  - `.aips-log-details`: Log viewer modal content
  - Various utility classes for the review interface

## Usage

### For End Users

1. **Navigate to the Post Review Page**:
   - In WordPress admin, go to: AI Post Scheduler → Post Review

2. **Review Draft Posts**:
   - Browse the list of draft posts
   - Use search/filter to find specific posts
   - Click "Edit" to modify a post in the WordPress editor

3. **View Generation Details**:
   - Click "View Logs" to see the AI prompt and generation details

4. **Publish Posts**:
   - Click "Publish" on individual posts to publish them immediately
   - Or select multiple posts and use "Bulk Actions → Publish"

5. **Regenerate Posts**:
   - Click "Re-generate" if you want the AI to create a new version
   - The current post will be deleted and a new one generated

6. **Delete Posts**:
   - Click "Delete" to permanently remove unwanted posts
   - Or use bulk delete for multiple posts

### For Developers

#### Extending the Feature

The Post Review feature fires WordPress actions for extensibility:

```php
// When a post is published from review
do_action('aips_post_review_published', $post_id);

// When a post is deleted from review
do_action('aips_post_review_deleted', $post_id);

// When a post is regenerated
do_action('aips_post_review_regenerated', $history_id);
```

#### Filtering Results

You can filter the draft posts query using:

```php
add_filter('aips_post_review_query_args', function($args) {
    // Modify query arguments
    return $args;
});
```

## Security

- All AJAX requests are protected with nonce verification
- User capability checks ensure only administrators can access the feature
- All inputs are sanitized and outputs are escaped
- SQL queries use prepared statements via the repository pattern

## Activity Logging

All actions performed in the Post Review page are logged in the Activity log:
- Post published from review queue
- Post deleted from review queue
- Each action includes post ID and timestamp

## Database Queries

The feature uses efficient SQL queries:
- Joins the history table with wp_posts to filter by post_status
- Uses pagination to limit memory usage
- Supports search and filtering without performance impact
- Indexed fields for fast queries

## Browser Compatibility

The JavaScript code is compatible with:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Internet Explorer 11+ (with jQuery)

## Future Enhancements

Potential improvements for future versions:
- Preview mode for posts before publishing
- Scheduled publishing from the review queue
- Comparison view for regenerated posts
- Revision history tracking
- Email notifications for posts awaiting review
- Custom review workflows with approval stages

## Testing

Tests are located in:
- `tests/test-post-review-repository.php`: Unit tests for the repository
- `tests/integration-test-post-review.php`: Integration test script

Run tests with:
```bash
vendor/bin/phpunit ai-post-scheduler/tests/test-post-review-repository.php
php ai-post-scheduler/tests/integration-test-post-review.php
```

## Troubleshooting

### No Draft Posts Showing

**Issue**: The Post Review page shows "No Draft Posts" even though you have draft posts.

**Solutions**:
1. Check that posts have a corresponding history record
2. Verify the post_status is 'draft'
3. Ensure the history status is 'completed'
4. Check database for orphaned records

### AJAX Errors

**Issue**: Actions fail with "Permission denied" or AJAX errors.

**Solutions**:
1. Verify user has 'manage_options' capability
2. Check that nonces are valid
3. Look at browser console for JavaScript errors
4. Check WordPress debug.log for PHP errors

### Log Viewer Not Working

**Issue**: "View Logs" button doesn't show logs.

**Solutions**:
1. Verify history record exists for the post
2. Check that AJAX URL is correct
3. Look for JavaScript console errors
4. Ensure modal CSS is loaded

## Support

For issues or questions:
- Check the TESTING.md documentation
- Review the WordPress debug log
- Enable 'aips_enable_logging' option for detailed logs
- Contact plugin support with reproduction steps
