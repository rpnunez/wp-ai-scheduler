## Overview

This PR is the combined landing point for the full **AI provider abstraction refactor**. It consolidates work from three development branches:

1. **`claude/romantic-poitras-1b1369`** — the core provider abstraction (originally PR #1809)
2. **`codex/review-and-address-pr-comments`** — WP AI Client hardening and diagnostics (originally PR #1815)
3. **`claude/pr-1815-review-plan-4gm204`** (this branch) — two rounds of Gemini Code Assist review fixes

PRs #1809 and #1815 will be closed in favor of this one.

---

## What changed and why

### 1. AI Provider abstraction layer

**The problem:** `AIPS_AI_Service` was tightly coupled to the Meow Apps AI Engine — it imported the global `$mwai` object directly in every generation method, making it impossible to swap in a different backend without touching the service itself.

**The solution:** A thin provider interface + factory that the service delegates to.

#### New: `AIPS_AI_Provider_Interface`

Defines the contract every AI backend must satisfy:

```php
interface AIPS_AI_Provider_Interface {
    public function get_id(): string;
    public function get_label(): string;
    public function is_available(): bool;
    public function get_unavailable_reason(): string;
    public function generate_text(string $prompt, array $params): string;
    public function generate_json(?string $prompt, array $params): ?array;
    public function generate_image(string $prompt, array $params): string;
    public function generate_embedding(string $text, array $params): array;
    public function supports_native_json(): bool;
    public function supports_embeddings(): bool;
    public function extract_error_code(string $message): string;
}
```

All four generation methods have strict PHP return types. `get_unavailable_reason()` is part of the contract (not opt-in via `method_exists`), so the settings UI and factory can always surface a diagnostic message without ad-hoc checks.

#### New: `AIPS_AI_Provider_Factory`

Owns a `REGISTRY` of known provider IDs → class names and provides:

- `create(?string $id)` — resolves the active provider: explicit ID → first available → `AIPS_Null_AI_Provider`
- `available_providers()` — map of `id => label` for providers that pass `is_available()`
- `all_providers()` — same map but including unavailable ones (for the settings dropdown)
- `unavailable_reasons()` — map of `id => human-readable reason` for unavailable providers; used by the settings UI to show diagnostic notes beneath the dropdown

#### New: `AIPS_Meow_AI_Provider`

Wraps the existing Meow Apps AI Engine (`$mwai` / `$mwai_core` globals). Preserves all historical behavior of the direct `$mwai->simpleTextQuery()` / `simpleJsonQuery()` / `simpleImageQuery()` / embedding paths. Adds `get_unavailable_reason()` returning `'Meow Apps AI Engine plugin is not installed or active.'`.

#### New: `AIPS_WP_AI_Client_Provider`

Adapts the WordPress 7.0 native AI Client (`wp_ai_client_prompt()`) to the interface. Key design points:

- **Capability probing:** `is_available()` delegates to `supports_text_generation()`, which calls `create_prompt_builder()` and checks `is_supported_for_text_generation()` on the builder. This ensures the provider is only considered available when a connector is actually configured for text generation — not just when the function exists.
- **Cached probe builder:** `create_prompt_builder()` uses a `static $cache` array keyed on `spl_object_hash($this)`. All capability checks within a single request (`is_available`, `get_unavailable_reason`, `supports_native_json`) share one `wp_ai_client_prompt('')` call instead of making redundant ones.
- **Runtime capability guards:** `generate_text()` throws `text_generation_not_supported` and `generate_image()` throws `image_generation_not_supported` when the configured connector doesn't support the requested operation, giving the resilience layer a classifiable error code.
- **WP_Error handling:** `build_prompt()` and all `generate_*()` methods convert `WP_Error` returns into exceptions via `throw_from_wp_error()`, so `AIPS_AI_Service`'s existing try/catch + `extract_error_code()` path works uniformly.
- **`supports_native_json()`:** Returns `true` only when the builder has `as_json_response()` AND supports text generation — not unconditionally.
- **`generate_image()` string guarantee:** Always returns a string (handles object with `getDataUri()`, array of objects/strings, bare string, and unexpected types via `is_string($result) ? $result : ''`).

#### New: `AIPS_Null_AI_Provider`

Returned by the factory when no backend is available. All `generate_*()` methods throw, but the service's `is_available()` guard prevents them from ever being reached in practice. Exists to keep the provider contract total and avoid nullable provider references throughout the codebase. `get_unavailable_reason()` returns `'No AI provider is currently available.'`.

#### Refactored: `AIPS_AI_Service`

- Receives an `AIPS_AI_Provider_Interface` instance via constructor injection (defaulting to `AIPS_AI_Provider_Factory::create()`).
- All five generation methods (`generate_text`, `generate_json`, `generate_image`, `generate_embedding`, and the JSON fallback path) now delegate directly to the provider instead of importing `$mwai` themselves.
- The `is_available()` guard error message updated from the Meow-specific `'AI Engine plugin is not available.'` to the provider-agnostic `'The selected AI provider is not available.'` across all five call sites.
- `prepare_options()` now calls `wp_parse_args($options)` before processing to normalize array/object inputs.

#### Refactored: `AIPS_Embeddings_Service`

Delegates embedding generation to the active provider's `generate_embedding()` rather than directly calling the Meow Engine.

---

### 2. Settings UI diagnostics

The provider dropdown in the settings page now:

- Labels unavailable providers with `(currently unavailable)` instead of the old `(not detected)`.
- Shows a `<ul class="description aips-provider-readiness">` list beneath the dropdown when any provider is unavailable, with one `<li>` per provider explaining the specific reason (e.g. "WordPress AI Client: WordPress AI Client has no connector/model configured for text generation.").
- Updates the description text to mention that Auto-detect requires a connector ready for text generation, not just the function being present.

---

### 3. Dependency check hardening (`ai-post-scheduler.php`)

The `check_dependencies()` admin_init callback now guards with `function_exists('wp_ai_client_prompt')` **before** `class_exists('AIPS_WP_AI_Client_Provider')`. On all WordPress installations prior to 7.0 (where `wp_ai_client_prompt` doesn't exist), this short-circuits the check without loading or instantiating the provider class on every admin page load.

---

### 4. New unit tests

**`Test_AIPS_WP_AI_Client_Provider`** covers:

| Test | What it verifies |
|---|---|
| `test_is_available_requires_text_generation_support` | Provider reports unavailable + correct reason when builder exists but text generation is unsupported |
| `test_is_available_returns_false_when_builder_creation_fails` | Provider reports unavailable when `wp_ai_client_prompt` returns `WP_Error` |
| `test_factory_autodetect_does_not_select_wp_ai_client_without_text_support` | Factory auto-detect skips WP AI Client when it can't generate text |
| `test_factory_available_providers_includes_wp_ai_client_when_text_ready` | Factory includes WP AI Client in available set when fully configured |
| `test_supports_native_json_requires_json_api_and_text_support` | `supports_native_json()` requires both `as_json_response()` method and text generation support |
| `test_generate_image_throws_when_image_generation_unsupported` | `generate_image()` throws with classifiable error code when connector lacks image support |
| `test_service_falls_back_directly_when_wp_provider_native_json_unsupported` | Service correctly falls back to text-based JSON extraction when `supports_native_json()` is false |

The test file defines a global stub `wp_ai_client_prompt()` (guarded by `function_exists`) and two builder stubs (`AIPS_Test_WP_AI_Client_Builder` with full capability support, `AIPS_Test_WP_AI_Client_Builder_Without_JSON` without `as_json_response`). Tests use `$aips_wp_ai_client_test_builder` as a global seam to control builder behavior.

---

### 5. Composer autoload

`vendor/composer/autoload_classmap.php` and `autoload_static.php` updated to include the new provider classes and test class. Two stale entries (`AIPS_Automations_Controller`, `AIPS_Dashboard_Repository`) that no longer exist on disk were removed.

---

## File inventory

| File | Status | Notes |
|---|---|---|
| `ai-post-scheduler.php` | Modified | `function_exists` guard in `check_dependencies()` |
| `includes/interface-aips-ai-provider-interface.php` | **New** | Provider contract |
| `includes/interface-aips-ai-service-interface.php` | Modified | Minor updates |
| `includes/class-aips-ai-provider-factory.php` | **New** | Provider registry and resolution |
| `includes/class-aips-ai-service.php` | Modified | Provider delegation, generic error messages |
| `includes/class-aips-embeddings-service.php` | Modified | Provider delegation |
| `includes/class-aips-settings-ui.php` | Modified | Diagnostic reasons UI |
| `includes/class-aips-settings.php` | Modified | Provider option handling |
| `includes/class-aips-config.php` | Modified | Minor |
| `includes/class-aips-autoloader.php` | Modified | Includes providers directory |
| `includes/providers/class-aips-meow-ai-provider.php` | **New** | Meow Engine adapter |
| `includes/providers/class-aips-wp-ai-client-provider.php` | **New** | WordPress AI Client adapter |
| `includes/providers/class-aips-null-ai-provider.php` | **New** | Null/fallback provider |
| `tests/Test_AIPS_WP_AI_Client_Provider.php` | **New** | 7 unit tests |
| `tests/Test_AIPS_AI_Provider_Factory.php` | **New** | Factory tests |
| `tests/Test_AIPS_AI_Service_With_Provider.php` | **New** | Service+provider integration tests |
| `vendor/composer/autoload_classmap.php` | Modified | New classes, removed stale entries |
| `vendor/composer/autoload_static.php` | Modified | Same |
| `vendor/composer/installed.php` | Modified | Updated reference SHA |

---

## Testing

```bash
# All new provider tests
vendor/bin/phpunit --filter AIPS_WP_AI_Client_Provider

# Factory tests
vendor/bin/phpunit --filter AIPS_AI_Provider_Factory

# Full suite (regression check)
vendor/bin/phpunit
```

All tests passed at time of last push.

---

## Related PRs (to be closed)

- #1809 — original AI provider abstraction commit (superseded by this PR)
- #1815 — WP AI Client hardening commit (superseded by this PR)