<?php
/**
 * Pending Review Tab Template
 *
 * Tab 3 panel for the Content admin page.
 * Displays draft posts awaiting review before publishing.
 *
 * Rendering goes through AIPS_Pending_Review_List_Table so the tab exposes native
 * WordPress Screen Options (drafts-per-page + Type/Created column show/hide). The
 * filter bar, bulk-action toolbar, and footer count are kept around the table so
 * the existing AJAX behaviour (admin-post-review.js) is unchanged.
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
				<!-- Filter Bar -->
					<div class="aips-filter-bar">
						<form method="get" class="aips-post-review-filters aips-filter-form">
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
								<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
									<span class="dashicons dashicons-filter"></span>
									<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
								</button>
								<?php if (!empty($template_id) || !empty($post_type_filter)): ?>
								<a href="<?php echo esc_url(remove_query_arg(array('template_id', 'post_type', 'paged'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear filters', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></a>
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
								<a href="<?php echo esc_url(remove_query_arg(array('s', 'paged'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost" title="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>" aria-label="<?php esc_attr_e('Clear search', 'ai-post-scheduler'); ?>"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span></a>
								<?php endif; ?>
							</div>
						</form>
					</div>

					<?php if ($review_table->has_items()): ?>
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
							<?php $review_table->display(); ?>
						</div><!-- .aips-panel-body -->
					</form>

					<!-- Table footer: total feeds the header count chip via JS. -->
					<div class="tablenav">
						<span class="aips-table-footer-count" data-total="<?php echo esc_attr((int) $review_total); ?>">
							<?php printf( esc_html( _n( '%d draft', '%d drafts', $review_total, 'ai-post-scheduler' ) ), (int) $review_total ); ?>
						</span>
					</div>
					<?php else: ?>
					<div class="aips-panel-body">
						<?php if (!empty($search_query)): ?>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Found', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No draft posts match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
							<div class="aips-empty-state-actions">
								<a href="<?php echo esc_url(remove_query_arg(array('s', 'paged'))); ?>" class="aips-btn aips-btn-primary">
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
