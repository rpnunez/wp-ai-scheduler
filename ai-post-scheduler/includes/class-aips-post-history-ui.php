<?php
/**
 * Post History UI integration.
 *
 * Adds History quick access links from native WP post list and post editor.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_History_UI {

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * @param AIPS_History_Repository_Interface|null $history_repository Optional repository override.
	 */
	public function __construct($history_repository = null) {
		$this->history_repository = $history_repository instanceof AIPS_History_Repository_Interface
			? $history_repository
			: new AIPS_History_Repository();

		add_filter('post_row_actions', array($this, 'add_post_row_action'), 10, 2);
		add_action('post_submitbox_misc_actions', array($this, 'render_submitbox_action'));
	}

	/**
	 * Add History link to native post list row actions.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post Current post object.
	 * @return array
	 */
	public function add_post_row_action($actions, $post) {
		if (!$this->can_render_for_post($post)) {
			return $actions;
		}

		$post_id = (int) $post->ID;
		$history = $this->history_repository->get_by_post_id($post_id);
		$history_id = (is_object($history) && !empty($history->id)) ? absint($history->id) : 0;

		if (!$history_id) {
			return $actions;
		}

		$actions['aips_history'] = sprintf(
			'<a href="#" class="aips-open-history-modal" data-history-id="%1$s" data-post-id="%2$s">%3$s</a>',
			esc_attr($history_id),
			esc_attr($post_id),
			esc_html__('History', 'ai-post-scheduler')
		);

		return $actions;
	}

	/**
	 * Render History link in post submit box.
	 *
	 * @return void
	 */
	public function render_submitbox_action() {
		global $post;

		if (!$this->can_render_for_post($post)) {
			return;
		}

		$post_id = (int) $post->ID;
		$history = $this->history_repository->get_by_post_id($post_id);
		$history_id = (is_object($history) && !empty($history->id)) ? absint($history->id) : 0;

		if (!$history_id) {
			return;
		}
		?>
		<div class="misc-pub-section aips-post-history-link">
			<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			<a href="#" 
			   class="aips-open-history-modal" 
			   data-history-id="<?php echo esc_attr($history_id); ?>"
			   data-post-id="<?php echo esc_attr($post_id); ?>">
				<?php esc_html_e('View AI History', 'ai-post-scheduler'); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Build history page URL for a post (kept for backward compatibility).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 * @deprecated Use add_post_row_action() or render_submitbox_action() instead
	 */
	private function get_post_history_url($post_id) {
		$post_id = absint($post_id);
		if (!$post_id) {
			return '';
		}

		$history = $this->history_repository->get_by_post_id($post_id);
		$args = array(
			'post_id' => $post_id,
		);

		if (is_object($history) && !empty($history->id)) {
			$args['history_id'] = absint($history->id);
		}

		return AIPS_Admin_Menu_Helper::get_page_url('history', $args);
	}

	/**
	 * Determine whether History UI should be shown for a post.
	 *
	 * @param WP_Post|mixed $post Post object.
	 * @return bool
	 */
	private function can_render_for_post($post) {
		if (!current_user_can('manage_options')) {
			return false;
		}

		if (!($post instanceof WP_Post)) {
			return false;
		}

		if ($post->post_type !== 'post') {
			return false;
		}

		return true;
	}
}
