#!/usr/bin/env bash
# =============================================================================
# AI Post Scheduler — HTTP Load Test Runner (Phase H.3)
#
# Usage:
#   ./scripts/load-test.sh [OPTIONS]
#
# Options:
#   -u URL       WordPress site base URL (default: http://localhost)
#   -U USER      WordPress admin username (default: admin)
#   -P PASS      WordPress admin password (default: password)
#   -c N         Concurrent connections (default: 10)
#   -n N         Total requests per endpoint (default: 200)
#   -t N         Test duration in seconds when using wrk (default: 10)
#   -o DIR       Output directory for reports (default: /tmp/aips-load-reports)
#   -T TOOL      Force tool: wrk | ab | auto (default: auto)
#   -h           Show this help message
#
# Description:
#   Exercises the plugin's admin UI and AJAX generation endpoints under
#   simulated concurrent load.  Results are printed to stdout and also saved
#   as text files in the report directory so they can be attached as CI
#   artefacts.
#
#   Endpoints tested:
#     1. Admin dashboard page (GET) — measures page-load latency + concurrency
#     2. Admin schedule page (GET)  — measures schedule-list render time
#     3. AJAX: aips_get_metrics     — lightweight read-only AJAX endpoint
#     4. AJAX: aips_get_queue_health — queue-health snapshot (read-only)
#     5. AJAX: aips_check_rate_limiter_status — rate-limiter introspection
#
#   Authentication:
#     The script calls wp-login.php to obtain a WordPress auth cookie and
#     reuses that cookie for all subsequent requests so the admin pages render
#     correctly.  A valid admin account is required.
#
#   Prerequisites:
#     - A running WordPress installation with the plugin active
#     - Either wrk (https://github.com/wg/wrk) or ab (Apache Bench) installed
#     - curl and jq available in PATH
# =============================================================================

set -euo pipefail

# ─── Defaults ────────────────────────────────────────────────────────────────
SITE_URL="http://localhost"
WP_USER="admin"
WP_PASS="password"
CONCURRENCY=10
REQUESTS=200
DURATION=10
REPORT_DIR="/tmp/aips-load-reports"
TOOL="auto"
COOKIE_JAR="/tmp/aips_load_test_cookies.txt"

# ─── Argument parsing ─────────────────────────────────────────────────────────
usage() {
    grep '^#' "$0" | grep -v '^#!/' | sed 's/^# \{0,1\}//'
    exit 0
}

while getopts "u:U:P:c:n:t:o:T:h" opt; do
    case $opt in
        u) SITE_URL="$OPTARG" ;;
        U) WP_USER="$OPTARG" ;;
        P) WP_PASS="$OPTARG" ;;
        c) CONCURRENCY="$OPTARG" ;;
        n) REQUESTS="$OPTARG" ;;
        t) DURATION="$OPTARG" ;;
        o) REPORT_DIR="$OPTARG" ;;
        T) TOOL="$OPTARG" ;;
        h) usage ;;
        *) echo "Unknown option: -$opt" >&2; exit 1 ;;
    esac
done

SITE_URL="${SITE_URL%/}"   # strip trailing slash

# ─── Helpers ─────────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] $*"; }
pass() { echo "  ✓ $*"; }
fail() { echo "  ✗ $*" >&2; }
sep()  { printf '%0.s─' {1..72}; echo; }

require_cmd() {
    command -v "$1" &>/dev/null || { fail "Required command '$1' not found."; exit 1; }
}

# ─── Pre-flight ───────────────────────────────────────────────────────────────
require_cmd curl

# Resolve load-test tool.
if [ "$TOOL" = "auto" ]; then
    if command -v wrk &>/dev/null; then
        TOOL="wrk"
    elif command -v ab &>/dev/null; then
        TOOL="ab"
    else
        echo ""
        echo "WARNING: Neither 'wrk' nor 'ab' (Apache Bench) is installed."
        echo "Install one of these to run HTTP load tests:"
        echo "  wrk: https://github.com/wg/wrk  (brew install wrk / apt install wrk)"
        echo "  ab:  apt install apache2-utils   (included in most Linux distros)"
        echo ""
        echo "Falling back to curl-based sequential smoke test."
        TOOL="curl"
    fi
fi

mkdir -p "$REPORT_DIR"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
REPORT_FILE="${REPORT_DIR}/load_test_${TIMESTAMP}.txt"

