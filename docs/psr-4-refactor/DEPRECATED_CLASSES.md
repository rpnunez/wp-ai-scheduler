# Deprecated Class Names (PSR-4 Migration)

As of v2.0.0, all plugin classes use PSR-4 namespacing. The following old class names are **deprecated** but continue to work via the compatibility layer in `includes/compatibility-loader.php`.

## Deprecation Timeline

| Version | Status |
|---------|--------|
| v2.0.0 | Old names work via aliases. No warnings. |
| v2.1.0 | Deprecation notices may be added (optional). |
| v3.0.0 | Compatibility layer removed. **Breaking change.** |

## Migration Guide

Replace old class names with new namespaced classes:

```php
// OLD (deprecated)
$repo = new AIPS_Template_Repository();

// NEW (recommended)
use AIPS\Repositories\TemplateRepository;
$repo = new TemplateRepository();
```

See [PSR4_CLASS_MAPPING.md](./PSR4_CLASS_MAPPING.md) for the complete mapping of all 77 classes.

## All Deprecated Classes (77 total)

- **Repositories (13):** AIPS_DB_Manager, AIPS_Template_Repository, AIPS_Schedule_Repository, etc.
- **Models (7):** AIPS_Config, AIPS_History_Type, AIPS_Template_Context, etc.
- **Interfaces (1):** AIPS_Generation_Context
- **Services (18):** AIPS_Logger, AIPS_AI_Service, AIPS_Template_Processor, etc.
- **Generators (4):** AIPS_Generator, AIPS_Author_Post_Generator, etc.
- **Controllers (12):** AIPS_AI_Edit_Controller, AIPS_Data_Management, AIPS_Templates_Controller, etc.
- **Admin (10):** AIPS_Settings, AIPS_Admin_Assets, AIPS_Planner, AIPS_Dev_Tools, etc.
- **Utilities (2):** AIPS_Interval_Calculator, AIPS_Author_Topics_Scheduler
- **Data Management (6):** AIPS_Data_Management_Export_*, AIPS_Data_Management_Import_*
- **Notifications (1):** AIPS_Post_Review_Notifications

## Note on AIPS_Autoloader

The `AIPS_Autoloader` class has been **replaced** (not deprecated) by Composer's PSR-4 autoloader. It is no longer loaded. Do not reference it in new code.
