/**
 * Workflows Admin Page JavaScript
 *
 * Handles delete confirmation for the Workflows admin page using the
 * shared AIPS.Utilities.confirm() modal instead of the native browser confirm().
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
		 * Bind event listeners for the Workflows page.
		 *
		 * Uses delegated event listeners on document so handlers work
		 * even if the table is re-rendered dynamically.
		 */
		bindWorkflowEvents: function() {
			$(document).on('submit', '.aips-delete-workflow-form', this.confirmDeleteWorkflow.bind(this));
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
		}
	});

	$(document).ready(function() {
		AIPS.bindWorkflowEvents();
	});

})(jQuery);
