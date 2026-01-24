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
        
        // View logs
        $(document).on('click', '.aips-view-logs', function(e) {
            e.preventDefault();
            var historyId = $(this).data('history-id');
            
            // Show modal
            $('#aips-log-viewer-modal').fadeIn();
            $('#aips-log-viewer-content').html('<p>' + (aipsPostReviewL10n.loading || 'Loading...') + '</p>');
            
            // Fetch log details
            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_history_details',
                    history_id: historyId,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var html = '<div class="aips-log-details">';
                        
                        if (response.data.prompt) {
                            html += '<h3>' + (aipsPostReviewL10n.prompt || 'Prompt') + '</h3>';
                            html += '<pre>' + escapeHtml(response.data.prompt) + '</pre>';
                        }
                        
                        if (response.data.generation_log) {
                            html += '<h3>' + (aipsPostReviewL10n.generationLog || 'Generation Log') + '</h3>';
                            html += '<pre>' + escapeHtml(response.data.generation_log) + '</pre>';
                        }
                        
                        if (response.data.error_message) {
                            html += '<h3>' + (aipsPostReviewL10n.errorMessage || 'Error Message') + '</h3>';
                            html += '<div class="notice notice-error"><p>' + escapeHtml(response.data.error_message) + '</p></div>';
                        }
                        
                        html += '</div>';
                        $('#aips-log-viewer-content').html(html);
                    } else {
                        $('#aips-log-viewer-content').html('<p>' + (aipsPostReviewL10n.loadingError || 'Failed to load logs.') + '</p>');
                    }
                },
                error: function() {
                    $('#aips-log-viewer-content').html('<p>' + (aipsPostReviewL10n.loadingError || 'Failed to load logs.') + '</p>');
                }
            });
        });
        
        // Close modal
        $('.aips-modal-close').on('click', function() {
            $('#aips-log-viewer-modal').fadeOut();
        });
        
        // Close modal on outside click
        $(document).on('click', function(e) {
            if ($(e.target).is('#aips-log-viewer-modal')) {
                $('#aips-log-viewer-modal').fadeOut();
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
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });

})(jQuery);
