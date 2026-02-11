# Logging System Refactoring - Version 2.1.0

## Overview

The logging system has been completely refactored to use the History Container-based system as the primary logging mechanism. This change simplifies the API, reduces code duplication, and provides a unified approach to logging throughout the plugin.

**Requirements**: PHP 8.2 or higher

## What Changed

### Simplified `record()` Signature

**Old Signature (5 parameters):**
```php
record($log_type, $message, $input = null, $output = null, $context = array())
```

**New Signature (3 parameters):**
```php
record($log_type, $message, $context = array())
```

### Key Improvements

1. **Fewer Parameters**: Reduced from 5 to 3 parameters
2. **Flexible Context**: All data now passed via a single `$context` array
3. **Built-in error_log**: PHP error_log integration when `WP_DEBUG` is enabled
4. **Cleaner Code**: No more `null, null` when you only need context
5. **Breaking Change**: Old 5-parameter calls must be updated

## Migration Guide

All code using the old 5-parameter signature must be updated to use the new 3-parameter signature with the `$context` array.

### Before & After Examples

#### Example 1: Simple Activity Logging

**Before:**
```php
$history->record(
    'activity',
    'Post generated successfully',
    null,
    null,
    array('post_id' => 123, 'template_id' => 456)
);
```

**After:**
```php
$history->record(
    'activity',
    'Post generated successfully',
    array('post_id' => 123, 'template_id' => 456)
);
```

#### Example 2: AI Request Logging

**Before:**
```php
$history->record(
    'ai_request',
    'Requesting AI generation',
    array('prompt' => $prompt, 'options' => $options),
    null,
    array('component' => 'title')
);
```

**After:**
```php
$history->record(
    'ai_request',
    'Requesting AI generation',
    array(
        'input' => array('prompt' => $prompt, 'options' => $options),
        'component' => 'title',
    )
);
```

#### Example 3: AI Response Logging

**Before:**
```php
$history->record(
    'ai_response',
    'AI generation successful',
    null,
    $result,
    array('component' => 'title')
);
```

**After:**
```php
$history->record(
    'ai_response',
    'AI generation successful',
    array(
        'output' => $result,
        'component' => 'title',
    )
);
```

#### Example 4: Error Logging

**Before:**
```php
$history->record(
    'error',
    'Generation failed: ' . $error->get_error_message(),
    array('prompt' => $prompt),
    null,
    array('component' => 'content', 'error' => $error->get_error_message())
);
```

**After:**
```php
$history->record(
    'error',
    'Generation failed: ' . $error->get_error_message(),
    array(
        'input' => array('prompt' => $prompt),
        'component' => 'content',
        'error' => $error->get_error_message(),
    )
);
```

## Context Array Structure

The `$context` array is completely flexible. You can include any keys you need:

### Reserved Keys (handled specially by record())

- **`input`**: Input data for the operation (moved to `details.input` in database)
  - Arrays/objects stored as-is
  - Scalar values wrapped in `{value: ...}`
- **`output`**: Output/result data (moved to `details.output` in database)
  - Large strings (>500 chars) are base64-encoded with `output_encoded: true`
  - Arrays/objects stored as-is
  - Scalar values wrapped in `{value: ...}`

### Common Context Keys (best practices)

- **`component`**: Which component is logging (e.g., 'title', 'content', 'featured_image')
- **`error`**: Error message or details
- **`error_code`**: Standardized error code
- **`prompt`**: AI prompts (if not in input)
- **`response`**: AI responses (if not in output)
- **`options`**: Configuration options used
- **`duration_ms`**: Operation duration in milliseconds
- **`attempt`**: Retry attempt number
- **`topic_id`**, **`post_id`**, **`template_id`**: Related entity IDs

### Custom Keys

You can add any custom keys your use case requires:

```php
$history->record(
    'info',
    'Custom operation completed',
    array(
        'custom_field_1' => 'value1',
        'custom_field_2' => 'value2',
        'nested_data' => array(
            'key' => 'value',
        ),
    )
);
```

## Helper Methods

Helper methods remain unchanged and continue to work as before:

### record_error()

```php
$history->record_error(
    'Error message',
    array(
        'component' => 'generator',
        'error_code' => 'GENERATION_FAILED',
    ),
    $wp_error  // Optional WP_Error object
);
```

Automatically adds:
- `timestamp` (microtime)
- `php_error` (error_get_last())
- `memory_usage`
- `memory_peak`
- WP_Error details (if provided)

### record_user_action()

```php
$history->record_user_action(
    'manual_generation',
    'User manually generated post',
    array(
        'topic_id' => 123,
        'custom_data' => 'value',
    )
);
```

Automatically adds:
- `action_type`
- `user_id`
- `user_login`
- `timestamp` (microtime)
- `source` = 'manual_ui'

