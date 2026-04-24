<?php
if (!defined('ABSPATH')) {
	exit;
}

// Data for the unified schedules page
$unified_service = new AIPS_Unified_Schedule_Service();
$type_filter     = isset($_GET['schedule_type']) ? sanitize_key(wp_unslash($_GET['schedule_type'])) : '';
$all_schedules   = $unified_service->get_all($type_filter);

// Also fetch template-schedule data needed for the "Add Schedule" modal
$templates_handler  = new AIPS_Templates();
$templates          = $templates_handler->get_all(true);
$structure_manager  = new AIPS_Article_Structure_Manager();
$article_structures = $structure_manager->get_active_structures();
$template_type_selector = new AIPS_Template_Type_Selector();
$rotation_patterns  = $template_type_selector->get_rotation_patterns();

$preselect_template_id  = isset($_GET['schedule_template']) ? absint($_GET['schedule_template']) : 0;
$preselect_structure_id = isset($_GET['schedule_structure']) ? absint($_GET['schedule_structure']) : 0;

$date_format = get_option('date_format') . ' ' . get_option('time_format');

/**
 * Helper: render a human-readable frequency label.
 */
if (!function_exists('aips_frequency_label')) {
	function aips_frequency_label($frequency) {
		if (empty($frequency)) {
			return __('—', 'ai-post-scheduler');
		}
		$schedules = wp_get_schedules();
		if (isset($schedules[$frequency])) {
			return $schedules[$frequency]['display'];
		}
		return ucfirst(str_replace('_', ' ', $frequency));
	}
}

/**
 * Helper: render a type badge.
 */
if (!function_exists('aips_type_badge')) {
	function aips_type_badge($type) {
		switch ($type) {
			case AIPS_Unified_Schedule_Service::TYPE_TEMPLATE:
				return '<span class="aips-badge aips-badge-type-template">' . esc_html__('Post Generation', 'ai-post-scheduler') . '</span>';
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC:
				return '<span class="aips-badge aips-badge-type-topic">' . esc_html__('Author Topics', 'ai-post-scheduler') . '</span>';
			case AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST:
				return '<span class="aips-badge aips-badge-type-post">' . esc_html__('Author Posts', 'ai-post-scheduler') . '</span>';
		}
		return '';
	}
}

/**
 * Helper: render last-run output label for the Last Run column.
 */
if (!function_exists('aips_run_output_label')) {
	function aips_run_output_label($type) {
		if ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC) {
			return __('Generated topics for author queue', 'ai-post-scheduler');
		}
		if ($type === AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST) {
			return __('Generated approved-topic post', 'ai-post-scheduler');
		}
		return __('Generated post from template', 'ai-post-scheduler');
	}
}

/**
 * Helper: format a future/past timestamp as a relative countdown string.
 * Returns e.g. "In 2 hours", "In 5 minutes", or "Past due".
 */
if (!function_exists('aips_next_run_relative')) {
	function aips_next_run_relative($timestamp) {
		$diff = $timestamp - time();
		if ($diff <= 0) {
			return __('Past due', 'ai-post-scheduler');
		}
		/* translators: %s = human-readable time difference, e.g. "2 hours" */
		return sprintf(__('In %s', 'ai-post-scheduler'), human_time_diff(time(), $timestamp));
	}
}

/**
 * Helper: normalize a DB date/time value to an AIPS_DateTime instance.
 */
