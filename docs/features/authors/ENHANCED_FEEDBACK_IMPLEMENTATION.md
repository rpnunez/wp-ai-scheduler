# Enhanced Topic Feedback & Expansion Implementation

## Overview

This implementation adds advanced feedback persistence and topic expansion features to the AI Post Scheduler's Author Topics system. It enables structured feedback reasons with tailored actions and uses embeddings for semantic topic suggestions.

## Features Implemented

### 1. Enhanced Feedback Persistence

#### Database Schema Changes

The `aips_topic_feedback` table now includes:
- `reason_category` (varchar): Categorizes feedback (duplicate/tone/irrelevant/policy/other)
- `source` (varchar): Tracks feedback origin (UI/automation)

These columns are added to the existing schema in `class-aips-db-manager.php` and will be automatically created via WordPress's `dbDelta()` on next plugin activation or upgrade.

#### Enhanced AIPS_Feedback_Repository

New methods added:
- `get_by_reason_category($reason_category, $author_id = null)`: Query feedback by category
- `get_reason_category_statistics($author_id = null)`: Get aggregated statistics by reason category

Updated methods:
- `record_approval()` and `record_rejection()` now accept `reason_category` and `source` parameters
- `create()` validates and stores the new fields

### 2. Reason-Based Actions (AIPS_Topic_Penalty_Service)

#### Penalty System

Different penalties based on rejection reasons:
- **Duplicate**: -10 points (soft penalty)
- **Tone**: -5 points (minimal penalty)
- **Irrelevant**: -15 points (moderate penalty)
- **Policy**: -50 points (hard penalty)
- **Other**: -5 points (minimal penalty)

#### Policy Violation Tracking

When a topic is rejected for policy reasons:
1. A hard penalty (-50 points) is applied to the topic score
2. The author is flagged for policy review
3. Policy flags are tracked in the author's `details` metadata
4. After 3+ policy violations, a warning is logged (optional auto-deactivation can be enabled)

#### Approval Rewards

Topics receive +10 points when approved, encouraging high-quality content.

### 3. Embeddings Infrastructure (AIPS_Embeddings_Service)

#### Features

- **Embedding Generation**: Uses Meow AI Engine to generate text embeddings
- **Similarity Calculation**: Cosine similarity between embedding vectors
- **Caching**: In-memory cache to avoid redundant API calls
- **Batch Processing**: Generate embeddings for multiple texts efficiently
- **Nearest Neighbors**: Find most similar items to a target embedding

#### Technical Details

The service integrates with Meow AI's embedding functionality (via `Meow_MWAI_Query_Embed` if available). If embeddings are not supported by the AI Engine configuration, appropriate error messages are returned.

### 4. Topic Expansion (AIPS_Topic_Expansion_Service)

#### Features

- **Compute Topic Embeddings**: Generate and store embeddings for topics
- **Find Similar Topics**: Use semantic similarity to find related topics
- **Suggest Related Topics**: Recommend pending topics similar to approved ones
- **Expanded Context**: Generate enhanced context for prompts using similar approved topics

#### Storage

Embeddings are stored in the `metadata` column of the `aips_author_topics` table as JSON:
```json
{
  "embedding": [0.123, 0.456, ...],
  "other_metadata": "..."
}
```

#### Use Cases

1. **Editor Suggestions**: Show pending topics similar to approved ones
2. **Prompt Enhancement**: Include related approved topics in generation prompts
3. **Topic Discovery**: Find semantically similar topics across the author's content

### 5. AJAX API Endpoints

#### Existing Enhanced Endpoints

- `aips_approve_topic`: Now accepts `reason_category` and `source`
- `aips_reject_topic`: Now accepts `reason_category` and `source`

#### New Endpoints

- `aips_get_similar_topics`: Get topics similar to a specific topic
  - Parameters: `topic_id`, `author_id`, `limit`
  - Returns: Array of similar topics with similarity scores

- `aips_suggest_related_topics`: Get pending topics similar to approved ones
  - Parameters: `author_id`, `limit`
  - Returns: Array of suggestions with similarity scores

- `aips_compute_topic_embeddings`: Batch compute embeddings for approved topics
  - Parameters: `author_id`
  - Returns: Statistics (success, failed, skipped counts)

## Usage Examples

### Approving a Topic with Reason

