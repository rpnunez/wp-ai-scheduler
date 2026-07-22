---
name: aips-repository-boundary
description: Use when writing or reviewing any $wpdb/SQL code in the AI Post Scheduler plugin (ai-post-scheduler/), or when composer lint:repository-boundary needs to be understood, satisfied, or whitelisted.
---

# Repository boundary enforcement

This plugin enforces "SQL lives only in repositories" mechanically, not just by
convention.

## What the lint actually checks

`composer lint:repository-boundary` runs `tools/check-repository-boundary.php`,
which:

1. Recursively scans `includes/` for files matching
   `class-aips-*-(controller|service).php`.
2. Flags any such file whose content matches the regex `\$wpdb\s*->|global\s+\$wpdb`.
3. Skips files listed in `config/repository-boundary-whitelist.txt` (one relative
   path per line, `#`-comments allowed).
4. Exits non-zero and lists violations if any are found.

## Required workflow

1. **If you're writing SQL, put it in a repository.** A `class-aips-*-repository.php`
   file (implementing an `*_Repository_Interface`) is the only place `$wpdb` should
   appear outside the whitelist.
2. **If a controller/service seems to need direct SQL, that's a signal to add a
   repository method instead**, not to reach for `$wpdb` inline.
3. **Only whitelist as a last resort, with rationale.** Add the relative path to
   `config/repository-boundary-whitelist.txt` and explain why in the PR description
   — currently only `includes/class-aips-telemetry.php` and
   `includes/class-aips-db-migrations.php` are whitelisted, both for specific
   structural reasons (telemetry write-path performance, migration bootstrapping
   before repositories are wired).
4. **Run the lint before considering a controller/service change done:**
   ```bash
   cd ai-post-scheduler && composer lint:repository-boundary
   ```

## Reference files

- `ai-post-scheduler/tools/check-repository-boundary.php`
- `ai-post-scheduler/config/repository-boundary-whitelist.txt`
