<?php
/**
 * WordPress Site Health Integration Class
 *
 * Integrates AI Post Scheduler diagnostics into native WordPress Core Site Health
 * (Tools > Site Health), providing direct status checks and system debugging
 * export information. Provider availability does not test remote connectivity.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Site_Health_Integration
 */
class AIPS_Site_Health_Integration {

	/**
	 * Primary generation cron hook.
	 */
	const GENERATION_CRON_HOOK = 'aips_generate_scheduled_posts';

	/**
	 * Initialize Site Health hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter('site_status_tests', array(__CLASS__, 'register_site_status_tests'));
		add_filter('debug_information', array(__CLASS__, 'add_debug_information'));
	}

	/**
	 * Register AIPS status tests with the WordPress Site Health system.
	 *
	 * @param array<string, mixed> $tests Existing tests array.
	 * @return array<string, mixed> Modified tests array.
	 */
	public static function register_site_status_tests($tests) {
		if (!is_array($tests)) {
			$tests = array();
		}

		if (!isset($tests['direct'])) {
			$tests['direct'] = array();
		}

		// 1. AI Provider Engine Check (Direct)
		$tests['direct']['aips_ai_provider'] = array(
			'label' => __('AI Provider Availability', 'ai-post-scheduler'),
			'test'  => array(__CLASS__, 'test_ai_provider'),
		);

		// 2. WP-Cron Execution Health Check (Direct)
		$tests['direct']['aips_cron_health'] = array(
			'label' => __('AI Post Scheduler Cron Health', 'ai-post-scheduler'),
			'test'  => array(__CLASS__, 'test_cron_health'),
		);

		// 3. Database Integrity Check (Direct)
		$tests['direct']['aips_database_integrity'] = array(
			'label' => __('AI Post Scheduler Database Tables', 'ai-post-scheduler'),
			'test'  => array(__CLASS__, 'test_database_integrity'),
		);

		return $tests;
	}

