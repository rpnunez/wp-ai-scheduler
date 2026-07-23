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
			$(document).on('click', '.aips-refresh-system', this.refreshSystem.bind(this));
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

			$btn.prop('disabled', true);

			$.post(
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
						$btn.prop('disabled', false);
					}
				}
			).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed. Please try again.').show();
				$btn.prop('disabled', false);
			});
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
				'aips_status_cleanup_notifications': l10n.nonceCleanupNotifications || '',
				'aips_status_reset_resilience': l10n.nonceResetResilience || '',
				'aips_status_repair_datetime': l10n.nonceRepairDatetime || ''
			};

			var nonce = nonceMap[action] || '';

			$btn.prop('disabled', true);
			$.post(ajaxurl, { action: action, nonce: nonce }, function(response) {
				if (response && response.success) {
					$result.text((response.data && response.data.message) ? response.data.message : 'Done.').show();
				} else {
					$result.text((response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.')).show();
				}
				$btn.prop('disabled', false);
			}).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed.').show();
				$btn.prop('disabled', false);
			});
		},


		rebuildCaches: function(e) {
			e.preventDefault();
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var subsystem = $('#aips-cache-subsystem').val() || 'all';
			var $result = $('.aips-status-op-result');
			$btn.prop('disabled', true);
			$.post(ajaxurl, { action: 'aips_rebuild_caches', nonce: l10n.nonceRebuildCaches || '', subsystem: subsystem }, function(response) {
				if (response && response.success) {
					$result.text((response.data && response.data.message) ? response.data.message : 'Done.').show();
				} else {
					$result.text((response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.')).show();
				}
				$btn.prop('disabled', false);
			}).fail(function() {
				$result.text(l10n.requestFailed || 'Request failed.').show();
				$btn.prop('disabled', false);
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
					AIPS.Utilities.showToast(l10n.selectTasksRequired || 'Select at least one maintenance task to run.', 'warning');
				}
				$results.hide().empty();
				return;
			}

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');
			$results.hide().empty();

			if (AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(l10n.refreshRunning || 'Refreshing system…', 'info');
			}

			$.post(ajaxurl, { action: 'aips_status_refresh_system', nonce: l10n.nonceRefreshSystem || '', tasks: selectedTasks }, function(response) {
				if (response && response.success && response.data) {
					var data = response.data;
					var failed = data.failed || 0;
					var message = data.message || (failed > 0 ? (l10n.refreshPartial || 'System refresh finished with some failures.') : (l10n.refreshDone || 'System refresh complete.'));

					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(message, failed > 0 ? 'warning' : 'success');
					}
					self.renderRefreshResults($results, data.steps);
				} else {
					var errMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.requestFailed || 'Request failed.');
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast(errMsg, 'error');
					}
				}
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			}).fail(function() {
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed.', 'error');
				}
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
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

	};

	$(document).ready(function() {
		AIPS.SystemStatus.init();
	});

})(jQuery);
