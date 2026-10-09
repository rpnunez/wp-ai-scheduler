# Testing

This repository maintains a single PHPUnit suite: the full WordPress test-library suite (`ai-post-scheduler/tests/`, about 209 test files).

The recommended way to run it is **inside the running dev stack**: PHPUnit executes in the `web` container against a separate `wp_tests` database on the `db` container. No host PHP, Composer, or `svn` is needed, and your development data is never touched.

## Requirements

- Docker Desktop (or Docker Engine + Docker Compose v2), running
- Bash (Git Bash on Windows, WSL2, or a macOS/Linux shell)
- The dev stack, started once with `./start-dev.sh` (or `make start-dev`). See [README.md](README.md#development).

`./start-dev.sh` **is** the supported way to get a working test environment: it builds the image (PHP, Xdebug, WP-CLI, Composer) and starts the database and web containers. The container entrypoint only installs the plugin's *production* Composer dependencies, so the first time (or whenever `ai-post-scheduler/vendor/bin/phpunit` is missing) run `make composer-install` to install the dev dependencies (PHPUnit and the WordPress test library) into the bind-mounted `vendor/` directory.

## Running tests

From the repository root, with the stack up:

```bash
make test                                            # whole suite
make test ARGS="tests/Test_AIPS_DB_Migrations.php"   # one file
make test ARGS="--filter test_some_method"           # any PHPUnit arguments
make test ARGS="--fresh"                             # recreate the wp_tests database first
make test-verbose                                    # PHPUnit --verbose
make test-coverage                                   # coverage (see below)
```

`make` is a thin wrapper around `scripts/run-docker-test.sh`, which you can also call directly with the same arguments:

```bash
bash scripts/run-docker-test.sh tests/Test_AIPS_DB_Migrations.php
```

What the script does:

1. Makes sure the `db` and `web` services are up and healthy (`docker compose up -d --wait`).
2. Creates the `wp_tests` database (root credentials come from the container's `MYSQL_ROOT_PASSWORD`). `--fresh` drops and recreates it first.
3. Writes `/tmp/wp-tests-config.php` in the container and exports `WP_TESTS_DIR`, `WP_CORE_DIR`, `WP_PHPUNIT__TESTS_CONFIG`.
4. Runs `php vendor/bin/phpunit` in the plugin directory. It adds `--no-coverage` by default so PHPUnit does not try to start a coverage driver while Xdebug is off; any explicit `--coverage*`/`--no-coverage` argument is honored instead.

You do not need to run `./start-dev.sh` every time: once the stack exists, `make start` brings it back, and the test script starts `db`/`web` itself if they are stopped.

## Code coverage

```bash
make test-coverage
```

Equivalent:

```bash
bash scripts/run-docker-test.sh --coverage-filter includes --coverage-html coverage --coverage-text
```

- `--coverage-filter includes` tells PHPUnit which source to measure (`phpunit.xml` intentionally has no `<coverage>` block, because that would force a coverage driver on every run).
- `--coverage-html coverage` writes the HTML report to `ai-post-scheduler/coverage/` on your machine (the plugin directory is bind-mounted). Open `ai-post-scheduler/coverage/index.html` (or `dashboard.html`). The folder is gitignored.
- `--coverage-text` prints a per-class summary in the terminal.
- Passing a `--coverage*` argument also sets `XDEBUG_MODE=coverage` for that run (an environment variable overrides the runtime `xdebug.mode = off`).
- Other formats work the same way, e.g. `--coverage-clover .artifacts/clover.xml`.

**Let the report finish.** PHPUnit prints the test summary first, then prints `Generating code coverage report in HTML format ...` and renders every page. The stylesheets, scripts and icons (`_css/`, `_js/`, `_icons/`) are copied as the **last** step. If you interrupt (Ctrl+C) while it is generating, you get HTML pages with no styling. Coverage runs are noticeably slower than normal runs; a long quiet period is not a hang.

## Baseline run

A full coverage run of the whole suite on the dev stack (2026-10-09) finished with:

```
ERRORS!
Tests: 2127, Assertions: 5968, Errors: 22, Failures: 114, Warnings: 1, Skipped: 68.
```

These failures have **not been triaged**; treat them as the starting baseline, not a statement that those tests should fail. Compare your own run against it, and when you fix tests, update this number. (Run time was not recorded.)

## CI-style host runner

`scripts/run-wp-tests-docker.sh` (`make test-ci`) is what the GitHub Actions workflows use. It runs **on the host**: it starts the DB container, installs WordPress core and the test library to a host directory (`C:/tmp/wordpress-docker` on Git Bash, `/tmp/wordpress-docker` elsewhere), then runs `composer test` with the host's PHP and Composer. It needs host PHP (with `mysqli`), Composer, and `curl` or `wget`; `svn` is optional (the installer falls back to the packaged `wp-phpunit`).

```bash
bash scripts/run-wp-tests-docker.sh              # full suite
bash scripts/run-wp-tests-docker.sh ai-api       # AI/provider-related tests
bash scripts/run-wp-tests-docker.sh coverage     # coverage
```

Use it for CI parity; use `make test` for everyday local work.

## Direct Composer commands (no Docker runner)

If a WordPress test environment is already installed in your shell, from `ai-post-scheduler/`:

```bash
composer test            # runs test:setup (bin/prepare-wp-tests.sh) then PHPUnit
composer test:verbose
composer test:coverage
```

`composer test:setup` installs Composer dependencies if `vendor/bin/phpunit` is missing and installs WordPress core plus the test library/config via `scripts/install-wp-tests.sh` using `WP_TESTS_DIR` / `WP_CORE_DIR` (defaults `/tmp/wordpress-tests-lib` and `/tmp/wordpress`). For agent/CI environments without DB create permissions, set `AIPS_WP_TEST_SKIP_DB_CREATE=true`. Set `AIPS_WP_TEST_AUTO_INSTALL_SVN=false` to skip best-effort `svn` installation and go straight to the packaged fallback.

## Environment variables

| Variable | Default | Used by | Purpose |
| :--- | :--- | :--- | :--- |
| `AIPS_WP_TEST_DB_NAME` | `wp_tests` (`wp_ns_tests_docker` for the host runner) | both runners | Name of the disposable test database. |
| `MYSQL_ROOT_PASSWORD` | `root` | both runners | Root password used to create the test database. |
| `AIPS_WP_TEST_SKIP_DB_CREATE` | `true` in the container runner | PHPUnit bootstrap | Skip creating the database (the runner already did). |
| `AIPS_WP_TEST_DB_USER`, `AIPS_WP_TEST_DB_PASS`, `AIPS_WP_TEST_DB_HOST` | `root`, root password, `127.0.0.1:<MYSQL_PORT>` | host runner | Test database connection. |
| `AIPS_WP_TEST_WP_VERSION` | `latest` | host runner | WordPress version for the test library. |
| `AIPS_WP_TEST_AUTO_INSTALL_SVN` | unset (`true`) | `composer test:setup` | Set `false` to skip installing `svn`. |
| `WP_TESTS_DIR`, `WP_CORE_DIR` | set by the runners | PHPUnit bootstrap | Test library and WordPress core paths. If invalid, the bootstrap fails fast. |

## Common failures

### `vendor/bin/phpunit not found`

Dev dependencies are missing from the bind-mounted `ai-post-scheduler/vendor/`:

```bash
make composer-install
```

### `ERROR 2026 (HY000): TLS/SSL error: SSL is required, but the server does not support it`

The MariaDB 11 client defaults to requiring TLS, but the dev database does not offer it. The runners already pass `--skip-ssl`; if you run `mariadb`/`mysql` by hand against the database container, add `--skip-ssl`.

### `Error establishing a database connection` / unknown database `wp_tests`

Re-create the test database: `make test ARGS="--fresh"`. Make sure the stack is healthy (`make status`).

### Stack not running / unhealthy

```bash
make start         # or ./start-dev.sh if you have never provisioned this checkout
make status
make logs-db
```

### `WordPress test library not found` (host runner / composer)

Run `bash scripts/run-wp-tests-docker.sh` (reinstalls the test library and core paths) or `composer test:setup` from `ai-post-scheduler/`.

### Docker Hub errors while starting the stack

`start-dev.sh` retries transient `auth.docker.io` 5xx errors and reuses cached images; see [README.md](README.md#troubleshooting).

## Notes

- The test database is disposable. Use `--fresh` to reset it.
- The supported suite is always full WordPress mode; there is no limited-mode fallback.
- `./start-dev.sh` writes each run's output to `.artifacts/start-dev-*.log`; keep that in mind when reporting environment problems.
