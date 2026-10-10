/**
 * Redirects tab (Content hub): list, add, enable/disable and delete AIPS
 * redirects; choose the redirect provider and move existing redirects.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsRedirectsL10n || {};

	AIPS.Redirects = {
		state: { paged: 1, totalPages: 1 },
		searchTimer: null,
		filterTimer: null,

		init: function() {
			if (!$('#aips-redirects').length) {
				return;
			}
			this.bindEvents();
			this.load();
		},

		bindEvents: function() {
			$(document).on('submit', '#aips-redirect-form', this.onCreate.bind(this));
			$(document).on('input', '#aips-redirect-target', this.onTargetInput.bind(this));
			$(document).on('click', '.aips-redirect-pick', this.onPick.bind(this));
			$(document).on('click', '.aips-redirect-toggle', this.onToggle.bind(this));
			$(document).on('click', '.aips-redirect-delete', this.onDelete.bind(this));
			$(document).on('click', '#aips-redirects-provider-save', this.onProvider.bind(this, false));
			$(document).on('click', '#aips-redirects-provider-move', this.onProvider.bind(this, true));
			$(document).on('input', '#aips-redirects-search', this.onFilter.bind(this));
			$(document).on('change', '#aips-redirects-origin', this.onFilter.bind(this));
			$(document).on('click', '#aips-redirects-prev', this.onPage.bind(this, -1));
			$(document).on('click', '#aips-redirects-next', this.onPage.bind(this, 1));
		},

		request: function(action, data) {
			return $.post(ajaxurl, $.extend({ action: action, nonce: l10n.nonce }, data || {}));
		},

		fail: function(response) {
			AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
		},

		load: function() {
			var self = this;
			this.request('aips_redirects_list', {
				paged: this.state.paged,
				search: $('#aips-redirects-search').val(),
				origin: $('#aips-redirects-origin').val()
			}).done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				self.render(response.data);
			}).fail(function() {
				self.fail();
			});
		},

		render: function(data) {
			var $tbody = $('#aips-redirects-tbody').empty();

			if (!data.rows.length) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-redirect-empty', { message: l10n.noRedirects }));
			}

			data.rows.forEach(function(row) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-redirect-row', {
					id: row.id,
					source_path: row.source_path,
					source_url: row.source_url,
					origin_label: row.origin === 'consolidation' ? l10n.originConsolidation : '',
					target_url: row.target_url || '#',
					target_label: row.status_code === 410 ? l10n.gone : (row.target_title || row.target_url),
					status_code: row.status_code,
					provider_label: row.provider_label,
					provider_error: row.provider_error,
					error_class: row.provider_error ? '' : 'aips-hidden',
					hits: row.provider === 'aips' ? row.hits : '—',
					status_class: row.enabled ? 'aips-badge-success' : 'aips-badge-neutral',
					status_label: row.enabled ? l10n.active : l10n.disabled,
					toggle_to: row.enabled ? '0' : '1',
					toggle_label: row.enabled ? l10n.disable : l10n.enable
				}));
			});

			this.state.totalPages = data.total_pages;
			this.state.paged = data.page;
			$('#aips-redirects-page-info').text(l10n.pageInfo.replace('%1$d', data.page).replace('%2$d', data.total_pages).replace('%3$d', data.total));
			$('#aips-redirects-prev').prop('disabled', data.page <= 1);
			$('#aips-redirects-next').prop('disabled', data.page >= data.total_pages);
		},

		onPage: function(delta) {
			var next = this.state.paged + delta;
			if (next < 1 || next > this.state.totalPages) {
				return;
			}
			this.state.paged = next;
			this.load();
		},

		onFilter: function() {
			var self = this;
			clearTimeout(this.filterTimer);
			this.filterTimer = setTimeout(function() {
				self.state.paged = 1;
				self.load();
			}, 300);
		},

		onCreate: function(e) {
			e.preventDefault();
			var self = this;
			var targetId = parseInt($('#aips-redirect-target-id').val(), 10) || 0;
			var $button = $('#aips-redirect-save');

			var req = this.request('aips_redirects_create', {
				source: $('#aips-redirect-source').val(),
				target: targetId ? '' : $('#aips-redirect-target').val(),
				target_post_id: targetId,
				status_code: $('#aips-redirect-code').val()
			}).done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				$('#aips-redirect-source, #aips-redirect-target, #aips-redirect-target-id').val('');
				self.state.paged = 1;
				self.load();
			}).fail(function() {
				self.fail();
			});

			AIPS.Utilities.withLock($button, req);
		},

		onTargetInput: function(e) {
			var self = this;
			var term = $(e.currentTarget).val();

			$('#aips-redirect-target-id').val('');
			clearTimeout(this.searchTimer);

			if (term.length < 2 || /^(https?:)?\//i.test(term)) {
				$('#aips-redirect-results').empty();
				return;
			}

			this.searchTimer = setTimeout(function() {
				self.request('aips_link_report_search_posts', { term: term }).done(function(response) {
					var $results = $('#aips-redirect-results').empty();
					if (!response || !response.success) {
						return;
					}
					(response.data.posts || []).forEach(function(post) {
						$results.append(AIPS.Templates.render('aips-tmpl-redirect-result', post));
					});
				});
			}, 300);
		},

		onPick: function(e) {
			var $button = $(e.currentTarget);
			$('#aips-redirect-target-id').val($button.data('id'));
			$('#aips-redirect-target').val($button.data('title'));
			$('#aips-redirect-results').empty();
		},

		onToggle: function(e) {
			var self = this;
			var $button = $(e.currentTarget);
			this.request('aips_redirects_toggle', { id: $button.data('id'), enabled: String($button.data('enabled')) }).done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				AIPS.Utilities.showToast(response.data.message, 'success');
				self.load();
			}).fail(function() {
				self.fail();
			});
		},

		onDelete: function(e) {
			var self = this;
			var id = $(e.currentTarget).data('id');

			AIPS.Utilities.confirm(l10n.confirmDelete, l10n.confirmDeleteTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{
					label: l10n.deleteRedirect,
					className: 'aips-btn aips-btn-danger-solid',
					action: function() {
						self.request('aips_redirects_delete', { id: id }).done(function(response) {
							if (!response || !response.success) {
								self.fail(response);
								return;
							}
							AIPS.Utilities.showToast(response.data.message, 'success');
							self.load();
						}).fail(function() {
							self.fail();
						});
					}
				}
			]);
		},

		onProvider: function(move) {
			var self = this;
			var send = function() {
				var $button = move ? $('#aips-redirects-provider-move') : $('#aips-redirects-provider-save');
				var req = self.request('aips_redirects_set_provider', { provider: $('#aips-redirects-provider').val(), move: move ? 1 : 0 }).done(function(response) {
					if (!response || !response.success) {
						self.fail(response);
						return;
					}
					AIPS.Utilities.showToast(response.data.message, 'success');
					self.load();
				}).fail(function() {
					self.fail();
				});
				AIPS.Utilities.withLock($button, req, { timeout: 120000 });
			};

			if (!move) {
				send();
				return;
			}

			AIPS.Utilities.confirm(l10n.confirmMove, l10n.confirmMoveTitle, [
				{ label: l10n.cancel, className: 'aips-btn aips-btn-secondary' },
				{ label: l10n.moveRedirects, className: 'aips-btn aips-btn-primary', action: send }
			]);
		}
	};

	$(document).ready(function() {
		AIPS.Redirects.init();
	});
})(jQuery);
