# Quick start script for Windows PowerShell users
# Right-click and select "Run with PowerShell"

# Set console colors
$Host.UI.RawUI.BackgroundColor = "Black"
$Host.UI.RawUI.ForegroundColor = "White"
Clear-Host

Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  AI Post Scheduler - Docker Development Environment  " -ForegroundColor Cyan
Write-Host "  Quick Start for Windows (PowerShell)                " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

# Check if Docker is installed
try {
    $dockerVersion = docker --version
    Write-Host "[OK] Docker is installed: $dockerVersion" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Docker is not installed or not in PATH" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please install Docker Desktop from:" -ForegroundColor Yellow
    Write-Host "https://www.docker.com/products/docker-desktop" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host ""

# Check if Docker is running
try {
    docker info | Out-Null
    Write-Host "[OK] Docker is running" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Docker is not running" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please start Docker Desktop and try again." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host ""

# Create .env if it doesn't exist
if (-not (Test-Path .env)) {
    Write-Host "Creating .env file from .env.example..." -ForegroundColor Yellow
    Copy-Item .env.example .env
    Write-Host "[OK] .env file created" -ForegroundColor Green
} else {
    Write-Host "[OK] .env file already exists" -ForegroundColor Green
}
Write-Host ""

# Check for existing volumes
$volumes = docker volume ls | Select-String "wp-ai-scheduler"
if ($volumes) {
    Write-Host "[INFO] Existing Docker volumes found." -ForegroundColor Yellow
    Write-Host ""
    $fresh = Read-Host "Do you want to start fresh? This will DELETE all existing WordPress data. (y/N)"
    if ($fresh -eq "y" -or $fresh -eq "Y") {
        Write-Host ""
        Write-Host "Removing existing volumes..." -ForegroundColor Yellow
        docker-compose down -v
        Write-Host "[OK] Volumes removed" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "[INFO] Using existing volumes (data preserved)" -ForegroundColor Cyan
    }
}
Write-Host ""

# Build and start services
Write-Host "Building and starting Docker containers..." -ForegroundColor Cyan
Write-Host "This may take a few minutes on the first run..." -ForegroundColor Yellow
Write-Host ""

docker-compose up -d --build

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "[ERROR] Failed to start containers" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please check the error messages above." -ForegroundColor Yellow
    Write-Host "You can also try running: docker-compose logs" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Waiting for services to be ready..." -ForegroundColor Cyan
Write-Host ""

# Wait for web service to be ready
Write-Host "Waiting for WordPress (this may take 30-90 seconds)..." -ForegroundColor Yellow
$maxAttempts = 60
$attempt = 0
$ready = $false

while (-not $ready -and $attempt -lt $maxAttempts) {
    try {
        $response = Invoke-WebRequest -Uri http://localhost:8080 -TimeoutSec 2 -UseBasicParsing -ErrorAction Stop
        $ready = $true
    } catch {
        Write-Host "." -NoNewline
        Start-Sleep -Seconds 2
        $attempt++
    }
}
Write-Host ""

if (-not $ready) {
    Write-Host ""
    Write-Host "[WARNING] WordPress did not respond in time" -ForegroundColor Yellow
    Write-Host "The containers are starting. You can check status with: docker-compose ps" -ForegroundColor Yellow
    Write-Host ""
} else {
    Write-Host "[OK] WordPress is ready!" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  Development Environment Ready!                       " -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "WordPress Site:   " -NoNewline
Write-Host "http://localhost:8080" -ForegroundColor Cyan
Write-Host "Admin Panel:      " -NoNewline
Write-Host "http://localhost:8080/wp-admin" -ForegroundColor Cyan
Write-Host "                  Username: " -NoNewline
Write-Host "admin" -ForegroundColor Yellow
Write-Host "                  Password: " -NoNewline
Write-Host "admin" -ForegroundColor Yellow
Write-Host ""
Write-Host "phpMyAdmin:       " -NoNewline
Write-Host "http://localhost:8082" -ForegroundColor Cyan
Write-Host "                  Server:   " -NoNewline
Write-Host "db" -ForegroundColor Yellow
Write-Host "                  Username: " -NoNewline
Write-Host "wordpress" -ForegroundColor Yellow
Write-Host "                  Password: " -NoNewline
Write-Host "wordpress" -ForegroundColor Yellow
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  Important Notes                                      " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Edit files in .\ai-post-scheduler - changes are live!"
Write-Host "2. Press F5 in VS Code to start debugging with Xdebug"
Write-Host "3. You need to install 'AI Engine' by Meow Apps from WordPress admin"
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  Useful Commands                                      " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "View logs:         " -NoNewline
Write-Host "docker-compose logs -f" -ForegroundColor Yellow
Write-Host "Stop services:     " -NoNewline
Write-Host "docker-compose down" -ForegroundColor Yellow
Write-Host "Restart:           " -NoNewline
Write-Host "docker-compose restart" -ForegroundColor Yellow
Write-Host "Enter shell:       " -NoNewline
Write-Host "docker-compose exec web bash" -ForegroundColor Yellow
Write-Host ""
Write-Host "For more commands, see " -NoNewline
Write-Host "DOCKER_QUICKREF.md" -ForegroundColor Yellow
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan

# Ask if user wants to open in browser
Write-Host ""
$openBrowser = Read-Host "Would you like to open WordPress in your browser? (Y/n)"
if ($openBrowser -ne "n" -and $openBrowser -ne "N") {
    Start-Process "http://localhost:8080"
}

# Ask if user wants to open in VS Code
if (Get-Command code -ErrorAction SilentlyContinue) {
    $openVSCode = Read-Host "Would you like to open the project in VS Code? (Y/n)"
    if ($openVSCode -ne "n" -and $openVSCode -ne "N") {
        code .
        Write-Host ""
        Write-Host "Opening VS Code... Press F5 to start debugging!" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Happy coding! " -NoNewline
Write-Host "🚀" -ForegroundColor Yellow
Write-Host ""
Read-Host "Press Enter to exit"
