<?php
/**
 * Tests for AIPS_Notification_Registry
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Notification_Registry extends WP_UnitTestCase {

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	public function test_mode_constants_are_defined() {
		$this->assertSame('off',   AIPS_Notification_Registry::MODE_OFF);
		$this->assertSame('db',    AIPS_Notification_Registry::MODE_DB_ONLY);
		$this->assertSame('email', AIPS_Notification_Registry::MODE_EMAIL_ONLY);
		$this->assertSame('both',  AIPS_Notification_Registry::MODE_BOTH);
	}

	public function test_mode_constants_match_aips_notifications() {
		$this->assertSame(AIPS_Notifications::MODE_OFF,        AIPS_Notification_Registry::MODE_OFF);
		$this->assertSame(AIPS_Notifications::MODE_DB_ONLY,    AIPS_Notification_Registry::MODE_DB_ONLY);
		$this->assertSame(AIPS_Notifications::MODE_EMAIL_ONLY, AIPS_Notification_Registry::MODE_EMAIL_ONLY);
		$this->assertSame(AIPS_Notifications::MODE_BOTH,       AIPS_Notification_Registry::MODE_BOTH);
	}

	// -----------------------------------------------------------------------
	// get_type_registry
	// -----------------------------------------------------------------------

	public function test_get_type_registry_returns_array() {
		$registry = AIPS_Notification_Registry::get_type_registry();
		$this->assertIsArray($registry);
	}

	public function test_get_type_registry_has_all_21_types() {
		$registry = AIPS_Notification_Registry::get_type_registry();
		$this->assertCount(21, $registry);
	}

	public function test_get_type_registry_contains_required_keys() {
		$registry = AIPS_Notification_Registry::get_type_registry();
		$expected_types = array(
			'author_topics_generated',
			'generation_failed',
			'quota_alert',
			'integration_error',
			'scheduler_error',
			'system_error',
			'template_generated',
			'manual_generation_completed',
			'post_ready_for_review',
			'post_rejected',
			'partial_generation_completed',
			'daily_digest',
			'weekly_summary',
			'monthly_report',
			'history_cleanup',
			'seeder_complete',
			'template_change',
			'author_suggestions',
			'circuit_breaker_opened',
			'rate_limit_reached',
			'research_topics_ready',
		);

		foreach ($expected_types as $type) {
			$this->assertArrayHasKey($type, $registry, "Registry missing type: {$type}");
		}
	}

	public function test_each_registry_entry_has_label_and_description() {
		$registry = AIPS_Notification_Registry::get_type_registry();
		foreach ($registry as $type => $config) {
			$this->assertArrayHasKey('label', $config, "Type '{$type}' missing label");
			$this->assertArrayHasKey('description', $config, "Type '{$type}' missing description");
		}
	}

	public function test_each_registry_entry_has_valid_default_mode() {
		$registry     = AIPS_Notification_Registry::get_type_registry();
		$valid_modes  = array(
			AIPS_Notification_Registry::MODE_OFF,
			AIPS_Notification_Registry::MODE_DB_ONLY,
			AIPS_Notification_Registry::MODE_EMAIL_ONLY,
			AIPS_Notification_Registry::MODE_BOTH,
		);

		foreach ($registry as $type => $config) {
			$this->assertArrayHasKey('default_mode', $config, "Type '{$type}' missing default_mode");
			$this->assertContains($config['default_mode'], $valid_modes, "Type '{$type}' has invalid default_mode");
		}
	}

	public function test_registry_matches_aips_notifications_registry() {
		$from_registry     = AIPS_Notification_Registry::get_type_registry();
		$from_notifications = AIPS_Notifications::get_notification_type_registry();
		$this->assertSame($from_registry, $from_notifications);
	}

	// -----------------------------------------------------------------------
	// get_high_priority_types
	// -----------------------------------------------------------------------

	public function test_get_high_priority_types_returns_array() {
		$types = AIPS_Notification_Registry::get_high_priority_types();
		$this->assertIsArray($types);
	}

	public function test_get_high_priority_types_subset_of_full_registry() {
		$full       = AIPS_Notification_Registry::get_type_registry();
		$high       = AIPS_Notification_Registry::get_high_priority_types();
		foreach (array_keys($high) as $type) {
			$this->assertArrayHasKey($type, $full, "High-priority type '{$type}' not in full registry");
		}
	}

	public function test_get_high_priority_types_excludes_digest_types() {
		$high = AIPS_Notification_Registry::get_high_priority_types();
		$this->assertArrayNotHasKey('daily_digest',   $high);
		$this->assertArrayNotHasKey('weekly_summary',  $high);
		$this->assertArrayNotHasKey('monthly_report',  $high);
		$this->assertArrayNotHasKey('history_cleanup', $high);
	}

	public function test_get_high_priority_types_includes_error_types() {
		$high = AIPS_Notification_Registry::get_high_priority_types();
		$this->assertArrayHasKey('generation_failed',    $high);
		$this->assertArrayHasKey('quota_alert',          $high);
		$this->assertArrayHasKey('integration_error',    $high);
		$this->assertArrayHasKey('circuit_breaker_opened', $high);
	}

	public function test_get_high_priority_types_matches_aips_notifications() {
		$from_registry     = AIPS_Notification_Registry::get_high_priority_types();
		$from_notifications = AIPS_Notifications::get_high_priority_notification_types();
		$this->assertSame($from_registry, $from_notifications);
	}

	// -----------------------------------------------------------------------
	// get_channel_mode_options
	// -----------------------------------------------------------------------

	public function test_get_channel_mode_options_returns_array() {
		$options = AIPS_Notification_Registry::get_channel_mode_options();
		$this->assertIsArray($options);
	}

	public function test_get_channel_mode_options_has_four_modes() {
		$options = AIPS_Notification_Registry::get_channel_mode_options();
		$this->assertCount(4, $options);
	}

	public function test_get_channel_mode_options_has_all_mode_keys() {
		$options = AIPS_Notification_Registry::get_channel_mode_options();
		$this->assertArrayHasKey(AIPS_Notification_Registry::MODE_OFF,        $options);
		$this->assertArrayHasKey(AIPS_Notification_Registry::MODE_DB_ONLY,    $options);
		$this->assertArrayHasKey(AIPS_Notification_Registry::MODE_EMAIL_ONLY, $options);
		$this->assertArrayHasKey(AIPS_Notification_Registry::MODE_BOTH,       $options);
	}

	public function test_get_channel_mode_options_matches_aips_notifications() {
		$from_registry      = AIPS_Notification_Registry::get_channel_mode_options();
		$from_notifications = AIPS_Notifications::get_channel_mode_options();
		$this->assertSame($from_registry, $from_notifications);
	}
}
