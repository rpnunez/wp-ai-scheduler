<?php
/**
 * Admin Error Fallback Partial
 *
 * Renders standardized error state fallback boxes for sections or controllers that encounter exceptions.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.0
 *
 * @var array<string, mixed> $args
 */

if (!defined('ABSPATH')) {
	exit;
}

$title       = isset($args['title']) ? $args['title'] : __('Section Unavailable', 'ai-post-scheduler');
$message     = isset($args['message']) ? $args['message'] : __('An error occurred while loading this section. Please try again or check the system logs.', 'ai-post-scheduler');
$retry_url   = isset($args['retry_url']) ? $args['retry_url'] : '';
$retry_label = isset($args['retry_label']) ? $args['retry_label'] : __('Retry', 'ai-post-scheduler');
$details     = isset($args['details']) ? $args['details'] : '';
?>
<div class="notice notice-error aips-error-fallback">
	<div class="aips-error-fallback-content">
		<span class="dashicons dashicons-warning aips-error-fallback-icon" aria-hidden="true"></span>
		<div style="flex:1;">
			<h4 class="aips-error-fallback-title"><?php echo esc_html($title); ?></h4>
			<p class="aips-error-fallback-message"><?php echo esc_html($message); ?></p>

			<?php if (!empty($details)) : ?>
				<details class="aips-error-fallback-details">
					<summary><?php esc_html_e('Technical Details', 'ai-post-scheduler'); ?></summary>
					<pre><?php echo esc_html($details); ?></pre>
				</details>
			<?php endif; ?>

			<?php if (!empty($retry_url)) : ?>
				<div style="margin-top:var(--aips-space-2);">
					<a href="<?php echo esc_url($retry_url); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php echo esc_html($retry_label); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
