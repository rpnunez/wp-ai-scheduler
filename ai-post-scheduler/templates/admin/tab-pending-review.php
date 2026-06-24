<?php
/**
 * Pending Review Tab Template
 *
 * Tab 3 panel for the Content admin page.
 * Displays draft/pending posts awaiting review before publishing.
 *
 * @var AIPS_Generated_Posts_Controller $controller
 * @var array $templates
 * @var int $template_id
 * @var string $search_query
 * @var string $review_status
 * @var array $draft_posts
 * @var int $review_current_page
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

$review_status = isset($review_status) ? $review_status : '';
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
							<?php endif; ?>

							<label class="screen-reader-text" for="aips-filter-review-status"><?php esc_html_e('Filter by Review Status:', 'ai-post-scheduler'); ?></label>
							<select name="review_status" id="aips-filter-review-status" class="aips-form-select">
								<option value=""><?php esc_html_e('All Posts', 'ai-post-scheduler'); ?></option>
								<option value="needs_revision" <?php selected($review_status, 'needs_revision'); ?>><?php esc_html_e('Needs Revision', 'ai-post-scheduler'); ?></option>
								<option value="clean" <?php selected($review_status, 'clean'); ?>><?php esc_html_e('No Flags', 'ai-post-scheduler'); ?></option>
							</select>

							<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
								<span class="dashicons dashicons-filter"></span>
								<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
							</button>
							<?php if (!empty($template_id) || !empty($review_status)): ?>
							<a href="<?php echo esc_url(remove_query_arg(array('template_id', 'review_status'))); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear Filters', 'ai-post-scheduler'); ?></a>
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
							<a href="<?php echo esc_url(remove_query_arg('s')); ?>" class="aips-btn aips-btn-sm aips-btn-ghost"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
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
								<option value="regenerate"><?php esc_html_e('Regenerate', 'ai-post-scheduler'); ?></option>
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
									<th scope="col"><?php esc_html_e('Waiting', 'ai-post-scheduler'); ?></th>
									<th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($draft_posts['items'] as $item): ?>
								<?php
									$post_age_seconds = isset($item->created_at) ? (time() - strtotime($item->created_at)) : 0;
									$is_stale         = $post_age_seconds > 3 * DAY_IN_SECONDS;
									$wait_label       = isset($item->created_at) ? human_time_diff(strtotime($item->created_at), time()) : '';
									$is_needs_revision = isset($item->review_status) && $item->review_status === 'needs_revision';
									$review_note       = isset($item->review_note) ? $item->review_note : '';
								?>
								<tr data-post-id="<?php echo esc_attr($item->post_id); ?>" data-history-id="<?php echo esc_attr($item->id); ?>">
									<td>
										<label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->post_id); ?>"><?php esc_html_e('Select Post', 'ai-post-scheduler'); ?></label>
										<input id="cb-select-<?php echo esc_attr($item->post_id); ?>" type="checkbox" class="aips-post-checkbox"
											value="<?php echo esc_attr($item->post_id); ?>"
											data-post-id="<?php echo esc_attr($item->post_id); ?>"
											data-history-id="<?php echo esc_attr($item->id); ?>">
									</td>
									<td>
										<div class="aips-post-title-cell">
											<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>" class="cell-primary" target="_blank">
												<?php echo esc_html($item->post_title ?: $item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
											</a>
											<?php if ($is_needs_revision): ?>
											<span class="aips-badge aips-badge--warning"><?php esc_html_e('Needs Revision', 'ai-post-scheduler'); ?></span>
											<?php endif; ?>
										</div>
										<span class="aips-cell-source"><?php echo esc_html($controller->format_source($item)); ?></span>
										<!-- Review note display -->
										<div class="aips-review-note-wrap" data-post-id="<?php echo esc_attr($item->post_id); ?>">
											<div class="aips-review-note-display <?php echo $review_note ? '' : 'aips-review-note-empty'; ?>">
												<?php if ($review_note): ?>
												<span class="aips-review-note-text"><?php echo esc_html(wp_trim_words($review_note, 20, '…')); ?></span>
												<button type="button" class="aips-note-edit-btn aips-btn-link" data-post-id="<?php echo esc_attr($item->post_id); ?>" data-note="<?php echo esc_attr($review_note); ?>" title="<?php esc_attr_e('Edit note', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-edit" aria-hidden="true"></span>
													<span class="screen-reader-text"><?php esc_html_e('Edit note', 'ai-post-scheduler'); ?></span>
												</button>
												<?php else: ?>
												<button type="button" class="aips-note-add-btn aips-btn-link" data-post-id="<?php echo esc_attr($item->post_id); ?>" title="<?php esc_attr_e('Add reviewer note', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
													<?php esc_html_e('Add note', 'ai-post-scheduler'); ?>
												</button>
												<?php endif; ?>
											</div>
											<div class="aips-review-note-editor" style="display:none;">
												<textarea class="aips-note-textarea aips-form-input" rows="2" placeholder="<?php esc_attr_e('Add reviewer note…', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($review_note); ?></textarea>
												<div class="aips-note-editor-actions">
													<button type="button" class="aips-note-save-btn aips-btn aips-btn-sm aips-btn-primary" data-post-id="<?php echo esc_attr($item->post_id); ?>"><?php esc_html_e('Save note', 'ai-post-scheduler'); ?></button>
													<button type="button" class="aips-note-cancel-btn aips-btn aips-btn-sm aips-btn-secondary"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
												</div>
											</div>
										</div>
									</td>
									<td>
										<div class="cell-meta">
											<?php echo esc_html($item->created_at_formatted); ?>
										</div>
										<?php if ($wait_label): ?>
										<span class="aips-wait-time <?php echo $is_stale ? 'aips-wait-time--stale' : ''; ?>">
											<?php
											/* translators: %s: human-readable time difference */
											printf(esc_html__('%s ago', 'ai-post-scheduler'), esc_html($wait_label));
											?>
										</span>
										<?php endif; ?>
									</td>
									<td>
										<div class="cell-actions">
											<div class="aips-row-action-group">
												<button type="button"
													class="aips-btn aips-btn-sm aips-btn-primary aips-publish-post"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													title="<?php esc_attr_e('Publish this post now', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-upload"></span>
													<?php esc_html_e('Publish', 'ai-post-scheduler'); ?>
												</button>
												<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-row-action-overflow-toggle"
													aria-haspopup="true"
													aria-expanded="false"
													aria-controls="aips-review-row-actions-<?php echo esc_attr($item->post_id); ?>"
													title="<?php esc_attr_e('More actions', 'ai-post-scheduler'); ?>">
													<span class="screen-reader-text"><?php esc_html_e('More actions', 'ai-post-scheduler'); ?></span>
												</button>
											</div>
											<div id="aips-review-row-actions-<?php echo esc_attr($item->post_id); ?>" class="aips-row-action-menu" hidden>
												<button type="button" class="aips-row-action-item aips-schedule-publish-btn"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													data-post-title="<?php echo esc_attr($item->post_title ?: $item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>"
													title="<?php esc_attr_e('Schedule this post for a future date', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-calendar-alt"></span>
													<span><?php esc_html_e('Schedule…', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-row-action-item aips-edit-post"
													data-edit-url="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>"
													title="<?php esc_attr_e('Edit this post', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-edit"></span>
													<span><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-row-action-item aips-preview-post"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													title="<?php esc_attr_e('Preview this post', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-visibility"></span>
													<span><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-row-action-item aips-flag-needs-revision-btn <?php echo $is_needs_revision ? 'aips-needs-revision-active' : ''; ?>"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													data-action-type="<?php echo $is_needs_revision ? 'clear' : 'flag'; ?>"
													title="<?php echo $is_needs_revision ? esc_attr__('Clear revision flag', 'ai-post-scheduler') : esc_attr__('Flag as needing revision', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-flag"></span>
													<span><?php echo $is_needs_revision ? esc_html__('Clear Flag', 'ai-post-scheduler') : esc_html__('Needs Revision', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-row-action-item aips-ai-edit-btn"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													data-history-id="<?php echo esc_attr($item->id); ?>"
													title="<?php esc_attr_e('AI Edit - Regenerate components', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-admin-customizer"></span>
													<span><?php esc_html_e('AI Edit', 'ai-post-scheduler'); ?></span>
												</button>
												<?php
													$history_url = AIPS_Admin_Menu_Helper::get_page_url('history', array_filter(array(
														'history_id' => !empty($item->id) ? absint($item->id) : 0,
														'post_id'    => !empty($item->post_id) ? absint($item->post_id) : 0,
													)));
												?>
												<a class="aips-row-action-item aips-open-history-modal"
													href="<?php echo esc_url($history_url); ?>"
													data-history-id="<?php echo esc_attr($item->id); ?>"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													title="<?php esc_attr_e('View history for this post', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-backup"></span>
													<span><?php esc_html_e('History', 'ai-post-scheduler'); ?></span>
												</a>
												<button type="button" class="aips-row-action-item aips-view-session"
													data-history-id="<?php echo esc_attr($item->id); ?>"
													title="<?php esc_attr_e('View generation session', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-visibility"></span>
													<span><?php esc_html_e('View Session', 'ai-post-scheduler'); ?></span>
												</button>
												<button type="button" class="aips-row-action-item aips-regenerate-post"
													data-history-id="<?php echo esc_attr($item->id); ?>"
													data-post-id="<?php echo esc_attr($item->post_id); ?>"
													title="<?php esc_attr_e('Regenerate this post', 'ai-post-scheduler'); ?>">
													<span class="dashicons dashicons-update"></span>
													<span><?php esc_html_e('Re-generate', 'ai-post-scheduler'); ?></span>
												</button>
											</div>
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
					<?php if (!empty($search_query) || !empty($review_status)): ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Posts Found', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('No draft posts match your search criteria. Try a different search term or filter.', 'ai-post-scheduler'); ?></p>
						<div class="aips-empty-state-actions">
							<a href="<?php echo esc_url(remove_query_arg(array('s', 'review_status'))); ?>" class="aips-btn aips-btn-primary">
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
					$build_review_page_url = static function($page_number) use ($review_base_url, $template_id, $search_query, $review_status) {
						return add_query_arg(array_filter(array(
							'review_paged'  => absint($page_number),
							'template_id'   => $template_id ? $template_id : false,
							's'             => $search_query ? $search_query : false,
							'review_status' => $review_status ? $review_status : false,
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

<!-- Schedule Publish Modal -->
<div id="aips-schedule-publish-modal" class="aips-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aips-schedule-publish-modal-title">
	<div class="aips-modal-overlay"></div>
	<div class="aips-modal-content aips-schedule-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-schedule-publish-modal-title"><?php esc_html_e('Schedule Post', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close aips-schedule-modal-close" aria-label="<?php esc_attr_e('Close', 'ai-post-scheduler'); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="aips-modal-body aips-schedule-modal-body">
			<p class="aips-schedule-modal-subtitle"></p>
			<div class="aips-schedule-field">
				<label for="aips-schedule-date-input"><?php esc_html_e('Publish on:', 'ai-post-scheduler'); ?></label>
				<input type="datetime-local" id="aips-schedule-date-input" class="aips-form-input" min="<?php echo esc_attr(date('Y-m-d\TH:i', time() + 60)); ?>">
				<p class="description"><?php esc_html_e('Date and time are in the site\'s local timezone.', 'ai-post-scheduler'); ?></p>
			</div>
		</div>
		<div class="aips-modal-footer">
			<button type="button" id="aips-schedule-confirm-btn" class="aips-btn aips-btn-primary" data-post-id="">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php esc_html_e('Schedule Post', 'ai-post-scheduler'); ?>
			</button>
			<button type="button" class="aips-btn aips-btn-secondary aips-schedule-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
