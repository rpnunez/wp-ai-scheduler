/**
 * AI Post Scheduler – Admin Toolbar JavaScript
 *
 * Handles "mark as read" / "mark all as read" for toolbar notifications.
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/**
		 * Bootstrap the Admin Bar module.
		 *
		 * Binds all delegated event listeners for the toolbar notification system.
		 */
		adminBarInit: function () {
			this.adminBarBindEvents();
			this.adminBarSpeedUpHeartbeat();
		},

		/**
		 * Register delegated event listeners for the admin toolbar.
		 */
		adminBarBindEvents: function () {
			$(document).on('click', '#wpadminbar .aips-mark-read', this.adminBarMarkRead);
			$(document).on('click', '#wpadminbar .aips-mark-all-read', this.adminBarMarkAllRead);
			$(document).on('click', '#wpadminbar .aips-toolbar-notification, #wpadminbar .aips-toolbar-notif-header', this.adminBarStopPropagation);

			// Hook into WordPress Heartbeat events
			$(document).on('heartbeat-send', this.adminBarHeartbeatSend);
			$(document).on('heartbeat-tick', this.adminBarHeartbeatTick);
		},

		/**
		 * Update the unread-notification badge on the toolbar root node.
		 *
		 * @param {number} count New unread count.
		 */
		adminBarUpdateBadge: function (count) {
			var $badge = $('#wp-admin-bar-aips-toolbar .aips-toolbar-badge');
			var $root  = $('#wp-admin-bar-aips-toolbar');

			if (count > 0) {
				var label = count > 99 ? '99+' : String(count);
				if ($badge.length) {
					$badge.text(label);
				} else {
					$('#wp-admin-bar-aips-toolbar > .ab-item .ab-label').after(
						$('<span class="aips-toolbar-badge">').text(label)
					);
				}
				$root.addClass('aips-has-notifications');
			} else {
				$badge.remove();
				$root.removeClass('aips-has-notifications');
			}
		},

		/**
		 * Build the "no new notifications" placeholder list item HTML.
		 *
		 * @return {string} HTML string.
		 */
		adminBarNoNotificationsHtml: function () {
			var noNotifText = wp && wp.i18n
				? wp.i18n.__('No new notifications', 'ai-post-scheduler')
				: 'No new notifications';
			return AIPS.Templates.render('aips-tmpl-admin-bar-no-notifications', {
				text: noNotifText
			});
		},

		/**
		 * Handle click on a "Mark as read" button for a single notification.
		 *
		 * @param {Event} e Click event.
		 */
		adminBarMarkRead: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var l10n  = window.aipsAdminBarL10n || {};
			var $btn  = $(this);
			var id    = $btn.data('id');
			var nonce = $btn.data('nonce');
			var $row  = $btn.closest('li.aips-toolbar-notification');

			$btn.prop('disabled', true);

			$.post(l10n.ajaxUrl, {
				action: 'aips_mark_notification_read',
				nonce:  nonce,
				id:     id
			})
			.done(function (response) {
				if (response && response.success) {
					$row.fadeOut(200, function () {
						$(this).remove();

						// If no notification rows remain, show the empty-state placeholder.
						if ($('#wpadminbar li.aips-toolbar-notification').length === 0) {
							$('#wp-admin-bar-aips-toolbar-notifications-header').remove();
							$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
								AIPS.adminBarNoNotificationsHtml()
							);
						}
					});
					AIPS.adminBarUpdateBadge(response.data.unread_count);
				} else {
					$btn.prop('disabled', false);
					alert(l10n.markReadError || 'Error marking notification as read.');
				}
			})
			.fail(function () {
				$btn.prop('disabled', false);
				alert(l10n.markReadError || 'Error marking notification as read.');
			});
		},

		/**
		 * Handle click on the "Mark all as read" button.
		 *
		 * @param {Event} e Click event.
		 */
		adminBarMarkAllRead: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var l10n  = window.aipsAdminBarL10n || {};
			var $btn  = $(this);
			var nonce = $btn.data('nonce');

			$btn.prop('disabled', true);

			$.post(l10n.ajaxUrl, {
				action: 'aips_mark_all_notifications_read',
				nonce:  nonce
			})
			.done(function (response) {
				if (response && response.success) {
					$('#wpadminbar li.aips-toolbar-notification').remove();
					$('#wp-admin-bar-aips-toolbar-notifications-header').remove();

					// Add "no notifications" placeholder inside the submenu <ul>.
					$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
						AIPS.adminBarNoNotificationsHtml()
					);

					AIPS.adminBarUpdateBadge(response.data.unread_count || 0);
				} else {
					$btn.prop('disabled', false);
					alert(l10n.markAllReadError || 'Error marking all notifications as read.');
				}
			})
			.fail(function () {
				$btn.prop('disabled', false);
				alert(l10n.markAllReadError || 'Error marking all notifications as read.');
			});
		},

		/**
		 * Prevent click events from bubbling out of toolbar notification rows,
		 * so interacting with buttons does not collapse the flyout menu.
		 *
		 * @param {Event} e Click event.
		 */
		adminBarStopPropagation: function (e) {
			e.stopPropagation();
		},

		/**
		 * Append our notification check request parameter to Heartbeat data before sending.
		 *
		 * @param {Event}  e    Event object.
		 * @param {Object} data Heartbeat payload data.
		 */
		adminBarHeartbeatSend: function (e, data) {
			data.aips_check_notifications = true;
		},

		/**
		 * Process incoming Heartbeat tick data to check and show new notifications.
		 *
		 * @param {Event}  e    Event object.
		 * @param {Object} data Heartbeat response data.
		 */
		adminBarHeartbeatTick: function (e, data) {
			if (data && data.aips_notifications) {
				AIPS.adminBarHandleRealtime(data.aips_notifications);
			}
		},

		/**
		 * Handle realtime notification updates from Heartbeat payload.
		 *
		 * @param {Object} payload Heartbeat response payload.
		 */
		adminBarHandleRealtime: function (payload) {
			var unreadCount = parseInt(payload.unread_count, 10) || 0;
			var items = payload.items || [];

			// 1. Update the toolbar root badge count
			AIPS.adminBarUpdateBadge(unreadCount);

			// 2. Remove any notifications from the DOM that are no longer unread
			var serverIds = items.map(function (item) {
				return parseInt(item.id, 10);
			});

			$('#wpadminbar li.aips-toolbar-notification').each(function () {
				var $this = $(this);
				var id = parseInt($this.data('notif-id'), 10);
				if (serverIds.indexOf(id) === -1) {
					$this.fadeOut(200, function () {
						$(this).remove();
						AIPS.adminBarCheckEmptyState();
					});
				}
			});

			// 3. Prepend new notifications
			// Reverse iterate so prepended items maintain descending order (newest first)
			for (var i = items.length - 1; i >= 0; i--) {
				var item = items[i];
				var itemId = parseInt(item.id, 10);
				if (!Number.isInteger(itemId) || itemId <= 0) {
					continue;
				}

				var existingEl = $('#wp-admin-bar-aips-notif-' + itemId);
				if (existingEl.length === 0) {
					var notifHtml = AIPS.adminBarRenderNotificationHtml(item);
					var $header = $('#wp-admin-bar-aips-toolbar-notifications-header');
					if ($header.length > 0) {
						$header.after(notifHtml);
					} else {
						$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').prepend(notifHtml);
					}

					// Trigger toast notification if not already seen
					AIPS.maybeToastNotification(item);
				}
			}

			AIPS.adminBarCheckEmptyState();
		},

		/**
		 * Check empty state and toggle header / placeholder rows in the toolbar.
		 */
		adminBarCheckEmptyState: function () {
			var notifCount = $('#wpadminbar li.aips-toolbar-notification').length;
			if (notifCount === 0) {
				$('#wp-admin-bar-aips-toolbar-notifications-header').remove();
				if ($('#wp-admin-bar-aips-toolbar-no-notifications').length === 0) {
					$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
						AIPS.adminBarNoNotificationsHtml()
					);
				}
			} else {
				$('#wp-admin-bar-aips-toolbar-no-notifications').remove();
				if ($('#wp-admin-bar-aips-toolbar-notifications-header').length === 0) {
					$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').prepend(
						AIPS.adminBarHeaderHtml()
					);
				}
			}
		},

		/**
		 * Build the admin toolbar notifications header list item HTML.
		 *
		 * @return {string} HTML string.
		 */
		adminBarHeaderHtml: function () {
			var headingText = wp && wp.i18n ? wp.i18n.__('Notifications', 'ai-post-scheduler') : 'Notifications';
			var markAllText = wp && wp.i18n ? wp.i18n.__('Mark all as read', 'ai-post-scheduler') : 'Mark all as read';
			var nonce = window.aipsAdminBarL10n ? window.aipsAdminBarL10n.nonce : '';

			return AIPS.Templates.render('aips-tmpl-admin-bar-header', {
				headingText: headingText,
				markAllText: markAllText,
				nonce: nonce
			});
		},

		/**
		 * Render the list item HTML for a single notification in the toolbar.
		 *
		 * @param {Object} item Notification object.
		 * @return {string} HTML string.
		 */
		adminBarRenderNotificationHtml: function (item) {
			var id = parseInt(item.id, 10);
			if (!Number.isInteger(id) || id <= 0) {
				return '';
			}

			var title = item.title ? String(item.title) : '';
			var message = item.message ? String(item.message) : '';
			var url = item.url ? String(item.url) : '';
			var levelClass = '';
			if (item.level && (item.level === 'warning' || item.level === 'error')) {
				levelClass = ' aips-notif-level-' + item.level;
			}

			var markReadText = wp && wp.i18n ? wp.i18n.__('Mark as read', 'ai-post-scheduler') : 'Mark as read';
			var nonce = window.aipsAdminBarL10n ? window.aipsAdminBarL10n.nonce : '';
			var titleHtml = '';
			if (title) {
				titleHtml = AIPS.Templates.render('aips-tmpl-admin-bar-notification-title', {
					title: title
				});
			}

			var messageHtml = AIPS.Templates.escape(message);
			if (url) {
				messageHtml = AIPS.Templates.render('aips-tmpl-admin-bar-notification-message-link', {
					url: url,
					message: message
				});
			}

			return AIPS.Templates.renderRaw('aips-tmpl-admin-bar-notification-row', {
				id: id,
				levelClass: levelClass,
				titleHtml: titleHtml,
				messageHtml: messageHtml,
				nonce: AIPS.Templates.escape(nonce),
				markReadText: AIPS.Templates.escape(markReadText)
			});
		},

		/**
		 * Check localStorage seen toasts registry and trigger toast if not seen yet.
		 *
		 * @param {Object} item Notification object.
		 */
		maybeToastNotification: function (item) {
			var seenToasts = [];
			try {
				seenToasts = JSON.parse(localStorage.getItem('aips_seen_toasts') || '[]');
			} catch (e) {
				seenToasts = [];
			}

			if (seenToasts.indexOf(item.id) !== -1) {
				return;
			}

			seenToasts.push(item.id);
			if (seenToasts.length > 200) {
				seenToasts.shift();
			}
			localStorage.setItem('aips_seen_toasts', JSON.stringify(seenToasts));

			AIPS.adminBarShowToast(item);
		},

		/**
		 * Render a toast alert using the shared utilities API.
		 *
		 * @param {Object} item Notification object.
		 */
		adminBarShowToast: function (item) {
			var infoTitle = wp && wp.i18n ? wp.i18n.__('Info', 'ai-post-scheduler') : 'Info';
			var warningTitle = wp && wp.i18n ? wp.i18n.__('Warning', 'ai-post-scheduler') : 'Warning';
			var errorTitle = wp && wp.i18n ? wp.i18n.__('Error', 'ai-post-scheduler') : 'Error';
			var successTitle = wp && wp.i18n ? wp.i18n.__('Success', 'ai-post-scheduler') : 'Success';
			var level = (item.level === 'warning' || item.level === 'error' || item.level === 'success') ? item.level : 'info';
			var defaultTitle = infoTitle;

			if (level === 'warning') {
				defaultTitle = warningTitle;
			} else if (level === 'error') {
				defaultTitle = errorTitle;
			} else if (level === 'success') {
				defaultTitle = successTitle;
			}

			var title = item.title || defaultTitle;
			var toastHtml = AIPS.Templates.render('aips-tmpl-admin-bar-toast-message', {
				title: title,
				message: item.message || ''
			});

			if (item.url) {
				toastHtml = AIPS.Templates.render('aips-tmpl-admin-bar-toast-link-message', {
					title: title,
					url: item.url,
					message: item.message || ''
				});
			}

			AIPS.Utilities.showToast(toastHtml, level, {
				isHtml: true,
				duration: (level === 'warning' || level === 'error') ? 0 : 6000
			});
		},

		/**
		 * Speed up Heartbeat frequency dynamically on AI Post Scheduler pages.
		 */
		adminBarSpeedUpHeartbeat: function () {
			var page = null;
			var match = window.location.search.match(/[?&]page=([^&]+)/);
			if (match) {
				page = decodeURIComponent(match[1]);
			}

			if (page && (page.indexOf('aips-') === 0 || page === 'ai-post-scheduler')) {
				if (window.wp && wp.heartbeat) {
					wp.heartbeat.interval('fast');
				}
			}
		}
	});

	$(document).ready(function () {
		AIPS.adminBarInit();
	});

}(jQuery));
