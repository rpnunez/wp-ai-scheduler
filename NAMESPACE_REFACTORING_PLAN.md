# Plugin Namespace Refactoring Plan

## Executive Summary

This document outlines a comprehensive plan to refactor the AI Post Scheduler WordPress plugin from a traditional WordPress plugin structure to a modern PHP architecture using namespaces, PSR-4 autoloading, and improved organization. This will eliminate 50+ `require_once` calls and create a more maintainable, scalable codebase.

**Current State:**
- 70+ class files in a flat `/includes/` directory
- All files prefixed with `class-aips-` or `interface-aips-`
- All classes prefixed with `AIPS_`
- 50+ manual `require_once` statements in main plugin file
- Uses Composer classmap autoloading
- PHP 8.2+ requirement (modern PHP standards)

**Target State:**
- Organized namespace structure: `AIPostScheduler\{Domain}\{Class}`
- PSR-4 autoloading via Composer
- Logical directory structure by domain/concern
- Zero manual `require_once` calls
- Full backward compatibility maintained
- Modern PHP development experience

## Namespace Structure Design

### Root Namespace
```
AIPostScheduler
```

### Proposed Directory & Namespace Hierarchy

```
src/
├── AIPostScheduler/
│   ├── Core/                          # Core infrastructure
│   │   ├── Config.php                 # Configuration singleton
│   │   ├── DBManager.php              # Database management
│   │   ├── Logger.php                 # Logging service
│   │   ├── Plugin.php                 # Main plugin class (replaces AI_Post_Scheduler)
│   │   └── Upgrades.php               # Version upgrades
│   │
│   ├── Repository/                    # Data access layer
│   │   ├── HistoryRepository.php
│   │   ├── ScheduleRepository.php
│   │   ├── TemplateRepository.php
│   │   ├── ArticleStructureRepository.php
│   │   ├── PromptSectionRepository.php
│   │   ├── TrendingTopicsRepository.php
│   │   ├── AuthorsRepository.php
│   │   ├── AuthorTopicsRepository.php
│   │   ├── AuthorTopicLogsRepository.php
│   │   ├── FeedbackRepository.php
│   │   ├── PostReviewRepository.php
│   │   └── ActivityRepository.php
│   │
│   ├── Service/                       # Business logic services
│   │   ├── AI/
│   │   │   ├── AIService.php
│   │   │   ├── EmbeddingsService.php
│   │   │   ├── PromptBuilder.php
│   │   │   └── ResearchService.php
│   │   ├── Content/
│   │   │   ├── Generator.php
│   │   │   ├── PostCreator.php
│   │   │   ├── TemplateProcessor.php
│   │   │   └── ArticleStructureManager.php
│   │   ├── Image/
│   │   │   └── ImageService.php
│   │   ├── Topic/
│   │   │   ├── TopicExpansionService.php
│   │   │   └── TopicPenaltyService.php
│   │   ├── Scheduling/
│   │   │   ├── Scheduler.php
│   │   │   ├── Planner.php
│   │   │   └── IntervalCalculator.php
│   │   ├── Seeder/
│   │   │   └── SeederService.php
│   │   └── ResilienceService.php
│   │
│   ├── Controller/                    # Admin controllers (MVC pattern)
│   │   ├── TemplatesController.php
│   │   ├── ScheduleController.php
│   │   ├── GeneratedPostsController.php
│   │   ├── StructuresController.php
│   │   ├── PromptSectionsController.php
│   │   ├── ResearchController.php
│   │   ├── AuthorsController.php
│   │   └── AuthorTopicsController.php
│   │
│   ├── Admin/                         # Admin UI classes
│   │   ├── Settings.php
│   │   ├── History.php
│   │   ├── Templates.php
│   │   ├── Voices.php
│   │   ├── SystemStatus.php
│   │   ├── DevTools.php
│   │   └── SeederAdmin.php
│   │
│   ├── Generation/                    # Generation pipeline
│   │   ├── Context/
│   │   │   ├── GenerationContextInterface.php
│   │   │   ├── TemplateContext.php
│   │   │   └── TopicContext.php
│   │   ├── GenerationSession.php
│   │   ├── HistoryContainer.php
│   │   ├── HistoryService.php
│   │   └── HistoryType.php
│   │
│   ├── Author/                        # Authors feature
│   │   ├── AuthorTopicsGenerator.php
│   │   ├── AuthorTopicsScheduler.php
│   │   └── AuthorPostGenerator.php
│   │
│   ├── Review/                        # Post review feature
│   │   ├── PostReview.php
│   │   └── PostReviewNotifications.php
│   │
│   ├── DataManagement/                # Import/Export
│   │   ├── Export/
│   │   │   ├── ExportInterface.php
│   │   │   ├── JsonExporter.php
│   │   │   └── MySQLExporter.php
│   │   ├── Import/
│   │   │   ├── ImportInterface.php
│   │   │   ├── JsonImporter.php
│   │   │   └── MySQLImporter.php
│   │   └── DataManagement.php
│   │
│   └── Helper/                        # Utility helpers
│       └── TemplateHelper.php
```

