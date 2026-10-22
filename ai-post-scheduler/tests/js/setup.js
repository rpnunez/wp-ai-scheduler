/**
 * Jest setup for assets/js unit tests.
 *
 * Provides the globals `assets/js/*.js` files expect to find on `window`
 * when WordPress enqueues them in a real admin page: Underscore (bundled by
 * the `wp-util` script handle) and a jQuery stand-in. Files under test are
 * loaded with `require()` directly against `window.jQuery`, same as the
 * `(function ($) { ... })(jQuery)` wrapper they ship in.
 */

global._ = require('underscore');
window._ = global._;

// templates.js only uses the `$` parameter as the IIFE wrapper argument; it
// does not call into jQuery itself, so a no-op stands in for it here.
global.jQuery = function () {};
window.jQuery = global.jQuery;
