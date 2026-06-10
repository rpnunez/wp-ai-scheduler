# Running the Content Seeding Script

The content seeder script bootstrap the content strategy for the plugin. It can seed a production-ready profile for the live DevStackTips website or a lightweight profile for local testing and development.

## Option A: Running in Docker (Recommended)

If you are using the Docker-backed development stack, you can run the seeder directly from your host using the `Makefile` shortcut.

1. Start your Docker services:
   ```powershell
   make up
   ```

2. Seed the development/testing profile:
   ```powershell
   make seed PROFILE=dev-test
   ```

3. If you want to wipe existing tables and do a fresh seed of the production profile:
   ```powershell
   make seed PROFILE=devstacktips FRESH=1
   ```

4. If you need to rollback/delete all created entities for a profile:
   ```powershell
   make seed PROFILE=devstacktips ROLLBACK=1
   ```

---

## Option B: Running via host WP-CLI

If you are running WordPress directly on your host machine:

1. Open PowerShell and go to the repo root:
   ```powershell
   cd c:\Projects\NunezScheduler\wp-ai-scheduler
   ```

2. Confirm the script exists:
   ```powershell
   Get-Item .\scripts\seed-content.php
   ```

3. Run a database backup before changing anything:
   ```powershell
   wp db export ".\backups\devstacktips-before-seed-$(Get-Date -Format 'yyyyMMdd-HHmmss').sql"
   ```

4. Make sure the plugin is active:
   ```powershell
   wp plugin activate ai-post-scheduler
   ```

5. Run the seeder with the local testing profile:
   ```powershell
   wp eval-file .\scripts\seed-content.php --profile=dev-test
   ```

6. Or run the production profile with a fresh start:
   ```powershell
   wp eval-file .\scripts\seed-content.php --profile=devstacktips --fresh
   ```

7. To rollback/undo the seeded entities:
   ```powershell
   wp eval-file .\scripts\seed-content.php --profile=devstacktips rollback
   ```