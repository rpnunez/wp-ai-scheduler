<?php
/**
 * REST Controller base class
 *
 * Shared plumbing for every plugin REST endpoint: namespace, capability
 * gating, and response/error helpers. The REST counterpart of the
 * AIPS_Ajax_Response conventions used by the admin-ajax controllers.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Rest_Controller
 *
 * Subclasses set `$rest_base` and implement register_routes(). Route
 * callbacks return either respond() output or a WP_Error from one of the
 * error helpers; the REST server turns both into the proper HTTP response.
 *
 * Envelope contract (idiomatic REST — differs from AIPS_Ajax_Response):
 *   - Success: the payload itself, with an HTTP 2xx status. No `{success, data}` wrapper.
 *   - Failure: `{code, message, data: {status, ...}}` — the standard WP_Error REST shape —
 *     with a matching HTTP 4xx/5xx status.
 *
 * Authentication is WordPress cookie auth + `X-WP-Nonce` (action `wp_rest`),
 * handled by core before the permission callback runs. Controllers therefore
 * never verify nonces themselves; they only check capabilities.
 */
abstract class AIPS_Rest_Controller extends WP_REST_Controller {

	/**
	 * Capability every route in this controller requires by default.
	 *
	 * Override in a subclass (or pass a capability to require_capability()) for
	 * endpoints with different access requirements.
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Constructor: fixes the namespace so subclasses only declare `$rest_base`.
	 */
	public function __construct() {
		$this->namespace = AIPS_Rest_Registry::NAMESPACE_V1;
	}

	/**
	 * Register this controller's routes. Called on `rest_api_init`.
	 *
	 * @return void
	 */
	abstract public function register_routes();

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Default permission callback: current user must hold `$capability`.
	 *
	 * Bind directly in route definitions:
	 *   'permission_callback' => array($this, 'permission_check')
	 *
	 * @param WP_REST_Request $request Request (unused; kept for callback signature).
	 * @return true|WP_Error
	 */
	public function permission_check($request = null) {
		return $this->require_capability($this->capability);
	}

	/**
	 * Build a permission callback for a specific capability.
	 *
	 * Use when one route in a controller needs a different capability:
	 *   'permission_callback' => $this->capability_callback('edit_posts')
	 *
	 * @param string $capability Capability to require.
	 * @return callable
	 */
	protected function capability_callback($capability) {
		return function () use ($capability) {
			return $this->require_capability($capability);
		};
	}

