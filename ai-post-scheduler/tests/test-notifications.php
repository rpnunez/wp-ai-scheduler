<?php
/**
 * Tests for AIPS_Notification_Template, AIPS_Notification_Templates, and AIPS_Notifications
 *
 * @package AI_Post_Scheduler
 */

// ============================================================================
// AIPS_Notification_Template
// ============================================================================

class Test_AIPS_Notification_Template extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// get_type
	// -----------------------------------------------------------------------

	public function test_get_type_returns_slug() {
		$tpl = new AIPS_Notification_Template('my_event', 'Subject', '<p>Body</p>');
		$this->assertSame('my_event', $tpl->get_type());
	}

	// -----------------------------------------------------------------------
	// render_subject
	// -----------------------------------------------------------------------

	public function test_render_subject_replaces_token_with_curly_braces() {
		$tpl     = new AIPS_Notification_Template('t', '[{{site_name}}] Alert', '<p/>', 'Alert');
		$subject = $tpl->render_subject(array('{{site_name}}' => 'My Blog'));
		$this->assertSame('[My Blog] Alert', $subject);
	}

	public function test_render_subject_accepts_token_without_curly_braces() {
		$tpl     = new AIPS_Notification_Template('t', '[{{site_name}}] Alert', '<p/>');
		$subject = $tpl->render_subject(array('site_name' => 'My Blog'));
		$this->assertSame('[My Blog] Alert', $subject);
	}

	public function test_render_subject_leaves_unreplaced_tokens_intact() {
		$tpl     = new AIPS_Notification_Template('t', '{{missing}} Alert', '<p/>');
		$subject = $tpl->render_subject(array());
		$this->assertSame('{{missing}} Alert', $subject);
	}

	// -----------------------------------------------------------------------
	// render_body
	// -----------------------------------------------------------------------

	public function test_render_body_replaces_multiple_tokens() {
		$body = '<p>Hello {{user}}, you have {{count}} messages.</p>';
		$tpl  = new AIPS_Notification_Template('t', 'Subject', $body);
		$rendered = $tpl->render_body(array(
			'{{user}}'  => 'Jane',
			'{{count}}' => '5',
		));
		$this->assertSame('<p>Hello Jane, you have 5 messages.</p>', $rendered);
	}

	public function test_render_body_returns_original_when_no_vars() {
		$body = '<p>Static body</p>';
		$tpl  = new AIPS_Notification_Template('t', 'Subject', $body);
		$this->assertSame($body, $tpl->render_body());
	}

	public function test_render_body_normalises_keys_without_curlies() {
		$tpl  = new AIPS_Notification_Template('t', 'S', '<p>{{name}}</p>');
		$rendered = $tpl->render_body(array('name' => 'World'));
		$this->assertSame('<p>World</p>', $rendered);
	}

	// -----------------------------------------------------------------------
	// header helpers
	// -----------------------------------------------------------------------

	public function test_default_header_color_is_blue() {
		$tpl = new AIPS_Notification_Template('t', 'S', 'B');
		$this->assertSame('#2271b1', $tpl->get_header_color());
	}

	public function test_custom_header_color_is_stored() {
		$tpl = new AIPS_Notification_Template('t', 'S', 'B', 'Title', '#b32d2e');
		$this->assertSame('#b32d2e', $tpl->get_header_color());
	}

	public function test_header_title_is_stored() {
		$tpl = new AIPS_Notification_Template('t', 'S', 'B', 'My Header');
		$this->assertSame('My Header', $tpl->get_header_title());
	}
}


// ============================================================================
// AIPS_Notification_Templates
// ============================================================================

class Test_AIPS_Notification_Templates extends WP_UnitTestCase {

