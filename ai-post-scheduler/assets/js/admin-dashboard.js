(function($) {
    'use strict';

    $(document).ready(function() {
        // Inner Tab Switching
        $('.aips-inner-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');

            // Toggle active tab
            $('.aips-inner-tab').removeClass('active');
            $(this).addClass('active');

            // Toggle content
            $('.aips-inner-content').hide();
            $('#aips-dashboard-' + target).show();
        });

        // Refresh Stats
        $('#aips-refresh-stats').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).find('.dashicons').addClass('spin');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_refresh_stats',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Simplest way to refresh all numbers
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                }
            });
        });

        // Fetch Logs
        $('#aips-fetch-logs').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $console = $('#aips-log-viewer');

            $btn.prop('disabled', true);
            $console.html('<div class="aips-loading"><span class="spinner is-active"></span> Loading...</div>');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_logs',
                    nonce: aipsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var logs = response.data.logs;
                        if (logs.length > 0) {
                            $console.text(logs.join('\n'));
                            // Scroll to bottom
                            $console.scrollTop($console[0].scrollHeight);
                        } else {
                            $console.html('<p>No logs found.</p>');
                        }
                    } else {
                        $console.html('<p class="error">Error: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $console.html('<p class="error">Server error.</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        // Save Automation Settings
        $('#aips-automation-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=aips_save_automation&nonce=' + aipsAjax.nonce,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Server error.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        // Handle main tab switching to Dashboard if hash matches
        // Standard WP tab switching logic usually requires manual handling if not using standard classes
        var hash = window.location.hash;
        if (hash === '#dashboard') {
             $('.nav-tab[href="#dashboard"]').click();
        }
    });

})(jQuery);
