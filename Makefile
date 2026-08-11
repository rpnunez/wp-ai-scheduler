# Makefile for AI Post Scheduler Docker Development Environment
# Provides convenient shortcuts for common Docker operations

.PHONY: help build up down restart logs shell wp-shell db-shell clean rebuild install test test-coverage reload-php xdebug-log-follow sync-wp-core \
	qa-build qa-up qa-seed qa-uploads qa-down qa-list qa-logs qa-shell qa-urls

# --- QA build arguments ------------------------------------------------------
# PRS   comma or space separated pull request ids, e.g. PRS=1887,1888
# DB    database mode for qa-up: seed | keep | fresh | clone:<key>
# FILE  path to a production .sql/.sql.gz dump to cache as the seed
# UPLOADS       path to production media (directory, .zip, .tar.gz) to cache
# UPLOADS_MODE  copy (default, isolated) | mount (shared) | skip
# PORT  pin the WordPress port; omitted means a random free one
# PR    set to 1 to also open a draft pull request for the build
# FORCE set to 1 to rebuild an existing build from a fresh main
# PURGE set to 1 to delete volumes and worktree on qa-down
QA_DB_ARG := $(if $(DB),--db $(DB),)
QA_FILE_ARG := $(if $(FILE),--file $(FILE),)
QA_UPLOADS_ARG := $(if $(UPLOADS),--uploads $(UPLOADS),)
QA_UPLOADS_MODE_ARG := $(if $(UPLOADS_MODE),--uploads-mode $(UPLOADS_MODE),)
QA_PORT_ARG := $(if $(PORT),--port $(PORT),)
QA_PR_ARG := $(if $(PR),--pr,)
QA_FORCE_ARG := $(if $(FORCE),--force,)
QA_PURGE_ARG := $(if $(PURGE),--purge,)

# Default target
.DEFAULT_GOAL := help

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo "$(BLUE)AI Post Scheduler - Docker Development Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Quick Start:$(NC)"
	@echo "  1. Run '$(GREEN)make up$(NC)' to start the environment"
	@echo "  2. Visit $(BLUE)http://localhost:8080$(NC)"
	@echo "  3. Run '$(GREEN)make logs$(NC)' to view logs"
	@echo ""

up: ## Start all services
	@echo "$(GREEN)Starting Docker services...$(NC)"
	docker compose up -d
	@echo "$(GREEN)Services started!$(NC)"
	@echo "WordPress: $(BLUE)http://localhost:8080$(NC)"
	@echo "phpMyAdmin: $(BLUE)http://localhost:8082$(NC)"
	@echo "Run '$(GREEN)make logs$(NC)' to view startup logs"

build: ## Build Docker images
	@echo "$(YELLOW)Building Docker images...$(NC)"
	docker compose build

down: ## Stop and remove containers (keeps volumes)
	@echo "$(YELLOW)Stopping services...$(NC)"
	docker compose down
	@echo "$(GREEN)Services stopped. Data volumes preserved.$(NC)"

stop: ## Stop services without removing containers
	@echo "$(YELLOW)Stopping services...$(NC)"
	docker compose stop
	@echo "$(GREEN)Services stopped.$(NC)"

start: ## Start existing containers
	@echo "$(GREEN)Starting services...$(NC)"
	docker compose start

restart: ## Restart all services
	@echo "$(YELLOW)Restarting services...$(NC)"
	docker compose restart
	@echo "$(GREEN)Services restarted!$(NC)"

reload-php: ## Reload Apache/PHP in web container (applies dev-php.ini changes)
	@echo "$(BLUE)Reloading Apache in web container...$(NC)"
	bash ./scripts/reload-php.sh
	@echo "$(GREEN)Apache reloaded; PHP/Xdebug ini changes applied.$(NC)"

rebuild: ## Rebuild and restart services
	@echo "$(YELLOW)Rebuilding and restarting...$(NC)"
	docker compose up -d --build
	@echo "$(GREEN)Rebuild complete!$(NC)"

logs: ## View logs from all services
	docker compose logs -f

logs-web: ## View WordPress logs only
	docker compose logs -f web

