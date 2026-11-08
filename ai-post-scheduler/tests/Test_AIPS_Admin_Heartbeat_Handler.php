<?php
/**
 * Unit Tests for AIPS_Admin_Heartbeat_Handler
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Heartbeat_Handler extends WP_UnitTestCase {

	/**
	 * Setup user context for testing.
	 */
	public function setUp(): void {
		parent::setUp();
		$admin_id = $this->factory->user->create(array('role' => 'administrator'));
		wp_set_current_user($admin_id);
	}

	/**
	 * Test handle_heartbeat ignores unauthorized requests.
	 */
	public function test_handle_heartbeat_unauthorized() {
		$subscriber_id = $this->factory->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber_id);

		$data = array(
			'aips_link_report_backfill' => true,
			'aips_queue_monitor'        => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);
		$this->assertEmpty($response);
	}

	/**
	 * Test handle_heartbeat responds to queue monitor request.
	 */
	public function test_handle_heartbeat_queue_monitor() {
		$data = array(
			'aips_queue_monitor' => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);

		$this->assertArrayHasKey('aips_queue_monitor', $response);
		$this->assertArrayHasKey('in_flight', $response['aips_queue_monitor']);
		$this->assertArrayHasKey('queue_length', $response['aips_queue_monitor']);
		$this->assertArrayHasKey('digest_queue', $response['aips_queue_monitor']);
		$this->assertArrayHasKey('timestamp', $response['aips_queue_monitor']);
	}

	/**
	 * Test handle_heartbeat responds to cooldown monitor request.
	 */
	public function test_handle_heartbeat_cooldown_monitor() {
		update_option('aips_indexer_paused_until', time() + 300);

		$data = array(
			'aips_cooldown_monitor' => true,
		);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), $data);

		$this->assertArrayHasKey('aips_cooldown_monitor', $response);
		$this->assertTrue($response['aips_cooldown_monitor']['is_active']);
		$this->assertGreaterThan(0, $response['aips_cooldown_monitor']['remaining_seconds']);
	}

	/**
	 * Test get_link_report_backfill_data returns array.
	 */
	public function test_get_link_report_backfill_data() {
		$result = AIPS_Admin_Heartbeat_Handler::get_link_report_backfill_data();
		$this->assertIsArray($result);
	}

	/**
	 * Clear indexer options so each test starts from an idle indexer.
	 */
	public function tearDown(): void {
		delete_option('aips_indexer_paused_until');
		delete_option('aips_indexer_pause_reason');
		delete_option('aips_pending_index_queue');
		delete_option('aips_pending_topic_index_queue');
		delete_option(AIPS_Content_Indexer_Service::QUEUE_PAUSED_OPTION);
		parent::tearDown();
	}

	/**
	 * A non-admin never receives Content Indexer data.
	 */
	public function test_content_indexer_requires_manage_options() {
		$editor_id = $this->factory->user->create(array('role' => 'editor'));
		wp_set_current_user($editor_id);

		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array('other' => 1), array('aips_content_indexer' => true));

		$this->assertSame(array('other' => 1), $response);
	}

	/**
	 * Content Indexer data is only computed when the client asks for it.
	 */
	public function test_content_indexer_not_returned_without_key() {
		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), array('aips_bg' => true));

		$this->assertArrayNotHasKey('aips_content_indexer', $response);
	}

	/**
	 * The payload carries exactly the fields the indexer screen renders.
	 */
	public function test_content_indexer_payload_shape() {
		$response = AIPS_Admin_Heartbeat_Handler::handle_heartbeat(array(), array('aips_content_indexer' => true));

		$this->assertArrayHasKey('aips_content_indexer', $response);
		$payload = $response['aips_content_indexer'];

		$this->assertSame(array('status', 'rate_limits', 'queue', 'cooldown'), array_keys($payload));
		$this->assertSame(
			array('indexed', 'total_posts', 'unindexed', 'percent', 'indexed_topics', 'total_topics', 'unindexed_topics', 'topics_percent'),
			array_keys($payload['status'])
		);
		$this->assertSame(
			array('daily_count', 'daily_limit', 'weekly_count', 'weekly_limit', 'monthly_count', 'monthly_limit'),
			array_keys($payload['rate_limits'])
		);
		$this->assertSame(array('pending_count', 'is_running', 'paused'), array_keys($payload['queue']));
		$this->assertSame(array('active', 'until', 'remaining_seconds', 'reason'), array_keys($payload['cooldown']));
		$this->assertFalse($payload['cooldown']['active']);
		$this->assertSame(0, $payload['queue']['pending_count']);
	}

	/**
	 * Index counts reflect the published posts and their embeddings state.
	 */
	public function test_content_indexer_counts_unindexed_posts() {
		$before = AIPS_Admin_Heartbeat_Handler::get_content_indexer_data();

		// The default embeddings scope counts AI-generated posts only.
		$post_id = $this->factory->post->create(array('post_status' => 'publish'));
		update_post_meta($post_id, '_aips_generated_post', 1);
		$after = AIPS_Admin_Heartbeat_Handler::get_content_indexer_data();

		$this->assertSame($before['status']['total_posts'] + 1, $after['status']['total_posts']);
		$this->assertSame($before['status']['unindexed'] + 1, $after['status']['unindexed']);
	}

	/**
	 * Pending queue items and the paused flag are reported.
	 */
	public function test_content_indexer_reports_queue_state() {
		update_option('aips_pending_index_queue', array(11, 12));
		update_option('aips_pending_topic_index_queue', array(7));
		update_option(AIPS_Content_Indexer_Service::QUEUE_PAUSED_OPTION, 1);

		$payload = AIPS_Admin_Heartbeat_Handler::get_content_indexer_data();

		$this->assertSame(3, $payload['queue']['pending_count']);
		$this->assertTrue($payload['queue']['paused']);
	}

	/**
	 * An active cooldown is reported with its expiry and reason; a lapsed one is not.
	 */
	public function test_content_indexer_reports_cooldown() {
		$until = time() + 600;
		update_option('aips_indexer_paused_until', $until);
		update_option('aips_indexer_pause_reason', 'Provider quota exhausted.');

		$payload = AIPS_Admin_Heartbeat_Handler::get_content_indexer_data();

		$this->assertTrue($payload['cooldown']['active']);
		$this->assertSame($until, $payload['cooldown']['until']);
		$this->assertGreaterThan(0, $payload['cooldown']['remaining_seconds']);
		$this->assertSame('Provider quota exhausted.', $payload['cooldown']['reason']);

		update_option('aips_indexer_paused_until', time() - 5);
		$payload = AIPS_Admin_Heartbeat_Handler::get_content_indexer_data();

		$this->assertFalse($payload['cooldown']['active']);
		$this->assertSame(0, $payload['cooldown']['remaining_seconds']);
	}
}
