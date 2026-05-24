# Testing

This repository maintains a single PHPUnit test suite: the full WordPress test-library suite.

The supported local workflow is Docker-backed MySQL plus the WordPress PHPUnit test library installer. Do not rely on host-only or limited-mode test execution.

## Requirements

- Docker Desktop (or Docker Engine + Docker Compose)
- Bash
  - Git Bash on Windows
  - WSL2
  - macOS/Linux shell
- `svn`
- `curl` or `wget`

## One-Command Test Runs

From the repository root:

```bash
bash scripts/run-wp-tests-docker.sh
```

You do **not** need to run `./start-dev.sh` first.

You also do **not** need to manually start the containers first.

The test wrapper handles the database container itself by running:

```bash
docker compose up -d db
```

So the minimum requirement is just:
- Docker Desktop is running
- `docker compose` works
- `svn` is installed

That command:

1. Starts the Docker database container if needed
2. Recreates a disposable test database
3. Installs WordPress core and `wordpress-tests-lib`
4. Exports `WP_TESTS_DIR` and `WP_CORE_DIR`
5. Runs `composer test`

To generate coverage:

```bash
bash scripts/run-wp-tests-docker.sh coverage
```

## When To Use `./start-dev.sh`

Use `./start-dev.sh` only when you want the full local development stack for manual WordPress work, for example:

- browsing the site at `http://localhost:8080`
- using wp-admin
- using phpMyAdmin
- debugging plugin behavior in the running app

It is **not required** just to run the PHPUnit suite through `bash scripts/run-wp-tests-docker.sh`.

## Direct Commands

If the Docker-backed WordPress test environment is already installed in the current shell:

```bash
cd ai-post-scheduler
composer test
composer test:verbose
composer test:coverage
```

## Expected Environment Variables

The Docker wrapper sets these automatically:

```bash
WP_TESTS_DIR=C:/tmp/wordpress-tests-lib-docker
WP_CORE_DIR=C:/tmp/wordpress-docker
```

If you run PHPUnit directly without those paths being valid, the bootstrap fails fast.

## Common Failures

### `Required command not found: svn`

Install Subversion and make sure `svn` is on your `PATH`.

### `WordPress test library not found`

Run:

```bash
bash scripts/run-wp-tests-docker.sh
```

This reinstalls the WordPress test library and core paths.

### Docker DB health timeout

Make sure Docker Desktop is running, then retry:

```bash
docker compose up -d db
bash scripts/run-wp-tests-docker.sh
```

## Notes

- The test database is disposable and recreated for each Docker-backed run.
- The supported suite is always full WordPress mode.
- There is no maintained limited-mode fallback.