	/** @var AIPS_Notification_Templates */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry = new AIPS_Notification_Templates();
	}

	// -----------------------------------------------------------------------
	// Built-in templates
	// -----------------------------------------------------------------------

	public function test_partial_generation_template_is_registered() {
		$tpl = $this->registry->get('partial_generation');
		$this->assertInstanceOf(AIPS_Notification_Template::class, $tpl);
	}

	public function test_posts_awaiting_review_template_is_registered() {
		$tpl = $this->registry->get('posts_awaiting_review');
		$this->assertInstanceOf(AIPS_Notification_Template::class, $tpl);
	}

	public function test_get_unknown_type_returns_null() {
		$this->assertNull($this->registry->get('does_not_exist'));
	}

	// -----------------------------------------------------------------------
	// register
	// -----------------------------------------------------------------------

	public function test_register_adds_template() {
		$tpl = new AIPS_Notification_Template('custom', 'Subject', 'Body');
		$this->registry->register($tpl);
		$this->assertSame($tpl, $this->registry->get('custom'));
	}

	public function test_register_overwrites_existing_template() {
		$original    = new AIPS_Notification_Template('partial_generation', 'Old', 'Old body');
		$replacement = new AIPS_Notification_Template('partial_generation', 'New', 'New body');

		$this->registry->register($original);
		$this->registry->register($replacement);

		$this->assertSame('New', $this->registry->get('partial_generation')->render_subject());
	}

	// -----------------------------------------------------------------------
	// all
	// -----------------------------------------------------------------------

	public function test_all_returns_array_of_templates() {
		$all = $this->registry->all();
		$this->assertIsArray($all);
		$this->assertArrayHasKey('partial_generation', $all);
		$this->assertArrayHasKey('posts_awaiting_review', $all);
	}

	// -----------------------------------------------------------------------
	// aips_notification_templates action hook
	// -----------------------------------------------------------------------

	public function test_action_hook_allows_adding_custom_template() {
		$custom_tpl = new AIPS_Notification_Template('custom_hook_type', 'Subject', '<p>Body</p>');

		add_action('aips_notification_templates', function ( $registry ) use ( $custom_tpl ) {
			$registry->register($custom_tpl);
		});

		$registry = new AIPS_Notification_Templates();
		$this->assertSame($custom_tpl, $registry->get('custom_hook_type'));
	}

	// -----------------------------------------------------------------------
	// Shared layout: built-in templates use the email layout file
	// -----------------------------------------------------------------------

	public function test_partial_generation_body_uses_shared_layout_chrome() {
		$tpl  = $this->registry->get('partial_generation');
		$body = $tpl->render_body(array());

		// Verify the full HTML document structure from the shared layout is present.
		$this->assertStringContainsString('<!DOCTYPE html>', $body);
		$this->assertStringContainsString('<html>', $body);
		$this->assertStringContainsString('.email-container', $body);
		$this->assertStringContainsString('.email-header', $body);
		$this->assertStringContainsString('.email-footer', $body);
	}

	public function test_posts_awaiting_review_body_uses_shared_layout_chrome() {
		$tpl  = $this->registry->get('posts_awaiting_review');
		$body = $tpl->render_body(array());

		$this->assertStringContainsString('<!DOCTYPE html>', $body);
		$this->assertStringContainsString('.email-footer', $body);
	}

	// -----------------------------------------------------------------------
	// Built-in template token smoke tests
	// -----------------------------------------------------------------------

	public function test_partial_generation_subject_renders_site_name() {
		$tpl     = $this->registry->get('partial_generation');
		$subject = $tpl->render_subject(array('{{site_name}}' => 'TestSite'));
		$this->assertStringContainsString('TestSite', $subject);
	}

	public function test_partial_generation_body_renders_post_title() {
		$tpl  = $this->registry->get('partial_generation');
		$body = $tpl->render_body(array('{{post_title}}' => 'My Special Post'));
		$this->assertStringContainsString('My Special Post', $body);
	}

	public function test_partial_generation_body_renders_missing_components() {
		$tpl  = $this->registry->get('partial_generation');
		$body = $tpl->render_body(array('{{missing_components}}' => '<ul><li>Excerpt</li></ul>'));
		$this->assertStringContainsString('Excerpt', $body);
	}

	public function test_posts_awaiting_review_subject_renders_stats_label() {
		$tpl     = $this->registry->get('posts_awaiting_review');
		$subject = $tpl->render_subject(array(
			'{{site_name}}'   => 'My Blog',
			'{{stats_label}}' => '3 Posts Awaiting Review',
		));
		$this->assertStringContainsString('3 Posts Awaiting Review', $subject);
	}

	public function test_posts_awaiting_review_body_renders_review_url() {
		$tpl  = $this->registry->get('posts_awaiting_review');
		$body = $tpl->render_body(array(
			'{{review_url}}'  => 'https://example.com/review',
			'{{stats_label}}' => '1 Post',
			'{{post_list}}'   => '',
			'{{more_posts}}'  => '',
			'{{site_name}}'   => 'Blog',
		));
		$this->assertStringContainsString('https://example.com/review', $body);
	}
}


