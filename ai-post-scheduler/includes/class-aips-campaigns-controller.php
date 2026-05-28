<?php
/**
 * Campaigns Controller
 *
 * Canonical controller for campaign page management and campaign wizard flow.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Campaigns_Controller {

	const PAGE_SLUG = 'aips-campaign-wizard';

	/**
	 * Campaign detail page slug.
	 */
	const DETAIL_PAGE_SLUG = 'aips-campaign-detail';

	/**
	 * @var string
	 */
	private $draft_option = 'aips_campaign_wizard_draft';

	/**
	 * @var AIPS_Campaigns_Repository
	 */
	private $campaigns_repository;

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

	/**
	 * Constructor.
	 */
	public function __construct(
		?AIPS_Campaigns_Repository $campaigns_repository = null,
		?AIPS_Template_Repository $template_repository = null,
		?AIPS_Unified_Schedule_Service $unified_schedule_service = null,
		?AIPS_Config $config = null
	) {
		$this->campaigns_repository = $campaigns_repository ?: AIPS_Campaigns_Repository::instance();
		$this->template_repository = $template_repository ?: AIPS_Template_Repository::instance();
		$this->unified_schedule_service = $unified_schedule_service ?: new AIPS_Unified_Schedule_Service();
		$this->config = $config ?: AIPS_Config::get_instance();

		add_action('wp_ajax_aips_get_campaigns', array($this, 'ajax_get_campaigns'));
		add_action('wp_ajax_aips_get_campaign_metrics', array($this, 'ajax_get_campaign_metrics'));
		add_action('wp_ajax_aips_toggle_campaign', array($this, 'ajax_toggle_campaign'));
		add_action('wp_ajax_aips_duplicate_campaign', array($this, 'ajax_duplicate_campaign'));
		add_action('wp_ajax_aips_archive_campaign', array($this, 'ajax_archive_campaign'));
		add_action('wp_ajax_aips_restore_campaign', array($this, 'ajax_restore_campaign'));
		add_action('wp_ajax_aips_delete_campaign', array($this, 'ajax_delete_campaign'));
		add_action('wp_ajax_aips_campaign_wizard_save_draft', array($this, 'ajax_save_draft'));
		add_action('wp_ajax_aips_campaign_wizard_validate_step', array($this, 'ajax_validate_step'));
		add_action('wp_ajax_aips_campaign_wizard_finalize', array($this, 'ajax_finalize_campaign'));
	}

	/**
	 * Render campaigns index page.
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		include AIPS_PLUGIN_DIR . 'templates/admin/campaigns.php';
	}

	/**
	 * Render campaign wizard page.
	 */
	public function render_wizard_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$draft           = $this->get_draft();
		$templates       = $this->template_repository->get_all(true);
		$categories      = get_categories(array('hide_empty' => false));
		$post_types      = $this->get_supported_post_types();
		$voices          = (new AIPS_Voices_Repository())->get_all();
		$structures      = (new AIPS_Article_Structure_Repository())->get_all(true);
		$authors         = AIPS_Authors_Repository::instance()->get_all(true);
		$frequencies     = (new AIPS_Interval_Calculator())->get_intervals();
		$aips_config     = $this->config;
		$default_summary = $this->build_summary($this->normalise_payload($draft));

		include AIPS_PLUGIN_DIR . 'templates/admin/campaign-wizard.php';
	}


	/**
	 * Render campaign detail page.
	 */
	public function render_detail_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$campaign_id = isset($_GET['campaign_id']) ? absint(wp_unslash($_GET['campaign_id'])) : 0;
		$campaign = $campaign_id ? $this->campaigns_repository->get_campaign_by_id($campaign_id) : null;

		if (!$campaign) {
			wp_die(esc_html__('Campaign not found.', 'ai-post-scheduler'));
		}

		if (isset($_POST['aips_campaign_detail_nonce'])) {
			if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aips_campaign_detail_nonce'])), 'aips_campaign_detail_save_' . $campaign_id)) {
				wp_die(esc_html__('Security check failed.', 'ai-post-scheduler'));
			}

			$detail_action = isset($_POST['detail_action']) ? sanitize_key(wp_unslash($_POST['detail_action'])) : 'save';

			if ('save' === $detail_action) {
				$name = isset($_POST['campaign_name']) ? sanitize_text_field(wp_unslash($_POST['campaign_name'])) : '';
				$content_goal = isset($_POST['content_goal']) ? sanitize_textarea_field(wp_unslash($_POST['content_goal'])) : '';

				if ('' !== $name) {
					$this->campaigns_repository->update_campaign($campaign_id, array(
						'name' => $name,
						'content_goal' => $content_goal,
					));
				}
			}

			if ('pause' === $detail_action) {
				$this->campaigns_repository->set_active($campaign_id, 0);
			} elseif ('resume' === $detail_action) {
				$this->campaigns_repository->set_active($campaign_id, 1);
			} elseif ('archive' === $detail_action) {
				$this->campaigns_repository->archive_campaign($campaign_id);
			} elseif ('restore' === $detail_action) {
				$this->campaigns_repository->restore_campaign($campaign_id);
			}

			wp_safe_redirect(add_query_arg(array(
				'page' => self::DETAIL_PAGE_SLUG,
				'campaign_id' => $campaign_id,
				'updated' => 1,
			), admin_url('admin.php')));
			exit;
		}

		$templates = $this->campaigns_repository->get_templates_by_campaign($campaign_id);
		$schedules = $this->campaigns_repository->get_schedules_by_campaign($campaign_id);

		include AIPS_PLUGIN_DIR . 'templates/admin/campaign-detail.php';
	}

	/**
	 * AJAX: fetch campaigns.
	 */
	public function ajax_get_campaigns() {
		$this->ajax_guard();

		$active = $this->campaigns_repository->get_campaigns(false);
		$archived = $this->campaigns_repository->get_campaigns(true);

		AIPS_Ajax_Response::success(array(
			'campaigns' => $active,
			'archived_campaigns' => $archived,
			'stats' => $this->campaigns_repository->get_summary_stats(),
		));
	}

	/**
	 * AJAX: fetch campaign metrics.
	 */
	public function ajax_get_campaign_metrics() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		AIPS_Ajax_Response::success(array(
			'metrics' => $this->campaigns_repository->get_campaign_metrics($campaign_id),
		));
	}

	/**
	 * AJAX: toggle campaign active state.
	 */
	public function ajax_toggle_campaign() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		$is_active = isset($_POST['is_active']) ? absint($_POST['is_active']) : 0;

		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		if ($this->campaigns_repository->set_active($campaign_id, $is_active)) {
			$message = $is_active ? __('Campaign resumed.', 'ai-post-scheduler') : __('Campaign paused.', 'ai-post-scheduler');
			AIPS_Ajax_Response::success(array('is_active' => $is_active), $message);
		}

		AIPS_Ajax_Response::error(__('Failed to update campaign status.', 'ai-post-scheduler'), 'update_failed', 500);
	}

	/**
	 * AJAX: duplicate campaign.
	 */
	public function ajax_duplicate_campaign() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		$new_campaign_id = $this->campaigns_repository->duplicate_campaign($campaign_id);
		if (!$new_campaign_id) {
			AIPS_Ajax_Response::error(__('Failed to duplicate campaign.', 'ai-post-scheduler'), 'duplicate_failed', 500);
		}

		AIPS_Ajax_Response::success(array(
			'campaign' => $this->campaigns_repository->get_campaign_by_id($new_campaign_id),
		), __('Campaign duplicated successfully.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: archive campaign.
	 */
	public function ajax_archive_campaign() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		if ($this->campaigns_repository->archive_campaign($campaign_id)) {
			AIPS_Ajax_Response::success(array(), __('Campaign archived successfully.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::error(__('Failed to archive campaign.', 'ai-post-scheduler'), 'archive_failed', 500);
	}

	/**
	 * AJAX: restore campaign.
	 */
	public function ajax_restore_campaign() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		if ($this->campaigns_repository->restore_campaign($campaign_id)) {
			AIPS_Ajax_Response::success(array(), __('Campaign restored successfully.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::error(__('Failed to restore campaign.', 'ai-post-scheduler'), 'restore_failed', 500);
	}

	/**
	 * AJAX: delete campaign.
	 */
	public function ajax_delete_campaign() {
		$this->ajax_guard();

		$campaign_id = $this->get_campaign_id_from_request();
		if (!$campaign_id) {
			AIPS_Ajax_Response::error(__('Invalid campaign ID.', 'ai-post-scheduler'), 'invalid_id', 400);
		}

		if (!$this->campaigns_repository->can_delete_campaign($campaign_id)) {
			AIPS_Ajax_Response::error(__('This campaign has generated posts and can only be archived.', 'ai-post-scheduler'), 'delete_blocked', 400);
		}

		if ($this->campaigns_repository->delete_campaign($campaign_id)) {
			AIPS_Ajax_Response::success(array(), __('Campaign deleted successfully.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::error(__('Failed to delete campaign.', 'ai-post-scheduler'), 'delete_failed', 500);
	}

	/**
	 * AJAX: save wizard draft.
	 */
	public function ajax_save_draft() {
		$this->ajax_guard();

		$payload = $this->get_request_payload();
		$draft = $this->normalise_payload(array_merge($this->get_draft(), $payload));
		$step = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
		$errors = $this->validate_step($step, $draft);

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
			'draft' => $draft,
			'summary' => $this->build_summary($draft),
		), __('Draft saved.', 'ai-post-scheduler'));
	}

	/**
	 * AJAX: validate wizard step.
	 */
	public function ajax_validate_step() {
		$this->ajax_guard();

		$payload = $this->normalise_payload(array_merge($this->get_draft(), $this->get_request_payload()));
		$step = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
		$errors = $this->validate_step($step, $payload);

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

	/**
	 * AJAX: finalize campaign creation.
	 */
	public function ajax_finalize_campaign() {
		$this->ajax_guard();

		$payload = $this->normalise_payload(array_merge($this->get_draft(), $this->get_request_payload()));
		$payload['season_end_date'] = $this->normalise_season_end_date($payload['season_end_date']);
		$errors = $this->validate_payload($payload);

		if (!empty($errors)) {
			AIPS_Ajax_Response::error(
				__('Campaign validation failed.', 'ai-post-scheduler'),
				'validation_failed',
				400,
				array('errors' => $errors)
			);
		}

		try {
			$result = $this->campaigns_repository->create_campaign_bundle($payload);
		} catch (Throwable $e) {
			AIPS_Ajax_Response::error($e->getMessage(), 'finalize_failed', 500);
		}

		delete_option($this->get_draft_option_name());

		AIPS_Ajax_Response::success(array(
			'campaign_id' => $result['campaign_id'],
			'template_id' => $result['template_id'],
			'schedule_id' => $result['schedule_id'],
			'schedule' => $this->find_unified_schedule($result['schedule_id']),
			'summary' => $this->build_summary($payload),
			'redirect_url' => AIPS_Admin_Menu_Helper::get_page_url('campaigns'),
		), __('Campaign created and scheduled.', 'ai-post-scheduler'));
	}

	/**
	 * Common AJAX guard.
	 */
	private function ajax_guard() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::permission_denied();
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Get campaign ID from request.
	 *
	 * @return int
	 */
	private function get_campaign_id_from_request() {
		if (isset($_POST['campaign_id'])) {
			return absint($_POST['campaign_id']);
		}

		return 0;
	}

	/**
	 * Parse payload from request.
	 *
	 * @return array
	 */
	private function get_request_payload() {
		$payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : array();
		if (is_string($payload)) {
			$decoded = json_decode($payload, true);
			$payload = is_array($decoded) ? $decoded : array();
		}

		return is_array($payload) ? $payload : array();
	}

	/**
	 * Get current user draft.
	 *
	 * @return array
	 */
	private function get_draft() {
		$draft = get_option($this->get_draft_option_name(), array());
		return is_array($draft) ? $draft : array();
	}

	/**
	 * Get per-user draft option name.
	 *
	 * @return string
	 */
	private function get_draft_option_name() {
		return $this->draft_option . '_' . get_current_user_id();
	}

	/**
	 * Normalize wizard payload.
	 *
	 * @param array $payload Raw payload.
	 * @return array
	 */
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

	/**
	 * Validate full payload.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
	private function validate_payload($payload) {
		$errors = array();
		foreach (array('goal', 'template', 'defaults', 'schedule', 'review') as $step) {
			$errors = array_merge($errors, $this->validate_step($step, $payload));
		}

		return $errors;
	}

	/**
	 * Validate one wizard step.
	 *
	 * @param string $step Step key.
	 * @param array  $payload Payload.
	 * @return array
	 */
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

	/**
	 * Build wizard summary payload.
	 *
	 * @param array $payload Payload.
	 * @return array
	 */
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

	/**
	 * Find schedule in unified list.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return array|null
	 */
	private function find_unified_schedule($schedule_id) {
		$schedules = $this->unified_schedule_service->get_all(AIPS_Unified_Schedule_Service::TYPE_TEMPLATE, false);
		foreach ($schedules as $schedule) {
			if (isset($schedule['id']) && (int) $schedule['id'] === (int) $schedule_id) {
				return $schedule;
			}
		}

		return null;
	}

	/**
	 * Resolve post status from review policy.
	 *
	 * @param string $review_policy Review policy.
	 * @param string $default_status Default status.
	 * @return string
	 */
	private function post_status_for_review_policy($review_policy, $default_status) {
		if ('auto_publish' === $review_policy) {
			return 'publish';
		}
		if ('approval' === $review_policy) {
			return 'pending';
		}

		return $default_status ?: 'draft';
	}

	/**
	 * Get supported post types.
	 *
	 * @return array
	 */
	private function get_supported_post_types() {
		$post_types = get_post_types(array('public' => true), 'objects');
		unset($post_types['attachment']);
		return $post_types;
	}

	/**
	 * Normalize post type.
	 *
	 * @param string $post_type Post type key.
	 * @return string
	 */
	private function normalise_post_type($post_type) {
		$post_type = sanitize_key((string) $post_type);
		return $this->post_type_exists($post_type) ? $post_type : 'post';
	}

	/**
	 * Normalize post type rules.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
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

	/**
	 * Normalize day preferences.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
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

	/**
	 * Normalize season end date to timestamp.
	 *
	 * @param string $date_string Input date string.
	 * @return int
	 */
	private function normalise_season_end_date($date_string) {
		if (empty($date_string)) {
			return 0;
		}

		$timestamp = strtotime($date_string);
		return $timestamp !== false ? $timestamp : 0;
	}

	/**
	 * Validate post type existence.
	 *
	 * @param string $post_type Post type key.
	 * @return bool
	 */
	private function post_type_exists($post_type) {
		$post_type_obj = get_post_type_object($post_type);
		return $post_type_obj && !empty($post_type_obj->public) && 'attachment' !== $post_type;
	}
}
