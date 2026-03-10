/**
 * History Page JavaScript
 *
 * Manages the History admin page: search/filter, row selection, bulk delete,
 * and the logs modal that shows all aips_history_log entries for a container.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	Object.assign(AIPS, {

		/* ------------------------------------------------------------------ */
		/* State                                                                */
		/* ------------------------------------------------------------------ */

		/** @type {string} Current status filter value */
		historyStatusFilter: '',

		/** @type {string} Current search query */
		historySearchQuery: '',

		/* ------------------------------------------------------------------ */
		/* Init / events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the History module.
		 */
		initHistory: function () {
			this.bindHistoryEvents();
			this.historyStatusFilter = $('#aips-filter-status').val() || '';
			this.historySearchQuery  = $('#aips-history-search-input').val() || '';
			this.syncSearchClearButton();
		},

		/**
		 * Register all event listeners for the History admin page.
		 */
		bindHistoryEvents: function () {
			// Open logs modal when clicking a container row or "View Logs" button.
			$(document).on('click', '.aips-view-history-logs', this.openLogsModal.bind(this));

			// Close modal.
			$(document).on('click', '#aips-history-logs-modal .aips-modal-close', this.closeLogsModal.bind(this));
			$(document).on('click', '#aips-history-logs-modal', this.closeLogsModalOnOverlay.bind(this));

			// Select-all checkbox.
			$(document).on('change', '#aips-cb-select-all', this.toggleSelectAll.bind(this));
			$(document).on('change', '.aips-history-cb', this.onRowCheckboxChange.bind(this));

			// Bulk delete.
			$('#aips-delete-selected-btn').on('click', this.deleteSelected.bind(this));

			// Individual row delete.
			$(document).on('click', '.aips-delete-history', this.deleteSingleItem.bind(this));

			// Clear history (failed / all).
			$(document).on('click', '.aips-clear-history', this.clearHistory.bind(this));

			// Reload.
			$('#aips-reload-history-btn').on('click', this.reloadHistory.bind(this));

			// Filter button.
			$('#aips-filter-btn').on('click', this.applyFilter.bind(this));
			$('#aips-filter-status').on('change', this.applyFilter.bind(this));

			// Search input — live client-side filter + server reload on Enter.
			$('#aips-history-search-input').on('input', this.onSearchInput.bind(this));
			$('#aips-history-search-input').on('keydown', this.onSearchKeydown.bind(this));
			$('#aips-history-search-clear').on('click', this.clearSearch.bind(this));
			$('.aips-clear-history-search-btn').on('click', this.clearSearch.bind(this));

			// Export CSV.
			$('#aips-export-history-btn').on('click', this.exportHistory.bind(this));
		},

		/* ------------------------------------------------------------------ */
		/* Logs modal                                                           */
		/* ------------------------------------------------------------------ */

		/**
		 * Fetch and display all history_log entries for the clicked container.
		 *
		 * @param {Event} e - Click event from an `.aips-view-history-logs` element.
		 */
		openLogsModal: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var historyId = $(e.currentTarget).data('id');
			if (!historyId) {
				return;
			}

			var $modal   = $('#aips-history-logs-modal');
			var $content = $('#aips-history-logs-content');
			var $title   = $('#aips-history-logs-modal-title');
			var loadingText = aipsHistoryL10n.loadingLogs || 'Loading logs…';

			$title.text(loadingText);
			$content.html('<p>' + loadingText + '</p>');
			$modal.fadeIn(200);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_history_logs',
					nonce: aipsAjax.nonce,
					history_id: historyId
				},
				success: function (response) {
					if (!response.success) {
						$content.html('<p class="notice notice-error">' + (response.data && response.data.message ? response.data.message : (aipsHistoryL10n.errorLoading || 'Error loading logs.')) + '</p>');
						return;
					}

					var container = response.data.container;
					var logs      = response.data.logs;

					// Update modal title.
					var title = container.generated_title || ('#' + container.id);
					$title.text(aipsHistoryL10n.logsModalTitle
						? aipsHistoryL10n.logsModalTitle.replace('%s', title)
						: 'Logs — ' + title);

					$content.html(AIPS.renderLogsModalContent(container, logs));
				},
				error: function () {
					$content.html('<p class="notice notice-error">' + (aipsHistoryL10n.errorLoading || 'Error loading logs.') + '</p>');
				}
			});
		},

		/**
		 * Build the HTML for the logs modal body.
		 *
		 * @param {Object}   container  History container summary fields.
		 * @param {Object[]} logs       Array of log entry objects.
		 * @return {string} HTML string.
		 */
		renderLogsModalContent: function (container, logs) {
			var html = '';

			// ---- Container summary ----
			html += '<div class="aips-history-modal-summary">';
			html += '<table class="aips-table" style="width:100%;margin-bottom:20px;">';
			html += '<tbody>';

			if (container.generated_title) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelTitle || 'Title') + '</th><td>' + AIPS.esc(container.generated_title) + '</td></tr>';
			}
			if (container.template_name) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelTemplate || 'Template') + '</th><td>' + AIPS.esc(container.template_name) + '</td></tr>';
			}

			var statusClass = container.status === 'completed' ? 'aips-badge-success'
				: (container.status === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral');
			html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelStatus || 'Status') + '</th>'
				+ '<td><span class="aips-badge ' + statusClass + '">' + AIPS.esc(container.status) + '</span></td></tr>';

			if (container.created_at) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelCreated || 'Created') + '</th><td>' + AIPS.esc(container.created_at) + '</td></tr>';
			}
			if (container.completed_at) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelCompleted || 'Completed') + '</th><td>' + AIPS.esc(container.completed_at) + '</td></tr>';
			}
			if (container.error_message) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelError || 'Error') + '</th>'
					+ '<td style="color:#d63638;">' + AIPS.esc(container.error_message) + '</td></tr>';
			}
			if (container.post_id) {
				html += '<tr><th>' + AIPS.esc(aipsHistoryL10n.labelPostId || 'Post ID') + '</th><td>' + AIPS.esc(String(container.post_id)) + '</td></tr>';
			}

			html += '</tbody></table></div>';

			// ---- Log entries ----
			html += '<h3>' + AIPS.esc(aipsHistoryL10n.logsHeading || 'Log Entries') + ' <span class="aips-badge aips-badge-neutral">' + logs.length + '</span></h3>';

			if (logs.length === 0) {
				html += '<p>' + AIPS.esc(aipsHistoryL10n.noLogsFound || 'No log entries found for this container.') + '</p>';
				return html;
			}

			html += '<table class="aips-table aips-history-logs-table" style="width:100%;">';
			html += '<thead><tr>'
				+ '<th style="width:150px;">' + AIPS.esc(aipsHistoryL10n.colTimestamp || 'Timestamp') + '</th>'
				+ '<th style="width:130px;">' + AIPS.esc(aipsHistoryL10n.colType || 'Type') + '</th>'
				+ '<th style="width:150px;">' + AIPS.esc(aipsHistoryL10n.colLogType || 'Log Type') + '</th>'
				+ '<th>' + AIPS.esc(aipsHistoryL10n.colDetails || 'Details') + '</th>'
				+ '</tr></thead><tbody>';

			$.each(logs, function (i, log) {
				var typeClass = AIPS.historyTypeClass(log.history_type_id);
				var message   = (log.details && log.details.message) ? log.details.message : '';

				html += '<tr>';
				html += '<td style="white-space:nowrap;font-size:12px;">' + AIPS.esc(log.timestamp) + '</td>';
				html += '<td><span class="aips-badge ' + typeClass + '">' + AIPS.esc(log.type_label) + '</span></td>';
				html += '<td style="font-size:12px;font-family:monospace;">' + AIPS.esc(log.log_type) + '</td>';
				html += '<td>';

				if (message) {
					html += '<p style="margin:0 0 6px;">' + AIPS.esc(message) + '</p>';
				}

				// Render extra details (input/output/context) as collapsible.
				var extra = {};
				$.each(log.details, function (key, val) {
					if (key !== 'message' && key !== 'timestamp') {
						extra[key] = val;
					}
				});

				if (Object.keys(extra).length > 0) {
					var rowId = 'aips-log-detail-' + i;
					html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-ghost aips-log-toggle" '
						+ 'data-target="#' + rowId + '" style="font-size:11px;">'
						+ (aipsHistoryL10n.showDetails || 'Show details') + '</button>';
					html += '<div id="' + rowId + '" style="display:none;margin-top:8px;">';
					html += '<pre style="max-height:200px;overflow:auto;white-space:pre-wrap;font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;">'
						+ AIPS.esc(JSON.stringify(extra, null, 2)) + '</pre>';
					html += '</div>';
				}

				html += '</td></tr>';
			});

			html += '</tbody></table>';

			return html;
		},

		/**
		 * Return a badge CSS class for a history_type_id constant.
		 *
		 * @param {number} typeId
		 * @return {string}
		 */
		historyTypeClass: function (typeId) {
			var map = {
				1: 'aips-badge-neutral',   // LOG
				2: 'aips-badge-error',     // ERROR
				3: 'aips-badge-warning',   // WARNING
				4: 'aips-badge-info',      // INFO
				5: 'aips-badge-ai',        // AI_REQUEST
				6: 'aips-badge-ai',        // AI_RESPONSE
				7: 'aips-badge-neutral',   // DEBUG
				8: 'aips-badge-success',   // ACTIVITY
				9: 'aips-badge-neutral'    // SESSION_METADATA
			};
			return map[typeId] || 'aips-badge-neutral';
		},

		/**
		 * Close the logs modal.
		 *
		 * @param {Event} e
		 */
		closeLogsModal: function (e) {
			e.preventDefault();
			$('#aips-history-logs-modal').fadeOut(200);
		},

		/**
		 * Close the logs modal when the overlay backdrop is clicked.
		 *
		 * @param {Event} e
		 */
		closeLogsModalOnOverlay: function (e) {
			if ($(e.target).is('#aips-history-logs-modal')) {
				$('#aips-history-logs-modal').fadeOut(200);
			}
		},

		/* ------------------------------------------------------------------ */
		/* Selection / bulk delete                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * Toggle all row checkboxes to match the select-all checkbox state.
		 *
		 * @param {Event} e
		 */
		toggleSelectAll: function (e) {
			var checked = $(e.target).prop('checked');
			$('.aips-history-cb').prop('checked', checked);
			this.updateDeleteButton();
		},

		/**
		 * Update the Delete Selected button disabled state when a row checkbox changes.
		 */
		onRowCheckboxChange: function () {
			this.updateDeleteButton();
			var allChecked = $('.aips-history-cb').length > 0
				&& $('.aips-history-cb:not(:checked)').length === 0;
			$('#aips-cb-select-all').prop('checked', allChecked);
		},

		/**
		 * Enable or disable the Delete Selected button based on selections.
		 */
		updateDeleteButton: function () {
			var count = $('.aips-history-cb:checked').length;
			$('#aips-delete-selected-btn').prop('disabled', count === 0);
		},

		/**
		 * Send bulk-delete request for all checked containers.
		 *
		 * @param {Event} e
		 */
		deleteSelected: function (e) {
			e.preventDefault();

			var ids = [];
			$('.aips-history-cb:checked').each(function () {
				ids.push($(this).val());
			});

			if (!ids.length) {
				return;
			}

			if (!confirm(aipsHistoryL10n.confirmBulkDelete || 'Delete the selected history containers? This cannot be undone.')) {
				return;
			}

			var $btn     = $(e.currentTarget);
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.deleting || 'Deleting…')
			);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_bulk_delete_history',
					nonce: aipsAjax.nonce,
					ids: ids
				},
				success: function (response) {
					if (response.success) {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Items deleted successfully.', 'success');
						}
						AIPS.reloadHistory();
					} else {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : (aipsHistoryL10n.errorDeleting || 'Error deleting items.'), 'error');
						}
						$btn.prop('disabled', false).html(origHtml);
					}
				},
				error: function () {
					if (typeof AIPS.Utilities !== 'undefined') {
						AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting items.', 'error');
					}
					$btn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/**
		 * Delete a single history container row.
		 *
		 * @param {Event} e - Click event from an `.aips-delete-history` element.
		 */
		deleteSingleItem: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var id = $(e.currentTarget).data('id');
			if (!id) {
				return;
			}

			if (!confirm(aipsHistoryL10n.confirmDelete || 'Delete this history container? This cannot be undone.')) {
				return;
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_bulk_delete_history',
					nonce: aipsAjax.nonce,
					ids: [id]
				},
				success: function (response) {
					if (response.success) {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Item deleted.', 'success');
						}
						AIPS.reloadHistory();
					} else {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : (aipsHistoryL10n.errorDeleting || 'Error deleting item.'), 'error');
						}
					}
				},
				error: function () {
					if (typeof AIPS.Utilities !== 'undefined') {
						AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting item.', 'error');
					}
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Clear history                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Clear history by status (or all).
		 *
		 * @param {Event} e - Click event from an `.aips-clear-history` element.
		 */
		clearHistory: function (e) {
			e.preventDefault();

			var status = $(e.currentTarget).data('status');
			var msg    = status
				? (aipsHistoryL10n.confirmClearStatus || 'Clear all history with this status?')
				: (aipsHistoryL10n.confirmClearAll   || 'Clear all history? This cannot be undone.');

			if (!confirm(msg)) {
				return;
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_clear_history',
					nonce: aipsAjax.nonce,
					status: status
				},
				success: function (response) {
					if (response.success) {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : (aipsHistoryL10n.clearedSuccess || 'History cleared.'), 'success');
						}
						AIPS.reloadHistory();
					} else {
						if (typeof AIPS.Utilities !== 'undefined') {
							AIPS.Utilities.showToast(response.data && response.data.message ? response.data.message : (aipsHistoryL10n.errorClearing || 'Error clearing history.'), 'error');
						}
					}
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Reload / filter / search                                            */
		/* ------------------------------------------------------------------ */

		/**
		 * Reload the history table via AJAX, applying current filter and search.
		 */
		reloadHistory: function () {
			var $tbody      = $('#aips-history-tbody');
			var $pagination = $('.aips-history-pagination-cell');

			if ($tbody.length) {
				$tbody.html('<tr><td colspan="6" style="text-align:center;padding:20px;">'
					+ (aipsHistoryL10n.loading || 'Loading…') + '</td></tr>');
			}

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_reload_history',
					nonce: aipsAjax.nonce,
					status: AIPS.historyStatusFilter,
					search: AIPS.historySearchQuery,
					paged: 1
				},
				success: function (response) {
					if (!response.success) {
						return;
					}

					if ($tbody.length) {
						$tbody.html(response.data.items_html || '');
					}
					if ($pagination.length && response.data.pagination_html !== undefined) {
						$pagination.html(response.data.pagination_html);
					}

					// Update stat cards.
					var stats = response.data.stats;
					if (stats) {
						$('#aips-stat-total').text(stats.total);
						$('#aips-stat-completed').text(stats.completed);
						$('#aips-stat-failed').text(stats.failed);
						$('#aips-stat-success-rate').text(stats.success_rate + '%');
					}

					// Reset checkboxes / button.
					$('#aips-cb-select-all').prop('checked', false);
					AIPS.updateDeleteButton();
				}
			});
		},

		/**
		 * Apply the current status filter and trigger a reload.
		 *
		 * @param {Event} e
		 */
		applyFilter: function (e) {
			if (e) {
				e.preventDefault();
			}
			AIPS.historyStatusFilter = $('#aips-filter-status').val() || '';
			AIPS.reloadHistory();
		},

		/**
		 * Live client-side row filter as the user types in the search box.
		 *
		 * @param {Event} e
		 */
		onSearchInput: function (e) {
			var query = $(e.target).val().toLowerCase().trim();
			AIPS.historySearchQuery = query;
			AIPS.syncSearchClearButton();

			var hasResults = false;

			$('#aips-history-tbody tr').each(function () {
				var text = $(this).text().toLowerCase();
				if (text.indexOf(query) !== -1) {
					$(this).show();
					hasResults = true;
				} else {
					$(this).hide();
				}
			});

			$('#aips-history-search-no-results').toggle(!hasResults && query.length > 0);
		},

		/**
		 * On Enter in the search box, perform a server-side reload.
		 *
		 * @param {Event} e
		 */
		onSearchKeydown: function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				AIPS.historySearchQuery = $('#aips-history-search-input').val().trim();
				AIPS.reloadHistory();
			}
		},

		/**
		 * Clear the search input and reload.
		 *
		 * @param {Event} e
		 */
		clearSearch: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-history-search-input').val('');
			AIPS.historySearchQuery = '';
			AIPS.syncSearchClearButton();
			$('#aips-history-search-no-results').hide();
			$('#aips-history-tbody tr').show();
			AIPS.reloadHistory();
		},

		/**
		 * Show or hide the search clear button based on current input value.
		 */
		syncSearchClearButton: function () {
			var hasValue = $('#aips-history-search-input').val().trim().length > 0;
			$('#aips-history-search-clear').toggle(hasValue);
		},

		/* ------------------------------------------------------------------ */
		/* Export                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * Trigger the CSV export.
		 *
		 * @param {Event} e
		 */
		exportHistory: function (e) {
			e.preventDefault();

			var form = $('<form method="POST" action="' + aipsAjax.ajaxUrl + '" style="display:none;"></form>');
			form.append($('<input type="hidden" name="action" value="aips_export_history">'));
			form.append($('<input type="hidden" name="nonce">').val(aipsAjax.nonce));
			form.append($('<input type="hidden" name="status">').val(AIPS.historyStatusFilter));
			form.append($('<input type="hidden" name="search">').val(AIPS.historySearchQuery));
			$('body').append(form);
			form.submit();
			form.remove();
		},

		/* ------------------------------------------------------------------ */
		/* Utility                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * HTML-escape a string for safe output.
		 *
		 * @param {string} str
		 * @return {string}
		 */
		esc: function (str) {
			if (str === null || str === undefined) {
				return '';
			}
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}
	});

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function () {
		AIPS.initHistory();

		// Delegated click handler for collapsible log-detail sections inside the modal.
		$(document).on('click', '.aips-log-toggle', function () {
			var targetSelector = $(this).data('target');
			var $target = $(targetSelector);
			if ($target.length) {
				$target.slideToggle(150);
			}
		});
	});

})(jQuery);
