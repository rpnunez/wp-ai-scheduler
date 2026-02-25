# Authors Feature API Reference

## Overview

This document describes the AJAX endpoints and repository methods available for the Authors feature.

## AJAX Endpoints

### Author Management

- **`wp_ajax_aips_save_author`** - Create/update author
- **`wp_ajax_aips_get_author`** - Get author by ID
- **`wp_ajax_aips_delete_author`** - Delete author
- **`wp_ajax_aips_get_author_topics`** - Get topics for author
- **`wp_ajax_aips_get_author_posts`** - Get generated posts
- **`wp_ajax_aips_generate_topics_now`** - Manual topic generation

### Topic Management

- **`wp_ajax_aips_approve_topic`** - Approve a topic
- **`wp_ajax_aips_reject_topic`** - Reject a topic
- **`wp_ajax_aips_edit_topic`** - Edit topic title
- **`wp_ajax_aips_delete_topic`** - Delete topic
- **`wp_ajax_aips_generate_post_from_topic`** - Generate post now
- **`wp_ajax_aips_get_topic_logs`** - View topic audit trail
- **`wp_ajax_aips_bulk_approve_topics`** - Bulk approve
- **`wp_ajax_aips_bulk_reject_topics`** - Bulk reject
- **`wp_ajax_aips_regenerate_post`** - Regenerate existing post
- **`wp_ajax_aips_delete_generated_post`** - Delete generated post

## Repository Methods

### AIPS_Authors_Repository

```php
get_all($active_only = false) // Get all authors
get_by_id($id) // Get single author
create($data) // Create author
update($id, $data) // Update author
delete($id) // Delete author
get_due_for_topic_generation() // Get authors due for topics
get_due_for_post_generation() // Get authors due for posts
```

### AIPS_Author_Topics_Repository

```php
get_by_author($author_id, $status = null) // Get author's topics
get_by_id($id) // Get single topic
create($data) // Create topic
update($id, $data) // Update topic
delete($id) // Delete topic
update_status($id, $status, $user_id) // Change topic status
get_approved_for_generation($author_id, $limit = 1) // Get approved topics
get_approved_summary($author_id, $limit = 20) // For feedback loop
get_rejected_summary($author_id, $limit = 20) // For feedback loop
get_status_counts($author_id) // Count by status
```
