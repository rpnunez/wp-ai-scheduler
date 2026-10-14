/**
 * Post Insights Table Interactivity (edit.php).
 *
 * Handles flyout popover cards, on-demand reindexing, and pillar toggles
 * on the native WordPress Posts list table.
 *
 * @package AI_Post_Scheduler
 * @since 3.6.7
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * Post Insights Table Controller.
	 */
	AIPS.PostInsightsTable = {

		/**
		 * Active popover element.
		 *
		 * @type {jQuery|null}
		 */
		activePopover: null,

		/**
		 * Initialize table listeners.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind DOM event handlers.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			var self = this;

			// Toggle popover on trigger button click
			$(document).on('click', '.aips-popover-trigger', this.onTriggerClick.bind(this));

			// Close popover on close button click
			$(document).on('click', '.aips-popover-close', this.onCloseClick.bind(this));

			// Close popover when clicking outside
			$(document).on('click', function (e) {
				if (!$(e.target).closest('.aips-insights-col-cell').length) {
					self.closeActivePopover();
				}
			});

			// Re-index single post button
			$(document).on('click', '.aips-reindex-post-btn', this.onReindexClick.bind(this));

			// Toggle pillar button
			$(document).on('click', '.aips-toggle-pillar-btn', this.onTogglePillarClick.bind(this));
		},

		/**
		 * Handle popover trigger button click.
		 *
		 * @param {jQuery.Event} e
		 * @return {void}
		 */
		onTriggerClick: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $btn = $(e.currentTarget);
			var $cell = $btn.closest('.aips-insights-col-cell');
			var $popover = $cell.find('.aips-insights-popover');

			if (this.activePopover && this.activePopover[0] !== $popover[0]) {
				this.closeActivePopover();
			}

			if ($popover.hasClass('is-visible')) {
				$popover.removeClass('is-visible');
				this.activePopover = null;
			} else {
				$popover.addClass('is-visible');
				this.activePopover = $popover;
			}
		},

		/**
		 * Handle popover close button click.
		 *
		 * @param {jQuery.Event} e
		 * @return {void}
		 */
		onCloseClick: function (e) {
			e.preventDefault();
			e.stopPropagation();
			this.closeActivePopover();
		},

		/**
		 * Close currently open popover.
		 *
		 * @return {void}
		 */
		closeActivePopover: function () {
			if (this.activePopover) {
				this.activePopover.removeClass('is-visible');
				this.activePopover = null;
			}
		},

		/**
		 * Trigger on-demand re-indexing for a post.
		 *
		 * @param {jQuery.Event} e
		 * @return {void}
		 */
		onReindexClick: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var postId = $btn.data('post-id');
			var originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> Indexing…');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_reindex_single_post',
					nonce: aipsPostInsightsL10n.nonce,
					post_id: postId
				},
				success: function (res) {
					$btn.prop('disabled', false).html(originalText);
					if (res.success) {
						if (AIPS.Utilities && AIPS.Utilities.showNotice) {
							AIPS.Utilities.showNotice(res.data.message || 'Post re-indexed successfully.', 'success');
						}
						// Refresh row or reload if in table
						window.location.reload();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Re-indexing failed.');
					}
				},
				error: function () {
					$btn.prop('disabled', false).html(originalText);
					alert('Server error while re-indexing post.');
				}
			});
		},

		/**
		 * Toggle pillar post status for a cluster.
		 *
		 * @param {jQuery.Event} e
		 * @return {void}
		 */
		onTogglePillarClick: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var postId = $btn.data('post-id');
			var clusterId = $btn.data('cluster-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_toggle_single_pillar',
					nonce: aipsPostInsightsL10n.nonce,
					post_id: postId,
					cluster_id: clusterId
				},
				success: function (res) {
					$btn.prop('disabled', false);
					if (res.success) {
						window.location.reload();
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Failed to update pillar.');
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					alert('Server error while updating pillar post.');
				}
			});
		}
	};

	$(document).ready(function () {
		AIPS.PostInsightsTable.init();
	});

})(jQuery);
