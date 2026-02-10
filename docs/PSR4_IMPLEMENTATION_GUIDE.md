# PSR-4 Implementation Guide

## Quick Start

This guide provides step-by-step instructions for implementing the PSR-4 refactoring plan outlined in `PSR4_REFACTORING_PLAN.md`. Follow these phases in order.

---

## Phase 0: Preparation

### Step 1: Create Directory Structure

Create the new `src/` directory structure in `ai-post-scheduler/`:

```bash
cd ai-post-scheduler
mkdir -p src/Controllers/Admin
mkdir -p src/Services/AI
mkdir -p src/Services/Content
mkdir -p src/Services/Generation
mkdir -p src/Services/Research
mkdir -p src/Repositories
mkdir -p src/Generators
mkdir -p src/Models
mkdir -p src/Interfaces
mkdir -p src/Admin
mkdir -p src/Utilities
mkdir -p src/DataManagement/Export
mkdir -p src/DataManagement/Import
mkdir -p src/Notifications
```

### Step 2: Update composer.json (Root)

Update `/home/runner/work/wp-ai-scheduler/wp-ai-scheduler/composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "AIPS\\": "ai-post-scheduler/src/"
    },
    "classmap": [
      "ai-post-scheduler/includes/"
    ]
  }
}
```

### Step 3: Update composer.json (Plugin)

Update `ai-post-scheduler/composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "AIPS\\": "src/"
    },
    "classmap": [
      "includes/"
    ]
  }
}
```

### Step 4: Create Compatibility Loader

Create `ai-post-scheduler/includes/compatibility-loader.php`:

```php
<?php
/**
 * Backward Compatibility Layer
 * 
 * Provides class aliases for old class names to maintain backward compatibility
 * with third-party code that may reference the old AIPS_* class names.
 * 
 * This file will be maintained for 2-3 versions and then deprecated.
 * 
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// This file will be populated as classes are migrated
// Format: class_alias('AIPS\\Namespace\\NewClassName', 'AIPS_Old_Class_Name');
```

### Step 5: Run Composer

```bash
cd /home/runner/work/wp-ai-scheduler/wp-ai-scheduler
composer dump-autoload
```

---

## Phase 1: Repositories Migration

### Migration Order

Repositories have the fewest dependencies, so we start here.

1. DBManager
2. TemplateRepository
3. ScheduleRepository
4. HistoryRepository
5. ArticleStructureRepository
6. AuthorsRepository
7. AuthorTopicsRepository
8. AuthorTopicLogsRepository
9. VoicesRepository
10. PromptSectionRepository
11. PostReviewRepository
12. FeedbackRepository
13. TrendingTopicsRepository

### Example: Migrating DBManager

**Original file:** `includes/class-aips-db-manager.php`

1. Copy to new location: `src/Repositories/DBManager.php`

2. Add namespace and update class name:
```php
<?php
namespace AIPS\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

class DBManager {
    // ... existing code ...
}
```

3. Update any internal dependencies (use statements):
```php
use AIPS\Utilities\Logger;
```

4. Add backward compatibility alias to `includes/compatibility-loader.php`:
```php
class_alias('AIPS\\Repositories\\DBManager', 'AIPS_DB_Manager');
```

5. Test that the class works with both old and new names

### Example: Migrating TemplateRepository

**Original file:** `includes/class-aips-template-repository.php`

Follow the same pattern:

```php
<?php
namespace AIPS\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateRepository {
    
    private $db_manager;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = new DBManager();
        $this->table_name = $this->db_manager->get_table_name('templates');
    }
    
    // ... rest of code ...
}
```

Add alias:
```php
class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
```

### Testing Repositories

After migrating each repository:

```php
// Test old name still works
$template_repo_old = new AIPS_Template_Repository();
var_dump(get_class($template_repo_old)); // Should show AIPS\Repositories\TemplateRepository

// Test new name works
$template_repo_new = new \AIPS\Repositories\TemplateRepository();
var_dump(get_class($template_repo_new)); // Should show AIPS\Repositories\TemplateRepository

// Test functionality
$templates = $template_repo_new->get_all();
var_dump($templates); // Should return array of templates
```

---

## Phase 2: Models & Interfaces Migration

### Interfaces First