### Class Naming Convention Changes

**Before:**
```php
class AIPS_History_Repository
class AIPS_Template_Processor
interface AIPS_Generation_Context
```

**After:**
```php
namespace AIPostScheduler\Repository;
class HistoryRepository

namespace AIPostScheduler\Service\Content;
class TemplateProcessor

namespace AIPostScheduler\Generation\Context;
interface GenerationContextInterface
```

## Implementation Strategy

### Phase 1: Foundation Setup
**Goal:** Set up the new structure without breaking existing code

1. **Create New Directory Structure**
   - Create `src/AIPostScheduler/` directory hierarchy
   - Keep existing `includes/` directory intact

2. **Update Composer Configuration**
   ```json
   {
     "autoload": {
       "psr-4": {
         "AIPostScheduler\\": "src/AIPostScheduler/"
       },
       "classmap": [
         "includes/"
       ]
     }
   }
   ```

3. **Create Backward Compatibility Layer**
   - Create alias file: `includes/class-aliases.php`
   - Register class aliases for all old class names
   ```php
   <?php
   // Map old class names to new namespaced classes
   class_alias(AIPostScheduler\Core\Logger::class, 'AIPS_Logger');
   class_alias(AIPostScheduler\Repository\HistoryRepository::class, 'AIPS_History_Repository');
   // ... all other classes
   ```

4. **Run Composer Dump-Autoload**
   ```bash
   composer dump-autoload
   ```

### Phase 2: Migrate Core Infrastructure
**Goal:** Move foundational classes that have few dependencies

**Batch 1 - Core Classes (No dependencies on others):**
1. `class-aips-logger.php` → `Core/Logger.php`
2. `class-aips-config.php` → `Core/Config.php`
3. `class-aips-history-type.php` → `Generation/HistoryType.php`

**Steps for each class:**
1. Copy file to new location
2. Add namespace declaration
3. Remove `AIPS_` prefix from class name
4. Update any internal references
5. Add class alias in `class-aliases.php`
6. Test that both old and new names work

**Example Migration:**

**Before** (`includes/class-aips-logger.php`):
```php
<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Logger {
    public function log($message) {
        // ...
    }
}
```

**After** (`src/AIPostScheduler/Core/Logger.php`):
```php
<?php

namespace AIPostScheduler\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    public function log($message) {
        // ...
    }
}
```

**Alias** (`includes/class-aliases.php`):
```php
class_alias(AIPostScheduler\Core\Logger::class, 'AIPS_Logger');
```

### Phase 3: Migrate Repository Layer
**Goal:** Move all repository classes (clean interfaces, minimal dependencies)

**Classes to migrate:**
- All `*Repository.php` files (12 classes)

**Benefits of early migration:**
- Repositories have minimal dependencies
- Well-defined interfaces
- Easy to test isolation
- Other layers depend on repositories (not vice versa)

### Phase 4: Migrate Service Layer
**Goal:** Move business logic services

**Sub-batches:**
1. AI Services (AIService, ResearchService, PromptBuilder, etc.)
2. Content Services (Generator, PostCreator, TemplateProcessor, etc.)
3. Supporting Services (ImageService, TopicExpansionService, etc.)

### Phase 5: Migrate Controllers & Admin
**Goal:** Move UI and controller classes

**Classes:**
- All `*Controller.php` files
- Admin UI classes (Settings, History, Templates, etc.)

### Phase 6: Migrate Specialized Features
**Goal:** Move domain-specific features

**Features:**
1. Generation Context architecture
2. Authors feature
3. Data Management (Import/Export)
4. Post Review system
5. Seeder feature

