<?php
/**
 * Partial Generation Email Notifications
 *
 * Sends immediate notifications when a generated post is saved with one or
 * more failed components.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Partial_Generation_Notifications
 */
class AIPS_Partial_Generation_Notifications {

	/**
	 * @var AIPS_History_Service
	 */
	private $history_service;

	/**
	 * Initialize the notification handler.
	 */
	public function __construct() {
		$this->history_service = new AIPS_History_Service();

		add_action('aips_post_generation_incomplete', array($this, 'send_partial_generation_notification'), 10, 4);
	}

	/**
	 * Send an email when a post is created with missing generated components.
	 *
	 * @param int                     $post_id             Post ID.
	 * @param array                   $component_statuses  Per-component status map.
	 * @param AIPS_Generation_Context $context             Generation context.
	 * @param int                     $history_id          Related history ID.
	 * @return void
	 */
	public function send_partial_generation_notification($post_id, $component_statuses, $context, $history_id = 0) {
		$post_id = absint($post_id);
		if (!$post_id || !is_array($component_statuses)) {
			return;
		}

		$to_email = get_option('aips_review_notifications_email', get_option('admin_email'));
		if (!is_email($to_email)) {
			return;
		}

		$missing_components = $this->get_missing_components($component_statuses);
		if (empty($missing_components)) {
			return;
		}

		$post = get_post($post_id);
		$post_title = $post && !empty($post->post_title) ? $post->post_title : __('Untitled', 'ai-post-scheduler');
		$subject = sprintf(
			__('[%s] Partial AI Post Generation Detected', 'ai-post-scheduler'),
			get_bloginfo('name')
		);

		$message = $this->build_email_message($post_id, $post_title, $missing_components, $context, $history_id);
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$sent = wp_mail($to_email, $subject, $message, $headers);

		if ($sent) {
			$history = $this->history_service->create('notification_sent', array());
			$history->record(
				'activity',
				sprintf(__('Partial generation notification sent to %s', 'ai-post-scheduler'), $to_email),
				array('event_type' => 'partial_generation_notification_sent', 'event_status' => 'success'),
				null,
				array(
					'post_id' => $post_id,
					'history_id' => $history_id,
					'to_email' => $to_email,
					'missing_components' => $missing_components,
				)
			);
		}
	}

	/**
	 * Build the notification email body.
	 *
	 * @param int                     $post_id            Post ID.
	 * @param string                  $post_title         Post title.
	 * @param array                   $missing_components Missing component labels.
	 * @param AIPS_Generation_Context $context            Generation context.
	 * @param int                     $history_id         Related history ID.
	 * @return string
	 */
	private function build_email_message($post_id, $post_title, $missing_components, $context, $history_id = 0) {
		$edit_url = esc_url_raw(get_edit_post_link($post_id));
		$partial_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts') . '#aips-partial-generations';
		$source_label = $this->get_source_label($context);

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
				.email-header { background: #b32d2e; color: #ffffff; padding: 20px; text-align: center; }
				.email-body { padding: 30px; }
				.alert-box { background: #fcf0f1; border-left: 4px solid #b32d2e; padding: 15px; margin: 20px 0; }
				.component-list { margin: 16px 0 24px; padding-left: 18px; }
				.button { display: inline-block; padding: 12px 24px; background: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: bold; margin-right: 12px; }
				.button-secondary { background: #50575e; }
				.email-footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #646970; }
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1><?php esc_html_e('Partial Generation Detected', 'ai-post-scheduler'); ?></h1>
				</div>
				<div class="email-body">
					<p><?php esc_html_e('An AI-generated post was created, but one or more requested components failed to generate.', 'ai-post-scheduler'); ?></p>
					<div class="alert-box">
						<strong><?php esc_html_e('Post:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($post_title); ?><br>
						<strong><?php esc_html_e('Source:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($source_label); ?>
						<?php if (!empty($history_id)): ?><br>
						<strong><?php esc_html_e('Session ID:', 'ai-post-scheduler'); ?></strong>
						<?php echo esc_html($history_id); ?>
						<?php endif; ?>
					</div>

					<p><strong><?php esc_html_e('Missing Components:', 'ai-post-scheduler'); ?></strong></p>
					<ul class="component-list">
						<?php foreach ($missing_components as $component_label): ?>
						<li><?php echo esc_html($component_label); ?></li>
						<?php endforeach; ?>
					</ul>

					<p>
						<a href="<?php echo esc_url($edit_url); ?>" class="button"><?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?></a>
						<a href="<?php echo esc_url($partial_url); ?>" class="button button-secondary"><?php esc_html_e('Open Partial Generations', 'ai-post-scheduler'); ?></a>
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

	/**
	 * Get the missing component labels for a status map.
	 *
	 * @param array $component_statuses Per-component status map.
	 * @return array
	 */
	private function get_missing_components($component_statuses) {
		$labels = array(
			'post_title' => __('Title', 'ai-post-scheduler'),
			'post_excerpt' => __('Excerpt', 'ai-post-scheduler'),
			'post_content' => __('Content', 'ai-post-scheduler'),
			'featured_image' => __('Featured Image', 'ai-post-scheduler'),
		);

		$missing = array();
		foreach ($labels as $key => $label) {
			if (array_key_exists($key, $component_statuses) && !$component_statuses[$key]) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Build a source label from the generation context.
	 *
	 * @param AIPS_Generation_Context $context Generation context.
	 * @return string
	 */
	private function get_source_label($context) {
		if (!is_object($context) || !method_exists($context, 'get_type')) {
			return __('Unknown', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Template_Context) {
			$template = $context->get_template();
			if ($template && !empty($template->name)) {
				return sprintf(__('Template: %s', 'ai-post-scheduler'), $template->name);
			}

			return __('Template', 'ai-post-scheduler');
		}

		if ($context instanceof AIPS_Topic_Context) {
			$topic = $context->get_topic();
			if (!empty($topic)) {
				return sprintf(__('Author Topic: %s', 'ai-post-scheduler'), $topic);
			}

			return __('Author Topic', 'ai-post-scheduler');
		}

		return __('Unknown', 'ai-post-scheduler');
	}
}