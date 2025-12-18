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
- **Excerpt Instructions** (Optional): Custom instructions for excerpt generation

**Usage:**
- Select a Voice when creating or editing a Template
- If a Voice is selected, its Title Prompt and Content Instructions are used
- If no Voice is selected, the Template's Title Prompt (if provided) or default title generation is used
- If Excerpt Instructions are provided, they influence the AI's excerpt generation

## Post Generation Features

**Content Output:**
- Post content is generated in **HTML format** with proper semantic tags (<p>, <h2>, <h3>, <ul>, <li>, <blockquote>, etc.)
- Posts automatically include a **generated excerpt** (40-60 characters) saved to the post's excerpt field
- Excerpts can be influenced by Voice "Excerpt Instructions" if a Voice is selected

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

## Featured Image Generation

Templates can automatically generate and set AI-generated featured images:
- Enable "Generate Featured Image?" checkbox in template settings
- Provide an "Image Prompt" describing the desired image
- Image is generated using Meow Apps AI Engine, uploaded to Media Library
- Media item is named as the slug version of the post title
- Image is automatically set as the featured image for each generated post

## Installation

1. Copy the `ai-post-scheduler` folder to WordPress `/wp-content/plugins/`
2. Activate the plugin in WordPress admin (automatically creates/updates database tables)
3. Ensure Meow Apps AI Engine is installed and configured
4. Navigate to "AI Post Scheduler" in the admin menu
5. Create Voices (optional) to establish consistent tone
6. Create Templates to define content generation
7. Set up Schedules for automated generation

## Database Migrations & Upgrade System

The plugin uses a **WordPress-native upgrade system** for automatic database management:

**How it works:**
- Each version increment checks the stored `aips_db_version` against `AIPS_VERSION`
- If an upgrade is needed, all pending migrations are executed in sequence
- Migration files are stored in `migrations/` directory (e.g., `migration-1.2-add-featured-images.php`)
- Upgrades run automatically on:
  - Plugin activation (register_activation_hook)
  - Every page load (plugins_loaded hook) - to catch cases where activation may fail

**For existing installations:**
- Simply reactivate or update the plugin - upgrades apply automatically
- Check plugin logs (if enabled) to verify successful upgrades

**Current Migration Chain:**
1. `migration-1.0-initial.php` - Creates all base tables (history, templates, schedule, voices)
2. `migration-1.1-add-voices.php` - Adds voice_id and post_quantity columns to templates
3. `migration-1.2-add-featured-images.php` - Adds image_prompt and generate_featured_image columns

**Current Version:** 1.2.0

## Dependencies

- WordPress 5.8+
- PHP 7.4+
- Meow Apps AI Engine plugin (required)

## Security

- All admin actions protected with WordPress nonces and capability checks
- Secure AJAX handlers with referer validation
- Database sanitization and escaping
- Rate limiting via WordPress Cron
