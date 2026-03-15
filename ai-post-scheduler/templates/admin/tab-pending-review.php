<?php
/**
 * Pending Review Tab Template
 *
 * Tab 3: Pending Review - Shows draft posts awaiting review before publishing.
 * Included by generated-posts-container.php.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var AIPS_Generated_Posts_Controller $controller */
/** @var array $draft_posts */
/** @var array $templates */
/** @var int|string $template_id */
/** @var string $search_query */
/** @var int $review_current_page */
?>

<!-- Tab 3 panel -->
<div id="aips-pending-review-tab" class="aips-tab-content" style="display:none;" role="tabpanel" aria-hidden="true">
	<div class="aips-content-panel">
		<!-- Filter Bar -->
		<div class="aips-filter-bar">
			<form method="get" class="aips-post-review-filters aips-filter-form">
				<input type="hidden" name="page" value="aips-generated-posts">
				<div class="aips-filter-left">
						<?php if (!empty($templates)): ?>
					<label class="screen-reader-text" for="aips-filter-template"><?php esc_html_e('Filter by Template:', 'ai-post-scheduler'); ?></label>
					<select name="template_id" id="aips-filter-template" class="aips-form-select">
						<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
						<?php foreach ($templates as $template): ?>
						<option value="<?php echo esc_attr($template->id); ?>" <?php selected($template_id, $template->id); ?>>
							<?php echo esc_html($template->name); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
					</button>
					<?php endif; ?>
				</div>
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-post-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
					<button type="submit" id="aips-post-search-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
					</button>
					<?php if (!empty($search_query)): ?>
					<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>

	<?php if (!empty($draft_posts['items'])): ?>
	<form id="aips-post-review-form" method="post">
		<!-- Bulk Actions Toolbar -->
		<div class="aips-panel-toolbar">
			<div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
				<select name="bulk_action" id="bulk-action-selector-top" class="aips-form-select" style="width: auto;">
					<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
					<option value="publish"><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
					<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
				</select>
				<button type="button" id="aips-bulk-action-btn" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
			</div>
			<div class="aips-toolbar-right">
				<button type="button" id="aips-reload-posts-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>

		<div class="aips-panel-body no-padding">
	<table class="wp-list-table widefat fixed striped aips-post-review-table">
		<thead>
			<tr>
				<th id="cb" class="manage-column column-cb check-column">
					<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All', 'ai-post-scheduler'); ?></label>
					<input id="cb-select-all-1" type="checkbox">
				</th>
				<th class="column-title"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></th>
				<th class="column-preview" style="width: 60px; text-align: center;"><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></th>
				<th class="column-source"><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
				<th class="column-date"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></th>
				<th class="column-modified"><?php esc_html_e('Modified', 'ai-post-scheduler'); ?></th>
				<th class="column-actions"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($draft_posts['items'] as $item): ?>
			<tr data-post-id="<?php echo esc_attr($item->post_id); ?>" data-history-id="<?php echo esc_attr($item->id); ?>">
				<th scope="row" class="check-column">
					<label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->post_id); ?>"><?php esc_html_e('Select Post', 'ai-post-scheduler'); ?></label>
					<input id="cb-select-<?php echo esc_attr($item->post_id); ?>" type="checkbox" class="aips-post-checkbox" 
						   value="<?php echo esc_attr($item->post_id); ?>" 
						   data-post-id="<?php echo esc_attr($item->post_id); ?>"
						   data-history-id="<?php echo esc_attr($item->id); ?>">
					</th>
					<td class="column-title">
						<strong>
							<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" target="_blank">
								<?php echo esc_html($item->post_title ?: $item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
							</a>
						</strong>
					</td>
					<td class="column-preview" style="text-align: center;">
						<span class="aips-preview-trigger dashicons dashicons-visibility" 
						  data-post-id="<?php echo esc_attr($item->post_id); ?>"
						  title="<?php esc_attr_e('Hover to preview this post', 'ai-post-scheduler'); ?>"
						  style="cursor: pointer; font-size: 20px; color: #2271b1;">
						</span>
					</td>
					<td class="column-source">
						<?php echo esc_html( $controller->format_source( $item ) ); ?>
					</td>
					<td class="column-date">
						<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->created_at))); ?>
					</td>
					<td class="column-modified">
						<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($item->post_modified))); ?>
					</td>
					<td class="column-actions">
						<div class="aips-action-buttons">
							<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" 
						   class="button button-small" 
						   target="_blank"
						   title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
							</a>
							<button type="button"
								class="button button-small aips-preview-post"
								data-post-id="<?php echo esc_attr($item->post_id); ?>"
								title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('Preview', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" 
								class="button button-small aips-ai-edit-btn" 
								data-post-id="<?php echo esc_attr($item->post_id); ?>"
								data-history-id="<?php echo esc_attr($item->id); ?>"
								title="<?php esc_attr_e('AI Edit - Regenerate components', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" 
								class="button button-small aips-view-session" 
								data-history-id="<?php echo esc_attr($item->id); ?>"
								title="<?php esc_attr_e('View generation session', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" 
								class="button button-primary button-small aips-publish-post" 
								data-post-id="<?php echo esc_attr($item->post_id); ?>"
								title="<?php esc_attr_e('Publish this post', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" 
								class="button button-small aips-regenerate-post" 
								data-history-id="<?php echo esc_attr($item->id); ?>"
								data-post-id="<?php echo esc_attr($item->post_id); ?>"
								title="<?php esc_attr_e('Regenerate this post', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
							</button>
							<button type="button" 
								class="button button-small button-link-delete aips-delete-post" 
								data-post-id="<?php echo esc_attr($item->post_id); ?>"
								data-history-id="<?php echo esc_attr($item->id); ?>"
								title="<?php esc_attr_e('Delete this post', 'ai-post-scheduler'); ?>">
								<?php esc_html_e('Delete', 'ai-post-scheduler'); ?>
							</button>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		</div><!-- .aips-panel-body -->
	</form>
	<?php else: ?>
	<div class="aips-panel-body">
		<div class="aips-empty-state">
			<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<h3><?php esc_html_e('No Draft Posts', 'ai-post-scheduler'); ?></h3>
			<p><?php esc_html_e('There are no draft posts waiting for review. All generated posts have been published or deleted.', 'ai-post-scheduler'); ?></p>
		</div>
	</div>
	<?php endif; ?>
	<!-- Table footer -->
	<div class="tablenav">
		<span class="aips-table-footer-count">
			<?php printf( esc_html( _n( '%d draft', '%d drafts', $draft_posts['total'], 'ai-post-scheduler' ) ), $draft_posts['total'] ); ?>
		</span>
		<?php if ($draft_posts['pages'] > 1): ?>
		<div class="tablenav-pages">
			<span class="pagination-links">
				<?php
				$base_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
				if ($template_id) {
					$base_url .= '&template_id=' . $template_id;
				}
				if ($search_query) {
					$base_url .= '&s=' . urlencode($search_query);
				}
				$hash_fragment = '#aips-pending-review';
				if ($review_current_page > 1): ?>
				<a class="prev-page button" href="<?php echo esc_url($base_url . '&review_paged=' . ($review_current_page - 1) . $hash_fragment); ?>">
					<span class="screen-reader-text"><?php esc_html_e('Previous page', 'ai-post-scheduler'); ?></span>
					<span aria-hidden="true">&lsaquo;</span>
				</a>
				<?php endif; ?>
				<span class="paging-input">
					<span class="tablenav-paging-text">
						<?php echo esc_html($review_current_page); ?>
						<?php esc_html_e('of', 'ai-post-scheduler'); ?>
						<span class="total-pages"><?php echo esc_html($draft_posts['pages']); ?></span>
					</span>
				</span>
				<?php if ($review_current_page < $draft_posts['pages']): ?>
				<a class="next-page button" href="<?php echo esc_url($base_url . '&review_paged=' . ($review_current_page + 1) . $hash_fragment); ?>">
					<span class="screen-reader-text"><?php esc_html_e('Next page', 'ai-post-scheduler'); ?></span>
					<span aria-hidden="true">&rsaquo;</span>
				</a>
				<?php endif; ?>
			</span>
		</div>
		<?php endif; ?>
	</div>
		</div><!-- .aips-content-panel -->
	</div><!-- .aips-tab-content -->
