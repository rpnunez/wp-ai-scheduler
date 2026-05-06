<?php
/**
 * Unit tests for AIPS_Config.
 *
 * Verifies that get_option() returns correct defaults when no DB value is set,
 * that all typed accessor methods return expected structures, that the singleton
 * contract is maintained, and that the per-request cache is invalidated on write.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

class Test_AIPS_Config extends WP_UnitTestCase {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Option keys this test class may mutate.
	 *
	 * @var array
	 */
	private $tracked_option_keys = array(
		'aips_ai_model',
		'aips_max_tokens_limit',
		'aips_enable_logging',
		'aips_cache_driver',
		'aips_ai_env',
	);

	/**
	 * Original option values captured before each test.
	 *
	 * @var array
	 */
	private $original_option_values = array();

	/**
	 * Snapshot tracked option values before each test runs.
	 *
	 * @return void
	 */
	private function capture_tracked_option_values(): void {
		$this->original_option_values = array();

		foreach ( $this->tracked_option_keys as $option_key ) {
			$sentinel = new stdClass();
			$value    = get_option( $option_key, $sentinel );

			$this->original_option_values[ $option_key ] = array(
				'exists' => $value !== $sentinel,
				'value'  => $value !== $sentinel ? $value : null,
			);
		}
	}

	/**
	 * Restore tracked option values after each test runs.
	 *
	 * @return void
	 */
	private function restore_tracked_option_values(): void {
		foreach ( $this->tracked_option_keys as $option_key ) {
			if ( ! isset( $this->original_option_values[ $option_key ] ) ) {
				delete_option( $option_key );
				continue;
			}

			if ( ! empty( $this->original_option_values[ $option_key ]['exists'] ) ) {
				update_option( $option_key, $this->original_option_values[ $option_key ]['value'] );
			} else {
				delete_option( $option_key );
			}
		}

		$this->original_option_values = array();
	}

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->config = AIPS_Config::get_instance();
		$this->capture_tracked_option_values();
		$this->config->flush_option_cache();
	}

	/**
	 * Runs after each test — flush cache, restore hooks, and restore/delete any tracked test options.
	 */
	public function tearDown(): void {
		$this->restore_tracked_option_values();
		$this->config->flush_option_cache();
		$this->config->reregister_option_cache_hooks();
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_instance_returns_same_object() {
		$a = AIPS_Config::get_instance();
		$b = AIPS_Config::get_instance();
		$this->assertSame( $a, $b );
	}

	// -----------------------------------------------------------------------
	// get_option — default values
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_option_returns_registered_default_when_option_absent() {
		delete_option( 'aips_ai_model' );
		$this->assertSame( '', $this->config->get_option( 'aips_ai_model' ) );
	}

	/** @test */
	public function test_get_option_returns_integer_default_for_max_tokens_limit() {
		delete_option( 'aips_max_tokens_limit' );
		$this->assertSame( 16000, $this->config->get_option( 'aips_max_tokens_limit' ) );
	}

	/** @test */
	public function test_get_option_returns_boolean_default_for_enable_logging() {
		delete_option( 'aips_enable_logging' );
		$this->assertTrue( $this->config->get_option( 'aips_enable_logging' ) );
	}

	/** @test */
	public function test_get_option_returns_float_default_for_temperature() {
		delete_option( 'aips_temperature' );
		$this->assertSame( 0.7, $this->config->get_option( 'aips_temperature' ) );
	}

	/** @test */
	public function test_get_option_returns_stored_db_value_over_default() {
		update_option( 'aips_ai_model', 'gpt-4o' );
		$this->assertSame( 'gpt-4o', $this->config->get_option( 'aips_ai_model' ) );
	}

	/** @test */
	public function test_get_option_returns_caller_default_when_option_absent() {
		delete_option( 'aips_ai_model' );
		$this->assertSame( 'fallback-model', $this->config->get_option( 'aips_ai_model', 'fallback-model' ) );
	}

	/** @test */
	public function test_get_option_returns_null_for_unknown_option() {
		delete_option( 'aips_unknown_option_xyz' );
		$this->assertNull( $this->config->get_option( 'aips_unknown_option_xyz' ) );
	}

	/** @test */
	public function test_get_option_reflects_update_after_cache_invalidation() {
		update_option( 'aips_ai_model', 'original-model' );
		$this->assertSame( 'original-model', $this->config->get_option( 'aips_ai_model' ) );

		update_option( 'aips_ai_model', 'updated-model' );
		// Cache is invalidated via the updated_option hook; next read should see new value.
		$this->assertSame( 'updated-model', $this->config->get_option( 'aips_ai_model' ) );
	}

	// -----------------------------------------------------------------------
	// has_option — database-presence check
	// -----------------------------------------------------------------------

	/** @test */
	public function test_has_option_returns_false_when_option_absent() {
		delete_option( 'aips_ai_model' );
		$this->assertFalse( $this->config->has_option( 'aips_ai_model' ) );
	}

	/** @test */
	public function test_has_option_returns_true_when_option_present() {
		update_option( 'aips_ai_model', 'gpt-4o' );
		$this->assertTrue( $this->config->has_option( 'aips_ai_model' ) );
	}

	/** @test */
	public function test_has_option_returns_true_when_option_stored_as_false() {
		update_option( 'aips_enable_retry', false );
		// The value is explicitly stored — has_option() must return true even though
		// the stored value is boolean false (which WordPress uses as its "not found" sentinel).
		$this->assertTrue( $this->config->has_option( 'aips_enable_retry' ) );
	}

	/** @test */
	public function test_has_option_returns_true_when_option_stored_as_empty_string() {
		update_option( 'aips_unsplash_access_key', '' );
		$this->assertTrue( $this->config->has_option( 'aips_unsplash_access_key' ) );
	}

	/** @test */
	public function test_has_option_returns_false_for_completely_unknown_key() {
		delete_option( 'aips_nonexistent_xyz_abc' );
		$this->assertFalse( $this->config->has_option( 'aips_nonexistent_xyz_abc' ) );
	}

	/** @test */
	public function test_has_option_does_not_fall_back_to_registered_defaults() {
		// aips_max_tokens_limit has a registered default of 16000 in get_default_options().
		// has_option() must not return true just because a default is registered.
		delete_option( 'aips_max_tokens_limit' );
		$this->assertFalse( $this->config->has_option( 'aips_max_tokens_limit' ) );
	}

	// -----------------------------------------------------------------------
	// get_ai_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_ai_config_returns_expected_keys() {
		$ai = $this->config->get_ai_config();

		$this->assertArrayHasKey( 'model',            $ai );
		$this->assertArrayHasKey( 'env_id',           $ai );
		$this->assertArrayHasKey( 'max_tokens_limit', $ai );
		$this->assertArrayHasKey( 'temperature',      $ai );
	}

	/** @test */
	public function test_get_ai_config_returns_defaults_when_options_absent() {
		delete_option( 'aips_ai_model' );
		delete_option( 'aips_ai_env_id' );
		delete_option( 'aips_max_tokens_limit' );
		delete_option( 'aips_temperature' );

		$ai = $this->config->get_ai_config();

		$this->assertSame( '',      $ai['model'] );
		$this->assertSame( '',      $ai['env_id'] );
		$this->assertSame( 16000,   $ai['max_tokens_limit'] );
		$this->assertSame( 0.7,     $ai['temperature'] );
	}

	/** @test */
	public function test_get_ai_config_returns_stored_values() {
		update_option( 'aips_ai_model',         'claude-3-5-sonnet' );
		update_option( 'aips_ai_env_id',        'env-abc123' );
		update_option( 'aips_max_tokens_limit', 8000 );
		update_option( 'aips_temperature',      0.5 );

		$ai = $this->config->get_ai_config();

		$this->assertSame( 'claude-3-5-sonnet', $ai['model'] );
		$this->assertSame( 'env-abc123',        $ai['env_id'] );
		$this->assertSame( 8000,                $ai['max_tokens_limit'] );
		$this->assertSame( 0.5,                 $ai['temperature'] );
	}

	/** @test */
	public function test_get_ai_config_casts_types_correctly() {
		update_option( 'aips_max_tokens_limit', '4000' );
		update_option( 'aips_temperature',      '0.9' );

		$ai = $this->config->get_ai_config();

		$this->assertIsInt(   $ai['max_tokens_limit'] );
		$this->assertIsFloat( $ai['temperature'] );
		$this->assertIsString( $ai['model'] );
		$this->assertIsString( $ai['env_id'] );
	}

	// -----------------------------------------------------------------------
	// get_retry_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_retry_config_returns_expected_keys() {
		$r = $this->config->get_retry_config();

		$this->assertArrayHasKey( 'enabled',       $r );
		$this->assertArrayHasKey( 'max_attempts',  $r );
		$this->assertArrayHasKey( 'initial_delay', $r );
		$this->assertArrayHasKey( 'exponential',   $r );
		$this->assertArrayHasKey( 'jitter',        $r );
	}

	/** @test */
	public function test_get_retry_config_defaults() {
		delete_option( 'aips_enable_retry' );
		delete_option( 'aips_retry_max_attempts' );
		delete_option( 'aips_retry_initial_delay' );

		$r = $this->config->get_retry_config();

		$this->assertFalse( $r['enabled'] );
		$this->assertSame( 3, $r['max_attempts'] );
		$this->assertSame( 1, $r['initial_delay'] );
		$this->assertTrue( $r['exponential'] );
		$this->assertTrue( $r['jitter'] );
	}

	// -----------------------------------------------------------------------
	// get_token_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_token_config_returns_expected_keys() {
		$t = $this->config->get_token_config();

		$this->assertArrayHasKey( 'max_tokens_limit',   $t );
		$this->assertArrayHasKey( 'max_tokens_title',   $t );
		$this->assertArrayHasKey( 'max_tokens_excerpt', $t );
		$this->assertArrayHasKey( 'max_tokens_content', $t );
	}

	/** @test */
	public function test_get_token_config_defaults() {
		delete_option( 'aips_max_tokens_limit' );
		delete_option( 'aips_max_tokens_title' );
		delete_option( 'aips_max_tokens_excerpt' );
		delete_option( 'aips_max_tokens_content' );

		$t = $this->config->get_token_config();

		$this->assertSame( 16000, $t['max_tokens_limit'] );
		$this->assertSame( 150,   $t['max_tokens_title'] );
		$this->assertSame( 300,   $t['max_tokens_excerpt'] );
		$this->assertSame( 4000,  $t['max_tokens_content'] );
	}

	// -----------------------------------------------------------------------
	// get_post_defaults_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_post_defaults_config_returns_expected_keys() {
		$p = $this->config->get_post_defaults_config();

		$this->assertArrayHasKey( 'post_status', $p );
		$this->assertArrayHasKey( 'category',    $p );
		$this->assertArrayHasKey( 'post_author', $p );
	}

	/** @test */
	public function test_get_post_defaults_config_defaults() {
		delete_option( 'aips_default_post_status' );
		delete_option( 'aips_default_category' );
		delete_option( 'aips_default_post_author' );

		$p = $this->config->get_post_defaults_config();

		$this->assertSame( 'draft', $p['post_status'] );
		$this->assertSame( 0,       $p['category'] );
		$this->assertSame( 1,       $p['post_author'] );
	}

	// -----------------------------------------------------------------------
	// get_notification_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_notification_config_returns_expected_keys() {
		$n = $this->config->get_notification_config();

		$this->assertArrayHasKey( 'review_email', $n );
		$this->assertArrayHasKey( 'preferences',  $n );
	}

	/** @test */
	public function test_get_notification_config_preferences_default_is_array() {
		delete_option( 'aips_notification_preferences' );

		$n = $this->config->get_notification_config();

		$this->assertIsArray( $n['preferences'] );
	}

	// -----------------------------------------------------------------------
	// get_notification_digest_config — typed accessor (NEW)
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_notification_digest_config_returns_expected_keys() {
		$d = $this->config->get_notification_digest_config();

		$this->assertArrayHasKey( 'daily_last_sent',   $d );
		$this->assertArrayHasKey( 'weekly_last_sent',  $d );
		$this->assertArrayHasKey( 'monthly_last_sent', $d );
	}

	/** @test */
	public function test_get_notification_digest_config_defaults_are_empty_strings() {
		delete_option( 'aips_notif_daily_digest_last_sent' );
		delete_option( 'aips_notif_weekly_summary_last_sent' );
		delete_option( 'aips_notif_monthly_report_last_sent' );

		$d = $this->config->get_notification_digest_config();

		$this->assertSame( '', $d['daily_last_sent'] );
		$this->assertSame( '', $d['weekly_last_sent'] );
		$this->assertSame( '', $d['monthly_last_sent'] );
	}

	/** @test */
	public function test_get_notification_digest_config_returns_stored_values() {
		update_option( 'aips_notif_daily_digest_last_sent',   '2025-04-11' );
		update_option( 'aips_notif_weekly_summary_last_sent', '2025-W15' );
		update_option( 'aips_notif_monthly_report_last_sent', '2025-04' );

		$d = $this->config->get_notification_digest_config();

		$this->assertSame( '2025-04-11', $d['daily_last_sent'] );
		$this->assertSame( '2025-W15',   $d['weekly_last_sent'] );
		$this->assertSame( '2025-04',    $d['monthly_last_sent'] );
	}

	/** @test */
	public function test_get_notification_digest_config_casts_values_to_string() {
		update_option( 'aips_notif_daily_digest_last_sent', '2025-04-11' );

		$d = $this->config->get_notification_digest_config();

		$this->assertIsString( $d['daily_last_sent'] );
		$this->assertIsString( $d['weekly_last_sent'] );
		$this->assertIsString( $d['monthly_last_sent'] );
	}

	// -----------------------------------------------------------------------
	// get_cache_config — typed accessor (NEW)
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_cache_config_returns_expected_keys() {
		$c = $this->config->get_cache_config();

		$this->assertArrayHasKey( 'driver',         $c );
		$this->assertArrayHasKey( 'db_prefix',      $c );
		$this->assertArrayHasKey( 'default_ttl',    $c );
		$this->assertArrayHasKey( 'redis_host',     $c );
		$this->assertArrayHasKey( 'redis_port',     $c );
		$this->assertArrayHasKey( 'redis_password', $c );
		$this->assertArrayHasKey( 'redis_db',       $c );
		$this->assertArrayHasKey( 'redis_prefix',   $c );
		$this->assertArrayHasKey( 'redis_timeout',  $c );
	}

	/** @test */
	public function test_get_cache_config_returns_defaults_when_options_absent() {
		delete_option( 'aips_cache_driver' );
		delete_option( 'aips_cache_db_prefix' );
		delete_option( 'aips_cache_default_ttl' );
		delete_option( 'aips_cache_redis_host' );
		delete_option( 'aips_cache_redis_port' );
		delete_option( 'aips_cache_redis_password' );
		delete_option( 'aips_cache_redis_db' );
		delete_option( 'aips_cache_redis_prefix' );
		delete_option( 'aips_cache_redis_timeout' );

		$c = $this->config->get_cache_config();

		$this->assertSame( 'array',     $c['driver'] );
		$this->assertSame( '',          $c['db_prefix'] );
		$this->assertSame( 3600,        $c['default_ttl'] );
		$this->assertSame( '127.0.0.1', $c['redis_host'] );
		$this->assertSame( 6379,        $c['redis_port'] );
		$this->assertSame( '',          $c['redis_password'] );
		$this->assertSame( 0,           $c['redis_db'] );
		$this->assertSame( 'aips',      $c['redis_prefix'] );
		$this->assertSame( 2.0,         $c['redis_timeout'] );
	}

	/** @test */
	public function test_get_cache_config_casts_types_correctly() {
		update_option( 'aips_cache_driver',      'db' );
		update_option( 'aips_cache_redis_port',  '6380' );
		update_option( 'aips_cache_redis_db',    '1' );
		update_option( 'aips_cache_redis_timeout', '5' );
		update_option( 'aips_cache_default_ttl', '7200' );

		$c = $this->config->get_cache_config();

		$this->assertIsString( $c['driver'] );
		$this->assertIsInt(    $c['redis_port'] );
		$this->assertIsInt(    $c['redis_db'] );
		$this->assertIsFloat(  $c['redis_timeout'] );
		$this->assertIsInt(    $c['default_ttl'] );
	}

	// -----------------------------------------------------------------------
	// get_logging_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_logging_config_returns_expected_keys() {
		$l = $this->config->get_logging_config();

		$this->assertArrayHasKey( 'enabled',        $l );
		$this->assertArrayHasKey( 'retention_days', $l );
		$this->assertArrayHasKey( 'level',          $l );
	}

	/** @test */
	public function test_get_logging_config_defaults() {
		delete_option( 'aips_enable_logging' );
		delete_option( 'aips_log_retention_days' );

		$l = $this->config->get_logging_config();

		$this->assertTrue( $l['enabled'] );
		$this->assertSame( 30, $l['retention_days'] );
		$this->assertSame( 'info', $l['level'] );
	}

	// -----------------------------------------------------------------------
	// get_general_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_general_config_returns_expected_keys() {
		$g = $this->config->get_general_config();

		$this->assertArrayHasKey( 'developer_mode',             $g );
		$this->assertArrayHasKey( 'unsplash_access_key',        $g );
		$this->assertArrayHasKey( 'topic_similarity_threshold', $g );
	}

	/** @test */
	public function test_get_general_config_defaults() {
		delete_option( 'aips_developer_mode' );
		delete_option( 'aips_unsplash_access_key' );
		delete_option( 'aips_topic_similarity_threshold' );

		$g = $this->config->get_general_config();

		$this->assertFalse( $g['developer_mode'] );
		$this->assertSame( '', $g['unsplash_access_key'] );
		$this->assertSame( 0.8, $g['topic_similarity_threshold'] );
	}

	// -----------------------------------------------------------------------
	// get_site_content_config — typed accessor
	// -----------------------------------------------------------------------

	/** @test */
	public function test_get_site_content_config_returns_expected_keys() {
		$s = $this->config->get_site_content_config();

		$this->assertArrayHasKey( 'niche',               $s );
		$this->assertArrayHasKey( 'target_audience',     $s );
		$this->assertArrayHasKey( 'content_goals',       $s );
		$this->assertArrayHasKey( 'default_article_structure_id', $s );
		$this->assertArrayHasKey( 'brand_voice',         $s );
		$this->assertArrayHasKey( 'content_language',    $s );
		$this->assertArrayHasKey( 'content_guidelines',  $s );
		$this->assertArrayHasKey( 'excluded_topics',     $s );
	}

	/** @test */
	public function test_get_site_content_config_defaults() {
		delete_option( 'aips_site_niche' );
		delete_option( 'aips_default_article_structure_id' );
		delete_option( 'aips_site_content_language' );

		$s = $this->config->get_site_content_config();

		$this->assertSame( '',   $s['niche'] );
		$this->assertSame( 0,    $s['default_article_structure_id'] );
		$this->assertSame( 'en', $s['content_language'] );
	}

	// -----------------------------------------------------------------------
	// set_option / cache invalidation
	// -----------------------------------------------------------------------

	/** @test */
	public function test_set_option_updates_value_and_invalidates_cache() {
		update_option( 'aips_ai_model', 'old-model' );
		$this->assertSame( 'old-model', $this->config->get_option( 'aips_ai_model' ) );

		$this->config->set_option( 'aips_ai_model', 'new-model' );
		$this->assertSame( 'new-model', $this->config->get_option( 'aips_ai_model' ) );
	}

	/** @test */
	public function test_flush_option_cache_forces_fresh_read_from_db() {
		update_option( 'aips_ai_model', 'initial' );
		$this->assertSame( 'initial', $this->config->get_option( 'aips_ai_model' ) );

		// Simulate a direct DB write that bypasses set_option() (e.g. during tests).
		update_option( 'aips_ai_model', 'flushed' );
		$this->config->flush_option_cache();

		$this->assertSame( 'flushed', $this->config->get_option( 'aips_ai_model' ) );
	}
}
