/**
 * Monetization Frontend Telemetry & Viewability Tracker
 *
 * Implements cookieless, GDPR-safe IntersectionObserver viewability tracking
 * and click events for in-article ad slots and sponsor links.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

(function () {
	'use strict';

	window.AIPS = window.AIPS || {};
	var AIPS = window.AIPS;

	/**
	 * @namespace AIPS.MonetizationFrontend
	 */
	AIPS.MonetizationFrontend = {

		/** Configuration from wp_localize_script */
		config: window.aipsMonetizationConfig || {},

		/** Queue of events to beacon */
		_eventQueue: [],

		/** Flush debounce timer */
		_flushTimer: null,

		/** Set of observed slot IDs that have already recorded an impression */
		_recordedImpressions: new Set(),

		/**
		 * Initialize tracker.
		 */
		init: function () {
			if (!this.config.telemetryEnabled && !this.config.ga4Enabled) {
				return;
			}

			this.setupIntersectionObserver();
			this.setupClickListeners();
		},

		/**
		 * Setup IntersectionObserver for MRC-standard viewable impressions (50% visibility for >= 1s).
		 */
		setupIntersectionObserver: function () {
			var self = this;
			var containers = document.querySelectorAll('.aips-ad-container');
			if (!containers.length || typeof IntersectionObserver === 'undefined') {
				return;
			}

			var timers = new Map();

			var observer = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					var target = entry.target;
					var slotId = target.getAttribute('data-slot-id');
					var postId = target.getAttribute('data-post-id');
					var campaignId = target.getAttribute('data-campaign-id') || 0;

					if (!slotId || self._recordedImpressions.has(slotId)) {
						return;
					}

					if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
						// Start dwell timer (1 second)
						if (!timers.has(target)) {
							var timerId = setTimeout(function () {
								self.recordImpression(slotId, postId, campaignId);
								self._recordedImpressions.add(slotId);
								timers.delete(target);
								observer.unobserve(target);
							}, 1000);
							timers.set(target, timerId);
						}
					} else {
						// Cancel timer if user quickly scrolled past
						if (timers.has(target)) {
							clearTimeout(timers.get(target));
							timers.delete(target);
						}
					}
				});
			}, {
				threshold: [0.5]
			});

			containers.forEach(function (container) {
				observer.observe(container);
			});
		},

		/**
		 * Setup click listeners on links within ad containers and sponsor elements.
		 */
		setupClickListeners: function () {
			var self = this;
			document.addEventListener('click', function (e) {
				var target = e.target;
				var container = target.closest('.aips-ad-container, .aips-sponsor-card, .aips-sponsor-disclosure');
				if (!container) {
					return;
				}

				// Check if clicked an anchor or button
				var link = target.closest('a, button');
				if (!link) {
					return;
				}

				var slotId = container.getAttribute('data-slot-id') || 0;
				var postId = container.getAttribute('data-post-id') || 0;
				var campaignId = container.getAttribute('data-campaign-id') || 0;

				self.recordClick(slotId, postId, campaignId);
			}, true);
		},

		/**
		 * Record impression event.
		 */
		recordImpression: function (slotId, postId, campaignId) {
			var device = this.detectDevice();

			// GA4 dataLayer event
			if (this.config.ga4Enabled && window.dataLayer) {
				window.dataLayer.push({
					event: 'aips_ad_impression',
					aips_slot_id: slotId,
					aips_post_id: postId,
					aips_campaign_id: campaignId,
					aips_device: device
				});
			}

			// First-party telemetry
			this.enqueueEvent({
				slot_id: parseInt(slotId, 10),
				post_id: parseInt(postId, 10),
				campaign_id: parseInt(campaignId, 10),
				event_type: 'impression',
				device_type: device
			});
		},

		/**
		 * Record click event.
		 */
		recordClick: function (slotId, postId, campaignId) {
			var device = this.detectDevice();

			// GA4 dataLayer event
			if (this.config.ga4Enabled && window.dataLayer) {
				window.dataLayer.push({
					event: 'aips_ad_click',
					aips_slot_id: slotId,
					aips_post_id: postId,
					aips_campaign_id: campaignId,
					aips_device: device
				});
			}

			// First-party telemetry
			this.enqueueEvent({
				slot_id: parseInt(slotId, 10),
				post_id: parseInt(postId, 10),
				campaign_id: parseInt(campaignId, 10),
				event_type: 'click',
				device_type: device
			});
		},

		/**
		 * Detect basic device bucket.
		 */
		detectDevice: function () {
			var w = window.innerWidth || document.documentElement.clientWidth;
			if (w < 768) {
				return 'mobile';
			} else if (w < 1024) {
				return 'tablet';
			}
			return 'desktop';
		},

		/**
		 * Queue event and schedule flush.
		 */
		enqueueEvent: function (evt) {
			this._eventQueue.push(evt);
			if (this._flushTimer) {
				clearTimeout(this._flushTimer);
			}
			var self = this;
			this._flushTimer = setTimeout(function () {
				self.flushQueue();
			}, 800);
		},

		/**
		 * Beacon event batch to REST endpoint.
		 */
		flushQueue: function () {
			if (!this._eventQueue.length || !this.config.restUrl) {
				return;
			}

			var payload = this._eventQueue.slice();
			this._eventQueue = [];

			if (navigator.sendBeacon) {
				var blob = new Blob([JSON.stringify({ events: payload })], { type: 'application/json' });
				navigator.sendBeacon(this.config.restUrl, blob);
			} else {
				fetch(this.config.restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.config.nonce || ''
					},
					body: JSON.stringify({ events: payload }),
					keepalive: true
				}).catch(function () {});
			}
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			AIPS.MonetizationFrontend.init();
		});
	} else {
		AIPS.MonetizationFrontend.init();
	}

})();
