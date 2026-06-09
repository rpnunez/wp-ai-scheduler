import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';
import { ScheduleModel } from '../models/schedule';

/**
 * Schedules View Controller
 */
export const SchedulesView = BaseListView.extend({
	el: 'body',

	listSelector: '.aips-unified-schedule-table',
	rowSelector: '.aips-unified-row',
	searchSelector: '#aips-unified-search',
	selectAllSelector: '#cb-select-all-unified',
	checkboxSelector: '.aips-unified-checkbox',
	bulkActionSelector: '#aips-unified-bulk-action',
	bulkApplySelector: '#aips-unified-bulk-apply',

	events: _.extend({}, BaseListView.prototype.events, {
		// CRUD Operations
		'click .aips-add-schedule-btn': 'openScheduleModal',
		'click .aips-edit-schedule': 'editSchedule',
		'click .aips-clone-schedule': 'cloneSchedule',
		'click .aips-save-schedule': 'saveSchedule',
		'click .aips-delete-schedule': 'deleteSchedule',
		'change .aips-toggle-schedule': 'toggleSchedule',
		'click .aips-run-now-schedule': 'runNowSchedule',
		'click .aips-view-schedule-history': 'viewScheduleHistory',
		'click .aips-view-unified-history': 'viewScheduleHistory',

		// Unified scheduling events
		'change .aips-unified-toggle-schedule': 'toggleUnifiedSchedule',
		'click .aips-unified-run-now': 'runNowUnified',
		'change #aips-unified-type-filter': 'filterUnifiedByType',
		'keyup #aips-unified-search': 'onSearchKeyup',
		'search #aips-unified-search': 'onSearchClear',
		'click #aips-unified-search-clear': 'clearSearch',
		'click .aips-clear-unified-search-btn': 'clearSearch',
		'click .aips-renew-schedules-btn': 'renewSchedules',

		// Bulk Actions override
		'change #cb-select-all-unified': 'toggleSelectAll',
		'change .aips-unified-checkbox': 'onSelectionChange',
		'click #aips-unified-bulk-apply': 'onBulkApply'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		// Initialize Modals
		this.scheduleModal = new BaseModalView({ el: '#aips-schedule-modal' });
		this.historyModal = new BaseModalView({ el: '#aips-schedule-history-modal' });

		// Auto-initialize status strip if present
		if ($('#aips-schedule-status-strip').length) {
			this.initScheduleStatusStrip();
		}
	},

	openScheduleModal(e) {
		if (e) e.preventDefault();
		$('#aips-schedule-form')[0].reset();
		$('#schedule_id').val('');
		$('#aips-schedule-modal-title').text('Add New Schedule');
		this.scheduleModal.open();
	},

	editSchedule(e) {
		if (e) e.preventDefault();
		const id = $(e.currentTarget).data('schedule-id');
		const $btn = $(e.currentTarget);
		$btn.prop('disabled', true);

		const schedule = new ScheduleModel({ id: id });
		schedule.fetch({
			success: (model) => {
				const s = model.toJSON();
				$('#schedule_id').val(s.id);
				$('#schedule_title').val(s.title || '');
				$('#schedule_template').val(s.template_id);
				$('#schedule_frequency').val(s.frequency || 'daily');
				$('#schedule_start_time').val(s.start_time || '');
				$('#schedule_topic').val(s.topic || '');
				$('#article_structure_id').val(s.article_structure_id || '');
				$('#rotation_pattern').val(s.rotation_pattern || '');
				$('#schedule_campaign_id').val(s.campaign_id || '');
				$('#schedule_is_active').prop('checked', s.is_active == 1);
				
				$('#aips-schedule-modal-title').text('Edit Schedule');
				this.scheduleModal.open();
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
		const $btn = $(e.currentTarget);
		const $row = $btn.closest('tr');
		
		const title = $row.data('title');
		const templateId = $row.data('template-id');
		const campaignId = $row.data('campaign-id');
		const frequency = $row.data('frequency');
		const topic = $row.data('topic');
		const articleStructureId = $row.data('article-structure-id');
		const rotationPattern = $row.data('rotation-pattern');

		$('#aips-schedule-form')[0].reset();
		$('#schedule_id').val(''); // Clear ID for new schedule
		$('#schedule_title').val(title ? title + ' (Copy)' : '');
		$('#schedule_template').val(templateId || '');
		$('#schedule_campaign_id').val(campaignId || '');
		$('#schedule_frequency').val(frequency || 'daily');
		$('#schedule_topic').val(topic || '');
		$('#article_structure_id').val(articleStructureId || '');
		$('#rotation_pattern').val(rotationPattern || '');
		$('#schedule_start_time').val('');
		$('#schedule_is_active').prop('checked', true);

		$('#aips-schedule-modal-title').text('Clone Schedule');
		this.scheduleModal.open();
	},

	saveSchedule(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		
		const $form = $('#aips-schedule-form');
		if (!$form[0].checkValidity()) {
			$form[0].reportValidity();
			return;
		}

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Saving...');
		}

		const data = {
			id: $('#schedule_id').val(),
			schedule_title: $('#schedule_title').val(),
			template_id: $('#schedule_template').val(),
			frequency: $('#schedule_frequency').val(),
			start_time: $('#schedule_start_time').val(),
			topic: $('#schedule_topic').val(),
			article_structure_id: $('#article_structure_id').val(),
			rotation_pattern: $('#rotation_pattern').val(),
			campaign_id: $('#schedule_campaign_id').val(),
			is_active: $('#schedule_is_active').is(':checked') ? 1 : 0
		};

		const schedule = new ScheduleModel(data);
		schedule.save(null, {
			success: () => {
				this.scheduleModal.close();
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-unified-schedule-table');
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

	deleteSchedule(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		const confirmMsg = (window.aipsScheduleL10n && window.aipsScheduleL10n.deleteScheduleConfirm) || 'Are you sure you want to delete this schedule?';
		const cancelLabel = (window.aipsAdminL10n && window.aipsAdminL10n.confirmCancelButton) || 'Cancel';
		const deleteLabel = (window.aipsAdminL10n && window.aipsAdminL10n.confirmDeleteButton) || 'Yes, delete';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: cancelLabel, className: 'aips-btn aips-btn-primary' },
				{ label: deleteLabel, className: 'aips-btn aips-btn-danger-solid', action: () => this._executeDelete($btn, id) }
			]);
		}
	},

	_executeDelete($btn, id) {
		const schedule = new ScheduleModel({ id: id });
		schedule.destroy({
			success: () => {
				if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
					window.AIPS.refreshContentPanel('.aips-unified-schedule-table');
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
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, '<span class="dashicons dashicons-update aips-spin"></span>', { isHtml: true });
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_run_now',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				schedule_id: id
			},
			success: (response) => {
				if (response.success) {
					let msg = _.escape(response.data.message || 'Post generated successfully!');
					if (response.data.edit_url) {
						msg += ' <a href="' + _.escape(response.data.edit_url) + '" target="_blank">Edit Post</a>';
					}
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(msg, 'success', { isHtml: true, duration: 8000 });
					}
					this.initScheduleStatusStrip();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Generation failed.', 'error');
					}
				}
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Network error during execution.', 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
	},

	viewScheduleHistory(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const scheduleId = $btn.data('id');
		const scheduleName = $btn.data('name') || scheduleId;

		if (!scheduleId) return;

		const $modal = $('#aips-schedule-history-modal');
		const $title = $modal.find('#aips-schedule-history-modal-title');
		const $loading = $modal.find('#aips-schedule-history-loading');
		const $empty = $modal.find('#aips-schedule-history-empty');
		const $list = $modal.find('#aips-schedule-history-list');

		$title.text('Schedule History: ' + scheduleName);
		$loading.show();
		$empty.hide();
		$list.hide().empty();
		this.historyModal.open();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_schedule_history',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				schedule_id: scheduleId
			},
			success: (response) => {
				$loading.hide();

				if (!response.success) {
					const l10n = window.aipsScheduleL10n || {};
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || l10n.failedToLoadHistory || 'Failed to load history', 'error');
					}
					this.historyModal.close();
					return;
				}

				const entries = response.data.entries;

				if (!entries || entries.length === 0) {
					$empty.show();
					return;
				}

				const iconMap = {
					'schedule_created':  { icon: 'dashicons-plus-alt',        cls: 'aips-timeline-created'  },
					'schedule_updated':  { icon: 'dashicons-edit',             cls: 'aips-timeline-updated'  },
					'schedule_enabled':  { icon: 'dashicons-yes-alt',          cls: 'aips-timeline-enabled'  },
					'schedule_disabled': { icon: 'dashicons-minus',            cls: 'aips-timeline-disabled' },
					'schedule_executed': { icon: 'dashicons-controls-play',    cls: 'aips-timeline-executed' },
					'manual_schedule_started':   { icon: 'dashicons-controls-play', cls: 'aips-timeline-executed' },
					'manual_schedule_completed': { icon: 'dashicons-yes',           cls: 'aips-timeline-success'  },
					'manual_schedule_failed':    { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
					'schedule_failed':   { icon: 'dashicons-warning',          cls: 'aips-timeline-error'    },
					'post_published':    { icon: 'dashicons-media-document',   cls: 'aips-timeline-success'  },
					'post_draft':        { icon: 'dashicons-media-document',   cls: 'aips-timeline-draft'    },
					'post_generated':    { icon: 'dashicons-media-document',   cls: 'aips-timeline-draft'    },
				};
				const defaultIcon = { icon: 'dashicons-info', cls: '' };

				entries.forEach(entry => {
					let info = iconMap[entry.event_type] || defaultIcon;
					const isError = (entry.history_type_id === 2 || entry.event_status === 'failed');
					if (isError && !info.cls) {
						info = { icon: 'dashicons-warning', cls: 'aips-timeline-error' };
					}

					const $item = $('<li>', { 'class': 'aips-timeline-item ' + info.cls });
					const $icon = $('<span>', { 'class': 'aips-timeline-icon', 'aria-hidden': 'true' })
						.append($('<span>', { 'class': 'dashicons ' + info.icon }));
					const $content = $('<div>', { 'class': 'aips-timeline-content' });
					const $msg = $('<p>', { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
					const $time = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
						.text(entry.timestamp);

					$content.append($msg).append($time);
					$item.append($icon).append($content);
					$list.append($item);
				});

				$list.show();
			},
			error: () => {
				$loading.hide();
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Failed to load schedule history.', 'error');
				}
				this.historyModal.close();
			}
		});
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

	executeBulkAction(action, ids) {
		const $btn = this.$(this.bulkApplySelector);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($btn, 'Applying...');
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_unified_bulk_action',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				bulk_action: action,
				items: ids
			},
			success: (resp) => {
				if (resp.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Bulk action applied.', 'success');
					}
					if (window.AIPS && typeof window.AIPS.refreshContentPanel === 'function') {
						window.AIPS.refreshContentPanel('.aips-unified-schedule-table');
					} else {
						window.location.reload();
					}
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(resp.data.message || 'Action failed.', 'error');
					}
				}
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($btn);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Network error during bulk action.', 'error');
					window.AIPS.Utilities.resetButton($btn);
				}
			}
		});
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
