# Docker Development Environment for AI Post Scheduler

This Docker setup provides a complete WordPress development environment optimized for the AI Post Scheduler plugin, with full Xdebug support, phpMyAdmin, and live code reloading.

## Features

- ✅ **WordPress 6.4** with PHP 8.2 and Apache
- ✅ **MariaDB 10.6** database with optimized configuration
- ✅ **Xdebug 3.3.1** fully configured for VS Code debugging
- ✅ **phpMyAdmin** for database management
- ✅ **WP-CLI** for WordPress command-line management
- ✅ **Composer** for PHP dependency management
- ✅ **Live code reloading** - changes to plugin files are immediately reflected
- ✅ **Named volumes** for data persistence
- ✅ **Health checks** for all services
- ✅ **Optimized** for local development with reduced logging

## Quick Start

### Prerequisites

- Docker Desktop installed ([Windows](https://docs.docker.com/desktop/install/windows-install/) | [Mac](https://docs.docker.com/desktop/install/mac-install/) | [Linux](https://docs.docker.com/desktop/install/linux-install/))
- Docker Compose (included with Docker Desktop)
- VS Code with [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) (for Xdebug support)

### 1. Start the Environment

```bash
# Build and start all services
docker-compose up -d

# View logs (optional)
docker-compose logs -f web
```

The first startup will take a few minutes as it:
- Downloads Docker images
- Installs WordPress
- Configures the database
- Activates the plugin

### 2. Access Your Development Environment

Once started, you can access:

- **WordPress Site**: http://localhost:8080
- **WordPress Admin**: http://localhost:8080/wp-admin
  - Username: `admin`
  - Password: `admin`
- **phpMyAdmin**: http://localhost:8082
  - Server: `db`
  - Username: `wordpress`
  - Password: `wordpress`

### 3. Start Debugging

1. Open the project in VS Code
2. Press `F5` or go to Run → Start Debugging
3. Select "Listen for Xdebug (Docker)"
4. Set breakpoints in your plugin code
5. Refresh your WordPress page

The debugger will pause at your breakpoints automatically!

## Configuration

### Environment Variables

Copy `.env.example` to `.env` to customize configuration:

```bash
cp .env.example .env
```

Common settings you might want to change:

```env
# WordPress admin credentials
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=your-secure-password

# Port mappings (if 8080 is already in use)
WP_PORT=8080
PHPMYADMIN_PORT=8082
MYSQL_PORT=3307
```

### Xdebug Configuration

Xdebug is pre-configured and should work out of the box. If you need to adjust settings:

**For VS Code:**
- Configuration is in `.vscode/launch.json`
- Default port: 9003
- Path mappings are already configured

**For PhpStorm:**
- Configure PHP → Servers
- Name: `localhost`
- Host: `localhost`
- Port: `8080`
- Debugger: Xdebug
- Path mappings: 
  - Project path → `/var/www/html/wp-content/plugins/ai-post-scheduler`

## Docker Commands

### Starting/Stopping

```bash
# Start services
docker-compose up -d

# Stop services (keeps data)
docker-compose stop

# Stop and remove containers (keeps data in volumes)
docker-compose down

# Stop and remove everything including volumes (DELETES ALL DATA)
docker-compose down -v
```

### Viewing Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f web
docker-compose logs -f db

# Last 100 lines
docker-compose logs --tail=100 web
```

### Rebuilding

If you modify the Dockerfile or need to rebuild:

```bash
# Rebuild and restart
docker-compose up -d --build

# Force rebuild from scratch
docker-compose build --no-cache
docker-compose up -d
```

### WordPress Management

Use WP-CLI inside the container:

```bash
# Enter the WordPress container
docker-compose exec web bash

# Run WP-CLI commands (inside container)
wp plugin list --allow-root
wp theme list --allow-root
wp user list --allow-root
wp db export /tmp/backup.sql --allow-root

# Or run commands directly
docker-compose exec web wp plugin list --allow-root
```

### Database Management

**Via phpMyAdmin:**
Access http://localhost:8082

**Via command line:**
```bash
# Enter MySQL shell
docker-compose exec db mysql -u wordpress -pwordpress wordpress

# Backup database
docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > backup.sql

# Restore database
docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < backup.sql
```

**Via external tools:**
Connect using:
- Host: `localhost`
- Port: `3307`
- User: `wordpress`
- Password: `wordpress`
- Database: `wordpress`

## Development Workflow

### Live Code Editing

The plugin directory is mounted as a volume, so changes you make locally are immediately reflected in the container:

```
./ai-post-scheduler  →  /var/www/html/wp-content/plugins/ai-post-scheduler
```

Simply edit files in your local `ai-post-scheduler` directory and refresh your browser!

### Installing Dependencies

```bash
# Enter the container
docker-compose exec web bash

# Navigate to plugin directory
cd /var/www/html/wp-content/plugins/ai-post-scheduler

# Install Composer dependencies
composer install

# Run tests
composer test
```

### Installing WordPress Plugins

**Via WP-CLI:**
```bash
docker-compose exec web wp plugin install plugin-name --activate --allow-root
```

**Via WordPress Admin:**
Navigate to http://localhost:8080/wp-admin/plugin-install.php

### Installing Meow Apps AI Engine

The AI Post Scheduler plugin requires Meow Apps AI Engine. You need to install it manually:

1. Visit http://localhost:8080/wp-admin/plugin-install.php
2. Search for "AI Engine"
3. Install and activate "AI Engine" by Meow Apps
4. Configure your AI provider (OpenAI, etc.) in Settings → AI Engine

## Xdebug Troubleshooting

### Xdebug Not Connecting

1. **Check Xdebug is enabled:**
   ```bash
   docker-compose exec web php -v | grep Xdebug
   ```

2. **View Xdebug log:**
   ```bash
   docker-compose exec web cat /tmp/xdebug.log
   ```

3. **Verify Xdebug configuration:**
   ```bash
   docker-compose exec web php -i | grep xdebug
   ```

4. **Check firewall:** Ensure port 9003 is not blocked by your firewall

5. **Windows/Mac:** Make sure Docker Desktop has access to your filesystem

### VS Code Not Stopping at Breakpoints

1. Ensure the PHP Debug extension is installed
2. Check path mappings in `.vscode/launch.json`
3. Make sure you're using "Listen for Xdebug (Docker)" configuration
4. Try adding `xdebug_break()` in your code to force a breakpoint
5. Enable logging in launch.json and check the Debug Console

### Xdebug Performance

Xdebug can slow down your site. To disable temporarily:

```bash
# Disable Xdebug
docker-compose exec web rm /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
docker-compose restart web

# Re-enable Xdebug (rebuild container)
docker-compose up -d --build web
```

## Advanced Usage

### Accessing Container Shell

```bash
# Web container (WordPress/Apache)
docker-compose exec web bash

# Database container
docker-compose exec db bash
```

### Custom PHP Configuration

PHP settings are configured in the Dockerfile. To modify:

1. Edit `Dockerfile` (look for php.ini settings)
2. Rebuild: `docker-compose up -d --build`

### Adding Other Plugins for Development

Mount additional plugin directories in `docker-compose.yml`:

```yaml
volumes:
  - ./my-other-plugin:/var/www/html/wp-content/plugins/my-other-plugin:rw
```

### Resetting Everything

To completely reset your development environment:

```bash
# Stop and remove all containers, networks, and volumes
docker-compose down -v

# Remove all WordPress files
docker volume rm wp-ai-scheduler_wordpress_data_v2 wp-ai-scheduler_db_data_v2 wp-ai-scheduler_uploads_data

# Start fresh
docker-compose up -d
```

## Project Structure

```
.
├── ai-post-scheduler/          # Plugin source (mounted as volume)
├── mariadb-conf/              # MySQL/MariaDB configuration
│   └── my.cnf                 # Custom database settings
├── .vscode/                   # VS Code configuration
│   └── launch.json           # Xdebug debugging config
├── Dockerfile                 # WordPress + Xdebug image
├── docker-compose.yml         # Service orchestration
├── docker-entrypoint.sh       # Container initialization script
├── healthcheck.sh            # Container health check
├── .dockerignore             # Files excluded from build
├── .env.example              # Environment template
└── DOCKER_DEV_README.md      # This file
```

## Data Persistence

Data is stored in Docker named volumes:

- `db_data_v2`: MySQL database files
- `wordpress_data_v2`: WordPress core files
- `uploads_data`: WordPress media uploads

These volumes persist even when containers are stopped or removed (unless you use `docker-compose down -v`).

## Performance Tips

1. **Use named volumes**: Already configured (don't use bind mounts for WordPress core)
2. **Exclude unnecessary files**: Already configured in `.dockerignore`
3. **Disable Xdebug when not needed**: See "Xdebug Performance" section
4. **Allocate more resources**: In Docker Desktop → Settings → Resources

## Common Issues

### Port Already in Use

If you see "port is already allocated":

1. Check what's using the port:
   ```bash
   # Linux/Mac
   lsof -i :8080
   
   # Windows
   netstat -ano | findstr :8080
   ```

2. Change the port in `docker-compose.yml` or `.env`:
   ```yaml
   ports:
     - "8081:80"  # Use port 8081 instead
   ```

### Permission Denied Errors

If you encounter permission issues:

```bash
# Fix ownership of plugin files
docker-compose exec web chown -R www-data:www-data /var/www/html/wp-content/plugins/ai-post-scheduler
```

### Database Connection Errors

If WordPress can't connect to the database:

1. Wait for the database to be ready (check logs: `docker-compose logs db`)
2. Verify database credentials in `docker-compose.yml`
3. Try restarting: `docker-compose restart`

### Container Won't Start

Check logs for detailed error messages:

```bash
docker-compose logs web
docker-compose logs db
```

## Getting Help

- **Docker Documentation**: https://docs.docker.com/
- **WordPress Codex**: https://codex.wordpress.org/
- **WP-CLI**: https://wp-cli.org/
- **Xdebug Documentation**: https://xdebug.org/docs/

## Clean Up

When you're done with development and want to free up disk space:

```bash
# Remove all containers and volumes for this project
docker-compose down -v

# Clean up unused Docker resources (careful - affects all Docker projects)
docker system prune -a --volumes
```

---

**Happy Coding!** 🚀
