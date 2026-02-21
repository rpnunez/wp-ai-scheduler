# PSR-4 Class Mapping Reference

Quick reference guide for migrating from old class names to new PSR-4 namespaced classes.

## How to Use This Guide

1. Find your old class name in the "Old Class" column
2. Use the corresponding "New Class" with proper use statement
3. Old class names will continue to work via compatibility layer

## Repository Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_DB_Manager` | `DBManager` | `AIPS\Repositories` | `src/Repositories/DBManager.php` |
| `AIPS_Template_Repository` | `TemplateRepository` | `AIPS\Repositories` | `src/Repositories/TemplateRepository.php` |
| `AIPS_Schedule_Repository` | `ScheduleRepository` | `AIPS\Repositories` | `src/Repositories/ScheduleRepository.php` |
| `AIPS_History_Repository` | `HistoryRepository` | `AIPS\Repositories` | `src/Repositories/HistoryRepository.php` |
| `AIPS_Article_Structure_Repository` | `ArticleStructureRepository` | `AIPS\Repositories` | `src/Repositories/ArticleStructureRepository.php` |
| `AIPS_Authors_Repository` | `AuthorsRepository` | `AIPS\Repositories` | `src/Repositories/AuthorsRepository.php` |
| `AIPS_Author_Topics_Repository` | `AuthorTopicsRepository` | `AIPS\Repositories` | `src/Repositories/AuthorTopicsRepository.php` |
| `AIPS_Author_Topic_Logs_Repository` | `AuthorTopicLogsRepository` | `AIPS\Repositories` | `src/Repositories/AuthorTopicLogsRepository.php` |
| `AIPS_Voices_Repository` | `VoicesRepository` | `AIPS\Repositories` | `src/Repositories/VoicesRepository.php` |
| `AIPS_Prompt_Section_Repository` | `PromptSectionRepository` | `AIPS\Repositories` | `src/Repositories/PromptSectionRepository.php` |
| `AIPS_Post_Review_Repository` | `PostReviewRepository` | `AIPS\Repositories` | `src/Repositories/PostReviewRepository.php` |
| `AIPS_Feedback_Repository` | `FeedbackRepository` | `AIPS\Repositories` | `src/Repositories/FeedbackRepository.php` |
| `AIPS_Trending_Topics_Repository` | `TrendingTopicsRepository` | `AIPS\Repositories` | `src/Repositories/TrendingTopicsRepository.php` |

### Usage Example
```php
// Old way
$repo = new AIPS_Template_Repository();

// New way
use AIPS\Repositories\TemplateRepository;
$repo = new TemplateRepository();
```

---

## Model Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Config` | `Config` | `AIPS\Models` | `src/Models/Config.php` |
| `AIPS_History_Type` | `HistoryType` | `AIPS\Models` | `src/Models/HistoryType.php` |
| `AIPS_History_Container` | `HistoryContainer` | `AIPS\Models` | `src/Models/HistoryContainer.php` |
| `AIPS_Template_Context` | `TemplateContext` | `AIPS\Models` | `src/Models/TemplateContext.php` |
| `AIPS_Topic_Context` | `TopicContext` | `AIPS\Models` | `src/Models/TopicContext.php` |
| `AIPS_Template_Type_Selector` | `TemplateTypeSelector` | `AIPS\Models` | `src/Models/TemplateTypeSelector.php` |
| `AIPS_Article_Structure_Manager` | `ArticleStructureManager` | `AIPS\Models` | `src/Models/ArticleStructureManager.php` |

### Usage Example
```php
// Old way
$config = AIPS_Config::get_instance();

// New way
use AIPS\Models\Config;
$config = Config::get_instance();
```

---

## Interface Classes

| Old Interface | New Interface | Namespace | File Location |
|---------------|---------------|-----------|---------------|
| `AIPS_Generation_Context` | `GenerationContext` | `AIPS\Interfaces` | `src/Interfaces/GenerationContext.php` |

### Usage Example
```php
// Old way
class MyContext implements AIPS_Generation_Context { }

// New way
use AIPS\Interfaces\GenerationContext;
class MyContext implements GenerationContext { }
```

---

## Service Classes

