<?php
/**
 * Internal Links Admin Page
 *
 * Two-tab layout:
 *   Tab 1 — Index Posts: manage post embedding status.
 *   Tab 2 — Generate Internal Links: find related posts and inject AI links.
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Internal Links', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Use AI embeddings to discover semantically related posts and automatically inject internal links to improve SEO and time-on-site.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<?php if (!$pinecone_configured) : ?>
					<div class="aips-notice aips-notice-warning" style="margin-top:0;">
						<?php
						$settings_url = admin_url('admin.php?page=aips-settings');
						printf(
							/* translators: %s: settings page URL */
							esc_html__('Pinecone is not configured. Please add your API key and index name in %s.', 'ai-post-scheduler'),
							'<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings → Integrations', 'ai-post-scheduler') . '</a>'
						);
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Stats Summary Bar -->
		<div class="aips-content-panel" style="margin-bottom:16px;">
			<div class="aips-panel-body">
				<div class="aips-internal-links-stats">
					<div class="aips-stat-item">
						<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($total_published)); ?></span>
						<span class="aips-stat-label"><?php esc_html_e('Published Posts', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-stat-item aips-stat-indexed">
						<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($total_indexed)); ?></span>
						<span class="aips-stat-label"><?php esc_html_e('Indexed', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-stat-item aips-stat-pending">
						<span class="aips-stat-value" id="aips-stat-pending-count"><?php echo esc_html(number_format_i18n($total_pending)); ?></span>
						<span class="aips-stat-label"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></span>
					</div>
					<div class="aips-stat-item aips-stat-error">
						<span class="aips-stat-value"><?php echo esc_html(number_format_i18n($total_error)); ?></span>
						<span class="aips-stat-label"><?php esc_html_e('Errors', 'ai-post-scheduler'); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Tab Navigation -->
		<div class="aips-content-panel">
			<div class="aips-panel-body">
				<div class="aips-tab-nav" id="aips-internal-links-tab-nav">
					<button type="button" class="aips-tab-link active" data-tab="il-index"><?php esc_html_e('Index Posts', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="il-generate"><?php esc_html_e('Generate Internal Links', 'ai-post-scheduler'); ?></button>
				</div>

				<!-- ============================================================ -->
				<!-- Tab 1: Index Posts -->
				<!-- ============================================================ -->
				<div id="il-index-tab" class="aips-tab-content">

					<!-- Toolbar -->
					<div class="aips-panel-toolbar">
						<div class="aips-toolbar-left">
							<input type="text" id="aips-il-search" class="aips-search-input" placeholder="<?php esc_attr_e('Search post title…', 'ai-post-scheduler'); ?>">
							<select id="aips-il-status-filter" class="aips-select">
								<option value=""><?php esc_html_e('All statuses', 'ai-post-scheduler'); ?></option>
								<option value="indexed"><?php esc_html_e('Indexed', 'ai-post-scheduler'); ?></option>
								<option value="pending"><?php esc_html_e('Pending', 'ai-post-scheduler'); ?></option>
								<option value="error"><?php esc_html_e('Error', 'ai-post-scheduler'); ?></option>
							</select>
							<button type="button" id="aips-il-apply-filter" class="aips-btn aips-btn-secondary aips-btn-sm">
								<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
							</button>
						</div>
						<div class="aips-toolbar-right">
							<button type="button" id="aips-index-all-posts" class="aips-btn aips-btn-primary" <?php disabled(!$pinecone_configured); ?>>
								<span class="dashicons dashicons-database-import"></span>
								<?php esc_html_e('Index All Posts', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</div>

					<!-- Index Table -->
					<div id="aips-index-table-wrapper">
						<table class="aips-table aips-internal-links-index-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Post Type', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Indexed At', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody id="aips-index-table-body">
								<tr class="aips-loading-row">
									<td colspan="5"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Pagination -->
					<div id="aips-index-pagination" class="aips-pagination" style="display:none;"></div>

				</div><!-- /il-index-tab -->

				<!-- ============================================================ -->
				<!-- Tab 2: Generate Internal Links -->
				<!-- ============================================================ -->
				<div id="il-generate-tab" class="aips-tab-content" style="display:none;">

					<div class="aips-il-generate-layout">

						<!-- Step 1: Post Selector -->
						<div class="aips-il-step">
							<h3 class="aips-il-step-heading">
								<span class="aips-il-step-number">1</span>
								<?php esc_html_e('Select a Post to Enhance', 'ai-post-scheduler'); ?>
							</h3>
							<div class="aips-il-post-selector">
								<input type="text"
									id="aips-il-post-search"
									class="aips-search-input aips-il-post-autocomplete"
									placeholder="<?php esc_attr_e('Search for a post by title…', 'ai-post-scheduler'); ?>"
									autocomplete="off">
								<div id="aips-il-post-suggestions" class="aips-il-suggestions-dropdown" style="display:none;"></div>
								<input type="hidden" id="aips-il-selected-post-id" value="">
							</div>
							<div id="aips-il-selected-post-preview" class="aips-il-post-preview" style="display:none;">
								<strong id="aips-il-preview-title"></strong>
								<p id="aips-il-preview-excerpt" class="description"></p>
							</div>
						</div>

						<!-- Step 2: Settings + Find Related -->
						<div class="aips-il-step">
							<h3 class="aips-il-step-heading">
								<span class="aips-il-step-number">2</span>
								<?php esc_html_e('Find Related Posts', 'ai-post-scheduler'); ?>
							</h3>
							<div class="aips-il-settings-row">
								<label for="aips-il-top-n">
									<?php esc_html_e('Max Links', 'ai-post-scheduler'); ?>
									<input type="number" id="aips-il-top-n" min="1" max="20" value="<?php echo esc_attr($default_top_n); ?>" class="small-text">
								</label>
								<label for="aips-il-min-score">
									<?php esc_html_e('Min Similarity', 'ai-post-scheduler'); ?>
									<input type="range" id="aips-il-min-score" min="0" max="1" step="0.05" value="<?php echo esc_attr($default_min_score); ?>">
									<span id="aips-il-min-score-display"><?php echo esc_html(number_format($default_min_score, 2)); ?></span>
								</label>
							</div>
							<button type="button" id="aips-find-related-posts" class="aips-btn aips-btn-primary" disabled>
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e('Find Related Posts', 'ai-post-scheduler'); ?>
							</button>
						</div>

						<!-- Step 3: Related Posts Table -->
						<div class="aips-il-step" id="aips-il-related-step" style="display:none;">
							<h3 class="aips-il-step-heading">
								<span class="aips-il-step-number">3</span>
								<?php esc_html_e('Select Posts to Link', 'ai-post-scheduler'); ?>
							</h3>
							<table class="aips-table aips-il-related-table">
								<thead>
									<tr>
										<th class="check-column"><input type="checkbox" id="aips-il-select-all-related"></th>
										<th><?php esc_html_e('Post Title', 'ai-post-scheduler'); ?></th>
										<th><?php esc_html_e('Similarity Score', 'ai-post-scheduler'); ?></th>
									</tr>
								</thead>
								<tbody id="aips-il-related-tbody"></tbody>
							</table>
							<div class="aips-il-step-actions">
								<button type="button" id="aips-preview-links" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e('Preview Links', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</div>

					</div><!-- /.aips-il-generate-layout -->

				</div><!-- /il-generate-tab -->

			</div><!-- /.aips-panel-body -->
		</div><!-- /.aips-content-panel -->

	</div><!-- /.aips-page-container -->
</div><!-- /.wrap.aips-wrap -->

<!-- ================================================================ -->
<!-- Preview Modal -->
<!-- ================================================================ -->
<div id="aips-il-preview-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-il-modal-title">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-il-modal-title"><?php esc_html_e('Preview — Rewritten Content with Internal Links', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close preview modal', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</div>
		<div class="aips-modal-body">
			<div id="aips-il-preview-content" class="aips-il-preview-content"></div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" id="aips-apply-save-links" class="aips-btn aips-btn-primary">
				<?php esc_html_e('Apply &amp; Save', 'ai-post-scheduler'); ?>
			</button>
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close">
				<?php esc_html_e('Cancel', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>
