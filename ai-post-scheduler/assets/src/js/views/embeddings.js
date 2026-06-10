import Backbone from 'backbone';
import $ from 'jquery';

/**
 * Embeddings View Controller
 */
export const EmbeddingsView = Backbone.View.extend({
	el: 'body',

	initialize() {
		// Exposed globally for backwards compatibility/inline hooks
		window.AIPS = window.AIPS || {};
		window.AIPS.Embeddings = {
			queueEmbeddings: this.queueEmbeddings.bind(this)
		};
	},

	/**
	 * Queue embeddings computation for an author.
	 *
	 * @param {number} authorId   Author ID (0 for all authors).
	 * @param {number} batchSize  Optional. Batch size for processing (default 20).
	 */
	queueEmbeddings(authorId, batchSize) {
		const parsedAuthorId = parseInt(authorId, 10) || 0;
		const parsedBatchSize = parseInt(batchSize, 10) || 20;

		console.log('Queueing embeddings computation for author ID:', parsedAuthorId, 'with batch size:', parsedBatchSize);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_compute_topic_embeddings',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				author_id: parsedAuthorId,
				batch_size: parsedBatchSize
			},
			success: (response) => {
				if (response.success) {
					console.log('Embeddings queued successfully:', response.data);
					alert(response.data.message);
				} else {
					console.error('Failed to queue embeddings:', response.data);
					alert('Error: ' + (response.data.message || 'Failed to queue embeddings.'));
				}
			},
			error: (xhr, status, error) => {
				console.error('AJAX error while queueing embeddings:', error);
				alert('Network error: Failed to queue embeddings.');
			}
		});
	}
});

export default EmbeddingsView;
