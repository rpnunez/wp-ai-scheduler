import Backbone from 'backbone';
import $ from 'jquery';
import _ from 'underscore';
import { BaseListView } from './base-list';
import { BaseModalView } from './base-modal';

/**
 * Telemetry View
 */
export const TelemetryView = BaseListView.extend({
	el: 'body',

	listSelector: '#aips-telemetry-tbody',
	rowSelector: '#aips-telemetry-tbody tr',
	searchSelector: '#aips-telemetry-page-filter',
	selectAllSelector: '',
	checkboxSelector: '',
	bulkApplySelector: '',

	page: 1,
	totalPages: 1,
	perPage: 25,
	charts: {},
	templateColors: ['color-1', 'color-2', 'color-3'],

	events: _.extend({}, BaseListView.prototype.events, {
		'click .aips-telemetry-refresh': 'refresh',
		'click #aips-telemetry-apply-filters': 'applyFilters',
		'click #aips-telemetry-reset-filters': 'resetFilters',
		'click #aips-telemetry-prev': 'prevPage',
		'click #aips-telemetry-next': 'nextPage',
		'change #aips-telemetry-per-page': 'changePerPage',
		'click .aips-telemetry-view-details': 'viewDetails',
		'click .aips-telemetry-payload-toggle': 'togglePayloadSection'
	}),

	initialize() {
		BaseListView.prototype.initialize.apply(this, arguments);

		this.charts = {};

		if ($('#aips-telemetry-details-modal').length) {
			this.detailsModal = new BaseModalView({ el: '#aips-telemetry-details-modal' });
		}

		if (this.isTelemetryPage()) {
			this.perPage = parseInt(this.$('#aips-telemetry-per-page').val(), 10) || 25;
			this.loadPage(1);
		}
	},

	isTelemetryPage() {
		return this.$('#aips-telemetry-panel').length > 0;
	},

	refresh(e) {
		e.preventDefault();
		this.loadPage(this.page);
	},

	applyFilters(e) {
		e.preventDefault();
		this.loadPage(1);
	},

	resetFilters(e) {
		e.preventDefault();

		this.$('#aips-telemetry-type-filter').val('');
		this.$('#aips-telemetry-category-filter').val('');
		this.$('#aips-telemetry-method-filter').val('');
		this.$('#aips-telemetry-page-filter').val('');
		this.$('#aips-telemetry-issues-only').prop('checked', false);
		this.loadPage(1);
	},

	prevPage(e) {
		e.preventDefault();
		if (this.page > 1) {
			this.loadPage(this.page - 1);
		}
	},

	nextPage(e) {
		e.preventDefault();
		if (this.page < this.totalPages) {
			this.loadPage(this.page + 1);
		}
	},

	changePerPage(e) {
		this.perPage = parseInt($(e.currentTarget).val(), 10) || 25;
		this.loadPage(1);
	},

	loadPage(page) {
		const $refreshButtons = this.$('.aips-telemetry-refresh');
		const l10n = window.aipsTelemetryL10n || {};

		this.setLoadingState(true);
		this.setTableMessage(l10n.loading || 'Loading...', 'aips-telemetry-loading');
		this.setRefreshButtonLabel($refreshButtons, l10n.refreshing || 'Refreshing...');
		$refreshButtons.prop('disabled', true);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_get_telemetry',
			nonce: l10n.nonce || '',
			page: page,
			per_page: this.perPage,
			type: this.$('#aips-telemetry-type-filter').val() || '',
			event_category: this.$('#aips-telemetry-category-filter').val() || '',
			request_method: this.$('#aips-telemetry-method-filter').val() || '',
			page_search: this.$('#aips-telemetry-page-filter').val() || '',
			issues_only: this.$('#aips-telemetry-issues-only').is(':checked') ? '1' : '0',
			start_date: this.$('#aips-telemetry-start-date').val() || '',
			end_date: this.$('#aips-telemetry-end-date').val() || ''
		}, (response) => {
			if (!response || !response.success || !response.data) {
				this.handleRequestFailure();
				return;
			}

			this.page = response.data.page || 1;
			this.totalPages = response.data.total_pages || 1;
			this.perPage = response.data.per_page || this.perPage;

			this.$('#aips-telemetry-start-date').val(response.data.start_date || '');
			this.$('#aips-telemetry-end-date').val(response.data.end_date || '');
			
			this.renderRows(response.data.rows || []);
			this.updatePagination(response.data);
			this.updateCharts(response.data.charts || {});
		}).fail(() => {
			this.handleRequestFailure();
		}).always(() => {
			this.setLoadingState(false);
			this.setRefreshButtonLabel($refreshButtons, l10n.refreshLabel || 'Refresh');
			$refreshButtons.prop('disabled', false);
		});
	},

	setRefreshButtonLabel($button, label) {
		const T = window.AIPS.Templates;
		$button.each(function() {
			const $current = $(this);
			if (T) {
				$current.html(T.render('aips-tmpl-telemetry-refresh-button-content', {
					label: label
				}));
			}
		});
	},

	handleRequestFailure() {
		const l10n = window.aipsTelemetryL10n || {};
		const message = l10n.requestFailed || 'Request failed. Please try again.';
		this.setTableMessage(message, 'aips-telemetry-empty');
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.showToast(message, 'error');
		}
	},

	setLoadingState(isLoading) {
		if (isLoading) {
			this.$('#aips-telemetry-prev, #aips-telemetry-next, #aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date, #aips-telemetry-type-filter, #aips-telemetry-category-filter, #aips-telemetry-method-filter, #aips-telemetry-page-filter, #aips-telemetry-issues-only, #aips-telemetry-apply-filters, #aips-telemetry-reset-filters').prop('disabled', true);
			return;
		}

		this.$('#aips-telemetry-per-page, #aips-telemetry-start-date, #aips-telemetry-end-date, #aips-telemetry-type-filter, #aips-telemetry-category-filter, #aips-telemetry-method-filter, #aips-telemetry-page-filter, #aips-telemetry-issues-only, #aips-telemetry-apply-filters, #aips-telemetry-reset-filters').prop('disabled', false);
		this.$('#aips-telemetry-prev').prop('disabled', this.page <= 1);
		this.$('#aips-telemetry-next').prop('disabled', this.page >= this.totalPages);
	},

	setTableMessage(message, className) {
		const $tbody = this.$('#aips-telemetry-tbody');
		const T = window.AIPS.Templates;
		if (T) {
			$tbody.html(T.render('aips-tmpl-telemetry-message-row', {
				message: message,
				class_name: className
			}));
		}
	},

	renderRows(rows) {
		const $tbody = this.$('#aips-telemetry-tbody');
		const dtL10n = this._buildL10n();
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		$tbody.empty();
		if (!rows || !rows.length) {
			this.setTableMessage(l10n.telemetryNoRecords || 'No telemetry records found for the selected range.', 'aips-telemetry-empty');
			return;
		}

		$tbody.html(rows.map(row => {
			const relativeTime = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatRelative === 'function')
				? window.AIPS.DateTime.formatRelative(row.inserted_at, dtL10n)
				: row.inserted_at;

			const elapsedSeconds = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatElapsedSeconds === 'function')
				? window.AIPS.DateTime.formatElapsedSeconds(row.elapsed_ms)
				: row.elapsed_ms;

			const categoriesHtml = this.formatCategories(row.event_categories);

			if (T) {
				return T.renderRaw('aips-tmpl-telemetry-data-row', {
					raw_id: esc(this.displayValue(row.id)),
					type: esc(this.formatTypeLabel(row.type)),
					page: esc(this.displayValue(row.page)),
					event_categories_html: categoriesHtml,
					request_method: esc(this.displayValue(row.request_method)),
					num_queries: esc(this.displayValue(row.num_queries)),
					elapsed_ms: esc(elapsedSeconds),
					inserted_at: esc(relativeTime),
					view_details_label: esc(l10n.viewDetails || 'View Details')
				});
			}
			return '';
		}).join(''));
	},

	viewDetails(e) {
		e.preventDefault();

		const $button = $(e.currentTarget);
		const rowId = parseInt($button.attr('data-telemetry-id'), 10) || 0;
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;

		if (rowId < 1) {
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.requestFailed || 'Request failed. Please try again.', 'error');
			}
			return;
		}

		if (T) {
			this.openDetailsModal(
				(l10n.detailsTitle || 'Telemetry Details #%s').replace('%s', String(rowId)),
				T.render('aips-tmpl-telemetry-details-loading', {
					message: l10n.loadingDetails || 'Loading telemetry details...'
				})
			);
		}

		$button.prop('disabled', true);

		$.post((window.aipsAjax && window.aipsAjax.ajaxUrl) || window.ajaxurl, {
			action: 'aips_get_telemetry_details',
			nonce: l10n.detailsNonce || '',
			id: rowId
		}, (response) => {
			if (!response || !response.success || !response.data || !response.data.row) {
				this.handleDetailsFailure();
				return;
			}

			this.renderDetailsModal(response.data);
		}).fail(() => {
			this.handleDetailsFailure();
		}).always(() => {
			$button.prop('disabled', false);
		});
	},

	renderDetailsModal(data) {
		const row = data.row || {};
		const dtL10n = this._buildL10n();
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		const relativeTime = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatRelative === 'function')
			? window.AIPS.DateTime.formatRelative(row.inserted_at, dtL10n)
			: row.inserted_at;

		const formatMemoryStr = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatMemory === 'function')
			? window.AIPS.DateTime.formatMemory(row.peak_memory_bytes)
			: row.peak_memory_bytes;

		const formatElapsedStr = (window.AIPS && window.AIPS.DateTime && typeof window.AIPS.DateTime.formatElapsed === 'function')
			? window.AIPS.DateTime.formatElapsed(row.elapsed_ms)
			: row.elapsed_ms;

		const detailRows = [
			{ label: l10n.detailsIdLabel || 'ID', value: this.displayValue(row.id) },
			{ label: l10n.detailsTypeLabel || 'Type', value: this.displayValue(row.type) },
			{ label: l10n.detailsPageLabel || 'Page', value: this.displayValue(row.page) },
			{ label: l10n.detailsCategoriesLabel || 'Categories', value: this.displayValue(row.event_categories) },
			{ label: l10n.detailsMethodLabel || 'Method', value: this.displayValue(row.request_method) },
			{ label: l10n.detailsUserIdLabel || 'User ID', value: this.displayValue(row.user_id) },
			{ label: l10n.detailsEventsLabel || 'Events', value: this.displayValue(row.total_events) },
			{ label: l10n.detailsCacheCallsLabel || 'Cache Calls', value: this.displayValue(row.cache_calls) },
			{ label: l10n.detailsCacheHitsLabel || 'Cache Hits', value: this.displayValue(row.cache_hits) },
			{ label: l10n.detailsCacheMissesLabel || 'Cache Misses', value: this.displayValue(row.cache_misses) },
			{ label: l10n.detailsQueriesLabel || 'Queries', value: this.displayValue(row.num_queries) },
			{ label: l10n.detailsSlowQueriesLabel || 'Slow Queries', value: this.displayValue(row.slow_query_count) },
			{ label: l10n.detailsDuplicateQueriesLabel || 'Duplicate Queries', value: this.displayValue(row.duplicate_query_count) },
			{ label: l10n.detailsPeakMemoryLabel || 'Peak Memory', value: formatMemoryStr + ' (' + this.displayValue(row.peak_memory_bytes) + ' bytes)' },
			{ label: l10n.detailsElapsedLabel || 'Elapsed', value: formatElapsedStr },
			{ label: l10n.detailsInsertedLabel || 'Inserted At', value: relativeTime }
		];

		const detailRowsHtml = this.renderDetailRows(detailRows);
		const payloadSectionsHtml = this.renderPayloadSections(data.payload_decoded || this.safeParseJson(row.payload));
		const payloadJson = data.payload_decoded
			? JSON.stringify(data.payload_decoded, null, 2)
			: (row.payload || l10n.payloadEmpty || 'No payload was stored.');

		if (T) {
			const payloadHtml = T.renderRaw('aips-tmpl-telemetry-details-payload', {
				payload_sections: payloadSectionsHtml,
				raw_payload_json: esc(payloadJson),
				raw_payload_label: esc(l10n.detailsRawPayloadLabel || 'Raw Payload JSON'),
				raw_payload_help: esc(l10n.detailsRawPayloadHelp || 'Review structural payload summaries.'),
				expand_label: esc(l10n.expandLabel || 'Expand'),
				collapse_label: esc(l10n.collapseLabel || 'Collapse')
			});

			this.openDetailsModal(
				(l10n.detailsTitle || 'Telemetry Details #%s').replace('%s', this.displayValue(row.id)),
				T.renderRaw('aips-tmpl-telemetry-details-modal-body', {
					detail_rows: detailRowsHtml,
					payload_section: payloadHtml
				})
			);
		}
	},

	togglePayloadSection(e) {
		e.preventDefault();

		const $button = $(e.currentTarget);
		const targetSelector = $button.attr('data-target') || '';
		const $section = targetSelector ? $(targetSelector) : $button.closest('.aips-telemetry-payload-section');
		const $body = $section.find('.aips-telemetry-payload-section-body').first();
		const isCollapsed = $section.hasClass('is-collapsed');

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

	initPayloadSections($container) {
		$container.find('.aips-telemetry-payload-section.is-collapsed .aips-telemetry-payload-section-body').hide();
	},

	renderDetailRows(detailRows) {
		const rows = [];
		const T = window.AIPS.Templates;

		if (T) {
			for (let index = 0; index < detailRows.length; index += 2) {
				rows.push(T.render('aips-tmpl-telemetry-detail-pair-row', {
					label_1: detailRows[index] ? detailRows[index].label : '',
					value_1: detailRows[index] ? detailRows[index].value : '',
					label_2: detailRows[index + 1] ? detailRows[index + 1].label : '',
					value_2: detailRows[index + 1] ? detailRows[index + 1].value : ''
				}));
			}
		}

		return rows.join('');
	},

	renderPayloadSections(payload) {
		let payloadObject = payload;
		const sections = [];
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		const sectionMap = [
			{
				key: 'cache_summary',
				title: l10n.detailsCacheSummarySection || 'Cache Summary',
				help: l10n.detailsCacheSummaryHelp || 'Cache summary operations.',
				collapsed: false
			},
			{
				key: 'query_summary',
				title: l10n.detailsQuerySummarySection || 'Query Summary',
				help: l10n.detailsQuerySummaryHelp || 'Query totals summary.',
				collapsed: false
			},
			{
				key: 'event_summary',
				title: l10n.detailsEventSummarySection || 'Event Summary',
				help: l10n.detailsEventSummaryHelp || 'Event counts summary.',
				collapsed: false
			},
			{
				key: 'events',
				title: l10n.detailsEventsSection || 'Events',
				help: l10n.detailsEventsHelp || 'Detailed events list.',
				collapsed: true
			}
		];

		if (typeof payloadObject === 'string') {
			payloadObject = this.safeParseJson(payloadObject);
		}

		if (!payloadObject || typeof payloadObject !== 'object') {
			if (T) {
				return T.render('aips-tmpl-telemetry-payload-empty', {
					message: l10n.payloadEmpty || 'No payload was stored.'
				});
			}
			return '';
		}

		sectionMap.forEach(section => {
			if (typeof payloadObject[section.key] === 'undefined' || payloadObject[section.key] === null) {
				return;
			}

			if (T) {
				sections.push(T.renderRaw('aips-tmpl-telemetry-payload-group', {
					section_key: section.key,
					title: esc(section.title),
					help_text: esc(section.help),
					content_html: this.renderPayloadTable(payloadObject[section.key], section.key),
					button_expand_label: esc(l10n.expandLabel || 'Expand'),
					button_collapse_label: esc(l10n.collapseLabel || 'Collapse'),
					aria_expanded: section.collapsed ? 'false' : 'true',
					is_collapsed: section.collapsed ? 'is-collapsed' : ''
				}));
			}
		});

		if (!sections.length) {
			if (T) {
				return T.render('aips-tmpl-telemetry-payload-empty', {
					message: l10n.payloadEmpty || 'No payload was stored.'
				});
			}
			return '';
		}

		return sections.join('');
	},

	renderPayloadTable(payloadValue, groupKey) {
		return this.renderPayloadNode(payloadValue, groupKey, 0);
	},

	renderPayloadNode(value, label, depth) {
		const tableClass = 'aips-details-table aips-telemetry-payload-table aips-telemetry-payload-table--depth-' + depth;
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		if (Array.isArray(value)) {
			if (!value.length) {
				return '<table class="' + tableClass + '"><tbody><tr><th>' + esc(label) + '</th><td>[]</td></tr></tbody></table>';
			}

			return '<table class="' + tableClass + '"><tbody>' + value.map((item, index) => {
				return this.renderPayloadRow(String(index), item, depth + 1, true, label);
			}).join('') + '</tbody></table>';
		}

		if (value && typeof value === 'object') {
			const keys = Object.keys(value);
			if (!keys.length) {
				return '<table class="' + tableClass + '"><tbody><tr><th>' + esc(label) + '</th><td>{}</td></tr></tbody></table>';
			}

			return '<table class="' + tableClass + '"><tbody>' + keys.map(key => {
				return this.renderPayloadRow(key, value[key], depth + 1, false, label);
			}).join('') + '</tbody></table>';
		}

		return '<table class="' + tableClass + '"><tbody><tr><th>' + esc(label) + '</th><td>' + esc(this.displayValue(value)) + '</td></tr></tbody></table>';
	},

	renderPayloadRow(label, value, depth, useIndexLabel, groupKey) {
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;
		const esc = T ? T.escape : _.escape;

		const itemLabelTemplate = (groupKey === 'events')
			? (l10n.detailsEventItemLabel || 'Event %s')
			: (l10n.detailsItemLabel || 'Item %s');
		const rowLabel = esc(useIndexLabel ? itemLabelTemplate.replace('%s', label) : label);

		if (Array.isArray(value) || (value && typeof value === 'object')) {
			return '<tr><th>' + rowLabel + '</th><td>' + this.renderPayloadNode(value, label, depth) + '</td></tr>';
		}

		return '<tr><th>' + rowLabel + '</th><td>' + esc(this.displayValue(value)) + '</td></tr>';
	},

	safeParseJson(value) {
		if (typeof value !== 'string' || !value.trim()) {
			return null;
		}
		try {
			return JSON.parse(value);
		} catch (error) {
			return null;
		}
	},

	handleDetailsFailure() {
		const l10n = window.aipsTelemetryL10n || {};
		const T = window.AIPS.Templates;
		const message = l10n.detailsRequestFailed || 'Failed to load details.';

		if (T) {
			this.openDetailsModal(
				l10n.detailsTitle || 'Telemetry Details',
				T.render('aips-tmpl-telemetry-details-loading', {
					message: message
				})
			);
		}
		if (window.AIPS && window.AIPS.Utilities) {
			window.AIPS.Utilities.showToast(message, 'error');
		}
	},

	openDetailsModal(title, bodyHtml) {
		const $content = this.$('#aips-telemetry-details-content');
		this.$('#aips-telemetry-details-title').text(title);
		$content.html(bodyHtml);
		this.initPayloadSections($content);

		if (this.detailsModal) {
			this.detailsModal.open();
		}
	},

	_buildL10n() {
		const t = window.aipsTelemetryL10n || {};
		return {
			justNow:         t.insertedJustNow,
			minuteAgo:       t.insertedMinuteAgo,
			minutesAgo:      t.insertedMinutesAgo,
			hourAgo:         t.insertedHourAgo,
			hoursAgo:        t.insertedHoursAgo,
			hoursMinutesAgo: t.insertedHoursMinutesAgo,
			yesterdayAt:     t.insertedYesterdayAt,
			absoluteDate:    t.insertedAbsoluteDate,
			locale:          t.locale
		};
	},

	formatTypeLabel(value) {
		const type = String(value || '').toLowerCase();
		if (type === 'ajax') return 'AJAX';
		if (type === 'cron') return 'Cron';
		if (type === 'admin') return 'Admin';
		if (type === 'frontend') return 'Frontend';
		return this.displayValue(value);
	},

	formatCategories(value) {
		const categories = String(value || '').split(',').map(item => item.trim()).filter(Boolean);
		const T = window.AIPS.Templates;

		if (!categories.length && T) {
			return T.renderRaw('aips-tmpl-telemetry-category-list', {
				badges: T.render('aips-tmpl-telemetry-category-empty', {
					label: '—'
				})
			});
		}

		if (T) {
			const badges = categories.map(category => {
				return T.render('aips-tmpl-telemetry-category-badge', {
					class_name: this.getCategoryClass(category),
					label: this.formatCategoryLabel(category)
				});
			}).join('');

			return T.renderRaw('aips-tmpl-telemetry-category-list', {
				badges: badges
			});
		}
		return '';
	},

	formatCategoryLabel(value) {
		const category = String(value || '').toLowerCase();
		const labels = {
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

	getCategoryClass(value) {
		return String(value || '').toLowerCase().replace(/[^a-z0-9_-]/g, '') || 'general';
	},

	updatePagination(data) {
		const l10n = window.aipsTelemetryL10n || {};
		const countLabel = (l10n.telemetryTotal || '%s records').replace('%s', String(data.total || 0));
		const pageLabel = (l10n.telemetryPage || 'Page %1$s of %2$s')
			.replace('%1$s', String(data.page || 1))
			.replace('%2$s', String(data.total_pages || 1));

		this.$('#aips-telemetry-count').text(countLabel);
		this.$('#aips-telemetry-page-label').text(pageLabel);
		this.$('#aips-telemetry-per-page').val(String(data.per_page || this.perPage));
		this.$('#aips-telemetry-prev').prop('disabled', (data.page || 1) <= 1);
		this.$('#aips-telemetry-next').prop('disabled', (data.page || 1) >= (data.total_pages || 1));
	},

	updateCharts(charts) {
		if (typeof window.Chart === 'undefined') {
			const l10n = window.aipsTelemetryL10n || {};
			if (window.AIPS && window.AIPS.Utilities) {
				window.AIPS.Utilities.showToast(l10n.chartUnavailable || 'Chart library failed to load.', 'error');
			}
			return;
		}

		const labels = this.getChartLabels(charts);
		const l10n = window.aipsTelemetryL10n || {};

		this.renderChart(
			'queries',
			labels,
			charts.queries || [],
			'bar',
			l10n.chartQueriesTitle || 'Queries Executed per Day',
			l10n.chartQueriesLabel || 'Queries',
			'#2271b1'
		);
		this.renderChart(
			'memory',
			labels,
			charts.peak_memory_mb || [],
			'line',
			l10n.chartMemoryTitle || 'Peak Memory per Day',
			l10n.chartMemoryLabel || 'Peak Memory (MB)',
			'#8c8f94'
		);
		this.renderChart(
			'elapsed',
			labels,
			charts.avg_elapsed_ms || [],
			'line',
			l10n.chartElapsedTitle || 'Average Elapsed Time per Day',
			l10n.chartElapsedLabel || 'Average Elapsed (ms)',
			'#d63638'
		);
		this.renderChart(
			'requests',
			labels,
			charts.requests || [],
			'bar',
			l10n.chartRequestsTitle || 'Requests Logged per Day',
			l10n.chartRequestsLabel || 'Requests',
			'#00a32a'
		);
	},

	getChartLabels(charts) {
		const labels = charts.labels || [];
		const requests = charts.requests || [];
		const queries = charts.queries || [];
		const memory = charts.peak_memory_mb || [];
		const elapsed = charts.avg_elapsed_ms || [];
		const filtered = [];

		labels.forEach((label, index) => {
			const requestValue = parseFloat(requests[index] || 0);
			const queryValue = parseFloat(queries[index] || 0);
			const memoryValue = parseFloat(memory[index] || 0);
			const elapsedValue = parseFloat(elapsed[index] || 0);

			if (requestValue > 0 || queryValue > 0 || memoryValue > 0 || elapsedValue > 0) {
				filtered.push(label);
			}
		});

		return filtered.length ? filtered : labels;
	},

	renderChart(key, labels, data, type, title, label, color) {
		const canvas = document.getElementById('aips-telemetry-chart-' + key);
		if (!canvas) return;

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

	displayValue(value) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}
		return String(value);
	},

	toAlpha(hex, alpha) {
		const normalized = String(hex || '').replace('#', '');
		if (normalized.length !== 6) return hex;

		const red = parseInt(normalized.substring(0, 2), 16);
		const green = parseInt(normalized.substring(2, 4), 16);
		const blue = parseInt(normalized.substring(4, 6), 16);
		return 'rgba(' + red + ', ' + green + ', ' + blue + ', ' + alpha + ')';
	}
});
export default TelemetryView;
