/**
 * Admin Notifications Page JavaScript
 *
 * Manages the Notifications admin page: listing, filtering, pagination,
 * bulk actions, and the view modal with smart linker.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * AIPS.Notifications module.
	 *
	 * Handles all Notifications page interactions.
	 */
	AIPS.Notifications = {
		MESSAGE_PREVIEW_LENGTH: 80,
		currentPage: 1,
		perPage: 20,
		levelFilter: '',
		typeFilter: '',
		readFilter: -1,
		searchQuery: '',
		selectedIds: [],
		currentNotification: null,
		searchTimeout: null,

		/**
		 * Initialise the module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
			this.loadNotifications();
		},

		/**
		 * Bind all delegated and direct event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			// Filters
			$(document).on('change', '#aips-level-filter',              this.onLevelFilterChange.bind(this));
			$(document).on('change', '#aips-type-filter',               this.onTypeFilterChange.bind(this));
			$(document).on('change', '#aips-read-filter',               this.onReadFilterChange.bind(this));
			$(document).on('input search', '#aips-notifications-search', this.onSearchInput.bind(this));

			// Bulk actions
			$(document).on('change', '#aips-notifications-select-all', this.onSelectAll.bind(this));
			$(document).on('change', '.aips-notification-checkbox',    this.onRowCheckboxChange.bind(this));
			$(document).on('click',  '#aips-bulk-action-apply',        this.onApplyBulkAction.bind(this));

			// Mark all read
			$(document).on('click', '#aips-mark-all-read-btn', this.onMarkAllRead.bind(this));

			// View notification
			$(document).on('click', '.aips-view-notification', this.onViewNotification.bind(this));

			// Toggle read status from modal
			$(document).on('click', '#aips-notification-toggle-read-btn', this.onMarkReadToggle.bind(this));

			// Pagination
			$(document).on('click', '.aips-notifications-page-btn', this.onPageChange.bind(this));

			// Close modal
			$(document).on('click', '.aips-modal-close', this.onCloseModal.bind(this));
			$(document).on('click', '.aips-modal',       this.onModalBackdropClick.bind(this));
		},

		// -----------------------------------------------------------------
		// Filter handlers
		// -----------------------------------------------------------------

		/**
		 * Handle level filter change.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onLevelFilterChange: function (e) {
			this.levelFilter = $(e.currentTarget).val();
			this.currentPage = 1;
			this.loadNotifications();
		},

		/**
		 * Handle type filter change.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onTypeFilterChange: function (e) {
			this.typeFilter = $(e.currentTarget).val();
			this.currentPage = 1;
			this.loadNotifications();
		},

		/**
		 * Handle read status filter change.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onReadFilterChange: function (e) {
			this.readFilter = parseInt($(e.currentTarget).val(), 10);
			this.currentPage = 1;
			this.loadNotifications();
		},

		/**
		 * Handle search input with debounce.
		 *
		 * @param {Event} e Input event.
		 * @return {void}
		 */
		onSearchInput: function (e) {
			var self = this;
			clearTimeout(this.searchTimeout);
			this.searchTimeout = setTimeout(function () {
				self.searchQuery = $(e.currentTarget).val();
				self.currentPage = 1;
				self.loadNotifications();
			}, 350);
		},

		// -----------------------------------------------------------------
		// Bulk action handlers
		// -----------------------------------------------------------------

		/**
		 * Handle select-all checkbox toggle.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onSelectAll: function (e) {
			var checked = $(e.currentTarget).prop('checked');
			$('.aips-notification-checkbox').prop('checked', checked);
			this.selectedIds = checked
				? $('.aips-notification-checkbox').map(function () { return parseInt($(this).val(), 10); }).get()
				: [];
		},

		/**
		 * Handle individual row checkbox change.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onRowCheckboxChange: function (e) {
			var id      = parseInt($(e.currentTarget).val(), 10);
			var checked = $(e.currentTarget).prop('checked');
			if (checked) {
				if (this.selectedIds.indexOf(id) === -1) {
					this.selectedIds.push(id);
				}
			} else {
				this.selectedIds = this.selectedIds.filter(function (v) { return v !== id; });
				$('#aips-notifications-select-all').prop('checked', false);
			}
		},

		/**
		 * Handle bulk action apply button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onApplyBulkAction: function (e) {
			var action = $('#aips-bulk-action-select').val();
			if (!action) {
				alert(aipsNotificationsData.l10n.selectAction);
				return;
			}
			if (this.selectedIds.length === 0) {
				alert(aipsNotificationsData.l10n.selectItems);
				return;
			}

			var self = this;
			$.post(aipsNotificationsData.ajaxUrl, {
				action:      'aips_bulk_notifications_action',
				nonce:       aipsNotificationsData.nonce,
				bulk_action: action,
				ids:         this.selectedIds,
			}, function (response) {
				if (response.success) {
					self.selectedIds = [];
					$('#aips-notifications-select-all').prop('checked', false);
					self.loadNotifications();
				} else {
					alert(response.data && response.data.message ? response.data.message : aipsNotificationsData.l10n.error);
				}
			});
		},

		/**
		 * Handle "Mark All as Read" button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onMarkAllRead: function (e) {
			var self = this;
			$.post(aipsNotificationsData.ajaxUrl, {
				action: 'aips_mark_all_notifications_read',
				nonce:  aipsNotificationsData.nonce,
			}, function (response) {
				if (response.success) {
					self.loadNotifications();
				} else {
					alert(response.data && response.data.message ? response.data.message : aipsNotificationsData.l10n.error);
				}
			});
		},

		// -----------------------------------------------------------------
		// Notification view / modal handlers
		// -----------------------------------------------------------------

		/**
		 * Handle "View" button click to open the notification modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onViewNotification: function (e) {
			var id           = parseInt($(e.currentTarget).data('id'), 10);
			var notification = this._findNotificationById(id);
			if (!notification) {
				return;
			}
			this.currentNotification = notification;
			this.showViewModal(notification);
		},

		/**
		 * Handle mark-as-read/unread toggle inside the view modal.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onMarkReadToggle: function (e) {
			var notification = this.currentNotification;
			if (!notification) {
				return;
			}
			var isRead     = parseInt(notification.is_read, 10) === 1;
			var ajaxAction = isRead ? 'aips_mark_notification_unread' : 'aips_mark_notification_read';
			var self       = this;

			$.post(aipsNotificationsData.ajaxUrl, {
				action: ajaxAction,
				nonce:  aipsNotificationsData.nonce,
				id:     notification.id,
			}, function (response) {
				if (response.success) {
					$('#aips-view-notification-modal').hide();
					self.currentNotification = null;
					self.loadNotifications();
				}
			});
		},

		/**
		 * Handle pagination button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onPageChange: function (e) {
			var page = parseInt($(e.currentTarget).data('page'), 10);
			if (page && page !== this.currentPage) {
				this.currentPage = page;
				this.loadNotifications();
			}
		},

		/**
		 * Handle modal close button click.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onCloseModal: function (e) {
			$(e.currentTarget).closest('.aips-modal').hide();
		},

		/**
		 * Close modal when clicking on the backdrop overlay.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		onModalBackdropClick: function (e) {
			if ($(e.target).hasClass('aips-modal')) {
				$(e.target).hide();
			}
		},

		// -----------------------------------------------------------------
		// Data loading
		// -----------------------------------------------------------------

		/**
		 * Load notifications from the server.
		 *
		 * @return {void}
		 */
		loadNotifications: function () {
			var self = this;
			$('#aips-notifications-loading').show();
			$('#aips-notifications-content').html('');

			$.post(aipsNotificationsData.ajaxUrl, {
				action:   'aips_get_notifications_list',
				nonce:    aipsNotificationsData.nonce,
				page:     this.currentPage,
				per_page: this.perPage,
				level:    this.levelFilter,
				type:     this.typeFilter,
				is_read:  this.readFilter,
				search:   this.searchQuery,
			}, function (response) {
				$('#aips-notifications-loading').hide();
				if (response.success) {
					self._cachedItems = response.data.items;
					self.renderNotifications(response.data);
				} else {
					$('#aips-notifications-content').html('<p style="padding:20px;color:#d63638;">' + aipsNotificationsData.l10n.loadError + '</p>');
				}
			});
		},

		// -----------------------------------------------------------------
		// Rendering
		// -----------------------------------------------------------------

		/**
		 * Render the full notifications panel (stats + table + pagination).
		 *
		 * @param {Object} data Server response data.
		 * @return {void}
		 */
		renderNotifications: function (data) {
			var html = '';

			// Stats bar
			var statsTmpl = $('#aips-tmpl-notifications-stats').html() || '';
			statsTmpl = statsTmpl
				.replace('{{totalLabel}}',    aipsNotificationsData.l10n.totalLabel)
				.replace('{{unreadLabel}}',   aipsNotificationsData.l10n.unreadLabel)
				.replace('{{errorsLabel}}',   aipsNotificationsData.l10n.errorsLabel)
				.replace('{{warningsLabel}}', aipsNotificationsData.l10n.warningsLabel);
			html += statsTmpl;

			var items = data.items || [];

			if (items.length === 0) {
				var emptyTmpl = $('#aips-tmpl-notifications-empty').html() || '';
				html += emptyTmpl.replace('{{emptyMessage}}', aipsNotificationsData.l10n.noNotifications);
			} else {
				var rowsTmpl = $('#aips-tmpl-notification-row').html() || '';
				var rows     = '';
				for (var i = 0; i < items.length; i++) {
					rows += this._renderRow(items[i], rowsTmpl);
				}

				var tableTmpl = $('#aips-tmpl-notifications-table').html() || '';
				tableTmpl = tableTmpl
					.replace('{{titleLabel}}',   aipsNotificationsData.l10n.colTitle)
					.replace('{{typeLabel}}',    aipsNotificationsData.l10n.colType)
					.replace('{{levelLabel}}',   aipsNotificationsData.l10n.colLevel)
					.replace('{{messageLabel}}', aipsNotificationsData.l10n.colMessage)
					.replace('{{dateLabel}}',    aipsNotificationsData.l10n.colDate)
					.replace('{{statusLabel}}',  aipsNotificationsData.l10n.colStatus)
					.replace('{{actionsLabel}}', aipsNotificationsData.l10n.colActions)
					.replace('{{rows}}',         rows);
				html += tableTmpl;
			}

			$('#aips-notifications-content').html(html);

			// Update stats
			if (data.summary) {
				$('#aips-stat-total').text(data.summary.total || 0);
				$('#aips-stat-unread').text(data.summary.unread || 0);
				$('#aips-stat-errors').text(data.summary.errors || 0);
				$('#aips-stat-warnings').text(data.summary.warnings || 0);
			}

			// Render pagination
			this.renderPagination(data);

			// Re-sync selected checkboxes
			var self = this;
			if (this.selectedIds.length > 0) {
				$('.aips-notification-checkbox').each(function () {
					var id = parseInt($(this).val(), 10);
					if (self.selectedIds.indexOf(id) !== -1) {
						$(this).prop('checked', true);
					}
				});
			}
		},

		/**
		 * Render a single notification row from template.
		 *
		 * @param {Object} item Notification object.
		 * @param {string} tmpl Row template HTML.
		 * @return {string} Rendered row HTML.
		 */
		_renderRow: function (item, tmpl) {
			var isRead     = parseInt(item.is_read, 10) === 1;
			var rowClass   = isRead ? 'aips-notification-read' : 'aips-notification-unread';
			var typeLabel  = this._getTypeLabel(item.type);
			var msgPreview = item.message ? item.message.substring(0, AIPS.Notifications.MESSAGE_PREVIEW_LENGTH) + (item.message.length > AIPS.Notifications.MESSAGE_PREVIEW_LENGTH ? '\u2026' : '') : '';
			var readBadge  = isRead
				? '<span class="aips-status aips-status-approved">' + aipsNotificationsData.l10n.read + '</span>'
				: '<span class="aips-status aips-status-pending">' + aipsNotificationsData.l10n.unread + '</span>';

			return tmpl
				.replace(/\{\{id\}\}/g,       item.id)
				.replace('{{rowClass}}',       rowClass)
				.replace('{{title}}',          this._esc(item.title || item.type))
				.replace('{{typeLabel}}',      this._esc(typeLabel))
				.replace(/\{\{level\}\}/g,     this._esc(item.level || 'info'))
				.replace('{{messagePreview}}', this._esc(msgPreview))
				.replace('{{date}}',           this._esc(item.created_at || ''))
				.replace('{{readBadge}}',      readBadge)
				.replace('{{viewLabel}}',      aipsNotificationsData.l10n.view);
		},

		/**
		 * Render pagination controls.
		 *
		 * @param {Object} data Server response data with total/pages/page.
		 * @return {void}
		 */
		renderPagination: function (data) {
			if (!data.pages || data.pages <= 1) {
				return;
			}

			var l10n        = aipsNotificationsData.l10n;
			var countLabel  = l10n.countLabel.replace('%d', data.total);
			var links       = '';
			var currentPage = data.page || this.currentPage;

			if (currentPage > 1) {
				links += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-notifications-page-btn" data-page="' + (currentPage - 1) + '">&laquo; ' + l10n.prev + '</button> ';
			}

			var start = Math.max(1, currentPage - 2);
			var end   = Math.min(data.pages, currentPage + 2);
			for (var p = start; p <= end; p++) {
				if (p === currentPage) {
					links += '<span class="aips-btn aips-btn-sm aips-btn-primary" style="cursor:default;">' + p + '</span> ';
				} else {
					links += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-notifications-page-btn" data-page="' + p + '">' + p + '</button> ';
				}
			}

			if (currentPage < data.pages) {
				links += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-notifications-page-btn" data-page="' + (currentPage + 1) + '">' + l10n.next + ' &raquo;</button>';
			}

			var tmpl          = $('#aips-tmpl-notifications-pagination').html() || '';
			var paginationHtml = tmpl
				.replace('{{countLabel}}', countLabel)
				.replace('{{links}}',      links);

			$('#aips-notifications-content').append(paginationHtml);
		},

		/**
		 * Show the view notification modal with full details.
		 *
		 * @param {Object} notification Notification object.
		 * @return {void}
		 */
		showViewModal: function (notification) {
			var isRead    = parseInt(notification.is_read, 10) === 1;
			var typeLabel = this._getTypeLabel(notification.type);
			var readBadge = isRead
				? '<span class="aips-status aips-status-approved">' + aipsNotificationsData.l10n.read + '</span>'
				: '<span class="aips-status aips-status-pending">' + aipsNotificationsData.l10n.unread + '</span>';

			var tmpl     = $('#aips-tmpl-notification-modal-body').html() || '';
			var bodyHtml = tmpl
				.replace('{{title}}',       this._esc(notification.title || notification.type))
				.replace(/\{\{level\}\}/g,  this._esc(notification.level || 'info'))
				.replace('{{message}}',     this._esc(notification.message || ''))
				.replace('{{typeLabel}}',   aipsNotificationsData.l10n.colType)
				.replace('{{typeValue}}',   this._esc(typeLabel))
				.replace('{{dateLabel}}',   aipsNotificationsData.l10n.colDate)
				.replace('{{date}}',        this._esc(notification.created_at || ''))
				.replace('{{statusLabel}}', aipsNotificationsData.l10n.colStatus)
				.replace('{{readBadge}}',   readBadge)
				.replace('{{smartLinks}}',  this.buildSmartLinks(notification.meta));

			$('#aips-view-notification-modal-body').html(bodyHtml);

			var toggleBtn = $('#aips-notification-toggle-read-btn');
			if (isRead) {
				toggleBtn.text(aipsNotificationsData.l10n.markUnread);
			} else {
				toggleBtn.text(aipsNotificationsData.l10n.markRead);
			}

			$('#aips-view-notification-modal').show();
		},

		/**
		 * Build smart linker HTML buttons from notification meta JSON.
		 *
		 * @param {string|null} metaJson JSON string from notification.meta field.
		 * @return {string} HTML string of smart link buttons, or empty string.
		 */
		buildSmartLinks: function (metaJson) {
			if (!metaJson) {
				return '';
			}

			var meta;
			try {
				meta = typeof metaJson === 'string' ? JSON.parse(metaJson) : metaJson;
			} catch (e) {
				return '';
			}

			if (!meta || typeof meta !== 'object') {
				return '';
			}

			var data    = aipsNotificationsData;
			var buttons = '';

			if (meta.post_id) {
				buttons += '<a href="' + data.editPostUrl.replace('%d', meta.post_id) + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewPost + '</a>';
			}
			if (meta.author_id) {
				buttons += '<a href="' + data.authorTopicsUrl + '&author_id=' + encodeURIComponent(meta.author_id) + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewAuthorTopics + '</a>';
			}
			if (meta.history_id) {
				buttons += '<a href="' + data.historyUrl + '&id=' + encodeURIComponent(meta.history_id) + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewHistory + '</a>';
			}
			if (meta.template_id) {
				buttons += '<a href="' + data.templatesUrl + '&template_id=' + encodeURIComponent(meta.template_id) + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewTemplate + '</a>';
			}
			if (meta.schedule_id) {
				buttons += '<a href="' + data.scheduleUrl + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewSchedule + '</a>';
			}
			if (meta.topic_id) {
				buttons += '<a href="' + data.authorTopicsUrl + '" class="aips-btn aips-btn-sm aips-btn-secondary" target="_blank" rel="noopener noreferrer">' + data.l10n.viewTopic + '</a>';
			}

			if (!buttons) {
				return '';
			}

			var tmpl = $('#aips-tmpl-notification-smart-links').html() || '';
			return tmpl.replace('{{buttons}}', buttons);
		},

		// -----------------------------------------------------------------
		// Utilities
		// -----------------------------------------------------------------

		/**
		 * Get the human-readable label for a notification type slug.
		 *
		 * @param {string} type Notification type slug.
		 * @return {string} Label or the type slug as fallback.
		 */
		_getTypeLabel: function (type) {
			var types = aipsNotificationsData.types || {};
			return types[type] ? types[type] : type;
		},

		/**
		 * Find a cached notification object by ID.
		 *
		 * @param {number} id Notification ID.
		 * @return {Object|null}
		 */
		_findNotificationById: function (id) {
			var items = this._cachedItems || [];
			for (var i = 0; i < items.length; i++) {
				if (parseInt(items[i].id, 10) === id) {
					return items[i];
				}
			}
			return null;
		},

		/**
		 * Escape HTML special characters in a string.
		 *
		 * @param {string} str Input string.
		 * @return {string} Escaped string.
		 */
		_esc: function (str) {
			if (str === null || str === undefined) {
				return '';
			}
			return String(str)
				.replace(/&/g,  '&amp;')
				.replace(/</g,  '&lt;')
				.replace(/>/g,  '&gt;')
				.replace(/"/g,  '&quot;')
				.replace(/'/g,  '&#039;');
		},

		/**
		 * Cached notification items from the last load response.
		 *
		 * @type {Array}
		 */
		_cachedItems: [],
	};

	$(document).ready(function () {
		if (typeof aipsNotificationsData !== 'undefined') {
			AIPS.Notifications.init();
		}
	});

})(jQuery);
