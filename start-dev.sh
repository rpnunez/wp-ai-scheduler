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

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo -e "${YELLOW}docker-compose not found, trying 'docker compose' (Docker Compose V2)${NC}"
    DOCKER_COMPOSE="docker compose"
else
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
echo "WordPress is available at: http://localhost:8080"
echo "PHPMyAdmin is available at: http://localhost:8082"
echo ""
echo "Admin credentials:"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo "To view logs, run: $DOCKER_COMPOSE logs -f"
echo "To stop the environment, run: $DOCKER_COMPOSE down"
echo ""