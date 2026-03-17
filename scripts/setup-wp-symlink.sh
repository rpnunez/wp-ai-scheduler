#!/usr/bin/env bash
#
# setup-wp-symlink.sh
#
# Checks out the wp-ai-scheduler repository to a local path and creates a
# symlink from the plugin source (ai-post-scheduler/) into an existing
# WordPress installation's wp-content/plugins/ directory.
#
# Usage:
#   ./scripts/setup-wp-symlink.sh [--branch <branch-or-tag>]
#
# Configuration:
#   Edit the variables below, or export them before running the script:
#
#     REPO_URL      — Git remote URL to clone (SSH or HTTPS)
#     REPO_PATH     — Absolute path where the repo should be checked out
#     WP_PATH       — Absolute path to the WordPress installation root
#     REPO_BRANCH   — Branch, tag, or commit to check out (default: main)
#
# Examples:
#   # Clone main and link the plugin
#   ./scripts/setup-wp-symlink.sh
#
#   # Clone a specific branch via argument
#   ./scripts/setup-wp-symlink.sh --branch feature/my-feature
#
#   # Override variables inline
#   REPO_PATH=/srv/repos/wp-ai-scheduler WP_PATH=/var/www/html ./scripts/setup-wp-symlink.sh

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration — edit these or export before running
# ---------------------------------------------------------------------------

# Git remote URL for the repository (SSH or HTTPS).
REPO_URL="${REPO_URL:-git@github.com:rpnunez/wp-ai-scheduler.git}"

# Absolute path on the server where the repository will be cloned / already lives.
REPO_PATH="${REPO_PATH:-/srv/repos/wp-ai-scheduler}"

# Absolute path to the root of the WordPress installation (the directory that
# contains wp-config.php and wp-content/).
WP_PATH="${WP_PATH:-/var/www/html}"

# Branch, tag, or commit SHA to check out.
REPO_BRANCH="${REPO_BRANCH:-main}"

# ---------------------------------------------------------------------------
# Parse optional command-line arguments
# ---------------------------------------------------------------------------

while [[ $# -gt 0 ]]; do
    case "$1" in
        --branch)
            REPO_BRANCH="${2:?"--branch requires a value"}"
            shift 2
            ;;
        --repo-url)
            REPO_URL="${2:?"--repo-url requires a value"}"
            shift 2
            ;;
        --repo-path)
            REPO_PATH="${2:?"--repo-path requires a value"}"
            shift 2
            ;;
        --wp-path)
            WP_PATH="${2:?"--wp-path requires a value"}"
            shift 2
            ;;
        -h|--help)
            grep '^#' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            echo "Run with --help for usage." >&2
            exit 1
            ;;
    esac
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

info()    { echo "[INFO]  $*"; }
success() { echo "[OK]    $*"; }
warn()    { echo "[WARN]  $*" >&2; }
error()   { echo "[ERROR] $*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Validate configuration
# ---------------------------------------------------------------------------

[[ -z "$REPO_URL"    ]] && error "REPO_URL is not set."
[[ -z "$REPO_PATH"   ]] && error "REPO_PATH is not set."
[[ -z "$WP_PATH"     ]] && error "WP_PATH is not set."
[[ -z "$REPO_BRANCH" ]] && error "REPO_BRANCH is not set."

# Resolve WP plugins directory
PLUGINS_DIR="${WP_PATH}/wp-content/plugins"

info "Configuration:"
info "  REPO_URL    : ${REPO_URL}"
info "  REPO_PATH   : ${REPO_PATH}"
info "  REPO_BRANCH : ${REPO_BRANCH}"
info "  WP_PATH     : ${WP_PATH}"
info "  PLUGINS_DIR : ${PLUGINS_DIR}"
echo ""

# ---------------------------------------------------------------------------
# Validate the WordPress installation
# ---------------------------------------------------------------------------

if [[ ! -f "${WP_PATH}/wp-config.php" && ! -f "${WP_PATH}/../wp-config.php" ]]; then
    warn "wp-config.php not found under ${WP_PATH}. Make sure WP_PATH points to the WordPress root."
fi

if [[ ! -d "$PLUGINS_DIR" ]]; then
    error "Plugins directory not found: ${PLUGINS_DIR}. Is WP_PATH correct?"
fi

# ---------------------------------------------------------------------------
# Clone or update the repository
# ---------------------------------------------------------------------------

if [[ -d "${REPO_PATH}/.git" ]]; then
    info "Repository already exists at ${REPO_PATH}. Fetching latest…"
    git -C "$REPO_PATH" fetch --all --prune
    git -C "$REPO_PATH" checkout "$REPO_BRANCH"
    git -C "$REPO_PATH" pull --ff-only origin "$REPO_BRANCH" \
        || warn "Could not fast-forward; you may have local changes. Skipping pull."
    success "Repository updated to branch '${REPO_BRANCH}'."
else
    info "Cloning ${REPO_URL} into ${REPO_PATH}…"
    git clone --branch "$REPO_BRANCH" "$REPO_URL" "$REPO_PATH"
    success "Repository cloned."
fi

# ---------------------------------------------------------------------------
# Verify the plugin source directory exists inside the repo
# ---------------------------------------------------------------------------

PLUGIN_SOURCE="${REPO_PATH}/ai-post-scheduler"

if [[ ! -d "$PLUGIN_SOURCE" ]]; then
    error "Plugin source directory not found: ${PLUGIN_SOURCE}"
fi

# ---------------------------------------------------------------------------
# Create (or update) the symlink in wp-content/plugins/
# ---------------------------------------------------------------------------

SYMLINK_TARGET="${PLUGINS_DIR}/ai-post-scheduler"

if [[ -L "$SYMLINK_TARGET" ]]; then
    EXISTING_DEST=$(readlink "$SYMLINK_TARGET")
    if [[ "$EXISTING_DEST" == "$PLUGIN_SOURCE" ]]; then
        success "Symlink already correct: ${SYMLINK_TARGET} -> ${PLUGIN_SOURCE}"
        exit 0
    fi
    warn "Symlink exists but points elsewhere (${EXISTING_DEST}). Updating…"
    rm "$SYMLINK_TARGET"
elif [[ -e "$SYMLINK_TARGET" ]]; then
    error "${SYMLINK_TARGET} exists and is not a symlink. Remove or rename it before running this script."
fi

ln -s "$PLUGIN_SOURCE" "$SYMLINK_TARGET"
success "Symlink created: ${SYMLINK_TARGET} -> ${PLUGIN_SOURCE}"

echo ""
success "Done. Activate the 'AI Post Scheduler' plugin from the WordPress admin (Plugins page)."