if (!function_exists('aips_datetime_from_db_value')) {
	function aips_datetime_from_db_value($value) {
		if (empty($value) || '0000-00-00 00:00:00' === $value) {
			return null;
		}

		if (is_numeric($value)) {
			return AIPS_DateTime::fromTimestampOrNull((int) $value);
		}

		return AIPS_DateTime::fromMysqlOrNull((string) $value);
	}
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">

		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Schedules', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('All scheduled processes — template post generation, author topic generation, and author post generation — in one view.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-page-actions">
					<?php if (!empty($templates)): ?>
					<button class="aips-btn aips-btn-primary aips-add-schedule-btn">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e('Add Template Schedule', 'ai-post-scheduler'); ?>
					</button>
					<?php else: ?>
					<a href="<?php echo esc_url(AIPS_Admin_Menu_Helper::get_page_url('templates')); ?>" class="aips-btn aips-btn-secondary">
						<span class="dashicons dashicons-media-document"></span>
						<?php esc_html_e('Create Template First', 'ai-post-scheduler'); ?>
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Content Panel -->
		<div class="aips-content-panel">

			<!-- Filter Bar -->
			<div class="aips-filter-bar">
				<div class="aips-filter-left">
					<select id="aips-unified-type-filter" class="aips-form-select">
						<option value="" <?php selected($type_filter, ''); ?>><?php esc_html_e('All Types', 'ai-post-scheduler'); ?></option>
						<option value="<?php echo esc_attr(AIPS_Unified_Schedule_Service::TYPE_TEMPLATE); ?>" <?php selected($type_filter, AIPS_Unified_Schedule_Service::TYPE_TEMPLATE); ?>>
							<?php esc_html_e('Post Generation', 'ai-post-scheduler'); ?>
						</option>
						<option value="<?php echo esc_attr(AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC); ?>" <?php selected($type_filter, AIPS_Unified_Schedule_Service::TYPE_AUTHOR_TOPIC); ?>>
							<?php esc_html_e('Author Topics', 'ai-post-scheduler'); ?>
						</option>
						<option value="<?php echo esc_attr(AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST); ?>" <?php selected($type_filter, AIPS_Unified_Schedule_Service::TYPE_AUTHOR_POST); ?>>
							<?php esc_html_e('Author Posts', 'ai-post-scheduler'); ?>
						</option>
					</select>
				</div>
				<div class="aips-filter-right">
					<label class="screen-reader-text" for="aips-unified-search"><?php esc_html_e('Search Schedules:', 'ai-post-scheduler'); ?></label>
					<input type="search" id="aips-unified-search" class="aips-form-input" placeholder="<?php esc_attr_e('Search schedules…', 'ai-post-scheduler'); ?>">
					<button type="button" id="aips-unified-search-clear" class="aips-btn aips-btn-sm aips-btn-ghost" style="display:none;"><?php esc_html_e('Clear', 'ai-post-scheduler'); ?></button>
				</div>
			</div>

			<!-- Bulk Actions Toolbar -->
			<div class="aips-panel-toolbar">
				<div class="aips-toolbar-left aips-btn-group aips-btn-group-inline">
					<button type="button" id="aips-unified-select-all" class="aips-btn aips-btn-secondary aips-btn-sm">
						<?php esc_html_e('Select All', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" id="aips-unified-unselect-all" class="aips-btn aips-btn-secondary aips-btn-sm" disabled>
						<?php esc_html_e('Unselect All', 'ai-post-scheduler'); ?>
					</button>
					<select id="aips-unified-bulk-action" class="aips-form-input" style="width:auto;">
						<option value=""><?php esc_html_e('Bulk Actions', 'ai-post-scheduler'); ?></option>
						<option value="run_now"><?php esc_html_e('Run Now', 'ai-post-scheduler'); ?></option>
						<option value="pause"><?php esc_html_e('Pause', 'ai-post-scheduler'); ?></option>
						<option value="resume"><?php esc_html_e('Resume', 'ai-post-scheduler'); ?></option>
						<option value="delete"><?php esc_html_e('Delete', 'ai-post-scheduler'); ?></option>
					</select>
					<button type="button" id="aips-unified-bulk-apply" class="aips-btn aips-btn-primary aips-btn-sm" disabled>
						<?php esc_html_e('Apply', 'ai-post-scheduler'); ?>
					</button>
					<span id="aips-unified-selected-count" class="aips-selected-count" style="display:none;"></span>
				</div>
			</div>

			<!-- Unified Schedules Table -->
			<div class="aips-panel-body no-padding">
				<?php if (!empty($all_schedules)): ?>
				<table class="aips-table aips-unified-schedule-table">
					<thead>
						<tr>
							<th class="check-column">
								<input type="checkbox" id="cb-select-all-unified" aria-label="<?php esc_attr_e('Select all schedules', 'ai-post-scheduler'); ?>">
							</th>
							<th><?php esc_html_e('Title', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Type', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Last Run', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Next Run', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Stats', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Status', 'ai-post-scheduler'); ?></th>
							<th><?php esc_html_e('Actions', 'ai-post-scheduler'); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($all_schedules as $sched):
						$next_run_dt = aips_datetime_from_db_value($sched['next_run']);
						$last_run_dt = aips_datetime_from_db_value($sched['last_run']);
						$next_run_ts = $next_run_dt ? $next_run_dt->timestamp() : 0;
						$last_run_ts = $last_run_dt ? $last_run_dt->timestamp() : 0;
						$is_active   = $sched['is_active'];
						$status      = $sched['status'];

						// Status badge
						switch ($status) {
							case 'failed':
								$badge_cls = 'aips-badge-error';
								$icon_cls  = 'dashicons-warning';
								$status_lbl = __('Failed', 'ai-post-scheduler');
								break;
							case 'inactive':
								$badge_cls = 'aips-badge-neutral';
								$icon_cls  = 'dashicons-minus';
								$status_lbl = __('Paused', 'ai-post-scheduler');
								break;
							default:
								$badge_cls = 'aips-badge-success';
								$icon_cls  = 'dashicons-yes-alt';
								$status_lbl = __('Active', 'ai-post-scheduler');
						}

						// Composite row ID for JS (e.g. "template_schedule:5")
						$row_key = esc_attr($sched['type'] . ':' . $sched['id']);
					?>
					<tr class="aips-unified-row"
						data-id="<?php echo esc_attr($sched['id']); ?>"
						data-type="<?php echo esc_attr($sched['type']); ?>"
						data-row-key="<?php echo $row_key; ?>"
						data-can-delete="<?php echo esc_attr($sched['can_delete'] ? '1' : '0'); ?>"
						data-is-active="<?php echo esc_attr($is_active); ?>"
						data-title="<?php echo esc_attr($sched['title']); ?>"
						data-schedule-id="<?php echo esc_attr($sched['id']); ?>">
						<th scope="row" class="check-column">
							<input type="checkbox"
								class="aips-unified-checkbox"
								value="<?php echo $row_key; ?>"
								aria-label="<?php echo esc_attr(sprintf(__('Select: %s', 'ai-post-scheduler'), $sched['title'])); ?>">
						</th>
						<td class="column-title">
							<div class="cell-primary">
								<strong><?php echo esc_html($sched['title']); ?></strong>
							</div>
							<?php if (!empty($sched['subtitle'])): ?>
							<div class="cell-meta"><?php echo esc_html($sched['subtitle']); ?></div>
							<?php endif; ?>
							<div class="aips-row-actions">
								<a href="#"
									class="aips-view-unified-history"
									data-id="<?php echo esc_attr($sched['id']); ?>"
									data-type="<?php echo esc_attr($sched['type']); ?>"
									data-name="<?php echo esc_attr($sched['title']); ?>"
									data-limit="5">
									<?php esc_html_e('History', 'ai-post-scheduler'); ?>
								</a>
							</div>
						</td>
						<td class="column-type">
							<?php echo aips_type_badge($sched['type']); // WPCS: XSS ok — output is safe HTML. ?>
							<div class="cell-meta" style="font-size:11px;margin-top:4px;opacity:.7;">
								<?php echo esc_html($sched['cron_hook']); ?>
							</div>
						</td>
						<td class="column-frequency">
							<span class="aips-badge aips-badge-info">
								<?php echo esc_html(aips_frequency_label($sched['frequency'])); ?>
							</span>
						</td>
						<td class="column-last-run">
							<?php if ($last_run_ts): ?>
							<div class="cell-meta"><?php echo esc_html(date_i18n($date_format, $last_run_ts)); ?></div>
							<div class="cell-meta aips-muted" style="font-size:11px;"><?php echo esc_html(aips_run_output_label($sched['type'])); ?></div>
							<?php else: ?>
							<div class="cell-meta aips-muted"><?php esc_html_e('Never', 'ai-post-scheduler'); ?></div>
							<?php endif; ?>
						</td>
						<td class="column-next-run">
							<?php if (!$is_active): ?>
							<div class="cell-meta aips-muted"><?php esc_html_e('N/A', 'ai-post-scheduler'); ?></div>
							<?php elseif ($next_run_ts): ?>
							<div class="cell-primary aips-next-run-countdown" title="<?php echo esc_attr(date_i18n($date_format, $next_run_ts)); ?>">
								<?php echo esc_html(aips_next_run_relative($next_run_ts)); ?>
							</div>
							<div class="cell-meta aips-muted" style="font-size:11px;"><?php echo esc_html(date_i18n($date_format, $next_run_ts)); ?></div>
							<?php if ($next_run_ts < time()): ?>
							<div class="cell-meta" style="color:var(--aips-warning);font-size:11px;"><?php esc_html_e('Due — runs on next cron trigger', 'ai-post-scheduler'); ?></div>
							<?php endif; ?>
							<?php else: ?>
							<div class="cell-meta aips-muted"><?php esc_html_e('—', 'ai-post-scheduler'); ?></div>
							<?php endif; ?>
						</td>
						<td class="column-stats">
							<div class="cell-primary">
								<strong><?php echo esc_html(number_format_i18n($sched['stats_count'])); ?></strong>
							</div>
							<?php if (!empty($sched['stats_label'])): ?>
							<div class="cell-meta"><?php echo esc_html($sched['stats_label']); ?></div>
							<?php endif; ?>
						</td>
						<td class="column-status">
							<div class="aips-schedule-status-wrapper" style="display:flex;align-items:center;gap:8px;">
								<span class="aips-badge <?php echo esc_attr($badge_cls); ?>">
									<span class="dashicons <?php echo esc_attr($icon_cls); ?>"></span>
									<?php echo esc_html($status_lbl); ?>
								</span>
								<label class="aips-toggle">
									<input type="checkbox"
										class="aips-unified-toggle-schedule"
										data-id="<?php echo esc_attr($sched['id']); ?>"
										data-type="<?php echo esc_attr($sched['type']); ?>"
										aria-label="<?php esc_attr_e('Toggle schedule status', 'ai-post-scheduler'); ?>"
										<?php checked($is_active, 1); ?>>
									<span class="aips-toggle-slider"></span>
								</label>
							</div>
						</td>
						<td class="column-actions">
							<div class="cell-actions">
								<!-- Edit (template schedules only) -->
								<?php if ($sched['type'] === AIPS_Unified_Schedule_Service::TYPE_TEMPLATE): ?>
								<button class="aips-btn aips-btn-sm aips-btn-ghost aips-edit-schedule"
									aria-label="<?php esc_attr_e('Edit schedule', 'ai-post-scheduler'); ?>"
									title="<?php esc_attr_e('Edit', 'ai-post-scheduler'); ?>"
									data-schedule-id="<?php echo esc_attr($sched['id']); ?>"
									data-template-id="<?php echo esc_attr($sched['template_id'] ?? ''); ?>"
									data-title="<?php echo esc_attr($sched['title']); ?>"
									data-frequency="<?php echo esc_attr($sched['frequency']); ?>"
									data-next-run="<?php echo esc_attr($sched['next_run'] ?? ''); ?>"
									data-is-active="<?php echo esc_attr($is_active); ?>">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<?php endif; ?>

								<!-- Run Now (all types) -->
								<button class="aips-btn aips-btn-sm aips-btn-ghost aips-unified-run-now"
									data-id="<?php echo esc_attr($sched['id']); ?>"
									data-type="<?php echo esc_attr($sched['type']); ?>"
									aria-label="<?php esc_attr_e('Run now', 'ai-post-scheduler'); ?>"
									title="<?php esc_attr_e('Run Now', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-controls-play"></span>
								</button>

								<!-- Delete (template schedules only) -->
								<?php if ($sched['can_delete']): ?>
								<button class="aips-btn aips-btn-sm aips-btn-danger aips-delete-schedule"
									data-id="<?php echo esc_attr($sched['id']); ?>"
									aria-label="<?php esc_attr_e('Delete schedule', 'ai-post-scheduler'); ?>"
									title="<?php esc_attr_e('Delete', 'ai-post-scheduler'); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<!-- No Search Results State -->
				<div id="aips-unified-search-no-results" class="aips-empty-state" style="display:none;padding:60px 20px;">
					<div class="dashicons dashicons-search aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Found', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description"><?php esc_html_e('No schedules match your search criteria. Try a different search term.', 'ai-post-scheduler'); ?></p>
					<div class="aips-empty-state-actions">
						<button type="button" class="aips-btn aips-btn-primary aips-clear-unified-search-btn">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e('Clear Search', 'ai-post-scheduler'); ?>
						</button>
					</div>
				</div>

				<?php else: ?>
				<!-- Empty State -->
				<div class="aips-empty-state" style="padding:60px 20px;">
					<div class="dashicons dashicons-calendar-alt aips-empty-state-icon" aria-hidden="true"></div>
					<h3 class="aips-empty-state-title"><?php esc_html_e('No Schedules Yet', 'ai-post-scheduler'); ?></h3>
					<p class="aips-empty-state-description">
						<?php if (!empty($type_filter)): ?>
						<?php esc_html_e('No schedules found for the selected type.', 'ai-post-scheduler'); ?>
						<?php else: ?>
						<?php esc_html_e('Create a template schedule, or configure authors with topic / post generation schedules, to see them here.', 'ai-post-scheduler'); ?>
						<?php endif; ?>
					</p>
					<?php if (!empty($templates)): ?>
					<div class="aips-empty-state-actions">
						<button class="aips-btn aips-btn-primary aips-add-schedule-btn">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e('Add Template Schedule', 'ai-post-scheduler'); ?>
						</button>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Table footer -->
			<?php if (!empty($all_schedules)): ?>
			<div class="tablenav">
				<span class="aips-table-footer-count">
					<?php
					$count = count($all_schedules);
					printf(
						esc_html(_n('%s schedule', '%s schedules', $count, 'ai-post-scheduler')),
						number_format_i18n($count)
					);
					?>
				</span>
			</div>
			<?php endif; ?>
		</div><!-- /.aips-content-panel -->

	</div><!-- /.aips-page-container -->
</div><!-- /.wrap -->

<!-- ============================================================ -->
<!-- Add / Edit Template Schedule Modal                           -->
<!-- ============================================================ -->
<div id="aips-schedule-modal" class="aips-modal" style="display:none;"
	data-preselect-template="<?php echo esc_attr($preselect_template_id); ?>"
	data-preselect-structure="<?php echo esc_attr($preselect_structure_id); ?>">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 id="aips-schedule-modal-title"><?php esc_html_e('Add New Schedule', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<form id="aips-schedule-form">
				<input type="hidden" name="schedule_id" id="schedule_id" value="">
				<div class="aips-form-row">
					<label for="schedule_title"><?php esc_html_e('Title (Optional)', 'ai-post-scheduler'); ?></label>
					<input type="text" id="schedule_title" name="schedule_title" class="regular-text">
					<p class="description"><?php esc_html_e('A friendly name for this schedule to help identify it in the list.', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-form-row">
					<label for="schedule_template"><?php esc_html_e('Template', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<select id="schedule_template" name="template_id" required>
						<option value=""><?php esc_html_e('Select Template', 'ai-post-scheduler'); ?></option>
						<?php foreach ($templates as $template): ?>
						<option value="<?php echo esc_attr($template->id); ?>"><?php echo esc_html($template->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aips-form-row">
					<label for="schedule_frequency"><?php esc_html_e('Frequency', 'ai-post-scheduler'); ?></label>
					<select id="schedule_frequency" name="frequency">
						<?php
						$cron_schedules_list = wp_get_schedules();
						uasort($cron_schedules_list, function ($a, $b) {
							return $a['interval'] - $b['interval'];
						});
						foreach ($cron_schedules_list as $key => $schedule) {
							echo '<option value="' . esc_attr($key) . '" ' . selected('daily', $key, false) . '>' . esc_html($schedule['display']) . '</option>';
						}
						?>
					</select>
				</div>
				<div class="aips-form-row">
					<label for="schedule_start_time"><?php esc_html_e('Start Time', 'ai-post-scheduler'); ?></label>
					<input type="datetime-local" id="schedule_start_time" name="start_time">
					<p class="description"><?php esc_html_e('Leave empty to start from now', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-form-row">
					<label for="schedule_topic"><?php esc_html_e('Topic (Optional)', 'ai-post-scheduler'); ?></label>
					<input type="text" id="schedule_topic" name="topic" class="regular-text">
					<p class="description"><?php esc_html_e('Optional topic to pass to template variables', 'ai-post-scheduler'); ?></p>
				</div>
				<div class="aips-form-row">
					<label for="article_structure_id"><?php esc_html_e('Article Structure (Optional)', 'ai-post-scheduler'); ?></label>
					<select id="article_structure_id" name="article_structure_id">
						<option value=""><?php esc_html_e('Use Default', 'ai-post-scheduler'); ?></option>
						<?php foreach ($article_structures as $structure): ?>
						<option value="<?php echo esc_attr($structure->id); ?>"><?php echo esc_html($structure->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aips-form-row">
					<label for="rotation_pattern"><?php esc_html_e('Rotation Pattern (Optional)', 'ai-post-scheduler'); ?></label>
					<select id="rotation_pattern" name="rotation_pattern">
						<option value=""><?php esc_html_e('No Rotation', 'ai-post-scheduler'); ?></option>
						<?php foreach ($rotation_patterns as $key => $label): ?>
						<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="aips-form-row">
					<label class="aips-checkbox-label">
						<input type="checkbox" id="schedule_is_active" name="is_active" value="1" checked>
						<?php esc_html_e('Schedule is active', 'ai-post-scheduler'); ?>
					</label>
				</div>
			</form>
		</div>
		<div class="aips-modal-footer">
			<button type="button" class="button aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="button button-primary aips-save-schedule"><?php esc_html_e('Save Schedule', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>

<!-- ============================================================ -->
<!-- Schedule History Modal                                       -->
<!-- ============================================================ -->
<div id="aips-schedule-history-modal" class="aips-modal" style="display:none;"
	role="dialog" aria-modal="true" aria-labelledby="aips-schedule-history-modal-title">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 id="aips-schedule-history-modal-title"><?php esc_html_e('Recent History', 'ai-post-scheduler'); ?></h2>
			<button class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
		</div>
		<div class="aips-modal-body">
			<div id="aips-schedule-history-loading" style="text-align:center;padding:20px;">
				<span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e('Loading history…', 'ai-post-scheduler'); ?></span>
			</div>
			<div id="aips-schedule-history-empty" class="aips-empty-state" style="display:none;padding:40px 20px;">
				<div class="dashicons dashicons-backup aips-empty-state-icon" aria-hidden="true"></div>
				<h3 class="aips-empty-state-title"><?php esc_html_e('No History Yet', 'ai-post-scheduler'); ?></h3>
				<p class="aips-empty-state-description"><?php esc_html_e('No history events have been recorded for this schedule yet.', 'ai-post-scheduler'); ?></p>
			</div>
			<ul id="aips-schedule-history-list" class="aips-history-timeline" style="display:none;margin:0;padding:0;list-style:none;"></ul>
		</div>
	</div>
</div>

