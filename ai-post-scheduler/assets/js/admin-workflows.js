/**
 * Workflows Admin Page JavaScript
 *
 * Handles delete confirmation for the Workflows admin page using the
 * shared AIPS.Utilities.confirm() modal instead of the native browser confirm().
 * Also handles workflow status updates triggered by AI Edit button clicks,
 * consolidating all AI-Edit-related workflow functionality in one place.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/**
		 * Bind event listeners for Workflow-related actions.
		 *
		 * Uses delegated event listeners on document so handlers work
		 * even if the table is re-rendered dynamically.
		 */
		bindWorkflowEvents: function() {
			$(document).on('submit', '.aips-delete-workflow-form', this.confirmDeleteWorkflow.bind(this));
			$(document).on('click', '.aips-ai-edit-btn', this.handleAiEditWorkflow.bind(this));
		},

		/**
		 * Intercept the delete workflow form submission and show a styled
		 * confirmation dialog via AIPS.Utilities.confirm().
		 *
		 * Prevents the default form submission immediately and only proceeds
		 * if the user confirms the action.
		 *
		 * @param {Event} e - The form submit event.
		 */
		confirmDeleteWorkflow: function(e) {
			e.preventDefault();

			var $form = $(e.currentTarget);

			AIPS.Utilities.confirm(
				aipsWorkflowsL10n.confirmDelete,
				aipsWorkflowsL10n.confirmDeleteHeading,
				[
					{
						label: aipsWorkflowsL10n.cancelLabel,
						className: 'aips-btn aips-btn-secondary'
					},
					{
						label: aipsWorkflowsL10n.deleteLabel,
						className: 'aips-btn aips-btn-danger',
						action: function() {
							$form[0].submit();
						}
					}
				]
			);
		},

		/**
		 * Handle AI Edit button clicks by updating the workflow status to "needs review".
		 *
		 * Optimistically updates the row's status badge and makes an AJAX request to
		 * persist the change. Reverts the badge and shows an error toast on failure.
		 *
		 * @param {Event} e - The click event.
		 */
		handleAiEditWorkflow: function(e) {
			var l10n = typeof aipsWorkflowsL10n !== 'undefined' ? aipsWorkflowsL10n : {};
			var ajaxUrl = l10n.ajaxUrl;
			var nonce = l10n.nonce;
			var needsReviewStatus = l10n.workflowStatusNeedsReview;

			if (!ajaxUrl || !nonce || !needsReviewStatus) {
				return;
			}

			var $button = $(e.currentTarget);
			var historyId = $button.data('history-id');

			if (!historyId) {
				return;
			}

			var $row = $button.closest('tr');
			var previousStatus = $row.attr('data-workflow-status');
			var statusLabels = l10n.workflowStatusLabels || {};
			var label = statusLabels[needsReviewStatus] || needsReviewStatus;
			var $badge = $row.find('.column-workflow .aips-badge');

			// Optimistically update the badge
			if ($badge.length && label) {
				$badge.text(label);
			}
			$row.attr('data-workflow-status', needsReviewStatus);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_update_workflow_status',
					history_id: historyId,
					workflow_status: needsReviewStatus,
					workflow_id: '',
					nonce: nonce
				}
			}).done(function(response) {
				if (!response.success) {
					AIPS.revertWorkflowStatusBadge($row, $badge, previousStatus, statusLabels);
					var msg = (response.data && response.data.message) || l10n.workflowUpdateError;
					if (msg && AIPS.Utilities) {
						AIPS.Utilities.showToast(msg, 'error');
					}
				}
			}).fail(function() {
				AIPS.revertWorkflowStatusBadge($row, $badge, previousStatus, statusLabels);
				if (l10n.workflowUpdateError && AIPS.Utilities) {
					AIPS.Utilities.showToast(l10n.workflowUpdateError, 'error');
				}
			});
		},

		/**
		 * Restore a row's workflow status badge to a previous value.
		 *
		 * @param {jQuery} $row
		 * @param {jQuery} $badge
		 * @param {string} previousStatus
		 * @param {Object} statusLabels
		 */
		revertWorkflowStatusBadge: function($row, $badge, previousStatus, statusLabels) {
			if (previousStatus) {
				var prevLabel = statusLabels[previousStatus] || previousStatus;
				if ($badge.length && prevLabel) {
					$badge.text(prevLabel);
				}
				$row.attr('data-workflow-status', previousStatus);
			}
		}
	});

	$(document).ready(function() {
		AIPS.bindWorkflowEvents();
	});

})(jQuery);
