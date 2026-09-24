/**
 * Silos tab (Content hub): silo link coverage, Fix silo, confirm or change
 * pillars, and re-detect clusters.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;
	var l10n = window.aipsSilosL10n || {};

	AIPS.Silos = {
		data: null,

		init: function() {
			if (!$('#aips-silos').length) {
				return;
			}
			this.bindEvents();
			this.load();
		},

		bindEvents: function() {
			$(document).on('click', '#aips-silos-refresh', this.onRefresh.bind(this));
			$(document).on('click', '.aips-silo-fix', this.onFix.bind(this));
			$(document).on('click', '.aips-silo-toggle', this.onToggle.bind(this));
			$(document).on('click', '.aips-silo-change', this.onChange.bind(this));
			$(document).on('click', '.aips-silo-confirm', this.onConfirm.bind(this));
			$(document).on('click', '.aips-silo-confirm-picked', this.onConfirmPicked.bind(this));
		},

		request: function(action, data) {
			return $.post(ajaxurl, $.extend({ action: action, nonce: l10n.nonce }, data || {}));
		},

		fail: function(response) {
			AIPS.Utilities.showToast((response && response.data && response.data.message) || l10n.error, 'error');
		},

		load: function() {
			var self = this;
			this.request('aips_silos_overview').done(function(response) {
				if (!response || !response.success) {
					self.fail(response);
					return;
				}
				self.render(response.data);
			}).fail(function() {
				self.fail();
			});
		},

		/**
		 * Handle a response that returns the overview (and a message).
		 */
		handle: function(response, toastType) {
			if (!response || !response.success) {
				this.fail(response);
				return false;
			}
			if (response.data.message) {
				AIPS.Utilities.showToast(response.data.message, toastType || 'success');
			}
			this.render(response.data);
			return true;
		},

		render: function(data) {
			this.data = data;
			$('#aips-silos-loading').addClass('aips-hidden');
			this.renderSilos(data.silos || []);
			this.renderCandidates(data.candidates || [], data.has_clusters);
		},

		renderSilos: function(silos) {
			var $list = $('#aips-silos-list').empty();

			if (!silos.length) {
				$list.append(AIPS.Templates.render('aips-tmpl-silo-empty', { message: l10n.noSilos }));
				return;
			}

			silos.forEach(function(silo) {
				var $card = $(AIPS.Templates.render('aips-tmpl-silo-card', {
					cluster_id: silo.cluster_id,
					name: silo.name,
					pillar_title: silo.pillar_title,
					pillar_url: silo.pillar_url,
					health: silo.health,
					health_class: silo.health >= 90 ? 'is-good' : (silo.health >= 60 ? 'is-fair' : 'is-poor'),
					health_label: l10n.health.replace('%d', silo.health),
					up_label: l10n.upLabel.replace('%1$d', silo.links_up).replace('%2$d', silo.total),
					down_label: l10n.downLabel
						.replace('%1$d', silo.down_text + silo.down_guide)
						.replace('%2$d', silo.total)
						.replace('%3$d', silo.down_text)
						.replace('%4$d', silo.down_guide),
					fix_disabled: silo.links_up >= silo.total ? 'disabled' : ''
				}));

				var $select = $card.find('.aips-silo-pillar-select');
				silo.choices.forEach(function(choice) {
					$select.append(AIPS.Templates.render('aips-tmpl-silo-option', {
						id: choice.id,
						title: choice.title,
						selected: choice.id === silo.pillar_id ? 'selected' : ''
					}));
				});

				var $tbody = $card.find('.aips-silo-members tbody');
				silo.members.forEach(function(member) {
					$tbody.append(AIPS.Templates.render('aips-tmpl-silo-member', {
						title: member.title,
						edit_url: member.edit_url || member.url,
						up_class: member.links_up ? 'aips-badge-success' : 'aips-badge-warning',
						up_label: member.links_up ? l10n.yes : l10n.missing,
						down_class: member.down === 'none' ? 'aips-badge-neutral' : 'aips-badge-success',
						down_label: l10n.down[member.down] || ''
					}));
				});

				$list.append($card);
			});
		},

		renderCandidates: function(candidates, hasClusters) {
			var $tbody = $('#aips-silos-candidates-tbody').empty();

			if (!candidates.length) {
				$tbody.append(AIPS.Templates.render('aips-tmpl-silo-candidates-empty', {
					message: hasClusters ? l10n.allConfirmed : l10n.noClusters
				}));
				return;
			}

			candidates.forEach(function(candidate) {
				var suggested = candidate.suggested || { id: 0, title: '', url: '#', inbound: 0, words: 0, topic: 0 };
				var $row = $(AIPS.Templates.render('aips-tmpl-silo-candidate', {
					cluster_id: candidate.cluster_id,
					name: candidate.name,
					total_label: l10n.articles.replace('%d', candidate.total),
					id: suggested.id,
					title: suggested.title,
					url: suggested.url,
					reasons: l10n.reasons
						.replace('%1$d', suggested.inbound)
						.replace('%2$d', suggested.words)
						.replace('%3$d', suggested.topic)
				}));

				var $select = $row.find('.aips-silo-pick');
				candidate.choices.forEach(function(choice) {
					$select.append(AIPS.Templates.render('aips-tmpl-silo-option', {
						id: choice.id,
						title: choice.title,
						selected: choice.id === suggested.id ? 'selected' : ''
					}));
				});

				$tbody.append($row);
			});
		},

		confirm: function(clusterId, postId, $button) {
			var self = this;
			var req = this.request('aips_silos_confirm_pillar', { cluster_id: clusterId, post_id: postId }).done(function(response) {
				self.handle(response);
			}).fail(function() {
				self.fail();
			});
			AIPS.Utilities.withLock($button, req);
		},

		onConfirm: function(e) {
			var $button = $(e.currentTarget);
			this.confirm($button.data('cluster'), $button.data('post'), $button);
		},

		onConfirmPicked: function(e) {
			var $button = $(e.currentTarget);
			var clusterId = $button.data('cluster');
			this.confirm(clusterId, $('.aips-silo-pick[data-cluster="' + clusterId + '"]').val(), $button);
		},

		onChange: function(e) {
			var $button = $(e.currentTarget);
			var clusterId = $button.data('cluster');
			var postId = parseInt($('.aips-silo-pillar-select[data-cluster="' + clusterId + '"]').val(), 10);
			var silo = (this.data.silos || []).filter(function(s) { return s.cluster_id === clusterId; })[0];

			if (silo && silo.pillar_id === postId) {
				return;
			}
			this.confirm(clusterId, postId, $button);
		},

		onToggle: function(e) {
			var $button = $(e.currentTarget);
			var $table = $('.aips-silo-members[data-cluster="' + $button.data('cluster') + '"]');
			$table.toggleClass('aips-hidden');
			$button.text($table.hasClass('aips-hidden') ? l10n.showArticles : l10n.hideArticles);
		},

		onFix: function(e) {
			var self = this;
			var $button = $(e.currentTarget);
			var req = this.request('aips_silos_fix', { cluster_id: $button.data('cluster') }).done(function(response) {
				self.handle(response);
			}).fail(function() {
				self.fail();
			});
			AIPS.Utilities.withLock($button, req, { timeout: 180000 });
		},

		onRefresh: function(e) {
			var self = this;
			var req = this.request('aips_silos_refresh').done(function(response) {
				self.handle(response, 'info');
			}).fail(function() {
				self.fail();
			});
			AIPS.Utilities.withLock($(e.currentTarget), req, { timeout: 180000 });
		}
	};

	$(document).ready(function() {
		AIPS.Silos.init();
	});
})(jQuery);
