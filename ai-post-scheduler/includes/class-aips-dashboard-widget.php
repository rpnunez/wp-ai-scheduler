<?php
/**
 * Admin Dashboard Widget
 *
 * Registers a WordPress admin dashboard widget showing the pending review
 * post count with a direct link to the review queue.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Dashboard_Widget
 */
class AIPS_Dashboard_Widget {

	/**
	 * @var AIPS_Post_Review_Repository
	 */
	private $repository;

	/**
	 * @param AIPS_Post_Review_Repository|null $repository
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: new AIPS_Post_Review_Repository();
	}

	/**
	 * Register the dashboard widget on `wp_dashboard_setup`.
	 *
	 * @return void
	 */
	public function register() {
		add_action('wp_dashboard_setup', array($this, 'add_widget'));
	}

	/**
	 * Add the widget via wp_add_dashboard_widget.
	 *
	 * @return void
	 */
	public function add_widget() {
		if (!current_user_can('manage_options')) {
			return;
		}

		wp_add_dashboard_widget(
			'aips_review_queue_widget',
			__('AI Post Scheduler — Review Queue', 'ai-post-scheduler'),
			array($this, 'render')
		);
	}

	/**
	 * Render the widget content.
	 *
	 * @return void
	 */
	public function render() {
		$count    = $this->repository->get_draft_count();
		$queue_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-pending-review';
		?>
		<div class="aips-dashboard-widget">
			<?php if ($count > 0): ?>
			<p class="aips-dashboard-widget-count">
				<?php
				printf(
					/* translators: %d: number of posts awaiting review */
					esc_html(_n(
						'<strong>%d post</strong> is waiting for review.',
						'<strong>%d posts</strong> are waiting for review.',
						$count,
						'ai-post-scheduler'
					)),
					$count
				);
				?>
			</p>
			<a href="<?php echo esc_url($queue_url); ?>" class="button button-primary">
				<?php esc_html_e('Review Now', 'ai-post-scheduler'); ?>
			</a>
			<?php else: ?>
			<p><?php esc_html_e('No posts are currently waiting for review.', 'ai-post-scheduler'); ?></p>
			<a href="<?php echo esc_url($queue_url); ?>" class="button">
				<?php esc_html_e('View Review Queue', 'ai-post-scheduler'); ?>
			</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