```javascript
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'aips_approve_topic',
    nonce: aips_ajax.nonce,
    topic_id: 123,
    reason: 'Excellent topic that aligns with our content strategy',
    reason_category: 'other',
    source: 'UI'
  }
});
```

### Rejecting a Topic for Policy Violation

```javascript
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'aips_reject_topic',
    nonce: aips_ajax.nonce,
    topic_id: 456,
    reason: 'Contains prohibited content',
    reason_category: 'policy',
    source: 'UI'
  }
});
```

### Getting Similar Topics

```javascript
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'aips_get_similar_topics',
    nonce: aips_ajax.nonce,
    topic_id: 123,
    author_id: 1,
    limit: 5
  },
  success: function(response) {
    // response.data.similar_topics contains array of similar topics
  }
});
```

### Getting Topic Suggestions

```javascript
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'aips_suggest_related_topics',
    nonce: aips_ajax.nonce,
    author_id: 1,
    limit: 10
  },
  success: function(response) {
    // response.data.suggestions contains recommended pending topics
  }
});
```

### Computing Embeddings

```javascript
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'aips_compute_topic_embeddings',
    nonce: aips_ajax.nonce,
    author_id: 1
  },
  success: function(response) {
    // response.data.stats contains {success, failed, skipped} counts
  }
});
```

## Integration Points

### With Existing Feedback System

The enhanced feedback system is backward compatible. Existing code that doesn't pass `reason_category` or `source` will use default values ('other' and 'UI' respectively).

### With Topic Generation

Future enhancements can integrate the expanded context into the topic generation prompts:

```php
$expansion_service = new AIPS_Topic_Expansion_Service();
$context = $expansion_service->get_expanded_context($author_id, $topic_id, 5);
// Add $context to the generation prompt
```

### With Author Post Generator

The penalty service automatically adjusts topic scores, which can be used to prioritize topics for post generation:

```php
// Get approved topics sorted by score
$topics = $topics_repository->get_approved_for_generation($author_id, 10);
// Higher scored topics will naturally be selected first
```

## Configuration

### Customizing Penalty Weights

```php
$penalty_service = new AIPS_Topic_Penalty_Service();
$penalty_service->set_penalty_weights(array(
  'duplicate' => -20,
  'custom_reason' => -30
));
```

### Embedding Options

```php
$embeddings_service = new AIPS_Embeddings_Service();
$embedding = $embeddings_service->generate_embedding(
  $text,
  array('embeddings_env_id' => 'custom-env-id')
);
```

## Testing

Tests have been created for:
- Enhanced feedback repository functionality
- Penalty service with various scenarios
- Score bounds checking
- Policy flag management

Run tests with:
```bash
composer test
```

## Future Enhancements

1. **UI Integration**: Add reason category dropdowns in admin interface
2. **Analytics Dashboard**: Visualize feedback statistics by category
3. **Automated Policy Detection**: Use AI to automatically detect policy violations
4. **Embedding-Based Duplicate Detection**: Use similarity scores to detect duplicate topics
5. **Configurable Penalty UI**: Admin panel to adjust penalty weights
6. **Prompt Templates**: Pre-defined prompt enhancement templates using similar topics

## Dependencies

- **Meow Apps AI Engine**: Required for embedding generation
- **WordPress**: 5.8+
- **PHP**: 7.4+

## Database Migrations

The schema changes are applied automatically via WordPress's `dbDelta()` function when:
1. The plugin is activated
2. The plugin version changes
3. Manual database repair is triggered from the admin panel

No manual SQL scripts needed!

## Performance Considerations

1. **Embedding Cache**: In-memory cache reduces API calls during batch operations
2. **Lazy Computation**: Embeddings are computed on-demand, not all at once
3. **Indexed Columns**: `reason_category` and `source` columns are indexed for fast queries
4. **Metadata Storage**: Embeddings stored as JSON in existing metadata column (no new table)

## Security

- All AJAX endpoints require nonce verification
- User capability checks (`manage_options`) on all admin actions
- Input sanitization for all user-provided data
- SQL queries use prepared statements via repository pattern
- No direct database access from controllers

## Notes

- Embeddings require Meow AI Engine with embedding support
- If embeddings are not available, the system gracefully degrades
- Policy flags are stored in author metadata (not a separate table)
- Score bounds are enforced (0-100 range)
