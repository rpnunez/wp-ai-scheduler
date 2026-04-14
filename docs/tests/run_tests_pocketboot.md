## Pocketboot: Run Tests (Git Bash)

## Always delete test library first

Always run this before installing/running tests:

```bash
rm -rf /c/Projects/NunezScheduler/wordpress-tests-lib
```

## Full reset + run (recommended)

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
./scripts/recreate_test_env.sh
```

## Manual flow

### 1) Go to repo root

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
```

### 2) Export env vars

```bash
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
```

### 3) Install WP test library

```bash
cd scripts
./install-wp-tests.sh wp_ns_tests_schedule root '' localhost latest
```

### 4) Verify required files

```bash
ls "$WP_TESTS_DIR"
ls "$WP_TESTS_DIR/includes"
test -f "$WP_TESTS_DIR/includes/functions.php" && echo "WP tests lib OK"
test -f "$WP_TESTS_DIR/includes/bootstrap.php" && echo "WP bootstrap OK"
```

### 5) Run tests with full logging

```bash
cd ../ai-post-scheduler
composer install
composer test -- --stop-on-error 2>&1 | tee test-results.txt
```