### Phase 7: Main Plugin File Refactoring
**Goal:** Update main plugin file to use autoloading

**Before** (`ai-post-scheduler.php`):
```php
private function includes() {
    require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
    require_once AIPS_PLUGIN_DIR . 'includes/class-aips-config.php';
    // ... 50+ more require_once calls
}
```

**After** (`ai-post-scheduler.php`):
```php
// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Backward compatibility aliases (temporary during transition)
require_once __DIR__ . '/includes/class-aliases.php';

private function includes() {
    // No manual requires needed - autoloading handles it
    // Optional: Load any non-class includes (like function files)
}
```

### Phase 8: Update Test Infrastructure
**Goal:** Ensure tests work with new structure

1. **Update Test Bootstrap**
   - Ensure Composer autoloader is loaded
   - Load class aliases for backward compatibility
   - Update any hardcoded class references

2. **Update Individual Tests**
   - Tests can continue using old class names (via aliases)
   - Gradually update test imports to use new namespaces
   - Add tests for new namespace usage

**Test Bootstrap Changes:**
```php
// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load backward compatibility aliases
require_once dirname(__DIR__) . '/includes/class-aliases.php';

// Rest of bootstrap...
```

### Phase 9: Deprecation & Migration Path
**Goal:** Guide developers to new namespaces

1. **Add Deprecation Notices**
   ```php
   // In class-aliases.php
   class_alias(AIPostScheduler\Core\Logger::class, 'AIPS_Logger');
   
   if (defined('WP_DEBUG') && WP_DEBUG) {
       // Trigger deprecation notice on first use
       // This can be done via __autoload or class constructor
   }
   ```

2. **Documentation Updates**
   - Update all documentation to use new namespaces
   - Create migration guide for third-party developers
   - Add UPGRADING.md with detailed migration instructions

3. **Timeline**
   - v2.0.0: New namespace structure introduced, old names aliased
   - v2.1.0-v2.5.0: Both old and new names work, deprecation notices
   - v3.0.0: Remove aliases, old names no longer work

### Phase 10: Cleanup
**Goal:** Remove old structure and aliases

1. **Remove Old Files**
   - Delete all files from `includes/` directory
   - Remove `class-aliases.php`

2. **Remove Classmap from Composer**
   ```json
   {
     "autoload": {
       "psr-4": {
         "AIPostScheduler\\": "src/AIPostScheduler/"
       }
     }
   }
   ```

3. **Final Composer Dump**
   ```bash
   composer dump-autoload -o
   ```

## Detailed Migration Checklist

### Pre-Migration
- [x] Analyze current structure (70+ classes)
- [x] Design namespace hierarchy
- [ ] Create detailed mapping document (old → new)
- [ ] Set up development/testing branch
- [ ] Back up current codebase
- [ ] Document all external dependencies

### Phase 1: Foundation
- [ ] Create `src/AIPostScheduler/` directory structure
- [ ] Update `composer.json` with PSR-4 autoloading
- [ ] Create `includes/class-aliases.php` file
- [ ] Run `composer dump-autoload`
- [ ] Verify autoloader works
- [ ] Run existing tests to ensure no breakage

### Phase 2: Core (Est. 5 classes)
- [ ] Migrate `Logger.php`
- [ ] Migrate `Config.php`
- [ ] Migrate `DBManager.php`
- [ ] Migrate `Upgrades.php`
- [ ] Migrate `HistoryType.php`
- [ ] Add class aliases
- [ ] Test each migration individually
- [ ] Run full test suite

### Phase 3: Repositories (Est. 12 classes)
- [ ] Migrate `HistoryRepository.php`
- [ ] Migrate `ScheduleRepository.php`
- [ ] Migrate `TemplateRepository.php`
- [ ] Migrate `ArticleStructureRepository.php`
- [ ] Migrate `PromptSectionRepository.php`
- [ ] Migrate `TrendingTopicsRepository.php`
- [ ] Migrate `AuthorsRepository.php`
- [ ] Migrate `AuthorTopicsRepository.php`
- [ ] Migrate `AuthorTopicLogsRepository.php`
- [ ] Migrate `FeedbackRepository.php`
- [ ] Migrate `PostReviewRepository.php`
- [ ] Migrate `ActivityRepository.php`
- [ ] Add class aliases
- [ ] Test repository layer
- [ ] Run full test suite

