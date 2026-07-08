import Backbone from 'backbone';
import $ from 'jquery';
import { SettingsModel } from '../models/settings';

/**
 * Settings View
 * Manages settings page behavior including tab switching, cache field visibility, and form submission
 */
export const SettingsView = Backbone.View.extend({
	el: 'body',

	events: {
		'click #aips-settings-tab-nav .aips-tab-link': 'onTabClick',
		'submit #aips-settings-form': 'onFormSubmit',
		'change input[name="aips_enable_cache_system"]': 'updateCacheFields',
		'change #aips_cache_driver': 'updateCacheDriverFields'
	},

	initialize() {
		this.model = new SettingsModel();
		this.l10n = window.aipsSettingsL10n || {};
		this.activateTabFromHash();
		this.updateCacheFields();
	},

	onTabClick(e) {
		e.preventDefault();
		const $link = $(e.currentTarget);
		const tabId = $link.data('tab');

		this.$('#aips-settings-tab-nav .aips-tab-link').removeClass('active');
		$link.addClass('active');

		this.$('.aips-tab-content').hide();
		this.$(`#${tabId}`).show();

		if (history.replaceState) {
			history.replaceState(null, '', `#${tabId}`);
		}

		$(document).trigger('aips:tabSwitch', [tabId]);
	},

	activateTabFromHash() {
		const hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
		if (!hash) return;

		const $link = this.$('#aips-settings-tab-nav .aips-tab-link').filter(function() {
			return $(this).data('tab') === hash;
		});

		if ($link.length) {
			$link.trigger('click');
		}
	},

	onFormSubmit(e) {
		e.preventDefault();

		const $form = this.$('#aips-settings-form');
		const $activeTab = $form.find('.aips-tab-content:visible').first();
		const $submit = $activeTab.find('[type="submit"]').length
			? $activeTab.find('[type="submit"]').first()
			: $form.find('[type="submit"]').first();

		const settings = this.collectSettingsPayload($activeTab);

		if (!Object.keys(settings).length) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(
					this.l10n.payloadError || 'No settings were found to save.',
					'warning'
				);
			}
			return;
		}

		const defaultLabel = $submit.is('input') ? $submit.val() : $submit.text();
		const savingLabel = this.l10n.saving || 'Saving...';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.setButtonLoading($submit, savingLabel);
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'aips_save_settings',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				settings
			}
		})
			.done((response) => {
				const message = (response && response.data && response.data.message)
					|| this.l10n.saveSuccess
					|| 'Settings saved successfully.';

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(message, 'success');
				}
			})
			.fail((xhr) => {
				const message = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					|| this.l10n.saveError
					|| 'Failed to save settings.';

				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(message, 'error');
				}
			})
			.always(() => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.resetButton($submit, defaultLabel);
				}
			});
	},

	collectSettingsPayload($scope) {
		const payload = {};

		$scope.find(':input[name]').serializeArray().forEach((field) => {
			if (!field.name
				|| field.name === 'action'
				|| field.name === 'option_page'
				|| field.name === '_wpnonce'
				|| field.name === '_wp_http_referer') {
				return;
			}

			this.assignNestedSetting(payload, field.name, field.value);
		});

		return payload;
	},

	assignNestedSetting(payload, name, value) {
		const keys = name.match(/([^[\]]+)/g);
		if (!keys || !keys.length) return;

		let cursor = payload;

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

	updateCacheFields() {
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

	updateCacheDriverFields() {
		const driver = this.$('#aips_cache_driver').val();

		this.$('.aips-cache-db-fields').each(function() {
			$(this).closest('tr').toggle(driver === 'db');
		});
	}
});
