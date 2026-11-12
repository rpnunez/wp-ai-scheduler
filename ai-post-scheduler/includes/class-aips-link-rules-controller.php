<?php
/**
 * Keyword Link Rules Controller
 *
 * AJAX endpoints for the Link Rules page (Content hub): list, save, enable /
 * disable and delete "keyword → post" link rules.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Link_Rules_Controller
 */
class AIPS_Link_Rules_Controller {

	/**
	 * @var AIPS_Link_Rules_Service
	 */
	private $rules;

	/**
	 * @param AIPS_Link_Rules_Service|null $rules Rules service.
	 */
	public function __construct(?AIPS_Link_Rules_Service $rules = null) {
		$container   = AIPS_Container::get_instance();
		$this->rules = $rules ?: ($container->has(AIPS_Link_Rules_Service::class) ? $container->make(AIPS_Link_Rules_Service::class) : new AIPS_Link_Rules_Service());

		add_action('wp_ajax_aips_link_rules_list', array($this, 'ajax_list'));
		add_action('wp_ajax_aips_link_rules_save', array($this, 'ajax_save'));
		add_action('wp_ajax_aips_link_rules_toggle', array($this, 'ajax_toggle'));
		add_action('wp_ajax_aips_link_rules_delete', array($this, 'ajax_delete'));
	}

	/**
	 * Data for the initial render of templates/admin/link-rules.php.
	 *
	 * @return array{enabled:bool, max_per_post:int, settings_url:string}
	 */
	public function get_view_data(): array {
		return array(
			'enabled'      => $this->rules->is_enabled(),
			'max_per_post' => (int) AIPS_Config::get_instance()->get_option('aips_link_rules_max_per_post', 3),
			'settings_url' => admin_url('admin.php?page=aips-settings&tab=settings-linking'),
		);
	}

	/**
	 * AJAX: list rules.
	 *
	 * @return void
	 */
	public function ajax_list() {
		$this->verify_request();
		AIPS_Ajax_Response::success(array('rules' => $this->rules->get_rules()));
	}

	/**
	 * AJAX: create or update a rule.
	 *
	 * @return void
	 */
	public function ajax_save() {
		$this->verify_request();

		$result = $this->rules->save_rule(array(
			'id'           => isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '',
			'keyword'      => isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '',
			'target_id'    => isset($_POST['target_id']) ? absint($_POST['target_id']) : 0,
			'max_per_post' => isset($_POST['max_per_post']) ? absint($_POST['max_per_post']) : 1,
			'enabled'      => !isset($_POST['enabled']) || filter_var(wp_unslash($_POST['enabled']), FILTER_VALIDATE_BOOLEAN),
		));

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Link rule saved. It applies to published posts right away.', 'ai-post-scheduler'),
			'rules'   => $this->rules->get_rules(),
		));
	}

	/**
	 * AJAX: enable or disable a rule.
	 *
	 * @return void
	 */
	public function ajax_toggle() {
		$this->verify_request();

		$id      = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
		$enabled = isset($_POST['enabled']) && filter_var(wp_unslash($_POST['enabled']), FILTER_VALIDATE_BOOLEAN);

		if (!$this->rules->set_enabled($id, $enabled)) {
			AIPS_Ajax_Response::error(__('Rule not found.', 'ai-post-scheduler'), 'not_found');
		}

		AIPS_Ajax_Response::success(array(
			'message' => $enabled ? __('Rule enabled.', 'ai-post-scheduler') : __('Rule disabled.', 'ai-post-scheduler'),
			'rules'   => $this->rules->get_rules(),
		));
	}

	/**
	 * AJAX: delete a rule.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		$this->verify_request();

		$id = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';

		if (!$this->rules->delete_rule($id)) {
			AIPS_Ajax_Response::error(__('Rule not found.', 'ai-post-scheduler'), 'not_found');
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Rule deleted. Its links disappear from your posts right away.', 'ai-post-scheduler'),
			'rules'   => $this->rules->get_rules(),
		));
	}

	/**
	 * Nonce and capability gate shared by every endpoint.
	 *
	 * @return void
	 */
	private function verify_request() {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Security check failed. Please refresh the page.', 'ai-post-scheduler'), 'invalid_nonce');
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