**Migrate:** `includes/interface-aips-generation-context.php`
**To:** `src/Interfaces/GenerationContext.php`

```php
<?php
namespace AIPS\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface GenerationContext {
    public function get_type(): string;
    public function to_array(): array;
    public function get_prompt_data(): array;
}
```

Alias:
```php
class_alias('AIPS\\Interfaces\\GenerationContext', 'AIPS_Generation_Context');
```

### Models

Migrate in order:
1. Config → `src/Models/Config.php`
2. HistoryType → `src/Models/HistoryType.php`
3. HistoryContainer → `src/Models/HistoryContainer.php`
4. TemplateContext → `src/Models/TemplateContext.php`
5. TopicContext → `src/Models/TopicContext.php`
6. TemplateTypeSelector → `src/Models/TemplateTypeSelector.php`
7. ArticleStructureManager → `src/Models/ArticleStructureManager.php`

### Example: Config Migration

**Original:** `includes/class-aips-config.php`
**New:** `src/Models/Config.php`

```php
<?php
namespace AIPS\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Config {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // ... existing code ...
}
```

Alias:
```php
class_alias('AIPS\\Models\\Config', 'AIPS_Config');
```

**Important:** Config is a singleton, so ensure getInstance() pattern still works.

---

## Phase 3: Services Migration

Services have more complex dependencies. Migrate in sub-phases.

### Sub-Phase 3.1: Core Services

1. **Logger** → `src/Services/Logger.php`

```php
<?php
namespace AIPS\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    // ... existing code ...
}
```

Alias: `class_alias('AIPS\\Services\\Logger', 'AIPS_Logger');`

2. **ImageService** → `src/Services/ImageService.php`
3. **HistoryService** → `src/Services/HistoryService.php`
4. **SessionToJson** → `src/Services/SessionToJson.php`

### Sub-Phase 3.2: AI Services

1. **AIService** → `src/Services/AI/AIService.php`

```php
<?php
namespace AIPS\Services\AI;

use AIPS\Services\Logger;
use AIPS\Models\Config;

if (!defined('ABSPATH')) {
    exit;
}

class AIService {
    
    private $logger;
    private $config;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->config = Config::get_instance();
    }
    
    // ... existing code ...
}
```

Alias: `class_alias('AIPS\\Services\\AI\\AIService', 'AIPS_AI_Service');`

2. **EmbeddingsService** → `src/Services/AI/EmbeddingsService.php`
3. **PromptBuilder** → `src/Services/AI/PromptBuilder.php`
4. **ResilienceService** → `src/Services/AI/ResilienceService.php`

### Sub-Phase 3.3: Content Services

1. **ComponentRegenerationService** → `src/Services/Content/ComponentRegenerationService.php`
2. **PostCreator** → `src/Services/Content/PostCreator.php`
3. **TemplateProcessor** → `src/Services/Content/TemplateProcessor.php`
4. **TemplateHelper** → `src/Services/Content/TemplateHelper.php`

### Sub-Phase 3.4: Research Services

1. **ResearchService** → `src/Services/Research/ResearchService.php`
2. **TopicExpansionService** → `src/Services/Research/TopicExpansionService.php`
3. **TopicPenaltyService** → `src/Services/Research/TopicPenaltyService.php`

### Sub-Phase 3.5: Generation Services

1. **SeederService** → `src/Services/SeederService.php`
2. **GenerationLogger** → `src/Services/Generation/GenerationLogger.php`
3. **GenerationSession** → `src/Services/Generation/GenerationSession.php`

### Dependency Resolution Example

When a service depends on other services:

```php
<?php
namespace AIPS\Services\Content;

use AIPS\Services\Logger;
use AIPS\Services\AI\AIService;
use AIPS\Repositories\TemplateRepository;
use AIPS\Models\TemplateContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateProcessor {
    
    private $logger;
    private $ai_service;
    private $template_repo;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->ai_service = new AIService();
        $this->template_repo = new TemplateRepository();
    }
    
    // ... existing code ...
}
```

---

## Phase 4: Generators Migration

Migrate in order:
1. **Generator** → `src/Generators/Generator.php`
2. **AuthorPostGenerator** → `src/Generators/AuthorPostGenerator.php`
3. **AuthorTopicsGenerator** → `src/Generators/AuthorTopicsGenerator.php`
4. **ScheduleProcessor** → `src/Generators/ScheduleProcessor.php`

