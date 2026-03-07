# PSR-4 Architecture

This document describes the PSR-4 compliant architecture of the AI Post Scheduler plugin as of v2.0.0.

## Overview

All 77 plugin classes have been migrated from a flat `includes/` structure to a namespaced `src/` directory using PSR-4 autoloading via Composer. The migration maintains full backward compatibility through a compatibility layer that maps old `AIPS_*` class names to new namespaced classes.

## Directory Structure

```
ai-post-scheduler/
├── src/                          # PSR-4 source (primary)
│   ├── Controllers/
│   │   ├── Admin/                # Admin page controllers
│   │   │   ├── AuthorsController.php
│   │   │   ├── AuthorTopicsController.php
│   │   │   ├── CalendarController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── GeneratedPostsController.php
│   │   │   ├── PromptSectionsController.php
│   │   │   ├── ResearchController.php
│   │   │   ├── ScheduleController.php
│   │   │   ├── StructuresController.php
│   │   │   └── TemplatesController.php
│   │   ├── AIEditController.php
│   │   └── DataManagementController.php
│   │
│   ├── Services/
│   │   ├── AI/                   # AI Engine integration
│   │   │   ├── AIService.php
│   │   │   ├── EmbeddingsService.php
│   │   │   ├── PromptBuilder.php
│   │   │   └── ResilienceService.php
│   │   ├── Content/              # Content generation
│   │   │   ├── ComponentRegenerationService.php
│   │   │   ├── PostCreator.php
│   │   │   ├── TemplateHelper.php
│   │   │   └── TemplateProcessor.php
│   │   ├── Generation/           # Generation session tracking
│   │   │   ├── GenerationLogger.php
│   │   │   └── GenerationSession.php
│   │   ├── Research/            # Topic research
│   │   │   ├── ResearchService.php
│   │   │   ├── TopicExpansionService.php
│   │   │   └── TopicPenaltyService.php
│   │   ├── HistoryService.php
│   │   ├── ImageService.php
│   │   ├── Logger.php
│   │   ├── SeederService.php
│   │   └── SessionToJson.php
│   │
│   ├── Repositories/             # Database access layer
│   │   ├── ArticleStructureRepository.php
│   │   ├── AuthorTopicLogsRepository.php
│   │   ├── AuthorTopicsRepository.php
│   │   ├── AuthorsRepository.php
│   │   ├── DBManager.php
│   │   ├── FeedbackRepository.php
│   │   ├── HistoryRepository.php
│   │   ├── PostReviewRepository.php
│   │   ├── PromptSectionRepository.php
│   │   ├── ScheduleRepository.php
│   │   ├── TemplateRepository.php
│   │   ├── TrendingTopicsRepository.php
│   │   └── VoicesRepository.php
│   │
│   ├── Generators/               # Content generation orchestration
│   │   ├── AuthorPostGenerator.php
│   │   ├── AuthorTopicsGenerator.php
│   │   ├── Generator.php
│   │   └── ScheduleProcessor.php
│   │
│   ├── Models/
│   │   ├── ArticleStructureManager.php
│   │   ├── Config.php
│   │   ├── HistoryContainer.php
│   │   ├── HistoryType.php
│   │   ├── TemplateContext.php
│   │   ├── TemplateTypeSelector.php
│   │   └── TopicContext.php
│   │
│   ├── Interfaces/
│   │   └── GenerationContext.php
│   │
│   ├── Admin/                    # Admin UI components
│   │   ├── AdminAssets.php
│   │   ├── DevTools.php
│   │   ├── History.php
│   │   ├── Planner.php
│   │   ├── PostReview.php
│   │   ├── Scheduler.php
│   │   ├── SeederAdmin.php
│   │   ├── Settings.php
│   │   ├── SystemStatus.php
│   │   ├── Templates.php
│   │   ├── Upgrades.php
│   │   └── Voices.php
│   │
│   ├── Utilities/
│   │   ├── AuthorTopicsScheduler.php
│   │   └── IntervalCalculator.php
│   │
│   ├── DataManagement/
│   │   ├── Export/
│   │   │   ├── ExportHandler.php
│   │   │   ├── JsonExporter.php
│   │   │   └── MySQLExporter.php
│   │   └── Import/
│   │       ├── ImportHandler.php
│   │       ├── JsonImporter.php
│   │       └── MySQLImporter.php
│   │
│   └── Notifications/
│       └── PostReviewNotifications.php
│
├── includes/
│   ├── compatibility-loader.php  # Class aliases for backward compatibility
│   └── class-aips-*.php         # Legacy files (kept for reference; not loaded)
│
└── vendor/                       # Composer autoloader
```

## Namespace Hierarchy

| Namespace | Purpose | Classes |
|-----------|---------|---------|
| `AIPS\Repositories` | Database access | 13 |
| `AIPS\Services` | Business logic | 5 |
| `AIPS\Services\AI` | AI Engine integration | 4 |
| `AIPS\Services\Content` | Content generation | 4 |
| `AIPS\Services\Generation` | Session tracking | 2 |
| `AIPS\Services\Research` | Topic research | 3 |
| `AIPS\Generators` | Generation orchestration | 4 |
| `AIPS\Models` | Data models | 7 |
| `AIPS\Interfaces` | Contracts | 1 |
| `AIPS\Controllers` | Request handlers | 2 |
| `AIPS\Controllers\Admin` | Admin controllers | 10 |
| `AIPS\Admin` | Admin UI | 12 |
| `AIPS\Utilities` | Helpers | 2 |
| `AIPS\DataManagement\Export` | Export | 3 |
| `AIPS\DataManagement\Import` | Import | 3 |
| `AIPS\Notifications` | Notifications | 1 |

## Dependency Patterns

### Autoloading

1. **Composer PSR-4** loads all `src/` classes via `AIPS\` namespace prefix.
2. **Compatibility loader** (`includes/compatibility-loader.php`) creates `class_alias()` mappings so old class names (`AIPS_*`) resolve to new namespaced classes.
3. **Plugin bootstrap** loads Composer autoloader first, then compatibility loader.

### Cross-Namespace References

When referencing global classes (WordPress, old aliases) from within namespaced code, use the leading backslash:

```php
// WordPress core
new \WP_Error('code', 'message');

// Old class names (via compatibility alias)
$config = \AIPS_Config::get_instance();
if ($context instanceof \AIPS_Generation_Context) { }
```

### Generation Flow

```
Controller → Generator → AIService → AI Engine
                ↓
         GenerationSession (tracks calls)
                ↓
         GenerationLogger → HistoryRepository
```

## Backward Compatibility

- **v2.0.0**: All old `AIPS_*` names work via aliases. No warnings.
- **v2.1.0**: Deprecation notices may be added (optional).
- **v3.0.0**: Compatibility layer removed. **Breaking change.**

See [MIGRATION_GUIDE.md](./MIGRATION_GUIDE.md) for migration instructions and [PSR4_CLASS_MAPPING.md](./PSR4_CLASS_MAPPING.md) for the complete class mapping.
