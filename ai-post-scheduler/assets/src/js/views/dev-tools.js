import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseModalView } from './base-modal';

/**
 * Developer Tools & Cache Monitor View Controller
 */
export const DevToolsView = Backbone.View.extend({
	el: 'body',

	events: {
		// Scaffold Events
		'submit #aips-dev-scaffold-form': 'generateScaffold',

		// Seeder Events
		'submit #aips-seeder-form': 'runSeeder',

		// DB Operations Events
		'click .aips-repair-db': 'repairDb',
		'click .aips-fix-datetime-db': 'fixDateTimeValues',
		'click .aips-reinstall-db': 'reinstallDb',
		'click .aips-wipe-db': 'wipeDb',
		'click .aips-export-data': 'exportData',
		'click .aips-import-data': 'importData',
		'click .aips-notifications-hygiene': 'runNotificationsHygiene',
		'click .aips-flush-cron': 'flushCronEvents',

		// Cache Monitor Maintenance Events
		'click .aips-cache-monitor-refresh': 'refreshCacheMonitor',
		'click .aips-cache-flush-expired': 'flushExpiredCache',
		'click .aips-cache-flush-all-btn': 'flushAllCache',
		'click .aips-cache-flush-group': 'flushCacheGroup',
		'click .aips-cache-invalidate-tag': 'invalidateCacheTag',
		'click .aips-cache-invalidate-domain': 'invalidateCacheDomain',

		// Cache Entries List Events
		'click #aips-cache-entries-search-btn': 'searchCacheEntries',
		'keydown #aips-cache-search': 'onCacheSearchKeydown',
		'click .aips-entries-prev': 'prevCacheEntriesPage',
		'click .aips-entries-next': 'nextCacheEntriesPage',
		'change #aips-cache-per-page': 'changeCachePerPage',
		'change #aips-cache-select-all': 'toggleSelectAllCache',
		'click .aips-cache-inspect-link, .aips-cache-inspect-btn': 'inspectCacheEntry',
		'click .aips-cache-delete-link': 'deleteCacheEntry',
		'click #aips-cache-bulk-apply': 'bulkDeleteCacheEntries',

		// Cache Operations & Events
		'click #aips-ops-search-btn': 'searchCacheOperations',
		'click #aips-events-load-btn': 'loadCacheEvents',
		'click .aips-maintenance-action-btn': 'runCacheMaintenance'
	},

	initialize() {
		this.l10n = window.aipsCacheMonitor || { i18n: {} };
		this.adminL10n = window.aipsAdminL10n || {};

		// Initialize inspect modal if present
		if (this.$('#aips-cache-inspect-modal').length) {
			this.inspectModal = new BaseModalView({ el: '#aips-cache-inspect-modal' });
		}

		// Cache Monitor page entries state
		this.entriesState = {
			page: 1,
			perPage: 50,
			filters: {},
			orderby: 'updated_at',
			order: 'DESC'
		};
		this.eventsPage = 1;

		if (this.$('#aips-cache-entries-tbody').length) {
			this.loadEntries();
		}
	},

	// Helper: Escape HTML string
	esc(str) {
		return $('<span>').text(String(str)).html();
	},

	// Helper: Escape Attribute value
	escAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	},

	// Helper: Format bytes to human readable format
	formatBytes(bytes) {
		bytes = parseInt(bytes, 10) || 0;
		if (bytes < 1024) { return bytes + ' B'; }
		if (bytes < 1048576) { return (bytes / 1024).toFixed(1) + ' KB'; }
		return (bytes / 1048576).toFixed(2) + ' MB';
	},

	// Helper: Format unix timestamp
	formatTs(ts) {
		ts = parseInt(ts, 10);
		if (!ts) { return (this.l10n.i18n && this.l10n.i18n.never) || 'Never'; }
		return new Date(ts * 1000).toLocaleString();
	},

	// -------------------------------------------------------------------------
	// Scaffold Features
	// -------------------------------------------------------------------------
	generateScaffold(e) {
		e.preventDefault();

		const $form = $(e.currentTarget);
		const $btn = this.$('#aips-generate-scaffold-btn');
		const $spinner = $form.find('.spinner');
		const $output = this.$('#aips-dev-output');
		const $error = this.$('#aips-dev-error');

		$output.hide().find('#aips-dev-output-list').empty();
		$error.hide();

		const topic = this.$('#topic').val().trim();
		if (!topic) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please enter a topic.', 'warning');
			}
			return;
		}

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');

		let formData = $form.serialize();
		formData += '&action=aips_generate_scaffold&nonce=' + ((window.aipsAjax && window.aipsAjax.nonce) || '');

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, formData, (response) => {
			if (response.success) {
				this.$('#aips-dev-output-message').text(response.data.message);

				let listHtml = '';
				if (response.data.items && response.data.items.length) {
					response.data.items.forEach((item) => {
						listHtml += '<li>' + item + '</li>';
					});
				}
				this.$('#aips-dev-output-list').html(listHtml);
				$output.fadeIn();
			} else {
				this.$('#aips-dev-error-message').text(response.data.message);
				$error.fadeIn();
			}
		}).fail(() => {
			this.$('#aips-dev-error-message').text('An error occurred. Please try again.');
			$error.fadeIn();
		}).always(() => {
			$btn.prop('disabled', false);
			$spinner.removeClass('is-active');
		});
	},

	// -------------------------------------------------------------------------
	// Seeder Features
	// -------------------------------------------------------------------------
	runSeeder(e) {
		e.preventDefault();

		const $form = $(e.currentTarget);
		const $submitBtn = this.$('#aips-seeder-submit');
		const $spinner = $form.find('.spinner');
		const $results = this.$('#aips-seeder-results');
		const $log = this.$('#aips-seeder-log');

		const queue = [];
		const keywords = this.$('#seeder-keywords').val();
		const voices = parseInt(this.$('#seeder-voices').val(), 10) || 0;
		const templates = parseInt(this.$('#seeder-templates').val(), 10) || 0;
		const schedule = parseInt(this.$('#seeder-schedule').val(), 10) || 0;
		const planner = parseInt(this.$('#seeder-planner').val(), 10) || 0;

		if (voices > 0) queue.push({ type: 'voices', count: voices, label: 'Voices', keywords: keywords });
		if (templates > 0) queue.push({ type: 'templates', count: templates, label: 'Templates', keywords: keywords });
		if (schedule > 0) queue.push({ type: 'schedule', count: schedule, label: 'Scheduled Templates', keywords: keywords });
		if (planner > 0) queue.push({ type: 'planner', count: planner, label: 'Planner Entries', keywords: keywords });

		if (queue.length === 0) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please enter at least one quantity.', 'warning');
			}
			return;
		}

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('This will generate dummy data in your database. Are you sure?', 'Notice', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, generate',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$submitBtn.prop('disabled', true);
						$spinner.addClass('is-active');
						$results.show();
						$log.empty().append('<div>Starting Seeder...</div>');
						this.processSeederQueue(queue, $submitBtn, $spinner, $log);
					}
				}
			]);
		}
	},

	processSeederQueue(queue, $submitBtn, $spinner, $log) {
		if (queue.length === 0) {
			$log.append('<div><strong>All Done!</strong></div>');
			$submitBtn.prop('disabled', false);
			$spinner.removeClass('is-active');
			return;
		}

		const task = queue.shift();
		$log.append(`<div>Generating ${task.count} ${task.label}...</div>`);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_process_seeder',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				type: task.type,
				count: task.count,
				keywords: task.keywords
			},
			success: (response) => {
				if (response.success) {
					$log.append(`<div style="color: green;">✔ ${response.data.message}</div>`);
				} else {
					$log.append(`<div style="color: red;">✘ Error: ${response.data.message}</div>`);
				}
				this.processSeederQueue(queue, $submitBtn, $spinner, $log);
			},
			error: (xhr, status, error) => {
				$log.append(`<div style="color: red;">✘ AJAX Error: ${error}</div>`);
				this.processSeederQueue(queue, $submitBtn, $spinner, $log);
			}
		});
	},

	// -------------------------------------------------------------------------
	// Database Actions
	// -------------------------------------------------------------------------
	repairDb(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Are you sure you want to run the database repair? This will attempt to create missing tables and columns.', 'Confirm', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, repair',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Repairing...');
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_repair_db',
							nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
						}, (response) => {
							if (response.success) {
								window.AIPS.Utilities.showToast(response.data.message, 'success');
								setTimeout(() => location.reload(), 1500);
							} else {
								window.AIPS.Utilities.showToast(response.data.message, 'error');
							}
						}).fail(() => {
							window.AIPS.Utilities.showToast('An error occurred.', 'error');
						}).always(() => {
							$btn.prop('disabled', false).text('Repair DB Tables');
						});
					}
				}
			]);
		}
	},

	fixDateTimeValues(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Run the date/time repair routine? This will normalize legacy date/time storage and backfill missing next-run values for active schedules, authors, and sources.', 'Confirm', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, fix values',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Fixing...');
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_fix_datetime_values',
							nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
						}, (response) => {
							if (response.success) {
								window.AIPS.Utilities.showToast(response.data.message, 'success');
								setTimeout(() => location.reload(), 1500);
							} else {
								window.AIPS.Utilities.showToast(response.data.message, 'error');
							}
						}).fail(() => {
							window.AIPS.Utilities.showToast('An error occurred.', 'error');
						}).always(() => {
							$btn.prop('disabled', false).text('Fix Date/Time Values in DB');
						});
					}
				}
			]);
		}
	},

	reinstallDb(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const backup = this.$('#aips-backup-db').is(':checked');
		let msg = 'Are you sure you want to reinstall the database tables?';
		if (!backup) {
			msg += ' WARNING: ALL DATA WILL BE LOST unless you check the backup option!';
		} else {
			msg += ' Data will be backed up and restored.';
		}

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(msg, 'Confirm', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, reinstall',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Reinstalling...');
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_reinstall_db',
							nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
							backup: backup
						}, (response) => {
							if (response.success) {
								window.AIPS.Utilities.showToast(response.data.message, 'success');
								setTimeout(() => location.reload(), 1500);
							} else {
								window.AIPS.Utilities.showToast(response.data.message, 'error');
							}
						}).fail(() => {
							window.AIPS.Utilities.showToast('An error occurred.', 'error');
						}).always(() => {
							$btn.prop('disabled', false).text('Reinstall DB Tables');
						});
					}
				}
			]);
		}
	},

	wipeDb(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Are you sure you want to WIPE ALL DATA? This cannot be undone.', 'Warning', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, wipe all data',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Wiping...');
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_wipe_db',
							nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
						}, (response) => {
							if (response.success) {
								window.AIPS.Utilities.showToast(response.data.message, 'success');
								setTimeout(() => location.reload(), 1500);
							} else {
								window.AIPS.Utilities.showToast(response.data.message, 'error');
							}
						}).fail(() => {
							window.AIPS.Utilities.showToast('An error occurred.', 'error');
						}).always(() => {
							$btn.prop('disabled', false).text('Wipe Plugin Data');
						});
					}
				}
			]);
		}
	},

	exportData(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const format = this.$('#aips-export-format').val() || 'json';

		$btn.prop('disabled', true).text('Exporting...');

		const form = $('<form>', {
			'method': 'POST',
			'action': (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl
		});

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'action',
			'value': 'aips_export_data'
		}));

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'nonce',
			'value': (window.aipsAjax && window.aipsAjax.nonce) || ''
		}));

		form.append($('<input>', {
			'type': 'hidden',
			'name': 'format',
			'value': format
		}));

		form.appendTo('body').submit();

		setTimeout(() => {
			$btn.prop('disabled', false).text('Export Data');
			form.remove();
		}, 1000);
	},

	importData(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const format = this.$('#aips-import-format').val();
		const fileInput = this.$('#aips-import-file')[0];

		if (!fileInput.files || !fileInput.files[0]) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast('Please select a file to import.', 'warning');
			}
			return;
		}

		const confirmMsg = 'WARNING: This will overwrite existing data! Have you made a backup? This action is irreversible. Are you sure you want to continue?';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Warning', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, import',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Importing...');

						const formData = new FormData();
						formData.append('action', 'aips_import_data');
						formData.append('nonce', (window.aipsAjax && window.aipsAjax.nonce) || '');
						formData.append('format', format);
						formData.append('import_file', fileInput.files[0]);

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: formData,
							processData: false,
							contentType: false,
							success: (response) => {
								if (response.success) {
									window.AIPS.Utilities.showToast(response.data.message, 'success');
									setTimeout(() => location.reload(), 1500);
								} else {
									window.AIPS.Utilities.showToast('Import failed: ' + response.data.message, 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast('An error occurred during import.', 'error');
							},
							complete: () => {
								$btn.prop('disabled', false).text('Import Data');
								fileInput.value = '';
							}
						});
					}
				}
			]);
		}
	},

	runNotificationsHygiene(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $result = this.$('.aips-notifications-hygiene-result');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm('Run notifications hygiene now? This cleans legacy notification options, unschedules deprecated hooks, and normalizes preferences.', 'Confirm', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{
					label: 'Yes, run hygiene',
					className: 'aips-btn aips-btn-danger-solid',
					action: () => {
						$btn.prop('disabled', true).text('Running...');
						$result.hide().empty();

						$.ajax({
							url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
							type: 'POST',
							data: {
								action: 'aips_notifications_data_hygiene',
								nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
							},
							success: (response) => {
								if (response.success) {
									const details = response.data && response.data.details ? response.data.details : {};
									const summary = 'Removed options: ' + (details.removed_options || 0)
										+ ' | Unscheduled events: ' + (details.unscheduled_events || 0)
										+ ' | Rollup scheduled: ' + ((details.rollup_scheduled || 0) ? 'yes' : 'no')
										+ ' | Preferences normalized: ' + ((details.preferences_changed || 0) ? 'yes' : 'no');
									window.AIPS.Utilities.showToast(response.data.message, 'success');
									$result.html('<p class="aips-status-message aips-status-success">' + summary + '</p>').show();
								} else {
									window.AIPS.Utilities.showToast(response.data.message || 'Hygiene command failed.', 'error');
								}
							},
							error: () => {
								window.AIPS.Utilities.showToast('An error occurred while running hygiene.', 'error');
							},
							complete: () => {
								$btn.prop('disabled', false).text('Run Notifications Hygiene');
							}
						});
					}
				}
			]);
		}
	},

	flushCronEvents(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $result = this.$('.aips-flush-cron-result');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(
				'This will remove ALL registered instances of every plugin WP-Cron event and re-register each one exactly once. Use this when duplicate cron events have accumulated and are causing excessive AI calls. Continue?',
				'Flush WP-Cron Events',
				[
					{ label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
					{
						label: 'Yes, flush & reschedule',
						className: 'aips-btn aips-btn-danger-solid',
						action: () => {
							$btn.prop('disabled', true).text('Flushing...');
							$result.hide().empty();

							$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
								action: 'aips_flush_cron_events',
								nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
							}, (response) => {
								if (response.success) {
									const details = response.data && response.data.details ? response.data.details : {};
									const rescheduled = details.rescheduled ? details.rescheduled.join(', ') : '';
									let summary = response.data.message;
									if (rescheduled) {
										summary += ' Rescheduled: ' + rescheduled + '.';
									}
									window.AIPS.Utilities.showToast(response.data.message, 'success');
									$result.html('<p class="aips-status-message aips-status-success">' + this.esc(summary) + '</p>').show();
									setTimeout(() => location.reload(), 2000);
								} else {
									const errMsg = response.data && response.data.message ? response.data.message : 'Flush failed.';
									window.AIPS.Utilities.showToast(errMsg, 'error');
									$result.html('<p class="aips-status-message aips-status-error">' + this.esc(errMsg) + '</p>').show();
								}
							}).fail(() => {
								window.AIPS.Utilities.showToast('An error occurred while flushing cron events.', 'error');
							}).always(() => {
								$btn.prop('disabled', false).text('Flush WP-Cron Events');
							});
						}
					}
				]
			);
		}
	},

	// -------------------------------------------------------------------------
	// Cache Monitor Panel Actions
	// -------------------------------------------------------------------------
	refreshCacheMonitor(e) {
		if (e) e.preventDefault();
		location.reload();
	},

	flushExpiredCache(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';

		$btn.prop('disabled', true);
		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_flush_expired',
			nonce: actionNonce
		}, (res) => {
			$btn.prop('disabled', false);
			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					window.AIPS.Utilities.showToast(res.data.message, 'success');
				} else {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		}).fail(() => {
			$btn.prop('disabled', false);
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast((this.l10n.i18n && this.l10n.i18n.requestFailed) || 'Request failed.', 'error');
			}
		});
	},

	flushAllCache(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';
		const confirmMsg = (this.l10n.i18n && this.l10n.i18n.confirmFlushAll) || 'This will flush ALL plugin-owned cache. Are you sure?';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(
				confirmMsg,
				(this.l10n.i18n && this.l10n.i18n.flushAllTitle) || 'Flush All Plugin Cache',
				[{
					label: (this.l10n.i18n && this.l10n.i18n.confirmBtn) || 'Confirm Flush',
					className: 'aips-btn-danger',
					action: () => {
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_cache_monitor_flush_all',
							nonce: actionNonce,
							confirmed: 1
						}, (res) => {
							if (res.success) {
								window.AIPS.Utilities.showToast(res.data.message, 'success');
								setTimeout(() => location.reload(), 1200);
							} else {
								window.AIPS.Utilities.showToast(res.data.message, 'error');
							}
						});
					}
				}]
			);
		}
	},

	flushCacheGroup(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const group = $btn.attr('data-group') || $btn.data('group');
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';
		const confirmMsg = $btn.attr('data-confirm') || $btn.data('confirm') || ('Flush group "' + group + '"?');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(
				confirmMsg,
				(this.l10n.i18n && this.l10n.i18n.flushGroupTitle) || 'Flush Cache Group',
				[{
					label: (this.l10n.i18n && this.l10n.i18n.flushGroupBtn) || 'Flush Group',
					className: 'aips-btn-danger',
					action: () => {
						$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
							action: 'aips_cache_monitor_flush_group',
							nonce: actionNonce,
							cache_group: group
						}, (res) => {
							if (res.success) {
								window.AIPS.Utilities.showToast(res.data.message, 'success');
								setTimeout(() => location.reload(), 1200);
							} else {
								window.AIPS.Utilities.showToast(res.data.message, 'error');
							}
						});
					}
				}]
			);
		}
	},

	invalidateCacheTag(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const tag = $btn.attr('data-tag') || $btn.data('tag');
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_invalidate_tag',
			nonce: actionNonce,
			tag: tag
		}, (res) => {
			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					window.AIPS.Utilities.showToast(res.data.message, 'success');
					$btn.closest('tr').find('.aips-badge').text('v' + res.data.new_version);
				} else {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		});
	},

	invalidateCacheDomain(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const domain = $btn.attr('data-domain') || $btn.data('domain');
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_invalidate_domain',
			nonce: actionNonce,
			domain: domain
		}, (res) => {
			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					window.AIPS.Utilities.showToast(res.data.message, 'success');
				} else {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		});
	},

	// -------------------------------------------------------------------------
	// Cache Entries List & Pagination
	// -------------------------------------------------------------------------
	searchCacheEntries(e) {
		if (e) e.preventDefault();
		this.entriesState.filters.search = this.$('#aips-cache-search').val();
		this.entriesState.filters.group = this.$('#aips-cache-filter-group').val();
		this.entriesState.filters.tier = this.$('#aips-cache-filter-tier').val();
		this.entriesState.filters.ttl_state = this.$('#aips-cache-filter-ttl').val();
		this.entriesState.page = 1;
		this.loadEntries();
	},

	onCacheSearchKeydown(e) {
		if (e.key === 'Enter') {
			this.searchCacheEntries();
		}
	},

	prevCacheEntriesPage(e) {
		e.preventDefault();
		if (this.entriesState.page > 1) {
			this.entriesState.page--;
			this.loadEntries();
		}
	},

	nextCacheEntriesPage(e) {
		e.preventDefault();
		this.entriesState.page++;
		this.loadEntries();
	},

	changeCachePerPage(e) {
		this.entriesState.perPage = parseInt($(e.currentTarget).val(), 10) || 50;
		this.entriesState.page = 1;
		this.loadEntries();
	},

	toggleSelectAllCache(e) {
		this.$('.aips-cache-entry-cb').prop('checked', $(e.currentTarget).is(':checked'));
	},

	inspectCacheEntry(e) {
		e.preventDefault();
		const hash = $(e.currentTarget).attr('data-hash') || $(e.currentTarget).data('hash');
		const readNonce = (window.aipsCacheMonitor && window.aipsCacheMonitor.nonce) || '';

		if (this.inspectModal) {
			this.inspectModal.open();
		}
		this.$('#aips-cache-inspect-body').html('<p>' + this.esc((this.l10n.i18n && this.l10n.i18n.loading) || 'Loading…') + '</p>');

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_inspect',
			nonce: readNonce,
			key_hash: hash
		}, (res) => {
			if (!res.success) {
				this.$('#aips-cache-inspect-body').html('<p>' + this.esc(res.data.message) + '</p>');
				return;
			}

			const d = res.data;
			const expiresFmt = d.expires_at > 0 ? this.formatTs(d.expires_at) : ((this.l10n.i18n && this.l10n.i18n.never) || 'Never');
			const ttlRemFmt = d.ttl_remaining !== null && d.ttl_remaining !== undefined ? d.ttl_remaining + 's' : 'N/A';

			let html = '<dl class="aips-dl">';
			html += '<dt>Key Hash</dt><dd><code>' + this.esc(d.key_hash) + '</code></dd>';
			html += '<dt>Group</dt><dd>' + this.esc(d.cache_group) + '</dd>';
			html += '<dt>Driver</dt><dd>' + this.esc(d.driver) + '</dd>';
			html += '<dt>Tier</dt><dd>' + this.esc(d.tier) + '</dd>';
			html += '<dt>Operation</dt><dd>' + this.esc(d.operation_id) + '</dd>';
			html += '<dt>Tags</dt><dd>' + this.esc(d.tags) + '</dd>';
			html += '<dt>TTL</dt><dd>' + this.esc(d.ttl) + 's</dd>';
			html += '<dt>Expires</dt><dd>' + this.esc(expiresFmt) + '</dd>';
			html += '<dt>TTL Remaining</dt><dd>' + this.esc(ttlRemFmt) + '</dd>';
			html += '<dt>Value Type</dt><dd>' + this.esc(d.value_type) + '</dd>';
			html += '<dt>Value Size</dt><dd>' + this.formatBytes(d.value_size) + '</dd>';
			html += '</dl>';

			if (d.preview !== null && d.preview !== undefined) {
				html += '<h4>' + this.esc((this.l10n.i18n && this.l10n.i18n.preview) || 'Preview') + '</h4>';
				if (d.preview_note) {
					html += '<p style="font-style:italic;margin-bottom:6px;">' + this.esc(d.preview_note) + '</p>';
				}
				html += '<pre style="max-height:400px;overflow:auto;background:#f9f9f9;padding:10px;border:1px solid #ddd;">' + this.esc(JSON.stringify(d.preview, null, 2)) + '</pre>';
			}

			this.$('#aips-cache-inspect-body').html(html);
		});
	},

	deleteCacheEntry(e) {
		e.preventDefault();
		const $el = $(e.currentTarget);
		const hash = $el.attr('data-hash') || $el.data('hash');
		const actionNonce = (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_delete_entry',
			nonce: actionNonce,
			key_hash: hash
		}, (res) => {
			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					window.AIPS.Utilities.showToast(res.data.message, 'success');
					$el.closest('tr').fadeOut(300, function() { $(this).remove(); });
				} else {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		});
	},

	bulkDeleteCacheEntries(e) {
		e.preventDefault();
		const action = this.$('#aips-cache-bulk-action').val();
		if (action !== 'delete') return;

		const hashes = [];
		this.$('.aips-cache-entry-cb:checked').each(function() {
			hashes.push($(this).val());
		});

		if (!hashes.length) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast((this.l10n.i18n && this.l10n.i18n.noneSelected) || 'No entries selected.', 'warning');
			}
			return;
		}

		const actionNonce = $(e.currentTarget).attr('data-nonce') || $(e.currentTarget).data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_delete_bulk',
			nonce: actionNonce,
			key_hashes: hashes
		}, (res) => {
			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					window.AIPS.Utilities.showToast(res.data.message, 'success');
					this.loadEntries();
				} else {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		});
	},

	// -------------------------------------------------------------------------
	// Cache Operations tab
	// -------------------------------------------------------------------------
	searchCacheOperations(e) {
		e.preventDefault();
		const readNonce = (window.aipsCacheMonitor && window.aipsCacheMonitor.nonce) || '';

		const params = {
			action: 'aips_cache_monitor_operations',
			nonce: $(e.currentTarget).attr('data-nonce') || $(e.currentTarget).data('nonce') || readNonce,
			repository_class: this.$('#aips-ops-filter-repo').val(),
			tier: this.$('#aips-ops-filter-tier').val()
		};

		this.$('#aips-ops-tbody').html(
			'<tr><td colspan="6">' + this.esc((this.l10n.i18n && this.l10n.i18n.loading) || 'Loading…') + '</td></tr>'
		);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, params, (res) => {
			if (!res.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
				return;
			}

			const ops = res.data.operations || [];
			let html = '';

			ops.forEach((op) => {
				html += '<tr>';
				html += '<td><code>' + this.esc(op.operation_id) + '</code></td>';
				html += '<td><small>' + this.esc(op.repository_class) + '</small></td>';
				html += '<td>' + this.esc(op.tier) + '</td>';
				html += '<td>' + this.esc(op.index_count) + '</td>';
				html += '<td>' + this.formatBytes(op.total_size) + '</td>';
				html += '<td>' + this.formatTs(op.last_updated) + '</td>';
				html += '</tr>';
			});

			if (!html) {
				html = '<tr><td colspan="6">' + this.esc((this.l10n.i18n && this.l10n.i18n.noOps) || 'No operations found.') + '</td></tr>';
			}
			this.$('#aips-ops-tbody').html(html);
		});
	},

	// -------------------------------------------------------------------------
	// Cache Events tab
	// -------------------------------------------------------------------------
	loadCacheEvents(e) {
		if (e) e.preventDefault();
		this.eventsPage = 1;
		this.loadEvents();
	},

	loadEvents() {
		const readNonce = (window.aipsCacheMonitor && window.aipsCacheMonitor.nonce) || '';
		const params = {
			action: 'aips_cache_monitor_events',
			nonce: readNonce,
			event_type: this.$('#aips-events-filter-type').val(),
			page: this.eventsPage,
			per_page: 50
		};

		this.$('#aips-events-tbody').html(
			'<tr><td colspan="6">' + this.esc((this.l10n.i18n && this.l10n.i18n.loading) || 'Loading…') + '</td></tr>'
		);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, params, (res) => {
			if (!res.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
				return;
			}

			const rows = res.data.rows || [];
			let html = '';

			rows.forEach((ev) => {
				html += '<tr>';
				html += '<td>' + this.esc(this.formatTs(ev.created_at)) + '</td>';
				html += '<td><code>' + this.esc(ev.event_type) + '</code></td>';
				html += '<td>' + this.esc(ev.cache_group) + '</td>';
				html += '<td>' + this.esc(ev.affected_count) + '</td>';
				html += '<td>' + this.esc(ev.user_id) + '</td>';
				html += '<td>' + this.esc(ev.message) + '</td>';
				html += '</tr>';
			});

			if (!html) {
				html = '<tr><td colspan="6">' + this.esc((this.l10n.i18n && this.l10n.i18n.noEvents) || 'No events found.') + '</td></tr>';
			}
			this.$('#aips-events-tbody').html(html);
		});
	},

	// -------------------------------------------------------------------------
	// Cache Maintenance actions
	// -------------------------------------------------------------------------
	runCacheMaintenance(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const action = $btn.attr('data-action') || $btn.data('action');
		const actionNonce = $btn.attr('data-nonce') || $btn.data('nonce') || (window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce) || '';
		const $result = this.$('#aips-maintenance-result');

		$btn.prop('disabled', true);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_cache_monitor_maintenance',
			nonce: actionNonce,
			maintenance_action: action
		}, (res) => {
			$btn.prop('disabled', false);

			// Export Diagnostics JSON Download
			if (action === 'export_diagnostics' && res.success) {
				try {
					const blob = new Blob([JSON.stringify(res.data.diagnostics, null, 2)], { type: 'application/json' });
					const url = URL.createObjectURL(blob);
					const a = document.createElement('a');
					a.href = url;
					a.download = 'aips-cache-diagnostics-' + Date.now() + '.json';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
				} catch (err) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast('Export failed: ' + err.message, 'error');
					}
				}
				return;
			}

			if (window.AIPS && window.AIPS.Utilities) {
				if (res.success) {
					$result.show().html('<div class="notice notice-success inline"><p>' + this.esc(res.data.message) + '</p></div>');
					window.AIPS.Utilities.showToast(res.data.message, 'success');
				} else {
					$result.show().html('<div class="notice notice-error inline"><p>' + this.esc(res.data.message) + '</p></div>');
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
			}
		}).fail(() => {
			$btn.prop('disabled', false);
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast((this.l10n.i18n && this.l10n.i18n.requestFailed) || 'Request failed.', 'error');
			}
		});
	},

	// Load entries main method
	loadEntries() {
		const readNonce = (window.aipsCacheMonitor && window.aipsCacheMonitor.nonce) || '';
		const params = $.extend({}, this.entriesState.filters, {
			action: 'aips_cache_monitor_entries',
			nonce: readNonce,
			page: this.entriesState.page,
			per_page: this.entriesState.perPage,
			orderby: this.entriesState.orderby,
			order: this.entriesState.order
		});

		this.$('#aips-cache-entries-tbody').html(
			'<tr><td colspan="10">' + this.esc((this.l10n.i18n && this.l10n.i18n.loading) || 'Loading…') + '</td></tr>'
		);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, params, (res) => {
			if (!res.success) {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(res.data.message, 'error');
				}
				return;
			}

			const rows = res.data.rows || [];
			let html = '';

			rows.forEach((row) => {
				const expiresFmt = row.expires_at > 0 ? this.formatTs(row.expires_at) : ((this.l10n.i18n && this.l10n.i18n.never) || 'Never');
				const rowStyle = row.is_expired ? ' style="opacity:0.55;"' : '';

				html += '<tr data-hash="' + this.escAttr(row.key_hash) + '"' + rowStyle + '>';
				html += '<td class="check-column"><input type="checkbox" class="aips-cache-entry-cb" value="' + this.escAttr(row.key_hash) + '" /></td>';
				html += '<td class="cell-primary">';
				html += '<code class="aips-key-hash" title="' + this.escAttr(row.key_hash) + '">' + this.esc(row.key_hash.substring(0, 12) + '…') + '</code>';
				html += '<div class="row-actions">';
				html += '<span><a href="#" class="aips-cache-inspect-link" data-hash="' + this.escAttr(row.key_hash) + '">' + this.esc((this.l10n.i18n && this.l10n.i18n.inspect) || 'Inspect') + '</a></span> | ';
				html += '<span class="delete"><a href="#" class="aips-cache-delete-link" style="color:#a00;" data-hash="' + this.escAttr(row.key_hash) + '">' + this.esc((this.l10n.i18n && this.l10n.i18n.delete) || 'Delete') + '</a></span>';
				html += '</div>';
				html += '</td>';
				html += '<td>' + this.esc(row.cache_group) + '</td>';
				html += '<td><small>' + this.esc(row.operation_id) + '</small></td>';
				html += '<td>' + this.esc(row.tier) + '</td>';
				html += '<td>' + this.esc(row.driver) + '</td>';
				html += '<td><small>' + this.esc(row.value_type) + '</small></td>';
				html += '<td>' + this.formatBytes(row.value_size) + '</td>';
				html += '<td>' + this.esc(expiresFmt) + '</td>';
				html += '<td><button class="aips-btn aips-btn-sm aips-btn-ghost aips-cache-inspect-link" data-hash="' + this.escAttr(row.key_hash) + '">' + this.esc((this.l10n.i18n && this.l10n.i18n.inspect) || 'Inspect') + '</button></td>';
				html += '</tr>';
			});

			if (!html) {
				html = '<tr><td colspan="10">' + this.esc((this.l10n.i18n && this.l10n.i18n.noEntries) || 'No entries found.') + '</td></tr>';
			}

			this.$('#aips-cache-entries-tbody').html(html);

			// Update Pagination markup
			const totalPages = res.data.total_pages || 1;
			const currentPage = res.data.page || 1;
			let pagHtml = '';

			if (totalPages > 1) {
				pagHtml = '<span class="aips-pag-info">' + this.esc('Page ' + currentPage + ' / ' + totalPages + ' (' + res.data.total + ' total)') + '</span> ';
				if (currentPage > 1) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-prev">&laquo; ' + this.esc((this.l10n.i18n && this.l10n.i18n.prev) || 'Prev') + '</button> ';
				}
				if (currentPage < totalPages) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-next">' + this.esc((this.l10n.i18n && this.l10n.i18n.next) || 'Next') + ' &raquo;</button>';
				}
			}
			this.$('#aips-cache-entries-pagination').html(pagHtml);
		});
	}
});

export default DevToolsView;
