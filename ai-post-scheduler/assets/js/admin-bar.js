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
			return '<li id="wp-admin-bar-aips-toolbar-no-notifications" class="aips-toolbar-no-notifications ab-empty-item">'
				+ '<span class="ab-item aips-toolbar-empty">'
				+ $('<div>').text(noNotifText).html()
				+ '</span></li>';
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
				var existingEl = $('#wp-admin-bar-aips-notif-' + item.id);
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

			return '<li id="wp-admin-bar-aips-toolbar-notifications-header" class="aips-toolbar-notif-header ab-empty-item">'
				+ '<div class="ab-item ab-empty-item">'
				+ '<span class="aips-toolbar-notif-heading">' + $('<div>').text(headingText).html() + '</span>'
				+ '<button class="aips-mark-all-read" data-nonce="' + nonce + '">'
				+ $('<div>').text(markAllText).html()
				+ '</button>'
				+ '</div></li>';
		},

		/**
		 * Render the list item HTML for a single notification in the toolbar.
		 *
		 * @param {Object} item Notification object.
		 * @return {string} HTML string.
		 */
		adminBarRenderNotificationHtml: function (item) {
			var $row = $('<li>', {
				id: 'wp-admin-bar-aips-notif-' + item.id,
				'class': 'aips-toolbar-notification ab-empty-item',
				'data-notif-id': item.id
			});
			var levelClass = '';
			if (item.level && (item.level === 'warning' || item.level === 'error')) {
				levelClass = 'aips-notif-level-' + item.level;
			}
			$row.addClass(levelClass);

			var markReadText = wp && wp.i18n ? wp.i18n.__('Mark as read', 'ai-post-scheduler') : 'Mark as read';
			var nonce = window.aipsAdminBarL10n ? window.aipsAdminBarL10n.nonce : '';
			var $content = $('<div class="ab-item ab-empty-item"></div>');

			if (item.title) {
				$content.append(
					$('<span class="aips-notif-title"></span>').text(item.title)
				);
			}

			var $message = $('<span class="aips-notif-message"></span>');
			if (item.url) {
				$message.append(
					$('<a></a>').attr('href', item.url).text(item.message)
				);
			} else {
				$message.text(item.message);
			}

			var $markReadButton = $('<button class="aips-mark-read" type="button"></button>')
				.attr('data-id', item.id)
				.attr('data-nonce', nonce)
				.attr('title', markReadText)
				.append('<span class="dashicons dashicons-yes-alt"></span>');

			$content.append($message).append($markReadButton);
			$row.append($content);

			return $('<div>').append($row).html();
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

			AIPS.showToastNotification(item);
		},

		/**
		 * Render and slide-in a floating toast alert.
		 *
		 * @param {Object} item Notification object.
		 */
		showToastNotification: function (item) {
			var $container = $('.aips-toast-container');
			if ($container.length === 0) {
				$container = $('<div class="aips-toast-container"></div>');
				$('body').append($container);
			}

			var infoTitle = wp && wp.i18n ? wp.i18n.__('Info', 'ai-post-scheduler') : 'Info';
			var warningTitle = wp && wp.i18n ? wp.i18n.__('Warning', 'ai-post-scheduler') : 'Warning';
			var errorTitle = wp && wp.i18n ? wp.i18n.__('Error', 'ai-post-scheduler') : 'Error';
			var successTitle = wp && wp.i18n ? wp.i18n.__('Success', 'ai-post-scheduler') : 'Success';
			var closeToastText = wp && wp.i18n ? wp.i18n.__('Close notification', 'ai-post-scheduler') : 'Close notification';

			var iconClass = 'dashicons-info';
			var defaultTitle = infoTitle;

			if (item.level === 'warning') {
				iconClass = 'dashicons-warning';
				defaultTitle = warningTitle;
			} else if (item.level === 'error') {
				iconClass = 'dashicons-no';
				defaultTitle = errorTitle;
			} else if (item.level === 'success') {
				iconClass = 'dashicons-yes';
				defaultTitle = successTitle;
			}

			var messageHtml = item.url
				? '<a href="' + item.url + '">' + $('<div>').text(item.message).html() + '</a>'
				: $('<div>').text(item.message).html();

			var isPersistent = (item.level === 'warning' || item.level === 'error');
			var progressHtml = isPersistent ? '' : '<div class="aips-toast-progress"></div>';
			var closeButtonHtml = $('<button class="aips-toast-close" type="button">&times;</button>')
				.attr('aria-label', closeToastText)
				.prop('outerHTML');

			var toastHtml = '<div class="aips-toast aips-toast-' + item.level + '" data-id="' + item.id + '">'
				+ '<div class="aips-toast-header">'
				+ '<span class="aips-toast-icon dashicons ' + iconClass + '"></span>'
				+ '<span class="aips-toast-title">' + $('<div>').text(item.title || defaultTitle).html() + '</span>'
				+ closeButtonHtml
				+ '</div>'
				+ '<div class="aips-toast-body">' + messageHtml + '</div>'
				+ progressHtml
				+ '</div>';

			var $toast = $(toastHtml);
			$container.append($toast);

			// Click to close handler
			$toast.find('.aips-toast-close').on('click', function (e) {
				e.preventDefault();
				$toast.fadeOut(300, function () {
					$(this).remove();
				});
			});

			// Auto-dismiss progress bar and hover-to-pause logic
			if (!isPersistent) {
				var timeLeft = 6000;
				var duration = 6000;
				var interval = 50;
				var isHovered = false;
				var $progress = $toast.find('.aips-toast-progress');

				$toast.on('mouseenter', function () {
					isHovered = true;
				});
				$toast.on('mouseleave', function () {
					isHovered = false;
				});

				var timer = setInterval(function () {
					if (!isHovered) {
						timeLeft -= interval;
						var pct = (timeLeft / duration) * 100;
						$progress.css('width', pct + '%');
						if (timeLeft <= 0) {
							clearInterval(timer);
							$toast.fadeOut(300, function () {
								$(this).remove();
							});
						}
					}
				}, interval);
			}
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
