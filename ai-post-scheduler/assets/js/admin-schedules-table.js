/**
 * Schedules table: click-to-expand rows and lazily loaded details
 * (Activity, Health, Recent output) for Automations → Schedules.
 *
 * Expanding is handled by AIPS.onRowExpandToggle (admin.js), which triggers
 * `aips:row-expanded` on the details row. This module fills the row on first
 * expand and re-fetches it when `aips:schedule-changed` fires for that schedule
 * (run now, toggle, circuit reset).
 *
 * @package AI_Post_Scheduler
 */
(function($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var WRAP = '.aips-schedules-table-wrap';

	AIPS.SchedulesTable = {

		/**
		 * In-flight requests keyed by "type:id" so double expands don't double fetch.
		 *
		 * @type {Object<string, jqXHR>}
		 */
		pending: {},

		init: function() {
			if (!$(WRAP).length) {
				return;
			}
			this.bindEvents();
		},

		bindEvents: function() {
			$(document).on('aips:row-expanded', WRAP + ' .aips-schedule-details-row', this.onRowExpanded.bind(this));
			$(document).on('aips:schedule-changed', this.onScheduleChanged.bind(this));
			$(document).on('click', WRAP + ' .aips-sched-details-retry', this.onRetry.bind(this));
			$(document).on('click', WRAP + ' td.column-title', this.onTitleCellClick.bind(this));
		},

		/**
		 * Clicking the schedule name or empty space in its cell toggles the row,
		 * except when the click lands on an interactive element.
		 *
		 * @param {Event} e Click event.
		 */
		onTitleCellClick: function(e) {
			var $target = $(e.target);
			if ($target.closest('a, button, input, label, select, textarea, .row-actions, .toggle-row').length) {
				return;
			}
			// Don't hijack text selection.
			if (window.getSelection && String(window.getSelection()).length > 0) {
				return;
			}
			$(e.currentTarget).find('.aips-row-expand-toggle').first().trigger('click');
		},

		onRowExpanded: function(e) {
			var $details = $(e.currentTarget).find('.aips-sched-details');
			if ($details.length && String($details.attr('data-loaded')) !== '1') {
				this.load($details);
			}
		},

		/**
		 * Mark a schedule's details stale; re-fetch now if the row is open.
		 *
		 * @param {Event}  e    Event.
		 * @param {string} type Schedule type.
		 * @param {number} id   Schedule ID.
		 */
		onScheduleChanged: function(e, type, id) {
			if (!type || !id) {
				return;
			}
			var $details = $(WRAP + ' .aips-sched-details[data-type="' + type + '"][data-id="' + id + '"]');
			if (!$details.length) {
				return;
			}
			$details.attr('data-loaded', '0');
			if (!$details.closest('tr').prop('hidden')) {
				this.load($details);
			}
		},

		onRetry: function(e) {
			e.preventDefault();
			this.load($(e.currentTarget).closest('.aips-sched-details'));
		},

		/**
		 * Fetch and render a details row.
		 *
		 * @param {jQuery} $details `.aips-sched-details` container.
		 */
		load: function($details) {
			var self = this;
			var id   = $details.data('id');
			var type = $details.data('type');
			var key  = type + ':' + id;

			if (this.pending[key]) {
				return;
			}

			// Existing content stays visible while a refresh is in flight.
			$details.attr('aria-busy', 'true');

			this.pending[key] = $.ajax({
				url: aipsAjax.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aips_get_schedule_details',
					nonce: aipsAjax.nonce,
					id: id,
					type: type
				}
			}).done(function(response) {
				if (response && response.success && response.data) {
					$details.html(self.renderDetails(response.data, id, type));
					$details.attr('data-loaded', '1');
				} else {
					self.renderError($details, response && response.data && response.data.message);
				}
			}).fail(function() {
				self.renderError($details);
			}).always(function() {
				delete self.pending[key];
				$details.removeAttr('aria-busy');
			});
		},

		renderError: function($details, message) {
			var l10n = window.aipsScheduleL10n || {};
			$details.html(AIPS.Templates.render('aips-tmpl-sched-details-error', {
				message: message || l10n.detailsLoadFailed || 'Could not load schedule details.'
			}));
		},

		/**
		 * Build the full details markup. Every dynamic value goes through
		 * AIPS.Templates.render (escaped); renderRaw only stitches already-rendered parts.
		 *
		 * @param {Object} data Response payload: activity, health, recent, recent_limit.
		 * @param {number} id   Schedule ID.
		 * @param {string} type Schedule type.
		 * @return {string}
		 */
		renderDetails: function(data, id, type) {
			var l10n     = window.aipsScheduleL10n || {};
			var activity = data.activity || {};
			var limit    = data.recent_limit || 5;

			var recentTitle = (l10n.recentOutputTitle || 'Last %d generated').replace('%d', limit);

			return AIPS.Templates.renderRaw('aips-tmpl-sched-details', {
				template_card: this.renderTemplate(data.template || {}),
				stats: this.renderStats(activity.stats || []),
				stages: this.renderStages(activity.stages || []),
				health: this.renderHealth(data.health || {}, id, type),
				recent_title: AIPS.Templates.escape(recentTitle),
				recent: this.renderRecent(data.recent || [])
			});
		},

		renderTemplate: function(template) {
			if (!template || !template.name) {
				return '';
			}
			return AIPS.Templates.render('aips-tmpl-sched-template', {
				name: template.name
			});
		},

		renderStats: function(stats) {
			return stats.map(function(stat) {
				return AIPS.Templates.render('aips-tmpl-sched-stat', { value: stat.value, label: stat.label });
			}).join('');
		},

		renderStages: function(stages) {
			if (!stages.length) {
				return '';
			}
			var esc    = AIPS.Templates.escape;
			var paused = AIPS.Templates.render('aips-tmpl-sched-stage-paused', {});
			var rows = stages.map(function(stage) {
				// renderRaw so the static paused chip survives; every dynamic value is escaped here.
				return AIPS.Templates.renderRaw('aips-tmpl-sched-stage-row', {
					row_class: stage.is_active ? '' : 'is-paused',
					label: esc(String(stage.label || '')),
					paused: stage.is_active ? '' : paused,
					frequency: esc(String(stage.frequency || '')),
					output: esc(String(stage.output || '')),
					last_run: esc(String(stage.last_run || ''))
				});
			}).join('');

			return AIPS.Templates.renderRaw('aips-tmpl-sched-stages', { rows: rows });
		},

		renderHealth: function(health, id, type) {
			var l10n  = window.aipsScheduleL10n || {};
			var icons = { healthy: 'dashicons-yes-alt', warning: 'dashicons-warning', error: 'dashicons-dismiss' };
			var state = icons[health.state] ? health.state : 'healthy';

			var html = AIPS.Templates.render('aips-tmpl-sched-health', {
				state: state,
				icon: icons[state],
				label: health.label || '',
				message: health.message || ''
			});

			if (health.last_error) {
				html += AIPS.Templates.render('aips-tmpl-sched-health-error', {
					message: health.last_error.message,
					time_display: health.last_error.time_display,
					time_iso: health.last_error.time_iso
				});
			}

			var actionDefs = {
				reset_circuit: { class_name: 'aips-reset-circuit', icon: 'dashicons-update', label: l10n.resetCircuit || 'Reset circuit & retry' },
				resume_batch:  { class_name: 'aips-resume-batch', icon: 'dashicons-controls-forward', label: l10n.resumeBatch || 'Resume batch' }
			};
			var buttons = (health.actions || []).map(function(action) {
				var def = actionDefs[action];
				if (!def) {
					return '';
				}
				return AIPS.Templates.render('aips-tmpl-sched-health-action', {
					class_name: def.class_name,
					icon: def.icon,
					label: def.label,
					id: id,
					type: type
				});
			}).join('');

			if (buttons) {
				// Buttons were rendered (escaped) above; renderRaw only wraps them.
				html += AIPS.Templates.renderRaw('aips-tmpl-sched-health-actions', { buttons: buttons });
			}

			return html;
		},

		renderRecent: function(items) {
			var l10n = window.aipsScheduleL10n || {};

			if (!items.length) {
				return AIPS.Templates.render('aips-tmpl-sched-recent-empty', {});
			}

			var html = items.map(function(item) {
				var isTopic = item.kind === 'topic';
				var url     = AIPS.Utilities && AIPS.Utilities.sanitizeUrl ? AIPS.Utilities.sanitizeUrl(item.url || '') : (item.url || '');
				var data    = {
					icon: isTopic ? 'dashicons-lightbulb' : 'dashicons-media-document',
					kind_label: isTopic ? (l10n.recentKindTopic || 'Topic') : (l10n.recentKindPost || 'Post'),
					title: item.title,
					url: url,
					status: item.status,
					status_label: item.status_label,
					time_display: item.time_display,
					time_full: item.time_full,
					time_iso: item.time_iso
				};
				return AIPS.Templates.render(url ? 'aips-tmpl-sched-recent-item' : 'aips-tmpl-sched-recent-item-nolink', data);
			}).join('');

			return AIPS.Templates.renderRaw('aips-tmpl-sched-recent-list', { items: html });
		}
	};

	$(document).ready(function() {
		AIPS.SchedulesTable.init();
	});

})(jQuery);
