#!/usr/bin/env bash
set -euo pipefail

DUMP_FILE=""
OLD_URL="http://localhost/wp_nunezscheduler"
NEW_URL="http://localhost:8080"
AUTO_EXECUTE=0

DB_VOLUME="wp-ai-scheduler_db_data_v2"
DB_SERVICE="db"
WEB_SERVICE="web"
DB_CONTAINER_NAME="wp-ai-scheduler-db"
PLUGIN_SOURCE_DIR="./ai-post-scheduler"
COMPOSE_FILE="docker-compose.yml"
DRY_RUN_FAILED=0

usage() {
	echo "Usage: ./scripts/import-db-docker.sh [--execute] [dump_file] [old_url] [new_url]" >&2
	echo "Default dump file: /c/tmp/xampp-wordpress.sql" >&2
}

parse_args() {
	local positional=()

	while [[ $# -gt 0 ]]; do
		case "$1" in
			--execute)
				AUTO_EXECUTE=1
				shift
				;;
			-h|--help)
				usage
				exit 0
				;;
			*)
				positional+=("$1")
				shift
				;;
		esac
	done

	DUMP_FILE="${positional[0]:-/c/tmp/xampp-wordpress.sql}"
	OLD_URL="${positional[1]:-$OLD_URL}"
	NEW_URL="${positional[2]:-$NEW_URL}"
}

print_check() {
	local status="$1"
	local label="$2"
	local detail="$3"

	printf '[%s] %s' "$status" "$label"
	if [[ -n "$detail" ]]; then
		printf ' - %s' "$detail"
	fi
	printf '\n'
}

pass_check() {
	print_check "PASS" "$1" "$2"
}

fail_check() {
	DRY_RUN_FAILED=1
	print_check "FAIL" "$1" "$2"
}

check_command() {
	local command_name="$1"

	if command -v "$command_name" >/dev/null 2>&1; then
		pass_check "Command available: $command_name" "$(command -v "$command_name")"
	else
		fail_check "Command available: $command_name" "not found in PATH"
	fi
}

check_file_exists() {
	local label="$1"
	local path="$2"

	if [[ -f "$path" ]]; then
		pass_check "$label" "$path"
	else
		fail_check "$label" "$path not found"
	fi
}

check_directory_exists() {
	local label="$1"
	local path="$2"

	if [[ -d "$path" ]]; then
		pass_check "$label" "$path"
	else
		fail_check "$label" "$path not found"
	fi
}

check_compose_support() {
	if docker compose version >/dev/null 2>&1; then
		pass_check "Docker Compose v2" "available"
	else
		fail_check "Docker Compose v2" "docker compose is not available"
	fi
}

check_service_declared() {
	local service_name="$1"
	local services

	if ! services="$(docker compose config --services 2>/dev/null)"; then
		fail_check "Compose services readable" "docker compose config --services failed"
		return
	fi

	if printf '%s\n' "$services" | grep -qx "$service_name"; then
		pass_check "Compose service declared: $service_name" "present in $COMPOSE_FILE"
	else
		fail_check "Compose service declared: $service_name" "missing from compose config"
	fi
}

get_service_container_id() {
	local service_name="$1"
	docker compose ps -q "$service_name" 2>/dev/null | head -n 1
}

check_service_running() {
	local service_name="$1"
	local container_id
	local state

	container_id="$(get_service_container_id "$service_name")"
	if [[ -z "$container_id" ]]; then
		fail_check "Container running: $service_name" "service is not currently up"
		return
	fi

	state="$(docker inspect -f '{{.State.Status}}' "$container_id" 2>/dev/null || true)"
	if [[ "$state" == "running" || "$state" == "healthy" ]]; then
		pass_check "Container running: $service_name" "status=$state"
	else
		fail_check "Container running: $service_name" "status=${state:-unknown}"
	fi
}

check_db_health() {
	local container_id
	local health

	container_id="$(get_service_container_id "$DB_SERVICE")"
	if [[ -z "$container_id" ]]; then
		fail_check "Database container health" "db service is not currently up"
		return
	fi

	health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null || true)"
	if [[ "$health" == "healthy" || "$health" == "running" ]]; then
		pass_check "Database container health" "status=$health"
	else
		fail_check "Database container health" "status=${health:-unknown}"
	fi
}