// ============================================================================
// AIPS_Notifications (unit / limited-env tests)
// ============================================================================

class Test_AIPS_Notifications_Service extends WP_UnitTestCase {

	/** @var AIPS_Notifications */
	private $notifications;

	/** @var AIPS_Notifications_Repository */
	private $repository;

	public function setUp(): void {
		parent::setUp();

		AIPS_DB_Manager::install_tables();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );

		$this->repository    = new AIPS_Notifications_Repository();
		$this->notifications = new AIPS_Notifications($this->repository);
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_notifications" );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// send() — CHANNEL_DB
	// -----------------------------------------------------------------------

	public function test_send_db_channel_creates_notification() {
		$this->notifications->send(
			'test_type',
			array(),
			array(AIPS_Notifications::CHANNEL_DB),
			'',
			'https://example.com',
			'Hello from send()'
		);
		$this->assertEquals(1, $this->repository->count_unread());
	}

	public function test_send_db_channel_with_empty_message_does_nothing() {
		$this->notifications->send(
			'test_type',
			array(),
			array(AIPS_Notifications::CHANNEL_DB),
			'',
			'',
			'' // empty message
		);
		$this->assertEquals(0, $this->repository->count_unread());
	}

	// -----------------------------------------------------------------------
	// send() — CHANNEL_EMAIL
	// -----------------------------------------------------------------------

	public function test_send_email_channel_sends_email() {
		$templates = new AIPS_Notification_Templates();
		$templates->register(new AIPS_Notification_Template(
			'test_email',
			'Test Subject {{site_name}}',
			'<p>Hello {{name}}</p>'
		));

		$notifs = new AIPS_Notifications($this->repository, $templates);

		// Reset mock sent log.
		$GLOBALS['phpmailer']->mock_sent = array();

		$notifs->send(
			'test_email',
			array('{{site_name}}' => 'MyBlog', '{{name}}' => 'Jane'),
			array(AIPS_Notifications::CHANNEL_EMAIL),
			'test@example.com'
		);

		$this->assertCount(1, $GLOBALS['phpmailer']->mock_sent);
		$this->assertSame('test@example.com', $GLOBALS['phpmailer']->mock_sent[0]['to'][0][0]);
		$this->assertStringContainsString('MyBlog', $GLOBALS['phpmailer']->mock_sent[0]['subject']);
	}

