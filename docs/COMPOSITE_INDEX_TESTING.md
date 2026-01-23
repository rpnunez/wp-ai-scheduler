# Composite Index Testing for PR #370

This document explains the testing infrastructure created to verify the composite index addition to the `aips_schedule` table in PR #370.

## What Changed

PR #370 adds a composite index `KEY is_active_next_run (is_active, next_run)` to the `aips_schedule` table defined in `AIPS_DB_Manager::get_schema()`.

### Why This Index?

The scheduler runs a polling query every minute:
```sql
SELECT ... WHERE s.is_active = 1 AND s.next_run <= %s ORDER BY s.next_run ASC
```

Previously, only `next_run` was indexed. This required the database to check `is_active` for every row in the time range. The composite index allows MySQL to:
1. Jump directly to active schedules (`is_active = 1`)
2. Scan only the due schedules in that subset (`next_run <= NOW()`)

This significantly improves performance as inactive schedules accumulate.

## Testing Infrastructure

### 1. PHPUnit Test (`tests/test-db-schema.php`)

Comprehensive test suite that verifies:
- ✅ Composite index exists with correct name
- ✅ Index contains the correct columns in the correct order
- ✅ All expected indexes are present on the table
- ✅ Table structure is correct
- ✅ Index can be used in queries (performance test)
- ✅ dbDelta properly maintains the index on upgrades

**Run the test:**
```bash
composer install
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_CORE_DIR=/tmp/wordpress-tests-lib/src \
vendor/bin/phpunit tests/test-db-schema.php --testdox
```

### 2. GitHub Actions Workflow (`.github/workflows/test-composite-index.yml`)

Automated CI workflow that:
- Spins up a MySQL 8.0 service
- Installs WordPress test environment
- Activates the plugin
- Runs the PHPUnit test suite
- Verifies the index exists in the database directly
- Displays all indexes on the table

**Triggers:**
- Pushes to `main` and `develop` branches (aligned with the main PHPUnit workflow)
- Pull requests targeting `main` or `develop`
- Manual dispatch (`workflow_dispatch`) when ad-hoc verification is needed

**View results:**
Go to Actions tab → Test Composite Index workflow

### 3. Standalone Verification Scripts

#### `verify-composite-index.php`
Direct PHP script that:
- Loads WordPress test environment
- Installs plugin tables
- Queries the database for the composite index
- Validates index structure
- Tests queries using the index

**Run:**
```bash
./verify-composite-index.php
```

#### `test-composite-index.sh`
Bash wrapper script that:
- Checks system requirements (MySQL, Composer)
- Installs dependencies
- Runs PHPUnit tests
- Optionally verifies directly in database

**Run:**
```bash
./test-composite-index.sh
```

## Verification Steps

### Method 1: Using GitHub Actions (Recommended)

1. Push changes to the branch or create a PR
2. GitHub Actions automatically runs the test workflow
3. View results in Actions tab
4. Check for green checkmarks ✅

### Method 2: Local Testing with Docker

If you have Docker and docker-compose:

```bash
# Start WordPress + MySQL environment
docker-compose up -d

# Wait for services to be ready
sleep 10

# Run tests inside container
docker-compose exec wordpress bash -c "
  cd /var/www/html/wp-content/plugins/wp-ai-scheduler && \
  composer install && \
  vendor/bin/phpunit tests/test-db-schema.php --testdox
"
```

### Method 3: Manual Database Verification

If you have a WordPress installation with the plugin:

1. Activate the plugin (or update to latest version)
2. Connect to MySQL:
   ```bash
   mysql -u root -p wordpress_database
   ```
3. Check for the index:
   ```sql
   SHOW INDEX FROM wp_aips_schedule WHERE Key_name = 'is_active_next_run';
   ```
4. Verify output shows 2 rows (one for each column):
   ```
   +----------------+------------+----------------------+...
   | Table          | Key_name   | Seq_in_index | Column_name | ...
   +----------------+------------+----------------------+...
   | wp_aips_schedule | is_active_next_run | 1 | is_active | ...
   | wp_aips_schedule | is_active_next_run | 2 | next_run  | ...
   +----------------+------------+----------------------+...
   ```

## Expected Results

✅ **Success Indicators:**
- PHPUnit tests pass (all 5 test methods)
- GitHub Actions workflow completes successfully
- Database shows composite index with 2 columns
- Index is named `is_active_next_run`
- First column is `is_active`
- Second column is `next_run`

❌ **Failure Indicators:**
- Test assertion failures
- Missing index in database
- Incorrect column order
- Missing columns

## File Changes

### Modified Files
- `includes/class-aips-db-manager.php` (Line 113)
  - Added: `KEY is_active_next_run (is_active, next_run)`

### New Files
- `tests/test-db-schema.php` - PHPUnit test suite
- `.github/workflows/test-composite-index.yml` - CI workflow
- `verify-composite-index.php` - Standalone verification script
- `test-composite-index.sh` - Bash test runner
- `COMPOSITE_INDEX_TESTING.md` - This documentation

## Performance Impact

The composite index improves query performance for the scheduler's polling query:

**Before:**
```
Using index: next_run
Rows examined: All rows with next_run <= NOW() (then filtered by is_active)
```

**After:**
```
Using index: is_active_next_run
Rows examined: Only active rows with next_run <= NOW()
```

As the number of inactive schedules grows (from paused campaigns, completed one-time tasks, etc.), the performance benefit becomes more significant.

## Maintenance

### Adding More Schema Tests

To add more schema validation tests, edit `tests/test-db-schema.php`:

```php
public function test_my_new_schema_feature() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aips_schedule';
    
    // Your test code here
    $this->assertTrue($condition, 'Descriptive failure message');
}
```

Then add the method name to the test runner in `run-schema-test.php`.

### Updating the Workflow

To modify CI behavior, edit `.github/workflows/test-composite-index.yml`.

## Troubleshooting

**Issue:** Tests fail with "Table doesn't exist"
- **Solution:** Ensure `AIPS_DB_Manager::install_tables()` is called in `setUp()`

**Issue:** Workflow fails at MySQL step
- **Solution:** Check MySQL service configuration and health checks

**Issue:** Composite index not found
- **Solution:** Verify the index definition in `class-aips-db-manager.php` line 113

**Issue:** Local tests can't find WordPress
- **Solution:** Set environment variables:
  ```bash
  export WP_TESTS_DIR=/path/to/wordpress-tests-lib
  export WP_CORE_DIR=/path/to/wordpress-tests-lib/src
  ```

## References

- PR #370: [⚡ Bolt: Add composite index to aips_schedule for faster polling](https://github.com/rpnunez/wp-ai-scheduler/pull/370)
- WordPress dbDelta documentation: https://developer.wordpress.org/reference/functions/dbdelta/
- MySQL Composite Index documentation: https://dev.mysql.com/doc/refman/8.0/en/multiple-column-indexes.html
