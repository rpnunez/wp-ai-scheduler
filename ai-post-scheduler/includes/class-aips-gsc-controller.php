<?php
/**
 * Search Console Controller
 *
 * AJAX endpoints behind the Google Search Console field on Settings → API
 * Keys: test the connection, sync target keywords now, and disconnect.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_GSC_Controller
 */
class AIPS_GSC_Controller {

	/**
	 * @var AIPS_GSC_Keywords_Service
	 */
	private $service;

	/**
	 * @param AIPS_GSC_Keywords_Service|null $service Keywords service.
	 */
	public function __construct(?AIPS_GSC_Keywords_Service $service = null) {
		$container     = AIPS_Container::get_instance();
		$this->service = $service ?: ($container->has(AIPS_GSC_Keywords_Service::class) ? $container->make(AIPS_GSC_Keywords_Service::class) : new AIPS_GSC_Keywords_Service());

		add_action('wp_ajax_aips_gsc_test', array($this, 'ajax_test'));
		add_action('wp_ajax_aips_gsc_sync', array($this, 'ajax_sync'));
		add_action('wp_ajax_aips_gsc_disconnect', array($this, 'ajax_disconnect'));
	}

	/**
	 * AJAX: check the key works and can read the configured property.
	 *
	 * @return void
	 */
	public function ajax_test() {
		$this->verify_request();

		$client = $this->service->get_client();
		$sites  = $client->list_sites();

		if (is_wp_error($sites)) {
			AIPS_Ajax_Response::error($sites->get_error_message(), $sites->get_error_code());
		}

		$available = array_values(array_filter(array_map(function ($site) {
			return isset($site['siteUrl']) ? (string) $site['siteUrl'] : '';
		}, $sites), 'strlen'));

		$property = $client->get_property();

		if ($property !== '' && in_array($property, $available, true)) {
			$this->service->ensure_schedule();
			AIPS_Ajax_Response::success(array(
				/* translators: %s: Search Console property */
				'message' => sprintf(__('Connected. The service account can read %s.', 'ai-post-scheduler'), $property),
				'sites'   => $available,
			));
		}

		if (empty($available)) {
			AIPS_Ajax_Response::error(
				/* translators: %s: service account email */
				sprintf(__('The key works, but no Search Console property is shared with %s yet. Add it as a user in Search Console → Settings → Users and permissions.', 'ai-post-scheduler'), $client->get_client_email()),
				'aips_gsc_no_sites'
			);
		}

		AIPS_Ajax_Response::error(
			/* translators: %s: comma-separated list of properties */
			sprintf(__('The key works, but the property field does not match a property it can read. Available: %s', 'ai-post-scheduler'), implode(', ', $available)),
			'aips_gsc_property_mismatch'
		);
	}

	/**
	 * AJAX: sync target keywords now.
	 *
	 * @return void
	 */
	public function ajax_sync() {
		$this->verify_request();

		$result = $this->service->sync();
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), $result->get_error_code());
		}

		$this->service->ensure_schedule();

		AIPS_Ajax_Response::success(array(
			'message' => sprintf(
				/* translators: 1: number of queries, 2: number of posts */
				__('Search Console synced: %1$d target keywords for %2$d posts.', 'ai-post-scheduler'),
				(int) $result['queries'],
				(int) $result['posts']
			),
			'status'  => $result,
		));
	}

	/**
	 * AJAX: remove the saved service account key.
	 *
	 * @return void
	 */
	public function ajax_disconnect() {
		$this->verify_request();

		$this->service->get_client()->disconnect();
		$this->service->ensure_schedule();

		AIPS_Ajax_Response::success(array(
			'message' => __('Search Console disconnected. Stored target keywords stay until you connect again and sync.', 'ai-post-scheduler'),
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
