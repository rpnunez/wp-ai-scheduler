# Makefile for AI Post Scheduler Docker Development Environment
# Provides convenient shortcuts for common Docker operations

.PHONY: help up build down stop start start-dev restart reload-php rebuild \
	logs logs-web logs-db shell wp-shell db-shell status info install clean prune \
	plugin-activate plugin-deactivate plugin-list \
	test test-verbose test-coverage test-ci composer-install composer-update \
	db-backup db-restore \
	xdebug-log xdebug-log-follow xdebug-status xdebug-on xdebug-off \
	urls sync-wp-core

# Default target
.DEFAULT_GOAL := help

# Colors for output. Built with real ESC bytes (not the literal text "\033") so
# they render under any shell/echo. Set NO_COLOR=1 to disable. Keep these lines
# free of trailing spaces/comments, which would become part of the value.
ifdef NO_COLOR
BLUE :=
GREEN :=
YELLOW :=
RED :=
NC :=
else
BLUE := $(shell printf '\033[0;34m')
GREEN := $(shell printf '\033[0;32m')
YELLOW := $(shell printf '\033[0;33m')
RED := $(shell printf '\033[0;31m')
NC := $(shell printf '\033[0m')
endif

# env_get(KEY,DEFAULT): read KEY from .env (last occurrence wins), falling back
# to DEFAULT. `cut -f2-` keeps values that contain '='; `tr` strips CR from
# files saved with Windows line endings.
env_get = $(or $(shell grep -hE '^$(1)=' .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r'),$(2))

# Per-instance host ports, read from .env (provisioned by start-dev.sh) with a
# fallback to the shared defaults. This keeps the URLs/ports shown by targets
# like `make urls` in sync with whatever ports this instance actually uses.
WP_PORT         := $(call env_get,WP_PORT,8080)
PHPMYADMIN_PORT := $(call env_get,PHPMYADMIN_PORT,8082)
MYSQL_PORT      := $(call env_get,MYSQL_PORT,3307)
INSTANCE_ID     := $(call env_get,AIPS_INSTANCE_ID,default)

# Credentials (same .env keys docker-compose.yml uses, same defaults).
MYSQL_USER          := $(call env_get,MYSQL_USER,wordpress)
MYSQL_PASSWORD      := $(call env_get,MYSQL_PASSWORD,wordpress)
MYSQL_DATABASE      := $(call env_get,MYSQL_DATABASE,wordpress)
WP_ADMIN_USER       := $(call env_get,WP_ADMIN_USER,admin)
WP_ADMIN_PASSWORD   := $(call env_get,WP_ADMIN_PASSWORD,admin)

# Compose project name (used to scope cleanup to this checkout only): an
# explicit COMPOSE_PROJECT_NAME from .env, else compose's default (the
# directory name, lowercased, with unsupported characters removed).
PROJECT := $(or $(call env_get,COMPOSE_PROJECT_NAME,),$(shell basename "$(CURDIR)" | tr 'A-Z' 'a-z' | tr -cd 'a-z0-9_-'))

help: ## Show this help message
	@echo "$(BLUE)AI Post Scheduler - Docker Development Commands$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(GREEN)%-15s$(NC) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(YELLOW)Quick Start:$(NC)"
	@echo "  1. First time (or after pulling changes): '$(GREEN)make start-dev$(NC)'"
	@echo "     Day to day: '$(GREEN)make start$(NC)' / '$(GREEN)make stop$(NC)'"
	@echo "  2. Visit $(BLUE)http://localhost:$(WP_PORT)$(NC)"
	@echo "  3. Run '$(GREEN)make logs$(NC)' to view logs"
	@echo ""

start-dev: ## Provision + build + start (start-dev.sh; ARGS="--import-sql file.sql")
	bash ./start-dev.sh $(ARGS)

up: ## Start all services (no provisioning; use start-dev on a fresh checkout)
	@echo "$(GREEN)Starting Docker services...$(NC)"
	docker compose up -d
	@echo "$(GREEN)Services started!$(NC)"
	@echo "WordPress: $(BLUE)http://localhost:$(WP_PORT)$(NC)"
	@echo "phpMyAdmin: $(BLUE)http://localhost:$(PHPMYADMIN_PORT)$(NC)"
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
	docker compose exec -e MYSQL_PWD="$(MYSQL_PASSWORD)" db mariadb -u "$(MYSQL_USER)" "$(MYSQL_DATABASE)"

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

prune: ## Remove this project's dangling images only (other projects untouched)
	@echo "$(YELLOW)Removing dangling images for compose project '$(PROJECT)'...$(NC)"
	docker image prune -f --filter "label=com.docker.compose.project=$(PROJECT)"
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

test: ## Run plugin tests in the web container (ARGS="tests/Test_X.php" for one file)
	@echo "$(BLUE)Running tests...$(NC)"
	bash ./scripts/run-docker-test.sh $(ARGS)

test-verbose: ## Run plugin tests in the web container with verbose output
	@echo "$(BLUE)Running tests (verbose)...$(NC)"
	bash ./scripts/run-docker-test.sh --verbose $(ARGS)

test-coverage: ## Run all tests with coverage: text summary + HTML in ai-post-scheduler/coverage/
	@echo "$(BLUE)Running coverage (HTML report: ai-post-scheduler/coverage/index.html)...$(NC)"
	bash ./scripts/run-docker-test.sh --coverage-filter includes --coverage-html coverage --coverage-text $(ARGS)

test-ci: ## Run the host-side CI runner (needs host PHP + composer)
	@echo "$(BLUE)Running CI-style tests on the host...$(NC)"
	bash ./scripts/run-wp-tests-docker.sh

composer-install: ## Install Composer dependencies in plugin
	@echo "$(BLUE)Installing Composer dependencies...$(NC)"
	docker compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer install"

composer-update: ## Update Composer dependencies in plugin
	@echo "$(YELLOW)Updating Composer dependencies...$(NC)"
	docker compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer update"

db-backup: ## Backup database to backup.sql
	@echo "$(BLUE)Backing up database...$(NC)"
	docker compose exec -e MYSQL_PWD="$(MYSQL_PASSWORD)" db mariadb-dump -u "$(MYSQL_USER)" "$(MYSQL_DATABASE)" > backup.sql
	@echo "$(GREEN)Database backed up to backup.sql$(NC)"

db-restore: ## Restore database from backup.sql
	@echo "$(YELLOW)Restoring database...$(NC)"
	docker compose exec -T -e MYSQL_PWD="$(MYSQL_PASSWORD)" db mariadb -u "$(MYSQL_USER)" "$(MYSQL_DATABASE)" < backup.sql
	@echo "$(GREEN)Database restored!$(NC)"

xdebug-log: ## View Xdebug log
	@echo "$(BLUE)Xdebug Log:$(NC)"
	@docker compose exec web cat /tmp/xdebug.log 2>/dev/null || echo "No Xdebug log found"

xdebug-log-follow: ## Follow Xdebug log (Git Bash-safe wrapper)
	@echo "$(BLUE)Following Xdebug log...$(NC)"
	bash ./scripts/xdebug-log.sh

xdebug-status: ## Check Xdebug configuration
	@echo "$(BLUE)Xdebug Configuration:$(NC)"
	@echo "XDEBUG_MODE (.env): $(or $(shell grep -hE '^XDEBUG_MODE=' .env 2>/dev/null | tail -1 | cut -d= -f2),off)"
	@docker compose exec web php -i | grep -i "xdebug.mode\|xdebug.client_host\|xdebug.client_port\|xdebug.start_with_request" || echo "$(YELLOW)Xdebug appears disabled inside the container.$(NC)"

xdebug-on: ## Enable Xdebug in .env (mode=develop,debug, trigger-based) and restart web
	@echo "$(YELLOW)Enabling Xdebug in .env...$(NC)"
	@touch .env
	@if grep -q '^XDEBUG_MODE=' .env; then \
		sed -i.bak 's|^XDEBUG_MODE=.*|XDEBUG_MODE=develop,debug|' .env && rm -f .env.bak; \
	else \
		echo 'XDEBUG_MODE=develop,debug' >> .env; \
	fi
	@if grep -q '^XDEBUG_START_WITH_REQUEST=' .env; then \
		sed -i.bak 's|^XDEBUG_START_WITH_REQUEST=.*|XDEBUG_START_WITH_REQUEST=trigger|' .env && rm -f .env.bak; \
	else \
		echo 'XDEBUG_START_WITH_REQUEST=trigger' >> .env; \
	fi
	@echo "$(GREEN)XDEBUG_MODE=develop,debug, XDEBUG_START_WITH_REQUEST=trigger$(NC)"
	@echo "$(YELLOW)Recreating web container to apply Xdebug settings...$(NC)"
	@docker compose up -d --force-recreate web
	@echo "$(GREEN)Xdebug enabled.$(NC)"

xdebug-off: ## Disable Xdebug in .env and restart web
	@echo "$(YELLOW)Disabling Xdebug in .env...$(NC)"
	@touch .env
	@if grep -q '^XDEBUG_MODE=' .env; then \
		sed -i.bak 's|^XDEBUG_MODE=.*|XDEBUG_MODE=off|' .env && rm -f .env.bak; \
	else \
		echo 'XDEBUG_MODE=off' >> .env; \
	fi
	@echo "$(YELLOW)Recreating web container to apply Xdebug settings...$(NC)"
	@docker compose up -d --force-recreate web
	@echo "$(GREEN)Xdebug disabled.$(NC)"

urls: ## Display all service URLs
	@echo "$(BLUE)Service URLs$(NC) (instance: $(GREEN)$(INSTANCE_ID)$(NC)):"
	@echo "WordPress:   $(GREEN)http://localhost:$(WP_PORT)$(NC)"
	@echo "Admin:       $(GREEN)http://localhost:$(WP_PORT)/wp-admin$(NC) ($(WP_ADMIN_USER)/$(WP_ADMIN_PASSWORD))"
	@echo "phpMyAdmin:  $(GREEN)http://localhost:$(PHPMYADMIN_PORT)$(NC) ($(MYSQL_USER)/$(MYSQL_PASSWORD))"
	@echo ""
	@echo "$(BLUE)Database Connection:$(NC)"
	@echo "Host:     localhost"
	@echo "Port:     $(MYSQL_PORT)"
	@echo "User:     $(MYSQL_USER)"
	@echo "Password: $(MYSQL_PASSWORD)"
	@echo "Database: $(MYSQL_DATABASE)"

sync-wp-core: ## Sync /var/www/html from web container into ./.docker/wp-html for IDE path mappings
	@echo "$(BLUE)Syncing WordPress files from container...$(NC)"
	bash ./scripts/sync-wp-core.sh
	@echo "$(GREEN)Sync complete.$(NC)"
