/**
 * AIPS HTTP client for the plugin REST API (`aips/v1`).
 *
 * Thin wrapper around `wp.apiFetch` exposing `AIPS.Http`. Every REST-backed
 * admin page should call the plugin API through this module rather than
 * `$.ajax` + `ajaxurl`, which remains only for the endpoints intentionally
 * kept on admin-ajax.
 *
 * Contract:
 *   - Resolves with the response payload directly (no `{success, data}` envelope).
 *   - Rejects with a normalized error: `{ code, message, status, data }`.
 *   - Returns native Promises — use `.then()/.catch()`, not `.done()/.fail()`.
 *
 * Authentication is cookie-based; the `X-WP-Nonce` header (action `wp_rest`)
 * is attached automatically by the apiFetch nonce middleware core installs
 * when `wp-api-fetch` is enqueued, and by hand in the jQuery fallback.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    var settings = window.aipsRest || {};

    /**
     * Join the API root and a resource path without duplicating slashes.
     *
     * @param {string} path Resource path relative to the namespace, e.g. 'templates/12'.
     * @return {string}
     */
    function buildPath(path) {
        var root = (settings.root || '').replace(/\/+$/, '');
        var rel  = String(path || '').replace(/^\/+/, '');
        return root + '/' + rel;
    }

    /**
     * Append a query object to a URL/path.
     *
     * @param {string} path
     * @param {Object|undefined} query
     * @return {string}
     */
    function withQuery(path, query) {
        if (!query || typeof query !== 'object') {
            return path;
        }
        var qs = $.param(query);
        if (!qs) {
            return path;
        }
        return path + (path.indexOf('?') === -1 ? '?' : '&') + qs;
    }

    /**
     * Normalize any rejection (apiFetch error object, jqXHR, or thrown Error)
     * into `{ code, message, status, data }`.
     *
     * @param {*} err
     * @return {{code: string, message: string, status: number, data: Object}}
     */
    function normalizeError(err) {
        var fallbackMessage = (window.aipsAdminL10n && window.aipsAdminL10n.errorOccurred) || 'An error occurred.';

        // jQuery XHR fallback path.
        if (err && typeof err.getResponseHeader === 'function') {
            var body = err.responseJSON || null;
            if (!body && err.responseText) {
                try { body = JSON.parse(err.responseText); } catch (e) { body = null; }
            }
            return {
                code:    (body && body.code) || 'aips_http_error',
                message: (body && body.message) || err.statusText || fallbackMessage,
                status:  err.status || 0,
                data:    (body && body.data) || {}
            };
        }

        // apiFetch error object: { code, message, data: { status } }.
        if (err && typeof err === 'object') {
            var data = err.data && typeof err.data === 'object' ? err.data : {};
            return {
                code:    err.code || 'aips_http_error',
                message: err.message || fallbackMessage,
                status:  typeof data.status === 'number' ? data.status : 0,
                data:    data
            };
        }

        return { code: 'aips_http_error', message: fallbackMessage, status: 0, data: {} };
    }

    window.AIPS.Http = {

        /**
         * Perform a request against the plugin REST API.
         *
         * @param {string} path              Resource path relative to `aips/v1`, e.g. 'templates/12'.
         * @param {Object} [options]
         * @param {string} [options.method]  HTTP method. Default 'GET'.
         * @param {Object} [options.data]    JSON body for non-GET requests.
         * @param {Object} [options.query]   Query-string parameters.
         * @param {Object} [options.headers] Extra request headers.
         * @return {Promise<*>}
         */
        request: function(path, options) {
            options = options || {};
            var method  = (options.method || 'GET').toUpperCase();
            var url     = withQuery(buildPath(path), options.query);
            var headers = $.extend({}, options.headers || {});
            var body    = method === 'GET' ? undefined : (options.data || {});

            if (window.wp && window.wp.apiFetch) {
                return window.wp.apiFetch({
                    path:    url,
                    method:  method,
                    data:    body,
                    headers: headers,
                    parse:   true
                }).catch(function(err) {
                    return Promise.reject(normalizeError(err));
                });
            }

            // Fallback for pages where wp-api-fetch is unavailable.
            headers['X-WP-Nonce'] = settings.nonce || '';
            var ajaxOptions = {
                url:      settings.rootUrl ? settings.rootUrl.replace(/\/+$/, '') + '/' + url.replace(/^\/+/, '') : url,
                method:   method,
                headers:  headers,
                dataType: 'json'
            };
            if (body !== undefined) {
                ajaxOptions.contentType = 'application/json; charset=utf-8';
                ajaxOptions.data = JSON.stringify(body);
            }

            return new Promise(function(resolve, reject) {
                $.ajax(ajaxOptions)
                    .done(function(payload) { resolve(payload); })
                    .fail(function(jqXHR) { reject(normalizeError(jqXHR)); });
            });
        },

        /**
         * GET a resource or collection.
         *
         * @param {string} path
         * @param {Object} [query]
         * @return {Promise<*>}
         */
        get: function(path, query) {
            return this.request(path, { method: 'GET', query: query });
        },

        /**
         * POST (create, or invoke an action sub-route such as `/schedules/3/run`).
         *
         * @param {string} path
         * @param {Object} [data]
         * @return {Promise<*>}
         */
        post: function(path, data) {
            return this.request(path, { method: 'POST', data: data });
        },

        /**
         * PUT (full replace).
         *
         * @param {string} path
         * @param {Object} [data]
         * @return {Promise<*>}
         */
        put: function(path, data) {
            return this.request(path, { method: 'PUT', data: data });
        },

        /**
         * PATCH (partial update).
         *
         * @param {string} path
         * @param {Object} [data]
         * @return {Promise<*>}
         */
        patch: function(path, data) {
            return this.request(path, { method: 'PATCH', data: data });
        },

        /**
         * DELETE.
         *
         * @param {string} path
         * @param {Object} [data] Optional body (e.g. `{ force: true }`).
         * @return {Promise<*>}
         */
        delete: function(path, data) {
            return this.request(path, { method: 'DELETE', data: data });
        },

        /**
         * Surface a normalized error to the user via the shared toast helper.
         *
         * Convenience for the common `.catch(AIPS.Http.toastError)` tail.
         *
         * @param {{message: string}} err
         * @return {void}
         */
        toastError: function(err) {
            var normalized = normalizeError(err);
            if (window.AIPS.Utilities && typeof window.AIPS.Utilities.showToast === 'function') {
                window.AIPS.Utilities.showToast(normalized.message, 'error');
            }
        },

        /**
         * Exposed for unit tests and callers that receive raw errors.
         */
        normalizeError: normalizeError
    };

})(jQuery);
