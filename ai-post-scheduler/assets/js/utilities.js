/**
 * AIPS Shared Utilities
 *
 * Common/shared utility functions used across all AIPS admin pages.
 * Exposes methods under the window.AIPS.Utilities namespace.
 *
 * @package AI_Post_Scheduler
 * @since 1.8.0
 */

(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    window.AIPS.Utilities = {

        /**
         * Placeholder initialisation hook for the Utilities namespace.
         *
         * Called on `document.ready`. Currently a no-op; reserved for any
         * future setup that must run once the DOM is ready.
         */
        init: function() {
            // Nothing needed on init currently; reserved for future use.
        },

        /**
         * Displays a toast notification centered ~1/3 down from the top of the screen,
         * aligned to the horizontal center of .aips-page-container when present.
         *
         * Accepts plain text or pre-built HTML (for links). Plain-text messages
         * are auto-escaped; if you pass HTML, set `isHtml` to true.
         *
         * @param {string}  message           - The message to display.
         * @param {string}  [type='info']     - One of 'success', 'error', 'warning', 'info'.
         * @param {Object}  [opts]            - Optional settings.
         * @param {boolean} [opts.isHtml]     - If true, message is inserted as raw HTML.
         * @param {number}  [opts.duration]   - Auto-dismiss delay in ms (0 = no auto-dismiss). Default 6000.
         */
        showToast: function(message, type, opts) {
            type = type || 'info';
            opts = opts || {};
            var duration = opts.duration !== undefined ? opts.duration : 6000;
            var isHtml   = opts.isHtml || false;

            var iconMap = { success: '\u2713', error: '\u2715', warning: '\u26A0', info: '\u2139' };

            var $container = $('#aips-toast-container');
            if (!$container.length) {
                $container = $('<div id="aips-toast-container"></div>');
                $('body').append($container);
                AIPS.Utilities._positionToastContainer($container);
            }

            var closeLabel = (window.aipsUtilitiesL10n && aipsUtilitiesL10n.closeLabel) ? aipsUtilitiesL10n.closeLabel : 'Close notification';
            var safeMessage = isHtml ? message : $('<div>').text(message).html();

            var $toast = $('<div class="aips-toast ' + type + '">')
              .append('<span class="aips-toast-icon">' + iconMap[type] + '</span>')
              .append('<div class="aips-toast-message">' + safeMessage + '</div>')
              .append($('<button class="aips-toast-close">&times;</button>').attr('aria-label', closeLabel));

            $container.append($toast);

            $toast.find('.aips-toast-close').on('click', function() {
                $toast.addClass('closing');
                setTimeout(function() { $toast.remove(); }, 300);
            });

            if (duration > 0) {
                setTimeout(function() {
                    if ($toast.parent().length) {
                        $toast.addClass('closing');
                        setTimeout(function() { $toast.remove(); }, 300);
                    }
                }, duration);
            }
        },

        /**
         * Aligns the toast container's horizontal center to .aips-page-container
         * (or #wpcontent as a fallback) so toasts appear centered within the
         * plugin's content area rather than the full viewport.
         *
         * Also attaches a debounced window resize listener so the position stays
         * correct if the browser window is resized or the sidebar is toggled.
         *
         * @param {jQuery} $container - The #aips-toast-container element.
         * @private
         */
        _positionToastContainer: function($container) {
            var self = this;

            /**
             * Recalculate and apply the horizontal centre position of the
             * toast container based on the current bounding rect of the
             * `.aips-page-container` element (or `#wpcontent` as a fallback).
             *
             * Called immediately and again on every debounced `resize` event so
             * the container stays centred when the window or admin sidebar changes
             * size.
             *
             * @private
             */
            function reposition() {
                var el = document.querySelector('.aips-page-container') ||
                         document.getElementById('wpcontent');
                if (!el) { return; }
                var rect = el.getBoundingClientRect();
                $container.css('left', Math.round(rect.left + rect.width / 2) + 'px');
            }

            reposition();

            var resizeTimer;
            $(window).on('resize.aips-toast', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(reposition, 100);
            });
        },

        /**
         * Shows a styled modal confirmation dialog.
         *
         * Replaces the native browser confirm() with a non-blocking, styled modal.
         * The caller provides an action callback on the relevant button.
         *
         * @param {string} message            - The message/body text to display.
         * @param {string} [heading='Notice'] - The modal heading/title.
         * @param {Array}  [buttons]          - Array of button config objects. Each object may contain:
         *   @param {string}   buttons[].label     - Button label text.
         *   @param {string}   [buttons[].className] - CSS class(es) for the button (e.g. 'aips-btn aips-btn-danger-solid').
         *   @param {Function} [buttons[].action]   - Callback invoked when this button is clicked.
         *                                            If omitted the modal simply closes.
         *
         * @example
         * // Simple one-button alert
         * AIPS.Utilities.confirm('Something happened.');
         *
         * @example
         * // Delete confirmation
         * AIPS.Utilities.confirm(
         *     "You're about to delete this item. Are you sure?",
         *     'Notice',
         *     [
         *         { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
         *         { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() { doDelete(); } }
         *     ]
         * );
         */
        confirm: function(message, heading, buttons) {
            heading = heading || 'Notice';

            if (!buttons || !buttons.length) {
                buttons = [
                    { label: 'OK', className: 'aips-btn aips-btn-primary' }
                ];
            }


            var headingId = 'aips-confirm-heading-' + Date.now() + '-' + Math.floor(Math.random() * 1000000);

            // Build the overlay
            var $overlay = $('<div></div>')
                .addClass('aips-confirm-overlay')
                .attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': headingId });

            var $dialog = $('<div class="aips-confirm-dialog"></div>');

            var $header = $('<div class="aips-confirm-header"></div>')
                .append($('<h3></h3>').attr({ id: headingId, 'class': 'aips-confirm-heading' }).text(heading));

            var $body = $('<div class="aips-confirm-body"></div>')
                .append($('<p class="aips-confirm-message"></p>').text(message));

            var $footer = $('<div class="aips-confirm-footer"></div>');

            /**
             * Animate the dialog overlay out and remove it from the DOM.
             *
             * Also unbinds the `keydown.aips-confirm` event handler that was
             * registered to close the dialog on Escape.
             *
             * @private
             */
            function closeDialog() {
                $overlay.addClass('aips-confirm-closing');
                setTimeout(function() { $overlay.remove(); }, 200);
                $(document).off('keydown.aips-confirm');
            }

            $.each(buttons, function(i, btn) {
                var label     = btn.label     || 'OK';
                var className = btn.className || 'aips-btn aips-btn-secondary';
                var action    = typeof btn.action === 'function' ? btn.action : null;

                var $btn = $('<button type="button"></button>')
                    .addClass(className)
                    .text(label);

                $btn.on('click', function() {
                    closeDialog();
                    if (action) {
                        action();
                    }
                });

                $footer.append($btn);
            });

            $dialog.append($header, $body, $footer);
            $overlay.append($dialog);
            $('body').append($overlay);

            // Focus the first button for accessibility
            $footer.find('button').first().trigger('focus');

            // Close on Escape key
            $(document).on('keydown.aips-confirm', function(e) {
                if (e.key === 'Escape') {
                    closeDialog();
                }
            });

            // Close when clicking the backdrop (outside the dialog)
            $overlay.on('click', function(e) {
                if ($(e.target).is($overlay)) {
                    closeDialog();
                }
            });
        },

        /**
         * Opens a non-dismissable progress-bar modal to give feedback during a
         * long-running async operation (e.g. bulk post generation).
         *
         * The bar advances linearly from 0 % to a configurable `stallAt`
         * percentage over `totalSeconds`. Once the caller invokes `complete()`,
         * the bar jumps to 100 %, the status line updates, and the modal
         * auto-closes after a short pause.
         *
         * @param {Object} options
         * @param {string}   [options.title]        - Modal heading.  Defaults to 'Processing…'.
         * @param {string}   [options.message]       - Subtitle / description shown below the heading.
         * @param {number}   [options.totalSeconds]  - Estimated total duration in seconds. Default 30.
         * @param {number}   [options.stallAt]       - Percentage at which the bar pauses to wait for
         *                                             the real completion signal (0–99). Default 92.
         *
         * @returns {{ complete: function(string, string): void,
         *             cancel:   function(): void }}
         *   `complete(message, type)` — jump the bar to 100 %, show `message`, close after 1.2 s.
         *   `cancel()`               — close the modal immediately without animation.
         *
         * @example
         * var ctrl = AIPS.Utilities.showProgressBar({
         *     title:        'Generating Posts',
         *     message:      'Please wait…',
         *     totalSeconds: 60
         * });
         *
         * $.ajax({ ... }).always(function(resp) {
         *     var ok = resp && resp.success;
         *     ctrl.complete(
         *         ok ? 'Done!' : 'Finished with errors.',
         *         ok ? 'success' : 'warning'
         *     );
         * });
         */
        showProgressBar: function(options) {
            options = options || {};

            // Use caller-supplied strings → aipsUtilitiesL10n (always available on every
            // AIPS admin page) → bare English fallbacks.  Never couple to aipsAuthorsL10n.
            var l10n = options.l10n || window.aipsUtilitiesL10n || {};

            var title        = options.title        || 'Processing…';
            var message      = options.message      || '';
            var totalSeconds = options.totalSeconds  > 0 ? options.totalSeconds : 30;
            var stallAt      = (options.stallAt !== undefined) ? options.stallAt : 92;

            stallAt = Math.min(Math.max(stallAt, 10), 99);

            // ── Build DOM ──────────────────────────────────────────────────
            var headingId = 'aips-progress-heading-' + Date.now() + '-' + Math.floor(Math.random() * 1e6);

            // The overlay is a modal container; aria-live does NOT belong here
            // (it would cause every descendant text change to be announced).
            var $overlay = $('<div></div>')
                .addClass('aips-confirm-overlay aips-progress-overlay')
                .attr({ role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': headingId });

            var $dialog = $('<div class="aips-confirm-dialog aips-progress-dialog"></div>');

            var $header = $('<div class="aips-confirm-header"></div>')
                .append($('<h3></h3>').attr({ id: headingId, 'class': 'aips-confirm-heading' }).text(title));

            var $body = $('<div class="aips-confirm-body aips-progress-body"></div>');

            if (message) {
                $body.append($('<p class="aips-confirm-message aips-progress-description"></p>').text(message));
            }

            var $barWrap = $('<div class="aips-progress-bar-wrap"></div>');
            var $barFill = $('<div class="aips-progress-bar-fill"></div>')
                .attr({
                    role: 'progressbar',
                    'aria-valuemin': '0',
                    'aria-valuemax': '100',
                    'aria-valuenow': '0'
                })
                .css('width', '0%');
            $barWrap.append($barFill);
            $body.append($barWrap);

            // Visual-only countdown (updated every 500 ms, no aria-live).
            var $statusLine = $('<p class="aips-progress-status"></p>');
            $body.append($statusLine);

            // Separate hidden live region updated at most every 5 s so screen
            // readers get occasional progress announcements without being spammed.
            var $liveRegion = $('<span class="screen-reader-text" aria-live="polite" aria-atomic="true"></span>');
            $body.append($liveRegion);

            $dialog.append($header, $body);
            $overlay.append($dialog);
            $('body').append($overlay);

            // Move keyboard focus into the dialog when it opens.
            $dialog.attr('tabindex', '-1');
            setTimeout(function() {
                $dialog.focus();
            }, 0);

            // ── Timer helpers ──────────────────────────────────────────────
            var startTime        = Date.now();
            var tickInterval;
            var closed           = false;
            var overdue          = false; // true once the estimated time has elapsed
            var lastAnnounceTime = 0;
            var ANNOUNCE_INTERVAL_MS = 5000; // announce to screen readers at most every 5 s

            /**
             * Format seconds into a human-readable string using l10n keys when
             * available, falling back to bare English.
             * @param  {number} secs
             * @returns {string}
             */
            function formatTime(secs) {
                secs = Math.max(0, Math.round(secs));
                if (secs < 60) {
                    return secs + ' ' + (l10n.seconds || 'seconds');
                }
                var m = Math.floor(secs / 60);
                var s = secs % 60;
                if (s === 0) {
                    if (m === 1) {
                        return l10n.minute || '1 minute';
                    }
                    var mTpl = l10n.minutes || '%d minutes';
                    return mTpl.replace('%d', m);
                }
                var msTpl = l10n.minutesSeconds || '%dm %ds';
                // Replace each %d placeholder in order (minutes first, seconds second).
                var msParts = [m, s];
                var msIdx   = 0;
                return msTpl.replace(/%d/g, function() { return msParts[msIdx++]; });
            }

            function tick() {
                if (closed) { return; }

                var elapsed   = (Date.now() - startTime) / 1000;
                var remaining = Math.max(0, totalSeconds - elapsed);

                // Once the estimated time has elapsed, switch to indeterminate mode.
                if (remaining <= 0) {
                    if (!overdue) {
                        overdue = true;
                        $barFill
                            .css('width', '100%')
                            .attr('aria-valuenow', '100')
                            .addClass('aips-progress-bar-fill--indeterminate');
                        var overdueMsg = l10n.takingLonger || 'Taking a little bit longer than expected…';
                        $statusLine.text(overdueMsg);
                        $liveRegion.text(overdueMsg); // Announce to screen readers.
                    }
                    return;
                }

                var progress = Math.min((elapsed / totalSeconds) * 100, stallAt);
                var pct      = progress.toFixed(1);

                $barFill.css('width', pct + '%').attr('aria-valuenow', Math.round(progress));

                var tpl      = l10n.estimatedTimeRemaining || 'Estimated time remaining: %s';
                var timeText = tpl.replace('%s', formatTime(remaining));

                // Update the visible countdown on every tick.
                $statusLine.text(timeText);

                // Only update the screen-reader live region every 5 s.
                var now = Date.now();
                if (now - lastAnnounceTime >= ANNOUNCE_INTERVAL_MS) {
                    $liveRegion.text(timeText);
                    lastAnnounceTime = now;
                }
            }

            tickInterval = setInterval(tick, 500);
            tick(); // Immediate first paint

            // ── Public controller ──────────────────────────────────────────
            /**
             * Signal that the operation has finished.
             *
             * @param {string} [completionMessage] - Optional text shown to the user.
             * @param {string} [type]              - 'success' | 'warning' | 'error'. Unused visually
             *                                       but available for future styling.
             */
            function complete(completionMessage, type) {
                if (closed) { return; }
                clearInterval(tickInterval);
                $barFill
                    .removeClass('aips-progress-bar-fill--indeterminate')
                    .css('width', '100%')
                    .attr('aria-valuenow', '100');

                var msg = completionMessage || l10n.generationComplete || 'Generation complete!';
                $statusLine.text(msg);
                $liveRegion.text(msg); // Announce completion immediately.

                // Close the modal after a short pause so the user sees 100%.
                setTimeout(function() { cancel(); }, 1200);
            }

            /**
             * Close (remove) the modal immediately, cancelling any pending timer.
             */
            function cancel() {
                if (closed) { return; }
                closed = true;
                clearInterval(tickInterval);
                $overlay.addClass('aips-confirm-closing');
                setTimeout(function() { $overlay.remove(); }, 200);
            }

            return { complete: complete, cancel: cancel };
        },

        // ── Action-state helpers ────────────────────────────────────────────────

        /**
         * Puts a button into a loading state.
         *
         * Saves the button's current HTML to a private data attribute so that
         * `resetButton()` can restore it exactly, then disables the button and
         * replaces its visible content with a loading label.
         *
         * Pair every call with a corresponding `resetButton()` call (typically
         * in the AJAX `complete` callback) to re-enable the button and restore
         * its original label.
         *
         * @param {jQuery} $btn         - The button element to update.
         * @param {string} loadingLabel - The text (or HTML when opts.isHtml=true)
         *                                to display while loading.
         * @param {Object} [opts]       - Optional settings.
         * @param {boolean} [opts.isHtml] - When true, loadingLabel is inserted as
         *                                  raw HTML rather than escaped text.
         *
         * @example
         * // Simple text label
         * AIPS.Utilities.setButtonLoading($saveBtn, aipsAdminL10n.saving);
         *
         * @example
         * // HTML label with a dashicon
         * AIPS.Utilities.setButtonLoading(
         *     $draftBtn,
         *     '<span class="dashicons dashicons-cloud-saved"></span> ' + aipsAdminL10n.saving,
         *     { isHtml: true }
         * );
         */
        setButtonLoading: function($btn, loadingLabel, opts) {
            opts = opts || {};
            $btn.data('aips-btn-original', $btn.html());
            $btn.prop('disabled', true);
            if (opts.isHtml) {
                $btn.html(loadingLabel);
            } else {
                $btn.text(loadingLabel);
            }
        },

        /**
         * Restores a button that was disabled by `setButtonLoading()`.
         *
         * Re-enables the element and restores its original HTML (saved by
         * `setButtonLoading()`). Safe to call even if `setButtonLoading()` was
         * never called — the button will simply be re-enabled with no label change.
         *
         * @param {jQuery} $btn - The button element to reset.
         *
         * @example
         * $.ajax({
         *     ...
         *     complete: function() { AIPS.Utilities.resetButton($saveBtn); }
         * });
         */
        resetButton: function($btn) {
            var original = $btn.data('aips-btn-original');
            if (original !== undefined) {
                $btn.html(original);
                $btn.removeData('aips-btn-original');
            }
            $btn.prop('disabled', false);
        },

        // ── String / escaping helpers ───────────────────────────────────────────

        /**
         * Escape a plain-text value for safe insertion as HTML content.
         *
         * Uses a temporary <div> element and the browser's own textContent
         * setter so the browser handles all entity encoding natively.
         * Returns an empty string for null / undefined input.
         *
         * @param  {*}      text - Value to escape (coerced to string if needed).
         * @return {string} HTML-safe string.
         */
        escapeHtml: function(text) {
            if (text === null || text === undefined) {
                return '';
            }
            var div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        },

        /**
         * Escape a plain-text value for safe use in an HTML attribute.
         *
         * Escapes &, ", ', <, >, and control characters (CR, LF, TAB) that are
         * meaningful inside attribute values.  Do not pass already-encoded
         * entities — they will be double-encoded.
         *
         * @param  {*}      text - Value to escape (coerced to string if needed).
         * @return {string} Attribute-safe string.
         */
        escapeAttribute: function(text) {
            if (text === null || text === undefined) {
                return '';
            }
            var entityMap = {
                '&':  '&amp;',
                '"':  '&quot;',
                "'":  '&#039;',
                '<':  '&lt;',
                '>':  '&gt;',
                '\r': '&#13;',
                '\n': '&#10;',
                '\t': '&#9;'
            };
            return String(text).replace(/[&"'<>\r\n\t]/g, function(match) {
                return entityMap[match];
            });
        },

        /**
         * Sanitize a URL value for safe use in an href attribute.
         *
         * Validates the protocol and rejects dangerous schemes (javascript:,
         * data:, vbscript:, file:).  Absolute http(s) URLs are normalised via
         * the URL constructor; root-relative paths are returned as-is.
         * Returns an empty string for any value that does not match an allowed
         * pattern.
         *
         * @param  {*}      url - URL value to sanitize (coerced to string).
         * @return {string} Safe URL string, or '' when the input is invalid.
         */
        sanitizeUrl: function(url) {
            if (!url) {
                return '';
            }
            var urlStr = String(url).trim();
            if (!urlStr) {
                return '';
            }
            var dangerous = ['javascript:', 'data:', 'vbscript:', 'file:'];
            var lower = urlStr.toLowerCase();
            for (var i = 0; i < dangerous.length; i++) {
                if (lower.indexOf(dangerous[i]) === 0) {
                    return '';
                }
            }
            if (urlStr.indexOf('http://') === 0 || urlStr.indexOf('https://') === 0) {
                try {
                    return new URL(urlStr).href;
                } catch (e) {
                    return '';
                }
            }
            if (urlStr.indexOf('/') === 0) {
                return urlStr;
            }
            return '';
        },

        /**
         * Convert a string to Title Case.
         *
         * Lowercases the full input, replaces underscores and hyphens with
         * spaces, then capitalises the first letter of every word.  Suitable
         * for converting slug-style values ("in_progress", "well-researched")
         * or mixed-case labels into human-readable headings.
         *
         * @param  {*}      text - Value to convert (coerced to string).
         * @return {string} Title-cased string.
         */
        toTitleCase: function(text) {
            if (text === null || text === undefined) {
                return '';
            }
            return String(text)
                .toLowerCase()
                .replace(/[_-]/g, ' ')
                .replace(/\b\w/g, function(letter) {
                    return letter.toUpperCase();
                });
        }
    };

    // ---------------------------------------------------------------------------
    // Backward-compatibility shims
    // ---------------------------------------------------------------------------
    window.AIPS.showToast = function(message, type, opts) {
        window.AIPS.Utilities.showToast(message, type, opts);
    };

    window.AIPS.escapeHtml = function(text) {
        return window.AIPS.Utilities.escapeHtml(text);
    };

    window.AIPS.escapeAttribute = function(text) {
        return window.AIPS.Utilities.escapeAttribute(text);
    };

    $(document).ready(function() {
        AIPS.Utilities.init();
    });

})(jQuery);
