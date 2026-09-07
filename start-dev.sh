#!/bin/bash
# Start development environment script for Unix/Linux/Mac
# This script builds and starts the Docker development environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting WP AI Scheduler Development Environment${NC}"
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Error: Docker is not running. Please start Docker and try again.${NC}"
    exit 1
fi

# Use 'docker compose' (Compose V2, built into Docker CLI)
# This is the modern, recommended approach
if command -v docker compose >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
else
    # Fall back to legacy docker-compose if docker compose not available
    DOCKER_COMPOSE="docker-compose"
fi

# Check if we're in the correct directory (should have docker-compose.yml)
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}Error: docker-compose.yml not found. Please run this script from the repository root.${NC}"
    exit 1
fi

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

# Check for .env file and create from .env.example if missing
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
        cp .env.example .env
        echo -e "${GREEN}✓ .env created. You can customize your AI Connector API key in .env${NC}"
    else
        touch .env
    fi
fi

# ------------------------------------------------------------------
# Per-instance container names & host ports
# ------------------------------------------------------------------
# Container names and host ports are hard-coded no more: each checkout /
# git worktree provisions a unique instance id, container names, and a
# non-conflicting set of host ports (persisted in .env). This lets several
# copies of this environment run at the same time instead of colliding on
# shared names/ports. Delete the auto-generated block in .env to regenerate.

# Return success if the given TCP port on 127.0.0.1 has no listener.
# If /dev/tcp is unsupported the connect errors out and the port is treated
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

# Upsert KEY=VALUE in .env: replace an existing line, otherwise append.
set_env_var() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env 2>/dev/null; then
        sed "s|^${key}=.*|${key}=${value}|" .env > .env.tmp && mv .env.tmp .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

if ! grep -q "^AIPS_INSTANCE_ID=" .env 2>/dev/null; then
    echo -e "${YELLOW}Provisioning a unique instance (names + ports) in .env...${NC}"

    INSTANCE_ID="$(basename "$PWD" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9')"
    INSTANCE_ID="$(printf '%s' "$INSTANCE_ID" | tail -c 12)-$(printf '%04x' $((RANDOM % 65536)))"

    WP_PORT_PICK="$(find_free_port 8080)"
    PHPMYADMIN_PORT_PICK="$(find_free_port 8082)"
    MYSQL_PORT_PICK="$(find_free_port 3307)"
    XDEBUG_PORT_PICK="$(find_free_port 9003)"

    set_env_var AIPS_INSTANCE_ID  "${INSTANCE_ID}"
    set_env_var AIPS_WEB_CONTAINER "wp-ai-scheduler-web-${INSTANCE_ID}"
    set_env_var AIPS_DB_CONTAINER  "wp-ai-scheduler-db-${INSTANCE_ID}"
    set_env_var AIPS_PMA_CONTAINER "wp-ai-scheduler-phpmyadmin-${INSTANCE_ID}"
    set_env_var WP_PORT         "${WP_PORT_PICK}"
    set_env_var PHPMYADMIN_PORT "${PHPMYADMIN_PORT_PICK}"
    set_env_var MYSQL_PORT      "${MYSQL_PORT_PICK}"
    set_env_var XDEBUG_PORT     "${XDEBUG_PORT_PICK}"

    echo -e "${GREEN}✓ Instance '${INSTANCE_ID}' → WP:${WP_PORT_PICK} phpMyAdmin:${PHPMYADMIN_PORT_PICK} MySQL:${MYSQL_PORT_PICK} Xdebug:${XDEBUG_PORT_PICK}${NC}"
fi

# Read back the effective ports for the summary below (docker compose reads
# .env on its own for interpolation). Piping through cut keeps `set -e` happy
# even when a key is absent.
WP_PORT="$(grep '^WP_PORT=' .env | tail -n1 | cut -d= -f2)"
WP_PORT="${WP_PORT:-8080}"
PHPMYADMIN_PORT="$(grep '^PHPMYADMIN_PORT=' .env | tail -n1 | cut -d= -f2)"
PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8082}"
echo ""


# Stop any existing containers
echo -e "${YELLOW}Stopping existing containers...${NC}"
$DOCKER_COMPOSE down

# Build and start containers
echo -e "${GREEN}Building and starting containers...${NC}"
$DOCKER_COMPOSE build

echo -e "${GREEN}Starting services...${NC}"
$DOCKER_COMPOSE up -d

echo ""
echo -e "${GREEN}✓ Development environment started successfully!${NC}"
echo ""
echo "WordPress is available at: http://localhost:${WP_PORT}"
echo "PHPMyAdmin is available at: http://localhost:${PHPMYADMIN_PORT}"
echo ""
echo "Admin credentials:"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo "To view logs, run: $DOCKER_COMPOSE logs -f"
echo "To stop the environment, run: $DOCKER_COMPOSE down"
echo ""