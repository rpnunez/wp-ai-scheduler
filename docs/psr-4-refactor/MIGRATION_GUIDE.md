# PSR-4 Migration Guide for Developers

This guide helps developers migrate from the old `AIPS_*` class names to the new PSR-4 namespaced classes in AI Post Scheduler v2.0.0.

## Quick Reference

**Old names still work.** The compatibility layer ensures all `AIPS_*` class names resolve to the new namespaced classes. No immediate changes are required. Migrate at your own pace.

## When to Migrate

- **Third-party code** (themes, plugins) that extends or uses AI Post Scheduler classes
- **Custom code** that instantiates or references `AIPS_*` classes
- **New development** — prefer namespaced classes for better IDE support and clarity

## Migration Steps

### 1. Add Use Statements

At the top of your file, add `use` statements for the classes you need:

```php
<?php
use AIPS\Repositories\TemplateRepository;
use AIPS\Services\AI\AIService;
use AIPS\Models\Config;
```

### 2. Replace Class References

| Old | New |
|-----|-----|
| `new AIPS_Template_Repository()` | `new TemplateRepository()` |
| `new AIPS_AI_Service()` | `new AIService()` |
| `AIPS_Config::get_instance()` | `Config::get_instance()` |
| `AIPS_DB_Manager::install_tables()` | `\AIPS\Repositories\DBManager::install_tables()` (or use + `DBManager::install_tables()`) |

### 3. Interface Implementations

```php
// Old
class MyContext implements AIPS_Generation_Context { }

// New
use AIPS\Interfaces\GenerationContext;
class MyContext implements GenerationContext { }
```

### 4. Type Hints and Docblocks

Update type hints and `@param` / `@return` annotations:

```php
// Old
/** @param AIPS_Generation_Context $context */
public function process($context) { }

// New
/** @param \AIPS\Interfaces\GenerationContext $context */
public function process(GenerationContext $context) { }
```

## Common Issues

### "Class not found" in namespaced files

When calling global classes (WordPress or old aliases) from within a namespaced file, use the leading backslash:

```php
// Wrong — resolves to AIPS\Services\AI\WP_Error
return new WP_Error('code', 'message');

// Correct
return new \WP_Error('code', 'message');
```

### Static calls to global classes

```php
// Wrong — may resolve to current namespace
AIPS_Config::get_instance();

// Correct
\AIPS_Config::get_instance();
// Or with use:
use AIPS\Models\Config;
Config::get_instance();
```

### Instanceof checks

```php
// Wrong — AIPS_Generation_Context may resolve to non-existent class in namespace
if ($obj instanceof AIPS_Generation_Context) { }

// Correct
if ($obj instanceof \AIPS_Generation_Context) { }
```

## Complete Class Mapping

See [PSR4_CLASS_MAPPING.md](./PSR4_CLASS_MAPPING.md) for the full mapping of all 77 classes.

## Deprecation Timeline

| Version | Status |
|---------|--------|
| v2.0.0 | Old names work via aliases. No warnings. |
| v2.1.0 | Deprecation notices may be added (optional). |
| v3.0.0 | Compatibility layer removed. **Breaking change.** |

Plan to migrate custom code before v3.0.0.

## Architecture

See [ARCHITECTURE.md](./ARCHITECTURE.md) for the full PSR-4 directory structure and dependency patterns.
