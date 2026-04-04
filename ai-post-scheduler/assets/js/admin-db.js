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
         * Export plugin data as a downloadable file.
         *
         * Reads the desired format from `#aips-export-format`, builds a hidden
         * `<form>` with the selected format and security nonce, submits it to the
         * `aips_export_data` AJAX action (which responds with file-download headers),
         * then removes the form and re-enables the button after a short delay.
         *
         * @param {Event} e - Click event from an `.aips-export-data` element.
         */
        exportData: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var format = $('#aips-export-format').val();

            $btn.prop('disabled', true).text('Exporting...');

            // Create a form and submit it to trigger download
            var form = $('<form>', {
                'method': 'POST',
                'action': aipsAjax.ajaxUrl
            });

            form.append($('<input>', {
                'type': 'hidden',
                'name': 'action',
                'value': 'aips_export_data'
            }));

            form.append($('<input>', {
                'type': 'hidden',
                'name': 'nonce',
                'value': aipsAjax.nonce
            }));

            form.append($('<input>', {
                'type': 'hidden',
                'name': 'format',
                'value': format
            }));

            form.appendTo('body').submit();

            // Re-enable button after a short delay
            setTimeout(function() {
                $btn.prop('disabled', false).text('Export Data');
                form.remove();
            }, 1000);
        },

        /**
         * Confirm and import plugin data from a user-selected file.
         *
         * Validates that a file has been chosen via `#aips-import-file`, shows a
         * destructive-data-loss warning dialog, then sends a multipart AJAX
         * request to the `aips_import_data` action using `FormData`.
         * Reloads the page after a short delay on success.
         *
         * @param {Event} e - Click event from an `.aips-import-data` element.
         */
        importData: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var format = $('#aips-import-format').val();
            var fileInput = $('#aips-import-file')[0];

            if (!fileInput.files || !fileInput.files[0]) {
                AIPS.Utilities.showToast('Please select a file to import.', 'warning');
                return;
            }

            var confirmMsg = 'WARNING: This will overwrite existing data! Have you made a backup? This action is irreversible. Are you sure you want to continue?';

            AIPS.Utilities.confirm(confirmMsg, 'Warning', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, import', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Importing...');

            var formData = new FormData();
            formData.append('action', 'aips_import_data');
            formData.append('nonce', aipsAjax.nonce);
            formData.append('format', format);
            formData.append('import_file', fileInput.files[0]);

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        AIPS.Utilities.showToast('Import failed: ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast('An error occurred during import.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Import Data');
                    fileInput.value = '';
                }
            });
                }}
            ]);
        },

        /**
         * Run one-time notifications hygiene command from System Status.
         *
         * @param {Event} e - Click event from an `.aips-notifications-hygiene` element.
         */
        runNotificationsHygiene: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $result = $('.aips-notifications-hygiene-result');

            AIPS.Utilities.confirm('Run notifications hygiene now? This cleans legacy notification options, unschedules deprecated hooks, and normalizes preferences.', 'Confirm', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, run hygiene', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $btn.prop('disabled', true).text('Running...');
                    $result.hide().empty();

                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_notifications_data_hygiene',
                            nonce: aipsAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                var details = response.data && response.data.details ? response.data.details : {};
                                var summary = 'Removed options: ' + (details.removed_options || 0)
                                    + ' | Unscheduled events: ' + (details.unscheduled_events || 0)
                                    + ' | Rollup scheduled: ' + ((details.rollup_scheduled || 0) ? 'yes' : 'no')
                                    + ' | Preferences normalized: ' + ((details.preferences_changed || 0) ? 'yes' : 'no');
                                AIPS.Utilities.showToast(response.data.message, 'success');
                                $result.html('<p class="aips-status-message aips-status-success">' + summary + '</p>').show();
                            } else {
                                AIPS.Utilities.showToast(response.data.message || 'Hygiene command failed.', 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast('An error occurred while running hygiene.', 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Run Notifications Hygiene');
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
        },

        /**
         * Refresh vector diagnostics panel data in System Status.
         *
         * @param {Event} e - Click event from `.aips-refresh-vector-diagnostics`.
         */
        refreshVectorDiagnostics: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $result = $('.aips-vector-diagnostics-result');

            $btn.prop('disabled', true).text('Refreshing...');
            $result.hide().empty();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_vector_diagnostics',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (!response || !response.success || !response.data || !response.data.diagnostics) {
                        var fallbackError = (response && response.data && response.data.message) ? response.data.message : 'Failed to refresh vector diagnostics.';
                        AIPS.Utilities.showToast(fallbackError, 'error');
                        $result.html('<p class="aips-status-message aips-status-error">' + $('<span>').text(fallbackError).html() + '</p>').show();
                        return;
                    }

                    var diagnostics = response.data.diagnostics;

                    $('#aips-vector-active-provider').text(diagnostics.active_provider || 'local');
                    $('#aips-vector-reachability-label').text((diagnostics.pinecone_reachability && diagnostics.pinecone_reachability.label) ? diagnostics.pinecone_reachability.label : 'Unknown');

                    var detailsText = (diagnostics.pinecone_reachability && diagnostics.pinecone_reachability.details) ? diagnostics.pinecone_reachability.details : '';
                    if (detailsText) {
                        $('#aips-vector-reachability-details').text(detailsText).show();
                    } else {
                        $('#aips-vector-reachability-details').text('').hide();
                    }

                    var reachabilityStatus = diagnostics.pinecone_reachability && diagnostics.pinecone_reachability.status ? diagnostics.pinecone_reachability.status : 'info';
                    var badgeHtml = '<span class="aips-badge aips-badge-info"><span class="dashicons dashicons-info"></span>Info</span>';
                    if (reachabilityStatus === 'ok') {
                        badgeHtml = '<span class="aips-badge aips-badge-success"><span class="dashicons dashicons-yes-alt"></span>OK</span>';
                    } else if (reachabilityStatus === 'warning') {
                        badgeHtml = '<span class="aips-badge aips-badge-warning"><span class="dashicons dashicons-warning"></span>Warning</span>';
                    } else if (reachabilityStatus === 'error') {
                        badgeHtml = '<span class="aips-badge aips-badge-error"><span class="dashicons dashicons-dismiss"></span>Error</span>';
                    }
                    $('#aips-vector-reachability-status-cell').html(badgeHtml);

                    $('#aips-vector-upsert-success').text(parseInt(diagnostics.upsert_success || 0, 10));
                    $('#aips-vector-upsert-error').text(parseInt(diagnostics.upsert_error || 0, 10));
                    $('#aips-vector-query-success').text(parseInt(diagnostics.query_success || 0, 10));
                    $('#aips-vector-query-error').text(parseInt(diagnostics.query_error || 0, 10));
                    $('#aips-vector-last-error').text(diagnostics.last_error_message ? diagnostics.last_error_message : 'None');

                    var successMessage = response.data.message || 'Vector diagnostics refreshed.';
                    AIPS.Utilities.showToast(successMessage, 'success');
                    $result.html('<p class="aips-status-message aips-status-success">' + $('<span>').text(successMessage).html() + '</p>').show();
                },
                error: function() {
                    var errorMessage = 'An error occurred while refreshing vector diagnostics.';
                    AIPS.Utilities.showToast(errorMessage, 'error');
                    $result.html('<p class="aips-status-message aips-status-error">' + $('<span>').text(errorMessage).html() + '</p>').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Refresh Vector Diagnostics');
                }
            });
        }
    });

    // Bind DB Management Events
    $(document).ready(function() {
        $(document).on('click', '.aips-repair-db', window.AIPS.repairDb);
        $(document).on('click', '.aips-reinstall-db', window.AIPS.reinstallDb);
        $(document).on('click', '.aips-wipe-db', window.AIPS.wipeDb);
        $(document).on('click', '.aips-export-data', window.AIPS.exportData);
        $(document).on('click', '.aips-import-data', window.AIPS.importData);
		$(document).on('click', '.aips-notifications-hygiene', window.AIPS.runNotificationsHygiene);
        $(document).on('click', '.aips-flush-cron', window.AIPS.flushCronEvents);
        $(document).on('click', '.aips-refresh-vector-diagnostics', window.AIPS.refreshVectorDiagnostics);
    });

})(jQuery);
