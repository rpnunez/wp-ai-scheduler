# PSR-4 Refactoring Implementation Plan

## Executive Summary

This document outlines the comprehensive plan to refactor the AI Post Scheduler plugin from its current custom autoloader to a PSR-4 compliant structure. The refactoring will reorganize 77 PHP classes from `includes/` into a structured `src/` directory with proper namespacing.

**Current State:**
- 77 classes in flat `includes/` directory
- Custom autoloader (file name based)
- Classes prefixed with `AIPS_`
- Files named `class-aips-*.php`

**Target State:**
- PSR-4 autoloader via Composer
- Organized in `src/` with namespace `AIPS\`
- Logical folder structure (Controllers, Services, Repositories, etc.)
- Proper namespace hierarchy

---

## Project Scope

### File Inventory (77 files)

**Controllers (12):**
- AI Edit Controller
- Authors Controller  
- Author Topics Controller
- Calendar Controller
- Dashboard Controller
- Generated Posts Controller
- Prompt Sections Controller
- Research Controller
- Schedule Controller
- Structures Controller
- Templates Controller
- Data Management

**Services (18):**
- AI Service
- Component Regeneration Service
- Embeddings Service
- History Service
- Image Service
- Logger
- Post Creator
- Prompt Builder
- Research Service
- Resilience Service
- Seeder Service
- Session to JSON
- Template Helper
- Template Processor
- Topic Expansion Service
- Topic Penalty Service
- Generation Logger
- Generation Session

**Repositories (13):**
- Article Structure Repository
- Author Topic Logs Repository
- Author Topics Repository
- Authors Repository
- Feedback Repository
- History Repository
- Post Review Repository
- Prompt Section Repository
- Schedule Repository
- Template Repository
- Trending Topics Repository
- Voices Repository
- DB Manager

**Generators (4):**
- Generator
- Author Post Generator
- Author Topics Generator
- Schedule Processor

**Models/Entities (8):**
- Article Structure Manager
- History Container
- History Type
- Template Context
- Template Type Selector
- Topic Context
- Generation Context (interface)
- Config

**Admin/UI (10):**
- Admin Assets
- Settings
- Dev Tools
- History
- Planner
- Post Review
- Seeder Admin
- System Status
- Templates
- Voices

**Utilities (6):**
- Autoloader (to be replaced)
- Interval Calculator
- Scheduler
- Upgrades
- Data Management Export/Import classes (4)

**Notifications (1):**
- Post Review Notifications

---

## Architecture Design

### New Directory Structure

```
ai-post-scheduler/
├── src/
│   ├── Controllers/
│   │   ├── Admin/
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
│   │   ├── AI/
│   │   │   ├── AIService.php
│   │   │   ├── EmbeddingsService.php
│   │   │   ├── PromptBuilder.php
│   │   │   └── ResilienceService.php
│   │   ├── Content/
│   │   │   ├── ComponentRegenerationService.php
│   │   │   ├── PostCreator.php
│   │   │   ├── TemplateProcessor.php
│   │   │   └── TemplateHelper.php
│   │   ├── Generation/
│   │   │   ├── GenerationLogger.php
│   │   │   └── GenerationSession.php
│   │   ├── Research/
│   │   │   ├── ResearchService.php
│   │   │   ├── TopicExpansionService.php
│   │   │   └── TopicPenaltyService.php
│   │   ├── ImageService.php
│   │   ├── Logger.php
│   │   ├── HistoryService.php
│   │   ├── SeederService.php
│   │   └── SessionToJson.php
│   │
│   ├── Repositories/
│   │   ├── ArticleStructureRepository.php
│   │   ├── AuthorTopicLogsRepository.php
│   │   ├── AuthorTopicsRepository.php
│   │   ├── AuthorsRepository.php
│   │   ├── FeedbackRepository.php
│   │   ├── HistoryRepository.php
│   │   ├── PostReviewRepository.php
│   │   ├── PromptSectionRepository.php
│   │   ├── ScheduleRepository.php
│   │   ├── TemplateRepository.php
│   │   ├── TrendingTopicsRepository.php
│   │   ├── VoicesRepository.php
│   │   └── DBManager.php
│   │
│   ├── Generators/
│   │   ├── Generator.php
│   │   ├── AuthorPostGenerator.php
│   │   ├── AuthorTopicsGenerator.php
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
│   ├── Admin/
│   │   ├── AdminAssets.php
│   │   ├── Settings.php
│   │   ├── DevTools.php
│   │   ├── History.php
│   │   ├── Planner.php
│   │   ├── PostReview.php
│   │   ├── SeederAdmin.php
│   │   ├── SystemStatus.php
│   │   ├── Templates.php
│   │   └── Voices.php
│   │
│   ├── Utilities/
│   │   ├── IntervalCalculator.php
│   │   ├── Scheduler.php
│   │   └── Upgrades.php
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
├── includes/          (legacy compatibility layer - temporary)
│   └── compatibility-loader.php
│
├── vendor/
├── composer.json
└── ai-post-scheduler.php
```

### Namespace Structure

```
AIPS\
├── Controllers\
│   └── Admin\
├── Services\
│   ├── AI\
│   ├── Content\
│   ├── Generation\
│   └── Research\
├── Repositories\
├── Generators\
├── Models\
├── Interfaces\
├── Admin\
├── Utilities\
├── DataManagement\
│   ├── Export\
│   └── Import\
└── Notifications\
```

---

## Implementation Phases

### Phase 0: Preparation (1-2 hours)

**Objectives:**
- Set up new directory structure
- Update composer.json for PSR-4
- Create compatibility layer
- Document the plan

**Tasks:**
1. Create `src/` directory and all subdirectories
2. Update `composer.json`:
   ```json
   "autoload": {
       "psr-4": {
           "AIPS\\": "src/"
       }
   }
   ```
3. Create `includes/compatibility-loader.php` for backward compatibility
4. Run `composer dump-autoload`

**Deliverables:**
- Empty directory structure
- Updated composer.json
- Compatibility loader stub

---

### Phase 1: Repositories (3-4 hours)

**Why start here:** Repositories have the fewest dependencies and are used by everything else.

**Classes to migrate (13):**
1. DBManager
2. ArticleStructureRepository
3. AuthorTopicLogsRepository
4. AuthorTopicsRepository
5. AuthorsRepository
6. FeedbackRepository
7. HistoryRepository
8. PostReviewRepository
9. PromptSectionRepository
10. ScheduleRepository
11. TemplateRepository
12. TrendingTopicsRepository
13. VoicesRepository

**Migration steps per class:**
1. Copy file to `src/Repositories/`
2. Remove `class-aips-` prefix, rename to PascalCase
3. Add namespace `AIPS\Repositories`
4. Update class name (remove AIPS_ prefix)
5. Add use statements for dependencies
6. Update references in old location (alias for compatibility)
7. Test functionality

**Example transformation:**
```php
// OLD: includes/class-aips-template-repository.php
class AIPS_Template_Repository {
    // ...
}

