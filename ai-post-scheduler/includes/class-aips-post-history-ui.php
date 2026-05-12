<?php
/**
 * Post History UI integration.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_History_UI {

	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	/**
	 * @param AIPS_History_Repository|null $history_repository Optional injected repository.
	 */
	public function __construct($history_repository = null) {
		$this->history_repository = $history_repository instanceof AIPS_History_Repository
			? $history_repository
			: AIPS_History_Repository::instance();

		add_filter('post_row_actions', array($this, 'add_post_row_history_action'), 10, 2);
		add_action('post_submitbox_misc_actions', array($this, 'render_submitbox_history_action'));
	}

	/**
	 * Add a History row action on edit.php for users with plugin admin capability.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Post object.
	 * @return array
	 */
	public function add_post_row_history_action($actions, $post) {
		if (!is_admin() || !current_user_can('manage_options') || !$post instanceof WP_Post) {
			return $actions;
		}

		$history_url = $this->get_history_url_for_post($post->ID, true);
		if (empty($history_url)) {
			return $actions;
		}

		$actions['aips_history'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url($history_url),
			esc_html__('History', 'ai-post-scheduler')
		);

		return $actions;
	}

	/**
	 * Render a quick action in the publish meta box on post edit screens.
	 *
	 * @return void
	 */
	public function render_submitbox_history_action() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->base !== 'post' || empty($screen->post_type)) {
			return;
		}

		$post_id = get_the_ID();
		if (!$post_id) {
			return;
		}

		$history_url = $this->get_history_url_for_post($post_id, true);
		if (empty($history_url)) {
			return;
		}
		?>
		<div class="misc-pub-section aips-post-history-link">
			<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			<a href="<?php echo esc_url($history_url); ?>"><?php echo esc_html__('History', 'ai-post-scheduler'); ?></a>
		</div>
		<?php
	}

	/**
	 * Build a deep-link into plugin History page context for a post.
	 *
	 * @param int  $post_id            WordPress post ID.
	 * @param bool $open_history_modal Whether to include a modal-open query flag.
	 * @return string
	 */
	private function get_history_url_for_post($post_id, $open_history_modal = false) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return '';
		}

		$history = $this->history_repository->get_by_post_id($post_id);
		if (!$history || empty($history->id)) {
			return '';
		}

		$query_args = array(
			'history_id' => absint($history->id),
		);

		if ($open_history_modal) {
			$query_args['open_modal'] = '1';
		}

		return add_query_arg($query_args, AIPS_Admin_Menu_Helper::get_page_url('history'));
	}
}