### Phase 4: Services (Est. 15 classes)
- [ ] Migrate AI services
- [ ] Migrate Content services
- [ ] Migrate Image service
- [ ] Migrate Topic services
- [ ] Migrate Scheduling services
- [ ] Add class aliases
- [ ] Test service layer
- [ ] Run full test suite

### Phase 5: Controllers (Est. 10 classes)
- [ ] Migrate all controller classes
- [ ] Add class aliases
- [ ] Test AJAX endpoints
- [ ] Test admin interfaces
- [ ] Run full test suite

### Phase 6: Admin UI (Est. 8 classes)
- [ ] Migrate admin classes
- [ ] Add class aliases
- [ ] Test admin pages render correctly
- [ ] Test settings functionality
- [ ] Run full test suite

### Phase 7: Specialized Features (Est. 15 classes)
- [ ] Migrate Generation Context
- [ ] Migrate Authors feature
- [ ] Migrate Data Management
- [ ] Migrate Post Review
- [ ] Migrate Seeder
- [ ] Add class aliases
- [ ] Test each feature
- [ ] Run full test suite

### Phase 8: Main Plugin File
- [ ] Update main plugin file
- [ ] Remove all `require_once` calls
- [ ] Add autoloader require
- [ ] Add aliases require (temporary)
- [ ] Test plugin activation
- [ ] Test plugin deactivation
- [ ] Test all admin pages load
- [ ] Test cron jobs
- [ ] Run full test suite

### Phase 9: Test Updates
- [ ] Update test bootstrap
- [ ] Update test class references
- [ ] Add new namespace tests
- [ ] Verify all tests pass
- [ ] Add integration tests for autoloading

### Phase 10: Documentation
- [ ] Update README.md
- [ ] Update inline documentation
- [ ] Create MIGRATION.md guide
- [ ] Update developer documentation
- [ ] Update COPILOT_SETUP_STEPS.md
- [ ] Create changelog entry

### Phase 11: Cleanup (Future release)
- [ ] Plan deprecation timeline
- [ ] Add deprecation notices
- [ ] Communicate changes to users
- [ ] Wait for adoption period
- [ ] Remove old files
- [ ] Remove class aliases
- [ ] Update composer.json (remove classmap)
- [ ] Final testing
- [ ] Release v3.0.0

## Class Migration Mapping