// NEW: src/Repositories/TemplateRepository.php
namespace AIPS\Repositories;

use AIPS\Utilities\Logger;

class TemplateRepository {
    // ...
}
```

**Testing checklist:**
- [ ] All repository methods work
- [ ] Database queries execute correctly
- [ ] Data is returned in expected format
- [ ] Old code using `AIPS_Template_Repository` still works via compatibility layer

---

### Phase 2: Models & Interfaces (2-3 hours)

**Why next:** Models are data structures with minimal dependencies.

**Classes to migrate (8):**
1. GenerationContext (interface) → `Interfaces/GenerationContext.php`
2. Config → `Models/Config.php`
3. ArticleStructureManager → `Models/ArticleStructureManager.php`
4. HistoryContainer → `Models/HistoryContainer.php`
5. HistoryType → `Models/HistoryType.php`
6. TemplateContext → `Models/TemplateContext.php`
7. TemplateTypeSelector → `Models/TemplateTypeSelector.php`
8. TopicContext → `Models/TopicContext.php`

**Migration pattern:**
```php
// OLD: includes/interface-aips-generation-context.php
interface AIPS_Generation_Context {
    // ...
}

// NEW: src/Interfaces/GenerationContext.php
namespace AIPS\Interfaces;

interface GenerationContext {
    // ...
}
```

**Testing checklist:**
- [ ] Interfaces are properly implemented
- [ ] Models can be instantiated
- [ ] Data can be set and retrieved
- [ ] Type hints work correctly

---

### Phase 3: Services (5-6 hours)

**Why next:** Services use repositories and models but are used by controllers.

**Sub-phase 3.1: Core Services (4 classes)**
1. Logger → `Services/Logger.php`
2. ImageService → `Services/ImageService.php`
3. HistoryService → `Services/HistoryService.php`
4. SessionToJson → `Services/SessionToJson.php`

**Sub-phase 3.2: AI Services (4 classes)**
1. AIService → `Services/AI/AIService.php`
2. EmbeddingsService → `Services/AI/EmbeddingsService.php`
3. PromptBuilder → `Services/AI/PromptBuilder.php`
4. ResilienceService → `Services/AI/ResilienceService.php`

**Sub-phase 3.3: Content Services (4 classes)**
1. ComponentRegenerationService → `Services/Content/ComponentRegenerationService.php`
2. PostCreator → `Services/Content/PostCreator.php`
3. TemplateProcessor → `Services/Content/TemplateProcessor.php`
4. TemplateHelper → `Services/Content/TemplateHelper.php`

**Sub-phase 3.4: Research Services (3 classes)**
1. ResearchService → `Services/Research/ResearchService.php`
2. TopicExpansionService → `Services/Research/TopicExpansionService.php`
3. TopicPenaltyService → `Services/Research/TopicPenaltyService.php`

**Sub-phase 3.5: Generation Services (3 classes)**
1. SeederService → `Services/SeederService.php`
2. GenerationLogger → `Services/Generation/GenerationLogger.php`
3. GenerationSession → `Services/Generation/GenerationSession.php`

**Migration complexity notes:**
- Services have the most internal dependencies
- Require careful update of `use` statements
- May need dependency injection refactoring

**Testing checklist:**
- [ ] Service methods execute without errors
- [ ] Dependencies are properly injected
- [ ] Integration with repositories works
- [ ] API responses are correct

---

### Phase 4: Generators (2-3 hours)

**Classes to migrate (4):**
1. Generator → `Generators/Generator.php`
2. AuthorPostGenerator → `Generators/AuthorPostGenerator.php`
3. AuthorTopicsGenerator → `Generators/AuthorTopicsGenerator.php`
4. ScheduleProcessor → `Generators/ScheduleProcessor.php`

**Dependencies:** Services, Repositories, Models

**Testing checklist:**
- [ ] Content generation works
- [ ] Post creation succeeds
- [ ] Topics are generated correctly
- [ ] Schedule processing runs

---

### Phase 5: Controllers (4-5 hours)

**Why next:** Controllers orchestrate services and handle admin pages.

**Sub-phase 5.1: Core Controllers (2 classes)**
1. AIEditController → `Controllers/AIEditController.php`
2. DataManagementController → `Controllers/DataManagementController.php` (rename from DataManagement)

**Sub-phase 5.2: Admin Controllers (10 classes)**
1. AuthorsController → `Controllers/Admin/AuthorsController.php`
2. AuthorTopicsController → `Controllers/Admin/AuthorTopicsController.php`
3. CalendarController → `Controllers/Admin/CalendarController.php`
4. DashboardController → `Controllers/Admin/DashboardController.php`
5. GeneratedPostsController → `Controllers/Admin/GeneratedPostsController.php`
6. PromptSectionsController → `Controllers/Admin/PromptSectionsController.php`
7. ResearchController → `Controllers/Admin/ResearchController.php`
8. ScheduleController → `Controllers/Admin/ScheduleController.php`
9. StructuresController → `Controllers/Admin/StructuresController.php`
10. TemplatesController → `Controllers/Admin/TemplatesController.php`

**Migration notes:**
- Controllers have many AJAX handlers
- Need to preserve WordPress action/filter hooks
- May need to update JavaScript references

**Testing checklist:**
- [ ] Admin pages load correctly
- [ ] AJAX requests work
- [ ] Forms submit successfully
- [ ] Data displays properly

---

### Phase 6: Admin Classes (3-4 hours)

**Classes to migrate (10):**
1. AdminAssets → `Admin/AdminAssets.php`
2. Settings → `Admin/Settings.php`
3. DevTools → `Admin/DevTools.php`
4. History → `Admin/History.php`
5. Planner → `Admin/Planner.php`
6. PostReview → `Admin/PostReview.php`
7. SeederAdmin → `Admin/SeederAdmin.php`
8. SystemStatus → `Admin/SystemStatus.php`
9. Templates → `Admin/Templates.php`
10. Voices → `Admin/Voices.php`

**Migration notes:**
- These manage admin pages and assets
- Need to preserve enqueue hooks
- Settings may require special handling

**Testing checklist:**
- [ ] Assets load correctly
- [ ] CSS/JS enqueued properly
- [ ] Settings save and load
- [ ] Admin pages render

---

### Phase 7: Utilities & Supporting Classes (2-3 hours)

**Sub-phase 7.1: Utilities (3 classes)**
1. IntervalCalculator → `Utilities/IntervalCalculator.php`
2. Scheduler → `Utilities/Scheduler.php`
3. Upgrades → `Utilities/Upgrades.php`

**Sub-phase 7.2: Data Management (6 classes)**
1. Export → `DataManagement/Export/ExportHandler.php`
2. ExportJson → `DataManagement/Export/JsonExporter.php`
3. ExportMySQL → `DataManagement/Export/MySQLExporter.php`
4. Import → `DataManagement/Import/ImportHandler.php`
5. ImportJson → `DataManagement/Import/JsonImporter.php`
6. ImportMySQL → `DataManagement/Import/MySQLImporter.php`

**Sub-phase 7.3: Notifications (1 class)**
1. PostReviewNotifications → `Notifications/PostReviewNotifications.php`

**Testing checklist:**
- [ ] Cron jobs still run
- [ ] Upgrades execute correctly
- [ ] Import/export works
- [ ] Notifications send

---

### Phase 8: Main Plugin File & Integration (2-3 hours)

**Tasks:**
1. Update `ai-post-scheduler.php` to use new namespaces
2. Replace custom autoloader with Composer autoloader
3. Update all class instantiations
4. Update dependency injection

**Main file changes:**
```php
// OLD
require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
AIPS_Autoloader::register();

