#!/usr/bin/env bash
# Run the live Abilities checks inside the dev stack's web container.
#
#   core group  (@group abilities-live)    real WordPress core abilities; no secrets.
#   AI group    (@group abilities-live-ai) real calls to the WordPress AI plugin with
#                                          Google; needs GOOGLE_API_KEY. Without it the
#                                          AI group is skipped (never failed).
#
# Usage (normally through make):
#   make test-live-abilities
#   GOOGLE_API_KEY=... make test-live-abilities
#
# The key is only passed to the container as an environment variable for this run.
# It is never written to a file. The AI group downloads the `ai` and
# `ai-provider-for-google` plugins from wordpress.org into the container's plugins
# directory (not activated) when they are not there yet; set AIPS_LIVE_PLUGINS_DIR
# to use a directory that already has them.
#
# Exit code: non-zero when any group that ran failed.

set -uo pipefail

# Git Bash on Windows would rewrite container paths such as /var/www/... into host paths.
export MSYS_NO_PATHCONV=1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

PLUGINS_DIR="${AIPS_LIVE_PLUGINS_DIR:-/var/www/html/wp-content/plugins}"
STATUS=0

echo "[live] Core abilities group (no secrets needed)..."
bash ./scripts/run-docker-test.sh --group abilities-live || STATUS=1

if [ -z "${GOOGLE_API_KEY:-}" ]; then
  echo "[live] GOOGLE_API_KEY is not set: skipping the AI group."
  exit "$STATUS"
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
else
  COMPOSE=(docker-compose)
fi

echo "[live] Making sure the WordPress AI plugin and the Google provider are present in ${PLUGINS_DIR}..."
"${COMPOSE[@]}" exec -T -e PLUGINS_DIR="$PLUGINS_DIR" web bash -c '
set -e
for slug in ai ai-provider-for-google; do
  if [ -d "$PLUGINS_DIR/$slug" ]; then
    continue
  fi
  echo "[live] Downloading $slug..."
  curl -fsSL "https://downloads.wordpress.org/plugin/$slug.latest-stable.zip" -o "/tmp/$slug.zip"
  php -r "\$z = new ZipArchive(); if (\$z->open(\"/tmp/$slug.zip\") !== true) { fwrite(STDERR, \"cannot open zip\n\"); exit(1); } \$z->extractTo(\"$PLUGINS_DIR\"); \$z->close();"
  rm -f "/tmp/$slug.zip"
done
' || { echo "[live] Could not install the plugins; AI group not run." >&2; exit 1; }

echo "[live] AI abilities group (real Google calls)..."
AIPS_LIVE_PLUGINS_DIR="$PLUGINS_DIR" bash ./scripts/run-docker-test.sh --group abilities-live-ai || STATUS=1

exit "$STATUS"
