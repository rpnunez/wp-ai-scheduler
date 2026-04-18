This runbook covers both normal setup and the common Windows partial-install scenario where PHPUnit still runs in limited mode.

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
./install-wp-tests.sh wp_ns_tests root '' localhost latest
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

If you open a new shell later, re-export the environment variables first:

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
cd ai-post-scheduler
composer test
```

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
./install-wp-tests.sh wp_ns_tests root '' localhost latest
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
