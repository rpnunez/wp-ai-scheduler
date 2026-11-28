# shellcheck shell=bash
# Per-checkout dev-stack identity: container names + host ports in .env.
#
# Sourced (not executed) by start-dev.sh and scripts/run-docker-test.sh so
# that every entry point (`./start-dev.sh`, `make test`, `make test-session`)
# gives a checkout its own containers and ports. Without this, a fresh
# worktree that runs `make test` first falls back to the shared default names
# (wp-ai-scheduler-web / -db) and default ports, and collides with whatever
# other checkout used them last.
#
# Must be sourced from the repository root (where .env and docker-compose.yml
# live). Callers may set before sourcing / calling:
#   RANDOM_CONTAINERS=1  or AIPS_RANDOMIZE_CONTAINER=1  force a new instance id
#   RANDOM_PORTS=1       or AIPS_RANDOMIZE_PORTS=1      force new random ports
#
# Functions set these globals:
#   aips_ensure_env_file  ENV_CREATED (0/1)
#   aips_ensure_instance  INSTANCE_ID, INSTANCE_CHANGED (0/1)
#   aips_ensure_ports     WP_PORT_PICK, PHPMYADMIN_PORT_PICK, MYSQL_PORT_PICK, XDEBUG_PORT_PICK

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

# Create .env from .env.example when missing.
aips_ensure_env_file() {
    ENV_CREATED=0
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env
        else
            touch .env
        fi
        ENV_CREATED=1
    fi
}

# Normalize a directory path for comparison: Docker on Windows labels
# containers with "C:\Projects\...", while Git Bash's $PWD is "/c/Projects/...".
_aips_norm_dir() {
    local p="$1"
    if command -v cygpath >/dev/null 2>&1; then
        p="$(cygpath -m "$p" 2>/dev/null || printf '%s' "$p")"
    fi
    printf '%s' "$p" | tr '\\' '/' | sed -e 's|/*$||' | tr '[:upper:]' '[:lower:]'
}

_aips_generate_unique_instance_id() {
    local slug="$1"
    local candidate
    local attempt=0
    while [ "$attempt" -lt 100 ]; do
        local rand
        rand="$(printf '%04x' $((RANDOM % 65536)))"
        candidate="${slug}-${rand}"
        if ! docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qE "^wp-ai-scheduler-(web|db|phpmyadmin)-${candidate}$"; then
            echo "$candidate"
            return 0
        fi
        attempt=$((attempt + 1))
    done
    echo "${slug}-$(date +%s | tail -c 4)"
}

# Give this checkout its own instance id and container names unless .env
# already holds valid ones for THIS directory. Detects a .env copied from
# another worktree (slug mismatch) and names held by another compose project.
aips_ensure_instance() {
    local dir_slug cur_instance_id cur_web_container need_new=0
    dir_slug="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9' | tail -c 12)"
    dir_slug="${dir_slug:-dev}"

    cur_instance_id="$(get_env_var AIPS_INSTANCE_ID)"
    cur_web_container="$(get_env_var AIPS_WEB_CONTAINER)"

    if [ -z "$cur_instance_id" ] || [ "${RANDOM_CONTAINERS:-0}" = "1" ] || [ "${AIPS_RANDOMIZE_CONTAINER:-0}" = "1" ]; then
        need_new=1
    elif [ -z "$cur_web_container" ] || [ "$cur_web_container" = "wp-ai-scheduler-web" ]; then
        need_new=1
    else
        # .env copied from another worktree: the instance id carries its slug.
        case "$cur_instance_id" in
            "${dir_slug}-"*) ;;
            *) need_new=1 ;;
        esac

        # The container name is held by a container from another directory/project.
        if [ "$need_new" = "0" ] && docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${cur_web_container}$"; then
            local current_project existing_project existing_dir
            current_project="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')"
            existing_project="$(docker inspect -f '{{ index .Config.Labels "com.docker.compose.project" }}' "$cur_web_container" 2>/dev/null || true)"
            if [ -n "$existing_project" ] && [ "$existing_project" != "$current_project" ]; then
                need_new=1
            else
                existing_dir="$(docker inspect -f '{{ index .Config.Labels "com.docker.compose.project.working_dir" }}' "$cur_web_container" 2>/dev/null || true)"
                if [ -n "$existing_dir" ] && [ "$(_aips_norm_dir "$existing_dir")" != "$(_aips_norm_dir "$PWD")" ]; then
                    need_new=1
                fi
            fi
        fi
    fi

    INSTANCE_CHANGED=0
    if [ "$need_new" = "1" ]; then
        INSTANCE_ID="$(_aips_generate_unique_instance_id "$dir_slug")"
        set_env_var AIPS_INSTANCE_ID   "${INSTANCE_ID}"
        set_env_var AIPS_WEB_CONTAINER "wp-ai-scheduler-web-${INSTANCE_ID}"
        set_env_var AIPS_DB_CONTAINER  "wp-ai-scheduler-db-${INSTANCE_ID}"
        set_env_var AIPS_PMA_CONTAINER "wp-ai-scheduler-phpmyadmin-${INSTANCE_ID}"
        INSTANCE_CHANGED=1
    else
        INSTANCE_ID="$cur_instance_id"
    fi
    INSTANCE_ID="${INSTANCE_ID:-default}"
}

