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
#
# Seeding existing data: --import-sql <file.sql|file.sql.gz> imports a dump into
# the database, but only when the database has no tables yet (so normal reruns
# never touch your data). --force-import backs the current database up to
# .artifacts/ first, then replaces it. AIPS_IMPORT_SQL in .env sets a default
# dump; the flag overrides it.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

AI_PROVIDER_FIXED="WP_AI_CLIENT"
AI_CONNECTOR_FIXED="ai-provider-for-google"

usage() {
    cat <<'USAGE'
Usage: ./start-dev.sh [--import-sql <file.sql|file.sql.gz>] [--force-import]

  --import-sql FILE   Import a SQL dump into the Docker database. Only runs when
                      the database has no tables; otherwise it is skipped and logged.
                      Default can be set with AIPS_IMPORT_SQL in .env.
  --force-import      With --import-sql: back up the current database to .artifacts/
                      and replace it even if it already has data.
  -h, --help          Show this help.
USAGE
}

IMPORT_SQL=""
FORCE_IMPORT=0
while [ $# -gt 0 ]; do
    case "$1" in
        --import-sql)
            if [ -z "${2:-}" ]; then
                echo -e "${RED}Error: --import-sql requires a file path.${NC}" >&2
                exit 1
            fi
            IMPORT_SQL="$2"
            shift 2
            ;;
        --import-sql=*)
            IMPORT_SQL="${1#--import-sql=}"
            shift
            ;;
        --force-import)
            FORCE_IMPORT=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo -e "${RED}Error: unknown argument: $1${NC}" >&2
            usage >&2
            exit 1
            ;;
    esac
done

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

# Default dump from .env when no --import-sql flag was given.
if [ -z "$IMPORT_SQL" ]; then
    IMPORT_SQL="$(get_env_var AIPS_IMPORT_SQL)"
