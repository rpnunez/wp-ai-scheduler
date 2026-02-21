/**
 * AIPS Settings
 *
 * Settings page functionality (connection test, etc.).
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(AIPS, {

        testConnection: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $spinner = $btn.next('.spinner');
            var $result = $spinner.next('#aips-connection-result');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $result.removeClass('aips-status-ok aips-status-error').text('');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_test_connection',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('aips-status-ok').html('<span class="dashicons dashicons-yes"></span> ' + response.data.message);
                    } else {
                        $result.addClass('aips-status-error').html('<span class="dashicons dashicons-warning"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    $result.addClass('aips-status-error').text('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        }
    });

    // Bind settings events
    $(document).ready(function() {
        $(document).on('click', '#aips-test-connection', AIPS.testConnection);
    });

})(jQuery);
