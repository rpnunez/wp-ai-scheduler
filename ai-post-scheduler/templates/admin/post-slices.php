<?php
/**
 * Post Slices admin page template.
 *
 * @package AI_Post_Scheduler
 * @since 2.6.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!isset($post_slices) || !is_array($post_slices)) {
	$post_slices = array();
}

if (!isset($post_slice_counts) || !is_array($post_slice_counts)) {
	$post_slice_counts = array(
		'total'    => count($post_slices),
		'active'   => 0,
		'inactive' => 0,
	);
}

$total_count    = isset($post_slice_counts['total']) ? (int) $post_slice_counts['total'] : count($post_slices);
$active_count   = isset($post_slice_counts['active']) ? (int) $post_slice_counts['active'] : 0;
$inactive_count = isset($post_slice_counts['inactive']) ? (int) $post_slice_counts['inactive'] : 0;
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container aips-post-slices-page">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Post Slices', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description">
						<?php esc_html_e('Define reusable editorial styles that rotate into title and content prompts so generated posts vary by angle, framing, examples, and emphasis.', 'ai-post-scheduler'); ?>
					</p>
				</div>
				<div class="aips-page-actions">
					<button type="button" class="aips-btn aips-btn-primary" id="aips-add-post-slice-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e('Add Post Slice', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="aips-post-slices-summary" aria-label="<?php esc_attr_e('Post slice counts', 'ai-post-scheduler'); ?>">
			<div class="aips-post-slices-summary-card">
				<span class="aips-post-slices-summary-value"><?php echo esc_html($total_count); ?></span>
				<span class="aips-post-slices-summary-label"><?php esc_html_e('Total Slices', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-post-slices-summary-card is-active">
				<span class="aips-post-slices-summary-value"><?php echo esc_html($active_count); ?></span>
				<span class="aips-post-slices-summary-label"><?php esc_html_e('Active', 'ai-post-scheduler'); ?></span>
			</div>
			<div class="aips-post-slices-summary-card is-inactive">
				<span class="aips-post-slices-summary-value"><?php echo esc_html($inactive_count); ?></span>
				<span class="aips-post-slices-summary-label"><?php esc_html_e('Inactive', 'ai-post-scheduler'); ?></span>
			</div>
		</div>

		<div class="aips-content-panel">
			<?php if (!empty($post_slices)): ?>
				<div class="aips-filter-bar">
					<div class="aips-filter-right">
						<label class="screen-reader-text" for="aips-post-slice-search"><?php esc_html_e('Search Post Slices:', 'ai-post-scheduler'); ?></label>
						<input type="search" id="aips-post-slice-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search post slices...', 'ai-post-scheduler'); ?>">
						<button type="button" id="aips-post-slice-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
					</div>
				</div>

				<div class="aips-panel-body no-padding">
					<table class="aips-table aips-post-slices-table" id="aips-post-slices-table">
						<thead>
							<tr>
								<th class="column-name"><?php esc_html_e('Name', 'ai-post-scheduler'); ?></th>
								<th class="column-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></th>
								<th class="column-sort-order"><?php esc_html_e('Sort Order', 'ai-post-scheduler'); ?></th>
								<th class="column-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($post_slices as $slice): ?>
								<?php
								$slice_id    = isset($slice->id) ? (int) $slice->id : 0;
								$name        = isset($slice->name) ? (string) $slice->name : '';
								$description = isset($slice->description) ? (string) $slice->description : '';
								$sort_order  = isset($slice->sort_order) ? (int) $slice->sort_order : 0;
								$is_active   = !empty($slice->is_active) ? 1 : 0;
								?>
								<tr data-slice-id="<?php echo esc_attr($slice_id); ?>"
									data-name="<?php echo esc_attr($name); ?>"
									data-description="<?php echo esc_attr($description); ?>"
									data-sort-order="<?php echo esc_attr($sort_order); ?>"
									data-active="<?php echo esc_attr($is_active); ?>">
									<td class="column-name cell-primary">
										<?php echo esc_html($name); ?>
									</td>
									<td class="column-description">
										<?php if ('' !== $description): ?>
											<?php echo esc_html($description); ?>
										<?php else: ?>
											<span class="cell-meta"><?php esc_html_e('No description', 'ai-post-scheduler'); ?></span>
										<?php endif; ?>
									</td>
									<td class="column-sort-order">
										<?php echo esc_html($sort_order); ?>
									</td>
									<td class="column-status">
										<?php if ($is_active): ?>
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
											<button type="button" class="aips-btn aips-btn-sm aips-edit-post-slice"
												data-id="<?php echo esc_attr($slice_id); ?>"
												title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-edit"></span>
												<span class="screen-reader-text"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
											</button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-toggle-post-slice"
												data-id="<?php echo esc_attr($slice_id); ?>"
												data-active="<?php echo esc_attr($is_active); ?>"
												title="<?php echo $is_active ? esc_attr__('Deactivate', 'ai-post-scheduler') : esc_attr__('Activate', 'ai-post-scheduler'); ?>">
												<span class="dashicons <?php echo $is_active ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
												<span class="screen-reader-text">
													<?php echo $is_active ? esc_html__('Deactivate', 'ai-post-scheduler') : esc_html__('Activate', 'ai-post-scheduler'); ?>
												</span>
											</button>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-delete-post-slice"
												data-id="<?php echo esc_attr($slice_id); ?>"
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

				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php
						printf(
							esc_html(_n('%s post slice', '%s post slices', count($post_slices), 'ai-post-scheduler')),
							esc_html((string) count($post_slices))
						);
						?>
					</span>
				</div>

				<div id="aips-post-slice-search-no-results" class="aips-empty-state" style="display:none;">
					<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('No Post Slices Found', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description"><?php esc_html_e('No post slices match your search criteria.', 'ai-post-scheduler'); ?></p>
					<div class="aips-empty-state-actions">
						<button type="button" class="aips-btn aips-btn-primary" id="aips-post-slice-search-clear-2">
							<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>
			<?php else: ?>
				<div class="aips-empty-state" id="aips-post-slices-empty">
					<div class="dashicons dashicons-editor-paragraph aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('No Post Slices Yet', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description"><?php esc_html_e('Create post slices to steer generated posts toward specific editorial styles such as failure modes, tool selection, or implementation checklists.', 'ai-post-scheduler'); ?></p>
					<div class="aips-empty-state-actions">
						<button type="button" class="aips-btn aips-btn-primary" id="aips-add-post-slice-empty-btn">
							<?php esc_html_e('Add Your First Post Slice', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="aips-post-slice-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-post-slice-modal-title">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-post-slice-modal-title"><?php esc_html_e('Add New Post Slice', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-post-slice-form" novalidate>
				<input type="hidden" name="slice_id" id="aips-post-slice-id" value="0">

				<div class="aips-form-row">
					<label for="aips-post-slice-name">
						<?php esc_html_e('Slice Name', 'ai-post-scheduler'); ?>
						<span class="required" aria-hidden="true">*</span>
					</label>
					<input type="text" id="aips-post-slice-name" name="name" required class="regular-text"
						placeholder="<?php esc_attr_e('e.g. failure modes, tool selection, production rollout mistakes', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('This label is injected into post prompts as the selected editorial style.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label for="aips-post-slice-description"><?php esc_html_e('Description', 'ai-post-scheduler'); ?></label>
					<textarea id="aips-post-slice-description" name="description" rows="4" class="large-text"
						placeholder="<?php esc_attr_e('Optional notes for admins about when this slice should be used.', 'ai-post-scheduler'); ?>"></textarea>
				</div>

				<div class="aips-form-row">
					<label for="aips-post-slice-sort-order"><?php esc_html_e('Sort Order', 'ai-post-scheduler'); ?></label>
					<input type="number" id="aips-post-slice-sort-order" name="sort_order" class="small-text" value="0" step="1">
					<p class="description"><?php esc_html_e('Lower numbers appear earlier in the rotation.', 'ai-post-scheduler'); ?></p>
				</div>

				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="aips-post-slice-is-active" name="is_active" value="1" checked>
						<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary" id="aips-save-post-slice-btn">
				<?php esc_html_e('Save Post Slice', 'ai-post-scheduler'); ?>
			</button>
		</div>
	</div>
</div>
