1. Open PowerShell and go to the repo root:
```powershell
cd c:\Projects\NunezScheduler\wp-ai-scheduler
```

2. Confirm you are in the right place and the script exists:
```powershell
Get-Item .\ai-post-scheduler\scripts\setup-devstacktips-content.php
```

3. Check that WP-CLI is available:
```powershell
wp --info
```

4. Check which WordPress site you are about to modify by running a harmless read:
```powershell
wp option get siteurl
```

5. Make a database backup before changing anything:
```powershell
wp db export ".\backups\devstacktips-before-seed-$(Get-Date -Format 'yyyyMMdd-HHmmss').sql"
```

6. Optionally back up uploaded files too if this is a shared/local env you care about:
```powershell
Compress-Archive -Path .\wp-content\uploads -DestinationPath ".\backups\uploads-before-seed-$(Get-Date -Format 'yyyyMMdd-HHmmss').zip"
```

7. Make sure the plugin is active:
```powershell
wp plugin is-active ai-post-scheduler
```

8. If that returns non-zero, activate it:
```powershell
wp plugin activate ai-post-scheduler
```

9. Run a quick syntax check on the script:
```powershell
php -l .\ai-post-scheduler\scripts\setup-devstacktips-content.php
```

10. Run the seeder:
```powershell
wp eval-file .\ai-post-scheduler\scripts\setup-devstacktips-content.php
```

11. Verify a few important settings were written:
```powershell
wp option get aips_default_post_status
```

12. Verify one of the seeded entities exists:
```powershell
wp option get aips_site_niche
```

13. If you need to inspect the stored notification prefs:
```powershell
wp option get aips_notification_preferences --format=json
```

14. If anything looks wrong and you want to undo the seeded entities:
```powershell
wp eval-file .\ai-post-scheduler\scripts\setup-devstacktips-content.php rollback
```

15. If rollback is not enough and you want full recovery, restore the DB backup you made:
```powershell
wp db import .\backups\YOUR-BACKUP-FILE.sql
```

A few safety notes:
- Run this from the repo root, because the script path relative to the repo root is `.\ai-post-scheduler\scripts\setup-devstacktips-content.php`.
- Step 4 is the important guardrail: make sure `siteurl` is your intended local/dev site before seeding.
- The script is designed to update/create settings and seeded records, so taking the DB export first is the main protection.