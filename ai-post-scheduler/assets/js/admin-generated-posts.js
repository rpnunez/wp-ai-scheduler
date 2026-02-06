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
			
			// Copy Session JSON button handler
			$(document).on('click', '.aips-copy-session-json', this.handleCopySessionJSON.bind(this));
			
			// Download Session JSON button handler
			$(document).on('click', '.aips-download-session-json', this.handleDownloadSessionJSON.bind(this));

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
			// Store current history ID for JSON export
			this.currentHistoryId = data.history.id;
			// Store current log count so we can choose client vs server download strategy
			this.currentLogCount = Array.isArray(data.logs) ? data.logs.length : 0;

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
		 * Handle Copy Session JSON button click
		 */
		handleCopySessionJSON: function(e) {
			e.preventDefault();
			var self = this;
			var $button = $(e.currentTarget);
			
			// Check if we have a history ID stored
			if (!this.currentHistoryId) {
				this.showNotification('No session data available.', 'error');
				return;
			}
			
			// Disable button and show loading state
			$button.prop('disabled', true).text('Loading...');
			
			// Fetch the JSON data
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_session_json',
					nonce: this.ajaxNonce,
					history_id: this.currentHistoryId
				},
				success: function(response) {
					if (response.success && response.data.json) {
						// Copy to clipboard
						self.copyToClipboard(response.data.json, $button);
					} else {
						self.showNotification(response.data.message || 'Failed to generate JSON.', 'error');
						$button.prop('disabled', false).text('Copy Session JSON');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					self.showNotification('Failed to load session JSON. Please try again.', 'error');
					$button.prop('disabled', false).text('Copy Session JSON');
				}
			});
		},
		
		/**
		 * Handle Download Session JSON button click
		 */
		handleDownloadSessionJSON: function(e) {
			e.preventDefault();
			var self = this;
			var $button = $(e.currentTarget);

			// Check if we have a history ID stored
			if (!this.currentHistoryId) {
				this.showNotification('No session data available for download.', 'error');
				return;
			}

			// Threshold (number of logs) below which we fetch JSON via AJAX and use client-side blob download
			// Threshold can be provided by the server via localization; fallback to 20 (matches PHP default)
			var CLIENT_LOG_THRESHOLD = (window.aipsGeneratedPostsConfig && typeof window.aipsGeneratedPostsConfig.clientLogThreshold !== 'undefined') ? parseInt(window.aipsGeneratedPostsConfig.clientLogThreshold, 10) : 20;

			if (typeof this.currentLogCount === 'number' && this.currentLogCount <= CLIENT_LOG_THRESHOLD) {
				// Small session: fetch the JSON via existing AJAX endpoint and trigger client-side download
				$button.prop('disabled', true).text('Preparing download...');
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'aips_get_session_json',
						nonce: this.ajaxNonce,
						history_id: this.currentHistoryId
					},
					success: function(response) {
						if (response.success && response.data.json) {
							var filename = 'aips-session-' + self.currentHistoryId + '.json';
							self.downloadJSON(response.data.json, filename);
							$button.prop('disabled', false).text('Download Session JSON');
							// Admin notice
							self.showAdminNotice('Session JSON download started. Check your browser downloads.');
						} else {
							self.showNotification(response.data.message || 'Failed to generate JSON for download.', 'error');
							$button.prop('disabled', false).text('Download Session JSON');
						}
					},
					error: function(xhr, status, error) {
						console.error('AJAX error:', status, error);
						self.showNotification('Failed to load session JSON for download. Please try again.', 'error');
						$button.prop('disabled', false).text('Download Session JSON');
					}
				});
				return;
			}

			// Large session: use a form POST to the new ajax download endpoint in a new tab/window so the browser handles the download
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = ajaxurl;
			form.target = '_blank';

			var inputAction = document.createElement('input');
			inputAction.type = 'hidden';
			inputAction.name = 'action';
			inputAction.value = 'aips_download_session_json';
			form.appendChild(inputAction);

			var inputNonce = document.createElement('input');
			inputNonce.type = 'hidden';
			inputNonce.name = 'nonce';
			inputNonce.value = this.ajaxNonce;
			form.appendChild(inputNonce);

			var inputHistory = document.createElement('input');
			inputHistory.type = 'hidden';
			inputHistory.name = 'history_id';
			inputHistory.value = this.currentHistoryId;
			form.appendChild(inputHistory);

			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);

			// Re-enable button quickly since download happens in new tab
			$button.prop('disabled', false).text('Download Session JSON');

			// Show admin notice that download has started
			this.showAdminNotice('Session JSON download started. Check your browser downloads.');
		},

		/**
		 * Download JSON data as a file
		 */
		downloadJSON: function(jsonData, fileName) {
			var blob = new Blob([jsonData], { type: 'application/json' });
			var url = URL.createObjectURL(blob);
			var a = document.createElement('a');
			a.href = url;
			a.download = fileName;
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(url);

			this.showNotification('Session JSON is being downloaded.', 'success');
		},

		/**
		 * Copy text to clipboard
		 */
		copyToClipboard: function(text, $button) {
			var self = this;
			
			// Try modern clipboard API first
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					self.showNotification('Session JSON copied to clipboard!', 'success');
					$button.prop('disabled', false).text('Copy Session JSON');
				}).catch(function(err) {
					console.error('Failed to copy:', err);
					self.fallbackCopyToClipboard(text, $button);
				});
			} else {
				// Fallback for older browsers
				self.fallbackCopyToClipboard(text, $button);
			}
		},
		
		/**
		 * Fallback clipboard copy method for older browsers
		 */
		fallbackCopyToClipboard: function(text, $button) {
			var self = this;
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			
			try {
				var successful = document.execCommand('copy');
				if (successful) {
					self.showNotification('Session JSON copied to clipboard!', 'success');
				} else {
					self.showNotification('Failed to copy to clipboard.', 'error');
				}
			} catch (err) {
				console.error('Fallback copy failed:', err);
				self.showNotification('Failed to copy to clipboard.', 'error');
			}
			
			$temp.remove();
			$button.prop('disabled', false).text('Copy Session JSON');
		},
		
		/**
		 * Show notification message
		 */
		showNotification: function(message, type) {
			// Remove existing notifications
			$('.aips-notification').remove();
			
			// Create notification element
			var $notification = $('<div class="aips-notification aips-notification-' + type + '">')
				.text(message)
				.appendTo('.aips-modal-body');
			
			// Auto-hide after 3 seconds
			setTimeout(function() {
				$notification.fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
		},
		
		/**
		 * Show a small admin notice at the top of the admin page
		 * This uses WP admin notice classes for consistency and is dismissible.
		 */
		showAdminNotice: function(message, type) {
			var noticeType = type === 'error' ? 'notice-error' : 'notice-success';
			var $notice = $('<div class="notice ' + noticeType + ' is-dismissible aips-admin-notice">')
				.append($('<p>').text(message));

			// Add dismiss button behavior
			$notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

			// Insert notice at top of main .wrap
			$('.wrap').first().prepend($notice);

			// Dismiss handler
			$notice.on('click', '.notice-dismiss', function() {
				$notice.remove();
			});
		},

		/**
		 * Close the modal
		 */
		closeModal: function() {
			$('#aips-session-modal').hide();
			this.currentHistoryId = null;
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
