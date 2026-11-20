#!/usr/bin/env bash
# Seed development database with deterministic dummy data for UI testing without making AI calls.
#
# Usage:
#   bash scripts/seed-dev-data.sh [options]
#   make seed-dev-data [PRESET=ui-processing] [CLEAN=1] [COUNT=1] [INCLUDE=templates,pending]
#
# Options forwarded to runner:
#   --preset=<name>    Preset to use (default: ui-processing; options: ui-processing, full, stress)
#   --clean            Purge prior dev-seeded data before seeding
#   --count=<n>        Multiply count for bulk stress testing
#   --include=<list>   Comma-separated list (templates,pending,published,authors)

set -euo pipefail

# Git Bash on Windows rewrites /unix/looking/paths in arguments passed to docker.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

CONTAINER_RUNNER="/var/www/html/wp-content/plugins/ai-post-scheduler/bin/dev-seeder-runner.php"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required." >&2
  exit 1
fi

compose=(docker compose)
if ! docker compose version >/dev/null 2>&1; then
  compose=(docker-compose)
fi

if ! "${compose[@]}" exec -T web true >/dev/null 2>&1; then
  echo "The dev stack's web container is not running. Start it with: make start (or ./start-dev.sh the first time)." >&2
  exit 1
fi

ARGS=()
for arg in "$@"; do
  # Strip leading dashes so WP-CLI does not treat positional arguments as unrecognized global flags
  clean_arg="${arg#--}"
  clean_arg="${clean_arg#-}"
  ARGS+=("$clean_arg")
done

"${compose[@]}" exec -T web wp eval-file "$CONTAINER_RUNNER" "${ARGS[@]}" --allow-root
