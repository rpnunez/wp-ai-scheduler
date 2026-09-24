/**
 * Monetization Frontend Telemetry, Smart Refresh & Ad-Block Engine
 *
 * Implements cookieless, GDPR-safe IntersectionObserver viewability tracking,
 * policy-compliant Smart Ad Refresh, Sticky Bottom Anchors, and Ad-Block Recovery.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.1
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

		/** Timestamp of latest user interaction */
		_lastUserActivity: Date.now(),

		/** Refresh state per slot element */
		_refreshStates: new Map(),

		/** Flag indicating whether ad blocker was detected */
		_adBlockDetected: false,

		/**
		 * Initialize tracker & optimization engines.
		 */
		init: function () {
			this.setupUserActivityTracking();
			this.setupIntersectionObserver();
			this.setupSmartRefresh();
			this.setupStickyAnchors();
			this.setupClickListeners();
			this.setupAdBlockRecovery();
			this.setupReferralRibbons();
		},

		/**
		 * Setup interactive Referral Ribbons: 1-click "Copy Code" button and impression tracking.
		 */
		setupReferralRibbons: function () {
			var self = this;
			var ribbons = document.querySelectorAll('.aips-referral-ribbon');
			if (!ribbons.length) {
				return;
			}

			// 1. Copy Code Button handler with clipboard API and fallback
			document.addEventListener('click', function (e) {
				var copyBtn = e.target.closest('.aips-btn-copy-code');
				if (!copyBtn) {
					return;
				}

				e.preventDefault();
				e.stopPropagation();

				var code = copyBtn.getAttribute('data-code') || '';
				if (!code) {
					return;
				}

				var copyTextSpan = copyBtn.querySelector('.aips-copy-text');
				var origText = copyTextSpan ? copyTextSpan.textContent : 'Copy Code';

				var notifyCopied = function () {
					copyBtn.classList.add('aips-copied');
					if (copyTextSpan) {
						copyTextSpan.textContent = 'Copied!';
					}
					setTimeout(function () {
						copyBtn.classList.remove('aips-copied');
						if (copyTextSpan) {
							copyTextSpan.textContent = origText;
						}
					}, 2000);
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(notifyCopied).catch(function () {
						self.fallbackCopyText(code, notifyCopied);
					});
				} else {
					self.fallbackCopyText(code, notifyCopied);
				}
			});

			// 2. Track Referral Ribbon impressions via IntersectionObserver
			if (typeof IntersectionObserver !== 'undefined') {
				var ribbonObserver = new IntersectionObserver(function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
							var target = entry.target;
							var programId = target.getAttribute('data-program-id') || 0;
							var postId = target.getAttribute('data-post-id') || 0;
							var obsKey = 'ref_' + programId + '_' + postId;

							if (!self._recordedImpressions.has(obsKey)) {
								self._recordedImpressions.add(obsKey);
								self.enqueueEvent({
									slot_id: 0,
									post_id: parseInt(postId, 10),
									campaign_id: parseInt(programId, 10),
									event_type: 'impression',
									device_type: self.detectDevice()
								});
							}
							ribbonObserver.unobserve(target);
						}
					});
				}, { threshold: [0.5] });

				ribbons.forEach(function (r) {
					ribbonObserver.observe(r);
				});
			}
		},

		/**
		 * Fallback textarea execCommand copy method.
		 */
		fallbackCopyText: function (text, callback) {
			var textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			textArea.style.top = '-9999px';
			textArea.style.left = '-9999px';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			try {
				document.execCommand('copy');
				if (typeof callback === 'function') {
					callback();
				}
			} catch (err) {}
			document.body.removeChild(textArea);
		},

		/**
		 * Monitor user interaction to ensure ads refresh only for active readers.
		 */
		setupUserActivityTracking: function () {
			var self = this;
			var updateActivity = function () {
				self._lastUserActivity = Date.now();
			};

			var events = ['scroll', 'mousemove', 'touchstart', 'keydown'];
			events.forEach(function (evt) {
				window.addEventListener(evt, updateActivity, { passive: true });
			});
		},

		/**
		 * Check if user has interacted within the last 30 seconds.
		 */
		isUserActive: function () {
			return (Date.now() - this._lastUserActivity) < 30000;
		},

		/**
		 * Setup IntersectionObserver for MRC-standard viewable impressions (50% visibility for >= 1s).
		 */
		setupIntersectionObserver: function () {
			var self = this;
			var containers = document.querySelectorAll('.aips-ad-container:not(.aips-sticky-anchor)');
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
						if (!timers.has(target)) {
							var timerId = setTimeout(function () {
								self.recordImpression(slotId, postId, campaignId);
								self._recordedImpressions.add(slotId);
								timers.delete(target);
							}, 1000);
							timers.set(target, timerId);
						}
					} else {
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
		 * Smart Ad Refresh Engine.
		 * Refreshes eligible in-view ad slots only when actively visible and user is active.
		 */
		setupSmartRefresh: function () {
			if (!this.config.adRefreshEnabled) {
				return;
			}

			var self = this;
			var refreshableContainers = document.querySelectorAll('.aips-ad-container[data-auto-refresh="1"]');
			if (!refreshableContainers.length || typeof IntersectionObserver === 'undefined') {
				return;
			}

			var refreshObserver = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					var target = entry.target;
					var state = self._refreshStates.get(target);
					if (!state) {
						var interval = parseInt(target.getAttribute('data-refresh-interval'), 10) || 30;
						var maxRefreshes = parseInt(target.getAttribute('data-max-refreshes'), 10) || 5;
						state = {
							inView: false,
							dwellSeconds: 0,
							refreshCount: 0,
							interval: interval,
							maxRefreshes: maxRefreshes,
							intervalTimer: null
						};
						self._refreshStates.set(target, state);
					}

					state.inView = (entry.isIntersecting && entry.intersectionRatio >= 0.5);

					if (state.inView && !state.intervalTimer && state.refreshCount < state.maxRefreshes) {
						state.intervalTimer = setInterval(function () {
							// Guard: tab must be visible and user active recently
							if (document.hidden || !self.isUserActive() || !state.inView) {
								return;
							}

							state.dwellSeconds++;
							if (state.dwellSeconds >= state.interval) {
								state.dwellSeconds = 0;
								state.refreshCount++;
								self.triggerSlotRefresh(target);

								if (state.refreshCount >= state.maxRefreshes) {
									clearInterval(state.intervalTimer);
									state.intervalTimer = null;
								}
							}
						}, 1000);
					} else if (!state.inView && state.intervalTimer) {
						clearInterval(state.intervalTimer);
						state.intervalTimer = null;
					}
				});
			}, {
				threshold: [0.5]
			});

			refreshableContainers.forEach(function (container) {
				refreshObserver.observe(container);
			});
		},

		/**
		 * Execute smart refresh on an ad container.
		 */
		triggerSlotRefresh: function (container) {
			var slotId = container.getAttribute('data-slot-id');
			var postId = container.getAttribute('data-post-id');
			var campaignId = container.getAttribute('data-campaign-id') || 0;
			var device = this.detectDevice();

			// Pulse animation for seamless transition
			var inner = container.querySelector('.aips-ad-inner');
			if (inner) {
				inner.style.transition = 'opacity 0.2s ease';
				inner.style.opacity = '0.4';
				setTimeout(function () {
					inner.style.opacity = '1';
				}, 250);
			}

			// Fire custom DOM event for custom ad scripts/prebid
			var evt = new CustomEvent('aips:ad-refresh', {
				detail: { slotId: slotId, postId: postId }
			});
			container.dispatchEvent(evt);

			// First-party telemetry
			this.enqueueEvent({
				slot_id: parseInt(slotId, 10),
				post_id: parseInt(postId, 10),
				campaign_id: parseInt(campaignId, 10),
				event_type: 'smart_refresh',
				device_type: device
			});
		},

		/**
		 * Sticky Bottom Anchor Controller.
		 */
		setupStickyAnchors: function () {
			var self = this;
			var anchors = document.querySelectorAll('.aips-sticky-anchor');
			if (!anchors.length) {
				return;
			}

			anchors.forEach(function (anchor) {
				var slotId = anchor.getAttribute('data-slot-id');
				var dismissKey = 'aips_dismiss_anchor_' + slotId;

				// Check dismissal persistence
				if (sessionStorage.getItem(dismissKey) === '1') {
					anchor.remove();
					return;
				}

				var triggerMode = anchor.getAttribute('data-anchor-trigger') || 'scroll_depth';
				var scrollThreshold = parseInt(anchor.getAttribute('data-anchor-scroll'), 10) || 15;

				// Close button listener
				var closeBtn = anchor.querySelector('.aips-anchor-close');
				if (closeBtn) {
					closeBtn.addEventListener('click', function () {
						anchor.classList.add('aips-anchor-hidden');
						sessionStorage.setItem(dismissKey, '1');
						setTimeout(function () {
							anchor.remove();
						}, 400);
					});
				}

				if (triggerMode === 'immediate') {
					anchor.classList.remove('aips-anchor-hidden');
					self.recordImpression(slotId, anchor.getAttribute('data-post-id') || 0, anchor.getAttribute('data-campaign-id') || 0);
					return;
				}

				// Initially hidden for scroll-based triggers
				anchor.classList.add('aips-anchor-hidden');

				var lastScrollTop = window.pageYOffset || document.documentElement.scrollTop;
				var revealed = false;

				window.addEventListener('scroll', function () {
					var currentScroll = window.pageYOffset || document.documentElement.scrollTop;
					var docHeight = document.documentElement.scrollHeight - window.innerHeight;
					var scrollPct = docHeight > 0 ? (currentScroll / docHeight) * 100 : 0;

					if (triggerMode === 'scroll_depth') {
						if (scrollPct >= scrollThreshold) {
							anchor.classList.remove('aips-anchor-hidden');
							if (!revealed) {
								revealed = true;
								self.recordImpression(slotId, anchor.getAttribute('data-post-id') || 0, anchor.getAttribute('data-campaign-id') || 0);
							}
						}
					} else if (triggerMode === 'smart_scroll') {
						// Show on scroll down past threshold, hide on scroll up
						if (currentScroll > lastScrollTop && scrollPct >= scrollThreshold) {
							anchor.classList.remove('aips-anchor-hidden');
							if (!revealed) {
								revealed = true;
								self.recordImpression(slotId, anchor.getAttribute('data-post-id') || 0, anchor.getAttribute('data-campaign-id') || 0);
							}
						} else if (currentScroll < lastScrollTop) {
							anchor.classList.add('aips-anchor-hidden');
						}
					}

					lastScrollTop = currentScroll;
				}, { passive: true });
			});
		},

		/**
		 * Ad-Block Detection and Three-Tier Recovery.
		 */
		setupAdBlockRecovery: function () {
			var self = this;
			var mode = this.config.adblockRecoveryMode || 'silent_fallback';
			if (mode === 'disabled') {
				return;
			}

			// Delay detection slightly to allow ad scripts / blockers to settle
			setTimeout(function () {
				var bait = document.getElementById('aips-adblock-bait');
				var isBlocked = false;

				if (!bait || bait.offsetParent === null || bait.offsetHeight === 0 || window.getComputedStyle(bait).display === 'none' || window.getComputedStyle(bait).visibility === 'hidden') {
					isBlocked = true;
				}

				if (isBlocked && !self._adBlockDetected) {
					self._adBlockDetected = true;

					// Record telemetry event
					self.enqueueEvent({
						slot_id: 0,
						post_id: 0,
						campaign_id: 0,
						event_type: 'ad_block_detected',
						device_type: self.detectDevice()
					});

					// Execute configured tier recovery
					if (mode === 'silent_fallback') {
						self.applySilentFallback();
					} else if (mode === 'soft_notice') {
						self.showSoftNotice();
					} else if (mode === 'polite_dimmer') {
						self.applyPoliteDimmer();
					}
				}
			}, 350);
		},

		/**
		 * Tier 1: Silently reveal house fallback ads in empty slots.
		 */
		applySilentFallback: function () {
			var fallbacks = document.querySelectorAll('.aips-ad-fallback');
			fallbacks.forEach(function (fb) {
				var container = fb.closest('.aips-ad-container');
				if (container) {
					var inner = container.querySelector('.aips-ad-inner');
					if (inner) {
						inner.style.display = 'none';
					}
					fb.style.display = 'block';
				}
			});
		},

		/**
		 * Tier 2: Unobtrusive toast notice asking to whitelist.
		 */
		showSoftNotice: function () {
			if (sessionStorage.getItem('aips_dismiss_adblock_notice') === '1') {
				return;
			}

			var text = this.config.adblockNoticeText || 'We notice you are using an ad blocker. Please consider supporting our free content by disabling your ad blocker.';
			var toast = document.createElement('div');
			toast.className = 'aips-adblock-notice';
			toast.innerHTML = '<button type="button" class="aips-adblock-notice-close" aria-label="Dismiss">&times;</button>'
				+ '<strong>Support Our Work</strong><p style="margin:6px 0 0 0;">' + text + '</p>';

			document.body.appendChild(toast);

			toast.querySelector('.aips-adblock-notice-close').addEventListener('click', function () {
				toast.classList.add('aips-notice-hidden');
				sessionStorage.setItem('aips_dismiss_adblock_notice', '1');
				setTimeout(function () { toast.remove(); }, 350);
			});
		},

		/**
		 * Tier 3: Polite Content Dimmer below paragraph 3.
		 */
		applyPoliteDimmer: function () {
			if (sessionStorage.getItem('aips_dismiss_adblock_dimmer') === '1') {
				return;
			}

			var article = document.querySelector('article, .entry-content, .post-content');
			if (!article) {
				return;
			}

			var paragraphs = article.querySelectorAll('p');
			if (paragraphs.length <= 3) {
				this.showSoftNotice();
				return;
			}

			// Wrap paragraphs after paragraph 3 in dimmed container
			var wrap = document.createElement('div');
			wrap.className = 'aips-content-dimmed-wrap';

			var dimmedContent = document.createElement('div');
			dimmedContent.className = 'aips-content-dimmed';

			var pArray = Array.from(paragraphs);
			for (var i = 3; i < pArray.length; i++) {
				dimmedContent.appendChild(pArray[i]);
			}

			var overlay = document.createElement('div');
			overlay.className = 'aips-dimmer-overlay';
			overlay.innerHTML = '<div class="aips-dimmer-card">'
				+ '<h4>Ad Blocker Detected</h4>'
				+ '<p>' + (this.config.adblockNoticeText || 'Please disable your ad blocker to continue reading our free content.') + '</p>'
				+ '<button type="button" class="aips-btn-dimmer-dismiss">Continue Reading</button>'
				+ '</div>';

			wrap.appendChild(dimmedContent);
			wrap.appendChild(overlay);
			article.appendChild(wrap);

			overlay.querySelector('.aips-btn-dimmer-dismiss').addEventListener('click', function () {
				dimmedContent.classList.remove('aips-content-dimmed');
				overlay.remove();
				sessionStorage.setItem('aips_dismiss_adblock_dimmer', '1');
			});
		},

		/**
		 * Setup click listeners on links within ad containers and sponsor elements.
		 */
		setupClickListeners: function () {
			var self = this;
			document.addEventListener('click', function (e) {
				var target = e.target;
				var container = target.closest('.aips-ad-container, .aips-sponsor-card, .aips-sponsor-disclosure, .aips-referral-ribbon');
				if (!container) {
					return;
				}

				// Check if clicked an anchor or button
				var link = target.closest('a, button');
				if (!link || link.classList.contains('aips-anchor-close') || link.classList.contains('aips-btn-copy-code')) {
					return;
				}

				var slotId = container.getAttribute('data-slot-id') || 0;
				var postId = container.getAttribute('data-post-id') || 0;
				var campaignId = container.getAttribute('data-campaign-id') || container.getAttribute('data-program-id') || 0;

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

			var token = this.config.telemetryToken || this.config.nonce || '';
			var url = this.config.restUrl;
			if (token) {
				url += (url.indexOf('?') === -1 ? '?' : '&') + '_wpnonce=' + encodeURIComponent(token) + '&token=' + encodeURIComponent(token);
			}

			var bodyData = JSON.stringify({ events: payload, token: token });

			if (navigator.sendBeacon) {
				var blob = new Blob([bodyData], { type: 'application/json' });
				navigator.sendBeacon(url, blob);
			} else {
				fetch(url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': token,
						'X-AIPS-Telemetry-Token': token
					},
					body: bodyData,
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
