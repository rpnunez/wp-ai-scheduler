// Basic HTMX init: add WordPress nonce header to every HTMX request.
// AIPS_HTMX.ajax_url and AIPS_HTMX.nonce are localized from PHP.
( function () {
	'use strict';

	// If htmx isn't loaded yet, wait for it.
	function initWhenReady() {
		if ( typeof htmx === 'undefined' ) {
			return setTimeout( initWhenReady, 50 );
		}

		// Set a default header for nonce verification. WordPress provides nonce via localization.
		htmx.defineExtension( 'aips-wp-nonce', {
			onEvent: function (name, evt) {
				// no-op for events we don't use
			},
			onBeforeRequest: function (evt) {
				var headers = evt.detail.headers || {};
				if ( typeof AIPS_HTMX !== 'undefined' && AIPS_HTMX.nonce ) {
					headers['X-WP-Nonce'] = AIPS_HTMX.nonce;
				}
				evt.detail.headers = headers;
			}
		} );

		// Enable the extension globally.
		htmx.addExtension( 'aips-wp-nonce' );
	}

	initWhenReady();
} )();
