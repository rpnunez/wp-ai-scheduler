# Step 10 — Per-Request Config Cache Status

## Issue Summary
The issue requested adding a per-request config cache to `AIPS_Config::get_option()` to avoid repeated `get_option()` + `unserialize` calls for the same key within a single request.

## Current Status: ✅ COMPLETE — Using Native Cache Framework

The per-request config cache has been implemented in `AIPS_Config` using the native `AIPS_Cache` framework introduced in PR #1259.

## Implementation Details

### 1. Cache Storage (Line 35-37)
```php
/**
 * @var AIPS_Cache Per-request cache for get_option() calls.
 */
private $cache = null;
```

The cache is now a named instance from `AIPS_Cache_Factory` rather than a simple PHP array.

### 2. Cache Initialization (Line 54-58)
```php
private function __construct() {
    $this->cache = AIPS_Cache_Factory::named('aips_config', 'array');
    $this->load_feature_flags();
    $this->register_option_cache_hooks();
}
```

Uses `AIPS_Cache_Factory::named('aips_config', 'array')` to create a named cache instance with the Array driver for request-scoped caching.

### 3. Cache Logic in get_option() (Lines 200-237)

**Cache Hit Path:**
```php
if ($default === null && $this->cache !== null && $this->cache->has($option_name)) {
    return $this->cache->get($option_name);
}
```

**Cache Population:**
```php
// For database values
if ($this->cache !== null) {
    $this->cache->set($option_name, $value);
}

// For authoritative defaults
if ($this->cache !== null) {
    $this->cache->set($option_name, $value);
}
```

**Caller-supplied defaults still bypass cache** (unchanged behavior) to prevent cache pollution.

**Sentinel Pattern** (unchanged) — Uses `stdClass()` sentinel to distinguish "option not in database" from stored `false` values.

### 4. Cache Invalidation (Lines 82-95, 249-254, 264-268)

**Hook-Based Auto-Invalidation:**
```php
$invalidate = function($option) {
    if ($this->cache !== null) {
        $this->cache->delete($option);
    }
};
add_action('updated_option', $invalidate);
add_action('added_option',   $invalidate);
add_action('deleted_option', $invalidate);
```

**Manual Methods:**
```php
// set_option() — invalidates specific key
public function set_option($option_name, $value, $autoload = null) {
    if ($this->cache !== null) {
        $this->cache->delete($option_name);
    }
    return update_option($option_name, $value, $autoload);
}

// flush_option_cache() — clears entire cache
public function flush_option_cache() {
    if ($this->cache !== null) {
        $this->cache->flush();
    }
}
```

### 5. Test Infrastructure Support

The test bootstrap's cleanup/reset logic continues to work unchanged:
```php
if (class_exists('AIPS_Config')) {
    $config = AIPS_Config::get_instance();
    $config->flush_option_cache();
    $config->reregister_option_cache_hooks();
}
```

The `flush()` method on `AIPS_Cache_Array_Driver` clears the internal array storage, and the hooks are re-registered for the next test.

## Benefits of Using AIPS_Cache Framework

### 1. **Consistency**
- Uses the same caching API as other plugin components
- Follows established patterns (`AIPS_Cache_Factory::named()`)
- Leverages the same driver architecture

### 2. **Future Flexibility**
- Can easily switch to a different driver if needed (though Array is ideal for this use case)
- Benefits from any improvements to the Cache framework
- Consistent debugging and monitoring

### 3. **Clean Architecture**
- Delegates cache implementation to specialized classes
- Clear separation of concerns
- No duplicate cache logic

### 4. **Testability**
- Cache behavior is tested through the Cache framework's own tests
- `AIPS_Cache_Factory::reset()` available for test isolation
- Named instances prevent cross-component interference

## Design Decisions

### Why Array Driver?

The Array driver is perfect for `AIPS_Config` because:
1. **Request-scoped** — Config values don't need to persist across requests
2. **No dependencies** — Always available, no Redis/DB required
3. **Fast** — In-memory lookups are instant
4. **Simple** — No serialization/deserialization overhead

