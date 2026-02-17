# Makefile for AI Post Scheduler Docker Development Environment
# Provides convenient shortcuts for common Docker operations

.PHONY: help build up down restart logs shell wp-shell db-shell clean rebuild install test

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
	docker-compose up -d
	@echo "$(GREEN)Services started!$(NC)"
	@echo "WordPress: $(BLUE)http://localhost:8080$(NC)"
	@echo "phpMyAdmin: $(BLUE)http://localhost:8082$(NC)"
	@echo "Run '$(GREEN)make logs$(NC)' to view startup logs"

build: ## Build Docker images
	@echo "$(YELLOW)Building Docker images...$(NC)"
	docker-compose build

down: ## Stop and remove containers (keeps volumes)
	@echo "$(YELLOW)Stopping services...$(NC)"
	docker-compose down
	@echo "$(GREEN)Services stopped. Data volumes preserved.$(NC)"

stop: ## Stop services without removing containers
	@echo "$(YELLOW)Stopping services...$(NC)"
	docker-compose stop
	@echo "$(GREEN)Services stopped.$(NC)"

start: ## Start existing containers
	@echo "$(GREEN)Starting services...$(NC)"
	docker-compose start

restart: ## Restart all services
	@echo "$(YELLOW)Restarting services...$(NC)"
	docker-compose restart
	@echo "$(GREEN)Services restarted!$(NC)"

rebuild: ## Rebuild and restart services
	@echo "$(YELLOW)Rebuilding and restarting...$(NC)"
	docker-compose up -d --build
	@echo "$(GREEN)Rebuild complete!$(NC)"

logs: ## View logs from all services
	docker-compose logs -f

logs-web: ## View WordPress logs only
	docker-compose logs -f web

logs-db: ## View database logs only
	docker-compose logs -f db

shell: ## Open bash shell in WordPress container
	@echo "$(BLUE)Opening WordPress container shell...$(NC)"
	docker-compose exec web bash

wp-shell: ## Open WP-CLI shell
	@echo "$(BLUE)Opening WP-CLI shell...$(NC)"
	docker-compose exec web wp shell --allow-root

db-shell: ## Open MySQL shell
	@echo "$(BLUE)Opening MySQL shell...$(NC)"
	docker-compose exec db mysql -u wordpress -pwordpress wordpress

status: ## Show status of all services
	@echo "$(BLUE)Docker Services Status:$(NC)"
	docker-compose ps

info: ## Show WordPress and plugin info
	@echo "$(BLUE)WordPress Information:$(NC)"
	@docker-compose exec web wp core version --allow-root 2>/dev/null || echo "WordPress not ready"
	@echo ""
	@echo "$(BLUE)Installed Plugins:$(NC)"
	@docker-compose exec web wp plugin list --allow-root 2>/dev/null || echo "WordPress not ready"
	@echo ""
	@echo "$(BLUE)Xdebug Status:$(NC)"
	@docker-compose exec web php -v | grep -i xdebug || echo "Xdebug not detected"

install: ## Install/reinstall WordPress
	@echo "$(YELLOW)Reinstalling WordPress...$(NC)"
	docker-compose down -v
	docker-compose up -d
	@echo "$(GREEN)WordPress reinstalled!$(NC)"

clean: ## Remove all containers and volumes (DELETES ALL DATA)
	@echo "$(RED)WARNING: This will delete all data!$(NC)"
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	docker-compose down -v
	@echo "$(GREEN)Cleanup complete!$(NC)"

prune: ## Clean up unused Docker resources
	@echo "$(YELLOW)Cleaning up Docker resources...$(NC)"
	docker system prune -f
	@echo "$(GREEN)Cleanup complete!$(NC)"

plugin-activate: ## Activate the AI Post Scheduler plugin
	@echo "$(GREEN)Activating plugin...$(NC)"
	docker-compose exec web wp plugin activate ai-post-scheduler --allow-root

plugin-deactivate: ## Deactivate the AI Post Scheduler plugin
	@echo "$(YELLOW)Deactivating plugin...$(NC)"
	docker-compose exec web wp plugin deactivate ai-post-scheduler --allow-root

plugin-list: ## List all installed plugins
	@echo "$(BLUE)Installed Plugins:$(NC)"
	docker-compose exec web wp plugin list --allow-root

test: ## Run plugin tests
	@echo "$(BLUE)Running tests...$(NC)"
	docker-compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer test"

test-verbose: ## Run plugin tests with verbose output
	@echo "$(BLUE)Running tests (verbose)...$(NC)"
	docker-compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer test:verbose"

composer-install: ## Install Composer dependencies in plugin
	@echo "$(BLUE)Installing Composer dependencies...$(NC)"
	docker-compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer install"

composer-update: ## Update Composer dependencies in plugin
	@echo "$(YELLOW)Updating Composer dependencies...$(NC)"
	docker-compose exec web bash -c "cd /var/www/html/wp-content/plugins/ai-post-scheduler && composer update"

db-backup: ## Backup database to backup.sql
	@echo "$(BLUE)Backing up database...$(NC)"
	docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > backup.sql
	@echo "$(GREEN)Database backed up to backup.sql$(NC)"

db-restore: ## Restore database from backup.sql
	@echo "$(YELLOW)Restoring database...$(NC)"
	docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < backup.sql
	@echo "$(GREEN)Database restored!$(NC)"

xdebug-log: ## View Xdebug log
	@echo "$(BLUE)Xdebug Log:$(NC)"
	@docker-compose exec web cat /tmp/xdebug.log 2>/dev/null || echo "No Xdebug log found"

xdebug-status: ## Check Xdebug configuration
	@echo "$(BLUE)Xdebug Configuration:$(NC)"
	@docker-compose exec web php -i | grep -i "xdebug.mode\|xdebug.client_host\|xdebug.client_port\|xdebug.start_with_request"

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
