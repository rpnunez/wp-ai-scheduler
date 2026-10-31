/**
 * Campaigns Page JavaScript
 *
 * Handles AJAX interactions for the Campaigns admin page and Campaign Detail workspace.
 *
 * @package AI_Post_Scheduler
 */

(function($) {
	'use strict';

	if (typeof AIPS === 'undefined') {
		console.error('AIPS object not found');
		return;
	}

	AIPS.Campaigns = {

		/**
		 * Initialize campaigns page.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-toggle-campaign', this.handleToggleCampaign.bind(this));
			$(document).on('click', '.aips-duplicate-campaign', this.handleDuplicateCampaign.bind(this));
			$(document).on('click', '.aips-archive-campaign', this.handleArchiveCampaign.bind(this));
			$(document).on('click', '.aips-restore-campaign', this.handleRestoreCampaign.bind(this));
			$(document).on('click', '.aips-delete-campaign', this.handleDeleteCampaign.bind(this));
			$(document).on('click', '.aips-campaign-run-now', this.handleRunNow.bind(this));

			// Quick Create modal handlers
			$(document).on('click', '#aips-open-quick-create-modal, #aips-open-quick-create-modal-empty', this.handleOpenQuickCreateModal.bind(this));
			$(document).on('submit', '#aips-quick-create-campaign-form', this.handleSubmitQuickCreate.bind(this));

			// Template linking handlers
			$(document).on('click', '#aips-open-link-template-modal, #aips-open-link-template-modal-empty', this.handleOpenLinkTemplateModal.bind(this));
			$(document).on('submit', '#aips-link-template-form', this.handleSubmitLinkTemplate.bind(this));
			$(document).on('click', '.aips-unlink-template-btn', this.handleUnlinkTemplate.bind(this));

			// Template overrides handlers
			$(document).on('click', '.aips-edit-overrides-btn', this.handleOpenOverridesModal.bind(this));
			$(document).on('submit', '#aips-template-overrides-form', this.handleSubmitOverrides.bind(this));

			// Generic modal close
			$(document).on('click', '.aips-modal-close, .aips-modal-cancel, .aips-modal-backdrop', this.handleCloseModals.bind(this));
		},

		/**
		 * Open Quick Create modal.
		 */
		handleOpenQuickCreateModal: function(e) {
			e.preventDefault();
			$('#aips-quick-create-campaign-modal').removeAttr('hidden').fadeIn(150);
			$('#aips-quick-campaign-name').focus();
		},

		/**
		 * Open Link Template modal.
		 */
		handleOpenLinkTemplateModal: function(e) {
			e.preventDefault();
			$('#aips-link-template-modal').removeAttr('hidden').fadeIn(150);
		},

		/**
		 * Close open modals.
		 */
		handleCloseModals: function(e) {
			if ($(e.target).closest('.aips-modal-content').length && !$(e.target).is('.aips-modal-close, .aips-modal-cancel')) {
				return;
			}
			$('.aips-modal').fadeOut(150, function() {
				$(this).attr('hidden', true);
			});
		},

		/**
		 * Submit quick campaign creation.
		 */
		handleSubmitQuickCreate: function(e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $btn = $form.find('#aips-quick-create-submit-btn');
			var name = $.trim($('#aips-quick-campaign-name').val());
			var goal = $.trim($('#aips-quick-campaign-goal').val());

			if (!name) {
				return;
			}

			$btn.prop('disabled', true);
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_quick_create_campaign',
					nonce: aipsAjax.nonce,
					name: name,
					content_goal: goal
				},
				success: function(response) {
					var redirectUrl = (response.data && (response.data.redirect_url || response.data.detail_url)) || null;
					if (response.success && redirectUrl) {
						window.location.href = redirectUrl;
					} else {
						AIPS.Utilities.showNotice(response.data?.message || aipsCampaignsL10n.errorToggle || 'Failed to create campaign.', 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork || 'Network error while creating campaign.', 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Submit linking an existing template to the campaign.
		 */
		handleSubmitLinkTemplate: function(e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $btn = $form.find('#aips-link-template-submit-btn');
			var campaignId = $form.find('input[name="campaign_id"]').val();
			var templateId = $form.find('select[name="template_id"]').val();
			var structId = $form.find('select[name="override_structure_id"]').val();
			var postStatus = $form.find('select[name="override_post_status"]').val();

			if (!templateId) {
				AIPS.Utilities.showNotice((typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.selectTemplateRequired) || 'Please select a template to link.', 'warning');
				return;
			}

			var overrides = {};
			if (structId) {
				overrides.article_structure_id = parseInt(structId, 10);
			}
			if (postStatus) {
				overrides.post_status = postStatus;
			}

			$btn.prop('disabled', true);
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_link_campaign_template',
					nonce: aipsAjax.nonce,
					campaign_id: campaignId,
					template_id: templateId,
					overrides: overrides
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						AIPS.Utilities.showNotice(response.data?.message || (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorLinkTemplate) || 'Failed to link template.', 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice((typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorNetwork) || 'Network error while linking template.', 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle unlinking a template from the campaign.
		 */
		handleUnlinkTemplate: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var campaignId = $('input[name="campaign_id"]').val() || (new URLSearchParams(window.location.search)).get('campaign_id');
			var templateId = $btn.data('template-id');
			var templateName = $btn.data('template-name') || 'this template';

			var confirmMsg = (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.confirmUnlinkTemplate) || 'Unlink this template from the campaign? The template itself will not be deleted.';

			AIPS.Utilities.confirm(
				confirmMsg,
				(typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.unlinkTemplateTitle) || 'Unlink Template',
				[
					{ label: (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.cancelAction) || 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.unlinkAction) || 'Unlink',
						className: 'aips-btn aips-btn-danger',
						action: function() {
							$btn.prop('disabled', true);
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_unlink_campaign_template',
									nonce: aipsAjax.nonce,
									campaign_id: campaignId,
									template_id: templateId
								},
								success: function(response) {
									if (response.success) {
										location.reload();
									} else {
										AIPS.Utilities.showNotice(response.data?.message || (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorUnlinkTemplate) || 'Failed to unlink template.', 'error');
										$btn.prop('disabled', false);
									}
								},
								error: function() {
									AIPS.Utilities.showNotice((typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorNetwork) || 'Network error while unlinking template.', 'error');
									$btn.prop('disabled', false);
								}
							});
						}
					}
				]
			);
		},

		/**
		 * Open Template Overrides Modal.
		 */
		handleOpenOverridesModal: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var templateId = $btn.data('template-id');
			var templateName = $btn.data('template-name');
			var overrides = $btn.data('overrides') || {};

			if (typeof overrides === 'string') {
				try {
					overrides = JSON.parse(overrides);
				} catch (err) {
					overrides = {};
				}
			}

			$('#aips-override-template-id').val(templateId);
			$('#aips-override-template-name-label').text(templateName);
			$('#aips-override-structure-id').val(overrides.article_structure_id || '');
			$('#aips-override-post-status').val(overrides.post_status || '');
			$('#aips-override-voice-id').val(overrides.voice_id || '');
			$('#aips-override-author-id').val(overrides.post_author || overrides.author_id || '');
			$('#aips-override-categories').val(overrides.post_category || overrides.categories || '');
			$('#aips-override-tags').val(overrides.post_tags || overrides.tags || '');

			$('#aips-template-overrides-modal').removeAttr('hidden').fadeIn(150);
		},

		/**
		 * Submit template overrides form.
		 */
		handleSubmitOverrides: function(e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $btn = $form.find('#aips-save-overrides-submit-btn');
			var campaignId = $form.find('input[name="campaign_id"]').val();
			var templateId = $form.find('#aips-override-template-id').val();

			var overrides = {
				article_structure_id: $('#aips-override-structure-id').val() || null,
				post_status: $('#aips-override-post-status').val() || null,
				voice_id: $('#aips-override-voice-id').val() || null,
				author_id: $('#aips-override-author-id').val() || null,
				categories: $('#aips-override-categories').val() || null,
				tags: $('#aips-override-tags').val() || null
			};

			$btn.prop('disabled', true);
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_update_campaign_template_overrides',
					nonce: aipsAjax.nonce,
					campaign_id: campaignId,
					template_id: templateId,
					overrides: overrides
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						AIPS.Utilities.showNotice(response.data?.message || (typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorUpdateOverrides) || 'Failed to update overrides.', 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice((typeof aipsCampaignsL10n !== 'undefined' && aipsCampaignsL10n.errorNetwork) || 'Network error while updating overrides.', 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle toggle campaign (pause/resume).
		 *
		 * @param {Event} e Click event.
		 */
		handleToggleCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');
			var isActive = $button.data('is-active');
			var newStatus = isActive ? 0 : 1;

			$button.prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_toggle_campaign',
					nonce: aipsAjax.nonce,
					campaign_id: campaignId,
					is_active: newStatus
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showNotice(response.data.message, 'success');
						location.reload();
					} else {
						AIPS.Utilities.showNotice(response.data.message || aipsCampaignsL10n.errorToggle, 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle duplicate campaign.
		 *
		 * @param {Event} e Click event.
		 */
		handleDuplicateCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			AIPS.Utilities.confirm(
				aipsCampaignsL10n.confirmDuplicate || 'Duplicate this campaign?',
				'Duplicate Campaign',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: 'Duplicate',
						className: 'aips-btn aips-btn-primary',
						action: function () {
							$button.prop('disabled', true);

							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_duplicate_campaign',
									nonce: aipsAjax.nonce,
									campaign_id: campaignId
								},
								success: function(response) {
									if (response.success) {
										AIPS.Utilities.showNotice(response.data.message, 'success');
										location.reload();
									} else {
										AIPS.Utilities.showNotice(response.data.message || aipsCampaignsL10n.errorDuplicate, 'error');
										$button.prop('disabled', false);
									}
								},
								error: function() {
									AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
									$button.prop('disabled', false);
								}
							});
						}
					}
				]
			);
		},

		/**
		 * Handle archive campaign.
		 *
		 * @param {Event} e Click event.
		 */
		handleArchiveCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			AIPS.Utilities.confirm(
				aipsCampaignsL10n.confirmArchive || 'Archive this campaign?',
				'Archive Campaign',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: 'Archive',
						className: 'aips-btn aips-btn-secondary',
						action: function () {
							var req = $.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_archive_campaign',
									nonce: aipsAjax.nonce,
									campaign_id: campaignId
								},
								success: function(response) {
									if (response.success) {
										AIPS.Utilities.showNotice(response.data.message, 'success');
										location.reload();
									} else {
										AIPS.Utilities.showNotice(response.data.message || aipsCampaignsL10n.errorArchive, 'error');
									}
								},
								error: function() {
									AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
								}
							});

							AIPS.Utilities.withLock($button, req, { loadingText: aipsCampaignsL10n.archiving || 'Archiving...' });
						}
					}
				]
			);
		},

		/**
		 * Handle restore campaign (unarchive).
		 *
		 * @param {Event} e Click event.
		 */
		handleRestoreCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			var req = $.post(ajaxurl, {
				action: 'aips_restore_campaign',
				nonce: aipsAjax.nonce,
				campaign_id: campaignId
			}).done(function(response) {
				if (response.success) {
					location.reload();
				} else {
					AIPS.Utilities.showNotice(response.data.message || aipsCampaignsL10n.errorRestore, 'error');
				}
			}).fail(function() {
				AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
			});

			AIPS.Utilities.withLock($button, req, { loadingText: aipsCampaignsL10n.restoring || 'Restoring...' });
		},

		/**
		 * Handle immediate generation for a single campaign schedule.
		 *
		 * @param {Event} e Click event.
		 */
		handleRunNow: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var scheduleId = $button.data('schedule-id');

			var errorMessage = aipsCampaignsL10n.errorRunNow || 'Failed to run campaign schedule.';
			var confirmMessage = aipsCampaignsL10n.confirmRunNow || 'Run this campaign schedule now? This will immediately generate content.';
			var successMessage = aipsCampaignsL10n.runNowSuccess || 'Campaign schedule completed.';

			if (!scheduleId) {
				AIPS.Utilities.showNotice(errorMessage, 'error');
				return;
			}

			AIPS.Utilities.confirm(
				confirmMessage,
				'Run Now',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: 'Run Now',
						className: 'aips-btn aips-btn-primary',
						action: function () {
							var req = $.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'aips_run_now',
									nonce: aipsAjax.nonce,
									schedule_id: scheduleId
								},
								success: function(response) {
									if (response.success) {
										AIPS.Utilities.showNotice(response.data.message || successMessage, 'success');
										location.reload();
									} else {
										AIPS.Utilities.showNotice(response.data.message || errorMessage, 'error');
									}
								},
								error: function() {
									AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
								}
							});

							AIPS.Utilities.withLock($button, req, { loadingText: aipsCampaignsL10n.running || 'Running...' });
						}
					}
				]
			);
		},

		/**
		 * Handle delete campaign (permanent removal).
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			AIPS.Utilities.confirm(
				aipsCampaignsL10n.confirmDelete || 'Delete this campaign?',
				'Delete Campaign',
				[
					{ label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: 'Delete',
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							var req = $.post(ajaxurl, {
								action: 'aips_delete_campaign',
								nonce: aipsAjax.nonce,
								campaign_id: campaignId
							}).done(function(response) {
								if (response.success) {
									location.reload();
								} else {
									AIPS.Utilities.showNotice(response.data.message || aipsCampaignsL10n.errorDelete, 'error');
								}
							}).fail(function() {
								AIPS.Utilities.showNotice(aipsCampaignsL10n.errorNetwork, 'error');
							});

							AIPS.Utilities.withLock($button, req, { loadingText: aipsCampaignsL10n.deleting || 'Deleting...' });
						}
					}
				]
			);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.aips-admin-page').length || $('.aips-campaign-detail-workspace').length || $('#aips-quick-create-campaign-modal').length) {
			AIPS.Campaigns.init();
		}
	});

})(jQuery);
