# Activity to History Service Migration TODO

## Files Still Using AIPS_Activity_Repository

The following files still instantiate and use `AIPS_Activity_Repository` and need to be migrated to use `AIPS_History_Service`:

### 1. class-aips-post-review.php
- **Lines**: 91, 191, 357, 397, 504
- **Pattern**: Creates local `$activity_repository` instance and calls `create()` method
- **Migration**: Replace with `$history_service = new AIPS_History_Service()` and convert `create()` calls to `log_activity()`

**Example conversion:**
```php
// OLD:
$activity_repository = new AIPS_Activity_Repository();
$activity_repository->create(array(
    'event_type' => 'post_published',
    'event_status' => 'failed',
    'message' => __('Post publish failed: Permission denied', 'ai-post-scheduler'),
));

// NEW:
$history_service = new AIPS_History_Service();
$history_service->log_activity(
    'post_published',
    'failed',
    __('Post publish failed: Permission denied', 'ai-post-scheduler')
);
```

### 2. class-aips-post-review-notifications.php
- **Lines**: 84
- **Pattern**: Same as above
- **Migration**: Same as above

### 3. class-aips-author-topics-controller.php
- Need to check for Activity usage

### 4. class-aips-author-topics-scheduler.php  
- Need to check for Activity usage

## Migration Method Signature

`AIPS_History_Service::log_activity()` signature:
```php
public function log_activity($event_type, $event_status, $message = '', $metadata = array())
```

`AIPS_Activity_Repository::create()` signature (OLD):
```php
public function create(array(
    'event_type' => string,
    'event_status' => string,
    'post_id' => int|null,
    'schedule_id' => int|null,
    'template_id' => int|null,
    'message' => string,
    'metadata' => array
))
```

## Conversion Steps

1. Replace `new AIPS_Activity_Repository()` with `new AIPS_History_Service()`
2. Convert `->create(array(...))` to `->log_activity(...)` using positional parameters
3. Move `post_id`, `schedule_id`, `template_id` from top-level into the `metadata` array
4. Test each converted method

## Files Already Migrated

- ✅ class-aips-author-post-generator.php
- ✅ class-aips-scheduler.php
- ✅ class-aips-generator.php (uses History Service throughout)
- ✅ class-aips-activity-repository.php (DELETED)
- ✅ class-aips-activity-controller.php (DELETED)

## Database Changes

- ✅ Removed `aips_activity` table from schema
- ✅ All activity events now logged to `aips_history_log` with `history_type_id = AIPS_History_Type::ACTIVITY`
