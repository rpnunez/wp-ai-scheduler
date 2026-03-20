/**
 * AIPS HTML Templates Engine
 *
 * Provides a lightweight client-side template system that reads HTML from
 * <script type="text/html"> elements embedded in admin pages, replaces
 * {{placeholder}} tokens with data values, and returns the rendered string.
 *
 * Usage:
 *   // In a PHP template (authors.php etc.):
 *   <script type="text/html" id="aips-tmpl-my-card">
 *     <div class="aips-my-card">
 *       <h4>{{name}}</h4>
 *       <p>{{description}}</p>
 *     </div>
 *   </script>
 *
 *   // In JS (authors.js etc.):
 *   var html = AIPS.Templates.render('aips-tmpl-my-card', { name: 'Alex', description: 'Bio...' });
 *   $('#container').html(html);
 *
 * @since 1.7.2
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * Template cache — avoids repeated DOM lookups for the same template ID.
	 *
	 * @type {Object<string, string>}
	 */
	var _cache = {};

	/**
	 * @namespace AIPS.Templates
	 */
	AIPS.Templates = {

		/**
		 * Retrieve the raw HTML string for a template by its DOM element ID.
		 *
		 * The template element must use `type="text/html"` so the browser does not
		 * parse or execute its contents. The raw innerHTML is cached after the first
		 * lookup for performance.
		 *
		 * @param {string} id - The element ID of the <script type="text/html"> block.
		 * @return {string} The raw template string, or an empty string if not found.
		 */
		get: function (id) {
			if (_cache[id] !== undefined) {
				return _cache[id];
			}

			var $el = $('#' + id);
			if (!$el.length) {
				return '';
			}

			_cache[id] = $el.html();
			return _cache[id];
		},

		/**
		 * Render a template by replacing `{{key}}` tokens with values from `data`.
		 *
		 * Tokens that do not have a matching key in `data` are replaced with an
		 * empty string to prevent stale placeholder text from appearing in the UI.
		 * All replacement values are HTML-escaped before insertion.
		 *
		 * @param {string} id   - The element ID of the template to render.
		 * @param {Object} data - Key-value map used to replace tokens.
		 * @return {string} The rendered HTML string.
		 */
		render: function (id, data) {
			var template = this.get(id);
			if (!template) {
				return '';
			}

			data = data || {};
			var escape = this.escape;

			// Replace every {{token}} in the template string.
			return template.replace(/\{\{(\w+)\}\}/g, function (match, key) {
				return Object.prototype.hasOwnProperty.call(data, key)
					? escape(String(data[key]))
					: '';
			});
		},

		/**
		 * Render a template without HTML-escaping the replacement values.
		 *
		 * Use this variant only when values are already safe HTML (e.g. trusted
		 * server-rendered markup or output from `render()` itself).
		 *
		 * @param {string} id   - The element ID of the template to render.
		 * @param {Object} data - Key-value map used to replace tokens.
		 * @return {string} The rendered HTML string.
		 */
		renderRaw: function (id, data) {
			var template = this.get(id);
			if (!template) {
				return '';
			}

			data = data || {};

			return template.replace(/\{\{(\w+)\}\}/g, function (match, key) {
				return Object.prototype.hasOwnProperty.call(data, key)
					? String(data[key])
					: '';
			});
		},

		/**
		 * HTML-escape a string so it is safe to insert into the DOM.
		 *
		 * @param {string} str - The raw string to escape.
		 * @return {string} The escaped string.
		 */
		escape: function (str) {
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		},

		/**
		 * Clear the in-memory template cache.
		 *
		 * Useful in tests or when templates are dynamically added to the DOM
		 * after initial page load.
		 *
		 * @return {void}
		 */
		clearCache: function () {
			_cache = {};
		},
	};

})(jQuery);
