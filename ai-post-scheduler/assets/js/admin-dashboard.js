/**
 * Dashboard page chart rendering.
 *
 * Relies on `aipsDashboardL10n` (localised by AIPS_Admin_Assets) and
 * `aipsDashboardChartData` (embedded by the dashboard template) to build
 * Chart.js visualisations for the dashboard overview page.
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
            // Bind page-level events (if any) regardless of the current screen, to ensure functionality if the user navigates without a full page reload.
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
			// No interactive events on the dashboard currently.
		},

		/**
		 * Render all dashboard charts from embedded page data.
		 *
		 * @return {void}
		 */
		renderCharts: function() {
			var data = window.aipsDashboardChartData;

			if (!data || !data.labels) {
				return;
			}

			if (typeof Chart === 'undefined') {
                // If Chart.js failed to load, show an error message in each chart container instead of the canvas.
				$('.aips-dashboard-chart-wrap').each(function() {
					$(this).html('<p class="aips-telemetry-loading">' + (window.aipsDashboardL10n.chartUnavailable || 'Chart library failed to load.') + '</p>');
				});
				return;
			}

            // Posts by day: Completed vs Failed (Bar Chart)
			this.renderChart(
				'aips-chart-posts-by-day',
				data.labels,
				[
					{
						label: window.aipsDashboardL10n.chartCompletedLabel || 'Completed',
						data:  data.completed,
						backgroundColor: AIPS.Utilities.toAlpha('#2271b1', 0.75),
						borderColor:     '#2271b1',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					},
					{
						label: window.aipsDashboardL10n.chartFailedLabel || 'Failed',
						data:  data.failed,
						backgroundColor: AIPS.Utilities.toAlpha('#b32d2e', 0.65),
						borderColor:     '#b32d2e',
						borderWidth: 2,
						borderRadius: 4,
						borderSkipped: false
					}
				],
				'bar',
                window.aipsDashboardL10n.chartPostsTitle || 'Post Generations by Day'
			);

            // Topics by day (Line Chart)
			this.renderChart(
				'aips-chart-topics-by-day',
				data.labels,
				[
					{
						label: window.aipsDashboardL10n.chartTopicsLabel || 'Topics Generated',
						data:  data.topics,
						backgroundColor: AIPS.Utilities.toAlpha('#00a32a', 0.65),
						borderColor:     '#00a32a',
						borderWidth: 2,
						fill:        true,
						tension:     0.3
					}
				],
				'line',
                window.aipsDashboardL10n.chartTopicsTitle || 'Topic Generations by Day'
			);

            // Error rate by day (Line Chart)
			this.renderChart(
				'aips-chart-error-rate',
				data.labels,
				[
					{
						label: window.aipsDashboardL10n.chartErrorRateLabel || 'Error Rate (%)',
						data:  data.errorRate,
						backgroundColor: AIPS.Utilities.toAlpha('#dba617', 0.55),
						borderColor:     '#dba617',
						borderWidth: 2,
						fill:        true,
						tension:     0.3
					}
				],
				'line',
                window.aipsDashboardL10n.chartErrorRateTitle || 'AI Error Rate (%)'
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
						display:  datasets.length > 1,
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
