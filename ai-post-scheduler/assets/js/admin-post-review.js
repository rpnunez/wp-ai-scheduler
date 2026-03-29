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

        // Preview Post (Button and Icon)
        $(document).on('click', '.aips-preview-post, .aips-preview-trigger', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            previewPost(postId);
        });

        // Publish single post
        $(document).on('click', '.aips-publish-post', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            var row = $(this).closest('tr');

            var button = $(this);
            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmPublish, 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        var rawMsg = response.data.message || aipsPostReviewL10n.publishSuccess;
                        var safeMsg = $('<div>').text(rawMsg).html();
                        if (response.data.post_id) {
                            var editUrl = 'post.php?post=' + encodeURIComponent(response.data.post_id) + '&action=edit';
                            var safeLink = '<a href="' + editUrl.replace(/"/g, '&quot;') + '" target="_blank">Edit Post</a>';
                            AIPS.Utilities.showToast(safeMsg + ' ' + safeLink, 'success', { isHtml: true });
                        } else {
                            AIPS.Utilities.showToast(safeMsg, 'success');
                        }

                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                }
            });
                }}
            ]);
        });

        // Delete single post
        $(document).on('click', '.aips-delete-post', function(e) {
            e.preventDefault();
            var postId = $(this).data('post-id');
            var historyId = $(this).data('history-id');
            var row = $(this).closest('tr');

            var button = $(this);
            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmDelete, 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteSuccess, 'success');

                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                }
            });
                }}
            ]);
        });

        // Regenerate post
        $(document).on('click', '.aips-regenerate-post', function(e) {
            e.preventDefault();
            var historyId = $(this).data('history-id');
            var row = $(this).closest('tr');

            var button = $(this);
            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmRegenerate, 'Notice', [
                { label: 'No, cancel',     className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, regenerate', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        // Show generic "Started" message instead of "Success"
                        var msg = response.data.message || aipsPostReviewL10n.regenerateSuccess;
                        AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');

                        // We still remove the row because the post is deleted
                        row.fadeOut(400, function() {
                            $(this).remove();
                            updateDraftCount();
                            checkEmptyState();
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.regenerateError, 'error');
                        button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.regenerateError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                }
            });
                }}
            ]);
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
                AIPS.Utilities.showToast(aipsPostReviewL10n.noPostsSelected, 'warning');
                return;
            }

            if (action === 'publish') {
                bulkPublish(checkedBoxes);
            } else if (action === 'delete') {
                bulkDelete(checkedBoxes);
            }
        });

        // Bulk publish
        /**
         * Bulk-publish the selected draft posts via `aips_bulk_publish_posts`.
         *
         * Shows a confirmation dialog with the post count. On confirmation,
         * collects the post IDs from the checked boxes and sends them to the
         * server. Fades out each published row and refreshes the draft count and
         * empty-state check on success.
         *
         * @param {jQuery} checkedBoxes - The set of checked `.aips-post-checkbox` elements.
         */
        function bulkPublish(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkPublish.replace('%d', count);

            AIPS.Utilities.confirm(confirmMsg, 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.bulkPublishSuccess.replace('%d', response.data.success_count || count), 'success');

                        checkedBoxes.each(function() {
                            $(this).closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                updateDraftCount();
                                checkEmptyState();
                            });
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
                }
            });
                }}
            ]);
        }

        // Bulk delete
        /**
         * Bulk-delete the selected draft posts via `aips_bulk_delete_draft_posts`.
         *
         * Shows a confirmation dialog with the post count. On confirmation,
         * builds an array of `{post_id, history_id}` objects and sends them to
         * the server. Fades out each deleted row and refreshes the draft count and
         * empty-state check on success.
         *
         * @param {jQuery} checkedBoxes - The set of checked `.aips-post-checkbox` elements.
         */
        function bulkDelete(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkDelete.replace('%d', count);

            AIPS.Utilities.confirm(confirmMsg, 'Notice', [
                { label: 'No, cancel',  className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
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
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.bulkDeleteSuccess.replace('%d', response.data.success_count || count), 'success');

                        checkedBoxes.each(function() {
                            $(this).closest('tr').fadeOut(400, function() {
                                $(this).remove();
                                updateDraftCount();
                                checkEmptyState();
                            });
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
                }
            });
                }}
            ]);
        }

        // Reload posts
        $('#aips-reload-posts-btn').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });

        // Update draft count
        /**
         * Refresh the `#aips-draft-count` badge with the number of currently
         * visible table rows.
         *
         * Called after any row is removed (published, deleted, or regenerated)
         * so the count stays accurate without a full page reload.
         */
        function updateDraftCount() {
            var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
            $('#aips-draft-count').text(visibleRows);
        }

        // Check if table is empty and show empty state
        /**
         * Show or hide the empty-state placeholder based on whether any table
         * rows remain visible.
         *
         * When all rows have been removed, hides the table and pagination
         * controls and injects (or reveals) an `.aips-empty-state` element with
         * a friendly "no draft posts" message.
         */
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
                } else {
                    $('.aips-empty-state').show();
                }
            }
        }

        // Preview Post Function
        /**
         * Open the post-preview modal and load the rendered post content.
         *
         * Resets the modal to a loading state, then sends the
         * `aips_get_draft_post_preview` AJAX action. On success, builds and
         * injects an HTML preview (title, featured image, excerpt, body content,
         * and an optional edit link) into `#aips-preview-content-container`.
         *
         * @param {number} postId - The WordPress post ID to preview.
         */
        function previewPost(postId) {
            var modal = $('#aips-post-preview-modal');
            var contentContainer = $('#aips-preview-content-container');
            var iframe = $('#aips-post-preview-iframe');
            var headerTitle = modal.find('.aips-modal-header h2');

            // Reset modal state
            contentContainer.show().html('<div class="aips-loading-spinner"><span class="spinner is-active" style="float:none; margin: 0 auto; display:block;"></span> <p style="text-align:center;">' + (aipsPostReviewL10n.loadingPreview || 'Loading preview...') + '</p></div>');
            iframe.hide().attr('src', '');
            headerTitle.text(aipsPostReviewL10n.previewTitle || 'Post Preview');

            modal.show();

            $.ajax({
                url: aipsPostReviewL10n.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_draft_post_preview',
                    post_id: postId,
                    nonce: aipsPostReviewL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '';

                        // Title
                        html += '<h1 style="margin-bottom: 20px;">' + data.title + '</h1>';

                        // Featured Image
                        if (data.featured_image) {
                            html += '<div class="aips-preview-image" style="margin-bottom: 20px;">';
                            html += '<img src="' + data.featured_image + '" style="max-width: 100%; height: auto; border-radius: 4px;">';
                            html += '</div>';
                        }

                        // Excerpt
                        if (data.excerpt) {
                            html += '<div class="aips-preview-excerpt" style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; border-left: 4px solid #72aee6;">';
                            html += '<strong>Excerpt:</strong> ' + data.excerpt;
                            html += '</div>';
                        }

                        // Content
                        html += '<div class="aips-preview-body">' + data.content + '</div>';

                        // Edit Link at bottom
                        if (data.edit_url) {
                            html += '<div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;">';
                            html += '<a href="' + data.edit_url + '" target="_blank" class="button button-primary">Edit Post in WordPress</a>';
                            html += '</div>';
                        }

                        contentContainer.html(html);
                    } else {
                        contentContainer.html('<div class="notice notice-error inline"><p>' + (response.data.message || aipsPostReviewL10n.previewError) + '</p></div>');
                    }
                },
                error: function() {
                    contentContainer.html('<div class="notice notice-error inline"><p>' + (aipsPostReviewL10n.previewError || 'Failed to load preview.') + '</p></div>');
                }
            });
        }

        // Close modal handlers (using delegated events to handle dynamically added modals if any)
        $(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', function() {
            $('#aips-post-preview-modal').hide();
            $('#aips-post-preview-iframe').attr('src', '');
        });

    });

})(jQuery);
