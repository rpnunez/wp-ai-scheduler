(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with DB Management functionality
    Object.assign(window.AIPS, {

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
        }
    });

    // Bind DB Management Events
    $(document).ready(function() {
        $(document).on('click', '.aips-repair-db', window.AIPS.repairDb);
        $(document).on('click', '.aips-reinstall-db', window.AIPS.reinstallDb);
        $(document).on('click', '.aips-wipe-db', window.AIPS.wipeDb);
        $(document).on('click', '.aips-export-data', window.AIPS.exportData);
        $(document).on('click', '.aips-import-data', window.AIPS.importData);
    });

})(jQuery);
