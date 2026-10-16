#!/usr/bin/env bash
# Run a file of read-only SQL queries against the DEV database (the `web`
# container of the docker stack) and bring the results back to the host.
# Also unpacks a results zip produced on another machine (e.g. production).
#
# Usage:
#   bash scripts/sql-batch.sh run <queries.sql>      # = make sql-batch QUERIES=...
#   bash scripts/sql-batch.sh unpack <results.zip>   # = make sql-batch-unpack ZIP=...
#
# `run` always targets the dev stack of THIS checkout; it can never reach
# production. Results land in .artifacts/sql-results/<run-id>/ (gitignored):
# read REPORT.md first, then queries/*.json. The runner itself
# (ai-post-scheduler/bin/sql-batch.php) enforces read-only SQL, a timeout, a
# row cap and redaction of secrets.
#
# On production, run the same runner directly over SSH/Webuzo; see TESTING.md.

set -euo pipefail

# Git Bash on Windows rewrites /unix/looking/paths in arguments passed to docker.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

RESULTS_ROOT=".artifacts/sql-results"
CONTAINER_RUNNER="/var/www/html/wp-content/plugins/ai-post-scheduler/bin/sql-batch.php"
CONTAINER_OUT="/tmp/aips-sql-batch"

usage() {
  sed -n '2,15p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

# Accept Windows-style paths (C:\... or C:/...) under Git Bash.
to_unix_path() {
  local p="$1"
  if command -v cygpath >/dev/null 2>&1 && [[ "$p" =~ ^[A-Za-z]: ]]; then
    cygpath -u "$p"
  else
    printf '%s' "$p"
  fi
}

cmd_run() {
  local file
  file="$(to_unix_path "${1:-}")"
  if [ -z "$file" ] || [ ! -f "$file" ]; then
    echo "Query file not found: ${1:-<none>}" >&2
    echo "Usage: make sql-batch QUERIES=.artifacts/sql-queries/my-queries.sql" >&2
    exit 1
  fi

  if ! command -v docker >/dev/null 2>&1; then
    echo "docker is required." >&2
    exit 1
  fi
  local compose=(docker-compose)
  if docker compose version >/dev/null 2>&1; then
    compose=(docker compose)
  fi

  if ! "${compose[@]}" exec -T web true >/dev/null 2>&1; then
    echo "The dev stack's web container is not running. Start it with: make start (or ./start-dev.sh the first time)." >&2
    exit 1
  fi

  mkdir -p "$RESULTS_ROOT"
  log="$(mktemp)"
  trap 'rm -f "${log:-}"' EXIT

  # Queries go in on stdin; --yes because the dev DB host (`db`) is not local.
  # Exit code 1 only means "some queries failed or were refused": still collect.
  set +e
  "${compose[@]}" exec -T web php "$CONTAINER_RUNNER" - --yes --out="$CONTAINER_OUT" < "$file" 2>&1 | tr -d '\r' | tee "$log"
  local rc=${PIPESTATUS[0]}
  set -e
  if [ "$rc" -ge 2 ]; then
    echo "sql-batch failed (exit $rc)." >&2
    exit "$rc"
  fi

  local dir zip
  dir="$(sed -n 's/^RESULTS_DIR=//p' "$log" | tail -n1)"
  zip="$(sed -n 's/^RESULTS_ZIP=//p' "$log" | tail -n1 | sed 's/ ([0-9,]* bytes)$//')"
  case "$dir" in
    "$CONTAINER_OUT"/sql-batch-*) ;;
    *) echo "Could not determine the results folder from the runner output." >&2; exit 1 ;;
  esac

  "${compose[@]}" cp "web:${dir}" "${RESULTS_ROOT}/"
  case "$zip" in
    "$CONTAINER_OUT"/sql-batch-*.zip|"$CONTAINER_OUT"/sql-batch-*.tar.gz)
      "${compose[@]}" cp "web:${zip}" "${RESULTS_ROOT}/" ;;
  esac
  "${compose[@]}" exec -T web rm -rf "$dir" "$zip" >/dev/null 2>&1 || true

  local name
  name="$(basename "$dir")"
  echo ""
  echo "Results copied to: ${RESULTS_ROOT}/${name}/"
  echo "Read first:        ${RESULTS_ROOT}/${name}/REPORT.md"
  exit "$rc"
}

cmd_unpack() {
  local archive
  archive="$(to_unix_path "${1:-}")"
  if [ -z "$archive" ] || [ ! -f "$archive" ]; then
    echo "Results archive not found: ${1:-<none>}" >&2
    echo "Usage: make sql-batch-unpack ZIP=~/Downloads/sql-batch-YYYYMMDD-HHMMSS-dbname.zip" >&2
    exit 1
  fi

  mkdir -p "$RESULTS_ROOT"
  tmp="$(mktemp -d "${RESULTS_ROOT}/.unpack-XXXXXX")"
  trap 'rm -rf "${tmp:-}"' EXIT

  case "$archive" in
    *.tar.gz|*.tgz) tar -xzf "$archive" -C "$tmp" ;;
    *)
      if command -v unzip >/dev/null 2>&1; then
        unzip -q "$archive" -d "$tmp"
      elif command -v python3 >/dev/null 2>&1; then
        python3 -m zipfile -e "$archive" "$tmp"
      elif command -v python >/dev/null 2>&1; then
        python -m zipfile -e "$archive" "$tmp"
      else
        tar -xf "$archive" -C "$tmp"
      fi
      ;;
  esac

  local top
  top="$(find "$tmp" -mindepth 1 -maxdepth 1 -type d | head -n1)"
  if [ -z "$top" ] || [ ! -f "$top/REPORT.md" ]; then
    echo "That archive does not look like an sql-batch result (no REPORT.md)." >&2
    exit 1
  fi

  local name dest
  name="$(basename "$top")"
  dest="${RESULTS_ROOT}/${name}"
  if [ -e "$dest" ]; then
    echo "Already exists: ${dest} (remove it first to re-import)." >&2
    exit 1
  fi
  mv "$top" "$dest"

  echo "Unpacked to: ${dest}/"
  echo "Read first:  ${dest}/REPORT.md"
}

case "${1:-}" in
  run)    shift; cmd_run "$@" ;;
  unpack) shift; cmd_unpack "$@" ;;
  -h|--help|"") usage ;;
  *) echo "Unknown command: $1" >&2; usage >&2; exit 1 ;;
esac
