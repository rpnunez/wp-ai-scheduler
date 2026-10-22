const fs = require('fs');
const path = require('path');

const SOURCE_PATH = path.join(__dirname, '..', '..', '..', 'assets', 'js', 'templates.js');
const SOURCE = fs.readFileSync(SOURCE_PATH, 'utf8');

/**
 * Evaluate templates.js fresh against the current jsdom `window`/`document`
 * and return the `AIPS.Templates` object it attaches.
 *
 * Re-reading and re-running the source (rather than requiring it once) gives
 * each test a clean `AIPS.Templates` instance with an empty compiled-template
 * cache, matching a fresh page load.
 *
 * @return {object} The `AIPS.Templates` namespace.
 */
function loadTemplates() {
	delete window.AIPS;
	// eslint-disable-next-line no-new-func
	new Function('jQuery', SOURCE)(window.jQuery);
	return window.AIPS.Templates;
}

/**
 * Register a `<script type="text/html" id="...">` element in the jsdom
 * document so `AIPS.Templates` can resolve it.
 *
 * @param {string} id   - The element ID (full legacy ID or bare `wp.template` ID's `tmpl-{id}` form).
 * @param {string} html - The raw template markup.
 */
function defineTemplate(id, html) {
	var el = document.createElement('script');
	el.type = 'text/html';
	el.id = id;
	el.innerHTML = html;
	document.body.appendChild(el);
}

module.exports = { loadTemplates, defineTemplate };
