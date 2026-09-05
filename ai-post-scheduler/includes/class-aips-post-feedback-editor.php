<?php
if (!defined('ABSPATH')) { exit; }

/** Adds explicit feedback controls to generated posts in the native editor. */
class AIPS_Post_Feedback_Editor {
	private $service;
	public function __construct($service = null) {
		$this->service = $service ?: new AIPS_Post_Feedback_Service();
		add_action('add_meta_boxes', array($this, 'register_meta_box'), 10, 2);
	}
	public function register_meta_box($post_type, $post) {
		if (!current_user_can('manage_options') || !AIPS_Config::get_instance()->get_option('aips_post_feedback_enabled')) { return; }
		if (!$post || '1' !== (string) get_post_meta($post->ID, AIPS_Post_Manager::META_GENERATED_POST, true)) { return; }
		add_meta_box('aips-generated-post-feedback', __('Generated Post Feedback', 'ai-post-scheduler'), array($this, 'render'), $post_type, 'side', 'high');
	}
	public function render($post) {
		$post_id = (int) $post->ID;
		$feedback = $this->service->get_current($post_id);
		include AIPS_PLUGIN_DIR . 'templates/partials/post-feedback-controls.php';
		echo '<p class="description">' . esc_html__('Your Like or Dislike guides future generated posts. Feedback is retained if this post is edited or published.', 'ai-post-scheduler') . '</p>';
	}
}
