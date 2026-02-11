@echo off
REM Start development environment script for Windows CMD
REM This script builds and starts the Docker development environment

echo Starting WP AI Scheduler Development Environment
echo.

REM Check if Docker is running
docker info >nul 2>&1
if errorlevel 1 (
    echo Error: Docker is not running. Please start Docker Desktop and try again.
    exit /b 1
)

REM Check if we're in the correct directory
if not exist "docker-compose.yml" (
    echo Error: docker-compose.yml not found. Please run this script from the repository root.
    exit /b 1
)

REM Check if required files exist
if not exist "healthcheck.sh" (
    echo Error: healthcheck.sh not found in current directory.
    exit /b 1
)

if not exist "docker-entrypoint.sh" (
    echo Error: docker-entrypoint.sh not found in current directory.
    exit /b 1
)

if not exist "ai-post-scheduler" (
    echo Error: ai-post-scheduler directory not found.
    exit /b 1
)

echo [OK] All required files found
echo.

REM Check for docker-compose or docker compose
docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo Using Docker Compose V2
    set DOCKER_COMPOSE=docker compose
) else (
    set DOCKER_COMPOSE=docker-compose
)

REM Stop any existing containers
echo Stopping existing containers...
%DOCKER_COMPOSE% down

REM Build and start containers
echo Building and starting containers...
%DOCKER_COMPOSE% build

echo Starting services...
%DOCKER_COMPOSE% up -d

echo.
echo [OK] Development environment started successfully!
echo.
echo WordPress is available at: http://localhost:8080
echo PHPMyAdmin is available at: http://localhost:8082
echo.
echo Admin credentials:
echo   Username: admin
echo   Password: admin
echo.
echo To view logs, run: %DOCKER_COMPOSE% logs -f
echo To stop the environment, run: %DOCKER_COMPOSE% down
echo.
