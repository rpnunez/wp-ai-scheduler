<?php
/**
 * Author Topics Email Notifications
 *
 * Sends an email notification when an author's scheduled topic generation
 * completes successfully.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Author_Topics_Notifications
 */
class AIPS_Author_Topics_Notifications {

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * Initialize the notification handler.
	 */
	public function __construct() {
		$this->history_service = new AIPS_History_Service();

		add_action('aips_author_topics_generated', array($this, 'send_topics_generated_notification'), 10, 3);
	}

	/**
	 * Send an email when an author's topics have been generated.
	 *
	 * @param object $author      Author object from database.
	 * @param int    $topic_count Number of topics generated.
	 * @param int    $history_id  Related history ID.
	 * @return void
	 */
	public function send_topics_generated_notification($author, $topic_count, $history_id = 0) {
		if (!is_object($author) || empty($author->id)) {
			return;
		}

		$to_email = get_option('aips_review_notifications_email', get_option('admin_email'));
		if (!is_email($to_email)) {
			return;
		}

		$topic_count = absint($topic_count);

		/* translators: %s: site name */
		$subject = sprintf(
			__('[%s] Author Topics Generated', 'ai-post-scheduler'),
			get_bloginfo('name')
		);

		$message = $this->build_email_message($author, $topic_count, $history_id);
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$sent = wp_mail($to_email, $subject, $message, $headers);

		if ($sent) {
			$history = $this->history_service->create('notification_sent', array());
			$history->record(
				'activity',
				sprintf(
					/* translators: 1: email address, 2: author name */
					__('Author topics notification sent to %1$s for author "%2$s"', 'ai-post-scheduler'),
					$to_email,
					$author->name
				),
				array(
					'event_type'   => 'author_topics_notification_sent',
					'event_status' => 'success',
				),
				null,
				array(
					'author_id'    => $author->id,
					'author_name'  => $author->name,
					'topic_count'  => $topic_count,
					'history_id'   => $history_id,
					'to_email'     => $to_email,
				)
			);
		}
	}

	/**
	 * Build the notification email body.
	 *
	 * @param object $author      Author object from database.
	 * @param int    $topic_count Number of topics generated.
	 * @param int    $history_id  Related history ID.
	 * @return string HTML email body.
	 */
	private function build_email_message($author, $topic_count, $history_id = 0) {
		$author_id     = absint($author->id);
		$topics_url    = AIPS_Admin_Menu_Helper::get_page_url('author_topics', array('author_id' => $author_id, 'status' => 'pending'));
		$dashboard_url = AIPS_Admin_Menu_Helper::get_page_url('dashboard');

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; margin: 0; padding: 0; }
				.email-container { max-width: 640px; margin: 20px auto; background: #ffffff; border: 1px solid #ddd; border-radius: 5px; overflow: hidden; }
				.email-header { background: #2271b1; color: #ffffff; padding: 20px; text-align: center; }
				.email-body { padding: 30px; }
				.info-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0; }
				.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: bold; margin-right: 12px; }
				.button-secondary { background: #50575e; }
				.email-footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #646970; }
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1><?php esc_html_e('Author Topics Generated', 'ai-post-scheduler'); ?></h1>
				</div>
				<div class="email-body">
					<p><?php esc_html_e('A scheduled topic generation run has completed successfully. New topics are ready for review.', 'ai-post-scheduler'); ?></p>
					<div class="info-box">
						<strong><?php esc_html_e('Author:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($author->name); ?><br>
						<strong><?php esc_html_e('Topics Generated:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($topic_count); ?>
						<?php if (!empty($author->field_niche)): ?><br>
						<strong><?php esc_html_e('Niche:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($author->field_niche); ?>
						<?php endif; ?>
						<?php if (!empty($history_id)): ?><br>
						<strong><?php esc_html_e('Session ID:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($history_id); ?>
						<?php endif; ?>
					</div>
					<p>
						<a href="<?php echo esc_url($topics_url); ?>" class="button"><?php esc_html_e('Review Topics', 'ai-post-scheduler'); ?></a>
						<a href="<?php echo esc_url($dashboard_url); ?>" class="button button-secondary"><?php esc_html_e('Go to Dashboard', 'ai-post-scheduler'); ?></a>
					</p>
				</div>
				<div class="email-footer">
					<p><?php echo esc_html(sprintf(__('This email was sent by AI Post Scheduler on %s', 'ai-post-scheduler'), get_bloginfo('name'))); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}
}
