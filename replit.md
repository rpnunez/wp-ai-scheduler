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
│   ├── class-aips-templates.php   # Template CRUD operations
│   ├── class-aips-generator.php   # AI Engine integration
│   ├── class-aips-scheduler.php   # WordPress Cron scheduling
│   ├── class-aips-history.php     # Generation history tracking
│   └── class-aips-logger.php      # Logging system
└── templates/
    └── admin/
        ├── dashboard.php          # Main dashboard view
        ├── templates.php          # Template management view
        ├── schedule.php           # Schedule management view
        ├── history.php            # Generation history view
        └── settings.php           # Plugin settings view
```

## Key Features

1. **Template System**: Create and manage AI prompt templates with dynamic variables
2. **Scheduling**: Flexible scheduling options (hourly, 6h, 12h, daily, weekly)
3. **AI Engine Integration**: Uses Meow Apps AI Engine for content generation
4. **History Tracking**: Full audit trail of all generated posts
5. **Error Handling**: Logging and retry capabilities for failed generations

## Database Tables

The plugin creates three custom tables:
- `{prefix}_aips_templates` - Stores prompt templates
- `{prefix}_aips_schedule` - Stores scheduling configuration
- `{prefix}_aips_history` - Stores generation history

## Template Variables

Available in prompts:
- `{{date}}`, `{{year}}`, `{{month}}`, `{{day}}`, `{{time}}`
- `{{site_name}}`, `{{site_description}}`
- `{{random_number}}`

## Installation

1. Copy the `ai-post-scheduler` folder to WordPress `/wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Ensure Meow Apps AI Engine is installed and configured
4. Navigate to "AI Post Scheduler" in the admin menu

## Dependencies

- WordPress 5.8+
- PHP 7.4+
- Meow Apps AI Engine plugin (required)
