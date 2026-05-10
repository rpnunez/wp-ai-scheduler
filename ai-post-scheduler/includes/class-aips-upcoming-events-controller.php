<?php
if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Upcoming_Events_Controller {
	public function __construct() {
		add_action('wp_ajax_aips_run_upcoming_event', array($this, 'handle_run_now'));
	}

	public function render_page() {
		$filter_state    = $this->get_filter_state();
		$all_events       = $this->get_events();
		$events           = $this->apply_filters($all_events, $filter_state, AIPS_DateTime::now()->timestamp());
		$workload_cards   = $this->build_workload_cards($all_events);
		$details_event    = $this->get_details_event($all_events);
		include AIPS_PLUGIN_DIR . 'templates/admin/upcoming.php';
	}

	public function handle_run_now() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'ai-post-scheduler'));
		}

		$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
		if (!wp_verify_nonce($nonce, 'aips_run_upcoming_event')) {
			wp_die(esc_html__('Security check failed.', 'ai-post-scheduler'));
		}

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
				if ($interval > 0) {
					$next_ts = $timestamp + $interval;
					$now_ts  = AIPS_DateTime::now()->timestamp();
					while ($next_ts <= $now_ts) {
						$next_ts += $interval;
					}
					wp_schedule_event($next_ts, $event['schedule'], $hook, $event['args']);
				}
			}
		}

		wp_schedule_single_event(AIPS_DateTime::now()->timestamp(), $hook, $event['args']);

		if (function_exists('spawn_cron')) {
			spawn_cron(AIPS_DateTime::now()->timestamp());
		}

		$this->redirect_with_notice('success', __('Event has been queued to run immediately.', 'ai-post-scheduler'));
	}

	private function get_filter_state() {
		$allowed_windows = array('all', '1h', '6h', '24h', '7d');
		$allowed_category = array('all', 'generation', 'research', 'maintenance', 'other');
		$window = isset($_GET['window']) ? sanitize_key(wp_unslash($_GET['window'])) : 'all';
		$category = isset($_GET['category']) ? sanitize_key(wp_unslash($_GET['category'])) : 'all';
		$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

		return array(
			'window'   => in_array($window, $allowed_windows, true) ? $window : 'all',
			'category' => in_array($category, $allowed_category, true) ? $category : 'all',
			'search'   => $search,
		);
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

	private function get_details_event($events) {
		$hook = isset($_GET['details']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['details']))) : '';
		$ts   = isset($_GET['details_ts']) ? absint($_GET['details_ts']) : 0;
		if (empty($hook) || empty($ts)) {
			return null;
		}

		foreach ($events as $event) {
			if ($event['hook'] === $hook && (int) $event['timestamp'] === $ts) {
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
		$now_ts = AIPS_DateTime::now()->timestamp();
		foreach ($cron as $timestamp => $hooks) {
			foreach ($hooks as $hook => $instances) {
				foreach ($instances as $key => $event) {
					$args = isset($event['args']) && is_array($event['args']) ? $event['args'] : array();
					$category = $this->get_event_category((string) $hook);
					$estimate = $this->estimate_expected_output((string) $hook, $args);
					$events[] = array(
						'timestamp'        => (int) $timestamp,
						'hook'             => (string) $hook,
						'args'             => $args,
						'schedule'         => isset($event['schedule']) ? (string) $event['schedule'] : '',
						'hash'             => (string) $key,
						'event_label'      => $this->format_event_label((string) $hook, $args),
						'run_time'         => $this->format_run_time((int) $timestamp),
						'run_time_absolute'=> $this->format_absolute_time((int) $timestamp),
						'category'         => $category,
						'estimate'         => $estimate,
						'recurrence_label' => $this->format_recurrence(isset($event['schedule']) ? (string) $event['schedule'] : ''),
					);
				}
			}
		}

		usort($events, function ($a, $b) {
			return $a['timestamp'] <=> $b['timestamp'];
		});

		return $events;
	}

	private function apply_filters($events, $filter_state, $now_ts) {
		if (empty($filter_state)) {
			return $events;
		}

		$window_seconds_map = array(
			'1h'  => HOUR_IN_SECONDS,
			'6h'  => 6 * HOUR_IN_SECONDS,
			'24h' => DAY_IN_SECONDS,
			'7d'  => 7 * DAY_IN_SECONDS,
		);

		return array_values(array_filter($events, function ($event) use ($filter_state, $window_seconds_map, $now_ts) {
			if (!empty($filter_state['category']) && 'all' !== $filter_state['category'] && $event['category'] !== $filter_state['category']) {
				return false;
			}

			if (!empty($filter_state['window']) && 'all' !== $filter_state['window'] && isset($window_seconds_map[$filter_state['window']])) {
				if ((int) $event['timestamp'] > ($now_ts + $window_seconds_map[$filter_state['window']])) {
					return false;
				}
			}

			if (!empty($filter_state['search'])) {
				$search = mb_strtolower($filter_state['search']);
				$haystack = mb_strtolower($event['event_label'] . ' ' . $event['hook']);
				if (false === strpos($haystack, $search)) {
					return false;
				}
			}

			return true;
		}));
	}


	private function build_workload_cards($events) {
		$now_ts = AIPS_DateTime::now()->timestamp();
		$end_ts = $now_ts + DAY_IN_SECONDS;
		$posts_24h = 0;
		$topics_24h = 0;
		$largest = array('count' => 0, 'label' => __('None', 'ai-post-scheduler'));

		foreach ($events as $event) {
			if ((int) $event['timestamp'] > $end_ts) {
				continue;
			}

			$count = isset($event['estimate']['count']) ? (int) $event['estimate']['count'] : 0;
			if ('aips_generate_author_topics' === $event['hook']) {
				$topics_24h += $count;
			}
			if ('aips_generate_author_posts' === $event['hook']) {
				$posts_24h += $count;
			}

			if ($count > $largest['count']) {
				$largest = array(
					'count' => $count,
					'label' => $event['event_label'],
				);
			}
		}

		return array(
			'next_24h_posts' => $posts_24h,
			'next_24h_topics' => $topics_24h,
			'largest_run' => $largest,
		);
	}

	private function format_run_time($timestamp) {
		$now = AIPS_DateTime::now()->timestamp();
		if ($timestamp <= $now) {
			return __('now', 'ai-post-scheduler');
		}

		return sprintf(__('in %s', 'ai-post-scheduler'), human_time_diff($now, $timestamp));
	}

	private function format_absolute_time($timestamp) {
		return AIPS_DateTime::fromTimestamp($timestamp)->toDisplay(get_option('date_format') . ' ' . get_option('time_format'));
	}

	private function format_recurrence($schedule_key) {
		if (empty($schedule_key)) {
			return __('Single event', 'ai-post-scheduler');
		}
		$schedules = wp_get_schedules();
		if (!isset($schedules[$schedule_key])) {
			return $schedule_key;
		}

		$display = !empty($schedules[$schedule_key]['display']) ? $schedules[$schedule_key]['display'] : $schedule_key;
		$interval = !empty($schedules[$schedule_key]['interval']) ? absint($schedules[$schedule_key]['interval']) : 0;
		if ($interval > 0) {
			return sprintf('%s (%s)', $display, human_time_diff(0, $interval));
		}
		return $display;
	}

	private function get_event_category($hook) {
		if (in_array($hook, array('aips_generate_scheduled_posts', 'aips_generate_author_topics', 'aips_generate_author_posts', 'aips_process_schedule_batch', 'aips_process_author_topics_slice', 'aips_process_author_post_slice'), true)) {
			return 'generation';
		}
		if (in_array($hook, array('aips_scheduled_research', 'aips_fetch_sources'), true)) {
			return 'research';
		}
		if (in_array($hook, array('aips_cleanup_export_files', 'aips_cleanup_bulk_batch_jobs', 'aips_notification_rollups'), true)) {
			return 'maintenance';
		}
		return 'other';
	}

	private function estimate_expected_output($hook, $args) {
		$estimate = array('value' => '~1', 'count' => 1, 'type' => 'operation', 'details' => '');
		switch ($hook) {
			case 'aips_generate_scheduled_posts':
				$repo = new AIPS_Schedule_Repository();
				$due = $repo->get_due_schedules(AIPS_DateTime::now()->timestamp(), 1000);
				$estimate = array('value' => '~' . count($due), 'count' => count($due), 'type' => __('template runs', 'ai-post-scheduler'), 'details' => __('Due active schedules', 'ai-post-scheduler'));
				break;
			case 'aips_generate_author_topics':
				$authors_repo = new AIPS_Authors_Repository();
				$authors = $authors_repo->get_all(false);
				$estimate = array('value' => '~' . count($authors), 'count' => count($authors), 'type' => __('authors scanned', 'ai-post-scheduler'), 'details' => __('Potential topic generation checks', 'ai-post-scheduler'));
				break;
			case 'aips_generate_author_posts':
				$topics_repo = new AIPS_Author_Topics_Repository();
				$pending = 0;
				foreach ((new AIPS_Authors_Repository())->get_all(false) as $author) {
					$counts = $topics_repo->get_status_counts((int) $author->id);
					$pending += isset($counts['approved']) ? (int) $counts['approved'] : 0;
				}
				$estimate = array('value' => '~' . $pending, 'count' => $pending, 'type' => __('post candidates', 'ai-post-scheduler'), 'details' => __('Approved topics pending generation', 'ai-post-scheduler'));
				break;
			case 'aips_process_schedule_batch':
			case 'aips_process_author_topics_slice':
			case 'aips_process_author_post_slice':
			case 'aips_process_bulk_batch':
				$estimate = array('value' => '1', 'count' => 1, 'type' => __('slice', 'ai-post-scheduler'), 'details' => __('Single queue slice execution', 'ai-post-scheduler'));
				break;
		}

		return $estimate;
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
