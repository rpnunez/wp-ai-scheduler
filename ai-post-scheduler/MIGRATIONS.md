# AI Post Scheduler - Database Migrations

## Version 1.0 → 1.1

### Overview
This migration adds the **Voices feature** and **batch post generation support** to the AI Post Scheduler plugin.

### Changes

#### New Table: `aips_voices`
- **name** (varchar 255): Voice name
- **title_prompt** (text): Instructions for generating post titles
- **content_instructions** (text): Pre-prompt instructions for content generation
- **excerpt_instructions** (text): Optional instructions for excerpt generation
- **is_active** (tinyint): Active/inactive status
- **created_at** (datetime): Timestamp

#### Modified Table: `aips_templates`
- **voice_id** (bigint): Reference to selected voice (nullable)
- **post_quantity** (int): Number of posts to generate (1-20, default 1)

### How to Apply the Migration

#### Option 1: Via WordPress Admin (Recommended for non-technical users)

1. Simply **activate the updated plugin** in WordPress Admin
2. The plugin automatically creates the new tables and columns on activation
3. No manual SQL needed!

#### Option 2: Manual SQL (Advanced users)

1. Open your database client (phpMyAdmin, MySQL Workbench, etc.)
2. Open the migration file: `ai-post-scheduler/migrations/001-add-voices-feature.sql`
3. **Replace `{wp_prefix}` with your actual WordPress table prefix** (usually `wp_`)
   - Find your prefix in `wp-config.php`: `$table_prefix = 'wp_';`
4. Execute the SQL queries

**Example for default WordPress prefix:**
```sql
CREATE TABLE IF NOT EXISTS `wp_aips_voices` (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    title_prompt text NOT NULL,
    content_instructions text NOT NULL,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `wp_aips_templates` ADD COLUMN `voice_id` bigint(20) DEFAULT NULL AFTER `title_prompt`;
ALTER TABLE `wp_aips_templates` ADD COLUMN `post_quantity` int DEFAULT 1 AFTER `voice_id`;
```

### Rollback

If you need to revert to version 1.0, run:

```sql
ALTER TABLE `{wp_prefix}aips_templates` DROP COLUMN `post_quantity`;
ALTER TABLE `{wp_prefix}aips_templates` DROP COLUMN `voice_id`;
DROP TABLE `{wp_prefix}aips_voices`;
```

Then deactivate and delete the updated plugin.

### Notes

- ✅ The migration is **safe** - it checks if columns exist before adding them
- ✅ **No data loss** - existing templates remain unchanged
- ✅ All new columns have **sensible defaults**
- ✅ Existing templates will work with **no Voice selected** by default
