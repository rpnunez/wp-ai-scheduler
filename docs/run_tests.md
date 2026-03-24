I’ll inspect the install script to see how it chooses WP_TESTS_DIR and WP_CORE_DIR, then I’ll give you an ordered set of commands tailored to your paths and DB.

Read [](file:///c%3A/Projects/NunezScheduler/wp-ai-scheduler/scripts/install-wp-tests.sh)

You should set those env vars **before** running install-wp-tests.sh, so the script installs the test library into your chosen directory and wires `wp-tests-config.php` to your chosen core and DB.

Below are concrete commands assuming **Git Bash** (or similar) and your given paths/DB.

---

**1. Open a bash shell at the repo root**

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
```

**2. Export the paths for WordPress core and tests**

```bash
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
```

- `WP_CORE_DIR` points at your existing WordPress install.
- `WP_TESTS_DIR` is where the WP test library will be installed.

These env vars will be used both by install-wp-tests.sh **and** later by PHPUnit via `tests/bootstrap.php`.

**3. Run the installer script with your XAMPP DB credentials**

From the same shell (env still set):

```bash
cd scripts
./install-wp-tests.sh wp_ns_tests root '' localhost latest
```

- DB name: `wp_ns_tests`
- DB user: `root`
- DB pass: empty string (`''`)
- DB host: `localhost`
- WP version: `latest`

Prereqs:

- `mysqladmin` must be on your `PATH` (from XAMPP’s `mysql/bin`).  
- This will:
  - Create the `wp_ns_tests` database (if it doesn’t exist).
  - Install the WP test suite into `C:/Projects/NunezScheduler/wordpress-tests-lib`.
  - Generate `wp-tests-config.php` pointing to `wp_ns_tests` and to wordpress.

**4. Run the plugin tests in full mode**

Stay in the same shell (so env vars are still set), then:

```bash
cd ../ai-post-scheduler
composer install
composer test
```

If you open a *new* shell later, re-export before `composer test`:

```bash
cd /c/Projects/NunezScheduler/wp-ai-scheduler
export WP_TESTS_DIR='C:/Projects/NunezScheduler/wordpress-tests-lib'
export WP_CORE_DIR='C:/Projects/NunezScheduler/wordpress-6.9/wordpress'
cd ai-post-scheduler
composer test
```

**5. How to confirm you’re in full mode**

- You should **not** see:

  > Warning: WordPress test library not found at ...

- Tests will now run against:
  - Full WP core at wordpress
  - Test DB `wp_ns_tests` (not your live site DB).

If you hit any path/`mysqladmin` issues, tell me which shell you’re using (Git Bash vs WSL vs PowerShell) and I can tweak the commands accordingly.