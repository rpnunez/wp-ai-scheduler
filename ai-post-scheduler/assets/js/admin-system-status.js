/**
 * System Status page — log detail toggles, circuit breaker reset, and the
 * System Health maintenance operations (including the one-click Refresh System).
 *
 * Relies on `aipsSystemStatusL10n` localised by AIPS_Admin_Assets:
 *   - nonce                           {string} wp_nonce for aips_reset_circuit_breaker
 *   - nonceCronReschedule             {string} wp_nonce for aips_status_reschedule_missed_cron
 *   - nonceRetrySlices                {string} wp_nonce for aips_status_retry_failed_slices
 *   - nonceRepairCampaignData         {string} wp_nonce for aips_status_repair_campaign_data
 *   - nonceClearPartialGenerations    {string} wp_nonce for aips_status_clear_partial_generations
 *   - nonceCleanupStaleJobsCache      {string} wp_nonce for aips_status_cleanup_stale_jobs_cache
 *   - nonceRebuildCaches              {string} wp_nonce for aips_rebuild_caches
 *   - nonceRefreshSystem              {string} wp_nonce for aips_status_refresh_system
 *   - nonceCacheMaintenance           {string} wp_nonce for aips_status_cache_maintenance
 *   - nonceCleanupNotifications       {string} wp_nonce for aips_status_cleanup_notifications
 *   - nonceResetResilience            {string} wp_nonce for aips_status_reset_resilience
 *   - nonceRepairDatetime             {string} wp_nonce for aips_status_repair_datetime
 *   - hideDetails                     {string} "Hide Details" label
 *   - showDetails                     {string} "Show Details" label
 *   - resetSuccess                    {string} Success confirmation text
 *   - resetFailed                     {string} Generic failure text
 *   - requestFailed                   {string} Network/AJAX failure text
 *   - refreshRunning / refreshDone / refreshPartial {string} Refresh System status text
 *   - selectTasksRequired             {string} No-task-selected validation text
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * AIPS.SystemStatus — self-contained module for the System Status admin page.
	 *
	 * Follows the same init() / bindEvents() convention used throughout this
	 * plugin (e.g. AIPS.History) so the page can be bootstrapped with a single
	 * AIPS.SystemStatus.init() call without polluting the global AIPS namespace
	 * with page-specific handlers.
	 */
	AIPS.SystemStatus = {

		/**
		 * Initialise System Status page behaviour.
		 *
		 * @return {void}
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Register all UI event listeners for the System Status page.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-toggle-log-details', this.toggleLogDetails.bind(this));
			$(document).on('click', '.aips-reset-circuit-breaker', this.resetCircuitBreaker.bind(this));
			$(document).on('click', '.aips-status-op', this.runStatusOperation.bind(this));
			$(document).on('click', '.aips-rebuild-cache-btn', this.rebuildCaches.bind(this));
			$(document).on('click', '.aips-toggle-refresh-tasks', this.toggleRefreshTasks.bind(this));
			$(document).on('click', '.aips-toggle-cache-tasks', this.toggleCacheTasks.bind(this));
			$(document).on('click', '.aips-refresh-system', this.refreshSystem.bind(this));
			$(document).on('click', '.aips-copy-system-report', this.copySystemReport.bind(this));
			$(document).on('click', '.aips-prune-telemetry-btn', this.pruneTelemetry.bind(this));
			$(document).on('click', '.aips-purge-telemetry-btn', this.purgeTelemetry.bind(this));
			$(document).on('click', '.aips-prune-history-logs-btn', this.pruneHistoryLogs.bind(this));
			$(document).on('click', '.aips-clean-orphaned-embeddings-btn', this.cleanOrphanedEmbeddings.bind(this));
			$(document).on('click', '.aips-optimize-table-btn', this.optimizeTable.bind(this));
			$(document).on('click', '.aips-refresh-tables-btn', function(e) {
				e.preventDefault();
				this.refreshTables(true);
			}.bind(this));
		},

		/**
		 * Copy the Markdown-formatted system report to clipboard.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		copySystemReport: function(e) {
			e.preventDefault();
			var self = this;
			var reportText = $('#aips-system-report-raw').val() || '';

			if (!reportText) {
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(reportText).then(function() {
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('System report copied to clipboard!', 'success');
					}
				}).catch(function() {
					self.fallbackCopy(reportText);
				});
			} else {
				this.fallbackCopy(reportText);
			}
		},

		/**
		 * Fallback clipboard copy using temporary textarea element.
		 *
		 * @param {string} text Text to copy.
		 * @return {void}
		 */
		fallbackCopy: function(text) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast('System report copied to clipboard!', 'success');
				}
			} catch (err) {
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast('Failed to copy report.', 'error');
				}
			}
			$temp.remove();
		},

		/**
		 * Toggle a collapsible log-detail row.
		 *
		 * Reads the target element ID from the `data-target` attribute on the
		 * clicked link and toggles its visibility with a slide animation.
		 * The link text updates to reflect the current visibility state.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleLogDetails: function(e) {
			e.preventDefault();

			var l10n    = window.aipsSystemStatusL10n || {};
			var $link   = $(e.currentTarget);
			var target  = $link.data('target');
			var $detail = $('#' + target);

			$detail.slideToggle(function() {
				$link.text(
					$detail.is(':visible')
						? (l10n.hideDetails || 'Hide Details')
						: (l10n.showDetails || 'Show Details')
				);
			});
		},

		/**
		 * Send an AJAX request to reset the circuit breaker.
		 *
		 * Disables the button during the request.  On success the button is
		 * hidden and a confirmation message is shown.  On failure the button is
		 * re-enabled and the error message is displayed next to it.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		resetCircuitBreaker: function(e) {
			e.preventDefault();

			var l10n    = window.aipsSystemStatusL10n || {};
			var $btn    = $(e.currentTarget);
			var $result = $btn.siblings('.aips-reset-circuit-result');

			var req = $.post(
				ajaxurl,
				{
					action: 'aips_reset_circuit_breaker',
					nonce:  l10n.nonce || ''
				},
				function(response) {
					if (response && response.success) {
						$result.text(l10n.resetSuccess || 'Circuit reset. Reload the page to confirm.').show();
						$btn.hide();
					} else {
						var msg = (response && response.data && response.data.message)
							? response.data.message
							: (l10n.resetFailed || 'Reset failed.');
						$result.text(msg).show();
					}
				}
			).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed. Please try again.').show();
			});

			AIPS.Utilities.withLock($btn, req, { timeout: 30000 });
		},

		/**
		 * Run a status operation (reschedule cron, retry slices, clear partial generations, etc.).
		 *
		 * Each operation uses its own specific nonce for security.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		runStatusOperation: function(e) {
			e.preventDefault();
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var action = $btn.data('op');
			var $result = $('.aips-status-op-result');

			// Map each action to its specific nonce
			var nonceMap = {
				'aips_status_reschedule_missed_cron': l10n.nonceCronReschedule || '',
				'aips_status_retry_failed_slices': l10n.nonceRetrySlices || '',
				'aips_status_repair_campaign_data': l10n.nonceRepairCampaignData || '',
				'aips_status_clear_partial_generations': l10n.nonceClearPartialGenerations || '',
				'aips_status_cleanup_stale_jobs_cache': l10n.nonceCleanupStaleJobsCache || '',
				'aips_status_cache_maintenance': l10n.nonceCacheMaintenance || '',
				'aips_status_clear_embeddings_cache': l10n.nonceClearEmbeddingsCache || '',
				'aips_status_cleanup_notifications': l10n.nonceCleanupNotifications || '',
				'aips_status_reset_resilience': l10n.nonceResetResilience || '',
				'aips_status_repair_datetime': l10n.nonceRepairDatetime || ''
			};

			var nonce = nonceMap[action] || '';

			var req = $.post(ajaxurl, { action: action, nonce: nonce }, function(response) {
				if (response && response.success) {
					$result.text((response.data && response.data.message) ? response.data.message : 'Done.').show();
				} else {
					$result.text((response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.')).show();
				}
			}).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed.').show();
			});

			AIPS.Utilities.withLock($btn, req, { timeout: 60000 });
		},

		/**
		 * Rebuild the selected cache subsystems.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		rebuildCaches: function(e) {
			e.preventDefault();
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var $spinner = $btn.siblings('.spinner');
			var $result = $('.aips-status-op-result');
			var selectedSubsystems = this.getSelectedCacheSubsystems();

			if (!selectedSubsystems.length) {
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(l10n.selectCachesRequired || 'Select at least one cache subsystem to rebuild.', 'warning');
				}
				return;
			}

			$spinner.addClass('is-active');

			if (AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(l10n.rebuildingCaches || 'Rebuilding caches…', 'info');
			}

			var req = $.post(
				ajaxurl,
				{
					action: 'aips_rebuild_caches',
					nonce: l10n.nonceRebuildCaches || '',
					subsystems: selectedSubsystems
				},
				function(response) {
					if (response && response.success) {
						var msg = (response.data && response.data.message) ? response.data.message : (l10n.rebuildDone || 'Caches rebuilt successfully.');
						$result.text(msg).show();
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(msg, 'success');
						}
					} else {
						var errMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.');
						$result.text(errMsg).show();
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(errMsg, 'error');
						}
					}
				}
			).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed.').show();
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
				}
			}).always(function() {
				$spinner.removeClass('is-active');
			});

			AIPS.Utilities.withLock($btn, req, {
				loadingText: l10n.rebuildingCaches || 'Rebuilding caches…',
				timeout: 60000
			});
		},

		/**
		 * Toggle the full Refresh System task selection set.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleRefreshTasks: function(e) {
			e.preventDefault();

			var $tasks = $('.aips-refresh-task');
			var allChecked = $tasks.length > 0 && $tasks.filter(':checked').length === $tasks.length;

			$tasks.prop('checked', !allChecked);
		},

		/**
		 * Toggle the Cache Subsystems selection set.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleCacheTasks: function(e) {
			e.preventDefault();

			var $tasks = $('.aips-cache-subsystem-task');
			var allChecked = $tasks.length > 0 && $tasks.filter(':checked').length === $tasks.length;

			$tasks.prop('checked', !allChecked);
		},

		/**
		 * Collect the currently selected Refresh System task IDs.
		 *
		 * @return {Array}
		 */
		getSelectedRefreshTasks: function() {
			return $('.aips-refresh-task:checked').map(function() {
				return $(this).val();
			}).get();
		},

		/**
		 * Collect the currently selected Cache subsystem IDs.
		 *
		 * @return {Array}
		 */
		getSelectedCacheSubsystems: function() {
			return $('.aips-cache-subsystem-task:checked').map(function() {
				return $(this).val();
			}).get();
		},

		/**
		 * Run the selected safe maintenance operations in one request and render the
		 * per-step results returned by the server.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		refreshSystem: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var $spinner = $btn.siblings('.spinner');
			var $results = $('.aips-refresh-system-results');
			var selectedTasks = this.getSelectedRefreshTasks();

			if (!selectedTasks.length) {
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(l10n.selectTasksRequired, 'warning');
				}
				$results.hide().empty();
				return;
			}

			var hasDestructive = $('.aips-refresh-task-destructive:checked').length > 0;

			if (hasDestructive) {
				var confirmMsg = l10n.confirmRefreshSystemDestructive || 'This includes one or more tasks that permanently delete data (e.g. telemetry/history pruning, orphaned embeddings cleanup). Continue?';
				AIPS.Utilities.confirm(confirmMsg, 'Refresh System', [
					{ label: 'No, cancel', className: 'aips-btn aips-btn-secondary' },
					{ label: 'Yes, continue', className: 'aips-btn aips-btn-danger-solid', action: function() {
						self.runRefreshSystem($btn, $spinner, $results, selectedTasks);
					}}
				]);
				return;
			}

			this.runRefreshSystem($btn, $spinner, $results, selectedTasks);
		},

		/**
		 * Post the selected "Refresh System" tasks and render the results.
		 *
		 * @param {jQuery} $btn          The Refresh System button.
		 * @param {jQuery} $spinner      Its sibling spinner element.
		 * @param {jQuery} $results      Results container.
		 * @param {Array}  selectedTasks Selected task step keys.
		 * @return {void}
		 */
		runRefreshSystem: function($btn, $spinner, $results, selectedTasks) {
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};

			$spinner.addClass('is-active');
			$results.hide().empty();

			if (AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(l10n.refreshRunning || 'Refreshing system…', 'info');
			}

			var req = $.post(ajaxurl, { action: 'aips_status_refresh_system', nonce: l10n.nonceRefreshSystem || '', tasks: selectedTasks }, function(response) {
				if (response && response.success && response.data) {
					var data = response.data;
					var failed = data.failed || 0;
					var message = data.message || (failed > 0 ? (l10n.refreshPartial || 'System refresh finished with some failures.') : (l10n.refreshDone || 'System refresh complete.'));

					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(message, failed > 0 ? 'warning' : 'success');
					}
					self.renderRefreshResults($results, data.steps || []);
				} else {
					var errMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.');
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(errMsg, 'error');
					}
				}
			}).fail(function() {
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
				}
			}).always(function() {
				$spinner.removeClass('is-active');
			});

			AIPS.Utilities.withLock($btn, req, {
				loadingText: l10n.refreshRunning || 'Refreshing system…',
				timeout: 120000
			});
		},

		/**
		 * Render the Refresh System per-step results list.
		 *
		 * Rows are built with .text() so server strings are never injected as HTML.
		 *
		 * @param {jQuery} $results Container element.
		 * @param {Array}  steps    Step result objects {step, label, success, message}.
		 * @return {void}
		 */
		renderRefreshResults: function($results, steps) {
			// The server payload can omit or mangle `steps`; normalize before rendering.
			var normalizedSteps = Array.isArray(steps) ? steps : [];

			if (!normalizedSteps.length) {
				$results.empty().hide();
				return;
			}

			$results.empty();

			normalizedSteps.forEach(function(step) {
				var refreshStep = step || {};
				var success = !!refreshStep.success;
				var $row = $('<div>').addClass('aips-refresh-step' + (success ? ' aips-refresh-step-ok' : ' aips-refresh-step-failed'));
				$row.append($('<span>').addClass('dashicons ' + (success ? 'dashicons-yes-alt' : 'dashicons-dismiss')));
				$row.append($('<strong>').text(refreshStep.label || refreshStep.step || ''));
				$row.append($('<span>').addClass('aips-refresh-step-message').text(refreshStep.message || ''));
				$results.append($row);
			});

			$results.show();
		},

		/**
		 * Prune telemetry older than the configured retention setting.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		pruneTelemetry: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);

			var confirmMsg = l10n.confirmPruneTelemetry || 'Prune telemetry records older than retention period? Batched deletion will defragment table if needed.';
			AIPS.Utilities.confirm(confirmMsg, 'Prune Telemetry', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, prune old records', className: 'aips-btn aips-btn-primary', action: function() {
					$btn.prop('disabled', true);
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('Pruning telemetry…', 'info');
					}

					$.post(ajaxurl, {
						action: 'aips_status_prune_telemetry',
						nonce:  l10n.noncePruneTelemetry || (window.aipsAjax && aipsAjax.nonce) || ''
					}, function(response) {
						if (response && response.success) {
							var msg = (response.data && response.data.message) ? response.data.message : 'Telemetry pruned successfully.';
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(msg, 'success');
							}
							if (!self.patchTablesFromResponse(response.data && response.data.tables)) {
								self.refreshTables(false);
							}
						} else {
							var err = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Prune failed.');
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(err, 'error');
							}
						}
						$btn.prop('disabled', false);
					}).fail(function() {
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
						}
						$btn.prop('disabled', false);
					});
				}}
			]);
		},

		/**
		 * Purge all telemetry data (TRUNCATE TABLE to reclaim all disk space).
		 *
		 * Requires typing 'PURGE' into confirmation modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		purgeTelemetry: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);

			AIPS.Utilities.confirmWithWord({
				heading:      l10n.confirmPurgeTelemetryTitle || 'Purge All Telemetry Data',
				message:      l10n.confirmPurgeTelemetry || 'Are you sure you want to PURGE ALL telemetry records? This will truncate the table and instantly reclaim all disk space (reset to 0 bytes). This action cannot be undone.',
				word:         'PURGE',
				confirmLabel: 'Purge Table',
				confirmClass: 'aips-btn aips-btn-danger-solid'
			}).then(function(confirmed) {
				if (!confirmed) {
					return;
				}

				$btn.prop('disabled', true);
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast('Purging telemetry table…', 'info');
				}

				$.post(ajaxurl, {
					action: 'aips_status_purge_telemetry',
					nonce:  l10n.noncePurgeTelemetry || (window.aipsAjax && aipsAjax.nonce) || ''
				}, function(response) {
					if (response && response.success) {
						var msg = (response.data && response.data.message) ? response.data.message : 'Telemetry table truncated and space reclaimed.';
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(msg, 'success');
						}
						if (!self.patchTablesFromResponse(response.data && response.data.tables)) {
							self.refreshTables(false);
						}
					} else {
						var err = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Purge failed.');
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(err, 'error');
						}
					}
					$btn.prop('disabled', false);
				}).fail(function() {
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
					}
					$btn.prop('disabled', false);
				});
			});
		},

		/**
		 * Prune history logs older than configured retention period.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		pruneHistoryLogs: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);

			var confirmMsg = l10n.confirmPruneHistoryLogs || 'Prune generation logs older than configured retention period?';
			AIPS.Utilities.confirm(confirmMsg, 'Prune History Logs', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, prune logs', className: 'aips-btn aips-btn-primary', action: function() {
					$btn.prop('disabled', true);
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('Pruning history logs…', 'info');
					}

					$.post(ajaxurl, {
						action: 'aips_status_prune_history_logs',
						nonce:  l10n.noncePruneHistoryLogs || (window.aipsAjax && aipsAjax.nonce) || ''
					}, function(response) {
						if (response && response.success) {
							var msg = (response.data && response.data.message) ? response.data.message : 'History logs pruned successfully.';
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(msg, 'success');
							}
							if (!self.patchTablesFromResponse(response.data && response.data.tables)) {
								self.refreshTables(false);
							}
						} else {
							var err = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Prune failed.');
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(err, 'error');
							}
						}
						$btn.prop('disabled', false);
					}).fail(function() {
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
						}
						$btn.prop('disabled', false);
					});
				}}
			]);
		},

		/**
		 * Clean orphaned post embeddings.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		cleanOrphanedEmbeddings: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);

			var confirmMsg = l10n.confirmCleanEmbeddings || 'Clean orphaned embeddings for posts that no longer exist?';
			AIPS.Utilities.confirm(confirmMsg, 'Clean Orphaned Embeddings', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, clean orphans', className: 'aips-btn aips-btn-primary', action: function() {
					$btn.prop('disabled', true);
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('Cleaning orphaned embeddings…', 'info');
					}

					$.post(ajaxurl, {
						action: 'aips_status_clean_orphaned_embeddings',
						nonce:  l10n.nonceCleanEmbeddings || (window.aipsAjax && aipsAjax.nonce) || ''
					}, function(response) {
						if (response && response.success) {
							var msg = (response.data && response.data.message) ? response.data.message : 'Orphaned embeddings cleaned successfully.';
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(msg, 'success');
							}
							if (!self.patchTablesFromResponse(response.data && response.data.tables)) {
								self.refreshTables(false);
							}
						} else {
							var err = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Cleanup failed.');
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(err, 'error');
							}
						}
						$btn.prop('disabled', false);
					}).fail(function() {
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
						}
						$btn.prop('disabled', false);
					});
				}}
			]);
		},

		/**
		 * Run OPTIMIZE TABLE on a specific plugin table.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		optimizeTable: function(e) {
			e.preventDefault();
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var tableName = $btn.data('table') || '';

			if (!tableName) {
				return;
			}

			var confirmMsg = l10n.confirmOptimizeTable || ('Run OPTIMIZE TABLE on ' + tableName + '? This can briefly lock the table on large sites.');
			AIPS.Utilities.confirm(confirmMsg, 'Optimize Table', [
				{ label: 'No, cancel', className: 'aips-btn aips-btn-secondary' },
				{ label: 'Yes, optimize', className: 'aips-btn aips-btn-primary', action: function() {
					$btn.prop('disabled', true);
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('Optimizing table ' + tableName + '…', 'info');
					}

					$.post(ajaxurl, {
						action: 'aips_status_optimize_table',
						nonce:  l10n.nonceOptimizeTable || (window.aipsAjax && aipsAjax.nonce) || '',
						table:  tableName
					}, function(response) {
						if (response && response.success) {
							var msg = (response.data && response.data.message) ? response.data.message : (l10n.tableOptimized || 'Table optimized successfully.');
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(msg, 'success');
							}
							if (!self.patchTablesFromResponse(response.data && response.data.tables)) {
								self.refreshTables(false);
							}
						} else {
							var err = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Optimization failed.');
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(err, 'error');
							}
						}
						$btn.prop('disabled', false);
					}).fail(function() {
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
						}
						$btn.prop('disabled', false);
					});
				}}
			]);
		},

		/**
		 * Patch a single table-status row's cells in the DOM.
		 *
		 * @param {Object} t Table status record (short_name, formatted_* fields, overhead).
		 * @return {void}
		 */
		updateTableRow: function(t) {
			var short = t.short_name || '';
			var $row = $('#aips-tbl-row-' + short);
			if (!$row.length) {
				return;
			}

			$row.find('.aips-cell-records').text(t.formatted_records || String(t.records));
			$row.find('.aips-cell-data').text(t.formatted_data_size || String(t.data_size));
			$row.find('.aips-cell-index').text(t.formatted_index_size || String(t.index_size));
			var $ohCell = $row.find('.aips-cell-overhead');
			var ohText = t.formatted_overhead || String(t.overhead);
			if (t.overhead > 1048576) {
				$ohCell.empty().append($('<span>').css({ color: '#d63638', fontWeight: '600' }).text(ohText));
			} else {
				$ohCell.text(ohText);
			}

			// Keep raw byte/record counts on the row in sync so recomputeTotals()
			// can sum them without a full server round trip.
			$row.attr({
				'data-records':    t.records,
				'data-data-size':  t.data_size,
				'data-index-size': t.index_size,
				'data-overhead':   t.overhead
			});
		},

		/**
		 * Format a byte count the same way PHP's size_format(x, 2) does, for
		 * client-side totals recomputation.
		 *
		 * @param {number} bytes Byte count.
		 * @return {string}
		 */
		formatBytes: function(bytes) {
			bytes = Number(bytes) || 0;
			var units = ['B', 'KB', 'MB', 'GB', 'TB'];
			var i = 0;
			while (bytes >= 1024 && i < units.length - 1) {
				bytes /= 1024;
				i++;
			}
			return bytes.toFixed(i === 0 ? 0 : 2) + ' ' + units[i];
		},

		/**
		 * Recompute the grand-totals row by summing the data-* byte/record
		 * counts kept on every table row, so totals stay accurate after a
		 * single-row patch without re-fetching the full table listing.
		 *
		 * @return {void}
		 */
		recomputeTotals: function() {
			var totals = { records: 0, dataSize: 0, indexSize: 0, overhead: 0 };

			$('[id^="aips-tbl-row-"]').each(function() {
				var $row = $(this);
				totals.records   += parseInt($row.attr('data-records'), 10) || 0;
				totals.dataSize   += parseInt($row.attr('data-data-size'), 10) || 0;
				totals.indexSize  += parseInt($row.attr('data-index-size'), 10) || 0;
				totals.overhead   += parseInt($row.attr('data-overhead'), 10) || 0;
			});

			if (!$('#aips-tot-records').length) {
				return;
			}

			$('#aips-tot-records').text(totals.records.toLocaleString());
			$('#aips-tot-data').text(this.formatBytes(totals.dataSize));
			$('#aips-tot-index').text(this.formatBytes(totals.indexSize));
			$('#aips-tot-overhead').text(this.formatBytes(totals.overhead));
		},

		/**
		 * Patch the DOM rows for tables included in a mutating action's AJAX
		 * response, avoiding a full extra `aips_status_get_tables` round trip,
		 * then recompute the grand-totals row from all rows' current data.
		 *
		 * @param {Array|undefined} tables Table status records from the response, if any.
		 * @return {boolean} True if at least one row was patched from the response.
		 */
		patchTablesFromResponse: function(tables) {
			if (!Array.isArray(tables) || !tables.length) {
				return false;
			}
			var self = this;
			tables.forEach(function(t) {
				self.updateTableRow(t);
			});
			self.recomputeTotals();
			return true;
		},

		/**
		 * Refresh table sizes and counts via AJAX and update the status matrix DOM.
		 *
		 * @param {boolean} showToastNotice Whether to display a toast on completion.
		 * @return {void}
		 */
		refreshTables: function(showToastNotice) {
			var self = this;
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $('.aips-refresh-tables-btn');

			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'aips_status_get_tables',
				nonce:  l10n.nonceGetTables || (window.aipsAjax && aipsAjax.nonce) || ''
			}, function(response) {
				if (response && response.success && response.data) {
					var tables = response.data.tables || [];
					var totals = response.data.totals || {};

					tables.forEach(function(t) {
						self.updateTableRow(t);
					});

					if (totals.formatted_records) {
						$('#aips-tot-records').text(totals.formatted_records);
						$('#aips-tot-data').text(totals.formatted_data);
						$('#aips-tot-index').text(totals.formatted_index);
						$('#aips-tot-overhead').text(totals.formatted_overhead);
					}

					if (showToastNotice && AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(l10n.tablesRefreshed || 'Table sizes refreshed.', 'success');
					}
				}
				$btn.prop('disabled', false);
			}).fail(function() {
				$btn.prop('disabled', false);
			});
		},

	};

	$(document).ready(function() {
		AIPS.SystemStatus.init();
	});

})(jQuery);
