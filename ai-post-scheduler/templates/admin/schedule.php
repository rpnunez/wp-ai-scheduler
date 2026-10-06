<?php
if (!defined('ABSPATH')) {
	exit;
}

// Fetch template-schedule data needed for the "Add Schedule" modal
$templates_handler      = new AIPS_Templates();
$templates              = $templates_handler->get_all(true);
$structure_manager      = new AIPS_Article_Structure_Manager();
$article_structures     = $structure_manager->get_active_structures();
$template_type_selector = new AIPS_Template_Type_Selector();
$rotation_patterns      = $template_type_selector->get_rotation_patterns();

$preselect_template_id  = isset($_GET['schedule_template']) ? absint($_GET['schedule_template']) : 0;
$preselect_structure_id = isset($_GET['schedule_structure']) ? absint($_GET['schedule_structure']) : 0;
?>
		<div id="aips-schedule-status-strip" class="aips-content-panel aips-schedule-status-strip">
			<div class="aips-panel-body">
				<div id="aips-schedule-status-summary" class="aips-schedule-status-summary-cards"><?php esc_html_e('Loading schedule status…', 'ai-post-scheduler'); ?></div>
				<div id="aips-schedule-status-warnings" class="aips-schedule-status-warnings"></div>
			</div>
		</div>

		<!-- Schedules List Table Panel -->
		<div class="aips-content-panel">
			<div class="aips-panel-body no-padding">
				<?php
				$schedules_list_table = new AIPS_Schedules_List_Table();
				$schedules_list_table->prepare_items();
				$schedules_list_table->display_page();
				?>
			</div>
		</div>

