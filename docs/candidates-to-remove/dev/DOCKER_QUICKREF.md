# Docker Development - Quick Reference

Quick command reference for daily development tasks.

## 🚀 Getting Started

```bash
./start-dev.sh                    # First time setup
make up                           # Start environment
```

**Access Points:**
- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin/admin)
- phpMyAdmin: http://localhost:8082 (wordpress/wordpress)

## 📝 Daily Commands

```bash
# Start/Stop
make up                           # Start all services
make down                         # Stop all services
make restart                      # Restart all services
make reload-php                   # Reload Apache/PHP after dev-php.ini edits

# View Logs
make logs                         # All logs (live)
make logs-web                     # WordPress logs only
make logs-db                      # Database logs only

# Shell Access
make shell                        # WordPress container shell
make wp-shell                     # WP-CLI interactive shell
make db-shell                     # MySQL shell

# Status
make status                       # Container status
make info                         # WordPress & plugin info
```

## 🐛 Debugging with Xdebug

```bash
# In VS Code:
1. Open project folder
2. Press F5
3. Select "Listen for Xdebug (Docker)"
4. Set breakpoints
5. Refresh browser

# Troubleshoot
make xdebug-status                # Check Xdebug config
make xdebug-log                   # View Xdebug log
make reload-php                   # Apply dev-php.ini changes quickly
docker-compose restart web        # Full web restart (fallback)
```

## 🔧 Plugin Development

```bash
# Edit files in ./ai-post-scheduler/
# Changes are live - just refresh browser!

# Plugin Management
make plugin-activate              # Activate plugin
make plugin-deactivate            # Deactivate plugin
make plugin-list                  # List all plugins

# Testing
make test                         # Run plugin tests
make test-verbose                 # Run tests with details
make composer-install             # Install dependencies
```

## 💾 Database Operations

```bash
# Backup/Restore
make db-backup                    # Backup to backup.sql
make db-restore                   # Restore from backup.sql

# Direct Access
make db-shell                     # MySQL shell
# Or use external tools:
# Host: localhost, Port: 3307
# User: wordpress, Pass: wordpress
```

## 📦 WordPress Management

```bash
# Using WP-CLI
docker-compose exec web wp plugin list --allow-root
docker-compose exec web wp theme list --allow-root
docker-compose exec web wp user list --allow-root
docker-compose exec web wp cache flush --allow-root

# Quick WP-CLI
make wp-shell                     # Interactive WP-CLI
```

## 🔄 Rebuild & Reset

```bash
# Rebuild
make rebuild                      # Rebuild containers
docker-compose build --no-cache   # Force clean rebuild

# Reset
make clean                        # Delete everything (⚠️ data loss!)
make install                      # Fresh WordPress install
```

## 📊 Monitoring

```bash
# Resource Usage
docker stats                      # Real-time stats
docker compose ps                 # Container status

# URLs
make urls                         # Show all access URLs
```

## 🆘 Common Issues

```bash
# Container won't start
make logs                         # Check for errors

# Port already in use
# Edit docker-compose.yml, change 8080 to 8081

# Xdebug not working
make xdebug-log                   # Check Xdebug log
# Check firewall allows port 9003

# Site is slow
# Xdebug causes slowdown - normal in debug mode

# Changes not showing
make reload-php                   # Reload Apache/PHP config changes
# If still needed: docker compose restart web
# Or clear cache in browser

# Database connection error
docker compose restart db         # Restart database
make logs-db                      # Check database logs
```

## 🗂️ File Locations

```
In Container                      → On Host
/var/www/html/                    → Docker volume (WordPress core)
/var/www/html/wp-content/plugins/ai-post-scheduler/ → ./ai-post-scheduler/
/var/www/html/wp-content/uploads/ → Docker volume (uploads_data)
/var/lib/mysql/                   → Docker volume (db_data_v2)
```

## ⚙️ Configuration Files

```bash
.env                              # Environment variables (create from .env.example)
dev-php.ini                       # Editable PHP/Xdebug overrides (reload with make reload-php)
docker-compose.yml                # Service configuration
Dockerfile                        # Image configuration
.vscode/launch.json               # Xdebug configuration
Makefile                          # Command shortcuts
```

## 🔗 Useful Links

- Full Documentation: [DOCKER_DEV_README.md](DOCKER_DEV_README.md)
- Troubleshooting: [DOCKER_TROUBLESHOOTING.md](DOCKER_TROUBLESHOOTING.md)
- XAMPP Comparison: [DOCKER_VS_XAMPP.md](DOCKER_VS_XAMPP.md)

## 💡 Pro Tips

```bash
# Multiple terminal tabs
Tab 1: make logs                  # Watch logs
Tab 2: make shell                 # Run commands
Tab 3: code .                     # Edit code

# Faster development
# - Keep logs running in a terminal
# - Use VS Code integrated terminal
# - Set breakpoints liberally
# - Use wp-shell for quick WordPress tasks

# Before committing
make test                         # Run tests
git status                        # Check what changed
# Only commit plugin files, not WordPress core

# Performance
# - Allocate 4GB+ RAM to Docker Desktop
# - Use named volumes (already configured)
# - Disable Xdebug when not debugging
```

## 📋 Cheat Sheet

| Task | Command |
|------|---------|
| **Start** | `make up` or `./start-dev.sh` |
| **Stop** | `make down` |
| **Logs** | `make logs` |
| **Shell** | `make shell` |
| **Test** | `make test` |
| **Debug** | Press `F5` in VS Code |
| **Restart** | `make restart` |
| **Reload PHP ini** | `make reload-php` |
| **Clean** | `make clean` (⚠️ deletes data) |
| **Help** | `make help` |

---

**Keep this file bookmarked for quick reference! 📌**

For detailed documentation, see [DOCKER_DEV_README.md](DOCKER_DEV_README.md)
