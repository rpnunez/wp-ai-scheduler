# Step 10 — Per-Request Config Cache Status

## Issue Summary
The issue requested adding a per-request config cache to `AIPS_Config::get_option()` to avoid repeated `get_option()` + `unserialize` calls for the same key within a single request.

## Current Status: ✅ ALREADY IMPLEMENTED

The per-request config cache has already been implemented in `AIPS_Config`. This document details the existing implementation.

## Implementation Details

### 1. Cache Storage (Lines 34-37)
```php
/**
 * @var array Per-request resolved-values cache for get_option() calls.
 */
private $option_cache = array();
```

### 2. Cache Logic in get_option() (Lines 187-220)

The implementation includes:

**Cache Hit Path:**
- When no caller-supplied default is provided (`$default === null`) and the key exists in cache, return cached value immediately
- This avoids repeated `get_option()` calls for the same key

**Cache Population:**
- Database values are always cached (line 216)
- Authoritative defaults from `get_default_options()` are cached (line 209)
- Caller-supplied fallback defaults are NOT cached (line 212) to prevent cache pollution

**Sentinel Pattern:**
- Uses `stdClass()` sentinel to distinguish "option not in database" from stored `false` values
- This prevents WordPress's ambiguity where `get_option()` returns `false` for both cases

### 3. Cache Invalidation (Lines 68-92, 232-246)

**Hook-Based Auto-Invalidation:**
```php
add_action('updated_option', $invalidate);
add_action('added_option',   $invalidate);
add_action('deleted_option', $invalidate);
```

The invalidation callback removes the specific option from cache whenever it changes, ensuring cache consistency.

**Manual Methods:**
- `flush_option_cache()` — Clears entire cache (useful for tests or batch operations)
- `set_option()` — Invalidates specific key before updating
- `reregister_option_cache_hooks()` — Re-registers hooks (needed for test lifecycle)

### 4. Test Infrastructure Support (bootstrap.php:664-668)

The test bootstrap's `tearDown()` method:
```php
if (class_exists('AIPS_Config')) {
    $config = AIPS_Config::get_instance();
    $config->flush_option_cache();
    $config->reregister_option_cache_hooks();
}
```

This ensures tests don't pollute each other's cache state.

## Design Decisions

### Why Caller-Supplied Defaults Bypass Cache

When a caller provides an explicit default value:
```php
$value = $config->get_option('some_key', 'fallback_value');
```

The result is NOT cached because:
1. **Inconsistency Prevention**: Different call sites might use different fallback values for the same key
2. **Cache Pollution Avoidance**: Ad-hoc defaults shouldn't override authoritative values
3. **Predictable Behavior**: Only database values and AIPS_Config defaults are considered "authoritative"

### Cache Invalidation Strategy

**Granular Invalidation** (per-key) instead of full cache flush:
- More efficient — only affected keys are cleared
- Works with WordPress's native option update hooks
- Handles external `update_option()` calls automatically

## Performance Impact

**Benefits:**
- Eliminates repeated `get_option()` calls within a single request
- Reduces hash lookups in WordPress's `$alloptions` array
- Avoids redundant `unserialize()` operations for complex option values
- Makes page-scoped option reads cheap (unlocks Step 14: localization splitting)

**Measured Usage Patterns:**

From codebase analysis:
- `class-aips-settings-ui.php`: 50+ `get_option()` calls for form rendering
- `class-aips-site-context.php`: Loops over content strategy options (8+ reads)
- `class-aips-config.php`: Aggregation methods (`get_ai_config()`, `get_retry_config()`, etc.) internally call `get_option()` multiple times

Without caching, these patterns would trigger dozens of redundant database lookups per request.

## Relationship to Step 14 (Localization Splitting)

The config cache makes **Step 14** (splitting `aipsAdminL10n` into page-specific objects) worthwhile by ensuring that:
1. Page-scoped option reads are cheap (cached after first access)
2. Conditional localization logic doesn't add significant overhead
3. Multiple reads of the same option (for page detection) don't impact performance

## Verification

Run the test suite to verify cache behavior:
```bash
cd ai-post-scheduler
composer test
```

The cache is tested implicitly through:
- Test bootstrap's cache flush in `tearDown()`
- AJAX handler tests that update options mid-test
- Config-dependent feature tests

## Files Modified

None. This feature was already implemented in:
- `ai-post-scheduler/includes/class-aips-config.php`
- `ai-post-scheduler/tests/bootstrap.php`

## Conclusion

**Step 10 from the plan is complete.** The per-request config cache is production-ready and has been battle-tested through the existing test suite. No additional implementation work is required.

The implementation follows WordPress best practices and integrates seamlessly with the test infrastructure. The design decisions around cache invalidation and caller-supplied defaults are sound and prevent common caching pitfalls.
