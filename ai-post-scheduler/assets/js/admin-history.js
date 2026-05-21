/**
 * History Page JavaScript
 *
 * Manages the History admin page: search/filter, pagination, row selection,
 * bulk delete, retry, and the logs modal that renders all aips_history_log
 * entries for a selected history container.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * AIPS.History — self-contained module for the History admin page.
	 *
	 * Follows the same init() / bindEvents() naming convention used throughout
	 * this plugin (e.g. authors.js / GenerationQueueModule) so the page can
	 * be bootstrapped with a single AIPS.History.init() call, without
	 * polluting the global AIPS namespace with page-specific handlers.
	 */
	AIPS.History = {

		/* ------------------------------------------------------------------ */
		/* State                                                                */
		/* ------------------------------------------------------------------ */

		/** @type {string} Current status filter value */
		statusFilter: '',

		/** @type {string} Raw search query as entered by the user */
		searchQuery: '',
		domainFilter: '',
		actorFilter: '',
		correlationId: '',
		dateFrom: '',
		dateTo: '',

		/* ------------------------------------------------------------------ */
		/* Init / events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the History module.
		 */
		init: function () {
			this.statusFilter = $('#aips-filter-status').val() || '';
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';
			this.searchQuery  = $('#aips-history-search-input').val() || '';
			this.syncSearchClearButton();
			this.bindEvents();
			this.maybeOpenFromQuery();
		},

		/**
		 * Register all event listeners for the History admin page.
		 */
		bindEvents: function () {
			// Open logs modal.
			$(document).on('click', '.aips-view-history-logs', this.openLogsModal.bind(this));

			// Collapsible log-detail sections inside the modal.
			$(document).on('click', '.aips-log-toggle', this.toggleLogDetail.bind(this));

			// Copy log detail to clipboard.
			$(document).on('click', '.aips-log-copy', this.copyLogDetail.bind(this));

			// Log type filter tabs inside the modal.
			$(document).on('click', '.aips-log-type-filter-btn', this.filterLogsByType.bind(this));
			$(document).on('change', '.aips-json-viewer-toggle', this.toggleJsonViewerMode.bind(this));

			// Close modal via close button or backdrop click.
			$(document).on('click', '#aips-history-logs-modal .aips-modal-close', this.closeLogsModal.bind(this));
			$(document).on('click', '#aips-history-logs-modal', this.closeLogsModalOnOverlay.bind(this));

			// Select-all and individual row checkboxes.
			$(document).on('change', '#aips-cb-select-all', this.toggleSelectAll.bind(this));
			$(document).on('change', '.aips-history-cb', this.onRowCheckboxChange.bind(this));

			// Bulk delete.
			$(document).on('click', '#aips-delete-selected-btn', this.deleteSelected.bind(this));

			// Individual row delete.
			$(document).on('click', '.aips-delete-history', this.deleteSingleItem.bind(this));

			// Retry failed generation.
			$(document).on('click', '.aips-retry-generation', this.retryGeneration.bind(this));

			// Clear history (failed / all).
			$(document).on('click', '.aips-clear-history', this.clearHistory.bind(this));

			// Reload button.
			$(document).on('click', '#aips-reload-history-btn', this.onReloadClick.bind(this));

			// Pagination links.
			$(document).on(
				'click',
				'.aips-history-page-link, .aips-history-page-prev, .aips-history-page-next',
				this.loadPage.bind(this)
			);

			// Filter button and status dropdown.
			$(document).on('click', '#aips-filter-btn', this.applyFilter.bind(this));
			$(document).on('change', '#aips-filter-status', this.applyFilter.bind(this));

			// Search: live client-side row filter + server reload on Enter.
			$(document).on('input', '#aips-history-search-input', this.onSearchInput.bind(this));
			$(document).on('keydown', '#aips-history-search-input', this.onSearchKeydown.bind(this));
			$(document).on('click', '#aips-history-search-clear', this.clearSearch.bind(this));
			$(document).on('click', '.aips-clear-history-search-btn', this.clearSearch.bind(this));

			// Export CSV.
			$(document).on('click', '#aips-export-history-btn', this.exportHistory.bind(this));
		},



		/**
		 * Auto-open a specific history container from query args when available.
		 */
		maybeOpenFromQuery: function () {
			var params = new URLSearchParams(window.location.search || '');
			var historyId = parseInt(params.get('history_id') || 0, 10);
			var postId = parseInt(params.get('post_id') || 0, 10);

			if (postId > 0 && !this.searchQuery) {
				this.searchQuery = String(postId);
				$('#aips-history-search-input').val(String(postId));
				this.syncSearchClearButton();
				$('#aips-history-search-input').trigger('input');
			}

			if (historyId > 0) {
				this.openLogsModalFromId(historyId);
			}
		},

		/**
		 * Open history logs modal for a known history id.
		 *
		 * @param {number} historyId History container id.
		 */
		openLogsModalFromId: function (historyId) {
			var $trigger = $('<button type="button" class="aips-view-history-logs" data-id="' + historyId + '"></button>');
			this.openLogsModal({
				preventDefault: function () {},
				stopPropagation: function () {},
				currentTarget: $trigger.get(0)
			});
		},

		/* ------------------------------------------------------------------ */
		/* Logs modal                                                           */
		/* ------------------------------------------------------------------ */

		/**
		 * Fetch and display all history_log entries for the clicked container.
		 *
		 * @param {Event} e - Click event from an `.aips-view-history-logs` element.
		 */
		openLogsModal: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var historyId = $(e.currentTarget).data('id');
			if (!historyId) {
				return;
			}

			var self     = this;
			var $modal   = $('#aips-history-logs-modal');
			var $content = $('#aips-history-logs-content');
			var $title   = $('#aips-history-logs-modal-title');
			var T        = AIPS.Templates;

			$title.text(aipsHistoryL10n.historyDetailsTitle || 'History Details');
			$content.html(T.render('aips-tmpl-history-loading-msg', {
				text: aipsHistoryL10n.loadingLogs || 'Loading logs\u2026'
			}));
			$modal.fadeIn(200);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_history_logs',
					nonce: aipsAjax.nonce,
					history_id: historyId
				},
				success: function (response) {
					if (!response.success) {
						$content.html(T.render('aips-tmpl-history-error-msg', {
							message: response.data && response.data.message
								? response.data.message
								: (aipsHistoryL10n.errorLoading || 'Error loading logs.')
						}));
						return;
					}

					var container = response.data.container;
					var logs      = response.data.logs;

					// Set modal title to the generated post title when available.
					if (container.generated_title) {
						$title.text(container.generated_title);
					} else {
						$title.text(
							(aipsHistoryL10n.historyDetailsTitle || 'History Details')
							+ ' #' + container.id
						);
					}

					$content.html(self.renderLogsModalContent(container, logs));
				},
				error: function () {
					$content.html(T.render('aips-tmpl-history-error-msg', {
						message: aipsHistoryL10n.errorLoading || 'Error loading logs.'
					}));
				}
			});
		},

		/**
		 * Toggle a collapsible log-detail section inside the modal.
		 *
		 * @param {Event} e - Click event from an `.aips-log-toggle` element.
		 */
		toggleLogDetail: function (e) {
			e.preventDefault();
			var $button = $(e.currentTarget);
			var targetSelector = $button.data('target');
			var $target = $(targetSelector);
			if ($target.length) {
				var showLabel = aipsHistoryL10n.showDetails || 'Show details';
				var hideLabel = aipsHistoryL10n.hideDetails || 'Hide details';

				$target.slideToggle(150, function () {
					$button.text($target.is(':visible') ? hideLabel : showLabel);
				});
			}
		},

		/**
		 * Show temporary copied-state feedback on a copy button.
		 *
		 * @param {jQuery} $button    Copy button element.
		 * @param {string} copyLabel   Default button label.
		 * @param {string} copiedLabel Success button label.
		 */
		showCopySuccess: function ($button, copyLabel, copiedLabel) {
			$button.text(copiedLabel);
			setTimeout(function () {
				$button.text(copyLabel);
			}, 2000);
		},

		/**
		 * Copy log detail text using the legacy execCommand fallback.
		 *
		 * @param {jQuery} $target Detail container.
		 * @return {boolean} True when the fallback copy succeeded.
		 */
		copyLogDetailFallback: function ($target) {
			var $pre = $target.find('pre');
			var range;
			var sel;

			if (!$pre.length || !$pre[0]) {
				return false;
			}

			try {
				// Fallback: expose the detail block, select text, copy.
				$target.show();
				range = document.createRange();
				range.selectNodeContents($pre[0]);
				sel = window.getSelection();

				if (!sel) {
					return false;
				}

				sel.removeAllRanges();
				sel.addRange(range);

				if (!document.execCommand('copy')) {
					sel.removeAllRanges();
					return false;
				}

				sel.removeAllRanges();

				return true;
			} catch (error) {
				if (sel) {
					sel.removeAllRanges();
				}

				return false;
			}
		},

		/**
		 * Copy the full log detail text for a row.
		 *
		 * @param {Event} e Click event.
		 */
		copyLogDetail: function (e) {
			e.preventDefault();

			var self           = this;
			var $button        = $(e.currentTarget);
			var targetSelector = $button.data('target');
			var $target        = $(targetSelector);
			var text           = $target.find('pre').text();
			var copyLabel      = aipsHistoryL10n.copyDetails || 'Copy';
			var copiedLabel    = aipsHistoryL10n.copiedDetails || 'Copied!';

			if (!text) {
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text)
					.then(function () {
						self.showCopySuccess($button, copyLabel, copiedLabel);
					})
					.catch(function () {
						if (self.copyLogDetailFallback($target)) {
							self.showCopySuccess($button, copyLabel, copiedLabel);
						}
					});
			} else if (self.copyLogDetailFallback($target)) {
				self.showCopySuccess($button, copyLabel, copiedLabel);
			}
		},

		/**
		 * Filter the visible log rows to only those matching the selected type.
		 *
		 * @param {Event} e - Click event from an `.aips-log-type-filter-btn` element.
		 */
		filterLogsByType: function (e) {
			e.preventDefault();
			var $btn    = $(e.currentTarget);
			var typeId  = $btn.data('type-id');
			var $modal  = $('#aips-history-logs-modal');

			$modal.find('.aips-log-type-filter-btn')
				.removeClass('aips-btn-primary')
				.addClass('aips-btn-ghost');
			$btn.removeClass('aips-btn-ghost').addClass('aips-btn-primary');

			var $rows = $modal.find('.aips-history-logs-table tbody tr');
			if (!typeId || typeId === 'all') {
				$rows.show();
			} else {
				$rows.each(function () {
					var rowTypes = String($(this).attr('data-type-ids') || $(this).data('type-id') || '')
						.split(',')
						.map(function (value) {
							return $.trim(String(value));
						})
						.filter(Boolean);
					$(this).toggle(rowTypes.indexOf(String(typeId)) !== -1);
				});
			}
		},

		/**
		 * Toggle JSON tree view vs. raw JSON for the current modal content.
		 *
		 * @param {Event} e Change event.
		 */
		toggleJsonViewerMode: function (e) {
			var $toggle = $(e.currentTarget);
			var $renderer = $toggle.closest('.aips-history-log-renderer');
			if (!$renderer.length) {
				return;
			}

			$renderer.toggleClass('aips-json-viewer-enabled', $toggle.is(':checked'));
		},

		/**
		 * Return whether a log entry is an AI request row.
		 *
		 * @param {Object} log Log entry.
		 * @return {boolean}
		 */
		isAiRequestLog: function (log) {
			return String(log && log.history_type_id) === '5' || String(log && log.log_type) === 'ai_request';
		},

		/**
		 * Return whether a log entry is an AI response row.
		 *
		 * @param {Object} log Log entry.
		 * @return {boolean}
		 */
		isAiResponseLog: function (log) {
			return String(log && log.history_type_id) === '6' || String(log && log.log_type) === 'ai_response';
		},

		/**
		 * Normalize a freeform phase string into a predictable lookup key.
		 *
		 * @param {*} value Raw candidate value.
		 * @return {string}
		 */
		normalizeAiPhaseKey: function (value) {
			return String(value || '')
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_+|_+$/g, '');
		},

		/**
		 * Infer which content phase an AI log belongs to.
		 *
		 * @param {Object} log Log entry.
		 * @return {string}
		 */
		deriveAiPhaseKey: function (log) {
			var details = log && log.details ? log.details : {};
			var candidates = [
				details.phase,
				details.component,
				details.content_type,
				details.request_type,
				details.target,
				details.section,
				details.field,
				details.item_type,
				details.stage
			];
			var i;

			for (i = 0; i < candidates.length; i++) {
				if (candidates[i]) {
					return this.normalizeAiPhaseKey(candidates[i]);
				}
			}

			var message = String(details.message || '');
			var messageMatch = message.match(/for\s+(.+?)(?:[\.:]|$)/i);
			if (messageMatch && messageMatch[1]) {
				return this.normalizeAiPhaseKey(messageMatch[1]);
			}

			if (message.toLowerCase().indexOf('title') !== -1) {
				return 'post_title';
			}
			if (message.toLowerCase().indexOf('excerpt') !== -1) {
				return 'post_excerpt';
			}
			if (message.toLowerCase().indexOf('featured image') !== -1 || message.toLowerCase().indexOf('image') !== -1) {
				return 'featured_image';
			}
			if (message.toLowerCase().indexOf('content') !== -1 || message.toLowerCase().indexOf('article') !== -1) {
				return 'post_content';
			}

			return 'general';
		},

		/**
		 * Convert an AI phase key into a user-friendly label.
		 *
		 * @param {string} phaseKey Normalized phase key.
		 * @return {string}
		 */
		humanizeAiPhaseLabel: function (phaseKey) {
			var map = {
				post_title: 'Post Title',
				title: 'Post Title',
				post_content: 'Post Content',
				content: 'Post Content',
				article: 'Post Content',
				body: 'Post Content',
				post_excerpt: 'Post Excerpt',
				excerpt: 'Post Excerpt',
				featured_image: 'Featured Image',
				image: 'Featured Image',
				topic: 'Topic',
				research: 'Research',
				general: 'General'
			};
			var normalized = this.normalizeAiPhaseKey(phaseKey || 'general');

			if (map[normalized]) {
				return map[normalized];
			}

			return normalized
				.replace(/_/g, ' ')
				.replace(/\b\w/g, function (letter) {
					return letter.toUpperCase();
				});
		},

		/**
		 * Extract log details excluding the summary message/timestamp fields.
		 *
		 * @param {Object} log Log entry.
		 * @return {Object}
		 */
		extractExtraDetails: function (log) {
			var extra = {};
			var details = log && log.details ? log.details : {};

			$.each(details, function (key, value) {
				if (key !== 'message' && key !== 'timestamp') {
					extra[key] = value;
				}
			});

			return extra;
		},

		/**
		 * Escape plaintext while preserving visible line breaks.
		 *
		 * @param {*} value Raw text value.
		 * @return {string}
		 */
		escapeWithLineBreaks: function (value) {
			return AIPS.Templates.escape(String(value === undefined || value === null ? '' : value))
				.replace(/\r\n|\r|\n/g, '<br>');
		},

		/**
		 * Render a scalar JSON value for the tree viewer.
		 *
		 * @param {*} value Scalar value.
		 * @return {string}
		 */
		renderJsonScalar: function (value) {
			var type = typeof value;

			if (value === null) {
				return '<span class="aips-json-value aips-json-value-null">null</span>';
			}

			if (type === 'string') {
				return '<span class="aips-json-value aips-json-value-string">"' + this.escapeWithLineBreaks(value) + '"</span>';
			}

			if (type === 'number') {
				return '<span class="aips-json-value aips-json-value-number">' + AIPS.Templates.escape(String(value)) + '</span>';
			}

			if (type === 'boolean') {
				return '<span class="aips-json-value aips-json-value-boolean">' + AIPS.Templates.escape(String(value)) + '</span>';
			}

			return '<span class="aips-json-value">' + AIPS.Templates.escape(String(value)) + '</span>';
		},

		/**
		 * Render a recursive tree for a JSON-compatible value.
		 *
		 * @param {*} value JSON-compatible value.
		 * @param {string|null} label Key/index label.
		 * @param {number} depth Current depth.
		 * @return {string}
		 */
		renderJsonTree: function (value, label, depth) {
			var self = this;
			var isArray = Array.isArray(value);
			var isObject = value && typeof value === 'object';
			var labelHtml = label !== null && label !== undefined
				? '<span class="aips-json-key">' + AIPS.Templates.escape(String(label)) + '</span>: '
				: '';

			if (!isObject) {
				return '<div class="aips-json-leaf">' + labelHtml + self.renderJsonScalar(value) + '</div>';
			}

			var entries = [];
			if (isArray) {
				$.each(value, function (index, item) {
					entries.push({ label: index, value: item });
				});
			} else {
				$.each(value, function (key, item) {
					entries.push({ label: key, value: item });
				});
			}

			if (!entries.length) {
				return '<div class="aips-json-leaf">' + labelHtml + '<span class="aips-json-value aips-json-value-empty">' + (isArray ? '[]' : '{}') + '</span></div>';
			}

			var summary = '<span class="aips-json-summary-label">' + labelHtml + '</span>'
				+ '<span class="aips-json-meta">' + (isArray ? 'Array[' + entries.length + ']' : 'Object{' + entries.length + '}') + '</span>';
			var html = '<details class="aips-json-node"' + (depth <= 1 ? ' open' : '') + '>';
			html += '<summary class="aips-json-summary">' + summary + '</summary>';
			html += '<div class="aips-json-children">';

			$.each(entries, function (index, entry) {
				html += self.renderJsonTree(entry.value, entry.label, depth + 1);
			});

			html += '</div></details>';

			return html;
		},

		/**
		 * Render the details block containing tree and raw JSON views.
		 *
		 * @param {string} rowId DOM id for the expandable panel.
		 * @param {Object} extra Structured details payload.
		 * @return {string}
		 */
		renderJsonDetailBlock: function (rowId, extra) {
			var rawJson = JSON.stringify(extra, null, 2);
			var treeHtml = this.renderJsonTree(extra, null, 0);

			return ''
				+ '<div class="aips-history-log-detail-actions">'
				+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" data-target="#' + AIPS.Templates.escape(rowId) + '" style="font-size:11px;">' + AIPS.Templates.escape(aipsHistoryL10n.showDetails || 'Show details') + '</button>'
				+ '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-copy" data-target="#' + AIPS.Templates.escape(rowId) + '" style="font-size:11px;margin-left:4px;">' + AIPS.Templates.escape(aipsHistoryL10n.copyDetails || 'Copy') + '</button>'
				+ '</div>'
				+ '<div id="' + AIPS.Templates.escape(rowId) + '" class="aips-history-log-detail-panel" style="display:none;margin-top:8px;">'
				+ '<div class="aips-json-tree-mode">' + treeHtml + '</div>'
				+ '<div class="aips-json-raw-mode"><pre style="max-height:240px;overflow:auto;white-space:pre-wrap;font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;"><code>' + AIPS.Templates.escape(rawJson) + '</code></pre></div>'
				+ '</div>';
		},

		/**
		 * Render a log message paragraph with preserved line breaks.
		 *
		 * @param {string} message Message text.
		 * @return {string}
		 */
		renderLogMessage: function (message) {
			return '<p style="margin:0 0 6px;">' + this.escapeWithLineBreaks(message) + '</p>';
		},

		/**
		 * Render one AI sub-section within a paired AI request/response row.
		 *
		 * @param {Object} log Log entry.
		 * @param {string} label Section label.
		 * @param {string} rowId Base row id.
		 * @return {string}
		 */
		renderAiSubSection: function (log, label, rowId) {
			var extra = this.extractExtraDetails(log);
			var html = '<div class="aips-ai-log-section">';
			html += '<div class="aips-ai-log-section-header">';
			html += '<strong>' + AIPS.Templates.escape(label) + '</strong>';

			if (log && log.timestamp) {
				html += '<span class="aips-ai-log-section-time">' + AIPS.Templates.escape(log.timestamp) + '</span>';
			}

			html += '</div>';

			if (log && log.details && log.details.message) {
				html += this.renderLogMessage(log.details.message);
			}

			if (Object.keys(extra).length > 0) {
				html += this.renderJsonDetailBlock(rowId, extra);
			}

			html += '</div>';

			return html;
		},

		/**
		 * Combine AI request/response rows into linear paired display rows.
		 *
		 * @param {Object[]} logs Raw log entries.
		 * @return {Object[]}
		 */
		buildDisplayLogs: function (logs) {
			var self = this;
			var displayLogs = [];
			var usedResponses = {};

			$.each(logs, function (index, log) {
				if (self.isAiResponseLog(log) && usedResponses[index]) {
					return;
				}

				if (!self.isAiRequestLog(log)) {
					displayLogs.push({
						timestamp: log.timestamp,
						typeLabel: log.type_label,
						typeClass: self.typeClass(log.history_type_id),
						logType: log.log_type,
						typeIds: [String(log.history_type_id)],
						detailsHtml: (function () {
							var extra = self.extractExtraDetails(log);
							var detailsHtml = '';

							if (log.details && log.details.message) {
								detailsHtml += self.renderLogMessage(log.details.message);
							}

							if (Object.keys(extra).length > 0) {
								detailsHtml += self.renderJsonDetailBlock('aips-log-detail-' + index, extra);
							}

							return detailsHtml;
						})()
					});
					return;
				}

				var phaseKey = self.deriveAiPhaseKey(log);
				var responseLog = null;
				var responseIndex = -1;
				var searchIndex;

				for (searchIndex = index + 1; searchIndex < logs.length; searchIndex++) {
					if (usedResponses[searchIndex]) {
						continue;
					}

					if (self.isAiResponseLog(logs[searchIndex]) && self.deriveAiPhaseKey(logs[searchIndex]) === phaseKey) {
						responseLog = logs[searchIndex];
						responseIndex = searchIndex;
						break;
					}
				}

				if (responseIndex !== -1) {
					usedResponses[responseIndex] = true;
				}

				var phaseLabel = self.humanizeAiPhaseLabel(phaseKey);
				var detailsHtml = '<div class="aips-ai-log-pair">';
				detailsHtml += self.renderAiSubSection(log, aipsHistoryL10n.aiRequestLabel || 'AI Request', 'aips-log-detail-' + index + '-request');

				if (responseLog) {
					detailsHtml += self.renderAiSubSection(responseLog, aipsHistoryL10n.aiResponseLabel || 'AI Response', 'aips-log-detail-' + index + '-response');
				}

				detailsHtml += '</div>';

				displayLogs.push({
					timestamp: log.timestamp,
					typeLabel: aipsHistoryL10n.aiRequestResponseLabel || 'AI Request / Response',
					typeClass: self.typeClass(5),
					logType: phaseLabel,
					typeIds: responseLog ? ['5', '6'] : ['5'],
					detailsHtml: detailsHtml
				});
			});

			return displayLogs;
		},

		/**
		 * Build the HTML for the logs modal body using AIPS.Templates.
		 *
		 * @param {Object}   container History container summary fields.
		 * @param {Object[]} logs      Array of log entry objects.
		 * @return {string} HTML string.
		 */
		renderLogsModalContent: function (container, logs) {
			var self = this;
			var T    = AIPS.Templates;
			var html = '';
			var displayLogs = self.buildDisplayLogs(logs);
			var inferredAction = self.inferWhatHappened(container, logs);
			var inferredOutcome = self.humanizeOutcome(container.status);
			var changedHighlights = self.detectWhatChanged(logs, container);

			html += '<div class="aips-history-log-renderer aips-json-viewer-enabled">';
			html += '<div class="aips-history-modal-toolbar">';
			html += '<label class="aips-history-json-toggle">';
			html += '<input type="checkbox" class="aips-json-viewer-toggle" checked> ';
			html += '<span>' + T.escape(aipsHistoryL10n.jsonViewerLabel || 'JSON Viewer') + '</span>';
			html += '</label>';
			html += '</div>';

			// ---- Container summary ----
			var rows = '';

			rows += T.render('aips-tmpl-history-summary-row', {
				label: aipsHistoryL10n.labelContainerId || 'Container ID',
				value: container.id ? String(container.id) : ''
			});

			if (container.generated_title) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelTitle || 'Title',
					value: container.generated_title
				});
			}
			if (container.template_name) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelTemplate || 'Template',
					value: container.template_name
				});
			}

			if (container.creation_method) {
				var methodLabel = container.creation_method.replace(/_/g, ' ');
				methodLabel = methodLabel.charAt(0).toUpperCase() + methodLabel.slice(1);
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCreationMethod || 'Method',
					value: methodLabel
				});
			}

			var statusClass = container.status === 'completed' ? 'aips-badge-success'
				: (container.status === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral');
			rows += T.renderRaw('aips-tmpl-history-summary-status-row', {
				label:       T.escape(aipsHistoryL10n.labelStatus || 'Status'),
				statusClass: T.escape(statusClass),
				status:      T.escape(container.status)
			});

			if (container.created_at) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCreated || 'Created',
					value: container.created_at
				});
			}
			if (container.completed_at) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCompleted || 'Completed',
					value: container.completed_at
				});
			}

			// Duration row.
			if (container.duration_seconds !== null && container.duration_seconds !== undefined) {
				rows += T.render('aips-tmpl-history-summary-duration-row', {
					label: aipsHistoryL10n.labelDuration || 'Duration',
					value: AIPS.DateTime.formatDuration(container.duration_seconds)
				});
			}

			if (container.error_message) {
				rows += T.render('aips-tmpl-history-summary-error-row', {
					label:   aipsHistoryL10n.labelError || 'Error',
					message: container.error_message
				});
			}

			// Post link row.
			if (container.post_id && container.post_url && container.post_edit_url) {
				rows += T.renderRaw('aips-tmpl-history-summary-post-row', {
					label:     T.escape(aipsHistoryL10n.labelPostId || 'Post'),
					url:       T.escape(container.post_url),
					postId:    T.escape(String(container.post_id)),
					editUrl:   T.escape(container.post_edit_url || ''),
					editLabel: T.escape(aipsHistoryL10n.editPostLabel || 'Edit')
				});
			} else if (container.post_id) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelPostId || 'Post ID',
					value: String(container.post_id)
				});
			}

			rows = T.render('aips-tmpl-history-summary-row', {
				label: aipsHistoryL10n.labelWhatHappened || 'What happened',
				value: inferredAction
			}) + rows;

			rows += T.render('aips-tmpl-history-summary-row', {
				label: aipsHistoryL10n.labelOutcome || 'Outcome',
				value: inferredOutcome
			});

			var relatedEntities = self.collectRelatedEntities(container, logs);
			if (relatedEntities) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelRelatedEntities || 'Related entities',
					value: relatedEntities
				});
			}

			rows += T.render('aips-tmpl-history-summary-row', {
				label: aipsHistoryL10n.labelWhatChanged || 'What changed',
				value: changedHighlights
			});

			html += '<h4 style="margin:0 0 8px;">' + T.escape(aipsHistoryL10n.summaryHeading || 'Summary') + '</h4>';
			html += T.renderRaw('aips-tmpl-history-modal-summary', { rows: rows });

			// ---- Log type filter toolbar ----
			var typeCounts = { all: displayLogs.length };
			$.each(displayLogs, function (i, displayLog) {
				$.each(displayLog.typeIds, function (j, tid) {
					typeCounts[tid] = (typeCounts[tid] || 0) + 1;
				});
			});

			if (displayLogs.length > 0) {
				var filterButtons = '';

				// "All" button.
				filterButtons += T.renderRaw('aips-tmpl-history-log-type-btn', {
					typeId:      T.escape('all'),
					activeClass: T.escape('aips-btn-primary'),
					label:       T.escape(aipsHistoryL10n.filterAll || 'All'),
					count:       T.escape(String(typeCounts.all))
				});

				// Per-type buttons (only types that appear in the log set).
				var typeOrder = [2, 3, 4, 5, 6, 8, 1, 7, 9, 10];
				$.each(typeOrder, function (i, tid) {
					if (!typeCounts[String(tid)]) {
						return;
					}
					var typeLabel = (aipsHistoryL10n.typeLabels && aipsHistoryL10n.typeLabels[tid])
						|| self.typeLabelFallback(tid);
					filterButtons += T.renderRaw('aips-tmpl-history-log-type-btn', {
						typeId:      T.escape(String(tid)),
						activeClass: T.escape('aips-btn-ghost'),
						label:       T.escape(typeLabel),
						count:       T.escape(String(typeCounts[String(tid)]))
					});
				});

				html += T.renderRaw('aips-tmpl-history-log-type-filter', {
					filterLabel: T.escape(aipsHistoryL10n.filterByType || 'Filter:'),
					buttons:     filterButtons
				});
			}

			// ---- Log entries heading ----
			html += '<details class="aips-history-advanced-details" style="margin-top:16px;">';
			html += '<summary style="cursor:pointer;font-weight:600;">' + T.escape(aipsHistoryL10n.labelAdvancedDetails || 'Advanced details') + '</summary>';
			html += T.renderRaw('aips-tmpl-history-logs-heading', {
				heading: T.escape(aipsHistoryL10n.logsHeading || 'Log Entries'),
				count:   displayLogs.length
			});

			if (displayLogs.length === 0) {
				html += T.render('aips-tmpl-history-no-logs', {
					message: aipsHistoryL10n.noLogsFound || 'No log entries found for this container.'
				});
				html += '</details>';
				html += '</div>';
				return html;
			}

			var rowsHtml = '';
			$.each(displayLogs, function (i, displayLog) {
				rowsHtml += T.renderRaw('aips-tmpl-history-log-row', {
					timestamp:   T.escape(displayLog.timestamp),
					typeClass:   T.escape(displayLog.typeClass),
					typeLabel:   T.escape(displayLog.typeLabel),
					logType:     T.escape(displayLog.logType),
					detailsHtml: displayLog.detailsHtml,
					typeIds:     T.escape(displayLog.typeIds.join(','))
				});
			});

			html += T.renderRaw('aips-tmpl-history-logs-table', {
				colTimestamp: T.escape(aipsHistoryL10n.colTimestamp || 'Timestamp'),
				colType:      T.escape(aipsHistoryL10n.colType || 'Type'),
				colLogType:   T.escape(aipsHistoryL10n.colLogType || 'Log Type'),
				colDetails:   T.escape(aipsHistoryL10n.colDetails || 'Details'),
				rows:         rowsHtml
			});

			html += '</details>';
			html += '</div>';

			return html;
		},

		inferWhatHappened: function (container, logs) {
			var text = ((container.creation_method || '') + ' ' + (container.template_name || '')).toLowerCase();
			if (text.indexOf('research') !== -1) { return aipsHistoryL10n.summaryActionResearchRun || 'Research run'; }
			if (text.indexOf('embedding') !== -1) { return aipsHistoryL10n.summaryActionEmbeddings || 'Embeddings processing'; }
			if (text.indexOf('author') !== -1 && text.indexOf('topic') !== -1) { return aipsHistoryL10n.summaryActionAuthorTopics || 'Author topic generation'; }
			if (text.indexOf('schedule') !== -1) { return aipsHistoryL10n.summaryActionScheduledPosts || 'Scheduled post generation'; }

			var sawAI = false;
			$.each(logs || [], function (i, log) {
				if (String(log.history_type_id) === '5' || String(log.history_type_id) === '6') {
					sawAI = true;
				}
			});
			return sawAI
				? (aipsHistoryL10n.summaryActionPostGeneration || 'Post generation')
				: (aipsHistoryL10n.summaryActionAutomationTask || 'Automation task');
		},

		humanizeOutcome: function (status) {
			if (status === 'completed') { return aipsHistoryL10n.summaryOutcomeSuccess || 'Success'; }
			if (status === 'failed') { return aipsHistoryL10n.summaryOutcomeFailed || 'Failed'; }
			return aipsHistoryL10n.summaryOutcomeInProgress || 'In progress';
		},

		collectRelatedEntities: function (container, logs) {
			var entities = [];
			var seen = {};
			var addEntity = function (label, value) {
				if (!value) {
					return;
				}
				var text = String(value);
				var key = label + '|' + text;
				if (seen[key]) {
					return;
				}
				seen[key] = true;
				entities.push(label + ': ' + text);
			};
			addEntity(aipsHistoryL10n.summaryEntityPost || 'Post', container.generated_title);
			addEntity(aipsHistoryL10n.summaryEntityTemplate || 'Template', container.template_name);
			addEntity(aipsHistoryL10n.summaryEntityPostId || 'Post ID', container.post_id);
			addEntity(
				aipsHistoryL10n.summaryEntityMethod || 'Method',
				container.creation_method ? container.creation_method.replace(/_/g, ' ') : ''
			);

			$.each(logs || [], function (i, log) {
				var details = log && log.details ? log.details : null;
				if (!details) {
					return;
				}
				if (!container.generated_title) {
					addEntity(aipsHistoryL10n.summaryEntityPost || 'Post', details.generated_title || details.title);
				}
				if (!container.template_name) {
					addEntity(aipsHistoryL10n.summaryEntityTemplate || 'Template', details.template_name);
				}
				if (!container.post_id) {
					addEntity(aipsHistoryL10n.summaryEntityPostId || 'Post ID', details.post_id);
				}
			});

			return entities.length ? entities.join(' | ') : (aipsHistoryL10n.summaryNoRelatedEntities || 'No related entities detected');
		},

		detectWhatChanged: function (logs, container) {
			var details = [];
			var sawTitleChange = false;
			var sawContentChange = false;
			var sawImageChange = false;
			var sawPublishedResult = false;
			var sawDraftResult = false;
			var scanTextForKeywords = function (value) {
				if (typeof value !== 'string' || value === '') {
					return;
				}
				var normalized = value.toLowerCase();
				if (normalized.indexOf('title') !== -1) { sawTitleChange = true; }
				if (normalized.indexOf('content') !== -1 || normalized.indexOf('body') !== -1) { sawContentChange = true; }
				if (normalized.indexOf('featured image') !== -1 || normalized.indexOf('image') !== -1) { sawImageChange = true; }
				if (normalized.indexOf('publish') !== -1) { sawPublishedResult = true; }
				if (normalized.indexOf('draft') !== -1) { sawDraftResult = true; }
			};

			$.each(logs || [], function (i, log) {
				if (!log || !log.details) {
					return;
				}
				$.each(log.details, function (key, val) {
					if (typeof val === 'string') {
						scanTextForKeywords(val);
						return;
					}
					if (val && typeof val === 'object') {
						$.each(val, function (nestedKey, nestedVal) {
							if (typeof nestedVal === 'string') {
								scanTextForKeywords(nestedVal);
							}
						});
					}
				});
			});

			if (sawTitleChange) { details.push(aipsHistoryL10n.summaryChangedTitle || 'Title updated'); }
			if (sawContentChange) { details.push(aipsHistoryL10n.summaryChangedContent || 'Content updated'); }
			if (sawImageChange) { details.push(aipsHistoryL10n.summaryChangedImage || 'Image generated/updated'); }
			if (sawPublishedResult) { details.push(aipsHistoryL10n.summaryChangedPublished || 'Published result'); }
			else if (sawDraftResult) { details.push(aipsHistoryL10n.summaryChangedDraft || 'Draft result'); }
			if (container.status === 'failed') { details.push(aipsHistoryL10n.summaryChangedError || 'Run ended with an error'); }
			return details.length ? details.join('; ') : (aipsHistoryL10n.summaryChangedNone || 'No major content changes detected');
		},

		/**
		 * Return a fallback human-readable label for a history type ID.
		 *
		 * Used when the server-side l10n map is not available for a given type.
		 *
		 * @param {number} typeId
		 * @return {string}
		 */
		typeLabelFallback: function (typeId) {
			var map = {
				1:  'Log',
				2:  'Error',
				3:  'Warning',
				4:  'Info',
				5:  'AI Request',
				6:  'AI Response',
				7:  'Debug',
				8:  'Activity',
				9:  'Session',
				10: 'Metric'
			};
			return map[typeId] || 'Unknown';
		},

		/**
		 * Return a badge CSS class for a history_type_id constant.
		 *
		 * @param {number} typeId
		 * @return {string}
		 */
		typeClass: function (typeId) {
			var map = {
				1: 'aips-badge-neutral',   // LOG
				2: 'aips-badge-error',     // ERROR
				3: 'aips-badge-warning',   // WARNING
				4: 'aips-badge-info',      // INFO
				5: 'aips-badge-ai',        // AI_REQUEST
				6: 'aips-badge-ai',        // AI_RESPONSE
				7: 'aips-badge-neutral',   // DEBUG
				8: 'aips-badge-success',   // ACTIVITY
				9: 'aips-badge-neutral'    // SESSION_METADATA
			};
			return map[typeId] || 'aips-badge-neutral';
		},

		/**
		 * Close the logs modal.
		 *
		 * @param {Event} e
		 */
		closeLogsModal: function (e) {
			e.preventDefault();
			$('#aips-history-logs-modal').fadeOut(200);
		},

		/**
		 * Close the logs modal when the overlay backdrop is clicked.
		 *
		 * @param {Event} e
		 */
		closeLogsModalOnOverlay: function (e) {
			if ($(e.target).is('#aips-history-logs-modal')) {
				$('#aips-history-logs-modal').fadeOut(200);
			}
		},

		/* ------------------------------------------------------------------ */
		/* Selection / bulk delete                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Toggle all row checkboxes to match the select-all checkbox state.
		 *
		 * @param {Event} e
		 */
		toggleSelectAll: function (e) {
			var checked = $(e.target).prop('checked');
			$('.aips-history-cb').prop('checked', checked);
			this.updateDeleteButton();
		},

		/**
		 * Sync the select-all checkbox and Delete Selected button on row change.
		 */
		onRowCheckboxChange: function () {
			this.updateDeleteButton();
			var allChecked = $('.aips-history-cb').length > 0
				&& $('.aips-history-cb:not(:checked)').length === 0;
			$('#aips-cb-select-all').prop('checked', allChecked);
		},

		/**
		 * Enable or disable the Delete Selected button based on current selections.
		 */
		updateDeleteButton: function () {
			var count = $('.aips-history-cb:checked').length;
			$('#aips-delete-selected-btn').prop('disabled', count === 0);
		},

		/**
		 * Send a bulk-delete request for all checked containers.
		 *
		 * @param {Event} e - Click event from `#aips-delete-selected-btn`.
		 */
		deleteSelected: function (e) {
			e.preventDefault();

			var ids = [];
			$('.aips-history-cb:checked').each(function () {
				ids.push($(this).val());
			});

			if (!ids.length) {
				return;
			}

			var self     = this;
			var $btn     = $(e.currentTarget);
			var origHtml = $btn.html();
			var msg      = aipsHistoryL10n.confirmBulkDelete || 'Delete the selected history containers? This cannot be undone.';

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmDeleteLabel || 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
					$btn.prop('disabled', true).html(
						'<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.deleting || 'Deleting\u2026')
					);

					$.ajax({
						url: aipsAjax.ajaxUrl,
						type: 'POST',
						data: {
							action: 'aips_bulk_delete_history',
							nonce: aipsAjax.nonce,
							ids: ids
						},
						success: function (response) {
							if (response.success) {
								AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Items deleted successfully.', 'success');
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorDeleting || 'Error deleting items.'),
									'error'
								);
								$btn.prop('disabled', false).html(origHtml);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting items.', 'error');
							$btn.prop('disabled', false).html(origHtml);
						}
					});
				}}
			]);
		},

		/**
		 * Delete a single history container row.
		 *
		 * @param {Event} e - Click event from an `.aips-delete-history` element.
		 */
		deleteSingleItem: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var id = $(e.currentTarget).data('id');
			if (!id) {
				return;
			}

			var self = this;
			var msg  = aipsHistoryL10n.confirmDelete || 'Delete this history container? This cannot be undone.';

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmDeleteLabel || 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
					$.ajax({
						url: aipsAjax.ajaxUrl,
						type: 'POST',
						data: {
							action: 'aips_bulk_delete_history',
							nonce: aipsAjax.nonce,
							ids: [id]
						},
						success: function (response) {
							if (response.success) {
								AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Item deleted.', 'success');
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorDeleting || 'Error deleting item.'),
									'error'
								);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting item.', 'error');
						}
					});
				}}
			]);
		},

		/* ------------------------------------------------------------------ */
		/* Retry generation                                                     */
		/* ------------------------------------------------------------------ */

		/**
		 * Retry a failed history entry via the `aips_retry_generation` AJAX action.
		 *
		 * Refreshes the table via AJAX using self.reload() upon success to avoid
		 * a full page reload and preserve context.
		 *
		 * @param {Event} e - Click event from an `.aips-retry-generation` element.
		 */
		retryGeneration: function (e) {
			e.preventDefault();

			var self     = this;
			var id       = $(e.currentTarget).data('id');
			var $btn     = $(e.currentTarget);
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.retrying || 'Retrying\u2026')
			);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_retry_generation',
					nonce: aipsAjax.nonce,
					history_id: id
				},
				success: function (response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success');
						self.reload();
					} else {
						AIPS.Utilities.showToast(response.data.message, 'error');
						$btn.prop('disabled', false).html(origHtml);
					}
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryL10n.errorRetrying || 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Clear history                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Clear history by status (or all) after an accessible confirmation dialog.
		 *
		 * @param {Event} e - Click event from an `.aips-clear-history` element.
		 */
		clearHistory: function (e) {
			e.preventDefault();

			var status = $(e.currentTarget).data('status');
			var msg    = status
				? (aipsHistoryL10n.confirmClearStatus || 'Clear all history entries with this status? This cannot be undone.')
				: (aipsHistoryL10n.confirmClearAll   || 'Clear all history? This cannot be undone.');

			var self = this;

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel    || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmClearLabel || 'Yes, clear', className: 'aips-btn aips-btn-danger-solid', action: function () {
					$.ajax({
						url: aipsAjax.ajaxUrl,
						type: 'POST',
						data: {
							action: 'aips_clear_history',
							nonce: aipsAjax.nonce,
							status: status
						},
						success: function (response) {
							if (response.success) {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.clearedSuccess || 'History cleared.'),
									'success'
								);
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorClearing || 'Error clearing history.'),
									'error'
								);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorClearing || 'Error clearing history.', 'error');
						}
					});
				}}
			]);
		},

		/* ------------------------------------------------------------------ */
		/* Reload / pagination / filter / search                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Handle a click on the Reload button.
		 *
		 * @param {Event} e
		 */
		onReloadClick: function (e) {
			e.preventDefault();
			this.reload();
		},

		/**
		 * Reload the history table via AJAX applying the current filter and search.
		 *
		 * @param {number} [paged=1] 1-based page number to load.
		 */
		reload: function (paged) {
			paged = (paged === undefined || paged === null) ? 1 : Math.max(1, parseInt(paged, 10));

			var self       = this;
			var $tbody     = $('#aips-history-tbody');
			var $pagCell   = $('.aips-history-pagination-cell');
			var $reloadBtn = $('#aips-reload-history-btn');
			var origHtml   = $reloadBtn.html();

			// Show a loading placeholder in the table body.
			if ($tbody.length) {
				$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-loading', {
					text: aipsHistoryL10n.loading || 'Loading\u2026'
				}));
			}
			$reloadBtn.prop('disabled', true).html(
				'<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> '
				+ (aipsHistoryL10n.reloading || 'Reloading\u2026')
			);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_reload_history',
					nonce: aipsAjax.nonce,
					status: self.statusFilter,
					search: self.searchQuery,
					domain: self.domainFilter,
					actor: self.actorFilter,
					correlation_id: self.correlationId,
					date_from: self.dateFrom,
					date_to: self.dateTo,
					paged: paged
				},
				success: function (response) {
					if (!response.success) {
						AIPS.Utilities.showToast(
							response.data && response.data.message
								? response.data.message
								: (aipsHistoryL10n.errorReloading || 'Failed to reload history.'),
							'error'
						);
						return;
					}

					var itemsHtml = response.data.items_html || '';

					if ($tbody.length) {
						if (itemsHtml) {
							$tbody.html(itemsHtml);
							$('#aips-history-search-no-results').hide();
						} else {
							// No results: render a friendly inline empty state.
							$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-empty', {
								message: aipsHistoryL10n.noResultsFound || 'No history containers match your current filters.'
							}));
						}
					}

					if ($pagCell.length && response.data.pagination_html !== undefined) {
						$pagCell.html(response.data.pagination_html);
					}

					// Refresh stat cards.
					var stats = response.data.stats;
					if (stats) {
						$('#aips-stat-total').text(stats.total);
						$('#aips-stat-completed').text(stats.completed);
						$('#aips-stat-failed').text(stats.failed);
						$('#aips-stat-success-rate').text(stats.success_rate + '%');
					}

					// Keep the URL in sync.
					var url = new URL(window.location.href);
					if (paged > 1) {
						url.searchParams.set('paged', paged);
					} else {
						url.searchParams.delete('paged');
					}
					window.history.replaceState({}, '', url.toString());

					// Reset checkboxes and delete button.
					$('#aips-cb-select-all').prop('checked', false);
					self.updateDeleteButton();
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryL10n.errorReloading || 'Failed to reload history.', 'error');
				},
				complete: function () {
					$reloadBtn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/**
		 * Handle a pagination link click.
		 *
		 * @param {Event} e - Click event from a pagination element.
		 */
		loadPage: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			if ($btn.prop('disabled')) {
				return;
			}
			var page = $btn.data('page');
			if (!page) {
				return;
			}
			this.reload(parseInt(page, 10));
		},

		/**
		 * Apply the current status filter and trigger a server reload.
		 *
		 * @param {Event} e
		 */
		applyFilter: function (e) {
			if (e) {
				e.preventDefault();
			}
			this.statusFilter = $('#aips-filter-status').val() || '';
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';

			// Reflect change in the URL without reloading.
			var url = new URL(window.location.href);
			[['status', this.statusFilter], ['domain', this.domainFilter], ['actor', this.actorFilter], ['correlation_id', this.correlationId], ['date_from', this.dateFrom], ['date_to', this.dateTo]].forEach(function (entry) {
				if (entry[1]) {
					url.searchParams.set(entry[0], entry[1]);
				} else {
					url.searchParams.delete(entry[0]);
				}
			});
			url.searchParams.delete('paged');
			window.history.pushState({}, '', url.toString());

			this.reload(1);
		},

		/**
		 * Live client-side row filter as the user types.
		 * Stores the raw input value in searchQuery so server calls use the
		 * exact string the user typed (not a lowercased copy).
		 *
		 * @param {Event} e
		 */
		onSearchInput: function (e) {
			var rawQuery   = $(e.target).val();
			this.searchQuery = rawQuery;
			this.syncSearchClearButton();

			var lowerQuery = rawQuery.toLowerCase().trim();
			var hasResults = false;

			$('#aips-history-tbody tr').each(function () {
				if (!lowerQuery || $(this).text().toLowerCase().indexOf(lowerQuery) !== -1) {
					$(this).show();
					hasResults = true;
				} else {
					$(this).hide();
				}
			});

			$('#aips-history-search-no-results').toggle(!hasResults && lowerQuery.length > 0);
		},

		/**
		 * Trigger a server-side reload when Enter is pressed in the search box.
		 *
		 * @param {Event} e
		 */
		onSearchKeydown: function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				this.searchQuery = $('#aips-history-search-input').val();

				var url = new URL(window.location.href);
				if (this.searchQuery) {
					url.searchParams.set('s', this.searchQuery);
				} else {
					url.searchParams.delete('s');
				}
				url.searchParams.delete('paged');
				window.history.pushState({}, '', url.toString());

				this.reload(1);
			}
		},

		/**
		 * Clear the search input and trigger a server reload.
		 *
		 * @param {Event} e
		 */
		clearSearch: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-history-search-input').val('');
			this.searchQuery = '';
			this.syncSearchClearButton();
			$('#aips-history-search-no-results').hide();
			$('#aips-history-tbody tr').show();
			this.reload(1);
		},

		/**
		 * Show or hide the inline search clear button.
		 */
		syncSearchClearButton: function () {
			var hasValue = $('#aips-history-search-input').val().trim().length > 0;
			$('#aips-history-search-clear').toggle(hasValue);
		},

		/* ------------------------------------------------------------------ */
		/* Export                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * Trigger the CSV export via a hidden form POST.
		 *
		 * @param {Event} e - Click event from `#aips-export-history-btn`.
		 */
		exportHistory: function (e) {
			e.preventDefault();

			var form = $('<form method="POST" action="' + aipsAjax.ajaxUrl + '" style="display:none;"></form>');
			form.append($('<input type="hidden" name="action" value="aips_export_history">'));
			form.append($('<input type="hidden" name="nonce">').val(aipsAjax.nonce));
			form.append($('<input type="hidden" name="status">').val(this.statusFilter));
			form.append($('<input type="hidden" name="search">').val(this.searchQuery));
			form.append($('<input type="hidden" name="domain">').val(this.domainFilter));
			form.append($('<input type="hidden" name="actor">').val(this.actorFilter));
			form.append($('<input type="hidden" name="correlation_id">').val(this.correlationId));
			form.append($('<input type="hidden" name="date_from">').val(this.dateFrom));
			form.append($('<input type="hidden" name="date_to">').val(this.dateTo));
			$('body').append(form);
			form.submit();
			form.remove();
		},

	};

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function () {
		AIPS.History.init();
	});

})(jQuery);
