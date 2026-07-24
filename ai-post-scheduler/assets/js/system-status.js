/**
 * System Status page — toggle log detail rows and reset the circuit breaker.
 *
 * Relies on `aipsSystemStatusL10n` localised by AIPS_Admin_Assets:
 *   - nonce                           {string} wp_nonce for aips_reset_circuit_breaker
 *   - nonceCronReschedule             {string} wp_nonce for aips_status_reschedule_missed_cron
 *   - nonceRetrySlices                {string} wp_nonce for aips_status_retry_failed_slices
 *   - nonceRepairCampaignData         {string} wp_nonce for aips_status_repair_campaign_data
 *   - nonceClearPartialGenerations    {string} wp_nonce for aips_status_clear_partial_generations
 *   - nonceCleanupStaleJobsCache      {string} wp_nonce for aips_status_cleanup_stale_jobs_cache
 *   - hideDetails                     {string} "Hide Details" label
 *   - showDetails                     {string} "Show Details" label
 *   - resetSuccess                    {string} Success confirmation text
 *   - resetFailed                     {string} Generic failure text
 *   - requestFailed                   {string} Network/AJAX failure text
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
			$(document).on('click', '.aips-status-data-panel', this.toggleStatusSectionFromPanel.bind(this));
			$(document).on('click', '.aips-panel-collapse-toggle', this.toggleStatusSection.bind(this));
			$(document).on('click', '.aips-status-sections-toggle', this.toggleAllStatusSections.bind(this));
			$(document).on('click', '.aips-reset-circuit-breaker', this.resetCircuitBreaker.bind(this));
			$(document).on('click', '.aips-status-op', this.runStatusOperation.bind(this));
			$(document).on('click', '.aips-rebuild-cache-btn', this.rebuildCaches.bind(this));
		},

		/**
		 * Toggle a data section when clicking anywhere on its panel.
		 *
		 * Clicks on interactive controls inside the panel are ignored so those
		 * controls keep their native behaviour.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleStatusSectionFromPanel: function(e) {
			var $target = $(e.target);
			var isInteractive = $target.closest('a, button, input, select, textarea, label').length > 0;

			if (isInteractive) {
				return;
			}

			var $panel = $(e.currentTarget);
			var $button = $panel.find('.aips-panel-collapse-toggle').first();

			if ($button.length) {
				$button.trigger('click');
			}
		},

		/**
		 * Synchronize the "Expand all / Collapse all" control state based on
		 * current data section visibility.
		 *
		 * @return {void}
		 */
		syncStatusSectionsToggleState: function() {
			var $toggle = $('.aips-status-sections-toggle').first();
			if (!$toggle.length) {
				return;
			}

			var $bodies = $('.aips-status-data-panel-body');
			if (!$bodies.length) {
				$toggle.hide();
				return;
			}

			var hiddenCount = $bodies.filter(function() {
				return !$(this).is(':visible');
			}).length;

			if (hiddenCount === 0) {
				$toggle.attr('data-mode', 'collapse').text('Collapse all');
			} else {
				$toggle.attr('data-mode', 'expand').text('Expand all');
			}
		},

		/**
		 * Toggle a System Status data section panel body.
		 *
		 * Only applies to table/info sections that include a collapse button.
		 * Action sections do not include this control and remain always expanded.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleStatusSection: function(e) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $(e.currentTarget);
			var targetId = $button.data('target');
			var $panelBody = $('#' + targetId);

			if (!$panelBody.length) {
				return;
			}

			var isExpanded = $button.attr('aria-expanded') === 'true';
			var nextExpanded = !isExpanded;
			var $icon = $button.find('.dashicons');
			var $label = $button.find('.aips-panel-collapse-label');

			$button.attr('aria-expanded', nextExpanded ? 'true' : 'false');
			$icon
				.toggleClass('dashicons-arrow-up-alt2', nextExpanded)
				.toggleClass('dashicons-arrow-down-alt2', !nextExpanded);
			$icon.addClass('aips-chevron-pop');
			window.setTimeout(function() {
				$icon.removeClass('aips-chevron-pop');
			}, 180);
			$label.text(nextExpanded ? 'Collapse' : 'Expand');

			$panelBody
				.attr('aria-hidden', nextExpanded ? 'false' : 'true')
				.slideToggle(140);

			this.syncStatusSectionsToggleState();
		},

		/**
		 * Expand or collapse all System Status data sections.
		 *
		 * This only affects data table/info sections and intentionally excludes
		 * action sections.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		toggleAllStatusSections: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var mode = $button.attr('data-mode') || 'expand';
			var shouldExpand = mode === 'expand';
			var $panelBodies = $('.aips-status-data-panel-body');
			var $sectionButtons = $('.aips-panel-collapse-toggle');

			if (shouldExpand) {
				$panelBodies.stop(true, true).slideDown(140).attr('aria-hidden', 'false');
				$sectionButtons.attr('aria-expanded', 'true');
				$sectionButtons.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
				$sectionButtons.find('.aips-panel-collapse-label').text('Collapse');
			} else {
				$panelBodies.stop(true, true).slideUp(140).attr('aria-hidden', 'true');
				$sectionButtons.attr('aria-expanded', 'false');
				$sectionButtons.find('.dashicons').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
				$sectionButtons.find('.aips-panel-collapse-label').text('Expand');
			}

			$sectionButtons.find('.dashicons').addClass('aips-chevron-pop');
			window.setTimeout(function() {
				$sectionButtons.find('.dashicons').removeClass('aips-chevron-pop');
			}, 180);

			this.syncStatusSectionsToggleState();
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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_reset_circuit_breaker',
				nonce: l10n.nonce || '',
				$button: $btn,
				toastOnError: false,
				errorFallback: l10n.resetFailed || 'Reset failed.',
				onSuccess: function() {
					$result.text(l10n.resetSuccess || 'Circuit reset. Reload the page to confirm.').show();
					$btn.hide();
				},
				onError: function(message) {
					$result.text(message).show();
				}
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
				'aips_status_cleanup_stale_jobs_cache': l10n.nonceCleanupStaleJobsCache || ''
			};

			var nonce = nonceMap[action] || '';

			AIPS.Core.Http.ajaxRequest({
				action: action,
				nonce: nonce,
				$button: $btn,
				toastOnError: false,
				errorFallback: l10n.requestFailed || 'Request failed.',
				onSuccess: function(data) {
					$result.text(data.message || 'Done.').show();
				},
				onError: function(message) {
					$result.text(message).show();
				}
			});
		},


		rebuildCaches: function(e) {
			e.preventDefault();
			var l10n = window.aipsSystemStatusL10n || {};
			var $btn = $(e.currentTarget);
			var subsystem = $('#aips-cache-subsystem').val() || 'all';
			var $result = $('.aips-status-op-result');

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_rebuild_caches',
				nonce: l10n.nonceRebuildCaches || '',
				data: { subsystem: subsystem },
				$button: $btn,
				toastOnError: false,
				errorFallback: l10n.requestFailed || 'Request failed.',
				onSuccess: function(data) {
					$result.text(data.message || 'Done.').show();
				},
				onError: function(message) {
					$result.text(message).show();
				}
			});
		},

	};

	$(document).ready(function() {
		AIPS.SystemStatus.init();
		AIPS.SystemStatus.syncStatusSectionsToggleState();
	});

})(jQuery);
