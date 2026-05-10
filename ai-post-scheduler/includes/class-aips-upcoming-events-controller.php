<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Upcoming_Events_Controller {
	public function __construct() {
		add_action('admin_post_aips_run_upcoming_event', array($this, 'handle_run_now'));
	}

	public function render_page() {
		$events = $this->get_events();
		include AIPS_PLUGIN_DIR . 'templates/admin/upcoming.php';
	}

	public function handle_run_now() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'ai-post-scheduler'));
		}

		check_admin_referer('aips_run_upcoming_event');

		$hook      = isset($_GET['hook']) ? sanitize_text_field(wp_unslash($_GET['hook'])) : '';
		$timestamp = isset($_GET['ts']) ? absint($_GET['ts']) : 0;
		$hash      = isset($_GET['hash']) ? sanitize_text_field(wp_unslash($_GET['hash'])) : '';

		$event = $this->find_event($hook, $timestamp, $hash);
		if (!$event) {
			$this->redirect_with_notice('error', __('Scheduled event not found.', 'ai-post-scheduler'));
		}

		wp_unschedule_event($timestamp, $hook, $event['args']);

		if (!empty($event['schedule'])) {
			$schedules = wp_get_schedules();
			if (isset($schedules[$event['schedule']]['interval'])) {
				$interval = absint($schedules[$event['schedule']]['interval']);
				$next_ts  = $timestamp + $interval;
				$now_ts   = AIPS_DateTime::now()->timestamp();
				while ($next_ts <= $now_ts) {
					$next_ts += $interval;
				}
				wp_schedule_event($next_ts, $event['schedule'], $hook, $event['args']);
			}
		}

		do_action_ref_array($hook, $event['args']);
		$this->redirect_with_notice('success', __('Event executed successfully.', 'ai-post-scheduler'));
	}

	private function redirect_with_notice($type, $message) {
		wp_safe_redirect(add_query_arg(array(
			'page'         => 'aips-upcoming',
			'aips_notice'  => $type,
			'aips_message' => rawurlencode($message),
		), admin_url('admin.php')));
		exit;
	}

	private function find_event($hook, $timestamp, $hash) {
		$events = $this->get_events();
		foreach ($events as $event) {
			if ($event['hook'] === $hook && (int) $event['timestamp'] === (int) $timestamp && $event['hash'] === $hash) {
				return $event;
			}
		}

		return null;
	}

	private function get_events() {
		$cron = _get_cron_array();
		if (!is_array($cron)) {
			return array();
		}

		$events = array();
		foreach ($cron as $timestamp => $hooks) {
			foreach ($hooks as $hook => $instances) {
				foreach ($instances as $key => $event) {
					$args = isset($event['args']) && is_array($event['args']) ? $event['args'] : array();
					$events[] = array(
						'timestamp'    => (int) $timestamp,
						'hook'         => (string) $hook,
						'args'         => $args,
						'schedule'     => isset($event['schedule']) ? (string) $event['schedule'] : '',
						'hash'         => (string) $key,
						'event_label'  => $this->format_event_label((string) $hook, $args),
						'run_time'     => $this->format_run_time((int) $timestamp),
					);
				}
			}
		}

		usort($events, function ($a, $b) {
			return $a['timestamp'] <=> $b['timestamp'];
		});

		return $events;
	}

	private function format_run_time($timestamp) {
		$now = AIPS_DateTime::now()->timestamp();
		if ($timestamp <= $now) {
			return __('now', 'ai-post-scheduler');
		}

		return sprintf(__('in %s', 'ai-post-scheduler'), human_time_diff($now, $timestamp));
	}

	private function format_event_label($hook, $args) {
		$labels = array(
			'aips_generate_scheduled_posts' => __('Template Generation Run', 'ai-post-scheduler'),
			'aips_generate_author_topics'   => __('Author Topic Generation Run', 'ai-post-scheduler'),
			'aips_generate_author_posts'    => __('Author Post Generation Run', 'ai-post-scheduler'),
			'aips_scheduled_research'       => __('Research Collection Run', 'ai-post-scheduler'),
			'aips_fetch_sources'            => __('Source Fetch Run', 'ai-post-scheduler'),
			'aips_process_schedule_batch'   => __('Schedule Batch Slice', 'ai-post-scheduler'),
			'aips_process_author_topics_slice' => __('Author Topics Slice', 'ai-post-scheduler'),
			'aips_process_author_post_slice'   => __('Author Posts Slice', 'ai-post-scheduler'),
		);

		$base = isset($labels[$hook]) ? $labels[$hook] : $hook;
		if (!empty($args)) {
			$base .= ' — ' . wp_json_encode($args);
		}
		return $base;
	}
}
