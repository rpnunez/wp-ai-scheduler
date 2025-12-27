(function($) {
    'use strict';

    // Ensure AIPS namespace exists
    window.AIPS = window.AIPS || {};

    // Add Dashboard functionality under AIPS via Object.assign
    Object.assign(window.AIPS, {
        Dashboard: {
            refreshIntervalId: null,

            // init now only for any future initialization (no DOM bindings here)
            init: function() {
                // reserved for future use
            },

            updateUI: function(data) {
                var stats = data.stats;
                // Update stats grid
                $('.aips-stat-card:eq(0) .aips-stat-number').text(stats.total);
                $('.aips-stat-card:eq(1) .aips-stat-number').text(stats.success_rate + '%');
                $('.aips-stat-card:eq(2) .aips-stat-number').text(stats.failed);
                $('.aips-stat-card:eq(3) .aips-stat-number').text(stats.processing);

                // Rebuild Suggestions
                var $suggestionsContainer = $('.aips-suggestions-container');
                if ($suggestionsContainer.length === 0 && data.suggestions.length > 0) {
                    // Insert container if missing but suggestions exist
                    $suggestionsContainer = $('<div class="aips-suggestions-container" style="margin-bottom: 20px;"></div>');
                    $('.aips-header-actions').after($suggestionsContainer);
                }

                if ($suggestionsContainer.length > 0) {
                    $suggestionsContainer.empty();
                    if (data.suggestions.length > 0) {
                        $.each(data.suggestions, function(i, suggestion) {
                            var allowedTypes = ['success', 'error', 'warning', 'info', 'updated'];
                            var type = (suggestion.type || '').toString().toLowerCase();
                            if ($.inArray(type, allowedTypes) === -1) {
                                type = 'info';
                            }

                            var $notice = $('<div></div>')
                                .addClass('notice')
                                .addClass('notice-' + type)
                                .addClass('inline')
                                .attr('style', 'margin: 5px 0 15px 0;');

                            var $message = $('<p></p>').text((suggestion.message || '').toString());
                            $notice.append($message);

                            $suggestionsContainer.append($notice);
                        });
                    } else {
                        $suggestionsContainer.remove();
                    }
                }

                // Rebuild Template Performance Table
                var $tbody = $('.aips-card table tbody');
                $tbody.empty();
                if (data.template_performance.length > 0) {
                    $.each(data.template_performance, function(i, temp) {
                        var barClass = (temp.success_rate < 50) ? 'low' : ((temp.success_rate < 80) ? 'medium' : 'high');
                        var html = '<tr>' +
                            '<td>' + temp.name + '</td>' +
                            '<td>' + temp.total + '</td>' +
                            '<td>' + temp.completed + '</td>' +
                            '<td>' + temp.success_rate + '%</td>' +
                            '<td style="width: 200px;">' +
                                '<div class="aips-progress-bar">' +
                                    '<div class="aips-progress-fill ' + barClass + '" style="width: ' + temp.success_rate + '%;"></div>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                        $tbody.append(html);
                    });
                } else {
                    $tbody.append('<tr><td colspan="5">No template data available yet.</td></tr>');
                }
            },

            fetchStats: function(force) {
                var self = this;
                var $btn = $('#aips-refresh-stats');
                if (force) {
                    $btn.prop('disabled', true).find('.dashicons').addClass('spin');
                }

                $.ajax({
                    url: aipsAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aips_refresh_stats',
                        nonce: aipsAjax.nonce,
                        force_refresh: force ? 'true' : 'false'
                    },
                    success: function(response) {
                        if (response.success) {
                            self.updateUI(response.data.data);
                        } else if (force) {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    complete: function() {
                        if (force) {
                            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
                        }
                    }
                });
            },

            startAutoRefresh: function() {
                var self = this;
                var interval = parseInt($('#aips-refresh-interval').val(), 10) || 5000;
                this.refreshIntervalId = setInterval(function() {
                    self.fetchStats(false);
                }, interval);
            },

            stopAutoRefresh: function() {
                if (this.refreshIntervalId) {
                    clearInterval(this.refreshIntervalId);
                    this.refreshIntervalId = null;
                }
            }
        }
    });

    // Bind Dashboard Events on DOM ready (pattern matches other admin*.js files)
    $(document).ready(function() {
        // Initialize any internal Dashboard setup
        if (window.AIPS && window.AIPS.Dashboard && typeof window.AIPS.Dashboard.init === 'function') {
            window.AIPS.Dashboard.init();
        }

        // Inner Tab Switching
        $(document).on('click', '.aips-inner-tab', function(e) {
            e.preventDefault();
            var target = $(this).data('target');

            $('.aips-inner-tab').removeClass('active');
            $(this).addClass('active');

            $('.aips-inner-content').hide();
            $('#aips-dashboard-' + target).show();
        });

        // Refresh Stats Button
        $(document).on('click', '#aips-refresh-stats', function(e) {
            e.preventDefault();
            window.AIPS.Dashboard.fetchStats(true);
        });

        // Real-Time Mode Toggle
        $(document).on('change', '#aips-realtime-toggle', function() {
            var enabled = $(this).is(':checked');
            var $intervalSelect = $('#aips-refresh-interval');

            if (enabled) {
                $intervalSelect.show();
                window.AIPS.Dashboard.startAutoRefresh();
            } else {
                $intervalSelect.hide();
                window.AIPS.Dashboard.stopAutoRefresh();
            }
        });

        // Interval Change
        $(document).on('change', '#aips-refresh-interval', function() {
            if ($('#aips-realtime-toggle').is(':checked')) {
                window.AIPS.Dashboard.stopAutoRefresh();
                window.AIPS.Dashboard.startAutoRefresh();
            }
        });

        // Fetch Logs
        $(document).on('click', '#aips-fetch-logs', function(e) {
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
        $(document).on('submit', '#aips-automation-form', function(e) {
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
                    var defaultSuccessMessage = 'Settings saved successfully.';
                    var defaultErrorMessage = 'An error occurred while saving settings.';
                    var hasDataMessage = response && response.data && typeof response.data.message === 'string' && response.data.message.length > 0;

                    if (response && response.success) {
                        alert(hasDataMessage ? response.data.message : defaultSuccessMessage);
                    } else {
                        alert('Error: ' + (hasDataMessage ? response.data.message : defaultErrorMessage));
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
        var hash = window.location.hash;
        if (hash === '#dashboard') {
            $('.nav-tab[href="#dashboard"]').click();
        }
    });

})(jQuery);