### Example: Generator Migration

```php
<?php
namespace AIPS\Generators;

use AIPS\Services\Logger;
use AIPS\Services\AI\AIService;
use AIPS\Services\Content\PostCreator;
use AIPS\Repositories\TemplateRepository;
use AIPS\Repositories\ScheduleRepository;

if (!defined('ABSPATH')) {
    exit;
}

class Generator {
    
    private $logger;
    private $ai_service;
    private $post_creator;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->ai_service = new AIService();
        $this->post_creator = new PostCreator();
    }
    
    // ... existing code ...
}
```

Alias: `class_alias('AIPS\\Generators\\Generator', 'AIPS_Generator');`

---

## Phase 5: Controllers Migration

### Core Controllers

1. **AIEditController** → `src/Controllers/AIEditController.php`
2. **DataManagementController** → `src/Controllers/DataManagementController.php`

### Admin Controllers

Move to `src/Controllers/Admin/`:

1. **AuthorsController**
2. **AuthorTopicsController**
3. **CalendarController**
4. **DashboardController**
5. **GeneratedPostsController**
6. **PromptSectionsController**
7. **ResearchController**
8. **ScheduleController**
9. **StructuresController**
10. **TemplatesController**

### Example: Admin Controller Migration

```php
<?php
namespace AIPS\Controllers\Admin;

use AIPS\Services\Logger;
use AIPS\Repositories\TemplateRepository;
use AIPS\Models\Config;

if (!defined('ABSPATH')) {
    exit;
}

class TemplatesController {
    
    private $logger;
    private $template_repo;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->template_repo = new TemplateRepository();
        
        // Register AJAX handlers
        add_action('wp_ajax_aips_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_aips_delete_template', array($this, 'ajax_delete_template'));
    }
    
    // ... existing code ...
}
```

Alias: `class_alias('AIPS\\Controllers\\Admin\\TemplatesController', 'AIPS_Templates_Controller');`

**Important:** Ensure all WordPress action/filter hooks are preserved!

---

## Phase 6: Admin Classes Migration

Migrate to `src/Admin/`:

1. **AdminAssets** → `src/Admin/AdminAssets.php`
2. **Settings** → `src/Admin/Settings.php`
3. **DevTools** → `src/Admin/DevTools.php`
4. **History** → `src/Admin/History.php`
5. **Planner** → `src/Admin/Planner.php`
6. **PostReview** → `src/Admin/PostReview.php`
7. **SeederAdmin** → `src/Admin/SeederAdmin.php`
8. **SystemStatus** → `src/Admin/SystemStatus.php`
9. **Templates** → `src/Admin/Templates.php`
10. **Voices** → `src/Admin/Voices.php`

### Example: Settings Migration

```php
<?php
namespace AIPS\Admin;

use AIPS\Services\Logger;
use AIPS\Models\Config;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_menu_pages() {
        // ... existing code ...
    }
    
    // ... rest of code ...
}
```

Alias: `class_alias('AIPS\\Admin\\Settings', 'AIPS_Settings');`

---

## Phase 7: Utilities & Supporting Classes Migration

### Utilities

1. **IntervalCalculator** → `src/Utilities/IntervalCalculator.php`
2. **Scheduler** → `src/Utilities/Scheduler.php`
3. **Upgrades** → `src/Utilities/Upgrades.php`

### Data Management - Export

1. **Export** → `src/DataManagement/Export/ExportHandler.php`
2. **ExportJson** → `src/DataManagement/Export/JsonExporter.php`
3. **ExportMySQL** → `src/DataManagement/Export/MySQLExporter.php`

### Data Management - Import

1. **Import** → `src/DataManagement/Import/ImportHandler.php`
2. **ImportJson** → `src/DataManagement/Import/JsonImporter.php`
3. **ImportMySQL** → `src/DataManagement/Import/MySQLImporter.php`

### Notifications

1. **PostReviewNotifications** → `src/Notifications/PostReviewNotifications.php`

### Example: Scheduler Migration

