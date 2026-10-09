/**
 * AI Post Scheduler – Background Processes
 *
 * Keeps every background-process widget in step with the server. State arrives
 * through the WordPress Heartbeat API (and through the control responses), and
 * is applied to:
 *   - any element with data-aips-bg-key (cards, table rows, admin bar items),
 *   - the pulsing indicator on the admin bar,
 *   - the Pause / Resume / Stop / Start buttons (data-aips-bg-action).
 *
 * Widget markup contract:
 *   [data-aips-bg-key="<process key>"]            root element for one process
 *   [data-aips-bg-field="<snapshot field>"]       text filled from the snapshot
 *   [data-aips-bg-bar]                            width set to the percent done
 *   [data-aips-bg-action="start|pause|resume|cancel"]  control buttons
 *
 * The page may listen for the `aips:bg-update` event on document.
 */
(function ($) {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	var L10n = window.aipsBgL10n || {};

	AIPS.BackgroundProcesses = {

		/** @type {Object<string,Object>} Latest snapshot per process key. */
		state: {},

		/** @type {number} Seconds between Heartbeat ticks while something runs. */
		ACTIVE_INTERVAL: 15,

		/** @type {number} Seconds between Heartbeat ticks while everything is idle. */
		IDLE_INTERVAL: 60,

		/** @type {number} Offset between server time and the browser clock, in seconds. */
		clockOffset: 0,

		init: function () {
			this.setSnapshots(L10n.initial || []);
			if (L10n.serverTime) {
				this.clockOffset = L10n.serverTime - Math.floor(Date.now() / 1000);
			}
			this.bindEvents();
			this.applyAll();
			this.syncHeartbeatInterval();
			$(document).trigger('aips:bg-update', [L10n.initial || [], this.state]);
		},

		bindEvents: function () {
			var self = this;

			$(document).on('heartbeat-send', function (e, data) {
				data.aips_bg = 1;
			});

			$(document).on('heartbeat-tick', function (e, data) {
				if (data && data.aips_bg) {
					if (data.aips_bg.server_time) {
						self.clockOffset = data.aips_bg.server_time - Math.floor(Date.now() / 1000);
					}
					self.update(data.aips_bg.processes || []);
				}
			});

			$(document).on('click', '[data-aips-bg-action]', this.onActionClick.bind(this));
			$(document).on('click', '[data-aips-bg-pause-all]', this.onPauseAllClick.bind(this));
		},

		// ---------------------------------------------------------------------
		// State
		// ---------------------------------------------------------------------

		/**
		 * Replace the stored snapshots.
		 *
		 * @param {Array} list Snapshots from the server.
		 */
		setSnapshots: function (list) {
			var state = {};
			$.each(list, function (i, snap) {
				state[snap.key] = snap;
			});
			this.state = state;
		},

		/**
		 * Apply fresh snapshots everywhere on the page.
		 *
		 * @param {Array} list Snapshots from the server.
		 */
		update: function (list) {
			this.setSnapshots(list);
			this.applyAll();
			this.syncHeartbeatInterval();
			$(document).trigger('aips:bg-update', [list, this.state]);
		},

		/**
		 * Merge one snapshot (from a control response) into the state.
		 *
		 * @param {Object} snap Snapshot.
		 */
		updateOne: function (snap) {
			if (!snap || !snap.key) {
				return;
			}
			this.state[snap.key] = snap;
			this.applyAll();
			this.syncHeartbeatInterval();
			$(document).trigger('aips:bg-update', [$.map(this.state, function (s) { return s; }), this.state]);
		},

		/**
		 * @return {Array} Snapshots that are running or waiting to retry.
		 */
		getActive: function () {
			return $.grep($.map(this.state, function (s) { return s; }), function (s) {
				return !!s.is_active;
			});
		},

		/**
		 * Poll faster while a process runs and slower when everything is idle.
		 */
		syncHeartbeatInterval: function () {
			if (!window.wp || !window.wp.heartbeat) {
				return;
			}
			window.wp.heartbeat.interval(this.getActive().length ? this.ACTIVE_INTERVAL : this.IDLE_INTERVAL);
		},

		// ---------------------------------------------------------------------
		// Rendering
		// ---------------------------------------------------------------------

		applyAll: function () {
			this.renderAdminBar();
			this.renderWidgets($(document));
		},

		/**
		 * Fill every widget root inside $scope from the stored snapshots.
		 *
		 * @param {jQuery} $scope Container to search.
		 */
		renderWidgets: function ($scope) {
			var self = this;

			$scope.find('[data-aips-bg-key]').addBack('[data-aips-bg-key]').each(function () {
				var $root = $(this);
				var snap  = self.state[$root.attr('data-aips-bg-key')];

				if (snap) {
					self.renderWidget($root, snap);
				}
			});
		},

		/**
		 * @param {jQuery} $root Widget root.
		 * @param {Object} snap  Snapshot for the root's process.
		 */
		renderWidget: function ($root, snap) {
			var self = this;

			$root
				.toggleClass('aips-bg-active', !!snap.is_active)
				.toggleClass('aips-bg-paused', snap.status === 'paused')
				.attr('data-aips-bg-status', snap.status);

			$root.find('[data-aips-bg-field]').each(function () {
				var $el = $(this);
				$el.text(self.fieldText($el.attr('data-aips-bg-field'), snap));
			});

			$root.find('[data-aips-bg-bar]').css('width', snap.percent + '%');

			var flags = { start: 'can_start', pause: 'can_pause', resume: 'can_resume', cancel: 'can_cancel' };
			$root.find('[data-aips-bg-action]').each(function () {
				var $btn = $(this);
				var flag = flags[$btn.attr('data-aips-bg-action')];

				if (flag) {
					$btn.prop('hidden', !snap[flag]);
				}
			});
		},

		/**
		 * Text for one data-aips-bg-field.
		 *
		 * @param {string} field Field name.
		 * @param {Object} snap  Snapshot.
		 * @return {string}
		 */
		fieldText: function (field, snap) {
			switch (field) {
				case 'status_label':
					if (snap.status === 'idle' && snap.last_status && (L10n.statusLabels || {})[snap.last_status]) {
						return snap.status_label + ' · ' + (L10n.lastRun || '%s').replace('%s', L10n.statusLabels[snap.last_status]);
					}
					return snap.status_label;
				case 'processed':
				case 'total':
				case 'failed':
				case 'ai_calls_used':
					return Number(snap[field] || 0).toLocaleString();
				case 'percent':
					return snap.percent + '%';
				case 'next_run':
					if ((snap.status === 'waiting_quota' || snap.status === 'cooldown') && snap.next_run_at) {
						return (L10n.resumesIn || 'Resumes in %s').replace('%s', this.until(snap.next_run_at));
					}
					return '';
				default:
					return snap[field] !== undefined && snap[field] !== null ? String(snap[field]) : '';
			}
		},

		/**
		 * Rough "in 3 hours" text for a future timestamp.
		 *
		 * @param {number} timestamp Unix seconds (server clock).
		 * @return {string}
		 */
		until: function (timestamp) {
			var secs = timestamp - (Math.floor(Date.now() / 1000) + this.clockOffset);

			if (secs < 60) {
				return L10n.lessThanMinute || 'under a minute';
			}
			if (secs < 5400) {
				return Math.round(secs / 60) + ' ' + (L10n.minutes || 'min');
			}
			if (secs < 129600) {
				return Math.round(secs / 3600) + ' ' + (L10n.hours || 'h');
			}
			return Math.round(secs / 86400) + ' ' + (L10n.days || 'd');
		},

		/**
		 * Pulse the toolbar icon and list the processes that are not idle.
		 */
		renderAdminBar: function () {
			var $root = $('#wp-admin-bar-aips-toolbar');
			if (!$root.length) {
				return;
			}

			var active = this.getActive();
			var shown  = $.grep($.map(this.state, function (s) { return s; }), function (s) {
				return s.status !== 'idle';
			});

			$root.toggleClass('aips-bg-running', active.length > 0);
			$root.find('.aips-toolbar-bg-count').text(active.length > 1 ? String(active.length) : '');

			var $list = $root.find('.aips-bg-items');
			if (!$list.length) {
				return;
			}

			$list.empty();

			if (!shown.length) {
				$list.append($('<span class="aips-bg-none"></span>').text(L10n.noneRunning || 'No background processes running'));
				return;
			}

			var self = this;
			$.each(shown, function (i, snap) {
				$list.append(self.buildAdminBarItem(snap));
			});
		},

		/**
		 * @param {Object} snap Snapshot.
		 * @return {jQuery}
		 */
		buildAdminBarItem: function (snap) {
			var $item = $('<span class="aips-bg-item"></span>').attr('data-aips-bg-key', snap.key);

			$item.append(
				$('<span class="aips-bg-item-head"></span>')
					.append($('<span class="aips-bg-item-label"></span>').text(snap.label))
					.append($('<span class="aips-bg-item-status" data-aips-bg-field="status_label"></span>'))
			);

			$item.append(
				$('<span class="aips-bg-mini"></span>').append($('<span class="aips-bg-mini-fill" data-aips-bg-bar></span>'))
			);

			$item.append(
				$('<span class="aips-bg-item-meta"></span>')
					.append($('<span data-aips-bg-field="processed"></span>'))
					.append(document.createTextNode(' / '))
					.append($('<span data-aips-bg-field="total"></span>'))
					.append($('<span class="aips-bg-item-next" data-aips-bg-field="next_run"></span>'))
			);

			var $controls = $('<span class="aips-bg-item-controls"></span>');
			$.each([['pause', L10n.pause || 'Pause'], ['resume', L10n.resume || 'Resume'], ['cancel', L10n.stop || 'Stop']], function (i, pair) {
				$controls.append(
					$('<button type="button" class="aips-bg-link"></button>')
						.attr('data-aips-bg-action', pair[0])
						.text(pair[1])
				);
			});
			$item.append($controls);

			this.renderWidget($item, snap);

			return $item;
		},

		// ---------------------------------------------------------------------
		// Actions
		// ---------------------------------------------------------------------

		/**
		 * @param {string} action AJAX action name.
		 * @param {Object} data   Extra request data.
		 * @return {jqXHR}
		 */
		post: function (action, data) {
			return $.post(L10n.ajaxUrl, $.extend({ action: action, nonce: L10n.nonce }, data || {}));
		},

		/**
		 * @param {Event} e Click on a [data-aips-bg-action] button.
		 */
		onActionClick: function (e) {
			e.preventDefault();

			var $btn   = $(e.currentTarget);
			var action = $btn.attr('data-aips-bg-action');
			var key    = $btn.closest('[data-aips-bg-key]').attr('data-aips-bg-key') || $btn.attr('data-aips-bg-key');

			if (!key) {
				return;
			}

			if (action === 'start') {
				this.startWithEstimate(key, $btn);
			} else if (action === 'cancel') {
				this.confirmStop(key, $btn);
			} else if (action === 'pause' || action === 'resume') {
				this.control(action, key, $btn);
			}
		},

		/**
		 * @param {Event} e Click on a [data-aips-bg-pause-all] button.
		 */
		onPauseAllClick: function (e) {
			e.preventDefault();

			var self    = this;
			var $btn    = $(e.currentTarget);
			var request = this.post('aips_bg_pause_all').done(function (response) {
				if (response.success) {
					self.update(response.data.processes || []);
					self.toast(response.data.message || '', 'success');
				} else {
					self.toast((response.data && response.data.message) || L10n.requestFailed, 'error');
				}
			}).fail(function () {
				self.toast(L10n.requestFailed, 'error');
			});

			this.lock($btn, request);
		},

		/**
		 * Show what a run will cost, then start it once confirmed.
		 *
		 * @param {string}      key  Process key.
		 * @param {jQuery|null} $btn Button to lock while the request runs.
		 */
		startWithEstimate: function (key, $btn) {
			var self = this;

			var request = this.post('aips_bg_estimate', { process: key }).done(function (response) {
				if (!response.success) {
					self.toast((response.data && response.data.message) || L10n.requestFailed, 'error');
					return;
				}

				var estimate = response.data.estimate || {};
				var message  = estimate.message || L10n.startFallback || 'Start this process? It runs in the background and can be paused or stopped at any time.';

				if (!AIPS.Utilities || !AIPS.Utilities.confirm) {
					self.control('start', key, $btn);
					return;
				}

				AIPS.Utilities.confirm(
					message,
					L10n.startHeading || 'Start background process',
					[
						{ label: L10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
						{
							label: L10n.start || 'Start',
							className: 'aips-btn aips-btn-primary',
							action: function () {
								self.control('start', key, $btn);
							}
						}
					]
				);
			}).fail(function () {
				self.toast(L10n.requestFailed, 'error');
			});

			this.lock($btn, request);
		},

		/**
		 * @param {string}      key  Process key.
		 * @param {jQuery|null} $btn Button to lock while the request runs.
		 */
		confirmStop: function (key, $btn) {
			var self = this;

			if (!AIPS.Utilities || !AIPS.Utilities.confirm) {
				this.control('cancel', key, $btn);
				return;
			}

			AIPS.Utilities.confirm(
				L10n.confirmStop || 'Stop this process? Work already done is kept, but it will not continue unless you start it again.',
				L10n.stopHeading || 'Stop process',
				[
					{ label: L10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
					{
						label: L10n.stop || 'Stop',
						className: 'aips-btn aips-btn-danger-solid',
						action: function () {
							self.control('cancel', key, $btn);
						}
					}
				]
			);
		},

		/**
		 * Send start / pause / resume / cancel and apply the returned snapshot.
		 *
		 * @param {string}      action start, pause, resume or cancel.
		 * @param {string}      key    Process key.
		 * @param {jQuery|null} $btn   Button to lock while the request runs.
		 */
		control: function (action, key, $btn) {
			var self = this;

			var request = this.post('aips_bg_' + action, { process: key }).done(function (response) {
				if (response.success) {
					self.updateOne(response.data.process);
					self.toast(response.data.message || '', 'success');

					if (window.wp && window.wp.heartbeat) {
						window.wp.heartbeat.connectNow();
					}
				} else {
					self.toast((response.data && response.data.message) || L10n.requestFailed, 'error');
				}
			}).fail(function () {
				self.toast(L10n.requestFailed, 'error');
			});

			this.lock($btn, request);
		},

		/**
		 * Disable a button until a request settles.
		 *
		 * @param {jQuery|null} $btn    Button.
		 * @param {jqXHR}       request Request.
		 */
		lock: function ($btn, request) {
			if (!$btn || !$btn.length) {
				return;
			}

			$btn.prop('disabled', true);
			request.always(function () {
				$btn.prop('disabled', false);
			});
		},

		/**
		 * @param {string} message Text.
		 * @param {string} type    success or error.
		 */
		toast: function (message, type) {
			if (message && AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(message, type);
			}
		}
	};

	$(document).ready(function () {
		AIPS.BackgroundProcesses.init();
	});
})(jQuery);
