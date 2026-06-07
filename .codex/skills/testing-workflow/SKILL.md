# Testing Workflow Skill — AI Post Scheduler

## Purpose
Use this workflow to create and maintain reliable PHPUnit coverage for the AI Post Scheduler WordPress plugin.

## Required test execution location
- Always run Composer and PHPUnit commands from `ai-post-scheduler/` only.
- Do **not** run plugin test commands from the repository root.

```bash
cd ai-post-scheduler
composer test
```

## Test file structure and class pattern
- Place tests in `ai-post-scheduler/tests/`.
- Use feature-level files with one primary class/feature target per file.
- Test classes should extend `WP_UnitTestCase`.
- Prefer file names that map to the feature/class under test, e.g.:
	- `tests/test-aips-settings.php`
	- `tests/test-aips-ajax-controller.php`
	- `tests/test-aips-template-repository.php`

Minimal structure:

```php
<?php

class Test_AIPS_Example_Service extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Arrange shared fixtures/mocks.
	}

	public function test_it_handles_success_path() {
		// Arrange
		// Act
		// Assert
	}

	public function test_it_handles_failure_path() {
		// Arrange
		// Act
		// Assert
	}
}
```

## When to update `tests/bootstrap.php`
Update `ai-post-scheduler/tests/bootstrap.php` when either of these is true:
- You add a new `ai-post-scheduler/includes/*.php` class that must be available in limited-mode/unit-like test runs where plugin bootstrapping does not autoload it.
- A new test directly instantiates or references a class that is not currently required by bootstrap or loaded transitively.

Practical rule:
- If a test fails with missing class errors in limited-mode runs, add the explicit `require_once` entry in `tests/bootstrap.php` for that include file.

## Coverage templates

### 1) Success/failure path coverage template

```php
public function test_execute_returns_expected_result_on_success() {
	// Arrange valid input and dependencies.

	// Act
	$result = $this->service->execute( $input );

	// Assert output.
	$this->assertTrue( $result['success'] );
	$this->assertSame( 'expected', $result['data'] );

	// Assert side effects (DB write, option update, hook, etc.).
	$this->assertSame( 1, (int) get_option( 'aips_example_counter', 0 ) );
}

public function test_execute_returns_error_on_failure() {
	// Arrange invalid input or forced dependency failure.

	// Act
	$result = $this->service->execute( $invalid_input );

	// Assert output.
	$this->assertFalse( $result['success'] );
	$this->assertNotEmpty( $result['message'] );

	// Assert side effects are absent or rolled back.
	$this->assertSame( 0, (int) get_option( 'aips_example_counter', 0 ) );
}
```

### 2) Capability/nonce failure tests for AJAX controllers

```php
public function test_ajax_fails_when_user_lacks_capability() {
	// Arrange non-admin user.
	wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
	$_POST['nonce'] = wp_create_nonce( 'aips_action_nonce' );

	// Act
	$response = $this->call_controller_action();

	// Assert response and no side effect.
	$this->assertFalse( $response['success'] );
	$this->assertStringContainsString( 'permission', strtolower( $response['message'] ) );
	$this->assertSame( 0, $this->repo->count_changes() );
}

public function test_ajax_fails_with_invalid_nonce() {
	// Arrange admin user but invalid nonce.
	wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	$_POST['nonce'] = 'invalid';

	// Act
	$response = $this->call_controller_action();

	// Assert response and no side effect.
	$this->assertFalse( $response['success'] );
	$this->assertStringContainsString( 'nonce', strtolower( $response['message'] ) );
	$this->assertSame( 0, $this->repo->count_changes() );
}
```

### 3) Repository/service boundary tests template

```php
public function test_service_delegates_persistence_to_repository() {
	// Arrange repository spy/fake with call tracking.
	$repo = new Test_AIPS_Example_Repository_Spy();
	$service = new AIPS_Example_Service( $repo );

	// Act
	$service->save_item( array( 'title' => 'Sample' ) );

	// Assert boundary contract.
	$this->assertSame( 1, $repo->save_calls );
	$this->assertSame( 'Sample', $repo->last_payload['title'] );
}

public function test_service_handles_repository_failure_contract() {
	// Arrange repository fake that throws or returns WP_Error.
	$repo = new Test_AIPS_Example_Repository_Failing_Fake();
	$service = new AIPS_Example_Service( $repo );

	// Act
	$result = $service->save_item( array( 'title' => 'Sample' ) );

	// Assert translated error contract.
	$this->assertFalse( $result['success'] );
	$this->assertStringContainsString( 'save', strtolower( $result['message'] ) );
}
```

## Pre-PR checklist
- [ ] Tests were executed from `ai-post-scheduler/`.
- [ ] Any new required `includes/*.php` class is loaded in `tests/bootstrap.php` when needed for limited-mode tests.
- [ ] Success and failure paths are both covered for changed behavior.
- [ ] Assertions validate both returned output and side effects (DB/options/hooks/events/files).
- [ ] AJAX controller tests include capability and nonce failure coverage for state-changing actions.