fi

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

    # --------------------------------------------------------------
    # SQL import request: validate up front, before any build/pull
    # --------------------------------------------------------------
    IMPORTED_SQL=0
    if [ -n "$IMPORT_SQL" ]; then
        # Accept Windows-style paths (C:\... or C:/...) under Git Bash.
        if command -v cygpath >/dev/null 2>&1 && [[ "$IMPORT_SQL" =~ ^[A-Za-z]: ]]; then
            IMPORT_SQL="$(cygpath -u "$IMPORT_SQL")"
        fi
        if [ ! -f "$IMPORT_SQL" ]; then
            echo -e "${RED}Error: SQL dump not found: ${IMPORT_SQL}${NC}"
            exit 1
        fi
        case "$IMPORT_SQL" in
            *.sql|*.sql.gz|*.gz) ;;
            *) echo -e "${YELLOW}Warning: ${IMPORT_SQL} does not end in .sql or .sql.gz; treating it as plain SQL.${NC}" ;;
        esac
        echo "SQL import"
        echo "  Dump file:        ${IMPORT_SQL}"
        echo "  Force import:     $([ "$FORCE_IMPORT" = "1" ] && echo yes || echo no)"
        echo ""
    elif [ "$FORCE_IMPORT" = "1" ]; then
        echo -e "${YELLOW}Warning: --force-import has no effect without --import-sql.${NC}"
    fi

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

    if $DOCKER_COMPOSE up --help 2>/dev/null | grep -q -- '--wait'; then
        UP_ARGS="-d --pull never --wait --wait-timeout 300"
    else
        UP_ARGS="-d --pull never"
    fi

    # --------------------------------------------------------------
    # Optional SQL import (database only, before WordPress boots)
    # --------------------------------------------------------------
    if [ -n "$IMPORT_SQL" ]; then
        DB_NAME_IMPORT="$(get_env_var MYSQL_DATABASE)"
        DB_NAME_IMPORT="${DB_NAME_IMPORT:-wordpress}"

        # Run a SQL statement as root inside the db container (statement is
        # passed as a positional arg, so no quoting issues). Prints rows only.
        db_sql() {
            $DOCKER_COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb --skip-ssl -uroot -N -e "$1"' _ "$1" | tr -d '\r'
        }

        # Stream the dump (decompressed if needed) with MySQL-8-only collations
        # mapped to ones MariaDB understands.
        stream_dump() {
            case "$IMPORT_SQL" in
                *.gz) gunzip -c "$IMPORT_SQL" ;;
                *) cat "$IMPORT_SQL" ;;
            esac | LC_ALL=C sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_520_ci/g' -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_520_ci/g'
        }

        echo -e "${GREEN}Starting the database for import...${NC}"
        if ! $DOCKER_COMPOSE up $UP_ARGS db; then
            echo -e "${RED}Database failed to start.${NC}"
            $DOCKER_COMPOSE logs --tail 50 db
            exit 1
        fi

        TABLE_COUNT="$(db_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME_IMPORT}'")"
        TABLE_COUNT="${TABLE_COUNT:-0}"
        DO_IMPORT=0
        if [ "$TABLE_COUNT" = "0" ]; then
            echo "Database '${DB_NAME_IMPORT}' is empty; importing."
            DO_IMPORT=1
        elif [ "$FORCE_IMPORT" = "1" ]; then
            BACKUP_FILE=".artifacts/db-backup-${INSTANCE_ID}-${RUN_ID}.sql"
            echo -e "${YELLOW}Database has ${TABLE_COUNT} tables; --force-import given. Backing up to ${BACKUP_FILE}...${NC}"
            $DOCKER_COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb-dump --skip-ssl -uroot "$MYSQL_DATABASE"' > "$BACKUP_FILE"
            echo "Backup written ($(wc -c < "$BACKUP_FILE" | tr -d ' ') bytes). Recreating database '${DB_NAME_IMPORT}'."
            db_sql "DROP DATABASE \`${DB_NAME_IMPORT}\`; CREATE DATABASE \`${DB_NAME_IMPORT}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            DO_IMPORT=1
        else
            echo -e "${YELLOW}Skipping SQL import: database '${DB_NAME_IMPORT}' already has ${TABLE_COUNT} tables. Use --force-import to back it up and replace it.${NC}"
        fi

        if [ "$DO_IMPORT" = "1" ]; then
            # Detect the dump's table prefix (from <prefix>postmeta) so wp-config.php matches.
            DUMP_PREFIX="$(stream_dump | grep -m1 -oE 'CREATE TABLE `[A-Za-z0-9_]+postmeta`' | sed -e 's/^CREATE TABLE `//' -e 's/postmeta`$//' || true)"
            echo "Importing ${IMPORT_SQL} (this can take a while for large dumps)..."
            IMPORT_START="$(date +%s)"
            stream_dump | $DOCKER_COMPOSE exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mariadb --skip-ssl -uroot "$MYSQL_DATABASE"'
            NEW_COUNT="$(db_sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME_IMPORT}'")"
            if [ "${NEW_COUNT:-0}" = "0" ]; then
                echo -e "${RED}Import finished but the database has no tables; the dump may be empty or invalid.${NC}"
                exit 1
            fi
            echo -e "${GREEN}✓ Imported ${NEW_COUNT} tables in $(( $(date +%s) - IMPORT_START ))s.${NC}"
            if [ -n "$DUMP_PREFIX" ]; then
                echo "  Table prefix:     ${DUMP_PREFIX}"
                set_env_var WP_TABLE_PREFIX "${DUMP_PREFIX}"
            else
                echo -e "${YELLOW}  Could not detect the table prefix from the dump; leaving WP_TABLE_PREFIX unchanged.${NC}"
            fi
            IMPORTED_SQL=1
        fi
        echo ""
    fi

    echo -e "${GREEN}Starting services (waiting for health checks; first boot installs WordPress and plugins)...${NC}"
    if ! $DOCKER_COMPOSE up $UP_ARGS; then
        echo -e "${RED}Services failed to start or become healthy.${NC}"
        $DOCKER_COMPOSE ps
        $DOCKER_COMPOSE logs --tail 50 web db
        exit 1
    fi

    # After an import, point the site at this instance's local URL. The old URL
    # is whatever the imported database stores; search-replace is serialization-safe.
    if [ "$IMPORTED_SQL" = "1" ]; then
        WP_CLI_WEB="$DOCKER_COMPOSE exec -T web wp --path=/var/www/html --allow-root --skip-plugins --skip-themes"
        NEW_URL="http://localhost:${WP_PORT}"
        OLD_URL="$($WP_CLI_WEB option get siteurl | tr -d '\r')"
        if [ -n "$OLD_URL" ] && [ "$OLD_URL" != "$NEW_URL" ]; then
            echo "Rewriting URLs in the imported database: ${OLD_URL} -> ${NEW_URL}"
            $WP_CLI_WEB search-replace "$OLD_URL" "$NEW_URL" --all-tables --skip-columns=guid --report-changed-only
            $WP_CLI_WEB option update home "$NEW_URL"
            $WP_CLI_WEB option update siteurl "$NEW_URL"
        else
            echo "Imported site URL already matches ${NEW_URL}."
        fi
        echo ""
    fi

    echo ""
    echo -e "${GREEN}✓ Development environment started successfully!${NC}"
    echo ""
    echo "WordPress is available at: http://localhost:${WP_PORT}"
    echo "PHPMyAdmin is available at: http://localhost:${PHPMYADMIN_PORT}"
    echo ""
    if [ "$IMPORTED_SQL" = "1" ]; then
        echo "Admin credentials: taken from the imported database (not .env)."
    else
        echo "Admin credentials:"
        echo "  Username: ${WP_ADMIN_USER_SHOWN}"
        echo "  Password: ${WP_ADMIN_PASSWORD_SHOWN}"
    fi
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
