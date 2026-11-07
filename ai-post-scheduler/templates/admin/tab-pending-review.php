<?php
/**
 * Pending Review Tab Template
 *
 * Tab 3 panel for the Content admin page.
 * Displays draft posts awaiting review before publishing as interactive cards.
 *
 * @var AIPS_Generated_Posts_Controller      $controller
 * @var AIPS_Pending_Review_List_Table        $review_table
 * @var array  $templates
 * @var int    $template_id
 * @var string $search_query
 * @var array  $selectable_post_types
 * @var string $post_type_filter
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$review_total = $review_table->get_total_items();
?>
					<!-- Bulk Actions & Cards Toolbar -->
					<div class="aips-panel-toolbar aips-pending-toolbar" <?php if (!$review_table->has_items()): ?>style="display:none;"<?php endif; ?>>
						<div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
							<label class="aips-card-select-all-label">
								<input type="checkbox" id="aips-select-all-cards" class="aips-checkbox" aria-label="<?php esc_attr_e('Select all posts', 'ai-post-scheduler'); ?>" />
								<span><?php esc_html_e('Select All', 'ai-post-scheduler'); ?></span>
							</label>
							<select name="bulk_action" id="bulk-action-selector-top" class="aips-form-select" style="width: auto;">
								<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
								<option value="publish"><?php esc_html_e('Publish', 'ai-post-scheduler'); ?></option>
								<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
							</select>
							<button type="button" id="aips-bulk-action-btn" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Apply', 'ai-post-scheduler'); ?></button>
							<button type="button" id="aips-reload-posts-btn" class="aips-btn aips-btn-sm aips-btn-secondary" title="<?php esc_attr_e('Reload posts', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php esc_html_e('Reload', 'ai-post-scheduler'); ?>
							</button>
						</div>
						<div class="aips-toolbar-right aips-pending-count-badge">
							<span class="aips-count-text">
								<?php esc_html_e('Showing', 'ai-post-scheduler'); ?>
								<strong id="aips-pending-loaded-count"><?php echo count($review_table->items); ?></strong>
								<?php esc_html_e('of', 'ai-post-scheduler'); ?>
								<strong id="aips-pending-total-count"><?php echo esc_html((string) $review_total); ?></strong>
								<?php esc_html_e('pending posts', 'ai-post-scheduler'); ?>
							</span>
						</div>
					</div>

				<!-- Filter Bar -->
					<div class="aips-filter-bar" data-total="<?php echo esc_attr((int) $review_total); ?>">
						<form method="get" class="aips-post-review-filters aips-filter-form" id="aips-pending-filter-form">
							<input type="hidden" name="page" value="aips-generated-posts">
							<input type="hidden" name="tab" value="aips-pending-review">
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
								<?php endif; ?>
								<?php if (!empty($selectable_post_types)): ?>
								<label class="screen-reader-text" for="aips-filter-post-type-review"><?php esc_html_e('Filter by Post Type:', 'ai-post-scheduler'); ?></label>
								<select name="post_type" id="aips-filter-post-type-review" class="aips-form-select">
									<option value=""><?php esc_html_e('All Post Types', 'ai-post-scheduler'); ?></option>
									<?php foreach ($selectable_post_types as $post_type_key => $post_type_info): ?>
									<option value="<?php echo esc_attr($post_type_key); ?>" <?php selected($post_type_filter, $post_type_key); ?>>
										<?php echo esc_html($post_type_info['label']); ?>
									</option>
									<?php endforeach; ?>
								</select>
								<?php endif; ?>
								<button type="submit" class="aips-btn aips-btn-sm aips-btn-primary aips-history-filter-apply">
									<span class="dashicons dashicons-filter" aria-hidden="true"></span>
									<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
								</button>
								<a href="<?php echo esc_url(remove_query_arg(array('template_id', 'post_type', 'paged', 's'))); ?>" id="aips-pending-clear-filters" class="aips-btn aips-btn-sm aips-btn-ghost<?php if (empty($template_id) && empty($post_type_filter) && empty($search_query)) echo ' aips-hidden'; ?>" title="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></a>
							</div>
							<div class="aips-filter-right">
								<label class="screen-reader-text" for="aips-post-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
								<input type="search" id="aips-post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>" autocomplete="off">
								<button type="submit" id="aips-post-search-btn" class="aips-btn aips-btn-sm aips-btn-secondary">
									<span class="dashicons dashicons-search"></span>
									<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
								</button>
							</div>
						</form>
					</div>

					<div id="aips-pending-posts-wrap" class="aips-pending-posts-wrap" <?php if (!$review_table->has_items()): ?>style="display:none;"<?php endif; ?>>
						<div id="aips-pending-posts-grid" class="aips-pending-posts-grid"
							data-total-pages="<?php echo esc_attr((int) $review_table->get_pagination_arg('total_pages')); ?>"
							data-current-page="<?php echo esc_attr((int) $review_table->get_pagenum()); ?>"
							data-per-page="<?php echo esc_attr((int) $review_table->get_pagination_arg('per_page')); ?>">
							<?php foreach ($review_table->items as $item): ?>
							<div class="aips-post-card" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-history-id="<?php echo esc_attr($item->id); ?>">
								<div class="aips-card-banner">
									<?php if (!empty($item->featured_image)): ?>
										<div class="aips-card-image aips-preview-post" data-post-id="<?php echo esc_attr($item->post_id); ?>" role="button" tabindex="0" title="<?php esc_attr_e('Preview post', 'ai-post-scheduler'); ?>">
											<img src="<?php echo esc_url($item->featured_image); ?>" alt="<?php echo esc_attr($item->post_title); ?>" loading="lazy" />
										</div>
									<?php else: ?>
										<div class="aips-card-image aips-card-image-placeholder aips-preview-post" data-post-id="<?php echo esc_attr($item->post_id); ?>" role="button" tabindex="0" title="<?php esc_attr_e('Preview post', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
										</div>
									<?php endif; ?>

									<div class="aips-card-checkbox-wrap">
										<input type="checkbox" class="aips-post-checkbox" value="<?php echo esc_attr($item->post_id); ?>" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-history-id="<?php echo esc_attr($item->id); ?>" aria-label="<?php esc_attr_e('Select post', 'ai-post-scheduler'); ?>" />
									</div>

									<div class="aips-card-type-badge">
										<span class="aips-badge aips-badge-neutral"><?php echo esc_html($item->post_type_label ?? 'Post'); ?></span>
									</div>
								</div>

								<div class="aips-card-body">
									<div class="aips-card-meta">
										<?php if (!empty($item->source)): ?>
											<span class="aips-card-source" title="<?php echo esc_attr($item->source); ?>"><?php echo esc_html($item->source); ?></span>
											<span class="aips-card-meta-sep">•</span>
										<?php endif; ?>
										<span class="aips-card-date"><?php echo esc_html($item->created_at_formatted); ?></span>
									</div>

									<h3 class="aips-card-title">
										<a href="#" class="aips-preview-post" data-post-id="<?php echo esc_attr($item->post_id); ?>" title="<?php esc_attr_e('Preview post', 'ai-post-scheduler'); ?>">
											<?php echo esc_html($item->post_title); ?>
										</a>
									</h3>

									<div class="aips-card-snippet">
										<?php echo esc_html($item->snippet ?? ''); ?>
									</div>
								</div>

								<div class="aips-card-footer">
									<div class="aips-card-footer-left">
										<button type="button"
											class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post"
											data-post-id="<?php echo esc_attr($item->post_id); ?>"
											title="<?php esc_attr_e('Publish this post immediately', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-upload"></span>
											<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>
										</button>
										<a href="<?php echo esc_url($item->edit_url ?? get_edit_post_link($item->post_id)); ?>" class="aips-card-edit-link" target="_blank" rel="noopener">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e('Edit Post', 'ai-post-scheduler'); ?>
										</a>
									</div>
									<div class="aips-card-footer-right">
										<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
											aria-haspopup="true"
											aria-expanded="false"
											aria-controls="aips-review-card-actions-<?php echo esc_attr($item->post_id); ?>"
											title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-ellipsis"></span>
											<span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
										</button>
										<div id="aips-review-card-actions-<?php echo esc_attr($item->post_id); ?>" class="aips-row-action-menu" hidden>
											<button type="button" class="aips-row-action-item aips-preview-post"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-visibility"></span>
												<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
											</button>
											<button type="button" class="aips-row-action-item aips-ai-edit-btn"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												title="<?php esc_attr_e('AI Edit - Regenerate components', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-admin-customizer"></span>
												<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
											</button>
											<a class="aips-row-action-item aips-open-history-modal"
												href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('history', array_filter(array('history_id' => $item->id, 'post_id' => $item->post_id)))); ?>"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('View history for this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-backup"></span>
												<span><?php esc_html_e('View History', 'ai-post-scheduler'); ?></span>
											</a>
											<button type="button" class="aips-row-action-item aips-regenerate-post"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												title="<?php esc_attr_e('Regenerate this post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-update"></span>
												<span><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
											</button>
											<button type="button" class="aips-row-action-item aips-delete-post aips-danger-action"
												data-post-id="<?php echo esc_attr($item->post_id); ?>"
												data-history-id="<?php echo esc_attr($item->id); ?>"
												title="<?php esc_attr_e('Delete this draft post', 'ai-post-scheduler'); ?>">
												<span class="dashicons dashicons-trash"></span>
												<span><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></span>
											</button>
										</div>
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>

						<div id="aips-pending-load-more-wrap" class="aips-pending-load-more-wrap" <?php if ((int) $review_table->get_pagination_arg('total_pages') <= 1): ?>style="display:none;"<?php endif; ?>>
							<button type="button" id="aips-pending-load-more-btn" class="aips-btn aips-btn-secondary aips-btn-lg">
								<span class="dashicons dashicons-update aips-load-more-spinner" aria-hidden="true"></span>
								<span class="aips-load-more-text"><?php esc_html_e('Load More Posts', 'ai-post-scheduler'); ?></span>
							</button>
						</div>
					</div>

					<div class="aips-panel-body aips-pending-empty-state-wrap" id="aips-pending-empty-state-wrap" <?php if ($review_table->has_items()): ?>style="display:none;"<?php endif; ?>>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-search aips-empty-state-icon" id="aips-empty-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title" id="aips-empty-title">
								<?php echo !empty($search_query) ? esc_html__('No Posts Found', 'ai-post-scheduler') : esc_html__('No Draft Posts', 'ai-post-scheduler'); ?>
							</h3>
							<p class="aips-empty-state-description" id="aips-empty-desc">
								<?php echo !empty($search_query)
									? esc_html__('No draft posts match your search criteria. Try a different search term.', 'ai-post-scheduler')
									: esc_html__('There are no draft posts waiting for review. All generated posts have been published or deleted.', 'ai-post-scheduler'); ?>
							</p>
							<div class="aips-empty-state-actions" id="aips-empty-actions">
								<?php if (!empty($search_query)): ?>
								<a href="<?php echo esc_url(remove_query_arg(array('s', 'paged'))); ?>" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
								</a>
								<?php else: ?>
								<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-secondary">
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
								</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
