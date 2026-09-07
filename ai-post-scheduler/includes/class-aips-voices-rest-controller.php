<?php
/**
 * REST controller for voices.
 *
 * Routes:
 *   GET    /aips/v1/voices               List voices (optional ?search=&active_only=)
 *   GET    /aips/v1/voices/{id}          Fetch one voice
 *   POST   /aips/v1/voices               Create a voice
 *   PUT    /aips/v1/voices/{id}          Update a voice
 *   DELETE /aips/v1/voices/{id}          Delete a voice
 *
 * Canonical replacement for wp_ajax_aips_(get|save|delete|search)_voice.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Voices_Rest_Controller extends AIPS_Rest_Controller {

	/**
	 * @var AIPS_Voices_Repository
	 */
	private $repository;

	protected $rest_base = 'voices';

	public function __construct() {
		parent::__construct();
		$this->repository = AIPS_Voices_Repository::instance();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'search'      => array(
						'description'       => __('Filter voices by name substring.', 'ai-post-scheduler'),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'active_only' => array(
						'description'       => __('Only return active voices.', 'ai-post-scheduler'),
						'type'              => 'boolean',
						'default'           => false,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->voice_args(true),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array($this, 'update_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array_merge($this->id_arg(), $this->voice_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));
	}

	public function get_items($request) {
		$search = (string) $request->get_param('search');
		if ('' !== $search) {
			return $this->respond(array('voices' => $this->repository->search($search)));
		}
		return $this->respond(array(
			'voices' => $this->repository->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$voice = $this->repository->get_by_id((int) $request['id']);
		if (!$voice) {
			return $this->error_not_found(__('Voice', 'ai-post-scheduler'));
		}
		return $this->respond(array('voice' => $voice));
	}

	public function create_item($request) {
		$data = $this->collect_input($request, true);
		$id   = $this->repository->create($data);
		if (!$id) {
			return $this->error_server(__('Failed to save voice.', 'ai-post-scheduler'));
		}
		return $this->respond_created(array(
			'voice_id' => (int) $id,
			'voice'    => $this->repository->get_by_id($id),
			'message'  => __('Voice saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Voice', 'ai-post-scheduler'));
		}
		$data = $this->collect_input($request, false);
		if (!$this->repository->update($id, $data)) {
			return $this->error_server(__('Failed to save voice.', 'ai-post-scheduler'));
		}
		return $this->respond(array(
			'voice_id' => $id,
			'voice'    => $this->repository->get_by_id($id),
			'message'  => __('Voice saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id = (int) $request['id'];
		if (!$this->repository->get_by_id($id)) {
			return $this->error_not_found(__('Voice', 'ai-post-scheduler'));
		}
		if (!$this->repository->delete($id)) {
			return $this->error_server(__('Failed to delete voice.', 'ai-post-scheduler'));
		}
		return $this->respond(array('message' => __('Voice deleted successfully.', 'ai-post-scheduler')));
	}

	private function collect_input($request, $require_all) {
		$data = array(
			'name'                 => $request->get_param('name'),
			'title_prompt'         => $request->get_param('title_prompt'),
			'content_instructions' => $request->get_param('content_instructions'),
			'excerpt_instructions' => $request->get_param('excerpt_instructions'),
			'is_active'            => $request->get_param('is_active') ? 1 : 0,
		);
		if ($require_all && null === $data['excerpt_instructions']) {
			$data['excerpt_instructions'] = '';
		}
		return array_filter($data, function ($value) { return null !== $value; });
	}

	private function voice_args($required) {
		return array(
			'name' => array(
				'description'       => __('Voice display name.', 'ai-post-scheduler'),
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'title_prompt' => array(
				'description'       => __('Prompt used to generate titles.', 'ai-post-scheduler'),
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'wp_kses_post',
			),
			'content_instructions' => array(
				'description'       => __('Instructions used when generating content.', 'ai-post-scheduler'),
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt_instructions' => array(
				'description'       => __('Instructions used when generating excerpts.', 'ai-post-scheduler'),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'wp_kses_post',
			),
			'is_active' => array(
				'description' => __('Whether the voice is active.', 'ai-post-scheduler'),
				'type'        => 'boolean',
				'default'     => false,
			),
		);
	}
}
