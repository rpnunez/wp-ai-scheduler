/**
 * System Status page — toggle log detail rows and reset the circuit breaker.
 *
 * Relies on `aipsSystemStatusL10n` localised by AIPS_Admin_Assets:
 *   - nonce          {string} wp_nonce for aips_reset_circuit_breaker
 *   - hideDetails    {string} "Hide Details" label
 *   - showDetails    {string} "Show Details" label
 *   - resetSuccess   {string} Success confirmation text
 *   - resetFailed    {string} Generic failure text
 *   - requestFailed  {string} Network/AJAX failure text
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/**
		 * Initialise System Status page behaviour.
		 *
		 * @return {void}
		 */
		initSystemStatus: function() {
			this.bindSystemStatusEvents();
		},

		/**
		 * Register all UI event listeners for the System Status page.
		 *
		 * @return {void}
		 */
		bindSystemStatusEvents: function() {
			$(document).on('click', '.aips-toggle-log-details', $.proxy(this.toggleLogDetails, this));
			$(document).on('click', '.aips-reset-circuit-breaker', $.proxy(this.resetCircuitBreaker, this));
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

	});

	$(document).ready(function() {
		AIPS.initSystemStatus();
	});

})(jQuery);
