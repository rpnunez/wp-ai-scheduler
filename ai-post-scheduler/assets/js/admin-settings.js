/**
 * Settings Page — Tab persistence.
 *
 * Keeps the URL hash in sync with the active settings tab so tabs are
 * bookmarkable, and restores the active tab after WordPress redirects back
 * to the settings page following a successful save.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/**
		 * Session-storage key used to remember the active settings tab
		 * across the WordPress options-save redirect.
		 *
		 * @type {string}
		 */
		settingsTabKey: 'aips_settings_active_tab',

		/**
		 * Initialise settings-page behaviour.
		 *
		 * Restores the previously active tab after a settings-saved redirect
		 * and wires up form-submit and tab-switch handlers.
		 *
		 * @return {void}
		 */
		initSettingsPage: function() {
			this.restoreSettingsTab();
			this.bindSettingsEvents();
		},

		/**
		 * Restore the active settings tab from sessionStorage after a redirect.
		 *
		 * WordPress redirects back to the settings page with `settings-updated`
		 * in the query string after a successful save. We use sessionStorage to
		 * remember which tab was active at the time of submission.
		 *
		 * @return {void}
		 */
		restoreSettingsTab: function() {
			if (!window.location.search.includes('settings-updated')) {
				return;
			}

			var savedTab = sessionStorage.getItem(AIPS.settingsTabKey);
			if (!savedTab) {
				return;
			}

			sessionStorage.removeItem(AIPS.settingsTabKey);

			var $link = $('#aips-settings-tab-nav .aips-tab-link[data-tab="' + savedTab + '"]');
			if ($link.length) {
				$link.trigger('click');
			}
		},

		/**
		 * Bind settings-page specific event handlers.
		 *
		 * @return {void}
		 */
		bindSettingsEvents: function() {
			// Persist the active tab in sessionStorage before the form is submitted
			// so it can be restored after WordPress redirects back to the page.
			$('#aips-settings-form').on('submit', AIPS.onSettingsFormSubmit);

			// Update the URL hash whenever a settings tab is activated so the
			// tab is reflected in the address bar and can be bookmarked.
			$(document).on('aips:tabSwitch', AIPS.onSettingsTabSwitch);
		},

		/**
		 * Store the currently active tab in sessionStorage on form submit.
		 *
		 * @return {void}
		 */
		onSettingsFormSubmit: function() {
			var activeTab = $('#aips-settings-tab-nav .aips-tab-link.active').data('tab');
			if (activeTab) {
				sessionStorage.setItem(AIPS.settingsTabKey, activeTab);
			}
		},

		/**
		 * Update the URL hash to reflect the newly active settings tab.
		 *
		 * Only runs when the settings tab nav is present on the page.
		 *
		 * @param {Event}  e     Custom jQuery event.
		 * @param {string} tabId The ID of the tab that was just activated.
		 * @return {void}
		 */
		onSettingsTabSwitch: function(e, tabId) {
			if ($('#aips-settings-tab-nav').length && history.replaceState) {
				history.replaceState(null, '', '#' + tabId);
			}
		},

	});

	// -----------------------------------------------------------------------
	// Cache settings — show/hide enable/disable and driver-specific rows
	// -----------------------------------------------------------------------

	/**
	 * Toggle visibility of cache-system-specific setting rows.
	 *
	 * Rows containing .aips-cache-system-fields are only shown when the cache
	 * system is enabled (i.e. the "Yes" radio is selected).
	 *
	 * @return {void}
	 */
	function updateCacheSystemFields() {
		var enabled = $('input[name="aips_enable_cache_system"]:checked').val() === '1';

		$('.aips-cache-system-fields').each(function() {
			$(this).closest('tr').toggle(enabled);
		});

		// When the cache system is enabled also apply the driver-specific toggle.
		if (enabled) {
			updateCacheDriverFields();
		} else {
			// Hide all driver-specific rows when the whole system is off.
			$('.aips-cache-redis-fields, .aips-cache-db-fields').each(function() {
				$(this).closest('tr').hide();
			});
		}
	}

	/**
	 * Toggle visibility of driver-specific cache setting rows.
	 *
	 * Rows containing .aips-cache-redis-fields are only shown when the redis
	 * driver is selected. Rows containing .aips-cache-db-fields are only
	 * shown when the db driver is selected.
	 *
	 * @return {void}
	 */
	function updateCacheDriverFields() {
		var driver = $('#aips_cache_driver').val();

		// Each driver-specific field wraps its content in a div with a
		// driver-scoped class. Walk up to the <tr> to show/hide the whole row.
		$('.aips-cache-redis-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'redis');
		});

		$('.aips-cache-db-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'db');
		});
	}

	$(document).ready(function() {
		if ($('#aips-settings-tab-nav').length) {
			AIPS.initSettingsPage();
		}

		// Cache system enable/disable radio may be present on the settings page.
		if ($('input[name="aips_enable_cache_system"]').length) {
			updateCacheSystemFields();
			$(document).on('change', 'input[name="aips_enable_cache_system"]', updateCacheSystemFields);
		}

		// Cache driver field may be present on the settings page.
		if ($('#aips_cache_driver').length) {
			$(document).on('change', '#aips_cache_driver', updateCacheDriverFields);
		}
	});

})(jQuery);