```php
<?php
namespace AIPS\Utilities;

use AIPS\Services\Logger;
use AIPS\Generators\Generator;
use AIPS\Generators\AuthorPostGenerator;
use AIPS\Generators\AuthorTopicsGenerator;

if (!defined('ABSPATH')) {
    exit;
}

class Scheduler {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new Logger();
        
        // Register cron hooks
        add_action('aips_generate_scheduled_posts', array($this, 'process_scheduled_posts'));
        add_action('aips_generate_author_topics', array($this, 'generate_author_topics'));
        add_action('aips_generate_author_posts', array($this, 'generate_author_posts'));
    }
    
    // ... existing code ...
}
```

Alias: `class_alias('AIPS\\Utilities\\Scheduler', 'AIPS_Scheduler');`

---

## Phase 8: Main Plugin File Update

### Update ai-post-scheduler.php

Replace the custom autoloader with Composer's PSR-4 autoloader:

**Old code:**
```php
private function includes() {
    require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
    AIPS_Autoloader::register();
}
```

**New code:**
```php
private function includes() {
    // Load Composer autoloader
    if (file_exists(AIPS_PLUGIN_DIR . 'vendor/autoload.php')) {
        require_once AIPS_PLUGIN_DIR . 'vendor/autoload.php';
    }
    
    // Load backward compatibility layer
    require_once AIPS_PLUGIN_DIR . 'includes/compatibility-loader.php';
}
```

### Update Class Instantiations

Update the init() method to use namespaced classes:

```php
use AIPS\Admin\Settings;
use AIPS\Admin\AdminAssets;
use AIPS\Utilities\Scheduler;
use AIPS\Utilities\Upgrades;
use AIPS\Controllers\Admin\DashboardController;
// ... etc

public function init() {
    $settings = new Settings();
    $assets = new AdminAssets();
    $scheduler = new Scheduler();
    // ... etc
}
```

### Test Plugin Activation

```bash
# Deactivate and reactivate plugin
wp plugin deactivate ai-post-scheduler
wp plugin activate ai-post-scheduler

# Check for errors
wp plugin list
```

---

## Phase 9: Complete Compatibility Layer

### Update includes/compatibility-loader.php

Add all class aliases:

