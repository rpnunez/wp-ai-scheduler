/**
 * Workflows Admin Page JavaScript
 *
 * Handles workflow-related UI interactions: optimistic status badge updates
 * with AJAX success/error recovery, and delete confirmation for the Workflows
 * admin page using the shared AIPS.Utilities.confirm() modal.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var workflowsModule = {
		l10n: {},

		/**
		 * Bootstrap the Workflows module.
		 *
		 * Reads localised strings and exposes updateWorkflowStatus on the global
		 * AIPS namespace for backward-compat callers (e.g. admin-ai-edit.js).
		 */
		init: function() {
			this.l10n = typeof aipsWorkflowsL10n !== 'undefined' ? aipsWorkflowsL10n : {};
			this.bindEvents();
			window.AIPS.updateWorkflowStatus = this.updateWorkflowStatus.bind(this);
		},

		/**
		 * Bind event listeners used across workflow-aware pages.
		 *
		 * Uses delegated event listeners on document so handlers work even if
		 * the table is re-rendered dynamically.
		 */
		bindEvents: function() {
			$(document).on('submit', '.aips-delete-workflow-form', this.confirmDeleteWorkflow.bind(this));
		},

		/**
		 * Apply a workflow status label to a table row.
		 *
		 * @param {jQuery} $row
		 * @param {string} status
		 */
		applyWorkflowStatusToRow: function($row, status) {
			if (!$row || !$row.length || !status) {
				return;
			}

			var statusLabels = this.l10n.workflowStatusLabels || {};
			var label = statusLabels[status] || status;
			var $badge = $row.find('.column-workflow .aips-badge');

			if ($badge.length && label) {
				$badge.text(label);
			}

			$row.attr('data-workflow-status', status);
		},

		/**
		 * Update the workflow status via AJAX and reconcile UI state after the response.
		 *
		 * The caller is responsible for applying the optimistic badge update before
		 * calling this function. On failure the previous status badge is restored and
		 * an error toast is shown.
		 *
		 * @param {number} historyId
		 * @param {string} status
		 * @param {number} workflowId
		 * @param {Object} [options]
		 * @param {jQuery} [options.row]
		 * @param {string} [options.previousStatus]
		 * @returns {jQuery.Deferred}
		 */
		updateWorkflowStatus: function(historyId, status, workflowId, options) {
			var self = this;
			if (!historyId || !status || !this.l10n.ajaxUrl) {
				return $.Deferred().reject();
			}

			options = options || {};

			return $.ajax({
				url: this.l10n.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_update_workflow_status',
					history_id: historyId,
					workflow_status: status,
					workflow_id: workflowId || '',
					nonce: this.l10n.nonce
				}
			}).done(function(response) {
				if (!response.success) {
					self.handleWorkflowStatusFailure(options.row, options.previousStatus, response.data && response.data.message);
				}
			}).fail(function() {
				self.handleWorkflowStatusFailure(options.row, options.previousStatus, self.l10n.workflowUpdateError);
			});
		},

		/**
		 * Restore the previous status badge and show a toast when the AJAX update fails.
		 *
		 * @param {jQuery|null} $row
		 * @param {string|null} previousStatus
		 * @param {string=} message
		 */
		handleWorkflowStatusFailure: function($row, previousStatus, message) {
			if ($row && previousStatus) {
				this.applyWorkflowStatusToRow($row, previousStatus);
			}

			var toast = message || this.l10n.workflowUpdateError;
			if (toast && window.AIPS && AIPS.Utilities) {
				AIPS.Utilities.showToast(toast, 'error');
			}
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
			var l10n = this.l10n;

			AIPS.Utilities.confirm(
				l10n.confirmDelete,
				l10n.confirmDeleteHeading,
				[
					{
						label: l10n.cancelLabel,
						className: 'aips-btn aips-btn-secondary'
					},
					{
						label: l10n.deleteLabel,
						className: 'aips-btn aips-btn-danger',
						action: function() {
							$form[0].submit();
						}
					}
				]
			);
		}
	};

	Object.assign(AIPS, {
		Workflows: workflowsModule
	});

	$(document).ready(function() {
		AIPS.Workflows.init();
	});

})(jQuery);
