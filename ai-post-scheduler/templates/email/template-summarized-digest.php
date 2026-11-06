<?php
/**
 * Summarized Digest Email Body Template
 *
 * Renders the wrapper layout for summarized batched notifications:
 * count message, grouped digest items table, and CTA link to the Generated Posts screen.
 *
 * Placeholders:
 *   {{item_count}}         Total number of batched notification items.
 *   {{digest_items_html}}  HTML tables of grouped notification items.
 *   {{all_posts_url}}      URL to the admin Generated Posts screen.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<p style="font-size:15px;color:#1d2327;line-height:1.5;">
	<?php
	printf(
		/* translators: %s: placeholder token for count */
		esc_html__('AI Post Scheduler batched %s updates for your review:', 'ai-post-scheduler'),
		'<strong>{{item_count}}</strong>'
	);
	?>
</p>
{{digest_items_html}}
<p class="button-center" style="margin-top:25px;">
	<a href="{{all_posts_url}}" class="button" style="display:inline-block;padding:10px 18px;background:#2271b1;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">
		<?php echo esc_html__('Open Generated Posts', 'ai-post-scheduler'); ?>
	</a>
</p>
