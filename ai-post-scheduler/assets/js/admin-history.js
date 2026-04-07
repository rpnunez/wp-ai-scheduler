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

			var self         = this;
			var $modal       = $('#aips-history-logs-modal');
			var $content     = $('#aips-history-logs-content');
			var $title       = $('#aips-history-logs-modal-title');
			var detailsTitle = aipsHistoryL10n.historyDetailsTitle || 'History Details';
			var T            = AIPS.Templates;

			//$title.text(detailsTitle);
			$content.html(T.render('aips-tmpl-history-loading-msg', {
				text: aipsHistoryL10n.loadingLogs || 'Loading logs\u2026'
			}));
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
						$content.html(T.render('aips-tmpl-history-error-msg', {
							message: response.data && response.data.message
								? response.data.message
								: (aipsHistoryL10n.errorLoading || 'Error loading logs.')
						}));
						return;
					}

					var container = response.data.container;
					var logs      = response.data.logs;

					//$title.text(detailsTitle);

					$content.html(self.renderLogsModalContent(container, logs));
				},
				error: function () {
					$content.html(T.render('aips-tmpl-history-error-msg', {
						message: aipsHistoryL10n.errorLoading || 'Error loading logs.'
					}));
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
			var $button = $(e.currentTarget);
			var targetSelector = $button.data('target');
			var $target = $(targetSelector);
			if ($target.length) {
				var showLabel = aipsHistoryL10n.showDetails || 'Show details';
				var hideLabel = aipsHistoryL10n.hideDetails || 'Hide details';

				$target.slideToggle(150, function () {
					$button.text($target.is(':visible') ? hideLabel : showLabel);
				});
			}
		},

		/**
		 * Build the HTML for the logs modal body using AIPS.Templates.
		 *
		 * @param {Object}   container History container summary fields.
		 * @param {Object[]} logs      Array of log entry objects.
		 * @return {string} HTML string.
		 */
		renderLogsModalContent: function (container, logs) {
			var self = this;
			var T    = AIPS.Templates;
			var html = '';

			// ---- Container summary ----
			var rows = '';

			rows += T.render('aips-tmpl-history-summary-row', {
				label: aipsHistoryL10n.labelContainerId || 'Container ID',
				value: container.id ? String(container.id) : ''
			});

			if (container.generated_title) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelTitle || 'Title',
					value: container.generated_title
				});
			}
			if (container.template_name) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelTemplate || 'Template',
					value: container.template_name
				});
			}

			var statusClass = container.status === 'completed' ? 'aips-badge-success'
				: (container.status === 'failed' ? 'aips-badge-error' : 'aips-badge-neutral');
			rows += T.renderRaw('aips-tmpl-history-summary-status-row', {
				label:       T.escape(aipsHistoryL10n.labelStatus || 'Status'),
				statusClass: T.escape(statusClass),
				status:      T.escape(container.status)
			});

			if (container.created_at) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCreated || 'Created',
					value: container.created_at
				});
			}
			if (container.completed_at) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCompleted || 'Completed',
					value: container.completed_at
				});
			}
			if (container.error_message) {
				rows += T.render('aips-tmpl-history-summary-error-row', {
					label:   aipsHistoryL10n.labelError || 'Error',
					message: container.error_message
				});
			}
			if (container.post_id) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelPostId || 'Post ID',
					value: String(container.post_id)
				});
			}

			html += T.renderRaw('aips-tmpl-history-modal-summary', { rows: rows });

			// ---- Log entries ----
			html += T.renderRaw('aips-tmpl-history-logs-heading', {
				heading: T.escape(aipsHistoryL10n.logsHeading || 'Log Entries'),
				count:   logs.length
			});

			if (logs.length === 0) {
				html += T.render('aips-tmpl-history-no-logs', {
					message: aipsHistoryL10n.noLogsFound || 'No log entries found for this container.'
				});
				return html;
			}

			var rowsHtml = '';
			$.each(logs, function (i, log) {
				var typeClass   = self.typeClass(log.history_type_id);
				var message     = (log.details && log.details.message) ? log.details.message : '';
				var detailsHtml = '';

				if (message) {
					detailsHtml += T.render('aips-tmpl-history-log-message', { message: message });
				}

				// Render extra details (input/output/context) as a collapsible block.
				var extra = {};
				$.each(log.details, function (key, val) {
					if (key !== 'message' && key !== 'timestamp') {
						extra[key] = val;
					}
				});

				if (Object.keys(extra).length > 0) {
					detailsHtml += T.render('aips-tmpl-history-log-detail-block', {
						rowId:     'aips-log-detail-' + i,
						showLabel: aipsHistoryL10n.showDetails || 'Show details',
						details:   JSON.stringify(extra, null, 2)
					});
				}

				rowsHtml += T.renderRaw('aips-tmpl-history-log-row', {
					timestamp:   T.escape(log.timestamp),
					typeClass:   T.escape(typeClass),
					typeLabel:   T.escape(log.type_label),
					logType:     T.escape(log.log_type),
					detailsHtml: detailsHtml
				});
			});

			html += T.renderRaw('aips-tmpl-history-logs-table', {
				colTimestamp: T.escape(aipsHistoryL10n.colTimestamp || 'Timestamp'),
				colType:      T.escape(aipsHistoryL10n.colType || 'Type'),
				colLogType:   T.escape(aipsHistoryL10n.colLogType || 'Log Type'),
				colDetails:   T.escape(aipsHistoryL10n.colDetails || 'Details'),
				rows:         rowsHtml
			});

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
			var self     = this;

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
						self.reload();
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
		 * Build HTML for all history table rows from a JSON items array using
		 * the AIPS.Templates engine and the script templates defined in history.php.
		 *
		 * @param {Object[]} items Array of history item objects returned by the server.
		 * @return {string} HTML string ready to be inserted into the tbody.
		 */
		renderHistoryRows: function (items) {
			var T       = AIPS.Templates;
			var rowsHtml = '';

			$.each(items, function (i, item) {
				// Title cell HTML.
				var titleHtml;
				if (item.edit_url) {
					titleHtml = T.renderRaw('aips-tmpl-history-row-title-link', {
						editUrl: T.escape(item.edit_url),
						title:   T.escape(item.generated_title || aipsHistoryL10n.untitled || 'Untitled')
					});
				} else {
					titleHtml = T.render('aips-tmpl-history-row-title-text', {
						title: item.generated_title || aipsHistoryL10n.untitled || 'Untitled'
					});
				}

				// Inline error message (failed items only).
				var errorHtml = '';
				if (item.status === 'failed' && item.error_message) {
					errorHtml = T.render('aips-tmpl-history-row-error', {
						message: item.error_message
					});
				}

				// Template/topic label — use the pre-formatted string from the server
				// to avoid i18n-unsafe JS placeholder substitution.
				var templateText = item.template_label || '-';

				// Status badge.
				var statusMap = {
					'completed':  { cls: 'aips-badge-success', icon: 'yes-alt' },
					'failed':     { cls: 'aips-badge-error',   icon: 'dismiss' },
					'processing': { cls: 'aips-badge-info',    icon: 'update' }
				};
				var statusInfo = statusMap[item.status] || { cls: 'aips-badge-neutral', icon: 'minus' };
				var statusLabel = item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : '';
				var statusHtml = T.renderRaw('aips-tmpl-history-row-status-badge', {
					statusClass: T.escape(statusInfo.cls),
					icon:        T.escape(statusInfo.icon),
					label:       T.escape(statusLabel)
				});

				// Action buttons.
				var actionsHtml = T.renderRaw('aips-tmpl-history-action-view-logs', {
					id:    T.escape(String(item.id)),
					title: T.escape(aipsHistoryL10n.viewLogs || 'View Logs'),
					label: T.escape(aipsHistoryL10n.viewLogs || 'View Logs')
				});

				if (item.post_id && item.post_url) {
					actionsHtml += T.renderRaw('aips-tmpl-history-action-view-post', {
						postUrl: T.escape(item.post_url),
						title:   T.escape(aipsHistoryL10n.viewPost || 'View Post'),
						label:   T.escape(aipsHistoryL10n.viewPost || 'View')
					});
				} else if (item.status === 'processing') {
					actionsHtml += T.renderRaw('aips-tmpl-history-action-view-session', {
						id:    T.escape(String(item.id)),
						title: T.escape(aipsHistoryL10n.viewSession || 'View Session'),
						label: T.escape(aipsHistoryL10n.viewSession || 'View Session')
					});
				}

				if (item.status === 'failed' && item.template_id) {
					actionsHtml += T.renderRaw('aips-tmpl-history-action-retry', {
						id:    T.escape(String(item.id)),
						title: T.escape(aipsHistoryL10n.retryGeneration || 'Retry Generation'),
						label: T.escape(aipsHistoryL10n.retry || 'Retry')
					});
				}

				// Assemble the full row.
				rowsHtml += T.renderRaw('aips-tmpl-history-row', {
					id:           T.escape(String(item.id)),
					selectLabel:  T.escape(aipsHistoryL10n.selectItem || 'Select Item'),
					titleHtml:    titleHtml,
					errorHtml:    errorHtml,
					templateText: T.escape(templateText),
					statusHtml:   statusHtml,
					createdAt:    T.escape(item.created_at_formatted || item.created_at || ''),
					actionsHtml:  actionsHtml
				});
			});

			return rowsHtml;
		},

		/**
		 * Build HTML for the pagination footer from the pagination data returned
		 * by the server, using the AIPS.Templates engine.
		 *
		 * @param {Object} pagination Pagination data: { total, pages, current_page }.
		 * @return {string} HTML string ready to be inserted into the pagination cell.
		 */
		renderHistoryPagination: function (pagination) {
			var T           = AIPS.Templates;
			var current     = pagination.current_page;
			var pages       = pagination.pages;
			// Use the pre-formatted label from the server (i18n-safe sprintf done in PHP).
			var totalItems  = pagination.items_label || String(pagination.total);

			if (pages <= 1) {
				return T.render('aips-tmpl-history-pagination-simple', { totalItems: totalItems });
			}

			// Build numbered page buttons.
			var start    = Math.max(1, current - 3);
			var end      = Math.min(pages, current + 3);
			var pagesHtml = '';

			if (start > 1) {
				pagesHtml += T.renderRaw('aips-tmpl-history-pagination-page-btn', {
					btnClass:   T.escape('aips-btn-secondary'),
					page:       T.escape('1'),
					ariaCurrent: ''
				});
				if (start > 2) {
					pagesHtml += T.render('aips-tmpl-history-pagination-ellipsis', {});
				}
			}

			for (var p = start; p <= end; p++) {
				var isActive = (p === current);
				pagesHtml += T.renderRaw('aips-tmpl-history-pagination-page-btn', {
					btnClass:   T.escape(isActive ? 'aips-btn-primary' : 'aips-btn-secondary'),
					page:       T.escape(String(p)),
					ariaCurrent: isActive ? 'aria-current="page"' : ''
				});
			}

			if (end < pages) {
				if (end < pages - 1) {
					pagesHtml += T.render('aips-tmpl-history-pagination-ellipsis', {});
				}
				pagesHtml += T.renderRaw('aips-tmpl-history-pagination-page-btn', {
					btnClass:   T.escape('aips-btn-secondary'),
					page:       T.escape(String(pages)),
					ariaCurrent: ''
				});
			}

			return T.renderRaw('aips-tmpl-history-pagination', {
				totalItems:  T.escape(totalItems),
				prevPage:    T.escape(String(current - 1)),
				prevDisabled: current <= 1 ? 'disabled' : '',
				prevLabel:   T.escape(aipsHistoryL10n.prevPage || 'Previous page'),
				pages:       pagesHtml,
				nextPage:    T.escape(String(current + 1)),
				nextDisabled: current >= pages ? 'disabled' : '',
				nextLabel:   T.escape(aipsHistoryL10n.nextPage || 'Next page')
			});
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
				$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-loading', {
					text: aipsHistoryL10n.loading || 'Loading\u2026'
				}));
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

					var items = response.data.items || [];

					if ($tbody.length) {
						if (items.length) {
							$tbody.html(self.renderHistoryRows(items));
							$('#aips-history-search-no-results').hide();
						} else {
							// No results: render a friendly inline empty state.
							$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-empty', {
								message: aipsHistoryL10n.noResultsFound || 'No history containers match your current filters.'
							}));
						}
					}

					// Render pagination using AIPS.Templates.
					if ($pagCell.length && response.data.pagination) {
						$pagCell.html(self.renderHistoryPagination(response.data.pagination));
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

	};

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function () {
		AIPS.History.init();
	});

})(jQuery);
