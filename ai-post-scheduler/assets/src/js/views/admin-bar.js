import Backbone from 'backbone';
import $ from 'jquery';

/**
 * WordPress Admin Bar View Controller
 */
export const AdminBarView = Backbone.View.extend({
	el: '#wpadminbar',

	events: {
		'click .aips-mark-read': 'markRead',
		'click .aips-mark-all-read': 'markAllRead',
		'click .aips-toolbar-notification, .aips-toolbar-notif-header': 'stopPropagation'
	},

	initialize() {
		this.l10n = window.aipsAdminBarL10n || {};
		
		// Exposed globally for backwards compatibility/PHP enqueues
		window.AIPS = window.AIPS || {};
		window.AIPS.adminBarInit = () => {};
		window.AIPS.adminBarUpdateBadge = this.updateBadge.bind(this);
	},

	updateBadge(count) {
		const $badge = this.$('#wp-admin-bar-aips-toolbar .aips-toolbar-badge');
		const $root = this.$('#wp-admin-bar-aips-toolbar');

		if (count > 0) {
			const label = count > 99 ? '99+' : String(count);
			if ($badge.length) {
				$badge.text(label);
			} else {
				this.$('#wp-admin-bar-aips-toolbar > .ab-item .ab-label').after(
					$('<span class="aips-toolbar-badge">').text(label)
				);
			}
			$root.addClass('aips-has-notifications');
		} else {
			$badge.remove();
			$root.removeClass('aips-has-notifications');
		}
	},

	getNoNotificationsHtml() {
		const noNotifText = (window.wp && window.wp.i18n)
			? window.wp.i18n.__('No new notifications', 'ai-post-scheduler')
			: 'No new notifications';
		return '<li id="wp-admin-bar-aips-toolbar-no-notifications" class="aips-toolbar-no-notifications ab-empty-item">'
			+ '<span class="ab-item aips-toolbar-empty">'
			+ $('<div>').text(noNotifText).html()
			+ '</span></li>';
	},

	markRead(e) {
		e.preventDefault();
		e.stopPropagation();

		const $btn = $(e.currentTarget);
		const id = $btn.attr('data-id') || $btn.data('id');
		const nonce = $btn.attr('data-nonce') || $btn.data('nonce');
		const $row = $btn.closest('li.aips-toolbar-notification');

		$btn.prop('disabled', true);

		$.post(this.l10n.ajaxUrl || (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_mark_notification_read',
			nonce: nonce,
			id: id
		})
		.done((response) => {
			if (response && response.success) {
				$row.fadeOut(200, () => {
					$row.remove();

					if (this.$('li.aips-toolbar-notification').length === 0) {
						this.$('#wp-admin-bar-aips-toolbar-notifications-header').remove();
						this.$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
							this.getNoNotificationsHtml()
						);
					}
				});
				this.updateBadge(response.data.unread_count);
			} else {
				$btn.prop('disabled', false);
				alert(this.l10n.markReadError || 'Error marking notification as read.');
			}
		})
		.fail(() => {
			$btn.prop('disabled', false);
			alert(this.l10n.markReadError || 'Error marking notification as read.');
		});
	},

	markAllRead(e) {
		e.preventDefault();
		e.stopPropagation();

		const $btn = $(e.currentTarget);
		const nonce = $btn.attr('data-nonce') || $btn.data('nonce');

		$btn.prop('disabled', true);

		$.post(this.l10n.ajaxUrl || (window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_mark_all_notifications_read',
			nonce: nonce
		})
		.done((response) => {
			if (response && response.success) {
				this.$('li.aips-toolbar-notification').remove();
				this.$('#wp-admin-bar-aips-toolbar-notifications-header').remove();

				this.$('#wp-admin-bar-aips-toolbar-notifications .ab-submenu').append(
					this.getNoNotificationsHtml()
				);

				this.updateBadge(response.data.unread_count || 0);
			} else {
				$btn.prop('disabled', false);
				alert(this.l10n.markAllReadError || 'Error marking all notifications as read.');
			}
		})
		.fail(() => {
			$btn.prop('disabled', false);
			alert(this.l10n.markAllReadError || 'Error marking all notifications as read.');
		});
	},

	stopPropagation(e) {
		e.stopPropagation();
	}
});

export default AdminBarView;
