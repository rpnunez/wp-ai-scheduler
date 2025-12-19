# Performance Improvements

This document outlines the performance optimizations made to the AI Post Scheduler plugin to improve efficiency and reduce resource consumption.

## Summary of Improvements

### 1. Database Query Optimization

#### Fixed N+1 Query Problem in Scheduler
**Location:** `includes/class-aips-scheduler.php::process_scheduled_posts()`

**Problem:** The scheduler was executing individual UPDATE/DELETE queries for each scheduled post within a loop, causing N+1 query issues.

**Solution:** 
- Batch collection of schedules to update/delete
- Single bulk DELETE query for completed one-time schedules
- Reduced database round trips from N queries to 1 for deletions

**Impact:** Significant performance improvement when processing multiple scheduled posts, especially during high-volume periods.

---

#### Optimized History Statistics Query
**Location:** `includes/class-aips-history.php::get_stats()`

**Problem:** Four separate queries were executed to calculate statistics (total, completed, failed, processing counts).

**Solution:** 
- Single query using conditional aggregation (CASE statements)
- All statistics calculated in one database round trip

**Impact:** 4x reduction in database queries for statistics retrieval.

**Before:**
```php
$stats = array(
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"),
    'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'"),
    'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'"),
    'processing' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'processing'"),
);
```

**After:**
```php
$results = $wpdb->get_row("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing
    FROM {$this->table_name}
", ARRAY_A);
```

---

### 2. Database Indexing

#### Added Strategic Indexes
**Location:** `migrations/migration-1.5-add-indexes.php`

**Indexes Added:**

1. **Schedule Table:**
   - `idx_is_active_next_run (is_active, next_run)` - Composite index for finding due schedules
   - `idx_template_id (template_id)` - Foreign key optimization

2. **History Table:**
   - `idx_status (status)` - Status filtering
   - `idx_template_id (template_id)` - Foreign key optimization
   - `idx_created_at (created_at)` - Date-based sorting

3. **Templates Table:**
   - `idx_is_active (is_active)` - Active template filtering

4. **Voices Table:**
   - `idx_is_active (is_active)` - Active voice filtering

**Impact:** Faster query execution for filtered and sorted data, especially important as the database grows.

---

### 3. Application-Level Caching

#### Template and Voice Caching
**Locations:** 
- `includes/class-aips-templates.php`
- `includes/class-aips-voices.php`

**Problem:** Repeated database queries for the same templates/voices during a single request.

**Solution:** 
- Added in-memory cache arrays to store query results
- Cache is checked before executing database queries
- Cache is cleared on save/delete operations

**Impact:** Eliminates redundant database queries when the same template or voice is accessed multiple times in a single request.

**Example:**
```php
private $cache = array();

public function get($id) {
    global $wpdb;
    
    $cache_key = 'template_' . $id;
    if (isset($this->cache[$cache_key])) {
        return $this->cache[$cache_key];
    }
    
    $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    
    if ($result) {
        $this->cache[$cache_key] = $result;
    }
    
    return $result;
}
```

---

### 4. Template Variable Processing Optimization

#### Optimized String Replacement
**Location:** `includes/class-aips-generator.php::process_template_variables()`

**Problem:** 
- Variables were regenerated on every call
- Used `str_replace()` with array keys/values (slower than alternatives)

**Solution:**
- Static cache for template variables to avoid regenerating unchanged values
- Switched from `str_replace()` to `strtr()` for better performance with multiple replacements
- Filter hook result is also cached

**Impact:** Faster template processing, especially when generating multiple posts with the same variables.

**Before:**
```php
$variables = array(...); // Generated every time
return str_replace(array_keys($variables), array_values($variables), $template);
```

**After:**
```php
static $cache = null;
if ($cache === null || ...) {
    $cache = array(...); // Generated once and cached
}
return strtr($template, $cache); // Faster replacement
```

---

### 5. JavaScript Performance

#### Debounced Search Inputs
**Location:** `assets/js/admin.js`

**Problem:** Search functions were triggered on every keyup event, causing excessive AJAX requests and DOM manipulation.

**Solution:**
- Added debounce utility function (300ms delay)
- Applied to voice search and template search inputs
- Prevents rapid-fire function calls during typing

**Impact:** Reduced AJAX requests and CPU usage during search operations.

**Implementation:**
```javascript
debounce: function(func, wait) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            func.apply(context, args);
        }, wait);
    };
}

// Usage
$(document).on('keyup', '#voice_search', this.debounce(this.searchVoices, 300));
```

---

## Performance Metrics

### Expected Improvements

1. **Scheduler Processing:** 50-70% reduction in query count when processing multiple schedules
2. **Statistics Retrieval:** 75% reduction in database queries (4 queries → 1 query)
3. **Template Access:** Up to 100% reduction in redundant queries via caching
4. **Search Operations:** 80-90% reduction in AJAX requests during active typing
5. **Indexed Queries:** 2-10x faster execution on filtered/sorted queries as data grows

### Scalability Improvements

- The plugin now scales better with:
  - Large numbers of scheduled posts (100+)
  - Extensive generation history (1000+ records)
  - Multiple templates and voices (50+)
  - High concurrent usage

---

## Migration Notes

The database index migration (`migration-1.5-add-indexes.php`) will run automatically on plugin upgrade. For existing installations:

1. Indexes are added if they don't exist
2. No data modification occurs
3. Safe to run multiple times (idempotent)

---

## Best Practices for Future Development

1. **Always consider indexes** when adding new query filters or sorts
2. **Use caching** for frequently accessed data that doesn't change often
3. **Batch database operations** when processing multiple items
4. **Debounce user input events** to prevent excessive function calls
5. **Use conditional aggregation** instead of multiple COUNT queries
6. **Profile queries** in development to identify bottlenecks

---

## Monitoring Recommendations

To verify performance improvements in production:

1. Enable query monitoring in WordPress (Query Monitor plugin)
2. Track average page load times for admin pages
3. Monitor database query counts per request
4. Check scheduler execution time via logs
5. Measure AJAX response times for search operations

---

## Additional Optimization Opportunities

Future improvements to consider:

1. **WordPress Object Cache:** Implement persistent caching using WordPress object cache API
2. **Transients:** Use WordPress transients for statistics that don't need real-time accuracy
3. **Database Table Partitioning:** For very large history tables (10,000+ records)
4. **Asynchronous Processing:** Move heavy AI operations to background jobs
5. **Query Result Pagination:** Limit result sets for large data views

---

*Last Updated: December 2024*
