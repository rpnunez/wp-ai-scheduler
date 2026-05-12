<?php
/**
 * Post Edit History UI.
 *
 * Adds a History action to the post edit screen when a history/session exists
 * for the current post, and includes the shared View Session modal.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Post_Edit_History {
	/**
	 * @var AIPS_History_Repository
	 */
	private $history_repository;

	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
		add_action('post_submitbox_misc_actions', array($this, 'render_history_action'));
		add_action('admin_footer-post.php', array($this, 'render_modal'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	public function enqueue_assets($hook_suffix) {
		if ('post.php' !== $hook_suffix) {
			return;
		}

		wp_enqueue_script(
			'aips-admin-view-session',
			AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);
	}

	public function render_history_action() {
		$post = get_post();
		if (!$post || empty($post->ID)) {
			return;
		}

		$history = $this->history_repository->get_by_post_id($post->ID);
		if (empty($history) || empty($history->id)) {
			return;
		}
		?>
		<div class="misc-pub-section aips-post-edit-history">
			<button type="button"
				class="button button-small aips-view-session"
				data-history-id="<?php echo esc_attr($history->id); ?>"
				title="<?php esc_attr_e('History', 'ai-post-scheduler'); ?>"
				aria-label="<?php esc_attr_e('View generation history for this post', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<?php esc_html_e('History', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<?php
	}

	public function render_modal() {
		$post = get_post();
		if (!$post || empty($post->ID)) {
			return;
		}

		$history = $this->history_repository->get_by_post_id($post->ID);
		if (empty($history) || empty($history->id)) {
			return;
		}

		include AIPS_PLUGIN_DIR . 'templates/partials/view-session-modal.php';
	}
}