check_plugin_mount_source() {
	local compose_config

	if [[ ! -d "$PLUGIN_SOURCE_DIR" ]]; then
		fail_check "Plugin mount source" "$PLUGIN_SOURCE_DIR not found"
		return
	fi

	compose_config="$(docker compose config 2>/dev/null || true)"
	if printf '%s' "$compose_config" | grep -Fq -- "target: /var/www/html/wp-content/plugins/ai-post-scheduler"; then
		pass_check "Plugin mount source" "target /var/www/html/wp-content/plugins/ai-post-scheduler is present in compose config"
	else
		fail_check "Plugin mount source" "expected container target not found in compose config"
	fi
}

run_dry_run() {
	echo "Dry run summary"
	echo "---------------"

	check_file_exists "SQL dump file" "$DUMP_FILE"
	check_file_exists "Docker compose file" "$COMPOSE_FILE"
	check_directory_exists "Plugin source directory" "$PLUGIN_SOURCE_DIR"
	check_command "docker"
	check_compose_support
	check_service_declared "$DB_SERVICE"
	check_service_declared "$WEB_SERVICE"
	check_plugin_mount_source
	check_service_running "$DB_SERVICE"
	check_service_running "$WEB_SERVICE"
	check_db_health

	echo
	if [[ "$DRY_RUN_FAILED" -eq 0 ]]; then
		echo "Dry run result: PASS"
	else
		echo "Dry run result: FAIL"
	fi
	echo
}

confirm_execution() {
	local response

	if [[ "$AUTO_EXECUTE" -eq 1 ]]; then
		return 0
	fi

	printf 'Proceed with import? [Y/n] '
	read -r response
	case "${response:-Y}" in
		Y|y|yes|YES)
			return 0
			;;
		*)
			echo "Import cancelled."
			return 1
			;;
	esac
}

wait_for_db_health() {
	local status=""

	echo "Waiting for MariaDB to become healthy..."
	for _ in {1..60}; do
		status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$DB_CONTAINER_NAME" 2>/dev/null || true)"
		if [[ "$status" == "healthy" ]]; then
			return 0
		fi
		sleep 2
	done

	echo "Database container did not become healthy in time." >&2
	return 1
}

wait_for_wordpress() {
	echo "Waiting for WordPress container..."
	for _ in {1..60}; do
		if docker compose exec -T "$WEB_SERVICE" wp core is-installed --path=/var/www/html --allow-root >/dev/null 2>&1; then
			return 0
		fi
		sleep 2
	done

	echo "WordPress did not become ready in time." >&2
	return 1
}

backup_current_docker_db() {
	local backup_file

	backup_file="$(pwd)/docker-mysql-db-$(date +%F).sql"
	echo "Backing up current Docker database to $backup_file"
	docker compose exec -T "$DB_SERVICE" mysqldump -u wordpress -pwordpress wordpress > "$backup_file"
}

perform_import() {
	echo "Stopping containers..."
	docker compose down

	echo "Removing Docker DB volume: $DB_VOLUME"
	docker volume rm -f "$DB_VOLUME" >/dev/null 2>&1 || true

	echo "Starting fresh containers..."
	docker compose up -d

	wait_for_db_health

	echo "Importing dump: $DUMP_FILE"
	docker compose exec -T "$DB_SERVICE" mysql -u wordpress -pwordpress wordpress < "$DUMP_FILE"

	wait_for_wordpress

	echo "Updating site URLs to $NEW_URL"
	docker compose exec -T "$WEB_SERVICE" wp option update home "$NEW_URL" --path=/var/www/html --allow-root
	docker compose exec -T "$WEB_SERVICE" wp option update siteurl "$NEW_URL" --path=/var/www/html --allow-root

	echo "Running serialized-safe search-replace: $OLD_URL -> $NEW_URL"
	docker compose exec -T "$WEB_SERVICE" wp search-replace \
		"$OLD_URL" \
		"$NEW_URL" \
		--all-tables \
		--skip-columns=guid \
		--path=/var/www/html \
		--allow-root

	echo
	echo "Import complete."
	echo "WordPress: $NEW_URL"
	echo "phpMyAdmin: http://localhost:8082"
}

main() {
	parse_args "$@"
	run_dry_run

	if [[ "$DRY_RUN_FAILED" -ne 0 ]]; then
		echo "Dry run failed. Fix the reported issues and run the script again." >&2
		exit 1
	fi

	if ! confirm_execution; then
		exit 0
	fi

	backup_current_docker_db
	perform_import
}

main "$@"
