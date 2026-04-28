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

		/** @type {string} Current view mode: 'operations' or 'all' */
		currentViewMode: 'operations',

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
			this.switchViewMode('operations');
		},

		/**
		 * Register all event listeners for the History admin page.
		 */
		bindEvents: function () {
			// Open logs modal.
			$(document).on('click', '.aips-view-history-logs', this.openLogsModal.bind(this));

			// Collapsible log-detail sections inside the modal.
			$(document).on('click', '.aips-log-toggle', this.toggleLogDetail.bind(this));

			// Copy log detail to clipboard.
			$(document).on('click', '.aips-log-copy', this.copyLogDetail.bind(this));

			// Log type filter tabs inside the modal.
			$(document).on('click', '.aips-log-type-filter-btn', this.filterLogsByType.bind(this));

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

			// View mode toggle (Operations / All Items).
			$(document).on('click', '.aips-view-mode-btn', this.onViewModeClick.bind(this));

			// Operation type filter (operations view).
			$(document).on('change', '#aips-filter-operation-type', this.onOperationTypeFilter.bind(this));

			// Expand/collapse child rows in operations view.
			$(document).on('click', '#aips-history-operations-tbody .aips-toggle-children', this.onToggleChildren.bind(this));
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

			var historyId = $(e.currentTarget).data('id') || $(e.currentTarget).data('history-id');
			if (!historyId) {
				return;
			}

			var self     = this;
			var $modal   = $('#aips-history-logs-modal');
			var $content = $('#aips-history-logs-content');
			var $title   = $('#aips-history-logs-modal-title');
			var T        = AIPS.Templates;

			$title.text(aipsHistoryL10n.historyDetailsTitle || 'History Details');
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

					// Set modal title to the generated post title when available.
					if (container.generated_title) {
						$title.text(container.generated_title);
					} else {
						$title.text(
							(aipsHistoryL10n.historyDetailsTitle || 'History Details')
							+ ' #' + container.id
						);
					}

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
		 * Show temporary copied-state feedback on a copy button.
		 *
		 * @param {jQuery} $button    Copy button element.
		 * @param {string} copyLabel   Default button label.
		 * @param {string} copiedLabel Success button label.
		 */
		showCopySuccess: function ($button, copyLabel, copiedLabel) {
			$button.text(copiedLabel);
			setTimeout(function () {
				$button.text(copyLabel);
			}, 2000);
		},

		/**
		 * Copy log detail text using the legacy execCommand fallback.
		 *
		 * @param {jQuery} $target Detail container.
		 * @return {boolean} True when the fallback copy succeeded.
		 */
		copyLogDetailFallback: function ($target) {
			var $pre = $target.find('pre');
			var range;
			var sel;

			if (!$pre.length || !$pre[0]) {
				return false;
			}

			try {
				// Fallback: expose the detail block, select text, copy.
				$target.show();
				range = document.createRange();
				range.selectNodeContents($pre[0]);
				sel = window.getSelection();

				if (!sel) {
					return false;
				}

				sel.removeAllRanges();
				sel.addRange(range);

				if (!document.execCommand('copy')) {
					sel.removeAllRanges();
					return false;
				}

				sel.removeAllRanges();

				return true;
			} catch (error) {
				if (sel) {
					sel.removeAllRanges();
				}

				return false;
			}
		},

		/**
		 * Copy the full log detail text for a row.
		 *
		 * @param {Event} e Click event.
		 */
		copyLogDetail: function (e) {
			e.preventDefault();

			var self           = this;
			var $button        = $(e.currentTarget);
			var targetSelector = $button.data('target');
			var $target        = $(targetSelector);
			var text           = $target.find('pre').text();
			var copyLabel      = aipsHistoryL10n.copyDetails || 'Copy';
			var copiedLabel    = aipsHistoryL10n.copiedDetails || 'Copied!';

			if (!text) {
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text)
					.then(function () {
						self.showCopySuccess($button, copyLabel, copiedLabel);
					})
					.catch(function () {
						if (self.copyLogDetailFallback($target)) {
							self.showCopySuccess($button, copyLabel, copiedLabel);
						}
					});
			} else if (self.copyLogDetailFallback($target)) {
				self.showCopySuccess($button, copyLabel, copiedLabel);
			}
		},

		/**
		 * Filter the visible log rows to only those matching the selected type.
		 *
		 * @param {Event} e - Click event from an `.aips-log-type-filter-btn` element.
		 */
		filterLogsByType: function (e) {
			e.preventDefault();
			var $btn    = $(e.currentTarget);
			var typeId  = $btn.data('type-id');
			var $modal  = $('#aips-history-logs-modal');

			$modal.find('.aips-log-type-filter-btn')
				.removeClass('aips-btn-primary')
				.addClass('aips-btn-ghost');
			$btn.removeClass('aips-btn-ghost').addClass('aips-btn-primary');

			var $rows = $modal.find('.aips-history-logs-table tbody tr');
			if (!typeId || typeId === 'all') {
				$rows.show();
			} else {
				$rows.each(function () {
					var rowType = $(this).data('type-id');
					$(this).toggle(String(rowType) === String(typeId));
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

			if (container.creation_method) {
				var methodLabel = container.creation_method.replace(/_/g, ' ');
				methodLabel = methodLabel.charAt(0).toUpperCase() + methodLabel.slice(1);
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCreationMethod || 'Method',
					value: methodLabel
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
					value: this.formatTimestamp(container.created_at)
				});
			}
			if (container.completed_at) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelCompleted || 'Completed',
					value: this.formatTimestamp(container.completed_at)
				});
			}

			// Duration row.
			if (container.duration_seconds !== null && container.duration_seconds !== undefined) {
				rows += T.render('aips-tmpl-history-summary-duration-row', {
					label: aipsHistoryL10n.labelDuration || 'Duration',
					value: AIPS.DateTime.formatDuration(container.duration_seconds)
				});
			}

			if (container.error_message) {
				rows += T.render('aips-tmpl-history-summary-error-row', {
					label:   aipsHistoryL10n.labelError || 'Error',
					message: container.error_message
				});
			}

			// Post link row.
			if (container.post_id && container.post_url && container.post_edit_url) {
				rows += T.renderRaw('aips-tmpl-history-summary-post-row', {
					label:     T.escape(aipsHistoryL10n.labelPostId || 'Post'),
					url:       T.escape(container.post_url),
					postId:    T.escape(String(container.post_id)),
					editUrl:   T.escape(container.post_edit_url || ''),
					editLabel: T.escape(aipsHistoryL10n.editPostLabel || 'Edit')
				});
			} else if (container.post_id) {
				rows += T.render('aips-tmpl-history-summary-row', {
					label: aipsHistoryL10n.labelPostId || 'Post ID',
					value: String(container.post_id)
				});
			}

			html += T.renderRaw('aips-tmpl-history-modal-summary', { rows: rows });

			// ---- Log type filter toolbar ----
			var typeCounts = { all: logs.length };
			$.each(logs, function (i, log) {
				var tid = String(log.history_type_id);
				typeCounts[tid] = (typeCounts[tid] || 0) + 1;
			});

			if (logs.length > 0) {
				var filterButtons = '';

				// "All" button.
				filterButtons += T.renderRaw('aips-tmpl-history-log-type-btn', {
					typeId:      T.escape('all'),
					activeClass: T.escape('aips-btn-primary'),
					label:       T.escape(aipsHistoryL10n.filterAll || 'All'),
					count:       T.escape(String(typeCounts.all))
				});

				// Per-type buttons (only types that appear in the log set).
				var typeOrder = [2, 3, 4, 5, 6, 8, 1, 7, 9, 10];
				$.each(typeOrder, function (i, tid) {
					if (!typeCounts[String(tid)]) {
						return;
					}
					var typeLabel = (aipsHistoryL10n.typeLabels && aipsHistoryL10n.typeLabels[tid])
						|| self.typeLabelFallback(tid);
					filterButtons += T.renderRaw('aips-tmpl-history-log-type-btn', {
						typeId:      T.escape(String(tid)),
						activeClass: T.escape('aips-btn-ghost'),
						label:       T.escape(typeLabel),
						count:       T.escape(String(typeCounts[String(tid)]))
					});
				});

				html += T.renderRaw('aips-tmpl-history-log-type-filter', {
					filterLabel: T.escape(aipsHistoryL10n.filterByType || 'Filter:'),
					buttons:     filterButtons
				});
			}

			// ---- Log entries heading ----
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
						copyLabel: aipsHistoryL10n.copyDetails  || 'Copy',
						details:   JSON.stringify(extra, null, 2)
					});
				}

				rowsHtml += T.renderRaw('aips-tmpl-history-log-row', {
					timestamp:   T.escape(self.formatTimestamp(log.timestamp)),
					typeClass:   T.escape(typeClass),
					typeLabel:   T.escape(log.type_label),
					logType:     T.escape(log.log_type),
					detailsHtml: detailsHtml,
					typeId:      T.escape(String(log.history_type_id))
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
		 * Format a Unix timestamp for display.
		 *
		 * @param {number|string} timestamp
		 * @return {string}
		 */
		formatTimestamp: function (timestamp) {
			var ts = parseInt(timestamp, 10);
			if (isNaN(ts) || ts <= 0) {
				return '';
			}
			return new Date(ts * 1000).toLocaleString();
		},

		/**
		 * Return a fallback human-readable label for a history type ID.
		 *
		 * Used when the server-side l10n map is not available for a given type.
		 *
		 * @param {number} typeId
		 * @return {string}
		 */
		typeLabelFallback: function (typeId) {
			var map = {
				1:  'Log',
				2:  'Error',
				3:  'Warning',
				4:  'Info',
				5:  'AI Request',
				6:  'AI Response',
				7:  'Debug',
				8:  'Activity',
				9:  'Session',
				10: 'Metric'
			};
			return map[typeId] || 'Unknown';
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
		 * Refreshes the table via AJAX using self.reload() upon success to avoid
		 * a full page reload and preserve context.
		 *
		 * @param {Event} e - Click event from an `.aips-retry-generation` element.
		 */
		retryGeneration: function (e) {
			e.preventDefault();

			var self     = this;
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
		 * Reload the history table via AJAX applying the current filter and search.
		 *
		 * @param {number} [paged=1] 1-based page number to load.
		 */
		reload: function (paged) {
			paged = (paged === undefined || paged === null) ? 1 : Math.max(1, parseInt(paged, 10));

			if (this.currentViewMode === 'operations') {
				this.loadOperationsView(paged);
				return;
			}

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

					var itemsHtml = response.data.items_html || '';

					if ($tbody.length) {
						if (itemsHtml) {
							$tbody.html(itemsHtml);
							$('#aips-history-search-no-results').hide();
						} else {
							// No results: render a friendly inline empty state.
							$tbody.html(AIPS.Templates.render('aips-tmpl-history-tbody-empty', {
								message: aipsHistoryL10n.noResultsFound || 'No history containers match your current filters.'
							}));
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
			if (this.currentViewMode === 'operations') {
				this.loadOperationsView(parseInt(page, 10));
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

			if (this.currentViewMode === 'operations') {
				this.loadOperationsView(1);
				return;
			}

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

			var $rows = this.currentViewMode === 'operations'
				? $('#aips-history-operations-tbody tr')
				: $('#aips-history-tbody tr');

			$rows.each(function () {
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

				if (this.currentViewMode === 'operations') {
					this.loadOperationsView(1);
					return;
				}

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
			$('#aips-history-tbody tr, #aips-history-operations-tbody tr').show();

			if (this.currentViewMode === 'operations') {
				this.loadOperationsView(1);
				return;
			}

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
		/* Operations (hierarchical) view                                      */
		/* ------------------------------------------------------------------ */

		/**
		 * Handle a view mode toggle button click.
		 *
		 * @param {Event} e
		 */
		onViewModeClick: function (e) {
			e.preventDefault();
			var mode = $(e.currentTarget).data('view-mode');
			if (mode) {
				this.switchViewMode(mode);
			}
		},

		/**
		 * Switch between 'operations' and 'all' views.
		 *
		 * @param {string} mode 'operations' or 'all'
		 */
		switchViewMode: function (mode) {
			this.currentViewMode = mode;

			var $opTable   = $('#aips-history-operations-table');
			var $allTable  = $('.aips-history-table:not(#aips-history-operations-table)');
			var $opFilter  = $('#aips-filter-operation-type');
			var $allFilter = $('#aips-filter-status');

			$('.aips-view-mode-btn').removeClass('aips-btn-primary').addClass('aips-btn-secondary');
			$('.aips-view-mode-btn[data-view-mode="' + mode + '"]')
				.removeClass('aips-btn-secondary').addClass('aips-btn-primary');

			if (mode === 'operations') {
				$opTable.show();
				$allTable.hide();
				$opFilter.show();
				this.loadOperationsView(1);
			} else {
				$opTable.hide();
				$allTable.show();
				$opFilter.hide();
				this.reload(1);
			}
		},

		/**
		 * Handle operation type filter changes (operations view).
		 */
		onOperationTypeFilter: function () {
			this.loadOperationsView(1);
		},

		/**
		 * Load top-level operation containers via AJAX into the operations tbody.
		 *
		 * @param {number} [paged=1]
		 */
		loadOperationsView: function (paged) {
			paged = (paged === undefined || paged === null) ? 1 : Math.max(1, parseInt(paged, 10));

			var self   = this;
			var $tbody = $('#aips-history-operations-tbody');
			var T      = AIPS.Templates;

			$tbody.html(T.render('aips-tmpl-history-operations-loading', {
				text: aipsHistoryL10n.loading || 'Loading\u2026'
			}));

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action:         'aips_get_history_top_level',
					nonce:          aipsAjax.nonce,
					paged:          paged,
					operation_type: $('#aips-filter-operation-type').val() || '',
					status:         self.statusFilter,
					search:         self.searchQuery
				},
				success: function (response) {
					if (!response.success) {
						$tbody.html(T.render('aips-tmpl-history-operations-empty', {
							message: aipsHistoryL10n.noResultsFound || 'No operations found.'
						}));
						return;
					}

					var items = response.data.items;
					if (!items || items.length === 0) {
						$tbody.html(T.render('aips-tmpl-history-operations-empty', {
							message: aipsHistoryL10n.noOperationsFound || 'No operations found.'
						}));
						return;
					}

					var html = '';
					$.each(items, function (i, item) {
						html += self.renderParentRow(item);
					});
					$tbody.html(html);

					if (response.data.pagination_html !== undefined) {
						$('.aips-history-pagination-cell').html(response.data.pagination_html);
					}

					if (response.data.stats) {
						$('#aips-stat-total').text(response.data.stats.total);
						$('#aips-stat-completed').text(response.data.stats.completed);
						$('#aips-stat-failed').text(response.data.stats.failed);
						$('#aips-stat-success-rate').text(response.data.stats.success_rate + '%');
					}

					var url = new URL(window.location.href);
					if (paged > 1) {
						url.searchParams.set('paged', paged);
					} else {
						url.searchParams.delete('paged');
					}
					window.history.replaceState({}, '', url.toString());
				},
				error: function () {
					$tbody.html(T.render('aips-tmpl-history-operations-empty', {
						message: aipsHistoryL10n.errorReloading || 'Failed to load operations.'
					}));
				}
			});
		},

		/**
		 * Render a parent (operation) row from template.
		 *
		 * @param  {Object} item
		 * @return {string} HTML
		 */
		renderParentRow: function (item) {
			var T     = AIPS.Templates;
			var cs    = item.child_summary || {};
			var total = cs.total || 0;

			// Roll-up status.
			var status      = item.status || 'processing';
			var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
			if (cs.failed_count > 0) {
				status      = 'error';
				statusLabel = aipsHistoryL10n.statusPartialError || 'Partial/Error';
			} else if (status === 'completed') {
				statusLabel = aipsHistoryL10n.statusCompleted || 'Completed';
			} else if (status === 'processing') {
				statusLabel = aipsHistoryL10n.statusProcessing || 'In Progress';
			}

			var toggleBtn = '';
			if (total > 0) {
				toggleBtn = '<button class="aips-toggle-children button-link" aria-label="Expand">'
					+ '<span class="dashicons dashicons-arrow-right"></span></button>';
			}

			var childrenText = total > 0
				? ((cs.completed_count || 0) + ' / ' + total)
				: '\u2014';

			var triggerLabel = item.trigger_name === 'cron'
				? (aipsHistoryL10n.triggerScheduled || 'Scheduled')
				: (aipsHistoryL10n.triggerManual || 'Manual');

			var duration = '\u2014';
			if (item.created_at && item.completed_at) {
				var secs = parseInt(item.completed_at, 10) - parseInt(item.created_at, 10);
				if (!isNaN(secs) && secs >= 0) {
					duration = AIPS.DateTime ? AIPS.DateTime.formatDuration(secs) : (secs + 's');
				}
			}

			var date = item.created_at
				? this.formatTimestamp(item.created_at)
				: '\u2014';

			return T.renderRaw('aips-tmpl-history-parent-row', {
				id:             T.escape(String(item.id)),
				status:         T.escape(status),
				operationLabel: T.escape(item.operation_label || item.creation_method || ''),
				childrenText:   T.escape(childrenText),
				statusLabel:    T.escape(statusLabel),
				triggerLabel:   T.escape(triggerLabel),
				date:           T.escape(date),
				duration:       T.escape(duration),
				toggleBtn:      toggleBtn
			});
		},

		/**
		 * Handle expand/collapse of child rows for a parent row.
		 *
		 * @param {Event} e
		 */
		onToggleChildren: function (e) {
			e.preventDefault();
			var $btn    = $(e.currentTarget);
			var $row    = $btn.closest('tr');
			var id      = $row.data('id');
			var isOpen  = $row.data('expanded') === 1 || $row.data('expanded') === '1';

			if (isOpen) {
				// Collapse: hide and remove child rows.
				$('#aips-history-operations-tbody tr.aips-history-child-row[data-parent-id="' + id + '"]').remove();
				$row.data('expanded', 0);
				$btn.find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
				return;
			}

			// Expand: fetch children via AJAX.
			var self = this;
			var T    = AIPS.Templates;

			$row.data('expanded', 1);
			$btn.find('.dashicons').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action:     'aips_get_operation_children',
					nonce:      aipsAjax.nonce,
					history_id: id
				},
				success: function (response) {
					if (!response.success) {
						return;
					}
					var children = response.data.children;
					var childHtml = '';
					$.each(children, function (i, child) {
						child.parent_id = child.parent_id || id;
						childHtml += self.renderChildRow(child);
					});
					$row.after(childHtml);
					$('#aips-history-operations-tbody tr.aips-history-child-row[data-parent-id="' + id + '"]').show();
				}
			});
		},

		/**
		 * Render a child row from template.
		 *
		 * @param  {Object} child
		 * @return {string} HTML
		 */
		renderChildRow: function (child) {
			var T      = AIPS.Templates;
			var status = child.status || 'processing';
			if (status === 'failed') {
				status = 'error';
			}
			var statusLabel = status === 'completed'
				? (aipsHistoryL10n.statusCompleted || 'Completed')
				: (status === 'error' ? (aipsHistoryL10n.statusError || 'Error') : (aipsHistoryL10n.statusProcessing || 'Processing'));

			var title = child.generated_title || child.template_name || '(' + (aipsHistoryL10n.noTitle || 'no title') + ')';

			var duration = '\u2014';
			if (child.created_at && child.completed_at) {
				var secs = parseInt(child.completed_at, 10) - parseInt(child.created_at, 10);
				if (!isNaN(secs) && secs >= 0) {
					duration = AIPS.DateTime ? AIPS.DateTime.formatDuration(secs) : (secs + 's');
				}
			}

			var postLink = '';
			if (child.post_id && child.post_url) {
				postLink = ' <a href="' + T.escape(child.post_url) + '" class="button-link" target="_blank">'
					+ T.escape(aipsHistoryL10n.editPostLabel || 'Edit Post') + '</a>';
			}

			return T.renderRaw('aips-tmpl-history-child-row', {
				id:          T.escape(String(child.id)),
				parentId:    T.escape(String(child.parent_id || '')),
				status:      T.escape(status),
				statusLabel: T.escape(statusLabel),
				title:       T.escape(title),
				duration:    T.escape(duration),
				postLink:    postLink
			});
		},

	};

	/* ---------------------------------------------------------------------- */
	/* Document ready                                                          */
	/* ---------------------------------------------------------------------- */
	$(document).ready(function () {
		AIPS.History.init();
	});

})(jQuery);
