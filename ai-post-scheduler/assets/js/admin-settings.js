/**
 * Settings page AJAX save behavior.
 *
 * Keeps the active tab in the URL hash, saves only the active tab's settings
 * over AJAX, and shows toast feedback without reloading the page.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/**
		 * Initialize settings-page behavior.
		 *
		 * @return {void}
		 */
		initSettingsPage: function() {
			this.activateSettingsTabFromHash();
			this.bindSettingsEvents();
		},

		/**
		 * Activate a settings tab from the current URL hash if one exists.
		 *
		 * @return {void}
		 */
		activateSettingsTabFromHash: function() {
			var hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
			if (!hash) {
				return;
			}

			var $link = $('#aips-settings-tab-nav .aips-tab-link[data-tab="' + hash + '"]');
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
			$('#aips-settings-form').on('submit', AIPS.onSettingsFormSubmit);
			$(document).on('aips:tabSwitch', AIPS.onSettingsTabSwitch);
		},

		/**
		 * Save the active settings tab via AJAX.
		 *
		 * @param {Event} e Form submit event.
		 * @return {void}
		 */
		onSettingsFormSubmit: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $activeTab = $form.find('.aips-tab-content:visible').first();
			var $submit = $activeTab.find('input[type="submit"], button[type="submit"]').first();
			var defaultLabel = $submit.is('input') ? $submit.val() : $submit.text();
			var savingLabel = (window.aipsSettingsL10n && aipsSettingsL10n.saving) ? aipsSettingsL10n.saving : 'Saving...';
			var settings = AIPS.collectSettingsPayload($activeTab);

			if ($.isEmptyObject(settings)) {
				AIPS.Utilities.showToast(
					(window.aipsSettingsL10n && aipsSettingsL10n.payloadError) ? aipsSettingsL10n.payloadError : 'No settings were found to save.',
					'warning'
				);
				return;
			}

			$submit.prop('disabled', true);
			if ($submit.is('input')) {
				$submit.val(savingLabel);
			} else {
				$submit.text(savingLabel);
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_save_settings',
					nonce: aipsAjax.nonce,
					settings: settings
				}
			}).done(function(response) {
				if (response && response.success) {
					AIPS.Utilities.showToast(
						(response.data && response.data.message) ? response.data.message : ((window.aipsSettingsL10n && aipsSettingsL10n.saveSuccess) ? aipsSettingsL10n.saveSuccess : 'Settings saved successfully.'),
						'success'
					);
					return;
				}

				AIPS.Utilities.showToast(
					(response && response.data && response.data.message) ? response.data.message : ((window.aipsSettingsL10n && aipsSettingsL10n.saveError) ? aipsSettingsL10n.saveError : 'Failed to save settings.'),
					'error'
				);
			}).fail(function() {
				AIPS.Utilities.showToast(
					(window.aipsSettingsL10n && aipsSettingsL10n.saveError) ? aipsSettingsL10n.saveError : 'Failed to save settings.',
					'error'
				);
			}).always(function() {
				$submit.prop('disabled', false);
				if ($submit.is('input')) {
					$submit.val(defaultLabel);
				} else {
					$submit.text(defaultLabel);
				}
			});
		},

		/**
		 * Collect setting fields from the currently active tab.
		 *
		 * @param {jQuery} $scope Active settings tab panel.
		 * @return {Object}
		 */
		collectSettingsPayload: function($scope) {
			var payload = {};

			$scope.find(':input[name]').serializeArray().forEach(function(field) {
				if (!field.name || field.name === 'action' || field.name === 'option_page' || field.name === '_wpnonce' || field.name === '_wp_http_referer') {
					return;
				}

				AIPS.assignNestedSetting(payload, field.name, field.value);
			});

			return payload;
		},

		/**
		 * Assign a serialized form field into a nested settings object.
		 *
		 * Supports names like `aips_notification_preferences[email]`.
		 *
		 * @param {Object} payload Settings payload being built.
		 * @param {string} name    Serialized field name.
		 * @param {string} value   Serialized field value.
		 * @return {void}
		 */
		assignNestedSetting: function(payload, name, value) {
			var keys = name.match(/([^[\]]+)/g);
			var cursor = payload;

			if (!keys || !keys.length) {
				return;
			}

			keys.forEach(function(key, index) {
				var isLast = index === keys.length - 1;

				if (isLast) {
					cursor[key] = value;
					return;
				}

				if (!cursor[key] || typeof cursor[key] !== 'object') {
					cursor[key] = {};
				}

				cursor = cursor[key];
			});
		},

		/**
		 * Update the URL hash to reflect the newly active settings tab.
		 *
		 * @param {Event}  e     Custom jQuery event.
		 * @param {string} tabId The ID of the tab that was just activated.
		 * @return {void}
		 */
		onSettingsTabSwitch: function(e, tabId) {
			if ($('#aips-settings-tab-nav').length && history.replaceState) {
				history.replaceState(null, '', '#' + tabId);
			}
		}

	});

	/**
	 * Toggle visibility of cache-system-specific setting rows.
	 *
	 * @return {void}
	 */
	function updateCacheSystemFields() {
		var enabled = $('input[name="aips_enable_cache_system"]:checked').val() === '1';

		$('.aips-cache-system-fields').each(function() {
			$(this).closest('tr').toggle(enabled);
		});

		if (enabled) {
			updateCacheDriverFields();
		} else {
			$('.aips-cache-db-fields').each(function() {
				$(this).closest('tr').hide();
			});
		}
	}

	/**
	 * Toggle visibility of driver-specific cache setting rows.
	 *
	 * @return {void}
	 */
	function updateCacheDriverFields() {
		var driver = $('#aips_cache_driver').val();

		$('.aips-cache-db-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'db');
		});
	}

	$(document).ready(function() {
		if ($('#aips-settings-tab-nav').length) {
			AIPS.initSettingsPage();
		}

		if ($('input[name="aips_enable_cache_system"]').length) {
			updateCacheSystemFields();
			$(document).on('change', 'input[name="aips_enable_cache_system"]', updateCacheSystemFields);
		}

		if ($('#aips_cache_driver').length) {
			$(document).on('change', '#aips_cache_driver', updateCacheDriverFields);
		}
	});

})(jQuery);
