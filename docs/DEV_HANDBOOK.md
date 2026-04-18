# Dev Handbook — Quick Reference

Fast-access cheat sheet for daily development. Full details in [DEV.md](DEV.md).

---

## First-Time Setup

```bash
./start-dev.sh       # Run from repo root in Bash (Git Bash / WSL2 / Mac/Linux)
```

---

## Access Points

| Service     | URL                                | Login                    |
|-------------|-------------------------------------|--------------------------|
| WordPress   | http://localhost:8080               | admin / admin            |
| WP Admin    | http://localhost:8080/wp-admin      | admin / admin            |
| phpMyAdmin  | http://localhost:8082               | wordpress / wordpress    |

---

## Daily Commands

| Task                          | Command                      |
|-------------------------------|------------------------------|
| Start environment             | `make up`                    |
| Stop environment              | `make down`                  |
| Restart all containers        | `make restart`               |
| Stream logs                   | `make logs`                  |
| WordPress container shell     | `make shell`                 |
| WP-CLI interactive shell      | `make wp-shell`              |
| Container status              | `make status`                |

---

## Testing

```bash
cd ai-post-scheduler

composer test              # Full suite
composer test:verbose      # Verbose output
composer test:coverage     # Coverage report

vendor/bin/phpunit tests/test-template-processor.php   # Single file
```

---

## Debugging (VS Code + Xdebug)

1. Run `make up`
2. Press **F5** in VS Code
3. Select **"Listen for Xdebug (Docker)"**
4. Set breakpoints → refresh browser

Apply `dev-php.ini` changes: `make reload-php`

---

## Rebuild Triggers

| When                                      | Command                              |
|-------------------------------------------|--------------------------------------|
| Plugin code changed (`.php`, `.js`, `.css`) | Just refresh the browser — no rebuild needed |
| `Dockerfile` or `docker-compose.yml` changed | `docker compose up -d --build`      |
| Force clean rebuild                       | `docker compose build --no-cache && docker compose up -d` |

---

## Database

```bash
make db-shell              # MySQL shell (or use phpMyAdmin at :8082)
make db-backup             # Backup → backup.sql
make db-restore            # Restore from backup.sql
```

---

## WP-CLI One-Liners

```bash
docker compose exec web wp plugin list --allow-root
docker compose exec web wp cache flush --allow-root
docker compose exec web wp user list --allow-root
```

---

## Quick Troubleshooting

| Symptom                        | Fix                                            |
|--------------------------------|------------------------------------------------|
| Port in use                    | Change `WP_PORT` in `.env` or `docker-compose.yml` |
| Plugin changes not reflecting  | `docker compose exec web wp cache flush --allow-root` |
| Xdebug not connecting          | `make xdebug-status`, `make reload-php`        |
| Container keeps restarting     | `docker compose logs web`                      |
| No space left on device        | `docker system prune -a --volumes`             |
| Database connection error      | `docker compose down && docker compose up -d`  |

Full troubleshooting guide in [DEV.md — Troubleshooting](DEV.md#troubleshooting).
