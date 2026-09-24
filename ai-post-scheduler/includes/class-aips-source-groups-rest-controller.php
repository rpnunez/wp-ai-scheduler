<?php
/**
 * REST controller for source group taxonomy terms.
 *
 * Routes:
 *   GET    /aips/v1/source-groups
 *   POST   /aips/v1/source-groups
 *   PUT    /aips/v1/source-groups/{id}
 *   DELETE /aips/v1/source-groups/{id}
 *
 * Wraps the `aips_source_group` taxonomy through the WP term functions.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Source_Groups_Rest_Controller extends AIPS_Rest_Controller {

	const TAXONOMY = 'aips_source_group';

	protected $rest_base = 'source-groups';

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->group_args(true),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->group_args(true)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	public function get_items() {
		$terms = get_terms(array('taxonomy' => self::TAXONOMY, 'hide_empty' => false));
		if (is_wp_error($terms)) {
			return $this->error_server($terms->get_error_message());
		}
		return $this->respond(array('groups' => $terms));
	}

	public function create_item($request) {
		$result = wp_insert_term(
			(string) $request->get_param('name'),
			self::TAXONOMY,
			array('description' => (string) $request->get_param('description'))
		);
		if (is_wp_error($result)) {
			return $this->error('aips_invalid_request', $result->get_error_message(), 400);
		}
		return $this->respond_created(array(
			'group'   => get_term($result['term_id'], self::TAXONOMY),
			'message' => __('Source group created.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id     = (int) $request['id'];
		$result = wp_update_term($id, self::TAXONOMY, array(
			'name'        => (string) $request->get_param('name'),
			'description' => (string) $request->get_param('description'),
		));
		if (is_wp_error($result)) {
			return $this->error('aips_invalid_request', $result->get_error_message(), 400);
		}
		return $this->respond(array(
			'group'   => get_term($id, self::TAXONOMY),
			'message' => __('Source group updated.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id     = (int) $request['id'];
		$result = wp_delete_term($id, self::TAXONOMY);
		if (is_wp_error($result)) {
			return $this->error('aips_invalid_request', $result->get_error_message(), 400);
		}
		if (!$result) {
			return $this->error_not_found(__('Source group', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Source group deleted.', 'ai-post-scheduler')));
	}

	private function group_args($required) {
		return array(
			'name' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'description' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}
}
