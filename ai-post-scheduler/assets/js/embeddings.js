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
			// Nothing needed on init currently; reserved for future use.
		},

		/**
		 * Queue embeddings computation for an author.
		 *
		 * @param {number} authorId   Author ID (0 for all authors).
		 * @param {number} batchSize  Optional. Batch size for processing (default 20).
		 */
		queueEmbeddings: function(authorId, batchSize) {
			authorId = parseInt(authorId) || 0;
			batchSize = parseInt(batchSize) || 20;

			// Show a console message
			console.log('Queueing embeddings computation for author ID:', authorId, 'with batch size:', batchSize);

			// Make AJAX request
			AIPS.Core.Http.ajaxRequest({
				action: 'aips_compute_topic_embeddings',
				data: {
					author_id: authorId,
					batch_size: batchSize
				},
				errorFallback: 'Failed to queue embeddings.',
				onSuccess: function(data) {
					console.log('Embeddings queued successfully:', data);
					AIPS.Utilities.showToast(data.message, 'success');
				},
				onError: function(message) {
					console.error('Failed to queue embeddings:', message);
				}
			});
		}

	};

})(jQuery);
