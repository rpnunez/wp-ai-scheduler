<?php
if (!defined('ABSPATH')) { exit; }
$post_id = absint($post_id ?? 0);
$feedback = $feedback ?? null;
$reaction = $feedback ? (string) $feedback->reaction : '';
$reason = $feedback ? (string) $feedback->reason_category : '';
$comment = $feedback ? (string) $feedback->comment : '';
?>
<div class="aips-post-feedback-controls" data-post-id="<?php echo esc_attr($post_id); ?>" data-reaction="<?php echo esc_attr($reaction); ?>" data-reason="<?php echo esc_attr($reason); ?>" data-comment="<?php echo esc_attr($comment); ?>">
	<div class="aips-post-feedback-buttons" role="group" aria-label="<?php esc_attr_e('Generated post feedback', 'ai-post-scheduler'); ?>">
		<button type="button" class="button aips-post-feedback-reaction" data-reaction="liked" aria-pressed="<?php echo 'liked' === $reaction ? 'true' : 'false'; ?>"><span class="dashicons dashicons-thumbs-up" aria-hidden="true"></span> <?php esc_html_e('Like', 'ai-post-scheduler'); ?></button>
		<button type="button" class="button aips-post-feedback-reaction" data-reaction="disliked" aria-pressed="<?php echo 'disliked' === $reaction ? 'true' : 'false'; ?>"><span class="dashicons dashicons-thumbs-down" aria-hidden="true"></span> <?php esc_html_e('Dislike', 'ai-post-scheduler'); ?></button>
		<button type="button" class="button-link aips-post-feedback-clear" <?php echo empty($reaction) ? 'hidden' : ''; ?>><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
	</div>
	<div class="aips-post-feedback-dialog" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e('Add optional feedback details', 'ai-post-scheduler'); ?>" hidden>
		<label><?php esc_html_e('Reason', 'ai-post-scheduler'); ?>
			<select class="aips-post-feedback-reason"><option value=""><?php esc_html_e('No reason', 'ai-post-scheduler'); ?></option></select>
		</label>
		<label><?php esc_html_e('Comment (optional)', 'ai-post-scheduler'); ?><textarea class="aips-post-feedback-comment" rows="3" maxlength="2000"></textarea></label>
		<button type="button" class="button button-primary aips-post-feedback-save"><?php esc_html_e('Save feedback', 'ai-post-scheduler'); ?></button>
		<button type="button" class="button aips-post-feedback-cancel"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
	</div>
</div>