// NEW
require_once AIPS_PLUGIN_DIR . 'vendor/autoload.php';

use AIPS\Admin\Settings;
use AIPS\Controllers\Admin\DashboardController;
// etc.
```

**Testing checklist:**
- [ ] Plugin activates successfully
- [ ] No fatal errors
- [ ] All hooks registered
- [ ] Database tables created

---

### Phase 9: Compatibility Layer & Cleanup (1-2 hours)

**Create compatibility aliases:**

```php
// includes/compatibility-loader.php
<?php
// Provide backward compatibility for third-party code

// Repositories
class_alias('AIPS\\Repositories\\TemplateRepository', 'AIPS_Template_Repository');
class_alias('AIPS\\Repositories\\ScheduleRepository', 'AIPS_Schedule_Repository');
// ... etc for all classes

// This file can be removed after ensuring no external dependencies
```

**Tasks:**
1. Create class aliases for all migrated classes
2. Add deprecation notices (optional)
3. Test with backward compatibility enabled
4. Document deprecation timeline

---

### Phase 10: Testing & Validation (3-4 hours)

**Unit tests:**
- [ ] Update test bootstrap to use PSR-4
- [ ] Update test class names and namespaces
- [ ] Run full test suite
- [ ] Fix any failing tests

**Integration tests:**
- [ ] Install plugin in clean WordPress
- [ ] Test all admin pages
- [ ] Test content generation
- [ ] Test scheduling
- [ ] Test import/export
- [ ] Test settings

**Performance tests:**
- [ ] Measure autoload time
- [ ] Check memory usage
- [ ] Verify no N+1 queries

---

### Phase 11: Documentation & Finalization (2-3 hours)

**Documentation updates:**
1. Update README.md with new structure
2. Create ARCHITECTURE.md explaining PSR-4 structure
3. Update inline documentation
4. Create migration guide for developers
5. Update CHANGELOG.md

**Code cleanup:**
1. Remove old `includes/class-aips-*.php` files
2. Remove custom autoloader
3. Remove compatibility layer (if safe)
4. Run code quality tools (PHPCS, PHPStan)

---

## Migration Strategy

### Backward Compatibility Approach

**Option 1: Gradual migration (Recommended)**
- Keep compatibility layer for 2-3 versions
- Add deprecation notices
- Give developers time to update

**Option 2: Breaking change**
- Remove old structure immediately
- Document breaking changes
- Increment major version

**Recommendation:** Use Option 1 for community plugins.

### Testing Strategy

**Per-phase testing:**
1. Unit tests for migrated classes
2. Integration tests for features
3. Manual testing of admin UI

**Regression testing:**
- Run full test suite after each phase
- Test with sample data
- Test in staging environment

### Rollback Plan

**Git strategy:**
1. Create feature branch: `refactor/psr4-migration`
2. Create sub-branches for each phase
3. Merge phases incrementally
4. Tag releases: `v2.0.0-alpha.1`, `v2.0.0-alpha.2`, etc.

**If issues arise:**
1. Revert to previous tag
2. Fix issues in isolation
3. Re-merge when stable

---

## Risk Assessment

### High Risk Areas

**1. Controllers with AJAX handlers**
- **Risk:** JavaScript may reference old class names
- **Mitigation:** Keep class aliases, test all AJAX endpoints

**2. Third-party integrations**
- **Risk:** External code may use class names directly
- **Mitigation:** Maintain compatibility layer indefinitely

**3. Serialized data in database**
- **Risk:** Class names in serialized data will break
- **Mitigation:** Create migration script to update serialized data

**4. Cron jobs**
- **Risk:** Scheduled tasks may reference old classes
- **Mitigation:** Re-register all cron jobs after migration

### Medium Risk Areas

**1. Template files**
- **Risk:** Templates may instantiate classes directly
- **Mitigation:** Update all template files, test rendering

**2. Settings and configuration**
- **Risk:** Saved settings may contain class references
- **Mitigation:** Create settings migration script

**3. Hooks and filters**
- **Risk:** Hook names may be class-name dependent
- **Mitigation:** Maintain hook names, use aliases

### Low Risk Areas

**1. CSS/JS assets**
- **Risk:** Asset loading is independent
- **Mitigation:** No changes needed

**2. Database schema**
- **Risk:** Tables and queries are independent
- **Mitigation:** No changes needed

---

## Timeline Estimate

### Breakdown by phase:
- Phase 0: Preparation - 1-2 hours
- Phase 1: Repositories - 3-4 hours
- Phase 2: Models & Interfaces - 2-3 hours
- Phase 3: Services - 5-6 hours
- Phase 4: Generators - 2-3 hours
- Phase 5: Controllers - 4-5 hours
- Phase 6: Admin Classes - 3-4 hours
- Phase 7: Utilities - 2-3 hours
- Phase 8: Main Plugin - 2-3 hours
- Phase 9: Compatibility - 1-2 hours
- Phase 10: Testing - 3-4 hours
- Phase 11: Documentation - 2-3 hours

**Total estimated time:** 30-42 hours (4-6 working days)

**Recommended schedule:**
- Week 1: Phases 0-4 (Foundation)
- Week 2: Phases 5-7 (Application layer)
- Week 3: Phases 8-11 (Integration & testing)

---

## Success Criteria

### Functional Requirements
- [ ] All 77 classes migrated to PSR-4
- [ ] Plugin activates without errors
- [ ] All admin pages load correctly
- [ ] Content generation works
- [ ] Scheduling functions properly
- [ ] All tests pass

### Code Quality Requirements
- [ ] PSR-4 autoloading works
- [ ] Proper namespace structure
- [ ] No deprecated WordPress functions
- [ ] Pass PHPCS WordPress standards
- [ ] PHPStan level 5 or higher

### Performance Requirements
- [ ] Autoload time < 100ms
- [ ] Memory usage within 10% of current
- [ ] No performance regressions

### Documentation Requirements
- [ ] Architecture documented
- [ ] Migration guide created
- [ ] Inline docs updated
- [ ] CHANGELOG updated

---

## Tools & Resources

### Required Tools
1. **Composer** - For PSR-4 autoloading
2. **PHPUnit** - For testing
3. **PHPCS** - For code standards
4. **PHPStan** - For static analysis

### Useful Scripts

**Mass rename script:**
```bash
#!/bin/bash
# rename-psr4.sh
for file in includes/class-aips-*.php; do
    base=$(basename "$file" .php)
    name=${base#class-aips-}
    pascal=$(echo "$name" | sed 's/-\([a-z]\)/\U\1/g' | sed 's/^./\U&/')
    echo "Renaming $file to src/.../$pascal.php"
done
```

**Namespace injection script:**
```php
<?php
// add-namespace.php
$files = glob('src/**/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $namespace = determine_namespace($file);
    $new_content = add_namespace($content, $namespace);
    file_put_contents($file, $new_content);
}
```

---

## Post-Migration Tasks

### Version 1 (with compatibility):
- Release v2.0.0-alpha with compatibility layer
- Monitor for issues
- Gather feedback

### Version 2 (deprecation warnings):
- Release v2.1.0 with deprecation notices
- Update documentation
- Notify community

### Version 3 (cleanup):
- Release v3.0.0 without compatibility layer
- Remove old files
- Final documentation update

---

## Conclusion

This PSR-4 refactoring will modernize the codebase, improve maintainability, and align with PHP best practices. The phased approach minimizes risk while delivering incremental value.

**Next Steps:**
1. Review and approve this plan
2. Create feature branch
3. Begin Phase 0 (Preparation)
4. Execute phases sequentially
5. Test thoroughly at each stage

**Questions or concerns?** Contact the development team before proceeding.

---

**Document Version:** 1.0  
**Last Updated:** 2026-02-10  
**Author:** AI Post Scheduler Development Team  
**Status:** Draft - Pending Approval
