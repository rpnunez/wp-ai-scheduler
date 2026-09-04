<?php
/**
 * REST controller for schedules.
 *
 * Routes:
 *   GET    /aips/v1/schedules                       List schedules (?active_only=)
 *   GET    /aips/v1/schedules/status                Live status read model (dashboard strip)
 *   GET    /aips/v1/schedules/post-count            Bulk post count for ?ids[]=...
 *   GET    /aips/v1/schedules/{id}                  Fetch one schedule
 *   POST   /aips/v1/schedules                       Create a schedule
 *   PUT    /aips/v1/schedules/{id}                  Update a schedule
 *   PATCH  /aips/v1/schedules/{id}                  Partial update (is_active toggle)
 *   DELETE /aips/v1/schedules/{id}                  Delete a schedule
 *   POST   /aips/v1/schedules/bulk-toggle           { ids: [], is_active: bool }
 *   POST   /aips/v1/schedules/bulk-delete           { ids: [] }
 *   GET    /aips/v1/schedules/{id}/history          Activity/error history for one schedule
 *
 * Long-running actions (aips_run_now, aips_bulk_run_now_schedules,
 * aips_resume_schedule_batch, aips_reset_schedule_circuit, and every
 * aips_unified_*_run_now variant) stay on admin-ajax per the migration keep-list.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Schedules_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Scheduler */
	private $scheduler;

	/** @var AIPS_Schedule_Repository */
	private $schedule_repository;

	/** @var AIPS_History_Repository */
	private $history_repository;

	protected $rest_base = 'schedules';

	public function __construct() {
		parent::__construct();
		$this->scheduler           = new AIPS_Scheduler();
		$this->schedule_repository = new AIPS_Schedule_Repository();
		$this->history_repository  = new AIPS_History_Repository();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'active_only' => array('type' => 'boolean', 'default' => false),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->schedule_args(true),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_status_read_model'),
			'permission_callback' => array($this, 'permission_check'),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/post-count', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_post_count'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => array(
				'ids' => array(
					'type'              => 'array',
					'default'           => array(),
					'items'             => array('type' => 'integer', 'minimum' => 1),
					'sanitize_callback' => function ($v) {
						return array_values(array_filter(array_map('absint', (array) $v)));
					},
				),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk-toggle', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'bulk_toggle'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => array_merge($this->ids_arg(), array(
				'is_active' => array('type' => 'boolean', 'required' => true),
			)),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/bulk-delete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'bulk_delete'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->ids_arg(),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->schedule_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/history', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_history'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->id_arg(),
		));
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	public function get_items($request) {
		return $this->respond(array(
			'schedules' => $this->schedule_repository->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$schedule = $this->schedule_repository->get_by_id((int) $request['id']);
		if (!$schedule) {
			return $this->error_not_found(__('Schedule', 'ai-post-scheduler'));
		}
		return $this->respond(array('schedule' => $schedule));
	}

	public function create_item($request) {
		$data = $this->collect_input($request, 0);

		if (empty($data['template_id'])) {
			return $this->error_invalid_request(__('Please select a template.', 'ai-post-scheduler'));
		}
		if (!(new AIPS_Interval_Calculator())->is_valid_frequency($data['frequency'])) {
			return $this->error_invalid_request(__('Invalid frequency selected.', 'ai-post-scheduler'));
		}

		$id = $this->scheduler->save_schedule($data);
		if (!$id) {
			return $this->error_server(__('Failed to save schedule.', 'ai-post-scheduler'));
		}
		return $this->respond_created(array(
			'schedule_id' => (int) $id,
			'schedule'    => $this->schedule_repository->get_by_id($id),
			'message'     => __('Schedule saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->schedule_repository->get_by_id($id)) {
			return $this->error_not_found(__('Schedule', 'ai-post-scheduler'));
		}

		// PATCH: partial is_active toggle only.
		if ('PATCH' === $request->get_method()) {
			$is_active = $request->get_param('is_active');
			if (null === $is_active) {
				return $this->error_invalid_request(__('No fields to update.', 'ai-post-scheduler'));
			}
			$result = $this->scheduler->toggle_active($id, $is_active ? 1 : 0);
			if (false === $result) {
				return $this->error_server(__('Failed to update schedule.', 'ai-post-scheduler'));
			}
			return $this->respond(array(
				'schedule_id' => $id,
				'schedule'    => $this->schedule_repository->get_by_id($id),
				'message'     => __('Schedule updated.', 'ai-post-scheduler'),
			));
		}

		$data = $this->collect_input($request, $id);

		if (empty($data['template_id'])) {
			return $this->error_invalid_request(__('Please select a template.', 'ai-post-scheduler'));
		}
		if (!(new AIPS_Interval_Calculator())->is_valid_frequency($data['frequency'])) {
			return $this->error_invalid_request(__('Invalid frequency selected.', 'ai-post-scheduler'));
		}

		if (!$this->scheduler->save_schedule($data)) {
			return $this->error_server(__('Failed to save schedule.', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'schedule_id' => $id,
			'schedule'    => $this->schedule_repository->get_by_id($id),
			'message'     => __('Schedule saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id       = (int) $request['id'];
		$schedule = $this->schedule_repository->get_by_id($id);
		if (!$schedule) {
			return $this->error_not_found(__('Schedule', 'ai-post-scheduler'));
		}
		if (!empty($schedule->campaign_id)) {
			return $this->error_conflict(__('This schedule cannot be deleted here because it belongs to a campaign. Delete it from the Campaigns page.', 'ai-post-scheduler'));
		}
		if (!$this->schedule_repository->delete($id)) {
			return $this->error_server(__('Failed to delete schedule.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Schedule deleted successfully.', 'ai-post-scheduler')));
	}

	// -------------------------------------------------------------------------
	// Bulk / auxiliary
	// -------------------------------------------------------------------------

	public function bulk_toggle($request) {
		$ids       = (array) $request->get_param('ids');
		$is_active = $request->get_param('is_active') ? 1 : 0;

		$updated = $this->schedule_repository->set_active_bulk($ids, $is_active);
		if (false === $updated) {
			return $this->error_server(__('Failed to update schedules.', 'ai-post-scheduler'));
		}
		$count = (int) $updated ?: count($ids);
		$label = $is_active ? __('activated', 'ai-post-scheduler') : __('paused', 'ai-post-scheduler');
		return $this->respond(array(
			'updated'   => (int) $updated,
			'is_active' => $is_active,
			'message'   => sprintf(
				/* translators: 1: number of schedules, 2: action label (activated/paused) */
				_n('%1$d schedule %2$s successfully.', '%1$d schedules %2$s successfully.', $count, 'ai-post-scheduler'),
				$count,
				$label
			),
		));
	}

	public function bulk_delete($request) {
		$ids = (array) $request->get_param('ids');

		$campaign_owned = $this->schedule_repository->get_campaign_owned_ids($ids);
		if (!empty($campaign_owned)) {
			return $this->error_conflict(__('One or more selected schedules belong to a campaign and cannot be deleted here.', 'ai-post-scheduler'));
		}

		$deleted = $this->schedule_repository->delete_bulk($ids);
		if (false === $deleted) {
			return $this->error_server(__('Failed to delete schedules.', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'deleted' => (int) $deleted,
			'message' => sprintf(
				_n('%d schedule deleted successfully.', '%d schedules deleted successfully.', $deleted, 'ai-post-scheduler'),
				$deleted
			),
		));
	}

	public function get_post_count($request) {
		$ids = (array) $request->get_param('ids');
		if (empty($ids)) {
			return $this->respond(array('count' => 0));
		}
		return $this->respond(array('count' => $this->schedule_repository->get_post_count_for_schedules($ids)));
	}

	public function get_history($request) {
		$id       = (int) $request['id'];
		$schedule = $this->schedule_repository->get_by_id($id);
		if (!$schedule) {
			return $this->error_not_found(__('Schedule', 'ai-post-scheduler'));
		}
		if (empty($schedule->schedule_history_id)) {
			return $this->respond(array('entries' => array()));
		}
		$logs = $this->history_repository->get_logs_by_history_id(
			absint($schedule->schedule_history_id),
			array(AIPS_History_Type::ACTIVITY, AIPS_History_Type::ERROR)
		);
		return $this->respond(array('entries' => AIPS_History_Event_View::from_logs($logs)));
	}

	// -------------------------------------------------------------------------
	// Status read model
	// -------------------------------------------------------------------------

	public function get_status_read_model() {
		$cache     = AIPS_Cache_Factory::make();
		$cache_key = 'aips_schedule_status_strip_v2';
		$cached    = $cache->get($cache_key);
		if (is_array($cached)) {
			return $this->respond($cached);
		}

		$families = array(
			AIPS_Unified_Schedule_Service::TYPE_TEMPLATE     => 'aips_generate_scheduled_posts',
			AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC => 'aips_generate_author_topics',
			AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST  => 'aips_generate_author_posts',
		);

		$next_runs = array();
		foreach ($families as $family => $hook) {
			$next_runs[$family] = wp_next_scheduled($hook) ?: null;
		}

		$queue_hooks = array(
			'aips_process_schedule_batch',
			'aips_process_author_topics_slice',
			'aips_retry_failed_author_slices_topics',
			'aips_process_author_post_slice',
			'aips_retry_failed_author_slices_posts',
			'aips_process_bulk_batch',
			'aips_process_author_embeddings',
			'aips_index_posts_batch',
		);
		$queue_depth    = array_fill_keys($queue_hooks, 0);
		$queue_timeline = array();
		$now            = time();
		$next_24h       = $now + DAY_IN_SECONDS;
		$cron           = _get_cron_array();
		if (is_array($cron)) {
			foreach ($cron as $timestamp => $hooks) {
				if ((int) $timestamp > $next_24h) {
					continue;
				}
				foreach ($queue_hooks as $hook) {
					if (!isset($hooks[$hook])) {
						continue;
					}
					$count = is_array($hooks[$hook]) ? count($hooks[$hook]) : 0;
					$queue_depth[$hook] += $count;
					$queue_timeline[] = array('hook' => $hook, 'timestamp' => (int) $timestamp, 'count' => $count);
				}
			}
		}

		$unified_service   = new AIPS_Unified_Schedule_Service();
		$all_schedules     = $unified_service->get_all('', false);
		$timeline          = array();
		$active_schedules  = 0;
		$overdue_schedules = 0;
		foreach ($all_schedules as $schedule) {
			$is_active = !empty($schedule['is_active']);
			if ($is_active) {
				$active_schedules++;
			}
			$next_run = isset($schedule['next_run']) ? (int) $schedule['next_run'] : 0;
			if (!$is_active || $next_run <= 0) {
				continue;
			}
			if ($next_run < $now) {
				$overdue_schedules++;
				continue;
			}
			if ($next_run > $next_24h) {
				continue;
			}
			$timeline[] = array(
				'id'        => isset($schedule['id']) ? (int) $schedule['id'] : 0,
				'type'      => isset($schedule['type']) ? (string) $schedule['type'] : '',
				'title'     => isset($schedule['title']) ? sanitize_text_field((string) $schedule['title']) : '',
				'cron_hook' => isset($schedule['cron_hook']) ? (string) $schedule['cron_hook'] : '',
				'timestamp' => $next_run,
			);
		}
		usort($timeline, function ($a, $b) {
			return (int) $a['timestamp'] - (int) $b['timestamp'];
		});

		$bulk_job_store = new AIPS_Bulk_Batch_Job_Store();
		$bulk_counts    = $bulk_job_store->get_status_counts(array('pending', 'processing', 'failed'));

		$last_success = array();
		foreach ($families as $family => $hook) {
			$runs = $this->history_repository->get_history(array(
				'creation_method' => $family,
				'status'          => 'completed',
				'per_page'        => 1,
			));
			$last_success[$family] = !empty($runs[0]->completed_at) ? (int) $runs[0]->completed_at : null;
		}

		$rate_limiter_status = array('enabled' => false, 'remaining' => 0, 'max_requests' => 0);
		if (class_exists('AIPS_Resilience_Service')) {
			$resilience_service = new AIPS_Resilience_Service();
			if (method_exists($resilience_service, 'get_rate_limiter_status')) {
				$rate_limiter_status = $resilience_service->get_rate_limiter_status();
			}
		}

		$payload = array(
			'next_runs'      => $next_runs,
			'timeline'       => $timeline,
			'queue_timeline' => $queue_timeline,
			'queue_depth'    => $queue_depth,
			'bulk_jobs'      => $bulk_counts,
			'schedule_counts' => array(
				'active'       => $active_schedules,
				'upcoming_24h' => count($timeline),
				'overdue'      => $overdue_schedules,
			),
			'last_success'  => $last_success,
			'retry_pending' => ($queue_depth['aips_retry_failed_author_slices_topics'] + $queue_depth['aips_retry_failed_author_slices_posts']) > 0,
			'last_error'    => $bulk_counts['failed'] > 0,
			'rate_limiter'  => $rate_limiter_status,
			'quick_links'   => array(
				'history'       => AIPS_Admin_Menu_Helper::get_page_url('history'),
				'notifications' => AIPS_Admin_Menu_Helper::get_page_url('settings', array('tab' => 'notifications')),
				'telemetry'     => AIPS_Admin_Menu_Helper::get_page_url('telemetry'),
				'system_status' => AIPS_Admin_Menu_Helper::get_page_url('system-status'),
			),
		);

		$cache->set($cache_key, $payload, 60);
		return $this->respond($payload);
	}

	// -------------------------------------------------------------------------
	// Input
	// -------------------------------------------------------------------------

	private function collect_input($request, $existing_id) {
		return array(
			'id'                    => (int) $existing_id,
			'template_id'           => absint($request->get_param('template_id')),
			'title'                 => (string) $request->get_param('schedule_title'),
			'frequency'             => (string) ($request->get_param('frequency') ?: 'daily'),
			'start_time'            => $request->get_param('start_time'),
			'is_active'             => $request->get_param('is_active') ? 1 : 0,
			'topic'                 => (string) $request->get_param('topic'),
			'article_structure_id'  => $request->get_param('article_structure_id') ? absint($request->get_param('article_structure_id')) : null,
			'rotation_pattern'      => $request->get_param('rotation_pattern') ? sanitize_text_field((string) $request->get_param('rotation_pattern')) : null,
		);
	}

	/**
	 * Declarative arg schema for the schedule create/update request bodies.
	 *
	 * @param bool $required Whether required fields (template_id, frequency)
	 *                       must be present in the payload (true on POST, false on PUT/PATCH).
	 * @return array<string, array<string, mixed>>
	 */
	private function schedule_args($required) {
		return array(
			'template_id' => array(
				'type'              => 'integer',
				'required'          => (bool) $required,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'schedule_title' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'frequency' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'default'           => 'daily',
				'sanitize_callback' => 'sanitize_text_field',
				// Runtime validation via AIPS_Interval_Calculator::is_valid_frequency()
				// in create_item()/update_item() so add-on-registered frequencies work.
			),
			'start_time' => array(
				'type'              => array('string', 'null'),
				'default'           => null,
				'sanitize_callback' => function ($v) {
					return null === $v ? null : sanitize_text_field((string) $v);
				},
			),
			'is_active' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'topic' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'article_structure_id' => array(
				'type'    => array('integer', 'null'),
				'default' => null,
				'minimum' => 1,
			),
			'rotation_pattern' => array(
				'type'              => array('string', 'null'),
				'default'           => null,
				'sanitize_callback' => function ($v) {
					return null === $v || '' === $v ? null : sanitize_text_field((string) $v);
				},
			),
		);
	}
}