```php
<?php
/**
 * Backward Compatibility Layer
 * 
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Repositories
class_alias('AIPS\\Repositories\\DBManager', 'AIPS_DB_Manager');
class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
class_alias('AIPS\\Repositories\\ScheduleRepository', 'AIPS_Schedule_Repository');
class_alias('AIPS\\Repositories\\HistoryRepository', 'AIPS_History_Repository');
class_alias('AIPS\\Repositories\\ArticleStructureRepository', 'AIPS_Article_Structure_Repository');
class_alias('AIPS\\Repositories\\AuthorsRepository', 'AIPS_Authors_Repository');
class_alias('AIPS\\Repositories\\AuthorTopicsRepository', 'AIPS_Author_Topics_Repository');
class_alias('AIPS\\Repositories\\AuthorTopicLogsRepository', 'AIPS_Author_Topic_Logs_Repository');
class_alias('AIPS\\Repositories\\VoicesRepository', 'AIPS_Voices_Repository');
class_alias('AIPS\\Repositories\\PromptSectionRepository', 'AIPS_Prompt_Section_Repository');
class_alias('AIPS\\Repositories\\PostReviewRepository', 'AIPS_Post_Review_Repository');
class_alias('AIPS\\Repositories\\FeedbackRepository', 'AIPS_Feedback_Repository');
class_alias('AIPS\\Repositories\\TrendingTopicsRepository', 'AIPS_Trending_Topics_Repository');

// Interfaces
class_alias('AIPS\\Interfaces\\GenerationContext', 'AIPS_Generation_Context');

// Models
class_alias('AIPS\\Models\\Config', 'AIPS_Config');
class_alias('AIPS\\Models\\HistoryType', 'AIPS_History_Type');
class_alias('AIPS\\Models\\HistoryContainer', 'AIPS_History_Container');
class_alias('AIPS\\Models\\TemplateContext', 'AIPS_Template_Context');
class_alias('AIPS\\Models\\TopicContext', 'AIPS_Topic_Context');
class_alias('AIPS\\Models\\TemplateTypeSelector', 'AIPS_Template_Type_Selector');
class_alias('AIPS\\Models\\ArticleStructureManager', 'AIPS_Article_Structure_Manager');

// Services - Core
class_alias('AIPS\\Services\\Logger', 'AIPS_Logger');
class_alias('AIPS\\Services\\ImageService', 'AIPS_Image_Service');
class_alias('AIPS\\Services\\HistoryService', 'AIPS_History_Service');
class_alias('AIPS\\Services\\SessionToJson', 'AIPS_Session_To_Json');
class_alias('AIPS\\Services\\SeederService', 'AIPS_Seeder_Service');

// Services - AI
class_alias('AIPS\\Services\\AI\\AIService', 'AIPS_AI_Service');
class_alias('AIPS\\Services\\AI\\EmbeddingsService', 'AIPS_Embeddings_Service');
class_alias('AIPS\\Services\\AI\\PromptBuilder', 'AIPS_Prompt_Builder');
class_alias('AIPS\\Services\\AI\\ResilienceService', 'AIPS_Resilience_Service');

// Services - Content
class_alias('AIPS\\Services\\Content\\ComponentRegenerationService', 'AIPS_Component_Regeneration_Service');
class_alias('AIPS\\Services\\Content\\PostCreator', 'AIPS_Post_Creator');
class_alias('AIPS\\Services\\Content\\TemplateProcessor', 'AIPS_Template_Processor');
class_alias('AIPS\\Services\\Content\\TemplateHelper', 'AIPS_Template_Helper');

// Services - Research
class_alias('AIPS\\Services\\Research\\ResearchService', 'AIPS_Research_Service');
class_alias('AIPS\\Services\\Research\\TopicExpansionService', 'AIPS_Topic_Expansion_Service');
class_alias('AIPS\\Services\\Research\\TopicPenaltyService', 'AIPS_Topic_Penalty_Service');

// Services - Generation
class_alias('AIPS\\Services\\Generation\\GenerationLogger', 'AIPS_Generation_Logger');
class_alias('AIPS\\Services\\Generation\\GenerationSession', 'AIPS_Generation_Session');

// Generators
class_alias('AIPS\\Generators\\Generator', 'AIPS_Generator');
class_alias('AIPS\\Generators\\AuthorPostGenerator', 'AIPS_Author_Post_Generator');
class_alias('AIPS\\Generators\\AuthorTopicsGenerator', 'AIPS_Author_Topics_Generator');
class_alias('AIPS\\Generators\\ScheduleProcessor', 'AIPS_Schedule_Processor');

// Controllers - Core
class_alias('AIPS\\Controllers\\AIEditController', 'AIPS_AI_Edit_Controller');
class_alias('AIPS\\Controllers\\DataManagementController', 'AIPS_Data_Management');

// Controllers - Admin
class_alias('AIPS\\Controllers\\Admin\\AuthorsController', 'AIPS_Authors_Controller');
class_alias('AIPS\\Controllers\\Admin\\AuthorTopicsController', 'AIPS_Author_Topics_Controller');
class_alias('AIPS\\Controllers\\Admin\\CalendarController', 'AIPS_Calendar_Controller');
class_alias('AIPS\\Controllers\\Admin\\DashboardController', 'AIPS_Dashboard_Controller');
class_alias('AIPS\\Controllers\\Admin\\GeneratedPostsController', 'AIPS_Generated_Posts_Controller');
class_alias('AIPS\\Controllers\\Admin\\PromptSectionsController', 'AIPS_Prompt_Sections_Controller');
class_alias('AIPS\\Controllers\\Admin\\ResearchController', 'AIPS_Research_Controller');
class_alias('AIPS\\Controllers\\Admin\\ScheduleController', 'AIPS_Schedule_Controller');
class_alias('AIPS\\Controllers\\Admin\\StructuresController', 'AIPS_Structures_Controller');
class_alias('AIPS\\Controllers\\Admin\\TemplatesController', 'AIPS_Templates_Controller');

// Admin
class_alias('AIPS\\Admin\\AdminAssets', 'AIPS_Admin_Assets');
class_alias('AIPS\\Admin\\Settings', 'AIPS_Settings');
class_alias('AIPS\\Admin\\DevTools', 'AIPS_Dev_Tools');
class_alias('AIPS\\Admin\\History', 'AIPS_History');
class_alias('AIPS\\Admin\\Planner', 'AIPS_Planner');
class_alias('AIPS\\Admin\\PostReview', 'AIPS_Post_Review');
class_alias('AIPS\\Admin\\SeederAdmin', 'AIPS_Seeder_Admin');
class_alias('AIPS\\Admin\\SystemStatus', 'AIPS_System_Status');
class_alias('AIPS\\Admin\\Templates', 'AIPS_Templates');
class_alias('AIPS\\Admin\\Voices', 'AIPS_Voices');

// Utilities
class_alias('AIPS\\Utilities\\IntervalCalculator', 'AIPS_Interval_Calculator');
class_alias('AIPS\\Utilities\\Scheduler', 'AIPS_Scheduler');
class_alias('AIPS\\Utilities\\Upgrades', 'AIPS_Upgrades');

// Data Management - Export
class_alias('AIPS\\DataManagement\\Export\\ExportHandler', 'AIPS_Data_Management_Export');
class_alias('AIPS\\DataManagement\\Export\\JsonExporter', 'AIPS_Data_Management_Export_Json');
class_alias('AIPS\\DataManagement\\Export\\MySQLExporter', 'AIPS_Data_Management_Export_MySQL');

// Data Management - Import
class_alias('AIPS\\DataManagement\\Import\\ImportHandler', 'AIPS_Data_Management_Import');
class_alias('AIPS\\DataManagement\\Import\\JsonImporter', 'AIPS_Data_Management_Import_Json');
class_alias('AIPS\\DataManagement\\Import\\MySQLImporter', 'AIPS_Data_Management_Import_MySQL');

// Notifications
class_alias('AIPS\\Notifications\\PostReviewNotifications', 'AIPS_Post_Review_Notifications');

// Author Topics Scheduler (special case)
class_alias('AIPS\\Utilities\\Scheduler', 'AIPS_Author_Topics_Scheduler');
```

