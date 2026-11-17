#!/usr/bin/env bash
# Run the AI payload capture tool (bin/ai-payload-capture.php) inside the dev
# stack's `web` container, where the Gemini connector is already configured
# from .env's GOOGLE_API_KEY (see docker-entrypoint.sh).
#
# Usage:
#   bash scripts/ai-payload-capture.sh [options]     # = make ai-payload-capture ARGS="..."
#   bash scripts/ai-payload-capture.sh --help
#
# The plugin directory is bind-mounted into the container, so the report
# lands directly on the host at:
#   ai-post-scheduler/.artifacts/ai-payload-capture/<run-id>/REPORT.md
# (or wherever --out= points, if given). Nothing needs copying out.
#
# This always targets THIS checkout's own dev stack; it can never reach
# production.

set -euo pipefail

# Git Bash on Windows rewrites /unix/looking/paths in arguments passed to docker.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

CONTAINER_SCRIPT="/var/www/html/wp-content/plugins/ai-post-scheduler/bin/ai-payload-capture.php"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required." >&2
  exit 1
fi

compose=(docker-compose)
if docker compose version >/dev/null 2>&1; then
  compose=(docker compose)
fi

if ! "${compose[@]}" exec -T web true >/dev/null 2>&1; then
  echo "The dev stack's web container is not running. Start it with: make start (or ./start-dev.sh the first time)." >&2
  exit 1
fi

"${compose[@]}" exec -T web php "$CONTAINER_SCRIPT" "$@"
