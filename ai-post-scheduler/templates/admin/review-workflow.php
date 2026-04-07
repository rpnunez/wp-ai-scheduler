<?php
/**
 * Review Workflow Admin Template
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 *
 * @var AIPS_Review_Workflow_Controller $this
 * @var array $items
 * @var array $templates
 * @var array $users
 * @var array $counts
 * @var array $stages
 * @var string $stage
 * @var string $closed
 * @var int $assigned_to
 * @var int $template_id
 * @var string $search
 */

if (!defined('ABSPATH')) {
	exit;
}

$page_url = AIPS_Admin_Menu_Helper::get_page_url('review_workflow');
?>

<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Review Workflow', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Move AI-generated drafts through a multi-stage editorial workflow.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
			<div class="aips-rw-stats">
				<?php foreach (AIPS_Review_Workflow_Repository::get_stages() as $stage_key): ?>
					<span class="aips-badge aips-badge-neutral">
						<?php echo esc_html($stages[$stage_key]['label']); ?>:
						<strong><?php echo esc_html(isset($counts[$stage_key]) ? (int) $counts[$stage_key] : 0); ?></strong>
					</span>
				<?php endforeach; ?>
				<span class="aips-badge aips-badge-warning">
					<?php esc_html_e('Overdue', 'ai-post-scheduler'); ?>:
					<strong><?php echo esc_html(isset($counts['overdue']) ? (int) $counts['overdue'] : 0); ?></strong>
				</span>
			</div>
		</div>

		<div class="aips-filter-bar">
			<form method="get" class="aips-filter-form">
				<input type="hidden" name="page" value="aips-review-workflow">
				<div class="aips-filter-left">
					<label class="screen-reader-text" for="aips-rw-stage"><?php esc_html_e('Stage', 'ai-post-scheduler'); ?></label>
					<select name="stage" id="aips-rw-stage" class="aips-form-select">
						<option value=""><?php esc_html_e('All Stages', 'ai-post-scheduler'); ?></option>
						<?php foreach (AIPS_Review_Workflow_Repository::get_stages() as $k): ?>
							<option value="<?php echo esc_attr($k); ?>" <?php selected($stage, $k); ?>>
								<?php echo esc_html($stages[$k]['label']); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label class="screen-reader-text" for="aips-rw-closed"><?php esc_html_e('State', 'ai-post-scheduler'); ?></label>
					<select name="closed_state" id="aips-rw-closed" class="aips-form-select">
						<option value="open" <?php selected($closed, 'open'); ?>><?php esc_html_e('Open', 'ai-post-scheduler'); ?></option>
						<option value="scheduled" <?php selected($closed, 'scheduled'); ?>><?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></option>
						<option value="published" <?php selected($closed, 'published'); ?>><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
						<option value="archived" <?php selected($closed, 'archived'); ?>><?php esc_html_e('Archived', 'ai-post-scheduler'); ?></option>
					</select>

					<label class="screen-reader-text" for="aips-rw-assignee"><?php esc_html_e('Assignee', 'ai-post-scheduler'); ?></label>
					<select name="assigned_to" id="aips-rw-assignee" class="aips-form-select">
						<option value=""><?php esc_html_e('All Assignees', 'ai-post-scheduler'); ?></option>
						<?php foreach ($users as $u): ?>
							<option value="<?php echo esc_attr($u->ID); ?>" <?php selected($assigned_to, (int) $u->ID); ?>>
								<?php echo esc_html($u->display_name); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php if (!empty($templates)): ?>
					<label class="screen-reader-text" for="aips-rw-template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?></label>
					<select name="template_id" id="aips-rw-template" class="aips-form-select">
						<option value=""><?php esc_html_e('All Templates', 'ai-post-scheduler'); ?></option>
						<?php foreach ($templates as $t): ?>
							<option value="<?php echo esc_attr($t->id); ?>" <?php selected($template_id, (int) $t->id); ?>>
								<?php echo esc_html($t->name); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>

					<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e('Filter', 'ai-post-scheduler'); ?>
					</button>
					<?php
					$has_filters = !empty($stage) || 'open' !== $closed || !empty($assigned_to) || !empty($template_id) || !empty($search);
					if ($has_filters):
					?>
						<a class="aips-btn aips-btn-sm aips-btn-ghost" href="<?php echo esc_url($page_url); ?>"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></a>
					<?php endif; ?>
				</div>

				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-rw-search"><?php esc_html_e('Search', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-rw-search" name="s" value="<?php echo esc_attr($search); ?>" class="aips-form-input" placeholder="<?php esc_attr_e('Search posts...', 'ai-post-scheduler'); ?>">
					<button type="submit" class="aips-btn aips-btn-sm aips-btn-secondary">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e('Search', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</form>
		</div>

		<div class="aips-content-panel">
			<div class="aips-panel-body no-padding">
				<?php if (!empty($items['items'])): ?>
				<table class="aips-table aips-rw-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Post', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Stage', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Assignee', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Due', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Source', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Updated', 'ai-post-scheduler'); ?></th>
							<th scope="col"><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($items['items'] as $row): ?>
							<?php
							$assignee_label = '';
							if (!empty($row->assigned_to)) {
								$assignee = get_userdata((int) $row->assigned_to);
								$assignee_label = $assignee ? $assignee->display_name : '';
							}

							$due_label = '';
							$is_overdue = false;
							if (!empty($row->due_at)) {
								$is_overdue = (strtotime((string) $row->due_at) < current_time('timestamp'));
								$due_label = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $row->due_at));
							}

							$updated_label = '';
							if (!empty($row->updated_at)) {
								$updated_label = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $row->updated_at));
							}

							$stage_key = (string) $row->stage;
							$stage_label = isset($stages[$stage_key]) ? $stages[$stage_key]['label'] : $stage_key;
							?>
							<tr data-review-item-id="<?php echo esc_attr($row->id); ?>" data-post-id="<?php echo esc_attr($row->post_id); ?>">
								<td>
									<a class="cell-primary" href="<?php echo esc_url(get_edit_post_link((int) $row->post_id)); ?>">
										<?php echo esc_html($row->post_title); ?>
									</a>
									<div class="cell-meta">
										<span class="aips-badge aips-badge-neutral"><?php echo esc_html($row->post_status); ?></span>
										<?php if (!empty($row->priority) && 'normal' !== $row->priority): ?>
											<span class="aips-badge aips-badge-warning"><?php echo esc_html(ucfirst((string) $row->priority)); ?></span>
										<?php endif; ?>
									</div>
								</td>
								<td>
									<span class="aips-badge aips-badge-neutral">
										<?php echo esc_html($stage_label); ?>
									</span>
									<?php if (!empty($row->stage_state) && 'pending' !== $row->stage_state): ?>
										<div class="cell-meta"><?php echo esc_html(str_replace('_', ' ', (string) $row->stage_state)); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<?php echo $assignee_label ? esc_html($assignee_label) : '—'; ?>
								</td>
								<td>
									<?php if ($due_label): ?>
										<span class="<?php echo $is_overdue ? esc_attr('aips-rw-overdue') : ''; ?>"><?php echo esc_html($due_label); ?></span>
									<?php else: ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if (!empty($row->template_name)): ?>
										<span class="aips-badge aips-badge-neutral"><?php echo esc_html(sprintf(__('Template: %s', 'ai-post-scheduler'), $row->template_name)); ?></span>
									<?php else: ?>
										<span class="aips-badge aips-badge-neutral"><?php esc_html_e('Unknown', 'ai-post-scheduler'); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $updated_label ? esc_html($updated_label) : '—'; ?></td>
								<td>
									<div class="aips-btn-group aips-btn-group-inline">
										<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-open">
											<span class="dashicons dashicons-visibility"></span>
											<?php esc_html_e('Open', 'ai-post-scheduler'); ?>
										</button>
										<?php if ('ready' === $stage_key && 'open' === (string) $row->closed_state): ?>
											<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-rw-open">
												<span class="dashicons dashicons-calendar-alt"></span>
												<?php esc_html_e('Schedule/Publish', 'ai-post-scheduler'); ?>
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else: ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-yes-alt aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Items', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('No posts match the current filters.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<div class="tablenav">
				<span class="aips-table-footer-count">
					<?php printf( esc_html( _n( '%d item', '%d items', (int) $items['total'], 'ai-post-scheduler' ) ), (int) $items['total'] ); ?>
				</span>
				<?php if (!empty($items['pages']) && $items['pages'] > 1): ?>
				<?php
				$current = (int) $items['current_page'];
				$pages   = (int) $items['pages'];
				$start   = max(1, $current - 3);
				$end     = min($pages, $current + 3);
				$build_url = static function($page_number) use ($page_url, $stage, $closed, $assigned_to, $template_id, $search) {
					return add_query_arg(array_filter(array(
						'paged'       => absint($page_number),
						'stage'       => $stage ? $stage : false,
						'closed_state'=> $closed ? $closed : false,
						'assigned_to' => $assigned_to ? $assigned_to : false,
						'template_id' => $template_id ? $template_id : false,
						's'           => $search ? $search : false,
					)), $page_url);
				};
				?>
				<div class="aips-history-pagination-links">
					<?php if ($current > 1): ?>
						<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_url($current - 1)); ?>" aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</a>
					<?php else: ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" disabled aria-label="<?php esc_attr_e('Previous page', 'ai-post-scheduler'); ?>">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</button>
					<?php endif; ?>

					<span class="aips-history-page-numbers">
						<?php if ($start > 1): ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_url(1)); ?>">1</a>
							<?php if ($start > 2): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
						<?php endif; ?>

						<?php for ($p = $start; $p <= $end; $p++): ?>
							<?php if ($p === $current): ?>
								<span class="aips-btn aips-btn-sm aips-btn-primary" aria-current="page"><?php echo esc_html($p); ?></span>
							<?php else: ?>
								<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_url($p)); ?>"><?php echo esc_html($p); ?></a>
							<?php endif; ?>
						<?php endfor; ?>

						<?php if ($end < $pages): ?>
							<?php if ($end < $pages - 1): ?><span class="aips-history-page-ellipsis">…</span><?php endif; ?>
							<a class="aips-btn aips-btn-sm aips-btn-secondary aips-history-page-link" href="<?php echo esc_url($build_url($pages)); ?>"><?php echo esc_html($pages); ?></a>
						<?php endif; ?>
					</span>

					<?php if ($current < $pages): ?>
						<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url($build_url($current + 1)); ?>" aria-label="<?php esc_attr_e('Next page', 'ai-post-scheduler'); ?>">
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
		</div>
	</div>
