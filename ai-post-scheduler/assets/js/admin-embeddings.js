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
			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_compute_topic_embeddings',
					nonce: aipsAjax.nonce,
					author_id: authorId,
					batch_size: batchSize
				},
				success: function(response) {
					if (response.success) {
						console.log('Embeddings queued successfully:', response.data);
						alert(response.data.message);
					} else {
						console.error('Failed to queue embeddings:', response.data);
						alert('Error: ' + (response.data.message || 'Failed to queue embeddings.'));
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error while queueing embeddings:', error);
					alert('Network error: Failed to queue embeddings.');
				}
			});
		}

	};

})(jQuery);
