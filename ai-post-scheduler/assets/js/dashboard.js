/**
 * Dashboard page chart rendering, event binding, and AJAX interactions.
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

		/** @type {Backbone.Collection|null} */
		postsCollection: null,

		/** @type {Backbone.Collection|null} */
		topicsCollection: null,

		/** @type {Backbone.Collection|null} */
		topicPostsCollection: null,

		/** @type {Backbone.Collection|null} */
		schedulesCollection: null,

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

			this.initListCollections();
		},

		/**
		 * Set up the 4 list-tab Collections/Views (posts, topics, topic-posts,
		 * schedules). These start empty -- the initial page load renders its
		 * rows server-side in PHP, same as before. They only get populated via
		 * `.reset()` from `aips_get_dashboard_data`'s response in
		 * `updateDashboardData()`, replacing the old generic tabsConfig loop
		 * with one View per tab.
		 *
		 * The 4 single-item action handlers below (`handlePublishPost` etc.)
		 * are left as-is: each already patches the exact row it was clicked
		 * from via `$btn.closest('tr')`, so there's no "which row is this"
		 * problem for Backbone to solve here, and no client-side row template
		 * would safely reconstruct `title_html`/`actions_html` for rows that
		 * came from the initial PHP render.
		 */
		initListCollections: function() {
			this.postsCollection = new AIPS.Core.Collection();
			this.topicsCollection = new AIPS.Core.Collection();
			this.topicPostsCollection = new AIPS.Core.Collection();
			this.schedulesCollection = new AIPS.Core.Collection();

			new AIPS.Dashboard.PostsView({ collection: this.postsCollection });
			new AIPS.Dashboard.TopicsView({ collection: this.topicsCollection });
			new AIPS.Dashboard.TopicPostsView({ collection: this.topicPostsCollection });
			new AIPS.Dashboard.SchedulesView({ collection: this.schedulesCollection });
		},

		/**
		 * Register page event listeners.
		 *
		 * @return {void}
		 */
		bindEvents: function() {
			$(document).on('click', '#aips-date-filter-trigger', this.handleDateFilterTrigger.bind(this));
			$(document).on('click', '#aips-date-popover-panel', this.handlePopoverClick.bind(this));
			$(document).on('click', '#aips-date-popover-cancel', this.handlePopoverCancel.bind(this));
			$(document).on('click', this.handleDocumentClick.bind(this));
			$(document).on('submit', '#aips-dashboard-date-form', this.handleDateFormSubmit.bind(this));
			$(document).on('click', '.aips-tab-btn', this.handleTabSwitch.bind(this));
			$(document).on('click', '.aips-dashboard-publish-post', this.handlePublishPost.bind(this));
			$(document).on('click', '.aips-dashboard-approve-topic', this.handleApproveTopic.bind(this));
			$(document).on('click', '.aips-dashboard-reject-topic', this.handleRejectTopic.bind(this));
			$(document).on('click', '.aips-dashboard-run-schedule', this.handleRunSchedule.bind(this));
		},

		/**
		 * Toggle the date range filter popover panel.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleDateFilterTrigger: function(e) {
			e.stopPropagation();
			$('#aips-date-popover-panel').toggle();
		},

		/**
		 * Prevent clicks inside the popover panel from propagation.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handlePopoverClick: function(e) {
			e.stopPropagation();
		},

		/**
		 * Hide the date popover panel.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handlePopoverCancel: function(e) {
			$('#aips-date-popover-panel').hide();
		},

		/**
		 * Hide the date popover panel when clicking outside.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleDocumentClick: function(e) {
			$('#aips-date-popover-panel').hide();
		},

		/**
		 * Handle AJAX date range form submission.
		 *
		 * @param {Event} e The submit event.
		 * @return {boolean|void}
		 */
		handleDateFormSubmit: function(e) {
			e.preventDefault();
			
			var dateFromVal = $('#aips-input-date-from').val();
			var dateToVal = $('#aips-input-date-to').val();
			var l10n = window.aipsDashboardL10n || {};

			if (dateFromVal && dateToVal) {
				var fromDate = new Date(dateFromVal + 'T00:00:00');
				var toDate = new Date(dateToVal + 'T23:59:59');

				if (fromDate > toDate) {
					AIPS.Utilities.showToast(l10n.dateValidationError || 'Start Date cannot be after End Date.', 'error');
					return false;
				}
			}

			// Hide popover
			$('#aips-date-popover-panel').hide();

			// Show spinner overlay
			$('.aips-dashboard-spinner-overlay').show();

			var self = this;
			AIPS.Core.Http.ajaxRequest({
				action: 'aips_get_dashboard_data',
				data: { date_from: dateFromVal, date_to: dateToVal },
				nonce: l10n.nonce,
				toastOnError: false,
				errorFallback: (window.aipsDashboardL10n && aipsDashboardL10n.fetchDataFailed) || 'Failed to fetch dashboard data.',
				onSuccess: function(data) {
					self.updateDashboardData(data);
				},
				onError: function(message) {
					AIPS.Utilities.showToast(message, 'error');
				}
			}).always(function() {
				$('.aips-dashboard-spinner-overlay').hide();
			});
		},

		/**
		 * Handle detail switching tabs.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleTabSwitch: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var targetId = $btn.attr('aria-controls');

			// Toggle active classes on buttons
			$('.aips-tab-btn').removeClass('active').attr('aria-selected', 'false');
			$btn.addClass('active').attr('aria-selected', 'true');

			// Show the target tab panel and hide the rest
			$('.aips-tab-panel').hide().removeClass('active');
			$('#' + targetId).show().addClass('active');
		},

		/**
		 * Handle inline publish post action.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handlePublishPost: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var postId = $btn.data('id');
			var l10n = window.aipsDashboardL10n || {};

			if (!confirm('Are you sure you want to publish this post now?')) {
				return;
			}

			$btn.text('Publishing...');

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_publish_post',
				data: { post_id: postId },
				nonce: l10n.nonce,
				toastOnError: false,
				errorFallback: (window.aipsDashboardL10n && aipsDashboardL10n.publishPostFailed) || 'Failed to publish post.',
				onSuccess: function() {
					var $tr = $btn.closest('tr');
					$tr.find('.aips-badge')
						.removeClass('aips-badge-warning aips-badge-neutral')
						.addClass('aips-badge-success')
						.text('Completed');
					$btn.remove();
				},
				onError: function(message) {
					AIPS.Utilities.showToast(message, 'error');
					$btn.text('Publish Now');
				}
			});
		},

		/**
		 * Handle inline approve topic action.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleApproveTopic: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var topicId = $btn.data('id');
			var l10n = window.aipsDashboardL10n || {};

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_approve_topic',
				data: { id: topicId },
				nonce: l10n.nonce,
				toastOnError: false,
				errorFallback: (window.aipsDashboardL10n && aipsDashboardL10n.approveTopicFailed) || 'Failed to approve topic.',
				onSuccess: function() {
					var $tr = $btn.closest('tr');
					$tr.find('.status-badge')
						.removeClass('aips-badge-warning aips-badge-error')
						.addClass('aips-badge-success')
						.text('Approved');
					$tr.find('.actions-container').empty();
				},
				onError: function(message) {
					AIPS.Utilities.showToast(message, 'error');
				}
			});
		},

		/**
		 * Handle inline reject topic action.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleRejectTopic: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var topicId = $btn.data('id');
			var l10n = window.aipsDashboardL10n || {};

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_reject_topic',
				data: { id: topicId },
				nonce: l10n.nonce,
				toastOnError: false,
				errorFallback: (window.aipsDashboardL10n && aipsDashboardL10n.rejectTopicFailed) || 'Failed to reject topic.',
				onSuccess: function() {
					var $tr = $btn.closest('tr');
					$tr.find('.status-badge')
						.removeClass('aips-badge-warning aips-badge-success')
						.addClass('aips-badge-error')
						.text('Rejected');
					$tr.find('.actions-container').empty();
				},
				onError: function(message) {
					AIPS.Utilities.showToast(message, 'error');
				}
			});
		},

		/**
		 * Handle inline trigger schedule/automation run action.
		 *
		 * @param {Event} e The click event.
		 * @return {void}
		 */
		handleRunSchedule: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var scheduleId = $btn.data('id');
			var l10n = window.aipsDashboardL10n || {};

			$btn.text('Running...');

			AIPS.Core.Http.ajaxRequest({
				action: 'aips_unified_run_now',
				data: { id: scheduleId },
				nonce: l10n.nonce,
				toastOnError: false,
				errorFallback: (window.aipsDashboardL10n && aipsDashboardL10n.triggerScheduleFailed) || 'Failed to trigger schedule.',
				onSuccess: function() {
					AIPS.Utilities.showToast('Automated run triggered successfully!', 'success');
				},
				onError: function(message) {
					AIPS.Utilities.showToast(message, 'error');
				}
			}).always(function() {
				$btn.text('Run Now');
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

			// 1. AI Calls & Errors (Daily)
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
				l10n.chartAiCallsTitle || 'AI Calls & Errors by Day'
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

		/**
		 * Dynamically update dashboard widgets, charts, and tables from AJAX response data.
		 *
		 * @param {Object} data Unified dashboard JSON payload.
		 * @return {void}
		 */
		updateDashboardData: function(data) {
			// 1. Update Date labels and form boundaries
			$('.aips-date-range-label').text(data.date_from_formatted + ' – ' + data.date_to_formatted);
			$('#aips-input-date-from').attr('max', data.date_to).val(data.date_from);
			$('#aips-input-date-to').attr('min', data.date_from).val(data.date_to);

			// 2. Update Section Headers Meta Title date descriptions
			$('.aips-section-meta').text('Activity from ' + data.date_from_formatted + ' to ' + data.date_to_formatted);

			// 3. Update top-level metrics counters
			// Card 1: Completed Posts
			$('.aips-stat-success .aips-stat-value').text(data.completed_in_period);
			$('.aips-stat-success .aips-stat-sub-meta').text('Success Rate: ' + data.success_rate_in_period + '%');

			// Card 2: Unsuccessful Attempts (Failed + Partial)
			var unsuccessfulCount = parseInt(data.failed_in_period) + parseInt(data.partial_in_period);
			$('.aips-stat-danger .aips-stat-value').text(unsuccessfulCount);
			$('.aips-stat-danger .aips-stat-sub-meta').text(data.failed_in_period + ' Failed, ' + data.partial_in_period + ' Partial');

			// Card 3: AI Requests
			$('.aips-stat-info .aips-stat-value').text(data.ai_calls_in_period);
			$('.aips-stat-info .aips-stat-sub-meta').text('Error Rate: ' + data.ai_error_rate_in_period + '% (' + data.ai_errors_in_period + ' errors)');

			// Card 4: Author Topics
			$('.aips-stat-warning .aips-stat-value').text(data.topics_created_in_period);
			$('.aips-stat-warning .aips-stat-sub-meta').text(data.topics_pending_in_period + ' Pending Review');

			// 4. Update Chart.js datasets
			if (typeof Chart !== 'undefined') {
				var cd = data.chart_data;
				
				// Update Chart 1: AI Calls & Errors
				if (this.charts['aips-chart-ai-calls']) {
					this.charts['aips-chart-ai-calls'].data.labels = cd.labels;
					this.charts['aips-chart-ai-calls'].data.datasets[0].data = cd.aiCalls;
					this.charts['aips-chart-ai-calls'].data.datasets[1].data = cd.aiErrors;
					this.charts['aips-chart-ai-calls'].update();
				}

				// Update Chart 2: Posts by day
				if (this.charts['aips-chart-posts-by-day']) {
					this.charts['aips-chart-posts-by-day'].data.labels = cd.labels;
					this.charts['aips-chart-posts-by-day'].data.datasets[0].data = cd.completed;
					this.charts['aips-chart-posts-by-day'].data.datasets[1].data = cd.failed;
					this.charts['aips-chart-posts-by-day'].update();
				}

				// Update Chart 3: Topics by day
				if (this.charts['aips-chart-topics-by-day']) {
					this.charts['aips-chart-topics-by-day'].data.labels = cd.labels;
					this.charts['aips-chart-topics-by-day'].data.datasets[0].data = cd.topics;
					this.charts['aips-chart-topics-by-day'].update();
				}

				// Update Chart 4: Error rate by day
				if (this.charts['aips-chart-error-rate']) {
					this.charts['aips-chart-error-rate'].data.labels = cd.labels;
					this.charts['aips-chart-error-rate'].data.datasets[0].data = cd.errorRate;
					this.charts['aips-chart-error-rate'].update();
				}
			}

			// 5. Reset the 4 list Collections; each View's 'reset' listener
			// re-renders its own tab (see AIPS.Dashboard.PostsView etc. below).
			if (window.AIPS && AIPS.Templates && this.postsCollection) {
				this.postsCollection.reset(data.recent_posts || []);
				this.topicsCollection.reset(data.recent_topics || []);
				this.topicPostsCollection.reset(data.posts_by_topic || []);
				this.schedulesCollection.reset(data.executed_schedules || []);
			}
		}
	};

	/**
	 * Shared render helper: build one tab's row HTML from its collection,
	 * toggle the table/empty-state, and inject via templateId. `processItem`
	 * mutates a shallow item copy with the same derived HTML fields the old
	 * tabsConfig loop computed (title_html/actions_html) before rendering.
	 *
	 * @param {Backbone.View} view       The view instance (for `.collection`).
	 * @param {string}        panelId    Tab panel selector (e.g. '#tab-posts').
	 * @param {string}        templateId AIPS.Templates row template id.
	 * @param {Function}      [processItem] Optional (item) => void mutator.
	 */
	function renderDashboardTab(view, panelId, templateId, processItem) {
		var $panel = $(panelId);
		var items = view.collection.toJSON();

		if (items.length > 0) {
			var html = items.map(function(item) {
				item = $.extend({}, item);
				if (processItem) {
					processItem(item);
				}
				return AIPS.Templates.renderRaw(templateId, item);
			}).join('');
			$panel.find('.aips-table-wrap').show().find('tbody').html(html);
			$panel.find('.aips-empty-state').hide();
		} else {
			$panel.find('.aips-table-wrap').hide();
			$panel.find('.aips-empty-state').show();
		}
	}

	AIPS.Dashboard.PostsView = AIPS.Core.View.extend({
		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
		},
		render: function() {
			renderDashboardTab(this, '#tab-posts', 'aips-tmpl-dashboard-posts-row', function(item) {
				item.title_html = item.edit_url
					? '<a href="' + item.edit_url + '" class="cell-primary">' + item.generated_title + '</a>'
					: '<div class="cell-primary">' + item.generated_title + '</div>';
				item.actions_html = (item.status === 'draft' || item.status === 'pending')
					? '<div class="aips-row-actions" style="visibility: visible; margin-top: 4px;"><a href="#" class="aips-dashboard-publish-post" data-id="' + item.post_id + '">Publish Now</a></div>'
					: '';
			});
		}
	});

	AIPS.Dashboard.TopicsView = AIPS.Core.View.extend({
		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
		},
		render: function() {
			renderDashboardTab(this, '#tab-topics', 'aips-tmpl-dashboard-topics-row', function(item) {
				item.actions_html = (item.status === 'pending')
					? '<div class="aips-row-actions" style="visibility: visible; margin-top: 4px;"><a href="#" class="aips-dashboard-approve-topic" data-id="' + item.id + '">Approve</a> | <a href="#" class="aips-dashboard-reject-topic" style="color:#d63638;" data-id="' + item.id + '">Reject</a></div>'
					: '';
			});
		}
	});

	AIPS.Dashboard.TopicPostsView = AIPS.Core.View.extend({
		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
		},
		render: function() {
			renderDashboardTab(this, '#tab-topic-posts', 'aips-tmpl-dashboard-topic-posts-row', function(item) {
				item.title_html = item.edit_url
					? '<a href="' + item.edit_url + '" class="cell-primary">' + item.generated_title + '</a>'
					: '<div class="cell-primary">' + item.generated_title + '</div>';
			});
		}
	});

	AIPS.Dashboard.SchedulesView = AIPS.Core.View.extend({
		initialize: function() {
			this.listenTo(this.collection, 'reset', this.render);
		},
		render: function() {
			renderDashboardTab(this, '#tab-schedules', 'aips-tmpl-dashboard-schedules-row', function(item) {
				item.actions_html = '<div class="aips-row-actions" style="margin-top: 4px;"><a href="#" class="aips-dashboard-run-schedule" data-id="' + item.id + '">Run Now</a></div>';
			});
		}
	});

	$(document).ready(function() {
		AIPS.Dashboard.init();
	});

})(jQuery);
