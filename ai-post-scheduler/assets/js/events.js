(function () {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * Return the core wp.hooks instance when the dependency is available.
	 *
	 * @return {Object|null} WordPress hooks instance or null when unavailable.
	 */
	function getHooks() {
		if (window.wp && window.wp.hooks) {
			return window.wp.hooks;
		}

		return null;
	}

	/**
	 * Check whether a hook currently has listeners registered.
	 *
	 * @param {string} method   The wp.hooks presence-check method to call.
	 * @param {string} hookName The hook name to inspect.
	 *
	 * @return {boolean} True when at least one listener exists.
	 */
	function hasHook(method, hookName) {
		var hooks = getHooks();

		if (!hooks || typeof hooks[method] !== 'function' || !hookName) {
			return false;
		}

		return !!hooks[method](hookName);
	}

	/**
	 * Thin proxy around wp.hooks so modules can publish and subscribe through
	 * one shared AIPS namespace without depending on WordPress internals.
	 */
	window.AIPS.Events = {
		/**
		 * Register an action callback.
		 *
		 * @param {string}   hookName  Action name.
		 * @param {string}   namespace Unique callback namespace.
		 * @param {Function} callback  Listener callback.
		 * @param {number}   priority  Optional execution priority.
		 */
		addAction: function (hookName, namespace, callback, priority) {
			var hooks = getHooks();

			if (!hooks || typeof hooks.addAction !== 'function') {
				return;
			}

			hooks.addAction(hookName, namespace, callback, priority);
		},

		/**
		 * Dispatch an action regardless of whether listeners are attached.
		 *
		 * @param {string} hookName Action name.
		 * @param {*}      payload  Payload object passed to listeners.
		 */
		doAction: function (hookName, payload) {
			var hooks = getHooks();

			if (!hooks || typeof hooks.doAction !== 'function') {
				return;
			}

			hooks.doAction(hookName, payload);
		},

		/**
		 * Dispatch an action only when listeners are present.
		 *
		 * @param {string} hookName Action name.
		 * @param {*}      payload  Payload object passed to listeners.
		 */
		emitAction: function (hookName, payload) {
			if (!this.hasAction(hookName)) {
				return;
			}

			this.doAction(hookName, payload);
		},

		/**
		 * Determine whether an action has listeners.
		 *
		 * @param {string} hookName Action name.
		 *
		 * @return {boolean} True when the action has listeners.
		 */
		hasAction: function (hookName) {
			return hasHook('hasAction', hookName);
		},

		/**
		 * Register a filter callback.
		 *
		 * @param {string}   hookName  Filter name.
		 * @param {string}   namespace Unique callback namespace.
		 * @param {Function} callback  Filter callback.
		 * @param {number}   priority  Optional execution priority.
		 */
		addFilter: function (hookName, namespace, callback, priority) {
			var hooks = getHooks();

			if (!hooks || typeof hooks.addFilter !== 'function') {
				return;
			}

			hooks.addFilter(hookName, namespace, callback, priority);
		},

		/**
		 * Apply registered filters to a value.
		 *
		 * @param {string} hookName Filter name.
		 * @param {*}      value    Value to filter.
		 * @param {*}      payload  Additional payload passed to callbacks.
		 *
		 * @return {*} Filtered value or the original value when hooks are absent.
		 */
		applyFilters: function (hookName, value, payload) {
			var hooks = getHooks();

			if (!hooks || typeof hooks.applyFilters !== 'function') {
				return value;
			}

			return hooks.applyFilters(hookName, value, payload);
		},

		/**
		 * Determine whether a filter has listeners.
		 *
		 * @param {string} hookName Filter name.
		 *
		 * @return {boolean} True when the filter has listeners.
		 */
		hasFilter: function (hookName) {
			return hasHook('hasFilter', hookName);
		}
	};
}());
