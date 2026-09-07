<?php
/**
 * REST controller for templates.
 *
 * Routes:
 *   GET    /aips/v1/templates                List templates (?active_only=)
 *   GET    /aips/v1/templates/{id}           Fetch one template
 *   POST   /aips/v1/templates                Create a template
 *   PUT    /aips/v1/templates/{id}           Update a template
 *   DELETE /aips/v1/templates/{id}           Delete a template
 *   POST   /aips/v1/templates/{id}/clone     Clone a template (returns 201 with new id)
 *
 * Long-running actions (test, preview_prompts) stay on admin-ajax during the
 * dual-run window; they are called from a wizard flow with its own state.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Templates_Rest_Controller extends AIPS_Rest_Controller {

	/** @var AIPS_Templates */
	private $templates;

	protected $rest_base = 'templates';

	public function __construct() {
		parent::__construct();
		$this->templates = new AIPS_Templates();
	}

	public function register_routes() {
		register_rest_route($this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'get_items'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => array(
					'active_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'create_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->template_args(true),
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
				'args'                => array_merge($this->id_arg(), $this->template_args(false)),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array($this, 'delete_item'),
				'permission_callback' => array($this, 'permission_check'),
				'args'                => $this->id_arg(),
			),
		));

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/clone', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array($this, 'clone_item'),
			'permission_callback' => array($this, 'permission_check'),
			'args'                => $this->id_arg(),
		));
	}

	public function get_items($request) {
		return $this->respond(array(
			'templates' => $this->templates->get_all((bool) $request->get_param('active_only')),
		));
	}

	public function get_item($request) {
		$template = $this->templates->get((int) $request['id']);
		if (!$template) {
			return $this->error_not_found(__('Template', 'ai-post-scheduler'));
		}
		return $this->respond(array('template' => $template));
	}

	public function create_item($request) {
		$data = $this->collect_input($request, 0);

		if ('' === trim((string) $data['name']) || '' === trim((string) $data['prompt_template'])) {
			return $this->error_invalid_request(__('Name and prompt template are required.', 'ai-post-scheduler'));
		}
		if (mb_strlen($data['name']) > 255) {
			return $this->error_invalid_request(__('Template name cannot exceed 255 characters.', 'ai-post-scheduler'));
		}

		$id = $this->templates->save($data);
		if (!$id) {
			return $this->error_server(__('Failed to save template.', 'ai-post-scheduler'));
		}

		$slicing_notice = $this->maybe_log_template_slicing_notice($id, $data['name'], $data['post_quantity']);

		do_action('aips_template_changed', array(
			'action'        => 'created',
			'template_id'   => absint($id),
			'template_name' => $data['name'],
			'user_id'       => get_current_user_id(),
		));

		return $this->respond_created(array(
			'template_id'    => (int) $id,
			'template'       => $this->templates->get($id),
			'slicing_notice' => $slicing_notice,
			'message'        => __('Template saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function update_item($request) {
		$id = (int) $request['id'];
		if (!$this->templates->get($id)) {
			return $this->error_not_found(__('Template', 'ai-post-scheduler'));
		}

		$data = $this->collect_input($request, $id);

		if ('' === trim((string) $data['name']) || '' === trim((string) $data['prompt_template'])) {
			return $this->error_invalid_request(__('Name and prompt template are required.', 'ai-post-scheduler'));
		}
		if (mb_strlen($data['name']) > 255) {
			return $this->error_invalid_request(__('Template name cannot exceed 255 characters.', 'ai-post-scheduler'));
		}

		if (!$this->templates->save($data)) {
			return $this->error_server(__('Failed to save template.', 'ai-post-scheduler'));
		}

		$slicing_notice = $this->maybe_log_template_slicing_notice($id, $data['name'], $data['post_quantity']);

		do_action('aips_template_changed', array(
			'action'        => 'updated',
			'template_id'   => absint($id),
			'template_name' => $data['name'],
			'user_id'       => get_current_user_id(),
		));

		return $this->respond(array(
			'template_id'    => $id,
			'template'       => $this->templates->get($id),
			'slicing_notice' => $slicing_notice,
			'message'        => __('Template saved successfully.', 'ai-post-scheduler'),
		));
	}

	public function delete_item($request) {
		$id       = (int) $request['id'];
		$template = $this->templates->get($id);
		if (!$template) {
			return $this->error_not_found(__('Template', 'ai-post-scheduler'));
		}
		if (!empty($template->campaign_id)) {
			return $this->error_conflict(__('This template cannot be deleted here because it belongs to a campaign. Delete it from the Campaigns page.', 'ai-post-scheduler'));
		}
		if (!$this->templates->delete($id)) {
			return $this->error_server(__('Failed to delete template.', 'ai-post-scheduler'));
		}

		do_action('aips_template_changed', array(
			'action'        => 'deleted',
			'template_id'   => $id,
			'template_name' => !empty($template->name) ? $template->name : __('Template', 'ai-post-scheduler'),
			'user_id'       => get_current_user_id(),
		));

		return $this->respond(array('message' => __('Template deleted successfully.', 'ai-post-scheduler')));
	}

	public function clone_item($request) {
		$id       = (int) $request['id'];
		$template = $this->templates->get($id);
		if (!$template) {
			return $this->error_not_found(__('Template', 'ai-post-scheduler'));
		}

		$new_data = array(
			'name'                             => $template->name . ' ' . __('(Copy)', 'ai-post-scheduler'),
			'description'                      => isset($template->description) ? $template->description : '',
			'prompt_template'                  => $template->prompt_template,
			'title_prompt'                     => $template->title_prompt,
			'voice_id'                         => $template->voice_id,
			'post_quantity'                    => $template->post_quantity,
			'image_prompt'                     => $template->image_prompt,
			'generate_featured_image'          => $template->generate_featured_image,
			'featured_image_source'            => $template->featured_image_source,
			'featured_image_unsplash_keywords' => $template->featured_image_unsplash_keywords,
			'featured_image_media_ids'         => $template->featured_image_media_ids,
			'post_status'                      => $template->post_status,
			'post_type'                        => isset($template->post_type) ? $template->post_type : 'post',
			'post_category'                    => $template->post_category,
			'post_tags'                        => $template->post_tags,
			'post_author'                      => $template->post_author,
			'include_sources'                  => isset($template->include_sources) ? $template->include_sources : 0,
			'affiliate_links_enabled'          => isset($template->affiliate_links_enabled) ? $template->affiliate_links_enabled : 0,
			'source_group_ids'                 => isset($template->source_group_ids) ? $template->source_group_ids : wp_json_encode(array()),
			'is_active'                        => $template->is_active,
		);

		$new_id = $this->templates->save($new_data);
		if (!$new_id) {
			return $this->error_server(__('Failed to clone template.', 'ai-post-scheduler'));
		}

		$mappings_repo = new AIPS_Integration_Mappings_Repository();
		$mappings_repo->clone_template_mappings($id, $new_id);

		do_action('aips_template_changed', array(
			'action'        => 'cloned',
			'template_id'   => absint($new_id),
			'template_name' => $new_data['name'],
			'user_id'       => get_current_user_id(),
		));

		return $this->respond_created(array(
			'template_id' => (int) $new_id,
			'template'    => $this->templates->get($new_id),
			'message'     => __('Template cloned successfully.', 'ai-post-scheduler'),
		));
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function collect_input($request, $existing_id) {
		$post_category = $this->extract_post_categories($request->get_param('post_category'));
		$source_groups = (array) $request->get_param('source_group_ids');
		$data = array(
			'id'                                => (int) $existing_id,
			'name'                              => (string) $request->get_param('name'),
			'description'                       => (string) $request->get_param('description'),
			'prompt_template'                   => (string) $request->get_param('prompt_template'),
			'title_prompt'                      => (string) $request->get_param('title_prompt'),
			'voice_id'                          => absint($request->get_param('voice_id')),
			'post_quantity'                     => max(1, absint($request->get_param('post_quantity'))),
			'image_prompt'                      => (string) $request->get_param('image_prompt'),
			'generate_featured_image'           => $request->get_param('generate_featured_image') ? 1 : 0,
			'featured_image_source'             => (string) ($request->get_param('featured_image_source') ?: 'ai_prompt'),
			'featured_image_unsplash_keywords'  => (string) $request->get_param('featured_image_unsplash_keywords'),
			'featured_image_media_ids'          => (string) $request->get_param('featured_image_media_ids'),
			'post_status'                       => (string) ($request->get_param('post_status') ?: 'draft'),
			'post_category'                     => $post_category,
			'post_tags'                         => (string) $request->get_param('post_tags'),
			'post_author'                       => absint($request->get_param('post_author')) ?: get_current_user_id(),
			'include_sources'                   => $request->get_param('include_sources') ? 1 : 0,
			'affiliate_links_enabled'           => $request->get_param('affiliate_links_enabled') ? 1 : 0,
			'source_group_ids'                  => wp_json_encode(array_map('absint', $source_groups)),
			'is_active'                         => $request->get_param('is_active') ? 1 : 0,
		);

		// post_type is write-once — repository ignores it on updates.
		if (0 === (int) $existing_id) {
			$post_type = $request->get_param('post_type');
			if (null !== $post_type) {
				$data['post_type'] = sanitize_key((string) $post_type);
			}
		}

		return $data;
	}

	private function extract_post_categories($raw): array {
		if (is_array($raw)) {
			return array_values(array_filter(array_map('intval', $raw), static function ($id) {
				return $id > 0;
			}));
		}
		$single = intval($raw);
		return $single > 0 ? array($single) : array();
	}

	private function maybe_log_template_slicing_notice($template_id, $template_name, $post_quantity) {
		$post_quantity = max(1, absint($post_quantity));
		$batch_service = new AIPS_Batch_Queue_Service();
		$threshold     = $batch_service->get_large_batch_threshold();

		if ($post_quantity <= $threshold) {
			return null;
		}

		$config      = $batch_service->calculate_config($post_quantity);
		$slice_count = isset($config['num_batches']) ? max(1, absint($config['num_batches'])) : 1;
		$notice_message = sprintf(
			/* translators: 1: configured quantity, 2: threshold, 3: slice count */
			__('This template is configured for %1$d posts. With threshold %2$d, runs will be split into %3$d slices.', 'ai-post-scheduler'),
			$post_quantity,
			$threshold,
			$slice_count
		);

		$history_service = new AIPS_History_Service();
		$history = $history_service->create('template_lifecycle', array(
			'template_id'     => absint($template_id),
			'creation_method' => 'template_lifecycle',
			'user_id'         => get_current_user_id(),
			'source'          => 'manual_ui',
		));
		if (!$history) {
			return null;
		}
		$history->record(
			'activity',
			sprintf(
				/* translators: 1: template name, 2: post quantity, 3: threshold, 4: slice count */
				__('Template "%1$s" saved with %2$d posts per run. Quantities above threshold (%3$d) will be split into %4$d slices at runtime.', 'ai-post-scheduler'),
				$template_name, $post_quantity, $threshold, $slice_count
			),
			array('event_type' => 'template_slicing_notice', 'event_status' => 'success'),
			null,
			array(
				'template_id'   => absint($template_id),
				'post_quantity' => $post_quantity,
				'threshold'     => $threshold,
				'slice_count'   => $slice_count,
			)
		);
		$history->complete_success();

		return array(
			'message'       => $notice_message,
			'slice_count'   => $slice_count,
			'threshold'     => $threshold,
			'post_quantity' => $post_quantity,
		);
	}

	private function template_args($required) {
		return array(
			'name' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'sanitize_text_field',
				'maxLength'         => 255,
			),
			'description' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'prompt_template' => array(
				'type'              => 'string',
				'required'          => (bool) $required,
				'sanitize_callback' => 'wp_kses_post',
			),
			'title_prompt' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'voice_id'      => array('type' => 'integer', 'default' => 0, 'minimum' => 0),
			'post_quantity' => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
			'image_prompt' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'wp_kses_post',
			),
			'generate_featured_image'          => array('type' => 'boolean', 'default' => false),
			'featured_image_source'            => array(
				'type'              => 'string',
				'default'           => 'ai_prompt',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'featured_image_unsplash_keywords' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'featured_image_media_ids'         => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_status'                      => array(
				'type'              => 'string',
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_type'                        => array(
				'type'              => 'string',
				'default'           => 'post',
				'sanitize_callback' => 'sanitize_key',
			),
			'post_category'                    => array(
				'type'    => array('array', 'integer'),
				'default' => array(),
			),
			'post_tags'                        => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'post_author'                      => array('type' => 'integer', 'default' => 0, 'minimum' => 0),
			'include_sources'                  => array('type' => 'boolean', 'default' => false),
			'affiliate_links_enabled'          => array('type' => 'boolean', 'default' => false),
			'source_group_ids'                 => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array('type' => 'integer'),
			),
			'is_active'                        => array('type' => 'boolean', 'default' => false),
		);
	}
}
