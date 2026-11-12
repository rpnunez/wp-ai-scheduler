<?php
/**
 * Classic Editor AI Edit Post Images meta box.
 *
 * Lists content images for the post. The featured image stays on its own widget.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * @var WP_Post $post Current post.
 */
$post_id = isset($post->ID) ? (int) $post->ID : 0;
?>
<div id="aips-editor-post-images" class="aips-editor-post-images" data-post-id="<?php echo esc_attr((string) $post_id); ?>">
	<p class="description">
		<?php esc_html_e('Each row is one content image. Re-generate replaces that figure only. The featured image is edited in its own panel.', 'ai-post-scheduler'); ?>
	</p>
	<p>
		<button type="button" class="button button-primary" id="aips-editor-post-images-regenerate-all">
			<?php esc_html_e('Re-generate all', 'ai-post-scheduler'); ?>
		</button>
	</p>
	<div id="aips-editor-post-images-list"></div>
	<p class="aips-editor-post-images-empty description" hidden>
		<?php esc_html_e('This post has no content images.', 'ai-post-scheduler'); ?>
	</p>
</div>
