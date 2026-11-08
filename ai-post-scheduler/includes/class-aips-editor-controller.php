<?php
/**
 * Editor Controller
 *
 * Handles AJAX requests from the Gutenberg Block Editor and Classic Editor
 * for directive rewrites, internal link weaving, and component variant generation.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editor_Controller {

	/**
	 * @var AIPS_Editor_Directive_Service
	 */
	private $directive_service;

	/**
	 * @var AIPS_Editor_Link_Weave_Service
	 */
	private $link_weave_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Editor_Directive_Service|null  $directive_service  Directive service.
	 * @param AIPS_Editor_Link_Weave_Service|null $link_weave_service Link weave service.
	 */
	public function __construct(
		?AIPS_Editor_Directive_Service $directive_service = null,
		?AIPS_Editor_Link_Weave_Service $link_weave_service = null
	) {
		$container                = AIPS_Container::get_instance();
		$this->directive_service  = $directive_service ?: ($container->has(AIPS_Editor_Directive_Service::class) ? $container->make(AIPS_Editor_Directive_Service::class) : new AIPS_Editor_Directive_Service());
		$this->link_weave_service = $link_weave_service ?: ($container->has(AIPS_Editor_Link_Weave_Service::class) ? $container->make(AIPS_Editor_Link_Weave_Service::class) : new AIPS_Editor_Link_Weave_Service());

		add_action('wp_ajax_aips_editor_apply_directive', array($this, 'ajax_apply_directive'));
		add_action('wp_ajax_aips_editor_weave_internal_links', array($this, 'ajax_weave_internal_links'));
		add_action('wp_ajax_aips_editor_generate_component_variants', array($this, 'ajax_generate_component_variants'));
	}

	/**
	 * AJAX handler: Apply a directive to content.
	 *
	 * @return void
	 */
	public function ajax_apply_directive(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid security token. Please refresh the page.', 'ai-post-scheduler'));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$post_id || !current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::permission_denied(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$content          = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
		$directive_preset = isset($_POST['directive_preset']) ? sanitize_key($_POST['directive_preset']) : '';
		$custom_directive = isset($_POST['custom_directive']) ? sanitize_text_field(wp_unslash($_POST['custom_directive'])) : '';
		$variants_count   = isset($_POST['variants_count']) ? absint($_POST['variants_count']) : 1;

		$post    = get_post($post_id);
		$context = array(
			'post_id'    => $post_id,
			'post_title' => $post ? $post->post_title : '',
		);

		$result = $this->directive_service->apply_directive($content, $directive_preset, $custom_directive, $variants_count, $context);
		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}

		AIPS_Ajax_Response::success(array(
			'variants' => $result,
			'count'    => count($result),
			'preset'   => $directive_preset,
		));
	}

	/**
	 * AJAX handler: Weave contextual internal links into content.
	 *
	 * @return void
	 */
	public function ajax_weave_internal_links(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid security token. Please refresh the page.', 'ai-post-scheduler'));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$post_id || !current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::permission_denied(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
		$result  = $this->link_weave_service->weave_internal_links($post_id, $content);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message());
		}

		AIPS_Ajax_Response::success($result);
	}

	/**
	 * AJAX handler: Generate 3 title, slug, or excerpt candidates.
	 *
	 * @return void
	 */
	public function ajax_generate_component_variants(): void {
		if (!check_ajax_referer('aips_ajax_nonce', 'nonce', false)) {
			AIPS_Ajax_Response::error(__('Invalid security token. Please refresh the page.', 'ai-post-scheduler'));
		}

		$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
		if (!$post_id || !current_user_can('edit_post', $post_id)) {
			AIPS_Ajax_Response::permission_denied(__('You do not have permission to edit this post.', 'ai-post-scheduler'));
		}

		$component = isset($_POST['component']) ? sanitize_key($_POST['component']) : 'title';
		if (!in_array($component, array('title', 'slug', 'excerpt'), true)) {
			AIPS_Ajax_Response::error(__('Invalid component type.', 'ai-post-scheduler'));
		}

		$variants = $this->directive_service->generate_component_variants($post_id, $component);
		if (is_wp_error($variants)) {
			AIPS_Ajax_Response::error($variants->get_error_message());
		}

		AIPS_Ajax_Response::success(array(
			'component' => $component,
			'variants'  => $variants,
			'count'     => count($variants),
		));
	}
}
