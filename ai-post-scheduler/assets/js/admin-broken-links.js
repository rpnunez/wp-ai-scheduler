/**
 * Broken Links (Content hub)
 *
 * Loads broken internal links with suggested replacements over AJAX, and
 * handles re-pointing, unlinking, manual replacement picking, and undo.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.BrokenLinks = {
		rows: [],
		page: 1,
		totalPages: 1,
		pickRow: null,
		searchTimer: null,

		init: function() {
			this.$root = $('#aips-broken-links');
			if (!this.$root.length || typeof aipsBrokenLinksL10n === 'undefined') {
				return;
			}

			this.bindEvents();
		},

		bindEvents: function() {
			$(document).on('click', '#aips-broken-links-load', this.load.bind(this, 1));
			$(document).on('click', '#aips-broken-links-prev', function() { this.load(this.page - 1); }.bind(this));
			$(document).on('click', '#aips-broken-links-next', function() { this.load(this.page + 1); }.bind(this));
			$(document).on('change', '.aips-broken-choice', this.onBrokenChoice.bind(this));
			$(document).on('click', '.aips-broken-fix', this.onFixBroken.bind(this));
			$(document).on('click', '.aips-broken-undo', this.onUndoFix.bind(this));
			$(document).on('input', '#aips-broken-pick-search', this.onPickSearch.bind(this));
			$(document).on('click', '.aips-broken-pick', this.onPick.bind(this));
		},

		load: function(page) {
			var self = this;
			var l10n = aipsBrokenLinksL10n;

			$('#aips-broken-links-loading').removeClass('aips-hidden');

			$.post(ajaxurl, { action: 'aips_broken_links_get', nonce: l10n.nonce, paged: Math.max(1, page || 1) }).done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.brokenError, 'error');
					return;
				}
				self.render(response.data);
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.brokenError, 'error');
			}).always(function() {
				$('#aips-broken-links-loading').addClass('aips-hidden');
			});
		},

		render: function(data) {
			var l10n = aipsBrokenLinksL10n;
			var self = this;
			var html = '';

			this.rows = data.rows;
			this.page = data.page;
			this.totalPages = data.total_pages;

			$.each(data.rows, function(i, row) {
				html += AIPS.Templates.render('aips-tmpl-broken-row', {
					index: i,
					source_title: row.source_title,
					source_edit: row.source_edit,
					anchor: row.anchor || l10n.noAnchorText,
					url: row.url,
					occurrences_label: l10n.occurrences.replace('%d', row.occurrences),
					occurrences_class: row.occurrences > 1 ? '' : 'aips-hidden'
				});
			});

			$('#aips-broken-links-tbody').html(html || AIPS.Templates.render('aips-tmpl-broken-links-empty-row', { colspan: 4, message: l10n.noBroken }));
			$('#aips-broken-links-table').removeClass('aips-hidden');

			$.each(data.rows, function(i) {
				self.fillChoices(i);
			});

			$('#aips-broken-links-pagination').toggleClass('aips-hidden', data.total_pages <= 1);
			$('#aips-broken-links-page-info').text(l10n.pageInfo.replace('%1$d', data.page).replace('%2$d', data.total_pages).replace('%3$d', data.total));
			$('#aips-broken-links-prev').prop('disabled', data.page <= 1);
			$('#aips-broken-links-next').prop('disabled', data.page >= data.total_pages);

			this.renderStat(data.summary);
			this.renderFixes(data.fixes);
		},

		renderStat: function(summary) {
			if (summary && typeof summary.broken !== 'undefined') {
				$('#aips-broken-links-stat-count').text(summary.broken);
			}
		},

		fillChoices: function(index, picked) {
			var l10n = aipsBrokenLinksL10n;
			var row = this.rows[index];
			var options = '';

			if (picked) {
				options += AIPS.Templates.render('aips-tmpl-broken-option', { value: picked.id, label: picked.title });
			}
			$.each(row.suggestions, function(i, suggestion) {
				options += AIPS.Templates.render('aips-tmpl-broken-option', {
					value: suggestion.id,
					label: l10n.suggestionOption.replace('%1$s', suggestion.title).replace('%2$d', suggestion.score).replace('%%', '%')
				});
			});
			options += AIPS.Templates.render('aips-tmpl-broken-option', { value: 'pick', label: l10n.choosePost });
			options += AIPS.Templates.render('aips-tmpl-broken-option', { value: 'unlink', label: l10n.removeLink });

			$('#aips-broken-choice-' + index).html(options);
		},

		onBrokenChoice: function(e) {
			var $select = $(e.currentTarget);
			if ($select.val() !== 'pick') {
				return;
			}
			this.pickRow = parseInt($select.data('row'), 10);
			$('#aips-broken-pick-search').val('');
			$('#aips-broken-pick-results').empty();
			$('#aips-broken-pick-modal').show();
			$('#aips-broken-pick-search').trigger('focus');
		},

		onPickSearch: function(e) {
			var term = $(e.currentTarget).val();
			clearTimeout(this.searchTimer);
			this.searchTimer = setTimeout(function() {
				$.post(ajaxurl, { action: 'aips_broken_links_search_posts', nonce: aipsBrokenLinksL10n.nonce, term: term }).done(function(response) {
					var html = '';
					if (response && response.success) {
						$.each(response.data.posts, function(i, post) {
							html += AIPS.Templates.render('aips-tmpl-broken-pick-result', post);
						});
					}
					$('#aips-broken-pick-results').html(html);
				});
			}, 300);
		},

		onPick: function(e) {
			var $btn = $(e.currentTarget);
			var index = this.pickRow;

			this.fillChoices(index, { id: $btn.data('id'), title: $btn.data('title') });
			$('#aips-broken-choice-' + index).val(String($btn.data('id')));
			$('#aips-broken-pick-modal').hide();
		},

		onFixBroken: function(e) {
			var self = this;
			var l10n = aipsBrokenLinksL10n;
			var index = parseInt($(e.currentTarget).data('row'), 10);
			var row = this.rows[index];
			var choice = $('#aips-broken-choice-' + index).val();
			var $btn = $(e.currentTarget);

			if (!row || !choice || choice === 'pick') {
				AIPS.Utilities.showToast(l10n.chooseFirst, 'warning');
				return;
			}

			$btn.prop('disabled', true);
			$.post(ajaxurl, {
				action: 'aips_broken_links_fix',
				nonce: l10n.nonce,
				source_id: row.source_id,
				url: row.url,
				mode: choice === 'unlink' ? 'unlink' : 'repoint',
				target_id: choice === 'unlink' ? 0 : choice
			}).done(function(response) {
				if (!response || !response.success) {
					$btn.prop('disabled', false);
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.brokenError, 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.renderStat(response.data.summary);
				self.load(self.page);
			}).fail(function() {
				$btn.prop('disabled', false);
				AIPS.Utilities.showToast(l10n.brokenError, 'error');
			});
		},

		onUndoFix: function(e) {
			var self = this;
			var l10n = aipsBrokenLinksL10n;
			var $btn = $(e.currentTarget).prop('disabled', true);

			$.post(ajaxurl, { action: 'aips_broken_links_undo_fix', nonce: l10n.nonce, fix_id: $btn.data('fix-id') }).done(function(response) {
				if (!response || !response.success) {
					$btn.prop('disabled', false);
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.brokenError, 'error');
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.renderStat(response.data.summary);
				self.load(self.page);
			}).fail(function() {
				$btn.prop('disabled', false);
				AIPS.Utilities.showToast(l10n.brokenError, 'error');
			});
		},

		renderFixes: function(fixes) {
			var l10n = aipsBrokenLinksL10n;
			var html = '';

			$.each(fixes || [], function(i, fix) {
				html += AIPS.Templates.render('aips-tmpl-broken-fix', {
					id: fix.id,
					action_label: fix.action === 'unlink' ? l10n.fixUnlinked : l10n.fixRepointed,
					action_class: fix.action === 'unlink' ? 'aips-badge-secondary' : 'aips-badge-success',
					source_title: fix.source_title,
					source_edit: fix.source_edit,
					detail: fix.new_url ? fix.old_url + ' → ' + fix.new_url : fix.old_url,
					undo_class: fix.undone ? 'aips-hidden' : '',
					undone_class: fix.undone ? '' : 'aips-hidden'
				});
			});

			$('#aips-broken-fixes').html(html);
			$('#aips-broken-fixes-wrap').toggleClass('aips-hidden', !html);
		}
	};

	$(document).ready(function() {
		AIPS.BrokenLinks.init();
	});
})(jQuery);