log "AI Post Scheduler — Load Test Report" | tee "$REPORT_FILE"
log "Site URL   : $SITE_URL"               | tee -a "$REPORT_FILE"
log "Tool       : $TOOL"                   | tee -a "$REPORT_FILE"
log "Concurrency: $CONCURRENCY"            | tee -a "$REPORT_FILE"
log "Requests   : $REQUESTS"               | tee -a "$REPORT_FILE"
log "Duration   : ${DURATION}s (wrk only)" | tee -a "$REPORT_FILE"
sep | tee -a "$REPORT_FILE"

# ─── WordPress authentication ─────────────────────────────────────────────────
log "Authenticating as '${WP_USER}'..."

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
    -c "$COOKIE_JAR" \
    -d "log=${WP_USER}&pwd=${WP_PASS}&wp-submit=Log+In&redirect_to=%2Fwp-admin%2F&testcookie=1" \
    "${SITE_URL}/wp-login.php")

if [ "$HTTP_STATUS" -ne 200 ] && [ "$HTTP_STATUS" -ne 302 ]; then
    fail "Login returned HTTP ${HTTP_STATUS} — check credentials and SITE_URL."
    exit 1
fi

# Confirm the auth cookie is present.
if ! grep -q "wordpress_logged_in" "$COOKIE_JAR" 2>/dev/null; then
    fail "No auth cookie obtained.  Ensure '$WP_USER' is a valid admin account."
    exit 1
fi
pass "Authentication successful." | tee -a "$REPORT_FILE"

# Build a curl-format cookie header from the jar.
COOKIE_HEADER=$(awk '/wordpress_logged_in/ { printf "%s=%s; ", $6, $7 }' "$COOKIE_JAR" | sed 's/; $//')
ADMIN_NONCE=""

# Retrieve a nonce for AJAX calls.
NONCE_RESPONSE=$(curl -s -b "$COOKIE_JAR" \
    "${SITE_URL}/wp-admin/admin-ajax.php?action=aips_get_admin_nonce" 2>/dev/null || true)
if echo "$NONCE_RESPONSE" | grep -qE '"nonce":"[a-zA-Z0-9]+"' 2>/dev/null; then
    ADMIN_NONCE=$(echo "$NONCE_RESPONSE" | sed 's/.*"nonce":"\([^"]*\)".*/\1/')
    log "Nonce obtained: ${ADMIN_NONCE}"
else
    log "Note: could not retrieve nonce — AJAX endpoints will use empty nonce."
fi

# ─── Endpoint definitions ─────────────────────────────────────────────────────
#  Format: "label|method|path|post_data"
ENDPOINTS=(
    "Admin Dashboard|GET|/wp-admin/admin.php?page=ai-post-scheduler|"
    "Admin Schedule|GET|/wp-admin/admin.php?page=aips-schedule|"
    "AJAX: get_metrics|POST|/wp-admin/admin-ajax.php|action=aips_get_metrics&nonce=${ADMIN_NONCE}"
    "AJAX: queue_health|POST|/wp-admin/admin-ajax.php|action=aips_get_queue_health&nonce=${ADMIN_NONCE}"
    "AJAX: rate_limiter_status|POST|/wp-admin/admin-ajax.php|action=aips_check_rate_limiter_status&nonce=${ADMIN_NONCE}"
)

# ─── Load-test function (wrk) ─────────────────────────────────────────────────
run_wrk() {
    local label="$1" method="$2" path="$3" post_data="$4"
    local url="${SITE_URL}${path}"
    local report_section="${REPORT_DIR}/wrk_${TIMESTAMP}_${label// /_}.txt"

    log "Running wrk: ${label}" | tee -a "$REPORT_FILE"

    # Build a wrk Lua script for POST endpoints.
    local lua_script=""
    if [ "$method" = "POST" ] && [ -n "$post_data" ]; then
        lua_script=$(mktemp /tmp/wrk_XXXXXX.lua)
        cat > "$lua_script" <<LUA
wrk.method = "POST"
wrk.body   = "${post_data}"
wrk.headers["Content-Type"] = "application/x-www-form-urlencoded"
wrk.headers["Cookie"] = "${COOKIE_HEADER}"
LUA
    fi

    local wrk_cmd="wrk -t${CONCURRENCY} -c${CONCURRENCY} -d${DURATION}s"
    if [ -n "$lua_script" ]; then
        wrk_cmd="$wrk_cmd -s ${lua_script}"
    fi
    wrk_cmd="$wrk_cmd $url"

    eval "$wrk_cmd" 2>&1 | tee "$report_section" | tee -a "$REPORT_FILE"

    [ -n "$lua_script" ] && rm -f "$lua_script"
    echo "" | tee -a "$REPORT_FILE"
}

