# Docker Development Environment

This repository includes a complete Docker development environment for the WP AI Scheduler plugin.

## Quick Start

### Prerequisites

- Docker Desktop (or Docker Engine + Docker Compose)
- Git

### Starting the Development Environment

**On Unix/Linux/Mac:**
```bash
./start-dev.sh
```

**On Windows (Command Prompt):**
```cmd
start-dev.bat
```

**On Windows (PowerShell):**
```powershell
./start-dev.ps1
```

The startup script will:
1. Check that Docker is running
2. Verify all required files are present
3. Stop any existing containers
4. Build the Docker images
5. Start all services

### Accessing the Services

Once started, you can access:

- **WordPress**: http://localhost:8080
- **PHPMyAdmin**: http://localhost:8082

**Admin Credentials:**
- Username: `admin`
- Password: `admin`

### Managing the Environment

**View logs:**
```bash
docker compose logs -f
```

**Stop the environment:**
```bash
docker compose down
```

**Restart containers:**
```bash
docker compose restart
```

**Rebuild after code changes:**
```bash
docker compose build
docker compose up -d
```

## What's Included

The development environment includes:

- **WordPress 6.4** with PHP 8.2 and Apache
- **MariaDB 10.6** as the database
- **PHPMyAdmin** for database management
- **WP-CLI** for WordPress management
- **Xdebug 3.3.1** for debugging (configured for VS Code)
- **Composer** for PHP dependency management

## Plugin Development

The plugin source code is mounted as a volume, so any changes you make to the code are immediately reflected in the running WordPress instance.

The plugin is automatically:
1. Copied into the WordPress plugins directory on first startup
2. Activated after WordPress installation

## Troubleshooting

### Error: "healthcheck.sh: not found"

This error occurs when running the Docker build from the wrong directory. Make sure you:

1. Run the start script from the repository root directory (where `docker-compose.yml` is located)
2. The required files exist:
   - `healthcheck.sh`
   - `docker-entrypoint.sh`
   - `ai-post-scheduler/` directory

The startup scripts include checks to ensure you're in the correct directory.

### Error: "Docker is not running"

Start Docker Desktop and wait for it to fully initialize before running the startup script.

### Port Conflicts

If ports 8080 or 8082 are already in use, you can modify the port mappings in `docker-compose.yml`:

```yaml
services:
  web:
    ports:
      - "8080:80"  # Change 8080 to another port
```

### Database Issues

If you encounter database connection issues, try:

```bash
docker compose down -v  # Remove volumes
./start-dev.sh          # Start fresh
```

**Warning:** This will delete all data in the database.

## Development Workflow

1. Start the environment: `./start-dev.sh`
2. Make code changes in the `ai-post-scheduler/` directory
3. Changes are immediately available in WordPress
4. View logs to debug: `docker compose logs -f web`
5. Stop when done: `docker compose down`

## Additional Resources

- [WordPress Development Handbook](https://developer.wordpress.org/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [WP-CLI Documentation](https://wp-cli.org/)