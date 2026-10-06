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
		 * Activate a settings tab from the current URL hash, query parameter, or localStorage if one exists.
		 *
		 * @return {void}
		 */
		activateSettingsTabFromHash: function() {
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
		bindSettingsEvents: function() {
			if (this._adminSettingsEventsBound) {
				return;
			}
			this._adminSettingsEventsBound = true;

			$('#aips-settings-form').off('submit.aipsSettings').on('submit.aipsSettings', AIPS.onSettingsFormSubmit);
			$(document).off('aips:tabSwitch.aipsSettings').on('aips:tabSwitch.aipsSettings', AIPS.onSettingsTabSwitch);
			if (AIPS.testConnection) {
				$(document).off('click.aipsTestConn', '#aips-test-connection').on('click.aipsTestConn', '#aips-test-connection', AIPS.testConnection);
			}
			$(document).on('click', '[data-aips-connector-move]', AIPS.onConnectorMove);
			$(document).on('change', 'input[name="aips_embeddings_scope"], #aips_embeddings_scope', function() {
				var val = $(this).val();
				if ($(this).is(':radio')) {
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
			});
			$(document).on('click', '#aips-fetch-meow-envs-btn', AIPS.onFetchMeowEnvironments);
			$(document).on('click', '#aips-copy-shortcode-btn', AIPS.onCopyShortcode);
			$(document).on('change', '#aips-meow-envs-select', function() {
				var selected = $(this).find(':selected');
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
			});
			function updateAuthorApprovalFields() {
				var mode = $('#aips_author_topic_auto_approval_mode').val();
				var isSimilarity = mode === 'similarity';
				$('#aips_author_topic_auto_approval_min_score, #aips_author_topic_auto_approval_max_similarity, #aips_author_topic_auto_approval_fallback')
					.closest('tr')
					.toggle(isSimilarity);
			}
			$(document).on('change', '#aips_author_topic_auto_approval_mode', updateAuthorApprovalFields);
			updateAuthorApprovalFields();
		},

		/**
		 * Copy shortcode text to clipboard with visual feedback.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onCopyShortcode: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var textToCopy = $btn.attr('data-clipboard-text') || $('#aips-related-posts-shortcode').text().trim() || '[aips_related_posts]';

			var doFeedback = function() {
				var $text = $btn.find('.aips-copy-text');
				var origText = $text.text();
				$btn.addClass('copied');
				$text.text((window.aipsSettingsL10n && aipsSettingsL10n.copied) ? aipsSettingsL10n.copied : 'Copied!');
				if (AIPS.Utilities && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast('Shortcode copied to clipboard: ' + textToCopy, 'success');
				}
				setTimeout(function() {
					$btn.removeClass('copied');
					$text.text(origText);
				}, 2000);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(textToCopy).then(doFeedback).catch(function() {
					AIPS.fallbackCopyText(textToCopy, doFeedback);
				});
			} else {
				AIPS.fallbackCopyText(textToCopy, doFeedback);
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
			var $btn = $(this);
			var originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> ' + ((window.aipsSettingsL10n && aipsSettingsL10n.discovering) ? aipsSettingsL10n.discovering : 'Discovering...'));

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
							if (AIPS.Utilities && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast('No custom embedding environments detected in Meow AI Engine.', 'info');
							}
						}
					} else {
						var errMsg = (response.data && response.data.message) ? response.data.message : 'Could not fetch Meow environments.';
						if (AIPS.Utilities && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(errMsg, 'error');
						} else {
							alert(errMsg);
						}
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalHtml);
					if (AIPS.Utilities && AIPS.Utilities.showToast) {
						AIPS.Utilities.showToast('Failed to connect to Meow Apps AI Engine.', 'error');
					} else {
						alert('Failed to connect to Meow Apps AI Engine.');
					}
				}
			});
		},

		/**
		 * Save the active settings tab via AJAX.
		 *
		 * @param {Event} e Form submit event.
		 * @return {void}
		 */
		onSettingsFormSubmit: function(e) {
			e.preventDefault();

			if (AIPS._settingsSaving) {
				return;
			}

			var $form = $(this);
			var $activeTab = $form.find('.aips-tab-content:visible').first();
			var $submit = $activeTab.find('input[type="submit"], button[type="submit"]');
			if (!$submit.length) {
				$submit = $form.find('input[type="submit"], button[type="submit"]');
			}
			$submit = $submit.first();
			var savingLabel = (window.aipsSettingsL10n && aipsSettingsL10n.saving) ? aipsSettingsL10n.saving : 'Saving...';
			var settings = AIPS.collectSettingsPayload($activeTab);

			if ($.isEmptyObject(settings)) {
				AIPS.Utilities.showToast(
					(window.aipsSettingsL10n && aipsSettingsL10n.payloadError) ? aipsSettingsL10n.payloadError : 'No settings were found to save.',
					'warning'
				);
				return;
			}

			AIPS._settingsSaving = true;

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
			}).fail(function(xhr) {
				var message = (window.aipsSettingsL10n && aipsSettingsL10n.saveError) ? aipsSettingsL10n.saveError : 'Failed to save settings.';
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					message = xhr.responseJSON.data.message;
				}
				AIPS.Utilities.showToast(message, 'error');
			}).always(function() {
				AIPS._settingsSaving = false;
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
			var payload = {};

			// First, ensure non-array checkboxes in the active tab default to 0 if unchecked
			$scope.find('input[type="checkbox"][name]').each(function() {
				var name = $(this).attr('name');
				if (name && !/\[\]$/.test(name) && !$(this).is(':checked')) {
					AIPS.assignNestedSetting(payload, name, 0);
				}
			});

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

			var direction = $(this).attr('data-aips-connector-move');
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
		 * Update the URL, hidden fields, and referer to reflect the newly active settings tab.
		 *
		 * @param {Event}  e     Custom jQuery event.
		 * @param {string} tabId The ID of the tab that was just activated.
		 * @return {void}
		 */
		onSettingsTabSwitch: function(e, tabId) {
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