### Core Services

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Logger` | `Logger` | `AIPS\Services` | `src/Services/Logger.php` |
| `AIPS_Image_Service` | `ImageService` | `AIPS\Services` | `src/Services/ImageService.php` |
| `AIPS_History_Service` | `HistoryService` | `AIPS\Services` | `src/Services/HistoryService.php` |
| `AIPS_Session_To_Json` | `SessionToJson` | `AIPS\Services` | `src/Services/SessionToJson.php` |
| `AIPS_Seeder_Service` | `SeederService` | `AIPS\Services` | `src/Services/SeederService.php` |

### AI Services

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_AI_Service` | `AIService` | `AIPS\Services\AI` | `src/Services/AI/AIService.php` |
| `AIPS_Embeddings_Service` | `EmbeddingsService` | `AIPS\Services\AI` | `src/Services/AI/EmbeddingsService.php` |
| `AIPS_Prompt_Builder` | `PromptBuilder` | `AIPS\Services\AI` | `src/Services/AI/PromptBuilder.php` |
| `AIPS_Resilience_Service` | `ResilienceService` | `AIPS\Services\AI` | `src/Services/AI/ResilienceService.php` |

### Content Services

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Component_Regeneration_Service` | `ComponentRegenerationService` | `AIPS\Services\Content` | `src/Services/Content/ComponentRegenerationService.php` |
| `AIPS_Post_Creator` | `PostCreator` | `AIPS\Services\Content` | `src/Services/Content/PostCreator.php` |
| `AIPS_Template_Processor` | `TemplateProcessor` | `AIPS\Services\Content` | `src/Services/Content/TemplateProcessor.php` |
| `AIPS_Template_Helper` | `TemplateHelper` | `AIPS\Services\Content` | `src/Services/Content/TemplateHelper.php` |

### Research Services

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Research_Service` | `ResearchService` | `AIPS\Services\Research` | `src/Services/Research/ResearchService.php` |
| `AIPS_Topic_Expansion_Service` | `TopicExpansionService` | `AIPS\Services\Research` | `src/Services/Research/TopicExpansionService.php` |
| `AIPS_Topic_Penalty_Service` | `TopicPenaltyService` | `AIPS\Services\Research` | `src/Services/Research/TopicPenaltyService.php` |

### Generation Services

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Generation_Logger` | `GenerationLogger` | `AIPS\Services\Generation` | `src/Services/Generation/GenerationLogger.php` |
| `AIPS_Generation_Session` | `GenerationSession` | `AIPS\Services\Generation` | `src/Services/Generation/GenerationSession.php` |

### Usage Example
```php
// Old way
$logger = new AIPS_Logger();
$ai = new AIPS_AI_Service();

// New way
use AIPS\Services\Logger;
use AIPS\Services\AI\AIService;

$logger = new Logger();
$ai = new AIService();
```

---

## Generator Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Generator` | `Generator` | `AIPS\Generators` | `src/Generators/Generator.php` |
| `AIPS_Author_Post_Generator` | `AuthorPostGenerator` | `AIPS\Generators` | `src/Generators/AuthorPostGenerator.php` |
| `AIPS_Author_Topics_Generator` | `AuthorTopicsGenerator` | `AIPS\Generators` | `src/Generators/AuthorTopicsGenerator.php` |
| `AIPS_Schedule_Processor` | `ScheduleProcessor` | `AIPS\Generators` | `src/Generators/ScheduleProcessor.php` |

### Usage Example
```php
// Old way
$generator = new AIPS_Generator();

// New way
use AIPS\Generators\Generator;
$generator = new Generator();
```

---

## Controller Classes

### Core Controllers

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_AI_Edit_Controller` | `AIEditController` | `AIPS\Controllers` | `src/Controllers/AIEditController.php` |
| `AIPS_Data_Management` | `DataManagementController` | `AIPS\Controllers` | `src/Controllers/DataManagementController.php` |

### Admin Controllers

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Authors_Controller` | `AuthorsController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/AuthorsController.php` |
| `AIPS_Author_Topics_Controller` | `AuthorTopicsController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/AuthorTopicsController.php` |
| `AIPS_Calendar_Controller` | `CalendarController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/CalendarController.php` |
| `AIPS_Dashboard_Controller` | `DashboardController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/DashboardController.php` |
| `AIPS_Generated_Posts_Controller` | `GeneratedPostsController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/GeneratedPostsController.php` |
| `AIPS_Prompt_Sections_Controller` | `PromptSectionsController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/PromptSectionsController.php` |
| `AIPS_Research_Controller` | `ResearchController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/ResearchController.php` |
| `AIPS_Schedule_Controller` | `ScheduleController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/ScheduleController.php` |
| `AIPS_Structures_Controller` | `StructuresController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/StructuresController.php` |
| `AIPS_Templates_Controller` | `TemplatesController` | `AIPS\Controllers\Admin` | `src/Controllers/Admin/TemplatesController.php` |

### Usage Example
```php
// Old way
$controller = new AIPS_Templates_Controller();

// New way
use AIPS\Controllers\Admin\TemplatesController;
$controller = new TemplatesController();
```