### Why Named Instance?

Using `AIPS_Cache_Factory::named('aips_config', 'array')`:
1. **Isolation** — Config cache is separate from other caches
2. **Explicit** — Clear intent in code
3. **Testable** — Can be reset independently
4. **Discoverable** — Named instances can be inspected/debugged

### Why Caller-Supplied Defaults Still Bypass Cache

When a caller provides an explicit default value:
```php
$value = $config->get_option('some_key', 'fallback_value');
```

The result is NOT cached because:
1. **Inconsistency Prevention** — Different call sites might use different fallback values
2. **Cache Pollution Avoidance** — Ad-hoc defaults shouldn't override authoritative values
3. **Predictable Behavior** — Only database values and AIPS_Config defaults are cached

This design decision remains unchanged from the original implementation.

## Performance Impact

**Benefits:**
- Eliminates repeated `get_option()` calls within a single request
- Reduces overhead of sentinel/default resolution logic
- Avoids repeated cache key construction in `AIPS_Cache_Array_Driver`
- Makes page-scoped option reads cheap (unlocks Step 14: localization splitting)

**Measured Usage Patterns:**

From codebase analysis:
- `class-aips-settings-ui.php`: 50+ `get_option()` calls for form rendering
- `class-aips-site-context.php`: Loops over content strategy options (8+ reads)
- `class-aips-config.php`: Aggregation methods internally call `get_option()` multiple times

Without caching, these patterns would trigger dozens of redundant function calls per request.

## Relationship to Step 14 (Localization Splitting)

The config cache makes **Step 14** (splitting `aipsAdminL10n` into page-specific objects) worthwhile by ensuring that:
1. Page-scoped option reads are cheap (cached after first access)
2. Conditional localization logic doesn't add significant overhead
3. Multiple reads of the same option (for page detection) don't impact performance

## Verification

Manual verification test:
```bash
cd ai-post-scheduler
php -r "
require_once 'tests/bootstrap.php';
\$config = AIPS_Config::get_instance();
echo 'Test 1: ' . \$config->get_option('aips_default_post_status') . PHP_EOL;
echo 'Test 2 (cached): ' . \$config->get_option('aips_default_post_status') . PHP_EOL;
\$config->flush_option_cache();
echo 'Test 3 (after flush): ' . \$config->get_option('aips_default_post_status') . PHP_EOL;
"
```

Run the full test suite:
```bash
composer test
```

## Files Modified

- `ai-post-scheduler/includes/class-aips-config.php` — Updated to use `AIPS_Cache` framework
- `STEP_10_STATUS.md` — Updated documentation

## Migration from Array to AIPS_Cache

The migration was straightforward:

| Old Implementation | New Implementation |
|--------------------|-------------------|
| `private $option_cache = array();` | `private $cache = null;` |
| `$this->option_cache = AIPS_Cache_Factory::named('aips_config', 'array');` | Constructor initialization |
| `array_key_exists($key, $this->option_cache)` | `$this->cache->has($key)` |
| `return $this->option_cache[$key];` | `return $this->cache->get($key);` |
| `$this->option_cache[$key] = $value;` | `$this->cache->set($key, $value);` |
| `unset($this->option_cache[$key]);` | `$this->cache->delete($key);` |
| `$this->option_cache = array();` | `$this->cache->flush();` |

All public APIs remain unchanged — `get_option()`, `set_option()`, `flush_option_cache()`, and `reregister_option_cache_hooks()` work exactly as before.

## Conclusion

**Step 10 from the plan is complete and enhanced.** The per-request config cache now uses the native Cache framework, providing better consistency, maintainability, and alignment with the plugin's architecture while maintaining all existing functionality and performance characteristics.

The implementation is production-ready and has been verified through manual testing. The existing test suite validates the behavior through the test bootstrap's cache lifecycle management.
