# AIPS Container Instantiation Skill

## Purpose
Use this skill whenever you need to implement or refactor object creation to use the plugin's DI container (`AIPS_Container`) instead of direct `new` instantiation.

## When to Use
- Adding a new service/controller/repository dependency.
- Refactoring legacy code that constructs shared services directly.
- Updating boot flows (`boot_common`, `boot_admin`, `boot_ajax`, `boot_cron`) to resolve container-managed singletons.

## Core Rule
Prefer:

```php
$container = AIPS_Container::get_instance();
$service   = $container->make( AIPS_Some_Service::class );
```

Instead of:

```php
$service = new AIPS_Some_Service();
```

## Implementation Workflow
1. **Locate direct instantiations**
   - Search in `ai-post-scheduler/includes/` and `ai-post-scheduler/ai-post-scheduler.php` for `new AIPS_` patterns.
2. **Classify the dependency**
   - If it is a shared infrastructure/service class, bind it in `AIPS_Container` as a singleton.
   - If it is request-specific and not shared, keep direct instantiation only when container resolution is unnecessary.
3. **Register/verify binding**
   - Add a singleton binding in the container bootstrap if missing.
   - Add interface alias bindings where testability benefits (`*_Interface` => concrete).
4. **Replace construction sites**
   - Use `AIPS_Container::get_instance()->make( ClassName::class )`.
   - Preserve behavior and constructor arguments by moving argument wiring into the binding closure.
5. **Validate lifecycle context**
   - Ensure only required subsystems are instantiated in each boot method.
   - Avoid eager loading of unrelated controllers/services.
6. **Add/adjust tests**
   - Update PHPUnit coverage for both successful resolution and failure/fallback paths.
   - Confirm mocks still work through interface aliases.

## WordPress + Project Conventions
- Keep SQL out of controllers; use repositories.
- Keep controller registration in constructors and `AIPS_Ajax_Registry` mappings.
- Use tabs and `array()` syntax in PHP files.
- Add `if (!defined('ABSPATH')) { exit; }` to new PHP files.

## Review Checklist
- [ ] No unnecessary `new AIPS_*` for container-managed classes.
- [ ] New bindings are registered in the container bootstrap.
- [ ] Interface aliases exist where needed for tests.
- [ ] Boot method keeps context-specific lazy instantiation behavior.
- [ ] Tests pass via `composer test` from `ai-post-scheduler/`.

## Example Patterns

### Constructor injection via container binding
```php
$container->singleton(
	AIPS_Feature_Service::class,
	function ( $c ) {
		return new AIPS_Feature_Service(
			$c->make( AIPS_Logger::class ),
			$c->make( AIPS_Template_Repository::class )
		);
	}
);
```

### Runtime resolution
```php
$feature_service = AIPS_Container::get_instance()->make( AIPS_Feature_Service::class );
$feature_service->run();
```
