/**
 * Shared AI Post Scheduler admin flyout navigation.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var config = window.aipsAdminFlyouts || {};

	AIPS.AdminFlyoutNavigation = {
		$panel: $(),
		$trigger: $(),
		openTimer: null,
		closeTimer: null,
		currentHub: null,

		init: function() {
			if (!AIPS.Templates || !Array.isArray(config.hubs) || !config.hubs.length || this.isDisabled()) {
				return;
			}
			var self = this;
			$('#toplevel_page_ai-post-scheduler .wp-submenu a').each(function() {
				if (self.getHubForLink(this)) {
					$(this).attr({ 'aria-haspopup': 'menu', 'aria-expanded': 'false' });
				}
			});
			this.bindEvents();
		},

		isDisabled: function() {
			var breakpoint = parseInt(config.breakpoint || 782, 10);
			return !!(window.matchMedia && (
				window.matchMedia('(max-width: ' + breakpoint + 'px)').matches ||
				window.matchMedia('(hover: none) and (pointer: coarse)').matches
			));
		},

		bindEvents: function() {
			var self = this;
			var selector = '#toplevel_page_ai-post-scheduler .wp-submenu a';

			$(document)
				.on('mouseenter.aipsFlyout', selector, function() {
					var hub = self.getHubForLink(this);
					if (hub) {
						self.scheduleOpen($(this), hub);
					}
				})
				.on('mouseleave.aipsFlyout', selector, function() {
					self.scheduleClose();
				})
				.on('focusin.aipsFlyout', selector, function() {
					var hub = self.getHubForLink(this);
					if (hub) {
						self.open($(this), hub);
					}
				})
				.on('keydown.aipsFlyout', selector, function(event) {
					if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
						var hub = self.getHubForLink(this);
						if (hub) {
							event.preventDefault();
							self.open($(this), hub);
							self.$panel.find('[role="menuitem"]').first().focus();
						}
					}
				})
				.on('focusin.aipsFlyout mouseenter.aipsFlyout', '.aips-admin-flyout', function() {
					self.cancelClose();
				})
				.on('focusout.aipsFlyout mouseleave.aipsFlyout', '.aips-admin-flyout', function() {
					self.scheduleClose();
				})
				.on('keydown.aipsFlyout', '.aips-admin-flyout [role="menuitem"]', function(event) {
					self.handleItemKeydown(event, $(this));
				})
				.on('focusin.aipsFlyout', function(event) {
					if (self.$panel.length && !self.$panel.is(event.target) && !self.$panel.has(event.target).length && !self.$trigger.is(event.target)) {
						self.close(false);
					}
				})
				.on('keydown.aipsFlyout', function(event) {
					if (event.key === 'Escape' && self.$panel.length) {
						event.preventDefault();
						self.close(true);
					}
				});

			$(window).on('resize.aipsFlyout scroll.aipsFlyout', function() {
				if (self.$panel.length && self.$trigger.length) {
					self.position();
				}
			});
		},

		getHubForLink: function(link) {
			var page = '';
			try {
				page = new URL(link.href, window.location.href).searchParams.get('page') || '';
			} catch (error) {
				return null;
			}
			return config.hubs.find(function(hub) {
				return hub.slug === page;
			}) || null;
		},

		scheduleOpen: function($trigger, hub) {
			var self = this;
			this.cancelClose();
			window.clearTimeout(this.openTimer);
			this.openTimer = window.setTimeout(function() {
				self.open($trigger, hub);
			}, parseInt(config.openDelay || 120, 10));
		},

		scheduleClose: function() {
			var self = this;
			window.clearTimeout(this.openTimer);
			this.cancelClose();
			this.closeTimer = window.setTimeout(function() {
				var active = document.activeElement;
				if (!self.$panel.is(':hover') && !self.$trigger.is(':hover') && !self.$panel.has(active).length) {
					self.close(false);
				}
			}, parseInt(config.closeDelay || 200, 10));
		},

		cancelClose: function() {
			window.clearTimeout(this.closeTimer);
			this.closeTimer = null;
		},

		open: function($trigger, hub) {
			window.clearTimeout(this.openTimer);
			this.cancelClose();
			if (this.$trigger.length && !this.$trigger.is($trigger)) {
				this.$trigger.attr('aria-expanded', 'false');
			}
			this.$trigger = $trigger;
			if (!this.$panel.length || !this.currentHub || this.currentHub.slug !== hub.slug) {
				this.render(hub);
			}
			this.currentHub = hub;
			this.$panel.prop('hidden', false);
			$trigger.attr('aria-expanded', 'true');
			this.position();
		},

		render: function(hub) {
			var groupsHtml = '';
			(hub.groups || []).forEach(function(group) {
				var itemsHtml = '';
				(group.items || []).forEach(function(item) {
					var hasBadge = Number.isInteger(item.badge) && item.badge > 0;
					itemsHtml += AIPS.Templates.render('aips-tmpl-admin-flyout-item', {
						activeClass: item.active ? 'is-active' : '',
						url: item.url,
						current: item.active ? 'page' : 'false',
						icon: item.icon,
						label: item.label,
						description: item.description,
						badgeClass: hasBadge ? '' : 'aips-is-hidden',
						badgeLabel: hasBadge ? String(item.badge) + ' ' + (config.pendingReviewLabel || 'pending reviews') : '',
						badgeCount: hasBadge ? item.badge : ''
					});
				});
				groupsHtml += AIPS.Templates.renderRaw('aips-tmpl-admin-flyout-group', {
					label: AIPS.Templates.escape(group.label),
					items: itemsHtml
				});
			});

			this.$panel.remove();
			$('body').append(AIPS.Templates.renderRaw('aips-tmpl-admin-flyout', {
				label: AIPS.Templates.escape(hub.label + ' ' + (config.navigationLabel || 'navigation')),
				hub: AIPS.Templates.escape(hub.slug),
				groups: groupsHtml
			}));
			this.$panel = $('.aips-admin-flyout').last();
		},

		position: function() {
			if (!this.$panel.length || !this.$trigger.length) {
				return;
			}
			var rect = this.$trigger[0].getBoundingClientRect();
			var panelWidth = this.$panel.outerWidth();
			var panelHeight = this.$panel.outerHeight();
			var rtl = $('body').hasClass('rtl');
			var left = rtl ? rect.left - panelWidth : rect.right;
			var top = rect.top - 8;
			var edge = 8;

			left = Math.max(edge, Math.min(left, window.innerWidth - panelWidth - edge));
			top = Math.max(edge, Math.min(top, window.innerHeight - panelHeight - edge));
			this.$panel.css({ left: left, top: top });
		},

		handleItemKeydown: function(event, $item) {
			var $items = this.$panel.find('[role="menuitem"]');
			var index = $items.index($item);
			var next = index;
			if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
				next = (index + 1) % $items.length;
			} else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
				next = (index - 1 + $items.length) % $items.length;
			} else if (event.key === 'Home') {
				next = 0;
			} else if (event.key === 'End') {
				next = $items.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			$items.eq(next).focus();
		},

		close: function(restoreFocus) {
			window.clearTimeout(this.openTimer);
			this.cancelClose();
			if (this.$panel.length) {
				this.$panel.prop('hidden', true);
			}
			this.$trigger.attr('aria-expanded', 'false');
			if (restoreFocus && this.$trigger.length) {
				this.$trigger.focus();
			}
		},

		destroy: function() {
			this.close(false);
			$(document).off('.aipsFlyout');
			$(window).off('.aipsFlyout');
			this.$panel.remove();
			this.$panel = $();
		}
	};

	$(function() {
		AIPS.AdminFlyoutNavigation.init();
	});
})(jQuery);
