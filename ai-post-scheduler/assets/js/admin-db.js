(function($) {
    'use strict';

    // Ensure AIPS object exists
    window.AIPS = window.AIPS || {};

    // Extend AIPS with DB Management functionality
    Object.assign(window.AIPS, {

        repairDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var confirmMsg = aipsAdminL10n.repairDbConfirm;
            if (!confirm(confirmMsg)) {
                return;
            }

            $btn.prop('disabled', true).text(aipsAdminL10n.repairing);

            $.ajax({
                url: window.AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_repair_db',
                    nonce: window.AIPS.resolveNonce()
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
                    alert(aipsAdminL10n.errorOccurred);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.repairDbLabel);
                }
            });
        },

        reinstallDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var backup = $('#aips-backup-db').is(':checked');
            var msg = aipsAdminL10n.reinstallDbConfirm;
            if (!backup) {
                msg += '\n\n' + aipsAdminL10n.reinstallDbWarning;
            } else {
                msg += '\n\n' + aipsAdminL10n.reinstallDbWithBackup;
            }

            if (!confirm(msg)) {
                return;
            }

            $btn.prop('disabled', true).text(aipsAdminL10n.reinstalling);

            $.ajax({
                url: window.AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_reinstall_db',
                    nonce: window.AIPS.resolveNonce(),
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
                    alert(aipsAdminL10n.errorOccurred);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.reinstallDbLabel);
                }
            });
        },

        wipeDb: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var wipeMsg = aipsAdminL10n.wipeDbConfirm;
            if (!confirm(wipeMsg)) {
                return;
            }

            $btn.prop('disabled', true).text(aipsAdminL10n.wiping);

            $.ajax({
                url: window.AIPS.resolveAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'aips_wipe_db',
                    nonce: window.AIPS.resolveNonce()
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
                    alert(aipsAdminL10n.errorOccurred);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(aipsAdminL10n.wipeDbLabel);
                }
            });
        }
    });

    // Bind DB Management Events
    $(document).ready(function() {
        $(document).on('click', '.aips-repair-db', window.AIPS.repairDb);
        $(document).on('click', '.aips-reinstall-db', window.AIPS.reinstallDb);
        $(document).on('click', '.aips-wipe-db', window.AIPS.wipeDb);
    });

})(jQuery);
