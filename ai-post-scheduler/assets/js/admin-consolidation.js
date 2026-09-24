/**
 * Consolidation (Cannibalization Shield): choose which post of an
 * overlapping pair to keep, optionally generate an AI-merged draft, then
 * consolidate; list recent consolidations with undo.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsConsolidationL10n || {};

	AIPS.Consolidation = {
		pair: null,
		preview: null,

		init: function() {
			if (!$('#aips-consolidate-modal').length) {
				return;
			}
			this.bindEvents();
			this.renderHistory($('#aips-consolidations-tbody').data('initial') || []);
		},

		bindEvents: function() {
			$(document).on('click', '.aips-consolidate-open', this.onOpen.bind(this));
			$(document).on('change', 'input[name="aips-consolidate-keep"]', this.onKeepChange.bind(this));
			$(document).on('change', 'input[name="aips-consolidate-mode"]', this.renderSummary.bind(this));
			$(document).on('click', '#aips-consolidate-generate', this.onGenerate.bind(this));
			$(document).on('click', '#aips-consolidate-preview-toggle', this.onTogglePreview.bind(this));
			$(document).on('input', '#aips-consolidate-content', this.onContentInput.bind(this));
			$(document).on('click', '#aips-consolidate-run', this.onRun.bind(this));
			$(document).on('click', '.aips-consolidation-undo', this.onUndo.bind(this));
		},

		request: function(action, data) {
			return $.post(ajaxurl, $.extend({ action: action, nonce: l10n.nonce }, data || {}));
		},

		fail: function(response) {
			AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
		},

		keepId: function() {
			return parseInt($('input[name="aips-consolidate-keep"]:checked').val(), 10) || 0;
		},

		retireId: function() {
			var keep = this.keepId();
			return keep === this.pair.a ? this.pair.b : this.pair.a;
		},

		onOpen: function(e) {
			var $button = $(e.currentTarget);
			var self = this;

			this.pair = { a: parseInt($button.data('a'), 10), b: parseInt($button.data('b'), 10) };
			this.preview = null;

			$('#aips-consolidate-loading').removeClass('aips-hidden');
			$('#aips-consolidate-body').addClass('aips-hidden');
			$('#aips-consolidate-content').val('');
			$('#aips-consolidate-instructions').val('');
			$('#aips-consolidate-merge, #aips-consolidate-preview').addClass('aips-hidden');
			$('#aips-consolidate-run').prop('disabled', true);
			this.setMergeAvailable(false);
			$('#aips-consolidate-modal').show();

			// The first post of the pair is the default "keep"; the preview is
			// requested for that direction and flipped client-side when changed.
			this.request('aips_consolidation_preview', { keep_id: this.pair.a, retire_id: this.pair.b }).done(function(response) {
				if (!response || !response.success) {
					$('#aips-consolidate-modal').hide();
					self.fail(response);
					return;
				}
				self.preview = response.data;
				self.renderChoices();
				$('#aips-consolidate-loading').addClass('aips-hidden');
				$('#aips-consolidate-body').removeClass('aips-hidden');
				$('#aips-consolidate-generate').prop('disabled', !response.data.ai_available);
				$('#aips-consolidate-ai-off').toggleClass('aips-hidden', !!response.data.ai_available);
				$('#aips-consolidate-run').prop('disabled', false);
			}).fail(function() {
				$('#aips-consolidate-modal').hide();
				self.fail();
			});
		},

		renderChoices: function() {
			var $choices = $('#aips-consolidate-choices').empty();
			var posts = [this.preview.keep, this.preview.retire];

			posts.forEach(function(post, index) {
				$choices.append(AIPS.Templates.render('aips-tmpl-consolidate-choice', {
					id: post.id,
					title: post.title,
					url: post.url,
					meta: l10n.postMeta.replace('%1$s', post.date).replace('%2$d', post.words),
					checked: index === 0 ? 'checked' : ''
				}));
			});

			this.renderSummary();
		},

		onKeepChange: function() {
			// A merged draft is written from the kept post's point of view, so
			// changing sides discards it.
			if ($('#aips-consolidate-content').val()) {
				$('#aips-consolidate-content').val('');
				$('#aips-consolidate-merge, #aips-consolidate-preview').addClass('aips-hidden');
				this.setMergeAvailable(false);
				AIPS.Utilities.showToast(l10n.mergeDiscarded, 'info');
			}
			this.renderSummary();
		},

		setMergeAvailable: function(available) {
			$('input[name="aips-consolidate-mode"][value="revision"], input[name="aips-consolidate-mode"][value="rewrite"]').prop('disabled', !available);
			if (!available) {
				$('input[name="aips-consolidate-mode"][value="none"]').prop('checked', true);
			}
		},

		renderSummary: function() {
			if (!this.preview) {
				return;
			}
			var keepId = this.keepId();
			var keep = keepId === this.preview.keep.id ? this.preview.keep : this.preview.retire;
			var retire = keepId === this.preview.keep.id ? this.preview.retire : this.preview.keep;
			var mode = $('input[name="aips-consolidate-mode"]:checked').val();
			var lines = [
				l10n.summaryDraft.replace('%s', retire.title),
				l10n.summaryRedirect.replace('%1$s', retire.url).replace('%2$s', keep.title).replace('%3$s', this.preview.provider),
				l10n.summaryLinks.replace('%s', keep.title),
				l10n.summaryNotify
			];
			if (mode === 'revision') {
				lines.push(l10n.summaryRevision);
			} else if (mode === 'rewrite') {
				lines.push(l10n.summaryRewrite);
			}

			var $summary = $('#aips-consolidate-summary').empty();
			var $list = $('<ul>');
			$summary.append($('<strong>').text(l10n.summaryHeading));
			lines.forEach(function(line) {
				$list.append($('<li>').text(line));
			});
			$summary.append($list);
		},

		onGenerate: function() {
			var self = this;
			var $button = $('#aips-consolidate-generate');

			var req = this.request('aips_consolidation_merge', {
				keep_id: this.keepId(),
				retire_id: this.retireId(),
				instructions: $('#aips-consolidate-instructions').val()
			}).done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				$('#aips-consolidate-content').val(response.data.content);
				$('#aips-consolidate-merge').removeClass('aips-hidden');
				$('#aips-consolidate-preview').addClass('aips-hidden');
				self.setMergeAvailable(true);
				$('input[name="aips-consolidate-mode"][value="revision"]').prop('checked', true);
				self.renderSummary();
			}).fail(function() {
				self.fail();
			});

			AIPS.Utilities.withLock($button, req, { timeout: 300000 });
		},

		onTogglePreview: function() {
			// Rendered in a sandboxed iframe: no scripts, no access to this page.
			$('#aips-consolidate-preview').attr('srcdoc', $('#aips-consolidate-content').val()).toggleClass('aips-hidden');
		},

		onContentInput: function() {
			var hasContent = $.trim($('#aips-consolidate-content').val()) !== '';
			this.setMergeAvailable(hasContent);
			if (!$('#aips-consolidate-preview').hasClass('aips-hidden')) {
				$('#aips-consolidate-preview').attr('srcdoc', $('#aips-consolidate-content').val());
			}
			this.renderSummary();
		},

		onRun: function() {
			var self = this;
			var mode = $('input[name="aips-consolidate-mode"]:checked').val() || 'none';

			AIPS.Utilities.confirm(l10n.confirmRun, l10n.confirmRunTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.consolidate,
					className: 'aips-btn aips-btn-primary',
					action: function() {
						var $button = $('#aips-consolidate-run');
						var req = self.request('aips_consolidation_run', {
							keep_id: self.keepId(),
							retire_id: self.retireId(),
							content_mode: mode,
							content: mode === 'none' ? '' : $('#aips-consolidate-content').val()
						}).done(function(response) {
							if (!response || !response.success) {
								self.fail(response);
								return;
							}
							$('#aips-consolidate-modal').hide();
							AIPS.Utilities.showToast(response.data.message, 'success');
							self.renderHistory(response.data.consolidations);
							$('.aips-consolidate-open[data-a="' + self.pair.a + '"][data-b="' + self.pair.b + '"]').prop('disabled', true);
						}).fail(function() {
							self.fail();
						});
						AIPS.Utilities.withLock($button, req, { timeout: 120000 });
					}
				}
			]);
		},

		onUndo: function(e) {
			var self = this;
			var id = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(l10n.confirmUndo, l10n.confirmUndoTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.undo,
					className: 'aips-btn aips-btn-primary',
					action: function() {
						self.request('aips_consolidation_undo', { id: id }).done(function(response) {
							if (!response || !response.success) {
								self.fail(response);
								return;
							}
							AIPS.Utilities.showToast(response.data.message, response.data.warnings.length ? 'warning' : 'success');
							self.renderHistory(response.data.consolidations);
						}).fail(function() {
							self.fail();
						});
					}
				}
			]);
		},

		renderHistory: function(rows) {
			var $tbody = $('#aips-consolidations-tbody').empty();

			if (!rows || !rows.length) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-consolidation-empty', { message: l10n.noConsolidations }));
				return;
			}

			rows.forEach(function(row) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-consolidation-row', {
					id: row.id,
					row_class: row.undone ? 'aips-text-muted' : '',
					keep_url: row.keep_url,
					keep_title: row.keep_title,
					retire_edit: row.retire_edit || '#',
					retire_title: row.retire_title,
					retire_url: row.retire_url,
					content_label: l10n.contentModes[row.content_mode] || '',
					revision_url: row.revision_url || '#',
					revision_class: row.revision_url && !row.undone ? '' : 'aips-hidden',
					links_repointed: row.links_repointed,
					when: new Date(row.time * 1000).toLocaleString(),
					undo_class: row.undone ? 'aips-hidden' : '',
					undone_class: row.undone ? '' : 'aips-hidden'
				}));
			});
		}
	};

	$(document).ready(function() {
		AIPS.Consolidation.init();
	});
})(jQuery);
