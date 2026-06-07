/**
 * Dashboard page chart rendering and event binding.
 *
 * Relies on `aipsDashboardL10n` (localised by AIPS_Admin_Assets) and
 * `aipsDashboardChartData` (embedded by the dashboard template) to build
 * Chart.js visualisations and manage interactive filters/tabs.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.Dashboard = {

		/** @type {Object.<string, Chart>} Active Chart.js instances keyed by canvas ID. */
		charts: {},

		/**
		 * Initialise the dashboard page.
		 *
		 * @return {void}
		 */
		init: function() {
			// Bind page-level events
			this.bindEvents();

			// Only run on the dashboard page.
			if (!$('#aips-dashboard-panel').length) {
				return;
			}

			// Render charts from embedded data.
			this.renderCharts();
		},

		/**
		 * Register page event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			// Date Filter Popover toggles
			$(document).on('click', '#aips-date-filter-trigger', function(e) {
				e.stopPropagation();
				$('#aips-date-popover-panel').toggle();
			});

			$(document).on('click', '#aips-date-popover-panel', function(e) {
				e.stopPropagation();
			});

			$(document).on('click', '#aips-date-popover-cancel', function(e) {
				$('#aips-date-popover-panel').hide();
			});

			$(document).on('click', function() {
				$('#aips-date-popover-panel').hide();
			});

			// Client-side date range validation
			$(document).on('submit', '#aips-dashboard-date-form', function(e) {
				var dateFromVal = $('#aips-input-date-from').val();
				var dateToVal = $('#aips-input-date-to').val();

				if (dateFromVal && dateToVal) {
					var fromDate = new Date(dateFromVal + 'T00:00:00');
					var toDate = new Date(dateToVal + 'T23:59:59');

					if (fromDate > toDate) {
						alert(window.aipsDashboardL10n.dateValidationError || 'Start Date cannot be after End Date.');
						e.preventDefault();
						return false;
					}
				}
			});

			// Detail tab switching logic
			$(document).on('click', '.aips-tab-btn', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var targetId = $btn.attr('aria-controls');

				// Toggle active classes on buttons
				$('.aips-tab-btn').removeClass('active').attr('aria-selected', 'false');
				$btn.addClass('active').attr('aria-selected', 'true');

				// Show the target tab panel and hide the rest
				$('.aips-tab-panel').hide().removeClass('active');
				$('#' + targetId).show().addClass('active');
			});
		},

		/**
		 * Render all dashboard charts from embedded page data.
		 *
		 * @return {void}
		 */
		renderCharts: function() {
			var data = window.aipsDashboardChartData;
			var l10n = window.aipsDashboardL10n || {};
			var utilities = (AIPS && AIPS.Utilities) ? AIPS.Utilities : null;

			if (!data || !data.labels) {
				return;
			}

			if (typeof Chart === 'undefined') {
				// If Chart.js failed to load, show an error message in each chart container instead of the canvas.
				var chartUnavailableMessage = l10n.chartUnavailable || 'Chart library failed to load.';
				$('.aips-dashboard-chart-wrap').each(function() {
					$(this)
						.empty()
						.append(
							$('<div></div>')
								.addClass('notice notice-warning inline')
								.append(
									$('<p></p>').text(chartUnavailableMessage)
								)
						);
				});
				return;
			}

			// 1. AI Calls & Errors (Daily) - NEW Chart
			this.renderChart(
				'aips-chart-ai-calls',
				data.labels,
				[
					{
						label: l10n.chartAiCallsLabel || 'AI Calls',
						data:  data.aiCalls,
						backgroundColor: utilities ? utilities.toAlpha('#2271b1', 0.75) : 'rgba(34,113,177,0.75)',
						borderColor:     '#2271b1',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					},
					{
						label: l10n.chartAiErrorsLabel || 'AI Errors',
						data:  data.aiErrors,
						backgroundColor: utilities ? utilities.toAlpha('#d63638', 0.65) : 'rgba(214,54,56,0.65)',
						borderColor:     '#d63638',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					}
				],
				'bar',
				l10n.chartAiCallsTitle || 'AI Calls & Errors (Daily)'
			);

			// 2. Posts by day: Completed vs Failed (Bar Chart)
			this.renderChart(
				'aips-chart-posts-by-day',
				data.labels,
				[
					{
						label: l10n.chartCompletedLabel || 'Completed',
						data:  data.completed,
						backgroundColor: utilities ? utilities.toAlpha('#00a32a', 0.75) : 'rgba(0,163,42,0.75)',
						borderColor:     '#00a32a',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					},
					{
						label: l10n.chartFailedLabel || 'Failed',
						data:  data.failed,
						backgroundColor: utilities ? utilities.toAlpha('#b32d2e', 0.65) : 'rgba(179,45,46,0.65)',
						borderColor:     '#b32d2e',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					}
				],
				'bar',
				l10n.chartPostsTitle || 'Post Generations by Day'
			);

			// 3. Topics by day (Line Chart)
			this.renderChart(
				'aips-chart-topics-by-day',
				data.labels,
				[
					{
						label: l10n.chartTopicsLabel || 'Topics Generated',
						data:  data.topics,
						backgroundColor: utilities ? utilities.toAlpha('#dba617', 0.15) : 'rgba(219,166,23,0.15)',
						borderColor:     '#dba617',
						borderWidth: 2,
						fill:        true,
						tension:     0.3
					}
				],
				'line',
				l10n.chartTopicsTitle || 'Topic Generations by Day'
			);

			// 4. Error rate by day (Line Chart)
			this.renderChart(
				'aips-chart-error-rate',
				data.labels,
				[
					{
						label: l10n.chartErrorRateLabel || 'Error Rate (%)',
						data:  data.errorRate,
						backgroundColor: utilities ? utilities.toAlpha('#d63638', 0.1) : 'rgba(214,54,56,0.1)',
						borderColor:     '#d63638',
						borderWidth: 2,
						fill:        true,
						tension:     0.3
					}
				],
				'line',
				l10n.chartErrorRateTitle || 'AI Error Rate (%)'
			);
		},

		/**
		 * Create or update a single Chart.js chart.
		 *
		 * @param {string} canvasId    ID of the target <canvas> element.
		 * @param {string[]} labels    X-axis label array.
		 * @param {Object[]} datasets  Chart.js dataset configuration array.
		 * @param {string}   type      Chart type ('bar' | 'line').
		 * @param {string}   title     Chart title shown in the legend/tooltip.
		 * @return {void}
		 */
		renderChart: function(canvasId, labels, datasets, type, title) {
			var $canvas = $('#' + canvasId);
			if (!$canvas.length) {
				return;
			}

			var ctx = $canvas[0].getContext('2d');

			if (this.charts[canvasId]) {
				this.charts[canvasId].destroy();
				delete this.charts[canvasId];
			}

			var options = {
				responsive:          true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display:  true,
						position: 'bottom',
						labels: {
							boxWidth:  12,
							font: { size: 11 }
						}
					},
					title: {
						display: false
					},
					tooltip: {
						mode:      'index',
						intersect: false
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: { font: { size: 11 } }
					},
					y: {
						beginAtZero: true,
						ticks: {
							font:      { size: 11 },
							precision: 0
						},
						grid: {
							color: 'rgba(0,0,0,0.05)'
						}
					}
				}
			};

			this.charts[canvasId] = new Chart(ctx, {
				type:    type,
				data:    { labels: labels, datasets: datasets },
				options: options
			});
		},
	};

	$(document).ready(function() {
		AIPS.Dashboard.init();
	});

})(jQuery);