	/**
	 * Direct Check: Verifies whether an active AI provider backend is configured.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_ai_provider() {
		$has_provider = AIPS_AI_Provider_Factory::has_available_provider();

		if ($has_provider) {
			return array(
				'label'       => __('AI provider backend is available', 'ai-post-scheduler'),
				'status'      => 'good',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__('A supported AI provider connector (Meow Apps AI Engine or WordPress AI Client) is active and available for generation pipelines.', 'ai-post-scheduler')
				),
				'actions'     => '',
				'test'        => 'aips_ai_provider',
			);
		}

		return array(
			'label'       => __('No active AI provider detected', 'ai-post-scheduler'),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__('AI Post Scheduler requires an active AI engine backend to generate content. Please install Meow Apps AI Engine or configure WordPress AI Client credentials.', 'ai-post-scheduler')
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url(admin_url('admin.php?page=aips-settings')),
				esc_html__('Configure AI Settings', 'ai-post-scheduler')
			),
			'test'        => 'aips_ai_provider',
		);
	}

	/**
	 * Direct Check: Verifies scheduling health and cron execution latency.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_cron_health() {
		$next_run = wp_next_scheduled(self::GENERATION_CRON_HOOK);
		$now      = time();

		if (!$next_run) {
			// Check if there are active schedules in the database
			$repo = new AIPS_Schedule_Repository();
			$status_counts = $repo->count_by_status();
			$active_schedules_count = isset($status_counts['active']) ? (int) $status_counts['active'] : 0;

			if ($active_schedules_count > 0) {
				return array(
					'label'       => __('Generation cron event is not scheduled despite active schedules', 'ai-post-scheduler'),
					'status'      => 'critical',
					'badge'       => array(
						'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
						'color' => 'red',
					),
					'description' => sprintf(
						'<p>%s</p>',
						esc_html__('You have active content generation schedules, but the main cron hook (aips_generate_scheduled_posts) is not scheduled in WordPress. Run the cron repair tool in Diagnostics.', 'ai-post-scheduler')
					),
					'actions'     => sprintf(
						'<p><a href="%s">%s</a></p>',
						esc_url(admin_url('admin.php?page=aips-diagnostics')),
						esc_html__('Repair Cron in Diagnostics', 'ai-post-scheduler')
					),
					'test'        => 'aips_cron_health',
				);
			}

			return array(
				'label'       => __('Automated generation cron is idle', 'ai-post-scheduler'),
				'status'      => 'good',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__('No automated generation schedules are currently active, so the recurring cron event is resting.', 'ai-post-scheduler')
				),
				'actions'     => '',
				'test'        => 'aips_cron_health',
			);
		}

		// If next run is over 15 minutes in the past, cron is lagging
		$lag = $now - $next_run;
		if ($lag > 900) {
			return array(
				'label'       => __('Generation cron is experiencing latency', 'ai-post-scheduler'),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %d: number of minutes delayed */
						esc_html__('The scheduled generation event was expected %d minutes ago. This may indicate low visitor traffic to trigger WP-Cron or a server-level cron worker delay.', 'ai-post-scheduler'),
						round($lag / 60)
					)
				),
				'actions'     => sprintf(
					'<p><a href="%s">%s</a></p>',
					esc_url(admin_url('admin.php?page=aips-diagnostics')),
					esc_html__('Check System Status Diagnostics', 'ai-post-scheduler')
				),
				'test'        => 'aips_cron_health',
			);
		}

		return array(
			'label'       => __('Automated generation cron is on schedule', 'ai-post-scheduler'),
			'status'      => 'good',
			'badge'       => array(
				'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: %s: Human-readable time until next run */
					esc_html__('The automated generation event is scheduled and running on time. Next execution is in %s.', 'ai-post-scheduler'),
					human_time_diff($now, $next_run)
				)
			),
			'actions'     => '',
			'test'        => 'aips_cron_health',
		);
	}

	/**
	 * Direct Check: Verifies that all required custom database tables exist and are healthy.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_database_integrity() {
		$missing = AIPS_DB_Manager::get_missing_table_names();

		if (empty($missing)) {
			return array(
				'label'       => __('All AI Post Scheduler database tables are present', 'ai-post-scheduler'),
				'status'      => 'good',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__('All core database tables were verified and are available for content generation and history tracking.', 'ai-post-scheduler')
				),
				'actions'     => '',
				'test'        => 'aips_database_integrity',
			);
		}

		return array(
			'label'       => __('One or more custom database tables are missing', 'ai-post-scheduler'),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p><p><code>%s</code></p>',
				esc_html__('The following required tables could not be found in the database. Please visit Diagnostics to re-run schema creation.', 'ai-post-scheduler'),
				esc_html(implode(', ', $missing))
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url(admin_url('admin.php?page=aips-diagnostics')),
				esc_html__('Repair Tables in Diagnostics', 'ai-post-scheduler')
			),
			'test'        => 'aips_database_integrity',
		);
	}

	/**
	 * Add plugin diagnostic telemetry to Tools > Site Health > Info.
	 *
	 * @param array<string, mixed> $debug_info Existing debug information.
	 * @return array<string, mixed>
	 */
	public static function add_debug_information($debug_info) {
		if (!is_array($debug_info)) {
			$debug_info = array();
		}

		$fields = array(
			'version' => array(
				'label' => __('Plugin Version', 'ai-post-scheduler'),
				'value' => defined('AIPS_VERSION') ? AIPS_VERSION : 'unknown',
			),
		);

		// Provider & Model Info
		$config = AIPS_Config::get_instance();
		$fields['default_model'] = array(
			'label' => __('Default AI Model', 'ai-post-scheduler'),
			'value' => $config->get_option('aips_ai_model', 'Default'),
		);
		$fields['default_temperature'] = array(
			'label' => __('Default Temperature', 'ai-post-scheduler'),
			'value' => (string) $config->get_option('aips_default_temperature', '0.7'),
		);

		// Active counts
		$schedule_repo = new AIPS_Schedule_Repository();
		$schedule_counts = $schedule_repo->count_by_status();
		$fields['active_schedules'] = array(
			'label' => __('Active Schedules', 'ai-post-scheduler'),
			'value' => (string) (isset($schedule_counts['active']) ? $schedule_counts['active'] : 0),
		);

		$template_repo = new AIPS_Template_Repository();
		$template_counts = $template_repo->count_by_status();
		$fields['total_templates'] = array(
			'label' => __('Configured Templates', 'ai-post-scheduler'),
			'value' => (string) (isset($template_counts['total']) ? $template_counts['total'] : 0),
		);

		$topics_repo = new AIPS_Author_Topics_Repository();
		$counts = $topics_repo->get_global_status_counts();
		$fields['approved_topic_queue'] = array(
			'label' => __('Approved Topics in Queue', 'ai-post-scheduler'),
			'value' => (string) (isset($counts['approved']) ? $counts['approved'] : 0),
		);

		$generation_cron = wp_next_scheduled(self::GENERATION_CRON_HOOK);
		$fields['next_cron_event'] = array(
			'label' => __('Next Scheduled Cron', 'ai-post-scheduler'),
			'value' => $generation_cron ? wp_date('Y-m-d H:i:s', $generation_cron) : __('None scheduled', 'ai-post-scheduler'),
		);

		$digest_enabled = (bool) $config->get_option('aips_notif_digest_enabled');
		$digest_cron    = wp_next_scheduled('aips_notif_digest_cron');
		$fields['notification_digest'] = array(
			'label' => __('Notification Digest Cron', 'ai-post-scheduler'),
			'value' => $digest_enabled
				? ($digest_cron ? wp_date('Y-m-d H:i:s', $digest_cron) : __('Enabled but not scheduled', 'ai-post-scheduler'))
				: __('Disabled', 'ai-post-scheduler'),
		);

		$fields['max_post_images_limit'] = array(
			'label' => __('Max Post Images', 'ai-post-scheduler'),
			'value' => (string) $config->get_max_post_images_limit(),
		);

		$debug_info['ai-post-scheduler'] = array(
			'label'       => __('AI Post Scheduler', 'ai-post-scheduler'),
			'description' => __('Operational telemetry and diagnostics for AI Post Scheduler.', 'ai-post-scheduler'),
			'fields'      => $fields,
		);

		return $debug_info;
	}
}
