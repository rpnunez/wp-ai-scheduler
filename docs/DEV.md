# Developer Guide — AI Post Scheduler

Complete reference for setting up and working with the local development environment.

For a quick-reference card, see [DEV_HANDBOOK.md](DEV_HANDBOOK.md).

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [First-Time Setup](#first-time-setup)
- [Environment Variables](#environment-variables)
- [Starting the Environment](#starting-the-environment)
- [Daily Workflow](#daily-workflow)
- [Xdebug / VS Code Debugging](#xdebug--vs-code-debugging)
- [PHPUnit Testing](#phpunit-testing)
- [Client-Side Assets Build](#client-side-assets-build)
- [WordPress Management](#wordpress-management)
- [Database Operations](#database-operations)
- [PHP Settings](#php-settings)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

- **Docker Desktop** (or Docker Engine + Docker Compose) — [Windows](https://docs.docker.com/desktop/install/windows-install/) | [Mac](https://docs.docker.com/desktop/install/mac-install/) | [Linux](https://docs.docker.com/desktop/install/linux-install/)
- **Git**
- **Bash** (Git Bash on Windows, Terminal on Mac/Linux, or WSL2)
- **Node.js (v18+) & npm** (for compiling client assets)
- **VS Code** with the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) (for Xdebug)

---

## First-Time Setup

Clone the repo and start the environment from the repo root:

```bash
./start-dev.sh
```

> **Windows users:** Run this script inside Git Bash, WSL2, or any bash-compatible shell. Native CMD/PowerShell are not supported.

The script will:
1. Verify Docker is running
2. Check all required files are present
3. Stop any existing containers
4. Build Docker images
5. Start all services

The first startup takes a few minutes — Docker downloads images, installs WordPress, configures the database, and activates the plugin.

---

## Environment Variables

Copy `.env.example` to `.env` to customize settings:

```bash
cp .env.example .env
```

Common overrides:

```env
# WordPress admin credentials
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=your-secure-password

# Port mappings (change if 8080/8082 are in use)
WP_PORT=8080
PHPMYADMIN_PORT=8082
MYSQL_PORT=3307
```

---

## Starting the Environment

```bash
./start-dev.sh       # First-time setup — builds images and starts all services
make up              # Subsequent starts — starts existing containers
```

Once running:

| Service     | URL                                | Credentials              |
|-------------|-------------------------------------|--------------------------|
| WordPress   | http://localhost:8080               | admin / admin            |
| WP Admin    | http://localhost:8080/wp-admin      | admin / admin            |
| phpMyAdmin  | http://localhost:8082               | wordpress / wordpress    |

---

## Daily Workflow

```bash
make up              # Start all services
make down            # Stop all services
make restart         # Restart all services
make logs            # Stream all logs
make logs-web        # Stream WordPress logs
make logs-db         # Stream database logs
make shell           # Open a shell in the WordPress container
make wp-shell        # Open an interactive WP-CLI session
make db-shell        # Open a MySQL shell
make status          # Show container status
make info            # Show WordPress and plugin info
```

### Rebuilding After Code Changes

Plugin source is bind-mounted — changes to `ai-post-scheduler/` are reflected immediately with no rebuild needed. Only rebuild if you modify `Dockerfile`, `docker-compose.yml`, or Docker image dependencies:

```bash
docker compose up -d --build          # Rebuild and restart
docker compose build --no-cache       # Force clean rebuild
docker compose up -d
```

---

## Client-Side Assets Build

The plugin uses a Node.js build system with Vite and ESBuild to compile modular client scripts and styles into unified minified bundles (`aips-admin.min.js` and `aips-admin.min.css`).

### Setup
From the `ai-post-scheduler` folder:
```bash
cd ai-post-scheduler
npm install
```

### Commands
- **Watcher (Development):** Runs a hot-reload compilation on every save.
  ```bash
  npm run dev
  ```
- **Production Build:** Performs full tree-shaking, minification, and PostCSS vendor prefixing.
  ```bash
  npm run build
  ```

For details on BEM naming conventions, Backbone view guidelines, and IDE integrations (tasks/file watchers), see [core-client-side.md](core-client-side.md).

---

## Xdebug / VS Code Debugging

Xdebug 3.3.1 is pre-configured and enabled for VS Code out of the box.

1. Start the Docker environment (`make up`)
2. Open the project in VS Code
3. Press `F5`
4. Select **"Listen for Xdebug (Docker)"**
5. Set breakpoints in your plugin code
6. Reload the page in your browser — the debugger will pause at your breakpoints

### Xdebug Configuration Details

- **Port:** 9003
- **Path mappings:** pre-configured in `.vscode/launch.json`
- **VS Code Server path:** `/var/www/html/wp-content/plugins/ai-post-scheduler`

### Updating Xdebug / PHP Settings

Edit `dev-php.ini`, then reload Apache without a full rebuild:

```bash
make reload-php
```

Fallback if graceful reload fails:

```bash
docker compose exec web apache2ctl -k graceful
docker compose restart web      # Full restart fallback
```

---

## PHPUnit Testing

All test commands must be run from inside `ai-post-scheduler/`:

```bash
cd ai-post-scheduler

composer install          # Install/update dependencies
composer test             # Full test suite
composer test:verbose     # Verbose output
composer test:coverage    # Generate coverage report

# Run a single test file
vendor/bin/phpunit tests/test-template-processor.php
```

### Canonical Test Workflow (Docker, Recommended)

The suite always runs against the real WordPress PHPUnit test library. The supported local workflow is the Docker-backed wrapper from the repo root:

```bash
bash scripts/run-wp-tests-docker.sh
bash scripts/run-wp-tests-docker.sh coverage
```

That wrapper:
1. starts the Docker database if needed
2. recreates a disposable test database
3. installs WordPress core and `wordpress-tests-lib`
4. exports `WP_TESTS_DIR` and `WP_CORE_DIR`
5. runs `composer test` or `composer test:coverage`

### Direct Suite Execution

If the WordPress test library is already installed, run the suite directly:

```bash
cd ai-post-scheduler
export WP_TESTS_DIR='C:/tmp/wordpress-tests-lib-docker'
export WP_CORE_DIR='C:/tmp/wordpress-docker'
composer test
```

If `WP_TESTS_DIR/includes/functions.php` is missing, `tests/bootstrap.php` fails fast. There is no limited-mode fallback.

### Performance Benchmarks

```bash
cd ai-post-scheduler

# Run benchmark
php bin/benchmark.php --wp-core-dir=/tmp/wordpress

# Compare against baseline
php bin/benchmark.php \
  --wp-core-dir=/tmp/wordpress \
  --baseline-file=../.github/performance-baseline.json \
  --fail-on-regression
```

Benchmarks run automatically in CI on pull requests. See [docs/PERFORMANCE.md](PERFORMANCE.md) for details.

---

## WordPress Management

Use WP-CLI inside the container:

```bash
# Open a shell then run commands
make shell
wp plugin list --allow-root

# Or run commands directly
docker compose exec web wp plugin list --allow-root
docker compose exec web wp theme list --allow-root
docker compose exec web wp user list --allow-root
docker compose exec web wp cache flush --allow-root
```

Plugin management via `make`:

```bash
make plugin-activate      # Activate plugin
make plugin-deactivate    # Deactivate plugin
make plugin-list          # List all plugins
```

---

## Database Operations

**phpMyAdmin:** http://localhost:8082 (wordpress / wordpress)

**Direct MySQL shell:**

```bash
make db-shell
# External: Host=localhost, Port=3307, User=wordpress, Pass=wordpress
```

**Backup and restore:**

```bash
make db-backup            # Saves to backup.sql
make db-restore           # Restores from backup.sql
```

---

## PHP Settings

Edit `dev-php.ini` in the repo root to change PHP settings (memory limit, error reporting, Xdebug options). Apply changes without rebuilding:

```bash
make reload-php
```

---

## Troubleshooting

### Docker is not running

Start Docker Desktop and wait for it to fully initialize before running any Docker commands.

### Port already in use

If ports 8080 or 8082 are in use, update the port mappings in `docker-compose.yml` or set them in `.env`:

```yaml
services:
  web:
    ports:
      - "8081:80"    # Changed from 8080 to 8081
```

Or find what is using the port:

```bash
# Mac/Linux
lsof -i :8080

# Windows (PowerShell)
netstat -ano | findstr :8080
```

### Container keeps restarting

```bash
docker compose logs web    # Check for errors
docker compose logs db
```

### "healthcheck.sh: not found" error

Ensure you are running the start script from the repo root (where `docker-compose.yml` lives).

### No space left on device

```bash
docker system prune -a --volumes    # Clean up unused images/volumes
docker system df                    # Check disk usage
```

### Changes not reflected in browser

```bash
docker compose exec web wp cache flush --allow-root
```

Verify the volume mount is working:

```bash
docker compose exec web ls /var/www/html/wp-content/plugins/ai-post-scheduler
```

### Plugin activation fails

```bash
docker compose logs web | grep -i error
docker compose exec web wp plugin activate ai-post-scheduler --allow-root
```

### WordPress installation fails / database connection error

```bash
docker compose ps          # Is the db container healthy?
docker compose logs db     # Any database errors?

# Try restarting in order
docker compose down
docker compose up -d db
sleep 10
docker compose up -d web
```

### Xdebug not connecting

```bash
make xdebug-status         # Check Xdebug config
make xdebug-log            # View Xdebug log
make reload-php            # Apply dev-php.ini changes
```

Ensure VS Code has **"Listen for Xdebug (Docker)"** selected (not a local config).

### Headers already sent warnings

Check for UTF-8 BOM at the start of PHP files, or restart the container: `make restart`.

### Cannot connect to Docker daemon

On Linux, add your user to the Docker group:

```bash
sudo usermod -aG docker $USER
newgrp docker
```
