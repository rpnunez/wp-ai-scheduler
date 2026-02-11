#!/usr/bin/env pwsh
# Start development environment script for Windows PowerShell
# This script builds and starts the Docker development environment

Write-Host "Starting WP AI Scheduler Development Environment" -ForegroundColor Green
Write-Host ""

# Check if Docker is running
try {
    docker info | Out-Null
} catch {
    Write-Host "Error: Docker is not running. Please start Docker Desktop and try again." -ForegroundColor Red
    exit 1
}

# Check if we're in the correct directory
if (-not (Test-Path "docker-compose.yml")) {
    Write-Host "Error: docker-compose.yml not found. Please run this script from the repository root." -ForegroundColor Red
    exit 1
}

# Check if required files exist
if (-not (Test-Path "healthcheck.sh")) {
    Write-Host "Error: healthcheck.sh not found in current directory." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "docker-entrypoint.sh")) {
    Write-Host "Error: docker-entrypoint.sh not found in current directory." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "ai-post-scheduler" -PathType Container)) {
    Write-Host "Error: ai-post-scheduler directory not found." -ForegroundColor Red
    exit 1
}

Write-Host "[OK] All required files found" -ForegroundColor Green
Write-Host ""

# Check for docker-compose or docker compose
try {
    docker-compose --version | Out-Null
    $dockerCompose = "docker-compose"
    Write-Host "Using docker-compose (V1)" -ForegroundColor Yellow
} catch {
    Write-Host "Using Docker Compose V2 (docker compose)" -ForegroundColor Yellow
    $dockerCompose = "docker compose"
}

# Stop any existing containers
Write-Host "Stopping existing containers..." -ForegroundColor Yellow
& $dockerCompose down

# Build and start containers
Write-Host "Building and starting containers..." -ForegroundColor Green
& $dockerCompose build

Write-Host "Starting services..." -ForegroundColor Green
& $dockerCompose up -d

Write-Host ""
Write-Host "[OK] Development environment started successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "WordPress is available at: http://localhost:8080"
Write-Host "PHPMyAdmin is available at: http://localhost:8082"
Write-Host ""
Write-Host "Admin credentials:"
Write-Host "  Username: admin"
Write-Host "  Password: admin"
Write-Host ""
Write-Host "To view logs, run: $dockerCompose logs -f"
Write-Host "To stop the environment, run: $dockerCompose down"
Write-Host ""
