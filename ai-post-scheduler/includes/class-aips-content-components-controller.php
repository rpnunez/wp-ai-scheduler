<?php
/**
 * Content Components Controller
 *
 * @package AI_Post_Scheduler
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Content_Components_Controller
 */
class AIPS_Content_Components_Controller {

	/**
	 * Minimum title length for QA pass.
	 */
	private const MIN_TITLE_LENGTH = 3;

	/**
	 * Minimum content length for QA pass.
	 */
	private const MIN_CONTENT_LENGTH = 40;

	/**
	 * @var AIPS_Content_Components_Repository
	 */
	private $repository;

	/**
	 * @var AIPS_Post_Component_Rules_Repository
	 */
	private $rules_repository;

	/**
	 * Initialize controller.
	 */
	public function __construct() {
		$this->repository = new AIPS_Content_Components_Repository();
		$this->rules_repository = new AIPS_Post_Component_Rules_Repository();

		add_action('wp_ajax_aips_get_content_components', array($this, 'ajax_get_content_components'));
		add_action('wp_ajax_aips_get_content_component', array($this, 'ajax_get_content_component'));
		add_action('wp_ajax_aips_save_content_component', array($this, 'ajax_save_content_component'));
		add_action('wp_ajax_aips_delete_content_component', array($this, 'ajax_delete_content_component'));
		add_action('wp_ajax_aips_toggle_content_component_active', array($this, 'ajax_toggle_content_component_active'));
		add_action('wp_ajax_aips_validate_content_component', array($this, 'ajax_validate_content_component'));
	}

	/**
	 * Authorize AJAX request.
	 *
	 * @return void
	 */
	private function authorize() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	/**
	 * Get all content components.
	 *
	 * @return void
	 */
	public function ajax_get_content_components() {
		$this->authorize();

		AIPS_Ajax_Response::success(
			array(
				'components' => $this->normalize_components($this->repository->get_all(false)),
				'counts'     => $this->repository->get_counts(),
			)
		);
	}

