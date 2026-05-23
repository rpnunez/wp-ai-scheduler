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
						AIPS.Utilities.showNotice(response.data.message || 'Failed to update campaign', 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice('Network error. Please try again.', 'error');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle duplicate campaign.
		 */
		handleDuplicateCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			if (!confirm('Duplicate this campaign? The copy will be created in a paused state.')) {
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
						AIPS.Utilities.showNotice(response.data.message || 'Failed to duplicate campaign', 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice('Network error. Please try again.', 'error');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle archive campaign.
		 */
		handleArchiveCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			if (!confirm('Archive this campaign? It will be hidden from the active campaigns list.')) {
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
						AIPS.Utilities.showNotice(response.data.message || 'Failed to archive campaign', 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					AIPS.Utilities.showNotice('Network error. Please try again.', 'error');
					$button.prop('disabled', false);
				}
			});
		},

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
					AIPS.Utilities.showNotice(response.data.message || 'Failed to restore campaign', 'error');
				}
			}).fail(function() {
				AIPS.Utilities.showNotice('Network error. Please try again.', 'error');
			});
		},

		handleDeleteCampaign: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var campaignId = $button.data('campaign-id');

			if (!confirm('Delete this campaign? This removes the campaign and its owned template/schedule rows.')) {
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
					AIPS.Utilities.showNotice(response.data.message || 'Failed to delete campaign', 'error');
				}
			}).fail(function() {
				AIPS.Utilities.showNotice('Network error. Please try again.', 'error');
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