	/**
	 * Check a capability and return true or a 401/403 WP_Error.
	 *
	 * Distinguishes "not logged in" (401) from "logged in but not allowed"
	 * (403) so clients can react appropriately.
	 *
	 * @param string $capability Capability to require.
	 * @return true|WP_Error
	 */
	protected function require_capability($capability) {
		if (!is_user_logged_in()) {
			return $this->error_unauthorized();
		}
		if (!current_user_can($capability)) {
			return $this->error_forbidden();
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Responses
	// -------------------------------------------------------------------------

	/**
	 * Build a success response.
	 *
	 * @param mixed $data    Payload (array, scalar, or object) — returned as-is, no envelope.
	 * @param int   $status  HTTP status. Default 200. Use 201 for creations, 204 with null data for deletions.
	 * @param array $headers Optional extra headers.
	 * @return WP_REST_Response
	 */
	protected function respond($data = null, $status = 200, $headers = array()) {
		return new WP_REST_Response($data, $status, $headers);
	}

	/**
	 * Build a 201 Created response.
	 *
	 * @param mixed $data Payload.
	 * @return WP_REST_Response
	 */
	protected function respond_created($data) {
		return $this->respond($data, 201);
	}

	/**
	 * Build a 204 No Content response.
	 *
	 * @return WP_REST_Response
	 */
	protected function respond_no_content() {
		return $this->respond(null, 204);
	}

	/**
	 * Build a paginated collection response with the standard X-WP-Total /
	 * X-WP-TotalPages headers WordPress clients already understand.
	 *
	 * @param array $items       Items for the current page.
	 * @param int   $total       Total item count across all pages.
	 * @param int   $per_page    Page size used for the query.
	 * @return WP_REST_Response
	 */
	protected function respond_collection(array $items, $total, $per_page) {
		$per_page = max(1, (int) $per_page);
		return $this->respond($items, 200, array(
			'X-WP-Total'      => (int) $total,
			'X-WP-TotalPages' => (int) ceil($total / $per_page),
		));
	}

	// -------------------------------------------------------------------------
	// Errors
	// -------------------------------------------------------------------------

	/**
	 * Build a WP_Error carrying an HTTP status for the REST server.
	 *
	 * @param string $code    Machine-readable error code (namespaced with `aips_` by convention).
	 * @param string $message Human-readable message (already translated).
	 * @param int    $status  HTTP status. Default 400.
	 * @param array  $data    Optional extra error context merged alongside `status`.
	 * @return WP_Error
	 */
	protected function error($code, $message, $status = 400, $data = array()) {
		$data = is_array($data) ? $data : array();
		$data['status'] = (int) $status;
		return new WP_Error($code, $message, $data);
	}

	/**
	 * 400 Bad Request.
	 *
	 * @param string $message Optional custom message.
	 * @param array  $data    Optional extra context (e.g. field-level validation errors).
	 * @return WP_Error
	 */
	protected function error_invalid_request($message = '', $data = array()) {
		if ('' === $message) {
			$message = __('Invalid request.', 'ai-post-scheduler');
		}
		return $this->error('aips_invalid_request', $message, 400, $data);
	}

	/**
	 * 401 Unauthorized (no authenticated user).
	 *
	 * @return WP_Error
	 */
	protected function error_unauthorized() {
		return $this->error(
			'aips_unauthorized',
			__('You must be logged in to do that.', 'ai-post-scheduler'),
			401
		);
	}

	/**
	 * 403 Forbidden (authenticated but lacking capability).
	 *
	 * @return WP_Error
	 */
	protected function error_forbidden() {
		return $this->error(
			'aips_forbidden',
			__('Permission denied.', 'ai-post-scheduler'),
			403
		);
	}

	/**
	 * 404 Not Found.
	 *
	 * @param string $resource Optional resource label (e.g. 'Template').
	 * @return WP_Error
	 */
	protected function error_not_found($resource = '') {
		if ('' === $resource) {
			$message = __('Resource not found.', 'ai-post-scheduler');
		} else {
			/* translators: %s: Resource name (e.g., Template, Schedule) */
			$message = sprintf(__('%s not found.', 'ai-post-scheduler'), $resource);
		}
		return $this->error('aips_not_found', $message, 404);
	}

	/**
	 * 409 Conflict (state prevents the operation, e.g. duplicate name).
	 *
	 * @param string $message Message.
	 * @param array  $data    Optional context.
	 * @return WP_Error
	 */
	protected function error_conflict($message, $data = array()) {
		return $this->error('aips_conflict', $message, 409, $data);
	}

	/**
	 * 500 Internal Server Error for failures inside services/repositories.
	 *
	 * @param string $message Optional message. Defaults to a generic one.
	 * @param array  $data    Optional context.
	 * @return WP_Error
	 */
	protected function error_server($message = '', $data = array()) {
		if ('' === $message) {
			$message = __('An error occurred.', 'ai-post-scheduler');
		}
		return $this->error('aips_server_error', $message, 500, $data);
	}

	// -------------------------------------------------------------------------
	// Arg schema helpers
	// -------------------------------------------------------------------------

	/**
	 * Standard `{id}` path argument definition.
	 *
	 * @return array
	 */
	protected function id_arg() {
		return array(
			'id' => array(
				'description'       => __('Unique identifier for the resource.', 'ai-post-scheduler'),
				'type'              => 'integer',
				'minimum'           => 1,
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Standard pagination arguments for collection routes.
	 *
	 * @param int $default_per_page Default page size.
	 * @param int $max_per_page     Upper bound for `per_page`.
	 * @return array
	 */
	protected function pagination_args($default_per_page = 20, $max_per_page = 100) {
		return array(
			'page'     => array(
				'description'       => __('Current page of the collection.', 'ai-post-scheduler'),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __('Maximum number of items to return per page.', 'ai-post-scheduler'),
				'type'              => 'integer',
				'default'           => (int) $default_per_page,
				'minimum'           => 1,
				'maximum'           => (int) $max_per_page,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Standard `ids` array argument for bulk routes.
	 *
	 * @return array
	 */
	protected function ids_arg() {
		return array(
			'ids' => array(
				'description'       => __('List of resource identifiers.', 'ai-post-scheduler'),
				'type'              => 'array',
				'required'          => true,
				'minItems'          => 1,
				'items'             => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'sanitize_callback' => function ($value) {
					return array_values(array_filter(array_map('absint', (array) $value)));
				},
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
