/**
 * Duplicate Review (Cannibalization Shield): small groups of near-duplicate
 * posts, each with a recommended post to keep. Merge into the keeper with the
 * Consolidate dialog, or mark a group as "not duplicates".
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsDuplicateReviewL10n || {};

	AIPS.DuplicateReview = {

		init: function() {
			if (!$('#aips-dup-review').length) {
				return;
			}
			this.bindEvents();

			// Every Content tab is rendered on one page, so wait until this tab is
			// actually shown before running the query.
			var self = this;
			var panel = $('#aips-dup-review')[0];
			if ('IntersectionObserver' in window) {
				var observer = new IntersectionObserver(function(entries) {
					if (entries.some(function(entry) { return entry.isIntersecting; })) {
						observer.disconnect();
						self.scan();
					}
				});
				observer.observe(panel);
			} else {
				this.scan();
			}
		},

		bindEvents: function() {
			$('#aips-dup-scan-btn').on('click', this.scan.bind(this));
			$('#aips-dup-reset-btn').on('click', this.onReset.bind(this));
			$(document).on('click', '.aips-dup-dismiss', this.onDismiss.bind(this));
			$(document).on('click', '.aips-dup-group-toggle', this.onToggle.bind(this));
			// Refresh after a consolidation changes which posts are published.
			$(document).on('aips:consolidated', this.scan.bind(this));
		},

		request: function(action, data) {
			return $.post(ajaxurl, $.extend({ action: action, nonce: l10n.nonce }, data || {}));
		},

		fail: function(response) {
			AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
		},

		format: function(template, values) {
			return String(template).replace(/%(\d)\$[ds]/g, function(match, index) {
				return values[parseInt(index, 10) - 1];
			});
		},

		scan: function() {
			var self = this;
			var $button = $('#aips-dup-scan-btn');
			var threshold = parseFloat($('#aips-dup-threshold').val());

			$('#aips-dup-loading').removeClass('aips-hidden');
			$button.prop('disabled', true);

			this.request('aips_duplicate_groups', { threshold: isNaN(threshold) ? '' : threshold }).done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				self.render(response.data);
			}).fail(function() {
				self.fail();
			}).always(function() {
				$('#aips-dup-loading').addClass('aips-hidden');
				$button.prop('disabled', false);
			});
		},

		render: function(data) {
			var self = this;
			var stats = data.stats || {};
			var groups = data.groups || [];
			var $container = $('#aips-dup-groups').empty();

			this.renderSummary(stats);

			if (!groups.length) {
				$container.append(AIPS.Templates.render('aips-tmpl-dup-empty', {
					title: l10n.emptyTitle,
					message: this.format(l10n.emptyMessage, [Math.round((stats.threshold || 0) * 100)])
				}));
				return;
			}

			groups.forEach(function(group) {
				$container.append(self.renderGroup(group));
			});
		},

		renderSummary: function(stats) {
			var parts = [];
			if (stats.group_count) {
				parts.push(this.format(l10n.summary, [stats.group_count, stats.post_count]));
			}
			if (stats.pairs_dismissed) {
				parts.push(this.format(l10n.hidden, [stats.pairs_dismissed]));
			}
			if (stats.pairs_excluded) {
				parts.push(this.format(l10n.excluded, [stats.pairs_excluded]));
			}
			if (stats.pairs_truncated) {
				parts.push(l10n.truncated);
			}

			$('#aips-dup-summary').text(parts.join(' · ')).toggleClass('aips-hidden', !parts.length);
			$('#aips-dup-reset-btn')
				.toggleClass('aips-hidden', !stats.dismissed_total)
				.find('.aips-dup-reset-count').text(stats.dismissed_total || 0);
		},

		renderGroup: function(group) {
			var self = this;
			var keepId = group.recommended_keep_id;
			var rows = '';

			(group.posts || []).forEach(function(post) {
				rows += self.renderRow(post, keepId);
			});

			return AIPS.Templates.renderRaw('aips-tmpl-dup-group', {
				id: AIPS.Templates.escape(group.id),
				postIds: group.posts.map(function(post) { return post.id; }).join(','),
				title: AIPS.Templates.escape(this.format(l10n.groupTitle, [group.post_count])),
				minPct: group.min_similarity_pct,
				avgPct: group.avg_similarity_pct,
				rowsHtml: rows
			});
		},

		renderRow: function(post, keepId) {
			var isKeep = post.id === keepId;
			var badge = '';
			if (isKeep) {
				badge = AIPS.Templates.render('aips-tmpl-dup-badge', { cls: 'aips-badge-success', label: l10n.keep });
			} else if (post.protected) {
				badge = AIPS.Templates.render('aips-tmpl-dup-badge', { cls: 'aips-badge-warning', label: l10n.protectedLabel });
			}

			var merge = '';
			if (!isKeep && !post.protected) {
				merge = AIPS.Templates.render('aips-tmpl-dup-merge-btn', { keep: keepId, retire: post.id });
			}

			return AIPS.Templates.renderRaw('aips-tmpl-dup-row', {
				rowClass: isKeep ? 'is-keep' : '',
				badge: badge,
				title: AIPS.Templates.escape(post.title || l10n.untitled),
				postType: AIPS.Templates.escape(post.post_type || 'post'),
				postId: post.id,
				date: AIPS.Templates.escape(post.post_date || ''),
				words: Number(post.words || 0).toLocaleString(),
				links: post.inbound_links,
				reasons: AIPS.Templates.escape((post.reasons || []).join(' · ')),
				viewUrl: AIPS.Templates.escape(post.url || '#'),
				editUrl: AIPS.Templates.escape(post.edit_url || '#'),
				mergeBtn: merge
			});
		},

		onToggle: function(e) {
			var $card = $(e.currentTarget).closest('.aips-dup-group');
			$card.toggleClass('is-collapsed');
			$(e.currentTarget).attr('aria-expanded', !$card.hasClass('is-collapsed'));
		},

		onDismiss: function(e) {
			var self = this;
			var ids = String($(e.currentTarget).closest('.aips-dup-group').data('post-ids')).split(',');

			AIPS.Utilities.confirm(l10n.confirmDismiss, l10n.confirmDismissTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.dismiss,
					className: 'aips-btn aips-btn-primary',
					action: function() {
						self.request('aips_duplicate_dismiss', { post_ids: ids }).done(function(response) {
							if (!response || !response.success) {
								self.fail(response);
								return;
							}
							AIPS.Utilities.showToast(response.data.message, 'success');
							self.scan();
						}).fail(function() {
							self.fail();
						});
					}
				}
			]);
		},

		onReset: function() {
			var self = this;

			AIPS.Utilities.confirm(l10n.confirmReset, l10n.confirmResetTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.reset,
					className: 'aips-btn aips-btn-primary',
					action: function() {
						self.request('aips_duplicate_reset_dismissed').done(function(response) {
							if (!response || !response.success) {
								self.fail(response);
								return;
							}
							AIPS.Utilities.showToast(response.data.message, 'success');
							self.scan();
						}).fail(function() {
							self.fail();
						});
					}
				}
			]);
		}
	};

	$(document).ready(function() {
		AIPS.DuplicateReview.init();
	});
})(jQuery);