logs-db: ## View database logs only
	docker compose logs -f db

shell: ## Open bash shell in WordPress container
	@echo "$(BLUE)Opening WordPress container shell...$(NC)"
	docker compose exec web bash

wp-shell: ## Open WP-CLI shell
	@echo "$(BLUE)Opening WP-CLI shell...$(NC)"
	docker compose exec web wp shell --allow-root

db-shell: ## Open MySQL shell
	@echo "$(BLUE)Opening MySQL shell...$(NC)"
	docker compose exec db mysql -u wordpress -pwordpress wordpress

status: ## Show status of all services
	@echo "$(BLUE)Docker Services Status:$(NC)"
	docker compose ps

info: ## Show WordPress and plugin info
	@echo "$(BLUE)WordPress Information:$(NC)"
	@docker compose exec web wp core version --allow-root 2>/dev/null || echo "WordPress not ready"
	@echo ""
	@echo "$(BLUE)Installed Plugins:$(NC)"
	@docker compose exec web wp plugin list --allow-root 2>/dev/null || echo "WordPress not ready"
	@echo ""
	@echo "$(BLUE)Xdebug Status:$(NC)"
	@docker compose exec web php -v | grep -i xdebug || echo "Xdebug not detected"

install: ## Install/reinstall WordPress
	@echo "$(YELLOW)Reinstalling WordPress...$(NC)"
	docker compose down -v
	docker compose up -d
	@echo "$(GREEN)WordPress reinstalled!$(NC)"

clean: ## Remove all containers and volumes (DELETES ALL DATA)
	@echo "$(RED)WARNING: This will delete all data!$(NC)"
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	docker compose down -v
	@echo "$(GREEN)Cleanup complete!$(NC)"

prune: ## Clean up unused Docker resources
	@echo "$(YELLOW)Cleaning up Docker resources...$(NC)"
	docker system prune -f
	@echo "$(GREEN)Cleanup complete!$(NC)"

plugin-activate: ## Activate the AI Post Scheduler plugin
	@echo "$(GREEN)Activating plugin...$(NC)"
	docker compose exec web wp plugin activate ai-post-scheduler --allow-root

plugin-deactivate: ## Deactivate the AI Post Scheduler plugin
	@echo "$(YELLOW)Deactivating plugin...$(NC)"
	docker compose exec web wp plugin deactivate ai-post-scheduler --allow-root

plugin-list: ## List all installed plugins
	@echo "$(BLUE)Installed Plugins:$(NC)"
	docker compose exec web wp plugin list --allow-root

test: ## Run plugin tests
	@echo "$(BLUE)Running tests...$(NC)"
	bash ./scripts/run-wp-tests-docker.sh

test-verbose: ## Run plugin tests with verbose output
	@echo "$(BLUE)Running tests (verbose)...$(NC)"
	cd ai-post-scheduler && composer test:verbose

test-coverage: ## Run plugin coverage with Docker-backed WordPress test env
	@echo "$(BLUE)Running coverage...$(NC)"
	bash ./scripts/run-wp-tests-docker.sh coverage

composer-install: ## Install Composer dependencies in plugin
	@echo "$(BLUE)Installing Composer dependencies...$(NC)"
	docker compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer install"

composer-update: ## Update Composer dependencies in plugin
	@echo "$(YELLOW)Updating Composer dependencies...$(NC)"
	docker compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer update"

db-backup: ## Backup database to backup.sql
	@echo "$(BLUE)Backing up database...$(NC)"
	docker compose exec db mysqldump -u wordpress -pwordpress wordpress > backup.sql
	@echo "$(GREEN)Database backed up to backup.sql$(NC)"

db-restore: ## Restore database from backup.sql
	@echo "$(YELLOW)Restoring database...$(NC)"
	docker compose exec -T db mysql -u wordpress -pwordpress wordpress < backup.sql
	@echo "$(GREEN)Database restored!$(NC)"

xdebug-log: ## View Xdebug log
	@echo "$(BLUE)Xdebug Log:$(NC)"
	@docker compose exec web cat /tmp/xdebug.log 2>/dev/null || echo "No Xdebug log found"

