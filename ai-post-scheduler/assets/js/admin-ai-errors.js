/**
 * AI Errors Diagnostics Tab Management
 *
 * Handles filtering, search, pagination, and modal inspection for AI error logs.
 * Renders UI components using AIPS.Templates.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.20
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * Main controller for AI Errors tab in Diagnostics.
	 */
	AIPS.AiErrors = {
		page: 1,
		perPage: 20,
		dateFrom: '',
		dateTo: '',
		component: '',
		errorScope: 'ai_only',
		search: '',
		searchTimer: null,
		currentErrorData: null,

		/**
		 * Initialize the module.
		 */
		init: function () {
			if (!$('#aips-tab-ai-errors-pane').length) {
				return;
			}

			this.initFromUrlParams();
			this.bindEvents();

			// Initialize standalone history opener so clicking "Open History Container"
			// in the modal opens the full history modal seamlessly.
			if (AIPS.HistoryModalShared && typeof AIPS.HistoryModalShared.initStandaloneOpener === 'function') {
				AIPS.HistoryModalShared.initStandaloneOpener();
			}

			this.fetchErrors();
		},

		/**
		 * Read URL search parameters and pre-populate filter fields.
		 */
		initFromUrlParams: function () {
			var urlParams = new URLSearchParams(window.location.search);

			if (urlParams.has('date_from')) {
				this.dateFrom = urlParams.get('date_from') || '';
				$('#aips-ai-errors-from').val(this.dateFrom);
			}
			if (urlParams.has('date_to')) {
				this.dateTo = urlParams.get('date_to') || '';
				$('#aips-ai-errors-to').val(this.dateTo);
			}
			if (urlParams.has('component')) {
				this.component = urlParams.get('component') || '';
				$('#aips-ai-errors-component').val(this.component);
			}
			if (urlParams.has('search')) {
				this.search = urlParams.get('search') || '';
				$('#aips-ai-errors-search').val(this.search);
			}
			if (urlParams.has('error_scope')) {
				this.errorScope = urlParams.get('error_scope') || 'ai_only';
				$('#aips-ai-errors-scope').val(this.errorScope);
			}
		},

		/**
		 * Bind DOM and keyboard event listeners.
		 */
		bindEvents: function () {
			var self = this;

			// Filter button
			$('#aips-ai-errors-filter-btn').on('click', function (e) {
				e.preventDefault();
				self.applyFilters();
			});

			// Reset button
			$('#aips-ai-errors-reset-btn').on('click', function (e) {
				e.preventDefault();
				self.resetFilters();
			});

			// Scope or Component change
			$('#aips-ai-errors-component, #aips-ai-errors-scope').on('change', function () {
				self.applyFilters();
			});

			// Search with debounce
			$('#aips-ai-errors-search').on('input', function () {
				clearTimeout(self.searchTimer);
				self.searchTimer = setTimeout(function () {
					self.applyFilters();
				}, 400);
			}).on('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					clearTimeout(self.searchTimer);
					self.applyFilters();
				}
			});

			// Row click to view details
			$(document).on('click', '.aips-ai-error-row', function (e) {
				if ($(e.target).closest('a, button, input').length) {
					return;
				}
				var logId = $(this).data('log-id');
				if (logId) {
					self.openErrorDetails(logId);
				}
			});

			// View details button click
			$(document).on('click', '.aips-ai-error-view-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var logId = $(this).data('log-id');
				if (logId) {
					self.openErrorDetails(logId);
				}
			});

			// Pagination clicks
			$(document).on('click', '.aips-ai-errors-page-btn', function (e) {
				e.preventDefault();
				var targetPage = parseInt($(this).data('page'), 10);
				if (targetPage && targetPage !== self.page) {
					self.page = targetPage;
					self.fetchErrors();
				}
			});

			// Modal close handlers
			$('#aips-ai-error-modal').on('click', '.aips-modal-close, .aips-modal-overlay', function (e) {
				e.preventDefault();
				self.closeModal();
			});

			// ESC key handler for error modal
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' && $('#aips-ai-error-modal').is(':visible')) {
					if (!$('#aips-history-modal').is(':visible')) {
						self.closeModal();
					}
				}
			});

			// Copy Prompt button
			$('#aips-ai-error-copy-prompt-btn').on('click', function (e) {
				e.preventDefault();
				var text = $('#aips-ai-error-modal-prompt').text();
				self.copyToClipboard(text, $(this));
			});

			// Copy Diagnostic button
			$('#aips-ai-error-copy-diagnostic-btn').on('click', function (e) {
				e.preventDefault();
				var text = $('#aips-ai-error-modal-diagnostic').text();
				self.copyToClipboard(text, $(this));
			});

			// When "Open History Container" is clicked from inside the error modal,
			// ensure history modal displays above error modal
			$('#aips-ai-error-open-history-btn').on('click', function () {
				$('#aips-history-modal').css('z-index', 100050);
			});
		},

		/**
		 * Read inputs, reset to page 1, and fetch errors.
		 */
		applyFilters: function () {
			this.page = 1;
			this.dateFrom = $('#aips-ai-errors-from').val() || '';
			this.dateTo = $('#aips-ai-errors-to').val() || '';
			this.component = $('#aips-ai-errors-component').val() || '';
			this.errorScope = $('#aips-ai-errors-scope').val() || 'ai_only';
			this.search = $('#aips-ai-errors-search').val() || '';
			this.fetchErrors();
		},

		/**
		 * Reset all filters to default.
		 */
		resetFilters: function () {
			this.page = 1;
			this.dateFrom = '';
			this.dateTo = '';
			this.component = '';
			this.errorScope = 'ai_only';
			this.search = '';

			$('#aips-ai-errors-from').val('');
			$('#aips-ai-errors-to').val('');
			$('#aips-ai-errors-component').val('');
			$('#aips-ai-errors-scope').val('ai_only');
			$('#aips-ai-errors-search').val('');

			this.fetchErrors();
		},

		/**
		 * Perform AJAX fetch of AI error logs.
		 */
		fetchErrors: function () {
			var self = this;
			var l10n = window.aipsAiErrorsL10n || {};
			var $tbody = $('#aips-ai-errors-tbody');
			var ajaxConfig = window.aipsAjax || {};

			$tbody.html(AIPS.Templates.render('aips-tmpl-ai-errors-loading', {
				message: l10n.loading || 'Loading error logs…'
			}));

			$.ajax({
				url: ajaxConfig.ajaxUrl || window.ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_ai_errors',
					nonce: ajaxConfig.nonce || '',
					date_from: self.dateFrom,
					date_to: self.dateTo,
					component: self.component,
					error_scope: self.errorScope,
					search: self.search,
					page: self.page,
					per_page: self.perPage
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						var msg = (response && response.data && response.data.message) || l10n.errorLoading || 'Failed to load error logs.';
						$tbody.html(AIPS.Templates.render('aips-tmpl-ai-errors-error', {
							message: msg
						}));
						$('#aips-ai-errors-count-text').text('');
						return;
					}

					var data = response.data;
					self.renderTableRows(data.items || []);
					self.renderPagination(data);
				},
				error: function () {
					$tbody.html(AIPS.Templates.render('aips-tmpl-ai-errors-error', {
						message: l10n.errorLoading || 'Failed to load error logs.'
					}));
					$('#aips-ai-errors-count-text').text('');
				}
			});
		},

		/**
		 * Render items into the table body using AIPS.Templates.
		 *
		 * @param {Array} items
		 */
		renderTableRows: function (items) {
			var $tbody = $('#aips-ai-errors-tbody');
			var l10n = window.aipsAiErrorsL10n || {};

			if (!items || !items.length) {
				$tbody.html(AIPS.Templates.render('aips-tmpl-ai-errors-empty', {
					message: l10n.noErrorsFound || 'No errors found matching the current filters.'
				}));
				return;
			}

			var rowsHtml = '';
			for (var i = 0; i < items.length; i++) {
				var item = items[i];
				var compLabel = item.component_label || item.component || 'General';
				var runLabel = l10n.runLabel ? l10n.runLabel.replace('%d', item.history_id) : ('Run #' + item.history_id);

				rowsHtml += AIPS.Templates.render('aips-tmpl-ai-error-row', {
					id: item.id,
					history_id: item.history_id,
					relative_date: item.relative_date || item.formatted_date || '',
					formatted_date: item.formatted_date || '',
					component_label: compLabel,
					error_message: item.error_message || item.message || '',
					post_title: item.post_title || runLabel,
					run_label: runLabel,
					view_details_label: l10n.viewDetails || 'View Details'
				});
			}

			$tbody.html(rowsHtml);
		},

		/**
		 * Render pagination controls and update count label using AIPS.Templates.
		 *
		 * @param {Object} data
		 */
		renderPagination: function (data) {
			var total = data.total || 0;
			var totalPages = data.total_pages || 1;
			var currentPage = data.page || 1;

			var countText = total === 1 ? '1 error record' : total.toLocaleString() + ' error records';
			if (total > 0) {
				countText += ' (Page ' + currentPage + ' of ' + totalPages + ')';
			}
			$('#aips-ai-errors-count-text').text(countText);

			if (totalPages <= 1) {
				$('#aips-ai-errors-pagination-top, #aips-ai-errors-pagination-bottom').empty();
				return;
			}

			var pagHtml = '';

			// Previous page button
			if (currentPage > 1) {
				pagHtml += AIPS.Templates.render('aips-tmpl-ai-errors-pagination-btn', {
					page: currentPage - 1,
					label: '« Prev'
				});
			} else {
				pagHtml += AIPS.Templates.render('aips-tmpl-ai-errors-pagination-disabled', {
					label: '« Prev'
				});
			}

			// Page indicator
			pagHtml += AIPS.Templates.render('aips-tmpl-ai-errors-pagination-info', {
				current: currentPage,
				total: totalPages
			});

			// Next page button
			if (currentPage < totalPages) {
				pagHtml += AIPS.Templates.render('aips-tmpl-ai-errors-pagination-btn', {
					page: currentPage + 1,
					label: 'Next »'
				});
			} else {
				pagHtml += AIPS.Templates.render('aips-tmpl-ai-errors-pagination-disabled', {
					label: 'Next »'
				});
			}

			$('#aips-ai-errors-pagination-top, #aips-ai-errors-pagination-bottom').html(pagHtml);
		},

		/**
		 * Fetch single error details and open the detail modal.
		 *
		 * @param {number} logId
		 */
		openErrorDetails: function (logId) {
			var self = this;
			var l10n = window.aipsAiErrorsL10n || {};
			var ajaxConfig = window.aipsAjax || {};
			var $modal = $('#aips-ai-error-modal');
			var $loading = $('#aips-ai-error-modal-loading');
			var $inner = $('#aips-ai-error-modal-inner');

			$modal.fadeIn(150);
			$loading.show();
			$inner.hide();

			$.ajax({
				url: ajaxConfig.ajaxUrl || window.ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_ai_error_details',
					nonce: ajaxConfig.nonce || '',
					log_id: logId
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						var msg = (response && response.data && response.data.message) || l10n.errorLoadingDetails || 'Failed to load error details.';
						alert(msg);
						self.closeModal();
						return;
					}

					self.populateModal(response.data);
					$loading.hide();
					$inner.show();
				},
				error: function () {
					alert(l10n.errorLoadingDetails || 'Failed to load error details.');
					self.closeModal();
				}
			});
		},

		/**
		 * Populate modal elements with retrieved error data.
		 *
		 * @param {Object} data
		 */
		populateModal: function (data) {
			this.currentErrorData = data;
			var l10n = window.aipsAiErrorsL10n || {};

			// Header
			var compLabel = data.component_label || data.component || 'General';
			$('#aips-ai-error-modal-title').text('AI Error #' + data.id + ' — ' + compLabel);
			$('#aips-ai-error-modal-badge').text(data.relative_date || data.formatted_date || '');

			// Run info
			$('#aips-ai-error-modal-run-title').text(data.post_title || ('Run #' + data.history_id));
			var runMeta = 'History Container ID: ' + data.history_id;
			if (data.creation_method) {
				runMeta += ' | Method: ' + data.creation_method;
			}
			if (data.container_status) {
				runMeta += ' | Status: ' + data.container_status;
			}
			$('#aips-ai-error-modal-run-meta').text(runMeta);

			// History button
			$('#aips-ai-error-open-history-btn').attr('data-history-id', data.history_id);

			// Message
			$('#aips-ai-error-modal-message').text(data.error_message || data.message || 'Unknown error');

			// Options
			var optionsText = '';
			if (data.options && Object.keys(data.options).length > 0) {
				optionsText = JSON.stringify(data.options, null, 2);
			} else {
				optionsText = l10n.optionsEmpty || 'No request parameters recorded.';
			}
			$('#aips-ai-error-modal-options').text(optionsText);

			// Prompt
			var promptText = data.prompt ? data.prompt : (l10n.promptEmpty || 'No prompt recorded for this error.');
			$('#aips-ai-error-modal-prompt').text(promptText);

			// Full Diagnostic JSON
			var diagnosticPayload = {
				error_log_id: data.id,
				history_id: data.history_id,
				component: data.component,
				timestamp: data.timestamp,
				date: data.formatted_date,
				error_message: data.error_message,
				options: data.options,
				raw_details: data.raw_details
			};
			$('#aips-ai-error-modal-diagnostic').text(JSON.stringify(diagnosticPayload, null, 2));
		},

		/**
		 * Close the detail modal.
		 */
		closeModal: function () {
			$('#aips-ai-error-modal').fadeOut(150);
		},

		/**
		 * Copy text to clipboard and provide visual button feedback.
		 *
		 * @param {string} text
		 * @param {jQuery} $btn
		 */
		copyToClipboard: function (text, $btn) {
			var l10n = window.aipsAiErrorsL10n || {};
			var originalHtml = $btn.html();

			var success = false;
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text);
				success = true;
			} else {
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				success = true;
			}

			if (success) {
				$btn.text(l10n.copied || 'Copied!').prop('disabled', true);
				setTimeout(function () {
					$btn.html(originalHtml).prop('disabled', false);
				}, 1500);
			}
		}
	};

	$(document).ready(function () {
		AIPS.AiErrors.init();
	});

})(jQuery);
