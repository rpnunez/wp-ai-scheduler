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

	// -----------------------------------------------------------------------
	// Tab switching
	// -----------------------------------------------------------------------

	$( '.aips-tab-link' ).on( 'click', function ( e ) {
		// Allow natural href navigation (full URL with ?tab=xxx) so that
		// server-side tab content is served correctly. JS handles the active-class toggle
		// for immediate visual feedback without waiting for the page reload.
		var $link = $( this );
		$( '.aips-tab-link' ).removeClass( 'nav-tab-active' );
		$link.addClass( 'nav-tab-active' );
	} );

	// -----------------------------------------------------------------------
	// Refresh button
	// -----------------------------------------------------------------------

	$( '.aips-cache-monitor-refresh' ).on( 'click', function () {
		location.reload();
	} );

	// -----------------------------------------------------------------------
	// Flush expired
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.aips-cache-flush-expired', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true );
		$.post( ajaxUrl, {
			action: 'aips_cache_monitor_flush_expired',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE
		} ).done( function ( res ) {
			$btn.prop( 'disabled', false );
			if ( res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'success' );
			} else {
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false );
			AIPS.Utilities.showToast( aipsCacheMonitor.i18n.requestFailed || 'Request failed.', 'error' );
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
					$.post( ajaxUrl, {
						action:    'aips_cache_monitor_flush_all',
						nonce:     actionNonce,
						confirmed: 1
					} ).done( function ( res ) {
						if ( res.success ) {
							AIPS.Utilities.showToast( res.data.message, 'success' );
						} else {
							AIPS.Utilities.showToast( res.data.message, 'error' );
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
					$.post( ajaxUrl, {
						action:      'aips_cache_monitor_flush_group',
						nonce:       actionNonce,
						cache_group: group
					} ).done( function ( res ) {
						if ( res.success ) {
							AIPS.Utilities.showToast( res.data.message, 'success' );
							setTimeout( function () { location.reload(); }, 1200 );
						} else {
							AIPS.Utilities.showToast( res.data.message, 'error' );
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

		$.post( ajaxUrl, {
			action: 'aips_cache_monitor_invalidate_tag',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE,
			tag:    tag
		} ).done( function ( res ) {
			if ( res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'success' );
				$btn.closest( 'tr' ).find( '.aips-badge' ).text( 'v' + res.data.new_version );
			} else {
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Invalidate domain
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.aips-cache-invalidate-domain', function () {
		var $btn   = $( this );
		var domain = $btn.data( 'domain' );

		$.post( ajaxUrl, {
			action: 'aips_cache_monitor_invalidate_domain',
			nonce:  $btn.data( 'nonce' ) || ACTION_NONCE,
			domain: domain
		} ).done( function ( res ) {
			if ( res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'success' );
			} else {
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Entries tab
	// -----------------------------------------------------------------------

	var entriesState = {
		page:    1,
		perPage: 50,
		filters: {},
		orderby: 'updated_at',
		order:   'DESC'
	};

	/**
	 * Load / refresh the entries table.
	 */
	function loadEntries() {
		var params = $.extend( {}, entriesState.filters, {
			action:   'aips_cache_monitor_entries',
			nonce:    READ_NONCE,
			page:     entriesState.page,
			per_page: entriesState.perPage,
			orderby:  entriesState.orderby,
			order:    entriesState.order
		} );

		$( '#aips-cache-entries-tbody' ).html(
			'<tr><td colspan="10">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
		);

		$.post( ajaxUrl, params ).done( function ( res ) {
			if ( ! res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'error' );
				return;
			}

			var rows = res.data.rows || [];
			var html = '';

			$.each( rows, function ( i, row ) {
				var expiresFmt = row.expires_at > 0 ? formatTs( row.expires_at ) : ( aipsCacheMonitor.i18n.never || 'Never' );
				var rowStyle   = row.is_expired ? ' style="opacity:0.55;"' : '';

				html += '<tr data-hash="' + escAttr( row.key_hash ) + '"' + rowStyle + '>';
				html += '<td class="check-column"><input type="checkbox" class="aips-cache-entry-cb" value="' + escAttr( row.key_hash ) + '" /></td>';
				html += '<td class="cell-primary">';
				html += '<code class="aips-key-hash" title="' + escAttr( row.key_hash ) + '">' + esc( row.key_hash.substring( 0, 12 ) + '…' ) + '</code>';
				html += '<div class="row-actions">';
				html += '<span><a href="#" class="aips-cache-inspect-link" data-hash="' + escAttr( row.key_hash ) + '">' + esc( aipsCacheMonitor.i18n.inspect || 'Inspect' ) + '</a></span> | ';
				html += '<span class="delete"><a href="#" class="aips-cache-delete-link" style="color:#a00;" data-hash="' + escAttr( row.key_hash ) + '">' + esc( aipsCacheMonitor.i18n.delete || 'Delete' ) + '</a></span>';
				html += '</div>';
				html += '</td>';
				html += '<td>' + esc( row.cache_group ) + '</td>';
				html += '<td><small>' + esc( row.operation_id ) + '</small></td>';
				html += '<td>' + esc( row.tier ) + '</td>';
				html += '<td>' + esc( row.driver ) + '</td>';
				html += '<td><small>' + esc( row.value_type ) + '</small></td>';
				html += '<td>' + formatBytes( row.value_size ) + '</td>';
				html += '<td>' + esc( expiresFmt ) + '</td>';
				html += '<td><button class="aips-btn aips-btn-sm aips-btn-ghost aips-cache-inspect-link" data-hash="' + escAttr( row.key_hash ) + '">' + esc( aipsCacheMonitor.i18n.inspect || 'Inspect' ) + '</button></td>';
				html += '</tr>';
			} );

			if ( ! html ) {
				html = '<tr><td colspan="10">' + esc( aipsCacheMonitor.i18n.noEntries || 'No entries found.' ) + '</td></tr>';
			}

			$( '#aips-cache-entries-tbody' ).html( html );

			// Pagination
			var totalPages  = res.data.total_pages || 1;
			var currentPage = res.data.page        || 1;
			var pagHtml     = '';

			if ( totalPages > 1 ) {
				pagHtml = '<span class="aips-pag-info">' + esc( 'Page ' + currentPage + ' / ' + totalPages + ' (' + res.data.total + ' total)' ) + '</span> ';
				if ( currentPage > 1 ) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-prev">&laquo; ' + esc( aipsCacheMonitor.i18n.prev || 'Prev' ) + '</button> ';
				}
				if ( currentPage < totalPages ) {
					pagHtml += '<button class="aips-btn aips-btn-sm aips-btn-ghost aips-entries-next">' + esc( aipsCacheMonitor.i18n.next || 'Next' ) + ' &raquo;</button>';
				}
			}
			$( '#aips-cache-entries-pagination' ).html( pagHtml );
		} );
	}

	// Auto-load when on Entries tab.
	if ( $( '#aips-cache-entries-tbody' ).length ) {
		loadEntries();
	}

	$( '#aips-cache-entries-search-btn' ).on( 'click', function () {
		entriesState.filters.search    = $( '#aips-cache-search' ).val();
		entriesState.filters.group     = $( '#aips-cache-filter-group' ).val();
		entriesState.filters.tier      = $( '#aips-cache-filter-tier' ).val();
		entriesState.filters.ttl_state = $( '#aips-cache-filter-ttl' ).val();
		entriesState.page              = 1;
		loadEntries();
	} );

	// Enter key triggers search.
	$( '#aips-cache-search' ).on( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) { $( '#aips-cache-entries-search-btn' ).trigger( 'click' ); }
	} );

	$( document ).on( 'click', '.aips-entries-prev', function () { entriesState.page--; loadEntries(); } );
	$( document ).on( 'click', '.aips-entries-next', function () { entriesState.page++; loadEntries(); } );

	$( '#aips-cache-per-page' ).on( 'change', function () {
		entriesState.perPage = parseInt( $( this ).val(), 10 );
		entriesState.page    = 1;
		loadEntries();
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

		$.post( ajaxUrl, {
			action:   'aips_cache_monitor_inspect',
			nonce:    READ_NONCE,
			key_hash: hash
		} ).done( function ( res ) {
			if ( ! res.success ) {
				$( '#aips-cache-inspect-body' ).html( '<p>' + esc( res.data.message ) + '</p>' );
				return;
			}

			var d           = res.data;
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
	// Delete single entry
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.aips-cache-delete-link', function ( e ) {
		e.preventDefault();
		var $el         = $( this );
		var hash        = $el.data( 'hash' );
		var actionNonce = $el.closest( '[data-nonce]' ).data( 'nonce' ) || ACTION_NONCE;

		$.post( ajaxUrl, {
			action:   'aips_cache_monitor_delete_entry',
			nonce:    actionNonce,
			key_hash: hash
		} ).done( function ( res ) {
			if ( res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'success' );
				$el.closest( 'tr' ).fadeOut( 300, function () { $( this ).remove(); } );
			} else {
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} );
	} );

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

		$.post( ajaxUrl, {
			action:     'aips_cache_monitor_delete_bulk',
			nonce:      $( this ).data( 'nonce' ) || ACTION_NONCE,
			key_hashes: hashes
		} ).done( function ( res ) {
			if ( res.success ) {
				AIPS.Utilities.showToast( res.data.message, 'success' );
				loadEntries();
			} else {
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} );
	} );

	// -----------------------------------------------------------------------
	// Operations tab
	// -----------------------------------------------------------------------

	$( '#aips-ops-search-btn' ).on( 'click', function () {
		var params = {
			action:           'aips_cache_monitor_operations',
			nonce:            $( this ).data( 'nonce' ) || READ_NONCE,
			repository_class: $( '#aips-ops-filter-repo' ).val(),
			tier:             $( '#aips-ops-filter-tier' ).val()
		};

		$( '#aips-ops-tbody' ).html(
			'<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
		);

		$.post( ajaxUrl, params ).done( function ( res ) {
			if ( ! res.success ) { AIPS.Utilities.showToast( res.data.message, 'error' ); return; }

			var ops  = res.data.operations || [];
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
		} );
	} );

	// -----------------------------------------------------------------------
	// Events tab
	// -----------------------------------------------------------------------

	var eventsPage = 1;

	function loadEvents() {
		var params = {
			action:     'aips_cache_monitor_events',
			nonce:      READ_NONCE,
			event_type: $( '#aips-events-filter-type' ).val(),
			page:       eventsPage,
			per_page:   50
		};

		$( '#aips-events-tbody' ).html(
			'<tr><td colspan="6">' + esc( aipsCacheMonitor.i18n.loading || 'Loading…' ) + '</td></tr>'
		);

		$.post( ajaxUrl, params ).done( function ( res ) {
			if ( ! res.success ) { AIPS.Utilities.showToast( res.data.message, 'error' ); return; }

			var rows = res.data.rows || [];
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
		} );
	}

	$( '#aips-events-load-btn' ).on( 'click', function () {
		eventsPage = 1;
		loadEvents();
	} );

	// -----------------------------------------------------------------------
	// Maintenance tab
	// -----------------------------------------------------------------------

	$( '.aips-maintenance-action-btn' ).on( 'click', function () {
		var $btn        = $( this );
		var action      = $btn.data( 'action' );
		var actionNonce = $btn.data( 'nonce' ) || ACTION_NONCE;
		var $result     = $( '#aips-maintenance-result' );

		$btn.prop( 'disabled', true );

		$.post( ajaxUrl, {
			action:             'aips_cache_monitor_maintenance',
			nonce:              actionNonce,
			maintenance_action: action
		} ).done( function ( res ) {
			$btn.prop( 'disabled', false );

			// Export: trigger file download.
			if ( action === 'export_diagnostics' && res.success ) {
				try {
					var blob = new Blob( [ JSON.stringify( res.data.diagnostics, null, 2 ) ], { type: 'application/json' } );
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

			if ( res.success ) {
				$result.show().html( '<div class="notice notice-success inline"><p>' + esc( res.data.message ) + '</p></div>' );
				AIPS.Utilities.showToast( res.data.message, 'success' );
			} else {
				$result.show().html( '<div class="notice notice-error inline"><p>' + esc( res.data.message ) + '</p></div>' );
				AIPS.Utilities.showToast( res.data.message, 'error' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false );
			AIPS.Utilities.showToast( aipsCacheMonitor.i18n.requestFailed || 'Request failed.', 'error' );
		} );
	} );

} )( jQuery );
