# 2026-11-03 - [Constructor Injection: AIPS_Broken_Links_Service]

- **Target:** `ai-post-scheduler/includes/class-aips-broken-links-service.php::AIPS_Broken_Links_Service`
- **Lens:** Hard coupling (Tuesday): `::get_instance()` singleton calls and `new` collaborator instantiation inside methods → constructor injection via `AIPS_Container`
- **Context:** `AIPS_Broken_Links_Service::undo()` directly instantiated its collaborator with `new AIPS_Link_Insertion_Engine()`, preventing dependency substitution and mockability in unit tests.
- **Decision:** Added private property `$engine` typed as `AIPS_Link_Insertion_Engine`. Expanded the constructor signature with an optional `?AIPS_Link_Insertion_Engine $engine = null` parameter that resolves from `AIPS_Container` when registered or falls back to `new AIPS_Link_Insertion_Engine()`. Refactored `undo()` to utilize the injected `$this->engine`.
- **Backward compatibility:** Preserved full backward compatibility for existing callers of `new AIPS_Broken_Links_Service(...)` by placing the new parameter as optional with an automatic container/default fallback. All public method signatures and behaviors remain identical.
- **Consequence:** Clean decoupling of link insertion/reversion mechanics from broken links orchestration, enabling deterministic isolation and mocking in automated tests without runtime side effects.
- **Tests:** Created `ai-post-scheduler/tests/Test_AIPS_Broken_Links_Service.php` with 4 tests covering default construction, container resolution, explicit parameter injection, and mock engine delegation in `undo()`. Ran `make test ARGS="tests/Test_AIPS_Broken_Links_Service.php"` (4 tests, 7 assertions passed) and executed `composer lint:repository-boundary`.
