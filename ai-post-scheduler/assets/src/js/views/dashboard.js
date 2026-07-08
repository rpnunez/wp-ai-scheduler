import Backbone from 'backbone';
import $ from 'jquery';
import { DashboardModel } from '../models/dashboard';

/**
 * Dashboard View
 * Renders Chart.js visualizations for dashboard overview page
 */
export const DashboardView = Backbone.View.extend({
	el: 'body',

	charts: {},

	initialize() {
		this.model = new DashboardModel();
		this.l10n = window.aipsDashboardL10n || {};
		this.chartData = window.aipsDashboardChartData || {};
		this.renderCharts();
	},

	renderCharts() {
		if (!this.$('#aips-dashboard-panel').length) {
			return;
		}

		if (!this.chartData.labels) {
			return;
		}

		if (typeof Chart === 'undefined') {
			this.showChartUnavailableError();
			return;
		}

		// Posts by day: Completed vs Failed (Bar Chart)
		this.renderChart(
			'aips-chart-posts-by-day',
			this.chartData.labels,
			[
				{
					label: this.l10n.chartCompletedLabel || 'Completed',
					data: this.chartData.completed,
					backgroundColor: this.getChartColor('#2271b1', 0.75),
					borderColor: '#2271b1',
					borderWidth: 2,
					borderRadius: 4,
					borderSkipped: false
				},
				{
					label: this.l10n.chartFailedLabel || 'Failed',
					data: this.chartData.failed,
					backgroundColor: this.getChartColor('#b32d2e', 0.65),
					borderColor: '#b32d2e',
					borderWidth: 2,
					borderRadius: 4,
					borderSkipped: false
				}
			],
			'bar',
			this.l10n.chartPostsTitle || 'Post Generations by Day'
		);

		// Topics by day (Line Chart)
		this.renderChart(
			'aips-chart-topics-by-day',
			this.chartData.labels,
			[
				{
					label: this.l10n.chartTopicsLabel || 'Topics Generated',
					data: this.chartData.topics,
					backgroundColor: this.getChartColor('#00a32a', 0.65),
					borderColor: '#00a32a',
					borderWidth: 2,
					fill: true,
					tension: 0.3
				}
			],
			'line',
			this.l10n.chartTopicsTitle || 'Topic Generations by Day'
		);

		// Error rate by day (Line Chart)
		this.renderChart(
			'aips-chart-error-rate',
			this.chartData.labels,
			[
				{
					label: this.l10n.chartErrorRateLabel || 'Error Rate (%)',
					data: this.chartData.errorRate,
					backgroundColor: this.getChartColor('#dba617', 0.55),
					borderColor: '#dba617',
					borderWidth: 2,
					fill: true,
					tension: 0.3
				}
			],
			'line',
			this.l10n.chartErrorRateTitle || 'AI Error Rate (%)'
		);
	},

	renderChart(canvasId, labels, datasets, type, title) {
		const $canvas = this.$(`#${canvasId}`);
		if (!$canvas.length) {
			return;
		}

		const ctx = $canvas[0].getContext('2d');

		if (this.charts[canvasId]) {
			this.charts[canvasId].destroy();
			delete this.charts[canvasId];
		}

		const options = {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					display: datasets.length > 1,
					position: 'bottom',
					labels: {
						boxWidth: 12,
						font: { size: 11 }
					}
				},
				title: {
					display: false
				},
				tooltip: {
					mode: 'index',
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
						font: { size: 11 },
						precision: 0
					},
					grid: {
						color: 'rgba(0,0,0,0.05)'
					}
				}
			}
		};

		this.charts[canvasId] = new Chart(ctx, {
			type,
			data: { labels, datasets },
			options
		});
	},

	getChartColor(hex, alpha) {
		if (window.AIPS && window.AIPS.Utilities && typeof window.AIPS.Utilities.toAlpha === 'function') {
			return window.AIPS.Utilities.toAlpha(hex, alpha);
		}

		// Fallback: convert hex to rgba
		const r = parseInt(hex.slice(1, 3), 16);
		const g = parseInt(hex.slice(3, 5), 16);
		const b = parseInt(hex.slice(5, 7), 16);
		return `rgba(${r},${g},${b},${alpha})`;
	},

	showChartUnavailableError() {
		const message = this.l10n.chartUnavailable || 'Chart library failed to load.';
		this.$('.aips-dashboard-chart-wrap').each(function() {
			$(this)
				.empty()
				.append(
					$('<div></div>')
						.addClass('notice notice-warning inline')
						.append(
							$('<p></p>').text(message)
						)
				);
		});
	},

	remove() {
		// Clean up all chart instances on view removal
		Object.values(this.charts).forEach((chart) => {
			if (chart) chart.destroy();
		});
		this.charts = {};
		return Backbone.View.prototype.remove.apply(this, arguments);
	}
});
