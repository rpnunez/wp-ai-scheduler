/**
 * AI Errors Diagnostics Tab Management
 *
 * Handles filtering, search, pagination, aggregate analytics, live provider health checks,
 * log exporting, and modal inspection for AI error logs.
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
		category: '',
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
			this.fetchSummaryStats();
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
			if (urlParams.has('category')) {
				this.category = urlParams.get('category') || '';
				$('#aips-ai-errors-category').val(this.category);
			}
			if (urlParams.has('search')) {
				this.search = urlParams.get('search') || '';
				$('#aips-ai-errors-search').val(this.search);
			}
			if (urlParams.has('error_scope')) {
				this.errorScope = urlParams.get('error_scope') || 'ai_only';
				$('#aips-ai-errors-scope').val(this.errorScope);
			}

			// Update active state on preset buttons if custom dates provided
			if (this.dateFrom || this.dateTo) {
				$('.aips-date-preset-btn').removeClass('active');
			}
		},

		/**
		 * Bind DOM and keyboard event listeners using named method references.
		 */
		bindEvents: function () {
			$(document).on('click', '.aips-date-preset-btn', this.handleDatePresetClick.bind(this));
			$('#aips-ai-errors-filter-btn').on('click', this.handleFilterClick.bind(this));
			$('#aips-ai-errors-reset-btn').on('click', this.handleResetClick.bind(this));
			$('#aips-ai-errors-component, #aips-ai-errors-category, #aips-ai-errors-scope').on('change', this.handleFilterChange.bind(this));
			$(document).on('click', '.aips-ai-errors-cat-pill', this.handleCategoryPillClick.bind(this));
			$('#aips-ai-errors-ping-btn').on('click', this.handlePingClick.bind(this));
			$('#aips-ai-errors-export-toggle').on('click', this.handleExportToggleClick.bind(this));
			$(document).on('click', this.handleExportDropdownDismiss.bind(this));
			$(document).on('click', '.aips-export-btn', this.handleExportActionClick.bind(this));
			$('#aips-ai-errors-search').on('input', this.handleSearchInput.bind(this))
				.on('keydown', this.handleSearchKeydown.bind(this));
			$(document).on('click', '.aips-ai-error-row', this.handleRowClick.bind(this));
			$(document).on('click', '.aips-ai-error-view-btn', this.handleViewDetailsClick.bind(this));
			$(document).on('click', '.aips-ai-errors-page-btn', this.handlePaginationClick.bind(this));
			$('#aips-ai-error-modal').on('click', '.aips-modal-close, .aips-modal-overlay', this.handleModalClose.bind(this));
			$(document).on('keydown', this.handleModalKeydown.bind(this));
			$('#aips-ai-error-copy-prompt-btn').on('click', this.handleCopyPrompt.bind(this));
			$('#aips-ai-error-copy-diagnostic-btn').on('click', this.handleCopyDiagnostic.bind(this));
			$('#aips-ai-error-copy-report-btn').on('click', this.handleCopySupportReport.bind(this));
			$('#aips-ai-error-open-history-btn').on('click', this.handleOpenHistoryClick.bind(this));
		},

		/**
		 * Handler for date preset button click.
		 *
		 * @param {Event} e
		 */
		handleDatePresetClick: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var preset = $btn.data('preset');
			$('.aips-date-preset-btn').removeClass('active');
			$btn.addClass('active');
			this.applyDatePreset(preset);
		},

		/**
		 * Handler for filter button click.
		 *
		 * @param {Event} e
		 */
		handleFilterClick: function (e) {
			e.preventDefault();
			this.applyFilters();
		},

		/**
		 * Handler for filter reset button click.
		 *
		 * @param {Event} e
		 */
		handleResetClick: function (e) {
			e.preventDefault();
			this.resetFilters();
		},

		/**
		 * Handler for dropdown filter changes (component, category, scope).
		 */
		handleFilterChange: function () {
			this.applyFilters();
		},

		/**
		 * Handler for clicking on a category pill in the summary card.
		 *
		 * @param {Event} e
		 */
		handleCategoryPillClick: function (e) {
			e.preventDefault();
			var catSlug = $(e.currentTarget).data('category');
			var currentCat = $('#aips-ai-errors-category').val();

			if (currentCat === catSlug) {
				$('#aips-ai-errors-category').val('');
			} else {
				$('#aips-ai-errors-category').val(catSlug);
			}
			this.applyFilters();
		},

		/**
		 * Handler for clicking the live provider health ping test button.
		 *
		 * @param {Event} e
		 */
		handlePingClick: function (e) {
			e.preventDefault();
			this.pingProviderConnection();
		},

		/**
		 * Handler for toggling export dropdown menu.
		 *
		 * @param {Event} e
		 */
		handleExportToggleClick: function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $menu = $('#aips-ai-errors-export-menu');
			$menu.toggleClass('aips-is-hidden');
		},

		/**
		 * Handler for dismissing export dropdown when clicking outside.
		 *
		 * @param {Event} e
		 */
		handleExportDropdownDismiss: function (e) {
			if (!$(e.target).closest('#aips-ai-errors-export-dropdown').length) {
				$('#aips-ai-errors-export-menu').addClass('aips-is-hidden');
			}
		},

		/**
		 * Handler for export format selection (CSV or JSON).
		 *
		 * @param {Event} e
		 */
		handleExportActionClick: function (e) {
			e.preventDefault();
			var format = $(e.currentTarget).data('format') || 'csv';
			$('#aips-ai-errors-export-menu').addClass('aips-is-hidden');
			this.triggerExport(format);
		},

		/**
		 * Handler for search input debounce.
		 */
		handleSearchInput: function () {
			var self = this;
			clearTimeout(this.searchTimer);
			this.searchTimer = setTimeout(function () {
				self.applyFilters();
			}, 400);
		},

		/**
		 * Handler for search Enter keypress.
		 *
		 * @param {KeyboardEvent} e
		 */
		handleSearchKeydown: function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				clearTimeout(this.searchTimer);
				this.applyFilters();
			}
		},

		/**
		 * Handler for table row click.
		 *
		 * @param {Event} e
		 */
		handleRowClick: function (e) {
			if ($(e.target).closest('a, button, input').length) {
				return;
			}
			var logId = $(e.currentTarget).data('log-id');
			if (logId) {
				this.openErrorDetails(logId);
			}
		},

		/**
		 * Handler for "View Details" button click on a table row.
		 *
		 * @param {Event} e
		 */
		handleViewDetailsClick: function (e) {
			e.preventDefault();
			e.stopPropagation();
			var logId = $(e.currentTarget).data('log-id');
			if (logId) {
				this.openErrorDetails(logId);
			}
		},

		/**
		 * Handler for pagination control button clicks.
		 *
		 * @param {Event} e
		 */
		handlePaginationClick: function (e) {
			e.preventDefault();
			var targetPage = parseInt($(e.currentTarget).data('page'), 10);
			if (targetPage && targetPage !== this.page) {
				this.page = targetPage;
				this.fetchErrors();
			}
		},

		/**
		 * Handler for closing the error detail modal.
		 *
		 * @param {Event} e
		 */
		handleModalClose: function (e) {
			if (e) {
				e.preventDefault();
			}
			this.closeModal();
		},

		/**
		 * Handler for Escape key when modal is open.
		 *
		 * @param {KeyboardEvent} e
		 */
		handleModalKeydown: function (e) {
			if (e.key === 'Escape' && $('#aips-ai-error-modal').is(':visible')) {
				if (!$('#aips-history-modal').is(':visible')) {
					this.closeModal();
				}
			}
		},

		/**
		 * Handler for copying prompt text to clipboard.
		 *
		 * @param {Event} e
		 */
		handleCopyPrompt: function (e) {
			e.preventDefault();
			var text = $('#aips-ai-error-modal-prompt').text();
			this.copyToClipboard(text, $(e.currentTarget));
		},

		/**
		 * Handler for copying full diagnostic JSON payload.
		 *
		 * @param {Event} e
		 */
		handleCopyDiagnostic: function (e) {
			e.preventDefault();
			var text = $('#aips-ai-error-modal-diagnostic').text();
			this.copyToClipboard(text, $(e.currentTarget));
		},

		/**
		 * Handler for copying formatted support report Markdown.
		 *
		 * @param {Event} e
		 */
		handleCopySupportReport: function (e) {
			e.preventDefault();
			var reportText = (this.currentErrorData && this.currentErrorData.support_report) || '';
			if (!reportText) {
				reportText = $('#aips-ai-error-modal-diagnostic').text();
			}
			this.copyToClipboard(reportText, $(e.currentTarget));
		},

		/**
		 * Handler for elevating history modal z-index when opened from inside the error modal.
		 */
		handleOpenHistoryClick: function () {
			$('#aips-history-modal').css('z-index', 100050);
		},

		/**
		 * Calculate and apply date range preset.
		 *
		 * @param {string} preset 'today', '7d', '30d', 'all'
		 */
		applyDatePreset: function (preset) {
			var today = new Date();
			var formatDate = function (d) {
				var month = '' + (d.getMonth() + 1);
				var day   = '' + d.getDate();
				var year  = d.getFullYear();
				if (month.length < 2) {
					month = '0' + month;
				}
				if (day.length < 2) {
					day = '0' + day;
				}
				return [year, month, day].join('-');
			};

			if ('today' === preset) {
				var todayStr = formatDate(today);
				$('#aips-ai-errors-from').val(todayStr);
				$('#aips-ai-errors-to').val(todayStr);
			} else if ('7d' === preset) {
				var d7 = new Date();
				d7.setDate(today.getDate() - 7);
				$('#aips-ai-errors-from').val(formatDate(d7));
				$('#aips-ai-errors-to').val(formatDate(today));
			} else if ('30d' === preset) {
				var d30 = new Date();
				d30.setDate(today.getDate() - 30);
				$('#aips-ai-errors-from').val(formatDate(d30));
				$('#aips-ai-errors-to').val(formatDate(today));
			} else {
				$('#aips-ai-errors-from').val('');
				$('#aips-ai-errors-to').val('');
			}

			this.applyFilters();
		},

		/**
		 * Read inputs, reset to page 1, and fetch errors and summary stats.
		 */
		applyFilters: function () {
			this.page = 1;
			this.dateFrom = $('#aips-ai-errors-from').val() || '';
			this.dateTo = $('#aips-ai-errors-to').val() || '';
			this.component = $('#aips-ai-errors-component').val() || '';
			this.category = $('#aips-ai-errors-category').val() || '';
			this.errorScope = $('#aips-ai-errors-scope').val() || 'ai_only';
			this.search = $('#aips-ai-errors-search').val() || '';

			this.fetchErrors();
			this.fetchSummaryStats();
		},

		/**
		 * Reset all filters to default.
		 */
		resetFilters: function () {
			this.page = 1;
			this.dateFrom = '';
			this.dateTo = '';
			this.component = '';
			this.category = '';
			this.errorScope = 'ai_only';
			this.search = '';

			$('#aips-ai-errors-from').val('');
			$('#aips-ai-errors-to').val('');
			$('#aips-ai-errors-component').val('');
			$('#aips-ai-errors-category').val('');
			$('#aips-ai-errors-scope').val('ai_only');
			$('#aips-ai-errors-search').val('');

			$('.aips-date-preset-btn').removeClass('active');
			$('.aips-date-preset-btn[data-preset="all"]').addClass('active');

			this.fetchErrors();
			this.fetchSummaryStats();
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
					nonce: ajaxConfig.nonce || (l10n.nonce || ''),
					date_from: self.dateFrom,
					date_to: self.dateTo,
					component: self.component,
					category: self.category,
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
		 * Perform AJAX fetch of aggregate summary metrics and category rollups.
		 */
		fetchSummaryStats: function () {
			var self = this;
			var l10n = window.aipsAiErrorsL10n || {};
			var ajaxConfig = window.aipsAjax || {};

			$.ajax({
				url: ajaxConfig.ajaxUrl || window.ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_ai_error_summary',
					nonce: ajaxConfig.nonce || (l10n.nonce || ''),
					date_from: self.dateFrom,
					date_to: self.dateTo,
					error_scope: self.errorScope,
					search: self.search
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						return;
					}

					self.renderSummaryStats(response.data);
				}
			});
		},

		/**
		 * Render summary stats across the 4-card header using AIPS.Templates.
		 *
		 * @param {Object} data Summary metrics payload.
		 */
		renderSummaryStats: function (data) {
			var total = data.total_errors || 0;
			$('#aips-stat-total-errors').text(total.toLocaleString());

			// Render category pills
			var categories = data.categories || {};
			var pillsHtml = '';
			var activeCategory = $('#aips-ai-errors-category').val();

			for (var key in categories) {
				if (!Object.prototype.hasOwnProperty.call(categories, key)) {
					continue;
				}
				var cat = categories[key];
				if (cat.count === 0 && !activeCategory) {
					continue;
				}

				pillsHtml += AIPS.Templates.render('aips-tmpl-ai-errors-category-pill', {
					slug: cat.slug,
					label: cat.label,
					short_label: cat.short_label || cat.label,
					badge_class: cat.badge_class || 'aips-badge-light',
					icon: cat.icon || 'dashicons-warning',
					count: cat.count,
					active_class: activeCategory === cat.slug ? 'active' : ''
				});
			}

			if (!pillsHtml) {
				pillsHtml = AIPS.Templates.render('aips-tmpl-ai-errors-no-categories', {
					message: 'No category errors logged in this range.'
				});
			}
			$('#aips-stat-category-pills').html(pillsHtml);

			// Render Top Component
			if (data.top_component && data.top_component.count > 0) {
				$('#aips-stat-top-component').text(data.top_component.label);
				$('#aips-stat-top-component-sub').text(data.top_component.count + ' failures (' + data.top_component.percentage + '%)');
			} else {
				$('#aips-stat-top-component').text('None');
				$('#aips-stat-top-component-sub').text('No failures recorded');
			}
		},

		/**
		 * Trigger live provider connectivity test ping.
		 */
		pingProviderConnection: function () {
			var l10n = window.aipsAiErrorsL10n || {};
			var ajaxConfig = window.aipsAjax || {};
			var $btn = $('#aips-ai-errors-ping-btn');
			var $badge = $('#aips-ai-errors-ping-badge');
			var $msg = $('#aips-ai-errors-ping-msg');

			var startTime = new Date().getTime();
			$btn.prop('disabled', true).find('.dashicons').addClass('aips-spin');
			$badge.attr('class', 'aips-badge aips-badge-info').text(l10n.pinging || 'Testing…');
			$msg.text('');

			$.ajax({
				url: ajaxConfig.ajaxUrl || window.ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_test_connection',
					nonce: ajaxConfig.nonce || (l10n.nonce || '')
				},
				success: function (response) {
					var latency = new Date().getTime() - startTime;
					$btn.prop('disabled', false).find('.dashicons').removeClass('aips-spin');

					if (response && response.success) {
						$badge.attr('class', 'aips-badge aips-badge-success').text('Online (' + latency + 'ms)');
						$msg.text('Connection verified successfully.');
					} else {
						var errMsg = (response && response.data && response.data.message) || l10n.pingFailed || 'Connection Failed';
						$badge.attr('class', 'aips-badge aips-badge-danger').text('Offline');
						$msg.text(errMsg);
					}
				},
				error: function (xhr, status, error) {
					$btn.prop('disabled', false).find('.dashicons').removeClass('aips-spin');
					$badge.attr('class', 'aips-badge aips-badge-danger').text('Offline');
					$msg.text(error || 'HTTP request failed.');
				}
			});
		},

		/**
		 * Trigger CSV or JSON download with current filter parameters.
		 *
		 * @param {string} format 'csv' or 'json'
		 */
		triggerExport: function (format) {
			var l10n = window.aipsAiErrorsL10n || {};
			var ajaxConfig = window.aipsAjax || {};
			var baseUrl = ajaxConfig.ajaxUrl || window.ajaxurl;

			var params = {
				action: 'aips_export_ai_errors',
				nonce: ajaxConfig.nonce || (l10n.nonce || ''),
				format: format,
				date_from: this.dateFrom,
				date_to: this.dateTo,
				component: this.component,
				category: this.category,
				error_scope: this.errorScope,
				search: this.search
			};

			var queryString = $.param(params);
			window.location.href = baseUrl + (baseUrl.indexOf('?') !== -1 ? '&' : '?') + queryString;
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
					category: item.category || 'general',
					category_label: item.category_label || 'General Error',
					category_short: item.category_short || item.category_label || 'General',
					category_badge: item.category_badge || 'aips-badge-light',
					category_icon: item.category_icon || 'dashicons-warning',
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

			$modal.removeClass('aips-is-hidden').fadeIn(150);
			$loading.removeClass('aips-is-hidden').show();
			$inner.addClass('aips-is-hidden').hide();

			$.ajax({
				url: ajaxConfig.ajaxUrl || window.ajaxurl,
				type: 'POST',
				data: {
					action: 'aips_get_ai_error_details',
					nonce: ajaxConfig.nonce || (l10n.nonce || ''),
					log_id: logId
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						var msg = (response && response.data && response.data.message) || l10n.errorLoadingDetails || 'Failed to load error details.';
						if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
							AIPS.Utilities.showToast(msg, 'error');
						}
						self.closeModal();
						return;
					}

					self.populateModal(response.data);
					$loading.addClass('aips-is-hidden').hide();
					$inner.removeClass('aips-is-hidden').show();
				},
				error: function () {
					var err = l10n.errorLoadingDetails || 'Failed to load error details.';
					if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
						AIPS.Utilities.showToast(err, 'error');
					}
					self.closeModal();
				}
			});
		},

		/**
		 * Populate modal elements with retrieved error data and root-cause resolution banner.
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

			// Root Cause & Resolution Banner
			var cls = data.classification || {};
			var $banner = $('#aips-ai-error-modal-resolution-banner');
			$banner.attr('class', 'aips-ai-error-resolution-banner aips-severity-' + (cls.severity || 'warning'));
			$('#aips-ai-error-res-icon').attr('class', 'dashicons ' + (cls.icon || 'dashicons-warning'));

			var bannerTitle = cls.label || 'Error Diagnosis';
			if (cls.http_code) {
				bannerTitle += ' (HTTP ' + cls.http_code + ')';
			}
			$('#aips-ai-error-res-title').text(bannerTitle);
			$('#aips-ai-error-res-cause').text(cls.root_cause || '');
			$('#aips-ai-error-res-action').text(cls.action || '');

			var $link = $('#aips-ai-error-res-link');
			if (cls.action_link) {
				$link.attr('href', cls.action_link).removeClass('aips-is-hidden').show();
				$('#aips-ai-error-res-link-text').text(cls.link_label || 'View Action ↗');
			} else {
				$link.addClass('aips-is-hidden').hide();
			}

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
				category: data.category,
				classification: data.classification,
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
			var $modal = $('#aips-ai-error-modal');
			$modal.fadeOut(150, function () {
				$modal.addClass('aips-is-hidden');
			});
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
