# AIPS Container Binding Catalog

This catalog documents bindings registered by AI_Post_Scheduler during boot_common via register_container_bindings.

## Conventions
- Lifetime: singleton means one shared instance per request lifecycle.
- Context: all bindings are currently registered in common boot and available to admin/ajax/cron/frontend requests.
- Alias: interface or abstract id resolved to a concrete service binding.

| Binding ID | Concrete Target | Lifetime | Type | Context |
|---|---|---|---|---|
| AIPS_Config::class | AIPS_Config::get_instance() | singleton | concrete | all |
| AIPS_Logger::class | AIPS_Logger::instance() | singleton | concrete | all |
| AIPS_Logger_Interface::class | alias -> AIPS_Logger::class | singleton | alias | all |
| AIPS_History_Repository::class | AIPS_History_Repository::instance() | singleton | concrete | all |
| AIPS_History_Repository_Interface::class | alias -> AIPS_History_Repository::class | singleton | alias | all |
| AIPS_History_Service::class | AIPS_History_Service::instance() | singleton | concrete | all |
| AIPS_History_Service_Interface::class | alias -> AIPS_History_Service::class | singleton | alias | all |
| AIPS_AI_Service::class | AIPS_AI_Service::instance() | singleton | concrete | all |
| AIPS_AI_Service_Interface::class | alias -> AIPS_AI_Service::class | singleton | alias | all |
| AIPS_Resilience_Service::class | new AIPS_Resilience_Service(logger, config) | singleton | concrete | all |
| AIPS_Notifications_Repository::class | AIPS_Notifications_Repository::instance() | singleton | concrete | all |
| AIPS_Notifications_Repository_Interface::class | alias -> AIPS_Notifications_Repository::class | singleton | alias | all |
| AIPS_Schedule_Repository::class | AIPS_Schedule_Repository::instance() | singleton | concrete | all |
| AIPS_Schedule_Repository_Interface::class | alias -> AIPS_Schedule_Repository::class | singleton | alias | all |
| AIPS_Telemetry_Repository::class | AIPS_Telemetry_Repository::instance() | singleton | concrete | all |
| AIPS_Template_Repository::class | AIPS_Template_Repository::instance() | singleton | concrete | all |

## Registration Ownership
- Orchestrator: AI_Post_Scheduler::register_container_bindings
- Core bindings: AI_Post_Scheduler::register_core_bindings
- Domain bindings: AI_Post_Scheduler::register_domain_bindings
- Runtime-dependent bindings: AI_Post_Scheduler::register_runtime_bindings_if_needed

## Notes
- Interface aliases now use container alias helpers for consistent governance.
- Runtime-dependent registration hook is present for future context-sensitive bindings.
