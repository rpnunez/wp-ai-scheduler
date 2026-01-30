<?php
/**
 * Post Review Email Notifications
 *
 * Handles daily email notifications for posts awaiting review.
 * Sends a summary of draft posts to the configured email address.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Post_Review_Notifications
 *
 * Manages scheduled email notifications for draft posts awaiting review.
 */
class AIPS_Post_Review_Notifications {
	
	/**
	 * @var AIPS_History_Service Service for history logging
	 */
	private $history_service;
	
	/**
	 * Initialize the notification handler.
	 */
	public function __construct() {
		$this->history_service = new AIPS_History_Service();
		
		// Hook into the cron event
		add_action('aips_send_review_notifications', array($this, 'send_review_notification_email'));
	}
	
	/**
	 * Send the daily review notification email.
	 *
	 * @return void
	 */
	public function send_review_notification_email() {
		// Check if notifications are enabled
		$enabled = get_option('aips_review_notifications_enabled', 0);
		if (!$enabled) {
			return;
		}
		
		// Get the email address
		$to_email = get_option('aips_review_notifications_email', get_option('admin_email'));
		if (!is_email($to_email)) {
			return;
		}
		
		// Get draft posts
		$repository = new AIPS_Post_Review_Repository();
		$draft_count = $repository->get_draft_count();
		
		// If no drafts, don't send email
		if ($draft_count === 0) {
			return;
		}
		
		// Get up to 10 draft posts for the list
		$draft_posts = $repository->get_draft_posts(array(
			'per_page' => 10,
			'page' => 1,
		));
		
		// Build the email
		$subject = sprintf(
			__('[%s] %d Posts Awaiting Review', 'ai-post-scheduler'),
			get_bloginfo('name'),
			$draft_count
		);
		
		$message = $this->build_email_message($draft_posts, $draft_count);
		
		// Set email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);
		
		// Send the email
		$sent = wp_mail($to_email, $subject, $message, $headers);
		
		// Log the result
		if ($sent) {
			$history = $this->history_service->create('notification_sent', array());
			$history->record(
				'activity',
				sprintf(
					__('Review notification email sent to %s (%d posts)', 'ai-post-scheduler'),
					$to_email,
					$draft_count
				),
				array('event_type' => 'review_notification_sent', 'event_status' => 'success'),
				null,
				array('to_email' => $to_email, 'draft_count' => $draft_count)
			);
		}
	}
	
	/**
	 * Build the email message content.
	 *
	 * @param array $draft_posts Draft posts data from repository.
	 * @param int   $total_count Total number of draft posts.
	 * @return string HTML email message.
	 */
	private function build_email_message($draft_posts, $total_count) {
		$site_url = get_site_url();
		$review_url = admin_url('admin.php?page=aips-post-review');
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				body {
					font-family: Arial, sans-serif;
					line-height: 1.6;
					color: #333;
					background-color: #f5f5f5;
					margin: 0;
					padding: 0;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background: #ffffff;
					border: 1px solid #ddd;
					border-radius: 5px;
					overflow: hidden;
				}
				.email-header {
					background: #2271b1;
					color: #ffffff;
					padding: 20px;
					text-align: center;
				}
				.email-header h1 {
					margin: 0;
					font-size: 24px;
				}
				.email-body {
					padding: 30px;
				}
				.email-body p {
					margin: 0 0 15px;
				}
				.stats-box {
					background: #f0f6fc;
					border-left: 4px solid #2271b1;
					padding: 15px;
					margin: 20px 0;
					font-size: 18px;
					font-weight: bold;
					color: #2271b1;
				}
				.post-list {
					list-style: none;
					padding: 0;
					margin: 20px 0;
				}
				.post-item {
					padding: 12px;
					margin-bottom: 10px;
					background: #f9f9f9;
					border-left: 3px solid #2271b1;
					border-radius: 3px;
				}
				.post-title {
					font-weight: bold;
					color: #1d2327;
					margin-bottom: 5px;
				}
				.post-meta {
					font-size: 13px;
					color: #646970;
				}
				.button {
					display: inline-block;
					padding: 12px 24px;
					background: #2271b1;
					color: #ffffff;
					text-decoration: none;
					border-radius: 3px;
					font-weight: bold;
					margin: 20px 0;
				}
				.email-footer {
					background: #f5f5f5;
					padding: 20px;
					text-align: center;
					font-size: 12px;
					color: #646970;
				}
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1><?php esc_html_e('Posts Awaiting Review', 'ai-post-scheduler'); ?></h1>
				</div>
				
				<div class="email-body">
					<p><?php esc_html_e('Hello,', 'ai-post-scheduler'); ?></p>
					
					<p><?php esc_html_e('You have AI-generated posts waiting for review before publication.', 'ai-post-scheduler'); ?></p>
					
					<div class="stats-box">
						<?php echo esc_html(sprintf(_n('%d Post Awaiting Review', '%d Posts Awaiting Review', $total_count, 'ai-post-scheduler'), $total_count)); ?>
					</div>
					
					<?php if (!empty($draft_posts['items'])): ?>
					<p><strong><?php esc_html_e('Recent Draft Posts:', 'ai-post-scheduler'); ?></strong></p>
					
					<ul class="post-list">
						<?php foreach ($draft_posts['items'] as $post): ?>
						<li class="post-item">
							<div class="post-title">
								<?php echo esc_html($post->post_title ?: $post->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
							</div>
							<div class="post-meta">
								<?php 
								echo esc_html(sprintf(
									__('Template: %s | Created: %s', 'ai-post-scheduler'),
									$post->template_name ?: __('None', 'ai-post-scheduler'),
									date_i18n(get_option('date_format'), strtotime($post->created_at))
								));
								?>
							</div>
						</li>
						<?php endforeach; ?>
					</ul>
					
					<?php if ($total_count > 10): ?>
					<p>
						<em>
							<?php 
							echo esc_html(sprintf(
								__('...and %d more posts', 'ai-post-scheduler'),
								$total_count - 10
							));
							?>
						</em>
					</p>
					<?php endif; ?>
					<?php endif; ?>
					
					<p style="text-align: center;">
						<a href="<?php echo esc_url($review_url); ?>" class="button">
							<?php esc_html_e('Review Posts', 'ai-post-scheduler'); ?>
						</a>
					</p>
					
					<p><?php esc_html_e('Click the button above to review and publish your posts.', 'ai-post-scheduler'); ?></p>
				</div>
				
				<div class="email-footer">
					<p>
						<?php echo esc_html(sprintf(__('This email was sent by AI Post Scheduler on %s', 'ai-post-scheduler'), get_bloginfo('name'))); ?>
					</p>
					<p>
						<?php esc_html_e('To disable these notifications, visit the plugin settings page.', 'ai-post-scheduler'); ?>
					</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
