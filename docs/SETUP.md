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

The script verifies Docker is running, stops any existing containers, builds images, and starts all services. First startup takes a few minutes — Docker downloads images, installs WordPress, configures the database, and activates the plugin.

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

| Service    | URL                           | Credentials           |
|------------|-------------------------------|-----------------------|
| WordPress  | http://localhost:8080         | admin / admin         |
| WP Admin   | http://localhost:8080/wp-admin | admin / admin        |
| phpMyAdmin | http://localhost:8082         | wordpress / wordpress |

---

## Daily Commands

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

### Rebuilding

Plugin source is bind-mounted — changes to `ai-post-scheduler/` are reflected immediately with no rebuild. Only rebuild when `Dockerfile`, `docker-compose.yml`, or image dependencies change:

```bash
docker compose up -d --build          # Rebuild and restart
docker compose build --no-cache       # Force clean rebuild
docker compose up -d
```

---

## Xdebug / VS Code Debugging

Xdebug 3.3.1 is pre-configured for VS Code.

1. Start the Docker environment (`make up`)
2. Open the project in VS Code
3. Press `F5` → select **"Listen for Xdebug (Docker)"**
4. Set breakpoints in plugin code
5. Reload the page in the browser

- **Port:** 9003
- **Path mappings:** pre-configured in `.vscode/launch.json`

Edit `dev-php.ini` to change PHP/Xdebug settings, then apply without a full rebuild:

```bash
make reload-php
```

---

## PHPUnit Testing

### Canonical workflow (Docker, recommended)

From the repo root:

```bash
bash scripts/run-wp-tests-docker.sh
bash scripts/run-wp-tests-docker.sh coverage
```

This script starts the Docker database, recreates a disposable test database, installs WordPress core and `wordpress-tests-lib`, exports `WP_TESTS_DIR`/`WP_CORE_DIR`, and runs the suite.

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

---

## MCP Bridge

To connect MCP-compatible tools (GitHub Copilot, automation scripts) to the plugin, see [docs/MCP_BRIDGE.md](MCP_BRIDGE.md).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Port in use | Change `WP_PORT` in `.env` or `docker-compose.yml` |
| Changes not in browser | `docker compose exec web wp cache flush --allow-root` |
| Xdebug not connecting | `make xdebug-status`, `make reload-php` |
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
