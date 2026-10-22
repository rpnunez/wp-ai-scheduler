/**
 * AIPS HTML Templates Engine
 *
 * Thin shim over WordPress core's `wp.template` (Underscore micro-templating,
 * bundled via the `wp-util` script handle) that preserves the existing
 * `AIPS.Templates` call signature used across 30+ admin scripts.
 *
 * Reads HTML from `<script type="text/html" id="...">` elements embedded in
 * admin pages. Both ID conventions are supported:
 *   - Legacy: the full element ID is passed as-is, e.g. `aips-tmpl-silo-card`.
 *   - Native: the bare `wp.template` ID is passed and resolved against
 *     `#tmpl-{id}`, e.g. `silo-card` resolves `#tmpl-silo-card`.
 *
 * Legacy bare tokens (`{{variable}}`, no surrounding whitespace, no dotted
 * path) are rewritten to `data.variable` references before compiling, so
 * existing templates keep working unmodified. Native `wp.template` syntax
 * (`{{ data.variable }}` auto-escaped, `{{{ data.variable }}}` raw,
 * `<# ... #>` evaluate blocks) is left untouched and available for new or
 * migrated templates.
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
	 * Compiled-template caches, keyed by the ID passed to render()/renderRaw().
	 * Escaped and raw variants are cached separately because the legacy
	 * bare-token rewrite differs between the two (see transformLegacyTokens).
	 *
	 * @type {Object<string, Function>}
	 */
	var _compiledEscaped = {};
	var _compiledRaw = {};

	/**
	 * Resolve the DOM element ID backing a given template ID, supporting
	 * both the legacy (full ID) and native wp.template (`tmpl-{id}`)
	 * conventions.
	 *
	 * @param {string} id - The template ID as passed by the caller.
	 * @return {string|null} The actual DOM element ID, or null if not found.
	 */
	function resolveElementId(id) {
		if (document.getElementById(id)) {
			return id;
		}

		if (document.getElementById('tmpl-' + id)) {
			return 'tmpl-' + id;
		}

		return null;
	}

	/**
	 * Rewrite legacy bare `{{key}}` tokens (no whitespace, no dotted path —
	 * the only form the old regex-based engine ever supported) into
	 * `wp.template`-compatible references against the `data` object.
	 *
	 * Native tokens (`{{{ ... }}}`, `{{ data.x }}`, `<# ... #>`) never match
	 * the bare-token pattern and pass through unchanged.
	 *
	 * @param {string}  source  - The raw template HTML.
	 * @param {boolean} rawMode - When true, bare tokens are rewritten to the
	 *                            unescaped `{{{ }}}` form (renderRaw());
	 *                            otherwise to the auto-escaped `{{ }}` form.
	 * @return {string} The rewritten template source.
	 */
	function transformLegacyTokens(source, rawMode) {
		return source.replace(/\{\{\{(\w+)\}\}\}|\{\{(\w+)\}\}/g, function (match, tripleKey, doubleKey) {
			if (tripleKey !== undefined) {
				return match;
			}

			return rawMode ? '{{{ data.' + doubleKey + ' }}}' : '{{ data.' + doubleKey + ' }}';
		});
	}

	/**
	 * Get (and cache) a compiled Underscore template function for the given
	 * template ID, compiled with the same evaluate/escape/interpolate
	 * settings `wp.template` itself uses.
	 *
	 * @param {string}  id      - The template ID.
	 * @param {boolean} rawMode - Whether bare legacy tokens should render raw.
	 * @return {Function|null} A `function(data)` returning rendered HTML, or null.
	 */
	function getCompiled(id, rawMode) {
		var cache = rawMode ? _compiledRaw : _compiledEscaped;

		if (cache[id]) {
			return cache[id];
		}

		var elementId = resolveElementId(id);
		if (!elementId) {
			return null;
		}

		var source = document.getElementById(elementId).innerHTML;
		source = transformLegacyTokens(source, rawMode);

		var options = (window.wp && window.wp.template && window.wp.template.options) || {
			evaluate: /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape: /\{\{([^\{][\s\S]+?)\}\}/g,
			variable: 'data',
		};

		cache[id] = window._.template(source, options);
		return cache[id];
	}

	/**
	 * @namespace AIPS.Templates
	 */
	AIPS.Templates = {

		/**
		 * Retrieve the raw HTML string for a template by its DOM element ID,
		 * accepting either the legacy full-ID or native `tmpl-{id}` convention.
		 *
		 * @param {string} id - The template ID.
		 * @return {string} The raw template string, or an empty string if not found.
		 */
		get: function (id) {
			var elementId = resolveElementId(id);
			return elementId ? document.getElementById(elementId).innerHTML : '';
		},

		/**
		 * Render a template, auto-escaping legacy bare `{{key}}` tokens and
		 * honoring native `wp.template` escape/raw/evaluate syntax.
		 *
		 * @param {string} id   - The template ID to render.
		 * @param {Object} data - Key-value map used to populate the template.
		 * @return {string} The rendered HTML string.
		 */
		render: function (id, data) {
			var compiled = getCompiled(id, false);
			if (!compiled) {
				return '';
			}

			try {
				return compiled(data || {});
			} catch (e) {
				return '';
			}
		},

		/**
		 * Render a template without HTML-escaping legacy bare `{{key}}`
		 * tokens. Native `{{{ ... }}}` raw tokens behave the same regardless
		 * of which render method is used.
		 *
		 * Use this variant only when values are already safe HTML (e.g. trusted
		 * server-rendered markup or output from `render()` itself).
		 *
		 * @param {string} id   - The template ID to render.
		 * @param {Object} data - Key-value map used to populate the template.
		 * @return {string} The rendered HTML string.
		 */
		renderRaw: function (id, data) {
			var compiled = getCompiled(id, true);
			if (!compiled) {
				return '';
			}

			try {
				return compiled(data || {});
			} catch (e) {
				return '';
			}
		},

		/**
		 * Whether a template with this ID exists on the page.
		 *
		 * @param {string} id - The template ID.
		 * @return {boolean}
		 */
		has: function (id) {
			return resolveElementId(id) !== null;
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
		 * Clear the in-memory compiled-template caches.
		 *
		 * Useful in tests or when templates are dynamically added to the DOM
		 * after initial page load.
		 *
		 * @return {void}
		 */
		clearCache: function () {
			_compiledEscaped = {};
			_compiledRaw = {};
		},
	};

})(jQuery);
