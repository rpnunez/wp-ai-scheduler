# AI Backend Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce an internal AI backend seam with a factory and facade, while preserving current Meow AI Engine runtime behavior and avoiding any user-facing changes.

**Architecture:** Extract the existing Meow-specific logic out of `AIPS_AI_Service` into a provider-specific implementation class, add a factory that resolves the active backend to Meow by default, and convert `AIPS_AI_Service` into a stable facade that delegates to the resolved backend. Keep existing consumer contracts and constructor patterns intact so the rest of the plugin continues to depend on `AIPS_AI_Service_Interface` without call-site churn.

**Tech Stack:** PHP 8.2+, WordPress plugin architecture, PHPUnit 10.5, Composer autoload/classmap, limited-mode `tests/bootstrap.php`

---

### Task 1: Add Failing Seam Tests

**Files:**
- Create: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`
- Modify: `ai-post-scheduler/tests/bootstrap.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Tests for AI backend factory and facade delegation seams.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Test_Stub_AI_Backend_For_Factory implements AIPS_AI_Service_Interface {

	public $calls = array();

	public function is_available() {
		$this->calls[] = array('method' => 'is_available');
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->calls[] = array(
			'method' => 'generate_text',
			'prompt' => $prompt,
			'options' => $options,
		);
		return 'stub-text';
	}

	public function generate_json($prompt, $options = array()) {
		$this->calls[] = array(
			'method' => 'generate_json',
			'prompt' => $prompt,
			'options' => $options,
		);
		return array('stub' => true);
	}

	public function generate_image($prompt, $options = array()) {
		$this->calls[] = array(
			'method' => 'generate_image',
			'prompt' => $prompt,
			'options' => $options,
		);
		return 'https://example.com/image.jpg';
	}

	public function get_call_log() {
		$this->calls[] = array('method' => 'get_call_log');
		return array(
			array(
				'type' => 'text',
				'response' => array('success' => true),
			),
		);
	}

	public function clear_call_log() {
		$this->calls[] = array('method' => 'clear_call_log');
	}
}

class Test_AIPS_AI_Backend_Factory extends WP_UnitTestCase {

	protected function tearDown(): void {
		remove_all_filters('aips_ai_backend');
		remove_all_filters('aips_ai_backend_instance');
		parent::tearDown();
	}

	public function test_factory_defaults_to_meow_backend() {
		$backend = AIPS_AI_Service_Factory::create();

		$this->assertInstanceOf(AIPS_AI_Service_Interface::class, $backend);
		$this->assertInstanceOf(AIPS_Meow_AI_Service::class, $backend);
	}

	public function test_factory_can_return_filtered_backend_instance() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		add_filter('aips_ai_backend_instance', function($backend, $backend_id, $args) use ($stub) {
			return $stub;
		}, 10, 3);

		$backend = AIPS_AI_Service_Factory::create(array('logger' => null));

		$this->assertSame($stub, $backend);
	}

	public function test_ai_service_facade_delegates_generate_text() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		add_filter('aips_ai_backend_instance', function($backend, $backend_id, $args) use ($stub) {
			return $stub;
		}, 10, 3);

		$service = new AIPS_AI_Service();
		$result  = $service->generate_text('Prompt', array('temperature' => 0.3));

		$this->assertSame('stub-text', $result);
		$this->assertSame('generate_text', $stub->calls[0]['method']);
		$this->assertSame('Prompt', $stub->calls[0]['prompt']);
		$this->assertSame(0.3, $stub->calls[0]['options']['temperature']);
	}

	public function test_ai_service_facade_preserves_get_call_log() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		add_filter('aips_ai_backend_instance', function($backend, $backend_id, $args) use ($stub) {
			return $stub;
		}, 10, 3);

		$service = new AIPS_AI_Service();
		$log     = $service->get_call_log();

		$this->assertCount(1, $log);
		$this->assertSame('get_call_log', $stub->calls[0]['method']);
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: FAIL with class-not-found errors for `AIPS_AI_Service_Factory` and `AIPS_Meow_AI_Service`, or method-missing failures on the current non-facade `AIPS_AI_Service`.

- [ ] **Step 3: Update limited bootstrap loading for the new test surface**

Add the new class files to the manual include list in `ai-post-scheduler/tests/bootstrap.php` near the existing AI service entries:

```php
        'class-aips-resilience-service.php',
        'interface-aips-ai-service-interface.php',
        'class-aips-ai-service-factory.php',
        'class-aips-meow-ai-service.php',
        'class-aips-ai-service.php',
        'class-aips-image-service.php',
```

- [ ] **Step 4: Run test to verify it still fails for the right reason**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: FAIL because the new classes exist in bootstrap references but are not implemented yet, confirming the test is exercising the intended seam.

- [ ] **Step 5: Commit**

```bash
git add ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php ai-post-scheduler/tests/bootstrap.php
git commit -m "test: add failing ai backend seam coverage"
```

### Task 2: Implement Backend Factory

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-ai-service-factory.php`
- Modify: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`

- [ ] **Step 1: Write the failing factory-specific assertion if needed**

If Task 1 only covered default resolution, extend the test file with an explicit backend-id assertion:

```php
	public function test_factory_resolves_meow_backend_id_by_default() {
		$this->assertSame('meow', AIPS_AI_Service_Factory::get_backend_id());
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: FAIL because `AIPS_AI_Service_Factory::get_backend_id()` does not exist yet.

- [ ] **Step 3: Write minimal implementation**

Create `ai-post-scheduler/includes/class-aips-ai-service-factory.php`:

```php
<?php
/**
 * AI Service Factory
 *
 * Resolves the active AI backend implementation.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service_Factory {

	public const BACKEND_MEOW = 'meow';

	/**
	 * Resolve the active backend identifier.
	 *
	 * @return string
	 */
	public static function get_backend_id() {
		$backend_id = apply_filters('aips_ai_backend', self::BACKEND_MEOW);
		$backend_id = is_string($backend_id) ? sanitize_key($backend_id) : '';

		return '' !== $backend_id ? $backend_id : self::BACKEND_MEOW;
	}

	/**
	 * Create the backend service instance.
	 *
	 * @param array $args Optional constructor dependencies.
	 * @return AIPS_AI_Service_Interface
	 */
	public static function create($args = array()) {
		$backend_id = self::get_backend_id();
		$backend    = self::build_backend($backend_id, $args);
		$backend    = apply_filters('aips_ai_backend_instance', $backend, $backend_id, $args);

		if (!$backend instanceof AIPS_AI_Service_Interface) {
			$backend = self::build_backend(self::BACKEND_MEOW, $args);
		}

		return $backend;
	}

	/**
	 * Build a backend instance for the provided identifier.
	 *
	 * @param string $backend_id Backend identifier.
	 * @param array  $args       Optional constructor dependencies.
	 * @return AIPS_AI_Service_Interface
	 */
	private static function build_backend($backend_id, $args) {
		switch ($backend_id) {
			case self::BACKEND_MEOW:
			default:
				return new AIPS_Meow_AI_Service(
					isset($args['logger']) ? $args['logger'] : null,
					isset($args['config']) ? $args['config'] : null,
					isset($args['resilience_service']) ? $args['resilience_service'] : null
				);
		}
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: Partial progress. Factory-related failures should move forward, while facade/provider tests still fail until the next tasks are implemented.

- [ ] **Step 5: Commit**

```bash
git add ai-post-scheduler/includes/class-aips-ai-service-factory.php ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php
git commit -m "feat: add ai service factory"
```

### Task 3: Extract Meow Provider Implementation

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-meow-ai-service.php`
- Modify: `ai-post-scheduler/includes/class-aips-ai-service.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`

- [ ] **Step 1: Add a failing assertion for the provider class**

Extend the test file if needed with:

```php
	public function test_meow_backend_implements_ai_service_interface() {
		$backend = new AIPS_Meow_AI_Service();

		$this->assertInstanceOf(AIPS_AI_Service_Interface::class, $backend);
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: FAIL because `AIPS_Meow_AI_Service` does not exist yet.

- [ ] **Step 3: Write minimal implementation**

Create `ai-post-scheduler/includes/class-aips-meow-ai-service.php` by moving the current concrete logic from `AIPS_AI_Service` into this class and preserving the public API surface used by the facade:

```php
<?php
/**
 * Meow AI Service
 *
 * Concrete AI backend implementation for Meow AI Engine.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Meow_AI_Service implements AIPS_AI_Service_Interface {

	private $ai_engine = null;
	private $logger;
	private $call_log;
	private $config;
	private $resilience_service;

	private const OPTIONAL_QUERY_OPTION_KEYS = array(
		'context',
		'instructions',
		'messages',
		'env_id',
		'embeddings_env_id',
		'max_results',
		'api_key',
	);

	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null) {
		// Move the current constructor body from AIPS_AI_Service here unchanged.
	}

	public function is_available() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function generate_text($prompt, $options = array()) {
		// Move current implementation from AIPS_AI_Service.
	}

	public function generate_json($prompt, $options = array()) {
		// Move current implementation from AIPS_AI_Service.
	}

	public function generate_image($prompt, $options = array()) {
		// Move current implementation from AIPS_AI_Service.
	}

	public function get_call_log() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function clear_call_log() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function get_call_statistics() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function reset_circuit_breaker() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function get_circuit_breaker_status() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function get_rate_limiter_status() {
		// Move current implementation from AIPS_AI_Service.
	}

	public function reset_rate_limiter() {
		// Move current implementation from AIPS_AI_Service.
	}

	// Move the remaining existing private helper methods unchanged:
	// get_ai_engine, fallback_json_generation, generate_json_from_text,
	// extract_json_fragment, sanitize_json_candidate, calculate_max_tokens,
	// prepare_options, apply_optional_query_settings, emit_integration_error_notification,
	// emit_quota_alert_notification, log_call.
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: Provider-instantiation and factory-default tests pass, while facade delegation may still fail until `AIPS_AI_Service` is converted.

- [ ] **Step 5: Commit**

```bash
git add ai-post-scheduler/includes/class-aips-meow-ai-service.php ai-post-scheduler/includes/class-aips-ai-service.php
git commit -m "refactor: extract meow ai backend implementation"
```

### Task 4: Convert AIPS_AI_Service Into a Facade

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-ai-service.php`
- Modify: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`

- [ ] **Step 1: Add a failing delegation assertion if needed**

If Task 1 did not already cover helper compatibility, add:

```php
	public function test_ai_service_facade_delegates_availability_check() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		add_filter('aips_ai_backend_instance', function($backend, $backend_id, $args) use ($stub) {
			return $stub;
		}, 10, 3);

		$service = new AIPS_AI_Service();

		$this->assertTrue($service->is_available());
		$this->assertSame('is_available', $stub->calls[0]['method']);
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: FAIL because `AIPS_AI_Service` still contains direct logic instead of delegating to the filtered backend instance.

- [ ] **Step 3: Write minimal implementation**

Replace the body of `ai-post-scheduler/includes/class-aips-ai-service.php` with a facade:

```php
<?php
/**
 * AI Service Facade
 *
 * Stable public entry point that delegates to the active backend.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_AI_Service implements AIPS_AI_Service_Interface {

	private static $instance = null;

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $backend;

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct(?AIPS_Logger_Interface $logger = null, $config = null, $resilience_service = null, ?AIPS_AI_Service_Interface $backend = null) {
		$this->backend = $backend ?: AIPS_AI_Service_Factory::create(array(
			'logger' => $logger,
			'config' => $config,
			'resilience_service' => $resilience_service,
		));
	}

	public function is_available() {
		return $this->backend->is_available();
	}

	public function generate_text($prompt, $options = array()) {
		return $this->backend->generate_text($prompt, $options);
	}

	public function generate_json($prompt, $options = array()) {
		return $this->backend->generate_json($prompt, $options);
	}

	public function generate_image($prompt, $options = array()) {
		return $this->backend->generate_image($prompt, $options);
	}

	public function get_call_log() {
		return method_exists($this->backend, 'get_call_log') ? $this->backend->get_call_log() : array();
	}

	public function clear_call_log() {
		if (method_exists($this->backend, 'clear_call_log')) {
			$this->backend->clear_call_log();
		}
	}

	public function get_call_statistics() {
		return method_exists($this->backend, 'get_call_statistics') ? $this->backend->get_call_statistics() : array();
	}

	public function reset_circuit_breaker() {
		return method_exists($this->backend, 'reset_circuit_breaker') ? $this->backend->reset_circuit_breaker() : false;
	}

	public function get_circuit_breaker_status() {
		return method_exists($this->backend, 'get_circuit_breaker_status') ? $this->backend->get_circuit_breaker_status() : array();
	}

	public function get_rate_limiter_status() {
		return method_exists($this->backend, 'get_rate_limiter_status') ? $this->backend->get_rate_limiter_status() : array();
	}

	public function reset_rate_limiter() {
		return method_exists($this->backend, 'reset_rate_limiter') ? $this->backend->reset_rate_limiter() : false;
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: PASS for the factory/facade seam test file.

- [ ] **Step 5: Commit**

```bash
git add ai-post-scheduler/includes/class-aips-ai-service.php ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php
git commit -m "refactor: make ai service delegate to backend factory"
```

### Task 5: Run Focused Regression Verification

**Files:**
- Modify: `ai-post-scheduler/tests/bootstrap.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Container_Bindings.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_AI_Service.php`

- [ ] **Step 1: Add a failing regression test only if class-loading or constructor regressions are found**

If container or construction behavior breaks during implementation, add a focused regression case such as:

```php
	public function test_ai_service_can_be_constructed_without_explicit_backend() {
		$service = new AIPS_AI_Service();

		$this->assertInstanceOf(AIPS_AI_Service::class, $service);
	}
```

- [ ] **Step 2: Run targeted verification**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: PASS

Run: `composer test -- tests/Test_AIPS_Container_Bindings.php`
Expected: PASS

Run: `composer test -- tests/Test_AIPS_AI_Service.php`
Expected: PASS, or skip this command if the file does not exist in the repo.

- [ ] **Step 3: Fix only seam-related regressions**

If any of the targeted tests fail, limit follow-up edits to:

```php
// bootstrap loading order
'interface-aips-ai-service-interface.php',
'class-aips-ai-service-factory.php',
'class-aips-meow-ai-service.php',
'class-aips-ai-service.php',

// facade compatibility wrappers
public function get_call_log() {
	return method_exists($this->backend, 'get_call_log') ? $this->backend->get_call_log() : array();
}
```

- [ ] **Step 4: Re-run verification to confirm green**

Run: `composer test -- tests/Test_AIPS_AI_Backend_Factory.php`
Expected: PASS

Run: `composer test -- tests/Test_AIPS_Container_Bindings.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add ai-post-scheduler/tests/bootstrap.php ai-post-scheduler/includes/class-aips-ai-service.php ai-post-scheduler/includes/class-aips-ai-service-factory.php ai-post-scheduler/includes/class-aips-meow-ai-service.php ai-post-scheduler/tests/Test_AIPS_AI_Backend_Factory.php
git commit -m "test: verify ai backend seam compatibility"
```