## PHP error_log Integration

When `WP_DEBUG` is enabled, all `record()` calls now automatically write to PHP's error_log:

```
[AIPS History] [INFO] Post generated successfully | Context: {"post_id":123,"template_id":456}
```

This provides real-time debugging without needing to check the database.

## Deprecated Classes

### AIPS_Generation_Logger

**Status**: Deprecated in 2.1.0

This class is no longer needed as its functionality is now built into `AIPS_History_Container`. It's maintained for backward compatibility but should not be used in new code.

**Migration:**

**Before:**
```php
$this->generation_logger->log('Message', 'info', array('data' => 'value'));
```

**After:**
```php
$this->logger->log('Message', 'info', array('data' => 'value'));
```

Or even better, use History Container directly:

```php
$this->current_history->record('info', 'Message', array('data' => 'value'));
```

## AIPS_Logger Role

`AIPS_Logger` remains for:
- File-based logging to `/wp-content/uploads/aips-logs/`
- PHP error_log output when WP_DEBUG is enabled
- Log file management (clear, retrieve)

For structured logging within generation processes, use `AIPS_History_Container` via `AIPS_History_Service`.

## Best Practices

### 1. Use History Container for Process Tracking

```php
$history = $this->history_service->create('post_generation', array(
    'template_id' => $template_id,
));

$history->record('activity', 'Generation started', array('source' => 'scheduled'));

// ... process logic ...

$history->record('ai_request', 'Calling AI for title', array(
    'input' => array('prompt' => $prompt),
));

// ... more logging ...

$history->complete_success(array('post_id' => $post_id));
```

### 2. Group Related Data in Context

```php
// Good: Related data together
$history->record('info', 'Template processed', array(
    'template' => array(
        'id' => 123,
        'name' => 'Daily Post',
        'structure' => 'default',
    ),
    'variables_resolved' => 15,
));

// Less ideal: Flat structure
$history->record('info', 'Template processed', array(
    'template_id' => 123,
    'template_name' => 'Daily Post',
    'template_structure' => 'default',
    'variables_resolved' => 15,
));
```

### 3. Use Appropriate Log Types

- **`activity`**: High-level events (shown in Activity feed)
- **`error`**: Errors (shown in Activity feed)
- **`ai_request`**: Before AI calls
- **`ai_response`**: After successful AI calls
- **`info`**: Informational messages
- **`warning`**: Non-critical issues
- **`debug`**: Development/troubleshooting data
- **`log`**: General logging

### 4. Add Component Context

Always include a `component` key to identify which part of the code is logging:

```php
$history->record('info', 'Processing complete', array(
    'component' => 'title_generator',
    'duration_ms' => 234,
));
```

### 5. Use Helper Methods When Appropriate

For errors, use `record_error()` to get automatic context:

```php
// Instead of:
$history->record('error', 'Failed', array(
    'timestamp' => microtime(true),
    'memory' => memory_get_usage(),
    'error' => $message,
));

// Use:
$history->record_error('Failed', array('error_code' => 'GENERATION_FAILED'));
```

## Testing

Comprehensive tests have been added in `tests/test-history-container-simplified.php` covering:

- Basic logging with context
- Input/output handling
- Large output base64 encoding
- Helper methods
- PHP error_log integration

## Files Changed

- `class-aips-history-container.php` - Updated record() signature and implementation
- `class-aips-generator.php` - Updated all record() calls
- `class-aips-author-topics-controller.php` - Updated record() calls
- `class-aips-author-post-generator.php` - Updated record() calls
- `class-aips-author-topics-scheduler.php` - Updated record() calls
- `class-aips-schedule-processor.php` - Updated record() calls
- `class-aips-post-review.php` - Updated record() calls
- `class-aips-post-review-notifications.php` - Updated record() calls
- `class-aips-logger.php` - Updated documentation
- `class-aips-generation-logger.php` - Deprecated
- `tests/test-generator-hooks.php` - Updated mocks
- `tests/test-history-container-simplified.php` - New comprehensive tests

## Breaking Changes

**This is a breaking change.** The `record()` method signature has been changed from 5 parameters to 3 parameters. All code that calls `record()` with 5 arguments must be updated to use the new signature with the `$context` array.

On PHP 8.2 and newer (which this plugin requires), passing extra arguments to `record()` will result in an `ArgumentCountError`.

All internal plugin code has been updated to use the new signature. External code or extensions that call `record()` directly must be updated.

## Performance Impact

Minimal. The refactoring actually reduces overhead by:
- Eliminating unnecessary parameter parsing
- Reducing function call overhead
- Consolidating logging paths

## Future Improvements

Potential future enhancements:
- Structured logging format (JSON lines)
- Log level filtering in History Container
- Automatic performance tracking
- Enhanced error context capture
