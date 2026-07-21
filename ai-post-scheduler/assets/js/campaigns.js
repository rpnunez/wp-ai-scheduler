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
			$(document).on('click', '.aips-campaign-run-now', this.handleRunNow.bind(this));
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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_toggle_campaign',
				data: { campaign_id: campaignId, is_active: newStatus },
				$button: $button,
				toastOnError: false,
				errorFallback: aipsCampaignsL10n.errorToggle,
				onSuccess: function(data) {
					AIPS.Utilities.showNotice('success', data.message);
					location.reload();
				},
				onError: function(message) {
					AIPS.Utilities.showNotice('error', message);
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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_duplicate_campaign',
				data: { campaign_id: campaignId },
				$button: $button,
				toastOnError: false,
				errorFallback: aipsCampaignsL10n.errorDuplicate,
				onSuccess: function(data) {
					AIPS.Utilities.showNotice('success', data.message);
					location.reload();
				},
				onError: function(message) {
					AIPS.Utilities.showNotice('error', message);
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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_archive_campaign',
				data: { campaign_id: campaignId },
				$button: $button,
				toastOnError: false,
				errorFallback: aipsCampaignsL10n.errorArchive,
				onSuccess: function(data) {
					AIPS.Utilities.showNotice('success', data.message);
					location.reload();
				},
				onError: function(message) {
					AIPS.Utilities.showNotice('error', message);
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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_restore_campaign',
				data: { campaign_id: campaignId },
				toastOnError: false,
				errorFallback: aipsCampaignsL10n.errorRestore,
				onSuccess: function() {
					location.reload();
				},
				onError: function(message) {
					AIPS.Utilities.showNotice('error', message);
				}
			});
		},

		/**
		 * Handle immediate generation for the primary campaign schedule.
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
				AIPS.Utilities.showNotice('error', errorMessage);
				return;
			}

			if (!confirm(confirmMessage)) {
				return;
			}

			$button.addClass('is-busy');

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_run_now',
				data: { schedule_id: scheduleId },
				$button: $button,
				toastOnError: false,
				errorFallback: errorMessage,
				onSuccess: function(data) {
					AIPS.Utilities.showNotice('success', data.message || successMessage);
					location.reload();
				},
				onError: function(message) {
					AIPS.Utilities.showNotice('error', message);
				}
			}).always(function() {
				$button.removeClass('is-busy');
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

			AIPS.Core.Modal.confirmDelete({
				message: aipsCampaignsL10n.confirmDelete,
				onConfirm: function() {
					AIPS.Core.Http.ajaxRequest({
						action: 'aips_delete_campaign',
						data: { campaign_id: campaignId },
						toastOnError: false,
						errorFallback: aipsCampaignsL10n.errorDelete,
						onSuccess: function() {
							location.reload();
						},
						onError: function(message) {
							AIPS.Utilities.showNotice('error', message);
						}
					});
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.aips-admin-page').length && (window.location.href.indexOf('aips-campaigns') > -1 || window.location.href.indexOf('aips-campaign-detail') > -1)) {
			AIPS.Campaigns.init();
		}
	});

})(jQuery);
