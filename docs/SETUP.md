# Setup — AI Post Scheduler

Local development environment reference for the AI Post Scheduler plugin.

---

## Prerequisites

- **Docker Desktop** (or Docker Engine + Docker Compose)
- **Git**
- **Bash** (Git Bash on Windows, Terminal on Mac/Linux, or WSL2)
- **VS Code** with the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) (for Xdebug)

---

## First-Time Setup

Clone the repo and run from the repo root:

```bash
./start-dev.sh
```

> **Windows users:** Run inside Git Bash, WSL2, or any bash-compatible shell. Native CMD/PowerShell are not supported.

The script verifies Docker is running, builds the image (retrying transient Docker Hub errors), pulls only images that are not already cached, then starts all services and waits for their health checks. First startup takes a few minutes — Docker downloads images, installs WordPress, configures the database, and activates the plugin.

Things to know:

- **Per-checkout identity.** The first run writes a unique instance id, container names and free host ports into `.env`, so several checkouts or git worktrees can run at once. Use `make urls` to see this instance's URLs and ports.
- **Google API key.** The stack always uses the WP AI Client with the Google connector. If `GOOGLE_API_KEY` is empty or still the placeholder, the script prompts for it (hidden input) and saves it to your gitignored `.env`. Without a terminal it fails fast; `AIPS_SKIP_API_KEY_CHECK=1` starts anyway.
- **Run log.** Every run's full output is saved to `.artifacts/start-dev-<instance>-<id>.log` (gitignored).
- **Existing data.** `./start-dev.sh --import-sql dump.sql` seeds the database; see [Starting with existing data](#starting-with-existing-data-sql-import).
- **Docker Hub outages.** Build and pulls are retried; cached images are reused. If the build still fails with `auth.docker.io` 5xx errors, retry later.

### `.gitignore`

After cloning, update `.gitignore` with development artifacts:

```
# Composer
ai-post-scheduler/vendor/
ai-post-scheduler/composer.lock

# PHPUnit
ai-post-scheduler/coverage/
ai-post-scheduler/.phpunit.result.cache

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Temporary files
*.log
*.tmp
/tmp/
```

Or run: `cp .gitignore.new .gitignore`

---

## Environment Variables

Copy `.env.example` to `.env` to customize settings:

```bash
cp .env.example .env
```

`start-dev.sh` creates `.env` for you on first run. Common overrides:

```env
# WordPress admin credentials
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=your-secure-password

# Image tags (defaults shown)
WP_IMAGE_TAG=7.1-php8.3-apache
# DB_IMAGE=mariadb:10.6
```

Ports are auto-assigned on first run (first free port at or above 8080 / 8082 / 3307 / 9003) and stored as `WP_PORT`, `PHPMYADMIN_PORT`, `MYSQL_PORT`, `XDEBUG_PORT`; edit them if you want specific ones.

The complete variable reference (every variable, its default and what it does) is in the [README](../README.md#environment-variables).

---

## Starting the Environment

```bash
./start-dev.sh       # First-time setup / after pulling changes (also: make start-dev)
make start           # Subsequent starts — starts existing containers
make stop            # Stop containers (keeps data)
make down            # Remove containers (keeps data volumes)
```

Once running (default ports; run `make urls` for yours):

| Service    | URL                           | Credentials           |
|------------|-------------------------------|-----------------------|
| WordPress  | http://localhost:8080         | admin / admin         |
| WP Admin   | http://localhost:8080/wp-admin | admin / admin        |
| phpMyAdmin | http://localhost:8082         | wordpress / wordpress |

Credentials are the `WP_ADMIN_*` and `MYSQL_*` values in `.env`.

---

## Daily Commands

```bash
make start-dev       # Provision + build + start (logged); ARGS="--import-sql x.sql"
make start           # Start existing containers
make stop            # Stop containers (keeps them and all data)
make down            # Remove containers (keeps data volumes)
make restart         # Restart all services
make urls            # URLs, ports and credentials for this instance
make logs            # Stream all logs
make logs-web        # Stream WordPress logs
make logs-db         # Stream database logs
make shell           # Open a shell in the WordPress container
make wp-shell        # Open an interactive WP-CLI session
make db-shell        # Open a MariaDB shell (credentials from .env)
make status          # Show container status
make info            # Show WordPress and plugin info
```

### Rebuilding

Plugin source is bind-mounted — changes to `ai-post-scheduler/` are reflected immediately with no rebuild. Only rebuild when `Dockerfile`, `docker-compose.yml`, or image dependencies change (the simplest way is `./start-dev.sh`, which also retries Docker Hub errors):

```bash
./start-dev.sh                        # Rebuild and restart (recommended)
docker compose up -d --build          # Rebuild and restart
docker compose build --no-cache       # Force clean rebuild
docker compose up -d
```

Xdebug and PHP ini changes do not need a rebuild: `make xdebug-on` / `make xdebug-off` recreate the web container, and `make reload-php` applies `dev-php.ini` edits.

---

## Xdebug / VS Code Debugging

Xdebug 3.3.1 ships in the image but is **disabled by default** (`XDEBUG_MODE=off`). Leaving it in the previous always-on configuration (`mode=develop,debug` + `start_with_request=yes`) forces PHP to attempt a debugger handshake on every request even when no IDE is attached, which materially slows the dev site. Turn it on only when you're actively debugging.

### Toggle Xdebug

Recommended flow — edit `.env` via the Make targets:

```bash
make xdebug-on     # sets XDEBUG_MODE=develop,debug + XDEBUG_START_WITH_REQUEST=trigger, rebuilds & restarts web
make xdebug-off    # sets XDEBUG_MODE=off, rebuilds & restarts web
make xdebug-status # shows the effective .env value and container ini
```

`trigger` mode means Xdebug only attaches when your IDE sets the `XDEBUG_TRIGGER` cookie / GET param / env var — zero overhead on other requests. That's the recommended dev default when Xdebug is on.

To hand-edit `.env` instead, set any of:

| Variable | Default | Meaning |
|---|---|---|
| `XDEBUG_MODE` | `off` | `off`, `debug`, `develop,debug`, `profile`, `trace`, … (Xdebug 3 modes) |
| `XDEBUG_START_WITH_REQUEST` | `trigger` | `trigger` (recommended), `yes` (attach every request — heavy), `no` (manual `xdebug_break()` only) |
| `XDEBUG_CLIENT_HOST` | `host.docker.internal` | Where Xdebug dials your IDE |
| `XDEBUG_CLIENT_PORT` | `9003` | IDE listen port |
| `XDEBUG_IDEKEY` | `PHPSTORM` | IDE key |
| `XDEBUG_LOG` / `XDEBUG_LOG_LEVEL` | `/tmp/xdebug.log` / `7` | Xdebug's own log for debugging Xdebug |

After hand-editing `.env`, apply with `docker compose up -d --force-recreate web` (or `make xdebug-on` / `make xdebug-off`). A plain `make reload-php` is **not enough** — the Xdebug ini is generated by the container entrypoint from environment variables, so the entrypoint must re-run.

### Debug flow (VS Code)

1. Start the Docker environment (`make up`).
2. Enable Xdebug: `make xdebug-on`.
3. Open the project in VS Code.
4. Press `F5` → select **"Listen for Xdebug (Docker)"**.
5. Set breakpoints in plugin code.
6. Trigger the request (browser reload with the Xdebug helper extension, or append `?XDEBUG_TRIGGER=1` to the URL).
7. When done: `make xdebug-off` to remove the overhead.

- **Port:** `XDEBUG_CLIENT_PORT` (default `9003`)
- **Path mappings:** pre-configured in `.vscode/launch.json`

### PHP-only settings

`dev-php.ini` still holds the general PHP settings (`memory_limit`, `upload_max_filesize`, `display_errors`, …). Edit it and apply with:

```bash
make reload-php
```

The `[xdebug]` block was removed from `dev-php.ini` — Xdebug config now comes from `.env` via the entrypoint, so `zz-xdebug-runtime.ini` is written to `/usr/local/etc/php/conf.d/` at container start.

---

## PHPUnit Testing

### Canonical workflow (Docker, recommended)

With the dev stack running (`./start-dev.sh`), run the suite inside the `web` container. No host PHP, Composer or svn needed:

```bash
make test                                  # whole suite (or: bash scripts/run-docker-test.sh)
make test ARGS="tests/Test_AIPS_DB_Migrations.php"   # one file
make test ARGS="--fresh"                   # recreate the wp_tests database first
make test-coverage                         # text coverage report (enables Xdebug coverage)
```

It uses a separate `wp_tests` database, never your development data.

### CI-style runner (host PHP + Composer required)

```bash
bash scripts/run-wp-tests-docker.sh        # or: make test-ci
bash scripts/run-wp-tests-docker.sh coverage
```

This script (used by the GitHub Actions workflows) starts the Docker database, recreates a disposable test database, installs WordPress core and `wordpress-tests-lib` on the host, exports `WP_TESTS_DIR`/`WP_CORE_DIR`, and runs the suite with the host's PHP and Composer.

### Direct execution

If the WordPress test library is already installed:

```bash
cd ai-post-scheduler
export WP_TESTS_DIR='C:/tmp/wordpress-tests-lib-docker'
export WP_CORE_DIR='C:/tmp/wordpress-docker'
composer test
```

Other composer targets:

```bash
composer install          # Install/update dependencies
composer test             # Full suite
composer test:verbose     # Verbose output
composer test:coverage    # Generate coverage report

vendor/bin/phpunit tests/test-template-processor.php   # Single file
```

---

## WordPress Management

```bash
# Open a shell then run WP-CLI commands
make shell
wp plugin list --allow-root

# Or run directly
docker compose exec web wp plugin list --allow-root
docker compose exec web wp cache flush --allow-root
docker compose exec web wp user list --allow-root

# Plugin via make
make plugin-activate
make plugin-deactivate
make plugin-list
```

---

## Database Operations

**phpMyAdmin:** http://localhost:8082 (wordpress / wordpress)

```bash
make db-shell             # MySQL shell (external: host=localhost port=3307)
make db-backup            # Saves to backup.sql
make db-restore           # Restores from backup.sql
```

### Starting with existing data (SQL import)

By default every environment starts as an empty WordPress install. To seed it from a dump:

```bash
./start-dev.sh --import-sql path/to/dump.sql        # also accepts .sql.gz
make start-dev ARGS="--import-sql path/to/dump.sql"
```

- The import only runs when the database has **no tables**. On a normal rerun (data present) it is skipped and the log says so.
- `--force-import` backs the current database up to `.artifacts/db-backup-<instance>-<id>.sql`, then replaces it.
- Set `AIPS_IMPORT_SQL=path/to/dump.sql` in `.env` for a standing default; the flag overrides it.
- The dump's table prefix is detected and applied (`WP_TABLE_PREFIX`), MySQL 8 collations are mapped for MariaDB, and URLs are rewritten to `http://localhost:<WP_PORT>` (serialization-safe).
- Admin credentials come from the imported database, not `.env`.

---

## MCP Bridge

To connect MCP-compatible tools (GitHub Copilot, automation scripts) to the plugin, see [docs/MCP_BRIDGE.md](MCP_BRIDGE.md).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Port in use | Change `WP_PORT` in `.env` or `docker-compose.yml` |
| Changes not in browser | `docker compose exec web wp cache flush --allow-root` |
| Xdebug not connecting | `make xdebug-status` — if it says disabled, run `make xdebug-on`; otherwise verify your IDE is listening on port 9003 and that the request carries the `XDEBUG_TRIGGER` cookie/param |
| Container keeps restarting | `docker compose logs web` |
| No space left on device | `docker system prune -a --volumes` |
| Database connection error | `docker compose down && docker compose up -d` |
| Plugin activation fails | `docker compose exec web wp plugin activate ai-post-scheduler --allow-root` |
| "healthcheck.sh: not found" | Run `./start-dev.sh` from repo root, not a subdirectory |

**Linux: "Cannot connect to Docker daemon"**

```bash
sudo usermod -aG docker $USER && newgrp docker
```

**Find what is using a port:**

```bash
lsof -i :8080          # Mac/Linux
netstat -ano | findstr :8080   # Windows PowerShell
```
