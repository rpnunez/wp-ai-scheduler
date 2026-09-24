/**
 * Link Rules tab (Content hub): list, add, edit, enable/disable and delete
 * keyword → post link rules.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.6
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsLinkRulesL10n || {};

	AIPS.LinkRules = {
		rules: [],
		searchTimer: null,

		init: function() {
			if (!$('#aips-link-rules').length) {
				return;
			}
			this.bindEvents();
			this.load();
		},

		bindEvents: function() {
			$(document).on('submit', '#aips-link-rules-form', this.onSubmit.bind(this));
			$(document).on('click', '#aips-link-rule-cancel', this.resetForm.bind(this));
			$(document).on('input', '#aips-link-rule-target-search', this.onSearch.bind(this));
			$(document).on('click', '.aips-link-rule-pick', this.onPick.bind(this));
			$(document).on('click', '.aips-link-rule-edit', this.onEdit.bind(this));
			$(document).on('click', '.aips-link-rule-toggle', this.onToggle.bind(this));
			$(document).on('click', '.aips-link-rule-delete', this.onDelete.bind(this));
		},

		request: function(action, data) {
			return $.post(ajaxurl, $.extend({ action: action, nonce: l10n.nonce }, data || {}));
		},

		handle: function(xhr, onSuccess) {
			var self = this;
			xhr.done(function(response) {
				if (!response || !response.success) {
					AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
					return;
				}
				if (response.data.rules) {
					self.rules = response.data.rules;
					self.render();
				}
				if (response.data.message) {
					AIPS.Utilities.showToast(response.data.message, 'success');
				}
				if (onSuccess) {
					onSuccess(response.data);
				}
			}).fail(function() {
				AIPS.Utilities.showToast(l10n.error, 'error');
			});
		},

		load: function() {
			this.handle(this.request('aips_link_rules_list'));
		},

		render: function() {
			var $tbody = $('#aips-link-rules-tbody').empty();

			if (!this.rules.length) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-link-rule-empty', { message: l10n.noRules }));
				return;
			}

			this.rules.forEach(function(rule) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-link-rule-row', {
					id: rule.id,
					keyword: rule.keyword,
					target_title: rule.target_title || ('#' + rule.target_id),
					target_url: rule.target_url || '#',
					missing_class: rule.target_exists ? 'aips-hidden' : '',
					max_per_post: rule.max_per_post,
					status_class: rule.enabled ? 'aips-badge-success' : 'aips-badge-neutral',
					status_label: rule.enabled ? l10n.active : l10n.disabled,
					toggle_to: rule.enabled ? '0' : '1',
					toggle_label: rule.enabled ? l10n.disable : l10n.enable
				}));
			});
		},

		findRule: function(id) {
			return this.rules.filter(function(rule) { return rule.id === id; })[0] || null;
		},

		onSubmit: function(e) {
			e.preventDefault();

			var targetId = parseInt($('#aips-link-rule-target-id').val(), 10) || 0;
			if (!targetId) {
				AIPS.Utilities.showToast(l10n.chooseTarget, 'error');
				return;
			}

			var id = $('#aips-link-rule-id').val();
			var rule = id ? this.findRule(id) : null;
			var $button = $('#aips-link-rule-save').prop('disabled', true);

			var xhr = this.request('aips_link_rules_save', {
				id: id,
				keyword: $('#aips-link-rule-keyword').val(),
				target_id: targetId,
				max_per_post: $('#aips-link-rule-max').val(),
				enabled: rule ? (rule.enabled ? 1 : 0) : 1
			});

			this.handle(xhr, this.resetForm.bind(this));
			xhr.always(function() { $button.prop('disabled', false); });
		},

		resetForm: function() {
			$('#aips-link-rule-id, #aips-link-rule-keyword, #aips-link-rule-target-search, #aips-link-rule-target-id').val('');
			$('#aips-link-rule-max').val(1);
			$('#aips-link-rule-results').empty();
			$('#aips-link-rule-save').text(l10n.addRule);
			$('#aips-link-rule-cancel').addClass('aips-hidden');
		},

		onSearch: function(e) {
			var self = this;
			var term = $(e.currentTarget).val();

			$('#aips-link-rule-target-id').val('');
			clearTimeout(this.searchTimer);

			if (term.length < 2) {
				$('#aips-link-rule-results').empty();
				return;
			}

			this.searchTimer = setTimeout(function() {
				self.request('aips_link_report_search_posts', { term: term }).done(function(response) {
					var $results = $('#aips-link-rule-results').empty();
					if (!response || !response.success) {
						return;
					}
					(response.data.posts || []).forEach(function(post) {
						$results.append(AIPS.Templates.render('aips-tmpl-link-rule-result', post));
					});
				});
			}, 300);
		},

		onPick: function(e) {
			var $button = $(e.currentTarget);
			$('#aips-link-rule-target-id').val($button.data('id'));
			$('#aips-link-rule-target-search').val($button.data('title'));
			$('#aips-link-rule-results').empty();
		},

		onEdit: function(e) {
			var rule = this.findRule(String($(e.currentTarget).data('id')));
			if (!rule) {
				return;
			}

			$('#aips-link-rule-id').val(rule.id);
			$('#aips-link-rule-keyword').val(rule.keyword).trigger('focus');
			$('#aips-link-rule-target-id').val(rule.target_id);
			$('#aips-link-rule-target-search').val(rule.target_title);
			$('#aips-link-rule-max').val(rule.max_per_post);
			$('#aips-link-rule-save').text(l10n.updateRule);
			$('#aips-link-rule-cancel').removeClass('aips-hidden');
		},

		onToggle: function(e) {
			var $button = $(e.currentTarget);
			this.handle(this.request('aips_link_rules_toggle', {
				id: $button.data('id'),
				enabled: String($button.data('enabled'))
			}));
		},

		onDelete: function(e) {
			var self = this;
			var id = String($(e.currentTarget).data('id'));

			AIPS.Utilities.confirm(l10n.confirmDelete, l10n.confirmDeleteTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.deleteRule,
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						self.handle(self.request('aips_link_rules_delete', { id: id }), function() {
							if ($('#aips-link-rule-id').val() === id) {
								self.resetForm();
							}
						});
					}
				}
			]);
		}
	};

	$(document).ready(function() {
		AIPS.LinkRules.init();
	});
})(jQuery);
