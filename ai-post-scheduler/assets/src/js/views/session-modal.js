import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';

/**
 * View Session Modal View Controller
 */
export const ViewSessionModalView = Backbone.View.extend({
	el: 'body',

	CLIENT_LOG_THRESHOLD: 20,
	currentHistoryId: null,
	currentLogCount: 0,

	events: {
		'click .aips-view-session': 'handleViewSession',
		'click .aips-copy-session-json': 'handleCopySessionJSON',
		'click .aips-download-session-json': 'handleDownloadSessionJSON',
		'click #aips-session-modal .aips-modal-close': 'closeModal',
		'click #aips-session-modal .aips-modal-overlay': 'closeModal',
		'click #aips-session-modal .aips-tab-nav a': 'handleTabSwitch',
		'click #aips-session-modal .aips-ai-component:not(.expanded)': 'handleAIComponentClick'
	},

	initialize() {
		$(document).on('keydown', this.handleKeyDown.bind(this));
	},

	handleKeyDown(e) {
		if (e.key === 'Escape' && $('#aips-session-modal').is(':visible')) {
			this.closeModal();
		}
	},

	handleViewSession(e) {
		e.preventDefault();
		const historyId = $(e.currentTarget).data('history-id');
		if (!historyId) {
			console.error('No history ID provided');
			return;
		}
		this.loadSessionData(historyId);
	},

	loadSessionData(historyId) {
		this.showLoadingModal();
		
		const ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl) || (window.aipsAjax && window.aipsAjax.ajaxUrl);
		const nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce) || (window.aipsAjax && window.aipsAjax.nonce);
		
		if (!ajaxUrl) {
			this.showError('AJAX URL not found. Please refresh the page and try again.');
			return;
		}
		
		if (!nonce) {
			this.showError('Security nonce not found. Please refresh the page and try again.');
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
			success: (response) => {
				if (response.success) {
					this.displaySessionModal(response.data);
				} else {
					this.showError(response.data.message || 'Failed to load session data.');
				}
			},
			error: (xhr, status, error) => {
				console.error('AJAX error:', status, error);
				this.showError('Failed to load session data. Please try again.');
			}
		});
	},

	showLoadingModal() {
		$('#aips-session-modal').show();
		$('#aips-session-title').text('Loading...');
		$('#aips-session-created').text('');
		$('#aips-session-completed').text('');
		$('#aips-logs-list').html('<p>Loading logs...</p>');
		$('#aips-ai-list').html('<p>Loading AI calls...</p>');
	},

	displaySessionModal(data) {
		this.currentHistoryId = data.history.id;
		this.currentLogCount = Array.isArray(data.logs) ? data.logs.length : 0;

		$('#aips-session-title').text(data.history.generated_title || 'N/A');
		$('#aips-session-created').text(data.history.created_at || 'N/A');
		$('#aips-session-completed').text(data.history.completed_at || 'N/A');
		
		this.renderLogs(data.logs);
		this.renderAICalls(data.ai_calls, data.component_revisions || {});
		
		$('#aips-session-modal').show();
	},

	renderLogs(logs) {
		let logsHtml = '';
		
		if (logs && logs.length > 0) {
			logs.forEach((log) => {
				let cssClass = '';
				if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.ERROR) {
					cssClass = 'error';
				} else if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.WARNING) {
					cssClass = 'warning';
				}
				
				const $logEntry = $('<div class="aips-log-entry"></div>');
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
	},

	renderAICalls(ai_calls, componentRevisions) {
		let aiHtml = '';
		const componentOrder = ['title', 'excerpt', 'content', 'featured_image'];
		const componentLabels = {
			title: 'Title',
			excerpt: 'Excerpt',
			content: 'Content',
			featured_image: 'Featured Image'
		};
		const callMap = {};
		
		if (Array.isArray(ai_calls)) {
			ai_calls.forEach((call) => {
				callMap[call.type] = call;
			});
		}
		
		componentOrder.forEach((componentType) => {
			const call = callMap[componentType] || { type: componentType, label: componentLabels[componentType] };
			const revisions = (componentRevisions && componentRevisions[componentType]) ? componentRevisions[componentType] : [];
			
			const $aiComponent = $('<div class="aips-ai-component"></div>').attr('data-component', componentType);
			$aiComponent.append($('<h4></h4>').text(call.label || componentLabels[componentType]));
			$aiComponent.append($('<p class="aips-ai-hint"></p>').text('Click to view request and response details'));
			
			const $aiDetails = $('<div class="aips-ai-details"></div>');
			
			if (call.request) {
				const $requestSection = $('<div class="aips-ai-section"></div>');
				$requestSection.append($('<h5></h5>').text('Request'));
				$requestSection.append(
					$('<div class="aips-json-viewer"><pre></pre></div>')
						.find('pre').text(JSON.stringify(call.request, null, 2)).end()
				);
				$aiDetails.append($requestSection);
			}
			
			if (call.response) {
				const $responseSection = $('<div class="aips-ai-section"></div>');
				$responseSection.append($('<h5></h5>').text('Response'));
				$responseSection.append(
					$('<div class="aips-json-viewer"><pre></pre></div>')
						.find('pre').text(JSON.stringify(call.response, null, 2)).end()
				);
				$aiDetails.append($responseSection);
			}
			
			const $revisionsSection = $('<div class="aips-ai-section"></div>');
			$revisionsSection.append($('<h5></h5>').text('Regenerations / Revisions'));
			
			if (Array.isArray(revisions) && revisions.length > 0) {
				const $list = $('<div class="aips-ai-revisions"></div>');
				revisions.forEach((revision) => {
					const $item = $('<div class="aips-ai-revision-item"></div>');
					$item.append($('<div class="aips-ai-revision-meta"></div>').text(revision.timestamp || ''));
					
					if (componentType === 'featured_image' && revision.value && revision.value.url) {
						$item.append(
							$('<div class="aips-ai-revision-value"></div>').append(
								$('<img class="aips-ai-revision-image" />').attr('src', revision.value.url).attr('alt', 'Revision')
							)
						);
					} else {
						let textValue = revision.value;
						if (typeof textValue !== 'string') {
							textValue = JSON.stringify(textValue || '');
						}
						if (textValue && textValue.length > 300) {
							textValue = textValue.substring(0, 300) + '...';
						}
						$item.append($('<div class="aips-ai-revision-value"></div>').text(textValue || '(empty)'));
					}
					
					$list.append($item);
				});
				$revisionsSection.append($list);
			} else {
				$revisionsSection.append($('<p class="aips-no-data"></p>').text('No regenerations found.'));
			}
			
			$aiDetails.append($revisionsSection);
			$aiComponent.append($aiDetails);
			aiHtml += $aiComponent[0].outerHTML;
		});
		
		$('#aips-ai-list').html(aiHtml);
	},

	handleTabSwitch(e) {
		e.preventDefault();
		const target = $(e.currentTarget).attr('href');
		const $tabs = $(e.currentTarget).closest('.aips-tabs');
		
		$tabs.find('.aips-tab-nav a').removeClass('active');
		$(e.currentTarget).addClass('active');
		
		$tabs.find('.aips-tab-content').hide();
		$tabs.find(target).show();
	},

	handleAIComponentClick(e) {
		$(e.currentTarget).addClass('expanded');
	},

	handleCopySessionJSON(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);
		
		if (!this.currentHistoryId) {
			this.showModalNotification('No session data available.', 'error');
			return;
		}
		
		$button.prop('disabled', true).text('Loading...');
		
		const ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl) || (window.aipsAjax && window.aipsAjax.ajaxUrl);
		const nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce) || (window.aipsAjax && window.aipsAjax.nonce);
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'aips_get_session_json',
				nonce: nonce,
				history_id: this.currentHistoryId
			},
			success: (response) => {
				if (response.success && response.data.json) {
					this.copyToClipboard(response.data.json, $button);
				} else {
					this.showModalNotification(response.data.message || 'Failed to generate JSON.', 'error');
					$button.prop('disabled', false).text('Copy Session JSON');
				}
			},
			error: (xhr, status, error) => {
				console.error('AJAX error:', status, error);
				this.showModalNotification('Failed to load session JSON. Please try again.', 'error');
				$button.prop('disabled', false).text('Copy Session JSON');
			}
		});
	},

	handleDownloadSessionJSON(e) {
		e.preventDefault();
		const $button = $(e.currentTarget);

		if (!this.currentHistoryId) {
			this.showModalNotification('No session data available for download.', 'error');
			return;
		}

		const ajaxUrl = window.ajaxurl || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.ajaxUrl) || (window.aipsAjax && window.aipsAjax.ajaxUrl);
		const nonce = window.aipsAjaxNonce || (window.aipsPostReviewL10n && window.aipsPostReviewL10n.nonce) || (window.aipsAjax && window.aipsAjax.nonce);

		if (this.currentLogCount <= this.CLIENT_LOG_THRESHOLD) {
			$button.prop('disabled', true).text('Preparing download...');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_session_json',
					nonce: nonce,
					history_id: this.currentHistoryId
				},
				success: (response) => {
					if (response.success && response.data.json) {
						const filename = 'aips-session-' + this.currentHistoryId + '.json';
						this.downloadJSON(response.data.json, filename);
						$button.prop('disabled', false).text('Download Session JSON');
						this.showNotice('Session JSON download started. Check your browser downloads.', 'success');
					} else {
						this.showModalNotification(response.data.message || 'Failed to generate JSON for download.', 'error');
						$button.prop('disabled', false).text('Download Session JSON');
					}
				},
				error: (xhr, status, error) => {
					console.error('AJAX error:', status, error);
					this.showModalNotification('Failed to load session JSON for download. Please try again.', 'error');
					$button.prop('disabled', false).text('Download Session JSON');
				}
			});
			return;
		}

		const form = document.createElement('form');
		form.method = 'POST';
		form.action = ajaxUrl;
		form.target = '_blank';

		const inputAction = document.createElement('input');
		inputAction.type = 'hidden';
		inputAction.name = 'action';
		inputAction.value = 'aips_download_session_json';
		form.appendChild(inputAction);

		const inputNonce = document.createElement('input');
		inputNonce.type = 'hidden';
		inputNonce.name = 'nonce';
		inputNonce.value = nonce;
		form.appendChild(inputNonce);

		const inputHistory = document.createElement('input');
		inputHistory.type = 'hidden';
		inputHistory.name = 'history_id';
		inputHistory.value = this.currentHistoryId;
		form.appendChild(inputHistory);

		document.body.appendChild(form);
		form.submit();
		document.body.removeChild(form);

		$button.prop('disabled', false).text('Download Session JSON');
		this.showNotice('Session JSON download started. Check your browser downloads.', 'success');
	},

	downloadJSON(jsonData, fileName) {
		const blob = new Blob([jsonData], { type: 'application/json' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = fileName;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	},

	copyToClipboard(text, $button) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(() => {
				this.showModalNotification('Session JSON copied to clipboard!', 'success');
				$button.prop('disabled', false).text('Copy Session JSON');
			}).catch((err) => {
				console.error('Failed to copy:', err);
				this.fallbackCopyToClipboard(text, $button);
			});
		} else {
			this.fallbackCopyToClipboard(text, $button);
		}
	},

	fallbackCopyToClipboard(text, $button) {
		const $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(text).select();
		
		try {
			const successful = document.execCommand('copy');
			if (successful) {
				this.showModalNotification('Session JSON copied to clipboard!', 'success');
			} else {
				this.showModalNotification('Failed to copy to clipboard.', 'error');
			}
		} catch (err) {
			console.error('Fallback copy failed:', err);
			this.showModalNotification('Failed to copy to clipboard.', 'error');
		}
		
		$temp.remove();
		$button.prop('disabled', false).text('Copy Session JSON');
	},

	showModalNotification(message, type) {
		$('.aips-notification').remove();
		
		const validTypes = ['success', 'error', 'warning', 'info'];
		const safeType = validTypes.indexOf(type) !== -1 ? type : 'info';
		
		const $notification = $('<div></div>')
			.addClass('aips-notification')
			.addClass('aips-notification-' + safeType)
			.text(message)
			.appendTo('.aips-modal-body');
		
		setTimeout(() => {
			$notification.fadeOut(function() {
				$(this).remove();
			});
		}, 3000);
	},

	showNotice(message, type) {
		if (window.AIPS && typeof window.AIPS.showNotice === 'function') {
			window.AIPS.showNotice(message, type);
			return;
		}
		
		const noticeType = type === 'error' ? 'notice-error' : 'notice-success';
		const $notice = $('<div class="notice ' + noticeType + ' is-dismissible aips-admin-notice">')
			.append($('<p>').text(message));

		$notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
		$('.wrap').first().prepend($notice);

		$notice.on('click', '.notice-dismiss', function() {
			$notice.remove();
		});
		
		setTimeout(() => {
			$notice.fadeOut(400, function() {
				$(this).remove();
			});
		}, 5000);
	},

	closeModal(e) {
		if (e) e.preventDefault();
		$('#aips-session-modal').hide();
		this.currentHistoryId = null;
		this.currentLogCount = 0;
	},

	showError(message) {
		this.showModalNotification(message, 'error');
		setTimeout(() => {
			this.closeModal();
		}, 3000);
	}
});
