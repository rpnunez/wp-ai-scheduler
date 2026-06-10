import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseFormView } from './base-form';
import { SettingsModel } from '../models/settings';

/**
 * Settings Page View Controller
 */
export const SettingsView = BaseFormView.extend({
	el: 'body',

	events: {
		'submit #aips-settings-form': 'onFormSubmit',
		'click #aips-settings-tab-nav .aips-tab-link': 'switchTab',
		'click #aips-test-connection': 'testConnection',
		'change input[name="aips_enable_cache_system"]': 'updateCacheSystemFields',
		'change #aips_cache_driver': 'updateCacheDriverFields'
	},

	initialize() {
		// Use SettingsModel if none passed
		if (!this.model) {
			this.model = new SettingsModel();
		}

		// Cache localizations
		this.l10n = window.aipsSettingsL10n || {};

		if (this.$('#aips-settings-tab-nav').length) {
			this.activateTabFromHash();
			this.updateCacheSystemFields();
		}
	},

	/**
	 * Switch settings tab
	 */
	switchTab(e) {
		e.preventDefault();
		const $link = $(e.currentTarget);
		const tabId = $link.attr('data-tab') || $link.data('tab');
		if (!tabId) return;

		// Update URL hash
		if (history.replaceState) {
			history.replaceState(null, '', '#' + tabId);
		} else {
			window.location.hash = '#' + tabId;
		}

		// Update tab links active states
		this.$('#aips-settings-tab-nav .aips-tab-link').removeClass('active');
		$link.addClass('active');

		// Hide all contents and show selected tab content
		this.$('.aips-tab-content').hide();
		this.$('#' + tabId + '-tab').show();

		// Trigger tabSwitch event for legacy code compatibility
		$(document).trigger('aips:tabSwitch', [tabId]);
	},

	/**
	 * Activate tab from URL hash on load
	 */
	activateTabFromHash() {
		const hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
		if (!hash) return;

		const $link = this.$('#aips-settings-tab-nav .aips-tab-link').filter(function() {
			return $(this).attr('data-tab') === hash;
		});

		if ($link.length) {
			$link.trigger('click');
		}
	},

	/**
	 * Override onFormSubmit to only serialize and save the active tab's settings
	 */
	onFormSubmit(e) {
		e.preventDefault();

		const $form = this.$('#aips-settings-form');
		const $activeTab = $form.find('.aips-tab-content:visible').first();
		if (!$activeTab.length) return;

		let $submit = $activeTab.find('input[type="submit"], button[type="submit"]');
		if (!$submit.length) {
			$submit = $form.find('input[type="submit"], button[type="submit"]');
		}
		$submit = $submit.first();

		const defaultLabel = $submit.is('input') ? $submit.val() : $submit.text();
		const savingLabel = this.l10n.saving || 'Saving...';
		const settings = this.collectSettingsPayload($activeTab);

		if (_.isEmpty(settings)) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(this.l10n.payloadError || 'No settings were found to save.', 'warning');
			}
			return;
		}

		// Disable submit button and set label
		$submit.prop('disabled', true);
		if ($submit.is('input')) {
			$submit.val(savingLabel);
		} else {
			$submit.text(savingLabel);
		}

		// Set payload on settings model
		this.model.clear({ silent: true });
		this.model.set({ settings: settings });

		// Perform save
		this.model.save(null, {
			success: (model, response) => {
				const msg = (response && response.message) || this.l10n.saveSuccess || 'Settings saved successfully.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(msg, 'success');
				}
				$submit.prop('disabled', false);
				if ($submit.is('input')) {
					$submit.val(defaultLabel);
				} else {
					$submit.text(defaultLabel);
				}
			},
			error: (model, err) => {
				const errMsg = (err && err.message) || this.l10n.saveError || 'Failed to save settings.';
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(errMsg, 'error');
				}
				$submit.prop('disabled', false);
				if ($submit.is('input')) {
					$submit.val(defaultLabel);
				} else {
					$submit.text(defaultLabel);
				}
			}
		});
	},

	/**
	 * Collect setting fields from the currently active tab.
	 */
	collectSettingsPayload($scope) {
		const payload = {};

		$scope.find(':input[name]').serializeArray().forEach((field) => {
			if (!field.name || field.name === 'action' || field.name === 'option_page' || field.name === '_wpnonce' || field.name === '_wp_http_referer') {
				return;
			}

			this.assignNestedSetting(payload, field.name, field.value);
		});

		// Include checkboxes explicitly (since serializeArray skips unchecked ones)
		$scope.find('input[type="checkbox"]').each((index, el) => {
			const $cb = $(el);
			const name = $cb.attr('name');
			if (!name || name === '_wpnonce' || name === '_wp_http_referer') {
				return;
			}

			// For checkboxes, send value if checked, otherwise empty/0
			const value = $cb.is(':checked') ? ($cb.val() || '1') : '0';
			this.assignNestedSetting(payload, name, value);
		});

		return payload;
	},

	/**
	 * Assign serialized form field into nested settings object
	 */
	assignNestedSetting(payload, name, value) {
		const keys = name.match(/([^[\]]+)/g);
		let cursor = payload;

		if (!keys || !keys.length) {
			return;
		}

		keys.forEach((key, index) => {
			const isLast = index === keys.length - 1;

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
	 * Toggle visibility of cache-system-specific setting rows
	 */
	updateCacheSystemFields() {
		const enabled = this.$('input[name="aips_enable_cache_system"]:checked').val() === '1';

		this.$('.aips-cache-system-fields').each(function() {
			$(this).closest('tr').toggle(enabled);
		});

		if (enabled) {
			this.updateCacheDriverFields();
		} else {
			this.$('.aips-cache-db-fields').each(function() {
				$(this).closest('tr').hide();
			});
		}
	},

	/**
	 * Toggle visibility of driver-specific cache setting rows
	 */
	updateCacheDriverFields() {
		const driver = this.$('#aips_cache_driver').val();

		this.$('.aips-cache-db-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'db');
		});
	},

	/**
	 * Test connection to AI engine
	 */
	testConnection(e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);
		const $spinner = $btn.next('.spinner');
		const $result = $spinner.next('#aips-connection-result');
		const adminL10n = window.aipsAdminL10n || {};

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$result.removeClass('aips-status-ok aips-status-error').text('');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_test_connection',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || ''
			},
			success: (response) => {
				if (response.success) {
					$result.addClass('aips-status-ok').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
				} else {
					$result.addClass('aips-status-error').html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
				}
			},
			error: () => {
				$result.addClass('aips-status-error').text(adminL10n.errorTryAgain || 'Request failed. Please try again.');
			},
			complete: () => {
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	}
});

export default SettingsView;
