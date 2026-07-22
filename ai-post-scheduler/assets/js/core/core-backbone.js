/**
 * AIPS Core Backbone — thin data-sync layer over admin-ajax.php.
 *
 * Backbone's default `Backbone.sync` assumes REST `url`/`urlRoot` conventions.
 * This plugin has a single endpoint (admin-ajax.php) keyed by an `action` name,
 * with `{success, data}` JSON responses (see AIPS_Ajax_Response). This module
 * provides `AIPS.Core.Model`/`AIPS.Core.Collection`/`AIPS.Core.View` base
 * classes whose `sync` is wired to `AIPS.Core.Http.ajaxRequest()` instead —
 * global `Backbone.sync` is intentionally left untouched, since WP core's own
 * `wp-backbone` handle installs a different sync override for `wp.media` and
 * similar core UI on some admin screens; overriding the global would create
 * an invisible, page-dependent collision.
 *
 * A Model/Collection declares which `wp_ajax_*` action backs each CRUD verb
 * via `ajaxActions` (shaped like AIPS_Ajax_Registry's action-name-per-key
 * map), and optionally `ajaxNonces` for pages using per-operation nonces
 * instead of the shared `aipsAjax.nonce` (e.g. Cache Monitor's read/action
 * nonce split). `url`/`urlRoot` are never read — this is deliberate, not an
 * oversight; a Model/Collection that sets them will have them silently
 * ignored, since this plugin has no REST routes to point them at.
 *
 * Usage:
 *   AIPS.CacheMonitor.EntryCollection = AIPS.Core.Collection.extend({
 *       resultsKey: 'rows',
 *       ajaxActions: { read: 'aips_cache_monitor_entries' },
 *       ajaxNonces:  { read: function () { return READ_NONCE; } }
 *   });
 *   collection.fetch({ data: { page: 1 }, reset: true });
 *
 * @since 3.3.0
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Core = AIPS.Core || {};

	/**
	 * Shared `sync` implementation for AIPS.Core.Model/Collection.
	 *
	 * @param {string} method  Backbone CRUD verb: 'create'|'read'|'update'|'patch'|'delete'.
	 * @param {Backbone.Model|Backbone.Collection} target
	 * @param {Object} options Backbone's sync options — `data`, `success(resp)`,
	 *                         `error(resp)` (both single-argument; Backbone
	 *                         pre-wraps these before calling sync(), unlike the
	 *                         (data, response) shape AIPS.Core.Http callbacks
	 *                         use everywhere else in this codebase), plus any
	 *                         AIPS.Core.Http.ajaxRequest option (`$button`,
	 *                         `toastOnError`, `errorFallback`).
	 * @return {jqXHR}
	 */
	function aipsBackboneSync(method, target, options) {
		options = options || {};
		var isModel = target instanceof Backbone.Model;

		var actions = target.ajaxActions ||
			(isModel && target.collection && target.collection.ajaxActions) || {};
		var action = actions[method];
		if (!action) {
			throw new Error(
				'AIPS.Core.Sync: no ajaxActions["' + method + '"] declared on this ' +
				(isModel ? 'Model' : 'Collection') + ' (or its .collection). ' +
				'Backbone\'s default url/urlRoot convention is not used here — declare ajaxActions instead.'
			);
		}

		var nonceCfg = target.ajaxNonces ||
			(isModel && target.collection && target.collection.ajaxNonces) || {};
		var nonce = typeof nonceCfg[method] === 'function' ? nonceCfg[method]() : nonceCfg[method];

		var data = $.extend({}, options.data || {});

		if (isModel && (method === 'update' || method === 'patch' || method === 'delete')) {
			var idParam = target.idParam || target.idAttribute || 'id';
			if (target.id !== undefined) {
				data[idParam] = target.id;
			}
		}
		if (isModel && (method === 'create' || method === 'update' || method === 'patch')) {
			$.extend(data, target.toJSON());
		}

		return AIPS.Core.Http.ajaxRequest({
			action: action,
			nonce: nonce,
			data: data,
			toastOnError: options.toastOnError !== false,
			errorFallback: options.errorFallback,
			$button: options.$button,
			onSuccess: function (unwrappedData /*, response */) {
				// Backbone's Model.fetch/save/destroy pre-wrap options.success into a
				// single-argument function(resp) before calling sync() — resp feeds
				// directly into model.parse()/collection.parse(). Do not pass a
				// second argument here.
				if (typeof options.success === 'function') {
					options.success(unwrappedData);
				}
			},
			onError: function (message, response /*, isTransportError */) {
				// Same constraint as above: Backbone's wrapError expects a single
				// `resp` argument. `response` is the parsed AIPS_Ajax_Response error
				// body ({success:false, data:{message, code}}), not a raw jqXHR.
				if (typeof options.error === 'function') {
					options.error(response);
				}
			}
		});
	}

	/**
	 * @class AIPS.Core.Model
	 * @augments Backbone.Model
	 */
	AIPS.Core.Model = Backbone.Model.extend({
		sync: function (method, model, options) {
			return aipsBackboneSync(method, model, options);
		}
	});

	/**
	 * @class AIPS.Core.Collection
	 * @augments Backbone.Collection
	 */
	AIPS.Core.Collection = Backbone.Collection.extend({
		sync: function (method, collection, options) {
			return aipsBackboneSync(method, collection, options);
		},

		// AIPS_Ajax_Response list payloads are shaped {rows: [...], total, total_pages, page}
		// by convention in this codebase; override per Collection where the key differs.
		resultsKey: 'rows',

		parse: function (response) {
			if ($.isArray(response)) {
				return response;
			}
			this.total = response.total;
			this.totalPages = response.total_pages;
			this.page = response.page;
			return response[this.resultsKey] || [];
		}
	});

	/**
	 * @class AIPS.Core.View
	 * @augments Backbone.View
	 *
	 * Deliberately thin: there is no generic render() here, because
	 * AIPS.Templates has no schema beyond a DOM template id, and different
	 * templates need different derived fields per row. Composes
	 * AIPS.Templates — never replaces it.
	 */
	AIPS.Core.View = Backbone.View.extend({
		templateId: null,

		/**
		 * Render a single model (or plain data object) through the named
		 * AIPS.Templates template. Uses the auto-escaping render() by default
		 * (matching this codebase's default templating convention — renderRaw()
		 * is only for pre-trusted/composed HTML); pass rawTemplate: true on the
		 * view if the template legitimately composes already-safe sub-HTML.
		 *
		 * @param {Backbone.Model|Object} dataOrModel
		 * @return {string} HTML.
		 */
		renderModel: function (dataOrModel) {
			var data = (dataOrModel && typeof dataOrModel.toJSON === 'function')
				? dataOrModel.toJSON() : dataOrModel;
			return this.rawTemplate
				? AIPS.Templates.renderRaw(this.templateId, data)
				: AIPS.Templates.render(this.templateId, data);
		}
	});

})(jQuery);
