<?php
/**
 * Redirects Controller
 *
 * AJAX endpoints for Content → Redirects: list, create, enable/disable and
 * delete AIPS redirects, choose the redirect provider and move existing
 * redirects to it.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Redirects_Controller
 */
class AIPS_Redirects_Controller {

	/**
	 * Rows per page.
	 */
	const PER_PAGE = 25;

	/**
	 * @var AIPS_Redirects_Service
	 */
	private $service;

	/**
	 * @param AIPS_Redirects_Service|null $service Redirects service.
	 */
	public function __construct(?AIPS_Redirects_Service $service = null) {
		$container     = AIPS_Container::get_instance();
		$this->service = $service ?: ($container->has(AIPS_Redirects_Service::class) ? $container->make(AIPS_Redirects_Service::class) : new AIPS_Redirects_Service());

		add_action('wp_ajax_aips_redirects_list', array($this, 'ajax_list'));
		add_action('wp_ajax_aips_redirects_create', array($this, 'ajax_create'));
		add_action('wp_ajax_aips_redirects_toggle', array($this, 'ajax_toggle'));
		add_action('wp_ajax_aips_redirects_delete', array($this, 'ajax_delete'));
		add_action('wp_ajax_aips_redirects_set_provider', array($this, 'ajax_set_provider'));
	}

	/**
	 * Data for the initial render of templates/admin/redirects.php.
	 *
	 * @return array{providers:array[], provider_setting:string, active_provider:string}
	 */
	public function get_view_data(): array {
		return array(
			'providers'        => $this->service->get_provider_summary(),
			'provider_setting' => $this->service->get_provider_setting(),
			'active_provider'  => $this->service->get_active_provider()->get_label(),
		);
	}

	/**
	 * AJAX: one page of redirects.
	 *
	 * @return void
	 */
	public function ajax_list() {
		$this->verify_request();

		$page = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
		$list = $this->service->get_list(array(
			'search'   => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
			'origin'   => isset($_POST['origin']) ? sanitize_key(wp_unslash($_POST['origin'])) : '',
			'page'     => $page,
			'per_page' => self::PER_PAGE,
		));

		AIPS_Ajax_Response::success(array(
			'rows'        => $list['rows'],
			'total'       => $list['total'],
			'page'        => $page,
			'total_pages' => max(1, (int) ceil($list['total'] / self::PER_PAGE)),
			'providers'   => $this->service->get_provider_summary(),
		));
	}

	/**
	 * AJAX: create a redirect by hand.
	 *
	 * @return void
	 */
	public function ajax_create() {
		$this->verify_request();

		$result = $this->service->create(array(
			'source'         => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '',
			'target'         => isset($_POST['target']) ? esc_url_raw(wp_unslash($_POST['target'])) : '',
			'target_post_id' => isset($_POST['target_post_id']) ? absint($_POST['target_post_id']) : 0,
			'status_code'    => isset($_POST['status_code']) ? absint($_POST['status_code']) : 301,
			'origin'         => AIPS_Redirects_Service::ORIGIN_MANUAL,
		));

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message' => $result->provider_error !== ''
				? $result->provider_error
				: __('Redirect created.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: enable or disable a redirect.
	 *
	 * @return void
	 */
	public function ajax_toggle() {
		$this->verify_request();

		$enabled = isset($_POST['enabled']) && filter_var(wp_unslash($_POST['enabled']), FILTER_VALIDATE_BOOLEAN);
		$result  = $this->service->set_enabled(isset($_POST['id']) ? absint($_POST['id']) : 0, $enabled);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array(
			'message' => $enabled ? __('Redirect enabled.', 'ai-post-scheduler') : __('Redirect disabled.', 'ai-post-scheduler'),
		));
	}

	/**
	 * AJAX: delete a redirect.
	 *
	 * @return void
	 */
	public function ajax_delete() {
		$this->verify_request();

		$result = $this->service->delete(isset($_POST['id']) ? absint($_POST['id']) : 0);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		AIPS_Ajax_Response::success(array('message' => __('Redirect deleted.', 'ai-post-scheduler')));
	}

	/**
	 * AJAX: choose the provider for new redirects, optionally moving the
	 * existing ones to it.
	 *
	 * @return void
	 */
	public function ajax_set_provider() {
		$this->verify_request();

		$provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : 'auto';
		$move     = isset($_POST['move']) && filter_var(wp_unslash($_POST['move']), FILTER_VALIDATE_BOOLEAN);

		if ($provider !== 'auto' && !isset($this->service->get_providers()[$provider])) {
			AIPS_Ajax_Response::error(__('Unknown redirect provider.', 'ai-post-scheduler'), 'invalid_provider');
		}

		AIPS_Config::get_instance()->set_option(AIPS_Redirects_Service::PROVIDER_OPTION, $provider);
		$active = $this->service->get_active_provider();

		$message = sprintf(
			/* translators: %s: provider name */
			__('New redirects will be served by %s.', 'ai-post-scheduler'),
			$active->get_label()
		);

		if ($move) {
			$result = $this->service->move_all($active->get_key());
			if (is_wp_error($result)) {
				AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
			}

			$message .= ' ' . sprintf(
				/* translators: 1: redirects moved, 2: redirects that stayed with AI Post Scheduler */
				__('%1$d existing redirects moved; %2$d could not be moved and are served by AI Post Scheduler.', 'ai-post-scheduler'),
				$result['moved'],
				$result['failed']
			);
		}

		AIPS_Ajax_Response::success(array(
			'message'   => $message,
			'providers' => $this->service->get_provider_summary(),
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