---

## Phase 10: Testing & Validation

### Update Test Bootstrap

Update `ai-post-scheduler/tests/bootstrap.php`:

```php
<?php
// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load compatibility layer
require_once dirname(__DIR__) . '/includes/compatibility-loader.php';

// ... rest of bootstrap code ...
```

### Update Test Classes

Update test files to use new namespaces:

```php
<?php
use AIPS\Repositories\TemplateRepository;
use AIPS\Services\AI\AIService;
use AIPS\Models\Config;

class Test_Template_Repository extends WP_UnitTestCase {
    
    private $repo;
    
    public function setUp(): void {
        parent::setUp();
        $this->repo = new TemplateRepository();
    }
    
    // ... tests ...
}
```

### Run Tests

```bash
cd /home/runner/work/wp-ai-scheduler/wp-ai-scheduler
composer test

# Run with verbose output
composer test:verbose

# Generate coverage report
composer test:coverage
```

### Manual Testing Checklist

- [ ] Plugin activates without errors
- [ ] Admin menu appears correctly
- [ ] Dashboard loads
- [ ] Templates page loads and can create/edit templates
- [ ] Schedule page loads and can create schedules
- [ ] Content generation works
- [ ] AJAX requests work
- [ ] Settings save correctly
- [ ] Import/Export functions work
- [ ] Cron jobs are scheduled
- [ ] Post Review notifications work

---

## Phase 11: Documentation & Cleanup

### Create ARCHITECTURE.md

Document the new structure:

```markdown
# Architecture

## Directory Structure

The plugin follows PSR-4 autoloading standards with the namespace `AIPS\`.

### src/
- **Controllers/** - Handle admin pages and AJAX requests
  - **Admin/** - Admin-specific controllers
- **Services/** - Business logic layer
  - **AI/** - AI-related services
  - **Content/** - Content generation services
  - **Generation/** - Generation tracking
  - **Research/** - Research and topic services
- **Repositories/** - Database access layer
- **Generators/** - Content generation engines
- **Models/** - Data structures and configuration
- **Interfaces/** - PHP interfaces
- **Admin/** - WordPress admin integration
- **Utilities/** - Helper classes and utilities
- **DataManagement/** - Import/Export functionality
- **Notifications/** - Notification system

## Class Naming

- Namespaced classes use PascalCase: `TemplateRepository`
- Namespace follows directory structure: `AIPS\Repositories\TemplateRepository`
- Old classes use underscores: `AIPS_Template_Repository` (deprecated)

## Backward Compatibility

The `includes/compatibility-loader.php` file provides class aliases for old class names.
This allows existing code to continue using `AIPS_*` class names.

## Dependencies

Classes use dependency injection where possible. Services are typically injected
through the constructor.
```

