<?php
/**
 * Sources admin page template.
 *
 * Displays the Trusted Sources management UI where admins can add, edit,
 * delete, and toggle URLs that the AI is encouraged to read from and cite
 * when generating post content.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Ensure $sources is defined for static analysis and for direct includes.
if (!isset($sources) || !is_array($sources)) {
	$sources = array();
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Trusted Sources', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Add URLs that the AI should reference and cite when generating post content. Active sources are injected into every content generation prompt.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-source-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Source', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<?php if (!empty($sources)): ?>

			<div class="aips-filter-bar">
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-source-search"><?php esc_html_e('Search Sources:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-source-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search sources…', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-source-search-clear" class="aips-btn aips-btn-secondary" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<div class="aips-panel-body no-padding">
				<table class="aips-table aips-sources-table" id="aips-sources-table">
					<thead>
						<tr>
							<th class="column-label"><?php esc_html_e('Label', 'ai-post-scheduler'); ?></th>
							<th class="column-url"><?php esc_html_e('URL', 'ai-post-scheduler'); ?></th>
							<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
							<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($sources as $source): ?>
						<tr data-source-id="<?php echo esc_attr($source->id); ?>"
							data-url="<?php echo esc_attr($source->url); ?>"
							data-label="<?php echo esc_attr($source->label); ?>"
							data-description="<?php echo esc_attr($source->description); ?>"
							data-active="<?php echo esc_attr($source->is_active); ?>">
							<td class="column-label cell-primary">
								<?php echo esc_html(!empty($source->label) ? $source->label : '—'); ?>
							</td>
							<td class="column-url">
								<a href="<?php echo esc_url($source->url); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html($source->url); ?>
								</a>
							</td>
							<td class="column-description">
								<?php echo esc_html(!empty($source->description) ? $source->description : '—'); ?>
							</td>
							<td class="column-status">
								<?php if ($source->is_active): ?>
									<span class="aips-badge aips-badge-success">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
									</span>
								<?php else: ?>
									<span class="aips-badge aips-badge-neutral">
										<span class="dashicons dashicons-minus"></span>
										<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="column-actions">
								<div class="aips-action-buttons">
									<button class="aips-btn aips-btn-sm aips-edit-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-edit"></span>
										<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
									</button>
									<button class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										data-active="<?php echo esc_attr($source->is_active); ?>"
										title="<?php echo $source->is_active ? esc_attr__('Deactivate', 'ai-post-scheduler') : esc_attr__('Activate', 'ai-post-scheduler'); ?>">
										<span class="dashicons <?php echo $source->is_active ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
										<span class="screen-reader-text">
											<?php echo $source->is_active ? esc_html__('Deactivate', 'ai-post-scheduler') : esc_html__('Activate', 'ai-post-scheduler'); ?>
										</span>
									</button>
									<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-source"
										data-id="<?php echo esc_attr($source->id); ?>"
										title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
										<span class="dashicons dashicons-trash"></span>
										<span class="screen-reader-text"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
									</button>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Table footer -->
			<div class="tablenav">
				<span class="aips-table-footer-count">
					<?php
					$count = count($sources);
					printf(
						esc_html(_n('%s source', '%s sources', $count, 'ai-post-scheduler')),
						number_format_i18n($count)
					);
					?>
				</span>
			</div>

			<div id="aips-source-search-no-results" class="aips-empty-state" style="display:none;">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Sources Found', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('No sources match your search criteria.', 'ai-post-scheduler'); ?></p>
				<button type="button" class="aips-btn aips-btn-primary" id="aips-source-search-clear-2">
					<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<?php else: ?>
			<div class="aips-empty-state" id="aips-sources-empty">
				<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
				<h3><?php esc_html_e('No Trusted Sources Yet', 'ai-post-scheduler'); ?></h3>
				<p><?php esc_html_e('Add URLs that the AI should reference when generating post content. The AI will be instructed to read from and cite these sources.', 'ai-post-scheduler'); ?></p>
				<button type="button" class="aips-btn aips-btn-primary" id="aips-add-source-empty-btn">
					<?php esc_html_e('Add Your First Source', 'ai-post-scheduler'); ?>
				</button>
			</div>
			<?php endif; ?>
		</div>

	</div><!-- .aips-page-container -->
</div><!-- .wrap -->

<!-- Add / Edit Source Modal -->
<div id="aips-source-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-source-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-source-modal-title"><?php esc_html_e('Add New Source', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-source-form" novalidate>
				<input type="hidden" name="source_id" id="aips-source-id" value="0">

				<div class="aips-form-row">
					<label for="aips-source-url">
						<?php esc_html_e('URL', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<input type="url" id="aips-source-url" name="url" required class="regular-text"
						placeholder="<?php esc_attr_e('https://example.com', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('The full URL of the website (e.g. https://example.com or https://example.com/blog).', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-source-label"><?php esc_html_e('Label', 'ai-post-scheduler'); ?></label>
					<input type="text" id="aips-source-label" name="label" class="regular-text"
						placeholder="<?php esc_attr_e('e.g. Official Docs, Industry Leader', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Optional short name to help you identify this source.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-source-description"><?php esc_html_e('Notes', 'ai-post-scheduler'); ?></label>
					<textarea id="aips-source-description" name="description" rows="3" class="large-text"
						placeholder="<?php esc_attr_e('Optional notes about why this source is trusted.', 'ai-post-scheduler'); ?>"></textarea>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-source-is-active" name="is_active" value="1" checked>
						<?php esc_html_e('Active (include in AI prompts)', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-source-btn">
				<?php esc_html_e('Save Source', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>
