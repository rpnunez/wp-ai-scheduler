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
		escapeHtml: function (value) {
			return $('<div>').text(value == null ? '' : String(value)).html();
		},

		updateModalHeader: function ($modal, container) {
			var title = container && container.header_title
				? container.header_title
				: (aipsHistoryL10n.historyDetailsTitle || 'History Details');
			var actions = container && Array.isArray(container.header_actions)
				? container.header_actions
				: [];
			var $title = $modal.find('#aips-history-logs-modal-title');
			var $actions = $modal.find('#aips-history-logs-modal-actions');
			var $status = $modal.find('#aips-history-logs-modal-status');
			var statusHtml = '';
			var actionsHtml = '';
			var self = this;

			$title.text(title);

			actions.forEach(function (action) {
				if (!action || !action.url || !action.label) {
					return;
				}

				actionsHtml += '<a href="' + self.escapeHtml(action.url) + '" target="_blank" rel="noopener noreferrer">'
					+ self.escapeHtml(action.label)
					+ '</a>';
			});

			if (container && container.status && container.status_class) {
				statusHtml = '<span class="aips-badge '
					+ self.escapeHtml(container.status_class)
					+ '">'
					+ self.escapeHtml(container.status)
					+ '</span>';
			}

			$actions.html(actionsHtml);
			$status.html(statusHtml);
		},

		/* ------------------------------------------------------------------ */
		/* State                                                                */
		/* ------------------------------------------------------------------ */

		/** @type {string} Current status filter value */
		statusFilter: '',

		/** @type {string} Raw search query as entered by the user */
		searchQuery: '',
		domainFilter: '',
		actorFilter: '',
		correlationId: '',
		dateFrom: '',
		dateTo: '',

		/* ------------------------------------------------------------------ */
		/* Init / events                                                        */
		/* ------------------------------------------------------------------ */

		/**
		 * Initialise the History module.
		 */
		init: function () {
			this.statusFilter = $('#aips-filter-status').val() || '';
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';
			this.searchQuery  = $('#aips-history-search-input').val() || '';
			this.syncSearchClearButton();
			this.bindEvents();
			this.maybeOpenFromQuery();
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
			$(document).on('change', '.aips-json-viewer-toggle', this.toggleJsonViewerMode.bind(this));

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



		/**
		 * Auto-open a specific history container from query args when available.
		 */
		maybeOpenFromQuery: function () {
			var params = new URLSearchParams(window.location.search || '');
			var historyId = parseInt(params.get('history_id') || 0, 10);
			var postId = parseInt(params.get('post_id') || 0, 10);

			if (postId > 0 && !this.searchQuery) {
				this.searchQuery = String(postId);
				$('#aips-history-search-input').val(String(postId));
				this.syncSearchClearButton();
				$('#aips-history-search-input').trigger('input');
			}

			if (historyId > 0) {
				this.openLogsModalFromId(historyId);
			}
		},

		/**
		 * Open history logs modal for a known history id.
		 *
		 * @param {number} historyId History container id.
		 */
		openLogsModalFromId: function (historyId) {
			var $trigger = $('<button type="button" class="aips-view-history-logs" data-id="' + historyId + '"></button>');
			this.openLogsModal({
				preventDefault: function () {},
				stopPropagation: function () {},
				currentTarget: $trigger.get(0)
			});
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
			var T        = AIPS.Templates;

			$title.text(aipsHistoryL10n.historyDetailsTitle || 'History Details');
			$modal.find('#aips-history-logs-modal-actions').empty();
			$modal.find('#aips-history-logs-modal-status').empty();
			$content.html(T.render('aips-tmpl-history-loading-msg', {
				text: aipsHistoryL10n.loadingLogs || 'Loading logs\u2026'
			}));
			$modal.fadeIn(200);

			$.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_history_modal_html',
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
					var modalHtml = response.data.modal_html || '';

					AIPS.History.updateModalHeader($modal, container);
					$content.html(modalHtml);
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
			var targetSelector = $button.data('copy-target');
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
					var rowTypes = String($(this).attr('data-type-ids') || $(this).data('type-id') || '')
						.split(',')
						.map(function (value) {
							return $.trim(String(value));
						})
						.filter(Boolean);
					if (String(typeId) === 'ai_request_response') {
						$(this).toggle(rowTypes.indexOf('5') !== -1 || rowTypes.indexOf('6') !== -1);
						return;
					}

					$(this).toggle(rowTypes.indexOf(String(typeId)) !== -1);
				});
			}
		},

		/**
		 * Toggle JSON tree view vs. raw JSON for the current modal content.
		 *
		 * @param {Event} e Change event.
		 */
		toggleJsonViewerMode: function (e) {
			var $toggle = $(e.currentTarget);
			var $renderer = $toggle.closest('.aips-history-log-renderer');
			if (!$renderer.length) {
				return;
			}

			$renderer.toggleClass('aips-json-viewer-enabled', $toggle.is(':checked'));
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
					domain: self.domainFilter,
					actor: self.actorFilter,
					correlation_id: self.correlationId,
					date_from: self.dateFrom,
					date_to: self.dateTo,
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
			this.domainFilter = $('#aips-filter-domain').val() || '';
			this.actorFilter = $('#aips-filter-actor').val() || '';
			this.correlationId = $('#aips-filter-correlation').val() || '';
			this.dateFrom = $('#aips-filter-date-from').val() || '';
			this.dateTo = $('#aips-filter-date-to').val() || '';

			// Reflect change in the URL without reloading.
			var url = new URL(window.location.href);
			[['status', this.statusFilter], ['domain', this.domainFilter], ['actor', this.actorFilter], ['correlation_id', this.correlationId], ['date_from', this.dateFrom], ['date_to', this.dateTo]].forEach(function (entry) {
				if (entry[1]) {
					url.searchParams.set(entry[0], entry[1]);
				} else {
					url.searchParams.delete(entry[0]);
				}
			});
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
			form.append($('<input type="hidden" name="domain">').val(this.domainFilter));
			form.append($('<input type="hidden" name="actor">').val(this.actorFilter));
			form.append($('<input type="hidden" name="correlation_id">').val(this.correlationId));
			form.append($('<input type="hidden" name="date_from">').val(this.dateFrom));
			form.append($('<input type="hidden" name="date_to">').val(this.dateTo));
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