### Update README.md

Add PSR-4 section to existing README.

### Update CHANGELOG.md

```markdown
## [2.0.0] - 2026-02-10

### Changed
- **BREAKING**: Migrated to PSR-4 autoloading with namespace `AIPS\`
- Reorganized 77 classes into structured `src/` directory
- Replaced custom autoloader with Composer's PSR-4 autoloader
- Added backward compatibility layer for old class names

### Migration Guide
For developers:
- Update class instantiations to use new namespaced classes
- Add `use` statements at top of files
- Old class names still work via compatibility layer
- See `/docs/PSR4_IMPLEMENTATION_GUIDE.md` for details

### Deprecated
- Old `AIPS_*` class names (use `AIPS\Namespace\ClassName` instead)
- Custom autoloader (`class-aips-autoloader.php`)
```

### Cleanup Tasks

1. **Keep compatibility layer** (do not remove for now)
2. **Keep old files** in `includes/` temporarily
3. **Update version** in `ai-post-scheduler.php` to 2.0.0
4. **Run code quality tools**:
   ```bash
   vendor/bin/phpcs --standard=WordPress ai-post-scheduler/src/
   vendor/bin/phpstan analyze ai-post-scheduler/src/
   ```

---

## Common Issues & Solutions

### Issue: Class not found

**Symptom:** `Fatal error: Class 'AIPS_Template_Repository' not found`

**Solution:** 
1. Check compatibility-loader.php has the alias
2. Run `composer dump-autoload`
3. Verify the class file exists in src/

### Issue: Undefined use statement

**Symptom:** `Class 'Logger' not found in TemplateProcessor.php`

**Solution:** Add use statement:
```php
use AIPS\Services\Logger;
```

### Issue: Circular dependency

**Symptom:** `Cannot instantiate class, circular dependency detected`

**Solution:** Use dependency injection instead of creating instances in constructor

### Issue: Hooks not firing

**Symptom:** Admin pages don't load, AJAX doesn't work

**Solution:** Ensure hooks are registered in __construct() method, not in static context

### Issue: Tests failing

**Symptom:** `Class not found` in test files

**Solution:** Update test bootstrap to load Composer autoloader before WordPress

---

## Rollback Procedure

If critical issues arise:

1. **Revert to previous commit:**
   ```bash
   git revert HEAD
   git push
   ```

2. **Restore from tag:**
   ```bash
   git checkout v1.7.0
   ```

3. **Emergency fix:**
   - Comment out PSR-4 autoloader in ai-post-scheduler.php
   - Uncomment custom autoloader
   - Run `composer dump-autoload`

---

## Version Strategy

### v2.0.0-alpha.1 (First release with PSR-4)
- Include compatibility layer
- Monitor for issues
- Gather feedback

### v2.0.0-beta.1 (After testing period)
- Add deprecation notices to old class usage
- Update documentation

### v2.0.0 (Stable release)
- Remove deprecation notices
- Keep compatibility layer (for 2-3 versions)

### v3.0.0 (Future)
- Remove compatibility layer
- Remove old files from includes/
- Pure PSR-4 structure

---

## Success Metrics

After completing all phases, verify:

- [ ] All 77 classes migrated to PSR-4
- [ ] All tests pass
- [ ] Plugin activates without errors
- [ ] Admin pages load correctly
- [ ] Content generation works
- [ ] No PHP warnings or notices
- [ ] Performance is maintained or improved
- [ ] Autoload time < 100ms
- [ ] Memory usage within 10% of before
- [ ] Code passes PHPCS WordPress standards
- [ ] Documentation is complete and accurate

---

## Getting Help

If you encounter issues during implementation:

1. Check this guide for common issues
2. Review the original plan: `PSR4_REFACTORING_PLAN.md`
3. Check Git history for similar migrations
4. Test in isolation (single class at a time)
5. Use `var_dump(class_exists('ClassName'))` to debug

---

**Last Updated:** 2026-02-10
**Version:** 1.0
**Status:** Implementation Guide
