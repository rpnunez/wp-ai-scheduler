/**
 * AI Post Scheduler – Admin Toolbar JavaScript
 *
 * Handles "mark as read" / "mark all as read" for toolbar notifications.
 */
(function ($) {
	'use strict';

	var l10n = window.aipsAdminBarL10n || {};

	/**
	 * Update the badge count on the toolbar root node.
	 *
	 * @param {number} count New unread count.
	 */
	function updateBadge(count) {
		var $badge = $('#wp-admin-bar-aips-toolbar .aips-toolbar-badge');
		var $root  = $('#wp-admin-bar-aips-toolbar');

		if (count > 0) {
			var label = count > 99 ? '99+' : String(count);
			if ($badge.length) {
				$badge.text(label);
			} else {
				// Re-create the badge if it was removed
				$('#wp-admin-bar-aips-toolbar > .ab-item .ab-label').after(
					$('<span class="aips-toolbar-badge">').text(label)
				);
			}
			$root.addClass('aips-has-notifications');
		} else {
			$badge.remove();
			$root.removeClass('aips-has-notifications');
		}
	}

	/**
	 * Handle "Mark as read" for a single notification.
	 */
	$(document).on('click', '#wpadminbar .aips-mark-read', function (e) {
		e.preventDefault();
		e.stopPropagation();

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

					// If no notification rows left, show "no notifications"
					if ($('#wpadminbar li.aips-toolbar-notification').length === 0) {
						$('#wp-admin-bar-aips-toolbar-notifications-header').remove();
						$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
							'<li id="wp-admin-bar-aips-toolbar-no-notifications" class="aips-toolbar-no-notifications">'
							+ '<span class="ab-item aips-toolbar-empty">'
							+ ($('<div>').text(wp.i18n ? wp.i18n.__('No new notifications', 'ai-post-scheduler') : 'No new notifications').html())
							+ '</span></li>'
						);
					}
				});
				updateBadge(response.data.unread_count);
			} else {
				$btn.prop('disabled', false);
				alert(l10n.markReadError || 'Error marking notification as read.');
			}
		})
		.fail(function () {
			$btn.prop('disabled', false);
			alert(l10n.markReadError || 'Error marking notification as read.');
		});
	});

	/**
	 * Handle "Mark all as read".
	 */
	$(document).on('click', '#wpadminbar .aips-mark-all-read', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn   = $(this);
		var nonce  = $btn.data('nonce');

		$btn.prop('disabled', true);

		$.post(l10n.ajaxUrl, {
			action: 'aips_mark_all_notifications_read',
			nonce:  nonce
		})
		.done(function (response) {
			if (response && response.success) {
				// Remove all notification rows and the header
				$('#wpadminbar li.aips-toolbar-notification').remove();
				$('#wp-admin-bar-aips-toolbar-notifications-header').remove();

				// Add "no notifications" placeholder
				$('#wp-admin-bar-aips-toolbar-notifications').append(
					'<li id="wp-admin-bar-aips-toolbar-no-notifications" class="aips-toolbar-no-notifications">'
					+ '<span class="ab-item aips-toolbar-empty">'
					+ ($('<div>').text(wp.i18n ? wp.i18n.__('No new notifications', 'ai-post-scheduler') : 'No new notifications').html())
					+ '</span></li>'
				);

				updateBadge(0);
			} else {
				$btn.prop('disabled', false);
				alert(l10n.markAllReadError || 'Error marking all notifications as read.');
			}
		})
		.fail(function () {
			$btn.prop('disabled', false);
			alert(l10n.markAllReadError || 'Error marking all notifications as read.');
		});
	});

	/**
	 * Keep the toolbar dropdown open when interacting with buttons inside it,
	 * so clicking "mark as read" doesn't close the flyout.
	 */
	$(document).on('click', '#wpadminbar .aips-toolbar-notification, #wpadminbar .aips-toolbar-notif-header', function (e) {
		e.stopPropagation();
	});

}(jQuery));
