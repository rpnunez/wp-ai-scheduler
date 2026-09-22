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
	var AIPS = window.AIPS;

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
			$(document).on('click', '.aips-add-profile-btn, #aips-add-profile-btn', this.handleAddProfile.bind(this));
			$(document).on('click', '.aips-edit-profile-btn', this.handleEditProfile.bind(this));
			$(document).on('click', '.aips-clone-profile-btn', this.handleCloneProfile.bind(this));
			$(document).on('click', '.aips-set-default-btn', this.handleSetDefaultProfile.bind(this));
			$(document).on('click', '.aips-delete-profile-btn', this.handleDeleteProfile.bind(this));
			$(document).on('click', '#aips-prompt-profile-modal .aips-modal-close', this.handleModalClose.bind(this));
			$(document).on('keydown', this.handleKeydown.bind(this));
			$(document).on('click', '.aips-stage-tabs-list li', this.handleStageTabClick.bind(this));
			$(document).on('click', '.aips-placeholder-chip', this.handlePlaceholderChipClick.bind(this));
			$(document).on('click', '.aips-reset-stage-btn', this.handleResetStageClick.bind(this));
			$(document).on('submit', '#aips-prompt-profile-form', this.handleFormSubmit.bind(this));
			$(document).on('input change', '#aips-prompt-profile-form input, #aips-prompt-profile-form textarea', this.handleFormInputChange.bind(this));
			$(document).on('click', '#aips-toggle-sandbox', this.handleToggleSandbox.bind(this));
			$(document).on('click', '#aips-refresh-preview-btn', this.handleRefreshPreviewClick.bind(this));
			$(document).on('click', '.aips-filter-btn', this.handleFilterClick.bind(this));
			$(document).on('input', '#aips-profile-search', this.handleSearchInput.bind(this));
			$(document).on('click', '#aips-profile-search-clear, #aips-clear-search-btn', this.handleSearchClearClick.bind(this));
		},

		/**
		 * Handle click on Add Profile button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleAddProfile: function (e) {
			e.preventDefault();
			this.openCreateModal();
		},

		/**
		 * Handle click on Edit Profile button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleEditProfile: function (e) {
			e.preventDefault();
			var profileId = $(e.currentTarget).data('id');
			this.openEditModal(profileId);
		},

		/**
		 * Handle click on Clone Profile button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleCloneProfile: function (e) {
			e.preventDefault();
			var profileId = $(e.currentTarget).data('id');
			this.cloneProfile(profileId);
		},

		/**
		 * Handle click on Set Default button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleSetDefaultProfile: function (e) {
			e.preventDefault();
			var profileId = $(e.currentTarget).data('id');
			this.setDefaultProfile(profileId);
		},

		/**
		 * Handle click on Delete Profile button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleDeleteProfile: function (e) {
			e.preventDefault();
			var profileId = $(e.currentTarget).data('id');
			this.deleteProfile(profileId);
		},

		/**
		 * Handle click on modal close button.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleModalClose: function (e) {
			e.preventDefault();
			this.closeModal();
		},

		/**
		 * Handle keydown events on document (e.g. Escape key to close modal).
		 *
		 * @param {Event} e Keyboard event.
		 * @return {void}
		 */
		handleKeydown: function (e) {
			if ((e.key === 'Escape' || e.keyCode === 27) && $('#aips-prompt-profile-modal').is(':visible')) {
				this.closeModal();
			}
		},

		/**
		 * Handle stage tab navigation click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleStageTabClick: function (e) {
			var stage = $(e.currentTarget).data('stage');
			this.switchStage(stage);
		},

		/**
		 * Handle click on placeholder chip to insert tag.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handlePlaceholderChipClick: function (e) {
			e.preventDefault();
			var tag = $(e.currentTarget).data('tag');
			this.insertTagAtCursor(tag);
		},

		/**
		 * Handle reset stage to core default prompt.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleResetStageClick: function (e) {
			e.preventDefault();
			var stage = $(e.currentTarget).data('stage');
			this.resetStagePrompt(stage);
		},

		/**
		 * Handle profile form submission.
		 *
		 * @param {Event} e Submit event.
		 * @return {void}
		 */
		handleFormSubmit: function (e) {
			e.preventDefault();
			this.saveProfile();
		},

		/**
		 * Handle input or change on profile form fields.
		 *
		 * @param {Event} e Input or change event.
		 * @return {void}
		 */
		handleFormInputChange: function (e) {
			this.setDirty(true);
			this.updateCustomizedIndicators();
			if ($('#aips-sandbox-body').is(':visible')) {
				this.debouncePreview();
			}
		},

		/**
		 * Handle sandbox accordion toggle.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleToggleSandbox: function (e) {
			var self = this;
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
		},

		/**
		 * Handle refresh preview button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleRefreshPreviewClick: function (e) {
			e.preventDefault();
			this.fetchLivePreview();
		},

		/**
		 * Handle filter button click in the list view.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleFilterClick: function (e) {
			$('.aips-filter-btn').removeClass('is-active');
			$(e.currentTarget).addClass('is-active');
			this.applyFilters();
		},

		/**
		 * Handle profile search input changes.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		handleSearchInput: function (e) {
			var query = $(e.currentTarget).val().trim();
			$('#aips-profile-search-clear').toggle(query.length > 0);
			this.applyFilters();
		},

		/**
		 * Handle search clear button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		handleSearchClearClick: function (e) {
			e.preventDefault();
			$('#aips-profile-search').val('');
			$('#aips-profile-search-clear').hide();
			this.applyFilters();
		},

		/**
		 * Retrieve the admin AJAX URL with multi-source fallback.
		 *
		 * @return {string}
		 */
		getAjaxUrl: function () {
			return (window.aipsPromptProfilesL10n && window.aipsPromptProfilesL10n.ajaxUrl) ||
				(window.aipsAjax && window.aipsAjax.ajaxUrl) ||
				window.ajaxurl ||
				'';
		},

		/**
		 * Fetch core codebase fallback prompts via AJAX for client-side resets.
		 *
		 * @return {void}
		 */
		loadCoreFallbacks: function () {
			var self = this;
			$.ajax({
				url: self.getAjaxUrl(),
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
			$('#aips-prompt-profile-modal').attr('aria-hidden', 'false').fadeIn(200, function () {
				$('#aips_profile_name').trigger('focus');
			});
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
				url: self.getAjaxUrl(),
				type: 'POST',
				data: {
					action: 'aips_get_prompt_profile',
					id: profileId,
					nonce: aipsPromptProfilesL10n.nonce
				},
				beforeSend: function () {
					$('#aips-prompt-profile-modal-title').text(aipsPromptProfilesL10n.editProfile + ' (Loading...)');
					$('#aips-prompt-profile-modal').attr('aria-hidden', 'false').fadeIn(200);
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

						var isBuiltin = p.is_builtin === 1 || p.is_builtin === '1' || p.is_builtin === true;
						var isDefault = p.is_default === 1 || p.is_default === '1' || p.is_default === true;
						var $badge = $('#aips-modal-profile-badge');
						if (isBuiltin) {
							$badge.text(aipsPromptProfilesL10n.builtinBadge).removeClass('aips-badge-neutral aips-badge-success').addClass('aips-badge-info').show();
						} else if (isDefault) {
							$badge.text(aipsPromptProfilesL10n.defaultBadge).removeClass('aips-badge-neutral aips-badge-info').addClass('aips-badge-success').show();
						} else {
							$badge.text(aipsPromptProfilesL10n.customBadge).removeClass('aips-badge-info aips-badge-success').addClass('aips-badge-neutral').show();
						}

						self.switchStage(self.activeStage || 'title_prompt');
						self.setDirty(false);
						self.updateCustomizedIndicators();
						$('#aips_profile_name').trigger('focus');
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
			$('#aips-prompt-profile-modal').attr('aria-hidden', 'true').fadeOut(200);
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
				url: self.getAjaxUrl(),
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
				url: self.getAjaxUrl(),
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
				url: self.getAjaxUrl(),
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
				url: self.getAjaxUrl(),
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
				url: self.getAjaxUrl(),
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
						var errMsg = (response.data && response.data.message) || aipsPromptProfilesL10n.errorPreview;
						$('#aips-preview-output-box').text(errMsg);
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

			var totalCount = $('#aips-prompt-profiles-table tbody tr').length;
			if (activeFilter !== 'all' || query.length > 0) {
				$('.aips-table-footer-count').text(visibleCount + ' / ' + totalCount + ' ' + (totalCount === 1 ? 'profile' : 'profiles'));
			} else {
				$('.aips-table-footer-count').text(totalCount + ' ' + (totalCount === 1 ? 'profile' : 'profiles'));
			}
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