### Complete Class Name Mapping
| Old Class Name | New Namespaced Class | New Location |
|----------------|---------------------|--------------|
| `AIPS_Logger` | `AIPostScheduler\Core\Logger` | `src/AIPostScheduler/Core/Logger.php` |
| `AIPS_Config` | `AIPostScheduler\Core\Config` | `src/AIPostScheduler/Core/Config.php` |
| `AIPS_DB_Manager` | `AIPostScheduler\Core\DBManager` | `src/AIPostScheduler/Core/DBManager.php` |
| `AIPS_Upgrades` | `AIPostScheduler\Core\Upgrades` | `src/AIPostScheduler/Core/Upgrades.php` |
| `AIPS_History_Type` | `AIPostScheduler\Generation\HistoryType` | `src/AIPostScheduler/Generation/HistoryType.php` |
| `AIPS_History_Repository` | `AIPostScheduler\Repository\HistoryRepository` | `src/AIPostScheduler/Repository/HistoryRepository.php` |
| `AIPS_Schedule_Repository` | `AIPostScheduler\Repository\ScheduleRepository` | `src/AIPostScheduler/Repository/ScheduleRepository.php` |
| `AIPS_Template_Repository` | `AIPostScheduler\Repository\TemplateRepository` | `src/AIPostScheduler/Repository/TemplateRepository.php` |
| `AIPS_Article_Structure_Repository` | `AIPostScheduler\Repository\ArticleStructureRepository` | `src/AIPostScheduler/Repository/ArticleStructureRepository.php` |
| `AIPS_Prompt_Section_Repository` | `AIPostScheduler\Repository\PromptSectionRepository` | `src/AIPostScheduler/Repository/PromptSectionRepository.php` |
| `AIPS_Trending_Topics_Repository` | `AIPostScheduler\Repository\TrendingTopicsRepository` | `src/AIPostScheduler/Repository/TrendingTopicsRepository.php` |
| `AIPS_Authors_Repository` | `AIPostScheduler\Repository\AuthorsRepository` | `src/AIPostScheduler/Repository/AuthorsRepository.php` |
| `AIPS_Author_Topics_Repository` | `AIPostScheduler\Repository\AuthorTopicsRepository` | `src/AIPostScheduler/Repository/AuthorTopicsRepository.php` |
| `AIPS_Author_Topic_Logs_Repository` | `AIPostScheduler\Repository\AuthorTopicLogsRepository` | `src/AIPostScheduler/Repository/AuthorTopicLogsRepository.php` |
| `AIPS_Feedback_Repository` | `AIPostScheduler\Repository\FeedbackRepository` | `src/AIPostScheduler/Repository/FeedbackRepository.php` |
| `AIPS_Post_Review_Repository` | `AIPostScheduler\Repository\PostReviewRepository` | `src/AIPostScheduler/Repository/PostReviewRepository.php` |
| `AIPS_Activity_Repository` | `AIPostScheduler\Repository\ActivityRepository` | `src/AIPostScheduler/Repository/ActivityRepository.php` |
| `AIPS_AI_Service` | `AIPostScheduler\Service\AI\AIService` | `src/AIPostScheduler/Service/AI/AIService.php` |
| `AIPS_Research_Service` | `AIPostScheduler\Service\AI\ResearchService` | `src/AIPostScheduler/Service/AI/ResearchService.php` |
| `AIPS_Prompt_Builder` | `AIPostScheduler\Service\AI\PromptBuilder` | `src/AIPostScheduler/Service/AI/PromptBuilder.php` |
| `AIPS_Embeddings_Service` | `AIPostScheduler\Service\AI\EmbeddingsService` | `src/AIPostScheduler/Service/AI/EmbeddingsService.php` |
| `AIPS_Generator` | `AIPostScheduler\Service\Content\Generator` | `src/AIPostScheduler/Service/Content/Generator.php` |
| `AIPS_Post_Creator` | `AIPostScheduler\Service\Content\PostCreator` | `src/AIPostScheduler/Service/Content/PostCreator.php` |
| `AIPS_Template_Processor` | `AIPostScheduler\Service\Content\TemplateProcessor` | `src/AIPostScheduler/Service/Content/TemplateProcessor.php` |
| `AIPS_Article_Structure_Manager` | `AIPostScheduler\Service\Content\ArticleStructureManager` | `src/AIPostScheduler/Service/Content/ArticleStructureManager.php` |
| `AIPS_Image_Service` | `AIPostScheduler\Service\Image\ImageService` | `src/AIPostScheduler/Service/Image/ImageService.php` |
| `AIPS_Topic_Expansion_Service` | `AIPostScheduler\Service\Topic\TopicExpansionService` | `src/AIPostScheduler/Service/Topic/TopicExpansionService.php` |
| `AIPS_Topic_Penalty_Service` | `AIPostScheduler\Service\Topic\TopicPenaltyService` | `src/AIPostScheduler/Service/Topic/TopicPenaltyService.php` |
| `AIPS_Scheduler` | `AIPostScheduler\Service\Scheduling\Scheduler` | `src/AIPostScheduler/Service/Scheduling/Scheduler.php` |
| `AIPS_Planner` | `AIPostScheduler\Service\Scheduling\Planner` | `src/AIPostScheduler/Service/Scheduling/Planner.php` |
| `AIPS_Interval_Calculator` | `AIPostScheduler\Service\Scheduling\IntervalCalculator` | `src/AIPostScheduler/Service/Scheduling/IntervalCalculator.php` |
| `AIPS_Resilience_Service` | `AIPostScheduler\Service\ResilienceService` | `src/AIPostScheduler/Service/ResilienceService.php` |
| `AIPS_Seeder_Service` | `AIPostScheduler\Service\Seeder\SeederService` | `src/AIPostScheduler/Service/Seeder/SeederService.php` |
| `AIPS_Templates_Controller` | `AIPostScheduler\Controller\TemplatesController` | `src/AIPostScheduler/Controller/TemplatesController.php` |
| `AIPS_Schedule_Controller` | `AIPostScheduler\Controller\ScheduleController` | `src/AIPostScheduler/Controller/ScheduleController.php` |
| `AIPS_Generated_Posts_Controller` | `AIPostScheduler\Controller\GeneratedPostsController` | `src/AIPostScheduler/Controller/GeneratedPostsController.php` |
| `AIPS_Structures_Controller` | `AIPostScheduler\Controller\StructuresController` | `src/AIPostScheduler/Controller/StructuresController.php` |
| `AIPS_Prompt_Sections_Controller` | `AIPostScheduler\Controller\PromptSectionsController` | `src/AIPostScheduler/Controller/PromptSectionsController.php` |
| `AIPS_Research_Controller` | `AIPostScheduler\Controller\ResearchController` | `src/AIPostScheduler/Controller/ResearchController.php` |
| `AIPS_Authors_Controller` | `AIPostScheduler\Controller\AuthorsController` | `src/AIPostScheduler/Controller/AuthorsController.php` |
| `AIPS_Author_Topics_Controller` | `AIPostScheduler\Controller\AuthorTopicsController` | `src/AIPostScheduler/Controller/AuthorTopicsController.php` |
| `AIPS_Settings` | `AIPostScheduler\Admin\Settings` | `src/AIPostScheduler/Admin/Settings.php` |
| `AIPS_History` | `AIPostScheduler\Admin\History` | `src/AIPostScheduler/Admin/History.php` |
| `AIPS_Templates` | `AIPostScheduler\Admin\Templates` | `src/AIPostScheduler/Admin/Templates.php` |
| `AIPS_Voices` | `AIPostScheduler\Admin\Voices` | `src/AIPostScheduler/Admin/Voices.php` |
| `AIPS_System_Status` | `AIPostScheduler\Admin\SystemStatus` | `src/AIPostScheduler/Admin/SystemStatus.php` |
| `AIPS_Dev_Tools` | `AIPostScheduler\Admin\DevTools` | `src/AIPostScheduler/Admin/DevTools.php` |
| `AIPS_Seeder_Admin` | `AIPostScheduler\Admin\SeederAdmin` | `src/AIPostScheduler/Admin/SeederAdmin.php` |
| `AIPS_Generation_Context` | `AIPostScheduler\Generation\Context\GenerationContextInterface` | `src/AIPostScheduler/Generation/Context/GenerationContextInterface.php` |
| `AIPS_Template_Context` | `AIPostScheduler\Generation\Context\TemplateContext` | `src/AIPostScheduler/Generation/Context/TemplateContext.php` |
| `AIPS_Topic_Context` | `AIPostScheduler\Generation\Context\TopicContext` | `src/AIPostScheduler/Generation/Context/TopicContext.php` |
| `AIPS_Generation_Session` | `AIPostScheduler\Generation\GenerationSession` | `src/AIPostScheduler/Generation/GenerationSession.php` |
| `AIPS_History_Container` | `AIPostScheduler\Generation\HistoryContainer` | `src/AIPostScheduler/Generation/HistoryContainer.php` |
| `AIPS_History_Service` | `AIPostScheduler\Generation\HistoryService` | `src/AIPostScheduler/Generation/HistoryService.php` |
| `AIPS_Author_Topics_Generator` | `AIPostScheduler\Author\AuthorTopicsGenerator` | `src/AIPostScheduler/Author/AuthorTopicsGenerator.php` |
| `AIPS_Author_Topics_Scheduler` | `AIPostScheduler\Author\AuthorTopicsScheduler` | `src/AIPostScheduler/Author/AuthorTopicsScheduler.php` |
| `AIPS_Author_Post_Generator` | `AIPostScheduler\Author\AuthorPostGenerator` | `src/AIPostScheduler/Author/AuthorPostGenerator.php` |
| `AIPS_Post_Review` | `AIPostScheduler\Review\PostReview` | `src/AIPostScheduler/Review/PostReview.php` |
| `AIPS_Post_Review_Notifications` | `AIPostScheduler\Review\PostReviewNotifications` | `src/AIPostScheduler/Review/PostReviewNotifications.php` |
| `AIPS_Data_Management` | `AIPostScheduler\DataManagement\DataManagement` | `src/AIPostScheduler/DataManagement/DataManagement.php` |
| `AIPS_Data_Management_Export` | `AIPostScheduler\DataManagement\Export\ExportInterface` | `src/AIPostScheduler/DataManagement/Export/ExportInterface.php` |
| `AIPS_Data_Management_Export_JSON` | `AIPostScheduler\DataManagement\Export\JsonExporter` | `src/AIPostScheduler/DataManagement/Export/JsonExporter.php` |
| `AIPS_Data_Management_Export_MySQL` | `AIPostScheduler\DataManagement\Export\MySQLExporter` | `src/AIPostScheduler/DataManagement/Export/MySQLExporter.php` |
| `AIPS_Data_Management_Import` | `AIPostScheduler\DataManagement\Import\ImportInterface` | `src/AIPostScheduler/DataManagement/Import/ImportInterface.php` |
| `AIPS_Data_Management_Import_JSON` | `AIPostScheduler\DataManagement\Import\JsonImporter` | `src/AIPostScheduler/DataManagement/Import/JsonImporter.php` |
| `AIPS_Data_Management_Import_MySQL` | `AIPostScheduler\DataManagement\Import\MySQLImporter` | `src/AIPostScheduler/DataManagement/Import/MySQLImporter.php` |
| `AIPS_Template_Helper` | `AIPostScheduler\Helper\TemplateHelper` | `src/AIPostScheduler/Helper/TemplateHelper.php` |
| `AIPS_Template_Type_Selector` | `AIPostScheduler\Admin\TemplateTypeSelector` | `src/AIPostScheduler/Admin/TemplateTypeSelector.php` |
| `AI_Post_Scheduler` | `AIPostScheduler\Core\Plugin` | `src/AIPostScheduler/Core/Plugin.php` |

