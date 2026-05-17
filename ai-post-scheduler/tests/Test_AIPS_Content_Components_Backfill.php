<?php
/**
 * Integration tests for Content Component backfill apply logic.
 *
 * Verifies that applying a backfill:
 *   1. Updates the target post's content via wp_update_post.
 *   2. Writes an injection trace record to the injections table.
 *
 * The test exercises record_injections_from_content() and wp_update_post()
 * directly to keep the test fast and deterministic without going through
 * the AJAX layer.
 *
 * @package AI_Post_Scheduler
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Minimal no-op base so tests can be loaded in limited environments
	 * (e.g. phpunit without the WP test harness installed).
	 */
	abstract class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
		public function setUp(): void {}
		public function tearDown(): void {}
	}
}

class Test_AIPS_Content_Components_Backfill extends WP_UnitTestCase {

	/** @var int */
	private $post_id = 0;

	/** @var int */
	private $component_id = 0;

	/** @var AIPS_Content_Components_Repository */
	private $repository;

	/** @var AIPS_Content_Component_Injection_Service */
	private $injection_service;

	/** @var wpdb */
	private $wpdb;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->wpdb = $wpdb;

		// Guard: requires full WP test harness.
		if ( ! function_exists( 'wp_insert_post' ) ) {
			$this->markTestSkipped( 'WordPress test harness not available.' );
		}

		if ( ! class_exists( 'AIPS_Content_Components_Repository' ) ) {
			$this->markTestSkipped( 'AIPS classes not loaded.' );
		}

		$this->repository        = new AIPS_Content_Components_Repository();
		$this->injection_service = new AIPS_Content_Component_Injection_Service();

		// Create a post with plain content.
		$this->post_id = (int) $this->factory->post->create(
			array(
				'post_title'   => 'Backfill Test Post',
				'post_content' => '<p>Some introductory text.</p><p>More body content here.</p>',
				'post_status'  => 'publish',
			)
		);

		// Create a component to inject.
		$this->component_id = (int) $this->repository->create(
			array(
				'title'           => 'Backfill Test Component',
				'slug'            => 'backfill-test-component',
				'description'     => 'Used by integration test.',
				'status'          => 'active',
				'component_type'  => 'cta',
				'content_mode'    => 'html',
				'content'         => '<div class="cta-test">Test CTA</div>',
				'content_payload' => '<div class="cta-test">Test CTA</div>',
				'media_payload'   => array(),
				'cta_payload'     => array(),
				'rules_json'      => array(),
				'qa_status'       => 'passed',
				'qa_notes'        => '',
				'is_active'       => 1,
			)
		);
	}

	public function tearDown(): void {
		if ( $this->post_id > 0 ) {
			wp_delete_post( $this->post_id, true );
		}

		if ( $this->component_id > 0 ) {
			$this->repository->delete( $this->component_id );
		}

		// Clean up any injection records written during tests.
		if ( $this->post_id > 0 ) {
			$table = $this->wpdb->prefix . 'aips_content_component_injections';
			$this->wpdb->delete( $table, array( 'post_id' => $this->post_id ), array( '%d' ) );
		}

		parent::tearDown();
	}

	/**
	 * Backfill apply: wp_update_post changes the post content.
	 */
	public function test_backfill_apply_updates_post_content() {
		$this->assertGreaterThan( 0, $this->post_id, 'Post should have been created.' );

		$original_content = get_post_field( 'post_content', $this->post_id );

		// Simulate injected content (as the injection service would produce it).
		$hash             = hash( 'sha256', 'test-marker' );
		$injected_content = $original_content
			. "\n<!-- aips:component:start:{$this->component_id}:{$hash} -->"
			. "\n<div class=\"cta-test\">Test CTA</div>"
			. "\n<!-- aips:component:end:{$this->component_id}:{$hash} -->";

		// Apply via wp_update_post (mirrors backfill apply logic in controller).
		$save_result = wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $injected_content,
			),
			true
		);

		$this->assertNotInstanceOf( 'WP_Error', $save_result, 'wp_update_post should not return WP_Error.' );
		$this->assertSame( $this->post_id, (int) $save_result, 'wp_update_post should return the post ID.' );

		$updated = get_post_field( 'post_content', $this->post_id );
		$this->assertStringContainsString( 'aips:component:start', $updated, 'Updated post should contain injection marker.' );
		$this->assertStringContainsString( 'Test CTA', $updated, 'Updated post should contain injected component content.' );
	}

	/**
	 * Backfill apply: record_injections_from_content writes an injection trace row.
	 */
	public function test_backfill_apply_records_injection_trace() {
		$this->assertGreaterThan( 0, $this->post_id, 'Post should have been created.' );
		$this->assertGreaterThan( 0, $this->component_id, 'Component should have been created.' );

		$hash             = hash( 'sha256', 'trace-marker' );
		$injected_content = '<p>Body</p>'
			. "\n<!-- aips:component:start:{$this->component_id}:{$hash} -->"
			. "\n<div class=\"cta-test\">Test CTA</div>"
			. "\n<!-- aips:component:end:{$this->component_id}:{$hash} -->";

		$run_id = 'test-run-' . uniqid();

		$this->injection_service->record_injections_from_content(
			$this->post_id,
			$injected_content,
			$run_id,
			false
		);

		$table = $this->wpdb->prefix . 'aips_content_component_injections';
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND component_id = %d ORDER BY id DESC LIMIT 1",
				$this->post_id,
				$this->component_id
			)
		);

		$this->assertNotNull( $row, 'An injection trace row should exist after record_injections_from_content.' );
		$this->assertSame( $this->post_id, (int) $row->post_id );
		$this->assertSame( $this->component_id, (int) $row->component_id );
	}

	/**
	 * Backfill apply: both update and trace happen together (full apply flow).
	 */
	public function test_backfill_apply_full_flow_updates_post_and_writes_trace() {
		$this->assertGreaterThan( 0, $this->post_id );
		$this->assertGreaterThan( 0, $this->component_id );

		$original_content = get_post_field( 'post_content', $this->post_id );
		$hash             = hash( 'sha256', 'full-flow-marker' );
		$injected_content = $original_content
			. "\n<!-- aips:component:start:{$this->component_id}:{$hash} -->"
			. "\n<div class=\"cta-test\">Test CTA</div>"
			. "\n<!-- aips:component:end:{$this->component_id}:{$hash} -->";

		$run_id = 'test-run-full-' . uniqid();

		// Step 1: update post content.
		$save_result = wp_update_post(
			array(
				'ID'           => $this->post_id,
				'post_content' => $injected_content,
			),
			true
		);
		$this->assertNotInstanceOf( 'WP_Error', $save_result );

		// Step 2: record injection trace.
		$this->injection_service->record_injections_from_content(
			$this->post_id,
			$injected_content,
			$run_id,
			false
		);

		// Assert: post content was changed.
		$updated = get_post_field( 'post_content', $this->post_id );
		$this->assertStringContainsString( 'aips:component:start', $updated );

		// Assert: injection row was written.
		$table = $this->wpdb->prefix . 'aips_content_component_injections';
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE post_id = %d AND component_id = %d",
				$this->post_id,
				$this->component_id
			)
		);
		$this->assertGreaterThan( 0, $count, 'Injection record count should be > 0 after full apply flow.' );
	}
}
