<?php
/**
 * Telemetry subsystem registry.
 *
 * @package AI_Post_Scheduler
 * @since   2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Telemetry_Subsystems
 *
 * Defines the single source of truth for telemetry subsystem metadata,
 * default enabled states, and payload storage policies.
 */
class AIPS_Telemetry_Subsystems {

	/**
	 * Return the telemetry subsystem registry.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function registry() {
		return array(
			'generation'  => array(
				'label'           => __('Generation', 'ai-post-scheduler'),
				'description'     => __('Records generation lifecycle events, schedule IDs, author/topic/template IDs, success or failure, elapsed time, and sanitized error summaries.', 'ai-post-scheduler'),
				'default_enabled' => true,
				'payload_policy'  => 'metadata_only_sanitized_errors',
			),
			'ai_requests' => array(
				'label'           => __('AI Requests', 'ai-post-scheduler'),
				'description'     => __('Records AI Engine call metadata such as operation type, model or environment, token estimates, retry count, latency, and sanitized failure details. Prompts and responses are not stored by default.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'metadata_only_no_prompts_or_responses',
			),
			'errors'      => array(
				'label'           => __('Errors', 'ai-post-scheduler'),
				'description'     => __('Records PHP/plugin exceptions, failed AJAX operations, failed cron jobs, failed generations, and correlation IDs.', 'ai-post-scheduler'),
				'default_enabled' => true,
				'payload_policy'  => 'sanitized_error_summaries',
			),
			'cron'        => array(
				'label'           => __('Cron', 'ai-post-scheduler'),
				'description'     => __('Records plugin cron hook execution, duration, batch sizes, and outcome.', 'ai-post-scheduler'),
				'default_enabled' => true,
				'payload_policy'  => 'plugin_cron_metadata',
			),
			'ajax_admin'  => array(
				'label'           => __('Admin AJAX', 'ai-post-scheduler'),
				'description'     => __('Records plugin-owned admin AJAX action name, duration, current plugin page, and result status.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'plugin_ajax_metadata',
			),
			'cache'       => array(
				'label'           => __('Cache', 'ai-post-scheduler'),
				'description'     => __('Records aggregated cache hit, miss, set, delete, and flush counts only, not every individual cache event.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'aggregated_counts_only',
			),
			'queries'     => array(
				'label'           => __('Queries', 'ai-post-scheduler'),
				'description'     => __('Records aggregated query diagnostics only when explicitly enabled; SAVEQUERIES remains off unless this subsystem is enabled.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'aggregated_query_diagnostics',
			),
			'performance' => array(
				'label'           => __('Performance', 'ai-post-scheduler'),
				'description'     => __('Records slow request summaries, memory peak, elapsed time, and thresholds.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'request_summary_thresholds',
			),
			'frontend'    => array(
				'label'           => __('Frontend', 'ai-post-scheduler'),
				'description'     => __('Records only plugin-owned frontend and admin-bar activity, not ordinary public page views.', 'ai-post-scheduler'),
				'default_enabled' => false,
				'payload_policy'  => 'plugin_frontend_activity_only',
			),
		);
	}

	/**
	 * Return the default enabled-state map for persisted options.
	 *
	 * @return array<string,bool>
	 */
	public static function default_enabled_map() {
		$defaults = array();

		foreach (self::registry() as $key => $definition) {
			$defaults[$key] = !empty($definition['default_enabled']);
		}

		return $defaults;
	}

	/**
	 * Return a single subsystem definition.
	 *
	 * @param string $subsystem Subsystem key.
	 * @return array<string,mixed>|null
	 */
	public static function get($subsystem) {
		$key      = sanitize_key((string) $subsystem);
		$registry = self::registry();

		return isset($registry[$key]) ? $registry[$key] : null;
	}

	/**
	 * Return all known subsystem keys.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array_keys(self::registry());
	}
}
