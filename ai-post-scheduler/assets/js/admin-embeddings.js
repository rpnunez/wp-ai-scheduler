/**
 * Admin Embeddings
 *
 * Provides helpers to queue background embedding computation for one or all
 * authors by posting to the aips_compute_topic_embeddings AJAX endpoint.
 *
 * Usage (PHP template / inline JS):
 *   AIPS.Embeddings.queueEmbeddings(1);          // single author
 *   AIPS.Embeddings.queueEmbeddings(0);          // all active authors
 *   AIPS.Embeddings.queueEmbeddings(1, 50);      // single author, batch of 50
 *
 * HTML data-attribute trigger:
 *   <button data-aips-queue-embeddings="1" data-batch-size="20">
 *       Compute Embeddings
 *   </button>
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
			 * Bootstrap the embeddings module.
			 *
			 * Called on DOM ready; sets up delegated event listeners via bindEvents().
			 */
			init: function() {
				this.bindEvents();
			},

			/**
			 * Register all delegated event listeners for the embeddings UI.
			 *
			 * Uses event delegation on `document` so handlers work for dynamically
			 * rendered elements (e.g. rows appended by an AJAX call).
			 */
			bindEvents: function() {
				$(document).on(
					'click',
					'[data-aips-queue-embeddings]',
					this.handleQueueClick.bind(this)
				);
			},

			/**
			 * Handle a click on a [data-aips-queue-embeddings] element.
			 *
			 * Reads the author ID from the data attribute and an optional batch size
			 * from data-batch-size, then delegates to queueEmbeddings().
			 *
			 * @param {Event} e The click event.
			 */
			handleQueueClick: function(e) {
				e.preventDefault();
				var $btn      = $(e.currentTarget);
				var authorId  = $btn.data('aips-queue-embeddings');
				var batchSize = $btn.data('batch-size') || 20;
				AIPS.Embeddings.queueEmbeddings(authorId, batchSize);
			},

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

				this.showToast(aipsEmbeddingsL10n.queueing, 'info');

				$.ajax({
					url:  aipsAjax.ajaxUrl,
					type: 'POST',
					data: {
						action:     'aips_compute_topic_embeddings',
						nonce:      aipsAjax.nonce,
						author_id:  authorId,
						batch_size: batchSize,
					},
					success: this.handleQueueSuccess.bind(this),
					error:   this.handleQueueError.bind(this),
				});
			},

			/**
			 * Handle a successful response from the queue AJAX call.
			 *
			 * @param {Object} response WordPress JSON response object.
			 */
			handleQueueSuccess: function(response) {
				if (response.success) {
					var msg = response.data && response.data.message
						? response.data.message
						: aipsEmbeddingsL10n.queued;
					this.showToast(msg, 'success');
				} else {
					var errMsg = response.data && response.data.message
						? response.data.message
						: aipsEmbeddingsL10n.error;
					this.showToast(errMsg, 'error');
				}
			},

			/**
			 * Handle a network-level error from the queue AJAX call.
			 *
			 * @param {jqXHR}  xhr         jQuery XHR object.
			 * @param {string} status      Status string.
			 * @param {string} errorThrown Error message thrown by the browser.
			 */
			handleQueueError: function(xhr, status, errorThrown) {
				var errMsg = aipsEmbeddingsL10n.error + ' (' + errorThrown + ')';
				this.showToast(errMsg, 'error');
			},

			/**
			 * Display a toast notification, falling back to console when not available.
			 *
			 * @param {string} message   Notification text.
			 * @param {string} type      Toast type: 'info', 'success', or 'error'.
			 */
			showToast: function(message, type) {
				if (typeof AIPS.Utilities !== 'undefined' && AIPS.Utilities.showToast) {
					AIPS.Utilities.showToast(message, type);
				} else if (type === 'error') {
					// eslint-disable-next-line no-console
					console.error('[AIPS Embeddings] ' + message);
				} else {
					// eslint-disable-next-line no-console
					console.log('[AIPS Embeddings] ' + message);
				}
			},
		},
	});

	$(document).ready(function() {
		AIPS.Embeddings.init();
	});

})(jQuery);
