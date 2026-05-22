/**
 * Admin Embeddings Module
 *
 * Provides UI helper functions for queueing embedding computation jobs.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

(function($) {
	'use strict';

	// Initialize the AIPS global namespace
	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * @namespace AIPS.Embeddings
	 */
	AIPS.Embeddings = {

		/**
		 * Placeholder initialisation hook for the Embeddings namespace.
		 *
		 * Called on `document.ready`. Currently a no-op; reserved for any
		 * future setup that must run once the DOM is ready.
		 */
		init: function() {
			$(document).on('click', '[data-aips-queue-embeddings]', this.handleQueueClick.bind(this));
		},

		/**
		 * Handle queue button clicks.
		 *
		 * @param {Event} e Click event.
		 */
		handleQueueClick: function(e) {
			var $btn;
			var authorId;
			var batchSize;

			e.preventDefault();

			$btn = $(e.currentTarget);
			authorId = parseInt($btn.data('aips-queue-embeddings'), 10) || 0;
			batchSize = parseInt($btn.data('batch-size'), 10) || 0;

			$btn.prop('disabled', true).attr('aria-busy', 'true');

			this.queueEmbeddings(authorId, batchSize).always(function() {
				$btn.prop('disabled', false).removeAttr('aria-busy');
			});
		},

		/**
		 * Queue embeddings computation for an author.
		 *
		 * @param {number} authorId   Author ID (0 for all authors).
		 * @param {number} batchSize  Optional. Batch size for processing (default 20).
		 */
		queueEmbeddings: function(authorId, batchSize) {
			authorId = parseInt(authorId) || 0;
			batchSize = parseInt(batchSize, 10) || 20;

			AIPS.Utilities.showToast(aipsEmbeddingsL10n.queueing, 'info');

			return $.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_compute_topic_embeddings',
					nonce: aipsEmbeddingsL10n.nonce,
					author_id: authorId,
					batch_size: batchSize
				},
				success: function(response) {
					if (response.success) {
						AIPS.Utilities.showToast(
							(response.data && response.data.message) || aipsEmbeddingsL10n.queued,
							'success'
						);
						return;
					}

					AIPS.Utilities.showToast(
						(response.data && response.data.message) || aipsEmbeddingsL10n.error,
						'error'
					);
				},
				error: function(xhr) {
					var response = xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null;
					AIPS.Utilities.showToast(
						(response && response.message) || aipsEmbeddingsL10n.networkError,
						'error'
					);
				}
			});
		}

	};

	$(function() {
		AIPS.Embeddings.init();
	});

})(jQuery);
