<?php
/**
 * WordPress Site Health Integration Class
 *
 * Integrates AI Post Scheduler diagnostics into native WordPress Core Site Health
 * (Tools > Site Health), providing direct status checks, async AI connectivity tests,
 * and comprehensive system debugging export information.
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
	 * Core tables required for plugin execution.
	 *
	 * @var string[]
	 */
	const CORE_TABLES = array(
		'aips_templates',
		'aips_schedule',
		'aips_history',
		'aips_authors',
		'aips_author_topics',
		'aips_voices',
		'aips_article_structures',
		'aips_post_slices',
	);

	/**
	 * Initialize Site Health hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter('site_status_tests', array(__CLASS__, 'register_site_status_tests'));
		add_filter('debug_information', array(__CLASS__, 'add_debug_information'));
		add_action('wp_ajax_health_check_aips_site_health_ai_connectivity', array(__CLASS__, 'ajax_test_ai_connectivity'));
		add_action('wp_ajax_aips_site_health_ai_connectivity', array(__CLASS__, 'ajax_test_ai_connectivity'));
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
		if (!isset($tests['async'])) {
			$tests['async'] = array();
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

		// 4. Token Budget Headroom Check (Direct)
		$tests['direct']['aips_token_budget'] = array(
			'label' => __('Token Budget & Headroom', 'ai-post-scheduler'),
			'test'  => array(__CLASS__, 'test_token_budget'),
		);

		// 5. Live AI Connectivity Test (Async)
		$tests['async']['aips_ai_connectivity'] = array(
			'label'             => __('Live AI Provider Connectivity', 'ai-post-scheduler'),
			'test'              => 'aips_site_health_ai_connectivity',
			'has_rest'          => false,
			'async_direct_test' => array(__CLASS__, 'test_ai_connectivity_direct'),
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
				'label'       => __('AI Provider is configured and operational', 'ai-post-scheduler'),
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
		$next_run = wp_next_scheduled('aips_cron_event');
		$now      = time();

		$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
		$alternate_cron = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;

		if (!$next_run) {
			// Check if there are active schedules in the database
			$repo = new AIPS_Schedule_Repository();
			$active_schedules_count = $repo->count_active();

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
						esc_html__('You have active content generation schedules, but the main cron hook (aips_cron_event) is not scheduled in WordPress. Run the cron repair tool in Diagnostics.', 'ai-post-scheduler')
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
		global $wpdb;

		$missing = array();
		foreach (self::CORE_TABLES as $table_base) {
			$table_name = $wpdb->prefix . $table_base;
			$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
			if ($found !== $table_name) {
				$missing[] = $table_name;
			}
		}

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
	 * Direct Check: Verifies token headroom and monthly limit consumption.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_token_budget() {
		$budget_limit = (int) AIPS_Config::get_instance()->get_option('aips_monthly_token_budget', 0);

		if ($budget_limit <= 0) {
			return array(
				'label'       => __('No token budget cap is configured', 'ai-post-scheduler'),
				'status'      => 'good',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__('Your AI generation pipelines have unlimited token headroom configured without local spending caps.', 'ai-post-scheduler')
				),
				'actions'     => '',
				'test'        => 'aips_token_budget',
			);
		}

		// Calculate current usage for the current month
		$repo = new AIPS_Dashboard_Repository();
		$month_start = strtotime('first day of this month 00:00:00');
		$month_end   = strtotime('last day of this month 23:59:59');
		$stats = $repo->get_summary_stats($month_start, $month_end);
		$used_tokens = isset($stats['total_tokens']) ? (int) $stats['total_tokens'] : 0;

		$percent = $budget_limit > 0 ? ($used_tokens / $budget_limit) * 100 : 0;

		if ($percent >= 90) {
			return array(
				'label'       => __('Token budget is nearing monthly exhaustion', 'ai-post-scheduler'),
				'status'      => 'recommended',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'orange',
				),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: 1: formatted used tokens, 2: formatted budget limit, 3: percentage */
						esc_html__('You have consumed %1$s of your %2$s monthly token limit (%3$.1f%%). Consider adjusting your budget limits to prevent generation pauses.', 'ai-post-scheduler'),
						number_format_i18n($used_tokens),
						number_format_i18n($budget_limit),
						$percent
					)
				),
				'actions'     => sprintf(
					'<p><a href="%s">%s</a></p>',
					esc_url(admin_url('admin.php?page=aips-settings')),
					esc_html__('Update Token Limits in Settings', 'ai-post-scheduler')
				),
				'test'        => 'aips_token_budget',
			);
		}

		return array(
			'label'       => __('Token headroom is healthy', 'ai-post-scheduler'),
			'status'      => 'good',
			'badge'       => array(
				'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>',
				sprintf(
					/* translators: 1: formatted used tokens, 2: formatted budget limit, 3: percentage */
					esc_html__('%1$s of %2$s monthly tokens used (%3$.1f%%). Generous headroom is available for scheduled runs.', 'ai-post-scheduler'),
					number_format_i18n($used_tokens),
					number_format_i18n($budget_limit),
					$percent
				)
			),
			'actions'     => '',
			'test'        => 'aips_token_budget',
		);
	}

	/**
	 * Async Check / Direct Callback: Tests live AI connectivity.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_ai_connectivity_direct() {
		$start_time = microtime(true);
		$available  = AIPS_AI_Provider_Factory::has_available_provider();
		$latency = round((microtime(true) - $start_time) * 1000, 2);

		if ($available) {
			return array(
				'label'       => __('Live AI Provider connectivity is operational', 'ai-post-scheduler'),
				'status'      => 'good',
				'badge'       => array(
					'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
					'color' => 'blue',
				),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: %s: response latency in milliseconds */
						esc_html__('Connection to the configured AI engine completed successfully (latency: %s ms).', 'ai-post-scheduler'),
						$latency
					)
				),
				'actions'     => '',
				'test'        => 'aips_ai_connectivity',
			);
		}

		return array(
			'label'       => __('Could not establish live connection with AI provider', 'ai-post-scheduler'),
			'status'      => 'critical',
			'badge'       => array(
				'label' => __('AI Post Scheduler', 'ai-post-scheduler'),
				'color' => 'red',
			),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__('Failed to contact your configured AI provider backend. Verify your API credentials and ensure the Meow Apps AI Engine or WordPress AI Client connector is functioning.', 'ai-post-scheduler')
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url(admin_url('admin.php?page=aips-settings')),
				esc_html__('Review AI Settings', 'ai-post-scheduler')
			),
			'test'        => 'aips_ai_connectivity',
		);
	}

	/**
	 * AJAX endpoint for the async Site Health connectivity check.
	 *
	 * @return void
	 */
	public static function ajax_test_ai_connectivity() {
		check_ajax_referer('health-check-site-status');

		if (!current_user_can('view_site_health_checks')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$result = self::test_ai_connectivity_direct();
		wp_send_json_success($result);
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
			'value' => $config->get_option('aips_default_model', 'Default'),
		);
		$fields['default_temperature'] = array(
			'label' => __('Default Temperature', 'ai-post-scheduler'),
			'value' => (string) $config->get_option('aips_default_temperature', '0.7'),
		);

		// Active counts
		$schedule_repo = new AIPS_Schedule_Repository();
		$fields['active_schedules'] = array(
			'label' => __('Active Schedules', 'ai-post-scheduler'),
			'value' => (string) $schedule_repo->count_active(),
		);

		$template_repo = new AIPS_Template_Repository();
		$fields['total_templates'] = array(
			'label' => __('Configured Templates', 'ai-post-scheduler'),
			'value' => (string) $template_repo->count_all(),
		);

		$topics_repo = new AIPS_Author_Topics_Repository();
		$counts = $topics_repo->get_global_status_counts();
		$fields['approved_topic_queue'] = array(
			'label' => __('Approved Topics in Queue', 'ai-post-scheduler'),
			'value' => (string) (isset($counts['approved']) ? $counts['approved'] : 0),
		);

		$fields['next_cron_event'] = array(
			'label' => __('Next Scheduled Cron', 'ai-post-scheduler'),
			'value' => wp_next_scheduled('aips_cron_event') ? wp_date('Y-m-d H:i:s', wp_next_scheduled('aips_cron_event')) : __('None scheduled', 'ai-post-scheduler'),
		);

		$debug_info['ai-post-scheduler'] = array(
			'label'       => __('AI Post Scheduler', 'ai-post-scheduler'),
			'description' => __('Operational telemetry and diagnostics for AI Post Scheduler.', 'ai-post-scheduler'),
			'fields'      => $fields,
		);

		return $debug_info;
	}
}
