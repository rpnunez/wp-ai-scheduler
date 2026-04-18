#!/usr/bin/env bash
# Sync WordPress files from the running web container to a local mirror for IDE path mapping.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

TARGET_DIR="${1:-${REPO_ROOT}/.docker/wp-html}"
CONTAINER_PATH="${2:-/var/www/html}"

if [[ "${TARGET_DIR}" != /* ]] && [[ ! "${TARGET_DIR}" =~ ^[A-Za-z]:[\\/] ]]; then
  TARGET_DIR="${REPO_ROOT}/${TARGET_DIR}"
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker-compose)
else
  echo "[sync-wp-core] Error: neither 'docker compose' nor 'docker-compose' is available." >&2
  exit 1
fi

echo "[sync-wp-core] Syncing ${CONTAINER_PATH} from service 'web' to ${TARGET_DIR} ..."
rm -rf "${TARGET_DIR}"
mkdir -p "${TARGET_DIR}"

# Important: do not disable Git Bash path conversion here; docker compose cp
# needs it for the local destination path on Windows.
"${COMPOSE_CMD[@]}" cp "web:${CONTAINER_PATH}/." "${TARGET_DIR}"

echo "[sync-wp-core] Done."
echo "[sync-wp-core] IDE path mapping hint:"
echo "  Remote: ${CONTAINER_PATH}"
echo "  Local:  ${TARGET_DIR}"