## Backward Compatibility Strategy

### Approach: Class Aliasing
We will use PHP's `class_alias()` function to maintain backward compatibility. This allows external code and plugins to continue using old class names while we internally use new namespaced classes.

### Compatibility Timeline
- **v2.0.0 - v2.5.0**: Full backward compatibility (2-3 releases)
  - Old class names work via aliases
  - New namespaced classes preferred
  - Documentation updated to new style
  
- **v2.6.0 - v2.9.0**: Deprecation period
  - Old class names still work
  - Deprecation notices in WP_DEBUG mode
  - Migration guide published
  
- **v3.0.0+**: Breaking change
  - Class aliases removed
  - Only namespaced classes work
  - Major version bump signifies breaking change

### Testing Backward Compatibility
```php
// Test that both old and new names work
$logger1 = new AIPS_Logger();  // Old name (aliased)
$logger2 = new \AIPostScheduler\Core\Logger();  // New name

$this->assertInstanceOf(\AIPostScheduler\Core\Logger::class, $logger1);
$this->assertInstanceOf(\AIPostScheduler\Core\Logger::class, $logger2);
$this->assertEquals(get_class($logger1), get_class($logger2));
```

## Testing Strategy

### Unit Tests
- All existing tests must pass without modification
- Class aliases ensure old test code works
- Gradually add tests using new namespaces
- Test both old and new class names