</div>

<!-- Drawer -->
<div id="aips-rw-drawer" class="aips-rw-drawer" aria-hidden="true">
	<div class="aips-rw-drawer-header">
		<div class="aips-rw-drawer-title">
			<strong id="aips-rw-drawer-post-title"><?php esc_html_e('Loading…', 'ai-post-scheduler'); ?></strong>
			<span id="aips-rw-drawer-stage-badge" class="aips-badge aips-badge-neutral"></span>
		</div>
		<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-close">
			<span class="dashicons dashicons-no-alt"></span>
			<?php esc_html_e('Close', 'ai-post-scheduler'); ?>
		</button>
	</div>

	<div class="aips-rw-drawer-body">
		<div class="aips-rw-section">
			<h3><?php esc_html_e('Preview', 'ai-post-scheduler'); ?></h3>
			<div id="aips-rw-preview" class="aips-rw-preview"></div>
		</div>

		<div class="aips-rw-section">
			<h3><?php esc_html_e('Workflow', 'ai-post-scheduler'); ?></h3>
			<div class="aips-rw-controls">
				<label for="aips-rw-stage-select"><?php esc_html_e('Stage', 'ai-post-scheduler'); ?></label>
				<select id="aips-rw-stage-select" class="aips-form-select"></select>

				<label for="aips-rw-assignee-select"><?php esc_html_e('Assignee', 'ai-post-scheduler'); ?></label>
				<select id="aips-rw-assignee-select" class="aips-form-select">
					<option value=""><?php esc_html_e('Unassigned', 'ai-post-scheduler'); ?></option>
					<?php foreach ($users as $u): ?>
						<option value="<?php echo esc_attr($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
					<?php endforeach; ?>
				</select>

				<label for="aips-rw-due-input"><?php esc_html_e('Due', 'ai-post-scheduler'); ?></label>
				<input type="datetime-local" id="aips-rw-due-input" class="aips-form-input">
			</div>

			<div class="aips-rw-controls">
				<label for="aips-rw-priority-select"><?php esc_html_e('Priority', 'ai-post-scheduler'); ?></label>
				<select id="aips-rw-priority-select" class="aips-form-select">
					<option value="low"><?php esc_html_e('Low', 'ai-post-scheduler'); ?></option>
					<option value="normal"><?php esc_html_e('Normal', 'ai-post-scheduler'); ?></option>
					<option value="high"><?php esc_html_e('High', 'ai-post-scheduler'); ?></option>
				</select>
			</div>

			<div class="aips-rw-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-rw-approve">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e('Approve', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-request-changes">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e('Request changes', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-skip">
					<span class="dashicons dashicons-arrow-right-alt"></span>
					<?php esc_html_e('Skip', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-danger aips-rw-archive">
					<span class="dashicons dashicons-archive"></span>
					<?php esc_html_e('Archive', 'ai-post-scheduler'); ?>
				</button>
			</div>

			<div class="aips-rw-stage-notes">
				<label for="aips-rw-notes"><?php esc_html_e('Notes', 'ai-post-scheduler'); ?></label>
				<textarea id="aips-rw-notes" class="aips-form-input" rows="4"></textarea>
				<div class="aips-rw-notes-actions">
					<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-save-notes">
						<span class="dashicons dashicons-saved"></span>
						<?php esc_html_e('Save notes', 'ai-post-scheduler'); ?>
					</button>
				</div>
			</div>

			<div class="aips-rw-checklist">
				<h4><?php esc_html_e('Checklist', 'ai-post-scheduler'); ?></h4>
				<div id="aips-rw-checklist-items"></div>
			</div>
		</div>

		<div id="aips-rw-schedule-section" class="aips-rw-section" style="display:none;">
			<h3><?php esc_html_e('Schedule / Publish', 'ai-post-scheduler'); ?></h3>
			<div class="aips-rw-controls">
				<label for="aips-rw-schedule-at"><?php esc_html_e('Publish at', 'ai-post-scheduler'); ?></label>
				<input type="datetime-local" id="aips-rw-schedule-at" class="aips-form-input">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-schedule">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php esc_html_e('Schedule', 'ai-post-scheduler'); ?>
				</button>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-primary aips-rw-publish-now">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e('Publish now', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>

		<div class="aips-rw-section">
			<h3><?php esc_html_e('Comments', 'ai-post-scheduler'); ?></h3>
			<div id="aips-rw-comments" class="aips-rw-comments"></div>
			<textarea id="aips-rw-new-comment" class="aips-form-input" rows="3" placeholder="<?php esc_attr_e('Add a comment…', 'ai-post-scheduler'); ?>"></textarea>
			<div class="aips-rw-notes-actions">
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-rw-add-comment">
					<span class="dashicons dashicons-admin-comments"></span>
					<?php esc_html_e('Add comment', 'ai-post-scheduler'); ?>
				</button>
			</div>
		</div>
	</div>
</div>

