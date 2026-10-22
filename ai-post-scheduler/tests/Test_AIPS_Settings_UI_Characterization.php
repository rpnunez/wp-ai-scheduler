<?php
/**
 * Test AIPS Settings UI Characterization
 *
 * Validates domain presenter slicing, delegation facade, sanitization rules,
 * and HTML field callback rendering contracts.
 *
 * @package AI_Post_Scheduler\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_AIPS_Settings_UI_Characterization extends WP_UnitTestCase {

	/**
	 * @var AIPS_Settings_UI
	 */
	protected $ui;

	public function setUp(): void {
		parent::setUp();
		$this->ui = new AIPS_Settings_UI();
	}

	public function test_domain_presenters_instantiated_and_accessible() {
		$this->assertInstanceOf( AIPS_Settings_UI_General::class, $this->ui->get_general() );
		$this->assertInstanceOf( AIPS_Settings_UI_AI::class, $this->ui->get_ai() );
		$this->assertInstanceOf( AIPS_Settings_UI_Resilience::class, $this->ui->get_resilience() );
		$this->assertInstanceOf( AIPS_Settings_UI_Logging::class, $this->ui->get_logging() );
		$this->assertInstanceOf( AIPS_Settings_UI_Content::class, $this->ui->get_content() );
		$this->assertInstanceOf( AIPS_Settings_UI_Links::class, $this->ui->get_links() );
		$this->assertInstanceOf( AIPS_Settings_UI_Authors::class, $this->ui->get_authors() );
		$this->assertInstanceOf( AIPS_Settings_UI_Integrations::class, $this->ui->get_integrations() );
	}

	public function test_all_expected_callbacks_are_callable_on_ui_facade() {
		$callbacks = array(
			'general_section_callback',
			'post_status_field_callback',
			'category_field_callback',
			'bulk_publish_async_threshold_field_callback',
			'ai_section_callback',
			'ai_provider_field_callback',
			'wp_ai_connectors_field_callback',
			'ai_model_field_callback',
			'embeddings_header_callback',
			'embeddings_provider_field_callback',
			'resilience_section_callback',
			'enable_retry_field_callback',
			'logging_field_callback',
			'cache_driver_field_callback',
			'developers_section_callback',
			'content_strategy_section_callback',
			'site_niche_field_callback',
			'authors_section_callback',
			'author_topic_auto_approval_mode_field_callback',
			'api_keys_section_callback',
			'unsplash_access_key_field_callback',
			'gsc_field_callback',
			'ai_autolink_section_callback',
			'link_index_section_callback',
			'link_rules_section_callback',
			'ai_related_posts_section_callback',
			'ai_deduplication_section_callback',
			'indexer_post_types_field_callback',
			'related_posts_enabled_field_callback',
			'deduplication_mode_field_callback',
		);

		foreach ( $callbacks as $cb ) {
			$this->assertTrue( is_callable( array( $this->ui, $cb ) ), "Method {$cb} should be callable on AIPS_Settings_UI facade" );
		}
	}

	public function test_general_sanitizers() {
		// Retention days (0 to 3650)
		$this->assertEquals( 0, $this->ui->sanitize_source_archive_retention_days( -5 ) );
		$this->assertEquals( 30, $this->ui->sanitize_source_archive_retention_days( 30 ) );
		$this->assertEquals( 3650, $this->ui->sanitize_source_archive_retention_days( 4000 ) );

		// Bulk publish async threshold (clamped 2 to 500)
		$this->assertEquals( 2, $this->ui->sanitize_bulk_publish_async_threshold( 0 ) );
		$this->assertEquals( 2, $this->ui->sanitize_bulk_publish_async_threshold( 2 ) );
		$this->assertEquals( 25, $this->ui->sanitize_bulk_publish_async_threshold( 25 ) );
		$this->assertEquals( 500, $this->ui->sanitize_bulk_publish_async_threshold( 999 ) );
		$this->assertEquals( 2, $this->ui->sanitize_bulk_publish_async_threshold( 'invalid' ) );
	}

	public function test_ai_sanitizers() {
		// Token budget (min 1)
		$this->assertEquals( 1, $this->ui->sanitize_token_budget( 0 ) );
		$this->assertEquals( 1000, $this->ui->sanitize_token_budget( 1000 ) );

		// AI Provider (known registered providers: meow, wp_ai_client)
		$this->assertEquals( 'meow', $this->ui->sanitize_ai_provider( 'meow' ) );
		$this->assertEquals( 'wp_ai_client', $this->ui->sanitize_ai_provider( 'wp_ai_client' ) );
		$this->assertEquals( '', $this->ui->sanitize_ai_provider( 'malicious_provider' ) );

		// Connector mode
		$this->assertEquals( 'all', $this->ui->sanitize_wp_ai_connector_mode( 'all' ) );
		$this->assertEquals( 'selected', $this->ui->sanitize_wp_ai_connector_mode( 'selected' ) );
		$this->assertEquals( 'all', $this->ui->sanitize_wp_ai_connector_mode( 'invalid' ) );

		// Connector IDs
		$this->assertEquals( array( 'conn_1', 'conn_2' ), $this->ui->sanitize_wp_ai_connector_ids( array( 'conn_1', 'conn_2', '' ) ) );

		// Embeddings scope
		$this->assertEquals( 'aips_only', $this->ui->sanitize_embeddings_scope( 'aips_only' ) );
		$this->assertEquals( 'date_range', $this->ui->sanitize_embeddings_scope( 'date_range' ) );
		$this->assertEquals( 'all', $this->ui->sanitize_embeddings_scope( 'all' ) );
		$this->assertEquals( 'aips_only', $this->ui->sanitize_embeddings_scope( 'invalid_scope' ) );
	}

	public function test_logging_and_cache_sanitizers() {
		$this->assertEquals( 1, $this->ui->sanitize_enable_cache_system( '1' ) );
		$this->assertEquals( 1, $this->ui->sanitize_enable_cache_system( true ) );
		$this->assertEquals( 0, $this->ui->sanitize_enable_cache_system( '0' ) );
		$this->assertEquals( 0, $this->ui->sanitize_enable_cache_system( '' ) );

		$this->assertEquals( 'array', $this->ui->sanitize_cache_driver( 'array' ) );
		$this->assertEquals( 'db', $this->ui->sanitize_cache_driver( 'db' ) );
		$this->assertEquals( 'wp_object_cache', $this->ui->sanitize_cache_driver( 'wp_object_cache' ) );
		$this->assertEquals( 'wp_object_cache', $this->ui->sanitize_cache_driver( 'redis' ) );
		$this->assertEquals( 'array', $this->ui->sanitize_cache_driver( 'bad_driver' ) );
	}

	public function test_author_sanitizers() {
		$this->assertEquals( 0.75, $this->ui->sanitize_similarity_threshold( 0.75 ) );
		$this->assertEquals( 0.10, $this->ui->sanitize_similarity_threshold( 0.10 ) );
		$this->assertEquals( 1.0, $this->ui->sanitize_similarity_threshold( 1.5 ) );

		$this->assertEquals( 'score', $this->ui->sanitize_author_topic_auto_approval_mode( 'score' ) );
		$this->assertEquals( 'manual', $this->ui->sanitize_author_topic_auto_approval_mode( 'invalid' ) );

		$this->assertEquals( 'pending', $this->ui->sanitize_author_topic_auto_approval_fallback( 'pending' ) );
		$this->assertEquals( 'smart_split', $this->ui->sanitize_author_topic_auto_approval_fallback( 'smart_split' ) );
		$this->assertEquals( 'pending', $this->ui->sanitize_author_topic_auto_approval_fallback( 'unknown' ) );

		$this->assertEquals( 80, $this->ui->sanitize_author_topic_auto_approval_percent( 80 ) );
		$this->assertEquals( 1, $this->ui->sanitize_author_topic_auto_approval_percent( 0 ) );
		$this->assertEquals( 100, $this->ui->sanitize_author_topic_auto_approval_percent( 150 ) );

		$this->assertEquals( 0.85, $this->ui->sanitize_author_topic_auto_approval_max_similarity( 0.85 ) );
		$this->assertEquals( 0.50, $this->ui->sanitize_author_topic_auto_approval_max_similarity( 0.10 ) );
		$this->assertEquals( 0.99, $this->ui->sanitize_author_topic_auto_approval_max_similarity( 1.50 ) );
	}

	public function test_integrations_sanitizers() {
		$emails = $this->ui->sanitize_notification_emails( 'test@example.com, invalid-email, admin@domain.org' );
		$this->assertStringContainsString( 'test@example.com', $emails );
		$this->assertStringContainsString( 'admin@domain.org', $emails );
		$this->assertStringNotContainsString( 'invalid-email', $emails );

		$prefs = array(
			'generation_failed' => 'email',
		);
		$sanitized_prefs = $this->ui->sanitize_notification_preferences( $prefs );
		$this->assertIsArray( $sanitized_prefs );
		$this->assertEquals( 'email', $sanitized_prefs['generation_failed'] );
	}

	public function test_links_indexer_deduplication_sanitizers() {
		// Autolink threshold (0.50 - 1.00)
		$this->assertEquals( 0.85, $this->ui->sanitize_autolink_threshold( 'invalid' ) );
		$this->assertEquals( 0.50, $this->ui->sanitize_autolink_threshold( 0.10 ) );
		$this->assertEquals( 1.00, $this->ui->sanitize_autolink_threshold( 1.20 ) );
		$this->assertEquals( 0.78, $this->ui->sanitize_autolink_threshold( 0.78 ) );

		// Autolink limit (1 - 100)
		$this->assertEquals( 1, $this->ui->sanitize_autolink_limit( 0 ) );
		$this->assertEquals( 100, $this->ui->sanitize_autolink_limit( 200 ) );
		$this->assertEquals( 15, $this->ui->sanitize_autolink_limit( 15 ) );

		// Link index batch size (10 - 500)
		$this->assertEquals( 10, $this->ui->sanitize_link_index_batch_size( 5 ) );
		$this->assertEquals( 500, $this->ui->sanitize_link_index_batch_size( 1000 ) );
		$this->assertEquals( 50, $this->ui->sanitize_link_index_batch_size( 50 ) );

		// Link index batch delay (0 - 600)
		$this->assertEquals( 600, $this->ui->sanitize_link_index_batch_delay( 800 ) );
		$this->assertEquals( 20, $this->ui->sanitize_link_index_batch_delay( 20 ) );

		// Link rules max per post (1 - 20)
		$this->assertEquals( 1, $this->ui->sanitize_link_rules_max_per_post( 0 ) );
		$this->assertEquals( 20, $this->ui->sanitize_link_rules_max_per_post( 50 ) );
		$this->assertEquals( 5, $this->ui->sanitize_link_rules_max_per_post( 5 ) );

		// Publish linking mode (off|review|apply)
		$this->assertEquals( 'review', $this->ui->sanitize_publish_linking_mode( 'invalid' ) );
		$this->assertEquals( 'apply', $this->ui->sanitize_publish_linking_mode( 'apply' ) );
		$this->assertEquals( 'off', $this->ui->sanitize_publish_linking_mode( 'off' ) );

		// Link click retention days (30 - 730)
		$this->assertEquals( 30, $this->ui->sanitize_link_click_retention_days( 10 ) );
		$this->assertEquals( 730, $this->ui->sanitize_link_click_retention_days( 900 ) );
		$this->assertEquals( 180, $this->ui->sanitize_link_click_retention_days( 180 ) );

		// Silo guide settings
		$this->assertEquals( 'after_first_paragraph', $this->ui->sanitize_silo_guide_position( 'after_first_paragraph' ) );
		$this->assertEquals( 'end', $this->ui->sanitize_silo_guide_position( 'header' ) );
		$this->assertEquals( 10, $this->ui->sanitize_silo_guide_max( 10 ) );
		$this->assertEquals( 1, $this->ui->sanitize_silo_guide_max( 0 ) );
		$this->assertEquals( 50, $this->ui->sanitize_silo_guide_max( 100 ) );
		$this->assertEquals( 'theme', $this->ui->sanitize_silo_guide_style( 'theme' ) );
		$this->assertEquals( 'aips', $this->ui->sanitize_silo_guide_style( 'custom' ) );

		// Autolink rel
		$this->assertEquals( 'nofollow', $this->ui->sanitize_autolink_rel( 'nofollow' ) );
		$this->assertEquals( '', $this->ui->sanitize_autolink_rel( 'noopener' ) );

		// Cluster detection
		$this->assertEquals( 250, $this->ui->sanitize_cluster_detection_max_posts( '' ) );
		$this->assertEquals( 50, $this->ui->sanitize_cluster_detection_max_posts( 10 ) );
		$this->assertEquals( 5000, $this->ui->sanitize_cluster_detection_max_posts( 6000 ) );
		$this->assertEquals( 8, $this->ui->sanitize_cluster_detection_time_budget( '' ) );
		$this->assertEquals( 2, $this->ui->sanitize_cluster_detection_time_budget( 1 ) );
		$this->assertEquals( 25, $this->ui->sanitize_cluster_detection_time_budget( 30 ) );

		// Duplicate review
		$this->assertEquals( 0.88, $this->ui->sanitize_duplicate_review_threshold( '' ) );
		$this->assertEquals( 0.70, $this->ui->sanitize_duplicate_review_threshold( 0.50 ) );
		$this->assertEquals( 0.99, $this->ui->sanitize_duplicate_review_threshold( 1.10 ) );
		$this->assertEquals( 5, $this->ui->sanitize_duplicate_review_max_group_size( '' ) );
		$this->assertEquals( 2, $this->ui->sanitize_duplicate_review_max_group_size( 1 ) );
		$this->assertEquals( 10, $this->ui->sanitize_duplicate_review_max_group_size( 15 ) );
		$this->assertEquals( 1000, $this->ui->sanitize_duplicate_review_max_pairs( '' ) );
		$this->assertEquals( 100, $this->ui->sanitize_duplicate_review_max_pairs( 50 ) );
		$this->assertEquals( 5000, $this->ui->sanitize_duplicate_review_max_pairs( 9000 ) );

		// Excluded post IDs
		$this->assertEquals( '12, 48, 301', $this->ui->sanitize_duplicate_excluded_post_ids( '12, 48, 301, abc' ) );

		// Protection and strategy
		$this->assertEquals( 10, $this->ui->sanitize_duplicate_protect_inbound_links( '' ) );
		$this->assertEquals( 0, $this->ui->sanitize_duplicate_protect_inbound_links( -5 ) );
		$this->assertEquals( 1000, $this->ui->sanitize_duplicate_protect_inbound_links( 2000 ) );

		$this->assertEquals( 'revision', $this->ui->sanitize_duplicate_default_merge_mode( 'invalid' ) );
		$this->assertEquals( 'rewrite', $this->ui->sanitize_duplicate_default_merge_mode( 'rewrite' ) );

		$this->assertEquals( 'off', $this->ui->sanitize_duplicate_scan_schedule( 'invalid' ) );
		$this->assertEquals( 'daily', $this->ui->sanitize_duplicate_scan_schedule( 'daily' ) );

		$this->assertEquals( 'balanced', $this->ui->sanitize_cluster_tightness( 'invalid' ) );
		$this->assertEquals( 'strict', $this->ui->sanitize_cluster_tightness( 'strict' ) );

		$this->assertEquals( 'grid', $this->ui->sanitize_related_posts_layout( 'invalid' ) );
		$this->assertEquals( 'list', $this->ui->sanitize_related_posts_layout( 'list' ) );

		$this->assertEquals( 'warn', $this->ui->sanitize_deduplication_mode( 'invalid' ) );
		$this->assertEquals( 'block', $this->ui->sanitize_deduplication_mode( 'block' ) );

		$this->assertEquals( array( 'post' ), $this->ui->sanitize_post_types( 'not_an_array' ) );
		$this->assertEquals( array( 'post', 'page' ), $this->ui->sanitize_post_types( array( 'post', 'page', '' ) ) );

		$this->assertEquals( 'queued', $this->ui->sanitize_publish_execution_timing( 'invalid' ) );
		$this->assertEquals( 'immediate', $this->ui->sanitize_publish_execution_timing( 'immediate' ) );

		$this->assertEquals( 'minutes', $this->ui->sanitize_error_pause_unit( 'invalid' ) );
		$this->assertEquals( 'hours', $this->ui->sanitize_error_pause_unit( 'hours' ) );
	}

	public function test_html_rendering_characterization() {
		// Post Status Field
		ob_start();
		$this->ui->post_status_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="aips_default_post_status"', $html );

		// Bulk Publish Async Threshold Field
		ob_start();
		$this->ui->bulk_publish_async_threshold_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="aips_bulk_publish_async_threshold"', $html );

		// Cache Driver Field
		ob_start();
		$this->ui->cache_driver_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="aips_cache_driver"', $html );
		$this->assertStringContainsString( 'name="aips_cache_driver"', $html );

		// Site Niche Field (Content Strategy)
		ob_start();
		$this->ui->site_niche_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="aips_site_niche"', $html );

		// Related Posts Enabled Field
		ob_start();
		$this->ui->related_posts_enabled_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="aips_related_posts_enabled"', $html );

		// Link Index Field
		ob_start();
		$this->ui->link_index_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="aips_link_index_enabled"', $html );

		// Author Topic Auto Approval Mode Field
		ob_start();
		$this->ui->author_topic_auto_approval_mode_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'id="aips_author_topic_auto_approval_mode"', $html );

		// Unsplash Access Key Field
		ob_start();
		$this->ui->unsplash_access_key_field_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="aips_unsplash_access_key"', $html );
	}
}
