# AI Post Scheduler - Database Schema Updates

## Sources reliability (3.7.19)

The Sources table adds nullable `last_fetch_attempt_at`, `last_fetch_status`, and `last_fetch_error`. Legacy attempt state remains unknown until the next fetch; `last_fetched_at` continues to mean last successful fetch. `next_fetch_at` becomes nullable so disabling automatic fetching can clear it under strict MySQL. The guarded migration preserves existing due timestamps and can run repeatedly. It verifies the structural changes before acknowledging version 3.7.19 and restores the previous version gate on failure so the next request retries. Atomic group replacement requires a transactional source-group table, normally InnoDB; verify the storage engine on legacy installations.

Snapshot deduplication now hashes the current URL plus normalized text, allowing identical text fetched from a changed URL to retain separate provenance. Lookups also recognize legacy text-only hashes by exact URL/text match, leaving historical fetch timestamps intact. The existing `(source_id, content_hash)` uniqueness constraint remains; no extra selection index is added without a measured EXPLAIN benefit. Validate the anti-join winner query and bounded maintenance DELETE on the target MySQL/MariaDB instance before enabling retention.

## How Database Schema Updates Work

The AI Post Scheduler uses WordPress's built-in `dbDelta()` function to manage database schema updates. This is the standard WordPress approach for database migrations.

### Key Points

- **Single Source of Truth**: All database schema is defined in `AIPS_DB_Manager::get_schema()`
- **Automatic Updates**: When the plugin is updated, `dbDelta()` automatically:
  - Creates missing tables
  - Adds missing columns
  - Updates column types where needed
- **Idempotent**: Safe to run multiple times - won't duplicate or break existing data
- **Version Tracking**: Plugin tracks DB version in `aips_db_version` option

## Version-Specific Data Migrations

### 3.7.18 - Author topic auto-approval consolidation

Runs after `install_tables()` adds `aips_authors.topic_auto_approval_min_relevance`:

- Copies `topic_auto_approval_min_score` into `topic_auto_approval_min_relevance` for authors in `similarity`/`embeddings` mode.
- Switches authors with mode `manual`, empty or NULL (the old column default) to `inherit`; explicit `score`, `all` and `similarity` modes are unchanged.
- Renames the stored global fallback `reject` to `rejected`.
- Deletes the retired option family (`aips_author_topic_auto_approval_enabled`, `aips_author_topic_approval_mode`, `aips_author_topic_min_relevance`, `aips_author_topic_max_duplicate`, `aips_author_topic_fallback_action`).

## How Updates Are Applied

### On Plugin Activation or Update

1. Plugin detects version change by comparing `aips_db_version` with `AIPS_VERSION`
2. If update needed, calls `AIPS_Upgrades::check_and_run()`
3. This calls `AIPS_DB_Manager::install_tables()` which:
   - Runs `dbDelta()` with current schema definition
   - Seeds default data (prompt sections and article structures)
4. Updates `aips_db_version` to current plugin version

### For Developers

If you need to modify the database schema:

1. **Update the schema** in `AIPS_DB_Manager::get_schema()`
2. **Increment the plugin version** in `ai-post-scheduler.php`
3. **Test the upgrade** by:
   - Installing the old version
   - Upgrading to your new version
   - Verifying tables/columns are updated correctly

### Example: Adding a New Column

```php
// In AIPS_DB_Manager::get_schema()
$sql[] = "CREATE TABLE $table_name (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    existing_column varchar(255),
    new_column text DEFAULT NULL,  // Add your new column here
    PRIMARY KEY  (id)
) $charset_collate;";
```

When users upgrade, `dbDelta()` will automatically add the `new_column` to existing tables.

## System Status Page

The System Status page (`Settings > System Status`) shows database health:
- All tables exist
- All expected columns are present
- Uses `AIPS_DB_Manager` as source of truth for expected schema

## Database Tables

Current tables managed by the plugin:
- `aips_history` - Generation history
- `aips_templates` - Post templates
- `aips_schedule` - Scheduling configuration
- `aips_voices` - Writing voices/styles
- `aips_article_structures` - Article structure definitions
- `aips_prompt_sections` - Reusable prompt sections
- `aips_trending_topics` - Trending topic research

## Troubleshooting

### Tables or Columns Missing

Go to Settings > System Status and click "Repair Database" to re-run schema creation.

### Fresh Installation

To completely reinstall the database:
1. Go to Settings > System Status
2. Click "Reinstall Database" (backs up data by default)
3. Or use "Wipe Data" to remove all plugin data

## Notes

- ✅ **No data loss** - `dbDelta()` only adds/modifies, never removes
- ✅ **Safe to re-run** - Schema creation is idempotent
- ✅ **WordPress standard** - Uses native WordPress database functions
- ✅ **Default data** - Automatically seeds prompt sections and article structures
