#!/usr/bin/env bash
# Reload Apache in the web container so PHP/Xdebug ini changes are applied.

set -euo pipefail

if command -v docker compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
else
  COMPOSE_CMD=(docker-compose)
fi

echo "[reload-php] Reloading Apache in web container..."
"${COMPOSE_CMD[@]}" exec web apache2ctl -k graceful

echo "[reload-php] Done. Updated ini settings should now be active."

