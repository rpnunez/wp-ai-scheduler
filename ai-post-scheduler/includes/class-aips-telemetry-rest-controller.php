<?php
/**
 * Telemetry REST Controller
 *
 * GET /aips/v1/telemetry        — paginated report + chart series
 * GET /aips/v1/telemetry/{id}   — single row with decoded payload
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Telemetry_Rest_Controller extends AIPS_Rest_Controller {

	protected $rest_base = 'telemetry';

	/**
	 * @var AIPS_Telemetry_Report_Service
	 */
	private $service;

	/**
	 * @param AIPS_Telemetry_Report_Service|null $service Optional service (for tests).
	 */
	public function __construct($service = null) {
		parent::__construct();
		$this->service = $service ?: new AIPS_Telemetry_Report_Service();
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_report'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->report_args(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_details'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	/**
	 * Telemetry must be enabled in addition to the capability check.
	 *
	 * @param WP_REST_Request|null $request Request.
	 * @return true|WP_Error
	 */
	public function permission_check($request = null) {
		$allowed = parent::permission_check($request);
		if (true !== $allowed) {
			return $allowed;
		}

		if (!$this->service->is_enabled()) {
			return $this->error('aips_feature_disabled', __('Telemetry is disabled.', 'ai-post-scheduler'), 403);
		}

		return true;
	}

	/**
	 * Paginated report for the requested window and filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_report($request) {
		$report = $this->service->get_report(
			(string) $request->get_param('start_date'),
			(string) $request->get_param('end_date'),
			array(
				'type'           => $request->get_param('type'),
				'event_category' => $request->get_param('event_category'),
				'request_method' => $request->get_param('request_method'),
				'page_search'    => $request->get_param('page_search'),
				'issues_only'    => $request->get_param('issues_only'),
			),
			$request->get_param('per_page'),
			$request->get_param('page')
		);

		$response = $this->respond($report);
		$response->header('X-WP-Total', (int) $report['total']);
		$response->header('X-WP-TotalPages', (int) $report['total_pages']);

		return $response;
	}

	/**
	 * Single row with decoded payload and events.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_details($request) {
		$details = $this->service->get_details($request->get_param('id'));
		if (null === $details) {
			return $this->error_not_found(__('Telemetry row', 'ai-post-scheduler'));
		}

		return $this->respond($details);
	}

	/**
	 * Query args for the report route.
	 *
	 * @return array
	 */
	private function report_args() {
		$date_arg = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$enum_arg = function ($values, $description) {
			return array(
				'description'       => $description,
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge(array(''), $values),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			);
		};

		return array(
			'start_date'     => array_merge($date_arg, array(
				'description' => __('Window start (YYYY-MM-DD). Defaults to 30 days ago.', 'ai-post-scheduler'),
			)),
			'end_date'       => array_merge($date_arg, array(
				'description' => __('Window end (YYYY-MM-DD). Defaults to today.', 'ai-post-scheduler'),
			)),
			'page'           => array(
				'description'       => __('Page number.', 'ai-post-scheduler'),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page'       => array(
				'description'       => __('Rows per page.', 'ai-post-scheduler'),
				'type'              => 'integer',
				'default'           => AIPS_Telemetry_Report_Service::DEFAULT_PER_PAGE,
				'enum'              => AIPS_Telemetry_Report_Service::ALLOWED_PER_PAGE,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'type'           => $enum_arg($this->service->allowed_values('types'), __('Request type filter.', 'ai-post-scheduler')),
			'event_category' => $enum_arg($this->service->allowed_values('event_categories'), __('Event category filter.', 'ai-post-scheduler')),
			'request_method' => $enum_arg($this->service->allowed_values('request_methods'), __('HTTP method filter.', 'ai-post-scheduler')),
			'page_search'    => array(
				'description'       => __('Substring match on the recorded page.', 'ai-post-scheduler'),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'issues_only'    => array(
				'description'       => __('Only include requests that recorded issues.', 'ai-post-scheduler'),
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
