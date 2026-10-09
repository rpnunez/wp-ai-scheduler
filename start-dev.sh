#!/bin/bash
# Start development environment script for Unix/Linux/Mac
# This script builds and starts the Docker development environment.
#
# Every run writes its full output to .artifacts/start-dev-<instance>-<id>.log
# (gitignored) so the output can always be referred back to.
#
# AI configuration: this environment always uses the WP AI Client with the
# Google AI Provider connector. A real GOOGLE_API_KEY is required: if .env
# (or the environment) has none, or still has the 'your_google_api_key_here'
# placeholder, you are prompted for it (saved to the gitignored .env). Without
# a terminal the script fails fast instead of starting a stack that can't work.
# Set AIPS_SKIP_API_KEY_CHECK=1 to start without a key anyway.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

AI_PROVIDER_FIXED="WP_AI_CLIENT"
AI_CONNECTOR_FIXED="ai-provider-for-google"

# Check if we're in the correct directory (should have docker-compose.yml)
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}Error: docker-compose.yml not found. Please run this script from the repository root.${NC}"
    exit 1
fi

# ------------------------------------------------------------------
# .env helpers
# ------------------------------------------------------------------

# Upsert KEY=VALUE in .env: replace an existing line, otherwise append.
set_env_var() {
    local key="$1" value="$2"
    # Escape characters that are special in the sed replacement string.
    value="$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g')"
    if grep -q "^${key}=" .env 2>/dev/null; then
        sed "s|^${key}=.*|${key}=${value}|" .env > .env.tmp && mv .env.tmp .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

# Print the value of KEY from .env (last occurrence, CR and surrounding quotes
# stripped). Prints nothing when absent; never fails under `set -e`.
get_env_var() {
    grep "^${1}=" .env 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d '\r' \
        | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/" || true
}

# Success (0) when the value is empty or looks like a placeholder such as
# 'your_google_api_key_here'. Keep this pattern in sync with the guard in
# docker-entrypoint.sh.
is_placeholder_key() {
    local v
    v="$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
    [ -z "$v" ] && return 0
    case "$v" in
        *your_*|*api_key*|*_here*) return 0 ;;
    esac
    return 1
}

# ------------------------------------------------------------------
# Silent early setup: .env + instance identity (needed to name the log file)
# ------------------------------------------------------------------
ENV_CREATED=0
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        touch .env
    fi
    ENV_CREATED=1
fi

NEW_INSTANCE=0
if ! grep -q "^AIPS_INSTANCE_ID=" .env 2>/dev/null; then
    # Each checkout / git worktree gets a unique instance id and container
    # names (host ports are provisioned inside main). Delete the auto-generated
    # block in .env to regenerate.
    INSTANCE_ID="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9')"
    INSTANCE_ID="$(printf '%s' "$INSTANCE_ID" | tail -c 12)-$(printf '%04x' $((RANDOM % 65536)))"
    set_env_var AIPS_INSTANCE_ID   "${INSTANCE_ID}"
    set_env_var AIPS_WEB_CONTAINER "wp-ai-scheduler-web-${INSTANCE_ID}"
    set_env_var AIPS_DB_CONTAINER  "wp-ai-scheduler-db-${INSTANCE_ID}"
    set_env_var AIPS_PMA_CONTAINER "wp-ai-scheduler-phpmyadmin-${INSTANCE_ID}"
    NEW_INSTANCE=1
else
    INSTANCE_ID="$(get_env_var AIPS_INSTANCE_ID)"
fi
INSTANCE_ID="${INSTANCE_ID:-default}"

# Unique, human-referenceable log file for this run.
RUN_ID="$(od -An -N3 -tx1 /dev/urandom 2>/dev/null | tr -d ' \n')"
RUN_ID="${RUN_ID:-$(printf '%06x' $(( (RANDOM * 256 + RANDOM) % 16777216 )))}"
mkdir -p .artifacts
LOG_FILE=".artifacts/start-dev-${INSTANCE_ID}-${RUN_ID}.log"

