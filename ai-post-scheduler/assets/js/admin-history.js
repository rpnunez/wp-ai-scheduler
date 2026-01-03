/**
 * Admin History Component
 * 
 * Handles all history-related functionality including viewing details,
 * filtering, retry generation, bulk actions, and history clearing
 * for the History admin page.
 * 
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * History Component
	 * 
	 * Manages history operations including viewing generation details,
	 * filtering, retry generation, bulk deletion, and clearing history.
	 */
	AIPS.History = {
		/**
		 * Initialize the History component.
		 * 
		 * Binds all history-related event handlers.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind history-specific event handlers.
		 */
		bindEvents: function() {
			// History actions
			$(document).on('click', '.aips-clear-history', this.clearHistory);
			$(document).on('click', '.aips-retry-generation', this.retryGeneration);
			$(document).on('click', '#aips-filter-btn', this.filterHistory);
			$(document).on('click', '#aips-history-search-btn', this.filterHistory);
			$(document).on('keypress', '#aips-history-search-input', function(e) {
				if(e.which == 13) {
					AIPS.History.filterHistory(e);
				}
			});
			$(document).on('click', '.aips-view-details', this.viewDetails);

			// History bulk actions
			$(document).on('change', '#cb-select-all-1', this.toggleAllHistory);
			$(document).on('change', '.aips-history-table input[name="history[]"]', this.toggleHistorySelection);
			$(document).on('click', '#aips-delete-selected-btn', this.deleteSelectedHistory);
		},

		/**
		 * Clear history entries.
		 * 
		 * Prompts for confirmation and clears history entries via AJAX.
		 * Can clear all history or filter by status.
		 * 
		 * @param {Event} e - The click event.
		 */
		clearHistory: function(e) {
			e.preventDefault();
			var status = $(this).data('status');
			var message = status ? 'Are you sure you want to clear all ' + status + ' history?' : 'Are you sure you want to clear all history?';
			
			if (!confirm(message)) {
				return;
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_clear_history',
					nonce: aipsAjax.nonce,
					status: status
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		},

		/**
		 * Retry a failed generation.
		 * 
		 * Attempts to regenerate a post from a failed history entry via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		retryGeneration: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $btn = $(this);

			$btn.prop('disabled', true).text('Retrying...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_retry_generation',
					nonce: aipsAjax.nonce,
					history_id: id
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Retry');
				}
			});
		},

		/**
		 * Filter history by status and search term.
		 * 
		 * Updates the URL with filter parameters and reloads the page
		 * to show filtered results.
		 * 
		 * @param {Event} e - The click event.
		 */
		filterHistory: function(e) {
			e.preventDefault();
			var status = $('#aips-filter-status').val();
			var search = $('#aips-history-search-input').val();
			var url = new URL(window.location.href);
			
			if (status) {
				url.searchParams.set('status', status);
			} else {
				url.searchParams.delete('status');
			}

			if (search) {
				url.searchParams.set('s', search);
			} else {
				url.searchParams.delete('s');
			}

			url.searchParams.delete('paged');
			
			window.location.href = url.toString();
		},

		/**
		 * View generation details.
		 * 
		 * Fetches and displays detailed information about a generation
		 * in a modal dialog via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		viewDetails: function(e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $btn = $(this);
			
			$btn.prop('disabled', true);
			$('#aips-details-loading').show();
			$('#aips-details-content').hide();
			$('#aips-details-modal').show();

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_history_details',
					nonce: aipsAjax.nonce,
					history_id: id
				},
				success: function(response) {
					if (response.success) {
						AIPS.History.renderDetails(response.data);
					} else {
						alert(response.data.message);
						$('#aips-details-modal').hide();
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$('#aips-details-modal').hide();
				},
				complete: function() {
					$btn.prop('disabled', false);
					$('#aips-details-loading').hide();
				}
			});
		},

		/**
		 * Render generation details in the modal.
		 * 
		 * Builds and displays HTML for the generation details modal
		 * including summary, template info, voice info, AI calls, and errors.
		 * 
		 * @param {Object} data - The generation details data.
		 */
		renderDetails: function(data) {
			var log = data.generation_log || {};
			
			var summaryHtml = '<table class="aips-details-table">';
			summaryHtml += '<tr><th>Status:</th><td><span class="aips-status aips-status-' + data.status + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span></td></tr>';
			summaryHtml += '<tr><th>Title:</th><td>' + (data.generated_title || '-') + '</td></tr>';
			if (data.post_id) {
				summaryHtml += '<tr><th>Post ID:</th><td>' + data.post_id + '</td></tr>';
			}
			summaryHtml += '<tr><th>Started:</th><td>' + (log.started_at || data.created_at) + '</td></tr>';
			summaryHtml += '<tr><th>Completed:</th><td>' + (log.completed_at || data.completed_at || '-') + '</td></tr>';
			if (data.error_message) {
				summaryHtml += '<tr><th>Error:</th><td class="aips-error-text">' + data.error_message + '</td></tr>';
			}
			summaryHtml += '</table>';
			$('#aips-details-summary').html(summaryHtml);
			
			if (log.template) {
				var templateHtml = '<table class="aips-details-table">';
				templateHtml += '<tr><th>Name:</th><td>' + (log.template.name || '-') + '</td></tr>';
				templateHtml += '<tr><th>Prompt Template:</th><td>';
				templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.History.escapeHtml(log.template.prompt_template || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
				templateHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.template.prompt_template || '') + '</pre></td></tr>';
				if (log.template.title_prompt) {
					templateHtml += '<tr><th>Title Prompt:</th><td>';
					templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.History.escapeHtml(log.template.title_prompt) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
					templateHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.template.title_prompt) + '</pre></td></tr>';
				}
				templateHtml += '<tr><th>Post Status:</th><td>' + (log.template.post_status || 'draft') + '</td></tr>';
				templateHtml += '<tr><th>Post Quantity:</th><td>' + (log.template.post_quantity || 1) + '</td></tr>';
				if (log.template.generate_featured_image) {
					templateHtml += '<tr><th>Image Prompt:</th><td><pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.template.image_prompt || '') + '</pre></td></tr>';
				}
				templateHtml += '</table>';
				$('#aips-details-template').html(templateHtml);
			} else {
				$('#aips-details-template').html('<p>No template data available.</p>');
			}
			
			if (log.voice) {
				var voiceHtml = '<table class="aips-details-table">';
				voiceHtml += '<tr><th>Name:</th><td>' + (log.voice.name || '-') + '</td></tr>';
				voiceHtml += '<tr><th>Title Prompt:</th><td>';
				voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.History.escapeHtml(log.voice.title_prompt || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
				voiceHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.voice.title_prompt || '') + '</pre></td></tr>';
				voiceHtml += '<tr><th>Content Instructions:</th><td>';
				voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.History.escapeHtml(log.voice.content_instructions || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
				voiceHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.voice.content_instructions || '') + '</pre></td></tr>';
				if (log.voice.excerpt_instructions) {
					voiceHtml += '<tr><th>Excerpt Instructions:</th><td>';
					voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + AIPS.History.escapeHtml(log.voice.excerpt_instructions) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
					voiceHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(log.voice.excerpt_instructions) + '</pre></td></tr>';
				}
				voiceHtml += '</table>';
				$('#aips-details-voice').html(voiceHtml);
				$('#aips-details-voice-section').show();
			} else {
				$('#aips-details-voice-section').hide();
			}
			
			if (log.ai_calls && log.ai_calls.length > 0) {
				var callsHtml = '';
				log.ai_calls.forEach(function(call, index) {
					var statusClass = call.response.success ? 'aips-call-success' : 'aips-call-error';
					callsHtml += '<div class="aips-ai-call ' + statusClass + '">';
					callsHtml += '<div class="aips-call-header">';
					callsHtml += '<strong>Call #' + (index + 1) + ' - ' + call.type.charAt(0).toUpperCase() + call.type.slice(1) + '</strong>';
					callsHtml += '<span class="aips-call-time">' + call.timestamp + '</span>';
					callsHtml += '</div>';
					callsHtml += '<div class="aips-call-section">';
					callsHtml += '<h4>Request</h4>';
					callsHtml += '<pre class="aips-prompt-text">' + AIPS.History.escapeHtml(call.request.prompt || '') + '</pre>';
					if (call.request.options && Object.keys(call.request.options).length > 0) {
						callsHtml += '<p><small>Options: ' + JSON.stringify(call.request.options) + '</small></p>';
					}
					callsHtml += '</div>';
					callsHtml += '<div class="aips-call-section">';
					callsHtml += '<h4>Response</h4>';
					if (call.response.success) {
						callsHtml += '<pre class="aips-response-text">' + AIPS.History.escapeHtml(call.response.content || '') + '</pre>';
					} else {
						callsHtml += '<p class="aips-error-text">Error: ' + AIPS.History.escapeHtml(call.response.error || 'Unknown error') + '</p>';
					}
					callsHtml += '</div>';
					callsHtml += '</div>';
				});
				$('#aips-details-ai-calls').html(callsHtml);
			} else {
				$('#aips-details-ai-calls').html('<p>No AI call data available for this entry.</p>');
			}
			
			if (log.errors && log.errors.length > 0) {
				var errorsHtml = '<ul class="aips-errors-list">';
				log.errors.forEach(function(error) {
					errorsHtml += '<li>';
					errorsHtml += '<strong>' + error.type + '</strong> at ' + error.timestamp + '<br>';
					errorsHtml += '<span class="aips-error-text">' + AIPS.History.escapeHtml(error.message) + '</span>';
					errorsHtml += '</li>';
				});
				errorsHtml += '</ul>';
				$('#aips-details-errors').html(errorsHtml);
				$('#aips-details-errors-section').show();
			} else {
				$('#aips-details-errors-section').hide();
			}
			
			$('#aips-details-content').show();
		},

		/**
		 * Escape HTML for safe display.
		 * 
		 * Converts text to safe HTML by escaping special characters.
		 * 
		 * @param {string} text - The text to escape.
		 * @return {string} The escaped HTML.
		 */
		escapeHtml: function(text) {
			if (!text) return '';
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Toggle all history checkboxes.
		 * 
		 * Checks or unchecks all history item checkboxes based on the
		 * state of the "select all" checkbox.
		 */
		toggleAllHistory: function() {
			var isChecked = $(this).prop('checked');
			$('.aips-history-table input[name="history[]"]').prop('checked', isChecked);
			AIPS.History.updateDeleteButton();
		},

		/**
		 * Toggle individual history selection.
		 * 
		 * Updates the "select all" checkbox state based on individual
		 * checkbox selections and updates the delete button state.
		 */
		toggleHistorySelection: function() {
			var allChecked = $('.aips-history-table input[name="history[]"]').length === $('.aips-history-table input[name="history[]"]:checked').length;
			$('#cb-select-all-1').prop('checked', allChecked);
			AIPS.History.updateDeleteButton();
		},

		/**
		 * Update the bulk delete button state.
		 * 
		 * Enables or disables the bulk delete button based on whether
		 * any history items are selected.
		 */
		updateDeleteButton: function() {
			var count = $('.aips-history-table input[name="history[]"]:checked').length;
			$('#aips-delete-selected-btn').prop('disabled', count === 0);
		},

		/**
		 * Delete selected history items.
		 * 
		 * Prompts for confirmation and deletes all selected history items via AJAX.
		 * 
		 * @param {Event} e - The click event.
		 */
		deleteSelectedHistory: function(e) {
			e.preventDefault();
			var ids = [];
			$('.aips-history-table input[name="history[]"]:checked').each(function() {
				ids.push($(this).val());
			});

			if (ids.length === 0) return;

			if (!confirm('Are you sure you want to delete ' + ids.length + ' item(s)?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Deleting...');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_bulk_delete_history',
					nonce: aipsAjax.nonce,
					ids: ids
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message);
						$btn.prop('disabled', false).text('Delete Selected');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$btn.prop('disabled', false).text('Delete Selected');
				}
			});
		}
	};

	// Initialize History component when DOM is ready
	$(document).ready(function() {
		AIPS.History.init();
	});

})(jQuery);
