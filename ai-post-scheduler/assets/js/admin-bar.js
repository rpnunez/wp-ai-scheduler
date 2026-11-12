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
		},

		/**
		 * Register delegated event listeners for the admin toolbar.
		 */
		adminBarBindEvents: function () {
			$(document).on('click', '#wpadminbar .aips-mark-read', this.adminBarMarkRead);
			$(document).on('click', '#wpadminbar .aips-mark-all-read', this.adminBarMarkAllRead);
			$(document).on('click', '#wpadminbar .aips-toolbar-notification, #wpadminbar .aips-toolbar-notif-header', this.adminBarStopPropagation);
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
		 * Show a toast, falling back to the background-process notice on pages
		 * where the plugin's utilities are not loaded.
		 *
		 * @param {string} message Text.
		 * @param {string} type    success or error.
		 */
		adminBarToast: function (message, type) {
			if (AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(message, type);
				return;
			}

			if (AIPS.BackgroundProcesses && AIPS.BackgroundProcesses.toast) {
				AIPS.BackgroundProcesses.toast(message, type);
				return;
			}

			var $toast = $('<div class="aips-bg-toast" role="status"></div>')
				.addClass('aips-bg-toast-' + (type || 'info'))
				.text(message);
			$('body').append($toast);
			setTimeout(function () {
				$toast.remove();
			}, 5000);
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
							$('#wp-admin-bar-aips-toolbar-notifications').append(
								AIPS.adminBarNoNotificationsHtml()
							);
						}
					});
					AIPS.adminBarUpdateBadge(response.data.unread_count);
				} else {
					$btn.prop('disabled', false);
					AIPS.adminBarToast(l10n.markReadError || 'Error marking notification as read.', 'error');
				}
			})
			.fail(function () {
				$btn.prop('disabled', false);
				AIPS.adminBarToast(l10n.markReadError || 'Error marking notification as read.', 'error');
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
					$('#wp-admin-bar-aips-toolbar-notifications').append(
						AIPS.adminBarNoNotificationsHtml()
					);

					AIPS.adminBarUpdateBadge(response.data.unread_count || 0);
				} else {
					$btn.prop('disabled', false);
					AIPS.adminBarToast(l10n.markAllReadError || 'Error marking all notifications as read.', 'error');
				}
			})
			.fail(function () {
				$btn.prop('disabled', false);
				AIPS.adminBarToast(l10n.markAllReadError || 'Error marking all notifications as read.', 'error');
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
		}
	});

	$(document).ready(function () {
		AIPS.adminBarInit();
	});

}(jQuery));
