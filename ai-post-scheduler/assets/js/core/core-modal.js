/**
 * AIPS Core Modal — shared lifecycle helper for the plugin's static CRUD modals.
 *
 * The plugin's admin pages each define a static `.aips-modal` element directly
 * in their PHP template (per the documented structure: `.aips-modal` →
 * `.aips-modal-content` → `.aips-modal-header`/`.aips-modal-body`/
 * `.aips-modal-footer`). `AIPS.Core.Modal` standardizes the mechanical
 * open/close/populate/reset lifecycle around that existing static node —
 * it does not build modal markup itself. For a modal built entirely from a
 * JS-side field spec (no pre-existing template markup), use
 * `AIPS.Utilities.showModal()` instead; that solves a different problem.
 *
 * Usage:
 *   AIPS.Core.Modal.open('#aips-thing-modal', {
 *       title: aipsThingL10n.addNewThing,
 *       focusSelector: '#aips-thing-name',
 *   });
 *
 *   AIPS.Core.Modal.populateFields('#aips-thing-modal', {
 *       '#aips-thing-id': id,
 *       '#aips-thing-name': $row.data('name') || '',
 *       '#aips-thing-is-active': parseInt($row.data('active'), 10) === 1,
 *   });
 *
 *   AIPS.Core.Modal.close('#aips-thing-modal');
 *
 * @since 2.7.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * Normalize a modal reference (selector string or jQuery object) to a
	 * jQuery object.
	 *
	 * @param {string|jQuery} modal
	 * @return {jQuery}
	 */
	function resolve(modal) {
		return modal instanceof $ ? modal : $(modal);
	}

	/**
	 * Set form field values on a modal from a `{selector: value}` map.
	 *
	 * Checkbox fields are set via `.prop('checked', !!value)`; everything
	 * else via `.val(value)` (`null`/`undefined` become an empty string).
	 * Selectors are resolved relative to the modal element. This single
	 * function backs both `resetFields()` and `populateFields()` — "reset"
	 * is just "populate with default values" from the caller's perspective.
	 *
	 * @param {jQuery} $modal
	 * @param {Object} values
	 */
	function setFields($modal, values) {
		values = values || {};

		$.each(values, function (selector, value) {
			var $field = $modal.find(selector);

			if (!$field.length) {
				return;
			}

			if ($field.is(':checkbox')) {
				$field.prop('checked', !!value);
			} else {
				$field.val(value === undefined || value === null ? '' : value);
			}
		});
	}

	/**
	 * @namespace AIPS.Core.Modal
	 */
	AIPS.Core.Modal = {

		/**
		 * Show a modal, optionally setting its title and focusing a field.
		 *
		 * @param {string|jQuery} modal                Modal selector or jQuery object.
		 * @param {Object}        [options]
		 * @param {string}        [options.title]       Text for `.aips-modal-title`.
		 * @param {string}        [options.focusSelector] Selector (relative to the
		 *                                               modal) of a field to focus.
		 * @return {void}
		 */
		open: function (modal, options) {
			options = options || {};

			var $modal = resolve(modal);

			if (options.title) {
				$modal.find('.aips-modal-title').text(options.title);
			}

			$modal.show();

			if (options.focusSelector) {
				$modal.find(options.focusSelector).trigger('focus');
			}
		},

		/**
		 * Hide a modal.
		 *
		 * @param {string|jQuery} modal Modal selector or jQuery object.
		 * @return {void}
		 */
		close: function (modal) {
			resolve(modal).hide();
		},

		/**
		 * Reset a modal's fields to default values.
		 *
		 * @param {string|jQuery} modal  Modal selector or jQuery object.
		 * @param {Object}        values `{selector: defaultValue}` map.
		 * @return {void}
		 */
		resetFields: function (modal, values) {
			setFields(resolve(modal), values);
		},

		/**
		 * Populate a modal's fields from existing data (a row's `data-*`
		 * attributes, an AJAX response, etc.). Semantically identical to
		 * `resetFields()` — the caller supplies real values instead of
		 * defaults.
		 *
		 * @param {string|jQuery} modal  Modal selector or jQuery object.
		 * @param {Object}        values `{selector: value}` map.
		 * @return {void}
		 */
		populateFields: function (modal, values) {
			setFields(resolve(modal), values);
		},

		/**
		 * Show a standard {Cancel, destructive-confirm} confirmation dialog
		 * via the existing `AIPS.Utilities.confirm()`, for the delete-style
		 * flows repeated across the codebase.
		 *
		 * @param {Object}   options
		 * @param {string}   options.message       Confirmation body text.
		 * @param {string}   [options.heading]      Dialog heading. Default `'Delete'`.
		 * @param {string}   [options.cancelLabel]  Cancel button label. Default `'Cancel'`.
		 * @param {string}   [options.confirmLabel] Confirm button label. Default `'Delete'`.
		 * @param {Function} options.onConfirm       Called when the destructive button is clicked.
		 * @return {void}
		 */
		confirmDelete: function (options) {
			options = options || {};

			AIPS.Utilities.confirm(
				options.message,
				options.heading || 'Delete',
				[
					{ label: options.cancelLabel || 'Cancel', className: 'aips-btn aips-btn-primary' },
					{
						label: options.confirmLabel || 'Delete',
						className: 'aips-btn aips-btn-danger-solid',
						action: options.onConfirm
					}
				]
			);
		}
	};

})(jQuery);
