#!/usr/bin/env bash
# Tail Xdebug log from the web container, avoiding Git Bash path conversion.

set -euo pipefail

LOG_PATH="${1:-/tmp/xdebug.log}"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker-compose)
else
  echo "[xdebug-log] Error: neither 'docker compose' nor 'docker-compose' is available." >&2
  exit 1
fi

echo "[xdebug-log] Tailing ${LOG_PATH} from web container..."

# Git Bash on Windows rewrites Unix paths (for example /tmp/*) unless disabled.
if [ -n "${MSYSTEM:-}" ]; then
  MSYS_NO_PATHCONV=1 "${COMPOSE_CMD[@]}" exec web tail -f "$LOG_PATH"
else
  "${COMPOSE_CMD[@]}" exec web tail -f "$LOG_PATH"
fi

