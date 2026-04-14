## Quick Links

- Fast commands only: [docs/tests/run_tests_pocketboot.md](docs/tests/run_tests_pocketboot.md)
- Full troubleshooting runbook: [docs/tests/runbook.md](docs/tests/runbook.md)

This runbook covers both normal setup and the common Windows partial-install scenario where PHPUnit still runs in limited mode.

## Always reset test library first (required)

Before every test run, always delete and recreate the WordPress test library directory:

```bash
rm -rf /c/Projects/NunezScheduler/wordpress-tests-lib
```

Why:
- This project has repeatedly hit partial-install states in `wordpress-tests-lib`.
- The installer skips re-checkout if the directory already exists.
- Starting clean prevents stale/missing `includes/` and `data/` from causing misleading failures.

If you want one-command reset + test execution, use:

```bash
./scripts/recreate_test_env.sh
```

## Why limited mode happens

The plugin test bootstrap switches to full WordPress mode only if this file exists:

```bash
$WP_TESTS_DIR/includes/functions.php
```

If that file is missing, PHPUnit prints:

```text
Warning: WordPress test library not found at ...
Tests will run in limited mode without WordPress environment.
```

Important:
- `wp-tests-config.php` by itself is not enough.
- A valid WordPress test library install also needs at least:
  - `$WP_TESTS_DIR/includes/functions.php`
  - `$WP_TESTS_DIR/includes/bootstrap.php`
  - `$WP_TESTS_DIR/data/`
  - `$WP_TESTS_DIR/wp-tests-config.php`

If your `wordpress-tests-lib` folder contains only `wp-tests-config.php`, the install is incomplete and PHPUnit will stay in limited mode.

## Why this happens on reruns

The installer script only checks whether `$WP_TESTS_DIR` already exists.
If the directory exists, it skips downloading the `includes/` and `data/` folders.

That means this failure pattern is common:
- the directory gets created
- the SVN checkout fails or is interrupted
- rerunning the script does not repair the missing files because the directory already exists

## Normal setup steps

These commands assume Git Bash.

### 1. Open a bash shell at the repo root

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
```

### 2. Export WordPress core and test-library paths

```bash
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
```

These environment variables are used by both:
- `scripts/install-wp-tests.sh`
- `ai-post-scheduler/tests/bootstrap.php`

### 3. Verify required tools are on PATH

```bash
command -v svn
command -v mysqladmin
```

Notes:
- `svn` is required because the installer fetches the WordPress PHPUnit library via Subversion.
- `mysqladmin` is required if you want the script to create the database automatically.

### 4. Run the installer script

From the same shell:

```bash
cd scripts
./install-wp-tests.sh wp_ns_tests_schedule root '' localhost latest
```

This should:
- install the WordPress PHPUnit library into `C:/Projects/NunezScheduler/wordpress-tests-lib`
- create `wp-tests-config.php` in that same folder
- point the config at `C:/Projects/NunezScheduler/wordpress-6.9/wordpress`
- create the test DB if needed

### 5. Verify the install before running PHPUnit

Run these checks before `composer test`:

```bash
ls "$WP_TESTS_DIR"
ls "$WP_TESTS_DIR/includes"
test -f "$WP_TESTS_DIR/includes/functions.php" && echo "WP tests lib OK"
test -f "$WP_TESTS_DIR/includes/bootstrap.php" && echo "WP bootstrap OK"
```

You should see:
- `includes/`
- `data/`
- `wp-tests-config.php`

If `includes/functions.php` is missing, stop there and follow the recovery steps below.

### 6. Run plugin tests in full mode

```bash
cd ../ai-post-scheduler
composer install
composer test
```

If you want to save the full PHPUnit output to a file, do **not** use stdout-only redirection like this:

```bash
composer test > test-results.txt
```

That only captures stdout. PHPUnit and PHP warnings/errors often go to stderr, so the file can look very small or incomplete even when tests are running and failing.

Use one of these instead:

```bash
composer test > test-results.txt 2>&1
```

or:

```bash
composer test 2>&1 | tee test-results.txt
```

For quicker diagnosis of the first failure:

```bash
composer test -- --stop-on-error 2>&1 | tee test-results.txt
```

If you open a new shell later, re-export the environment variables first:

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
cd ai-post-scheduler
composer test
```

### 7. Interpreting partial output files

If a saved output file looks like this:

```text
......EEE{"success":true,...}
```

that usually means:
- tests are definitely running
- some early tests already passed (`.`)
- some early tests errored (`E`)
- an AJAX-style test printed JSON to stdout
- the detailed PHPUnit error report was likely written to stderr, not captured in the file

## Recovery steps for partial install

Use this section if:
- PHPUnit says the WordPress test library is missing
- `wordpress-tests-lib` contains only `wp-tests-config.php`
- `includes/functions.php` does not exist

### 1. Remove the incomplete test-library directory

```bash
rm -rf /c/Projects/NunezScheduler/wordpress-tests-lib
```

Do not pre-create this directory manually before rerunning the installer.

### 2. Re-export the environment variables

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
```

### 3. Rerun the installer

```bash
cd scripts
./install-wp-tests.sh wp_ns_tests_schedule root '' localhost latest
```

### 4. Verify the install again

```bash
ls "$WP_TESTS_DIR"
ls "$WP_TESTS_DIR/includes"
test -f "$WP_TESTS_DIR/includes/functions.php" && echo "WP tests lib OK"
```

Only continue when `includes/functions.php` exists.

## Full-mode confirmation checklist

You are in full WordPress test mode when all of these are true:
- `composer test` does not print `WordPress test library not found`
- `$WP_TESTS_DIR/includes/functions.php` exists
- `$WP_TESTS_DIR/includes/bootstrap.php` exists
- `$WP_TESTS_DIR/data/` exists
- `WP_CORE_DIR` points at a real WordPress core directory

## Notes for the current scenario

If your folder looks like this:

```text
wordpress-tests-lib/
  wp-tests-config.php
```

then the install is incomplete. That exact state will always trigger limited mode.

The presence of `wp-tests-config.php` at the repo root or in another folder does not fix this. The bootstrap check is specifically looking for `WP_TESTS_DIR/includes/functions.php`.

## One-command reset and run

This script resets the test DB, recreates `wordpress-tests-lib`, verifies required files, and runs tests:

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
./scripts/recreate_test_env.sh
```

The script asks for explicit confirmation (`YES`) before dropping the test database.
