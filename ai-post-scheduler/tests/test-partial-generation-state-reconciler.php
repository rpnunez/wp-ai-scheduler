<?php
/**
 * Tests for AIPS_Partial_Generation_State_Reconciler
 *
 * Covers the metadata_exists() fast-path short-circuit added in the
 * on_save_post() handler to avoid 3 get_post_meta() calls for posts that
 * have no AIPS generation metadata at all.
 *
 * @package AI_Post_Scheduler
 */

class Test_Partial_Generation_State_Reconciler extends WP_UnitTestCase {

	/** @var AIPS_Partial_Generation_State_Reconciler */
	private $reconciler;

	public function setUp(): void {
		parent::setUp();
		$this->reconciler = new AIPS_Partial_Generation_State_Reconciler();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helper
	// -----------------------------------------------------------------------

	/**
	 * Build a minimal WP_Post-like object.
	 *
	 * @param string $post_type
	 * @return object
	 */
	private function make_post($post_type = 'post') {
		$p = new stdClass();
		$p->post_type = $post_type;
		$p->ID = 42;
		return $p;
	}

	// -----------------------------------------------------------------------
	// Early-out guards (unchanged logic, regression checks)
	// -----------------------------------------------------------------------

	/**
	 * on_save_post() must do nothing when $update is false (new post).
	 */
	public function test_skips_when_not_update() {
		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post(), false);

		$this->assertEmpty($actions_fired, 'Should not fire hook for new posts.');
	}

	/**
	 * on_save_post() must do nothing for non-"post" post types.
	 */
	public function test_skips_for_non_post_type() {
		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post('page'), true);

		$this->assertEmpty($actions_fired, 'Should not fire hook for page post type.');
	}

	// -----------------------------------------------------------------------
	// metadata_exists() fast-path (the new short-circuit)
	// -----------------------------------------------------------------------

	/**
	 * When the primary key (aips_post_generation_component_statuses) does NOT
	 * exist in post meta, on_save_post() must return early without calling
	 * reconcile_generation_status_meta_from_post() or firing the hook.
	 */
	public function test_fast_path_skips_when_primary_meta_absent() {
		global $aips_test_meta, $aips_reconcile_calls;
		$aips_test_meta    = array(); // no AIPS meta on this post
		$aips_reconcile_calls = array();

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post(), true);

		$this->assertEmpty($actions_fired, 'Hook must not fire when primary meta key is absent.');
		$this->assertEmpty($aips_reconcile_calls, 'reconcile_generation_status_meta_from_post must not be called when primary meta key is absent.');
	}

	/**
	 * When metadata_exists() returns true (primary key is set), the handler
	 * must proceed past the fast-path check and attempt reconciliation.
	 * Because all three get_post_meta() values are non-empty, $has_generation_meta
	 * is true and reconcile_generation_status_meta_from_post() is called.
	 */
	public function test_proceeds_when_primary_meta_exists() {
		global $aips_test_meta;
		$aips_test_meta = array(
			42 => array(
				'aips_post_generation_component_statuses' => 'some_value',
			),
		);

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		$this->reconciler->on_save_post(42, $this->make_post(), true);

		$this->assertNotEmpty($actions_fired, 'Hook must fire when primary meta key exists and reconcile returns data.');
	}

	/**
	 * When the primary key exists but returns empty string (e.g. value was
	 * deleted after metadata_exists() returned true), the fallback 3-key check
	 * may still result in no-op — verify the code path doesn't crash and the
	 * hook is only fired when reconcile returns an array.
	 */
	public function test_no_hook_when_reconcile_returns_null() {
		global $aips_test_meta, $aips_reconcile_calls;
		// metadata_exists returns true because the key is set (even to empty string)
		$aips_test_meta = array(
			42 => array(
				'aips_post_generation_component_statuses' => '',
				'aips_post_generation_incomplete'         => '',
				'aips_post_generation_had_partial'        => '',
			),
		);
		$aips_reconcile_calls = array();

		$actions_fired = array();
		add_action('aips_partial_generation_state_reconciled', function() use (&$actions_fired) {
			$actions_fired[] = true;
		});

		// When all three values are empty get_post_meta returns '', so
		// $has_generation_meta is false and we return early.
		$this->reconciler->on_save_post(42, $this->make_post(), true);

		// The metadata_exists fast-path passes (key is set), but the full
		// 3-key check yields false (all empty), so reconcile is NOT called.
		$this->assertEmpty($actions_fired, 'Hook must not fire when all meta values are empty strings.');
	}
}
