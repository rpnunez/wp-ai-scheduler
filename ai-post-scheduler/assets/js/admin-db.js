(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with DB Management functionality
    Object.assign(window.AIPS, {

        /**
         * Confirm and run the database repair routine.
         *
         * Shows a confirmation dialog, then sends the `aips_repair_db` AJAX
         * action which attempts to create any missing tables or columns.
         * Reloads the page after a short delay on success.
         *
         * @param {Event} e - Click event from an `.aips-repair-db` element.
         */
        repairDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            AIPS.Utilities.confirm('Are you sure you want to run the database repair? This will attempt to create missing tables and columns.', 'Confirm', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, repair', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Repairing...');

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_repair_db',
                            nonce: aipsAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                AIPS.Utilities.showToast(response.data.message, 'success');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred.', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Repair DB Tables');
                        }
                    });
                }}
            ]);
        },

        /**
         * Confirm and run the date/time normalization repair routine.
         *
         * Converts lingering legacy date/time storage and backfills missing
         * scheduler-facing next-run values for active records.
         *
         * @param {Event} e - Click event from an `.aips-fix-datetime-db` element.
         */
        fixDateTimeValues: function(e) {
            e.preventDefault();
            var $btn = $(this);
            AIPS.Utilities.confirm('Run the date/time repair routine? This will normalize legacy date/time storage and backfill missing next-run values for active schedules, authors, and sources.', 'Confirm', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, fix values', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Fixing...');

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_fix_datetime_values',
                            nonce: aipsAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                AIPS.Utilities.showToast(response.data.message, 'success');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred.', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Fix Date/Time Values in DB');
                        }
                    });
                }}
            ]);
        },

        /**
         * Confirm and reinstall all plugin database tables.
         *
         * Reads the `#aips-backup-db` checkbox to decide whether to back up
         * existing data first. Shows a confirmation dialog with an appropriate
         * warning, then sends the `aips_reinstall_db` AJAX action.
         * Reloads the page after a short delay on success.
         *
         * @param {Event} e - Click event from an `.aips-reinstall-db` element.
         */
        reinstallDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var backup = $('#aips-backup-db').is(':checked');
            var msg = 'Are you sure you want to reinstall the database tables?';
            if (!backup) {
                msg += ' WARNING: ALL DATA WILL BE LOST unless you check the backup option!';
            } else {
                msg += ' Data will be backed up and restored.';
            }

            AIPS.Utilities.confirm(msg, 'Confirm', [
                { label: 'No, cancel',    className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, reinstall', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Reinstalling...');

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_reinstall_db',
                            nonce: aipsAjax.nonce,
                            backup: backup
                        },
                        success: function(response) {
                            if (response.success) {
                                AIPS.Utilities.showToast(response.data.message, 'success');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred.', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Reinstall DB Tables');
                        }
                    });
                }}
            ]);
        },

        /**
         * Confirm and permanently delete all plugin data.
         *
         * Shows a warning confirmation dialog (this action cannot be undone),
         * then sends the `aips_wipe_db` AJAX action.
         * Reloads the page after a short delay on success.
         *
         * @param {Event} e - Click event from an `.aips-wipe-db` element.
         */
        wipeDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            AIPS.Utilities.confirm('Are you sure you want to WIPE ALL DATA? This cannot be undone.', 'Warning', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, wipe all data', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Wiping...');

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_wipe_db',
                            nonce: aipsAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                AIPS.Utilities.showToast(response.data.message, 'success');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred.', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Wipe Plugin Data');
                        }
                    });
                }}
            ]);
        },

        /**
         * Flush all plugin WP-Cron events and re-register each exactly once.
         *
         * Shows a confirmation dialog warning that active cron events will be
         * removed and re-scheduled, then sends the `aips_flush_cron_events` AJAX
         * action. Reloads the page after a short delay on success so the updated
         * cron diagnostics are visible.
         *
         * @param {Event} e - Click event from an `.aips-flush-cron` element.
         */
        flushCronEvents: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $result = $('.aips-flush-cron-result');

            AIPS.Utilities.confirm(
                'This will remove ALL registered instances of every plugin WP-Cron event and re-register each one exactly once. ' +
                'Use this when duplicate cron events have accumulated and are causing excessive AI calls. Continue?',
                'Flush WP-Cron Events',
                [
                    { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                    { label: 'Yes, flush & reschedule', className: 'aips-btn aips-btn-danger-solid', action: function() {
                        $btn.prop('disabled', true).text('Flushing...');
                        $result.hide().empty();

                        $.ajax({
                            url: aipsAjax.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'aips_flush_cron_events',
                                nonce: aipsAjax.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var details = response.data && response.data.details ? response.data.details : {};
                                    var rescheduled = details.rescheduled ? details.rescheduled.join(', ') : '';
                                    var summary = response.data.message;
                                    if (rescheduled) {
                                        summary += ' Rescheduled: ' + rescheduled + '.';
                                    }
                                    AIPS.Utilities.showToast(response.data.message, 'success');
                                    $result.html('<p class="aips-status-message aips-status-success">' + $('<span>').text(summary).html() + '</p>').show();
                                    setTimeout(function() { location.reload(); }, 2000);
                                } else {
                                    var errMsg = response.data && response.data.message ? response.data.message : 'Flush failed.';
                                    AIPS.Utilities.showToast(errMsg, 'error');
                                    $result.html('<p class="aips-status-message aips-status-error">' + $('<span>').text(errMsg).html() + '</p>').show();
                                }
                            },
                            error: function() {
                                AIPS.Utilities.showToast('An error occurred while flushing cron events.', 'error');
                            },
                            complete: function() {
                                $btn.prop('disabled', false).text('Flush WP-Cron Events');
                            }
                        });
                    } }
                ]
            );
        }
    });

    // Bind DB Management Events
    $(document).ready(function() {
        $(document).on('click', '.aips-repair-db', window.AIPS.repairDb);
        $(document).on('click', '.aips-fix-datetime-db', window.AIPS.fixDateTimeValues);
        $(document).on('click', '.aips-reinstall-db', window.AIPS.reinstallDb);
        $(document).on('click', '.aips-wipe-db', window.AIPS.wipeDb);
        $(document).on('click', '.aips-flush-cron', window.AIPS.flushCronEvents);
    });

})(jQuery);
