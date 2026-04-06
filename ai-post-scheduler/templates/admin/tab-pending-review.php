<?php
/**
 * Pending Review Tab Template
 *
 * Tab 3 panel for the Content admin page.
 * Displays draft posts awaiting review before publishing.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $templates
 * @var int $template_id
 * @var string $search_query
 * @var array $draft_posts
 * @var int $review_current_page
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>
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
							<?php if (!empty($template_id)): ?>
							<a href="<?php echo esc_url(remove_query_arg('template_id')); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear Filters', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
							<?php endif; ?>
						</div>
						<div class="aips-filter-right">
							<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('review_workflow')); ?>" class="aips-btn aips-btn-sm aips-btn-primary" style="margin-right: 8px;">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e('Open Review Workflow', 'ai-post-scheduler'); ?>
							</a>
							<label class="screen-reader-text" for="aips-post-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
							<input type="search" id="aips-post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
							<button type="submit" id="aips-post-search-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($search_query)): ?>
							<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
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
						<table class="aips-table">
							<thead>
								<tr>
									<th scope="col" style="width: 30px;">
										<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e('Select All', 'ai-post-scheduler'); ?></label>
										<input id="cb-select-all-1" type="checkbox">
									</th>
									<th scope="col"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></th>
									<th scope="col"><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
									<th scope="col"><?php esc_html_e('Created', 'ai-post-scheduler'); ?></th>
									<th scope="col"><?php esc_html_e('Modified', 'ai-post-scheduler'); ?></th>
									<th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($draft_posts['items'] as $item): ?>
								<tr data-post-id="<?php echo esc_attr($item->post_id); ?>" data-history-id="<?php echo esc_attr($item->id); ?>">
									<td>
										<label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->post_id); ?>"><?php esc_html_e('Select Post', 'ai-post-scheduler'); ?></label>
										<input id="cb-select-<?php echo esc_attr($item->post_id); ?>" type="checkbox" class="aips-post-checkbox"
											value="<?php echo esc_attr($item->post_id); ?>"
											data-post-id="<?php echo esc_attr($item->post_id); ?>"
											data-history-id="<?php echo esc_attr($item->id); ?>">
									</td>
									<td>
										<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary" target="_blank">
											<?php echo esc_html($item->post_title ?: $item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
										</a>
									</td>
									<td>
										<span class="aips-badge aips-badge-neutral">
											<?php echo esc_html($controller->format_source($item)); ?>
										</span>
									</td>
									<td>
										<div class="cell-meta">
											<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at))); ?>
										</div>
									</td>
									<td>
										<div class="cell-meta">
											<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->post_modified))); ?>
										</div>
									</td>
									<td>
										<div class="cell-actions">
											<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>"
												class="aips-btn aips-btn-sm aips-btn-secondary"
												target="_blank"
												title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-edit"></span>
												<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
											</a>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-preview-post"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e('Preview', 'ai-post-scheduler'); ?>
											</button>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-edit-btn"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												title="<?php esc_attr_e('AI Edit - Regenerate components', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-admin-customizer"></span>
												<?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?>
											</button>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-view-session"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												title="<?php esc_attr_e('View generation session', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
											</button>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('Publish this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-upload"></span>
												<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>
											</button>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-secondary aips-regenerate-post"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('Regenerate this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-update"></span>
												<?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?>
											</button>
											<button type="button"
												class="aips-btn aips-btn-sm aips-btn-danger aips-delete-post"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												title="<?php esc_attr_e('Delete this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-trash"></span>
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
					<?php if (!empty($search_query)): ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Found', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('No draft posts match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-primary">
								<span class="dashicons dashicons-dismiss"></span>
								<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
							</a>
						</div>
					</div>
					<?php else: ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-yes-alt aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Draft Posts', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('There are no draft posts waiting for review. All generated posts have been published or deleted.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-secondary">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
							</a>
						</div>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				
				<!-- Table footer -->
				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php printf( esc_html( _n( '%d draft', '%d drafts', $draft_posts['total'], 'ai-post-scheduler' ) ), $draft_posts['total'] ); ?>
					</span>
					<?php if ($draft_posts['pages'] > 1): ?>
					<?php
					$review_base_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
					$build_review_page_url = static function($page_number) use ($review_base_url, $template_id, $search_query) {
						return add_query_arg(array_filter(array(
							'review_paged' => absint($page_number),
							'template_id'  => $template_id ? $template_id : false,
							's'            => $search_query ? $search_query : false,
						)), $review_base_url) . '#aips-pending-review';
					};
					$review_start = max(1, $review_current_page - 3);
					$review_end   = min($draft_posts['pages'], $review_current_page + 3);
					?>
					<div class="aips-history-pagination-links">
						<?php if ($review_current_page > 1): ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_review_page_url($review_current_page - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
							</a>
						<?php else: ?>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" disabled aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
							</button>
						<?php endif; ?>

						<span class="aips-history-page-numbers">
							<?php if ($review_start > 1): ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_review_page_url(1)); ?>">1</a>
								<?php if ($review_start > 2): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
							<?php endif; ?>

							<?php for ($p = $review_start; $p <= $review_end; $p++): ?>
								<?php if ($p === $review_current_page): ?>
									<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo esc_html($p); ?></span>
								<?php else: ?>
									<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_review_page_url($p)); ?>"><?php echo esc_html($p); ?></a>
								<?php endif; ?>
							<?php endfor; ?>

							<?php if ($review_end < $draft_posts['pages']): ?>
								<?php if ($review_end < $draft_posts['pages'] - 1): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_review_page_url($draft_posts['pages'])); ?>"><?php echo esc_html($draft_posts['pages']); ?></a>
							<?php endif; ?>
						</span>

						<?php if ($review_current_page < $draft_posts['pages']): ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_review_page_url($review_current_page + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						<?php else: ?>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" disabled aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</button>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

