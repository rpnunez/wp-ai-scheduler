# Docker Development Environment Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         Your Computer                            │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                     Docker Desktop                        │  │
│  │                                                            │  │
│  │  ┌──────────────────────────────────────────────────┐   │  │
│  │  │           wp-network (Bridge Network)             │   │  │
│  │  │                                                    │   │  │
│  │  │  ┌─────────────────┐  ┌──────────────────┐      │   │  │
│  │  │  │  Web Container  │  │   DB Container   │      │   │  │
│  │  │  │  (WordPress)    │  │   (MariaDB)      │      │   │  │
│  │  │  │                 │  │                  │      │   │  │
│  │  │  │  Port 8080:80  │  │  Port 3307:3306  │      │   │  │
│  │  │  │  Port 9003     │  │                  │      │   │  │
│  │  │  │                 │  │                  │      │   │  │
│  │  │  │  Volume Mount  │  │  Named Volume    │      │   │  │
│  │  │  │  ./ai-post-    │  │  db_data_v2      │      │   │  │
│  │  │  │   scheduler/   │  │                  │      │   │  │
│  │  │  └────────┬────────┘  └────────┬─────────┘      │   │  │
│  │  │           │                     │                │   │  │
│  │  │  ┌────────▼─────────────────────▼─────────┐     │   │  │
│  │  │  │     phpMyAdmin Container               │     │   │  │
│  │  │  │     Port 8082:80                       │     │   │  │
│  │  │  └────────────────────────────────────────┘     │   │  │
│  │  │                                                    │   │  │
│  │  └──────────────────────────────────────────────────┘   │  │
│  │                                                            │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐    │
│  │   Browser    │    │   VS Code    │    │  DB Client   │    │
│  │  :8080       │    │  Xdebug      │    │  :3307       │    │
│  │  :8082       │    │  :9003       │    │              │    │
│  └──────────────┘    └──────────────┘    └──────────────┘    │
│                                                                  │
│  Local Files:                                                   │
│  ./ai-post-scheduler/  ←→  /var/www/html/.../ai-post-scheduler/│
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Container Details

### Web Container (wp-ai-scheduler-web)
```
┌─────────────────────────────────────────────┐
│  WordPress 6.4 + PHP 8.2 + Apache          │
├─────────────────────────────────────────────┤
│  Components:                                │
│  • WordPress Core (in named volume)        │
│  • AI Post Scheduler Plugin (bind mount)   │
│  • Xdebug 3.3.1 (pre-configured)           │
│  • WP-CLI (for management)                 │
│  • Composer (for dependencies)             │
│                                             │
│  Ports:                                     │
│  • 8080 → 80 (HTTP)                        │
│  • 9003 → 9003 (Xdebug)                    │
│                                             │
│  Volumes:                                   │
│  • wordpress_data_v2 (WP core)             │
│  • uploads_data (WP uploads)               │
│  • ./ai-post-scheduler → plugin dir (RW)   │
└─────────────────────────────────────────────┘
```

### Database Container (wp-ai-scheduler-db)
```
┌─────────────────────────────────────────────┐
│  MariaDB 10.6                               │
├─────────────────────────────────────────────┤
│  Configuration:                             │
│  • Custom my.cnf (optimized for dev)       │
│  • Database: wordpress                      │
│  • User: wordpress / wordpress             │
│  • Root: root / root                        │
│                                             │
│  Ports:                                     │
│  • 3307 → 3306 (MySQL protocol)            │
│                                             │
│  Volumes:                                   │
│  • db_data_v2 (persistent data)            │
│  • ./mariadb-conf/my.cnf (config)          │
└─────────────────────────────────────────────┘
```

### phpMyAdmin Container (wp-ai-scheduler-phpmyadmin)
```
┌─────────────────────────────────────────────┐
│  phpMyAdmin (latest)                        │
├─────────────────────────────────────────────┤
│  Purpose: Database management UI            │
│                                             │
│  Ports:                                     │
│  • 8082 → 80 (HTTP)                        │
│                                             │
│  Connects to: db:3306                       │
└─────────────────────────────────────────────┘
```

## Data Flow

### Development Workflow
```
┌──────────────┐
│ Edit Code    │  Edit files in ./ai-post-scheduler/
│ Locally      │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Bind Mount   │  Changes instantly reflected in container
│ (Live)       │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Apache       │  Serves PHP files immediately
│ Processes    │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Browser      │  Refresh to see changes
│ Shows Result │
└──────────────┘
```

### Debugging Workflow
```
┌──────────────┐
│ Set          │  Set breakpoint in VS Code
│ Breakpoint   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Press F5     │  Start listening for Xdebug
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Load Page    │  Trigger PHP execution
│ in Browser   │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Xdebug       │  Connects to VS Code on port 9003
│ Connects     │  using host.docker.internal
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ VS Code      │  Debugger pauses at breakpoint
│ Pauses       │  Inspect variables, step through code
└──────────────┘
```