---

## Admin Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Admin_Assets` | `AdminAssets` | `AIPS\Admin` | `src/Admin/AdminAssets.php` |
| `AIPS_Settings` | `Settings` | `AIPS\Admin` | `src/Admin/Settings.php` |
| `AIPS_Dev_Tools` | `DevTools` | `AIPS\Admin` | `src/Admin/DevTools.php` |
| `AIPS_History` | `History` | `AIPS\Admin` | `src/Admin/History.php` |
| `AIPS_Planner` | `Planner` | `AIPS\Admin` | `src/Admin/Planner.php` |
| `AIPS_Post_Review` | `PostReview` | `AIPS\Admin` | `src/Admin/PostReview.php` |
| `AIPS_Seeder_Admin` | `SeederAdmin` | `AIPS\Admin` | `src/Admin/SeederAdmin.php` |
| `AIPS_System_Status` | `SystemStatus` | `AIPS\Admin` | `src/Admin/SystemStatus.php` |
| `AIPS_Templates` | `Templates` | `AIPS\Admin` | `src/Admin/Templates.php` |
| `AIPS_Voices` | `Voices` | `AIPS\Admin` | `src/Admin/Voices.php` |

### Usage Example
```php
// Old way
$settings = new AIPS_Settings();

// New way
use AIPS\Admin\Settings;
$settings = new Settings();
```

---

## Utility Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Interval_Calculator` | `IntervalCalculator` | `AIPS\Utilities` | `src/Utilities/IntervalCalculator.php` |
| `AIPS_Scheduler` | `Scheduler` | `AIPS\Utilities` | `src/Utilities/Scheduler.php` |
| `AIPS_Author_Topics_Scheduler` | `Scheduler` | `AIPS\Utilities` | `src/Utilities/Scheduler.php` (merged) |
| `AIPS_Upgrades` | `Upgrades` | `AIPS\Utilities` | `src/Utilities/Upgrades.php` |

### Usage Example
```php
// Old way
$scheduler = new AIPS_Scheduler();

// New way
use AIPS\Utilities\Scheduler;
$scheduler = new Scheduler();
```

---

## Data Management Classes

### Export Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Data_Management_Export` | `ExportHandler` | `AIPS\DataManagement\Export` | `src/DataManagement/Export/ExportHandler.php` |
| `AIPS_Data_Management_Export_Json` | `JsonExporter` | `AIPS\DataManagement\Export` | `src/DataManagement/Export/JsonExporter.php` |
| `AIPS_Data_Management_Export_MySQL` | `MySQLExporter` | `AIPS\DataManagement\Export` | `src/DataManagement/Export/MySQLExporter.php` |

### Import Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Data_Management_Import` | `ImportHandler` | `AIPS\DataManagement\Import` | `src/DataManagement/Import/ImportHandler.php` |
| `AIPS_Data_Management_Import_Json` | `JsonImporter` | `AIPS\DataManagement\Import` | `src/DataManagement/Import/JsonImporter.php` |
| `AIPS_Data_Management_Import_MySQL` | `MySQLImporter` | `AIPS\DataManagement\Import` | `src/DataManagement/Import/MySQLImporter.php` |

### Usage Example
```php
// Old way
$exporter = new AIPS_Data_Management_Export_Json();

// New way
use AIPS\DataManagement\Export\JsonExporter;
$exporter = new JsonExporter();
```

---

## Notification Classes

| Old Class | New Class | Namespace | File Location |
|-----------|-----------|-----------|---------------|
| `AIPS_Post_Review_Notifications` | `PostReviewNotifications` | `AIPS\Notifications` | `src/Notifications/PostReviewNotifications.php` |

### Usage Example
```php
// Old way
$notifier = new AIPS_Post_Review_Notifications();

// New way
use AIPS\Notifications\PostReviewNotifications;
$notifier = new PostReviewNotifications();
```

---

## Deprecated Classes

These classes will be removed in future versions:

| Class | Status | Removal Version |
|-------|--------|-----------------|
| `AIPS_Autoloader` | Deprecated | v3.0.0 |

---

## Migration Patterns

### Pattern 1: Simple Class Instantiation

```php
// Before
$logger = new AIPS_Logger();
$logger->log('Message');

// After
use AIPS\Services\Logger;

$logger = new Logger();
$logger->log('Message');
```

### Pattern 2: Multiple Dependencies

```php
// Before
$ai = new AIPS_AI_Service();
$template_repo = new AIPS_Template_Repository();
$processor = new AIPS_Template_Processor();

// After
use AIPS\Services\AI\AIService;
use AIPS\Repositories\TemplateRepository;
use AIPS\Services\Content\TemplateProcessor;

$ai = new AIService();
$template_repo = new TemplateRepository();
$processor = new TemplateProcessor();
```

