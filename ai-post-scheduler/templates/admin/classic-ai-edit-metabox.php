<?php
/**
 * Classic Editor AI Edit Meta Box
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="aips-classic-editor-metabox">
	<p class="aips-editor-hint description">
		<?php esc_html_e('Generate SEO component variants or highlight editor text and click the AI Edit icon in the toolbar.', 'ai-post-scheduler'); ?>
	</p>

	<div class="aips-editor-component-actions">
		<button type="button" class="button button-secondary aips-gen-titles-btn" data-component="title">
			<span class="dashicons dashicons-heading"></span>
			<?php esc_html_e('3 Title Candidates', 'ai-post-scheduler'); ?>
		</button>
		<button type="button" class="button button-secondary aips-gen-slugs-btn" data-component="slug">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e('3 Post Slug Candidates', 'ai-post-scheduler'); ?>
		</button>
		<button type="button" class="button button-secondary aips-gen-excerpts-btn" data-component="excerpt">
			<span class="dashicons dashicons-text"></span>
			<?php esc_html_e('3 Excerpt Candidates', 'ai-post-scheduler'); ?>
		</button>
	</div>

	<div class="aips-classic-variants-container" style="display: none;">
		<h4 class="aips-variants-title"><?php esc_html_e('Generated Candidates', 'ai-post-scheduler'); ?></h4>
		<div class="aips-classic-variants-list"></div>
	</div>
</div>
