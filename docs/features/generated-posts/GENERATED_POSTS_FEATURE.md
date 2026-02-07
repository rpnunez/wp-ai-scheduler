# Generated Posts Feature

## Overview

The Generated Posts feature provides a comprehensive view of all posts created by the AI Post Scheduler plugin, along with detailed session information showing every step of the generation process.

## Features

### 1. Generated Posts Admin Page

Located at: **AI Post Scheduler → Generated Posts**

This page displays a table showing all successfully generated posts with the following information:

- **Title**: The post title with a link to edit the post
- **Date Scheduled**: When the post was scheduled (if applicable)
- **Date Published**: The WordPress publish date from the posts table
- **Date Generated**: When the AI Post Scheduler created the post

### 2. View Session Modal

Clicking "View Session" on any post opens a detailed modal showing:

#### Logs Tab
Shows all logging, debug, and activity information related to the post generation:
- Log entries with timestamps
- Error messages (highlighted in red)
- Warning messages (highlighted in yellow)
- Activity events (post published, draft created, etc.)

#### AI Tab
Shows all AI interactions that occurred during post generation:
- Title generation (request and response)
- Content generation (request and response)
- Excerpt generation (request and response)
- Featured image generation (request and response)

Each AI interaction is clickable to expand and view:
- **Request**: The full prompt sent to the AI, including options
- **Response**: The raw response received from the AI

## Unified History System

The plugin now uses a unified history system with the following type classifications:

### History Types (AIPS_History_Type constants)

1. **LOG** (1): General log entries
2. **ERROR** (2): Error entries
3. **WARNING** (3): Warning entries
4. **INFO** (4): Informational entries
5. **AI_REQUEST** (5): AI request entries (prompts sent to AI)
6. **AI_RESPONSE** (6): AI response entries (responses from AI)
7. **DEBUG** (7): Debug entries
8. **ACTIVITY** (8): Activity entries (high-level events)
9. **SESSION_METADATA** (9): Session metadata entries

### Data Storage

All history data is stored in the `aips_history_log` table with:
- `history_id`: Links to the main history entry
- `log_type`: Specific type of log (e.g., 'title_request', 'content_response')
- `history_type_id`: Classification using AIPS_History_Type constants
- `timestamp`: When the entry was created
- `details`: JSON-encoded details specific to the log type

### Benefits

1. **Unified View**: All data related to a post generation is in one place
2. **Easy Filtering**: Filter by history type to see only AI calls, errors, etc.
3. **Complete Traceability**: Every step of the generation process is logged
4. **Debugging**: Easy to identify where issues occurred
5. **Transparency**: Users can see exactly what prompts were sent and what responses were received

## Technical Implementation

### Database Schema Changes

Added `history_type_id` column to `aips_history_log` table:
```sql
ALTER TABLE wp_aips_history_log ADD COLUMN history_type_id int DEFAULT 1 AFTER log_type;
```

### New Classes

1. **AIPS_History_Type**: Defines constants and helper methods for history types
2. **AIPS_Generated_Posts_Controller**: Handles the Generated Posts admin page and AJAX requests

### Modified Classes

1. **AIPS_Generator**: Now logs AI requests separately from responses
2. **AIPS_History_Repository**: Supports the new `history_type_id` parameter
3. **AIPS_Activity_Repository**: Logs activities to the unified history system

## Usage for Developers

### Adding a Custom Log Entry

```php
$history_repo = new AIPS_History_Repository();
$history_repo->add_log_entry(
    $history_id,
    'custom_log_type',
    array(
        'message' => 'Custom log message',
        'data' => array('key' => 'value'),
        'timestamp' => current_time('mysql'),
    ),
    AIPS_History_Type::INFO
);
```

### Filtering by History Type

```php
// Get all AI requests for a history entry
$history_item = $history_repo->get_by_id($history_id);
$ai_requests = array_filter($history_item->log, function($log) {
    return isset($log->history_type_id) && $log->history_type_id === AIPS_History_Type::AI_REQUEST;
});
```

## Future Enhancements

Potential improvements for future versions:

1. Export session data as JSON or PDF
2. Compare sessions to see what changed between generations
3. Replay a session with different parameters
4. Advanced filtering and search in the session viewer
5. Statistics dashboard showing common errors and patterns
