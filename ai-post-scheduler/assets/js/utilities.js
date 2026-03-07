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

            var safeMessage = isHtml ? message : $('<div>').text(message).html();

            var $toast = $('<div class="aips-toast ' + type + '">')
                .append('<span class="aips-toast-icon">' + (iconMap[type] || iconMap.info) + '</span>')
                .append('<div class="aips-toast-message">' + safeMessage + '</div>')
                .append('<button class="aips-toast-close" aria-label="Close">&times;</button>');

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
        }
    };

    // ---------------------------------------------------------------------------
    // Backward-compatibility shim: AIPS.showToast → AIPS.Utilities.showToast
    // ---------------------------------------------------------------------------
    window.AIPS.showToast = function(message, type, opts) {
        window.AIPS.Utilities.showToast(message, type, opts);
    };

    $(document).ready(function() {
        AIPS.Utilities.init();
    });

})(jQuery);
