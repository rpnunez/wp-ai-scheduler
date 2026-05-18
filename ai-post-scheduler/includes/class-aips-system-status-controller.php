<?php
/**
 * System Status Controller
 *
 * Handles AJAX actions specific to the System Status admin page.
 *
 * @package AI_Post_Scheduler
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}


/**
 * Class AIPS_System_Status_Controller
 *
 * Registers and handles AJAX actions for the System Status page.
 */
class AIPS_System_Status_Controller {

	public function __construct() {
		add_action('wp_ajax_aips_reset_circuit_breaker', array($this, 'ajax_reset_circuit_breaker'));
		add_action('wp_ajax_aips_status_reschedule_missed_cron', array($this, 'ajax_reschedule_missed_cron'));
		add_action('wp_ajax_aips_status_retry_failed_slices', array($this, 'ajax_retry_failed_slices'));
		add_action('wp_ajax_aips_status_clear_partial_generations', array($this, 'ajax_clear_partial_generations'));
		add_action('wp_ajax_aips_status_cleanup_stale_jobs_cache', array($this, 'ajax_cleanup_stale_jobs_cache'));
		add_action('wp_ajax_aips_rebuild_caches', array($this, 'ajax_rebuild_caches'));
	}

	/**
	 * AJAX: Reset the AI service circuit breaker.
	 *
	 * @return void
	 */
	public function ajax_reset_circuit_breaker() {
		if ( ! check_ajax_referer('aips_reset_circuit_breaker', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (class_exists('AIPS_Resilience_Service')) {
			$service = new AIPS_Resilience_Service();
			if (method_exists($service, 'reset_circuit_breaker')) {
				$service->reset_circuit_breaker();
			}
		}

		AIPS_Ajax_Response::success(array('reset' => true));
	}

	public function ajax_reschedule_missed_cron() {
		if ( ! check_ajax_referer('aips_status_reschedule_missed_cron', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$cron_events = AI_Post_Scheduler::get_cron_events();
		$rescheduled = 0;
		foreach ($cron_events as $hook => $config) {
			$schedule = isset($config['schedule']) ? $config['schedule'] : 'hourly';
			wp_unschedule_hook($hook);
			if (wp_schedule_event(time() + 60, $schedule, $hook) !== false) {
				$rescheduled++;
			}
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Flushed and rescheduled %d cron events.', 'ai-post-scheduler'), $rescheduled)));
	}

	public function ajax_retry_failed_slices() {
		if ( ! check_ajax_referer('aips_status_retry_failed_slices', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		// Immediately schedule retry hooks for both topics and posts.
		// This bypasses the 5-minute delay and processes failed slices now.
		$scheduled = 0;
		if (wp_schedule_single_event(time(), 'aips_retry_failed_author_slices_topics')) {
			$scheduled++;
		}
		if (wp_schedule_single_event(time(), 'aips_retry_failed_author_slices_posts')) {
			$scheduled++;
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Scheduled %d failed slice retry hooks.', 'ai-post-scheduler'), $scheduled)));
	}

	public function ajax_clear_partial_generations() {
		if ( ! check_ajax_referer('aips_status_clear_partial_generations', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$repo = new AIPS_History_Repository();
		$partials = $repo->get_partial_generations(array('per_page' => 10));
		$reconciled = 0;
		if (!empty($partials['items'])) {
			foreach ($partials['items'] as $item) {
				if (!empty($item->post_id)) {
					do_action('aips_post_components_updated', (int) $item->post_id, array(), array());
					$reconciled++;
				}
			}
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Reconciled %d partial generations.', 'ai-post-scheduler'), $reconciled)));
	}

	public function ajax_cleanup_stale_jobs_cache() {
		if ( ! check_ajax_referer('aips_status_cleanup_stale_jobs_cache', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$deleted = 0;
		if (class_exists('AIPS_Bulk_Batch_Job_Store')) {
			$deleted = (new AIPS_Bulk_Batch_Job_Store())->cleanup_old_jobs();
		}
		$cache_flushed = false;
		if (class_exists('AIPS_Cache_Factory')) {
			$cache = AIPS_Cache_Factory::make();
			$cache_flushed = $cache ? (bool) $cache->flush() : false;
		}
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Cleaned %1$d stale jobs. Cache flushed: %2$s.', 'ai-post-scheduler'), $deleted, $cache_flushed ? __('yes', 'ai-post-scheduler') : __('no', 'ai-post-scheduler'))));
	}
	public function ajax_rebuild_caches() {
		if ( ! check_ajax_referer('aips_rebuild_caches', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}
		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$subsystem = isset($_POST['subsystem']) ? sanitize_key(wp_unslash($_POST['subsystem'])) : 'all';
		$allowed = array('all','admin_bar','schedule_repository','article_structure_repository','prompt_section_repository','post_slices_repository');
		if (!in_array($subsystem, $allowed, true)) {
			$subsystem = 'all';
		}

		$affected = AIPS_Cache_Invalidation_Bus::rebuild($subsystem);
		AIPS_Logger::instance()->info('Cache rebuild requested from admin tool.', array('subsystem' => $subsystem, 'affected_caches' => $affected));
		AIPS_Ajax_Response::success(array('message' => sprintf(__('Rebuilt caches for %1$s. Affected caches: %2$s', 'ai-post-scheduler'), $subsystem, implode(', ', $affected)), 'subsystem' => $subsystem));
	}

}

