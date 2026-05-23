<?php
/**
 * Admin campaign flow controller.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Admin_Flow_Controller {

	const PAGE_SLUG = 'aips-campaign-wizard';

	/**
	 * @var string
	 */
	private $draft_option = 'aips_campaign_wizard_draft';

	/**
	 * @var AIPS_Template_Repository
	 */
	private $template_repository;

	/**
	 * @var AIPS_Unified_Schedule_Service
	 */
	private $unified_schedule_service;

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function __construct(
		?AIPS_Template_Repository $template_repository = null,
		?AIPS_Unified_Schedule_Service $unified_schedule_service = null,
		?AIPS_Config $config = null
	) {
		$this->template_repository      = $template_repository ?: AIPS_Template_Repository::instance();
		$this->unified_schedule_service = $unified_schedule_service ?: new AIPS_Unified_Schedule_Service();
		$this->config                   = $config ?: AIPS_Config::get_instance();

		add_action('wp_ajax_aips_campaign_wizard_save_draft', array($this, 'ajax_save_draft'));
		add_action('wp_ajax_aips_campaign_wizard_validate_step', array($this, 'ajax_validate_step'));
		add_action('wp_ajax_aips_campaign_wizard_finalize', array($this, 'ajax_finalize_campaign'));
	}

	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$draft           = $this->get_draft();
		$templates       = $this->template_repository->get_all(true);
		$categories      = get_categories(array('hide_empty' => false));
		$post_types      = $this->get_supported_post_types();
		$voices          = class_exists('AIPS_Voices_Repository') ? (new AIPS_Voices_Repository())->get_all() : array();
		$structures      = class_exists('AIPS_Article_Structure_Repository') ? (new AIPS_Article_Structure_Repository())->get_all(true) : array();
		$authors         = class_exists('AIPS_Authors_Repository') ? AIPS_Authors_Repository::instance()->get_all(true) : array();
		$frequencies     = (new AIPS_Interval_Calculator())->get_intervals();
		$aips_config     = $this->config;
		$default_summary = $this->build_summary($this->normalise_payload($draft));

		include AIPS_PLUGIN_DIR . 'templates/admin/campaign-wizard.php';
	}

	public function ajax_save_draft() {
		$this->ajax_guard();

		$payload = $this->get_request_payload();
		$draft   = $this->normalise_payload(array_merge($this->get_draft(), $payload));
		$step    = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
		$errors  = $this->validate_step($step, $draft);

		if (!empty($errors)) {
			AIPS_Ajax_Response::error(
				__('Please fix the highlighted fields before continuing.', 'ai-post-scheduler'),
				'validation_failed',
				400,
				array('errors' => $errors)
			);
		}

		update_option($this->get_draft_option_name(), $draft, false);

		AIPS_Ajax_Response::success(array(
			'draft'   => $draft,
			'summary' => $this->build_summary($draft),
		), __('Draft saved.', 'ai-post-scheduler'));
	}

	public function ajax_validate_step() {
		$this->ajax_guard();

		$payload = $this->normalise_payload(array_merge($this->get_draft(), $this->get_request_payload()));
		$step    = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
		$errors  = $this->validate_step($step, $payload);

		if (!empty($errors)) {
			AIPS_Ajax_Response::error(
				__('Step validation failed.', 'ai-post-scheduler'),
				'validation_failed',
				400,
				array('errors' => $errors)
			);
		}

		AIPS_Ajax_Response::success(array(
			'summary' => $this->build_summary($payload),
		), __('Step is valid.', 'ai-post-scheduler'));
	}

	public function ajax_finalize_campaign() {
		$this->ajax_guard();

		$payload = $this->normalise_payload(array_merge($this->get_draft(), $this->get_request_payload()));
		$errors  = $this->validate_payload($payload);

		if (!empty($errors)) {
			AIPS_Ajax_Response::error(
				__('Campaign validation failed.', 'ai-post-scheduler'),
				'validation_failed',
				400,
				array('errors' => $errors)
			);
		}

		global $wpdb;

		$template_id = 0;
		$schedule_id = 0;
		$started_transaction = $this->start_transaction();

		try {
			$template_id = $this->persist_template($payload);

			if (!$template_id) {
				throw new RuntimeException(__('Template could not be saved.', 'ai-post-scheduler'));
			}

			$scheduler = new AIPS_Scheduler();
			$schedule_id = $scheduler->save_schedule(array(
				'template_id'           => $template_id,
				'title'                 => $payload['campaign_name'],
				'frequency'             => $payload['frequency'],
				'start_time'            => $payload['start_time'],
				'is_active'             => $payload['is_active'],
				'topic'                 => $payload['content_goal'],
				'article_structure_id'  => $payload['article_structure_id'],
				'rotation_pattern'      => $payload['rotation_pattern'],
				'author_id'             => $payload['author_id'],
				'campaign_mode'         => $payload['campaign_mode'],
				'post_type_rules'       => $payload['post_type_rules'],
				'blackout_dates'        => $payload['blackout_dates'],
				'time_window_start'     => $payload['time_window_start'],
				'time_window_end'       => $payload['time_window_end'],
				'day_preferences'       => $payload['day_preferences'],
				'season_end_date'       => $this->normalise_season_end_date($payload['season_end_date']),
			));

			if (!$schedule_id) {
				throw new RuntimeException(__('Schedule could not be created.', 'ai-post-scheduler'));
			}

			if ($started_transaction) {
				$wpdb->query('COMMIT');
			}
		} catch (Throwable $e) {
			if ($started_transaction) {
				$wpdb->query('ROLLBACK');
			}

			AIPS_Ajax_Response::error($e->getMessage(), 'finalize_failed', 500);
		}

		delete_option($this->get_draft_option_name());

		$schedule = $this->find_unified_schedule($schedule_id);

		AIPS_Ajax_Response::success(array(
			'template_id'  => $template_id,
			'schedule_id'  => $schedule_id,
			'schedule'     => $schedule,
			'summary'      => $this->build_summary($payload),
			'redirect_url' => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
		), __('Campaign created and scheduled.', 'ai-post-scheduler'));
	}

	private function ajax_guard() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	private function get_request_payload() {
		$payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : array();
		if (is_string($payload)) {
			$decoded = json_decode($payload, true);
			$payload = is_array($decoded) ? $decoded : array();
		}

		return is_array($payload) ? $payload : array();
	}

	private function get_draft() {
		$draft = get_option($this->get_draft_option_name(), array());
		return is_array($draft) ? $draft : array();
	}

	private function get_draft_option_name() {
		return $this->draft_option . '_' . get_current_user_id();
	}

	private function normalise_payload($payload) {
		$payload = is_array($payload) ? $payload : array();
		$default_status = $this->config->get_option('aips_default_post_status');

		$review_policy = isset($payload['review_policy']) ? sanitize_key($payload['review_policy']) : 'draft';
		$post_status = $this->post_status_for_review_policy($review_policy, $default_status);

		return array(
			'campaign_name'          => isset($payload['campaign_name']) ? sanitize_text_field($payload['campaign_name']) : '',
			'content_goal'           => isset($payload['content_goal']) ? sanitize_textarea_field($payload['content_goal']) : '',
			'post_type'              => $this->normalise_post_type($payload['post_type'] ?? 'post'),
			'template_mode'          => isset($payload['template_mode']) && 'existing' === $payload['template_mode'] ? 'existing' : 'custom',
			'template_id'            => isset($payload['template_id']) ? absint($payload['template_id']) : 0,
			'prompt_template'        => isset($payload['prompt_template']) ? wp_kses_post($payload['prompt_template']) : '',
			'title_prompt'           => isset($payload['title_prompt']) ? sanitize_text_field($payload['title_prompt']) : '',
			'voice_id'               => isset($payload['voice_id']) ? absint($payload['voice_id']) : 0,
			'article_structure_id'   => isset($payload['article_structure_id']) ? absint($payload['article_structure_id']) : absint($this->config->get_option('aips_default_article_structure_id')),
			'rotation_pattern'       => isset($payload['rotation_pattern']) ? sanitize_key($payload['rotation_pattern']) : 'sequential',
			'author_id'              => isset($payload['author_id']) ? absint($payload['author_id']) : 0,
			'campaign_mode'          => isset($payload['campaign_mode']) ? sanitize_key($payload['campaign_mode']) : 'template',
			'post_type_rules'        => $this->normalise_post_type_rules($payload),
			'post_category'          => isset($payload['post_category']) ? absint($payload['post_category']) : absint($this->config->get_option('aips_default_category')),
			'post_tags'              => isset($payload['post_tags']) ? sanitize_text_field($payload['post_tags']) : '',
			'post_author'            => isset($payload['post_author']) ? absint($payload['post_author']) : absint($this->config->get_option('aips_default_post_author')),
			'frequency'              => isset($payload['frequency']) ? sanitize_key($payload['frequency']) : 'daily',
			'start_time'             => isset($payload['start_time']) ? sanitize_text_field($payload['start_time']) : AIPS_DateTime::now()->toDisplay('Y-m-d\TH:i'),
			'is_active'              => isset($payload['is_active']) ? absint($payload['is_active']) : 1,
			'time_window_start'      => isset($payload['time_window_start']) ? sanitize_text_field($payload['time_window_start']) : '',
			'time_window_end'        => isset($payload['time_window_end']) ? sanitize_text_field($payload['time_window_end']) : '',
			'day_preferences'        => $this->normalise_day_preferences($payload),
			'blackout_dates'         => isset($payload['blackout_dates']) ? sanitize_textarea_field($payload['blackout_dates']) : '',
			'season_end_date'        => isset($payload['season_end_date']) ? sanitize_text_field($payload['season_end_date']) : '',
			'review_policy'          => $review_policy,
			'post_status'            => $post_status,
		);
	}

	private function validate_payload($payload) {
		$errors = array();
		foreach (array('goal', 'template', 'defaults', 'schedule', 'review') as $step) {
			$errors = array_merge($errors, $this->validate_step($step, $payload));
		}
		return $errors;
	}

	private function validate_step($step, $payload) {
		$errors = array();

		if ($step === '' || 'goal' === $step) {
			if ($payload['campaign_name'] === '') {
				$errors['campaign_name'] = __('Campaign name is required.', 'ai-post-scheduler');
			}
			if ($payload['content_goal'] === '') {
				$errors['content_goal'] = __('Content goal is required.', 'ai-post-scheduler');
			}
			if (!$this->post_type_exists($payload['post_type'])) {
				$errors['post_type'] = __('Select a valid public post type.', 'ai-post-scheduler');
			}
		}

		if ($step === '' || 'template' === $step) {
			if ('existing' === $payload['template_mode']) {
				if (!$payload['template_id'] || !$this->template_repository->get_by_id($payload['template_id'])) {
					$errors['template_id'] = __('Select a valid template.', 'ai-post-scheduler');
				}
			} elseif ($payload['prompt_template'] === '') {
				$errors['prompt_template'] = __('Prompt template is required.', 'ai-post-scheduler');
			}
		}

		if ($step === '' || 'defaults' === $step) {
			if ($payload['post_author'] && !get_user_by('id', $payload['post_author'])) {
				$errors['post_author'] = __('Select a valid post author.', 'ai-post-scheduler');
			}
		}

		if ($step === '' || 'schedule' === $step) {
			$interval_calculator = new AIPS_Interval_Calculator();
			if (!$interval_calculator->is_valid_frequency($payload['frequency'])) {
				$errors['frequency'] = __('Select a valid cadence.', 'ai-post-scheduler');
			}
			if (strtotime($payload['start_time']) === false) {
				$errors['start_time'] = __('Select a valid start date and time.', 'ai-post-scheduler');
			}
		}

		if ($step === '' || 'review' === $step) {
			if (!in_array($payload['review_policy'], array('auto_publish', 'draft', 'approval'), true)) {
				$errors['review_policy'] = __('Select a valid review policy.', 'ai-post-scheduler');
			}
		}

		return $errors;
	}

	private function persist_template($payload) {
		$template_data = array(
			'name'                 => $payload['campaign_name'],
			'prompt_template'      => $this->resolve_prompt_template($payload),
			'title_prompt'         => $payload['title_prompt'],
			'voice_id'             => $payload['voice_id'],
			'post_quantity'        => 1,
			'post_status'          => $payload['post_status'],
			'post_type'            => $payload['post_type'],
			'post_category'        => $payload['post_category'],
			'post_tags'            => $payload['post_tags'],
			'post_author'          => $payload['post_author'],
			'is_active'            => 1,
		);

		return $this->template_repository->create($template_data);
	}

	private function resolve_prompt_template($payload) {
		if ('existing' === $payload['template_mode']) {
			$template = $this->template_repository->get_by_id($payload['template_id']);
			if ($template && !empty($payload['prompt_template'])) {
				return $payload['prompt_template'];
			}
			return $template ? $template->prompt_template : '';
		}

		return $payload['prompt_template'];
	}

	private function build_summary($payload) {
		$template_label = __('New custom template', 'ai-post-scheduler');
		if (!empty($payload['template_id'])) {
			$template = $this->template_repository->get_by_id($payload['template_id']);
			if ($template) {
				$template_label = $template->name;
			}
		}

		$post_type_obj = get_post_type_object($payload['post_type']);

		return array(
			'campaign_name' => $payload['campaign_name'],
			'content_goal'  => $payload['content_goal'],
			'post_type'     => $post_type_obj ? $post_type_obj->labels->singular_name : $payload['post_type'],
			'template'      => $template_label,
			'frequency'     => $payload['frequency'],
			'start_time'    => $payload['start_time'],
			'review_policy' => $payload['review_policy'],
			'post_status'   => $payload['post_status'],
		);
	}

	private function start_transaction() {
		global $wpdb;

		$result = $wpdb->query('START TRANSACTION');
		return $result !== false;
	}

	private function find_unified_schedule($schedule_id) {
		$schedules = $this->unified_schedule_service->get_all(AIPS_Unified_Schedule_Service::TYPE_TEMPLATE, false);
		foreach ($schedules as $schedule) {
			if (isset($schedule['id']) && (int) $schedule['id'] === (int) $schedule_id) {
				return $schedule;
			}
		}
		return null;
	}

	private function post_status_for_review_policy($review_policy, $default_status) {
		if ('auto_publish' === $review_policy) {
			return 'publish';
		}
		if ('approval' === $review_policy) {
			return 'pending';
		}
		return $default_status ?: 'draft';
	}

	private function get_supported_post_types() {
		$post_types = get_post_types(array('public' => true), 'objects');
		unset($post_types['attachment']);
		return $post_types;
	}

	private function normalise_post_type($post_type) {
		$post_type = sanitize_key((string) $post_type);
		return $this->post_type_exists($post_type) ? $post_type : 'post';
	}

	private function normalise_post_type_rules($payload) {
		if (!isset($payload['post_type_rules']) || !is_array($payload['post_type_rules'])) {
			return '';
		}

		$rules = array();
		foreach ($payload['post_type_rules'] as $rule) {
			if (!is_array($rule)) {
				continue;
			}

			$post_type = isset($rule['post_type']) ? sanitize_key($rule['post_type']) : '';
			if (!$this->post_type_exists($post_type)) {
				continue;
			}

			$rules[] = array(
				'post_type'       => $post_type,
				'quantity'        => isset($rule['quantity']) ? absint($rule['quantity']) : 1,
				'prompt_override' => isset($rule['prompt_override']) ? sanitize_text_field($rule['prompt_override']) : '',
			);
		}

		return !empty($rules) ? wp_json_encode($rules) : '';
	}

	private function normalise_day_preferences($payload) {
		if (!isset($payload['day_preferences']) || !is_array($payload['day_preferences'])) {
			return '';
		}

		$valid_days = array();
		foreach ($payload['day_preferences'] as $day) {
			$day = absint($day);
			if ($day >= 1 && $day <= 7) {
				$valid_days[] = $day;
			}
		}

		return !empty($valid_days) ? implode(',', $valid_days) : '';
	}

	private function normalise_season_end_date($date_string) {
		if (empty($date_string)) {
			return 0;
		}

		$timestamp = strtotime($date_string);
		return $timestamp !== false ? $timestamp : 0;
	}

	private function post_type_exists($post_type) {
		$post_type_obj = get_post_type_object($post_type);
		return $post_type_obj && !empty($post_type_obj->public) && 'attachment' !== $post_type;
	}
}
