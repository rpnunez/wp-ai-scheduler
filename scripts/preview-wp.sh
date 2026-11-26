#!/usr/bin/env bash
# Serve this checkout's WordPress for the Claude Code desktop Browser pane
# (.claude/launch.json → `make preview`), or for a person in a terminal.
#
#   make preview            # WordPress on this instance's WP_PORT from .env
#   PORT=8123 make preview  # WordPress moved to port 8123 (what the pane does)
#
# 1. Gives the checkout its own instance (containers + ports) if it has none,
#    exactly like start-dev.sh / `make test` (scripts/lib/aips-instance.sh).
# 2. When PORT is set (the pane passes the port it assigned), writes it to .env
#    as WP_PORT so the `web` container publishes it.
# 3. Brings up db + web (recreating web if its port changed), then syncs the
#    WordPress site URL to http://localhost:<port> (serialization-safe).
# 4. Stays in the foreground following the web log. On Ctrl+C / SIGTERM it
#    stops only the services this run started, like test-session.sh. A hard
#    kill (Windows task stop, possibly the pane's stop button) skips that, so
#    run `make stop` afterwards if the stack should not keep running. Data
#    volumes are never touched.
#
# First run on a never-provisioned checkout builds the image and installs
# WordPress, which takes a few minutes. For an API key, seed data or a forced
# reprovision use ./start-dev.sh instead.

set -uo pipefail

# Git Bash would rewrite container paths such as --path=/var/www/html into
# C:/Program Files/Git/var/www/html before `docker compose exec` sees them.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "[preview] docker is required." >&2
  exit 1
fi
if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

# shellcheck source=lib/aips-instance.sh
. "$SCRIPT_DIR/lib/aips-instance.sh"
aips_ensure_env_file
aips_ensure_instance
if [ "$ENV_CREATED" = "1" ] || [ "$INSTANCE_CHANGED" = "1" ]; then
  aips_ensure_ports fresh || exit 1
  echo "[preview] New dev-stack instance '${INSTANCE_ID}' for this checkout."
fi

is_running() {
  "${COMPOSE[@]}" ps --status running --services 2>/dev/null | tr -d '\r' | grep -qx "$1"
}

TO_STOP=()
for svc in db web; do
  is_running "$svc" || TO_STOP+=("$svc")
done

TARGET_PORT="${PORT:-$(get_env_var WP_PORT)}"
TARGET_PORT="${TARGET_PORT:-8080}"
if ! [[ "$TARGET_PORT" =~ ^[0-9]+$ ]]; then
  echo "[preview] Invalid port '${TARGET_PORT}'." >&2
  exit 1
fi
if [ "$(get_env_var WP_PORT)" != "$TARGET_PORT" ]; then
  echo "[preview] Moving WordPress for instance '${INSTANCE_ID}' to port ${TARGET_PORT}."
  set_env_var WP_PORT "$TARGET_PORT"
fi

cleanup() {
  if [ "${#TO_STOP[@]}" -gt 0 ]; then
    echo "[preview] Stopping services this preview started: ${TO_STOP[*]}"
    "${COMPOSE[@]}" stop "${TO_STOP[@]}"
  else
    echo "[preview] Stack was already running; leaving it up."
  fi
}
trap cleanup EXIT
trap 'exit 130' INT TERM

echo "[preview] Starting db + web (first boot builds the image and installs WordPress)..."
UP_ARGS=(up -d --pull never db web)
if "${COMPOSE[@]}" up --help 2>/dev/null | grep -q -- '--wait'; then
  UP_ARGS=(up -d --pull never --wait --wait-timeout 600 db web)
fi
if ! "${COMPOSE[@]}" "${UP_ARGS[@]}"; then
  echo "[preview] Services failed to start or become healthy." >&2
  "${COMPOSE[@]}" logs --tail 50 web db
  exit 1
fi

# Point the site at this port (same steps as start-dev.sh).
WP_CLI=("${COMPOSE[@]}" exec -T web wp --path=/var/www/html --allow-root --skip-plugins --skip-themes)
NEW_URL="http://localhost:${TARGET_PORT}"
if "${WP_CLI[@]}" core is-installed >/dev/null 2>&1; then
  OLD_URL="$("${WP_CLI[@]}" option get siteurl 2>/dev/null | tr -d '\r' || true)"
  if [ -n "$OLD_URL" ] && [ "$OLD_URL" != "$NEW_URL" ]; then
    echo "[preview] Syncing site URL: ${OLD_URL} -> ${NEW_URL}"
    "${WP_CLI[@]}" search-replace "$OLD_URL" "$NEW_URL" --all-tables --skip-columns=guid --report-changed-only
    "${WP_CLI[@]}" option update home "$NEW_URL" >/dev/null
    "${WP_CLI[@]}" option update siteurl "$NEW_URL" >/dev/null
  fi
fi

echo "[preview] WordPress: ${NEW_URL}  (admin: ${NEW_URL}/wp-admin, $(get_env_var WP_ADMIN_USER)/$(get_env_var WP_ADMIN_PASSWORD))"
echo "[preview] Following the web log; stop the preview to shut down what it started."
"${COMPOSE[@]}" logs -f --since 1s --no-log-prefix web
