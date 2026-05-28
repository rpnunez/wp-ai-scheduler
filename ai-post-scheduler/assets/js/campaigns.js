/**
 * Campaigns Page JavaScript
 *
 * Handles AJAX interactions for the Campaigns admin page.
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

			if (!confirm(aipsCampaignsL10n.confirmDuplicate)) {
				return;
			}

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

			if (!confirm(aipsCampaignsL10n.confirmArchive)) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
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
		 * Handle restore campaign (unarchive).
		 *
		 * @param {Event} e Click event.
		 */
		handleRestoreCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			$.post(ajaxurl, {
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

			if (!confirm(aipsCampaignsL10n.confirmDelete)) {
				return;
			}

			$.post(ajaxurl, {
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
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.aips-admin-page').length && window.location.href.indexOf('aips-campaigns') > -1) {
			AIPS.Campaigns.init();
		}
	});

})(jQuery);
