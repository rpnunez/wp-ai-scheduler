/**
 * Classic Editor "Internal Links" meta box.
 *
 * Loads the post's link counts, linking posts and inbound suggestions, and
 * handles Suggest / Insert / Dismiss / Undo through the Link Report AJAX
 * endpoints.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	AIPS.LinkPanel = {
		postId: 0,

		init: function() {
			var $panel = $('#aips-link-panel');
			if (!$panel.length || typeof aipsLinkPanelL10n === 'undefined') {
				return;
			}

			this.postId = parseInt($panel.data('post-id'), 10) || 0;
			this.bindEvents();
			this.load();
		},

		bindEvents: function() {
			$(document).on('click', '#aips-link-panel-suggest', this.onSuggest.bind(this));
			$(document).on('click', '.aips-link-panel-apply', this.onAction.bind(this, 'aips_link_report_apply_suggestion'));
			$(document).on('click', '.aips-link-panel-dismiss', this.onAction.bind(this, 'aips_link_report_dismiss_suggestion'));
			$(document).on('click', '.aips-link-panel-revert', this.onAction.bind(this, 'aips_link_report_revert_suggestion'));
		},

		request: function(action, extra) {
			return $.post(aipsLinkPanelL10n.ajaxurl, $.extend({
				action: action,
				nonce: aipsLinkPanelL10n.nonce,
				post_id: this.postId
			}, extra || {}));
		},

		load: function(message) {
			var self = this;
			var l10n = aipsLinkPanelL10n;

			if (!this.postId) {
				$('#aips-link-panel-status').text(l10n.notPublished);
				return;
			}

			this.request('aips_link_report_get_post_panel').done(function(response) {
				if (!response || !response.success) {
					$('#aips-link-panel-status').text(l10n.loadError);
					return;
				}
				self.render(response.data);
				if (message) {
					self.message(message);
				}
			}).fail(function() {
				$('#aips-link-panel-status').text(l10n.loadError);
			});
		},

		render: function(data) {
			var l10n = aipsLinkPanelL10n;
			var $status = $('#aips-link-panel-status');
			var $body = $('#aips-link-panel-body');

			if (!data.in_scope) {
				$status.text(l10n.notPublished).show();
				$body.prop('hidden', true);
				return;
			}

			if (!data.indexed) {
				$status.text(l10n.notIndexed).show();
			} else {
				$status.hide();
			}

			$body.html(AIPS.Templates.render('aips-tmpl-link-panel-body', {
				inbound: data.counts.inbound,
				outbound: data.counts.outbound,
				external: data.counts.external,
				broken: data.counts.broken,
				inbound_label: l10n.inbound,
				outbound_label: l10n.outbound,
				external_label: l10n.external,
				broken_label: l10n.broken,
				broken_class: data.counts.broken > 0 ? 'aips-link-panel-broken' : '',
				orphan_label: l10n.orphan,
				orphan_class: data.is_orphan ? '' : 'hidden',
				linked_from_label: l10n.linkedFrom,
				sources_class: data.sources.length ? '' : 'hidden',
				suggestions_label: l10n.suggestions,
				well_linked_label: l10n.wellLinked,
				well_linked_class: data.can_suggest ? 'hidden' : '',
				suggest_label: data.suggestions.length ? l10n.suggestAgainBtn : l10n.suggestBtn,
				suggest_class: data.can_suggest ? '' : 'hidden',
				report_url: data.report_url,
				report_label: l10n.openReport
			})).prop('hidden', false);

			var sources = '';
			$.each(data.sources, function(i, source) {
				sources += AIPS.Templates.render('aips-tmpl-link-panel-source', source);
			});
			$('#aips-link-panel-sources').html(sources);

			this.renderSuggestions(data.suggestions);
		},

		renderSuggestions: function(suggestions) {
			var l10n = aipsLinkPanelL10n;
			var html = '';

			$.each(suggestions || [], function(i, item) {
				var inserted = item.status === 'inserted';
				html += AIPS.Templates.render('aips-tmpl-link-panel-suggestion', {
					id: item.id,
					status: item.status,
					source_title: item.source_title,
					source_edit: item.source_edit,
					confidence: item.confidence,
					context: item.context || l10n.noAnchor,
					pending_class: inserted ? 'hidden' : '',
					inserted_class: inserted ? '' : 'hidden',
					apply_disabled: item.anchor ? '' : 'disabled',
					insert_label: l10n.insertBtn,
					dismiss_label: l10n.dismissBtn,
					undo_label: l10n.undoBtn,
					inserted_label: l10n.inserted
				});
			});

			$('#aips-link-panel-suggestions').html(html);
		},

		onSuggest: function() {
			var self = this;
			var l10n = aipsLinkPanelL10n;
			var $btn = $('#aips-link-panel-suggest').prop('disabled', true);

			this.message(l10n.suggesting);
			this.request('aips_link_report_suggest').done(function(response) {
				if (!response || !response.success) {
					self.message((response && response.data && response.data.message) || l10n.actionError, true);
					return;
				}
				self.renderSuggestions(response.data.suggestions);
				self.message(response.data.suggestions.length ? '' : l10n.noSuggestions);
			}).fail(function() {
				self.message(l10n.actionError, true);
			}).always(function() {
				$btn.prop('disabled', false).text(l10n.suggestAgainBtn);
			});
		},

		onAction: function(action, e) {
			var self = this;
			var l10n = aipsLinkPanelL10n;
			var $btn = $(e.currentTarget).prop('disabled', true);

			this.request(action, { suggestion_id: $btn.data('id') }).done(function(response) {
				if (!response || !response.success) {
					$btn.prop('disabled', false);
					self.message((response && response.data && response.data.message) || l10n.actionError, true);
					return;
				}
				self.load(response.data.message);
			}).fail(function() {
				$btn.prop('disabled', false);
				self.message(l10n.actionError, true);
			});
		},

		message: function(text, isError) {
			$('#aips-link-panel-message').text(text || '').toggleClass('aips-link-panel-error', !!isError);
		}
	};

	$(document).ready(function() {
		AIPS.LinkPanel.init();
	});
})(jQuery);
