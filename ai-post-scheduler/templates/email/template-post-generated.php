<?php
/**
 * Post Generated Email Body Template
 *
 * Renders an email-safe presentation table for newly generated posts:
 * title header, featured image, excerpt, formatted content, status/source meta,
 * and view/edit action buttons.
 *
 * Placeholders:
 *   {{post_title}}          Title of the generated post.
 *   {{featured_image_row}}  HTML table row containing the featured image (if present).
 *   {{post_excerpt}}        Post excerpt or truncated text preview.
 *   {{post_content}}        Post body content formatted safely.
 *   {{source_label}}        Name of the source strategy/topic.
 *   {{post_status_label}}   Human-readable post status (e.g. Published, Draft).
 *   {{edit_url}}            WordPress admin edit URL for the post.
 *   {{view_url}}            Frontend view/preview URL for the post.
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<p><?php echo esc_html__('AI Post Scheduler generated a new post:', 'ai-post-scheduler'); ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ddd;border-radius:4px;overflow:hidden;margin:20px 0;border-collapse:collapse;">
	<tr>
		<td style="background:#2271b1;color:#ffffff;padding:14px 18px;font-size:18px;font-weight:bold;">{{post_title}}</td>
	</tr>
	{{featured_image_row}}
	<tr>
		<td style="padding:14px 18px;border-top:1px solid #eee;">
			<strong style="display:block;margin-bottom:6px;color:#1d2327;"><?php echo esc_html__('Excerpt', 'ai-post-scheduler'); ?></strong>
			<div style="color:#3c434a;">{{post_excerpt}}</div>
		</td>
	</tr>
	<tr>
		<td style="padding:14px 18px;border-top:1px solid #eee;">
			<strong style="display:block;margin-bottom:6px;color:#1d2327;"><?php echo esc_html__('Content', 'ai-post-scheduler'); ?></strong>
			<div style="color:#3c434a;line-height:1.6;">{{post_content}}</div>
		</td>
	</tr>
	<tr>
		<td style="padding:12px 18px;border-top:1px solid #eee;background:#f9f9f9;font-size:13px;color:#646970;">
			<strong>{{source_label}}</strong> &middot; <strong>{{post_status_label}}</strong>
		</td>
	</tr>
</table>
<p class="button-center">
	<a href="{{edit_url}}" class="button"><?php echo esc_html__('Edit Post', 'ai-post-scheduler'); ?></a>
	<a href="{{view_url}}" class="button button-secondary"><?php echo esc_html__('View Post', 'ai-post-scheduler'); ?></a>
</p>