# Keep the ports in .env when they are free, otherwise pick random free ones,
# and write them back to .env. Pass "fresh" for a new instance: its .env ports
# came from .env.example or another checkout, so they are always re-picked
# (keeping them would let two worktrees share ports whenever one is stopped).
# Call only when this instance's own containers are stopped (their published
# ports would otherwise count as "in use"). Non-zero when no port is free.
aips_ensure_ports() {
    local -a allocated=()
    local docker_published py_bin="" timeout_bin="" force_random=0
    [ "${1:-}" = "fresh" ] && force_random=1

    # Probes are chosen so a missing/broken tool can never make every port look
    # "in use" (e.g. the Windows Store python3 stub exits 49 on every call).
    docker_published="$(docker ps --format '{{.Ports}}' 2>/dev/null || true)"
    local candidate_py
    for candidate_py in python3 python; do
        if command -v "$candidate_py" >/dev/null 2>&1 && "$candidate_py" -c 'import socket' >/dev/null 2>&1; then
            py_bin="$candidate_py"
            break
        fi
    done
    # Bound the /dev/tcp connect probe: it can take seconds per port on Windows.
    command -v timeout >/dev/null 2>&1 && timeout_bin="timeout 1"

    _aips_port_is_free() {
        local port="$1" ap
        [ -z "$port" ] && return 1
        for ap in ${allocated[@]+"${allocated[@]}"}; do
            [ "$ap" -eq "$port" ] && return 1
        done
        # Docker-published port (also matches ranges such as 8100-8102->80)
        if printf '%s\n' "$docker_published" | grep -qE "[:-]${port}(-[0-9]+)?->"; then
            return 1
        fi
        # Something already accepts connections on localhost (only exit 0 counts).
        if $timeout_bin bash -c "exec 3<>/dev/tcp/127.0.0.1/$port" 2>/dev/null; then
            return 1
        fi
        # Socket bind check. Only the script's explicit exit 99 means "in use".
        if [ -n "$py_bin" ]; then
            local rc=0
            "$py_bin" -c "import socket, sys
s = socket.socket()
try:
    s.bind(('', int(sys.argv[1])))
except OSError:
    sys.exit(99)
finally:
    s.close()" "$port" >/dev/null 2>&1 || rc=$?
            [ "$rc" -eq 99 ] && return 1
        fi
        return 0
    }

    # Result via PICKED_PORT (not stdout) so `allocated` persists in this shell.
    _aips_pick_port() {
        local current="$1" min="$2" max="$3" span candidate attempt=0
        PICKED_PORT=""
        if [ "$force_random" != "1" ] && [ "${RANDOM_PORTS:-0}" != "1" ] && [ "${AIPS_RANDOMIZE_PORTS:-0}" != "1" ] && [ -n "$current" ] && _aips_port_is_free "$current"; then
            allocated+=("$current")
            PICKED_PORT="$current"
            return 0
        fi
        span=$(( max - min + 1 ))
        while [ "$attempt" -lt 100 ]; do
            candidate=$(( min + (RANDOM % span) ))
            if _aips_port_is_free "$candidate"; then
                allocated+=("$candidate")
                PICKED_PORT="$candidate"
                return 0
            fi
            attempt=$((attempt + 1))
        done
        candidate="$min"
        while [ "$candidate" -le "$max" ]; do
            if _aips_port_is_free "$candidate"; then
                allocated+=("$candidate")
                PICKED_PORT="$candidate"
                return 0
            fi
            candidate=$((candidate + 1))
        done
        echo "Error: Could not find a free port in range ${min}-${max}" >&2
        return 1
    }

    _aips_pick_port "$(get_env_var WP_PORT)" 8100 8899         || return 1; WP_PORT_PICK="$PICKED_PORT"
    _aips_pick_port "$(get_env_var PHPMYADMIN_PORT)" 8900 9699 || return 1; PHPMYADMIN_PORT_PICK="$PICKED_PORT"
    _aips_pick_port "$(get_env_var MYSQL_PORT)" 3310 3999      || return 1; MYSQL_PORT_PICK="$PICKED_PORT"
    _aips_pick_port "$(get_env_var XDEBUG_PORT)" 9700 9999     || return 1; XDEBUG_PORT_PICK="$PICKED_PORT"

    local var_name val
    for var_name in WP_PORT_PICK PHPMYADMIN_PORT_PICK MYSQL_PORT_PICK XDEBUG_PORT_PICK; do
        val="${!var_name}"
        if ! [[ "$val" =~ ^[0-9]+$ ]] || [ "$val" -le 0 ] || [ "$val" -gt 65535 ]; then
            echo "Error: Invalid port allocated for ${var_name}: '${val}'." >&2
            return 1
        fi
    done

    set_env_var WP_PORT         "${WP_PORT_PICK}"
    set_env_var PHPMYADMIN_PORT "${PHPMYADMIN_PORT_PICK}"
    set_env_var MYSQL_PORT      "${MYSQL_PORT_PICK}"
    set_env_var XDEBUG_PORT     "${XDEBUG_PORT_PICK}"
}
