/**
 * Telemetry page charts, filters, and table pagination.
 *
 * Relies on `aipsTelemetryL10n` localised by AIPS_Admin_Assets.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Telemetry = {

		/** @type {number} */
		page: 1,

		/** @type {number} */
		totalPages: 1,

		/** @type {number} */
		perPage: 25,

		/** @type {Object.<string, Object>} */
		charts: {},

		/**
		 * Initialise the Telemetry page.
		 *
		 * @return {void}
		 */
		init: function() {
			if (!$('#aips-telemetry-panel').length) {
				return;
			}

			this.perPage = parseInt($('#aips-telemetry-per-page').val(), 10) || 25;
			this.bindEvents();
			this.loadPage(1);
		},

		/**
		 * Register page event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '.aips-telemetry-refresh', this.refresh.bind(this));
			$(document).on('click', '#aips-telemetry-apply-filters', this.applyFilters.bind(this));
			$(document).on('click', '#aips-telemetry-reset-filters', this.resetFilters.bind(this));
			$(document).on('click', '#aips-telemetry-prev', this.prevPage.bind(this));
			$(document).on('click', '#aips-telemetry-next', this.nextPage.bind(this));
			$(document).on('change', '#aips-telemetry-per-page', this.changePerPage.bind(this));
			$(document).on('click', '.aips-telemetry-view-details', this.viewDetails.bind(this));
			$(document).on('click', '.aips-telemetry-payload-toggle', this.togglePayloadSection.bind(this));
		},

		/**
		 * Refresh the current page.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		refresh: function(e) {
			e.preventDefault();
			this.loadPage(this.page);
		},

		/**
		 * Apply the current filter set.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		applyFilters: function(e) {
			e.preventDefault();
			this.loadPage(1);
		},

		/**
		 * Reset all table filters to their defaults.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		resetFilters: function(e) {
			e.preventDefault();

			$('#aips-telemetry-type-filter').val('');
			$('#aips-telemetry-category-filter').val('');
			$('#aips-telemetry-method-filter').val('');
			$('#aips-telemetry-page-filter').val('');
			$('#aips-telemetry-issues-only').prop('checked', false);
			this.loadPage(1);
		},

		/**
		 * Move to the previous table page.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		prevPage: function(e) {
			e.preventDefault();
			if (this.page > 1) {
				this.loadPage(this.page - 1);
			}
		},

		/**
		 * Move to the next table page.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		nextPage: function(e) {
			e.preventDefault();
			if (this.page < this.totalPages) {
				this.loadPage(this.page + 1);
			}
		},

		/**
		 * Apply a new rows-per-page value.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		changePerPage: function(e) {
			this.perPage = parseInt($(e.currentTarget).val(), 10) || 25;
			this.loadPage(1);
		},

		/**
		 * Load telemetry charts and table data for the current filters.
		 *
		 * @param {number} page Page number to fetch.
		 * @return {void}
		 */
		loadPage: function(page) {
			var self = this;
			var $refreshButtons = $('.aips-telemetry-refresh');

			this.setLoadingState(true);
			this.setTableMessage(window.aipsTelemetryL10n.loading || 'Loading...', 'aips-telemetry-loading');
			this.setRefreshButtonLabel($refreshButtons, window.aipsTelemetryL10n.refreshing || 'Refreshing...');
			$refreshButtons.prop('disabled', true);

			$.post(
				ajaxurl,
				{
					action: 'aips_get_telemetry',
					nonce: window.aipsTelemetryL10n.nonce || '',
					page: page,
					per_page: this.perPage,
					type: $('#aips-telemetry-type-filter').val() || '',
					event_category: $('#aips-telemetry-category-filter').val() || '',
					request_method: $('#aips-telemetry-method-filter').val() || '',
					page_search: $('#aips-telemetry-page-filter').val() || '',
					issues_only: $('#aips-telemetry-issues-only').is(':checked') ? '1' : '0',
					start_date: $('#aips-telemetry-start-date').val() || '',
					end_date: $('#aips-telemetry-end-date').val() || ''
				},
				function(response) {
					if (!response || !response.success || !response.data) {
						self.handleRequestFailure();
						return;
					}

					self.page = response.data.page || 1;
					self.totalPages = response.data.total_pages || 1;
					self.perPage = response.data.per_page || self.perPage;

					$('#aips-telemetry-start-date').val(response.data.start_date || '');
					$('#aips-telemetry-end-date').val(response.data.end_date || '');
					self.renderRows(response.data.rows || []);
					self.updatePagination(response.data);
					self.updateCharts(response.data.charts || {});
				}
			).fail(function() {
				self.handleRequestFailure();
			}).always(function() {
				self.setLoadingState(false);
				self.setRefreshButtonLabel($refreshButtons, window.aipsTelemetryL10n.refreshLabel || 'Refresh');
				$refreshButtons.prop('disabled', false);
			});
		},

		/**
		 * Update the refresh button label while preserving its icon.
		 *
		 * @param {jQuery} $button Refresh button element.
		 * @param {string} label Button text label.
		 * @return {void}
		 */
		setRefreshButtonLabel: function($button, label) {
			$button.each(function() {
				var $current = $(this);
				$current.html(AIPS.Templates.render('aips-tmpl-telemetry-refresh-button-content', {
					label: label
				}));
			});
		},

		/**
		 * Handle a failed AJAX request.
		 *
		 * @return {void}
		 */
		handleRequestFailure: function() {
			var message = window.aipsTelemetryL10n.requestFailed || 'Request failed. Please try again.';
			this.setTableMessage(message, 'aips-telemetry-empty');
			if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
				AIPS.Utilities.showToast(message, 'error');
			}
		},

		/**
		 * Set loading/disabled state for pagination controls.
		 *
		 * @param {boolean} isLoading Whether a request is in-flight.
		 * @return {void}
		 */
		setLoadingState: function(isLoading) {
			if (isLoading) {
				$('#aips-telemetry-prev, #aips-telemetry-next, #aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date, #aips-telemetry-type-filter, #aips-telemetry-category-filter, #aips-telemetry-method-filter, #aips-telemetry-page-filter, #aips-telemetry-issues-only, #aips-telemetry-apply-filters, #aips-telemetry-reset-filters').prop('disabled', true);
				return;
			}

			$('#aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date, #aips-telemetry-type-filter, #aips-telemetry-category-filter, #aips-telemetry-method-filter, #aips-telemetry-page-filter, #aips-telemetry-issues-only, #aips-telemetry-apply-filters, #aips-telemetry-reset-filters').prop('disabled', false);
			$('#aips-telemetry-prev').prop('disabled', this.page <= 1);
			$('#aips-telemetry-next').prop('disabled', this.page >= this.totalPages);
		},

		/**
		 * Render a single full-width message row.
		 *
		 * @param {string} message Message text.
		 * @param {string} className CSS class for the message cell.
		 * @return {void}
		 */
		setTableMessage: function(message, className) {
			var $tbody = $('#aips-telemetry-tbody');
			$tbody.html(AIPS.Templates.render('aips-tmpl-telemetry-message-row', {
				message: message,
				class_name: className
			}));
		},

		/**
		 * Render table rows.
		 *
		 * @param {Array} rows Row payload.
		 * @return {void}
		 */
		renderRows: function(rows) {
			var $tbody = $('#aips-telemetry-tbody');
			var dtL10n = this._buildL10n();

			$tbody.empty();
			if (!rows || !rows.length) {
				this.setTableMessage(window.aipsTelemetryL10n.telemetryNoRecords || 'No telemetry records found for the selected range.', 'aips-telemetry-empty');
				return;
			}

			$tbody.html(rows.map(function(row) {
				var insertedAt = AIPS.DateTime.formatRelative(row.inserted_at, dtL10n);
				var categoriesHtml = this.formatCategories(row.event_categories);
				var elapsedSeconds = AIPS.DateTime.formatElapsedSeconds(row.elapsed_ms);

				return AIPS.Templates.renderRaw('aips-tmpl-telemetry-data-row', {
					raw_id: AIPS.Templates.escape(this.displayValue(row.id)),
					type: AIPS.Templates.escape(this.formatTypeLabel(row.type)),
					page: AIPS.Templates.escape(this.displayValue(row.page)),
					event_categories_html: categoriesHtml,
					request_method: AIPS.Templates.escape(this.displayValue(row.request_method)),
					num_queries: AIPS.Templates.escape(this.displayValue(row.num_queries)),
					elapsed_ms: AIPS.Templates.escape(elapsedSeconds),
					inserted_at: AIPS.Templates.escape(insertedAt),
					view_details_label: AIPS.Templates.escape(window.aipsTelemetryL10n.viewDetails || 'View Details')
				});
			}, this).join(''));
		},

		/**
		 * Load and display details for a single telemetry row.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		viewDetails: function(e) {
			e.preventDefault();

			var self = this;
			var $button = $(e.currentTarget);
			var rowId = parseInt($button.attr('data-telemetry-id'), 10) || 0;

			if (rowId < 1) {
				AIPS.Utilities.showToast(window.aipsTelemetryL10n.requestFailed || 'Request failed. Please try again.', 'error');
				return;
			}

			this.openDetailsModal(
				(window.aipsTelemetryL10n.detailsTitle || 'Telemetry Details #%s').replace('%s', rowId),
				AIPS.Templates.render('aips-tmpl-telemetry-details-loading', {
					message: window.aipsTelemetryL10n.loadingDetails || 'Loading telemetry details...'
				})
			);

			$button.prop('disabled', true);

			$.post(
				ajaxurl,
				{
					action: 'aips_get_telemetry_details',
					nonce: window.aipsTelemetryL10n.detailsNonce || '',
					id: rowId
				},
				function(response) {
					if (!response || !response.success || !response.data || !response.data.row) {
						self.handleDetailsFailure();
						return;
					}

					self.renderDetailsModal(response.data);
				}
			).fail(function() {
				self.handleDetailsFailure();
			}).always(function() {
				$button.prop('disabled', false);
			});
		},

		/**
		 * Render the telemetry details modal content.
		 *
		 * @param {Object} data Server response data.
		 * @return {void}
		 */
		renderDetailsModal: function(data) {
			var row = data.row || {};
			var dtL10n = this._buildL10n();
			var detailRows = [
				{ label: window.aipsTelemetryL10n.detailsIdLabel || 'ID', value: this.displayValue(row.id) },
				{ label: window.aipsTelemetryL10n.detailsTypeLabel || 'Type', value: this.displayValue(row.type) },
				{ label: window.aipsTelemetryL10n.detailsPageLabel || 'Page', value: this.displayValue(row.page) },
				{ label: window.aipsTelemetryL10n.detailsCategoriesLabel || 'Categories', value: this.displayValue(row.event_categories) },
				{ label: window.aipsTelemetryL10n.detailsMethodLabel || 'Method', value: this.displayValue(row.request_method) },
				{ label: window.aipsTelemetryL10n.detailsUserIdLabel || 'User ID', value: this.displayValue(row.user_id) },
				{ label: window.aipsTelemetryL10n.detailsEventsLabel || 'Events', value: this.displayValue(row.total_events) },
				{ label: window.aipsTelemetryL10n.detailsCacheCallsLabel || 'Cache Calls', value: this.displayValue(row.cache_calls) },
				{ label: window.aipsTelemetryL10n.detailsCacheHitsLabel || 'Cache Hits', value: this.displayValue(row.cache_hits) },
				{ label: window.aipsTelemetryL10n.detailsCacheMissesLabel || 'Cache Misses', value: this.displayValue(row.cache_misses) },
				{ label: window.aipsTelemetryL10n.detailsQueriesLabel || 'Queries', value: this.displayValue(row.num_queries) },
				{ label: window.aipsTelemetryL10n.detailsSlowQueriesLabel || 'Slow Queries', value: this.displayValue(row.slow_query_count) },
				{ label: window.aipsTelemetryL10n.detailsDuplicateQueriesLabel || 'Duplicate Queries', value: this.displayValue(row.duplicate_query_count) },
				{ label: window.aipsTelemetryL10n.detailsPeakMemoryLabel || 'Peak Memory', value: AIPS.DateTime.formatMemory(row.peak_memory_bytes) + ' (' + this.displayValue(row.peak_memory_bytes) + ' bytes)' },
				{ label: window.aipsTelemetryL10n.detailsElapsedLabel || 'Elapsed', value: AIPS.DateTime.formatElapsed(row.elapsed_ms) },
				{ label: window.aipsTelemetryL10n.detailsInsertedLabel || 'Inserted At', value: AIPS.DateTime.formatRelative(row.inserted_at, dtL10n) }
			];

			var detailRowsHtml = this.renderDetailRows(detailRows);
			var payloadSectionsHtml = this.renderPayloadSections(data.payload_decoded || this.safeParseJson(row.payload));
			var payloadJson = data.payload_decoded
				? JSON.stringify(data.payload_decoded, null, 2)
				: (row.payload || window.aipsTelemetryL10n.payloadEmpty || 'No payload was stored for this telemetry row.');

			var payloadHtml = AIPS.Templates.renderRaw('aips-tmpl-telemetry-details-payload', {
				payload_sections: payloadSectionsHtml,
				raw_payload_json: AIPS.Templates.escape(payloadJson),
				raw_payload_label: AIPS.Templates.escape(window.aipsTelemetryL10n.detailsRawPayloadLabel || 'Raw Payload JSON'),
				raw_payload_help: AIPS.Templates.escape(window.aipsTelemetryL10n.detailsRawPayloadHelp || 'Review the structured payload summaries above, or expand the raw JSON below for the original object.'),
				expand_label: AIPS.Templates.escape(window.aipsTelemetryL10n.expandLabel || 'Expand'),
				collapse_label: AIPS.Templates.escape(window.aipsTelemetryL10n.collapseLabel || 'Collapse')
			});

			this.openDetailsModal(
				(window.aipsTelemetryL10n.detailsTitle || 'Telemetry Details #%s').replace('%s', this.displayValue(row.id)),
				AIPS.Templates.renderRaw('aips-tmpl-telemetry-details-modal-body', {
					detail_rows: detailRowsHtml,
					payload_section: payloadHtml
				})
			);
		},

		/**
		 * Toggle the raw payload JSON block.
		 *
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		togglePayloadSection: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var targetSelector = $button.attr('data-target') || '';
			var $section = targetSelector ? $(targetSelector) : $button.closest('.aips-telemetry-payload-section');
			var $body = $section.find('.aips-telemetry-payload-section-body').first();
			var isCollapsed = $section.hasClass('is-collapsed');

			if (isCollapsed) {
				$section.removeClass('is-collapsed');
				$button.attr('aria-expanded', 'true');
				$body.slideDown(150);
			} else {
				$section.addClass('is-collapsed');
				$button.attr('aria-expanded', 'false');
				$body.slideUp(150);
			}
		},

		/**
		 * Hide the bodies of initially-collapsed payload sections after modal HTML is injected.
		 *
		 * @param {jQuery} $container Modal content container.
		 * @return {void}
		 */
		initPayloadSections: function($container) {
			$container.find('.aips-telemetry-payload-section.is-collapsed .aips-telemetry-payload-section-body').hide();
		},

		/**
		 * Build the request detail table in two-column rows.
		 *
		 * @param {Array} detailRows Detail item list.
		 * @return {string}
		 */
		renderDetailRows: function(detailRows) {
			var rows = [];

			for (var index = 0; index < detailRows.length; index += 2) {
				rows.push(AIPS.Templates.render('aips-tmpl-telemetry-detail-pair-row', {
					label_1: detailRows[index] ? detailRows[index].label : '',
					value_1: detailRows[index] ? detailRows[index].value : '',
					label_2: detailRows[index + 1] ? detailRows[index + 1].label : '',
					value_2: detailRows[index + 1] ? detailRows[index + 1].value : ''
				}));
			}

			return rows.join('');
		},

		/**
		 * Render grouped payload tables for each top-level object.
		 *
		 * @param {*} payload Payload object or raw payload string.
		 * @return {string}
		 */
		renderPayloadSections: function(payload) {
			var payloadObject = payload;
			var sections = [];
			var sectionMap = [
				{
					key: 'cache_summary',
					title: window.aipsTelemetryL10n.detailsCacheSummarySection || 'Cache Summary',
					help: window.aipsTelemetryL10n.detailsCacheSummaryHelp || 'Cache activity grouped by operation and result.',
					collapsed: false
				},
				{
					key: 'query_summary',
					title: window.aipsTelemetryL10n.detailsQuerySummarySection || 'Query Summary',
					help: window.aipsTelemetryL10n.detailsQuerySummaryHelp || 'Query totals, slow queries, and duplicate query counts.',
					collapsed: false
				},
				{
					key: 'event_summary',
					title: window.aipsTelemetryL10n.detailsEventSummarySection || 'Event Summary',
					help: window.aipsTelemetryL10n.detailsEventSummaryHelp || 'High-level telemetry counts grouped by bucket and event type.',
					collapsed: false
				},
				{
					key: 'events',
					title: window.aipsTelemetryL10n.detailsEventsSection || 'Events',
					help: window.aipsTelemetryL10n.detailsEventsHelp || 'The full event list can be long. Expand to inspect each nested event object.',
					collapsed: true
				}
			];

			if (typeof payloadObject === 'string') {
				payloadObject = this.safeParseJson(payloadObject);
			}

			if (!payloadObject || typeof payloadObject !== 'object') {
				return AIPS.Templates.render('aips-tmpl-telemetry-payload-empty', {
					message: window.aipsTelemetryL10n.payloadEmpty || 'No payload was stored for this telemetry row.'
				});
			}

			sectionMap.forEach(function(section) {
				if (typeof payloadObject[section.key] === 'undefined' || payloadObject[section.key] === null) {
					return;
				}

				sections.push(AIPS.Templates.renderRaw('aips-tmpl-telemetry-payload-group', {
					section_key: section.key,
					title: AIPS.Templates.escape(section.title),
					help_text: AIPS.Templates.escape(section.help),
					content_html: this.renderPayloadTable(payloadObject[section.key], section.key),
					button_expand_label: AIPS.Templates.escape(window.aipsTelemetryL10n.expandLabel || 'Expand'),
					button_collapse_label: AIPS.Templates.escape(window.aipsTelemetryL10n.collapseLabel || 'Collapse'),
					aria_expanded: section.collapsed ? 'false' : 'true',
					is_collapsed: section.collapsed ? 'is-collapsed' : ''
				}));
			}, this);

			if (!sections.length) {
				return AIPS.Templates.render('aips-tmpl-telemetry-payload-empty', {
					message: window.aipsTelemetryL10n.payloadEmpty || 'No payload was stored for this telemetry row.'
				});
			}

			return sections.join('');
		},

		/**
		 * Render a table for a grouped payload object.
		 *
		 * @param {*} payloadValue Grouped payload value.
		 * @param {string} groupKey Payload group key.
		 * @return {string}
		 */
		renderPayloadTable: function(payloadValue, groupKey) {
			return this.renderPayloadNode(payloadValue, groupKey, 0);
		},

		/**
		 * Render a payload node recursively as a nested table when needed.
		 *
		 * @param {*} value Payload node value.
		 * @param {string} label Node label.
		 * @param {number} depth Nesting depth.
		 * @return {string}
		 */
		renderPayloadNode: function(value, label, depth) {
			var tableClass = 'aips-details-table aips-telemetry-payload-table aips-telemetry-payload-table--depth-' + depth;

			if (Array.isArray(value)) {
				if (!value.length) {
					return '<table class="' + tableClass + '"><tbody><tr><th>' + AIPS.Templates.escape(label) + '</th><td>' + AIPS.Templates.escape('[]') + '</td></tr></tbody></table>';
				}

				return '<table class="' + tableClass + '"><tbody>' + value.map(function(item, index) {
					return this.renderPayloadRow(String(index), item, depth + 1, true, label);
				}, this).join('') + '</tbody></table>';
			}

			if (value && typeof value === 'object') {
				var keys = Object.keys(value);
				if (!keys.length) {
					return '<table class="' + tableClass + '"><tbody><tr><th>' + AIPS.Templates.escape(label) + '</th><td>' + AIPS.Templates.escape('{}') + '</td></tr></tbody></table>';
				}

				return '<table class="' + tableClass + '"><tbody>' + keys.map(function(key) {
					return this.renderPayloadRow(key, value[key], depth + 1, false, label);
				}, this).join('') + '</tbody></table>';
			}

			return '<table class="' + tableClass + '"><tbody><tr><th>' + AIPS.Templates.escape(label) + '</th><td>' + AIPS.Templates.escape(this.displayValue(value)) + '</td></tr></tbody></table>';
		},

		/**
		 * Render a single payload row, nesting if the value is complex.
		 *
		 * @param {string} label Row label.
		 * @param {*} value Row value.
		 * @param {number} depth Nesting depth.
		 * @param {boolean} useIndexLabel Whether the label is already an index.
		 * @param {string} groupKey Key of the parent group (used to pick the item label for arrays).
		 * @return {string}
		 */
		renderPayloadRow: function(label, value, depth, useIndexLabel, groupKey) {
			var itemLabelTemplate = (groupKey === 'events')
				? (window.aipsTelemetryL10n.detailsEventItemLabel || 'Event %s')
				: (window.aipsTelemetryL10n.detailsItemLabel || 'Item %s');
			var rowLabel = AIPS.Templates.escape(useIndexLabel ? itemLabelTemplate.replace('%s', label) : label);

			if (Array.isArray(value) || (value && typeof value === 'object')) {
				return '<tr><th>' + rowLabel + '</th><td>' + this.renderPayloadNode(value, label, depth) + '</td></tr>';
			}

			return '<tr><th>' + rowLabel + '</th><td>' + AIPS.Templates.escape(this.displayValue(value)) + '</td></tr>';
		},

		/**
		 * Safely parse a payload string into JSON.
		 *
		 * @param {*} value Payload string.
		 * @return {*}
		 */
		safeParseJson: function(value) {
			if (typeof value !== 'string' || !value.trim()) {
				return null;
			}

			try {
				return JSON.parse(value);
			} catch (error) {
				return null;
			}
		},

		/**
		 * Show a details-request failure message.
		 *
		 * @return {void}
		 */
		handleDetailsFailure: function() {
			var message = window.aipsTelemetryL10n.detailsRequestFailed || 'Failed to load telemetry details. Please try again.';
			this.openDetailsModal(
				window.aipsTelemetryL10n.detailsTitle || 'Telemetry Details',
				AIPS.Templates.render('aips-tmpl-telemetry-details-loading', {
					message: message
				})
			);
			AIPS.Utilities.showToast(message, 'error');
		},

		/**
		 * Open the telemetry details modal.
		 *
		 * @param {string} title Modal title.
		 * @param {string} bodyHtml Modal body HTML.
		 * @return {void}
		 */
		openDetailsModal: function(title, bodyHtml) {
			var $content = $('#aips-telemetry-details-content');
			$('#aips-telemetry-details-title').text(title);
			$content.html(bodyHtml);
			this.initPayloadSections($content);
			$('#aips-telemetry-details-modal').fadeIn(150);
		},

		/**
		 * Build a standard AIPS.DateTime l10n map from aipsTelemetryL10n.
		 *
		 * Maps telemetry-specific key names to the generic AIPS.DateTime keys.
		 *
		 * @return {Object}
		 */
		_buildL10n: function() {
			var t = window.aipsTelemetryL10n || {};
			return {
				justNow:         t.insertedJustNow         || undefined,
				minuteAgo:       t.insertedMinuteAgo        || undefined,
				minutesAgo:      t.insertedMinutesAgo       || undefined,
				hourAgo:         t.insertedHourAgo          || undefined,
				hoursAgo:        t.insertedHoursAgo         || undefined,
				hoursMinutesAgo: t.insertedHoursMinutesAgo  || undefined,
				yesterdayAt:     t.insertedYesterdayAt      || undefined,
				absoluteDate:    t.insertedAbsoluteDate     || undefined,
				locale:          t.locale                   || undefined
			};
		},

		/**
		 * Format a request type label for display.
		 *
		 * @param {*} value Request type value.
		 * @return {string}
		 */
		formatTypeLabel: function(value) {
			var type = String(value || '').toLowerCase();

			if (type === 'ajax') {
				return 'AJAX';
			}
			if (type === 'cron') {
				return 'Cron';
			}
			if (type === 'admin') {
				return 'Admin';
			}
			if (type === 'frontend') {
				return 'Frontend';
			}

			return this.displayValue(value);
		},

		/**
		 * Format event categories as labeled chips.
		 *
		 * @param {*} value Comma-separated category string.
		 * @return {string}
		 */
		formatCategories: function(value) {
			var categories = String(value || '').split(',').map(function(item) {
				return item.trim();
			}).filter(function(item) {
				return item.length > 0;
			});

			if (!categories.length) {
				return AIPS.Templates.renderRaw('aips-tmpl-telemetry-category-list', {
					badges: AIPS.Templates.render('aips-tmpl-telemetry-category-empty', {
						label: '—'
					})
				});
			}

			var badges = categories.map(function(category) {
				return AIPS.Templates.render('aips-tmpl-telemetry-category-badge', {
					class_name: this.getCategoryClass(category),
					label: this.formatCategoryLabel(category)
				});
			}, this).join('');

			return AIPS.Templates.renderRaw('aips-tmpl-telemetry-category-list', {
				badges: badges
			});
		},

		/**
		 * Format an event category label.
		 *
		 * @param {*} value Category key.
		 * @return {string}
		 */
		formatCategoryLabel: function(value) {
			var category = String(value || '').toLowerCase();
			var labels = {
				admin: 'Admin',
				ajax: 'AJAX',
				cache: 'Cache',
				classes: 'Classes',
				cron: 'Cron',
				frontend: 'Frontend',
				general: 'General',
				performance: 'Performance',
				query: 'Query'
			};

			return labels[category] || this.displayValue(value);
		},

		/**
		 * Return the CSS modifier for a telemetry category badge.
		 *
		 * @param {*} value Category key.
		 * @return {string}
		 */
		getCategoryClass: function(value) {
			var category = String(value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
			return category || 'general';
		},


		/**
		 * Update count and pagination labels.
		 *
		 * @param {Object} data Server response data.
		 * @return {void}
		 */
		updatePagination: function(data) {
			var countLabel = (window.aipsTelemetryL10n.telemetryTotal || '%s records').replace('%s', data.total || 0);
			var pageLabel = (window.aipsTelemetryL10n.telemetryPage || 'Page %1$s of %2$s')
				.replace('%1$s', data.page || 1)
				.replace('%2$s', data.total_pages || 1);

			$('#aips-telemetry-count').text(countLabel);
			$('#aips-telemetry-page-label').text(pageLabel);
			$('#aips-telemetry-per-page').val(String(data.per_page || this.perPage));
			$('#aips-telemetry-prev').prop('disabled', (data.page || 1) <= 1);
			$('#aips-telemetry-next').prop('disabled', (data.page || 1) >= (data.total_pages || 1));
		},

		/**
		 * Render or update all four charts.
		 *
		 * @param {Object} charts Chart payload from the server.
		 * @return {void}
		 */
		updateCharts: function(charts) {
			if (typeof window.Chart === 'undefined') {
				if (AIPS.Utilities && typeof AIPS.Utilities.showToast === 'function') {
					AIPS.Utilities.showToast(window.aipsTelemetryL10n.chartUnavailable || 'Chart library failed to load.', 'error');
				}
				return;
			}

			var labels = this.getChartLabels(charts);
			this.renderChart(
				'queries',
				labels,
				charts.queries || [],
				'bar',
				window.aipsTelemetryL10n.chartQueriesTitle || 'Queries Executed per Day',
				window.aipsTelemetryL10n.chartQueriesLabel || 'Queries',
				'#2271b1'
			);
			this.renderChart(
				'memory',
				labels,
				charts.peak_memory_mb || [],
				'line',
				window.aipsTelemetryL10n.chartMemoryTitle || 'Peak Memory per Day',
				window.aipsTelemetryL10n.chartMemoryLabel || 'Peak Memory (MB)',
				'#8c8f94'
			);
			this.renderChart(
				'elapsed',
				labels,
				charts.avg_elapsed_ms || [],
				'line',
				window.aipsTelemetryL10n.chartElapsedTitle || 'Average Elapsed Time per Day',
				window.aipsTelemetryL10n.chartElapsedLabel || 'Average Elapsed (ms)',
				'#d63638'
			);
			this.renderChart(
				'requests',
				labels,
				charts.requests || [],
				'bar',
				window.aipsTelemetryL10n.chartRequestsTitle || 'Requests Logged per Day',
				window.aipsTelemetryL10n.chartRequestsLabel || 'Requests',
				'#00a32a'
			);
		},

		/**
		 * Return chart labels limited to dates that actually have data.
		 *
		 * @param {Object} charts Chart payload from the server.
		 * @return {Array}
		 */
		getChartLabels: function(charts) {
			var labels = charts.labels || [];
			var requests = charts.requests || [];
			var queries = charts.queries || [];
			var memory = charts.peak_memory_mb || [];
			var elapsed = charts.avg_elapsed_ms || [];
			var filtered = [];

			labels.forEach(function(label, index) {
				var requestValue = parseFloat(requests[index] || 0);
				var queryValue = parseFloat(queries[index] || 0);
				var memoryValue = parseFloat(memory[index] || 0);
				var elapsedValue = parseFloat(elapsed[index] || 0);

				if (requestValue > 0 || queryValue > 0 || memoryValue > 0 || elapsedValue > 0) {
					filtered.push(label);
				}
			});

			return filtered.length ? filtered : labels;
		},

		/**
		 * Render a chart into its canvas.
		 *
		 * @param {string} key Chart identifier.
		 * @param {Array} labels Axis labels.
		 * @param {Array} data Dataset values.
		 * @param {string} type Chart.js type.
		 * @param {string} title Chart title.
		 * @param {string} label Dataset label.
		 * @param {string} color Primary chart color.
		 * @return {void}
		 */
		renderChart: function(key, labels, data, type, title, label, color) {
			var canvas = document.getElementById('aips-telemetry-chart-' + key);
			if (!canvas) {
				return;
			}

			if (this.charts[key]) {
				this.charts[key].destroy();
			}

			this.charts[key] = new window.Chart(canvas, {
				type: type,
				data: {
					labels: labels,
					datasets: [{
						label: label,
						data: data,
						backgroundColor: this.toAlpha(color, type === 'line' ? 0.18 : 0.72),
						borderColor: color,
						borderWidth: 2,
						fill: type === 'line',
						tension: 0.28,
						pointRadius: type === 'line' ? 3 : 0
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false
						},
						title: {
							display: true,
							text: title,
							color: '#1d2327',
							font: {
								size: 14,
								weight: '600'
							}
						}
					},
					scales: {
						x: {
							grid: {
								display: false
							}
						},
						y: {
							beginAtZero: true
						}
					}
				}
			});
		},

		/**
		 * Normalize a display value.
		 *
		 * @param {*} value Input value.
		 * @return {string}
		 */
		displayValue: function(value) {
			if (value === null || value === undefined || value === '') {
				return '—';
			}
			return String(value);
		},

		/**
		 * Convert a hex color to rgba().
		 *
		 * @param {string} hex Hex color.
		 * @param {number} alpha Alpha channel value.
		 * @return {string}
		 */
		toAlpha: function(hex, alpha) {
			var normalized = (hex || '').replace('#', '');
			if (normalized.length !== 6) {
				return hex;
			}

			var red = parseInt(normalized.substring(0, 2), 16);
			var green = parseInt(normalized.substring(2, 4), 16);
			var blue = parseInt(normalized.substring(4, 6), 16);
			return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + alpha + ')';
		}
	};

	$(document).ready(function() {
		AIPS.Telemetry.init();
	});

})(jQuery);