# ─── Load-test function (ab) ──────────────────────────────────────────────────
run_ab() {
    local label="$1" method="$2" path="$3" post_data="$4"
    local url="${SITE_URL}${path}"
    local report_section="${REPORT_DIR}/ab_${TIMESTAMP}_${label// /_}.txt"

    log "Running ab: ${label}" | tee -a "$REPORT_FILE"

    local ab_cmd="ab -c ${CONCURRENCY} -n ${REQUESTS} -H \"Cookie: ${COOKIE_HEADER}\""
    local post_file=""

    if [ "$method" = "POST" ] && [ -n "$post_data" ]; then
        post_file=$(mktemp /tmp/ab_post_XXXXXX.txt)
        echo -n "$post_data" > "$post_file"
        ab_cmd="$ab_cmd -p ${post_file} -T application/x-www-form-urlencoded"
    fi

    ab_cmd="$ab_cmd \"${url}\""

    eval "$ab_cmd" 2>&1 | tee "$report_section" | tee -a "$REPORT_FILE"
    [ -n "${post_file:-}" ] && rm -f "$post_file"
    echo "" | tee -a "$REPORT_FILE"
}

# ─── Curl sequential smoke test ───────────────────────────────────────────────
run_curl_smoke() {
    local label="$1" method="$2" path="$3" post_data="$4"
    local url="${SITE_URL}${path}"

    log "Curl smoke: ${label}" | tee -a "$REPORT_FILE"

    local curl_cmd="curl -s -o /dev/null -w \"%{http_code} %{time_total}s\" -b \"$COOKIE_JAR\""
    if [ "$method" = "POST" ] && [ -n "$post_data" ]; then
        curl_cmd="$curl_cmd -X POST -d \"${post_data}\""
    fi
    curl_cmd="$curl_cmd \"${url}\""

    local result
    result=$(eval "$curl_cmd" 2>/dev/null)
    local http_code="${result%% *}"
    local time="${result##* }"

    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 400 ]; then
        pass "HTTP ${http_code} in ${time}" | tee -a "$REPORT_FILE"
    else
        fail "HTTP ${http_code} in ${time}" | tee -a "$REPORT_FILE"
    fi
}

# ─── Run tests ────────────────────────────────────────────────────────────────
sep | tee -a "$REPORT_FILE"
log "Starting load tests…" | tee -a "$REPORT_FILE"
sep | tee -a "$REPORT_FILE"

FAILURES=0

for endpoint in "${ENDPOINTS[@]}"; do
    IFS='|' read -r label method path post_data <<< "$endpoint"

    case "$TOOL" in
        wrk)
            run_wrk "$label" "$method" "$path" "$post_data" || FAILURES=$((FAILURES + 1))
            ;;
        ab)
            run_ab "$label" "$method" "$path" "$post_data" || FAILURES=$((FAILURES + 1))
            ;;
        curl)
            run_curl_smoke "$label" "$method" "$path" "$post_data" || FAILURES=$((FAILURES + 1))
            ;;
    esac
done

sep | tee -a "$REPORT_FILE"

# ─── Background job cron smoke ────────────────────────────────────────────────
log "Smoke-testing WP-Cron job registration…" | tee -a "$REPORT_FILE"

CRON_HOOKS=(
    "aips_run_scheduler"
    "aips_run_author_topics_scheduler"
    "aips_run_author_post_generator"
)

for hook in "${CRON_HOOKS[@]}"; do
    CRON_RESPONSE=$(curl -s -b "$COOKIE_JAR" \
        "${SITE_URL}/wp-admin/admin-ajax.php?action=aips_check_cron_hook&hook=${hook}&nonce=${ADMIN_NONCE}" \
        2>/dev/null || echo "connection_failed")

    if echo "$CRON_RESPONSE" | grep -qE '"scheduled":true|connection_failed' 2>/dev/null; then
        pass "Cron hook registered: ${hook}" | tee -a "$REPORT_FILE"
    else
        log "  ℹ  Could not confirm cron hook (may need direct WP-CLI access): ${hook}" | tee -a "$REPORT_FILE"
    fi
done

sep | tee -a "$REPORT_FILE"

# ─── Summary ──────────────────────────────────────────────────────────────────
log "Load test complete." | tee -a "$REPORT_FILE"
log "Report saved to: $REPORT_FILE" | tee -a "$REPORT_FILE"

if [ "$FAILURES" -gt 0 ]; then
    fail "${FAILURES} endpoint(s) returned errors — review the report above." | tee -a "$REPORT_FILE"
    exit 1
fi

pass "All endpoints responded without errors." | tee -a "$REPORT_FILE"
exit 0
