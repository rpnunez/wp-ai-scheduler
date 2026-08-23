<?php
if (!defined('ABSPATH')) { die; }
?>
<!-- ============================================================ -->
<div id="aips-schedule-modal" class="aips-modal" style="display:none;"
	data-preselect-template="<?php echo esc_attr($preselect_template_id); ?>"
	data-preselect-structure="<?php echo esc_attr($preselect_structure_id); ?>">
	<div class="aips-modal-content">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Add New Schedule', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
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

						// The 7 single-day frequencies (every_monday ... every_sunday) are chosen
						// via the "Repeat On" day picker below instead of being listed here
						// individually — that used to mean scanning past 7 near-duplicate entries
						// to find e.g. "Every Monday".
						$day_specific_keys = array_map(function ($day) {
							return 'every_' . strtolower($day);
						}, array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'));

						$frequency_groups = array(
							'hourly'  => array('label' => __('Hourly', 'ai-post-scheduler'), 'options' => array()),
							'daily'   => array('label' => __('Daily', 'ai-post-scheduler'), 'options' => array()),
							'weekly'  => array('label' => __('Weekly', 'ai-post-scheduler'), 'options' => array()),
							'monthly' => array('label' => __('Monthly', 'ai-post-scheduler'), 'options' => array()),
							'once'    => array('label' => __('One-Time', 'ai-post-scheduler'), 'options' => array()),
						);

						foreach ($cron_schedules_list as $key => $schedule) {
							if (in_array($key, $day_specific_keys, true)) {
								continue;
							}
							if ('once' === $key) {
								$group_key = 'once';
							} elseif ($schedule['interval'] < DAY_IN_SECONDS) {
								$group_key = 'hourly';
							} elseif ($schedule['interval'] === DAY_IN_SECONDS) {
								$group_key = 'daily';
							} elseif ($schedule['interval'] <= 2 * WEEK_IN_SECONDS) {
								$group_key = 'weekly';
							} else {
								$group_key = 'monthly';
							}
							$frequency_groups[$group_key]['options'][$key] = $schedule['display'];
						}

						foreach ($frequency_groups as $group) {
							if (empty($group['options'])) {
								continue;
							}
							echo '<optgroup label="' . esc_attr($group['label']) . '">';
							foreach ($group['options'] as $key => $display) {
								echo '<option value="' . esc_attr($key) . '" ' . selected('daily', $key, false) . '>' . esc_html($display) . '</option>';
							}
							echo '</optgroup>';
						}
						?>
					</select>
				</div>
				<div class="aips-form-row" id="aips-schedule-repeat-on-row" style="display:none;">
					<label><?php esc_html_e('Repeat On', 'ai-post-scheduler'); ?></label>
					<input type="hidden" id="schedule_repeat_day" name="repeat_day" value="">
					<div class="aips-btn-group" role="group" aria-label="<?php esc_attr_e('Day of week', 'ai-post-scheduler'); ?>">
						<?php
						$day_picker_labels = array(
							'monday'    => __('Mon', 'ai-post-scheduler'),
							'tuesday'   => __('Tue', 'ai-post-scheduler'),
							'wednesday' => __('Wed', 'ai-post-scheduler'),
							'thursday'  => __('Thu', 'ai-post-scheduler'),
							'friday'    => __('Fri', 'ai-post-scheduler'),
							'saturday'  => __('Sat', 'ai-post-scheduler'),
							'sunday'    => __('Sun', 'ai-post-scheduler'),
						);
						foreach ($day_picker_labels as $day_key => $day_label): ?>
						<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-schedule-day-btn" data-day="<?php echo esc_attr($day_key); ?>"><?php echo esc_html($day_label); ?></button>
						<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e('Choose which day of the week this schedule should run on.', 'ai-post-scheduler'); ?></p>
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
			<button type="button" class="aips-btn aips-btn-secondary aips-modal-close"><?php esc_html_e('Cancel', 'ai-post-scheduler'); ?></button>
			<button type="button" class="aips-btn aips-btn-primary aips-save-schedule"><?php esc_html_e('Save Schedule', 'ai-post-scheduler'); ?></button>
		</div>
	</div>
</div>
<!-- ============================================================ -->
<!-- Schedule History Modal                                       -->
<!-- ============================================================ -->
<div id="aips-schedule-history-modal" class="aips-modal" style="display:none;"
	role="dialog" aria-modal="true">
	<div class="aips-modal-content aips-modal-large">
		<div class="aips-modal-header">
			<h2 class="aips-modal-title"><?php esc_html_e('Recent History', 'ai-post-scheduler'); ?></h2>
			<button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
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