	public function test_send_email_channel_skips_when_template_not_registered() {
		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->send(
			'unregistered_type',
			array(),
			array(AIPS_Notifications::CHANNEL_EMAIL),
			'test@example.com'
		);

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	public function test_send_email_channel_skips_invalid_email() {
		$templates = new AIPS_Notification_Templates();
		$templates->register(new AIPS_Notification_Template('t', 'S', '<p>B</p>'));
		$notifs = new AIPS_Notifications($this->repository, $templates);

		$GLOBALS['phpmailer']->mock_sent = array();

		$notifs->send('t', array(), array(AIPS_Notifications::CHANNEL_EMAIL), 'not-an-email');

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	// -----------------------------------------------------------------------
	// author_topics_generated()
	// -----------------------------------------------------------------------

	public function test_author_topics_generated_creates_db_notification() {
		$this->notifications->author_topics_generated('Jane Doe', 5, 42);
		$this->assertEquals(1, $this->repository->count_unread());
	}

	public function test_author_topics_generated_notification_contains_author_name() {
		$this->notifications->author_topics_generated('Bob Smith', 3, 7);

		$notifications = $this->repository->get_unread(1);
		$this->assertCount(1, $notifications);
		$this->assertStringContainsString('Bob Smith', $notifications[0]->message);
	}

	public function test_author_topics_generated_notification_contains_topic_count() {
		$this->notifications->author_topics_generated('Alice', 12, 1);

		$notifications = $this->repository->get_unread(1);
		$this->assertStringContainsString('12', $notifications[0]->message);
	}

	// -----------------------------------------------------------------------
	// handle_review_notifications_cron() — gate checks
	// -----------------------------------------------------------------------

	public function test_review_notifications_cron_skips_when_disabled() {
		update_option('aips_review_notifications_enabled', 0);
		update_option('aips_review_notifications_email', 'test@example.com');

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->handle_review_notifications_cron();

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	public function test_review_notifications_cron_skips_when_no_draft_posts() {
		update_option('aips_review_notifications_enabled', 1);
		update_option('aips_review_notifications_email', 'test@example.com');

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->handle_review_notifications_cron();

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	// -----------------------------------------------------------------------
	// posts_awaiting_review()
	// -----------------------------------------------------------------------

	public function test_posts_awaiting_review_sends_email() {
		update_option('aips_review_notifications_email', 'admin@example.com');

		$draft_posts = array(
			'items' => array(
				(object) array(
					'post_title'    => 'Draft 1',
					'generated_title' => '',
					'template_name' => 'My Template',
					'created_at'    => '2025-01-01 10:00:00',
				),
			),
		);

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->posts_awaiting_review($draft_posts, 1);

		$this->assertCount(1, $GLOBALS['phpmailer']->mock_sent);
		$this->assertSame('admin@example.com', $GLOBALS['phpmailer']->mock_sent[0]['to'][0][0]);
	}

	public function test_posts_awaiting_review_email_contains_post_title() {
		update_option('aips_review_notifications_email', 'admin@example.com');

		$draft_posts = array(
			'items' => array(
				(object) array(
					'post_title'    => 'My Review Post',
					'generated_title' => '',
					'template_name' => 'T',
					'created_at'    => '2025-01-01 10:00:00',
				),
			),
		);

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->posts_awaiting_review($draft_posts, 1);

		$this->assertStringContainsString('My Review Post', $GLOBALS['phpmailer']->mock_sent[0]['body']);
	}

	public function test_posts_awaiting_review_skips_invalid_email() {
		update_option('aips_review_notifications_email', 'bad-email');

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->posts_awaiting_review(array('items' => array()), 5);

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	// -----------------------------------------------------------------------
	// partial_generation() — gate checks
	// -----------------------------------------------------------------------

	public function test_partial_generation_skips_when_post_id_is_zero() {
		update_option('aips_review_notifications_email', 'admin@example.com');

		$GLOBALS['phpmailer']->mock_sent = array();

		$this->notifications->partial_generation(0, array('post_title' => false), null);

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);
	}

	public function test_partial_generation_skips_when_no_missing_components() {
		update_option('aips_review_notifications_email', 'admin@example.com');

		$post_id = wp_insert_post(array(
			'post_title'  => 'Test',
			'post_status' => 'draft',
			'post_type'   => 'post',
		));

		$GLOBALS['phpmailer']->mock_sent = array();

		// All components present (all true).
		$this->notifications->partial_generation($post_id, array(
			'post_title'     => true,
			'post_content'   => true,
			'post_excerpt'   => true,
			'featured_image' => true,
		), null);

		$this->assertEmpty($GLOBALS['phpmailer']->mock_sent);

		wp_delete_post($post_id, true);
	}

	public function test_partial_generation_sends_email_with_missing_components() {
		update_option('aips_review_notifications_email', 'admin@example.com');

		$post_id = wp_insert_post(array(
			'post_title'  => 'Partial Post',
			'post_status' => 'draft',
			'post_type'   => 'post',
		));

		$GLOBALS['phpmailer']->mock_sent = array();

		$context = new AIPS_Template_Context((object) array(
			'id'              => 1,
			'name'            => 'Blog Template',
			'prompt_template' => 'Prompt',
			'post_status'     => 'draft',
			'post_category'   => 0,
		));

		$this->notifications->partial_generation($post_id, array(
			'post_title'     => true,
			'post_content'   => false,
			'post_excerpt'   => false,
			'featured_image' => true,
		), $context, 99);

		$this->assertCount(1, $GLOBALS['phpmailer']->mock_sent);
		$this->assertStringContainsString('Partial Post', $GLOBALS['phpmailer']->mock_sent[0]['body']);
		$this->assertStringContainsString('Excerpt', $GLOBALS['phpmailer']->mock_sent[0]['body']);
		$this->assertStringContainsString('Content', $GLOBALS['phpmailer']->mock_sent[0]['body']);
		$this->assertStringContainsString('Blog Template', $GLOBALS['phpmailer']->mock_sent[0]['body']);

		wp_delete_post($post_id, true);
	}

	// -----------------------------------------------------------------------
	// Channel constants
	// -----------------------------------------------------------------------

	public function test_channel_db_constant_value() {
		$this->assertSame('db', AIPS_Notifications::CHANNEL_DB);
	}

	public function test_channel_email_constant_value() {
		$this->assertSame('email', AIPS_Notifications::CHANNEL_EMAIL);
	}

	// -----------------------------------------------------------------------
	// Dynamic hook bindings: get_hook_bindings()
	// -----------------------------------------------------------------------

	public function test_get_hook_bindings_returns_array() {
		$bindings = AIPS_Notifications::get_hook_bindings();
		$this->assertIsArray($bindings);
	}

	public function test_get_hook_bindings_includes_review_notifications_cron() {
		$bindings = AIPS_Notifications::get_hook_bindings();
		$hooks    = array_column($bindings, 'hook');
		$this->assertContains('aips_send_review_notifications', $hooks);
	}

	public function test_get_hook_bindings_includes_post_generation_incomplete() {
		$bindings = AIPS_Notifications::get_hook_bindings();
		$hooks    = array_column($bindings, 'hook');
		$this->assertContains('aips_post_generation_incomplete', $hooks);
	}

	public function test_aips_notification_hook_bindings_filter_can_add_binding() {
		add_filter('aips_notification_hook_bindings', function ( $bindings ) {
			$bindings[] = array(
				'hook'          => 'my_custom_event',
				'method'        => 'handle_review_notifications_cron',
				'priority'      => 20,
				'accepted_args' => 1,
			);
			return $bindings;
		});

		$bindings = AIPS_Notifications::get_hook_bindings();
		$hooks    = array_column($bindings, 'hook');

		$this->assertContains('my_custom_event', $hooks);
	}

	public function test_binding_with_nonexistent_method_is_skipped_with_warning() {
		// Reset static flag so hooks can re-register (test isolation).
		$reflection = new ReflectionClass(AIPS_Notifications::class);
		$prop       = $reflection->getProperty('hooks_registered');
		$prop->setAccessible(true);
		$prop->setValue(null, false);

		add_filter('aips_notification_hook_bindings', function ( $bindings ) {
			$bindings[] = array(
				'hook'          => 'some_hook',
				'method'        => 'method_that_does_not_exist',
				'priority'      => 10,
				'accepted_args' => 1,
			);
			return $bindings;
		});

		// Instantiating should trigger a USER_WARNING (not a fatal) and skip the binding.
		$warning_triggered = false;
		set_error_handler(function ( $errno ) use ( &$warning_triggered ) {
			if ( $errno === E_USER_WARNING ) {
				$warning_triggered = true;
			}
			return true; // suppress the default handler
		});

		new AIPS_Notifications( $this->repository );
		restore_error_handler();

		$this->assertTrue($warning_triggered, 'Expected E_USER_WARNING for invalid method binding');

		// Clean up: reset flag for other tests.
		$prop->setValue(null, false);
	}
}

