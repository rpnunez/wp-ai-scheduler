import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';
import { HistoryModel } from '../models/history';

/**
 * History Page View Controller
 */
export const HistoryView = BaseListView.extend({
	el: 'body',

	listSelector: '.aips-history-table',
	rowSelector: '#aips-history-tbody tr',
	searchSelector: '#aips-history-search-input',
	selectAllSelector: '#aips-cb-select-all',
	checkboxSelector: '.aips-history-cb',
	bulkActionSelector: '', // Not used directly; bulk delete button has its own click
	bulkApplySelector: '#aips-delete-selected-btn',

	MIN_HEARTBEAT_INTERVAL: 5,
	statusFilter: '',
	domainFilter: '',
	actorFilter: '',
	correlationId: '',
	dateFrom: '',
	dateTo: '',
	searchQuery: '',
	autoRefreshEnabled: false,
	heartbeatIntervalSeconds: 5,
	defaultHeartbeatInterval: null,
	isAutoRefreshing: false,

	events: _.extend({}, BaseListView.prototype.events, {
		// Modal Events
		'click .aips-view-history-logs': 'openLogsModal',
		'click .aips-log-toggle': 'toggleLogDetail',
		'click .aips-log-copy': 'copyLogDetail',
		'click .aips-log-type-filter-btn': 'filterLogsByType',
		'change .aips-json-viewer-toggle': 'toggleJsonViewerMode',

		// Bulk Selection Events
		'change #aips-cb-select-all': 'toggleSelectAll',
		'change .aips-history-cb': 'onRowCheckboxChange',
		'click #aips-delete-selected-btn': 'deleteSelected',

		// Row Action Events
		'click .aips-delete-history': 'deleteSingleItem',
		'click .aips-retry-generation': 'retryGeneration',
		'click .aips-clear-history': 'clearHistory',

		// Reload & Pagination Events
		'click #aips-reload-history-btn': 'onReloadClick',
		'click .aips-history-page-link, .aips-history-page-prev, .aips-history-page-next': 'loadPage',

		// Filter & Search Events
		'click #aips-filter-btn': 'applyFilter',
		'change #aips-filter-status': 'applyFilter',
		'input #aips-history-search-input': 'onSearchInput',
		'keydown #aips-history-search-input': 'onSearchKeydown',
		'click #aips-history-search-clear': 'clearSearch',
		'click .aips-clear-history-search-btn': 'clearSearch',

		// Export Event
		'click #aips-export-history-btn': 'exportHistory',

		// Auto Refresh / Heartbeat Events
		'change #aips-history-auto-refresh': 'toggleAutoRefresh',
		'change #aips-history-heartbeat-interval': 'changeHeartbeatInterval'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		// Initialize logs modal
		this.logsModal = new BaseModalView({ el: '#aips-history-logs-modal' });

		// Check if we are on the history page before configuring heartbeat
		if (this.isHistoryPage()) {
			this.statusFilter = $('#aips-filter-status').val() || '';
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';
			this.searchQuery  = $('#aips-history-search-input').val() || '';
			
			this.syncSearchClearButton();
			this.initHeartbeatAutoRefresh();
			this.maybeOpenFromQuery();

			// Bind heartbeat ticks and exit events
			$(document).on('heartbeat-tick.aipsHistory', this.onHeartbeatTick.bind(this));
			$(window).on('beforeunload.aipsHistory pagehide.aipsHistory', this.onPageExit.bind(this));
		}
	},

	isHistoryPage() {
		return $('#aips-history-logs-modal').length > 0
			|| $('#aips-history-search-input').length > 0
			|| $('#aips-history-tbody').length > 0;
	},

	maybeOpenFromQuery() {
		const params = new URLSearchParams(window.location.search || '');
		const historyId = parseInt(params.get('history_id') || 0, 10);
		const postId = parseInt(params.get('post_id') || 0, 10);

		if (postId > 0 && !this.searchQuery) {
			this.searchQuery = String(postId);
			$('#aips-history-search-input').val(String(postId));
			this.syncSearchClearButton();
			this.filterList(String(postId));
		}

		if (historyId > 0) {
			this.openLogsModalFromId(historyId);
		}
	},

	openLogsModalFromId(historyId) {
		const $trigger = $('<button type="button" class="aips-view-history-logs" data-id="' + historyId + '"></button>');
		this.openLogsModal({
			preventDefault: () => {},
			stopPropagation: () => {},
			currentTarget: $trigger.get(0)
		});
	},

	openLogsModal(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}

		const historyId = $(e.currentTarget).data('id');
		if (!historyId) return;

		const $modal = $('#aips-history-logs-modal');
		const $content = $('#aips-history-logs-content');
		const T = window.AIPS.Templates;
		const l10n = window.aipsHistoryL10n || {};

		$modal.find('#aips-history-logs-modal-title').text(l10n.historyDetailsTitle || 'History Details');
		$modal.find('#aips-history-logs-modal-actions').empty();
		$modal.find('#aips-history-logs-modal-status').empty();

		if (T && typeof T.render === 'function') {
			$content.html(T.render('aips-tmpl-history-loading-msg', {
				text: l10n.loadingLogs || 'Loading logs…'
			}));
		} else {
			$content.html('<p>Loading logs...</p>');
		}
		
		this.logsModal.open();

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_get_history_modal_html',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				history_id: historyId
			},
			success: (response) => {
				if (!response.success) {
					const msg = response.data && response.data.message ? response.data.message : (l10n.errorLoading || 'Error loading logs.');
					if (T && typeof T.render === 'function') {
						$content.html(T.render('aips-tmpl-history-error-msg', { message: msg }));
					} else {
						$content.html('<p class="error">' + _.escape(msg) + '</p>');
					}
					return;
				}

				const container = response.data.container || {};
				const modalHtml = response.data.modal_html || '';

				// Update header details
				const title = container.header_title || l10n.historyDetailsTitle || 'History Details';
				$modal.find('#aips-history-logs-modal-title').text(title);

				if (container.status && container.status_class) {
					$modal.find('#aips-history-logs-modal-status').html(
						'<span class="aips-badge ' + _.escape(container.status_class) + '">' + _.escape(container.status) + '</span>'
					);
				}

				if (Array.isArray(container.header_actions)) {
					let actionsHtml = '';
					container.header_actions.forEach(act => {
						if (act.url && act.label) {
							actionsHtml += '<a href="' + _.escape(act.url) + '" target="_blank" rel="noopener noreferrer">' + _.escape(act.label) + '</a>';
						}
					});
					$modal.find('#aips-history-logs-modal-actions').html(actionsHtml);
				}

				$content.html(modalHtml);
			},
			error: () => {
				const errMsg = l10n.errorLoading || 'Error loading logs.';
				if (T && typeof T.render === 'function') {
					$content.html(T.render('aips-tmpl-history-error-msg', { message: errMsg }));
				} else {
					$content.html('<p class="error">' + _.escape(errMsg) + '</p>');
				}
			}
		});
	},

	toggleLogDetail(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const targetSelector = $btn.data('target');
		const $target = this.$(targetSelector);
		const l10n = window.aipsHistoryL10n || {};
		const showLabel = l10n.showDetails || 'Show details';
		const hideLabel = l10n.hideDetails || 'Hide details';

		if (!$target.length) return;

		$target.slideToggle(150, () => {
			$btn.text($target.is(':visible') ? hideLabel : showLabel);
		});
	},

	copyLogDetail(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const targetSelector = $btn.data('copy-target');
		const $target = this.$(targetSelector);
		const text = $pre => $pre.text();
		const $pre = $target.find('pre').first();
		const l10n = window.aipsHistoryL10n || {};

		if (!$pre.length) return;

		const textVal = $pre.text();
		const showSuccess = () => {
			$btn.text(l10n.copiedDetails || 'Copied!');
			setTimeout(() => {
				$btn.text(l10n.copyDetails || 'Copy');
			}, 1500);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(textVal).then(showSuccess);
		} else {
			const $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(textVal).select();
			try {
				document.execCommand('copy');
				showSuccess();
			} catch (err) {
				console.error('Failed to copy', err);
			}
			$temp.remove();
		}
	},

	filterLogsByType(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const typeId = String($btn.data('type-id') || '');
		const $modal = $('#aips-history-logs-modal');

		$modal.find('.aips-log-type-filter-btn').removeClass('aips-btn-primary').addClass('aips-btn-ghost');
		$btn.removeClass('aips-btn-ghost').addClass('aips-btn-primary');

		$modal.find('.aips-history-logs-table tbody tr').each(function() {
			const $row = $(this);
			const rowTypes = String($row.attr('data-type-ids') || $row.data('type-id') || '').split(',').map(v => $.trim(v)).filter(Boolean);
			
			let match = false;
			if (!typeId || typeId === 'all') {
				match = true;
			} else if (typeId === 'ai_request_response') {
				match = rowTypes.indexOf('5') !== -1 || rowTypes.indexOf('6') !== -1;
			} else {
				match = rowTypes.indexOf(typeId) !== -1;
			}
			
			$row.toggle(match);
		});
	},

	toggleJsonViewerMode(e) {
		const $toggle = $(e.currentTarget);
		const $renderer = $toggle.closest('.aips-history-log-renderer');
		if ($renderer.length) {
			$renderer.toggleClass('aips-json-viewer-enabled', $toggle.is(':checked'));
		}
	},

	onRowCheckboxChange() {
		this.onSelectionChange();
	},

	deleteSelected(e) {
		if (e) e.preventDefault();
		const ids = this.getSelectedIds();
		if (!ids.length) return;

		const l10n = window.aipsHistoryL10n || {};
		const confirmMsg = l10n.confirmBulkDelete || 'Delete the selected history containers? This cannot be undone.';
		const cancelLabel = l10n.cancelLabel || 'No, cancel';
		const deleteLabel = l10n.confirmDeleteLabel || 'Yes, delete';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: cancelLabel, className: 'aips-btn aips-btn-primary' },
				{ label: deleteLabel, className: 'aips-btn aips-btn-danger-solid', action: () => this._executeBulkDelete(ids) }
			]);
		}
	},

	deleteSingleItem(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}
		const id = $(e.currentTarget).data('id');
		if (!id) return;

		const l10n = window.aipsHistoryL10n || {};
		const confirmMsg = l10n.confirmDelete || 'Delete this history container? This cannot be undone.';
		const cancelLabel = l10n.cancelLabel || 'No, cancel';
		const deleteLabel = l10n.confirmDeleteLabel || 'Yes, delete';

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: cancelLabel, className: 'aips-btn aips-btn-primary' },
				{ label: deleteLabel, className: 'aips-btn aips-btn-danger-solid', action: () => this._executeBulkDelete([id]) }
			]);
		}
	},

	_executeBulkDelete(ids) {
		const l10n = window.aipsHistoryL10n || {};
		const $btn = this.$('#aips-delete-selected-btn');
		const origHtml = $btn.html();

		$btn.prop('disabled', true).html(
			'<span class="dashicons dashicons-update aips-spin"></span> ' + (l10n.deleting || 'Deleting…')
		);

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_bulk_delete_history',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				ids: ids
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(l10n.deletedSuccess || 'Items deleted successfully.', 'success');
					}
					this.reload();
				} else {
					const msg = response.data && response.data.message ? response.data.message : (l10n.errorDeleting || 'Error deleting items.');
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(msg, 'error');
					}
					$btn.prop('disabled', false).html(origHtml);
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast(l10n.errorDeleting || 'Error deleting items.', 'error');
				}
				$btn.prop('disabled', false).html(origHtml);
			}
		});
	},

	retryGeneration(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}
		const $btn = $(e.currentTarget);
		const id = $btn.data('id');
		if (!id) return;

		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update aips-spin"></span>');

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_retry_generation',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				history_id: id
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Retry initiated.', 'success');
					}
					this.reload();
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Retry failed.', 'error');
					}
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span>');
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Network error during retry.', 'error');
				}
				$btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span>');
			}
		});
	},

	clearHistory(e) {
		if (e) e.preventDefault();
		const $btn = $(e.currentTarget);
		const type = $btn.data('clear-type') || 'failed';
		const l10n = window.aipsHistoryL10n || {};
		
		const confirmMsg = type === 'all' 
			? (l10n.confirmClearAll || 'Are you sure you want to clear ALL generation history? This cannot be undone.')
			: (l10n.confirmClearFailed || 'Are you sure you want to clear all FAILED generation history?');

		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.confirm(confirmMsg, 'Notice', [
				{ label: l10n.cancelLabel || 'Cancel', className: 'aips-btn aips-btn-primary' },
				{ label: l10n.confirmDeleteLabel || 'Clear', className: 'aips-btn aips-btn-danger-solid', action: () => this._executeClearHistory(type) }
			]);
		}
	},

	_executeClearHistory(type) {
		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_clear_history',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				clear_type: type
			},
			success: (response) => {
				if (response.success) {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'History cleared.', 'success');
					}
					this.reload(1);
				} else {
					if (window.AIPS && window.AIPS.Utilities) {
						window.AIPS.Utilities.showToast(response.data.message || 'Clear failed.', 'error');
					}
				}
			},
			error: () => {
				if (window.AIPS && window.AIPS.Utilities) {
					window.AIPS.Utilities.showToast('Network error while clearing history.', 'error');
				}
			}
		});
	},

	onReloadClick(e) {
		if (e) e.preventDefault();
		this.reload(this.getCurrentPage());
	},

	loadPage(e) {
		if (e) e.preventDefault();
		const page = $(e.currentTarget).data('page');
		if (page) {
			this.reload(page);
		}
	},

	getCurrentPage() {
		const params = new URLSearchParams(window.location.search || '');
		const paged = parseInt(params.get('paged') || '1', 10);
		return !isNaN(paged) && paged > 0 ? paged : 1;
	},

	applyFilter(e) {
		if (e) e.preventDefault();
		this.statusFilter = $('#aips-filter-status').val() || '';
		this.domainFilter = $('#aips-filter-domain').val() || '';
		this.actorFilter = $('#aips-filter-actor').val() || '';
		this.correlationId = $('#aips-filter-correlation').val() || '';
		this.dateFrom = $('#aips-filter-date-from').val() || '';
		this.dateTo = $('#aips-filter-date-to').val() || '';
		this.searchQuery = $('#aips-history-search-input').val() || '';

		this.reload(1);
	},

	onSearchInput() {
		const val = $('#aips-history-search-input').val() || '';
		this.searchQuery = val;
		this.syncSearchClearButton();
		
		// Client side quick filter
		this.filterList(val.toLowerCase());
	},

	onSearchKeydown(e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			this.reload(1);
		}
	},

	syncSearchClearButton() {
		const $clear = $('#aips-history-search-clear');
		if (this.searchQuery.length > 0) {
			$clear.show();
		} else {
			$clear.hide();
		}
	},

	clearSearch(e) {
		if (e) e.preventDefault();
		$('#aips-history-search-input').val('');
		this.searchQuery = '';
		this.syncSearchClearButton();
		this.filterList('');
		this.reload(1);
	},

	exportHistory(e) {
		if (e) e.preventDefault();
		
		// Trigger export by form POST or query args redirection
		const ajaxUrl = (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl;
		const nonce = (window.aipsAjax && window.aipsAjax.nonce) || '';

		const form = document.createElement('form');
		form.method = 'POST';
		form.action = ajaxUrl;
		form.target = '_blank';

		const fields = {
			action: 'aips_export_history_csv',
			nonce: nonce,
			status: this.statusFilter,
			domain: this.domainFilter,
			actor: this.actorFilter,
			correlation_id: this.correlationId,
			date_from: this.dateFrom,
			date_to: this.dateTo,
			search: this.searchQuery
		};

		for (const key in fields) {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = key;
			input.value = fields[key];
			form.appendChild(input);
		}

		document.body.appendChild(form);
		form.submit();
		document.body.removeChild(form);
	},

	reload(page, options) {
		page = page || 1;
		options = options || {};

		const $tbody = $('#aips-history-tbody');
		const $loadingIndicator = $('#aips-history-loading-indicator');

		if (!options.fromHeartbeat) {
			if ($loadingIndicator.length) $loadingIndicator.show();
			$tbody.addClass('aips-loading-state');
		}

		// Update query params in browser history
		if (window.history && window.history.replaceState && !options.fromHeartbeat) {
			const url = new URL(window.location.href);
			url.searchParams.set('paged', page);
			if (this.statusFilter) url.searchParams.set('status', this.statusFilter);
			else url.searchParams.delete('status');
			
			if (this.domainFilter) url.searchParams.set('domain', this.domainFilter);
			else url.searchParams.delete('domain');
			
			window.history.replaceState(null, '', url.toString());
		}

		$.ajax({
			url: (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl,
			type: 'POST',
			data: {
				action: 'aips_reload_history',
				nonce: (window.aipsAjax && window.aipsAjax.nonce) || '',
				paged: page,
				status: this.statusFilter,
				domain: this.domainFilter,
				actor: this.actorFilter,
				correlation_id: this.correlationId,
				date_from: this.dateFrom,
				date_to: this.dateTo,
				search: this.searchQuery
			},
			success: (response) => {
				if (response.success && response.data) {
					// Replace the tbody and the table pagination nav
					if ($tbody.length && response.data.tbody_html) {
						$tbody.html(response.data.tbody_html);
					}
					const $tablenav = $('.tablenav.aips-history-nav');
					if ($tablenav.length && response.data.nav_html) {
						$tablenav.html(response.data.nav_html);
					}
					
					this.onSelectionChange();
				}
			},
			complete: () => {
				if ($loadingIndicator.length) $loadingIndicator.hide();
				$tbody.removeClass('aips-loading-state');
				this.isAutoRefreshing = false;
			}
		});
	},

	initHeartbeatAutoRefresh() {
		if (!window.wp || !wp.heartbeat || typeof wp.heartbeat.interval !== 'function') {
			const label = 'WordPress Heartbeat unavailable.';
			$('#aips-history-auto-refresh').prop('disabled', true).attr('title', label);
			$('#aips-history-heartbeat-interval').prop('disabled', true).attr('title', label);
			$('#aips-history-auto-refresh-help').text(label);
			return;
		}

		this.defaultHeartbeatInterval = wp.heartbeat.interval();
		$('#aips-history-auto-refresh-help').empty();

		this.heartbeatIntervalSeconds = parseInt(
			$('#aips-history-heartbeat-interval').val() || String(this.MIN_HEARTBEAT_INTERVAL),
			10
		);
	},

	toggleAutoRefresh(e) {
		const enabled = $(e.currentTarget).is(':checked');
		const $intervalSelect = $('#aips-history-heartbeat-interval');

		this.autoRefreshEnabled = enabled;
		$intervalSelect.prop('disabled', !enabled);

		if (!enabled) {
			this.disableAutoRefresh();
			return;
		}

		this.heartbeatIntervalSeconds = parseInt(
			$intervalSelect.val() || String(this.MIN_HEARTBEAT_INTERVAL),
			10
		);

		this.applyHeartbeatInterval();

		if (window.wp && wp.heartbeat && typeof wp.heartbeat.connectNow === 'function') {
			wp.heartbeat.connectNow();
		}
	},

	changeHeartbeatInterval(e) {
		this.heartbeatIntervalSeconds = parseInt(
			$(e.currentTarget).val() || String(this.MIN_HEARTBEAT_INTERVAL),
			10
		);

		if (!this.autoRefreshEnabled) return;

		this.applyHeartbeatInterval();

		if (window.wp && wp.heartbeat && typeof wp.heartbeat.connectNow === 'function') {
			wp.heartbeat.connectNow();
		}
	},

	applyHeartbeatInterval() {
		if (!window.wp || !wp.heartbeat || typeof wp.heartbeat.interval !== 'function') return;

		let parsedInterval = parseInt(this.heartbeatIntervalSeconds, 10);
		if (isNaN(parsedInterval)) {
			parsedInterval = this.MIN_HEARTBEAT_INTERVAL;
		}

		const interval = Math.max(this.MIN_HEARTBEAT_INTERVAL, parsedInterval);
		wp.heartbeat.interval(interval);
	},

	disableAutoRefresh() {
		const defaultInterval = parseInt(this.defaultHeartbeatInterval, 10);
		if (
			window.wp
			&& wp.heartbeat
			&& typeof wp.heartbeat.interval === 'function'
			&& this.defaultHeartbeatInterval !== null
			&& !isNaN(defaultInterval)
		) {
			wp.heartbeat.interval(defaultInterval);
		}

		this.autoRefreshEnabled = false;
		this.isAutoRefreshing = false;

		$('#aips-history-auto-refresh').prop('checked', false);
		$('#aips-history-heartbeat-interval').prop('disabled', true);
	},

	onHeartbeatTick() {
		if (!this.autoRefreshEnabled || this.isAutoRefreshing) return;

		this.isAutoRefreshing = true;
		this.reload(this.getCurrentPage(), { fromHeartbeat: true });
	},

	onPageExit() {
		this.disableAutoRefresh();
		$(document).off('heartbeat-tick.aipsHistory');
		$(window).off('.aipsHistory');
	}
});
