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
			$(document).on('click', '#aips-telemetry-prev', this.prevPage.bind(this));
			$(document).on('click', '#aips-telemetry-next', this.nextPage.bind(this));
			$(document).on('change', '#aips-telemetry-per-page', this.changePerPage.bind(this));
			$(document).on('change', '#aips-telemetry-start-date, #aips-telemetry-end-date', this.changeDateRange.bind(this));
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
		 * Apply a new date range.
		 *
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		changeDateRange: function(e) {
			void e;
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
					self.updateRangeSummary(response.data);
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
				$('#aips-telemetry-prev, #aips-telemetry-next, #aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date').prop('disabled', true);
				return;
			}

			$('#aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date').prop('disabled', false);
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

			$tbody.empty();
			if (!rows || !rows.length) {
				this.setTableMessage(window.aipsTelemetryL10n.telemetryNoRecords || 'No telemetry records found for the selected range.', 'aips-telemetry-empty');
				return;
			}

			$tbody.html(rows.map(function(row) {
				return AIPS.Templates.render('aips-tmpl-telemetry-data-row', {
					id: this.displayValue(row.id),
					page: this.displayValue(row.page),
					request_method: this.displayValue(row.request_method),
					user_id: this.displayValue(row.user_id),
					num_queries: this.displayValue(row.num_queries),
					peak_memory: this.formatMemory(row.peak_memory_bytes),
					elapsed_ms: this.formatElapsed(row.elapsed_ms),
					inserted_at: this.formatInsertedAt(row.inserted_at)
				});
			}, this).join(''));
		},

		/**
		 * Format a timestamp for the Inserted At column.
		 *
		 * @param {*} value Timestamp value.
		 * @return {string}
		 */
		formatInsertedAt: function(value) {
			if (value === null || value === undefined || value === '') {
				return '—';
			}

			var raw = String(value);
			var normalized = raw.replace(' ', 'T');
			var date = new Date(normalized);

			if (isNaN(date.getTime())) {
				return this.displayValue(value);
			}

			return new Intl.DateTimeFormat('en-US', {
				month: 'long',
				day: 'numeric',
				year: 'numeric',
				hour: 'numeric',
				minute: '2-digit',
				hour12: true
			}).format(date);
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
		 * Update the selected range summary text.
		 *
		 * @param {Object} data Server response data.
		 * @return {void}
		 */
		updateRangeSummary: function(data) {
			var template = window.aipsTelemetryL10n.rangeSummary || 'Showing telemetry from %1$s to %2$s.';
			$('#aips-telemetry-range-summary').text(
				template.replace('%1$s', data.start_date || '').replace('%2$s', data.end_date || '')
			);
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

			var labels = charts.labels || [];
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
		 * Format a peak-memory value.
		 *
		 * @param {*} value Bytes value.
		 * @return {string}
		 */
		formatMemory: function(value) {
			if (value === null || value === undefined || value === '') {
				return '—';
			}
			return (parseFloat(value) / 1048576).toFixed(2) + ' MB';
		},

		/**
		 * Format an elapsed-ms value.
		 *
		 * @param {*} value Milliseconds value.
		 * @return {string}
		 */
		formatElapsed: function(value) {
			if (value === null || value === undefined || value === '') {
				return '—';
			}
			return parseFloat(value).toFixed(2) + ' ms';
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