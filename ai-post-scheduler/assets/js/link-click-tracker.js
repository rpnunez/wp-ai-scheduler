/**
 * Internal link click beacon (frontend, loaded only when click tracking is on).
 *
 * Sends the source post ID and link URL for clicks on links tagged with
 * data-aips-src. Each link is counted at most once per browser session.
 * No cookies are set and nothing identifying the visitor is sent.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */
(function() {
	'use strict';

	var config = window.aipsLinkTracker || {};
	if (!config.endpoint || !window.URLSearchParams) {
		return;
	}

	function alreadySent(key) {
		try {
			if (window.sessionStorage.getItem(key)) {
				return true;
			}
			window.sessionStorage.setItem(key, '1');
		} catch (e) {
			// Storage unavailable (private mode): count every click.
		}
		return false;
	}

	function send(link) {
		var source = link.getAttribute('data-aips-src');
		var href = link.href;
		if (!source || !href || alreadySent('aips-lc:' + source + ':' + href)) {
			return;
		}

		var body = new URLSearchParams({ source: source, href: href });

		if (navigator.sendBeacon && navigator.sendBeacon(config.endpoint, body)) {
			return;
		}

		if (window.fetch) {
			window.fetch(config.endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'omit' }).catch(function() {});
		}
	}

	function onClick(event) {
		if (event.type === 'auxclick' && event.button !== 1) {
			return;
		}
		var link = event.target && event.target.closest ? event.target.closest('a[data-aips-src]') : null;
		if (link) {
			send(link);
		}
	}

	document.addEventListener('click', onClick, true);
	document.addEventListener('auxclick', onClick, true);
})();
