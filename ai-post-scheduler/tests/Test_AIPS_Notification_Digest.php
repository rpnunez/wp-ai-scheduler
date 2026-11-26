<?php
/**
 * Tests for Summarized Notification Digest
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notification_Digest extends WP_UnitTestCase {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * @var AIPS_Settings_UI
	 */
	private $ui;

	/**
	 * @var AIPS_Notifications
	 */
	private $notifications;

	public function setUp(): void {
		parent::setUp();
		$this->config = AIPS_Config::get_instance();
		$this->ui = new AIPS_Settings_UI();
		$this->notifications = AIPS_Notifications::instance();

		// Ensure a clean queue state for each test.
		delete_option('aips_notif_digest_queue');
	}

	public function tearDown(): void {
		delete_option('aips_notif_digest_queue');
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Configuration & Defaults
	// -----------------------------------------------------------------------

	public function test_default_options_exist() {
		$defaults = $this->config->get_default_options();

		$this->assertArrayHasKey('aips_notif_digest_enabled', $defaults);
		$this->assertTrue($defaults['aips_notif_digest_enabled']);

		$this->assertArrayHasKey('aips_notif_digest_threshold', $defaults);
		$this->assertSame(5, $defaults['aips_notif_digest_threshold']);

		$this->assertArrayHasKey('aips_notif_digest_max_delay', $defaults);
		$this->assertSame(3600, $defaults['aips_notif_digest_max_delay']);

		$this->assertArrayHasKey('aips_notif_digest_types', $defaults);
		$this->assertContains('post_generated', $defaults['aips_notif_digest_types']);
		$this->assertContains('post_ready_for_review', $defaults['aips_notif_digest_types']);
	}

	public function test_get_notification_summarized_digest_config() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->config->set_option('aips_notif_digest_threshold', 10);
		$this->config->set_option('aips_notif_digest_max_delay', 1800);
		$this->config->set_option('aips_notif_digest_types', array('post_generated'));

		$config = $this->config->get_notification_summarized_digest_config();

		$this->assertTrue($config['enabled']);
		$this->assertSame(10, $config['threshold']);
		$this->assertSame(1800, $config['max_delay']);
		$this->assertSame(array('post_generated'), $config['types']);
	}

	// -----------------------------------------------------------------------
	// Settings UI Sanitizers
	// -----------------------------------------------------------------------

	public function test_sanitize_digest_threshold_clamps_values() {
		// Non-numeric fallback: 5
		$this->assertSame(5, $this->ui->sanitize_digest_threshold('invalid'));
		$this->assertSame(5, $this->ui->sanitize_digest_threshold(''));

		// Minimum clamp: 2
		$this->assertSame(2, $this->ui->sanitize_digest_threshold(1));
		$this->assertSame(2, $this->ui->sanitize_digest_threshold(-5));

		// Within valid range
		$this->assertSame(5, $this->ui->sanitize_digest_threshold(5));
		$this->assertSame(20, $this->ui->sanitize_digest_threshold('20'));

		// Maximum clamp: 50
		$this->assertSame(50, $this->ui->sanitize_digest_threshold(100));
	}

	public function test_sanitize_digest_max_delay_validates_options() {
		$this->assertSame(900, $this->ui->sanitize_digest_max_delay(900));
		$this->assertSame(1800, $this->ui->sanitize_digest_max_delay(1800));
		$this->assertSame(3600, $this->ui->sanitize_digest_max_delay(3600));
		$this->assertSame(7200, $this->ui->sanitize_digest_max_delay(7200));
		$this->assertSame(21600, $this->ui->sanitize_digest_max_delay(21600));

		// Fallback for invalid inputs
		$this->assertSame(3600, $this->ui->sanitize_digest_max_delay(42));
		$this->assertSame(3600, $this->ui->sanitize_digest_max_delay('abc'));
	}

	public function test_sanitize_digest_types_filters_ineligible_types() {
		$raw = array(
			'post_generated',
			'generation_failed', // Error - not eligible
			'non_existent_type', // Invalid
			'template_generated',
		);

		$sanitized = $this->ui->sanitize_digest_types($raw);

		$this->assertContains('post_generated', $sanitized);
		$this->assertContains('template_generated', $sanitized);
		$this->assertNotContains('generation_failed', $sanitized);
		$this->assertNotContains('non_existent_type', $sanitized);
	}

	// -----------------------------------------------------------------------
	// Interception & Queueing
	// -----------------------------------------------------------------------

	public function test_should_intercept_for_digest() {
		// When disabled: false
		$this->config->set_option('aips_notif_digest_enabled', false);
		$this->config->set_option('aips_notif_digest_types', array('post_generated'));
		$this->assertFalse($this->notifications->should_intercept_for_digest('post_generated'));

		// When enabled: true for configured eligible types
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->assertTrue($this->notifications->should_intercept_for_digest('post_generated'));

		// Ineligible type (system error): false even if configured
		$this->assertFalse($this->notifications->should_intercept_for_digest('generation_failed'));

		// Eligible type not in user-selected types: false
		$this->assertFalse($this->notifications->should_intercept_for_digest('template_generated'));
	}

	public function test_enqueue_digest_notification_and_threshold_dispatch() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->config->set_option('aips_notif_digest_threshold', 3);
		$this->config->set_option('aips_notif_digest_types', array('post_generated'));

		// Queue first item (below threshold of 3)
		$this->notifications->enqueue_digest_notification('post_generated', array(
			'title'   => 'First Post',
			'message' => 'Post created',
		));

		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		// Queue second item (still below threshold)
		$this->notifications->enqueue_digest_notification('post_generated', array(
			'title'   => 'Second Post',
			'message' => 'Post created',
		));

		$this->assertSame(2, $this->notifications->get_digest_queue_count());

		// Queue third item (meets threshold of 3 -> triggers automatic flush)
		$this->notifications->enqueue_digest_notification('post_generated', array(
			'title'   => 'Third Post',
			'message' => 'Post created',
		));

		// Queue should now be empty after flush
		$this->assertSame(0, $this->notifications->get_digest_queue_count());
	}

	public function test_enqueue_digest_notification_deduplicates_same_post_id_and_type() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->config->set_option('aips_notif_digest_threshold', 5);

		$payload = array(
			'title'   => 'Draft Post',
			'message' => 'Post created',
			'meta'    => array('post_id' => 42),
		);

		$this->assertTrue($this->notifications->enqueue_digest_notification('post_generated', $payload));
		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		// Enqueueing identical type and post_id returns false and does not add duplicate
		$this->assertFalse($this->notifications->enqueue_digest_notification('post_generated', $payload));
		$this->assertSame(1, $this->notifications->get_digest_queue_count());
	}

	public function test_enqueue_digest_notification_upgrades_post_generated_to_ready_for_review() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->config->set_option('aips_notif_digest_threshold', 5);

		// First, post_generated is enqueued for post 42
		$this->assertTrue($this->notifications->enqueue_digest_notification('post_generated', array(
			'title'   => 'Generated Post 42',
			'message' => 'Created draft',
			'meta'    => array('post_id' => 42),
		)));
		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		// Next, post_ready_for_review arrives for the same draft post 42
		$this->assertTrue($this->notifications->enqueue_digest_notification('post_ready_for_review', array(
			'title'   => 'Post ready for review: Post 42',
			'message' => 'Awaiting review',
			'meta'    => array('post_id' => 42),
		)));

		// Queue count should remain 1 (upgraded, not appended)
		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		$queue = $this->notifications->get_digest_queue();
		$this->assertSame('post_ready_for_review', $queue[0]['type']);
		$this->assertSame('Post ready for review: Post 42', $queue[0]['title']);

		// Subsequent post_generated for post 42 is ignored
		$this->assertFalse($this->notifications->enqueue_digest_notification('post_generated', array(
			'title'   => 'Generated Post 42',
			'meta'    => array('post_id' => 42),
		)));
		$this->assertSame(1, $this->notifications->get_digest_queue_count());
	}

	// -----------------------------------------------------------------------
	// Time-based Fallback Timeout
	// -----------------------------------------------------------------------

	public function test_check_digest_timeout_flushes_when_window_elapsed() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		$this->config->set_option('aips_notif_digest_threshold', 10);
		$this->config->set_option('aips_notif_digest_max_delay', 900); // 15 mins

		// Empty queue should return false
		$this->assertFalse($this->notifications->check_digest_timeout());

		// Put an item enqueued "just now"
		$queue = array(
			array(
				'type'        => 'post_generated',
				'title'       => 'Recent Post',
				'enqueued_at' => AIPS_DateTime::now()->timestamp() - 100, // 100s ago (< 900)
			),
		);
		update_option('aips_notif_digest_queue', $queue, false);

		// Timeout not yet reached
		$this->assertFalse($this->notifications->check_digest_timeout());
		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		// Put an item enqueued 1000s ago (> 900)
		$queue[0]['enqueued_at'] = AIPS_DateTime::now()->timestamp() - 1000;
		update_option('aips_notif_digest_queue', $queue, false);

		// Timeout exceeded -> flushes queue
		$this->assertTrue($this->notifications->check_digest_timeout());
		$this->assertSame(0, $this->notifications->get_digest_queue_count());
	}

	// -----------------------------------------------------------------------
	// Email Template Registration & Dedicated Template Files
	// -----------------------------------------------------------------------

	public function test_summarized_digest_template_registered() {
		$templates = new AIPS_Notification_Templates();
		$template = $templates->get('summarized_digest');

		$this->assertNotNull($template);
		$this->assertSame('summarized_digest', $template->get_type());
		$this->assertStringContainsString('Notification Digest', $template->render_subject());
		$this->assertStringContainsString('{{digest_items_html}}', $template->render_body());
		$this->assertStringContainsString('{{all_posts_url}}', $template->render_body());
	}

	public function test_email_template_files_exist_and_render() {
		$template_dir = dirname(__DIR__) . '/templates/email/';
		$this->assertFileExists($template_dir . 'template-alert.php');
		$this->assertFileExists($template_dir . 'template-event.php');
		$this->assertFileExists($template_dir . 'template-post-generated.php');
		$this->assertFileExists($template_dir . 'template-summarized-digest.php');
		$this->assertFileExists($template_dir . 'summarized-digest.php');

		$templates = new AIPS_Notification_Templates();

		// Alert template
		$alert_tpl = $templates->get('generation_failed');
		$this->assertNotNull($alert_tpl);
		$this->assertStringContainsString('alert-box', $alert_tpl->render_body());

		// Event template
		$event_tpl = $templates->get('post_ready_for_review');
		$this->assertNotNull($event_tpl);
		$this->assertStringContainsString('alert-box', $event_tpl->render_body());

		// Post generated template
		$post_tpl = $templates->get('post_generated');
		$this->assertNotNull($post_tpl);
		$this->assertStringContainsString('{{post_title}}', $post_tpl->render_body());
		$this->assertStringContainsString('{{post_excerpt}}', $post_tpl->render_body());

		// Summarized digest template
		$digest_tpl = $templates->get('summarized_digest');
		$this->assertNotNull($digest_tpl);
		$this->assertStringContainsString('{{digest_items_html}}', $digest_tpl->render_body());
	}

	public function test_render_summarized_digest_items_in_templates_class() {
		$items = array(
			array(
				'type'  => 'post_generated',
				'title' => 'Direct Template Test',
				'url'   => 'http://example.com/?p=99',
				'meta'  => array(
					'source_label'      => 'Custom Strategy',
					'post_status_label' => 'Published',
				),
			),
		);

		$html = AIPS_Notification_Templates::render_summarized_digest_items( $items );
		$this->assertStringContainsString( 'Direct Template Test', $html );
		$this->assertStringContainsString( 'Custom Strategy', $html );
		$this->assertStringContainsString( 'Published', $html );
		$this->assertStringContainsString( 'http://example.com/?p=99', $html );
	}

	public function test_summarized_digest_sender_renders_items_using_template_file() {
		$captured = null;
		$dispatcher = function ( $type, $args ) use ( &$captured ) {
			$captured = array(
				'type' => $type,
				'args' => $args,
			);
		};
		$vars_builder = function () {
			return array();
		};

		$senders = new AIPS_Notification_Senders( $dispatcher, $vars_builder );
		$payload = array(
			'items' => array(
				array(
					'type'  => 'post_generated',
					'title' => 'Test Post Alpha',
					'url'   => 'http://example.com/?p=1',
					'meta'  => array(
						'source_label'      => 'Technology',
						'post_status_label' => 'Draft',
					),
				),
				array(
					'type'  => 'post_ready_for_review',
					'title' => 'Test Post Beta',
					'url'   => 'http://example.com/?p=2',
					'meta'  => array(
						'source_label'      => 'Science',
						'post_status_label' => 'Pending Review',
					),
				),
			),
			'count' => 2,
		);

		$senders->summarized_digest( $payload );

		$this->assertNotNull( $captured );
		$this->assertSame( 'summarized_digest', $captured['type'] );
		$this->assertArrayHasKey( 'vars', $captured['args'] );
		$vars = $captured['args']['vars'];
		$this->assertSame( '2', $vars['{{item_count}}'] );
		$this->assertStringContainsString( 'Test Post Alpha', $vars['{{digest_items_html}}'] );
		$this->assertStringContainsString( 'Test Post Beta', $vars['{{digest_items_html}}'] );
		$this->assertStringContainsString( 'Technology', $vars['{{digest_items_html}}'] );
		$this->assertStringContainsString( 'Draft', $vars['{{digest_items_html}}'] );
	}

	public function test_interval_calculator_fifteen_minutes_frequency() {
		$calc = AIPS_Interval_Calculator::instance();
		$intervals = $calc->get_intervals();
		$this->assertArrayHasKey( 'fifteen_minutes', $intervals );
		$this->assertSame( 900, $intervals['fifteen_minutes']['interval'] );

		$last   = 1000;
		$target = 1001;
		$next   = $calc->calculate_next_occurrence_after( 'fifteen_minutes', $last, $target );
		$this->assertSame( 1900, $next );
	}

	public function test_settings_ui_callbacks_render_classes_without_inline_styles() {
		$ui = new AIPS_Settings_UI();

		ob_start();
		$ui->notif_digest_types_field_callback();
		$types_output = ob_get_clean();

		$this->assertStringContainsString( 'class="aips-checkbox-label-block"', $types_output );
		$this->assertStringContainsString( 'class="description aips-checkbox-label-desc"', $types_output );
		$this->assertStringNotContainsString( 'style="', $types_output );

		ob_start();
		$ui->notif_digest_actions_field_callback();
		$actions_output = ob_get_clean();

		$this->assertStringContainsString( 'class="aips-settings-action-row"', $actions_output );
		$this->assertStringNotContainsString( 'style="', $actions_output );
	}

	// -----------------------------------------------------------------------
	// Event Handler Cron Hook
	// -----------------------------------------------------------------------

	public function test_cron_event_is_registered_in_handler() {
		$events = AIPS_Notifications_Event_Handler::get_hook_bindings();

		$found = false;
		foreach ($events as $event) {
			if ('aips_notif_digest_cron' === $event['hook']) {
				$found = true;
				$this->assertSame('handle_digest_cron', $event['method']);
				break;
			}
		}

		$this->assertTrue($found, 'aips_notif_digest_cron hook not found in event handler registered events.');
	}

	// -----------------------------------------------------------------------
	// Cron Synchronization on Setting Change
	// -----------------------------------------------------------------------

	public function test_sync_digest_cron_schedule_schedules_when_enabled() {
		wp_clear_scheduled_hook('aips_notif_digest_cron');
		$this->assertFalse(wp_next_scheduled('aips_notif_digest_cron'));

		$this->config->set_option('aips_notif_digest_enabled', true);
		AIPS_Settings::sync_digest_cron_schedule();

		$this->assertNotEmpty(wp_next_scheduled('aips_notif_digest_cron'));
	}

	public function test_sync_digest_cron_schedule_unschedules_and_flushes_when_disabled() {
		$this->config->set_option('aips_notif_digest_enabled', true);
		AIPS_Settings::sync_digest_cron_schedule();
		$this->assertNotEmpty(wp_next_scheduled('aips_notif_digest_cron'));

		// Put a pending item into the queue
		$queue = array(
			array(
				'type'        => 'post_generated',
				'title'       => 'Pending Post',
				'enqueued_at' => AIPS_DateTime::now()->timestamp(),
			),
		);
		update_option('aips_notif_digest_queue', $queue, false);
		$this->assertSame(1, $this->notifications->get_digest_queue_count());

		// Disable digest and synchronize
		$this->config->set_option('aips_notif_digest_enabled', false);
		AIPS_Settings::sync_digest_cron_schedule();

		// Cron should be cleared
		$this->assertFalse(wp_next_scheduled('aips_notif_digest_cron'));
		// Pending queue should be flushed to avoid stranded notifications
		$this->assertSame(0, $this->notifications->get_digest_queue_count());
	}
}
