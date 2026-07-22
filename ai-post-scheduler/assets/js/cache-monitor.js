/* global AIPS, aipsAjax, jQuery */
/**
 * Cache Monitor admin page JS.
 *
 * Responsibilities:
 *  - Tab switching (hash-based, no page reload)
 *  - Entries table: load, search/filter, paginate, inspect, delete, bulk delete
 *  - Operations table: load on demand
 *  - Events log: load on demand, paginate
 *  - Maintenance action buttons: AJAX dispatch, diagnostics download
 *  - Global flush helpers (flush expired, flush group, flush all)
 *  - Tag / domain invalidation
 *
 * Dependencies: jQuery, AIPS.Utilities, AIPS.Templates (aipsAjax global localized by AIPS_Admin_Assets)
 *
 * @since 2.9.0
 */
(function ( $ ) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var ajaxUrl   = aipsAjax.ajaxUrl;

	// Nonces are embedded as data attributes on action buttons/elements
	// and can also be read from the page-level JS vars added by the controller's render_page().
	var READ_NONCE   = ( window.aipsCacheMonitor && window.aipsCacheMonitor.nonce )       || '';
	var ACTION_NONCE = ( window.aipsCacheMonitor && window.aipsCacheMonitor.actionNonce ) || '';

	// -----------------------------------------------------------------------
	// Utility helpers
	// -----------------------------------------------------------------------

	/**
	 * Format bytes to human-readable string.
	 *
	 * @param {number} bytes
	 * @returns {string}
	 */
	function formatBytes( bytes ) {
		bytes = parseInt( bytes, 10 ) || 0;
		if ( bytes < 1024 )    { return bytes + ' B'; }
		if ( bytes < 1048576 ) { return ( bytes / 1024 ).toFixed( 1 ) + ' KB'; }
		return ( bytes / 1048576 ).toFixed( 2 ) + ' MB';
	}

	/**
	 * Format Unix timestamp to localised date-time string.
	 *
	 * @param {number|string} ts
	 * @returns {string}
	 */
	function formatTs( ts ) {
		ts = parseInt( ts, 10 );
		if ( ! ts ) { return aipsCacheMonitor.i18n.never || 'Never'; }
		return new Date( ts * 1000 ).toLocaleString();
	}

	/**
	 * Escape HTML for inline insertion.
	 *
	 * @param {*} str
	 * @returns {string}
	 */
	function esc( str ) {
		return $( '<span>' ).text( String( str ) ).html();
	}

	/**
	 * Build a safe attribute value (single-quoted not needed because we always wrap in double-quotes).
	 *
	 * @param {*} str
	 * @returns {string}
	 */
	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	AIPS.CacheMonitor = {
		entriesState: null,
		eventsPage: 1,
		entriesCollection: null,
		entriesView: null,

		init: function() {
			if ( ! this.entriesState ) {
				this.entriesState = {
					page:    1,
					perPage: 50,
					filters: {},
					orderby: 'updated_at',
					order:   'DESC'
				};
			}

			this.bindEvents();
			if ( $( '#aips-cache-entries-tbody' ).length ) {
				this.entriesCollection = new AIPS.CacheMonitor.EntryCollection();
				this.entriesView = new AIPS.CacheMonitor.EntriesView( { collection: this.entriesCollection } );
				this.loadEntries();
			}
		},

		bindEvents: function() {
			var self = this;
	// -----------------------------------------------------------------------
	// Refresh button
	// -----------------------------------------------------------------------

		$( '.aips-cache-monitor-refresh' ).on( 'click', function () {
		self.loadEntries();
		} );

	// -----------------------------------------------------------------------
	// Flush expired
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-flush-expired', function () {
		var $btn = $( this );
		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_flush_expired',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE,
			$button: $btn,
			errorFallback: aipsCacheMonitor.i18n.requestFailed || 'Request failed.',
			onSuccess: function ( data ) {
				AIPS.Utilities.showToast( data.message, 'success' );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Flush All (requires confirmation)
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-flush-all-btn', function () {
		var actionNonce = $( this ).data( 'nonce' ) || ACTION_NONCE;
		var confirmMsg  = aipsCacheMonitor.i18n.confirmFlushAll || 'This will flush ALL plugin-owned cache. Are you sure?';

		AIPS.Utilities.confirm(
			confirmMsg,
			aipsCacheMonitor.i18n.flushAllTitle || 'Flush All Plugin Cache',
			[ {
				label:     aipsCacheMonitor.i18n.confirmBtn || 'Confirm Flush',
				className: 'aips-btn-danger',
				action:    function () {
					AIPS.Core.Http.ajaxRequest( {
						action: 'aips_cache_monitor_flush_all',
						nonce:  actionNonce,
						data:   { confirmed: 1 },
						onSuccess: function ( data ) {
							AIPS.Utilities.showToast( data.message, 'success' );
						}
					} );
				}
			} ]
		);
	} );

	// -----------------------------------------------------------------------
	// Flush Group
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-flush-group', function () {
		var $btn        = $( this );
		var group       = $btn.data( 'group' );
		var actionNonce = $btn.data( 'nonce' ) || ACTION_NONCE;
		var confirmMsg  = $btn.data( 'confirm' ) || ( 'Flush group "' + group + '"?' );

		AIPS.Utilities.confirm(
			confirmMsg,
			aipsCacheMonitor.i18n.flushGroupTitle || 'Flush Cache Group',
			[ {
				label:     aipsCacheMonitor.i18n.flushGroupBtn || 'Flush Group',
				className: 'aips-btn-danger',
				action:    function () {
					AIPS.Core.Http.ajaxRequest( {
						action: 'aips_cache_monitor_flush_group',
						nonce:  actionNonce,
						data:   { cache_group: group },
						onSuccess: function ( data ) {
							AIPS.Utilities.showToast( data.message, 'success' );
							setTimeout( function () { location.reload(); }, 1200 );
						}
					} );
				}
			} ]
		);
	} );

	// -----------------------------------------------------------------------
	// Invalidate tag
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-invalidate-tag', function () {
		var $btn = $( this );
		var tag  = $btn.data( 'tag' );

		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_invalidate_tag',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE,
			data:   { tag: tag },
			onSuccess: function ( data ) {
				AIPS.Utilities.showToast( data.message, 'success' );
				$btn.closest( 'tr' ).find( '.aips-badge' ).text( 'v' + data.new_version );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Invalidate domain
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-invalidate-domain', function () {
		var $btn   = $( this );
		var domain = $btn.data( 'domain' );

		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_invalidate_domain',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE,
			data:   { domain: domain },
			onSuccess: function ( data ) {
				AIPS.Utilities.showToast( data.message, 'success' );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Entries tab
	// -----------------------------------------------------------------------

		$( '#aips-cache-entries-search-btn' ).on( 'click', function () {
		self.entriesState.filters.search    = $( '#aips-cache-search' ).val();
		self.entriesState.filters.group     = $( '#aips-cache-filter-group' ).val();
		self.entriesState.filters.tier      = $( '#aips-cache-filter-tier' ).val();
		self.entriesState.filters.ttl_state = $( '#aips-cache-filter-ttl' ).val();
		self.entriesState.page              = 1;
		self.loadEntries();
		} );

	// Enter key triggers search.
		$( '#aips-cache-search' ).on( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) { $( '#aips-cache-entries-search-btn' ).trigger( 'click' ); }
		} );

		$( document ).on( 'click', '.aips-entries-prev', function () { self.entriesState.page--; self.loadEntries(); } );
		$( document ).on( 'click', '.aips-entries-next', function () { self.entriesState.page++; self.loadEntries(); } );

		$( '#aips-cache-per-page' ).on( 'change', function () {
		self.entriesState.perPage = parseInt( $( this ).val(), 10 );
		self.entriesState.page    = 1;
		self.loadEntries();
		} );

	// Select all checkbox.
		$( '#aips-cache-select-all' ).on( 'change', function () {
		$( '.aips-cache-entry-cb' ).prop( 'checked', $( this ).is( ':checked' ) );
		} );

	// -----------------------------------------------------------------------
	// Inspect entry modal
	// -----------------------------------------------------------------------

		$( document ).on( 'click', '.aips-cache-inspect-link, .aips-cache-inspect-btn', function ( e ) {
		e.preventDefault();
		var hash = $( this ).data( 'hash' );

		$( '#aips-cache-inspect-modal' ).show();
		$( '#aips-cache-inspect-body' ).html( '<p>' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</p>' );

		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_inspect',
			nonce:  READ_NONCE,
			data:   { key_hash: hash },
			toastOnError: false,
			onError: function ( message ) {
				$( '#aips-cache-inspect-body' ).html( '<p>' + esc( message ) + '</p>' );
			},
			onSuccess: function ( d ) {
			var expiresFmt  = d.expires_at > 0 ? formatTs( d.expires_at ) : ( aipsCacheMonitor.i18n.never || 'Never' );
			var ttlRemFmt   = d.ttl_remaining !== null && d.ttl_remaining !== undefined ? d.ttl_remaining + 's' : 'N/A';

			var html = '<dl class="aips-dl">';
			html += '<dt>Key Hash</dt><dd><code>' + esc( d.key_hash ) + '</code></dd>';
			html += '<dt>Group</dt><dd>' + esc( d.cache_group ) + '</dd>';
			html += '<dt>Driver</dt><dd>' + esc( d.driver ) + '</dd>';
			html += '<dt>Tier</dt><dd>' + esc( d.tier ) + '</dd>';
			html += '<dt>Operation</dt><dd>' + esc( d.operation_id ) + '</dd>';
			html += '<dt>Tags</dt><dd>' + esc( d.tags ) + '</dd>';
			html += '<dt>TTL</dt><dd>' + esc( d.ttl ) + 's</dd>';
			html += '<dt>Expires</dt><dd>' + esc( expiresFmt ) + '</dd>';
			html += '<dt>TTL Remaining</dt><dd>' + esc( ttlRemFmt ) + '</dd>';
			html += '<dt>Value Type</dt><dd>' + esc( d.value_type ) + '</dd>';
			html += '<dt>Value Size</dt><dd>' + formatBytes( d.value_size ) + '</dd>';
			html += '</dl>';

			if ( d.preview !== null && d.preview !== undefined ) {
				html += '<h4>' + esc( aipsCacheMonitor.i18n.preview || 'Preview' ) + '</h4>';
				if ( d.preview_note ) {
					html += '<p style="font-style:italic;margin-bottom:6px;">' + esc( d.preview_note ) + '</p>';
				}
				html += '<pre style="max-height:400px;overflow:auto;background:#f9f9f9;padding:10px;border:1px solid #ddd;">' + esc( JSON.stringify( d.preview, null, 2 ) ) + '</pre>';
			}

			$( '#aips-cache-inspect-body' ).html( html );
			}
		} );
	} );

		$( document ).on( 'click', '.aips-modal-close', function () {
		$( this ).closest( '.aips-modal' ).hide();
		} );

	// Close modal on backdrop click.
		$( document ).on( 'click', '.aips-modal', function ( e ) {
		if ( $( e.target ).hasClass( 'aips-modal' ) ) {
			$( this ).hide();
		}
		} );

	// -----------------------------------------------------------------------
	// Delete single entry — handled by AIPS.CacheMonitor.EntriesView's own
	// delegated 'click .aips-cache-delete-link' event (see onDeleteClick()),
	// scoped to #aips-cache-entries-tbody. No document-level handler needed.
	// -----------------------------------------------------------------------

	// -----------------------------------------------------------------------
	// Bulk delete
	// -----------------------------------------------------------------------

		$( '#aips-cache-bulk-apply' ).on( 'click', function () {
		var action = $( '#aips-cache-bulk-action' ).val();
		if ( action !== 'delete' ) { return; }

		var hashes = [];
		$( '.aips-cache-entry-cb:checked' ).each( function () {
			hashes.push( $( this ).val() );
			} );

		if ( ! hashes.length ) {
			AIPS.Utilities.showToast( aipsCacheMonitor.i18n.noneSelected || 'No entries selected.', 'warning' );
			return;
		}

		AIPS.Core.Bulk.dispatch( {
			action:   'aips_cache_monitor_delete_bulk',
			ids:      hashes,
			idsField: 'key_hashes',
			nonce:    $( this ).data( 'nonce' ) || ACTION_NONCE,
			onSuccess: function ( data ) {
				AIPS.Utilities.showToast( data.message, 'success' );
				// No re-fetch: remove the deleted models from the collection directly.
				// This fires 'remove' on the collection, which the view is already
				// listening to, so it re-renders itself.
				self.entriesCollection.remove( self.entriesCollection.filter( function ( model ) {
					return hashes.indexOf( model.id ) !== -1;
				} ) );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Operations tab
	// -----------------------------------------------------------------------

		$( '#aips-ops-search-btn' ).on( 'click', function () {
		var opsNonce = $( this ).data( 'nonce' ) || READ_NONCE;

		$( '#aips-ops-tbody' ).html(
			'<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
		);

		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_operations',
			nonce:  opsNonce,
			data: {
				repository_class: $( '#aips-ops-filter-repo' ).val(),
				tier:             $( '#aips-ops-filter-tier' ).val()
			},
			onSuccess: function ( data ) {
				var ops  = data.operations || [];
				var html = '';

				$.each( ops, function ( i, op ) {
					html += '<tr>';
					html += '<td><code>' + esc( op.operation_id ) + '</code></td>';
					html += '<td><small>' + esc( op.repository_class ) + '</small></td>';
					html += '<td>' + esc( op.tier ) + '</td>';
					html += '<td>' + esc( op.index_count ) + '</td>';
					html += '<td>' + formatBytes( op.total_size ) + '</td>';
					html += '<td>' + formatTs( op.last_updated ) + '</td>';
					html += '</tr>';
					} );

				if ( ! html ) {
					html = '<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.noOps || 'No operations found.' ) + '</td></tr>';
				}
				$( '#aips-ops-tbody' ).html( html );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Events tab
	// -----------------------------------------------------------------------

		$( '#aips-events-load-btn' ).on( 'click', function () {
		self.eventsPage = 1;
		self.loadEvents();
		} );

	// -----------------------------------------------------------------------
	// Maintenance tab
	// -----------------------------------------------------------------------

		$( '.aips-maintenance-action-btn' ).on( 'click', function () {
		var $btn        = $( this );
		var action      = $btn.data( 'action' );
		var actionNonce = $btn.data( 'nonce' ) || ACTION_NONCE;
		var $result     = $( '#aips-maintenance-result' );

		AIPS.Core.Http.ajaxRequest( {
			action: 'aips_cache_monitor_maintenance',
			nonce:  actionNonce,
			data:   { maintenance_action: action },
			$button: $btn,
			toastOnError: false,
			errorFallback: aipsCacheMonitor.i18n.requestFailed || 'Request failed.',
			onSuccess: function ( data ) {
				// Export: trigger file download.
				if ( action === 'export_diagnostics' ) {
					try {
						var blob = new Blob( [ JSON.stringify( data.diagnostics, null, 2 ) ], { type: 'application/json' } );
						var url  = URL.createObjectURL( blob );
						var a    = document.createElement( 'a' );
						a.href     = url;
						a.download = 'aips-cache-diagnostics-' + Date.now() + '.json';
						document.body.appendChild( a );
						a.click();
						document.body.removeChild( a );
						URL.revokeObjectURL( url );
					} catch ( err ) {
						AIPS.Utilities.showToast( 'Export failed: ' + err.message, 'error' );
					}
					return;
				}

				$result.show().html( '<div class="notice notice-success inline"><p>' + esc( data.message ) + '</p></div>' );
				AIPS.Utilities.showToast( data.message, 'success' );
			},
			onError: function ( message ) {
				$result.show().html( '<div class="notice notice-error inline"><p>' + esc( message ) + '</p></div>' );
				AIPS.Utilities.showToast( message, 'error' );
			}
		} );
		} );
		},

		loadEntries: function() {
			var self = this;
			var params = $.extend( {}, self.entriesState.filters, {
				page:     self.entriesState.page,
				per_page: self.entriesState.perPage,
				orderby:  self.entriesState.orderby,
				order:    self.entriesState.order
			} );

			self.entriesView.$el.html(
				'<tr><td colspan="10">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
			);

			self.entriesCollection.fetch( {
				data: params,
				reset: true, // Replace, don't merge-by-id — page 2 must fully replace page 1.
				success: function ( collection ) {
					self.renderEntriesPagination( collection );
				}
			} );
		},

		renderEntriesPagination: function ( collection ) {
			var totalPages  = collection.totalPages || 1;
			var currentPage = collection.page        || 1;
			var pagHtml     = '';

			if ( totalPages > 1 ) {
				pagHtml = '<span class="aips-pag-info">' + esc( 'Page ' + currentPage + ' / ' + totalPages + ' (' + collection.total + ' total)' ) + '</span> ';
				if ( currentPage > 1 ) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-prev">&laquo; ' + esc( aipsCacheMonitor.i18n.prev || 'Prev' ) + '</button> ';
				}
				if ( currentPage < totalPages ) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-next">' + esc( aipsCacheMonitor.i18n.next || 'Next' ) + ' &raquo;</button>';
				}
			}
			$( '#aips-cache-entries-pagination' ).html( pagHtml );
		},

		loadEvents: function() {
			var self = this;
			var params = {
				event_type: $( '#aips-events-filter-type' ).val(),
				page:       self.eventsPage,
				per_page:   50
			};

			$( '#aips-events-tbody' ).html(
				'<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
			);

			AIPS.Core.Http.ajaxRequest( {
				action: 'aips_cache_monitor_events',
				nonce:  READ_NONCE,
				data:   params,
				onSuccess: function ( data ) {

				var rows = data.rows || [];
				var html = '';

				$.each( rows, function ( i, ev ) {
					html += '<tr>';
					html += '<td>' + esc( formatTs( ev.created_at ) ) + '</td>';
					html += '<td><code>' + esc( ev.event_type ) + '</code></td>';
					html += '<td>' + esc( ev.cache_group ) + '</td>';
					html += '<td>' + esc( ev.affected_count ) + '</td>';
					html += '<td>' + esc( ev.user_id ) + '</td>';
					html += '<td>' + esc( ev.message ) + '</td>';
					html += '</tr>';
				} );

				if ( ! html ) {
					html = '<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.noEvents || 'No events found.' ) + '</td></tr>';
				}
				$( '#aips-events-tbody' ).html( html );
				}
			} );
		}
	};

	// -----------------------------------------------------------------------
	// Entries tab: Backbone Model/Collection/View (pilot — see
	// assets/js/core/core-backbone.js for the sync adapter these build on).
	// Entries are written by the cache layer itself, never created/edited via
	// this UI, so only 'read' and 'delete' are configured; calling
	// model.save() would throw (no 'create'/'update' action declared), which
	// is intentional — a clear signal rather than a silent no-op.
	// -----------------------------------------------------------------------

	AIPS.CacheMonitor.EntryModel = AIPS.Core.Model.extend( {
		idAttribute: 'key_hash',
		ajaxActions: { delete: 'aips_cache_monitor_delete_entry' },
		ajaxNonces:  { delete: function () { return ACTION_NONCE; } }
	} );

	AIPS.CacheMonitor.EntryCollection = AIPS.Core.Collection.extend( {
		model: AIPS.CacheMonitor.EntryModel,
		resultsKey: 'rows',
		ajaxActions: { read: 'aips_cache_monitor_entries' },
		ajaxNonces:  { read: function () { return READ_NONCE; } }
	} );

	AIPS.CacheMonitor.EntriesView = AIPS.Core.View.extend( {
		el: '#aips-cache-entries-tbody',
		templateId: 'aips-tmpl-cache-entry-row',

		events: {
			'click .aips-cache-delete-link': 'onDeleteClick'
		},

		initialize: function () {
			this.listenTo( this.collection, 'sync remove', this.render );
		},

		render: function () {
			if ( ! this.collection.length ) {
				this.$el.html(
					'<tr><td colspan="10">' + esc( aipsCacheMonitor.i18n.noEntries || 'No entries found.' ) + '</td></tr>'
				);
				return this;
			}

			var html = '';
			this.collection.each( function ( model ) {
				var data = model.toJSON();
				data.expires_fmt        = data.expires_at > 0 ? formatTs( data.expires_at ) : ( aipsCacheMonitor.i18n.never || 'Never' );
				data.key_hash_short     = data.key_hash.substring( 0, 12 ) + '…';
				data.value_size_fmt     = formatBytes( data.value_size );
				data.row_opacity_style  = data.is_expired ? 'opacity:0.55;' : '';
				data.inspect_label      = aipsCacheMonitor.i18n.inspect || 'Inspect';
				data.delete_label       = aipsCacheMonitor.i18n.delete || 'Delete';
				html += this.renderModel( data );
			}, this );
			this.$el.html( html );
			return this;
		},

		onDeleteClick: function ( e ) {
			e.preventDefault();
			var hash  = $( e.currentTarget ).data( 'hash' );
			var model = this.collection.get( hash );
			if ( ! model ) { return; }

			model.destroy( {
				wait: true,
				// Backbone.Model#destroy calls options.success as (model, response,
				// options) — NOT the single-argument (response) shape the sync
				// adapter's own internal callback uses. `response` here is the
				// unwrapped AIPS_Ajax_Response data ({message, affected}).
				success: function ( destroyedModel, response ) {
					AIPS.Utilities.showToast( response.message, 'success' );
				}
				// No manual .fadeOut().remove() — Backbone removes the model on
				// success, which fires 'remove' on the collection, which re-renders.
			} );
		}
	} );

	$( function () {
		AIPS.CacheMonitor.init();
	} );

} )( jQuery );