### Database Access Flow
```
WordPress Container         Database Container
┌──────────────┐           ┌──────────────┐
│ WordPress    │           │ MariaDB      │
│ PHP Code     ├──────────►│ wordpress DB │
│              │ db:3306   │              │
└──────────────┘           └──────────────┘
                                   │
                                   │
                           ┌───────┴───────┐
                           │               │
                           ▼               ▼
                    ┌──────────┐    ┌──────────┐
                    │phpMyAdmin│    │External  │
                    │:8082     │    │DB Tool   │
                    └──────────┘    │:3307     │
                                    └──────────┘
```

## File System Mapping

### Container to Host Mapping
```
Container Path                          Host Path
───────────────────────────────────────────────────────────────
/var/www/html/                    →     wordpress_data_v2 (volume)
/var/www/html/wp-content/plugins/
  ai-post-scheduler/              ←→    ./ai-post-scheduler/ (bind)
/var/www/html/wp-content/uploads/ →     uploads_data (volume)
/var/lib/mysql/                   →     db_data_v2 (volume)
/etc/mysql/conf.d/my_custom.cnf   ←     ./mariadb-conf/my.cnf (ro)
```

### Volume Types

**Named Volumes** (managed by Docker):
- `wordpress_data_v2`: WordPress core files
- `db_data_v2`: Database files
- `uploads_data`: Media uploads
- Persist across container restarts
- Optimized for performance

**Bind Mounts** (direct host mapping):
- `./ai-post-scheduler` → plugin directory
- Changes instantly visible in container
- Enables live development

## Network Communication

```
┌────────────────────────────────────────────┐
│         wp-network (Bridge)                │
│                                            │
│  web:                                      │
│    - Hostname: web                         │
│    - Internal IP: 172.18.0.2 (example)    │
│    - Can reach: db:3306                   │
│                                            │
│  db:                                       │
│    - Hostname: db                          │
│    - Internal IP: 172.18.0.3 (example)    │
│    - Accessible from: web, phpmyadmin     │
│                                            │
│  phpmyadmin:                               │
│    - Hostname: phpmyadmin                  │
│    - Internal IP: 172.18.0.4 (example)    │
│    - Connects to: db:3306                 │
│                                            │
└────────────────────────────────────────────┘
```

## Port Mapping Summary

| Service | Internal Port | External Port | Purpose |
|---------|--------------|---------------|---------|
| WordPress | 80 | 8080 | HTTP access |
| Xdebug | 9003 | 9003 | Debugger connection |
| MariaDB | 3306 | 3307 | Database access |
| phpMyAdmin | 80 | 8082 | Database UI |

## Command Flow

### docker-compose up
```
1. Read docker-compose.yml
2. Create network: wp-network
3. Create volumes: db_data_v2, wordpress_data_v2, uploads_data
4. Build image from Dockerfile (if needed)
5. Start db container
6. Wait for db health check
7. Start web container
8. Run docker-entrypoint.sh:
   - Wait for database
   - Download/configure WordPress
   - Copy/mount plugin
   - Activate plugin
9. Start phpMyAdmin container
10. All services ready!
```

### make shell
```
1. docker-compose exec web bash
2. Opens interactive shell in running web container
3. You're inside the container as root
4. Can run WP-CLI commands, view logs, etc.
```

## Security Considerations

### Development Only
- Default credentials (admin/admin)
- Debug mode enabled
- Xdebug active (performance impact)
- All ports exposed to localhost only

### Production
This setup is **NOT suitable for production**. For production:
- Use environment variables for credentials
- Disable debug mode
- Remove Xdebug
- Use proper security hardening
- Implement SSL/TLS
- Use proper backup strategy

## Resource Usage

Typical resource usage:
```
┌─────────────┬─────────┬──────────┬──────────┐
│ Container   │ CPU     │ Memory   │ Disk     │
├─────────────┼─────────┼──────────┼──────────┤
│ web         │ 0-10%   │ 200-500M │ 1.5 GB   │
│ db          │ 0-5%    │ 100-300M │ 500 MB   │
│ phpmyadmin  │ 0-1%    │ 50-100M  │ 100 MB   │
├─────────────┼─────────┼──────────┼──────────┤
│ Total       │ 0-16%   │ 350-900M │ ~2.1 GB  │
└─────────────┴─────────┴──────────┴──────────┘
```

Note: With Xdebug active and code execution, CPU and memory usage will increase.

## Troubleshooting Quick Reference

```
Problem              Command
─────────────────────────────────────────────────
Container won't      docker-compose logs [service]
start                
                     
Port in use          docker-compose ps
                     netstat -an | grep 8080
                     
Xdebug not working   docker-compose exec web php -v
                     cat /tmp/xdebug.log
                     
DB connection failed docker-compose restart db
                     docker-compose logs db
                     
Changes not showing  docker-compose restart web
                     docker-compose exec web ls -la /var/.../
                     
Reset everything     docker-compose down -v
                     docker-compose up -d --build
```

## Additional Resources

- [Complete Setup Guide](DOCKER_DEV_README.md)
- [Troubleshooting Guide](DOCKER_TROUBLESHOOTING.md)
- [Quick Reference](DOCKER_QUICKREF.md)
- [XAMPP Comparison](DOCKER_VS_XAMPP.md)
