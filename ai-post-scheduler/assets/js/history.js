/**
 * AIPS History
 *
 * Generation history management: view, filter, export, retry, bulk delete.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(AIPS, {

        clearHistory: function(e) {
            e.preventDefault();
            var status = $(this).data('status');
            var message = status
                ? 'Are you sure you want to clear all ' + status + ' history?'
                : 'Are you sure you want to clear all history?';

            if (!confirm(message)) {
                return;
            }

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_clear_history',
                    nonce: aipsAjax.nonce,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        },

        retryGeneration: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true).text('Retrying...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_retry_generation',
                    nonce: aipsAjax.nonce,
                    history_id: id
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
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Retry');
                }
            });
        },

        filterHistory: function(e) {
            e.preventDefault();
            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();
            var url = new URL(window.location.href);

            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }

            if (search) {
                url.searchParams.set('s', search);
            } else {
                url.searchParams.delete('s');
            }

            url.searchParams.delete('paged');
            url.searchParams.set('tab', 'history');

            window.location.href = url.toString();
        },

        exportHistory: function(e) {
            e.preventDefault();
            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();

            var form = $('<form>', {
                'method': 'POST',
                'action': aipsAjax.ajaxUrl,
                'target': '_self'
            });

            form.append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'aips_export_history' }));
            form.append($('<input>', { 'type': 'hidden', 'name': 'nonce', 'value': aipsAjax.nonce }));

            if (status) {
                form.append($('<input>', { 'type': 'hidden', 'name': 'status', 'value': status }));
            }
            if (search) {
                form.append($('<input>', { 'type': 'hidden', 'name': 'search', 'value': search }));
            }

            form.appendTo('body').submit().remove();
        },

        reloadHistory: function(e) {
            e.preventDefault();

            var status = $('#aips-filter-status').val();
            var search = $('#aips-history-search-input').val();

            var $btn = $(this);
            $btn.prop('disabled', true).text('Reloading...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'aips_reload_history',
                    nonce: aipsAjax.nonce,
                    status: status,
                    search: search
                },
                success: function(response) {
                    if (!response.success) {
                        alert(response.data && response.data.message ? response.data.message : 'Failed to reload history.');
                        return;
                    }

                    var $tbody = $('.aips-history-table tbody');
                    if ($tbody.length) {
                        $tbody.html(response.data.items_html || '');
                    }

                    if (response.data.stats) {
                        $('#aips-stat-total').text(response.data.stats.total);
                        $('#aips-stat-completed').text(response.data.stats.completed);
                        $('#aips-stat-failed').text(response.data.stats.failed);
                        $('#aips-stat-success-rate').text(response.data.stats.success_rate + '%');
                    }

                    $('#cb-select-all-1').prop('checked', false);
                    AIPS.updateDeleteButton();
                },
                error: function() {
                    alert('An error occurred while reloading history.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Reload');
                }
            });
        },

        viewDetails: function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);

            $btn.prop('disabled', true);
            $('#aips-details-loading').show();
            $('#aips-details-content').hide();
            $('#aips-details-modal').show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_history_details',
                    nonce: aipsAjax.nonce,
                    history_id: id
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.renderDetails(response.data);
                    } else {
                        alert(response.data.message);
                        $('#aips-details-modal').hide();
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $('#aips-details-modal').hide();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $('#aips-details-loading').hide();
                }
            });
        },

        renderDetails: function(data) {
            var escHtml = AIPS.utils.escapeHtml;
            var escAttr = AIPS.utils.escapeAttribute;
            var log = data.generation_log || {};

            var summaryHtml = '<table class="aips-details-table">';
            summaryHtml += '<tr><th>Status:</th><td><span class="aips-status aips-status-' + data.status + '">' + data.status.charAt(0).toUpperCase() + data.status.slice(1) + '</span></td></tr>';
            summaryHtml += '<tr><th>Title:</th><td>' + escHtml(data.generated_title || '-') + '</td></tr>';
            if (data.post_id) {
                summaryHtml += '<tr><th>Post ID:</th><td>' + data.post_id + '</td></tr>';
            }
            summaryHtml += '<tr><th>Started:</th><td>' + (log.started_at || data.created_at) + '</td></tr>';
            summaryHtml += '<tr><th>Completed:</th><td>' + (log.completed_at || data.completed_at || '-') + '</td></tr>';
            if (data.error_message) {
                summaryHtml += '<tr><th>Error:</th><td class="aips-error-text">' + escHtml(data.error_message) + '</td></tr>';
            }
            summaryHtml += '</table>';

            if (data.prompt) {
                summaryHtml += '<div class="aips-details-subsection"><h4>Generated Prompt</h4>';
                summaryHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(data.prompt) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                summaryHtml += '<pre class="aips-prompt-text">' + escHtml(data.prompt) + '</pre></div>';
            }

            if (data.generated_content) {
                summaryHtml += '<div class="aips-details-subsection"><h4>Generated Content</h4>';
                summaryHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(data.generated_content) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                summaryHtml += '<pre class="aips-prompt-text" style="max-height: 300px; overflow-y: auto;">' + escHtml(data.generated_content) + '</pre></div>';
            }

            $('#aips-details-summary').html(summaryHtml);

            if (log.template) {
                var templateHtml = '<table class="aips-details-table">';
                templateHtml += '<tr><th>Name:</th><td>' + escHtml(log.template.name || '-') + '</td></tr>';
                templateHtml += '<tr><th>Prompt Template:</th><td>';
                templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(log.template.prompt_template || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                templateHtml += '<pre class="aips-prompt-text">' + escHtml(log.template.prompt_template || '') + '</pre></td></tr>';
                if (log.template.title_prompt) {
                    templateHtml += '<tr><th>Title Prompt:</th><td>';
                    templateHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(log.template.title_prompt) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    templateHtml += '<pre class="aips-prompt-text">' + escHtml(log.template.title_prompt) + '</pre></td></tr>';
                }
                templateHtml += '<tr><th>Post Status:</th><td>' + (log.template.post_status || 'draft') + '</td></tr>';
                templateHtml += '<tr><th>Post Quantity:</th><td>' + (log.template.post_quantity || 1) + '</td></tr>';
                if (log.template.generate_featured_image) {
                    templateHtml += '<tr><th>Image Prompt:</th><td><pre class="aips-prompt-text">' + escHtml(log.template.image_prompt || '') + '</pre></td></tr>';
                }
                templateHtml += '</table>';
                $('#aips-details-template').html(templateHtml);
            } else {
                $('#aips-details-template').html('<p>No template data available.</p>');
            }

            if (log.voice) {
                var voiceHtml = '<table class="aips-details-table">';
                voiceHtml += '<tr><th>Name:</th><td>' + escHtml(log.voice.name || '-') + '</td></tr>';
                voiceHtml += '<tr><th>Title Prompt:</th><td>';
                voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(log.voice.title_prompt || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                voiceHtml += '<pre class="aips-prompt-text">' + escHtml(log.voice.title_prompt || '') + '</pre></td></tr>';
                voiceHtml += '<tr><th>Content Instructions:</th><td>';
                voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(log.voice.content_instructions || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                voiceHtml += '<pre class="aips-prompt-text">' + escHtml(log.voice.content_instructions || '') + '</pre></td></tr>';
                if (log.voice.excerpt_instructions) {
                    voiceHtml += '<tr><th>Excerpt Instructions:</th><td>';
                    voiceHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(log.voice.excerpt_instructions) + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    voiceHtml += '<pre class="aips-prompt-text">' + escHtml(log.voice.excerpt_instructions) + '</pre></td></tr>';
                }
                voiceHtml += '</table>';
                $('#aips-details-voice').html(voiceHtml);
                $('#aips-details-voice-section').show();
            } else {
                $('#aips-details-voice-section').hide();
            }

            if (log.ai_calls && log.ai_calls.length > 0) {
                var callsHtml = '';
                log.ai_calls.forEach(function(call, index) {
                    var statusClass = call.response.success ? 'aips-call-success' : 'aips-call-error';
                    callsHtml += '<div class="aips-ai-call ' + statusClass + '">';
                    callsHtml += '<div class="aips-call-header">';
                    callsHtml += '<strong>Call #' + (index + 1) + ' - ' + call.type.charAt(0).toUpperCase() + call.type.slice(1) + '</strong>';
                    callsHtml += '<span class="aips-call-time">' + call.timestamp + '</span>';
                    callsHtml += '</div>';
                    callsHtml += '<div class="aips-call-section">';
                    callsHtml += '<div class="aips-call-section-header">';
                    callsHtml += '<h4>Request</h4>';
                    callsHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(call.request.prompt || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    callsHtml += '</div>';
                    callsHtml += '<pre class="aips-prompt-text">' + escHtml(call.request.prompt || '') + '</pre>';
                    if (call.request.options && Object.keys(call.request.options).length > 0) {
                        callsHtml += '<p><small>Options: ' + JSON.stringify(call.request.options) + '</small></p>';
                    }
                    callsHtml += '</div>';
                    callsHtml += '<div class="aips-call-section">';
                    callsHtml += '<div class="aips-call-section-header">';
                    callsHtml += '<h4>Response</h4>';
                    if (call.response.success) {
                        callsHtml += '<button class="button button-small aips-copy-btn" data-clipboard-text="' + escAttr(call.response.content || '') + '"><span class="dashicons dashicons-admin-page"></span> Copy</button>';
                    }
                    callsHtml += '</div>';
                    if (call.response.success) {
                        callsHtml += '<pre class="aips-response-text">' + escHtml(call.response.content || '') + '</pre>';
                    } else {
                        callsHtml += '<p class="aips-error-text">Error: ' + escHtml(call.response.error || 'Unknown error') + '</p>';
                    }
                    callsHtml += '</div>';
                    callsHtml += '</div>';
                });
                $('#aips-details-ai-calls').html(callsHtml);
            } else {
                $('#aips-details-ai-calls').html('<p>No AI call data available for this entry.</p>');
            }

            if (log.errors && log.errors.length > 0) {
                var errorsHtml = '<ul class="aips-errors-list">';
                log.errors.forEach(function(error) {
                    errorsHtml += '<li>';
                    errorsHtml += '<strong>' + error.type + '</strong> at ' + error.timestamp + '<br>';
                    errorsHtml += '<span class="aips-error-text">' + escHtml(error.message) + '</span>';
                    errorsHtml += '</li>';
                });
                errorsHtml += '</ul>';
                $('#aips-details-errors').html(errorsHtml);
                $('#aips-details-errors-section').show();
            } else {
                $('#aips-details-errors-section').hide();
            }

            $('#aips-details-content').show();
        },

        // -----------------------------------------------------------
        // Bulk Actions
        // -----------------------------------------------------------

        toggleAllHistory: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-history-table input[name="history[]"]').prop('checked', isChecked);
            AIPS.updateDeleteButton();
        },

        toggleHistorySelection: function() {
            var allChecked = $('.aips-history-table input[name="history[]"]').length ===
                             $('.aips-history-table input[name="history[]"]:checked').length;
            $('#cb-select-all-1').prop('checked', allChecked);
            AIPS.updateDeleteButton();
        },

        updateDeleteButton: function() {
            var count = $('.aips-history-table input[name="history[]"]:checked').length;
            $('#aips-delete-selected-btn').prop('disabled', count === 0);
        },

        deleteSelectedHistory: function(e) {
            e.preventDefault();
            var ids = [];
            $('.aips-history-table input[name="history[]"]:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) return;

            if (!confirm('Are you sure you want to delete ' + ids.length + ' item(s)?')) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_history',
                    nonce: aipsAjax.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text('Delete Selected');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $btn.prop('disabled', false).text('Delete Selected');
                }
            });
        }
    });

    // Bind history events
    $(document).ready(function() {
        $(document).on('click', '.aips-clear-history', AIPS.clearHistory);
        $(document).on('click', '.aips-retry-generation', AIPS.retryGeneration);
        $(document).on('click', '#aips-filter-btn', AIPS.filterHistory);
        $(document).on('click', '#aips-export-history-btn', AIPS.exportHistory);
        $(document).on('click', '#aips-history-search-btn', AIPS.filterHistory);
        $(document).on('click', '#aips-reload-history-btn', AIPS.reloadHistory);
        $(document).on('keypress', '#aips-history-search-input', function(e) {
            if (e.which == 13) {
                AIPS.filterHistory(e);
            }
        });
        $(document).on('click', '.aips-view-details', AIPS.viewDetails);

        // Bulk actions
        $(document).on('change', '#cb-select-all-1', AIPS.toggleAllHistory);
        $(document).on('change', '.aips-history-table input[name="history[]"]', AIPS.toggleHistorySelection);
        $(document).on('click', '#aips-delete-selected-btn', AIPS.deleteSelectedHistory);
    });

})(jQuery);
