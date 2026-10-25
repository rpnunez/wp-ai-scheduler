# Code Health Metrics

This document tracks long-term code health indicators, architectural seams, dependency injection migration metrics, and request-boundary test coverage for `ai-post-scheduler`.

---

## 1. Direct Construction Health (Dependency Injection)

Direct instantiation of core service and repository dependencies via `new ClassName()` bypasses the container, making unit testing fragile and preventing polymorphic overrides or instrumentation.

### Target: Zero Direct Instantiations in `includes/`

| Component | Target Baseline (v3.7.18) | Phase 3 (v3.7.19) | Status |
|:---|:---:|:---:|:---:|
| `new AIPS_History_Repository` | ~8 | **0** | ✅ **100% DI Completed** |
| `new AIPS_History_Service` | ~19 | **0** | ✅ **100% DI Completed** |

All resolution now delegates through:
- `AIPS_Container::get_instance()->make(AIPS_History_Repository_Interface::class)`
- `AIPS_Container::get_instance()->make(AIPS_History_Service_Interface::class)`
- Safe singleton fallbacks `AIPS_History_Repository::instance()` / `AIPS_History_Service::instance()` when the container is not initialized.

Enforced by automated guard test: `ai-post-scheduler/tests/Test_AIPS_Code_Health_Metrics.php`.

---

## 2. Deprecated Compatibility Adapters & Sunset Targets

During modularization of large monoliths (`AIPS_History`, `AIPS_Generator`, `AIPS_DB_Manager`), backward-compatibility wrappers and facades are maintained so existing call sites, unit tests, and third-party extensions do not experience breaking changes.

| Adapter / Facade Class | Original Role | Delegated Modular Seam | Sunset Target | Removal Strategy |
|:---|:---|:---|:---:|:---|
| `AIPS_History` | 2,340-line god class (UI, AJAX, Query, Formatting) | `AIPS_History_Controller`<br>`AIPS_History_UI`<br>`AIPS_History_Modal_Presenter`<br>`AIPS_History_Stalled_Detector` | v4.0.0 | Deprecate public wrapper methods in favor of calling specific domain services directly. |
| `AIPS_History_Repository` stalled helpers | Inlined stalled SQL condition building and closing | `AIPS_History_Stalled_Detector` | v4.0.0 | Static helpers forward to `AIPS_History_Stalled_Detector`. Keep until v4.0. |
| `AIPS_DB_Manager` datetime helpers | Inlined datetime column map & bigint conversion | `AIPS_DB_Datetime_Migrator` | v4.0.0 | Static wrappers delegate to `AIPS_DB_Datetime_Migrator`. |
| `AIPS_Stress_Test_Service` data cleaner | Inlined post/attachment deletion loops | `AIPS_Stress_Test_Data_Cleaner` | v4.0.0 | Retain delegation methods on service for testing harnesses. |
| `AIPS_Generator` content normalization | Inlined Markdown parsing, title stripping, excerpt truncation | `AIPS_Content_Normalizer` | v4.0.0 | Retain `normalize_generated_content_for_wordpress` and `truncate_excerpt` facades. |

---

## 3. Large Controller Endpoint Counts

Controllers should focus strictly on request validation, capability/nonce verification, parameter sanitization, dispatching to services, and formatting responses.

| Controller Class | Registered AJAX Endpoints | Primary Responsibility |
|:---|:---:|:---|
| `AIPS_History_Controller` | 8 | History reload, clear, bulk delete, modal, details, logs, retry, export |
| `AIPS_Schedule_Controller` | 11 | Schedule saving, deletion, toggling, run-now, post counts, history |
| `AIPS_Author_Topics_Controller` | 10 | Author topic approval, generation, suggestions, indexing |
| `AIPS_Templates_Controller` | 6 | Template save, delete, clone, preview, batch configuration |
| `AIPS_Generated_Posts_Controller` | 6 | Review, publish, reject, regenerate, status filtering |
| `AIPS_AI_Edit_Controller` | 4 | AI revision viewer, suggestions, inline post editing |

---

## 4. Request-Boundary Test Coverage

Every AJAX endpoint registered in `AIPS_Ajax_Registry::$map` must be backed by a request-boundary test exercising nonce validation, capability checks (`manage_options`), input sanitization, and JSON response shape.

| Controller | Endpoints Tested | Suite | Pass Rate |
|:---|:---:|:---|:---:|
| `AIPS_History_Controller` | 8 / 8 | `tests/Test_AIPS_History_Controller.php` | 100% (21 tests, 85 assertions) |
| `AIPS_Schedule_Controller` | 11 / 11 | `tests/Test_Schedule_Controller.php` | 100% |
| `AIPS_Author_Topics_Controller` | 10 / 10 | `tests/Test_Author_Topics_Controller.php` | 100% |
| `AIPS_Templates_Controller` | 6 / 6 | `tests/Test_Templates_Controller.php` | 100% |
| `AIPS_Post_Review` | 8 / 8 | `tests/Test_Post_Review_Ajax.php` | 100% |
| `AIPS_Settings_Ajax` | 2 / 2 | `tests/Test_Settings_Ajax.php` | 100% |

---

## 5. Automated CI Health Guards

The test suite includes `Test_AIPS_Code_Health_Metrics.php` which executes during every CI and local test run to prevent code health regressions:
1. `test_zero_direct_history_instantiations_in_includes`: Ensures no file under `includes/` invokes `new AIPS_History_Repository()` or `new AIPS_History_Service()`.
2. `test_ajax_registry_controller_parity`: Ensures every entry in `AIPS_Ajax_Registry::$map` references a valid controller class implementing the corresponding `ajax_*` method.
3. `test_modular_history_size`: Enforces that `class-aips-history.php` remains under 500 lines (down from 2,340 lines).
