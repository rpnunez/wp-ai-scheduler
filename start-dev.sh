#!/bin/bash
# Quick start script for AI Post Scheduler Docker development environment
# This script makes it easy to get started with development

set -e

# Colors
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║   AI Post Scheduler - Docker Development Environment     ║"
echo "║   Quick Start Script                                      ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed.${NC}"
    echo "Please install Docker Desktop from: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo -e "${RED}Error: Docker is not running.${NC}"
    echo "Please start Docker Desktop and try again."
    exit 1
fi

echo -e "${GREEN}✓ Docker is installed and running${NC}"
echo ""

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}Error: docker-compose is not installed.${NC}"
    echo "Please install docker-compose or use Docker Desktop which includes it."
    exit 1
fi

echo -e "${GREEN}✓ docker-compose is available${NC}"
echo ""

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi
echo ""

# Ask user if they want to start fresh or use existing data
if docker volume ls | grep -q "wp-ai-scheduler"; then
    echo -e "${YELLOW}Existing Docker volumes found.${NC}"
    read -p "Do you want to start fresh? This will DELETE all existing WordPress data. (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Removing existing volumes...${NC}"
        docker-compose down -v
        echo -e "${GREEN}✓ Volumes removed${NC}"
    else
        echo -e "${BLUE}Using existing volumes (data preserved)${NC}"
    fi
fi
echo ""

# Build and start services
echo -e "${BLUE}Building and starting Docker containers...${NC}"
echo "This may take a few minutes on the first run..."
echo ""

docker-compose up -d --build

# Wait for services to be healthy
echo ""
echo -e "${BLUE}Waiting for services to be ready...${NC}"

# Wait for database to be healthy
echo -n "Waiting for database..."
for i in {1..60}; do
    if docker-compose ps | grep "wp-ai-scheduler-db" | grep -q "healthy"; then
        echo -e " ${GREEN}✓${NC}"
        break
    fi
    echo -n "."
    sleep 2
done

# Wait for WordPress to be healthy
echo -n "Waiting for WordPress..."
for i in {1..90}; do
    if curl -f -s -o /dev/null http://localhost:8080; then
        echo -e " ${GREEN}✓${NC}"
        break
    fi
    echo -n "."
    sleep 2
done

echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           🚀 Development Environment Ready! 🚀            ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}WordPress Site:${NC}   http://localhost:8080"
echo -e "${BLUE}Admin Panel:${NC}      http://localhost:8080/wp-admin"
echo -e "                      Username: ${GREEN}admin${NC}"
echo -e "                      Password: ${GREEN}admin${NC}"
echo ""
echo -e "${BLUE}phpMyAdmin:${NC}       http://localhost:8082"
echo -e "                      Server:   ${GREEN}db${NC}"
echo -e "                      Username: ${GREEN}wordpress${NC}"
echo -e "                      Password: ${GREEN}wordpress${NC}"
echo ""
echo -e "${YELLOW}Important Notes:${NC}"
echo "  1. Edit files in ./ai-post-scheduler - changes are live!"
echo "  2. Press F5 in VS Code to start debugging with Xdebug"
echo "  3. You need to install 'AI Engine' by Meow Apps from WordPress admin"
echo ""
echo -e "${BLUE}Useful Commands:${NC}"
echo "  ${GREEN}make logs${NC}        - View live logs"
echo "  ${GREEN}make shell${NC}       - Enter WordPress container"
echo "  ${GREEN}make db-shell${NC}    - Enter MySQL shell"
echo "  ${GREEN}make restart${NC}     - Restart all services"
echo "  ${GREEN}make down${NC}        - Stop services"
echo "  ${GREEN}make help${NC}        - Show all available commands"
echo ""
echo -e "${BLUE}Documentation:${NC}"
echo "  Read ${GREEN}DOCKER_DEV_README.md${NC} for comprehensive documentation"
echo ""

# Check if VS Code is available and offer to open the project
if command -v code &> /dev/null; then
    read -p "Would you like to open the project in VS Code? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        code .
        echo -e "${GREEN}Opening VS Code...${NC}"
        echo "Press F5 in VS Code to start debugging!"
    fi
fi

echo ""
echo -e "${GREEN}Happy coding! 🎉${NC}"
