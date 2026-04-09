<?php
/**
 * Onboarding Wizard
 *
 * Provides a first-install wizard that guides admins through:
 * - Site-wide Content Strategy settings
 * - Creating an Author
 * - Creating a Template
 * - Generating Author Topics
 * - Generating a first Post
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Onboarding_Wizard {

	const PAGE_SLUG = 'aips-onboarding';

	/**
	 * @var string
	 */
	private $state_option = 'aips_onboarding_state';

	/**
	 * @var string
	 */
	private $completed_option = 'aips_onboarding_completed';

	/**
	 * @var string
	 */
	private $activation_redirect_transient = 'aips_onboarding_redirect';

	public function __construct() {
		add_action('admin_menu', array($this, 'register_page'));
		add_action('admin_init', array($this, 'maybe_redirect_after_activation'));

		add_filter('parent_file', array($this, 'fix_parent_file'));
		add_filter('submenu_file', array($this, 'fix_submenu_file'));

		// AJAX endpoints.
		add_action('wp_ajax_aips_onboarding_save_strategy', array($this, 'ajax_save_strategy'));
		add_action('wp_ajax_aips_onboarding_create_author', array($this, 'ajax_create_author'));
		add_action('wp_ajax_aips_onboarding_create_template', array($this, 'ajax_create_template'));
		add_action('wp_ajax_aips_onboarding_generate_topics', array($this, 'ajax_generate_topics'));
		add_action('wp_ajax_aips_onboarding_generate_post', array($this, 'ajax_generate_post'));
		add_action('wp_ajax_aips_onboarding_reset', array($this, 'ajax_reset'));
		add_action('wp_ajax_aips_onboarding_complete', array($this, 'ajax_complete'));
		add_action('wp_ajax_aips_onboarding_skip', array($this, 'ajax_skip'));
	}

	public function register_page() {
		add_submenu_page(
			null,
			__('Onboarding Wizard', 'ai-post-scheduler'),
			__('Onboarding Wizard', 'ai-post-scheduler'),
			'manage_options',
			self::PAGE_SLUG,
			array($this, 'render_page')
		);
	}

	/**
	 * Redirect to the wizard after activation (only once).
	 */
	public function maybe_redirect_after_activation() {
		if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if ($this->is_completed()) {
			return;
		}

		// Avoid redirect loops.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if ($page === self::PAGE_SLUG) {
			return;
		}

		// Don't redirect during multi-activate.
		if (isset($_GET['activate-multi'])) {
			return;
		}

		$should_redirect = (bool) get_transient($this->activation_redirect_transient);
		if (!$should_redirect) {
			return;
		}

		delete_transient($this->activation_redirect_transient);

		wp_safe_redirect(AIPS_Admin_Menu_Helper::get_page_url(self::PAGE_SLUG));
		exit;
	}

	public function fix_parent_file($parent_file) {
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if ($page === self::PAGE_SLUG) {
			return 'ai-post-scheduler';
		}
		return $parent_file;
	}

	public function fix_submenu_file($submenu_file) {
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		if ($page === self::PAGE_SLUG) {
			return 'ai-post-scheduler';
		}
		return $submenu_file;
	}

	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$state = $this->get_state();

		$site_ctx = class_exists('AIPS_Site_Context') ? AIPS_Site_Context::get() : array();
		$ai_engine_active = class_exists('Meow_MWAI_Core');

		$authors_repo = new AIPS_Authors_Repository();
		$templates_repo = new AIPS_Template_Repository();

		$author = !empty($state['author_id']) ? $authors_repo->get_by_id((int) $state['author_id']) : null;
		$template = !empty($state['template_id']) ? $templates_repo->get_by_id((int) $state['template_id']) : null;

		$categories = get_categories(array('hide_empty' => false));

		include AIPS_PLUGIN_DIR . 'templates/admin/onboarding.php';
	}

	// ---------------------------------------------------------------------
	// State helpers
	// ---------------------------------------------------------------------

	private function get_default_state() {
		return array(
			'author_id'        => 0,
			'template_id'      => 0,
			'topics_generated' => 0,
			'first_topic'      => '',
			'post_id'          => 0,
			'updated_at'       => '',
		);
	}

	private function get_state() {
		$state = get_option($this->state_option, array());
		if (!is_array($state)) {
			$state = array();
		}
		return array_merge($this->get_default_state(), $state);
	}

	private function update_state($patch) {
		$state = $this->get_state();
		foreach ((array) $patch as $key => $value) {
			$state[$key] = $value;
		}
		$state['updated_at'] = current_time('mysql');
		update_option($this->state_option, $state, false);
		return $state;
	}

	private function reset_state() {
		delete_option($this->state_option);
		delete_option($this->completed_option);
	}

	private function is_completed() {
		return (bool) get_option($this->completed_option, false);
	}

	// ---------------------------------------------------------------------
	// AJAX helpers
	// ---------------------------------------------------------------------

	private function ajax_guard() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::permission_denied();
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}
	}

	private function get_content_strategy_fields() {
		if (class_exists('AIPS_Settings') && method_exists('AIPS_Settings', 'get_content_strategy_options')) {
			return AIPS_Settings::get_content_strategy_options();
		}
		return array();
	}

	private function sanitize_content_strategy_input($input) {
		$input = is_array($input) ? $input : array();
		$options = $this->get_content_strategy_fields();

		$sanitized = array();
		foreach ($options as $option_key => $meta) {
			$raw = isset($input[$option_key]) ? $input[$option_key] : null;
			$callback = isset($meta['sanitize_callback']) ? $meta['sanitize_callback'] : null;
			if (is_callable($callback)) {
				$sanitized[$option_key] = call_user_func($callback, $raw);
			} else {
				$sanitized[$option_key] = is_scalar($raw) ? sanitize_text_field((string) $raw) : '';
			}
		}

		return $sanitized;
	}

	// ---------------------------------------------------------------------
	// AJAX: steps
	// ---------------------------------------------------------------------

	public function ajax_save_strategy() {
		$this->ajax_guard();

		$input = isset($_POST['strategy']) ? (array) $_POST['strategy'] : array();
		$sanitized = $this->sanitize_content_strategy_input($input);

		foreach ($sanitized as $option_key => $value) {
			update_option($option_key, $value, false);
		}

		do_action('aips_onboarding_strategy_saved', $sanitized);

		AIPS_Ajax_Response::success(array(
			'message' => __('Content Strategy settings saved.', 'ai-post-scheduler'),
			'strategy' => $sanitized,
		));
	}

	public function ajax_create_author() {
		$this->ajax_guard();

		$state = $this->get_state();
		if (!empty($state['author_id'])) {
			AIPS_Ajax_Response::success(array(
				'message' => __('Author already created for onboarding.', 'ai-post-scheduler'),
				'author_id' => (int) $state['author_id'],
			));
		}

		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$field_niche = isset($_POST['field_niche']) ? sanitize_text_field(wp_unslash($_POST['field_niche'])) : '';

		if ($name === '' || $field_niche === '') {
			AIPS_Ajax_Response::invalid_request(__('Name and Field/Niche are required.', 'ai-post-scheduler'));
		}

		$now = current_time('mysql');

		$data = array(
			'name' => $name,
			'field_niche' => $field_niche,
			'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
			'voice_tone' => isset($_POST['voice_tone']) ? sanitize_text_field(wp_unslash($_POST['voice_tone'])) : '',
			'target_audience' => isset($_POST['target_audience']) ? sanitize_text_field(wp_unslash($_POST['target_audience'])) : '',
			'content_goals' => isset($_POST['content_goals']) ? sanitize_textarea_field(wp_unslash($_POST['content_goals'])) : '',
			'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en',
			'topic_generation_quantity' => isset($_POST['topic_generation_quantity']) ? max(1, absint($_POST['topic_generation_quantity'])) : 5,
			'topic_generation_frequency' => isset($_POST['topic_generation_frequency']) ? sanitize_text_field(wp_unslash($_POST['topic_generation_frequency'])) : 'weekly',
			'post_generation_frequency' => isset($_POST['post_generation_frequency']) ? sanitize_text_field(wp_unslash($_POST['post_generation_frequency'])) : 'daily',
			'post_status' => AIPS_Config::get_instance()->get_option('aips_default_post_status'),
			'post_category' => (int) AIPS_Config::get_instance()->get_option('aips_default_category'),
			'post_author' => get_current_user_id(),
			'is_active' => 1,
			// Ensure first run is not skipped.
			'topic_generation_next_run' => $now,
			'post_generation_next_run' => $now,
		);

		$repo = new AIPS_Authors_Repository();
		$author_id = $repo->create($data);

		if (!$author_id) {
			AIPS_Ajax_Response::error(array('message' => __('Failed to create author.', 'ai-post-scheduler')), 'error', 500);
		}

		$this->update_state(array('author_id' => (int) $author_id));
		do_action('aips_onboarding_author_created', (int) $author_id, $data);

		AIPS_Ajax_Response::success(array(
			'message' => __('Author created.', 'ai-post-scheduler'),
			'author_id' => (int) $author_id,
			'authors_url' => AIPS_Admin_Menu_Helper::get_page_url('authors'),
		));
	}

	public function ajax_create_template() {
		$this->ajax_guard();

		$state = $this->get_state();
		if (!empty($state['template_id'])) {
			AIPS_Ajax_Response::success(array(
				'message' => __('Template already created for onboarding.', 'ai-post-scheduler'),
				'template_id' => (int) $state['template_id'],
			));
		}

		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$prompt_template = isset($_POST['prompt_template']) ? wp_kses_post(wp_unslash($_POST['prompt_template'])) : '';

		if ($name === '' || $prompt_template === '') {
			AIPS_Ajax_Response::invalid_request(__('Template name and Content Prompt are required.', 'ai-post-scheduler'));
		}

		$data = array(
			'name' => $name,
			'prompt_template' => $prompt_template,
			'title_prompt' => isset($_POST['title_prompt']) ? sanitize_text_field(wp_unslash($_POST['title_prompt'])) : '',
			'post_quantity' => 1,
			'generate_featured_image' => 0,
			'featured_image_source' => 'ai_prompt',
			'post_status' => AIPS_Config::get_instance()->get_option('aips_default_post_status'),
			'post_category' => (int) AIPS_Config::get_instance()->get_option('aips_default_category'),
			'post_author' => get_current_user_id(),
			'is_active' => 1,
		);

		$repo = new AIPS_Template_Repository();
		$template_id = $repo->create($data);

		if (!$template_id) {
			AIPS_Ajax_Response::error(array('message' => __('Failed to create template.', 'ai-post-scheduler')), 'error', 500);
		}

		$this->update_state(array('template_id' => (int) $template_id));
		do_action('aips_onboarding_template_created', (int) $template_id, $data);

		AIPS_Ajax_Response::success(array(
			'message' => __('Template created.', 'ai-post-scheduler'),
			'template_id' => (int) $template_id,
			'templates_url' => AIPS_Admin_Menu_Helper::get_page_url('templates'),
		));
	}

	public function ajax_generate_topics() {
		$this->ajax_guard();

		if (!class_exists('Meow_MWAI_Core')) {
			AIPS_Ajax_Response::invalid_request(__('AI Engine is not active. Install/activate it before generating topics.', 'ai-post-scheduler'));
		}

		$state = $this->get_state();
		$author_id = !empty($state['author_id']) ? (int) $state['author_id'] : 0;
		if (!$author_id) {
			AIPS_Ajax_Response::invalid_request(__('Create an Author first.', 'ai-post-scheduler'));
		}

		$authors_repo = new AIPS_Authors_Repository();
		$author = $authors_repo->get_by_id($author_id);
		if (!$author) {
			AIPS_Ajax_Response::error(__('Author not found. Please restart the wizard.', 'ai-post-scheduler'), 'not_found', 404);
		}

		$generator = new AIPS_Author_Topics_Generator();
		$result = $generator->generate_topics($author);

		if (is_wp_error($result)) {
			AIPS_Ajax_Response::error($result->get_error_message(), 'error', 500);
		}

		$first_topic = '';
		$titles = array();
		foreach ((array) $result as $topic) {
			$title = '';
			if (is_array($topic) && isset($topic['topic_title'])) {
				$title = (string) $topic['topic_title'];
			} elseif (is_object($topic) && isset($topic->topic_title)) {
				$title = (string) $topic->topic_title;
			}
			$title = sanitize_text_field($title);
			if ($title !== '') {
				$titles[] = $title;
				if ($first_topic === '') {
					$first_topic = $title;
				}
			}
		}

		$this->update_state(array(
			'topics_generated' => 1,
			'first_topic' => $first_topic,
		));

		do_action('aips_onboarding_topics_generated', $author_id, $titles);

		AIPS_Ajax_Response::success(array(
			'message' => __('Topics generated.', 'ai-post-scheduler'),
			'count' => count($titles),
			'titles' => array_slice($titles, 0, 10),
			'first_topic' => $first_topic,
		));
	}

	public function ajax_generate_post() {
		$this->ajax_guard();

		if (!class_exists('Meow_MWAI_Core')) {
			AIPS_Ajax_Response::invalid_request(__('AI Engine is not active. Install/activate it before generating a post.', 'ai-post-scheduler'));
		}

		$state = $this->get_state();
		$template_id = !empty($state['template_id']) ? (int) $state['template_id'] : 0;
		if (!$template_id) {
			AIPS_Ajax_Response::invalid_request(__('Create a Template first.', 'ai-post-scheduler'));
		}

		$topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
		if ($topic === '' && !empty($state['first_topic'])) {
			$topic = sanitize_text_field((string) $state['first_topic']);
		}

		if ($topic === '') {
			AIPS_Ajax_Response::invalid_request(__('Generate topics (or enter a topic) first.', 'ai-post-scheduler'));
		}

		$templates_repo = new AIPS_Template_Repository();
		$template = $templates_repo->get_by_id($template_id);
		if (!$template) {
			AIPS_Ajax_Response::error(__('Template not found. Please restart the wizard.', 'ai-post-scheduler'), 'not_found', 404);
		}

		$generator = new AIPS_Generator();
		$post_id = $generator->generate_post($template, null, $topic);

		if (is_wp_error($post_id)) {
			AIPS_Ajax_Response::error($post_id->get_error_message(), 'error', 500);
		}

		$post_id = (int) $post_id;
		$this->update_state(array('post_id' => $post_id));

		do_action('aips_onboarding_post_generated', $post_id, $template_id, $topic);

		AIPS_Ajax_Response::success(array(
			'message' => __('Post generated.', 'ai-post-scheduler'),
			'post_id' => $post_id,
			'edit_url' => esc_url_raw(get_edit_post_link($post_id, 'raw')),
			'view_url' => esc_url_raw(get_permalink($post_id)),
		));
	}

	public function ajax_reset() {
		$this->ajax_guard();
		$this->reset_state();
		do_action('aips_onboarding_reset');
		AIPS_Ajax_Response::success(array(), __('Onboarding wizard reset.', 'ai-post-scheduler'));
	}

	public function ajax_complete() {
		$this->ajax_guard();
		update_option($this->completed_option, 1, false);
		do_action('aips_onboarding_completed');
		AIPS_Ajax_Response::success(array(
			'message' => __('Onboarding completed.', 'ai-post-scheduler'),
			'dashboard_url' => AIPS_Admin_Menu_Helper::get_page_url('dashboard'),
		));
	}

	public function ajax_skip() {
		$this->ajax_guard();
		update_option($this->completed_option, 1, false);
		delete_transient($this->activation_redirect_transient);
		do_action('aips_onboarding_skipped');
		AIPS_Ajax_Response::success(array(
			'message' => __('Onboarding skipped.', 'ai-post-scheduler'),
			'dashboard_url' => AIPS_Admin_Menu_Helper::get_page_url('dashboard'),
		));
	}
}