# ------------------------------------------------------------------
# Main script body (everything below is captured in the log file)
# ------------------------------------------------------------------
main() {
    set -e    # main runs in a pipeline subshell; re-enable errexit here

    echo -e "${GREEN}Starting WP AI Scheduler Development Environment${NC}"
    echo "Log file:    ${LOG_FILE}"
    echo "Started:     $(date '+%Y-%m-%d %H:%M:%S %z')"
    echo "Directory:   ${PWD}"
    echo "Instance:    ${INSTANCE_ID}"
    echo "Git:         $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?') @ $(git rev-parse --short HEAD 2>/dev/null || echo '?')"
    echo ""

    if [ "$ENV_CREATED" = "1" ]; then
        echo -e "${YELLOW}Created .env from .env.example.${NC}"
    fi

    # Check if Docker is running
    if ! docker info > /dev/null 2>&1; then
        echo -e "${RED}Error: Docker is not running. Please start Docker and try again.${NC}"
        exit 1
    fi

    # Use 'docker compose' (Compose V2, built into Docker CLI)
    if docker compose version >/dev/null 2>&1; then
        DOCKER_COMPOSE="docker compose"
    else
        # Fall back to legacy docker-compose if docker compose not available
        DOCKER_COMPOSE="docker-compose"
    fi
    echo "Compose:     ${DOCKER_COMPOSE}"

    # Run a command, retrying with backoff. Docker Hub / registry auth
    # endpoints occasionally return transient 5xx errors (e.g. "504 Gateway
    # Timeout" from auth.docker.io) that succeed on a later attempt.
    retry() {
        local attempt=1 max=4 delay=5
        until "$@"; do
            if [ "$attempt" -ge "$max" ]; then
                echo -e "${RED}Command failed after ${max} attempts: $*${NC}" >&2
                return 1
            fi
            echo -e "${YELLOW}Attempt ${attempt}/${max} failed; retrying in ${delay}s (transient registry errors are common)...${NC}"
            sleep "$delay"
            attempt=$((attempt + 1))
            delay=$((delay * 2))
        done
    }

    # Check if required files exist
    if [ ! -f "healthcheck.sh" ]; then
        echo -e "${RED}Error: healthcheck.sh not found in current directory.${NC}"
        exit 1
    fi

    if [ ! -f "docker-entrypoint.sh" ]; then
        echo -e "${RED}Error: docker-entrypoint.sh not found in current directory.${NC}"
        exit 1
    fi

    if [ ! -d "ai-post-scheduler" ]; then
        echo -e "${RED}Error: ai-post-scheduler directory not found.${NC}"
        exit 1
    fi

    echo -e "${GREEN}✓ All required files found${NC}"

    # --------------------------------------------------------------
    # Host ports for a newly provisioned instance
    # --------------------------------------------------------------

    # Return success if the given TCP port on 127.0.0.1 has no listener. If
    # /dev/tcp is unsupported the connect errors out and the port is treated
    # as free, so the script falls back to the default base ports.
    port_is_free() {
        ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null
    }

    # Print the first free port at or above the given base port.
    find_free_port() {
        local port="$1"
        while ! port_is_free "$port"; do
            port=$((port + 1))
        done
        echo "$port"
    }

    if [ "$NEW_INSTANCE" = "1" ]; then
        echo -e "${YELLOW}Provisioning a unique instance (names + ports) in .env...${NC}"

        WP_PORT_PICK="$(find_free_port 8080)"
        PHPMYADMIN_PORT_PICK="$(find_free_port 8082)"
        MYSQL_PORT_PICK="$(find_free_port 3307)"
        XDEBUG_PORT_PICK="$(find_free_port 9003)"

        set_env_var WP_PORT         "${WP_PORT_PICK}"
        set_env_var PHPMYADMIN_PORT "${PHPMYADMIN_PORT_PICK}"
        set_env_var MYSQL_PORT      "${MYSQL_PORT_PICK}"
        set_env_var XDEBUG_PORT     "${XDEBUG_PORT_PICK}"

        echo -e "${GREEN}✓ Instance '${INSTANCE_ID}' → WP:${WP_PORT_PICK} phpMyAdmin:${PHPMYADMIN_PORT_PICK} MySQL:${MYSQL_PORT_PICK} Xdebug:${XDEBUG_PORT_PICK}${NC}"
    fi

    # Ensure image/core settings exist in .env (existing instances keep their values).
    grep -q "^WP_IMAGE_TAG=" .env 2>/dev/null || set_env_var WP_IMAGE_TAG "7.1-php8.3-apache"
    grep -q "^WP_CORE_SOURCE=" .env 2>/dev/null || set_env_var WP_CORE_SOURCE "image"

    # --------------------------------------------------------------
    # AI configuration: WP AI Client + Google AI Provider, API key required
    # --------------------------------------------------------------
    echo ""
    echo "AI configuration"
    set_env_var AIPS_AI_PROVIDER "${AI_PROVIDER_FIXED}"
    set_env_var DEFAULT_AI_CONNECTOR_PLUGIN "${AI_CONNECTOR_FIXED}"
    echo "  AI client:        ${AI_PROVIDER_FIXED}"
    echo "  Connector plugin: ${AI_CONNECTOR_FIXED}"

    API_KEY_SOURCE=""
    API_KEY_PROMPTED="no"
    if ! is_placeholder_key "${GOOGLE_API_KEY:-}"; then
        # A real key exported in the shell wins; persist it for later runs.
        set_env_var GOOGLE_API_KEY "${GOOGLE_API_KEY}"
        API_KEY_SOURCE="environment (saved to .env)"
    elif ! is_placeholder_key "$(get_env_var GOOGLE_API_KEY)"; then
        API_KEY_SOURCE=".env"
    else
        # Missing or placeholder: ask now rather than failing deep in the stack.
        # Prompt on stderr and read from the terminal (stdout is piped to the log).
        TTY_IN=""
        if [ -t 0 ]; then
            TTY_IN="/dev/stdin"
        elif { : </dev/tty; } 2>/dev/null; then
            TTY_IN="/dev/tty"
        fi

        if [ "${AIPS_SKIP_API_KEY_CHECK:-0}" = "1" ]; then
            API_KEY_SOURCE="skipped (AIPS_SKIP_API_KEY_CHECK=1)"
        elif [ -z "$TTY_IN" ]; then
            echo "  GOOGLE_API_KEY:   missing or placeholder (no terminal to prompt on)"
            echo -e "${RED}Error: GOOGLE_API_KEY is not set. Set a real key in .env (GOOGLE_API_KEY=...) or export it, then re-run.${NC}"
            echo "       To start anyway without a key: AIPS_SKIP_API_KEY_CHECK=1 ./start-dev.sh"
            exit 1
        else
            API_KEY_PROMPTED="yes"
            ENTERED_KEY=""
            for attempt in 1 2; do
                printf '%b' "${YELLOW}GOOGLE_API_KEY is missing or still the placeholder. Enter your Google AI API key (input hidden): ${NC}" >&2
                IFS= read -rs ENTERED_KEY < "$TTY_IN" || true
                echo "" >&2
                if ! is_placeholder_key "$ENTERED_KEY"; then
                    break
                fi
                ENTERED_KEY=""
                echo -e "${RED}That doesn't look like a real key.${NC}" >&2
            done
            if [ -z "$ENTERED_KEY" ]; then
                echo "  GOOGLE_API_KEY:   missing (prompted: yes, no valid key entered)"
                echo -e "${RED}Error: no valid GOOGLE_API_KEY provided. Aborting.${NC}"
                exit 1
            fi
            set_env_var GOOGLE_API_KEY "${ENTERED_KEY}"
            export GOOGLE_API_KEY="${ENTERED_KEY}"
            ENTERED_KEY=""
            API_KEY_SOURCE="prompt (saved to .env)"
        fi
    fi

    if [ -n "$API_KEY_SOURCE" ] && [ "${API_KEY_SOURCE#skipped}" = "$API_KEY_SOURCE" ]; then
        echo "  GOOGLE_API_KEY:   present (source: ${API_KEY_SOURCE})"
    else
        echo "  GOOGLE_API_KEY:   missing (${API_KEY_SOURCE})"
    fi
    echo "  Prompted for key: ${API_KEY_PROMPTED}"
    echo ""

    # Read back the effective ports for the summary below (docker compose
    # reads .env on its own for interpolation).
    WP_PORT="$(get_env_var WP_PORT)"
    WP_PORT="${WP_PORT:-8080}"
    PHPMYADMIN_PORT="$(get_env_var PHPMYADMIN_PORT)"
    PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8082}"
    WP_ADMIN_USER_SHOWN="$(get_env_var WP_ADMIN_USER)"
    WP_ADMIN_USER_SHOWN="${WP_ADMIN_USER_SHOWN:-admin}"
    WP_ADMIN_PASSWORD_SHOWN="$(get_env_var WP_ADMIN_PASSWORD)"
    WP_ADMIN_PASSWORD_SHOWN="${WP_ADMIN_PASSWORD_SHOWN:-admin}"

    # Build first (with retries) so a registry hiccup never leaves a
    # previously running environment stopped.
    echo -e "${GREEN}Building containers...${NC}"
    if ! retry $DOCKER_COMPOSE build; then
        echo -e "${RED}Build failed. If the error mentions auth.docker.io / 5xx, Docker Hub is likely having an outage (see https://www.dockerstatus.com); try again later or run 'docker login'.${NC}"
        exit 1
    fi

    # Pre-pull any service images that are not cached locally, with retries.
    # Images already on disk are skipped, so a Docker Hub outage doesn't
    # matter when everything is cached. Partially downloaded layers are kept
    # between attempts, so retries resume.
    for img in $($DOCKER_COMPOSE config --images 2>/dev/null); do
        if docker image inspect "$img" >/dev/null 2>&1; then
            continue
        fi
        echo -e "${YELLOW}Pulling ${img}...${NC}"
        if ! retry docker pull "$img"; then
            echo -e "${RED}Could not pull ${img}. Docker Hub auth errors (5xx) are usually transient: retry later, run 'docker login', or set DB_IMAGE / PMA_IMAGE in .env to an image you already have locally.${NC}"
            exit 1
        fi
    done

    echo -e "${YELLOW}Stopping existing containers...${NC}"
    $DOCKER_COMPOSE down

    echo -e "${GREEN}Starting services (waiting for health checks; first boot installs WordPress and plugins)...${NC}"
    if $DOCKER_COMPOSE up --help 2>/dev/null | grep -q -- '--wait'; then
        UP_ARGS="-d --pull never --wait --wait-timeout 300"
    else
        UP_ARGS="-d --pull never"
    fi
    if ! $DOCKER_COMPOSE up $UP_ARGS; then
        echo -e "${RED}Services failed to start or become healthy.${NC}"
        $DOCKER_COMPOSE ps
        $DOCKER_COMPOSE logs --tail 50 web db
        exit 1
    fi

    echo ""
    echo -e "${GREEN}✓ Development environment started successfully!${NC}"
    echo ""
    echo "WordPress is available at: http://localhost:${WP_PORT}"
    echo "PHPMyAdmin is available at: http://localhost:${PHPMYADMIN_PORT}"
    echo ""
    echo "Admin credentials:"
    echo "  Username: ${WP_ADMIN_USER_SHOWN}"
    echo "  Password: ${WP_ADMIN_PASSWORD_SHOWN}"
    echo ""
    echo "To view logs, run: $DOCKER_COMPOSE logs -f"
    echo "To stop the environment, run: $DOCKER_COMPOSE down  (or: make stop / make down)"
    echo ""
}

# Run main with all output (stdout + stderr) shown on screen and written to
# the log. The pipeline waits for tee, so nothing is lost on early exits.
set +e
main "$@" 2>&1 | tee "$LOG_FILE"
RC=${PIPESTATUS[0]}
set -e

# Strip ANSI color codes from the saved log and record the result.
sed -i 's/\x1b\[[0-9;]*[A-Za-z]//g' "$LOG_FILE" 2>/dev/null || true
printf 'Finished: %s (exit code %s)\n' "$(date '+%Y-%m-%d %H:%M:%S %z')" "$RC" >> "$LOG_FILE"

echo "Full output saved to: ${LOG_FILE}"
exit "$RC"
