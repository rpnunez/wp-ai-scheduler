/**
 * Admin Embeddings
 *
 * Provides a helper to queue background embedding computation for one or all
 * authors by posting to the aips_compute_topic_embeddings AJAX endpoint.
 *
 * Usage (PHP template / inline JS):
 *   AIPS.Embeddings.queueEmbeddings(1);          // single author
 *   AIPS.Embeddings.queueEmbeddings(0);          // all active authors
 *   AIPS.Embeddings.queueEmbeddings(1, 50);      // single author, batch of 50
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		Embeddings: {

			/**
			 * Queue background embedding computation for an author (or all authors).
			 *
			 * Sends an AJAX request to schedule the embeddings work in the background.
			 * The server returns immediately; actual processing happens via WP-Cron or
			 * Action Scheduler.
			 *
			 * @param {number} authorId   Author ID, or 0 to queue all active authors.
			 * @param {number} batchSize  Optional topics-per-batch override. Default 20.
			 */
			queueEmbeddings: function(authorId, batchSize) {
				authorId  = parseInt(authorId, 10)  || 0;
				batchSize = parseInt(batchSize, 10) || 20;

				if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(aipsEmbeddingsL10n.queueing, 'info');
				}

				$.ajax({
					url:  aipsAjax.ajaxUrl,
					type: 'POST',
					data: {
						action:     'aips_compute_topic_embeddings',
						nonce:      aipsAjax.nonce,
						author_id:  authorId,
						batch_size: batchSize,
					},
					success: function(response) {
						if (response.success) {
							var msg = response.data && response.data.message
								? response.data.message
								: aipsEmbeddingsL10n.queued;
							if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(msg, 'success');
							} else {
								// eslint-disable-next-line no-console
								console.log('[AIPS Embeddings] ' + msg);
							}
						} else {
							var errMsg = response.data && response.data.message
								? response.data.message
								: aipsEmbeddingsL10n.error;
							if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
								AIPS.Utilities.showToast(errMsg, 'error');
							} else {
								// eslint-disable-next-line no-console
								console.error('[AIPS Embeddings] ' + errMsg);
							}
						}
					},
					error: function(xhr, status, errorThrown) {
						var errMsg = aipsEmbeddingsL10n.error + ' (' + errorThrown + ')';
						if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
							AIPS.Utilities.showToast(errMsg, 'error');
						} else {
							// eslint-disable-next-line no-console
							console.error('[AIPS Embeddings] ' + errMsg);
						}
					},
				});
			},
		},
	});

	$(document).ready(function() {
		/**
		 * Delegate click handler for any element with data-aips-queue-embeddings.
		 *
		 * HTML example:
		 *   <button data-aips-queue-embeddings="1" data-batch-size="20">
		 *       Compute Embeddings
		 *   </button>
		 */
		$(document).on('click', '[data-aips-queue-embeddings]', function(e) {
			e.preventDefault();
			var authorId  = $(this).data('aips-queue-embeddings');
			var batchSize = $(this).data('batch-size') || 20;
			AIPS.Embeddings.queueEmbeddings(authorId, batchSize);
		});
	});

})(jQuery);