<!-- ============================================================ -->
<!-- Add / Edit Template Schedule Modal                           -->
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
			<form id="aips-schedule-form" data-aips-async="true">
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
				<!-- Unified Schedule & Cadence Builder -->
				<div class="aips-form-row aips-schedule-builder-wrap">
					<label class="aips-builder-title"><?php esc_html_e('Schedule Cadence', 'ai-post-scheduler'); ?> <span class="required">*</span></label>
					<p class="description" style="margin-top:0; margin-bottom:12px;"><?php esc_html_e('Choose how often and when this schedule should run.', 'ai-post-scheduler'); ?></p>

					<!-- Hidden inputs submitted with form -->
					<input type="hidden" id="schedule_frequency" name="frequency" value="weekly">
					<input type="hidden" id="schedule_repeat_day" name="repeat_day" value="monday">
					<input type="hidden" id="schedule_start_time" name="start_time" value="">

					<!-- Cadence Segmented Tabs -->
					<div class="aips-cadence-tabs" role="tablist" aria-label="<?php esc_attr_e('Schedule Cadence', 'ai-post-scheduler'); ?>">
						<button type="button" class="aips-cadence-tab" data-cadence="once" role="tab" aria-selected="false"><?php esc_html_e('One-Time', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-cadence-tab" data-cadence="hourly" role="tab" aria-selected="false"><?php esc_html_e('Hourly', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-cadence-tab" data-cadence="daily" role="tab" aria-selected="false"><?php esc_html_e('Daily', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-cadence-tab active" data-cadence="weekly" role="tab" aria-selected="true"><?php esc_html_e('Weekly', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-cadence-tab" data-cadence="monthly" role="tab" aria-selected="false"><?php esc_html_e('Monthly', 'ai-post-scheduler'); ?></button>
						<button type="button" class="aips-cadence-tab" data-cadence="advanced" role="tab" aria-selected="false"><?php esc_html_e('Advanced', 'ai-post-scheduler'); ?></button>
					</div>

					<!-- Contextual Cadence Panes -->
					<div class="aips-cadence-panes">
						<!-- WEEKLY PANE (Default) -->
						<div class="aips-cadence-pane" id="aips-pane-weekly" style="display:block;">
							<div class="aips-pane-field-label"><?php esc_html_e('Repeat On', 'ai-post-scheduler'); ?></div>
							<div class="aips-btn-group aips-weekday-btn-group" role="group" aria-label="<?php esc_attr_e('Day of week', 'ai-post-scheduler'); ?>">
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
								<button type="button" class="aips-btn aips-btn-sm aips-schedule-day-btn<?php echo ('monday' === $day_key) ? ' active' : ''; ?>" data-day="<?php echo esc_attr($day_key); ?>"><?php echo esc_html($day_label); ?></button>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- HOURLY PANE -->
						<div class="aips-cadence-pane" id="aips-pane-hourly" style="display:none;">
							<div class="aips-pane-field-label"><?php esc_html_e('Interval', 'ai-post-scheduler'); ?></div>
							<div class="aips-btn-group aips-hourly-step-group" role="group">
								<button type="button" class="aips-btn aips-btn-sm aips-hourly-step-btn active" data-freq="hourly"><?php esc_html_e('Every Hour', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-hourly-step-btn" data-freq="every_2_hours"><?php esc_html_e('Every 2h', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-hourly-step-btn" data-freq="every_4_hours"><?php esc_html_e('Every 4h', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-hourly-step-btn" data-freq="every_6_hours"><?php esc_html_e('Every 6h', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-hourly-step-btn" data-freq="every_12_hours"><?php esc_html_e('Every 12h', 'ai-post-scheduler'); ?></button>
							</div>
						</div>

						<!-- DAILY PANE -->
						<div class="aips-cadence-pane" id="aips-pane-daily" style="display:none;">
							<p class="description" style="margin:0;"><?php esc_html_e('Runs once each day at the designated time below.', 'ai-post-scheduler'); ?></p>
						</div>

						<!-- MONTHLY PANE -->
						<div class="aips-cadence-pane" id="aips-pane-monthly" style="display:none;">
							<div class="aips-pane-field-label"><?php esc_html_e('Day of Month', 'ai-post-scheduler'); ?></div>
							<div class="aips-btn-group aips-monthly-step-group" role="group">
								<button type="button" class="aips-btn aips-btn-sm aips-monthly-day-btn active" data-month-day="1"><?php esc_html_e('1st of Month', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-monthly-day-btn" data-month-day="15"><?php esc_html_e('15th of Month', 'ai-post-scheduler'); ?></button>
								<button type="button" class="aips-btn aips-btn-sm aips-monthly-day-btn" data-month-day="last"><?php esc_html_e('Last Day', 'ai-post-scheduler'); ?></button>
							</div>
						</div>

						<!-- ONE-TIME PANE -->
						<div class="aips-cadence-pane" id="aips-pane-once" style="display:none;">
							<div class="aips-pane-field-label"><?php esc_html_e('Target Execution Date', 'ai-post-scheduler'); ?></div>
							<input type="date" id="schedule_builder_date" class="regular-text" style="max-width:220px;">
						</div>

						<!-- ADVANCED PANE -->
						<div class="aips-cadence-pane" id="aips-pane-advanced" style="display:none;">
							<div class="aips-pane-field-label"><?php esc_html_e('Custom Recurrence', 'ai-post-scheduler'); ?></div>
							<div class="aips-custom-interval-stepper">
								<span><?php esc_html_e('Every', 'ai-post-scheduler'); ?></span>
								<input type="number" id="schedule_builder_interval_val" min="1" max="90" value="2" style="width:65px;">
								<select id="schedule_builder_interval_unit" style="max-width:130px;">
									<option value="days" selected><?php esc_html_e('Days', 'ai-post-scheduler'); ?></option>
									<option value="weeks"><?php esc_html_e('Weeks', 'ai-post-scheduler'); ?></option>
									<option value="hours"><?php esc_html_e('Hours', 'ai-post-scheduler'); ?></option>
								</select>
							</div>
						</div>
					</div>

					<!-- TIME PICKER & PRESET CHIPS -->
					<div class="aips-builder-time-row" id="aips-builder-time-row">
						<div class="aips-builder-time-header">
							<span class="aips-pane-field-label" style="margin:0;"><?php esc_html_e('Time of Day', 'ai-post-scheduler'); ?></span>
							<span class="aips-tz-badge" id="aips-builder-tz-badge"><?php echo esc_html(wp_timezone_string()); ?></span>
						</div>
						<div class="aips-builder-time-controls">
							<input type="time" id="schedule_builder_time" value="09:00">
							<div class="aips-time-chips" role="group" aria-label="<?php esc_attr_e('Time presets', 'ai-post-scheduler'); ?>">
								<button type="button" class="aips-time-chip active" data-time="09:00">09:00 AM</button>
								<button type="button" class="aips-time-chip" data-time="14:00">02:00 PM</button>
								<button type="button" class="aips-time-chip" data-time="18:00">06:00 PM</button>
								<button type="button" class="aips-time-chip" data-time="22:00">10:00 PM</button>
							</div>
						</div>
						<p class="description aips-builder-time-hint" id="aips-builder-time-hint" style="margin-top:4px; display:none;"></p>
					</div>

					<!-- DYNAMIC LIVE SUMMARY CARD -->
					<div class="aips-schedule-summary-card" id="aips-schedule-summary-card">
						<div class="aips-summary-card-icon">
							<span class="dashicons dashicons-calendar-alt"></span>
						</div>
						<div class="aips-summary-card-body">
							<div class="aips-summary-card-title" id="aips-summary-card-title"><?php esc_html_e('Every Monday at 9:00 AM', 'ai-post-scheduler'); ?></div>
							<div class="aips-summary-card-subtitle" id="aips-summary-card-subtitle">...</div>
						</div>
					</div>
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
