/**
 * Prompt Profiles Admin JavaScript
 *
 * Manages the Prompt Profiles admin interface, including profile CRUD, tabbed stage
 * navigation, placeholder tag insertion, live preview assembling, and default toggling.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

/* global jQuery, aipsAjax, aipsPromptProfilesL10n, AIPS */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * Prompt Profiles management module.
	 *
	 * @namespace AIPS.PromptProfiles
	 * @type {Object}
	 */
	AIPS.PromptProfiles = {
		/**
		 * Currently active profile ID being edited (null when creating new).
		 *
		 * @type {number|null}
		 */
		currentProfileId: null,

		/**
		 * Currently selected generation stage tab.
		 *
		 * @type {string}
		 */
		activeStage: 'title_prompt',

		/**
		 * Core codebase fallback prompt strings dictionary.
		 *
		 * @type {Object.<string, string>}
		 */
		coreFallbacks: {},

		/**
		 * Tracks whether the form has unsaved modifications.
		 *
		 * @type {boolean}
		 */
		isDirty: false,

		/**
		 * Debounce timer identifier for live preview updates.
		 *
		 * @type {number|null}
		 */
		previewTimeout: null,

		/**
		 * List of all 9 generation stage keys.
		 *
		 * @type {string[]}
		 */
		stages: [
			'title_prompt',
			'title_followup_prompt',
			'content_prompt',
			'excerpt_prompt',
			'excerpt_followup_prompt',
			'featured_image_prompt',
			'topic_ideas_prompt',
			'metadata_prompt',
			'taxonomy_prompt'
		],

		/**
		 * Initialize the Prompt Profiles admin module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
			this.loadCoreFallbacks();
		},

		/**
		 * Bind DOM event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			var self = this;

			// Add Profile Button
			$(document).on('click', '.aips-add-profile-btn, #aips-add-profile-btn', function (e) {
				e.preventDefault();
				self.openCreateModal();
			});

			// Edit Profile Button
			$(document).on('click', '.aips-edit-profile-btn', function (e) {
				e.preventDefault();
				var profileId = $(this).data('id');
				self.openEditModal(profileId);
			});

			// Clone Profile Button
			$(document).on('click', '.aips-clone-profile-btn', function (e) {
				e.preventDefault();
				var profileId = $(this).data('id');
				self.cloneProfile(profileId);
			});

			// Set Default Button
			$(document).on('click', '.aips-set-default-btn', function (e) {
				e.preventDefault();
				var profileId = $(this).data('id');
				self.setDefaultProfile(profileId);
			});

			// Delete Profile Button
			$(document).on('click', '.aips-delete-profile-btn', function (e) {
				e.preventDefault();
				var profileId = $(this).data('id');
				self.deleteProfile(profileId);
			});

			// Modal Close Buttons
			$(document).on('click', '#aips-prompt-profile-modal .aips-modal-close', function (e) {
				e.preventDefault();
				self.closeModal();
			});

			// Stage Tab Navigation
			$(document).on('click', '.aips-stage-tabs-list li', function () {
				var stage = $(this).data('stage');
				self.switchStage(stage);
			});

			// Placeholder Chip Click -> Insert tag into active textarea
			$(document).on('click', '.aips-placeholder-chip', function (e) {
				e.preventDefault();
				var tag = $(this).data('tag');
				self.insertTagAtCursor(tag);
			});

			// Reset Stage to Core Default
			$(document).on('click', '.aips-reset-stage-btn', function (e) {
				e.preventDefault();
				var stage = $(this).data('stage');
				self.resetStagePrompt(stage);
			});

			// Form Submit / Save Profile
			$('#aips-prompt-profile-form').on('submit', function (e) {
				e.preventDefault();
				self.saveProfile();
			});

			// Track changes on textareas & inputs
			$('#aips-prompt-profile-form').on('input change', 'input, textarea', function () {
				self.setDirty(true);
				self.updateCustomizedIndicators();
				if ($('#aips-sandbox-body').is(':visible')) {
					self.debouncePreview();
				}
			});

			// Sandbox Accordion Toggle
			$('#aips-toggle-sandbox').on('click', function () {
				var $body = $('#aips-sandbox-body');
				var $container = $('.aips-prompt-preview-sandbox');
				if ($body.is(':visible')) {
					$body.slideUp(200);
					$container.removeClass('is-expanded');
				} else {
					$body.slideDown(200, function () {
						$container.addClass('is-expanded');
						self.fetchLivePreview();
					});
				}
			});

			// Refresh Preview button
			$('#aips-refresh-preview-btn').on('click', function (e) {
				e.preventDefault();
				self.fetchLivePreview();
			});

			// Filter Bar Buttons
			$('.aips-filter-btn').on('click', function () {
				$('.aips-filter-btn').removeClass('is-active');
				$(this).addClass('is-active');
				self.applyFilters();
			});

			// Search Bar Input
			$('#aips-profile-search').on('input', function () {
				var query = $(this).val().trim();
				$('#aips-profile-search-clear').toggle(query.length > 0);
				self.applyFilters();
			});

			// Search Clear Button
			$('#aips-profile-search-clear, #aips-clear-search-btn').on('click', function () {
				$('#aips-profile-search').val('');
				$('#aips-profile-search-clear').hide();
				self.applyFilters();
			});
		},

		/**
		 * Fetch core codebase fallback prompts via AJAX for client-side resets.
		 *
		 * @return {void}
		 */
		loadCoreFallbacks: function () {
			var self = this;
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_prompt_profiles',
					nonce: aipsPromptProfilesL10n.nonce
				},
				success: function (response) {
					if (response.success && response.data.core_fallbacks) {
						self.coreFallbacks = response.data.core_fallbacks;
					}
				}
			});
		},

		/**
		 * Open the Create Prompt Profile modal in a blank state.
		 *
		 * @return {void}
		 */
		openCreateModal: function () {
			var $form = $('#aips-prompt-profile-form');
			$form[0].reset();
			$('#aips_profile_id').val('');
			$('#aips_profile_is_default').val('0');
			$('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.createProfile);
			$('#aips-modal-profile-badge').hide();
			this.currentProfileId = null;
			this.switchStage('title_prompt');
			this.setDirty(false);
			this.updateCustomizedIndicators();
			$('#aips-preview-output-box').text('Click refresh or type above to assemble preview...');
			$('#aips-prompt-profile-modal').fadeIn(200);
		},

		/**
		 * Load and open an existing Prompt Profile in the editor modal.
		 *
		 * @param {number} profileId - The ID of the profile to edit.
		 * @return {void}
		 */
		openEditModal: function (profileId) {
			var self = this;
			self.currentProfileId = profileId;

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_prompt_profile',
					id: profileId,
					nonce: aipsPromptProfilesL10n.nonce
				},
				beforeSend: function () {
					$('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.editProfile + ' (Loading...)');
					$('#aips-prompt-profile-modal').fadeIn(200);
				},
				success: function (response) {
					if (response.success && response.data.profile) {
						var p = response.data.profile;
						$('#aips_profile_id').val(p.id);
						$('#aips_profile_name').val(p.name);
						$('#aips_profile_slug').val(p.slug);
						$('#aips_profile_description').val(p.description || '');
						$('#aips_profile_is_default').val(p.is_default ? '1' : '0');

						// Populate all 9 stages
						self.stages.forEach(function (stg) {
							$('#stage_' + stg).val(p[stg] || '');
						});

						$('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.editProfile + ': ' + p.name);

						var $badge = $('#aips-modal-profile-badge');
						if (p.is_builtin) {
							$badge.text(aipsPromptProfilesL10n.builtinBadge).removeClass('aips-badge-neutral').addClass('aips-badge-info').show();
						} else if (p.is_default) {
							$badge.text(aipsPromptProfilesL10n.defaultBadge).removeClass('aips-badge-neutral').addClass('aips-badge-success').show();
						} else {
							$badge.text(aipsPromptProfilesL10n.customBadge).removeClass('aips-badge-info aips-badge-success').addClass('aips-badge-neutral').show();
						}

						self.switchStage(self.activeStage || 'title_prompt');
						self.setDirty(false);
						self.updateCustomizedIndicators();
					} else {
						self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorLoading);
						self.closeModal();
					}
				},
				error: function () {
					self.notify('error', aipsPromptProfilesL10n.errorLoading);
					self.closeModal();
				}
			});
		},

		/**
		 * Close the profile modal dialog.
		 *
		 * @return {void}
		 */
		closeModal: function () {
			$('#aips-prompt-profile-modal').fadeOut(200);
			this.setDirty(false);
		},

		/**
		 * Switch the active generation stage panel and tab.
		 *
		 * @param {string} stage - The stage identifier to activate.
		 * @return {void}
		 */
		switchStage: function (stage) {
			this.activeStage = stage;
			$('.aips-stage-tabs-list li').removeClass('is-active').filter('[data-stage="' + stage + '"]').addClass('is-active');
			$('.aips-stage-panel').removeClass('is-active').filter('[data-stage-panel="' + stage + '"]').addClass('is-active');

			if ($('#aips-sandbox-body').is(':visible')) {
				this.fetchLivePreview();
			}
		},

		/**
		 * Insert a variable tag at the current cursor position in the active textarea.
		 *
		 * @param {string} tag - The placeholder tag string, e.g. '{{topic}}'.
		 * @return {void}
		 */
		insertTagAtCursor: function (tag) {
			var activePanel = $('.aips-stage-panel.is-active');
			var textarea = activePanel.find('.aips-stage-textarea')[0];
			if (!textarea) {
				return;
			}

			var startPos = textarea.selectionStart;
			var endPos = textarea.selectionEnd;
			var text = textarea.value;

			textarea.value = text.substring(0, startPos) + tag + text.substring(endPos, text.length);
			textarea.selectionStart = startPos + tag.length;
			textarea.selectionEnd = startPos + tag.length;
			textarea.focus();

			this.setDirty(true);
			this.updateCustomizedIndicators();
			this.debouncePreview();
			this.notify('info', aipsPromptProfilesL10n.copiedChip);
		},

		/**
		 * Reset the active stage prompt to the core codebase default.
		 *
		 * @param {string} stage - The stage key to reset.
		 * @return {void}
		 */
		resetStagePrompt: function (stage) {
			if (!confirm(aipsPromptProfilesL10n.confirmResetStage)) {
				return;
			}

			var fallback = this.coreFallbacks[stage] || '';
			var $textarea = $('#stage_' + stage);
			$textarea.val(fallback);
			this.setDirty(true);
			this.updateCustomizedIndicators();
			this.debouncePreview();
		},

		/**
		 * Update the blue indicator dots on stage tabs indicating customized prompts.
		 *
		 * @return {void}
		 */
		updateCustomizedIndicators: function () {
			var self = this;
			self.stages.forEach(function (stg) {
				var val = $('#stage_' + stg).val();
				var hasCustom = val && val.trim().length > 0;
				$('.aips-stage-indicator[data-stage="' + stg + '"]').toggleClass('is-customized', !!hasCustom);
			});
		},

		/**
		 * Save the current profile form data via AJAX.
		 *
		 * @return {void}
		 */
		saveProfile: function () {
			var self = this;
			var name = $('#aips_profile_name').val().trim();
			if (!name) {
				self.notify('error', aipsPromptProfilesL10n.nameRequired);
				$('#aips_profile_name').focus();
				return;
			}

			var formData = {
				action: 'aips_save_prompt_profile',
				nonce: aipsPromptProfilesL10n.nonce,
				id: $('#aips_profile_id').val(),
				name: name,
				slug: $('#aips_profile_slug').val().trim(),
				description: $('#aips_profile_description').val().trim(),
				is_default: $('#aips_profile_is_default').val(),
				title_prompt: $('#stage_title_prompt').val(),
				title_followup_prompt: $('#stage_title_followup_prompt').val(),
				content_prompt: $('#stage_content_prompt').val(),
				excerpt_prompt: $('#stage_excerpt_prompt').val(),
				excerpt_followup_prompt: $('#stage_excerpt_followup_prompt').val(),
				featured_image_prompt: $('#stage_featured_image_prompt').val(),
				topic_ideas_prompt: $('#stage_topic_ideas_prompt').val(),
				metadata_prompt: $('#stage_metadata_prompt').val(),
				taxonomy_prompt: $('#stage_taxonomy_prompt').val()
			};

			var $btn = $('#aips-save-profile-btn');
			$btn.prop('disabled', true).text(aipsPromptProfilesL10n.saving);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function (response) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + aipsPromptProfilesL10n.saveProfile);
					if (response.success) {
						self.notify('success', aipsPromptProfilesL10n.profileSaved);
						self.closeModal();
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorSaving);
					}
				},
				error: function () {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> ' + aipsPromptProfilesL10n.saveProfile);
					self.notify('error', aipsPromptProfilesL10n.errorSaving);
				}
			});
		},

		/**
		 * Clone an existing prompt profile.
		 *
		 * @param {number} profileId - The ID of the profile to clone.
		 * @return {void}
		 */
		cloneProfile: function (profileId) {
			var self = this;
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_clone_prompt_profile',
					id: profileId,
					nonce: aipsPromptProfilesL10n.nonce
				},
				success: function (response) {
					if (response.success) {
						self.notify('success', aipsPromptProfilesL10n.profileCloned);
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorCloning);
					}
				},
				error: function () {
					self.notify('error', aipsPromptProfilesL10n.errorCloning);
				}
			});
		},

		/**
		 * Set a prompt profile as the global site default.
		 *
		 * @param {number} profileId - The ID of the profile to make default.
		 * @return {void}
		 */
		setDefaultProfile: function (profileId) {
			var self = this;
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_set_default_prompt_profile',
					id: profileId,
					nonce: aipsPromptProfilesL10n.nonce
				},
				success: function (response) {
					if (response.success) {
						self.notify('success', aipsPromptProfilesL10n.defaultSet);
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorSaving);
					}
				},
				error: function () {
					self.notify('error', aipsPromptProfilesL10n.errorSaving);
				}
			});
		},

		/**
		 * Delete a prompt profile after user confirmation.
		 *
		 * @param {number} profileId - The ID of the profile to delete.
		 * @return {void}
		 */
		deleteProfile: function (profileId) {
			var self = this;
			if (!confirm(aipsPromptProfilesL10n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_delete_prompt_profile',
					id: profileId,
					nonce: aipsPromptProfilesL10n.nonce
				},
				success: function (response) {
					if (response.success) {
						self.notify('success', aipsPromptProfilesL10n.profileDeleted);
						$('tr[data-profile-id="' + profileId + '"]').fadeOut(300, function () {
							$(this).remove();
						});
					} else {
						self.notify('error', (response.data && response.data.message) || aipsPromptProfilesL10n.errorDeleting);
					}
				},
				error: function () {
					self.notify('error', aipsPromptProfilesL10n.errorDeleting);
				}
			});
		},

		/**
		 * Debounce live prompt preview assembling.
		 *
		 * @return {void}
		 */
		debouncePreview: function () {
			var self = this;
			clearTimeout(self.previewTimeout);
			self.previewTimeout = setTimeout(function () {
				self.fetchLivePreview();
			}, 400);
		},

		/**
		 * Execute AJAX prompt assembly for the active stage with stage-specific contextual test data.
		 *
		 * @return {void}
		 */
		fetchLivePreview: function () {
			var self = this;
			var stage = self.activeStage;
			var promptText = $('#stage_' + stage).val();
			var sampleTopic = $('#aips_preview_sample_topic').val() || '10 Proven Strategies for Sustainable Organic Gardening';
			var sampleVoice = $('#aips_preview_sample_voice').val() || 'Conversational and authoritative.';

			var sampleContext = {
				topic: sampleTopic,
				voice: sampleVoice,
				niche: 'Sustainable Gardening & Botany',
				author_name: 'Dr. Clara Vance',
				content: 'Title: ' + sampleTopic + '\nOutline: 1. Soil Health 2. Water Retention 3. Natural Pest Control',
				word_count_min: 1200,
				word_count_max: 2000
			};

			$('#aips-preview-output-box').text(aipsPromptProfilesL10n.previewGenerating);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_preview_prompt_profile',
					nonce: aipsPromptProfilesL10n.nonce,
					stage: stage,
					prompt_template: promptText,
					sample_context: sampleContext
				},
				success: function (response) {
					if (response.success && (response.data.preview_prompt || response.data.assembled_prompt)) {
						$('#aips-preview-output-box').text(response.data.preview_prompt || response.data.assembled_prompt);
					} else {
						$('#aips-preview-output-box').text(aipsPromptProfilesL10n.errorPreview);
					}
				},
				error: function () {
					$('#aips-preview-output-box').text(aipsPromptProfilesL10n.errorPreview);
				}
			});
		},

		/**
		 * Apply active category and text search filters to the profile table.
		 *
		 * @return {void}
		 */
		applyFilters: function () {
			var activeFilter = $('.aips-filter-btn.is-active').data('filter') || 'all';
			var query = ($('#aips-profile-search').val() || '').toLowerCase();
			var visibleCount = 0;

			$('#aips-prompt-profiles-table tbody tr').each(function () {
				var $row = $(this);
				var isBuiltin = $row.data('is-builtin') === 1 || $row.data('is-builtin') === '1';
				var text = $row.text().toLowerCase();

				var matchesFilter = true;
				if (activeFilter === 'builtin' && !isBuiltin) {
					matchesFilter = false;
				} else if (activeFilter === 'custom' && isBuiltin) {
					matchesFilter = false;
				}

				var matchesSearch = !query || text.indexOf(query) !== -1;

				if (matchesFilter && matchesSearch) {
					$row.show();
					visibleCount++;
				} else {
					$row.hide();
				}
			});

			$('#aips-profile-search-no-results').toggle(visibleCount === 0);
			$('#aips-prompt-profiles-table').toggle(visibleCount > 0);
		},

		/**
		 * Set the form dirty state and update warning badges.
		 *
		 * @param {boolean} dirty - Whether unsaved changes exist.
		 * @return {void}
		 */
		setDirty: function (dirty) {
			this.isDirty = dirty;
			$('#aips-unsaved-changes-indicator').toggle(dirty);
		},

		/**
		 * Display an administrative notification toast.
		 *
		 * @param {string} type - 'success', 'error', 'info', or 'warning'.
		 * @param {string} message - The message text to display.
		 * @return {void}
		 */
		notify: function (type, message) {
			if (typeof AIPS !== 'undefined' && AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
				AIPS.Utilities.showToast(message, type);
			} else if (typeof AIPS !== 'undefined' && AIPS.Utilities && typeof AIPS.Utilities.showNotification === 'function') {
				AIPS.Utilities.showNotification(message, type);
			} else {
				alert(message);
			}
		}
	};

	$(document).ready(function () {
		AIPS.PromptProfiles.init();
	});

})(jQuery);
