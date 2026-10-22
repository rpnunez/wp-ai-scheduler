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

		/** @type {Object<string,number>} When Stop was last clicked per process (confirmation fallback). */
		armedStops: {},

		/** @type {number} Milliseconds a second Stop click counts as the confirmation. */
		STOP_CONFIRM_WINDOW: 5000,

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

			// Play/pause toggle (admin bar): resume when paused, otherwise pause; hidden when neither applies.
			$root.find('[data-aips-bg-toggle]').each(function () {
				var $btn      = $(this);
				var isResume  = !!snap.can_resume;
				var label     = isResume ? (L10n.resume || 'Resume') : (L10n.pause || 'Pause');

				$btn
					.attr('data-aips-bg-action', isResume ? 'resume' : 'pause')
					.attr('aria-label', label)
					.attr('title', label)
					.prop('hidden', !(snap.can_resume || snap.can_pause))
					.find('.dashicons')
					.toggleClass('dashicons-controls-play', isResume)
					.toggleClass('dashicons-controls-pause', !isResume);
			});

			var flags = { start: 'can_start', pause: 'can_pause', resume: 'can_resume', cancel: 'can_cancel' };
			$root.find('[data-aips-bg-action]').not('[data-aips-bg-toggle]').each(function () {
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
				case 'bar_status':
					if (snap.status === 'running') {
						return L10n.playing || 'Playing';
					}
					if ((snap.status === 'waiting_quota' || snap.status === 'cooldown') && snap.next_run_at) {
						return snap.status_label + ' · ' + (L10n.resumesIn || 'Resumes in %s').replace('%s', this.until(snap.next_run_at));
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

			// Single play/pause toggle; renderWidget() picks the action, icon and label.
			$item.append(
				$('<button type="button" class="aips-bg-toggle" data-aips-bg-toggle></button>')
					.append($('<span class="dashicons" aria-hidden="true"></span>'))
			);

			$item.append($('<span class="aips-bg-item-label"></span>').text(snap.label));

			$item.append(
				$('<span class="aips-bg-item-meta"></span>')
					.append($('<span data-aips-bg-field="processed"></span>'))
					.append(document.createTextNode(' / '))
					.append($('<span data-aips-bg-field="total"></span>'))
					.append(document.createTextNode(' · '))
					.append($('<span class="aips-bg-item-status" data-aips-bg-field="bar_status"></span>'))
			);

			$item.append(
				$('<span class="aips-bg-mini"></span>').append($('<span class="aips-bg-mini-fill" data-aips-bg-bar></span>'))
			);

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
				// Buttons may carry a start option, e.g. data-aips-bg-mode="all" for "Rebuild all".
				var extra = {};
				if ($btn.attr('data-aips-bg-mode')) {
					extra.mode = $btn.attr('data-aips-bg-mode');
				}
				this.startWithEstimate(key, $btn, extra);
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
		 * AI-backed processes also ask for an optional AI call budget: the run
		 * pauses itself once it has made that many calls.
		 *
		 * @param {string}      key   Process key.
		 * @param {jQuery|null} $btn  Button to lock while the request runs.
		 * @param {Object}      extra Start options sent with the estimate and the start request (e.g. mode).
		 */
		startWithEstimate: function (key, $btn, extra) {
			var self = this;

			extra = extra || {};

			var request = this.post('aips_bg_estimate', $.extend({ process: key }, extra)).done(function (response) {
				if (!response.success) {
					self.toast((response.data && response.data.message) || L10n.requestFailed, 'error');
					return;
				}

				var estimate = response.data.estimate || {};
				var message  = estimate.message || L10n.startFallback || 'Start this process? It runs in the background and can be paused or stopped at any time.';

				var heading = L10n.startHeading || 'Start background process';

				// Offer an optional per-run AI call budget, but only where the process can honor it.
				if (response.data.supports_budget && AIPS.Utilities && AIPS.Utilities.showModal) {
					AIPS.Utilities.showModal({
						heading: heading,
						message: message,
						fields: [
							{
								type: 'number',
								name: 'ai_budget',
								label: L10n.budgetLabel || 'AI call budget (optional)',
								value: 0,
								min: 0,
								description: L10n.budgetHelp || 'Pause this run after it has made this many AI calls. 0 means no limit.'
							}
						],
						buttons: [
							{ label: L10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
							{
								label: L10n.start || 'Start',
								className: 'aips-btn aips-btn-primary',
								submit: true,
								action: function (formData) {
									var budget = parseInt(formData && formData.ai_budget, 10);
									self.control('start', key, $btn, $.extend({}, extra, { ai_budget: budget > 0 ? budget : 0 }));
								}
							}
						]
					});
					return;
				}

				// No dialog on this page: never start without the confirmation and estimate.
				if (!AIPS.Utilities || !AIPS.Utilities.confirm) {
					self.toast(L10n.startElsewhere || 'Open Diagnostics > Background Processes to start this process.', 'error');
					return;
				}

				AIPS.Utilities.confirm(
					message,
					heading,
					[
						{ label: L10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
						{
							label: L10n.start || 'Start',
							className: 'aips-btn aips-btn-primary',
							action: function () {
								self.control('start', key, $btn, extra);
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

			// No dialog on this page (the admin bar outside the plugin's screens): ask for a
			// second click instead of stopping on the first.
			if (!AIPS.Utilities || !AIPS.Utilities.confirm) {
				var now = Date.now();

				if (this.armedStops[key] && (now - this.armedStops[key]) < this.STOP_CONFIRM_WINDOW) {
					delete this.armedStops[key];
					this.control('cancel', key, $btn);
					return;
				}

				this.armedStops[key] = now;
				this.toast(L10n.confirmStopAgain || 'Click Stop again within 5 seconds to stop this process.', 'info');
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
		 * @param {Object}      [extra] Start options (ai_budget, mode).
		 */
		control: function (action, key, $btn, extra) {
			var self = this;

			var request = this.post('aips_bg_' + action, $.extend({ process: key }, extra || {})).done(function (response) {
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
			if (!message) {
				return;
			}

			if (AIPS.Utilities && AIPS.Utilities.showToast) {
				AIPS.Utilities.showToast(message, type);
				return;
			}

			// Pages without the plugin's utilities (admin bar elsewhere): a minimal notice.
			var $toast = $('<div class="aips-bg-toast" role="status"></div>')
				.addClass('aips-bg-toast-' + (type || 'info'))
				.text(message);

			$('body').append($toast);
			setTimeout(function () {
				$toast.remove();
			}, 5000);
		}
	};

	$(document).ready(function () {
		AIPS.BackgroundProcesses.init();
	});
})(jQuery);
