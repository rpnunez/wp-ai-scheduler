<?php
/**
 * Story Budget Admin Template
 *
 * Editorial planning interface above templates, schedules, and topics.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

$status_labels = AIPS_Story_Budget_Repository::get_statuses();
$priority_labels = AIPS_Story_Budget_Repository::get_priorities();
$story_type_labels = AIPS_Story_Budget_Repository::get_story_types();
$source_type_labels = AIPS_Story_Budget_Repository::get_source_types();
$form_item = $editing_item ? $editing_item : $form_defaults;
$notice_type = isset($_GET['notice_type']) ? sanitize_key(wp_unslash($_GET['notice_type'])) : '';
$notice_message = isset($_GET['notice_message']) ? sanitize_text_field(wp_unslash($_GET['notice_message'])) : '';
$story_budget_page_url = AIPS_Admin_Menu_Helper::get_page_url('story_budget');
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Story Budget', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Build an editorial planning layer above templates, schedules, research, and approved author topics.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<a href="<?php echo esc_url($story_budget_page_url); ?>" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('New Manual Item', 'ai-post-scheduler'); ?>
					</a>
				</div>
			</div>
		</div>

		<?php if (!empty($notice_message)) : ?>
			<div class="notice notice-<?php echo 'error' === $notice_type ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html($notice_message); ?></p>
			</div>
		<?php endif; ?>

		<div class="aips-status-summary">
			<div class="aips-summary-card">
				<div class="dashicons dashicons-feedback aips-summary-icon" aria-hidden="true"></div>
				<div class="aips-summary-content">
					<span class="aips-summary-number"><?php echo esc_html($stats['total']); ?></span>
					<span class="aips-summary-label"><?php esc_html_e('Budget Items', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-summary-card">
				<div class="dashicons dashicons-admin-users aips-summary-icon" aria-hidden="true"></div>
				<div class="aips-summary-content">
					<span class="aips-summary-number"><?php echo esc_html($stats['assigned']); ?></span>
					<span class="aips-summary-label"><?php esc_html_e('Assigned', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-summary-card">
				<div class="dashicons dashicons-search aips-summary-icon" aria-hidden="true"></div>
				<div class="aips-summary-content">
					<span class="aips-summary-number"><?php echo esc_html($stats['in_research']); ?></span>
					<span class="aips-summary-label"><?php esc_html_e('In Research', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-summary-card">
				<div class="dashicons dashicons-edit-page aips-summary-icon" aria-hidden="true"></div>
				<div class="aips-summary-content">
					<span class="aips-summary-number"><?php echo esc_html($stats['drafting'] + $stats['in_review']); ?></span>
					<span class="aips-summary-label"><?php esc_html_e('In Production', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<div class="aips-summary-card">
				<div class="dashicons dashicons-calendar-alt aips-summary-icon" aria-hidden="true"></div>
				<div class="aips-summary-content">
					<span class="aips-summary-number"><?php echo esc_html($stats['scheduled']); ?></span>
					<span class="aips-summary-label"><?php esc_html_e('Scheduled', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
		</div>

		<div class="aips-grid aips-grid-cols-2">
			<div class="aips-content-panel">
				<div class="aips-panel-header">
					<h2 class="aips-panel-title"><?php echo $editing_item ? esc_html__('Edit Story Budget Item', 'ai-post-scheduler') : esc_html__('Create Story Budget Item', 'ai-post-scheduler'); ?></h2>
				</div>
				<div class="aips-panel-body">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="aips_save_story_budget">
						<input type="hidden" name="story_budget_id" value="<?php echo esc_attr(!empty($form_item->id) ? $form_item->id : 0); ?>">
						<?php wp_nonce_field('aips_save_story_budget'); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="aips-story-budget-title"><?php esc_html_e('Title', 'ai-post-scheduler'); ?></label></th>
								<td><input type="text" class="regular-text" id="aips-story-budget-title" name="title" value="<?php echo esc_attr($form_item->title); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-beat"><?php esc_html_e('Beat', 'ai-post-scheduler'); ?></label></th>
								<td><input type="text" class="regular-text" id="aips-story-budget-beat" name="beat" value="<?php echo esc_attr($form_item->beat); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-desk"><?php esc_html_e('Desk', 'ai-post-scheduler'); ?></label></th>
								<td><input type="text" class="regular-text" id="aips-story-budget-desk" name="desk" value="<?php echo esc_attr($form_item->desk); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-story-type"><?php esc_html_e('Story Type', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips-story-budget-story-type" name="story_type" class="aips-form-select">
										<?php foreach ($story_type_labels as $story_type_key => $story_type_label) : ?>
											<option value="<?php echo esc_attr($story_type_key); ?>" <?php selected($form_item->story_type, $story_type_key); ?>><?php echo esc_html($story_type_label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-priority"><?php esc_html_e('Priority', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips-story-budget-priority" name="priority" class="aips-form-select">
										<?php foreach ($priority_labels as $priority_key => $priority_label) : ?>
											<option value="<?php echo esc_attr($priority_key); ?>" <?php selected($form_item->priority, $priority_key); ?>><?php echo esc_html($priority_label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-status"><?php esc_html_e('Status', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips-story-budget-status" name="status" class="aips-form-select">
										<?php foreach ($status_labels as $status_key => $status_label) : ?>
											<option value="<?php echo esc_attr($status_key); ?>" <?php selected($form_item->status, $status_key); ?>><?php echo esc_html($status_label); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-editor"><?php esc_html_e('Assigned Editor', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips-story-budget-editor" name="assigned_editor_user_id" class="aips-form-select">
										<option value="0"><?php esc_html_e('Unassigned', 'ai-post-scheduler'); ?></option>
										<?php foreach ($users as $user) : ?>
											<option value="<?php echo esc_attr($user->ID); ?>" <?php selected((int) $form_item->assigned_editor_user_id, (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-writer"><?php esc_html_e('Assigned Writer', 'ai-post-scheduler'); ?></label></th>
								<td>
									<select id="aips-story-budget-writer" name="assigned_writer_user_id" class="aips-form-select">
										<option value="0"><?php esc_html_e('Unassigned', 'ai-post-scheduler'); ?></option>
										<?php foreach ($users as $user) : ?>
											<option value="<?php echo esc_attr($user->ID); ?>" <?php selected((int) $form_item->assigned_writer_user_id, (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-due-at"><?php esc_html_e('Due At', 'ai-post-scheduler'); ?></label></th>
								<td><input type="datetime-local" id="aips-story-budget-due-at" name="due_at" value="<?php echo esc_attr(!empty($form_item->due_at) ? gmdate('Y-m-d\TH:i', strtotime($form_item->due_at)) : ''); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-publish-start"><?php esc_html_e('Publish Window Start', 'ai-post-scheduler'); ?></label></th>
								<td><input type="datetime-local" id="aips-story-budget-publish-start" name="publish_window_start" value="<?php echo esc_attr(!empty($form_item->publish_window_start) ? gmdate('Y-m-d\TH:i', strtotime($form_item->publish_window_start)) : ''); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-publish-end"><?php esc_html_e('Publish Window End', 'ai-post-scheduler'); ?></label></th>
								<td><input type="datetime-local" id="aips-story-budget-publish-end" name="publish_window_end" value="<?php echo esc_attr(!empty($form_item->publish_window_end) ? gmdate('Y-m-d\TH:i', strtotime($form_item->publish_window_end)) : ''); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="aips-story-budget-notes"><?php esc_html_e('Notes', 'ai-post-scheduler'); ?></label></th>
								<td>
									<textarea class="large-text" rows="5" id="aips-story-budget-notes" name="notes"><?php echo esc_textarea($form_item->notes); ?></textarea>
									<input type="hidden" name="source_type" value="<?php echo esc_attr($form_item->source_type); ?>">
									<input type="hidden" name="source_topic_id" value="<?php echo esc_attr((int) $form_item->source_topic_id); ?>">
									<input type="hidden" name="source_research_id" value="<?php echo esc_attr((int) $form_item->source_research_id); ?>">
									<p class="description">
										<?php
										if (!empty($source_type_labels[$form_item->source_type])) {
											printf(
												esc_html__('Source: %s', 'ai-post-scheduler'),
												esc_html($source_type_labels[$form_item->source_type])
											);
										}
										?>
									</p>
								</td>
							</tr>
						</table>
						<p>
							<button type="submit" class="aips-btn aips-btn-primary">
								<span class="dashicons dashicons-saved"></span>
								<?php echo $editing_item ? esc_html__('Update Story Budget Item', 'ai-post-scheduler') : esc_html__('Save Story Budget Item', 'ai-post-scheduler'); ?>
							</button>
							<?php if ($editing_item) : ?>
								<a href="<?php echo esc_url($story_budget_page_url); ?>" class="aips-btn aips-btn-secondary"><?php esc_html_e('Cancel Edit', 'ai-post-scheduler'); ?></a>
							<?php endif; ?>
						</p>
					</form>
				</div>
			</div>

			<div class="aips-content-panel">
				<div class="aips-panel-header">
					<h2 class="aips-panel-title"><?php esc_html_e('Seed the Budget', 'ai-post-scheduler'); ?></h2>
				</div>
				<div class="aips-panel-body">
					<p><?php esc_html_e('Create planning items from research discoveries, approved author topics, or manual newsroom entries.', 'ai-post-scheduler'); ?></p>
					<h3><?php esc_html_e('Recent Research Library Entries', 'ai-post-scheduler'); ?></h3>
					<?php if (!empty($research_entries)) : ?>
						<ul>
							<?php foreach ($research_entries as $research_entry) : ?>
								<li style="margin-bottom: 8px;">
									<strong><?php echo esc_html($research_entry['topic']); ?></strong>
									<small>(<?php echo esc_html($research_entry['niche']); ?>, <?php echo esc_html($research_entry['score']); ?>)</small>
									<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('story_budget', array('source_research_id' => $research_entry['id']))); ?>"><?php esc_html_e('Create Budget Item', 'ai-post-scheduler'); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="description"><?php esc_html_e('No research entries available yet.', 'ai-post-scheduler'); ?></p>
					<?php endif; ?>

					<h3><?php esc_html_e('Approved Author Topics', 'ai-post-scheduler'); ?></h3>
					<?php if (!empty($approved_topics)) : ?>
						<ul>
							<?php foreach (array_slice($approved_topics, 0, 12) as $topic) : ?>
								<li style="margin-bottom: 8px;">
									<strong><?php echo esc_html($topic->topic_title); ?></strong>
									<small>(<?php echo esc_html($topic->author_name); ?>)</small>
									<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('story_budget', array('source_topic_id' => $topic->id))); ?>"><?php esc_html_e('Create Budget Item', 'ai-post-scheduler'); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="description"><?php esc_html_e('No approved topics are waiting in the queue.', 'ai-post-scheduler'); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2 class="aips-panel-title"><?php esc_html_e('Budget Board', 'ai-post-scheduler'); ?></h2>
				<span class="aips-table-footer-count"><?php echo esc_html(sprintf(_n('%d item', '%d items', $total_items, 'ai-post-scheduler'), $total_items)); ?></span>
			</div>
			<div class="aips-panel-body">
				<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="aips-filter-bar" style="margin-bottom: 18px;">
					<input type="hidden" name="page" value="aips-story-budget">
					<div class="aips-filter-left">
						<select name="beat" class="aips-form-select">
							<option value=""><?php esc_html_e('All Beats', 'ai-post-scheduler'); ?></option>
							<?php foreach ($beats as $beat) : ?>
								<option value="<?php echo esc_attr($beat); ?>" <?php selected($filters['beat'], $beat); ?>><?php echo esc_html($beat); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="assignee" class="aips-form-select">
							<option value="0"><?php esc_html_e('All Assignees', 'ai-post-scheduler'); ?></option>
							<?php foreach ($users as $user) : ?>
								<option value="<?php echo esc_attr($user->ID); ?>" <?php selected((int) $filters['assignee'], (int) $user->ID); ?>><?php echo esc_html($user->display_name); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="priority" class="aips-form-select">
							<option value=""><?php esc_html_e('All Priorities', 'ai-post-scheduler'); ?></option>
							<?php foreach ($priority_labels as $priority_key => $priority_label) : ?>
								<option value="<?php echo esc_attr($priority_key); ?>" <?php selected($filters['priority'], $priority_key); ?>><?php echo esc_html($priority_label); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="status" class="aips-form-select">
							<option value=""><?php esc_html_e('All Statuses', 'ai-post-scheduler'); ?></option>
							<?php foreach ($status_labels as $status_key => $status_label) : ?>
								<option value="<?php echo esc_attr($status_key); ?>" <?php selected($filters['status'], $status_key); ?>><?php echo esc_html($status_label); ?></option>
							<?php endforeach; ?>
						</select>
						<label>
							<span class="screen-reader-text"><?php esc_html_e('Publish window start', 'ai-post-scheduler'); ?></span>
							<input type="date" name="publish_window_start" value="<?php echo esc_attr(!empty($filters['publish_window_start']) ? gmdate('Y-m-d', strtotime($filters['publish_window_start'])) : ''); ?>">
						</label>
						<label>
							<span class="screen-reader-text"><?php esc_html_e('Publish window end', 'ai-post-scheduler'); ?></span>
							<input type="date" name="publish_window_end" value="<?php echo esc_attr(!empty($filters['publish_window_end']) ? gmdate('Y-m-d', strtotime($filters['publish_window_end'])) : ''); ?>">
						</label>
						<button type="submit" class="aips-btn aips-btn-secondary"><?php esc_html_e('Apply Filters', 'ai-post-scheduler'); ?></button>
						<a href="<?php echo esc_url($story_budget_page_url); ?>" class="aips-btn aips-btn-ghost"><?php esc_html_e('Reset', 'ai-post-scheduler'); ?></a>
					</div>
				</form>

				<?php if (!empty($items)) : ?>
					<div class="aips-panel-body no-padding">
						<table class="aips-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Beat / Desk', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Assignees', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Publish Window', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Priority', 'ai-post-scheduler'); ?></th>
									<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($items as $item) : ?>
									<tr>
										<td>
											<div class="cell-primary"><?php echo esc_html($item->title); ?></div>
											<div class="cell-meta"><?php echo esc_html(isset($source_type_labels[$item->source_type]) ? $source_type_labels[$item->source_type] : ucfirst(str_replace('_', ' ', $item->source_type))); ?></div>
										</td>
										<td>
											<div><?php echo esc_html($item->beat ? $item->beat : '—'); ?></div>
											<div class="cell-meta"><?php echo esc_html($item->desk ? $item->desk : '—'); ?></div>
										</td>
										<td>
											<div><?php echo esc_html($item->assigned_writer_name ? $item->assigned_writer_name : __('No writer', 'ai-post-scheduler')); ?></div>
											<div class="cell-meta"><?php echo esc_html($item->assigned_editor_name ? $item->assigned_editor_name : __('No editor', 'ai-post-scheduler')); ?></div>
										</td>
										<td>
											<?php if (!empty($item->publish_window_start) || !empty($item->publish_window_end)) : ?>
												<div><?php echo esc_html(!empty($item->publish_window_start) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->publish_window_start)) : __('Open start', 'ai-post-scheduler')); ?></div>
												<div class="cell-meta"><?php echo esc_html(!empty($item->publish_window_end) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->publish_window_end)) : __('Open end', 'ai-post-scheduler')); ?></div>
											<?php else : ?>
												<div><?php echo esc_html(!empty($item->due_at) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->due_at)) : __('Not scheduled', 'ai-post-scheduler')); ?></div>
											<?php endif; ?>
										</td>
										<td><span class="aips-badge aips-badge-info"><?php echo esc_html(isset($status_labels[$item->status]) ? $status_labels[$item->status] : ucfirst($item->status)); ?></span></td>
										<td><span class="aips-badge aips-badge-<?php echo esc_attr(in_array($item->priority, array('urgent', 'high'), true) ? 'warning' : 'neutral'); ?>"><?php echo esc_html(isset($priority_labels[$item->priority]) ? $priority_labels[$item->priority] : ucfirst($item->priority)); ?></span></td>
										<td>
											<a class="aips-btn aips-btn-sm aips-btn-secondary" href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('story_budget', array('edit_story_budget' => $item->id))); ?>"><?php esc_html_e('Edit', 'ai-post-scheduler'); ?></a>
											<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-left: 6px;">
												<input type="hidden" name="action" value="aips_delete_story_budget">
												<input type="hidden" name="story_budget_id" value="<?php echo esc_attr($item->id); ?>">
												<?php wp_nonce_field('aips_delete_story_budget'); ?>
												<button type="submit" class="aips-btn aips-btn-sm aips-btn-danger" onclick="return confirm('<?php echo esc_js(__('Delete this story budget item?', 'ai-post-scheduler')); ?>');"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="aips-empty-state">
						<div class="dashicons dashicons-feedback aips-empty-state-icon" aria-hidden="true"></div>
						<h3 class="aips-empty-state-title"><?php esc_html_e('No Story Budget Items Yet', 'ai-post-scheduler'); ?></h3>
						<p class="aips-empty-state-description"><?php esc_html_e('Start planning by creating a manual item or seeding the budget from research and approved topics.', 'ai-post-scheduler'); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
