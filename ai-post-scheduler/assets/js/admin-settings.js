/**
 * Settings page AJAX save and UI behavior.
 *
 * Encapsulated under window.AIPS.Settings.
 * Handles tab navigation, AJAX saving of active settings tabs,
 * form control synchronization, and Meow AI Engine environment discovery.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsSettingsL10n || {};

	AIPS.Settings = {
		saving: false,
		eventsBound: false,

		/**
		 * Initialize settings page behavior.
		 *
		 * @return {void}
		 */
		init: function() {
			this.activateTabFromHash();
			this.bindEvents();
			this.initConditionalFields();
		},

		/**
		 * Activate a settings tab from the current URL hash, query parameter, or localStorage if one exists.
		 *
		 * @return {void}
		 */
		activateTabFromHash: function() {
			var tabId = '';
			var hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
			if (hash) {
				tabId = hash;
			} else {
				try {
					var params = new URLSearchParams(window.location.search);
					tabId = params.get('tab') || '';
				} catch (e) {}
			}

			if (!tabId) {
				try {
					tabId = localStorage.getItem('aips_active_tab_aips-settings') || '';
				} catch (e) {}
			}

			if (tabId === 'engine' || tabId === 'settings-engine' || tabId === 'ai') {
				tabId = 'settings-ai';
			} else if (tabId === 'linking' || tabId === 'internal-linking') {
				tabId = 'settings-linking';
			}

			if (!tabId) {
				return;
			}

			var $link = $('#aips-settings-tab-nav .aips-tab-link, #aips-settings-tab-nav .aips-rail-item').filter(function() {
				return $(this).attr('data-tab') === tabId || $(this).data('tab') === tabId;
			});
			if ($link.length) {
				$link.trigger('click');
			}
		},

		/**
		 * Bind settings-page specific event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			if (this.eventsBound) {
				return;
			}
			this.eventsBound = true;

			// --- 1. Form Submission & Navigation ---
			$('#aips-settings-form').on('submit.aipsSettings', this.onFormSubmit.bind(this));
			$(document).on('aips:tabSwitch.aipsSettings', this.onTabSwitch.bind(this));
			$(document).on(
				'click.aipsSettingsTab',
				'#aips-settings-tab-nav .aips-rail-item, #aips-settings-tab-nav .aips-tab-link',
				this.onRailTabClick.bind(this)
			);

			// --- 2. Interactive Form Controls ---
			$(document).on('click.aipsSecretToggle', '.aips-secret-toggle', this.onSecretToggle.bind(this));
			$(document).on('change.aipsSwitch', '.aips-switch input[role="switch"]', this.onSwitchChange.bind(this));
			$(document).on('input.aipsSlider', '.aips-slider-input', this.onSliderInput.bind(this));
			$(document).on('click.aipsCopyShortcode', '#aips-copy-shortcode-btn', this.onCopyShortcode.bind(this));

			// --- 3. Meow AI Engine & Embeddings Controls ---
			$(document).on('click.aipsFetchMeowEnvs', '#aips-fetch-meow-envs-btn', this.onFetchMeowEnvironments.bind(this));
			$(document).on('change.aipsMeowEnvSelect', '#aips-meow-envs-select', this.onMeowEnvSelect.bind(this));
			$(document).on('change.aipsEmbeddingsScope', 'input[name="aips_embeddings_scope"], #aips_embeddings_scope', this.onEmbeddingsScopeChange.bind(this));
			$(document).on('change.aipsAuthorApproval', '#aips_author_topic_auto_approval_mode', this.updateAuthorApprovalFields.bind(this));

			// --- 4. Connectors & Cache System Controls ---
			$(document).on('click.aipsConnectorMove', '[data-aips-connector-move]', this.onConnectorMove.bind(this));
			$(document).on('click.aipsFlushDigest', '#aips-flush-digest-btn', this.onFlushNotificationDigest.bind(this));
			$(document).on('click.aipsSendTestEmail', '#aips-send-test-email-btn', this.onSendTestNotificationEmail.bind(this));
			if (typeof AIPS.testConnection === 'function') {
				$(document).on('click.aipsTestConn', '#aips-test-connection', AIPS.testConnection);
			}
			$(document).on('change.aipsCacheSystem', 'input[name="aips_enable_cache_system"]', this.updateCacheSystemFields.bind(this));
			$(document).on('change.aipsCacheDriver', '#aips_cache_driver', this.updateCacheDriverFields.bind(this));
		},

		/**
		 * Initialize visibility states for conditional setting fields.
		 *
		 * @return {void}
		 */
		initConditionalFields: function() {
			this.updateAuthorApprovalFields();
			if ($('input[name="aips_enable_cache_system"]').length) {
				this.updateCacheSystemFields();
			}
			if ($('#aips_cache_driver').length) {
				this.updateCacheDriverFields();
			}
		},

		/**
		 * Scroll to top on rail or tab navigation item click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onRailTabClick: function(e) {
			this.scrollToTop();
		},

		/**
		 * Save the active settings tab via AJAX.
		 *
		 * @param {Event} e Form submit event.
		 * @return {void}
		 */
		onFormSubmit: function(e) {
			e.preventDefault();

			if (this.saving) {
				return;
			}

			var self = this;
			var $form = $(e.currentTarget);
			var $activeTab = $form.find('.aips-tab-content:visible').first();
			var $submit = $activeTab.find('input[type="submit"], button[type="submit"]');
			if (!$submit.length) {
				$submit = $form.find('input[type="submit"], button[type="submit"]');
			}
			$submit = $submit.first();
			var savingLabel = l10n.saving || 'Saving...';
			var settings = this.collectSettingsPayload($activeTab);

			if ($.isEmptyObject(settings)) {
				AIPS.Utilities.showToast(l10n.payloadError || 'No settings were found to save.', 'warning');
				return;
			}

			this.saving = true;

			var req = $.ajax({
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
					var successMsg = (response.data && response.data.message) ? response.data.message : (l10n.saveSuccess || 'Settings saved successfully.');
					AIPS.Utilities.showToast(successMsg, 'success');
					return;
				}

				var errorMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.saveError || 'Failed to save settings.');
				AIPS.Utilities.showToast(errorMsg, 'error');
			}).fail(function(xhr) {
				var message = (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
					? xhr.responseJSON.data.message
					: (l10n.saveError || 'Failed to save settings.');
				AIPS.Utilities.showToast(message, 'error');
			}).always(function() {
				self.saving = false;
			});

			AIPS.Utilities.withLock($submit, req, { loadingText: savingLabel });
		},

		/**
		 * Collect setting fields from the currently active tab.
		 *
		 * @param {jQuery} $scope Active settings tab panel.
		 * @return {Object}
		 */
		collectSettingsPayload: function($scope) {
			var self = this;
			var payload = {};

			// First, ensure non-array checkboxes in the active tab default to 0 if unchecked
			$scope.find('input[type="checkbox"][name]').each(function() {
				var name = $(this).attr('name');
				if (name && !/\[\]$/.test(name) && !$(this).is(':checked')) {
					self.assignNestedSetting(payload, name, 0);
				}
			});

			$scope.find(':input[name]').serializeArray().forEach(function(field) {
				if (!field.name || field.name === 'action' || field.name === 'option_page' || field.name === '_wpnonce' || field.name === '_wp_http_referer') {
					return;
				}

				self.assignNestedSetting(payload, field.name, field.value);
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
			if (/\[\]$/.test(name)) {
				var arrayKey = name.slice(0, -2);
				if (!Array.isArray(payload[arrayKey])) {
					payload[arrayKey] = [];
				}
				payload[arrayKey].push(value);
				return;
			}

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
		 * Update the URL, hidden fields, and referer to reflect the newly active settings tab.
		 *
		 * @param {Event}  e     Custom jQuery event.
		 * @param {string} tabId The ID of the tab that was just activated.
		 * @return {void}
		 */
		onTabSwitch: function(e, tabId) {
			if ($('#aips-settings-tab-nav').length) {
				if (history.replaceState) {
					try {
						var url = new URL(window.location.href);
						url.searchParams.set('tab', tabId);
						url.hash = tabId;
						history.replaceState(null, '', url.toString());
					} catch (err) {
						history.replaceState(null, '', '#' + tabId);
					}
				} else {
					window.location.hash = tabId;
				}

				$('#aips_active_tab').val(tabId);

				var $referer = $('input[name="_wp_http_referer"]');
				if ($referer.length) {
					$referer.each(function() {
						var refVal = $(this).val();
						if (refVal) {
							try {
								var refUrl = new URL(refVal, window.location.origin);
								refUrl.searchParams.set('tab', tabId);
								$(this).val(refUrl.pathname + refUrl.search + refUrl.hash);
							} catch (err) {
								if (refVal.indexOf('tab=') > -1) {
									refVal = refVal.replace(/([?&])tab=[^&#]*/, '$1tab=' + encodeURIComponent(tabId));
								} else {
									refVal += (refVal.indexOf('?') > -1 ? '&' : '?') + 'tab=' + encodeURIComponent(tabId);
								}
								$(this).val(refVal);
							}
						}
					});
				}

				try {
					localStorage.setItem('aips_active_tab_aips-settings', tabId);
				} catch (err) {}

				this.scrollToTop();
			}
		},

		/**
		 * Scroll the page to the top when navigating between settings tabs.
		 *
		 * @param {boolean} [smooth=true] Whether to use smooth scrolling.
		 * @return {void}
		 */
		scrollToTop: function(smooth) {
			if (typeof smooth === 'undefined') {
				smooth = true;
			}
			var prefersReducedMotion = false;
			try {
				prefersReducedMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
			} catch (e) {}

			var behavior = (smooth && !prefersReducedMotion) ? 'smooth' : 'auto';

			if (typeof window.scrollTo === 'function') {
				try {
					window.scrollTo({
						top: 0,
						left: 0,
						behavior: behavior
					});
				} catch (err) {
					window.scrollTo(0, 0);
				}
			} else {
				window.scrollTo(0, 0);
			}

			if (!smooth || prefersReducedMotion) {
				document.documentElement.scrollTop = 0;
				document.body.scrollTop = 0;
			}
		},

		/**
		 * Toggle password visibility for secret fields.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onSecretToggle: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var targetId = $btn.data('target');
			var $input = $('#' + targetId);
			if (!$input.length) {
				return;
			}
			var isPass = $input.attr('type') === 'password';
			$input.attr('type', isPass ? 'text' : 'password');
			$btn.find('.dashicons')
				.toggleClass('dashicons-visibility', !isPass)
				.toggleClass('dashicons-hidden', isPass);
		},

		/**
		 * Synchronize ARIA state for role="switch" checkboxes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onSwitchChange: function(e) {
			var input = e.currentTarget;
			$(input).attr('aria-checked', input.checked ? 'true' : 'false');
		},

		/**
		 * Synchronize live value label next to range sliders.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		onSliderInput: function(e) {
			var input = e.currentTarget;
			$(input).siblings('.aips-slider-value').text(input.value);
		},

		/**
		 * Copy shortcode text to clipboard with visual feedback.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onCopyShortcode: function(e) {
			e.preventDefault();
			var self = this;
			var $btn = $(e.currentTarget);
			var textToCopy = $btn.attr('data-clipboard-text') || $('#aips-related-posts-shortcode').text().trim() || '[aips_related_posts]';

			var doFeedback = function() {
				var $text = $btn.find('.aips-copy-text');
				var origText = $text.text();
				$btn.addClass('copied');
				$text.text(l10n.copied || 'Copied!');
				AIPS.Utilities.showToast('Shortcode copied to clipboard: ' + textToCopy, 'success');
				setTimeout(function() {
					$btn.removeClass('copied');
					$text.text(origText);
				}, 2000);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(textToCopy).then(doFeedback).catch(function() {
					self.fallbackCopyText(textToCopy, doFeedback);
				});
			} else {
				this.fallbackCopyText(textToCopy, doFeedback);
			}
		},

		/**
		 * Fallback copy helper using textarea and execCommand.
		 *
		 * @param {string}   text     Text to copy.
		 * @param {Function} callback Callback on success.
		 * @return {void}
		 */
		fallbackCopyText: function(text, callback) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				if (typeof callback === 'function') {
					callback();
				}
			} catch (err) {
				// Fallback failed
			}
			$temp.remove();
		},

		/**
		 * Fetch configured embedding environments from Meow AI Engine.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onFetchMeowEnvironments: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> ' + (l10n.discovering || 'Discovering...'));

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_indexer_fetch_meow_environments',
					nonce: aipsAjax.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html(originalHtml);
					if (response.success && response.data && response.data.environments) {
						var envs = response.data.environments;
						var $select = $('#aips-meow-envs-select');
						$select.empty();
						$select.append($('<option>', {
							value: '',
							text: '— Select a discovered environment (' + envs.length + ' found) —'
						}));

						envs.forEach(function(env) {
							var label = env.name || env.id;
							if (env.type) {
								label += ' (' + env.type + ')';
							}
							var $opt = $('<option>', {
								value: env.id,
								text: label
							});
							if (env.model) {
								$opt.attr('data-model', env.model);
							}
							if (env.dimensions) {
								$opt.attr('data-dimensions', env.dimensions);
							}
							$select.append($opt);
						});

						$('#aips-meow-envs-dropdown-container').removeClass('aips-hidden').show();
						if (envs.length === 0) {
							AIPS.Utilities.showToast('No custom embedding environments detected in Meow AI Engine.', 'info');
						}
					} else {
						var errMsg = (response.data && response.data.message) ? response.data.message : 'Could not fetch Meow environments.';
						AIPS.Utilities.showToast(errMsg, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalHtml);
					AIPS.Utilities.showToast('Failed to connect to Meow Apps AI Engine.', 'error');
				}
			});
		},

		/**
		 * Handle selection of a discovered Meow environment.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onMeowEnvSelect: function(e) {
			var selected = $(e.currentTarget).find(':selected');
			if (!selected.val()) {
				return;
			}
			$('#aips_embeddings_env_id').val(selected.val());
			if (selected.data('model')) {
				$('#aips_embeddings_model').val(selected.data('model'));
			}
			if (selected.data('dimensions')) {
				$('#aips_embeddings_dimensions').val(selected.data('dimensions'));
			}
		},

		/**
		 * Toggle date range fields when embeddings scope changes.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onEmbeddingsScopeChange: function(e) {
			var $target = $(e.currentTarget);
			var val = $target.val();
			if ($target.is(':radio')) {
				val = $('input[name="aips_embeddings_scope"]:checked').val();
			}
			var isDateRange = val === 'date_range';
			var $box = $('#aips-scope-date-range-fields');
			$box.toggleClass('aips-hidden', !isDateRange);
			if (isDateRange) {
				$box.show();
			} else {
				$box.hide();
			}
		},

		/**
		 * Toggle visibility of author topic auto-approval setting fields.
		 *
		 * @return {void}
		 */
		updateAuthorApprovalFields: function() {
			var mode = $('#aips_author_topic_auto_approval_mode').val();
			var isScore = mode === 'score';
			var isSimilarity = mode === 'similarity';
			var hasFallback = isScore || isSimilarity;
			$('#aips_author_topic_auto_approval_min_score').closest('tr').toggle(isScore);
			$('#aips_author_topic_auto_approval_min_relevance, #aips_author_topic_auto_approval_max_similarity')
				.closest('tr')
				.toggle(isSimilarity);
			$('#aips_author_topic_auto_approval_fallback').closest('tr').toggle(hasFallback);
			$('#aips-auto-approval-embeddings-warning').toggle(isSimilarity);
		},

		/**
		 * Move selected connector options up or down in failover priority.
		 *
		 * @param {Event} e Button click event.
		 * @return {void}
		 */
		onConnectorMove: function(e) {
			e.preventDefault();
			var field = document.getElementById('aips_wp_ai_connector_ids');
			if (!field) {
				return;
			}

			var direction = $(e.currentTarget).attr('data-aips-connector-move');
			var options = Array.prototype.slice.call(field.options);
			if (direction === 'up') {
				options.forEach(function(option) {
					if (option.selected && option.previousElementSibling && !option.previousElementSibling.selected) {
						field.insertBefore(option, option.previousElementSibling);
					}
				});
				return;
			}

			options.reverse().forEach(function(option) {
				if (option.selected && option.nextElementSibling && !option.nextElementSibling.selected) {
					field.insertBefore(option.nextElementSibling, option);
				}
			});
		},

		/**
		 * Flush and send pending notification digest immediately.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onFlushNotificationDigest: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> ' + (l10n.sending || 'Sending…'));

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_flush_notification_digest',
					nonce: aipsAjax.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html(originalHtml);
					if (response && response.success) {
						var msg = (response.data && response.data.message) ? response.data.message : (l10n.digestSent || 'Digest sent successfully.');
						AIPS.Utilities.showToast(msg, 'success');
						if (response.data && typeof response.data.queue_count !== 'undefined') {
							$('#aips-digest-queue-count').text(response.data.queue_count);
						}
					} else {
						var errMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.digestError || 'Failed to send digest.');
						AIPS.Utilities.showToast(errMsg, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalHtml);
					AIPS.Utilities.showToast(l10n.digestNetworkError || 'Network error while flushing digest.', 'error');
				}
			});
		},

		/**
		 * Send a sample of the selected notification email to the current user.
		 *
		 * Lets the redesigned email layouts be checked in a real mail client
		 * without waiting for a live event to fire.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onSendTestNotificationEmail: function(e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var originalHtml = $btn.html();
			var type = $('#aips-test-email-type').val();

			if (!type) {
				AIPS.Utilities.showToast(l10n.testEmailChooseType || 'Choose a notification type first.', 'error');
				return;
			}

			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> ' + (l10n.sending || 'Sending…'));

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_send_test_notification_email',
					nonce: aipsAjax.nonce,
					notification_type: type
				},
				success: function(response) {
					$btn.prop('disabled', false).html(originalHtml);

					if (response && response.success) {
						var msg = (response.data && response.data.message) ? response.data.message : (l10n.testEmailSent || 'Test email sent.');
						AIPS.Utilities.showToast(msg, 'success');
					} else {
						var errMsg = (response && response.data && response.data.message) ? response.data.message : (l10n.testEmailError || 'Failed to send the test email.');
						AIPS.Utilities.showToast(errMsg, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalHtml);
					AIPS.Utilities.showToast(l10n.testEmailNetworkError || 'Network error while sending the test email.', 'error');
				}
			});
		},

		/**
		 * Toggle visibility of cache-system-specific setting rows.
		 *
		 * @return {void}
		 */
		updateCacheSystemFields: function() {
			var enabled = $('input[name="aips_enable_cache_system"]:checked').val() === '1';

			$('.aips-cache-system-fields').each(function() {
				$(this).closest('tr').toggle(enabled);
			});

			if (enabled) {
				this.updateCacheDriverFields();
			} else {
				$('.aips-cache-db-fields').each(function() {
					$(this).closest('tr').hide();
				});
			}
		},

		/**
		 * Toggle visibility of driver-specific cache setting rows.
		 *
		 * @return {void}
		 */
		updateCacheDriverFields: function() {
			var driver = $('#aips_cache_driver').val();

			$('.aips-cache-db-fields').each(function() {
				$(this).closest('tr').toggle(driver === 'db');
			});
		}
	};

	$(document).ready(function() {
		if ($('#aips-settings-tab-nav').length || $('#aips-settings-form').length) {
			AIPS.Settings.init();
		}

		if (typeof AIPS.AIAssistance !== 'undefined') {
			AIPS.AIAssistance.init({ formContext: 'content_strategy' });
		}
	});

})(jQuery);