### Pattern 3: Type Hints

```php
// Before
public function process_template(AIPS_Template_Context $context) {
    // ...
}

// After
use AIPS\Models\TemplateContext;

public function process_template(TemplateContext $context) {
    // ...
}
```

### Pattern 4: Static Methods

```php
// Before
$config = AIPS_Config::get_instance();

// After
use AIPS\Models\Config;

$config = Config::get_instance();
```

### Pattern 5: Interface Implementation

```php
// Before
class MyGenerator implements AIPS_Generation_Context {
    // ...
}

// After
use AIPS\Interfaces\GenerationContext;

class MyGenerator implements GenerationContext {
    // ...
}
```

---

## Common Migration Scenarios

### Scenario 1: Migrating a Service File

**Step 1:** Add namespace at top of file
```php
<?php
namespace AIPS\Services;

if (!defined('ABSPATH')) {
    exit;
}
```

**Step 2:** Add use statements for dependencies
```php
use AIPS\Repositories\TemplateRepository;
use AIPS\Models\Config;
```

**Step 3:** Update class name (remove AIPS_ prefix)
```php
// Before
class AIPS_My_Service {

// After
class MyService {
```

### Scenario 2: Updating a Controller with AJAX

```php
<?php
namespace AIPS\Controllers\Admin;

use AIPS\Services\Logger;
use AIPS\Repositories\TemplateRepository;

if (!defined('ABSPATH')) {
    exit;
}

class TemplatesController {
    
    private $logger;
    private $repo;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->repo = new TemplateRepository();
        
        // AJAX hooks still work the same way
        add_action('wp_ajax_save_template', array($this, 'ajax_save'));
    }
    
    public function ajax_save() {
        // Implementation
    }
}
```

### Scenario 3: Updating Template Files

Template files that instantiate classes need updating:

```php
<!-- Before -->
<?php
$controller = new AIPS_Templates_Controller();
$templates = $controller->get_templates();
?>

<!-- After -->
<?php
use AIPS\Controllers\Admin\TemplatesController;

$controller = new TemplatesController();
$templates = $controller->get_templates();
?>
```

---

## Frequently Asked Questions

### Q: Do I need to update my code immediately?

**A:** No. The compatibility layer ensures old class names continue to work. However, you should update to use the new namespaced classes for future-proofing.

### Q: When will old class names stop working?

**A:** Old class names will be supported through v2.x releases. They will be deprecated in v3.0.0.

### Q: How do I know if I'm using old class names?

**A:** Search your codebase for `AIPS_` class names. Any class starting with `AIPS_` is an old name.

### Q: Can I mix old and new class names?

**A:** Yes, both work simultaneously. The compatibility layer ensures they reference the same classes.

### Q: Do WordPress hooks still work?

**A:** Yes, all WordPress action and filter hooks continue to work exactly as before.

### Q: What about serialized data in the database?

**A:** The compatibility layer handles this. Serialized old class names are automatically aliased to new classes.

---

## Quick Reference Card

### Class Name Conversion Rules

1. **Remove `AIPS_` prefix** → `AIPS_Logger` becomes `Logger`
2. **Convert to PascalCase** → `AIPS_Template_Repository` becomes `TemplateRepository`
3. **Add namespace** → Add appropriate `use` statement
4. **Update file location** → `includes/class-aips-*.php` → `src/*/*.php`

### Namespace Mapping

| Old Prefix | New Namespace |
|------------|---------------|
| `AIPS_*_Repository` | `AIPS\Repositories\*` |
| `AIPS_*_Service` | `AIPS\Services\*` (or subnamespace) |
| `AIPS_*_Controller` | `AIPS\Controllers\*` (or `\Admin`) |
| `AIPS_*_Generator` | `AIPS\Generators\*` |
| (Models) | `AIPS\Models\*` |
| (Admin) | `AIPS\Admin\*` |
| (Utilities) | `AIPS\Utilities\*` |

---

## Additional Resources

- **Full Plan:** `docs/psr-4-refactor/PSR4_REFACTORING_PLAN.md`
- **Implementation Guide:** `docs/psr-4-refactor/PSR4_IMPLEMENTATION_GUIDE.md`
- **Checklist:** `docs/psr-4-refactor/PSR4_MIGRATION_CHECKLIST.md`
- **Compatibility Layer:** `includes/compatibility-loader.php`

---

**Last Updated:** 2026-02-10
**Plugin Version:** 2.0.0
**Status:** Reference Document
