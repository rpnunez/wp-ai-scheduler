/**
 * System Status page — toggle log detail rows, reset the circuit breaker,
 * and paginate the telemetry table.
 *
 * Relies on `aipsSystemStatusL10n` localised by AIPS_Admin_Assets:
 *   - nonce              {string} wp_nonce for aips_reset_circuit_breaker
 *   - telemetryNonce     {string} wp_nonce for aips_get_telemetry
 *   - hideDetails        {string} "Hide Details" label
 *   - showDetails        {string} "Show Details" label
 *   - resetSuccess       {string} Success confirmation text
 *   - resetFailed        {string} Generic failure text
 *   - requestFailed      {string} Network/AJAX failure text
 *   - telemetryLoading   {string} "Loading..." text
 *   - telemetryPage      {string} "Page %1$s of %2$s" pattern
 *   - telemetryTotal     {string} "%s records" pattern
 *   - telemetryNoRecords {string} "No telemetry records found." text
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

		/** @type {number} Current telemetry page. */
		telemetryPage: 1,

		/** @type {number} Total telemetry pages. */
		telemetryTotalPages: 1,

		/**
		 * Initialise System Status page behaviour.
		 *
		 * @return {void}
		 */
		init: function() {
			this.bindEvents();
			this.loadTelemetryPage(1);
		},

		/**
		 * Register all UI event listeners for the System Status page.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-toggle-log-details', this.toggleLogDetails.bind(this));
			$(document).on('click', '.aips-reset-circuit-breaker', this.resetCircuitBreaker.bind(this));
			$(document).on('click', '#aips-telemetry-prev', this.telemetryPrev.bind(this));
			$(document).on('click', '#aips-telemetry-next', this.telemetryNext.bind(this));
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

		// -----------------------------------------------------------------------
		// Telemetry table
		// -----------------------------------------------------------------------

		/**
		 * Navigate to the previous telemetry page.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		telemetryPrev: function(e) {
			e.preventDefault();
			if (this.telemetryPage > 1) {
				this.loadTelemetryPage(this.telemetryPage - 1);
			}
		},

		/**
		 * Navigate to the next telemetry page.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		telemetryNext: function(e) {
			e.preventDefault();
			if (this.telemetryPage < this.telemetryTotalPages) {
				this.loadTelemetryPage(this.telemetryPage + 1);
			}
		},

		/**
		 * Load a page of telemetry rows via AJAX and update the table.
		 *
		 * @param {number} page 1-based page number to load.
		 * @return {void}
		 */
		loadTelemetryPage: function(page) {
			var self  = this;
			var l10n  = window.aipsSystemStatusL10n || {};
			var $wrap = $('#aips-telemetry-panel');

			if (!$wrap.length) {
				return;
			}

			var $tbody = $('#aips-telemetry-tbody');
			$tbody.html('<tr><td colspan="8" class="aips-telemetry-loading">' + (l10n.telemetryLoading || 'Loading...') + '</td></tr>');

			$('#aips-telemetry-prev, #aips-telemetry-next').prop('disabled', true);

			$.post(
				ajaxurl,
				{
					action: 'aips_get_telemetry',
					nonce:  l10n.telemetryNonce || '',
					page:   page
				},
				function(response) {
					if (!response || !response.success) {
						$tbody.html('<tr><td colspan="8">' + (l10n.requestFailed || 'Request failed.') + '</td></tr>');
						return;
					}

					var data = response.data;
					self.telemetryPage       = data.page;
					self.telemetryTotalPages = data.total_pages;

					self.renderTelemetryRows($tbody, data.rows, l10n);
					self.updateTelemetryPagination(data, l10n);
				}
			).fail(function() {
				$tbody.html('<tr><td colspan="8">' + (l10n.requestFailed || 'Request failed.') + '</td></tr>');
			});
		},

		/**
		 * Render telemetry rows into the table body.
		 *
		 * @param {jQuery} $tbody  The <tbody> element.
		 * @param {Array}  rows    Row data from the server.
		 * @param {Object} l10n    Localised strings.
		 * @return {void}
		 */
		renderTelemetryRows: function($tbody, rows, l10n) {
			if (!rows || rows.length === 0) {
				$tbody.html(
					'<tr><td colspan="8" class="aips-telemetry-empty">' +
					(l10n.telemetryNoRecords || 'No telemetry records found.') +
					'</td></tr>'
				);
				return;
			}

			var html = '';
			for (var i = 0; i < rows.length; i++) {
				var r          = rows[i];
				var peakMb     = r.peak_memory_bytes
					? (parseFloat(r.peak_memory_bytes) / 1048576).toFixed(2) + ' MB'
					: '—';
				var elapsed    = r.elapsed_ms ? parseFloat(r.elapsed_ms).toFixed(2) + ' ms' : '—';

				html += '<tr>';
				html += '<td>' + this.esc(r.id)             + '</td>';
				html += '<td>' + this.esc(r.page)           + '</td>';
				html += '<td>' + this.esc(r.request_method) + '</td>';
				html += '<td>' + this.esc(r.user_id)        + '</td>';
				html += '<td>' + this.esc(r.num_queries)    + '</td>';
				html += '<td>' + this.esc(peakMb)           + '</td>';
				html += '<td>' + this.esc(elapsed)          + '</td>';
				html += '<td>' + this.esc(r.inserted_at)    + '</td>';
				html += '</tr>';
			}
			$tbody.html(html);
		},

		/**
		 * Update pagination controls and record count label.
		 *
		 * @param {Object} data Server response data object.
		 * @param {Object} l10n Localised strings.
		 * @return {void}
		 */
		updateTelemetryPagination: function(data, l10n) {
			var pageLabel  = (l10n.telemetryPage  || 'Page %1$s of %2$s')
				.replace('%1$s', data.page)
				.replace('%2$s', data.total_pages);
			var countLabel = (l10n.telemetryTotal || '%s records')
				.replace('%s', data.total);

			$('#aips-telemetry-page-label').text(pageLabel);
			$('#aips-telemetry-count').text(countLabel);
			$('#aips-telemetry-prev').prop('disabled', data.page <= 1);
			$('#aips-telemetry-next').prop('disabled', data.page >= data.total_pages);
		},

		/**
		 * Escape a value for safe HTML text insertion.
		 *
		 * @param {*} value Value to escape.
		 * @return {string}
		 */
		esc: function(value) {
			if (value === null || value === undefined) {
				return '—';
			}
			return $('<span>').text(String(value)).html();
		},

	};

	$(document).ready(function() {
		AIPS.SystemStatus.init();
	});

})(jQuery);

