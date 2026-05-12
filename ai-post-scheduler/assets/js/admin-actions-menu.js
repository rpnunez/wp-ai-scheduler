/**
 * Admin Actions Menu — split-button overflow menu controller.
 *
 * Manages open/close state, single-open behaviour, click-outside close,
 * Escape to close, and ArrowUp/ArrowDown keyboard navigation for row action
 * overflow menus rendered with `.aips-row-actions-menu`.
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	// -----------------------------------------------------------------
	// ActionsMenu module
	// -----------------------------------------------------------------
	AIPS.ActionsMenu = {

		/** @type {Element|null} Currently open menu wrapper element. */
		openMenu: null,

		/** @type {string} Selector for the root wrapper of each split-button. */
		rootSelector: '.aips-row-actions-menu',

		/** @type {string} Selector for the toggle button inside the wrapper. */
		toggleSelector: '.aips-row-actions-toggle',

		/** @type {string} Selector for focusable items inside the dropdown. */
		itemSelector: '[role="menuitem"]',

		/**
		 * Bootstrap the ActionsMenu module.
		 *
		 * @return {void}
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind all UI event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function () {
			$(document).on('click', this.onDocumentClick.bind(this));
			$(document).on('keydown', this.onDocumentKeydown.bind(this));
		},

		/**
		 * Close the given menu wrapper, optionally returning focus to its toggle.
		 *
		 * @param {Element} menuWrapper  Root `.aips-row-actions-menu` element.
		 * @param {boolean} returnFocus  Whether to move focus back to the toggle.
		 * @return {void}
		 */
		closeMenu: function (menuWrapper, returnFocus) {
			if (!menuWrapper) {
				return;
			}

			var toggle = menuWrapper.querySelector(this.toggleSelector);
			var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
			var menu = menuId ? document.getElementById(menuId) : null;

			if (!toggle || !menu) {
				return;
			}

			toggle.setAttribute('aria-expanded', 'false');
			menu.hidden = true;
			this.openMenu = null;

			if (returnFocus) {
				toggle.focus();
			}
		},

		/**
		 * Open the dropdown for the given menu wrapper, closing any other open menu.
		 *
		 * @param {Element} menuWrapper  Root `.aips-row-actions-menu` element.
		 * @return {void}
		 */
		openMenuFor: function (menuWrapper) {
			if (!menuWrapper) {
				return;
			}

			if (this.openMenu && this.openMenu !== menuWrapper) {
				this.closeMenu(this.openMenu, false);
			}

			var toggle = menuWrapper.querySelector(this.toggleSelector);
			var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
			var menu = menuId ? document.getElementById(menuId) : null;

			if (!toggle || !menu) {
				return;
			}

			toggle.setAttribute('aria-expanded', 'true');
			menu.hidden = false;
			this.openMenu = menuWrapper;
		},

		/**
		 * Handle document-level click events to toggle menus and close on outside click.
		 *
		 * @param {Event} e  Native or jQuery click event.
		 * @return {void}
		 */
		onDocumentClick: function (e) {
			var target   = e.target || e.originalEvent && e.originalEvent.target;
			var toggle   = target.closest ? target.closest(this.toggleSelector) : null;

			// Toggle button clicked.
			if (toggle) {
				e.preventDefault();
				var wrapper    = toggle.closest(this.rootSelector);
				var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
				if (isExpanded) {
					this.closeMenu(wrapper, false);
				} else {
					this.openMenuFor(wrapper);
				}
				return;
			}

			// Menu item inside the dropdown clicked — close the menu.
			if (this.openMenu) {
				var menuToggle = this.openMenu.querySelector(this.toggleSelector);
				var menuId     = menuToggle ? menuToggle.getAttribute('aria-controls') : '';
				var dropdown   = menuId ? document.getElementById(menuId) : null;
				if (dropdown && dropdown.contains(target)) {
					this.closeMenu(this.openMenu, false);
					return;
				}
			}

			// Click outside — close any open menu.
			if (this.openMenu && !(target.closest ? target.closest(this.rootSelector) : false)) {
				this.closeMenu(this.openMenu, false);
			}
		},

		/**
		 * Handle document-level keydown events for Escape and arrow-key navigation.
		 *
		 * @param {Event} e  Native or jQuery keydown event.
		 * @return {void}
		 */
		onDocumentKeydown: function (e) {
			if (!this.openMenu) {
				return;
			}

			var toggle = this.openMenu.querySelector(this.toggleSelector);
			var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
			var menu   = menuId ? document.getElementById(menuId) : null;

			if (!toggle || !menu || menu.hidden) {
				return;
			}

			if (e.key === 'Escape') {
				e.preventDefault();
				this.closeMenu(this.openMenu, true);
				return;
			}

			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				var items = Array.prototype.slice.call(menu.querySelectorAll(this.itemSelector));
				if (!items.length) {
					return;
				}

				e.preventDefault();
				var activeIndex = items.indexOf(document.activeElement);
				if (activeIndex < 0) {
					items[0].focus();
					return;
				}

				var delta     = e.key === 'ArrowDown' ? 1 : -1;
				var nextIndex = (activeIndex + delta + items.length) % items.length;
				items[nextIndex].focus();
			}
		}
	};

	// -----------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------
	$(document).ready(function () {
		AIPS.ActionsMenu.init();
	});

})(jQuery);
