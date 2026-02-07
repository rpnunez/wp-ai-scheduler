(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Select all checkbox functionality
        $('#cb-select-all-1').on('change', function() {
            $('.aips-post-checkbox').prop('checked', $(this).prop('checked'));
        });
        
        // Update select all checkbox when individual checkboxes change
        $('.aips-post-checkbox').on('change', function() {
            var allChecked = $('.aips-post-checkbox').length === $('.aips-post-checkbox:checked').length;
            $('#cb-select-all-1').prop('checked', allChecked);
        });
        
        // Publish single post
        $(document).on('click', '.aips-publish-post', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            var row = $(this).closest('tr');
            
            if (!confirm(aipsPostReviewL10n.confirmPublish)) {
                return;
            }
            
            var button = $(this);
            button.prop('disabled', true).text(aipsPostReviewL10n.loading || 'Publishing...');
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_publish_post',
                    post_id: postId,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || aipsPostReviewL10n.publishSuccess, 'success');
                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        showNotice(response.data.message || aipsPostReviewL10n.publishError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                    }
                },
                error: function() {
                    showNotice(aipsPostReviewL10n.publishError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                }
            });
        });
        
        // Delete single post
        $(document).on('click', '.aips-delete-post', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            var historyId = $(this).data('history-id');
            var row = $(this).closest('tr');
            
            if (!confirm(aipsPostReviewL10n.confirmDelete)) {
                return;
            }
            
            var button = $(this);
            button.prop('disabled', true).text(aipsPostReviewL10n.deleting || 'Deleting...');
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_delete_draft_post',
                    post_id: postId,
                    history_id: historyId,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || aipsPostReviewL10n.deleteSuccess, 'success');
                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        showNotice(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                    }
                },
                error: function() {
                    showNotice(aipsPostReviewL10n.deleteError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                }
            });
        });
        
        // Regenerate post
        $(document).on('click', '.aips-regenerate-post', function(e) {
            e.preventDefault();
            var historyId = $(this).data('history-id');
            var row = $(this).closest('tr');
            
            if (!confirm(aipsPostReviewL10n.confirmRegenerate)) {
                return;
            }
            
            var button = $(this);
            button.prop('disabled', true).text(aipsPostReviewL10n.regenerating || 'Regenerating...');
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_regenerate_post',
                    history_id: historyId,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message || aipsPostReviewL10n.regenerateSuccess, 'success');
                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        showNotice(response.data.message || aipsPostReviewL10n.regenerateError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                    }
                },
                error: function() {
                    showNotice(aipsPostReviewL10n.regenerateError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                }
            });
        });
        
        // View Session - same as Generated Posts functionality
        $(document).on('click', '.aips-view-session', function(e) {
            e.preventDefault();
            var historyId = $(this).data('history-id');
            
            if (!historyId) {
                console.error('No history ID provided');
                return;
            }
            
            loadSessionData(historyId);
        });
        
        // Copy Session JSON button handler
        $(document).on('click', '.aips-copy-session-json', function(e) {
            e.preventDefault();
            handleCopySessionJSON($(this));
        });
        
        // Download Session JSON button handler
        $(document).on('click', '.aips-download-session-json', function(e) {
            e.preventDefault();
            handleDownloadSessionJSON($(this));
        });
        
        // Close modal handlers
        $(document).on('click', '.aips-modal-close, .aips-modal-overlay', function() {
            closeModal();
        });
        
        // Tab navigation
        $(document).on('click', '.aips-tab-nav a', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            $('.aips-tab-nav a').removeClass('active');
            $(this).addClass('active');
            
            $('.aips-tab-content').hide();
            $(target).show();
        });
        
        // Toggle AI component details
        $(document).on('click', '.aips-ai-component:not(.expanded)', function() {
            $(this).addClass('expanded');
        });
        
        // ESC key to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#aips-session-modal').is(':visible')) {
                closeModal();
            }
        });
        
        // Bulk actions
        $('#aips-bulk-action-btn').on('click', function(e) {
            e.preventDefault();
            
            var action = $('#bulk-action-selector-top').val();
            if (!action) {
                return;
            }
            
            var checkedBoxes = $('.aips-post-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert(aipsPostReviewL10n.noPostsSelected);
                return;
            }
            
            if (action === 'publish') {
                bulkPublish(checkedBoxes);
            } else if (action === 'delete') {
                bulkDelete(checkedBoxes);
            }
        });
        
        // Bulk publish
        function bulkPublish(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkPublish.replace('%d', count);
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            var postIds = [];
            checkedBoxes.each(function() {
                postIds.push($(this).data('post-id'));
            });
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_publish_posts',
                    post_ids: postIds,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = aipsPostReviewL10n.bulkPublishSuccess.replace('%d', response.data.count || count);
                        showNotice(msg, 'success');
                        
                        checkedBoxes.each(function() {
                            $(this).closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                updateDraftCount();
                                checkEmptyState();
                            });
                        });
                    } else {
                        showNotice(response.data.message || aipsPostReviewL10n.publishError, 'error');
                    }
                },
                error: function() {
                    showNotice(aipsPostReviewL10n.publishError, 'error');
                }
            });
        }
        
        // Bulk delete
        function bulkDelete(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkDelete.replace('%d', count);
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            var items = [];
            checkedBoxes.each(function() {
                items.push({
                    post_id: $(this).data('post-id'),
                    history_id: $(this).data('history-id')
                });
            });
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_draft_posts',
                    items: items,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = aipsPostReviewL10n.bulkDeleteSuccess.replace('%d', response.data.count || count);
                        showNotice(msg, 'success');
                        
                        checkedBoxes.each(function() {
                            $(this).closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                updateDraftCount();
                                checkEmptyState();
                            });
                        });
                    } else {
                        showNotice(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                    }
                },
                error: function() {
                    showNotice(aipsPostReviewL10n.deleteError, 'error');
                }
            });
        }
        
        // Reload posts
        $('#aips-reload-posts-btn').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
        
        // Update draft count
        function updateDraftCount() {
            var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
            
            $('#aips-draft-count').text(visibleRows);
        }
        
        // Check if table is empty and show empty state
        function checkEmptyState() {
            var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
            
            if (visibleRows === 0) {
                $('.aips-post-review-table').hide();
                $('.tablenav').hide();
                
                if ($('.aips-empty-state').length === 0) {
                    var emptyStateHtml = '<div class="aips-empty-state">' +
                        '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
                        '<h3>' + (aipsPostReviewL10n.noDraftPosts || 'No Draft Posts') + '</h3>' +
                        '<p>' + (aipsPostReviewL10n.noDraftPostsDesc || 'There are no draft posts waiting for review.') + '</p>' +
                        '</div>';
                    $('#aips-post-review-form').after(emptyStateHtml);
                }
            }
        }
        
        // Show notice
        function showNotice(message, type) {
            type = type || 'info';
            
            var noticeClass = 'notice-' + type;
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"></div>');
            var paragraph = $('<p></p>').text(message);
            notice.append(paragraph);
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(400, function() {
                    $(this).remove();
                });
            });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            if (text === null || text === undefined) {
                return '';
            }
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Session modal functions
        var currentHistoryId = null;
        var currentLogCount = 0;
        
        /**
         * Load session data via AJAX
         */
        function loadSessionData(historyId) {
            // Show loading state
            showLoadingModal();
            
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_post_session',
                    nonce: window.aipsAjaxNonce || aipsPostReviewL10n.nonce,
                    history_id: historyId
                },
                success: function(response) {
                    if (response.success) {
                        displaySessionModal(response.data);
                    } else {
                        showError(response.data.message || 'Failed to load session data.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    showError('Failed to load session data. Please try again.');
                }
            });
        }
        
        /**
         * Show loading state in modal
         */
        function showLoadingModal() {
            $('#aips-session-modal').show();
            $('#aips-session-title').text('Loading...');
            $('#aips-session-created').text('');
            $('#aips-session-completed').text('');
            $('#aips-logs-list').html('<p>Loading logs...</p>');
            $('#aips-ai-list').html('<p>Loading AI calls...</p>');
        }
        
        /**
         * Display session data in modal
         */
        function displaySessionModal(data) {
            // Store current history ID for JSON export
            currentHistoryId = data.history.id;
            currentLogCount = Array.isArray(data.logs) ? data.logs.length : 0;

            // Update session info
            $('#aips-session-title').text(data.history.generated_title || 'N/A');
            $('#aips-session-created').text(data.history.created_at || 'N/A');
            $('#aips-session-completed').text(data.history.completed_at || 'N/A');
            
            // Display logs
            renderLogs(data.logs);
            
            // Display AI calls
            renderAICalls(data.ai_calls);
            
            // Show modal
            $('#aips-session-modal').show();
        }
        
        /**
         * Render logs tab content
         */
        function renderLogs(logs) {
            var logsHtml = '';
            
            if (logs.length > 0) {
                logs.forEach(function(log) {
                    var cssClass = '';
                    if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.ERROR) {
                        cssClass = 'error';
                    } else if (window.AIPS_History_Type && log.type_id === window.AIPS_History_Type.WARNING) {
                        cssClass = 'warning';
                    }
                    
                    logsHtml += '<div class="aips-log-entry ' + escapeHtml(cssClass) + '">';
                    logsHtml += '<h4>' + escapeHtml(log.type) + ' - ' + escapeHtml(log.log_type) + '</h4>';
                    logsHtml += '<div class="aips-log-timestamp">' + escapeHtml(log.timestamp) + '</div>';
                    logsHtml += '<div class="aips-json-viewer"><pre>' + escapeHtml(JSON.stringify(log.details, null, 2)) + '</pre></div>';
                    logsHtml += '</div>';
                });
            } else {
                logsHtml = '<p class="aips-no-data">No log entries found.</p>';
            }
            
            $('#aips-logs-list').html(logsHtml);
        }
        
        /**
         * Render AI calls tab content
         */
        function renderAICalls(ai_calls) {
            var aiHtml = '';
            
            if (ai_calls.length > 0) {
                ai_calls.forEach(function(call) {
                    aiHtml += '<div class="aips-ai-component" data-component="' + escapeHtml(call.type) + '">';
                    aiHtml += '<h4>' + escapeHtml(call.label) + '</h4>';
                    aiHtml += '<p class="aips-ai-hint">Click to view request and response details</p>';
                    aiHtml += '<div class="aips-ai-details">';
                    
                    if (call.request) {
                        aiHtml += '<div class="aips-ai-section">';
                        aiHtml += '<h5>Request</h5>';
                        aiHtml += '<div class="aips-json-viewer"><pre>' + escapeHtml(JSON.stringify(call.request, null, 2)) + '</pre></div>';
                        aiHtml += '</div>';
                    }
                    
                    if (call.response) {
                        aiHtml += '<div class="aips-ai-section">';
                        aiHtml += '<h5>Response</h5>';
                        aiHtml += '<div class="aips-json-viewer"><pre>' + escapeHtml(JSON.stringify(call.response, null, 2)) + '</pre></div>';
                        aiHtml += '</div>';
                    }
                    
                    aiHtml += '</div></div>';
                });
            } else {
                aiHtml = '<p class="aips-no-data">No AI calls found.</p>';
            }
            
            $('#aips-ai-list').html(aiHtml);
        }
        
        /**
         * Handle Copy Session JSON button click
         */
        function handleCopySessionJSON($button) {
            // Check if we have a history ID stored
            if (!currentHistoryId) {
                showModalNotification('No session data available.', 'error');
                return;
            }
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Loading...');
            
            // Fetch the JSON data
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_session_json',
                    nonce: window.aipsAjaxNonce || aipsPostReviewL10n.nonce,
                    history_id: currentHistoryId
                },
                success: function(response) {
                    if (response.success && response.data.json) {
                        // Copy to clipboard
                        copyToClipboard(response.data.json, $button);
                    } else {
                        showModalNotification(response.data.message || 'Failed to generate JSON.', 'error');
                        $button.prop('disabled', false).text('Copy Session JSON');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    showModalNotification('Failed to load session JSON. Please try again.', 'error');
                    $button.prop('disabled', false).text('Copy Session JSON');
                }
            });
        }
        
        /**
         * Handle Download Session JSON button click
         */
        function handleDownloadSessionJSON($button) {
            // Check if we have a history ID stored
            if (!currentHistoryId) {
                showModalNotification('No session data available for download.', 'error');
                return;
            }

            var CLIENT_LOG_THRESHOLD = 20;

            if (typeof currentLogCount === 'number' && currentLogCount <= CLIENT_LOG_THRESHOLD) {
                // Small session: fetch the JSON via AJAX and trigger client-side download
                $button.prop('disabled', true).text('Preparing download...');
                $.ajax({
                    url: aipsPostReviewL10n.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aips_get_session_json',
                        nonce: window.aipsAjaxNonce || aipsPostReviewL10n.nonce,
                        history_id: currentHistoryId
                    },
                    success: function(response) {
                        if (response.success && response.data.json) {
                            var filename = 'aips-session-' + currentHistoryId + '.json';
                            downloadJSON(response.data.json, filename);
                            $button.prop('disabled', false).text('Download Session JSON');
                            showNotice('Session JSON download started. Check your browser downloads.', 'success');
                        } else {
                            showModalNotification(response.data.message || 'Failed to generate JSON for download.', 'error');
                            $button.prop('disabled', false).text('Download Session JSON');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        showModalNotification('Failed to load session JSON for download. Please try again.', 'error');
                        $button.prop('disabled', false).text('Download Session JSON');
                    }
                });
                return;
            }

            // Large session: use a form POST to the download endpoint
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = aipsPostReviewL10n.ajaxUrl;
            form.target = '_blank';

            var inputAction = document.createElement('input');
            inputAction.type = 'hidden';
            inputAction.name = 'action';
            inputAction.value = 'aips_download_session_json';
            form.appendChild(inputAction);

            var inputNonce = document.createElement('input');
            inputNonce.type = 'hidden';
            inputNonce.name = 'nonce';
            inputNonce.value = window.aipsAjaxNonce || aipsPostReviewL10n.nonce;
            form.appendChild(inputNonce);

            var inputHistory = document.createElement('input');
            inputHistory.type = 'hidden';
            inputHistory.name = 'history_id';
            inputHistory.value = currentHistoryId;
            form.appendChild(inputHistory);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            $button.prop('disabled', false).text('Download Session JSON');
            showNotice('Session JSON download started. Check your browser downloads.', 'success');
        }
        
        /**
         * Download JSON data as a file
         */
        function downloadJSON(jsonData, fileName) {
            var blob = new Blob([jsonData], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
        
        /**
         * Copy text to clipboard
         */
        function copyToClipboard(text, $button) {
            // Try modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showModalNotification('Session JSON copied to clipboard!', 'success');
                    $button.prop('disabled', false).text('Copy Session JSON');
                }).catch(function(err) {
                    console.error('Failed to copy:', err);
                    fallbackCopyToClipboard(text, $button);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyToClipboard(text, $button);
            }
        }
        
        /**
         * Fallback clipboard copy method for older browsers
         */
        function fallbackCopyToClipboard(text, $button) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    showModalNotification('Session JSON copied to clipboard!', 'success');
                } else {
                    showModalNotification('Failed to copy to clipboard.', 'error');
                }
            } catch (err) {
                console.error('Fallback copy failed:', err);
                showModalNotification('Failed to copy to clipboard.', 'error');
            }
            
            $temp.remove();
            $button.prop('disabled', false).text('Copy Session JSON');
        }
        
        /**
         * Show notification message in modal
         */
        function showModalNotification(message, type) {
            // Remove existing notifications
            $('.aips-notification').remove();
            
            // Create notification element
            var $notification = $('<div class="aips-notification aips-notification-' + type + '">')
                .text(message)
                .appendTo('.aips-modal-body');
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
        
        /**
         * Close the modal
         */
        function closeModal() {
            $('#aips-session-modal').hide();
            currentHistoryId = null;
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            alert(message);
            closeModal();
        }
    });

})(jQuery);
