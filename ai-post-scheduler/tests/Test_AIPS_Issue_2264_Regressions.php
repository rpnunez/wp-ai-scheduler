<?php
/**
 * Regression coverage for the asset, topic-edit, and schedule-status fixes
 * tracked with GitHub issue 2264.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_AIPS_Issue_2264_Regressions extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array(
			'role' => 'administrator',
		) );
		wp_set_current_user( $this->admin_user_id );

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		remove_all_actions( 'admin_footer' );
		$this->reset_asset_scaffold_flags();
	}

	public function tearDown(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();

		parent::tearDown();
	}

	/**
	 * History and the generated-posts tabs share the canonical AI Edit bundle.
	 */
	public function test_ai_edit_assets_enqueued_on_history_and_generated_post_screens() {
		$screens = array(
			'history'              => array( 'aips-history', '' ),
			'generated posts'      => array( 'aips-generated-posts', 'aips-generated-posts' ),
			'pending review'       => array( 'aips-generated-posts', 'aips-pending-review' ),
			'partial generations'  => array( 'aips-generated-posts', 'aips-partial-generations' ),
		);

		$saw_modal = false;
		foreach ( $screens as $label => $screen ) {
			$this->reset_asset_queues();
			$this->enqueue_plugin_screen( $screen[0], $screen[1] );

			$this->assertTrue( wp_script_is( 'aips-admin-ai-edit', 'enqueued' ), "AI Edit script missing on {$label}." );
			$this->assertTrue( wp_style_is( 'aips-admin-ai-edit', 'enqueued' ), "AI Edit style missing on {$label}." );

			global $wp_scripts, $wp_styles;
			$this->assertSame(
				array( 'jquery', 'aips-utilities-script', 'aips-admin-script' ),
				$wp_scripts->registered['aips-admin-ai-edit']->deps,
				"AI Edit script deps drifted on {$label}."
			);
			$this->assertSame(
				array( 'dashicons', 'aips-design-tokens' ),
				$wp_styles->registered['aips-admin-ai-edit']->deps,
				"AI Edit style deps drifted on {$label}."
			);

			$l10n = $this->get_localized_object( 'aips-admin-ai-edit', 'aipsAIEditL10n' );
			foreach ( array(
				'acceptAndReplace',
				'discard',
				'acceptAll',
				'currentVersion',
				'regeneratedVersion',
				'stagedForSave',
				'regenerated',
				'modified',
				'revisionOriginal',
				'imageStatusCompleted',
				'imageStatusFailed',
				'imageStatusPending',
				'noImage',
				'revisionManualEdit',
				'revisionRestored',
			) as $key ) {
				$this->assertArrayHasKey( $key, $l10n, "aipsAIEditL10n.{$key} missing on {$label}." );
			}
			$this->assertSame( 'Accept & Replace', $l10n['acceptAndReplace'] );
			$this->assertSame( 'No image', $l10n['noImage'] );

			$this->assertNotFalse( has_action( 'admin_footer' ), "AI Edit modal scaffold was not registered on {$label}." );

			if ( ! $saw_modal ) {
				ob_start();
				do_action( 'admin_footer' );
				$footer = ob_get_clean();
				$this->assertStringContainsString( 'id="aips-ai-edit-modal"', $footer );
				$saw_modal = true;
			}
		}
	}

	/**
	 * AI Errors uses the history modal opener registration, not the history page bundle.
	 */
	public function test_ai_errors_tab_uses_history_modal_opener() {
		$this->enqueue_plugin_screen( 'aips-diagnostics', 'ai-errors' );

		$this->assertTrue( wp_script_is( 'aips-admin-history', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'aips-admin-ai-errors', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'aips-admin-view-session', 'enqueued' ) );

		global $wp_scripts;
		$this->assertSame(
			array( 'jquery', 'aips-utilities-script', 'heartbeat' ),
			$wp_scripts->registered['aips-admin-history']->deps
		);

		$data = $wp_scripts->get_data( 'aips-admin-history', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'aipsHistoryModalAjax', $data );
		$this->assertStringContainsString( 'aipsHistoryModalL10n', $data );
		$this->assertStringNotContainsString( 'aipsHistoryL10n', $data );

		ob_start();
		do_action( 'admin_footer' );
		$footer = ob_get_clean();
		$this->assertStringContainsString( 'id="aips-history-modal"', $footer );
	}

	public function test_has_unsplash_access_key_localized_when_key_is_saved() {
		$config = AIPS_Config::get_instance();
		$config->set_option( 'aips_unsplash_access_key', 'test-unsplash-key' );

		$this->enqueue_plugin_screen( 'aips-settings', '' );
		$admin = $this->get_localized_object( 'aips-admin-script', 'aipsAdminL10n' );
		$this->assertSame( '1', $admin['hasUnsplashAccessKey'] );

		$this->reset_asset_queues();
		$this->enqueue_plugin_screen( 'aips-studio', '' );
		$templates = $this->get_localized_object( 'aips-admin-script', 'aipsTemplatesL10n' );
		$this->assertSame( '1', $templates['hasUnsplashAccessKey'] );

		$config->set_option( 'aips_unsplash_access_key', '   ' );
		$this->reset_asset_queues();
		$this->enqueue_plugin_screen( 'aips-settings', '' );
		$empty = $this->get_localized_object( 'aips-admin-script', 'aipsAdminL10n' );
		$this->assertSame( '', $empty['hasUnsplashAccessKey'] );

		$config->set_option( 'aips_unsplash_access_key', '' );
	}

	public function test_template_slug_and_author_wizard_and_post_review_l10n_keys() {
		$this->enqueue_plugin_screen( 'aips-studio', '' );
		$templates = $this->get_localized_object( 'aips-admin-script', 'aipsTemplatesL10n' );
		$this->assertSame( 'No', $templates['slugNo'] );
		$this->assertSame( 'Yes (SEO Standard)', $templates['slugYesStandard'] );
		$this->assertSame( 'Yes (Custom Prompt)', $templates['slugYesCustom'] );

		$this->reset_asset_queues();
		$this->enqueue_plugin_screen( 'aips-automations', 'authors' );
		$authors = $this->get_localized_object( 'aips-authors-script', 'aipsAuthorsL10n' );
		$this->assertSame( 'Save Changes', $authors['saveChanges'] );
		$this->assertSame( 'Not specified', $authors['notSpecified'] );
		$this->assertSame( 'Enabled', $authors['enabled'] );
		$this->assertSame( 'Disabled', $authors['disabled'] );
		$this->assertSame( 'Please complete this required field.', $authors['requiredField'] );
		$this->assertSame( 'Select at least one Source Group, or turn off Include Sources.', $authors['sourceGroupsRequired'] );

		$this->reset_asset_queues();
		$this->enqueue_plugin_screen( 'aips-generated-posts', 'aips-pending-review' );
		$review = $this->get_localized_object( 'aips-admin-post-review', 'aipsPostReviewL10n' );
		$this->assertSame( 'Please select a bulk action.', $review['noActionSelected'] );
		$this->assertSame( 'No Posts Found', $review['noPostsFound'] );
		$this->assertSame( 'No draft posts match your search criteria. Try a different search term.', $review['noPostsFoundDesc'] );
		$this->assertSame( 'Failed to load posts.', $review['fetchError'] );
	}

	public function test_diagnostics_and_automations_tabs_use_controller_active_key() {
		$resolver = new AIPS_Admin_Asset_Context_Resolver();

		$_GET = array( 'page' => 'aips-diagnostics', 'tab' => 'operations-insights' );
		$this->assertTrue( $resolver->is_diagnostics_tab( 'aips-diagnostics', 'insights' ) );
		$this->assertFalse( $resolver->is_diagnostics_tab( 'aips-diagnostics', 'operations-insights' ) );

		$_GET = array( 'page' => 'aips-diagnostics', 'tab' => 'not-a-real-tab' );
		$this->assertFalse( $resolver->is_diagnostics_tab( 'aips-diagnostics', 'not-a-real-tab' ) );
		$this->assertTrue( $resolver->is_diagnostics_tab( 'aips-diagnostics', 'system-info' ) );

		$_GET = array( 'page' => 'aips-automations', 'tab' => 'affiliate-links' );
		$this->assertTrue( $resolver->is_automations_tab( 'aips-automations', 'monetization' ) );
		$this->assertFalse( $resolver->is_automations_tab( 'aips-automations', 'affiliate-links' ) );

		$_GET = array( 'page' => 'aips-automations', 'tab' => 'not-a-real-tab' );
		$this->assertFalse( $resolver->is_automations_tab( 'aips-automations', 'not-a-real-tab' ) );
		$this->assertTrue( $resolver->is_automations_tab( 'aips-automations', 'schedules' ) );
	}

	public function test_edit_author_topic_succeeds_without_error() {
		$authors = new AIPS_Authors_Repository();
		$topics  = new AIPS_Author_Topics_Repository();
		$logs    = new AIPS_Author_Topic_Logs_Repository();

		$author_id = $authors->create( array(
			'name'        => 'Regression Author',
			'field_niche' => 'Testing',
			'is_active'   => 1,
		) );
		$topic_id = $topics->create( array(
			'author_id'   => $author_id,
			'topic_title' => 'Original title',
			'status'      => 'pending',
		) );

		$this->assertNotEmpty( $author_id );
		$this->assertNotEmpty( $topic_id );

		$_POST = array(
			'nonce'       => wp_create_nonce( 'aips_ajax_nonce' ),
			'topic_id'    => $topic_id,
			'topic_title' => 'Updated title',
		);
		$_REQUEST = $_POST;

		$handler  = new AIPS_Author_Topics_Mutation_Handler();
		$response = $this->call_ajax( array( $handler, 'edit_topic' ) );

		$this->assertIsArray( $response );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Topic updated successfully.', $response['data']['message'] );

		$updated = $topics->get_by_id( $topic_id );
		$this->assertSame( 'Updated title', $updated->topic_title );

		$entries = $logs->get_by_topic( $topic_id );
		$this->assertNotEmpty( $entries );
		$this->assertSame( 'Changed from: Original title', $entries[0]->notes );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'aips_author_topic_logs', array( 'author_topic_id' => $topic_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'aips_author_topics', array( 'id' => $topic_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'aips_authors', array( 'id' => $author_id ), array( '%d' ) );
	}

	public function test_non_array_cron_hook_still_adds_timeline_row_with_zero_count() {
		$original_cron = get_option( 'cron' );
		$timestamp     = time() + 120;

		update_option( 'cron', array(
			$timestamp => array(
				'aips_process_schedule_batch' => 'not-an-array',
			),
			'version'  => 2,
		) );
		$_POST = array(
			'nonce' => wp_create_nonce( 'aips_ajax_nonce' ),
		);
		$_REQUEST = $_POST;
		AIPS_Cache_Factory::make()->delete( 'aips_schedule_status_strip_v2' );

		try {
			$handler  = new AIPS_Schedule_Query_Handler();
			$response = $this->call_ajax( array( $handler, 'get_schedule_status_read_model' ) );
		} finally {
			update_option( 'cron', $original_cron );
			AIPS_Cache_Factory::make()->delete( 'aips_schedule_status_strip_v2' );
		}

		$this->assertIsArray( $response, 'Schedule status response was not JSON.' );
		$this->assertTrue( $response['success'], wp_json_encode( $response ) );

		$matches = array_values( array_filter(
			$response['data']['queue_timeline'],
			function ( $row ) {
				return isset( $row['hook'] ) && 'aips_process_schedule_batch' === $row['hook'];
			}
		) );
		$this->assertCount( 1, $matches );
		$this->assertSame( 0, $matches[0]['count'] );
		$this->assertSame( 0, $response['data']['queue_depth']['aips_process_schedule_batch'] );
	}

	public function test_history_log_retention_field_uses_shared_markup() {
		$ui = new AIPS_Settings_UI();
		ob_start();
		$ui->history_log_retention_field_callback();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'aips-retention-input-row', $html );
		$this->assertStringContainsString( 'aips-form-input aips-retention-input-val', $html );
		$this->assertStringContainsString( 'aips-form-select', $html );
		$this->assertStringContainsString( 'name="aips_history_log_retention_value"', $html );
		$this->assertStringContainsString( 'name="aips_history_log_retention_unit"', $html );
	}

	/**
	 * Allow each test to observe a fresh admin_footer scaffold registration.
	 *
	 * @return void
	 */
	private function reset_asset_scaffold_flags() {
		$reflection = new ReflectionClass( 'AIPS_Admin_Asset_Registrar_Global' );
		foreach ( array( 'history_modal_scaffold_registered', 'ai_edit_scaffold_registered' ) as $property_name ) {
			$property = $reflection->getProperty( $property_name );
			$property->setAccessible( true );
			$property->setValue( null, false );
		}
	}

	/**
	 * @param string $page Page slug.
	 * @param string $tab  Tab key. Empty leaves the query param unset.
	 * @return void
	 */
	private function enqueue_plugin_screen( $page, $tab ) {
		$_GET = array( 'page' => $page );
		if ( '' !== $tab ) {
			$_GET['tab'] = $tab;
		}

		$assets = new AIPS_Admin_Assets();
		$assets->enqueue_admin_assets( 'admin_page_' . $page );
	}

	/**
	 * @return void
	 */
	private function reset_asset_queues() {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	/**
	 * @param string $handle      Script handle.
	 * @param string $object_name Localized JS object name.
	 * @return array<string, mixed>
	 */
	private function get_localized_object( $handle, $object_name ) {
		global $wp_scripts;
		$data = $wp_scripts->get_data( $handle, 'data' );
		$this->assertIsString( $data, "No localized data for {$handle}." );

		$pattern = '/var ' . preg_quote( $object_name, '/' ) . ' = (\{.*?\});/s';
		$this->assertMatchesRegularExpression( $pattern, $data, "{$object_name} was not localized on {$handle}." );
		preg_match( $pattern, $data, $matches );
		$decoded = json_decode( $matches[1], true );
		$this->assertIsArray( $decoded, "{$object_name} JSON could not be decoded." );

		return $decoded;
	}

	/**
	 * @param callable $callable AJAX callback.
	 * @return array<string, mixed>|null
	 */
	private function call_ajax( $callable ) {
		ob_start();
		try {
			$callable();
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected when wp_send_json_* is called.
		} catch ( WPAjaxDieStopException $e ) {
			// Expected for wp_die()-style early exits.
		}

		$output = ob_get_clean();

		return json_decode( strtok( trim( (string) $output ), "\r\n" ), true );
	}
}