### Integration Tests
- Test plugin activation/deactivation
- Test all admin pages load correctly
- Test AJAX endpoints function
- Test cron jobs execute
- Test database operations
- Test external API integrations

### Performance Tests
- Measure autoloader performance
- Compare to manual require_once approach
- Ensure no performance regression
- Test with WordPress Query Monitor plugin

### Compatibility Tests
- Test with various WordPress versions (5.8+)
- Test with various PHP versions (8.2+)
- Test with common plugins (AI Engine, WooCommerce, etc.)
- Test theme compatibility

## Risk Mitigation

### Identified Risks
1. **Class instantiation breaks** - Mitigated by class aliases
2. **String-based class references** - Search codebase for string class names
3. **Reflection/dynamic class loading** - Update to use new names
4. **Third-party plugins** - Maintain aliases until v3.0
5. **Serialized data** - WordPress handles this for options/meta
6. **Performance impact** - Test and optimize autoloader

### Rollback Plan
If critical issues arise:
1. Revert composer.json changes
2. Remove new `src/` directory
3. Remove class aliases file
4. Re-add manual require_once statements
5. Run tests to verify rollback success

## Development Guidelines

### During Migration Period

**DO:**
- ✅ Use new namespaced classes in new code
- ✅ Add `use` statements at top of files
- ✅ Update PHPDoc blocks to reference new classes
- ✅ Test both old and new class names
- ✅ Document namespace changes in commit messages

**DON'T:**
- ❌ Break existing class aliases
- ❌ Remove old files before migration complete
- ❌ Change serialized data structure
- ❌ Modify public API without deprecation
- ❌ Skip testing after each migration batch

### Code Style for New Namespaced Files

