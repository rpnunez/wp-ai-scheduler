import Backbone from 'backbone';
import $ from 'jquery';
import { ScheduleModel } from '../models/schedule';

/**
 * Schedules View Controller
 */
export const SchedulesView = Backbone.View.extend({
	el: 'body',

	events: {
		// CRUD Operations
		'click .aips-add-schedule-btn': 'openScheduleModal',
		'click .aips-edit-schedule': 'editSchedule',
		'click .aips-clone-schedule': 'cloneSchedule',
		'click .aips-save-schedule': 'saveSchedule',
		'click .aips-save-schedule-wizard': 'saveScheduleWizard',
		'click .aips-delete-schedule': 'deleteSchedule',
		'change .aips-toggle-schedule': 'toggleSchedule',
		'click .aips-run-now-schedule': 'runNowSchedule',
		'click .aips-view-schedule-history': 'viewScheduleHistory',

		// Unified scheduling events
		'change .aips-unified-toggle-schedule': 'toggleUnifiedSchedule',
		'click .aips-unified-run-now': 'runNowUnified',
		'change #aips-unified-type-filter': 'filterUnifiedByType',
		'keyup #aips-unified-search': 'filterUnifiedSchedules',
		'search #aips-unified-search': 'filterUnifiedSchedules',
		'click #aips-unified-search-clear': 'clearUnifiedSearch',
		'click .aips-clear-unified-search-btn': 'clearUnifiedSearch',
		'click .aips-renew-schedules-btn': 'renewSchedules',

		// Bulk Actions
		'change #cb-select-all-unified': 'toggleAllUnified',
		'change .aips-unified-checkbox': 'toggleUnifiedSelection',
		'click #aips-unified-bulk-apply': 'applyUnifiedBulkAction'
	},

	initialize() {
		// Auto-initialize status strip if present
		if ($('#aips-schedule-status-strip').length) {
			this.initScheduleStatusStrip();
		}
	},

	openScheduleModal(e) {
		if (e) e.preventDefault();
		// Form reset and modal opening
		$('#aips-schedule-form')[0].reset();
		$('#schedule_id').val('');
		$('#aips-schedule-modal-title').text('Add New Schedule');
		$('#aips-schedule-modal').show();
	},

	editSchedule(e) {
		if (e) e.preventDefault();
		const id = $(e.currentTarget).data('id');
		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);

		const schedule = new ScheduleModel({ id: id });
		schedule.fetch({
			success: (model) => {
				const s = model.toJSON();
				$('#schedule_id').val(s.id);
				$('#schedule_template_id').val(s.template_id);
				$('#schedule_type').val(s.schedule_type);
				$('#schedule_interval_value').val(s.interval_value);
				$('#schedule_interval_unit').val(s.interval_unit);
				$('#schedule_start_time').val(s.start_time);
				$('#schedule_is_active').prop('checked', s.is_active == 1);
				
				$('#aips-schedule-modal-title').text('Edit Schedule');
				$('#aips-schedule-modal').show();
				$btn.prop('disabled', false);
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Error loading schedule.', 'error');
				}
				$btn.prop('disabled', false);
			}
		});
	},

	cloneSchedule(e) {
		if (e) e.preventDefault();
		// Clone action
	},

	saveSchedule(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Saving...');
		}

		const data = {
			id: $('#schedule_id').val(),
			template_id: $('#schedule_template_id').val(),
			schedule_type: $('#schedule_type').val(),
			interval_value: $('#schedule_interval_value').val(),
			interval_unit: $('#schedule_interval_unit').val(),
			start_time: $('#schedule_start_time').val(),
			is_active: $('#schedule_is_active').is(':checked') ? 1 : 0
		};

		const schedule = new ScheduleModel(data);
		schedule.save(null, {
			success: () => {
				$('#aips-schedule-modal').hide();
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-schedules-list');
				} else {
					window.location.reload();
				}
			},
			error: (model, err) => {
				const msg = (err && err.message) || 'Error saving schedule.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	saveScheduleWizard(e) {
		if (e) e.preventDefault();
		// Save schedule from wizard view
	},

	deleteSchedule(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Are you sure you want to delete this schedule?', 'Confirm Delete', [
				{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: () => this._executeDelete($btn, id) }
			]);
		}
	},

	_executeDelete($btn, id) {
		const schedule = new ScheduleModel({ id: id });
		schedule.destroy({
			success: () => {
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-schedules-list');
				} else {
					window.location.reload();
				}
			},
			error: (model, err) => {
				const msg = (err && err.message) || 'Error deleting schedule.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'error');
				}
			}
		});
	},

	toggleSchedule(e) {
		const $cb = $(e.currentTarget);
		const id = $cb.data('id');
		const active = $cb.is(':checked') ? 1 : 0;

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_toggle_schedule',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				schedule_id: id,
				is_active: active
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Failed to toggle schedule state.', 'error');
				}
				$cb.prop('checked', !active);
			}
		});
	},

	runNowSchedule(e) {
		if (e) e.preventDefault();
		// Run single schedule immediately
	},

	viewScheduleHistory(e) {
		if (e) e.preventDefault();
		// Load schedule history modal
	},

	toggleUnifiedSchedule(e) {
		const $cb = $(e.currentTarget);
		const id = $cb.data('id');
		const type = $cb.data('type');
		const active = $cb.is(':checked') ? 1 : 0;

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_toggle_unified_schedule',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				item_id: id,
				item_type: type,
				is_active: active
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Failed to toggle schedule state.', 'error');
				}
				$cb.prop('checked', !active);
			}
		});
	},

	runNowUnified(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');
		const type = $btn.data('type');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Running...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_run_now_unified',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				item_id: id,
				item_type: type
			},
			success: (resp) => {
				if (resp.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Run initiated.', 'success');
					}
					this.initScheduleStatusStrip();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Run failed.', 'error');
					}
				}
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Failed to execute run.', 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	renewSchedules(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Renewing...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_renew_schedules',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			success: (resp) => {
				if (resp.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Schedules renewed successfully.', 'success');
					}
					if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
						window.AIPS.refreshContentPanel('#aips-schedule-status-strip');
					} else {
						window.location.reload();
					}
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Failed to renew.', 'error');
						window.AIPS.Utilities.resetButton($btn);
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Failed to renew schedules.', 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	filterUnifiedByType(e) {
		const val = $(e.currentTarget).val();
		if (!val) {
			$('.aips-unified-row').show();
			return;
		}
		$('.aips-unified-row').each(function() {
			const type = $(this).data('type');
			$(this).toggle(type === val);
		});
	},

	filterUnifiedSchedules() {
		const query = $('#aips-unified-search').val().toLowerCase();
		$('.aips-unified-row').each(function() {
			const text = $(this).text().toLowerCase();
			$(this).toggle(text.indexOf(query) > -1);
		});
	},

	clearUnifiedSearch(e) {
		if (e) e.preventDefault();
		$('#aips-unified-search').val('');
		this.filterUnifiedSchedules();
	},

	toggleAllUnified(e) {
		const checked = $(e.currentTarget).is(':checked');
		$('.aips-unified-checkbox').prop('checked', checked);
	},

	toggleUnifiedSelection() {
		// Selection stats
	},

	applyUnifiedBulkAction(e) {
		if (e) e.preventDefault();
		// Bulk action implementation
	},

	initScheduleStatusStrip() {
		$.post(
			(window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			{
				action: 'aips_get_schedule_status_read_model',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			(resp) => {
				if (!resp || !resp.success || !resp.data) {
					const label = (window.aipsScheduleL10n && window.aipsScheduleL10n.scheduleStatusLoadFailed) || 'Failed to load status';
					$('#aips-schedule-status-summary').text(label);
					return;
				}

				const d = resp.data;
				let queueTotal = 0;
				$.each(d.queue_depth || {}, (_, count) => {
					queueTotal += parseInt(count || 0, 10);
				});

				const counts = d.schedule_counts || {};
				const l10n = window.aipsScheduleL10n || {};
				
				const cards = [
					{
						label: l10n.activeSchedulesLabel || 'Active Schedules',
						value: parseInt(counts.active || 0, 10),
						tone: 'info',
						icon: 'dashicons-calendar-alt'
					},
					{
						label: l10n.upcomingSchedulesLabel || 'Upcoming Schedules (24h)',
						value: parseInt(counts.upcoming_24h || 0, 10),
						tone: 'success',
						icon: 'dashicons-clock'
					},
					{
						label: l10n.successRateLabel || 'Success Rate',
						value: (counts.success_rate !== undefined ? counts.success_rate : 0) + '%',
						tone: (counts.success_rate || 0) >= 90 ? 'success' : ((counts.success_rate || 0) >= 70 ? 'warning' : 'error'),
						icon: 'dashicons-chart-bar'
					},
					{
						label: l10n.queueDepthLabel || 'Queue Depth',
						value: queueTotal,
						tone: queueTotal > 0 ? 'warning' : 'neutral',
						icon: 'dashicons-list-view'
					},
					{
						label: l10n.bulkFailedLabel || 'Bulk Failed',
						value: parseInt((d.bulk_jobs && d.bulk_jobs.failed) || 0, 10),
						tone: parseInt((d.bulk_jobs && d.bulk_jobs.failed) || 0, 10) > 0 ? 'error' : 'neutral',
						icon: 'dashicons-warning'
					}
				];

				const cardsHtml = cards.map((card) => {
					const iconHtml = card.icon ? '<span class="dashicons ' + _.escape(card.icon) + ' aips-schedule-status-card-icon"></span>' : '';
					if (window.AIPS && window.AIPS.Templates) {
						return window.AIPS.Templates.render('aips-tmpl-schedule-status-card', {
							tone: _.escape(card.tone),
							label: _.escape(card.label),
							iconHtml: iconHtml,
							value: _.escape(card.value)
						});
					}
					return '';
				});
				$('#aips-schedule-status-summary').html(cardsHtml.join(''));

				const formatTimestamp = (timestamp, fallback) => {
					if (!timestamp) return fallback || '—';
					const dt = new Date(timestamp * 1000);
					return dt.toLocaleString();
				};

				const lastSuccessItems = [
					{ label: l10n.typeTemplateLabel || 'Template Schedules', time: formatTimestamp(d.last_success.template_schedule, 'Never') },
					{ label: l10n.typeAuthorTopicLabel || 'Author Topic Gen', time: formatTimestamp(d.last_success.author_topic_gen, 'Never') },
					{ label: l10n.typeAuthorPostLabel || 'Author Post Gen', time: formatTimestamp(d.last_success.author_post_gen, 'Never') }
				];

				const nextRunItems = [
					{ label: l10n.typeTemplateLabel || 'Template Schedules', time: formatTimestamp(d.next_runs.template_schedule, 'Not scheduled') },
					{ label: l10n.typeAuthorTopicLabel || 'Author Topic Gen', time: formatTimestamp(d.next_runs.author_topic_gen, 'Not scheduled') },
					{ label: l10n.typeAuthorPostLabel || 'Author Post Gen', time: formatTimestamp(d.next_runs.author_post_gen, 'Not scheduled') }
				];

				const lastSuccessHtml = lastSuccessItems.map((item) => {
					if (window.AIPS && window.AIPS.Templates) {
						return window.AIPS.Templates.render('aips-tmpl-schedule-status-row', {
							label: item.label,
							time: item.time
						});
					}
					return '';
				}).join('');

				const nextRunHtml = nextRunItems.map((item) => {
					if (window.AIPS && window.AIPS.Templates) {
						return window.AIPS.Templates.render('aips-tmpl-schedule-status-row', {
							label: item.label,
							time: item.time
						});
					}
					return '';
				}).join('');

				$('#aips-schedule-status-timeline').html(lastSuccessHtml);
				$('#aips-schedule-status-queue-timeline').html(nextRunHtml);

				const warnings = [];
				if (d.last_error) {
					const viewHistoryLabel = l10n.viewHistory || 'View History';
					const systemStatusLabel = l10n.systemStatus || 'System Status';
					warnings.push('<div class="notice notice-error inline"><p>' + _.escape(l10n.lastErrorDetected || 'Last run detected errors.') + ' <a href="' + _.escape(d.quick_links.history) + '">' + _.escape(viewHistoryLabel) + '</a> · <a href="' + _.escape(d.quick_links.system_status) + '">' + _.escape(systemStatusLabel) + '</a></p></div>');
				}
				if (d.retry_pending) {
					const notifLabel = l10n.notifications || 'Notifications';
					const telLabel = l10n.telemetry || 'Telemetry';
					warnings.push('<div class="notice notice-warning inline"><p>' + _.escape(l10n.retryPending || 'Retry pending.') + ' <a href="' + _.escape(d.quick_links.notifications) + '">' + _.escape(notifLabel) + '</a> · <a href="' + _.escape(d.quick_links.telemetry) + '">' + _.escape(telLabel) + '</a></p></div>');
				}
				if (parseInt((counts.overdue || 0), 10) > 0) {
					const title = (l10n.overdueSchedulesWarning || 'Overdue Schedules: %d').replace('%d', counts.overdue);
					const desc = l10n.overdueSchedulesDesc || 'Schedules might be stalled. Click Renew to artificially bring them up to date.';
					const btnLabel = l10n.renewSchedulesLabel || 'Renew Schedules';

					if (window.AIPS && window.AIPS.Templates) {
						warnings.push(
							window.AIPS.Templates.render('aips-tmpl-schedule-overdue-banner', {
								title: title,
								desc: desc,
								btnLabel: btnLabel
							})
						);
					}
				}
				$('#aips-schedule-status-warnings').html(warnings.join(''));
			}
		);
	}
});
