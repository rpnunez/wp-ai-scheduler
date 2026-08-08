<?php
/**
 * Pending Review Dashboard Widget Template
 *
 * @var int    $count     Number of posts awaiting review.
 * @var string $queue_url URL to the review queue page.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-dashboard-widget">
	<?php if ($count > 0): ?>
	<p class="aips-dashboard-widget-count">
		<?php
		printf(
			/* translators: %d: number of posts awaiting review */
			wp_kses(
				_n(
					'<strong>%d post</strong> is waiting for review.',
					'<strong>%d posts</strong> are waiting for review.',
					$count,
					'ai-post-scheduler'
				),
				array('strong' => array())
			),
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
