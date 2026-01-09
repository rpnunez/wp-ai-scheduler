(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with Seeder functionality
    Object.assign(window.AIPS, {

        /**
         * Append a line to the seeder log area. Optionally colorize the text.
         * @param {string} message
         * @param {string} [color] CSS color or named color class
         */
        seederAppendLog: function(message, color) {
            var $log = $('#aips-seeder-log');

            if (!$log.length) {
                return;
            }

            var $el = $('<div></div>').html(message);

            if (color) {
                $el.css('color', color);
            }

            $log.append($el);
        },

        /**
         * Process a queue of seeder tasks sequentially.
         * Each task should be an object: { type, count, label, keywords }
         * @param {Array<Object>} queue
         */
        processSeederQueue: function(queue) {
            var $submitBtn = $('#aips-seeder-submit');
            var $spinner = $('#aips-seeder-form').find('.spinner');

            if (!Array.isArray(queue)) {
                queue = [];
            }

            if (queue.length === 0) {
                // Use localized "all done" text when available
                var doneHtml = aipsSeederL10n.allDone;

                window.AIPS.seederAppendLog(doneHtml);

                $submitBtn.prop('disabled', false);
                $spinner.removeClass('is-active');

                return;
            }

            var task = queue.shift();

            window.AIPS.seederAppendLog(aipsSeederL10n.generating
                .replace('%count%', window.AIPS.escapeHtml(String(task.count)))
                .replace('%label%', window.AIPS.escapeHtml(task.label) + '...')
            );

            $.ajax({
                url: window.AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_process_seeder',
                    nonce: window.AIPS.resolveNonce(),
                    type: task.type,
                    count: task.count,
                    keywords: task.keywords
                },
                success: function(response) {
                    if (response && response.success) {
                        var msg = response.data && response.data.message ? response.data.message : aipsSeederL10n.completedDefault + ' ' + task.label;

                        window.AIPS.seederAppendLog('- ' + window.AIPS.escapeHtml(msg), 'green');
                    } else {
                        var err = response && response.data && response.data.message ? response.data.message : aipsSeederL10n.unknownError;

                        window.AIPS.seederAppendLog('! Error: ' + window.AIPS.escapeHtml(err), 'red');
                    }

                    // Continue processing remaining tasks
                    window.AIPS.processSeederQueue(queue);
                },
                error: function(xhr, status, error) {
                    window.AIPS.seederAppendLog(aipsSeederL10n.ajaxErrorPrefix + window.AIPS.escapeHtml(error), 'red');

                    // Continue anyway
                    window.AIPS.processSeederQueue(queue);
                }
            });
        },

        /**
         * Handle seeder form submission.
         * Builds the queue, validates inputs, updates UI, and starts processing.
         * @param {Event} e
         */
        handleSeederSubmit: function(e) {
            e.preventDefault();

            var $form = $('#aips-seeder-form');
            var $submitBtn = $('#aips-seeder-submit');
            var $spinner = $form.find('.spinner');
            var $results = $('#aips-seeder-results');
            var $log = $('#aips-seeder-log');

            var queue = [];
            var keywords = $('#seeder-keywords').val();
            var voices = parseInt($('#seeder-voices').val()) || 0;
            var templates = parseInt($('#seeder-templates').val()) || 0;
            var schedule = parseInt($('#seeder-schedule').val()) || 0;
            var planner = parseInt($('#seeder-planner').val()) || 0;

            if (voices > 0) {
                queue.push({ type: 'voices', count: voices, label: 'Voices', keywords: keywords });
            }

            if (templates > 0) {
                queue.push({ type: 'templates', count: templates, label: 'Templates', keywords: keywords });
            }

            if (schedule > 0) {
                queue.push({ type: 'schedule', count: schedule, label: 'Scheduled Templates', keywords: keywords });
            }

            if (planner > 0) {
                queue.push({ type: 'planner', count: planner, label: 'Planner Entries', keywords: keywords });
            }

            if (queue.length === 0) {
                var enterMsg = (typeof aipsSeederL10n !== 'undefined' && aipsSeederL10n.enterQuantity) ? aipsSeederL10n.enterQuantity : 'Please enter at least one quantity.';
                alert(enterMsg);

                return;
            }

            if (!confirm(aipsSeederL10n.confirmMessage)) {
                return;
            }

            $submitBtn.prop('disabled', true);
            $spinner.addClass('is-active');
            $results.show();
            $log.empty();

            // Append to seeder log
            window.AIPS.seederAppendLog(aipsSeederL10n.startingSeeder);

            // Start processing the queue
            window.AIPS.processSeederQueue(queue);
        }

    });

    // Bind Seeder Events
    $(document).ready(function() {
        $(document).on('submit', '#aips-seeder-form', window.AIPS.handleSeederSubmit);
    });

})(jQuery);
