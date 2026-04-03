(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;
    AIPS.PostReview = AIPS.PostReview || {};
    var PostReview = AIPS.PostReview;

    Object.assign(PostReview, {
        /**
         * Initialize post review handlers.
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Register delegated event handlers for post review interactions.
         */
        bindEvents: function() {
            $(document).on('change', '#cb-select-all-1', PostReview.toggleAllPostSelection);
            $(document).on('change', '.aips-post-checkbox', PostReview.updateSelectAllPostCheckbox);
            $(document).on('click', '.aips-preview-post, .aips-preview-trigger', PostReview.handlePreviewPostClick);
            $(document).on('click', '.aips-publish-post', PostReview.handlePublishPostClick);
            $(document).on('click', '.aips-delete-post', PostReview.handleDeletePostClick);
            $(document).on('click', '.aips-regenerate-post', PostReview.handleRegeneratePostClick);
            $(document).on('click', '#aips-bulk-action-btn', PostReview.handleBulkActionClick);
            $(document).on('click', '#aips-reload-posts-btn', PostReview.reloadPostReviewPage);
            $(document).on('click', '#aips-post-preview-modal .aips-modal-close, #aips-post-preview-modal .aips-modal-overlay', PostReview.closePreviewModal);
        },

        /**
         * Parse a user-controlled value as a positive integer ID.
         *
         * @param {*} value Raw value from data attributes.
         * @returns {?number} Parsed integer ID or null when invalid.
         */
        sanitizeId: function(value) {
            var parsed = parseInt(value, 10);
            return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
        },

        /**
         * Toggle all row checkboxes from the header checkbox.
         *
         * @param {Event} e Change event.
         */
        toggleAllPostSelection: function(e) {
            var isChecked = $(e.currentTarget).prop('checked');
            $('.aips-post-checkbox').prop('checked', isChecked);
        },

        /**
         * Sync the header checkbox state based on row selections.
         *
         * @param {Event} e Change event.
         */
        updateSelectAllPostCheckbox: function(e) {
            var allChecked = $('.aips-post-checkbox').length === $('.aips-post-checkbox:checked').length;
            $('#cb-select-all-1').prop('checked', allChecked);
        },

        /**
         * Open the preview modal for the selected post.
         *
         * @param {Event} e Click event.
         */
        handlePreviewPostClick: function(e) {
            e.preventDefault();
            var postId = PostReview.sanitizeId($(e.currentTarget).data('post-id'));

            if (!postId) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.previewError || 'Failed to load preview.', 'error');
                return;
            }

            PostReview.previewPost(postId);
        },

        /**
         * Handle publish action for a single draft post.
         *
         * @param {Event} e Click event.
         */
        handlePublishPostClick: function(e) {
            e.preventDefault();

            var button = $(e.currentTarget);
            var postId = PostReview.sanitizeId(button.data('post-id'));
            var row = button.closest('tr');

            if (!postId || row.length === 0) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
                return;
            }

            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmPublish, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    PostReview.publishSinglePost(button, row, postId);
                }}
            ]);
        },

        /**
         * Publish a single draft post via AJAX.
         *
         * @param {jQuery} button Trigger button.
         * @param {jQuery} row Table row for removal on success.
         * @param {number} postId Draft post ID.
         */
        publishSinglePost: function(button, row, postId) {
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
                        PostReview.showPublishSuccessToast(response);
                        PostReview.removeRowAndRefreshState(row);
                        return;
                    }

                    AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.publish || 'Publish');
                }
            });
        },

        /**
         * Show the publish success toast, optionally with an Edit Post link.
         *
         * @param {Object} response AJAX response payload.
         */
        showPublishSuccessToast: function(response) {
            var rawMsg = response.data.message || aipsPostReviewL10n.publishSuccess;
            var safeMsg = $('<div>').text(rawMsg).html();

            if (response.data.post_id) {
                var editUrl = 'post.php?post=' + encodeURIComponent(response.data.post_id) + '&action=edit';
                var safeLink = '<a href="' + editUrl.replace(/"/g, '&quot;') + '" target="_blank">Edit Post</a>';
                AIPS.Utilities.showToast(safeMsg + ' ' + safeLink, 'success', { isHtml: true });
                return;
            }

            AIPS.Utilities.showToast(safeMsg, 'success');
        },

        /**
         * Handle delete action for a single draft post.
         *
         * @param {Event} e Click event.
         */
        handleDeletePostClick: function(e) {
            e.preventDefault();

            var button = $(e.currentTarget);
            var postId = PostReview.sanitizeId(button.data('post-id'));
            var historyId = PostReview.sanitizeId(button.data('history-id'));
            var row = button.closest('tr');

            if (!postId || !historyId || row.length === 0) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
                return;
            }

            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmDelete, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    PostReview.deleteSinglePost(button, row, postId, historyId);
                }}
            ]);
        },

        /**
         * Delete a single draft post via AJAX.
         *
         * @param {jQuery} button Trigger button.
         * @param {jQuery} row Table row for removal on success.
         * @param {number} postId Draft post ID.
         * @param {number} historyId History record ID.
         */
        deleteSinglePost: function(button, row, postId, historyId) {
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
                        PostReview.removeRowAndRefreshState(row);
                        return;
                    }

                    AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.delete || 'Delete');
                }
            });
        },

        /**
         * Handle regenerate action for a single draft post.
         *
         * @param {Event} e Click event.
         */
        handleRegeneratePostClick: function(e) {
            e.preventDefault();

            var button = $(e.currentTarget);
            var historyId = PostReview.sanitizeId(button.data('history-id'));
            var row = button.closest('tr');

            if (!historyId || row.length === 0) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.regenerateError, 'error');
                return;
            }

            AIPS.Utilities.confirm(aipsPostReviewL10n.confirmRegenerate, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, regenerate', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    PostReview.regenerateSinglePost(button, row, historyId);
                }}
            ]);
        },

        /**
         * Trigger regeneration for a post by history ID via AJAX.
         *
         * @param {jQuery} button Trigger button.
         * @param {jQuery} row Table row for removal on success.
         * @param {number} historyId History record ID.
         */
        regenerateSinglePost: function(button, row, historyId) {
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
                        var msg = response.data.message || aipsPostReviewL10n.regenerateSuccess;
                        AIPS.Utilities.showToast(msg + ' Check History for progress.', 'success');
                        PostReview.removeRowAndRefreshState(row);
                        return;
                    }

                    AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.regenerateError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.regenerateError, 'error');
                    button.prop('disabled', false).text(aipsPostReviewL10n.regenerate || 'Re-generate');
                }
            });
        },

        /**
         * Run the selected bulk action for checked rows.
         *
         * @param {Event} e Click event.
         */
        handleBulkActionClick: function(e) {
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
                PostReview.bulkPublish(checkedBoxes);
                return;
            }

            if (action === 'delete') {
                PostReview.bulkDelete(checkedBoxes);
            }
        },

        /**
         * Bulk-publish selected draft posts.
         *
         * @param {jQuery} checkedBoxes Checked row checkboxes.
         */
        bulkPublish: function(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkPublish.replace('%d', count);

            AIPS.Utilities.confirm(confirmMsg, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, publish', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    PostReview.submitBulkPublish(checkedBoxes, count);
                }}
            ]);
        },

        /**
         * Send the bulk publish AJAX request.
         *
         * @param {jQuery} checkedBoxes Checked row checkboxes.
         * @param {number} count Selected row count.
         */
        submitBulkPublish: function(checkedBoxes, count) {
            var postIds = [];

            checkedBoxes.each(function() {
                var postId = PostReview.sanitizeId($(this).data('post-id'));
                if (postId) {
                    postIds.push(postId);
                }
            });

            if (postIds.length === 0) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.noPostsSelected, 'warning');
                return;
            }

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
                        AIPS.Utilities.showToast(msg, 'success');
                        PostReview.removeRowsForCheckedBoxes(checkedBoxes);
                        return;
                    }

                    AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.publishError, 'error');
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.publishError, 'error');
                }
            });
        },

        /**
         * Bulk-delete selected draft posts.
         *
         * @param {jQuery} checkedBoxes Checked row checkboxes.
         */
        bulkDelete: function(checkedBoxes) {
            var count = checkedBoxes.length;
            var confirmMsg = aipsPostReviewL10n.confirmBulkDelete.replace('%d', count);

            AIPS.Utilities.confirm(confirmMsg, 'Notice', [
                { label: 'No, cancel', className: 'aips-btn aips-btn-primary' },
                { label: 'Yes, delete', className: 'aips-btn aips-btn-danger-solid', action: function() {
                    PostReview.submitBulkDelete(checkedBoxes, count);
                }}
            ]);
        },

        /**
         * Send the bulk delete AJAX request.
         *
         * @param {jQuery} checkedBoxes Checked row checkboxes.
         * @param {number} count Selected row count.
         */
        submitBulkDelete: function(checkedBoxes, count) {
            var items = [];

            checkedBoxes.each(function() {
                var postId = PostReview.sanitizeId($(this).data('post-id'));
                var historyId = PostReview.sanitizeId($(this).data('history-id'));

                if (postId && historyId) {
                    items.push({
                        post_id: postId,
                        history_id: historyId
                    });
                }
            });

            if (items.length === 0) {
                AIPS.Utilities.showToast(aipsPostReviewL10n.noPostsSelected, 'warning');
                return;
            }

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
                        AIPS.Utilities.showToast(msg, 'success');
                        PostReview.removeRowsForCheckedBoxes(checkedBoxes);
                        return;
                    }

                    AIPS.Utilities.showToast(response.data.message || aipsPostReviewL10n.deleteError, 'error');
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsPostReviewL10n.deleteError, 'error');
                }
            });
        },

        /**
         * Remove checked rows and refresh count/empty-state.
         *
         * @param {jQuery} checkedBoxes Checked row checkboxes.
         */
        removeRowsForCheckedBoxes: function(checkedBoxes) {
            checkedBoxes.each(function() {
                PostReview.removeRowAndRefreshState($(this).closest('tr'));
            });
        },

        /**
         * Remove a table row and update counts and empty state.
         *
         * @param {jQuery} row Row element to remove.
         */
        removeRowAndRefreshState: function(row) {
            row.fadeOut(400, function() {
                $(this).remove();
                PostReview.updateDraftCount();
                PostReview.checkEmptyState();
            });
        },

        /**
         * Reload the current post review page.
         *
         * @param {Event} e Click event.
         */
        reloadPostReviewPage: function(e) {
            e.preventDefault();
            location.reload();
        },

        /**
         * Refresh the draft count badge from visible rows.
         */
        updateDraftCount: function() {
            var visibleRows = $('.aips-post-review-table tbody tr:visible').length;
            $('#aips-draft-count').text(visibleRows);
        },

        /**
         * Show empty state when there are no visible draft rows.
         */
        checkEmptyState: function() {
            var visibleRows = $('.aips-post-review-table tbody tr:visible').length;

            if (visibleRows !== 0) {
                return;
            }

            $('.aips-post-review-table').hide();
            $('.tablenav').hide();

            if ($('.aips-empty-state').length === 0) {
                $('#aips-post-review-form').after(AIPS.Templates.render('aips-tmpl-post-review-empty-state', {
                    heading: aipsPostReviewL10n.noDraftPosts || 'No Draft Posts',
                    description: aipsPostReviewL10n.noDraftPostsDesc || 'There are no draft posts waiting for review.'
                }));
                return;
            }

            $('.aips-empty-state').show();
        },

        /**
         * Open the post-preview modal and load rendered content.
         *
         * @param {number} postId WordPress post ID.
         */
        previewPost: function(postId) {
            var modal = $('#aips-post-preview-modal');
            var contentContainer = $('#aips-preview-content-container');
            var iframe = $('#aips-post-preview-iframe');
            var headerTitle = modal.find('.aips-modal-header h2');

            contentContainer.show().html(AIPS.Templates.render('aips-tmpl-post-review-loading', {
                text: aipsPostReviewL10n.loadingPreview || 'Loading preview...'
            }));
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
                        PostReview.renderPreviewResponse(response.data);
                        return;
                    }

                    $('#aips-preview-content-container').html(AIPS.Templates.render('aips-tmpl-post-review-error', {
                        message: response.data.message || aipsPostReviewL10n.previewError
                    }));
                },
                error: function() {
                    $('#aips-preview-content-container').html(AIPS.Templates.render('aips-tmpl-post-review-error', {
                        message: aipsPostReviewL10n.previewError || 'Failed to load preview.'
                    }));
                }
            });
        },

        /**
         * Render preview payload content into the preview container.
         *
         * @param {Object} data Preview payload.
         */
        renderPreviewResponse: function(data) {
            var html = '';

            html += AIPS.Templates.render('aips-tmpl-post-review-preview-title', { title: data.title });

            if (data.featured_image) {
                html += AIPS.Templates.renderRaw('aips-tmpl-post-review-preview-image', {
                    src: AIPS.Templates.escape(data.featured_image)
                });
            }

            if (data.excerpt) {
                html += AIPS.Templates.renderRaw('aips-tmpl-post-review-preview-excerpt', {
                    excerpt: data.excerpt
                });
            }

            html += AIPS.Templates.renderRaw('aips-tmpl-post-review-preview-body', { content: data.content });

            if (data.edit_url) {
                html += AIPS.Templates.renderRaw('aips-tmpl-post-review-preview-edit-link', {
                    url: AIPS.Templates.escape(data.edit_url),
                    label: AIPS.Templates.escape('Edit Post in WordPress')
                });
            }

            $('#aips-preview-content-container').html(html);
        },

        /**
         * Close the preview modal and clear iframe content.
         *
         * @param {Event} e Click event.
         */
        closePreviewModal: function(e) {
            e.preventDefault();
            $('#aips-post-preview-modal').hide();
            $('#aips-post-preview-iframe').attr('src', '');
        }
    });

    $(document).ready(function() {
        PostReview.init();
    });

})(jQuery);
