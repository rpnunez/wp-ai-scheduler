/**
 * Google Search Console field on Settings → API Keys: test the connection,
 * sync target keywords now, and disconnect.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsGscL10n || {};

	AIPS.SearchConsole = {
		init: function() {
			if (!$('#aips-gsc-field').length) {
				return;
			}
			this.bindEvents();
		},

		bindEvents: function() {
			$(document).on('click', '#aips-gsc-test', this.onTest.bind(this));
			$(document).on('click', '#aips-gsc-sync', this.onSync.bind(this));
			$(document).on('click', '#aips-gsc-disconnect', this.onDisconnect.bind(this));
		},

		post: function($button, action, onSuccess) {
			var req = $.post(ajaxurl, { action: action, nonce: l10n.nonce }).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				if (onSuccess) {
					onSuccess(response.data);
				}
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.error, 'error');
			});

			AIPS.Utilities.withLock($button, req, { loadingText: l10n.working, timeout: 300000 });
		},

		onTest: function(e) {
			this.post($(e.currentTarget), 'aips_gsc_test');
		},

		onSync: function(e) {
			this.post($(e.currentTarget), 'aips_gsc_sync', function(data) {
				$('#aips-gsc-last-sync').text(data.message);
			});
		},

		onDisconnect: function(e) {
			var self = this;
			var $button = $(e.currentTarget);

			AIPS.Utilities.confirm(l10n.confirmDisconnect, l10n.confirmDisconnectTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.disconnect,
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						self.post($button, 'aips_gsc_disconnect', function() {
							$('#aips-gsc-field .aips-gsc-status').text(l10n.disconnected);
							$button.remove();
						});
					}
				}
			]);
		}
	};

	$(document).ready(function() {
		AIPS.SearchConsole.init();
	});
})(jQuery);
