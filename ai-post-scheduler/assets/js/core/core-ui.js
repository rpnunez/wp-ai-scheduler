/**
 * AIPS Core UI — shared helpers for the plugin's ad hoc loading indicators.
 *
 * Covers the two loading-indicator shapes duplicated across admin JS files:
 * the raw WP-core `<span class="spinner">` `is-active` toggle, and the
 * "show a loading element, hide the content element (and vice versa)" pair
 * used around AJAX calls that repaint a whole panel/table.
 *
 * Out of scope (deliberately): button-level loading state (text/HTML swap +
 * disable) — that's `AIPS.Utilities.setButtonLoading()`/`resetButton()`,
 * already used at 37+ call sites; don't duplicate it here. Also out of
 * scope: the per-page `AIPS.Templates`-rendered loading-row partials — those
 * are PHP-defined markup, a separate system this module doesn't touch.
 *
 * Usage:
 *   AIPS.Core.UI.toggleSpinner($form.find('.spinner'), true);
 *   // ... later
 *   AIPS.Core.UI.toggleSpinner($form.find('.spinner'), false);
 *
 *   AIPS.Core.UI.setLoading({
 *       $loading: $('#aips-topics-loading'),
 *       $content: $('#aips-topics-content'),
 *       isLoading: true
 *   });
 *
 * @since 3.2.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * @namespace AIPS.Core.UI
	 */
	AIPS.Core.UI = {

		/**
		 * Toggle a WP-core `<span class="spinner">` element's `is-active`
		 * class (the class WP core's CSS keys the spin animation off of).
		 *
		 * @param {jQuery}  $spinner  The `.spinner` element. No-op if empty/falsy.
		 * @param {boolean} isActive  Whether the spinner should be shown/spinning.
		 * @return {void}
		 */
		toggleSpinner: function ($spinner, isActive) {
			if (!$spinner || !$spinner.length) {
				return;
			}
			$spinner.toggleClass('is-active', !!isActive);
		},

		/**
		 * Swap visibility between a "loading" element and the "content"
		 * element it's temporarily standing in for, optionally also toggling
		 * a state class on a third (usually ancestor/panel) element.
		 *
		 * @param {Object}  options
		 * @param {jQuery}  options.$loading            Loading indicator element.
		 * @param {jQuery}  options.$content            Content element it replaces.
		 * @param {boolean} options.isLoading           Whether loading is active.
		 * @param {jQuery}  [options.$activeClassTarget] Element to toggle
		 *                                               `options.activeClass` on
		 *                                               (e.g. a wrapping panel).
		 * @param {string}  [options.activeClass]        Class name toggled on
		 *                                               `$activeClassTarget`. Default
		 *                                               `'aips-loading-active'`.
		 * @return {void}
		 */
		setLoading: function (options) {
			options = options || {};
			var isLoading = !!options.isLoading;

			if (options.$loading) {
				options.$loading.toggle(isLoading);
			}
			if (options.$content) {
				options.$content.toggle(!isLoading);
			}
			if (options.$activeClassTarget) {
				options.$activeClassTarget.toggleClass(
					options.activeClass || 'aips-loading-active',
					isLoading
				);
			}
		}
	};

})(jQuery);
