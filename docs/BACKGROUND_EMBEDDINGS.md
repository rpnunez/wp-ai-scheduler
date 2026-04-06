# Background Embeddings Processing

## Overview

The `aips_compute_topic_embeddings` AJAX endpoint has been converted from a synchronous inline operation to a scheduler-based background worker system.

## New Hook: `aips_process_author_embeddings`

### Description
Background action hook that processes topic embeddings in batches for a single author.

### Arguments
- `author_id` (int): The author ID to process topics for
- `batch_size` (int): Number of topics to process in each batch (default: 20, max: 100)
- `last_processed_id` (int): The last processed topic ID for ID-based pagination

### Behavior
- Processes approved topics incrementally using ID-based pagination (avoids slow OFFSET queries)
- Skips topics that already have embeddings (idempotent)
- Re-schedules itself automatically if more work remains
- Stores progress in transients: `aips_embeddings_progress_{author_id}` (TTL: 1 hour)
- Fires completion action: `aips_author_embeddings_completed` when done

### Scheduling
- Uses Action Scheduler (`as_schedule_single_action`) if available
- Falls back to `wp_schedule_single_event` if Action Scheduler not present
- Scheduled to run 5 seconds after being queued

## AJAX Endpoint Changes

### `aips_compute_topic_embeddings`

**Before**: Processed all embeddings synchronously in a single request

**After**: Queues background jobs and returns immediately

#### Parameters
- `author_id` (int): Author ID to process (0 = all authors)
- `batch_size` (int, optional): Batch size for processing (default: 20)

#### Response
```json
{
  "success": true,
  "data": {
    "message": "Queued embeddings processing for N author(s). Processing will run in the background.",
    "queued_count": 3
  }
}
```

## UI Helper Function

### JavaScript: `AIPS.Embeddings.queueEmbeddings(authorId, batchSize)`

**Parameters**:
- `authorId` (number): Author ID (0 for all authors)
- `batchSize` (number, optional): Batch size (default: 20)

**Example Usage**:
```javascript
// Queue embeddings for a specific author
AIPS.Embeddings.queueEmbeddings(42, 20);

// Queue embeddings for all authors
AIPS.Embeddings.queueEmbeddings(0, 20);
```

## Database Method Updates

### `AIPS_Author_Topics_Repository::get_approved_for_generation()`

**New signature**:
```php
public function get_approved_for_generation($author_id, $limit = 1, $after_id = 0)
```

**Changes**:
- Added `$after_id` parameter for ID-based pagination
- Uses `WHERE id > $after_id` instead of OFFSET
- Orders by `id ASC` for consistent pagination

## Service Method

### `AIPS_Topic_Expansion_Service::process_approved_embeddings_batch()`

**New method** for batched processing:
```php
public function process_approved_embeddings_batch($author_id, $batch_size = 20, $last_processed_id = 0)
```

**Returns**:
```php
array(
  'success' => 5,           // Number of embeddings successfully computed
  'failed' => 0,            // Number that failed
  'skipped' => 3,           // Number already had embeddings
  'last_processed_id' => 123, // Last topic ID processed
  'done' => false,          // Whether all topics processed
  'processed_count' => 8    // Total topics examined in this batch
)
```

## Progress Tracking

Progress is stored in transients for UI tracking:

**Transient key**: `aips_embeddings_progress_{author_id}`

**Data structure**:
```php
array(
  'success' => 10,
  'failed' => 2,
  'skipped' => 5,
  'last_processed_id' => 456,
  'done' => false,
  'processed_count' => 17,
  'timestamp' => 1234567890
)
```

**TTL**: 1 hour (`HOUR_IN_SECONDS`)

## Completion Hook

### `aips_author_embeddings_completed`

Fired when all topics for an author have been processed.

**Arguments**:
- `$author_id` (int): Author ID
- `$result` (array): Final stats array

**Example**:
```php
add_action('aips_author_embeddings_completed', function($author_id, $result) {
    error_log(sprintf(
        'Embeddings complete for author %d: %d success, %d failed, %d skipped',
        $author_id,
        $result['success'],
        $result['failed'],
        $result['skipped']
    ));
}, 10, 2);
```

## Files Modified

1. `ai-post-scheduler/includes/class-aips-author-topics-controller.php`
   - Modified `ajax_compute_topic_embeddings()` to schedule jobs
   - Added `schedule_embeddings_job()` helper method

2. `ai-post-scheduler/includes/class-aips-topic-expansion-service.php`
   - Added `process_approved_embeddings_batch()` method

3. `ai-post-scheduler/includes/class-aips-author-topics-repository.php`
   - Updated `get_approved_for_generation()` with `$after_id` parameter

4. `ai-post-scheduler/includes/class-aips-admin-assets.php`
   - Registered `admin-embeddings.js` script

5. `ai-post-scheduler/ai-post-scheduler.php`
   - Registered `aips_process_author_embeddings` action hook

## Files Added

1. `ai-post-scheduler/includes/class-aips-embeddings-cron.php`
   - New background worker class

2. `ai-post-scheduler/assets/js/admin-embeddings.js`
   - UI helper function for queueing jobs

## History Container Logging

The embeddings background worker uses the History Container pattern for comprehensive activity logging.

### History Type: `author_embeddings`

A history container is created (or reused if incomplete) for each author's embeddings processing run.

**Container Metadata**:
- `author_id`: The author ID being processed

**Events Logged**:

1. **Batch Start** (`embeddings_batch_start`)
   - Records when a batch begins processing
   - Includes `batch_size` and `last_processed_id`

2. **Batch Complete** (`embeddings_batch_complete`)
   - Records batch completion statistics
   - Includes success/failed/skipped counts

3. **Embedding Computed** (`embedding_computed`)
   - Records successful embedding computation for a topic
   - Includes `topic_id` and `topic_title`

4. **Embedding Skipped** (`embedding_skipped`)
   - Records when a topic already has an embedding
   - Includes `topic_id` and `topic_title`

5. **Embedding Failed** (`embedding_failed`)
   - Records computation failures
   - Includes `topic_id`, `topic_title`, and error message

6. **Batch Empty** (`embeddings_batch_empty`)
   - Records when no topics are found in a batch

**Example History Query**:
```php
// Get embeddings history for an author
$history_service = new AIPS_History_Service();
$containers = $history_service->find_by_type('author_embeddings', array(
    'author_id' => 42,
));
```

## Testing

To test the implementation:

1. **Queue a job via JavaScript console**:
   ```javascript
   AIPS.Embeddings.queueEmbeddings(0, 20); // Queue all authors
   ```

2. **Check scheduled events**:
   ```php
   // Check wp-cron
   $events = _get_cron_array();

   // Or check Action Scheduler (if available)
   as_get_scheduled_actions(array('hook' => 'aips_process_author_embeddings'));
   ```

3. **Monitor progress**:
   ```php
   $progress = get_transient('aips_embeddings_progress_42');
   var_dump($progress);
   ```

4. **Run cron manually**:
   ```bash
   wp cron event run aips_process_author_embeddings --due-now
   ```
