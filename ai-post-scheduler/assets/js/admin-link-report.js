/**
 * Link Report (Content hub)
 *
 * Loads the per-post link counts over AJAX, handles search / filters /
 * sorting / paging, the per-post drill-down modal, and the link index
 * rebuild with progress polling.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.4
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.LinkReport = {
		state: {
			paged: 1,
			totalPages: 1,
			orderby: 'inbound',
			order: 'asc',
			search: '',
			postType: '',
			orphansOnly: false,
			loaded: false
		},
		searchTimer: null,
		pollTimer: null,
		suggestTarget: 0,

		init: function() {
			this.$root = $('#aips-link-report');
			if (!this.$root.length || typeof aipsLinkReportL10n === 'undefined') {
				return;
			}

			this.bindEvents();
			this.updateSortIndicators();

			if (this.$root.closest('.aips-tab-content').is(':visible')) {
				this.load();
			}

			if (String(this.$root.data('backfill-running')) === '1') {
				this.startPolling();
			} else if (String(this.$root.data('backfill-paused')) === '1') {
				$('#aips-link-report-rebuild').prop('disabled', true);
			}
		},

		bindEvents: function() {
			$(document).on('click', '.aips-rail-item[data-tab="aips-link-report"]', this.onTabShown.bind(this));
			$(document).on('input', '#aips-link-report-search', this.onSearch.bind(this));
			$(document).on('change', '#aips-link-report-post-type, #aips-link-report-view', this.onFilterChange.bind(this));
			$(document).on('click', '#aips-link-report-table .aips-sort-link', this.onSort.bind(this));
			$(document).on('click', '#aips-link-report-prev', this.onPrev.bind(this));
			$(document).on('click', '#aips-link-report-next', this.onNext.bind(this));
			$(document).on('click', '.aips-link-report-details', this.onDetails.bind(this));
			$(document).on('click', '#aips-link-report-rebuild', this.onRebuild.bind(this));
			$(document).on('click', '#aips-link-scan-start', this.startRebuild.bind(this));
			$(document).on('focus click', '#aips-link-scan-days', function() {
				$('input[name="aips_link_scan_mode"][value="recent"]').prop('checked', true);
			});
			$(document).on('click', '#aips-link-backfill-pause', this.onScanControl.bind(this, 'aips_link_report_pause_backfill'));
			$(document).on('click', '#aips-link-backfill-resume', this.onScanControl.bind(this, 'aips_link_report_resume_backfill'));
			$(document).on('click', '#aips-link-backfill-cancel', this.onCancel.bind(this));
			$(document).on('click', '.aips-link-report-suggest', this.onSuggest.bind(this));
			$(document).on('click', '#aips-link-suggestions-regenerate', this.onRegenerate.bind(this));
			$(document).on('click', '#aips-link-suggestions-close', this.onCloseSuggestions.bind(this));
			$(document).on('click', '.aips-link-suggestion-apply', this.onSuggestionAction.bind(this, 'aips_link_report_apply_suggestion'));
			$(document).on('click', '.aips-link-suggestion-revert', this.onSuggestionAction.bind(this, 'aips_link_report_revert_suggestion'));
			$(document).on('click', '.aips-link-suggestion-dismiss', this.onSuggestionAction.bind(this, 'aips_link_report_dismiss_suggestion'));
		},

		onTabShown: function() {
			if (!this.state.loaded) {
				this.load();
			}
		},

		onSearch: function(e) {
			var self = this;
			clearTimeout(this.searchTimer);
			this.searchTimer = setTimeout(function() {
				self.state.search = $(e.currentTarget).val();
				self.state.paged = 1;
				self.load();
			}, 300);
		},

		onFilterChange: function() {
			this.state.postType = $('#aips-link-report-post-type').val() || '';
			this.state.orphansOnly = $('#aips-link-report-view').val() === 'orphans';
			this.state.paged = 1;
			this.load();
		},

		onSort: function(e) {
			var orderby = $(e.currentTarget).data('orderby');

			if (this.state.orderby === orderby) {
				this.state.order = this.state.order === 'asc' ? 'desc' : 'asc';
			} else {
				this.state.orderby = orderby;
				this.state.order = orderby === 'title' ? 'asc' : 'desc';
			}

			this.state.paged = 1;
			this.updateSortIndicators();
			this.load();
		},

		onPrev: function() {
			if (this.state.paged > 1) {
				this.state.paged--;
				this.load();
			}
		},

		onNext: function() {
			if (this.state.paged < this.state.totalPages) {
				this.state.paged++;
				this.load();
			}
		},

		updateSortIndicators: function() {
			var state = this.state;
			$('#aips-link-report-table .aips-sort-link').each(function() {
				var $btn = $(this);
				if ($btn.data('orderby') === state.orderby) {
					$btn.attr('aria-sort', state.order === 'asc' ? 'ascending' : 'descending');
				} else {
					$btn.removeAttr('aria-sort');
				}
			});
		},

		load: function() {
			var self = this;
			var l10n = aipsLinkReportL10n;

			this.state.loaded = true;
			$('#aips-link-report-loading').removeClass('aips-hidden');

			$.post(ajaxurl, {
				action: 'aips_link_report_get',
				nonce: l10n.nonce,
				paged: this.state.paged,
				orderby: this.state.orderby,
				order: this.state.order,
				search: this.state.search,
				post_type: this.state.postType,
				orphans_only: this.state.orphansOnly ? 1 : 0
			}).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.loadError, 'error');
					return;
				}
				self.renderRows(response.data);
				self.renderSummary(response.data.summary);
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.loadError, 'error');
			}).always(function() {
				$('#aips-link-report-loading').addClass('aips-hidden');
			});
		},

		renderRows: function(data) {
			var html = '';

			$.each(data.rows, function(i, row) {
				html += AIPS.Templates.render('aips-tmpl-link-report-row', {
					id: row.id,
					title: row.title,
					post_type: row.post_type,
					inbound: row.inbound,
					outbound: row.outbound,
					external: row.external,
					broken: row.broken,
					edit_url: row.edit_url,
					view_url: row.view_url,
					orphan_class: row.is_orphan ? '' : 'aips-hidden',
					suggestions_class: row.suggestions > 0 ? '' : 'aips-hidden',
					suggestions_label: aipsLinkReportL10n.suggestionsPending.replace('%d', row.suggestions),
					suggest_class: (row.is_orphan ? 'aips-btn-primary' : 'aips-btn-secondary') + (row.can_suggest ? '' : ' aips-hidden')
				});
			});

			$('#aips-link-report-tbody').html(html);
			$('#aips-link-report-table').toggleClass('aips-hidden', data.rows.length === 0);
			$('#aips-link-report-empty-wrap').toggleClass('aips-hidden', data.rows.length !== 0);

			this.state.totalPages = data.total_pages;
			this.state.paged = data.page;

			$('#aips-link-report-page-info').text(
				aipsLinkReportL10n.pageInfo
					.replace('%1$d', data.page)
					.replace('%2$d', data.total_pages)
					.replace('%3$d', data.total)
			);
			$('#aips-link-report-prev').prop('disabled', data.page <= 1);
			$('#aips-link-report-next').prop('disabled', data.page >= data.total_pages);
		},

		renderSummary: function(summary) {
			if (!summary) {
				return;
			}
			$('#aips-link-stat-internal').text(summary.internal);
			$('#aips-link-stat-external').text(summary.external);
			$('#aips-link-stat-broken').text(summary.broken);

			if (summary.sources > 0 && typeof summary.orphans !== 'undefined') {
				$('#aips-link-stat-orphans').text(summary.orphans + ' / ' + summary.posts).addClass('aips-text-warning');
				$('#aips-link-stat-posts').closest('.aips-stat-total').remove();
			}
		},

		onDetails: function(e) {
			var l10n = aipsLinkReportL10n;
			var postId = $(e.currentTarget).data('post-id');
			var $modal = $('#aips-link-report-modal');

			$('#aips-link-report-modal-title').text(l10n.loading);
			$('#aips-link-report-inbound, #aips-link-report-outbound').empty();
			$('#aips-link-report-inbound-count, #aips-link-report-outbound-count').text('');
			$modal.show();

			$.post(ajaxurl, {
				action: 'aips_link_report_get_post_links',
				nonce: l10n.nonce,
				post_id: postId
			}).done(function(response) {
				if (!response || !response.success) {
					$modal.hide();
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.loadError, 'error');
					return;
				}
				AIPS.LinkReport.renderDetails(response.data);
			}).fail(function() {
				$modal.hide();
				AIPS.Utilities.showToast(l10n.loadError, 'error');
			});
		},

		renderDetails: function(data) {
			var l10n = aipsLinkReportL10n;
			var inbound = '';
			var outbound = '';

			$('#aips-link-report-modal-title').text(data.title);

			$.each(data.inbound, function(i, link) {
				inbound += AIPS.Templates.render('aips-tmpl-link-report-inbound-row', link);
			});

			$.each(data.outbound, function(i, link) {
				var typeLabel = l10n.internal;
				var typeClass = 'aips-badge-success';

				if (link.is_broken) {
					typeLabel = l10n.broken;
					typeClass = 'aips-badge-danger';
				} else if (link.type === 'external') {
					typeLabel = link.is_nofollow ? l10n.externalNofollow : l10n.external;
					typeClass = 'aips-badge-info';
				}

				outbound += AIPS.Templates.render('aips-tmpl-link-report-outbound-row', {
					anchor: link.anchor,
					url: link.url,
					destination: link.target_title || link.url,
					type_label: typeLabel,
					type_class: typeClass
				});
			});

			$('#aips-link-report-inbound').html(inbound || AIPS.Templates.render('aips-tmpl-link-report-empty-row', { colspan: 2, message: l10n.noInbound }));
			$('#aips-link-report-outbound').html(outbound || AIPS.Templates.render('aips-tmpl-link-report-empty-row', { colspan: 3, message: l10n.noOutbound }));
			$('#aips-link-report-inbound-count').text(data.inbound.length);
			$('#aips-link-report-outbound-count').text(data.outbound.length);
		},

		onRebuild: function() {
			$('#aips-link-scan-modal').show();
		},

		startRebuild: function() {
			var self = this;
			var l10n = aipsLinkReportL10n;
			var $btn = $('#aips-link-scan-start').prop('disabled', true);

			$.post(ajaxurl, {
				action: 'aips_link_report_start_backfill',
				nonce: l10n.nonce,
				mode: $('input[name="aips_link_scan_mode"]:checked').val() || 'missing',
				days: parseInt($('#aips-link-scan-days').val(), 10) || 30
			}).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.rebuildError, 'error');
					return;
				}
				$('#aips-link-scan-modal').hide();
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.renderBackfill(response.data.backfill);
				self.startPolling();
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.rebuildError, 'error');
			}).always(function() {
				$btn.prop('disabled', false);
			});
		},

		onScanControl: function(action) {
			var self = this;
			var l10n = aipsLinkReportL10n;

			$.post(ajaxurl, { action: action, nonce: l10n.nonce }).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.rebuildError, 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.renderBackfill(response.data.backfill);
				if (response.data.backfill && response.data.backfill.status === 'processing') {
					self.startPolling();
				} else {
					clearInterval(self.pollTimer);
				}
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.rebuildError, 'error');
			});
		},

		onCancel: function() {
			var self = this;
			var l10n = aipsLinkReportL10n;

			AIPS.Utilities.confirm(l10n.confirmCancel, l10n.confirmCancelTitle, [
				{ label: l10n.keepScanning, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.cancelScan,
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						$.post(ajaxurl, { action: 'aips_link_report_cancel_backfill', nonce: l10n.nonce }).done(function(response) {
							if (!response || !response.success) {
								AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.rebuildError, 'error');
								return;
							}
							clearInterval(self.pollTimer);
							AIPS.Utilities.showToast(response.data.message, 'success');
							self.renderBackfill(response.data.backfill);
							self.renderSummary(response.data.summary);
							self.load();
						});
					}
				}
			]);
		},

		startPolling: function() {
			var self = this;
			clearInterval(this.pollTimer);
			$('#aips-link-report-rebuild').prop('disabled', true);
			this.pollTimer = setInterval(function() {
				self.poll();
			}, 5000);
		},

		poll: function() {
			var self = this;
			var l10n = aipsLinkReportL10n;

			$.post(ajaxurl, {
				action: 'aips_link_report_backfill_status',
				nonce: l10n.nonce
			}).done(function(response) {
				if (!response || !response.success) {
					return;
				}
				var backfill = response.data.backfill;

				self.renderSummary(response.data.summary);
				self.renderBackfill(backfill);

				if (!backfill || backfill.status !== 'processing') {
					clearInterval(self.pollTimer);
					if (backfill && backfill.status === 'completed') {
						AIPS.Utilities.showToast(l10n.rebuildDone, 'success');
					}
					self.load();
				}
			});
		},

		onSuggest: function(e) {
			var $btn = $(e.currentTarget);
			this.suggestTarget = parseInt($btn.data('post-id'), 10);
			$('#aips-link-suggestions-title').text($btn.data('title'));
			$('#aips-link-suggestions-wrap').removeClass('aips-hidden');
			this.requestSuggestions('aips_link_report_suggest');

			var el = document.getElementById('aips-link-suggestions-wrap');
			if (el && el.scrollIntoView) {
				el.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		},

		onRegenerate: function() {
			if (this.suggestTarget) {
				this.requestSuggestions('aips_link_report_suggest');
			}
		},

		onCloseSuggestions: function() {
			this.suggestTarget = 0;
			$('#aips-link-suggestions-wrap').addClass('aips-hidden');
			this.load();
		},

		requestSuggestions: function(action) {
			var self = this;
			var l10n = aipsLinkReportL10n;

			$('#aips-link-suggestions-loading').removeClass('aips-hidden');
			$('#aips-link-suggestions-tbody').empty();

			$.post(ajaxurl, {
				action: action,
				nonce: l10n.nonce,
				post_id: this.suggestTarget
			}).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.suggestError, 'error');
					return;
				}
				self.renderSuggestions(response.data.suggestions);
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.suggestError, 'error');
			}).always(function() {
				$('#aips-link-suggestions-loading').addClass('aips-hidden');
			});
		},

		onSuggestionAction: function(action, e) {
			var self = this;
			var l10n = aipsLinkReportL10n;
			var $btn = $(e.currentTarget).prop('disabled', true);

			$.post(ajaxurl, {
				action: action,
				nonce: l10n.nonce,
				post_id: this.suggestTarget,
				suggestion_id: $btn.data('id')
			}).done(function(response) {
				if (!response || !response.success) {
					$btn.prop('disabled', false);
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.suggestError, 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.renderSuggestions(response.data.suggestions);
				self.renderSummary(response.data.summary);
			}).fail(function() {
				$btn.prop('disabled', false);
				AIPS.Utilities.showToast(l10n.suggestError, 'error');
			});
		},

		renderSuggestions: function(suggestions) {
			var l10n = aipsLinkReportL10n;
			var html = '';

			$.each(suggestions || [], function(i, item) {
				var inserted = item.status === 'inserted';
				var confidenceClass = item.confidence >= 85 ? 'aips-badge-success' : (item.confidence >= 70 ? 'aips-badge-info' : 'aips-badge-secondary');

				html += AIPS.Templates.render('aips-tmpl-link-suggestion-row', {
					id: item.id,
					status: item.status,
					source_title: item.source_title,
					source_edit: item.source_edit,
					anchor_label: item.anchor || l10n.noAnchor,
					context: item.context,
					confidence: item.confidence,
					confidence_class: confidenceClass,
					pending_class: inserted ? 'aips-hidden' : '',
					inserted_class: inserted ? '' : 'aips-hidden',
					apply_disabled: item.anchor ? '' : 'disabled'
				});
			});

			$('#aips-link-suggestions-tbody').html(html || AIPS.Templates.render('aips-tmpl-link-report-empty-row', { colspan: 4, message: l10n.noSuggestions }));
		},

		renderBackfill: function(backfill) {
			var l10n = aipsLinkReportL10n;
			var status = backfill ? backfill.status : '';
			var running = status === 'processing' || status === 'pending';
			var paused = status === 'paused';

			$('#aips-link-backfill-banner').toggleClass('aips-hidden', !running && !paused);
			$('#aips-link-backfill-spinner').toggleClass('aips-hidden', !running);
			$('#aips-link-backfill-pause').toggleClass('aips-hidden', !running);
			$('#aips-link-backfill-resume').toggleClass('aips-hidden', !paused);
			$('#aips-link-backfill-title').text(paused ? l10n.scanPaused : l10n.scanRunning);
			$('#aips-link-report-rebuild').prop('disabled', running || paused);

			if (running || paused) {
				$('#aips-link-backfill-progress').text(
					l10n.progress
						.replace('%1$d', backfill.processed)
						.replace('%2$d', backfill.total)
				);
			}
		}
	};

	$(document).ready(function() {
		AIPS.LinkReport.init();
	});
})(jQuery);
