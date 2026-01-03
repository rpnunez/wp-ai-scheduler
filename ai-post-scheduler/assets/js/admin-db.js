(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with DB Management functionality
    Object.assign(window.AIPS, {

        repairDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            if (!confirm('Are you sure you want to run the database repair? This will attempt to create missing tables and columns.')) {
                return;
            }

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
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Repair DB Tables');
                }
            });
        },

        reinstallDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var backup = $('#aips-backup-db').is(':checked');
            var msg = 'Are you sure you want to reinstall the database tables?';
            if (!backup) {
                msg += '\n\nWARNING: ALL DATA WILL BE LOST unless you check the backup option!';
            } else {
                msg += '\n\nData will be backed up and restored.';
            }

            if (!confirm(msg)) {
                return;
            }

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
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Reinstall DB Tables');
                }
            });
        },

        wipeDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            if (!confirm('Are you sure you want to WIPE ALL DATA? This cannot be undone.')) {
                return;
            }

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
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Wipe Plugin Data');
                }
            });
        },

        clearLogs: function(e) {
            e.preventDefault();
            var $btn = $(this);
            if (!confirm('Are you sure you want to clear all log files?')) {
                return;
            }

            $btn.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_clear_logs',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Clear All Logs');
                }
            });
        }
    });

    // Bind DB Management Events
    $(document).ready(function() {
        $(document).on('click', '.aips-repair-db', window.AIPS.repairDb);
        $(document).on('click', '.aips-reinstall-db', window.AIPS.reinstallDb);
        $(document).on('click', '.aips-wipe-db', window.AIPS.wipeDb);
        $(document).on('click', '.aips-clear-logs', window.AIPS.clearLogs);
    });

})(jQuery);
