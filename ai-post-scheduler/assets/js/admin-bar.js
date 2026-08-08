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

		/** Matches any AJAX `action` that triggers AI content generation. */
		AI_RUNNING_ACTION_PATTERN: /generate|run_now/i,

		/** Number of in-flight AI-generation requests, used to track the running state across overlapping calls. */
		aiRunningRequestCount: 0,

		/**
		 * Bootstrap the Admin Bar module.
		 *
		 * Binds all delegated event listeners for the toolbar notification system.
		 */
		adminBarInit: function () {
			this.adminBarBindEvents();
			this.adminBarBindAiRunningIndicator();
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
		 * Show the toolbar AI spinner for the duration of any AJAX request whose
		 * `action` matches an AI-generation endpoint (e.g. `aips_run_now`,
		 * `aips_generate_topics`), across all admin pages/modules — instead of
		 * wiring each generation call site individually.
		 */
		adminBarBindAiRunningIndicator: function () {
			$(document).ajaxSend(function (event, jqXHR, ajaxSettings) {
				if (!AIPS.adminBarIsAiRunningAction(ajaxSettings)) {
					return;
				}
				AIPS.aiRunningRequestCount++;
				AIPS.adminBarSetAiRunning(true);
			});

			$(document).ajaxComplete(function (event, jqXHR, ajaxSettings) {
				if (!AIPS.adminBarIsAiRunningAction(ajaxSettings)) {
					return;
				}
				AIPS.aiRunningRequestCount = Math.max(0, AIPS.aiRunningRequestCount - 1);
				if (AIPS.aiRunningRequestCount === 0) {
					AIPS.adminBarSetAiRunning(false);
				}
			});
		},

		/**
		 * Determine whether an AJAX request's `action` parameter identifies it
		 * as an AI-generation request.
		 *
		 * @param {Object} ajaxSettings jQuery AJAX settings for the request.
		 * @return {boolean}
		 */
		adminBarIsAiRunningAction: function (ajaxSettings) {
			var data = ajaxSettings && ajaxSettings.data;
			if (!data) {
				return false;
			}
			var action = typeof data === 'string'
				? (function () {
					var match = /(?:^|&)action=([^&]*)/.exec(data);
					return match ? decodeURIComponent(match[1]) : '';
				})()
				: data.action;

			return !!action && AIPS.AI_RUNNING_ACTION_PATTERN.test(action);
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
		 * Show or hide the live AI-running spinner in the admin bar badge.
		 *
		 * @param {boolean} running Whether AI is currently processing.
		 * @return {void}
		 */
		adminBarSetAiRunning: function (running) {
			var $spinner = $('#wp-admin-bar-aips-toolbar .aips-toolbar-ai-spinner');
			if (running) {
				if (!$spinner.length) {
					$('#wp-admin-bar-aips-toolbar > .ab-item .ab-label').after(
						$('<span class="aips-toolbar-ai-spinner aips-spin dashicons dashicons-update" title="AI is running" aria-label="AI processing in progress">')
					);
				}
			} else {
				$spinner.remove();
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
		}
	});

	$(document).ready(function () {
		AIPS.adminBarInit();
	});

}(jQuery));