xdebug-log-follow: ## Follow Xdebug log (Git Bash-safe wrapper)
	@echo "$(BLUE)Following Xdebug log...$(NC)"
	bash ./scripts/xdebug-log.sh

xdebug-status: ## Check Xdebug configuration
	@echo "$(BLUE)Xdebug Configuration:$(NC)"
	@docker compose exec web php -i | grep -i "xdebug.mode\|xdebug.client_host\|xdebug.client_port\|xdebug.start_with_request"

urls: ## Display all service URLs
	@echo "$(BLUE)Service URLs:$(NC)"
	@echo "WordPress:   $(GREEN)http://localhost:8080$(NC)"
	@echo "Admin:       $(GREEN)http://localhost:8080/wp-admin$(NC) (admin/admin)"
	@echo "phpMyAdmin:  $(GREEN)http://localhost:8082$(NC) (wordpress/wordpress)"
	@echo ""
	@echo "$(BLUE)Database Connection:$(NC)"
	@echo "Host:     localhost"
	@echo "Port:     3307"
	@echo "User:     wordpress"
	@echo "Password: wordpress"
	@echo "Database: wordpress"

sync-wp-core: ## Sync /var/www/html from web container into ./.docker/wp-html for IDE path mappings
	@echo "$(BLUE)Syncing WordPress files from container...$(NC)"
	bash ./scripts/sync-wp-core.sh
	@echo "$(GREEN)Sync complete.$(NC)"

# =============================================================================
# QA builds — bundle N open PRs onto one branch and run it on production data
# =============================================================================

require-prs:
	@test -n "$(PRS)" || { \
		echo "$(RED)PRS is required, e.g. make $(MAKECMDGOALS) PRS=1887,1888$(NC)"; \
		exit 1; \
	}

qa-build: require-prs ## Bundle PRs onto a fresh qa-build branch (PRS=, PR=1, FORCE=1, PORT=)
	bash ./scripts/qa-build.sh --prs "$(PRS)" $(QA_PR_ARG) $(QA_FORCE_ARG) $(QA_PORT_ARG)

qa-up: require-prs ## Start a QA build's isolated stack (PRS=, DB=seed|keep|fresh|clone:key, PORT=)
	bash ./scripts/qa-up.sh --prs "$(PRS)" $(QA_DB_ARG) $(QA_UPLOADS_MODE_ARG) $(QA_PORT_ARG)

qa-seed: require-prs ## Load production DB + media into a QA build (PRS=, FILE=dump.sql, UPLOADS=uploads.zip)
	bash ./scripts/qa-seed.sh --prs "$(PRS)" $(QA_FILE_ARG) $(QA_UPLOADS_ARG) $(QA_UPLOADS_MODE_ARG)

qa-uploads: require-prs ## Apply/refresh production media only, leaving the DB alone (PRS=, UPLOADS=)
	bash ./scripts/qa-seed.sh --prs "$(PRS)" $(QA_UPLOADS_ARG) $(QA_UPLOADS_MODE_ARG) --uploads-only

qa-down: ## Stop a QA build (PRS=, PURGE=1 to delete volumes and worktree, or ALL=1)
	bash ./scripts/qa-down.sh $(if $(ALL),--all,--prs "$(PRS)") $(QA_PURGE_ARG)

qa-list: ## List all QA builds with ports, state and bundled PRs
	@bash ./scripts/qa-list.sh

qa-logs: require-prs ## Follow logs for a QA build (PRS=)
	bash ./scripts/qa-compose.sh --prs "$(PRS)" -- logs -f

qa-shell: require-prs ## Open a shell in a QA build's web container (PRS=)
	bash ./scripts/qa-compose.sh --prs "$(PRS)" -- exec web bash

qa-db-shell: require-prs ## Open a MySQL shell in a QA build's database (PRS=)
	bash ./scripts/qa-compose.sh --prs "$(PRS)" -- exec db mysql -u wordpress -pwordpress wordpress

qa-urls: ## Show URLs for every QA build
	@bash ./scripts/qa-list.sh