```php
<?php
/**
 * Class description
 *
 * @package AIPostScheduler
 * @subpackage Service\Content
 * @since 2.0.0
 */

namespace AIPostScheduler\Service\Content;

use AIPostScheduler\Core\Logger;
use AIPostScheduler\Repository\TemplateRepository;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateProcessor {
    
    private Logger $logger;
    private TemplateRepository $template_repository;
    
    public function __construct(Logger $logger = null, TemplateRepository $template_repository = null) {
        $this->logger = $logger ?? new Logger();
        $this->template_repository = $template_repository ?? new TemplateRepository();
    }
    
    // ... methods
}
```

## Documentation Requirements

### Technical Documentation
- [ ] Update README.md with new structure
- [ ] Create MIGRATION.md guide for developers
- [ ] Update TESTING.md with new test patterns
- [ ] Update inline code documentation
- [ ] Update PHPDoc blocks with new namespaces

### User Documentation
- [ ] Update plugin description
- [ ] Update screenshots if UI changes
- [ ] Create upgrade guide
- [ ] Document breaking changes (for v3.0)

### Developer Documentation
- [ ] Create namespace reference guide
- [ ] Update API documentation
- [ ] Document new autoloading approach
- [ ] Create examples of new usage patterns
- [ ] Update COPILOT_SETUP_STEPS.md

## Success Criteria

### Phase Completion Criteria
Each phase is complete when:
- ✅ All classes migrated as planned
- ✅ Class aliases added for backward compatibility
- ✅ All existing tests pass
- ✅ Manual testing confirms functionality
- ✅ No PHP errors or warnings
- ✅ Documentation updated
- ✅ Code reviewed and approved

### Overall Success Criteria
The refactoring is successful when:
- ✅ Zero manual `require_once` calls in main plugin file
- ✅ PSR-4 autoloading fully functional
- ✅ All 70+ classes organized in logical namespaces
- ✅ 100% backward compatibility maintained
- ✅ All existing tests pass
- ✅ No performance regression
- ✅ Improved developer experience
- ✅ Clean, maintainable codebase
- ✅ Documentation complete and accurate

## Timeline Estimate

### Conservative Estimate (Recommended)
- **Phase 1: Foundation** - 1-2 days
- **Phase 2: Core Classes** - 1 day
- **Phase 3: Repositories** - 2-3 days
- **Phase 4: Services** - 3-4 days
- **Phase 5: Controllers** - 2 days
- **Phase 6: Admin UI** - 2 days
- **Phase 7: Specialized Features** - 3 days
- **Phase 8: Main Plugin File** - 1 day
- **Phase 9: Test Updates** - 2 days
- **Phase 10: Documentation** - 2 days

**Total: 19-24 working days (4-5 weeks)**

### Aggressive Estimate (With automation)
If migration scripts are created to automate file copying, namespace injection, and alias generation:
- **Total: 10-15 working days (2-3 weeks)**

## Automation Opportunities

### Migration Script
A PHP/Shell script could automate:
1. File copying from `includes/` to `src/`
2. Adding namespace declarations
3. Removing `AIPS_` prefix from class names
4. Generating class aliases
5. Updating file headers
6. Running composer dump-autoload

### Example Script Outline
```bash
#!/bin/bash
# migrate-class.sh - Automate class migration

OLD_FILE=$1  # includes/class-aips-logger.php
NEW_NAMESPACE=$2  # AIPostScheduler\Core
CLASS_NAME=$3  # Logger

# Copy file
# Add namespace
# Update class name
# Generate alias
# Run tests
```

## Conclusion

This refactoring represents a significant modernization of the plugin architecture. By introducing namespaces, PSR-4 autoloading, and logical organization, we will:

1. **Improve Maintainability** - Clear organization makes code easier to find and modify
2. **Enhance Developer Experience** - Modern PHP standards, better IDE support
3. **Increase Scalability** - Easy to add new features in appropriate namespaces
4. **Maintain Compatibility** - Class aliases ensure no breaking changes
5. **Improve Performance** - Optimized autoloading vs. manual requires
6. **Enable Future Growth** - Foundation for dependency injection, better testing

The phased approach with backward compatibility ensures this can be done safely over multiple releases without disrupting users or breaking external integrations.

**Next Steps:**
1. Review and approve this plan
2. Create development branch
3. Begin Phase 1 implementation
4. Iterate with testing and validation
5. Document progress and lessons learned

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-28  
**Status:** Draft - Awaiting Review
