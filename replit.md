# AI Post Scheduler - WordPress Plugin

## Overview

This is a WordPress plugin that integrates with Meow Apps AI Engine to provide an admin interface for scheduling AI-generated blog posts. The plugin allows WordPress administrators to create content templates, schedule automated post generation, and manage the entire workflow from the WordPress admin dashboard.

## Project Structure

```
ai-post-scheduler/
├── ai-post-scheduler.php          # Main plugin file
├── readme.txt                      # WordPress plugin readme
├── assets/
│   ├── css/
│   │   └── admin.css              # Admin interface styles
│   └── js/
│       └── admin.js               # Admin interface JavaScript
├── includes/
│   ├── class-aips-settings.php    # Admin settings and menu pages
│   ├── class-aips-voices.php      # Voice CRUD operations
│   ├── class-aips-templates.php   # Template CRUD operations
│   ├── class-aips-generator.php   # AI Engine integration
│   ├── class-aips-scheduler.php   # WordPress Cron scheduling
│   ├── class-aips-history.php     # Generation history tracking
│   └── class-aips-logger.php      # Logging system
└── templates/
    └── admin/
        ├── dashboard.php          # Main dashboard view
        ├── voices.php             # Voice management view
        ├── templates.php          # Template management view
        ├── schedule.php           # Schedule management view
        ├── history.php            # Generation history view
        └── settings.php           # Plugin settings view
```

## Key Features

1. **Voice System**: Create reusable "Voices" with pre-configured title prompts and content instructions
2. **Template System**: Create and manage AI prompt templates with dynamic variables
3. **Bulk Generation**: Generate 1-20 posts at once from a single template
4. **Scheduling**: Flexible scheduling options (hourly, 6h, 12h, daily, weekly)
5. **AI Engine Integration**: Uses Meow Apps AI Engine for content generation
6. **History Tracking**: Full audit trail of all generated posts
7. **Error Handling**: Logging and retry capabilities for failed generations

## Database Tables

The plugin creates four custom tables:
- `{prefix}_aips_voices` - Stores voice configurations
- `{prefix}_aips_templates` - Stores prompt templates
- `{prefix}_aips_schedule` - Stores scheduling configuration
- `{prefix}_aips_history` - Stores generation history

## Voices Feature

A "Voice" allows you to establish a consistent tone and style for AI-generated content:

**Voice Fields:**
- **Title Prompt**: Instructions for generating compelling post titles
- **Content Instructions**: Pre-prompt instructions prepended to all content generation

**Usage:**
- Select a Voice when creating or editing a Template
- If a Voice is selected, the Voice's Title Prompt and Content Instructions are used
- If no Voice is selected, the Template's Title Prompt (if provided) or default title generation is used

## Template Variables

Available in prompts:
- `{{date}}`, `{{year}}`, `{{month}}`, `{{day}}`, `{{time}}`
- `{{site_name}}`, `{{site_description}}`
- `{{random_number}}`

## Batch Generation

Set the "Number of Posts to Generate" (1-20) in a Template to generate multiple posts at once:
- When you click "Run Now", it generates the specified number of posts
- All posts use the same template but generate unique content each time
- Useful for batch content creation workflows

## Installation

1. Copy the `ai-post-scheduler` folder to WordPress `/wp-content/plugins/`
2. Activate the plugin in WordPress admin (automatically creates/updates database tables)
3. Ensure Meow Apps AI Engine is installed and configured
4. Navigate to "AI Post Scheduler" in the admin menu
5. Create Voices (optional) to establish consistent tone
6. Create Templates to define content generation
7. Set up Schedules for automated generation

## Database Migrations

See `MIGRATIONS.md` for information about upgrading from version 1.0 to 1.1 (adds Voices feature and batch generation).

**For existing installations:**
- Simply reactivate the updated plugin - it automatically applies database changes
- OR manually apply the SQL migration in `migrations/001-add-voices-feature.sql`

## Dependencies

- WordPress 5.8+
- PHP 7.4+
- Meow Apps AI Engine plugin (required)

## Security

- All admin actions protected with WordPress nonces and capability checks
- Secure AJAX handlers with referer validation
- Database sanitization and escaping
- Rate limiting via WordPress Cron
