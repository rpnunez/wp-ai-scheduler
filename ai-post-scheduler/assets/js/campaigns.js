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
					nonce: AIPS.nonce,
					schedule_id: campaignId,
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
					nonce: AIPS.nonce,
					schedule_id: campaignId
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
					nonce: AIPS.nonce,
					schedule_id: campaignId
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showNotice(response.data.message, 'success');
						$button.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
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
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.aips-admin-page').length && window.location.href.indexOf('aips-campaigns') > -1) {
			AIPS.Campaigns.init();
		}
	});

})(jQuery);
