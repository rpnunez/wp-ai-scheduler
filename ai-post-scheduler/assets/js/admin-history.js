/**
 * History Page JavaScript
 *
 * Manages the History admin page with two main modules:
 * - AIPS.HistoryModalShared: Shared utilities for modal header management, log filtering,
 *   JSON viewer toggling, detail toggling, copy-to-clipboard, and standalone modal operations.
 * - AIPS.History: Main History page module handling search/filter, pagination, row selection,
 *   bulk/individual delete, retry, and the logs modal that renders all aips_history_log
 *   entries for a selected history container.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * Shared utilities for managing history modals.
	 *
	 * Provides methods for updating and resetting modal headers with title, actions, and status information.
	 *
	 * @namespace AIPS.HistoryModalShared
	 * @type {Object}
	 */
	AIPS.HistoryModalShared = {
		/**
		 * Update modal header with container-specific title, actions, and status badge.
		 *
		 * @param {jQuery} $modal     Modal element.
		 * @param {Object} container  History container object with header_title, header_actions, status, status_class.
		 * @param {Object} options    Configuration with titleSelector, actionsSelector, statusSelector, defaultTitle.
		 */
		updateModalHeader: function ($modal, container, options) {
			var settings = $.extend({
				titleSelector: '',
				actionsSelector: '',
				statusSelector: '',
				defaultTitle: 'History Details'
			}, options || {});
			var title = container && container.header_title
				? container.header_title
				: settings.defaultTitle;
			var actions = container && Array.isArray(container.header_actions)
				? container.header_actions
				: [];
			var $title = settings.titleSelector ? $modal.find(settings.titleSelector) : $();
			var $actions = settings.actionsSelector ? $modal.find(settings.actionsSelector) : $();
			var $status = settings.statusSelector ? $modal.find(settings.statusSelector) : $();
			var statusHtml = '';
			var actionsHtml = '';

			$title.text(title);

			actions.forEach(function (action) {
				if (!action || !action.url || !action.label) {
					return;
				}

				actionsHtml += '<a href="' + $('<div>').text(String(action.url)).html() + '" target="_blank" rel="noopener noreferrer">'
					+ $('<div>').text(String(action.label)).html()
					+ '</a>';
			});

			if (container && container.status && container.status_class) {
				statusHtml = '<span class="aips-badge '
					+ $('<div>').text(String(container.status_class)).html()
					+ '">'
					+ $('<div>').text(String(container.status)).html()
					+ '</span>';
			}

			$actions.html(actionsHtml);
			$status.html(statusHtml);
		},

		/**
		 * Reset modal header to default empty state.
		 *
		 * @param {jQuery} $modal  Modal element.
		 * @param {Object} options Configuration with titleSelector, actionsSelector, statusSelector, defaultTitle.
		 */
		resetModalHeader: function ($modal, options) {
			var settings = $.extend({
				titleSelector: '',
				actionsSelector: '',
				statusSelector: '',
				defaultTitle: 'History Details'
			}, options || {});

			if (settings.titleSelector) {
				$modal.find(settings.titleSelector).text(settings.defaultTitle);
			}
			if (settings.actionsSelector) {
				$modal.find(settings.actionsSelector).empty();
			}
			if (settings.statusSelector) {
				$modal.find(settings.statusSelector).empty();
			}
		},

		/**
		 * Extract log type IDs from a log row element.
		 *
		 * @param {jQuery} $row Log row element with data-type-ids or data-type-id attribute.
		 * @return {Array} Array of trimmed type ID strings.
		 */
		getRowTypes: function ($row) {
			return String($row.attr('data-type-ids') || $row.data('type-id') || '')
				.split(',')
				.map(function (value) {
					return $.trim(String(value));
				})
				.filter(Boolean);
		},

		/**
		 * Check if a row matches a specific type filter.
		 *
		 * @param {Array}  rowTypes Array of type IDs from the row.
		 * @param {string} typeId   Type ID to match.
		 * @return {boolean} True if row matches the type, false otherwise.
		 */
		rowMatchesType: function (rowTypes, typeId) {
			var normalizedTypeId = String(typeId || '');

			if (!normalizedTypeId || normalizedTypeId === 'all') {
				return true;
			}

			if (normalizedTypeId === 'ai_request_response') {
				return rowTypes.indexOf('5') !== -1 || rowTypes.indexOf('6') !== -1;
			}

			return rowTypes.indexOf(normalizedTypeId) !== -1;
		},

		/**
		 * Apply type filter to log rows in the modal.
		 *
		 * @param {jQuery} $modal  Modal element containing log rows.
		 * @param {jQuery} $button Filter button that was clicked.
		 */
		applyTypeFilter: function ($modal, $button) {
			var typeId = $button.data('type-id');
			var self = this;

			$modal.find('.aips-log-type-filter-btn')
				.removeClass('aips-btn-primary')
				.addClass('aips-btn-ghost');
			$button.removeClass('aips-btn-ghost').addClass('aips-btn-primary');

			$modal.find('.aips-history-logs-table tbody tr').each(function () {
				var $row = $(this);
				$row.toggle(self.rowMatchesType(self.getRowTypes($row), typeId));
			});
		},

		/**
		 * Toggle collapsible log detail section.
		 *
		 * @param {jQuery} $scope  Scope element containing the detail section.
		 * @param {jQuery} $button Toggle button element.
		 * @param {Object} labels  Label strings for expand/collapse states.
		 */
		toggleLogDetail: function ($scope, $button, labels) {
			var targetSelector = $button.data('target');
			var $target = $scope.find(targetSelector);
			var showLabel = labels && labels.show ? labels.show : 'Show details';
			var hideLabel = labels && labels.hide ? labels.hide : 'Hide details';

			if (!$target.length) {
				return;
			}

			$target.slideToggle(150, function () {
				$button.text($target.is(':visible') ? hideLabel : showLabel);
			});
		},

		/**
		 * Toggle JSON viewer mode between raw and formatted display.
		 *
		 * @param {jQuery} $toggle Checkbox toggle element.
		 */
		toggleJsonViewerMode: function ($toggle) {
			var $renderer = $toggle.closest('.aips-history-log-renderer');

			if (!$renderer.length) {
				return;
			}

			$renderer.toggleClass('aips-json-viewer-enabled', $toggle.is(':checked'));
		},

		/**
		 * Fallback copy method using textarea for older browsers.
		 *
		 * @param {jQuery} $target Element containing text to copy.
		 * @return {boolean} True if copy succeeded, false otherwise.
		 */
		copyDetailFallback: function ($target) {
			var $pre = $target.find('pre').first();
			var range;
			var selection;

			if (!$pre.length) {
				return false;
			}

			try {
				$target.show();
				range = document.createRange();
				range.selectNodeContents($pre[0]);
				selection = window.getSelection();

				if (!selection) {
					return false;
				}

				selection.removeAllRanges();
				selection.addRange(range);

				if (!document.execCommand('copy')) {
					selection.removeAllRanges();
					return false;
				}

				selection.removeAllRanges();
				return true;
			} catch (error) {
				if (selection) {
					selection.removeAllRanges();
				}

				return false;
			}
		},

		/**
		 * Show copy success feedback to user.
		 *
		 * @param {jQuery} $button Button element to update.
		 * @param {Object} labels  Label strings for copy/copied states.
		 * @param {Object} options Configuration with disable and duration properties.
		 */
		showCopySuccess: function ($button, labels, options) {
			var settings = $.extend({
				disable: false,
				duration: 1500
			}, options || {});
			var copyLabel = labels && labels.copy ? labels.copy : 'Copy';
			var copiedLabel = labels && labels.copied ? labels.copied : 'Copied!';

			$button.text(copiedLabel);
			if (settings.disable) {
				$button.prop('disabled', true);
			}

			setTimeout(function () {
				$button.text(copyLabel);
				if (settings.disable) {
					$button.prop('disabled', false);
				}
			}, settings.duration);
		},

		/**
		 * Copy log detail text to clipboard.
		 *
		 * Uses modern Clipboard API with fallback to execCommand for older browsers.
		 *
		 * @param {jQuery} $scope  Scope element containing the target.
		 * @param {jQuery} $button Copy button element.
		 * @param {Object} labels  Label strings for copy/copied states.
		 * @param {Object} options Configuration for success feedback.
		 */
		copyLogDetail: function ($scope, $button, labels, options) {
			var targetSelector = $button.data('copy-target');
			var $target = $scope.find(targetSelector);
			var text = $target.find('pre').text();
			var self = this;

			if (!text) {
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text)
					.then(function () {
						self.showCopySuccess($button, labels, options);
					})
					.catch(function () {
						if (self.copyDetailFallback($target)) {
							self.showCopySuccess($button, labels, options);
						}
					});
				return;
			}

			if (self.copyDetailFallback($target)) {
				self.showCopySuccess($button, labels, options);
			}
		},

		/**
		 * Initialize standalone history modal opener for use outside History page.
		 */
		initStandaloneOpener: function () {
			$(document).on('click', '.aips-open-history-modal', this.onStandaloneOpenClick.bind(this));
		},

		/**
		 * Get localized strings for history modal.
		 *
		 * @return {Object} Localization object.
		 */
		getModalL10n: function () {
			return window.aipsHistoryModalL10n || window.aipsHistoryL10n || {};
		},

		/**
		 * Handle click on standalone history modal opener button.
		 *
		 * @param {Event} e Click event.
		 */
		onStandaloneOpenClick: function (e) {
			e.preventDefault();
			e.stopPropagation();
			this.openStandaloneHistoryModal($(e.currentTarget));
		},

		getStandaloneAjaxConfig: function () {
			if (window.aipsAjax && window.aipsAjax.ajaxUrl && window.aipsAjax.nonce) {
				return window.aipsAjax;
			}

			if (window.aipsHistoryModalAjax && window.aipsHistoryModalAjax.ajaxUrl && window.aipsHistoryModalAjax.nonce) {
				return window.aipsHistoryModalAjax;
			}

			return null;
		},

		openStandaloneHistoryModal: function ($button) {
			var historyId = parseInt($button.data('history-id') || 0, 10);
			var ajaxConfig = this.getStandaloneAjaxConfig();
			var $modal = $('#aips-history-modal');
			var l10n = this.getModalL10n();
			var self = this;

			if (!historyId) {
				AIPS.Utilities.showToast(l10n.invalidHistoryId || 'Invalid history ID.', 'error');
				return;
			}

			if (!ajaxConfig) {
				AIPS.Utilities.showToast(l10n.loadingError || 'Error loading history modal.', 'error');
				return;
			}

			if (!$modal.length) {
				return;
			}

			self.showStandaloneModalLoading($modal);

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_get_history_modal_html',
				url: ajaxConfig.ajaxUrl,
				nonce: ajaxConfig.nonce,
				data: { history_id: historyId },
				toastOnError: false,
				errorFallback: l10n.loadingFailed || 'Failed to load history modal.',
				onSuccess: function (data) {
					self.updateModalHeader($modal, data.container || {}, {
						titleSelector: '#aips-history-modal-title',
						actionsSelector: '#aips-history-modal-actions',
						statusSelector: '#aips-history-modal-status',
						defaultTitle: l10n.historyDetailsTitle || 'History Details'
					});
					$modal.find('#aips-history-modal-content').html(data.modal_html || '');
					self.bindStandaloneModalEvents($modal);
					$modal.fadeIn(200);
				},
				onError: function (message) {
					AIPS.Utilities.showToast(message, 'error');
					$modal.fadeOut(200);
				}
			});
		},

		/**
		 * Show loading indicator in standalone modal.
		 *
		 * @param {jQuery} $modal Modal element.
		 */
		showStandaloneModalLoading: function ($modal) {
			var l10n = this.getModalL10n();
			var loadingHtml = '<div style="text-align: center; padding: 20px;"><span class="dashicons dashicons-update aips-spin" aria-hidden="true"></span> '
				+ (l10n.loading || 'Loading…')
				+ '</div>';

			this.resetModalHeader($modal, {
				titleSelector: '#aips-history-modal-title',
				actionsSelector: '#aips-history-modal-actions',
				statusSelector: '#aips-history-modal-status',
				defaultTitle: l10n.historyDetailsTitle || 'History Details'
			});
			$modal.find('#aips-history-modal-content').html(loadingHtml);
			$modal.fadeIn(200);
		},

		/**
		 * Bind event listeners for standalone modal interactions.
		 *
		 * @param {jQuery} $modal Modal element.
		 */
		bindStandaloneModalEvents: function ($modal) {
			var self = this;
			var l10n = this.getModalL10n();

			$modal.find('.aips-modal-close').off('click').on('click', function (e) {
				e.preventDefault();
				$modal.fadeOut(200);
			});

			$modal.off('click.historyModal').on('click.historyModal', function (e) {
				if ($(e.target).is('#aips-history-modal')) {
					$modal.fadeOut(200);
				}
			});

			$modal.find('.aips-log-type-filter-btn').off('click').on('click', function (e) {
				e.preventDefault();
				self.applyTypeFilter($modal, $(this));
			});

			$modal.find('.aips-log-toggle').off('click').on('click', function (e) {
				e.preventDefault();
				self.toggleLogDetail($modal, $(this), {
					show: l10n.showDetails || 'Show details',
					hide: l10n.hideDetails || 'Hide details'
				});
			});

			$modal.find('.aips-json-viewer-toggle').off('change').on('change', function () {
				self.toggleJsonViewerMode($(this));
			});

			$modal.find('[data-copy-target]').off('click').on('click', function (e) {
				e.preventDefault();
				self.copyLogDetail($modal, $(this), {
					copy: l10n.copyDetails || 'Copy',
					copied: l10n.copiedDetails || 'Copied!'
				}, {
					disable: true,
					duration: 1500
				});
			});

			$(document).off('keydown.historyModal').on('keydown.historyModal', function (e) {
				if (e.keyCode === 27 && $modal.is(':visible')) {
					$modal.fadeOut(200);
				}
			});
		}
	};

	/**
	 * Main History page module for managing search, filter, pagination, and bulk operations.
	 *
	 * Handles search/filter, pagination, row selection, bulk/individual delete, retry, and the logs modal
	 * that renders all aips_history_log entries for a selected history container.
	 *
	 * @namespace AIPS.History
	 * @type {Object}
	 */
	AIPS.History = {

		/* ========================================================================
		 * State Properties
		 * ======================================================================== */

		/** @type {string} Current status filter value (e.g., 'failed', 'success', 'pending') */
		statusFilter: '',

		/** @type {string} Raw search query as entered by the user */
		searchQuery: '',

		/** @type {number} Minimum allowed heartbeat interval in seconds */
		MIN_HEARTBEAT_INTERVAL: 5,

		/** @type {string} Domain filter value (template, author-topic, author-post, etc.) */
		domainFilter: '',

		/** @type {string} Actor filter value (cron, manual, etc.) */
		actorFilter: '',

		/** @type {string} Correlation ID filter for request tracing */
		correlationId: '',

		/** @type {string} Date range filter start (YYYY-MM-DD format) */
		dateFrom: '',

		/** @type {string} Date range filter end (YYYY-MM-DD format) */
		dateTo: '',

		/** @type {boolean} Whether auto-refresh via heartbeat is currently enabled */
		autoRefreshEnabled: false,

		/** @type {number} Current heartbeat polling interval in seconds */
		heartbeatIntervalSeconds: 5,

		/** @type {number|null} Default WordPress heartbeat interval to restore on disable */
		defaultHeartbeatInterval: null,

		/** @type {boolean} Flag to prevent concurrent auto-refresh AJAX requests */
		isAutoRefreshing: false,

		/* ========================================================================
		 * Initialization & Event Binding
		 * ======================================================================== */

		/**
		 * Initialize the History module.
		 *
		 * Reads initial filter/search state from DOM, binds event listeners,
		 * initializes heartbeat auto-refresh controls, and opens a specific history
		 * container if query params are present.
		 */
		init: function () {
			if (!this.isHistoryPage()) {
				return;
			}

			this.statusFilter = $('#aips-filter-status').val() || '';
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';
			this.searchQuery  = $('#aips-history-search-input').val() || '';
			this.syncSearchClearButton();
			this.bindEvents();
			this.initHeartbeatAutoRefresh();
			this.maybeOpenFromQuery();
		},

		/**
		 * Register all event listeners for the History admin page.
		 *
		 * Uses delegated event handlers for dynamic content and namespaced
		 * events for heartbeat and page exit cleanup.
		 */
		bindEvents: function () {
			/* --- Modal Events --- */
			// Open logs modal
			$(document).on('click', '.aips-view-history-logs', this.openLogsModal.bind(this));

			// Collapsible log-detail sections inside the modal
			$(document).on('click', '.aips-log-toggle', this.toggleLogDetail.bind(this));

			// Copy log detail to clipboard
			$(document).on('click', '.aips-log-copy', this.copyLogDetail.bind(this));

			// Log type filter tabs inside the modal
			$(document).on('click', '.aips-log-type-filter-btn', this.filterLogsByType.bind(this));
			$(document).on('change', '.aips-json-viewer-toggle', this.toggleJsonViewerMode.bind(this));

			// Close modal via close button or backdrop click.
			// $(document).on('click', '#aips-history-logs-modal .aips-modal-close', this.closeLogsModal.bind(this)); // Handled globally by admin.js
			$(document).on('click', '#aips-history-logs-modal', this.closeLogsModalOnOverlay.bind(this));

			/* --- Bulk Selection Events --- */
			// Select-all and individual row checkboxes
			$(document).on('change', '#aips-cb-select-all', this.toggleSelectAll.bind(this));
			$(document).on('change', '.aips-history-cb', this.onRowCheckboxChange.bind(this));

			// Bulk delete
			$(document).on('click', '#aips-delete-selected-btn', this.deleteSelected.bind(this));

			/* --- Row Action Events --- */
			// Overflow toggle for the action group
			$(document).on('click', '.aips-row-action-overflow-toggle', this.onRowActionOverflowToggle.bind(this));
			$(document).on('click', '.aips-row-action-menu .aips-row-action-item', this.onRowActionItemClick.bind(this));
			$(document).on('click', this.onDocumentClick.bind(this));
			$(document).on('keydown', this.onDocumentKeyDown.bind(this));

			// Individual row delete
			$(document).on('click', '.aips-delete-history', this.deleteSingleItem.bind(this));

			// Retry failed generation
			$(document).on('click', '.aips-retry-generation', this.retryGeneration.bind(this));

			// Clear history (failed / all)
			$(document).on('click', '.aips-clear-history', this.clearHistory.bind(this));

			/* --- Reload & Pagination Events --- */
			// Reload button
			$(document).on('click', '#aips-reload-history-btn', this.onReloadClick.bind(this));

			// Pagination links
			$(document).on(
				'click',
				'.aips-history-page-link, .aips-history-page-prev, .aips-history-page-next',
				this.loadPage.bind(this)
			);

			/* --- Filter & Search Events --- */
			// Filter button and status dropdown
			$(document).on('click', '#aips-filter-btn', this.applyFilter.bind(this));
			$(document).on('change', '#aips-filter-status', this.applyFilter.bind(this));

			// Search: live client-side row filter + server reload on Enter
			$(document).on('input', '#aips-history-search-input', this.onSearchInput.bind(this));
			$(document).on('keydown', '#aips-history-search-input', this.onSearchKeydown.bind(this));
			$(document).on('click', '#aips-history-search-clear', this.clearSearch.bind(this));
			$(document).on('click', '.aips-clear-history-search-btn', this.clearSearch.bind(this));

			/* --- Export Event --- */
			// Export CSV
			$(document).on('click', '#aips-export-history-btn', this.exportHistory.bind(this));

			/* --- Auto Refresh / Heartbeat Events --- */
			// Auto refresh controls
			$(document).on('change', '#aips-history-auto-refresh', this.toggleAutoRefresh.bind(this));
			$(document).on('change', '#aips-history-heartbeat-interval', this.changeHeartbeatInterval.bind(this));

			// Heartbeat tick (namespaced for cleanup)
			$(document).on('heartbeat-tick.aipsHistory', this.onHeartbeatTick.bind(this));

			// Page exit cleanup (namespaced)
			$(window).on('beforeunload.aipsHistory pagehide.aipsHistory', this.onPageExit.bind(this));
		},



		/* ========================================================================
		 * Row Action Overflow Menu
		 * ======================================================================== */

		onRowActionOverflowToggle: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $toggle = $(e.currentTarget);
			var menuId  = $toggle.attr('aria-controls');
			var $menu   = menuId ? $('#' + menuId) : $();

			if (!$menu.length) {
				return;
			}

			var isExpanded = $toggle.attr('aria-expanded') === 'true';
			this.closeAllRowActionMenus();

			if (!isExpanded) {
				$toggle.attr('aria-expanded', 'true');
				$menu.prop('hidden', false);
			}
		},

		onRowActionItemClick: function () {
			this.closeAllRowActionMenus();
		},

		onDocumentClick: function (e) {
			if ($(e.target).closest('.aips-row-action-group, .aips-row-action-menu').length) {
				return;
			}
			this.closeAllRowActionMenus();
		},

		onDocumentKeyDown: function (e) {
			if (e.key === 'Escape') {
				this.closeAllRowActionMenus();
			}
		},

		closeAllRowActionMenus: function () {
			$('.aips-row-action-overflow-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
			$('.aips-row-action-menu').prop('hidden', true);
		},

		/**
		 * Auto-open a specific history container from query args when available.
		 *
		 * Supports two query parameters:
		 * - `history_id`: Opens the logs modal for a specific history container ID
		 * - `post_id`: Pre-fills the search box with the post ID and filters the table
		 */
		maybeOpenFromQuery: function () {
			var params = new URLSearchParams(window.location.search || '');
			var historyId = parseInt(params.get('history_id') || 0, 10);
			var postId = parseInt(params.get('post_id') || 0, 10);

			// Pre-fill search with post_id if provided
			if (postId > 0 && !this.searchQuery) {
				this.searchQuery = String(postId);
				$('#aips-history-search-input').val(String(postId));
				this.syncSearchClearButton();
				$('#aips-history-search-input').trigger('input');
			}

			// Auto-open modal for specific history_id
			if (historyId > 0) {
				this.openLogsModalFromId(historyId);
			}
		},

		/**
		 * Open history logs modal for a known history ID.
		 *
		 * Creates a synthetic trigger element and calls openLogsModal with a mock event.
		 *
		 * @param {number} historyId History container ID.
		 */
		openLogsModalFromId: function (historyId) {
			var $trigger = $('<button type="button" class="aips-view-history-logs" data-id="' + historyId + '"></button>');
			this.openLogsModal({
				preventDefault: function () {},
				stopPropagation: function () {},
				currentTarget: $trigger.get(0)
			});
		},

		/* ========================================================================
		 * Logs Modal - View History Details
		 * ======================================================================== */

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

			var $modal   = $('#aips-history-logs-modal');
			var $content = $('#aips-history-logs-modal').find('.aips-modal-content-body');
			var T        = AIPS.Templates;

			AIPS.HistoryModalShared.resetModalHeader($modal, {
				titleSelector: '#aips-history-logs-modal-title',
				actionsSelector: '#aips-history-logs-modal-actions',
				statusSelector: '#aips-history-logs-modal-status',
				defaultTitle: aipsHistoryL10n.historyDetailsTitle || 'History Details'
			});
			$content.html(T.render('aips-tmpl-history-loading-msg', {
				text: aipsHistoryL10n.loadingLogs || 'Loading logs\u2026'
			}));
			$modal.fadeIn(200);

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_get_history_modal_html',
				data: { history_id: historyId },
				toastOnError: false,
				errorFallback: aipsHistoryL10n.errorLoading || 'Error loading logs.',
				onSuccess: function (data) {
					AIPS.HistoryModalShared.updateModalHeader($modal, data.container, {
						titleSelector: '#aips-history-logs-modal-title',
						actionsSelector: '#aips-history-logs-modal-actions',
						statusSelector: '#aips-history-logs-modal-status',
						defaultTitle: aipsHistoryL10n.historyDetailsTitle || 'History Details'
					});
					$content.html(data.modal_html || '');
				},
				onError: function (message) {
					$content.html(T.render('aips-tmpl-history-error-msg', {
						message: message
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
			var $scope = $(e.currentTarget).closest('.aips-modal');
			if (!$scope.length) {
				$scope = $(document);
			}
			AIPS.HistoryModalShared.toggleLogDetail($scope, $(e.currentTarget), {
				show: aipsHistoryL10n.showDetails || 'Show details',
				hide: aipsHistoryL10n.hideDetails || 'Hide details'
			});
		},

		/**
		 * Check if current page is the History admin page.
		 *
		 * @return {boolean} True if on History page, false otherwise.
		 */
		isHistoryPage: function () {
			return $('#aips-history-logs-modal').length > 0
				|| $('#aips-history-search-input').length > 0
				|| $('#aips-history-tbody').length > 0;
		},

		/* ========================================================================
		 * Auto Refresh & Heartbeat
		 * ======================================================================== */

		/**
		 * Initialize heartbeat auto-refresh controls and defaults.
		 *
		 * Checks for WordPress Heartbeat API availability and configures UI accordingly.
		 * Disables controls if Heartbeat is unavailable.
		 */
		initHeartbeatAutoRefresh: function () {
			if (!window.wp || !wp.heartbeat || typeof wp.heartbeat.interval !== 'function') {
				// Disable auto-refresh controls and show unavailability message
				$('#aips-history-auto-refresh')
					.prop('disabled', true)
					.attr('title', heartbeatUnavailableText);
				$('#aips-history-heartbeat-interval')
					.prop('disabled', true)
					.attr('title', heartbeatUnavailableText);
				$('#aips-history-auto-refresh-help').text(heartbeatUnavailableText);
				return;
			}

			// Store default heartbeat interval for restoration on disable
			this.defaultHeartbeatInterval = wp.heartbeat.interval();

			// Clear help text (no errors)
			$('#aips-history-auto-refresh-help').empty();

			// Read initial interval from DOM
			this.heartbeatIntervalSeconds = parseInt(
				$('#aips-history-heartbeat-interval').val() || String(this.MIN_HEARTBEAT_INTERVAL),
				10
			);
		},

		/**
		 * Enable or disable auto-refresh polling on heartbeat.
		 *
		 * @param {Event} e Change event from #aips-history-auto-refresh checkbox.
		 */
		toggleAutoRefresh: function (e) {
			var enabled = $(e.currentTarget).is(':checked');
			var $intervalSelect = $('#aips-history-heartbeat-interval');

			// Update state
			this.autoRefreshEnabled = enabled;
			$intervalSelect.prop('disabled', !enabled);

			// If disabling, restore defaults and exit
			if (!enabled) {
				this.disableAutoRefresh();
				return;
			}

			// Read current interval selection
			this.heartbeatIntervalSeconds = parseInt(
				$intervalSelect.val() || String(this.MIN_HEARTBEAT_INTERVAL),
				10
			);

			// Apply interval to WordPress Heartbeat API
			this.applyHeartbeatInterval();

			// Trigger immediate heartbeat connection
			if (window.wp && wp.heartbeat && typeof wp.heartbeat.connectNow === 'function') {
				wp.heartbeat.connectNow();
			}
		},

		/**
		 * Update heartbeat interval selection while auto-refresh is enabled.
		 *
		 * @param {Event} e Change event from #aips-history-heartbeat-interval select.
		 */
		changeHeartbeatInterval: function (e) {
			// Update interval state
			this.heartbeatIntervalSeconds = parseInt(
				$(e.currentTarget).val() || String(this.MIN_HEARTBEAT_INTERVAL),
				10
			);

			// Only apply if auto-refresh is currently enabled
			if (!this.autoRefreshEnabled) {
				return;
			}

			// Apply new interval to WordPress Heartbeat API
			this.applyHeartbeatInterval();

			// Trigger immediate reconnection with new interval
			if (window.wp && wp.heartbeat && typeof wp.heartbeat.connectNow === 'function') {
				wp.heartbeat.connectNow();
			}
		},

		/**
		 * Apply current heartbeat interval to WordPress Heartbeat API.
		 *
		 * Enforces minimum interval of MIN_HEARTBEAT_INTERVAL seconds.
		 */
		applyHeartbeatInterval: function () {
			// Ensure Heartbeat API is available
			if (!window.wp || !wp.heartbeat || typeof wp.heartbeat.interval !== 'function') {
				return;
			}

			// Parse and validate interval
			var parsedInterval = parseInt(this.heartbeatIntervalSeconds, 10);
			if (isNaN(parsedInterval)) {
				parsedInterval = this.MIN_HEARTBEAT_INTERVAL;
			}

			// Enforce minimum interval (5 seconds for quick refresh)
			var interval = Math.max(
				this.MIN_HEARTBEAT_INTERVAL,
				parsedInterval
			);

			// Apply to WordPress Heartbeat API
			wp.heartbeat.interval(interval);
		},

		/**
		 * Restore the default heartbeat interval and disable auto-refresh state.
		 *
		 * Resets heartbeat to WordPress default and clears auto-refresh flags.
		 */
		disableAutoRefresh: function () {
			// Restore default heartbeat interval if available
			var defaultInterval = parseInt(this.defaultHeartbeatInterval, 10);
			if (
				window.wp
				&& wp.heartbeat
				&& typeof wp.heartbeat.interval === 'function'
				&& this.defaultHeartbeatInterval !== null
				&& this.defaultHeartbeatInterval !== undefined
				&& !isNaN(defaultInterval)
			) {
				wp.heartbeat.interval(defaultInterval);
			}

			// Reset state flags
			this.autoRefreshEnabled = false;
			this.isAutoRefreshing = false;

			// Update UI
			$('#aips-history-auto-refresh').prop('checked', false);
			$('#aips-history-heartbeat-interval').prop('disabled', true);
		},

		/**
		 * Cleanup heartbeat state and namespaced listeners when leaving page.
		 *
		 * Prevents heartbeat interval changes from persisting across page loads.
		 */
		onPageExit: function () {
			// Restore default heartbeat and clear state
			this.disableAutoRefresh();

			// Remove namespaced event listeners
			$(document).off('heartbeat-tick.aipsHistory');
			$(window).off('.aipsHistory');
		},

		/**
		 * Handle heartbeat tick updates for background polling.
		 *
		 * Triggered by WordPress Heartbeat API at configured interval.
		 * Reloads history table in the background without user interaction.
		 */
		onHeartbeatTick: function () {
			// Skip if auto-refresh is disabled or already in progress
			if (!this.autoRefreshEnabled || this.isAutoRefreshing) {
				return;
			}

			// Set flag to prevent concurrent refresh requests
			this.isAutoRefreshing = true;

			// Reload current page via AJAX
			this.reload(this.getCurrentPage(), { fromHeartbeat: true });
		},

		/**
		 * Resolve current page number from URL query params.
		 *
		 * @return {number} Current page number (1-based), defaults to 1.
		 */
		getCurrentPage: function () {
			var params = new URLSearchParams(window.location.search || '');
			var paged = parseInt(params.get('paged') || '1', 10);
			return !isNaN(paged) && paged > 0 ? paged : 1;
		},

		/**
		 * Copy the full log detail text for a row.
		 *
		 * @param {Event} e Click event.
		 */
		copyLogDetail: function (e) {
			e.preventDefault();
			var $scope = $(e.currentTarget).closest('.aips-modal');
			if (!$scope.length) {
				$scope = $(document);
			}
			AIPS.HistoryModalShared.copyLogDetail($scope, $(e.currentTarget), {
				copy: aipsHistoryL10n.copyDetails || 'Copy',
				copied: aipsHistoryL10n.copiedDetails || 'Copied!'
			}, {
				disable: false,
				duration: 2000
			});
		},

		/**
		 * Filter the visible log rows to only those matching the selected type.
		 *
		 * @param {Event} e - Click event from an `.aips-log-type-filter-btn` element.
		 */
		filterLogsByType: function (e) {
			e.preventDefault();
			AIPS.HistoryModalShared.applyTypeFilter($('#aips-history-logs-modal'), $(e.currentTarget));
		},

		/**
		 * Toggle JSON tree view vs. raw JSON for the current modal content.
		 *
		 * @param {Event} e Change event.
		 */
		toggleJsonViewerMode: function (e) {
			AIPS.HistoryModalShared.toggleJsonViewerMode($(e.currentTarget));
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

		/* ========================================================================
		 * Row Selection & Bulk Delete
		 * ======================================================================== */

		/**
		 * Toggle all row checkboxes to match the select-all checkbox state.
		 *
		 * @param {Event} e
		 */
		toggleSelectAll: function (e) {
			AIPS.Core.Table.toggleAllRows({
				checked: $(e.target).prop('checked'),
				rowCheckboxSelector: '.aips-history-cb'
			});
			this.updateDeleteButton();
		},

		/**
		 * Sync the select-all checkbox and Delete Selected button on row change.
		 */
		onRowCheckboxChange: function () {
			this.updateDeleteButton();
			AIPS.Core.Table.syncSelectAll({
				$selectAll: $('#aips-cb-select-all'),
				rowCheckboxSelector: '.aips-history-cb'
			});
		},

		/**
		 * Enable or disable the Delete Selected button based on current selections.
		 */
		updateDeleteButton: function () {
			AIPS.Core.Bulk.updateToolbarState({
				rowCheckboxSelector: '.aips-history-cb',
				$toolbarActions: $('#aips-delete-selected-btn')
			});
		},

		/**
		 * Send a bulk-delete request for all checked containers.
		 *
		 * @param {Event} e - Click event from `#aips-delete-selected-btn`.
		 */
		deleteSelected: function (e) {
			e.preventDefault();

			var ids = AIPS.Core.Table.getSelectedIds('.aips-history-cb');

			if (!ids.length) {
				return;
			}

			var self = this;

			AIPS.Core.Bulk.dispatch({
				action: 'aips_bulk_delete_history',
				ids: ids,
				confirmMessage: aipsHistoryL10n.confirmBulkDelete || 'Delete the selected history containers? This cannot be undone.',
				confirmHeading: 'Notice',
				confirmLabel: aipsHistoryL10n.confirmDeleteLabel || 'Yes, delete',
				cancelLabel: aipsHistoryL10n.cancelLabel || 'No, cancel',
				$button: $(e.currentTarget),
				loadingLabel: '<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.deleting || 'Deleting\u2026'),
				loadingLabelIsHtml: true,
				errorFallback: aipsHistoryL10n.errorDeleting || 'Error deleting items.',
				onSuccess: function () {
					AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Items deleted successfully.', 'success');
					self.reload();
				}
			});
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
					AIPS.Core.Http.ajaxRequest({
						action: 'aips_bulk_delete_history',
						data: { ids: [id] },
						errorFallback: aipsHistoryL10n.errorDeleting || 'Error deleting item.',
						onSuccess: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Item deleted.', 'success');
							self.reload();
						}
					});
				}}
			]);
		},

		/* ========================================================================
		 * Retry Generation
		 * ======================================================================== */

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

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_retry_generation',
				data: { history_id: id },
				errorFallback: aipsHistoryL10n.errorRetrying || 'An error occurred. Please try again.',
				onSuccess: function (data) {
					AIPS.Utilities.showToast(data.message, 'success');
					self.reload();
				},
				onError: function (message) {
					AIPS.Utilities.showToast(message, 'error');
					$btn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/* ========================================================================
		 * Clear History
		 * ======================================================================== */

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
					AIPS.Core.Http.ajaxRequest({
						action: 'aips_clear_history',
						data: { status: status },
						errorFallback: aipsHistoryL10n.errorClearing || 'Error clearing history.',
						onSuccess: function (data) {
							AIPS.Utilities.showToast(data.message || (aipsHistoryL10n.clearedSuccess || 'History cleared.'), 'success');
							self.reload();
						}
					});
				}}
			]);
		},

		/* ========================================================================
		 * Reload, Pagination, Filters & Search
		 * ======================================================================== */

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
		 * @param {number} [paged=current URL page] 1-based page number to load.
		 *                                          Defaults to current `paged` URL query param.
		 */
		reload: function (paged, options) {
			paged = (paged === undefined || paged === null)
				? this.getCurrentPage()
				: Math.max(1, parseInt(paged, 10));
			options = $.extend({
				fromHeartbeat: false
			}, options || {});

			var self       = this;
			var $tbody     = $('#aips-history-tbody');
			var $pagWrap   = $('#aips-history-pagination-wrap');
			var $timeline  = $('#aips-history-timeline-content');
			var $reloadBtn = $('#aips-reload-history-btn');
			var origHtml   = $reloadBtn.html();

			// Show a loading placeholder in the table body.
			if ($tbody.length) {
				$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-loading', {
					text: aipsHistoryL10n.loading || 'Loading\u2026'
				}));
			}
			if (!options.fromHeartbeat) {
				$reloadBtn.prop('disabled', true).html(
					'<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> '
					+ (aipsHistoryL10n.reloading || 'Reloading\u2026')
				);
			}

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_reload_history',
				data: {
					status: self.statusFilter,
					search: self.searchQuery,
					domain: self.domainFilter,
					actor: self.actorFilter,
					correlation_id: self.correlationId,
					date_from: self.dateFrom,
					date_to: self.dateTo,
					paged: paged
				},
				toastOnError: false,
				errorFallback: aipsHistoryL10n.errorReloading || 'Failed to reload history.',
				onSuccess: function (data) {
					var itemsHtml = data.items_html || '';

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

					if ($pagWrap.length && data.pagination_html !== undefined) {
						$pagWrap.html(data.pagination_html);
					}

					if ($timeline.length && data.timeline_html !== undefined) {
						$timeline.html(data.timeline_html);
					}

					// Refresh stat cards.
					var stats = data.stats;
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
				onError: function (message) {
					if (!options.fromHeartbeat) {
						AIPS.Utilities.showToast(message, 'error');
					}
				}
			}).always(function () {
				if (!options.fromHeartbeat) {
					$reloadBtn.prop('disabled', false).html(origHtml);
				}
				self.isAutoRefreshing = false;
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
		AIPS.HistoryModalShared.initStandaloneOpener();
	});

})(jQuery);
