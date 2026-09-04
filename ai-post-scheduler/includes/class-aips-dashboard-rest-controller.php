<?php
/**
 * Dashboard REST Controller
 *
 * GET /aips/v1/dashboard?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Dashboard_Rest_Controller extends AIPS_Rest_Controller {

	protected $rest_base = 'dashboard';

	/**
	 * @var AIPS_Dashboard_Metrics_Service
	 */
	private $service;

	/**
	 * @param AIPS_Dashboard_Metrics_Service|null $service Optional service (for tests).
	 */
	public function __construct($service = null) {
		parent::__construct();
		$this->service = $service ?: new AIPS_Dashboard_Metrics_Service();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_dashboard'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->date_range_args(),
			),
		));
	}

	/**
	 * Return the date-range-scoped dashboard payload.
	 *
	 * Invalid or missing dates fall back to the default range (first of the
	 * current month → today) rather than erroring, matching the admin page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_dashboard($request) {
		$data = $this->service->get_data(
			(string) $request->get_param('date_from'),
			(string) $request->get_param('date_to')
		);

		return $this->respond($data);
	}

	/**
	 * Query args for the date range.
	 *
	 * @return array
	 */
	private function date_range_args() {
		$date_arg = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return array(
			'date_from' => array_merge($date_arg, array(
				'description' => __('Range start (YYYY-MM-DD). Defaults to the first of the current month.', 'ai-post-scheduler'),
			)),
			'date_to'   => array_merge($date_arg, array(
				'description' => __('Range end (YYYY-MM-DD). Defaults to today.', 'ai-post-scheduler'),
			)),
		);
	}
}
