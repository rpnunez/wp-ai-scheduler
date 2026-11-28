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

		/** @type {boolean} Whether event bindings and watchdog timers are already active. */
		initialized: false,

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

		/** @type {Object<string,number>} Count of pending control requests per process key (or '__all__'). */
		pendingControls: {},

		/** @type {number} Monotonic counter stamped on outgoing Heartbeat polls. */
		_hbSendSeq: 0,

		/** @type {number} Sequence of the most recently dispatched Heartbeat poll. */
		_activeHbSeq: 0,

		/** @type {Object<string,number>} Sequence after which Heartbeat snapshots are fresh for a controlled key. */
		_controlSeq: {},

		/** @type {number} Timestamp (ms) of the last accepted payload or initial summary. */
		lastValidAt: 0,

		/** @type {boolean} Whether live updates are currently marked stale/interrupted. */
		isStale: false,

		/** @type {boolean} Whether WordPress Heartbeat is unavailable on this page. */
		heartbeatUnavailable: false,

		/** @type {number|null} Coalesced immediate refresh timer handle. */
		_refreshTimer: null,

		/** @type {number|null} Stale-transport watchdog interval handle. */
		_watchdogTimer: null,

		/** @type {jqXHR|null} In-flight manual fallback refresh request. */
		_fallbackXhr: null,

		/** @type {boolean} Whether this module elevated Heartbeat to ACTIVE_INTERVAL. */
		_forcedActiveInterval: false,

		/** @type {number} Shared-screen Heartbeat interval observed before elevating to ACTIVE_INTERVAL. */
		_restoreInterval: 0,

		init: function () {
			L10n = window.aipsBgL10n || L10n || {};

			if (this.isValidSnapshotList(L10n.initial)) {
				this.setSnapshots(L10n.initial);
				this.lastValidAt = Date.now();
			} else if (!this.initialized) {
				this.state = {};
				this.lastValidAt = Date.now();
			}

			if (L10n.serverTime) {
				this.clockOffset = L10n.serverTime - Math.floor(Date.now() / 1000);
			}

			this.heartbeatUnavailable = !this.hasHeartbeat();

			if (!this.initialized) {
				this.bindEvents();
				this.startWatchdog();
				this.initialized = true;
			}

			this.applyAll();
			this.syncHeartbeatInterval();
			this.renderTransportStatus();

			$(document).trigger('aips:bg-update', [this.getSnapshotList(), this.state]);

			// Request an immediate fresh snapshot on initialization so the initial
			// page-load summary transient is replaced by live repository counts.
			if (!this.heartbeatUnavailable) {
				this.requestRefresh();
			}
		},

		/**
		 * Whether the WordPress Heartbeat client API is present.
		 *
		 * @return {boolean}
		 */
		hasHeartbeat: function () {
			return !!(window.wp && window.wp.heartbeat);
		},

		bindEvents: function () {
			var self = this;

			$(document).off('.aipsBg');

			$(document).on('heartbeat-send.aipsBg', function (e, data) {
				if (data && typeof data === 'object') {
					self._activeHbSeq = ++self._hbSendSeq;
					data.aips_bg = 1;
				}
			});

			$(document).on('heartbeat-tick.aipsBg', function (e, data) {
				if (!data || typeof data !== 'object' || !('aips_bg' in data)) {
					return;
				}
				self.onHeartbeatPayload(data.aips_bg);
			});

			$(document).on('heartbeat-error.aipsBg heartbeat-connection-lost.aipsBg', function () {
				self.markStale(true);
			});

			$(document).on('heartbeat-connection-restored.aipsBg', function () {
				self.lastValidAt = Date.now();
				self.markStale(false);
				self.requestRefresh();
			});

			$(document).on('click.aipsBg', '[data-aips-bg-action]', this.onActionClick.bind(this));
			$(document).on('click.aipsBg', '[data-aips-bg-pause-all]', this.onPauseAllClick.bind(this));
			$(document).on('click.aipsBg', '[data-aips-bg-refresh]', this.onManualRefreshClick.bind(this));

			if (typeof document.addEventListener === 'function') {
				if (this._onVisibilityChange) {
					document.removeEventListener('visibilitychange', this._onVisibilityChange);
				}
				this._onVisibilityChange = function () {
					if (document.visibilityState === 'visible') {
						// Reset baseline timestamp when returning from a hidden tab so background-tab
						// throttling does not falsely trip the foreground 3-interval stale check.
						self.lastValidAt = Date.now();
						self.requestRefresh();
					}
				};
				document.addEventListener('visibilitychange', this._onVisibilityChange);
			}
		},

		// ---------------------------------------------------------------------
		// Validation, Refresh & Transport Health
		// ---------------------------------------------------------------------

		/**
		 * Check whether a single process snapshot object has the required structure.
		 *
		 * @param {*} snap Candidate snapshot object.
		 * @return {boolean}
		 */
		isValidSnapshot: function (snap) {
			return !!(
				snap &&
				typeof snap === 'object' &&
				typeof snap.key === 'string' &&
				snap.key.length > 0 &&
				typeof snap.status === 'string' &&
				snap.status.length > 0
			);
		},

		/**
		 * Check whether a list of process snapshots is valid.
		 *
		 * @param {*} list Candidate snapshot list.
		 * @return {boolean}
		 */
		isValidSnapshotList: function (list) {
			if (!$.isArray(list)) {
				return false;
			}
			for (var i = 0; i < list.length; i++) {
				if (!this.isValidSnapshot(list[i])) {
					return false;
				}
			}
			return true;
		},

		/**
		 * Handle an incoming `aips_bg` payload from Heartbeat.
		 *
		 * Validates structure before replacing state. Missing or malformed payloads
		 * preserve the last valid display and mark it stale.
		 *
		 * @param {*} payload `data.aips_bg` from `heartbeat-tick`.
		 */
		onHeartbeatPayload: function (payload) {
			if (!payload || typeof payload !== 'object' || !this.isValidSnapshotList(payload.processes)) {
				this.markStale(true);
				return;
			}

			if (typeof payload.server_time === 'number' && payload.server_time > 0) {
				this.clockOffset = payload.server_time - Math.floor(Date.now() / 1000);
			}

			this.lastValidAt = Date.now();
			this.markStale(false);
			this.update(payload.processes, true);
		},

		/**
		 * Coalesce requests for an immediate refresh (on init, after controls, or on visibility return).
		 */
		requestRefresh: function () {
			var self = this;

			if (this._refreshTimer) {
				return;
			}

			this._refreshTimer = setTimeout(function () {
				self._refreshTimer = null;

				if (self.hasHeartbeat() && typeof window.wp.heartbeat.connectNow === 'function') {
					window.wp.heartbeat.connectNow();
				} else {
					self.fetchSnapshotsManual();
				}
			}, 150);
		},

		/**
		 * Fetch fresh snapshots using the secured `aips_bg_list` endpoint (used when Heartbeat
		 * is unavailable or when clicking the manual Refresh button).
		 *
		 * @param {jQuery|null} [$btn] Optional button triggered by the user.
		 * @return {jqXHR|null}
		 */
		fetchSnapshotsManual: function ($btn) {
			var self = this;

			if (!L10n.ajaxUrl || !L10n.nonce) {
				return null;
			}

			if (this._fallbackXhr && this._fallbackXhr.readyState && this._fallbackXhr.readyState !== 4) {
				return this._fallbackXhr;
			}

			var $refreshBtns = $('[data-aips-bg-refresh]');
			$refreshBtns.prop('disabled', true).addClass('is-busy');
			$refreshBtns.find('[data-aips-bg-refresh-label]').text(L10n.refreshing || 'Refreshing…');

			var request = this.post('aips_bg_list').done(function (response) {
				if (response && response.success && response.data && self.isValidSnapshotList(response.data.processes)) {
					self.lastValidAt = Date.now();
					self.markStale(false);
					self.update(response.data.processes, true);
				} else {
					self.markStale(true);
					if ($btn && $btn.length) {
						self.toast((response && response.data && response.data.message) || L10n.requestFailed, 'error');
					}
				}
			}).fail(function () {
				self.markStale(true);
				if ($btn && $btn.length) {
					self.toast(L10n.requestFailed, 'error');
				}
			}).always(function () {
				self._fallbackXhr = null;
				$refreshBtns.prop('disabled', false).removeClass('is-busy');
				$refreshBtns.find('[data-aips-bg-refresh-label]').text(L10n.refreshNow || 'Refresh');
			});

			this._fallbackXhr = request;
			return request;
		},

		/**
		 * Start a periodic check that marks the display stale if 3 expected foreground
		 * intervals elapse without a valid payload.
		 */
		startWatchdog: function () {
			var self = this;

			if (this._watchdogTimer) {
				clearInterval(this._watchdogTimer);
			}

			this._watchdogTimer = setInterval(function () {
				self.checkTransportHealth();
			}, 5000);
		},

		/**
		 * Check whether more than 3 expected foreground intervals have passed since the last valid payload.
		 */
		checkTransportHealth: function () {
			if (this.heartbeatUnavailable) {
				this.renderTransportStatus();
				return;
			}

			if (typeof document.visibilityState === 'string' && document.visibilityState === 'hidden') {
				return;
			}

			if (!this.lastValidAt) {
				return;
			}

			var expectedSecs = this.getExpectedIntervalSeconds();
			var thresholdMs  = expectedSecs * 3 * 1000;

			if ((Date.now() - this.lastValidAt) > thresholdMs) {
				this.markStale(true);
			}
		},

		/**
		 * Current expected foreground Heartbeat interval in seconds.
		 *
		 * @return {number}
		 */
		getExpectedIntervalSeconds: function () {
			if (this.hasHeartbeat() && typeof window.wp.heartbeat.interval === 'function') {
				var current = Number(window.wp.heartbeat.interval());
				if (current >= 5 && current <= 300) {
					return current;
				}
			}
			return this.getActive().length ? this.ACTIVE_INTERVAL : this.IDLE_INTERVAL;
		},

		/**
		 * Mark or clear the stale/interrupted transport state.
		 *
		 * @param {boolean} stale True when live updates are interrupted.
		 */
		markStale: function (stale) {
			var next = !!stale;
			if (this.isStale === next) {
				return;
			}
			this.isStale = next;
			this.applyAll();
			this.renderTransportStatus();
		},

		/**
		 * Render or hide the transport notice ("Live updates interrupted" or Heartbeat unavailable)
		 * and manual Refresh buttons.
		 */
		renderTransportStatus: function () {
			this.heartbeatUnavailable = !this.hasHeartbeat();

			var showNotice = this.heartbeatUnavailable || this.isStale;
			var message    = this.heartbeatUnavailable
				? (L10n.heartbeatUnavailable || 'WordPress Heartbeat is unavailable on this page. Live progress will not update automatically.')
				: (L10n.liveUpdatesInterrupted || 'Live updates interrupted');

			$('[data-aips-bg-transport-notice]').each(function () {
				var $notice = $(this);
				$notice.prop('hidden', !showNotice);
				$notice.toggleClass('aips-bg-transport-unavailable', !!(window.AIPS && AIPS.BackgroundProcesses.heartbeatUnavailable));
				$notice.toggleClass('aips-bg-transport-interrupted', !!(window.AIPS && AIPS.BackgroundProcesses.isStale && !AIPS.BackgroundProcesses.heartbeatUnavailable));
				$notice.find('[data-aips-bg-transport-text]').text(showNotice ? message : '');
			});

			// Header refresh button on Diagnostics panel is shown persistently when Heartbeat is unavailable
			$('.aips-bg-header-actions [data-aips-bg-refresh]').prop('hidden', !this.heartbeatUnavailable);
		},

		/**
		 * @param {Event} e Click on a [data-aips-bg-refresh] button.
		 */
		onManualRefreshClick: function (e) {
			e.preventDefault();
			this.fetchSnapshotsManual($(e.currentTarget));
		},

		// ---------------------------------------------------------------------
		// State & Control-Race Protection
		// ---------------------------------------------------------------------

		/**
		 * Whether a process currently has an in-flight control request.
		 *
		 * @param {string} [key] Process key.
		 * @return {boolean}
		 */
		hasPendingControl: function (key) {
			if (this.pendingControls.__all__ > 0) {
				return true;
			}
			return !!(key && this.pendingControls[key] > 0);
		},

		/**
		 * Increment the pending control counter for a process key (or '__all__').
		 *
		 * @param {string} key Process key or '__all__'.
		 */
		beginControl: function (key) {
			this.pendingControls[key] = (this.pendingControls[key] || 0) + 1;
		},

		/**
		 * Decrement the pending control counter for a process key (or '__all__').
		 *
		 * @param {string} key Process key or '__all__'.
		 */
		endControl: function (key) {
			if (this.pendingControls[key]) {
				this.pendingControls[key]--;
				if (this.pendingControls[key] <= 0) {
					delete this.pendingControls[key];
				}
			}
		},

		/**
		 * Return the current snapshots as an ordered array.
		 *
		 * @return {Array}
		 */
		getSnapshotList: function () {
			return $.map(this.state, function (s) {
				return s;
			});
		},

		/**
		 * Replace or merge the stored snapshots.
		 *
		 * When `respectPendingControls` is true (e.g. from an in-flight Heartbeat response),
		 * any process with an active control command preserves its local state so an older
		 * Heartbeat response never overwrites a newer control state.
		 *
		 * @param {Array}   list                     Snapshots from the server.
		 * @param {boolean} [respectPendingControls] Whether to ignore snapshots for processes being controlled.
		 * @return {boolean} True when state was updated.
		 */
		setSnapshots: function (list, respectPendingControls) {
			if (!this.isValidSnapshotList(list)) {
				this.markStale(true);
				return false;
			}

			if (respectPendingControls && this.hasPendingControl()) {
				return false;
			}

			var self  = this;
			var prev  = this.state || {};
			var state = {};

			$.each(list, function (i, snap) {
				if (
					respectPendingControls &&
					prev[snap.key] &&
					(self.hasPendingControl(snap.key) || (self._controlSeq[snap.key] && self._activeHbSeq <= self._controlSeq[snap.key]))
				) {
					state[snap.key] = prev[snap.key];
				} else {
					state[snap.key] = snap;
				}
			});

			this.state = state;
			return true;
		},

		/**
		 * Apply fresh snapshots everywhere on the page.
		 *
		 * @param {Array}   list                     Snapshots from the server.
		 * @param {boolean} [respectPendingControls] Whether to protect processes with pending controls.
		 */
		update: function (list, respectPendingControls) {
			if (!this.setSnapshots(list, respectPendingControls)) {
				return;
			}
			this.applyAll();
			this.syncHeartbeatInterval();
			this.renderTransportStatus();
			$(document).trigger('aips:bg-update', [this.getSnapshotList(), this.state]);
		},

		/**
		 * Merge one snapshot (from a control response) into the state.
		 *
		 * @param {Object} snap Snapshot.
		 */
		updateOne: function (snap) {
			if (!this.isValidSnapshot(snap)) {
				return;
			}
			this.state[snap.key] = snap;
			this._controlSeq[snap.key] = this._hbSendSeq;
			this.lastValidAt = Date.now();
			this.markStale(false);
			this.applyAll();
			this.syncHeartbeatInterval();
			this.renderTransportStatus();
			$(document).trigger('aips:bg-update', [this.getSnapshotList(), this.state]);
		},

		/**
		 * @return {Array} Snapshots that are running or waiting to retry.
		 */
		getActive: function () {
			return $.grep(this.getSnapshotList(), function (s) {
				return !!s.is_active;
			});
		},

		/**
		 * Poll faster (15s) while a process runs. When idle, use 60s on dedicated monitoring
		 * screens, and on shared screens avoid slowing an interval required by another feature.
		 */
		syncHeartbeatInterval: function () {
			if (!this.hasHeartbeat() || typeof window.wp.heartbeat.interval !== 'function') {
				return;
			}

			var current = Number(window.wp.heartbeat.interval());

			if (this.getActive().length > 0) {
				if (!this._forcedActiveInterval && current > this.ACTIVE_INTERVAL) {
					this._restoreInterval = current;
				}
				this._forcedActiveInterval = true;
				window.wp.heartbeat.interval(this.ACTIVE_INTERVAL);
				return;
			}

			var wasForced = this._forcedActiveInterval;
			var restoreTo = this._restoreInterval || this.IDLE_INTERVAL;
			this._forcedActiveInterval = false;
			this._restoreInterval      = 0;

			var isDedicated = !!L10n.isDedicatedScreen || $('.aips-background-processes-page').length > 0;
			if (isDedicated) {
				window.wp.heartbeat.interval(this.IDLE_INTERVAL);
				return;
			}

			// Shared screen: restore to the previous/idle interval if we elevated it to ACTIVE_INTERVAL,
			// without slowing a faster interval chosen independently by another WordPress feature.
			if (wasForced && current === this.ACTIVE_INTERVAL) {
				window.wp.heartbeat.interval(Math.min(this.IDLE_INTERVAL, Math.max(this.ACTIVE_INTERVAL, restoreTo)));
			} else if (!current || current > this.IDLE_INTERVAL) {
				window.wp.heartbeat.interval(this.IDLE_INTERVAL);
			}
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
			var self      = this;
			var percent   = Math.max(0, Math.min(100, Number(snap.percent || 0)));
			var isRunning = snap.status === 'running' && !this.isStale;
			var isZero    = isRunning && percent === 0;

			$root
				.toggleClass('aips-bg-active', !!snap.is_active && !this.isStale)
				.toggleClass('aips-bg-running', isRunning)
				.toggleClass('aips-bg-zero', isZero)
				.toggleClass('aips-bg-paused', snap.status === 'paused')
				.toggleClass('aips-bg-stale', !!this.isStale)
				.attr('data-aips-bg-status', snap.status);

			$root.find('[data-aips-bg-field]').each(function () {
				var $el = $(this);
				$el.text(self.fieldText($el.attr('data-aips-bg-field'), snap));
			});

			$root.find('[data-aips-bg-bar]').css('width', percent + '%');

			var ariaLabel = (L10n.progressAriaLabel || '%s progress').replace('%s', snap.label || snap.key);
			$root.find('[role="progressbar"], .aips-bg-progress-bar, .aips-bg-mini').each(function () {
				var $track = $(this);
				if (!$track.attr('role')) {
					$track.attr('role', 'progressbar');
				}
				if (!$track.attr('aria-label')) {
					$track.attr('aria-label', ariaLabel);
				}
				$track
					.attr('aria-valuemin', '0')
					.attr('aria-valuemax', '100')
					.attr('aria-valuenow', String(percent));
			});

			// Play/pause toggle (admin bar): resume when paused, otherwise pause; hidden when neither applies.
			$root.find('[data-aips-bg-toggle]').each(function () {
				var $btn     = $(this);
				var isResume = !!snap.can_resume;
				var label    = isResume ? (L10n.resume || 'Resume') : (L10n.pause || 'Pause');

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
				case 'pending':
				case 'in_flight':
					return Number(snap[field] || 0).toLocaleString();
				case 'ai_calls_used':
					if (!snap.uses_ai) {
						return '—';
					}
					if (snap.tracks_ai_calls === false) {
						return L10n.notTracked || 'Not tracked';
					}
					return Number(snap.ai_calls_used || 0).toLocaleString();
				case 'percent':
					return Math.max(0, Math.min(100, Number(snap.percent || 0))) + '%';
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
		 *
		 * Updates existing DOM nodes in place by `data-aips-bg-key` rather than
		 * rebuilding the entire list each tick so focused controls remain focused.
		 */
		renderAdminBar: function () {
			var $root = $('#wp-admin-bar-aips-toolbar');
			if (!$root.length) {
				return;
			}

			var active = this.getActive();
			var shown  = $.grep(this.getSnapshotList(), function (s) {
				return s.status !== 'idle';
			});

			$root
				.toggleClass('aips-bg-running', active.length > 0 && !this.isStale)
				.toggleClass('aips-bg-stale', !!this.isStale);
			$root.find('.aips-toolbar-bg-count').text(active.length > 1 ? String(active.length) : '');

			var $list = $root.find('.aips-bg-items');
			if (!$list.length) {
				return;
			}

			if (!shown.length) {
				$list.children('.aips-bg-item').remove();
				var $none = $list.children('.aips-bg-none');
				if (!$none.length) {
					$none = $('<span class="aips-bg-none"></span>');
					$list.append($none);
				}
				$none.text(L10n.noneRunning || 'No background processes running');
				return;
			}

			$list.children('.aips-bg-none').remove();

			var self     = this;
			var shownMap = {};
			$.each(shown, function (i, snap) {
				shownMap[snap.key] = true;
			});

			// Remove items that returned to idle.
			$list.children('.aips-bg-item').each(function () {
				var $item = $(this);
				var key   = $item.attr('data-aips-bg-key');
				if (!key || !shownMap[key]) {
					$item.remove();
				}
			});

			// Update existing items in place or insert new ones in order without disturbing focus.
			var $prev = null;
			$.each(shown, function (i, snap) {
				var $existing = $list.children('.aips-bg-item[data-aips-bg-key="' + snap.key + '"]');
				if ($existing.length) {
					self.ensureAdminBarItemFields($existing, snap);
					self.renderWidget($existing, snap);
					$prev = $existing;
				} else {
					var $created = self.buildAdminBarItem(snap);
					if ($prev && $prev.length) {
						$prev.after($created);
					} else {
						$list.prepend($created);
					}
					$prev = $created;
				}
			});
		},

		/**
		 * Ensure server-rendered admin bar items have `data-aips-bg-field` bindings
		 * so in-place updates refresh counts, status text, and progress bar width.
		 *
		 * @param {jQuery} $item Existing `.aips-bg-item`.
		 * @param {Object} snap  Snapshot.
		 */
		ensureAdminBarItemFields: function ($item, snap) {
			var $meta = $item.find('.aips-bg-item-meta');
			if ($meta.length && !$meta.find('[data-aips-bg-field="processed"]').length) {
				$meta.empty()
					.append($('<span data-aips-bg-field="processed"></span>'))
					.append(document.createTextNode(' / '))
					.append($('<span data-aips-bg-field="total"></span>'))
					.append(document.createTextNode(' · '))
					.append($('<span class="aips-bg-item-status" data-aips-bg-field="bar_status"></span>'));
			}
			var $fill = $item.find('.aips-bg-mini-fill');
			if ($fill.length && !$fill.is('[data-aips-bg-bar]')) {
				$fill.attr('data-aips-bg-bar', '');
			}
			$item.find('.aips-bg-item-label').text(snap.label);
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

			var ariaLabel = (L10n.progressAriaLabel || '%s progress').replace('%s', snap.label || snap.key);
			$item.append(
				$('<span class="aips-bg-mini" role="progressbar" aria-valuemin="0" aria-valuemax="100"></span>')
					.attr('aria-label', ariaLabel)
					.append($('<span class="aips-bg-mini-fill" data-aips-bg-bar></span>'))
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

			var self = this;
			var $btn = $(e.currentTarget);

			this.beginControl('__all__');

			var request = this.post('aips_bg_pause_all').done(function (response) {
				if (response && response.success && response.data) {
					self.lastValidAt = Date.now();
					self.markStale(false);
					self.update(response.data.processes || [], false);
					self.toast(response.data.message || '', 'success');
				} else {
					self.toast((response && response.data && response.data.message) || L10n.requestFailed, 'error');
				}
			}).fail(function () {
				self.toast(L10n.requestFailed, 'error');
			}).always(function () {
				self.endControl('__all__');
				self.requestRefresh();
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
				if (!response || !response.success) {
					self.toast((response && response.data && response.data.message) || L10n.requestFailed, 'error');
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
		 * While the command is in flight, incoming Heartbeat snapshots for `key` are
		 * ignored so an older Heartbeat response cannot overwrite the control result.
		 * Once settled, a fresh refresh is requested.
		 *
		 * @param {string}      action start, pause, resume or cancel.
		 * @param {string}      key    Process key.
		 * @param {jQuery|null} $btn   Button to lock while the request runs.
		 * @param {Object}      [extra] Start options (ai_budget, mode).
		 */
		control: function (action, key, $btn, extra) {
			var self = this;

			this.beginControl(key);

			var request = this.post('aips_bg_' + action, $.extend({ process: key }, extra || {})).done(function (response) {
				if (response && response.success && response.data) {
					self.updateOne(response.data.process);
					self.toast(response.data.message || '', 'success');
				} else {
					self.toast((response && response.data && response.data.message) || L10n.requestFailed, 'error');
				}
			}).fail(function () {
				self.toast(L10n.requestFailed, 'error');
			}).always(function () {
				self.endControl(key);
				self.requestRefresh();
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
