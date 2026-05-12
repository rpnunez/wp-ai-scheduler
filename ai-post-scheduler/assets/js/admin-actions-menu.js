(function() {
	'use strict';

	var rootSelector = '.aips-row-actions-menu';
	var toggleSelector = '.aips-row-actions-toggle';
	var itemSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
	var openMenu = null;

	function closeMenu(menuWrapper, returnFocus) {
		if (!menuWrapper) {
			return;
		}

		var toggle = menuWrapper.querySelector(toggleSelector);
		var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
		var menu = menuId ? document.getElementById(menuId) : null;

		if (!toggle || !menu) {
			return;
		}

		toggle.setAttribute('aria-expanded', 'false');
		menu.hidden = true;
		openMenu = null;

		if (returnFocus) {
			toggle.focus();
		}
	}

	function openMenuFor(menuWrapper) {
		if (!menuWrapper) {
			return;
		}

		if (openMenu && openMenu !== menuWrapper) {
			closeMenu(openMenu, false);
		}

		var toggle = menuWrapper.querySelector(toggleSelector);
		var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
		var menu = menuId ? document.getElementById(menuId) : null;
		if (!toggle || !menu) {
			return;
		}

		toggle.setAttribute('aria-expanded', 'true');
		menu.hidden = false;
		openMenu = menuWrapper;
	}

	document.addEventListener('click', function(event) {
		var toggle = event.target.closest(toggleSelector);

		if (toggle) {
			event.preventDefault();
			var wrapper = toggle.closest(rootSelector);
			var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
			if (isExpanded) {
				closeMenu(wrapper, false);
			} else {
				openMenuFor(wrapper);
			}
			return;
		}

		if (openMenu && !event.target.closest(rootSelector)) {
			closeMenu(openMenu, false);
		}
	});

	document.addEventListener('keydown', function(event) {
		if (!openMenu) {
			return;
		}

		var toggle = openMenu.querySelector(toggleSelector);
		var menuId = toggle ? toggle.getAttribute('aria-controls') : '';
		var menu = menuId ? document.getElementById(menuId) : null;
		if (!toggle || !menu || menu.hidden) {
			return;
		}

		if (event.key === 'Escape') {
			event.preventDefault();
			closeMenu(openMenu, true);
			return;
		}

		if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			var items = Array.prototype.slice.call(menu.querySelectorAll(itemSelector));
			if (!items.length) {
				return;
			}

			event.preventDefault();
			var activeIndex = items.indexOf(document.activeElement);
			if (activeIndex < 0) {
				items[0].focus();
				return;
			}

			var delta = event.key === 'ArrowDown' ? 1 : -1;
			var nextIndex = (activeIndex + delta + items.length) % items.length;
			items[nextIndex].focus();
		}
	});
})();
