# Docker Development Troubleshooting Guide

This guide covers common issues you might encounter when using the Docker development environment for AI Post Scheduler.

## Table of Contents

- [Docker Issues](#docker-issues)
- [WordPress Issues](#wordpress-issues)
- [Xdebug Issues](#xdebug-issues)
- [Database Issues](#database-issues)
- [Performance Issues](#performance-issues)
- [Network Issues](#network-issues)

---

## Docker Issues

### "Docker is not running"

**Symptoms:** Error message when running docker commands

**Solution:**
```bash
# Start Docker Desktop application
# Windows: Start Docker Desktop from Start Menu
# Mac: Start Docker Desktop from Applications
# Linux: sudo systemctl start docker
```

### "Port already in use" or "Address already in use"

**Symptoms:**
```
Error: bind: address already in use
Error starting userland proxy: listen tcp4 0.0.0.0:8080: bind: address already in use
```

**Solution:**

1. Find what's using the port:
   ```bash
   # Mac/Linux
   lsof -i :8080
   sudo lsof -i :8080
   
   # Windows (PowerShell)
   netstat -ano | findstr :8080
   Get-Process -Id (Get-NetTCPConnection -LocalPort 8080).OwningProcess
   ```

2. Stop the conflicting service or change the port in `docker-compose.yml`:
   ```yaml
   services:
     web:
       ports:
         - "8081:80"  # Changed from 8080 to 8081
   ```

### "Cannot connect to Docker daemon"

**Symptoms:**
```
Cannot connect to the Docker daemon at unix:///var/run/docker.sock
```

**Solution:**
- Ensure Docker Desktop is running
- On Linux, add your user to the docker group:
  ```bash
  sudo usermod -aG docker $USER
  newgrp docker
  ```

### Build fails with "no space left on device"

**Symptoms:**
```
failed to copy files: write /var/lib/docker/.../layer.tar: no space left on device
```

**Solution:**
```bash
# Clean up Docker resources
docker system prune -a --volumes

# Check Docker disk usage
docker system df

# In Docker Desktop: Settings → Resources → Disk image size
# Increase the disk allocation
```

### "Container won't start" or keeps restarting

**Symptoms:** Container shows as "Restarting" in `docker-compose ps`

**Solution:**
```bash
# Check container logs
docker-compose logs web
docker-compose logs db

# Check for specific errors and address them

# If database won't start, it might be corrupted
docker-compose down -v  # WARNING: Deletes all data
docker-compose up -d
```

---

## WordPress Issues

### WordPress installation fails

**Symptoms:** Container starts but WordPress admin shows "Error establishing database connection"

**Solution:**
```bash
# Check database is healthy
docker-compose ps

# Check database logs
docker-compose logs db

# Verify environment variables
docker-compose exec web env | grep WORDPRESS

# Try restarting in correct order
docker-compose down
docker-compose up -d db
sleep 10
docker-compose up -d web
```

### "Headers already sent" warnings

**Symptoms:** PHP warnings about headers being sent

**Solution:**
- This might be due to whitespace in PHP files
- Check for UTF-8 BOM in files
- Restart the container: `make restart`

### Changes to PHP files not reflected

**Symptoms:** You edit a file but changes don't appear in browser

**Solution:**
```bash
# Clear WordPress cache
docker-compose exec web wp cache flush --allow-root

# Clear object cache if using Redis/Memcached
docker-compose exec web wp cache delete --all --allow-root

# Check if file permissions are correct
docker-compose exec web ls -la /var/www/html/wp-content/plugins/ai-post-scheduler

# Verify volume mount is working
docker-compose exec web cat /var/www/html/wp-content/plugins/ai-post-scheduler/ai-post-scheduler.php | head -20
```

### Plugin activation fails

**Symptoms:** Plugin won't activate or shows error

**Solution:**
```bash
# Check for PHP errors
docker-compose logs web | grep -i error

# Try activating via WP-CLI with verbose output
docker-compose exec web wp plugin activate ai-post-scheduler --allow-root

# Check plugin file permissions
docker-compose exec web chown -R www-data:www-data /var/www/html/wp-content/plugins/ai-post-scheduler
```

### Can't access WordPress admin

**Symptoms:** 404 or permission denied errors

**Solution:**
```bash
# Check if WordPress is installed
docker-compose exec web wp core is-installed --allow-root

# Reinstall if needed
make install

# Check .htaccess exists and has correct content
docker-compose exec web cat /var/www/html/.htaccess
```

---

## Xdebug Issues

### Xdebug not connecting to IDE

**Symptoms:** Breakpoints are not being hit, debugger never pauses

**Solution:**

1. **Verify Xdebug is installed:**
   ```bash
   docker-compose exec web php -v | grep Xdebug
   ```

2. **Check Xdebug configuration:**
   ```bash
   docker-compose exec web php -i | grep xdebug
   ```

3. **Verify Xdebug is trying to connect:**
   ```bash
   docker-compose exec web cat /tmp/xdebug.log
   ```

4. **Check firewall settings:**
   - Ensure port 9003 is not blocked
   - On Mac/Windows: Docker Desktop should handle this automatically
   - On Linux: Check UFW/iptables rules

5. **Verify IDE configuration:**
   - VS Code: Check `.vscode/launch.json` path mappings
   - PhpStorm: Check PHP → Servers → Path mappings

6. **Test with xdebug_break():**
   Add this line in your PHP code:
   ```php
   xdebug_break();
   ```

### Xdebug connects but wrong file opens

**Symptoms:** Debugger stops but shows wrong file or can't find file

**Solution:**

1. **Check path mappings in `.vscode/launch.json`:**
   ```json
   "pathMappings": {
       "/var/www/html/wp-content/plugins/ai-post-scheduler": "${workspaceFolder}/ai-post-scheduler"
   }
   ```

2. **Verify the workspace folder is correct:**
   - Open the repository root in VS Code, not a subdirectory

3. **For Windows users:**
   - Ensure paths use forward slashes
   - Check that Docker Desktop has file sharing enabled for your drive

### Xdebug makes site very slow

**Symptoms:** Page loads take 30+ seconds

**Solution:**

1. **Temporary disable Xdebug:**
   ```bash
   docker-compose exec web mv /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini /tmp/
   docker-compose restart web
   ```

2. **Re-enable when needed:**
   ```bash
   docker-compose exec web mv /tmp/docker-php-ext-xdebug.ini /usr/local/etc/php/conf.d/
   docker-compose restart web
   ```

3. **Or configure Xdebug to only start when needed:**
   Change `xdebug.start_with_request=yes` to `xdebug.start_with_request=trigger`

### Xdebug log shows "Connection refused"

**Symptoms:** `/tmp/xdebug.log` shows connection errors

**Solution:**

1. **For Windows/Mac:**
   ```yaml
   # In docker-compose.yml, ensure extra_hosts is set:
   extra_hosts:
     - "host.docker.internal:host-gateway"
   ```

2. **For Linux:**
   Find your host IP and update `XDEBUG_CLIENT_HOST` in `.env`:
   ```bash
   # Get your host IP
   ip addr show docker0 | grep -Po 'inet \K[\d.]+'
   
   # Update .env
   XDEBUG_CLIENT_HOST=172.17.0.1  # Use your actual IP
   ```

---

## Database Issues

### "Connection refused" to database

**Symptoms:** WordPress can't connect to MySQL

**Solution:**
```bash
# Check if database container is running
docker-compose ps db

# Check database health
docker-compose exec db mysqladmin ping -u root -proot

# Try restarting database
docker-compose restart db

# If still failing, check network
docker network ls
docker network inspect wp-ai-scheduler_wp-network
```

### "Table doesn't exist" errors

**Symptoms:** WordPress shows database errors about missing tables

**Solution:**
```bash
# List existing tables
docker-compose exec db mysql -u wordpress -pwordpress -e "USE wordpress; SHOW TABLES;"

# Reinstall WordPress (creates tables)
docker-compose exec web wp core install --url=http://localhost:8080 --title="WP Site" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --allow-root

# Or start completely fresh
make install
```

### Can't connect with external database tool

**Symptoms:** Can't connect with TablePlus, MySQL Workbench, etc.

**Solution:**

1. **Verify port mapping:**
   ```bash
   docker-compose ps
   # Should show: 0.0.0.0:3307->3306/tcp
   ```

2. **Use these connection settings:**
   - Host: `localhost`
   - Port: `3307` (not 3306!)
   - User: `wordpress`
   - Password: `wordpress`
   - Database: `wordpress`

3. **Test connection:**
   ```bash
   mysql -h 127.0.0.1 -P 3307 -u wordpress -pwordpress wordpress
   ```

### Database is slow or unresponsive

**Symptoms:** Queries take a long time

**Solution:**
```bash
# Check database resource usage
docker stats wp-ai-scheduler-db

# Check slow query log
docker-compose exec db cat /var/log/mysql/slow-query.log

# Optimize database tables
docker-compose exec web wp db optimize --allow-root

# If necessary, increase resources in Docker Desktop
# Settings → Resources → Memory (increase to 4GB+)
```

---

## Performance Issues

### Site loads very slowly

**Symptoms:** Pages take 10+ seconds to load

**Possible causes and solutions:**

1. **Xdebug is active:**
   - See "Xdebug makes site very slow" section above

2. **Insufficient Docker resources:**
   ```bash
   # Check resource usage
   docker stats
   
   # Increase in Docker Desktop:
   # Settings → Resources → Memory (4GB+)
   # Settings → Resources → CPUs (2+)
   ```

3. **Too many Docker volumes:**
   ```bash
   # Clean up unused volumes
   docker volume prune
   ```

4. **Disk I/O issues (Windows/Mac):**
   - Using named volumes (already configured) is faster than bind mounts
   - Ensure Docker Desktop uses VirtioFS (Mac) or WSL2 (Windows)

### High CPU usage

**Symptoms:** Docker using 100% CPU

**Solution:**
```bash
# Check which container is using resources
docker stats

# Check for infinite loops in logs
docker-compose logs --tail=100 web

# Restart services
make restart
```

---

## Network Issues

### Can't access http://localhost:8080

**Symptoms:** Browser shows "This site can't be reached"

**Solution:**

1. **Check container is running:**
   ```bash
   docker-compose ps
   ```

2. **Check port is actually exposed:**
   ```bash
   docker-compose port web 80
   # Should show: 0.0.0.0:8080
   ```

3. **Try 127.0.0.1 instead:**
   http://127.0.0.1:8080

4. **Check if another service is using port 8080:**
   ```bash
   # See "Port already in use" section
   ```

5. **Check Docker network:**
   ```bash
   docker network ls
   docker network inspect wp-ai-scheduler_wp-network
   ```

### Containers can't communicate with each other

**Symptoms:** Web container can't reach database

**Solution:**
```bash
# Check if containers are on same network
docker network inspect wp-ai-scheduler_wp-network

# Verify service names resolve
docker-compose exec web ping -c 3 db

# Restart with network recreation
docker-compose down
docker-compose up -d
```

---

## Still Having Issues?

If you're still experiencing problems:

1. **Collect diagnostic information:**
   ```bash
   # System info
   docker version
   docker-compose version
   
   # Container status
   docker-compose ps
   
   # Logs
   docker-compose logs > docker-logs.txt
   
   # Resource usage
   docker stats --no-stream
   ```

2. **Try a clean restart:**
   ```bash
   # Stop everything
   docker-compose down
   
   # Start fresh (keeps data)
   docker-compose up -d --build
   
   # Or completely reset (DELETES DATA)
   docker-compose down -v
   docker-compose up -d --build
   ```

3. **Check Docker Desktop settings:**
   - Ensure sufficient resources allocated
   - Check file sharing is enabled for your project directory
   - Try resetting Docker Desktop to factory defaults (as last resort)

4. **Get help:**
   - Check Docker documentation: https://docs.docker.com/
   - WordPress debugging: https://wordpress.org/support/article/debugging-in-wordpress/
   - Xdebug documentation: https://xdebug.org/docs/
