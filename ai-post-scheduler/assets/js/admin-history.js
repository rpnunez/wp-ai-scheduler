/**
 * History Page JavaScript
 *
 * Manages the History admin page: search/filter, pagination, row selection,
 * bulk delete, retry, and the logs modal that renders all aips_history_log
 * entries for a selected history container.
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};

	/**
	 * AIPS.History — self-contained module for the History admin page.
	 *
	 * Follows the same init() / bindEvents() naming convention used throughout
	 * this plugin (e.g. authors.js / GenerationQueueModule) so the page can
	 * be bootstrapped with a single AIPS.History.init() call, without
	 * polluting the global AIPS namespace with page-specific handlers.
	 */
	AIPS.History = {

		/* ------------------------------------------------------------------ */
		/* State                                                                */
		/* ------------------------------------------------------------------ */

		/** @type {string} Current status filter value */
		statusFilter: '',

		/** @type {string} Raw search query as entered by the user */
		searchQuery: '',

		/* ------------------------------------------------------------------ */
		/* Init / events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the History module.
		 */
		init: function () {
			this.statusFilter = $('#aips-filter-status').val() || '';
			this.searchQuery  = $('#aips-history-search-input').val() || '';
			this.syncSearchClearButton();
			this.bindEvents();
		},

		/**
		 * Register all event listeners for the History admin page.
		 */
		bindEvents: function () {
			// Open logs modal.
			$(document).on('click', '.aips-view-history-logs', this.openLogsModal.bind(this));

			// Collapsible log-detail sections inside the modal.
			$(document).on('click', '.aips-log-toggle', this.toggleLogDetail.bind(this));

			// Close modal via close button or backdrop click.
			$(document).on('click', '#aips-history-logs-modal .aips-modal-close', this.closeLogsModal.bind(this));
			$(document).on('click', '#aips-history-logs-modal', this.closeLogsModalOnOverlay.bind(this));

			// Select-all and individual row checkboxes.
			$(document).on('change', '#aips-cb-select-all', this.toggleSelectAll.bind(this));
			$(document).on('change', '.aips-history-cb', this.onRowCheckboxChange.bind(this));

			// Bulk delete.
			$(document).on('click', '#aips-delete-selected-btn', this.deleteSelected.bind(this));

			// Individual row delete.
			$(document).on('click', '.aips-delete-history', this.deleteSingleItem.bind(this));

			// Retry failed generation.
			$(document).on('click', '.aips-retry-generation', this.retryGeneration.bind(this));

			// Clear history (failed / all).
			$(document).on('click', '.aips-clear-history', this.clearHistory.bind(this));

			// Reload button.
			$(document).on('click', '#aips-reload-history-btn', this.onReloadClick.bind(this));

			// Pagination links.
			$(document).on(
				'click',
				'.aips-history-page-link, .aips-history-page-prev, .aips-history-page-next',
				this.loadPage.bind(this)
			);

			// Filter button and status dropdown.
			$(document).on('click', '#aips-filter-btn', this.applyFilter.bind(this));
			$(document).on('change', '#aips-filter-status', this.applyFilter.bind(this));

			// Search: live client-side row filter + server reload on Enter.
			$(document).on('input', '#aips-history-search-input', this.onSearchInput.bind(this));
			$(document).on('keydown', '#aips-history-search-input', this.onSearchKeydown.bind(this));
			$(document).on('click', '#aips-history-search-clear', this.clearSearch.bind(this));
			$(document).on('click', '.aips-clear-history-search-btn', this.clearSearch.bind(this));

			// Export CSV.
			$(document).on('click', '#aips-export-history-btn', this.exportHistory.bind(this));
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

			var self        = this;
			var $modal      = $('#aips-history-logs-modal');
			var $content    = $('#aips-history-logs-content');
			var $title      = $('#aips-history-logs-modal-title');
			var loadingText = aipsHistoryL10n.loadingLogs || 'Loading logs\u2026';

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
						$content.html(
							'<p class="notice notice-error">'
							+ self.esc(response.data && response.data.message ? response.data.message : (aipsHistoryL10n.errorLoading || 'Error loading logs.'))
							+ '</p>'
						);
						return;
					}

					var container = response.data.container;
					var logs      = response.data.logs;

					var title = container.generated_title || ('#' + container.id);
					$title.text(
						aipsHistoryL10n.logsModalTitle
							? aipsHistoryL10n.logsModalTitle.replace('%s', title)
							: 'Logs \u2014 ' + title
					);

					$content.html(self.renderLogsModalContent(container, logs));
				},
				error: function () {
					$content.html(
						'<p class="notice notice-error">'
						+ self.esc(aipsHistoryL10n.errorLoading || 'Error loading logs.')
						+ '</p>'
					);
				}
			});
		},

		/**
		 * Toggle a collapsible log-detail section inside the modal.
		 *
		 * @param {Event} e - Click event from an `.aips-log-toggle` element.
		 */
		toggleLogDetail: function (e) {
			e.preventDefault();
			var targetSelector = $(e.currentTarget).data('target');
			var $target = $(targetSelector);
			if ($target.length) {
				$target.slideToggle(150);
			}
		},

		/**
		 * Build the HTML for the logs modal body.
		 *
		 * @param {Object}   container History container summary fields.
		 * @param {Object[]} logs      Array of log entry objects.
		 * @return {string} HTML string.
		 */
		renderLogsModalContent: function (container, logs) {
			var self = this;
			var html = '';

			// ---- Container summary ----
			html += '<div class="aips-history-modal-summary">';
			html += '<table class="aips-table" style="width:100%;margin-bottom:20px;"><tbody>';

			if (container.generated_title) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelTitle || 'Title') + '</th><td>' + self.esc(container.generated_title) + '</td></tr>';
			}
			if (container.template_name) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelTemplate || 'Template') + '</th><td>' + self.esc(container.template_name) + '</td></tr>';
			}

			var statusClass = container.status === 'completed' ? 'aips-badge-success'
				: (container.status === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral');
			html += '<tr><th>' + self.esc(aipsHistoryL10n.labelStatus || 'Status') + '</th>'
				+ '<td><span class="aips-badge ' + statusClass + '">' + self.esc(container.status) + '</span></td></tr>';

			if (container.created_at) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelCreated || 'Created') + '</th><td>' + self.esc(container.created_at) + '</td></tr>';
			}
			if (container.completed_at) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelCompleted || 'Completed') + '</th><td>' + self.esc(container.completed_at) + '</td></tr>';
			}
			if (container.error_message) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelError || 'Error') + '</th>'
					+ '<td style="color:#d63638;">' + self.esc(container.error_message) + '</td></tr>';
			}
			if (container.post_id) {
				html += '<tr><th>' + self.esc(aipsHistoryL10n.labelPostId || 'Post ID') + '</th><td>' + self.esc(String(container.post_id)) + '</td></tr>';
			}

			html += '</tbody></table></div>';

			// ---- Log entries ----
			html += '<h3>' + self.esc(aipsHistoryL10n.logsHeading || 'Log Entries')
				+ ' <span class="aips-badge aips-badge-neutral">' + logs.length + '</span></h3>';

			if (logs.length === 0) {
				html += '<p>' + self.esc(aipsHistoryL10n.noLogsFound || 'No log entries found for this container.') + '</p>';
				return html;
			}

			html += '<table class="aips-table aips-history-logs-table" style="width:100%;">';
			html += '<thead><tr>'
				+ '<th style="width:150px;">' + self.esc(aipsHistoryL10n.colTimestamp || 'Timestamp') + '</th>'
				+ '<th style="width:130px;">' + self.esc(aipsHistoryL10n.colType || 'Type') + '</th>'
				+ '<th style="width:150px;">' + self.esc(aipsHistoryL10n.colLogType || 'Log Type') + '</th>'
				+ '<th>' + self.esc(aipsHistoryL10n.colDetails || 'Details') + '</th>'
				+ '</tr></thead><tbody>';

			$.each(logs, function (i, log) {
				var typeClass = self.typeClass(log.history_type_id);
				var message   = (log.details && log.details.message) ? log.details.message : '';

				html += '<tr>';
				html += '<td style="white-space:nowrap;font-size:12px;">' + self.esc(log.timestamp) + '</td>';
				html += '<td><span class="aips-badge ' + typeClass + '">' + self.esc(log.type_label) + '</span></td>';
				html += '<td style="font-size:12px;font-family:monospace;">' + self.esc(log.log_type) + '</td>';
				html += '<td>';

				if (message) {
					html += '<p style="margin:0 0 6px;">' + self.esc(message) + '</p>';
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
						+ self.esc(aipsHistoryL10n.showDetails || 'Show details') + '</button>';
					html += '<div id="' + rowId + '" style="display:none;margin-top:8px;">';
					html += '<pre style="max-height:200px;overflow:auto;white-space:pre-wrap;font-size:11px;background:#f6f7f7;padding:8px;border-radius:4px;">'
						+ self.esc(JSON.stringify(extra, null, 2)) + '</pre>';
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
		typeClass: function (typeId) {
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
		 * Sync the select-all checkbox and Delete Selected button on row change.
		 */
		onRowCheckboxChange: function () {
			this.updateDeleteButton();
			var allChecked = $('.aips-history-cb').length > 0
				&& $('.aips-history-cb:not(:checked)').length === 0;
			$('#aips-cb-select-all').prop('checked', allChecked);
		},

		/**
		 * Enable or disable the Delete Selected button based on current selections.
		 */
		updateDeleteButton: function () {
			var count = $('.aips-history-cb:checked').length;
			$('#aips-delete-selected-btn').prop('disabled', count === 0);
		},

		/**
		 * Send a bulk-delete request for all checked containers.
		 *
		 * @param {Event} e - Click event from `#aips-delete-selected-btn`.
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

			var self     = this;
			var $btn     = $(e.currentTarget);
			var origHtml = $btn.html();
			var msg      = aipsHistoryL10n.confirmBulkDelete || 'Delete the selected history containers? This cannot be undone.';

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmDeleteLabel || 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
					$btn.prop('disabled', true).html(
						'<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.deleting || 'Deleting\u2026')
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
								AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Items deleted successfully.', 'success');
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorDeleting || 'Error deleting items.'),
									'error'
								);
								$btn.prop('disabled', false).html(origHtml);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting items.', 'error');
							$btn.prop('disabled', false).html(origHtml);
						}
					});
				}}
			]);
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

			var self = this;
			var msg  = aipsHistoryL10n.confirmDelete || 'Delete this history container? This cannot be undone.';

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmDeleteLabel || 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function () {
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
								AIPS.Utilities.showToast(aipsHistoryL10n.deletedSuccess || 'Item deleted.', 'success');
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorDeleting || 'Error deleting item.'),
									'error'
								);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorDeleting || 'Error deleting item.', 'error');
						}
					});
				}}
			]);
		},

		/* ------------------------------------------------------------------ */
		/* Retry generation                                                     */
		/* ------------------------------------------------------------------ */

		/**
		 * Retry a failed history entry via the `aips_retry_generation` AJAX action.
		 *
		 * @param {Event} e - Click event from an `.aips-retry-generation` element.
		 */
		retryGeneration: function (e) {
			e.preventDefault();

			var id       = $(e.currentTarget).data('id');
			var $btn     = $(e.currentTarget);
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update"></span> ' + (aipsHistoryL10n.retrying || 'Retrying\u2026')
			);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_retry_generation',
					nonce: aipsAjax.nonce,
					history_id: id
				},
				success: function (response) {
					if (response.success) {
						AIPS.Utilities.showToast(response.data.message, 'success');
						location.reload();
					} else {
						AIPS.Utilities.showToast(response.data.message, 'error');
						$btn.prop('disabled', false).html(origHtml);
					}
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryL10n.errorRetrying || 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/* ------------------------------------------------------------------ */
		/* Clear history                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Clear history by status (or all) after an accessible confirmation dialog.
		 *
		 * @param {Event} e - Click event from an `.aips-clear-history` element.
		 */
		clearHistory: function (e) {
			e.preventDefault();

			var status = $(e.currentTarget).data('status');
			var msg    = status
				? (aipsHistoryL10n.confirmClearStatus || 'Clear all history entries with this status? This cannot be undone.')
				: (aipsHistoryL10n.confirmClearAll   || 'Clear all history? This cannot be undone.');

			var self = this;

			AIPS.Utilities.confirm(msg, 'Notice', [
				{ label: aipsHistoryL10n.cancelLabel    || 'No, cancel', className: 'aips-btn aips-btn-primary' },
				{ label: aipsHistoryL10n.confirmClearLabel || 'Yes, clear', className: 'aips-btn aips-btn-danger-solid', action: function () {
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
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.clearedSuccess || 'History cleared.'),
									'success'
								);
								self.reload();
							} else {
								AIPS.Utilities.showToast(
									response.data && response.data.message
										? response.data.message
										: (aipsHistoryL10n.errorClearing || 'Error clearing history.'),
									'error'
								);
							}
						},
						error: function () {
							AIPS.Utilities.showToast(aipsHistoryL10n.errorClearing || 'Error clearing history.', 'error');
						}
					});
				}}
			]);
		},

		/* ------------------------------------------------------------------ */
		/* Reload / pagination / filter / search                               */
		/* ------------------------------------------------------------------ */

		/**
		 * Handle a click on the Reload button.
		 *
		 * @param {Event} e
		 */
		onReloadClick: function (e) {
			e.preventDefault();
			this.reload();
		},

		/**
		 * Reload the history table via AJAX applying the current filter and search.
		 *
		 * @param {number} [paged=1] 1-based page number to load.
		 */
		reload: function (paged) {
			paged = (paged === undefined || paged === null) ? 1 : Math.max(1, parseInt(paged, 10));

			var self       = this;
			var $tbody     = $('#aips-history-tbody');
			var $pagCell   = $('.aips-history-pagination-cell');
			var $reloadBtn = $('#aips-reload-history-btn');
			var origHtml   = $reloadBtn.html();

			// Show a loading placeholder in the table body.
			if ($tbody.length) {
				$tbody.html(
					'<tr><td colspan="6" style="text-align:center;padding:20px;">'
					+ (aipsHistoryL10n.loading || 'Loading\u2026') + '</td></tr>'
				);
			}
			$reloadBtn.prop('disabled', true).html(
				'<span class="spinner is-active" style="float:none;margin:0 4px 0 0;"></span> '
				+ (aipsHistoryL10n.reloading || 'Reloading\u2026')
			);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'aips_reload_history',
					nonce: aipsAjax.nonce,
					status: self.statusFilter,
					search: self.searchQuery,
					paged: paged
				},
				success: function (response) {
					if (!response.success) {
						AIPS.Utilities.showToast(
							response.data && response.data.message
								? response.data.message
								: (aipsHistoryL10n.errorReloading || 'Failed to reload history.'),
							'error'
						);
						return;
					}

					var itemsHtml = response.data.items_html || '';

					if ($tbody.length) {
						if (itemsHtml) {
							$tbody.html(itemsHtml);
							$('#aips-history-search-no-results').hide();
						} else {
							// No results: render a friendly inline empty state.
							$tbody.html(
								'<tr><td colspan="6" style="text-align:center;padding:40px;">'
								+ '<span class="dashicons dashicons-search" style="font-size:32px;color:#ccc;vertical-align:middle;margin-right:8px;"></span>'
								+ self.esc(aipsHistoryL10n.noResultsFound || 'No history containers match your current filters.')
								+ '</td></tr>'
							);
						}
					}

					if ($pagCell.length && response.data.pagination_html !== undefined) {
						$pagCell.html(response.data.pagination_html);
					}

					// Refresh stat cards.
					var stats = response.data.stats;
					if (stats) {
						$('#aips-stat-total').text(stats.total);
						$('#aips-stat-completed').text(stats.completed);
						$('#aips-stat-failed').text(stats.failed);
						$('#aips-stat-success-rate').text(stats.success_rate + '%');
					}

					// Keep the URL in sync.
					var url = new URL(window.location.href);
					if (paged > 1) {
						url.searchParams.set('paged', paged);
					} else {
						url.searchParams.delete('paged');
					}
					window.history.replaceState({}, '', url.toString());

					// Reset checkboxes and delete button.
					$('#aips-cb-select-all').prop('checked', false);
					self.updateDeleteButton();
				},
				error: function () {
					AIPS.Utilities.showToast(aipsHistoryL10n.errorReloading || 'Failed to reload history.', 'error');
				},
				complete: function () {
					$reloadBtn.prop('disabled', false).html(origHtml);
				}
			});
		},

		/**
		 * Handle a pagination link click.
		 *
		 * @param {Event} e - Click event from a pagination element.
		 */
		loadPage: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			if ($btn.prop('disabled')) {
				return;
			}
			var page = $btn.data('page');
			if (!page) {
				return;
			}
			this.reload(parseInt(page, 10));
		},

		/**
		 * Apply the current status filter and trigger a server reload.
		 *
		 * @param {Event} e
		 */
		applyFilter: function (e) {
			if (e) {
				e.preventDefault();
			}
			this.statusFilter = $('#aips-filter-status').val() || '';

			// Reflect change in the URL without reloading.
			var url = new URL(window.location.href);
			if (this.statusFilter) {
				url.searchParams.set('status', this.statusFilter);
			} else {
				url.searchParams.delete('status');
			}
			url.searchParams.delete('paged');
			window.history.pushState({}, '', url.toString());

			this.reload(1);
		},

		/**
		 * Live client-side row filter as the user types.
		 * Stores the raw input value in searchQuery so server calls use the
		 * exact string the user typed (not a lowercased copy).
		 *
		 * @param {Event} e
		 */
		onSearchInput: function (e) {
			var rawQuery   = $(e.target).val();
			this.searchQuery = rawQuery;
			this.syncSearchClearButton();

			var lowerQuery = rawQuery.toLowerCase().trim();
			var hasResults = false;

			$('#aips-history-tbody tr').each(function () {
				if (!lowerQuery || $(this).text().toLowerCase().indexOf(lowerQuery) !== -1) {
					$(this).show();
					hasResults = true;
				} else {
					$(this).hide();
				}
			});

			$('#aips-history-search-no-results').toggle(!hasResults && lowerQuery.length > 0);
		},

		/**
		 * Trigger a server-side reload when Enter is pressed in the search box.
		 *
		 * @param {Event} e
		 */
		onSearchKeydown: function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				this.searchQuery = $('#aips-history-search-input').val();

				var url = new URL(window.location.href);
				if (this.searchQuery) {
					url.searchParams.set('s', this.searchQuery);
				} else {
					url.searchParams.delete('s');
				}
				url.searchParams.delete('paged');
				window.history.pushState({}, '', url.toString());

				this.reload(1);
			}
		},

		/**
		 * Clear the search input and trigger a server reload.
		 *
		 * @param {Event} e
		 */
		clearSearch: function (e) {
			if (e) {
				e.preventDefault();
			}
			$('#aips-history-search-input').val('');
			this.searchQuery = '';
			this.syncSearchClearButton();
			$('#aips-history-search-no-results').hide();
			$('#aips-history-tbody tr').show();
			this.reload(1);
		},

		/**
		 * Show or hide the inline search clear button.
		 */
		syncSearchClearButton: function () {
			var hasValue = $('#aips-history-search-input').val().trim().length > 0;
			$('#aips-history-search-clear').toggle(hasValue);
		},

		/* ------------------------------------------------------------------ */
		/* Export                                                              */
		/* ------------------------------------------------------------------ */

		/**
		 * Trigger the CSV export via a hidden form POST.
		 *
		 * @param {Event} e - Click event from `#aips-export-history-btn`.
		 */
		exportHistory: function (e) {
			e.preventDefault();

			var form = $('<form method="POST" action="' + aipsAjax.ajaxUrl + '" style="display:none;"></form>');
			form.append($('<input type="hidden" name="action" value="aips_export_history">'));
			form.append($('<input type="hidden" name="nonce">').val(aipsAjax.nonce));
			form.append($('<input type="hidden" name="status">').val(this.statusFilter));
			form.append($('<input type="hidden" name="search">').val(this.searchQuery));
			$('body').append(form);
			form.submit();
			form.remove();
		},

		/* ------------------------------------------------------------------ */
		/* Utility                                                             */
		/* ------------------------------------------------------------------ */

		/**
		 * HTML-escape a string for safe inline output.
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
	};

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function () {
		AIPS.History.init();
	});

})(jQuery);
