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
				var isScore = mode === 'score';
				var isSimilarity = mode === 'similarity';
				var hasFallback = isScore || isSimilarity;
				$('#aips_author_topic_auto_approval_min_score').closest('tr').toggle(isScore);
				$('#aips_author_topic_auto_approval_min_relevance, #aips_author_topic_auto_approval_max_similarity')
					.closest('tr')
					.toggle(isSimilarity);
				$('#aips_author_topic_auto_approval_fallback').closest('tr').toggle(hasFallback);
				$('#aips-auto-approval-embeddings-warning').toggle(isSimilarity);
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

		if (typeof AIPS.AIAssistance !== 'undefined') {
			AIPS.AIAssistance.init({ formContext: 'content_strategy' });
		}

		initImportExport();
	});


	/**
	 * Import & Export Presets and Data Studio handlers
	 */
	function initImportExport() {
		var $grid = $('#aips-presets-grid');
		if (!$grid.length) {
			return;
		}

		var currentImportKey = '';

		function loadPresetsStatus() {
			$('#aips-presets-loading').show();
			$grid.empty();

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_get_presets_status',
					nonce: aipsAjax.nonce
				},
				success: function(res) {
					$('#aips-presets-loading').hide();
					if (!res.success || !res.data || !res.data.presets) {
						var errMsg = (res.data && res.data.message) ? res.data.message : 'Failed to load presets.';
						$grid.html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
							notice_class: 'notice-error',
							message: errMsg
						}));
						return;
					}

					var presets = res.data.presets;
					var html = '';
					presets.forEach(function(p) {
						var statusBadge = p.is_installed
							? AIPS.Templates.render('aips-tmpl-preset-badge-installed')
							: AIPS.Templates.render('aips-tmpl-preset-badge-available');

						var actionBtn = p.is_installed
							? AIPS.Templates.render('aips-tmpl-preset-btn-remove', {
								id: p.id,
								has_posts: p.post_count > 0 ? '1' : '0',
								post_count: p.post_count
							})
							: AIPS.Templates.render('aips-tmpl-preset-btn-install', {
								id: p.id
							});

						var usageNotice = (p.is_installed && p.post_count > 0)
							? AIPS.Templates.render('aips-tmpl-preset-usage-notice', { post_count: p.post_count })
							: '';

						html += AIPS.Templates.renderRaw('aips-tmpl-preset-card', {
							icon: p.icon,
							name: p.name,
							tagline: p.tagline,
							author_name: p.author_name,
							template_name: p.template_name,
							voice_name: p.voice_name,
							structure_name: p.structure_name,
							status_badge: statusBadge,
							action_button: actionBtn,
							usage_notice: usageNotice
						});
					});

					$grid.html(html);
				},
				error: function() {
					$('#aips-presets-loading').hide();
					$grid.html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
						notice_class: 'notice-error',
						message: 'Could not connect to server to fetch presets.'
					}));
				}
			});
		}

		// Initial load
		loadPresetsStatus();

		$('#aips-refresh-presets-btn').on('click', function(e) {
			e.preventDefault();
			loadPresetsStatus();
		});

		// Install preset
		$(document).on('click', '.aips-preset-install-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var presetId = $btn.data('preset-id');

			$btn.prop('disabled', true).text('Installing…');

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_install_preset',
					preset_id: presetId,
					set_strategy: true,
					nonce: aipsAjax.nonce
				},
				success: function(res) {
					if (res.success) {
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('success', res.data.message || 'Preset installed successfully!');
						} else if (typeof AIPS.showToast === 'function') {
							AIPS.showToast(res.data.message || 'Preset installed successfully!', 'success');
						}
						loadPresetsStatus();
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Error installing preset.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('error', err);
						} else {
							AIPS.Utilities.confirm(err, 'Error');
						}
						$btn.prop('disabled', false).text('Install Preset');
					}
				},
				error: function() {
					if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
						AIPS.Utilities.showNotice('error', 'Connection error occurred while installing preset.');
					} else {
						AIPS.Utilities.confirm('Connection error occurred while installing preset.', 'Error');
					}
					$btn.prop('disabled', false).text('Install Preset');
				}
			});
		});

		function executeUninstall(presetId, force, $btn) {
			$btn.prop('disabled', true).text('Removing…');

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_remove_preset',
					preset_id: presetId,
					force: force,
					nonce: aipsAjax.nonce
				},
				success: function(res) {
					if (res.success) {
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('success', res.data.message || 'Preset removed.');
						} else if (typeof AIPS.showToast === 'function') {
							AIPS.showToast(res.data.message || 'Preset removed.', 'success');
						}
						loadPresetsStatus();
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Error removing preset.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('error', err);
						} else {
							AIPS.Utilities.confirm(err, 'Error');
						}
						$btn.prop('disabled', false).text('Remove');
					}
				},
				error: function(xhr) {
					var res = xhr.responseJSON;
					if (res && res.data && res.data.has_usage) {
						AIPS.Utilities.confirm(
							(res.data.message || 'This preset has generated content.') + '\n\nDo you want to FORCE remove this preset and its configuration entities?',
							'Force Removal Confirmation',
							[
								{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
								{
									label: 'Force Remove',
									className: 'aips-btn aips-btn-danger-solid',
									action: function() {
										executeUninstall(presetId, true, $btn);
									}
								}
							]
						);
					} else {
						var err = (res && res.data && res.data.message) ? res.data.message : 'Connection error occurred while removing preset.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('error', err);
						} else {
							AIPS.Utilities.confirm(err, 'Error');
						}
					}
					$btn.prop('disabled', false).text('Remove');
				}
			});
		}

		// Remove preset
		$(document).on('click', '.aips-preset-remove-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var presetId = $btn.data('preset-id');
			var hasPosts = $btn.data('has-posts') === '1' || $btn.data('has-posts') === 1;
			var postCount = parseInt($btn.data('posts') || '0', 10);

			if (hasPosts) {
				if (AIPS.Utilities && typeof AIPS.Utilities.showModal === 'function') {
					AIPS.Utilities.showModal({
						heading: 'Strict Content Protection',
						message: 'Warning: This preset has ' + postCount + ' generated post(s) or history records linked to it.\n\nStrict Content Protection is active: deleting the preset will keep your WordPress posts, but remove the template and author definitions.\n\nType OK to force removal:',
						fields: [
							{
								type: 'text',
								name: 'confirm_text',
								label: 'Type OK to confirm',
								placeholder: 'OK',
								required: true,
								validate: function(val) {
									return (val && val.trim().toUpperCase() === 'OK') ? null : 'Please type OK to confirm removal.';
								}
							}
						],
						buttons: [
							{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
							{
								label: 'Force Uninstall',
								className: 'aips-btn aips-btn-danger-solid',
								submit: true,
								action: function() {
									executeUninstall(presetId, true, $btn);
								}
							}
						]
					});
				} else {
					executeUninstall(presetId, true, $btn);
				}
			} else {
				if (AIPS.Utilities && typeof AIPS.Utilities.confirm === 'function') {
					AIPS.Utilities.confirm(
						'Are you sure you want to uninstall this preset? All associated templates, voices, and authors created by this preset will be cleanly removed.',
						'Uninstall Preset',
						[
							{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
							{
								label: 'Uninstall',
								className: 'aips-btn aips-btn-danger-solid',
								action: function() {
									executeUninstall(presetId, false, $btn);
								}
							}
						]
					);
				} else {
					executeUninstall(presetId, false, $btn);
				}
			}
		});

		// Export selection helpers
		$('#aips-export-select-all').on('click', function(e) {
			e.preventDefault();
			$('.aips-export-type-cb').prop('checked', true);
		});

		$('#aips-export-deselect-all').on('click', function(e) {
			e.preventDefault();
			$('.aips-export-type-cb').prop('checked', false);
		});

		// Export execution
		$('#aips-btn-run-export').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var selected = [];
			$('.aips-export-type-cb:checked').each(function() {
				selected.push($(this).val());
			});

			if (!selected.length) {
				if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
					AIPS.Utilities.showNotice('warning', 'Please check at least one entity type to export.');
				} else {
					AIPS.Utilities.confirm('Please check at least one entity type to export.', 'Notice');
				}
				return;
			}

			$btn.prop('disabled', true).text('Exporting…');

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_export_data',
					types: selected,
					nonce: aipsAjax.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-export-btn-label'));
					if (res.success && res.data && res.data.json) {
						var blob = new Blob([res.data.json], { type: 'application/json' });
						var link = document.createElement('a');
						link.href = window.URL.createObjectURL(blob);
						link.download = res.data.filename || 'aips-export.json';
						document.body.appendChild(link);
						link.click();
						document.body.removeChild(link);

						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('success', 'Export package generated and downloaded successfully.');
						}
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Export failed.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('error', err);
						} else {
							AIPS.Utilities.confirm(err, 'Export Error');
						}
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-export-btn-label'));
					if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
						AIPS.Utilities.showNotice('error', 'Export request failed due to connection error.');
					} else {
						AIPS.Utilities.confirm('Export request failed due to connection error.', 'Export Error');
					}
				}
			});
		});

		// File Upload Analysis
		$('#aips-import-file-input').on('change', function() {
			var file = this.files && this.files[0];
			if (!file) {
				return;
			}

			var formData = new FormData();
			formData.append('action', 'aips_analyze_import_file');
			formData.append('nonce', aipsAjax.nonce);
			formData.append('import_file', file);

			var $status = $('#aips-import-status-box');
			$status.show().html(AIPS.Templates.render('aips-tmpl-import-analyzing'));
			$('#aips-import-analysis-card').hide();

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function(res) {
					$status.hide();
					if (res.success && res.data && res.data.counts) {
						currentImportKey = res.data.import_key;
						var counts = res.data.counts;
						var countsHtml = '';
						Object.keys(counts).forEach(function(k) {
							var count = counts[k];
							var label = k.replace(/_/g, ' ');
							label = label.charAt(0).toUpperCase() + label.slice(1);
							countsHtml += AIPS.Templates.render('aips-tmpl-import-count-item', {
								type_key: k,
								label: label,
								count: count
							});
						});
						$('#aips-import-counts-container').html(countsHtml);
						$('#aips-import-analysis-card').slideDown();
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Invalid backup file.';
						$status.show().html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
							notice_class: 'notice-error',
							message: err
						}));
					}
				},
				error: function() {
					$status.show().html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
						notice_class: 'notice-error',
						message: 'Failed to analyze backup file due to connection error.'
					}));
				}
			});
		});

		// Execute Import
		$('#aips-btn-confirm-import').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var selected = [];
			$('.aips-import-type-cb:checked').each(function() {
				selected.push($(this).val());
			});

			if (!selected.length) {
				if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
					AIPS.Utilities.showNotice('warning', 'Please select at least one component type to import.');
				} else {
					AIPS.Utilities.confirm('Please select at least one component type to import.', 'Notice');
				}
				return;
			}

			var policy = $('input[name="aips_import_conflict_policy"]:checked').val() || 'copy';

			$btn.prop('disabled', true).text('Importing…');
			var $status = $('#aips-import-status-box');
			$status.show().html(AIPS.Templates.render('aips-tmpl-import-executing'));

			$.ajax({
				url: aipsAjax.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_execute_import',
					import_key: currentImportKey,
					types: selected,
					conflict_policy: policy,
					nonce: aipsAjax.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-import-btn-label'));
					if (res.success && res.data && res.data.report) {
						var rep = res.data.report;
						var summaryMsg = 'Import complete: ' + (rep.imported ? rep.imported.length : 0) + ' imported, ' + (rep.updated ? rep.updated.length : 0) + ' updated, ' + (rep.skipped ? rep.skipped.length : 0) + ' skipped.';
						$status.html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
							notice_class: 'notice-success',
							message: summaryMsg
						}));
						if (AIPS.Utilities && typeof AIPS.Utilities.showNotice === 'function') {
							AIPS.Utilities.showNotice('success', summaryMsg);
						}
						$('#aips-import-analysis-card').slideUp();
						loadPresetsStatus();
					} else {
						var err = (res.data && res.data.message) ? res.data.message : 'Import failed.';
						$status.html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
							notice_class: 'notice-error',
							message: err
						}));
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(AIPS.Templates.render('aips-tmpl-import-btn-label'));
					$status.html(AIPS.Templates.render('aips-tmpl-import-status-notice', {
						notice_class: 'notice-error',
						message: 'Connection error occurred during import execution.'
					}));
				}
			});
		});
	}
})(jQuery);
