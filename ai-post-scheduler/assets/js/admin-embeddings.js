/**
 * Admin Embeddings Module
 *
 * Provides UI helper functions for queueing embedding computation jobs.
 * Loaded only on the Authors and Author Topics admin pages.
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
		 * Initialise the Embeddings module.
		 *
		 * Called on `document.ready`. Delegates event binding to bindEvents().
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Register UI event listeners for embedding actions.
		 *
		 * Currently a no-op; reserved for future DOM-driven triggers.
		 */
		bindEvents: function() {
			// Reserved for future event bindings.
		},

		/**
		 * Queue embeddings computation for an author.
		 *
		 * @param {number} authorId   Author ID (0 for all authors).
		 * @param {number} batchSize  Optional. Batch size for processing (default 20).
		 */
		queueEmbeddings: function(authorId, batchSize) {
			authorId  = parseInt(authorId, 10)  || 0;
			batchSize = parseInt(batchSize, 10) || 20;

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
						alert(response.data.message);
					} else {
						alert('Error: ' + (response.data.message || 'Failed to queue embeddings.'));
					}
				},
				error: function() {
					alert('Network error: Failed to queue embeddings.');
				}
			});
		}

	};

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function() {
		AIPS.Embeddings.init();
	});

})(jQuery);
