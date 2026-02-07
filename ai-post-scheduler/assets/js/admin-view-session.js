/**
 * View Session Modal JavaScript
 *
 * Reusable module for displaying post generation session data including logs and AI calls.
 * This module can be used across multiple admin pages (Generated Posts, Post Review, etc.)
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */
(function($) {
	'use strict';
	
	// Ensure AIPS object exists
	window.AIPS = window.AIPS || {};
	
	// Configuration constants
	var CLIENT_LOG_THRESHOLD = 20; // Threshold for using client-side vs server-side JSON download
	
	// Session state
	var currentHistoryId = null;
	var currentLogCount = 0;
	
	/**
	 * Initialize View Session functionality
	 * This should be called on document ready
	 */
	window.AIPS.initViewSession = function() {
		bindEvents();
	};
	
	/**
	 * Bind event handlers
	 */
	function bindEvents() {
		// View Session button handler
		$(document).on('click', '.aips-view-session', handleViewSession);
		
		// Copy Session JSON button handler
		$(document).on('click', '.aips-copy-session-json', handleCopySessionJSON);
		
		// Download Session JSON button handler
		$(document).on('click', '.aips-download-session-json', handleDownloadSessionJSON);

		// Close modal handlers
		$(document).on('click', '.aips-modal-close, .aips-modal-overlay', closeModal);
		
		// Tab navigation
		$(document).on('click', '.aips-tab-nav a', handleTabSwitch);
		
		// Toggle AI component details
		$(document).on('click', '.aips-ai-component:not(.expanded)', handleAIComponentClick);
		
		// ESC key to close modal
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#aips-session-modal').is(':visible')) {
				closeModal();
			}
		});
	}
	
	/**
	 * Handle View Session button click
	 */
	function handleViewSession(e) {
		e.preventDefault();
		var historyId = $(e.currentTarget).data('history-id');
		
		if (!historyId) {
			console.error('No history ID provided');
			return;
		}
		
		loadSessionData(historyId);
	}
	
	/**
	 * Load session data via AJAX
	 */
	function loadSessionData(historyId) {
		// Show loading state
		showLoadingModal();
		
		// Get AJAX URL and nonce from global variables
		var ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl);
		var nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce);
		
		if (!ajaxUrl) {
			showError('AJAX URL not found. Please refresh the page and try again.');
			return;
		}
		
		if (!nonce) {
			showError('Security nonce not found. Please refresh the page and try again.');
			return;
		}
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_get_post_session',
				nonce: nonce,
				history_id: historyId
			},
			success: function(response) {
				if (response.success) {
					displaySessionModal(response.data);
				} else {
					showError(response.data.message || 'Failed to load session data.');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX error:', status, error);
				showError('Failed to load session data. Please try again.');
			}
		});
	}
	
	/**
	 * Show loading state in modal
	 */
	function showLoadingModal() {
		$('#aips-session-modal').show();
		$('#aips-session-title').text('Loading...');
		$('#aips-session-created').text('');
		$('#aips-session-completed').text('');
		$('#aips-logs-list').html('<p>Loading logs...</p>');
		$('#aips-ai-list').html('<p>Loading AI calls...</p>');
	}
	
	/**
	 * Display session data in modal
	 */
	function displaySessionModal(data) {
		// Store current history ID for JSON export
		currentHistoryId = data.history.id;
		currentLogCount = Array.isArray(data.logs) ? data.logs.length : 0;

		// Update session info
		$('#aips-session-title').text(data.history.generated_title || 'N/A');
		$('#aips-session-created').text(data.history.created_at || 'N/A');
		$('#aips-session-completed').text(data.history.completed_at || 'N/A');
		
		// Display logs
		renderLogs(data.logs);
		
		// Display AI calls
		renderAICalls(data.ai_calls);
		
		// Show modal
		$('#aips-session-modal').show();
	}
	
	/**
	 * Render logs tab content
	 */
	function renderLogs(logs) {
		var logsHtml = '';
		
		if (logs.length > 0) {
			logs.forEach(function(log) {
				// Determine CSS class based on log type (safe known values)
				var cssClass = '';
				if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.ERROR) {
					cssClass = 'error';
				} else if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.WARNING) {
					cssClass = 'warning';
				}
				
				// Create log entry element safely
				var $logEntry = $('<div class="aips-log-entry"></div>');
				if (cssClass) {
					$logEntry.addClass(cssClass);
				}
				
				$logEntry.append(
					$('<h4></h4>').text(log.type + ' - ' + log.log_type),
					$('<div class="aips-log-timestamp"></div>').text(log.timestamp),
					$('<div class="aips-json-viewer"><pre></pre></div>').find('pre').text(JSON.stringify(log.details, null, 2)).end()
				);
				
				logsHtml += $logEntry[0].outerHTML;
			});
		} else {
			logsHtml = '<p class="aips-no-data">No log entries found.</p>';
		}
		
		$('#aips-logs-list').html(logsHtml);
	}
	
	/**
	 * Render AI calls tab content
	 */
	function renderAICalls(ai_calls) {
		var aiHtml = '';
		
		if (ai_calls.length > 0) {
			ai_calls.forEach(function(call) {
				// Create AI component element safely using jQuery
				var $aiComponent = $('<div class="aips-ai-component"></div>')
					.attr('data-component', call.type);
				
				$aiComponent.append($('<h4></h4>').text(call.label));
				$aiComponent.append($('<p class="aips-ai-hint"></p>').text('Click to view request and response details'));
				
				var $aiDetails = $('<div class="aips-ai-details"></div>');
				
				if (call.request) {
					var $requestSection = $('<div class="aips-ai-section"></div>');
					$requestSection.append($('<h5></h5>').text('Request'));
					$requestSection.append(
						$('<div class="aips-json-viewer"><pre></pre></div>')
							.find('pre').text(JSON.stringify(call.request, null, 2)).end()
					);
					$aiDetails.append($requestSection);
				}
				
				if (call.response) {
					var $responseSection = $('<div class="aips-ai-section"></div>');
					$responseSection.append($('<h5></h5>').text('Response'));
					$responseSection.append(
						$('<div class="aips-json-viewer"><pre></pre></div>')
							.find('pre').text(JSON.stringify(call.response, null, 2)).end()
					);
					$aiDetails.append($responseSection);
				}
				
				$aiComponent.append($aiDetails);
				aiHtml += $aiComponent[0].outerHTML;
			});
		} else {
			aiHtml = '<p class="aips-no-data">No AI calls found.</p>';
		}
		
		$('#aips-ai-list').html(aiHtml);
	}
	
	/**
	 * Handle tab switching
	 */
	function handleTabSwitch(e) {
		e.preventDefault();
		var target = $(e.currentTarget).attr('href');
		
		$('.aips-tab-nav a').removeClass('active');
		$(e.currentTarget).addClass('active');
		
		$('.aips-tab-content').hide();
		$(target).show();
	}
	
	/**
	 * Handle AI component click to expand details
	 */
	function handleAIComponentClick(e) {
		$(e.currentTarget).addClass('expanded');
	}
	
	/**
	 * Handle Copy Session JSON button click
	 */
	function handleCopySessionJSON(e) {
		e.preventDefault();
		var $button = $(e.currentTarget);
		
		// Check if we have a history ID stored
		if (!currentHistoryId) {
			showModalNotification('No session data available.', 'error');
			return;
		}
		
		// Disable button and show loading state
		$button.prop('disabled', true).text('Loading...');
		
		// Get AJAX URL and nonce
		var ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl);
		var nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce);
		
		// Fetch the JSON data
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_get_session_json',
				nonce: nonce,
				history_id: currentHistoryId
			},
			success: function(response) {
				if (response.success && response.data.json) {
					// Copy to clipboard
					copyToClipboard(response.data.json, $button);
				} else {
					showModalNotification(response.data.message || 'Failed to generate JSON.', 'error');
					$button.prop('disabled', false).text('Copy Session JSON');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX error:', status, error);
				showModalNotification('Failed to load session JSON. Please try again.', 'error');
				$button.prop('disabled', false).text('Copy Session JSON');
			}
		});
	}
	
	/**
	 * Handle Download Session JSON button click
	 */
	function handleDownloadSessionJSON(e) {
		e.preventDefault();
		var $button = $(e.currentTarget);

		// Check if we have a history ID stored
		if (!currentHistoryId) {
			showModalNotification('No session data available for download.', 'error');
			return;
		}

		// Get AJAX URL and nonce
		var ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl);
		var nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce);

		if (currentLogCount <= CLIENT_LOG_THRESHOLD) {
			// Small session: fetch the JSON via AJAX and trigger client-side download
			$button.prop('disabled', true).text('Preparing download...');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_session_json',
					nonce: nonce,
					history_id: currentHistoryId
				},
				success: function(response) {
					if (response.success && response.data.json) {
						var filename = 'aips-session-' + currentHistoryId + '.json';
						downloadJSON(response.data.json, filename);
						$button.prop('disabled', false).text('Download Session JSON');
						showNotice('Session JSON download started. Check your browser downloads.', 'success');
					} else {
						showModalNotification(response.data.message || 'Failed to generate JSON for download.', 'error');
						$button.prop('disabled', false).text('Download Session JSON');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', status, error);
					showModalNotification('Failed to load session JSON for download. Please try again.', 'error');
					$button.prop('disabled', false).text('Download Session JSON');
				}
			});
			return;
		}

		// Large session: use a form POST to the download endpoint
		var form = document.createElement('form');
		form.method = 'POST';
		form.action = ajaxUrl;
		form.target = '_blank';

		var inputAction = document.createElement('input');
		inputAction.type = 'hidden';
		inputAction.name = 'action';
		inputAction.value = 'aips_download_session_json';
		form.appendChild(inputAction);

		var inputNonce = document.createElement('input');
		inputNonce.type = 'hidden';
		inputNonce.name = 'nonce';
		inputNonce.value = nonce;
		form.appendChild(inputNonce);

		var inputHistory = document.createElement('input');
		inputHistory.type = 'hidden';
		inputHistory.name = 'history_id';
		inputHistory.value = currentHistoryId;
		form.appendChild(inputHistory);

		document.body.appendChild(form);
		form.submit();
		document.body.removeChild(form);

		$button.prop('disabled', false).text('Download Session JSON');
		showNotice('Session JSON download started. Check your browser downloads.', 'success');
	}
	
	/**
	 * Download JSON data as a file
	 */
	function downloadJSON(jsonData, fileName) {
		var blob = new Blob([jsonData], { type: 'application/json' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = fileName;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}
	
	/**
	 * Copy text to clipboard
	 */
	function copyToClipboard(text, $button) {
		// Try modern clipboard API first
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function() {
				showModalNotification('Session JSON copied to clipboard!', 'success');
				$button.prop('disabled', false).text('Copy Session JSON');
			}).catch(function(err) {
				console.error('Failed to copy:', err);
				fallbackCopyToClipboard(text, $button);
			});
		} else {
			// Fallback for older browsers
			fallbackCopyToClipboard(text, $button);
		}
	}
	
	/**
	 * Fallback clipboard copy method for older browsers
	 */
	function fallbackCopyToClipboard(text, $button) {
		var $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(text).select();
		
		try {
			var successful = document.execCommand('copy');
			if (successful) {
				showModalNotification('Session JSON copied to clipboard!', 'success');
			} else {
				showModalNotification('Failed to copy to clipboard.', 'error');
			}
		} catch (err) {
			console.error('Fallback copy failed:', err);
			showModalNotification('Failed to copy to clipboard.', 'error');
		}
		
		$temp.remove();
		$button.prop('disabled', false).text('Copy Session JSON');
	}
	
	/**
	 * Show notification message in modal
	 */
	function showModalNotification(message, type) {
		// Remove existing notifications
		$('.aips-notification').remove();
		
		// Validate type to prevent XSS (only allow known values)
		var validTypes = ['success', 'error', 'warning', 'info'];
		var safeType = validTypes.indexOf(type) !== -1 ? type : 'info';
		
		// Create notification element safely
		var $notification = $('<div></div>')
			.addClass('aips-notification')
			.addClass('aips-notification-' + safeType)
			.text(message)
			.appendTo('.aips-modal-body');
		
		// Auto-hide after 3 seconds
		setTimeout(function() {
			$notification.fadeOut(function() {
				$(this).remove();
			});
		}, 3000);
	}
	
	/**
	 * Show notice at the top of the page
	 */
	function showNotice(message, type) {
		// Try to use existing showNotice function if available
		if (typeof window.AIPS !== 'undefined' && typeof window.AIPS.showNotice === 'function') {
			window.AIPS.showNotice(message, type);
			return;
		}
		
		// Fallback: create a simple notice
		var noticeType = type === 'error' ? 'notice-error' : 'notice-success';
		var $notice = $('<div class="notice ' + noticeType + ' is-dismissible aips-admin-notice">')
			.append($('<p>').text(message));

		// Add dismiss button
		$notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

		// Insert notice at top of main .wrap
		$('.wrap').first().prepend($notice);

		// Dismiss handler
		$notice.on('click', '.notice-dismiss', function() {
			$notice.remove();
		});
		
		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			$notice.fadeOut(400, function() {
				$(this).remove();
			});
		}, 5000);
	}
	
	/**
	 * Close the modal
	 */
	function closeModal() {
		$('#aips-session-modal').hide();
		currentHistoryId = null;
		currentLogCount = 0;
	}
	
	/**
	 * Show error message
	 */
	function showError(message) {
		alert(message);
		closeModal();
	}
	
	// Initialize on document ready
	$(document).ready(function() {
		window.AIPS.initViewSession();
	});
	
})(jQuery);
