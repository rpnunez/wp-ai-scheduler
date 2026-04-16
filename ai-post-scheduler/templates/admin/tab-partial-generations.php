<?php
/**
 * Partial Generations Tab Template
 *
 * Tab 2 panel for the Content admin page.
 * Displays posts with one or more missing generated components.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $authors
 * @var array $templates
 * @var int $author_id
 * @var int $template_id
 * @var string $search_query
 * @var array $partial_posts_data
 * @var array $partial_generations
 * @var int $partial_current_page
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
					<form method="get" class="search-form aips-filter-form">
						<input type="hidden" name="page" value="aips-generated-posts">
						<div class="aips-filter-left">
							<?php if (!empty($authors)): ?>
							<label class="screen-reader-text" for="aips-filter-author-partial"><?php esc_html_e('Filter by Author:', 'ai-post-scheduler'); ?></label>
							<select name="author_id" id="aips-filter-author-partial" class="aips-form-select">
								<option value=""><?php esc_html_e('All Authors', 'ai-post-scheduler'); ?></option>
								<?php foreach ($authors as $a): ?>
								<option value="<?php echo esc_attr($a->id); ?>" <?php selected($author_id, $a->id); ?>>
									<?php echo esc_html($a->name); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							<?php if (!empty($templates)): ?>
							<label class="screen-reader-text" for="aips-filter-template-partial"><?php esc_html_e('Filter by Template:', 'ai-post-scheduler'); ?></label>
							<select name="template_id" id="aips-filter-template-partial" class="aips-form-select">
								<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
								<?php foreach ($templates as $template): ?>
								<option value="<?php echo esc_attr($template->id); ?>" <?php selected($template_id, $template->id); ?>>
									<?php echo esc_html($template->name); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-filter"></span>
								<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($author_id) || !empty($template_id)): ?>
							<a href="<?php echo esc_url(remove_query_arg(array('author_id', 'template_id'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear Filters', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</div>
						<div class="aips-filter-right">
							<label class="screen-reader-text" for="aips-partial-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
							<input type="search" id="aips-partial-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
							<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($search_query)): ?>
							<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</div>
					</form>
				</div>

				<!-- Partial Generations table -->
				<div class="aips-panel-body no-padding">
					<?php if (!empty($partial_posts_data)): ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Missing Components', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('State', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Updated', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Generated', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($partial_posts_data as $post_data): ?>
							<tr>
								<td>
									<a href="<?php echo esc_url($post_data['edit_link']); ?>" class="cell-primary">
										<?php echo esc_html($post_data['title']); ?>
									</a>
								</td>
								<td>
									<?php if (!empty($post_data['missing_components'])): ?>
										<?php foreach ($post_data['missing_components'] as $component_label): ?>
										<span class="aips-badge aips-badge-warning"><?php echo esc_html($component_label); ?></span>
										<?php endforeach; ?>
									<?php else: ?>
										<span class="aips-badge aips-badge-success"><?php esc_html_e('Resolved', 'ai-post-scheduler'); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($post_data['is_currently_incomplete'])): ?>
										<span class="aips-badge aips-badge-warning"><?php esc_html_e('Incomplete', 'ai-post-scheduler'); ?></span>
									<?php else: ?>
										<span class="aips-badge aips-badge-success"><?php esc_html_e('Resolved', 'ai-post-scheduler'); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="aips-badge aips-badge-neutral">
										<?php echo esc_html($post_data['source']); ?>
									</span>
								</td>
								<td>
									<div class="cell-meta"><?php echo esc_html($controller->format_post_status($post_data['post_status'])); ?></div>
								</td>
								<td>
									<div class="cell-meta"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post_data['date_updated']))); ?></div>
								</td>
								<td>
									<div class="cell-meta"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post_data['date_generated']))); ?></div>
								</td>
								<td>
									<div class="cell-actions">
										<a href="<?php echo esc_url($post_data['edit_link']); ?>" class="aips-btn aips-btn-sm aips-btn-secondary">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
										</a>
										<button class="aips-btn aips-btn-sm aips-btn-secondary aips-ai-edit-btn"
											data-post-id="<?php echo esc_attr($post_data['post_id']); ?>"
											data-history-id="<?php echo esc_attr($post_data['history_id']); ?>"
											title="<?php esc_attr_e('AI Edit', 'ai-post-scheduler'); ?>">
											<span class="dashicons dashicons-admin-customizer"></span>
											<?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?>
										</button>
								<button class="aips-btn aips-btn-sm aips-btn-secondary aips-view-session"
									data-history-id="<?php echo esc_attr($post_data['history_id']); ?>"
									title="<?php esc_attr_e('View Session', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-visibility"></span>
									<?php esc_html_e('View Session', 'ai-post-scheduler'); ?>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else: ?>
						<?php if (!empty($search_query)): ?>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Found', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No partial generations match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
							<div class="aips-empty-state-actions">
								<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
								</a>
							</div>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-saved aips-empty-state-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Partial Generations', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('Posts with missing generated components will appear here so you can review and repair them.', 'ai-post-scheduler'); ?></p>
							<div class="aips-empty-state-actions">
								<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
								</a>
								<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('generated_posts')); ?>" class="aips-btn aips-btn-secondary">
									<span class="dashicons dashicons-admin-post"></span>
									<?php esc_html_e('View Generated Posts', 'ai-post-scheduler'); ?>
								</a>
							</div>
						</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<!-- Table footer -->
				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php printf( esc_html( _n( '%s post', '%s posts', $partial_generations['total'], 'ai-post-scheduler' ) ), number_format_i18n( $partial_generations['total'] ) ); ?>
					</span>
					<?php if ($partial_generations['pages'] > 1): ?>
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links(array(
							'base' => add_query_arg('partial_paged', '%#%'),
							'format' => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total' => $partial_generations['pages'],
							'current' => $partial_current_page,
							'add_fragment' => '#aips-partial-generations',
							'add_args' => array_filter(array(
								'author_id' => $author_id ? $author_id : false,
								'template_id' => $template_id ? $template_id : false,
								's' => $search_query ? $search_query : false,
							)),
						));
						if ($page_links) {
							echo wp_kses_post($page_links);
						}
						?>
					</div>
					<?php endif; ?>
				</div>

