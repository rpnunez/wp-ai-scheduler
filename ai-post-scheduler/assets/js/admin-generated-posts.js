/**
 * Generated Posts Admin JavaScript
 *
 * Handles the Generated Posts page, session modal, and AI interaction viewing.
 */
(function($) {
	'use strict';
	
	// Ensure AIPS object exists
	window.AIPS = window.AIPS || {};
	
	// Extend AIPS with Generated Posts functionality
	Object.assign(window.AIPS, {
		
		/**
		 * History type constants (mirror PHP AIPS_History_Type)
		 * These are injected by PHP in the template
		 */
		HistoryType: window.AIPS_History_Type || {},
		
		/**
		 * AJAX nonce for security
		 * Injected by PHP in the template
		 */
		ajaxNonce: window.aipsAjaxNonce || '',
		
		/**
		 * Initialize Generated Posts functionality
		 */
		initGeneratedPosts: function() {
			this.bindEvents();
		},
		
		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// View Session button handler
			$(document).on('click', '.aips-view-session', this.handleViewSession.bind(this));
			
			// Clear History button handler
			$(document).on('click', '#aips-clear-history', this.handleClearHistory.bind(this));

			// Close modal handlers
			$(document).on('click', '.aips-modal-close, .aips-modal-overlay', this.closeModal.bind(this));
			
			// Tab navigation
			$(document).on('click', '.aips-tab-nav a', this.handleTabSwitch.bind(this));
			
			// Toggle AI component details
			$(document).on('click', '.aips-ai-component:not(.expanded)', this.handleAIComponentClick.bind(this));
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#aips-session-modal').is(':visible')) {
					window.AIPS.closeModal();
				}
			});
		},
		
		/**
		 * Handle View Session button click
		 */
		handleViewSession: function(e) {
			e.preventDefault();
			var historyId = $(e.currentTarget).data('history-id');
			
			if (!historyId) {
				console.error('No history ID provided');
				return;
			}
			
			this.loadSessionData(historyId);
		},

		/**
		 * Handle Clear History button click
		 */
		handleClearHistory: function(e) {
			e.preventDefault();

			if (!confirm(window.aipsAdminL10n.clearHistoryConfirm || 'Are you sure you want to clear all history?')) {
				return;
			}

			var self = this;
            var $button = $(e.currentTarget);
            var originalText = $button.text();
            $button.prop('disabled', true).text('Clearing...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_clear_history',
					nonce: this.ajaxNonce
				},
				success: function(response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert(response.data.message || 'Failed to clear history.');
                        $button.prop('disabled', false).text(originalText);
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					alert('Failed to clear history.');
                    $button.prop('disabled', false).text(originalText);
				}
			});
		},
		
		/**
		 * Load session data via AJAX
		 */
		loadSessionData: function(historyId) {
			var self = this;
			
			// Show loading state
			this.showLoadingModal();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_post_session',
					nonce: this.ajaxNonce,
					history_id: historyId
				},
				success: function(response) {
					if (response.success) {
						self.displaySessionModal(response.data);
					} else {
						self.showError(response.data.message || 'Failed to load session data.');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					self.showError('Failed to load session data. Please try again.');
				}
			});
		},
		
		/**
		 * Show loading state in modal
		 */
		showLoadingModal: function() {
			$('#aips-session-modal').show();
			$('#aips-session-title').text('Loading...');
			$('#aips-session-created').text('');
			$('#aips-session-completed').text('');
			$('#aips-logs-list').html('<p>Loading logs...</p>');
			$('#aips-ai-list').html('<p>Loading AI calls...</p>');
		},
		
		/**
		 * Display session data in modal
		 */
		displaySessionModal: function(data) {
			// Update session info
			$('#aips-session-title').text(data.history.generated_title || 'N/A');
			$('#aips-session-created').text(data.history.created_at || 'N/A');
			$('#aips-session-completed').text(data.history.completed_at || 'N/A');
			
			// Display logs
			this.renderLogs(data.logs);
			
			// Display AI calls
			this.renderAICalls(data.ai_calls);
			
			// Show modal
			$('#aips-session-modal').show();
		},
		
		/**
		 * Render logs tab content
		 */
		renderLogs: function(logs) {
			var logsHtml = '';
			
			if (logs.length > 0) {
				logs.forEach(function(log) {
					var cssClass = '';
					if (log.type_id === window.AIPS.HistoryType.ERROR) {
						cssClass = 'error';
					} else if (log.type_id === window.AIPS.HistoryType.WARNING) {
						cssClass = 'warning';
					}
					
					logsHtml += '<div class="aips-log-entry ' + window.AIPS.escapeHtml(cssClass) + '">';
					logsHtml += '<h4>' + window.AIPS.escapeHtml(log.type) + ' - ' + window.AIPS.escapeHtml(log.log_type) + '</h4>';
					logsHtml += '<div class="aips-log-timestamp">' + window.AIPS.escapeHtml(log.timestamp) + '</div>';
					logsHtml += '<div class="aips-json-viewer"><pre>' + window.AIPS.escapeHtml(JSON.stringify(log.details, null, 2)) + '</pre></div>';
					logsHtml += '</div>';
				});
			} else {
				logsHtml = '<p class="aips-no-data">No log entries found.</p>';
			}
			
			$('#aips-logs-list').html(logsHtml);
		},
		
		/**
		 * Render AI calls tab content
		 */
		renderAICalls: function(ai_calls) {
			var aiHtml = '';
			
			if (ai_calls.length > 0) {
				ai_calls.forEach(function(call) {
					aiHtml += '<div class="aips-ai-component" data-component="' + window.AIPS.escapeHtml(call.type) + '">';
					aiHtml += '<h4>' + window.AIPS.escapeHtml(call.label) + '</h4>';
					aiHtml += '<p class="aips-ai-hint">Click to view request and response details</p>';
					aiHtml += '<div class="aips-ai-details">';
					
					if (call.request) {
						aiHtml += '<div class="aips-ai-section">';
						aiHtml += '<h5>Request</h5>';
						aiHtml += '<div class="aips-json-viewer"><pre>' + window.AIPS.escapeHtml(JSON.stringify(call.request, null, 2)) + '</pre></div>';
						aiHtml += '</div>';
					}
					
					if (call.response) {
						aiHtml += '<div class="aips-ai-section">';
						aiHtml += '<h5>Response</h5>';
						aiHtml += '<div class="aips-json-viewer"><pre>' + window.AIPS.escapeHtml(JSON.stringify(call.response, null, 2)) + '</pre></div>';
						aiHtml += '</div>';
					}
					
					aiHtml += '</div></div>';
				});
			} else {
				aiHtml = '<p class="aips-no-data">No AI calls found.</p>';
			}
			
			$('#aips-ai-list').html(aiHtml);
		},
		
		/**
		 * Handle tab switching
		 */
		handleTabSwitch: function(e) {
			e.preventDefault();
			var target = $(e.currentTarget).attr('href');
			
			$('.aips-tab-nav a').removeClass('active');
			$(e.currentTarget).addClass('active');
			
			$('.aips-tab-content').hide();
			$(target).show();
		},
		
		/**
		 * Handle AI component click to expand details
		 */
		handleAIComponentClick: function(e) {
			$(e.currentTarget).addClass('expanded');
		},
		
		/**
		 * Close the modal
		 */
		closeModal: function() {
			$('#aips-session-modal').hide();
		},
		
		/**
		 * Show error message
		 */
		showError: function(message) {
			alert(message);
			this.closeModal();
		},
		
		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function(text) {
			if (text === null || text === undefined) {
				return '';
			}
			
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			
			return String(text).replace(/[&<>"']/g, function(m) { 
				return map[m]; 
			});
		}
		
	});
	
	// Initialize on document ready
	$(document).ready(function() {
		window.AIPS.initGeneratedPosts();
	});
	
})(jQuery);