	/**
	 * Get one content component.
	 *
	 * @return void
	 */
	public function ajax_get_content_component() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$component = $this->repository->get_by_id($component_id);
		if (!$component) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(array('component' => $this->normalize_component($component)));
	}

	/**
	 * Save content component.
	 *
	 * @return void
	 */
	public function ajax_save_content_component() {
		$this->authorize();

		$component_id  = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		$title         = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$description   = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
		$component_type = isset($_POST['component_type']) ? sanitize_key(wp_unslash($_POST['component_type'])) : 'custom';
		$content       = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
		$is_active     = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
		$rules         = $this->sanitize_rules(isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '');

		if ($title === '') {
			AIPS_Ajax_Response::error(__('A title is required.', 'ai-post-scheduler'));
		}

		if ($this->repository->title_exists($title, $component_id)) {
			AIPS_Ajax_Response::error(__('A content component with that title already exists.', 'ai-post-scheduler'));
		}

		$qa_result = $this->evaluate_quality($title, $content, $rules);

		$data = array(
			'title'          => $title,
			'slug'           => sanitize_title($title),
			'description'    => $description,
			'status'         => $is_active ? 'active' : 'draft',
			'component_type' => $component_type,
			'content_mode'   => 'html',
			'content'        => $content,
			'content_payload'=> $content,
			'media_payload'  => array(),
			'cta_payload'    => array(),
			'rules_json'     => $rules,
			'qa_status'      => $qa_result['status'],
			'qa_notes'       => $qa_result['notes'],
			'is_active'      => $is_active ? 1 : 0,
		);

		if ($component_id > 0) {
			$existing = $this->repository->get_by_id($component_id);
			if (!$existing) {
				AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
			}

			$result = $this->repository->update($component_id, $data);
			if ($result === false) {
				AIPS_Ajax_Response::error(__('Failed to update content component.', 'ai-post-scheduler'));
			}

			$this->rules_repository->upsert_legacy_rule_for_component($component_id, $rules, $is_active ? true : false, 100);

			$component = $this->repository->get_by_id($component_id);
			AIPS_Ajax_Response::success(
				array(
					'component_id' => $component_id,
					'component'    => $this->normalize_component($component),
					'counts'       => $this->repository->get_counts(),
				),
				__('Content component updated.', 'ai-post-scheduler')
			);
		}

		$new_id = $this->repository->create($data);
		if (!$new_id) {
			AIPS_Ajax_Response::error(__('Failed to create content component.', 'ai-post-scheduler'));
		}

		$this->rules_repository->upsert_legacy_rule_for_component($new_id, $rules, $is_active ? true : false, 100);

		$component = $this->repository->get_by_id($new_id);
		AIPS_Ajax_Response::success(
			array(
				'component_id' => $new_id,
				'component'    => $this->normalize_component($component),
				'counts'       => $this->repository->get_counts(),
			),
			__('Content component created.', 'ai-post-scheduler')
		);
	}

	/**
	 * Delete content component.
	 *
	 * @return void
	 */
	public function ajax_delete_content_component() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$existing = $this->repository->get_by_id($component_id);
		if (!$existing) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($component_id);
		if ($result === false || $result < 1) {
			AIPS_Ajax_Response::error(__('Failed to delete content component.', 'ai-post-scheduler'));
		}

		AIPS_Ajax_Response::success(
			array(
				'counts' => $this->repository->get_counts(),
			),
			__('Content component deleted.', 'ai-post-scheduler')
		);
	}

	/**
	 * Toggle content component active status.
	 *
	 * @return void
	 */
	public function ajax_toggle_content_component_active() {
		$this->authorize();

		$component_id = isset($_POST['component_id']) ? absint($_POST['component_id']) : 0;
		$is_active    = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;

		if ($component_id < 1) {
			AIPS_Ajax_Response::error(__('Invalid content component ID.', 'ai-post-scheduler'));
		}

		$existing = $this->repository->get_by_id($component_id);
		if (!$existing) {
			AIPS_Ajax_Response::not_found(__('Content component', 'ai-post-scheduler'));
		}

		if ($this->repository->set_active($component_id, $is_active) === false) {
			AIPS_Ajax_Response::error(__('Failed to update content component status.', 'ai-post-scheduler'));
		}

		$component = $this->repository->get_by_id($component_id);

		AIPS_Ajax_Response::success(
			array(
				'component' => $this->normalize_component($component),
				'counts'    => $this->repository->get_counts(),
			),
			__('Content component status updated.', 'ai-post-scheduler')
		);
	}

	/**
	 * Validate content component quality.
	 *
	 * @return void
	 */
	public function ajax_validate_content_component() {
		$this->authorize();

		$title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
		$content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
		$rules   = $this->sanitize_rules(isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '');

		$qa_result = $this->evaluate_quality($title, $content, $rules);

		AIPS_Ajax_Response::success(
			array(
				'qa_status' => $qa_result['status'],
				'qa_notes'  => $qa_result['notes'],
				'messages'  => $qa_result['messages'],
			)
		);
	}

	/**
	 * Sanitize rules payload.
	 *
	 * @param string $rules_raw JSON string.
	 * @return array
	 */
	private function sanitize_rules($rules_raw) {
		$decoded = json_decode($rules_raw, true);
		if (!is_array($decoded)) {
			$decoded = array();
		}

		$logic = isset($decoded['logic']) ? sanitize_key($decoded['logic']) : 'and';
		if ($logic !== 'or') {
			$logic = 'and';
		}

		$action = isset($decoded['action']) ? sanitize_key($decoded['action']) : 'add_at_end';

		$conditions = array();
		if (isset($decoded['conditions']) && is_array($decoded['conditions'])) {
			foreach ($decoded['conditions'] as $condition) {
				if (!is_array($condition)) {
					continue;
				}

				$values = array();
				if (isset($condition['values']) && is_array($condition['values'])) {
					foreach ($condition['values'] as $value) {
						$value = sanitize_text_field((string) $value);
						if ($value !== '') {
							$values[] = $value;
						}
					}
				}

				$conditions[] = array(
					'field'    => isset($condition['field']) ? sanitize_key($condition['field']) : 'category',
					'operator' => isset($condition['operator']) ? sanitize_key($condition['operator']) : 'is',
					'values'   => $values,
				);
			}
		}

		return array(
			'logic'      => $logic,
			'action'     => $action,
			'conditions' => $conditions,
		);
	}

	/**
	 * Evaluate quality gate.
	 *
	 * @param string $title Component title.
	 * @param string $content Component content.
	 * @param array  $rules Rules payload.
	 * @return array
	 */
	private function evaluate_quality($title, $content, $rules) {
		$messages = array();

		if (mb_strlen(trim((string) $title)) < self::MIN_TITLE_LENGTH) {
			$messages[] = __('Title should be at least 3 characters.', 'ai-post-scheduler');
		}

		if (mb_strlen(trim(wp_strip_all_tags((string) $content))) < self::MIN_CONTENT_LENGTH) {
			$messages[] = __('Content should include enough detail for insertion quality.', 'ai-post-scheduler');
		}

		$rules_count = isset($rules['conditions']) && is_array($rules['conditions']) ? count($rules['conditions']) : 0;
		if ($rules_count < 1) {
			$messages[] = __('Add at least one rule condition for auto-insertion.', 'ai-post-scheduler');
		}

		$status = empty($messages) ? 'passed' : 'needs_review';
		$notes  = empty($messages) ? __('QA gate passed.', 'ai-post-scheduler') : implode(' ', $messages);

		return array(
			'status'   => $status,
			'notes'    => $notes,
			'messages' => $messages,
		);
	}

	/**
	 * Normalize one component for API responses.
	 *
	 * @param object $component Raw DB component.
	 * @return array
	 */
	private function normalize_component($component) {
		$rules = json_decode((string) $component->rules_json, true);
		if (!is_array($rules)) {
			$rules = array();
		}

		return array(
			'id'             => (int) $component->id,
			'title'          => (string) $component->title,
			'description'    => (string) $component->description,
			'component_type' => (string) $component->component_type,
			'content'        => (string) $component->content,
			'rules'          => $rules,
			'qa_status'      => (string) $component->qa_status,
			'qa_notes'       => (string) $component->qa_notes,
			'is_active'      => (int) $component->is_active,
			'created_at'     => (int) $component->created_at,
			'updated_at'     => (int) $component->updated_at,
		);
	}

	/**
	 * Normalize list of components for API responses.
	 *
	 * @param array $components Raw DB components.
	 * @return array
	 */
	private function normalize_components($components) {
		$normalized = array();
		foreach ((array) $components as $component) {
			$normalized[] = $this->normalize_component($component);
		}
		return $normalized;
	}
}
