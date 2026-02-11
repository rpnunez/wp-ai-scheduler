@echo off
REM Quick start script for Windows users
REM Double-click this file to start the development environment

echo.
echo ========================================================
echo   AI Post Scheduler - Docker Development Environment
echo   Quick Start for Windows
echo ========================================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not installed or not in PATH
    echo.
    echo Please install Docker Desktop from:
    echo https://www.docker.com/products/docker-desktop
    echo.
    pause
    exit /b 1
)

echo [OK] Docker is installed
echo.

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Docker is not running
    echo.
    echo Please start Docker Desktop and try again.
    echo.
    pause
    exit /b 1
)

echo [OK] Docker is running
echo.

REM Create .env if it doesn't exist
if not exist .env (
    echo Creating .env file from .env.example...
    copy .env.example .env >nul
    echo [OK] .env file created
) else (
    echo [OK] .env file already exists
)
echo.

REM Check for existing volumes
docker volume ls | findstr "wp-ai-scheduler" >nul 2>&1
if %errorlevel% equ 0 (
    echo.
    echo [INFO] Existing Docker volumes found.
    echo.
    set /p FRESH="Do you want to start fresh? This will DELETE all existing WordPress data. (y/N): "
    if /i "%FRESH%"=="y" (
        echo.
        echo Removing existing volumes...
        docker-compose down -v
        echo [OK] Volumes removed
    ) else (
        echo.
        echo [INFO] Using existing volumes (data preserved)
    )
)

echo.
echo Building and starting Docker containers...
echo This may take a few minutes on the first run...
echo.

docker-compose up -d --build

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Failed to start containers
    echo.
    echo Please check the error messages above.
    echo You can also try running: docker-compose logs
    echo.
    pause
    exit /b 1
)

echo.
echo Waiting for services to be ready...
echo.

REM Wait for web service to be ready
echo Waiting for WordPress (this may take 30-60 seconds)...
:WAIT_LOOP
timeout /t 5 /nobreak >nul
curl -f -s -o nul http://localhost:8080
if %errorlevel% neq 0 (
    echo Still waiting...
    goto WAIT_LOOP
)

echo.
echo ========================================================
echo   Development Environment Ready!
echo ========================================================
echo.
echo WordPress Site:   http://localhost:8080
echo Admin Panel:      http://localhost:8080/wp-admin
echo                   Username: admin
echo                   Password: admin
echo.
echo phpMyAdmin:       http://localhost:8082
echo                   Server:   db
echo                   Username: wordpress
echo                   Password: wordpress
echo.
echo ========================================================
echo   Important Notes
echo ========================================================
echo.
echo 1. Edit files in .\ai-post-scheduler - changes are live!
echo 2. Press F5 in VS Code to start debugging with Xdebug
echo 3. You need to install 'AI Engine' by Meow Apps from WordPress admin
echo.
echo ========================================================
echo   Useful Commands
echo ========================================================
echo.
echo View logs:         docker-compose logs -f
echo Stop services:     docker-compose down
echo Restart:           docker-compose restart
echo Enter shell:       docker-compose exec web bash
echo.
echo For more commands, see DOCKER_QUICKREF.md
echo.
echo ========================================================

REM Ask if user wants to open in browser
set /p OPEN="Would you like to open WordPress in your browser? (Y/n): "
if /i not "%OPEN%"=="n" (
    start http://localhost:8080
)

REM Ask if user wants to open in VS Code
where code >nul 2>&1
if %errorlevel% equ 0 (
    set /p VSCODE="Would you like to open the project in VS Code? (Y/n): "
    if /i not "%VSCODE%"=="n" (
        code .
        echo.
        echo Opening VS Code... Press F5 to start debugging!
    )
)

echo.
echo Happy coding!
echo.
pause
