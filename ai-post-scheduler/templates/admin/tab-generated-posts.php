<?php
/**
 * Generated Posts Tab Template
 *
 * Tab 1 panel for the Content admin page.
 * Displays published/completed AI-generated posts.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $authors
 * @var array $templates
 * @var int $author_id
 * @var int $template_id
 * @var string $search_query
 * @var array $posts_data
 * @var array $history
 * @var int $current_page
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
							<label class="screen-reader-text" for="aips-filter-author"><?php esc_html_e('Filter by Author:', 'ai-post-scheduler'); ?></label>
							<select name="author_id" id="aips-filter-author" class="aips-form-select">
								<option value=""><?php esc_html_e('All Authors', 'ai-post-scheduler'); ?></option>
								<?php foreach ($authors as $a): ?>
								<option value="<?php echo esc_attr($a->id); ?>" <?php selected($author_id, $a->id); ?>>
									<?php echo esc_html($a->name); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							<?php if (!empty($templates)): ?>
							<label class="screen-reader-text" for="aips-filter-template-generated"><?php esc_html_e('Filter by Template:', 'ai-post-scheduler'); ?></label>
							<select name="template_id" id="aips-filter-template-generated" class="aips-form-select">
								<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
								<?php foreach ($templates as $template): ?>
								<option value="<?php echo esc_attr($template->id); ?>" <?php selected($template_id, $template->id); ?>>
									<?php echo esc_html($template->name); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
							<button type="submit" id="aips-filter-submit" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-filter"></span>
								<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($author_id) || !empty($template_id)): ?>
							<a href="<?php echo esc_url(remove_query_arg(array('author_id', 'template_id'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear Filters', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</div>
						<div class="aips-filter-right">
							<label class="screen-reader-text" for="post-search-input"><?php esc_html_e('Search Posts:', 'ai-post-scheduler'); ?></label>
							<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
							<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($search_query)): ?>
							<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</div>
					</form>
				</div>

				<!-- Generated posts table -->
				<div class="aips-panel-body no-padding">
					<?php if (!empty($posts_data)): ?>
					<table class="aips-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Published', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Generated', 'ai-post-scheduler'); ?></th>
								<th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($posts_data as $post_data): ?>
							<tr>
								<td>
									<a href="<?php echo esc_url($post_data['edit_link']); ?>" class="cell-primary">
										<?php echo esc_html($post_data['title']); ?>
									</a>
								</td>
								<td>
									<span class="aips-badge aips-badge-neutral">
										<?php echo esc_html($post_data['source']); ?>
									</span>
								</td>
								<td>
									<div class="cell-meta">
										<?php 
										if ($post_data['date_scheduled']) {
											echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post_data['date_scheduled'])));
										} else {
											echo '—';
										}
										?>
									</div>
								</td>
								<td>
									<div class="cell-meta">
										<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post_data['date_published']))); ?>
									</div>
								</td>
								<td>
									<div class="cell-meta">
										<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($post_data['date_generated']))); ?>
									</div>
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
							<p class="aips-empty-state-description"><?php esc_html_e('No generated posts match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
							<div class="aips-empty-state-actions">
								<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
								</a>
							</div>
						</div>
						<?php else: ?>
						<div class="aips-empty-state">
							<div class="dashicons dashicons-admin-post aips-empty-state-icon" aria-hidden="true"></div>
							<h3 class="aips-empty-state-title"><?php esc_html_e('No Generated Posts', 'ai-post-scheduler'); ?></h3>
							<p class="aips-empty-state-description"><?php esc_html_e('No generated posts found. Start creating content by setting up templates and schedules.', 'ai-post-scheduler'); ?></p>
							<div class="aips-empty-state-actions">
								<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-primary">
									<span class="dashicons dashicons-plus-alt"></span>
									<?php esc_html_e('Create Template', 'ai-post-scheduler'); ?>
								</a>
								<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('schedule')); ?>" class="aips-btn aips-btn-secondary">
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php esc_html_e('Manage Schedules', 'ai-post-scheduler'); ?>
								</a>
							</div>
						</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<!-- Table footer -->
				<div class="tablenav">
					<span class="aips-table-footer-count">
						<?php printf( esc_html( _n( '%s post', '%s posts', $history['total'], 'ai-post-scheduler' ) ), number_format_i18n( $history['total'] ) ); ?>
					</span>
					<?php if ($history['pages'] > 1): ?>
					<?php
					$current = (int) $current_page;
					$pages = (int) $history['pages'];
					$start = max(1, $current - 3);
					$end = min($pages, $current + 3);
					$base_url = AIPS_Admin_Menu_Helper::get_page_url('generated_posts');
					$build_generated_posts_page_url = static function($page_number) use ($base_url, $author_id, $template_id, $search_query) {
						return add_query_arg(array_filter(array(
							'generated_paged' => absint($page_number),
							'author_id' => $author_id ? $author_id : false,
							'template_id' => $template_id ? $template_id : false,
							's' => $search_query ? $search_query : false,
						)), $base_url);
					};
					?>
					<div class="aips-history-pagination-links">
						<?php if ($current > 1): ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" href="<?php echo esc_url($build_generated_posts_page_url($current - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
							</a>
						<?php else: ?>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-prev" disabled aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-left-alt2"></span>
							</button>
						<?php endif; ?>

						<span class="aips-history-page-numbers">
							<?php if ($start > 1): ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url(1)); ?>">1</a>
								<?php if ($start > 2): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
							<?php endif; ?>

							<?php for ($p = $start; $p <= $end; $p++): ?>
								<?php if ($p === $current): ?>
									<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo esc_html($p); ?></span>
								<?php else: ?>
									<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url($p)); ?>"><?php echo esc_html($p); ?></a>
								<?php endif; ?>
							<?php endfor; ?>

							<?php if ($end < $pages): ?>
								<?php if ($end < $pages - 1): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_generated_posts_page_url($pages)); ?>"><?php echo esc_html($pages); ?></a>
							<?php endif; ?>
						</span>

						<?php if ($current < $pages): ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" href="<?php echo esc_url($build_generated_posts_page_url($current + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</a>
						<?php else: ?>
							<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-next" disabled aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
								<span class="dashicons dashicons-arrow-right-alt2"></span>
							</button>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>

