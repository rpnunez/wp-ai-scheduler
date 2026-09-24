<?php
/**
 * Calendar REST Controller
 *
 * GET /aips/v1/calendar/events?year=YYYY&month=M
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Calendar_Rest_Controller extends AIPS_Rest_Controller {

	protected $rest_base = 'calendar';

	/**
	 * @var AIPS_Calendar_Controller
	 */
	private $calendar;

	/**
	 * @param AIPS_Calendar_Controller|null $calendar Optional occurrence calculator (for tests).
	 */
	public function __construct($calendar = null) {
		parent::__construct();
		// Constructed without AJAX hook registration — this is a REST context.
		$this->calendar = $calendar ?: new AIPS_Calendar_Controller(false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base . '/events', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_events'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'year'  => array(
						'description'       => __('Four-digit year. Defaults to the current year.', 'ai-post-scheduler'),
						'type'              => 'integer',
						'minimum'           => 1970,
						'maximum'           => 2100,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'month' => array(
						'description'       => __('Month number (1–12). Defaults to the current month.', 'ai-post-scheduler'),
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 12,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			),
		));
	}

	/**
	 * Projected schedule occurrences for a month.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_events($request) {
		$now   = AIPS_DateTime::now();
		$year  = $request->get_param('year');
		$month = $request->get_param('month');

		$year  = $year ? (int) $year : (int) $now->toDisplay('Y');
		$month = $month ? (int) $month : (int) $now->toDisplay('n');

		return $this->respond(array(
			'events' => $this->calendar->get_month_events($year, $month),
			'year'   => $year,
			'month'  => $month,
		));
	}
}
