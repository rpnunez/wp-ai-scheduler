/**
 * Internal Links Admin Page — JavaScript Module
 *
 * Follows the AIPS module pattern established in admin.js and admin-research.js:
 * - IIFE receiving jQuery
 * - 'use strict'
 * - window.AIPS namespace
 * - Object.assign on AIPS
 * - init() → bindEvents()
 * - Delegated listeners on document
 *
 * @package AI_Post_Scheduler
 * @since 2.2.0
 */

(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    Object.assign(AIPS, {

        /**
         * Selected post data for the Generate tab.
         * @type {{ id: number, title: string, excerpt: string }|null}
         */
        ilSelectedPost: null,

        /**
         * Related posts returned by the last find_related_posts call.
         * @type {Array}
         */
        ilRelatedPosts: [],

        /**
         * AI-generated preview content (HTML string).
         * @type {string}
         */
        ilPreviewContent: '',

        /**
         * Polling timer reference for bulk indexing progress.
         * @type {number|null}
         */
        ilPollingTimer: null,

        /**
         * Current page in the index table.
         * @type {number}
         */
        ilCurrentPage: 1,

        // ===================================================================
        // Initialisation
        // ===================================================================

        /**
         * Bootstrap the Internal Links admin module.
         */
        initInternalLinks: function() {
            AIPS.bindInternalLinksEvents();
            AIPS.loadIndexTable(1);
        },

        /**
         * Register all delegated event listeners.
         */
        bindInternalLinksEvents: function() {
            // Tab switching
            $(document).on('click', '#aips-internal-links-tab-nav .aips-tab-link', AIPS.switchILTab);

            // Index tab
            $(document).on('click', '#aips-index-all-posts',    AIPS.handleIndexAll);
            $(document).on('click', '.aips-index-single',       AIPS.handleIndexSingle);
            $(document).on('click', '#aips-il-apply-filter',    AIPS.applyIndexFilter);
            $(document).on('keyup', '#aips-il-search', function(e) {
                if (e.key === 'Enter') { AIPS.applyIndexFilter(); }
            });
            $(document).on('click', '.aips-il-page-btn',        AIPS.handleIndexPagination);

            // Generate tab — post search autocomplete
            $(document).on('input', '#aips-il-post-search',     AIPS.handlePostSearch);
            $(document).on('click', '.aips-il-suggestion-item', AIPS.handleSelectPost);
            $(document).on('keydown', '#aips-il-post-search',   AIPS.handlePostSearchKeydown);

            // Similarity slider live label
            $(document).on('input', '#aips-il-min-score',       AIPS.updateMinScoreLabel);

            // Find related posts
            $(document).on('click', '#aips-find-related-posts', AIPS.handleFindRelated);

            // Select-all checkbox in related table
            $(document).on('change', '#aips-il-select-all-related', AIPS.toggleAllRelated);

            // Preview links
            $(document).on('click', '#aips-preview-links',      AIPS.handlePreviewLinks);

            // Modal close
            $(document).on('click', '.aips-modal-close',        AIPS.closeILModal);
            $(document).on('click', '#aips-il-preview-modal',   AIPS.closeILModalOnOverlay);
            $(document).on('keydown', AIPS.closeILModalOnEsc);

            // Apply & Save
            $(document).on('click', '#aips-apply-save-links',   AIPS.handleSaveLinks);
        },

        // ===================================================================
        // Tab helper
        // ===================================================================

        /**
         * Switch visible tab panel when a tab button is clicked.
         *
         * @param {Event} e Click event.
         */
        switchILTab: function(e) {
            var $btn = $(e.currentTarget);
            var tabId = $btn.data('tab');

            $('#aips-internal-links-tab-nav .aips-tab-link').removeClass('active');
            $btn.addClass('active');

            $('.aips-tab-content').hide();
            $('#' + tabId + '-tab').show();
        },

        // ===================================================================
        // Index Tab
        // ===================================================================

        /**
         * Load the index status table for a given page.
         *
         * @param {number} page Page number (1-based).
         */
        loadIndexTable: function(page) {
            AIPS.ilCurrentPage = page;

            var $tbody = $('#aips-index-table-body');
            $tbody.html('<tr><td colspan="5">' + AIPS.escHtml(aipsInternalLinksL10n.loading) + '</td></tr>');

            var params = {
                action:   'aips_get_index_status',
                nonce:    aipsAjax.nonce,
                page_num: page,
                status:   $('#aips-il-status-filter').val(),
                search:   $('#aips-il-search').val(),
            };

            $.get(aipsAjax.ajaxUrl, params, function(response) {
                if (!response.success) {
                    $tbody.html('<tr><td colspan="5">' + AIPS.escHtml(aipsInternalLinksL10n.loadError) + '</td></tr>');
                    return;
                }

                AIPS.renderIndexTable(response.data);
            }).fail(function() {
                $tbody.html('<tr><td colspan="5">' + AIPS.escHtml(aipsInternalLinksL10n.loadError) + '</td></tr>');
            });
        },

        /**
         * Render the index table rows and pagination from AJAX response data.
         *
         * @param {{ items: Array, total: number, page: number, total_pages: number }} data
         */
        renderIndexTable: function(data) {
            var $tbody = $('#aips-index-table-body');
            $tbody.empty();

            if (!data.items || data.items.length === 0) {
                $tbody.html('<tr><td colspan="5">' + AIPS.escHtml(aipsInternalLinksL10n.noPostsFound) + '</td></tr>');
                $('#aips-index-pagination').hide();
                return;
            }

            $.each(data.items, function(i, item) {
                var statusBadge = AIPS.renderStatusBadge(item.index_status);
                var indexedAt   = item.indexed_at ? AIPS.escHtml(item.indexed_at) : '&mdash;';

                var editLink = item.edit_link
                    ? '<a href="' + AIPS.escHtml(item.edit_link) + '" target="_blank">' + AIPS.escHtml(item.post_title) + '</a>'
                    : AIPS.escHtml(item.post_title);

                var $tr = $('<tr>')
                    .attr('data-post-id', item.post_id)
                    .append($('<td class="cell-primary">').html(editLink))
                    .append($('<td>').text(item.post_type))
                    .append($('<td class="aips-il-status-cell">').html(statusBadge))
                    .append($('<td>').html(indexedAt))
                    .append($('<td>').html(
                        '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-index-single" data-post-id="' + item.post_id + '">' +
                        AIPS.escHtml(aipsInternalLinksL10n.indexNow) +
                        '</button>'
                    ));

                $tbody.append($tr);
            });

            // Pagination
            var $pager = $('#aips-index-pagination');
            $pager.empty();

            if (data.total_pages > 1) {
                for (var p = 1; p <= data.total_pages; p++) {
                    var $btn = $('<button>')
                        .addClass('aips-btn aips-btn-sm aips-il-page-btn')
                        .addClass(p === data.page ? 'aips-btn-primary' : 'aips-btn-secondary')
                        .attr('data-page', p)
                        .text(p);
                    $pager.append($btn);
                }
                $pager.show();
            } else {
                $pager.hide();
            }
        },

        /**
         * Build a status badge HTML string.
         *
         * @param {string} status
         * @returns {string}
         */
        renderStatusBadge: function(status) {
            var labels = {
                indexed: aipsInternalLinksL10n.statusIndexed,
                pending: aipsInternalLinksL10n.statusPending,
                error:   aipsInternalLinksL10n.statusError,
            };
            var label = labels[status] || AIPS.escHtml(status);
            return '<span class="aips-badge aips-badge-' + AIPS.escHtml(status) + '">' + AIPS.escHtml(label) + '</span>';
        },

        /**
         * Handle the "Index All Posts" button click.
         *
         * @param {Event} e Click event.
         */
        handleIndexAll: function(e) {
            var $btn = $(e.currentTarget);

            if (!confirm(aipsInternalLinksL10n.confirmIndexAll)) {
                return;
            }

            $btn.prop('disabled', true).text(aipsInternalLinksL10n.indexing);

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_bulk_index_posts',
                nonce:  aipsAjax.nonce,
            }, function(response) {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-database-import"></span> ' + aipsInternalLinksL10n.indexAllPosts
                );

                if (response.success) {
                    AIPS.showNotice(response.data.message, 'success');
                    AIPS.startIndexingProgressPolling();
                    AIPS.loadIndexTable(1);
                } else {
                    AIPS.showNotice(response.data.message || aipsInternalLinksL10n.errorGeneric, 'error');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-database-import"></span> ' + aipsInternalLinksL10n.indexAllPosts
                );
                AIPS.showNotice(aipsInternalLinksL10n.errorGeneric, 'error');
            });
        },

        /**
         * Handle the "Index Now" row-level button click.
         *
         * @param {Event} e Click event.
         */
        handleIndexSingle: function(e) {
            var $btn    = $(e.currentTarget);
            var postId  = $btn.data('post-id');

            $btn.prop('disabled', true).text(aipsInternalLinksL10n.indexing);

            $.post(aipsAjax.ajaxUrl, {
                action:  'aips_index_single_post',
                nonce:   aipsAjax.nonce,
                post_id: postId,
            }, function(response) {
                $btn.prop('disabled', false).text(aipsInternalLinksL10n.indexNow);

                if (response.success) {
                    AIPS.refreshIndexRow(postId, response.data);
                    AIPS.showNotice(response.data.message, 'success');
                } else {
                    AIPS.showNotice(response.data.message || aipsInternalLinksL10n.errorGeneric, 'error');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text(aipsInternalLinksL10n.indexNow);
                AIPS.showNotice(aipsInternalLinksL10n.errorGeneric, 'error');
            });
        },

        /**
         * Refresh the status badge in a specific index table row.
         *
         * @param {number} postId
         * @param {{ index_status: string, indexed_at: string }} data
         */
        refreshIndexRow: function(postId, data) {
            var $row = $('#aips-index-table-body tr[data-post-id="' + postId + '"]');
            if (!$row.length) { return; }

            $row.find('.aips-il-status-cell').html(AIPS.renderStatusBadge(data.index_status));
            $row.find('td:eq(3)').text(data.indexed_at || '—');
        },

        /**
         * Apply current search/filter values and reload page 1 of the index table.
         */
        applyIndexFilter: function() {
            AIPS.loadIndexTable(1);
        },

        /**
         * Handle pagination button clicks in the index table.
         *
         * @param {Event} e Click event.
         */
        handleIndexPagination: function(e) {
            var page = parseInt($(e.currentTarget).data('page'), 10);
            if (page > 0) {
                AIPS.loadIndexTable(page);
            }
        },

        /**
         * Start polling for indexing progress until pending count reaches 0.
         */
        startIndexingProgressPolling: function() {
            if (AIPS.ilPollingTimer) {
                clearInterval(AIPS.ilPollingTimer);
            }

            AIPS.ilPollingTimer = setInterval(function() {
                $.get(aipsAjax.ajaxUrl, {
                    action: 'aips_get_indexing_progress',
                    nonce:  aipsAjax.nonce,
                }, function(response) {
                    if (!response.success) { return; }

                    var pending = parseInt(response.data.pending, 10);
                    var indexed = parseInt(response.data.indexed, 10);

                    $('#aips-stat-pending-count').text(pending);

                    if (pending === 0) {
                        clearInterval(AIPS.ilPollingTimer);
                        AIPS.ilPollingTimer = null;
                        AIPS.loadIndexTable(AIPS.ilCurrentPage);
                    }
                });
            }, 5000);
        },

        // ===================================================================
        // Generate Tab — Post Autocomplete
        // ===================================================================

        /**
         * Handle post search autocomplete input changes.
         *
         * @param {Event} e Input event.
         */
        handlePostSearch: function(e) {
            var query = $(e.currentTarget).val().trim();

            if (query.length < 2) {
                $('#aips-il-post-suggestions').hide();
                return;
            }

            $.get(aipsAjax.ajaxUrl, {
                action: 'aips_search_posts_for_linking',
                nonce:  aipsAjax.nonce,
                search: query,
            }, function(response) {
                if (!response.success || !response.data.posts.length) {
                    $('#aips-il-post-suggestions').hide();
                    return;
                }

                var $dropdown = $('#aips-il-post-suggestions').empty().show();

                $.each(response.data.posts, function(i, post) {
                    var $item = $('<div class="aips-il-suggestion-item">')
                        .attr('data-post-id', post.id)
                        .attr('data-title', post.title)
                        .attr('data-excerpt', post.excerpt);

                    $item.append($('<div>').text(post.title));

                    if (post.excerpt) {
                        $item.append($('<div class="aips-il-suggestion-excerpt">').text(post.excerpt));
                    }

                    $dropdown.append($item);
                });
            });
        },

        /**
         * Keyboard navigation for the post autocomplete dropdown.
         *
         * @param {Event} e Keydown event.
         */
        handlePostSearchKeydown: function(e) {
            var $dropdown = $('#aips-il-post-suggestions');

            if (!$dropdown.is(':visible')) { return; }

            var $items   = $dropdown.find('.aips-il-suggestion-item');
            var $active  = $items.filter('.is-active');
            var idx      = $items.index($active);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                $items.removeClass('is-active');
                $items.eq(Math.min(idx + 1, $items.length - 1)).addClass('is-active');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                $items.removeClass('is-active');
                $items.eq(Math.max(idx - 1, 0)).addClass('is-active');
            } else if (e.key === 'Enter') {
                var $focused = $items.filter('.is-active');
                if ($focused.length) {
                    e.preventDefault();
                    $focused.trigger('click');
                }
            } else if (e.key === 'Escape') {
                $dropdown.hide();
            }
        },

        /**
         * Handle selection of a post from the autocomplete dropdown.
         *
         * @param {Event} e Click event.
         */
        handleSelectPost: function(e) {
            var $item   = $(e.currentTarget);
            var postId  = parseInt($item.data('post-id'), 10);
            var title   = $item.data('title');
            var excerpt = $item.data('excerpt');

            AIPS.ilSelectedPost = { id: postId, title: title, excerpt: excerpt };

            $('#aips-il-post-search').val(title);
            $('#aips-il-selected-post-id').val(postId);
            $('#aips-il-post-suggestions').hide();

            // Show preview
            $('#aips-il-preview-title').text(title);
            $('#aips-il-preview-excerpt').text(excerpt);
            $('#aips-il-selected-post-preview').show();

            // Enable Find Related button
            $('#aips-find-related-posts').prop('disabled', false);
        },

        /**
         * Update the min-score display label when the slider changes.
         *
         * @param {Event} e Input event.
         */
        updateMinScoreLabel: function(e) {
            var val = parseFloat($(e.currentTarget).val()).toFixed(2);
            $('#aips-il-min-score-display').text(val);
        },

        // ===================================================================
        // Generate Tab — Related Posts
        // ===================================================================

        /**
         * Handle the "Find Related Posts" button click.
         *
         * @param {Event} e Click event.
         */
        handleFindRelated: function(e) {
            if (!AIPS.ilSelectedPost) {
                AIPS.showNotice(aipsInternalLinksL10n.selectPostFirst, 'error');
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(aipsInternalLinksL10n.searching);

            $.post(aipsAjax.ajaxUrl, {
                action:    'aips_find_related_posts',
                nonce:     aipsAjax.nonce,
                post_id:   AIPS.ilSelectedPost.id,
                top_n:     parseInt($('#aips-il-top-n').val(), 10),
                min_score: parseFloat($('#aips-il-min-score').val()),
            }, function(response) {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-search"></span> ' + aipsInternalLinksL10n.findRelatedPosts
                );

                if (!response.success) {
                    AIPS.showNotice(response.data.message || aipsInternalLinksL10n.errorGeneric, 'error');
                    return;
                }

                AIPS.ilRelatedPosts = response.data.related;
                AIPS.renderRelatedPostsTable(AIPS.ilRelatedPosts);
            }).fail(function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-search"></span> ' + aipsInternalLinksL10n.findRelatedPosts
                );
                AIPS.showNotice(aipsInternalLinksL10n.errorGeneric, 'error');
            });
        },

        /**
         * Render the related posts table in Step 3.
         *
         * @param {Array} related Array of { post_id, title, permalink, score }.
         */
        renderRelatedPostsTable: function(related) {
            var $tbody = $('#aips-il-related-tbody');
            $tbody.empty();

            if (!related || related.length === 0) {
                $tbody.html('<tr><td colspan="3">' + AIPS.escHtml(aipsInternalLinksL10n.noRelatedFound) + '</td></tr>');
                $('#aips-il-related-step').show();
                $('#aips-preview-links').prop('disabled', true);
                return;
            }

            $.each(related, function(i, post) {
                var scorePercent = Math.round(post.score * 100);
                var $tr = $('<tr>')
                    .attr('data-post-id', post.post_id)
                    .attr('data-permalink', post.permalink)
                    .attr('data-title', post.title);

                var $checkbox = $('<input type="checkbox" class="aips-il-related-checkbox">')
                    .prop('checked', true)
                    .val(post.post_id);

                var titleLink = '<a href="' + AIPS.escHtml(post.permalink) + '" target="_blank">' + AIPS.escHtml(post.title) + '</a>';

                var scoreBar =
                    '<div class="aips-il-score-bar-wrap">' +
                    '<div class="aips-il-score-bar"><div class="aips-il-score-bar-fill" style="width:' + scorePercent + '%"></div></div>' +
                    '<span class="aips-il-score-value">' + AIPS.escHtml(post.score.toFixed(2)) + '</span>' +
                    '</div>';

                $tr.append($('<td class="check-column">').append($checkbox))
                   .append($('<td>').html(titleLink))
                   .append($('<td>').html(scoreBar));

                $tbody.append($tr);
            });

            $('#aips-il-related-step').show();
            $('#aips-preview-links').prop('disabled', false);
        },

        /**
         * Toggle all checkboxes in the related posts table.
         *
         * @param {Event} e Change event.
         */
        toggleAllRelated: function(e) {
            var checked = $(e.currentTarget).prop('checked');
            $('#aips-il-related-tbody .aips-il-related-checkbox').prop('checked', checked);
        },

        // ===================================================================
        // Generate Tab — Preview & Save
        // ===================================================================

        /**
         * Handle the "Preview Links" button click.
         *
         * Collects checked related posts and calls the preview AJAX endpoint.
         *
         * @param {Event} e Click event.
         */
        handlePreviewLinks: function(e) {
            if (!AIPS.ilSelectedPost) {
                AIPS.showNotice(aipsInternalLinksL10n.selectPostFirst, 'error');
                return;
            }

            var selected = AIPS.getSelectedRelatedPosts();

            if (selected.length === 0) {
                AIPS.showNotice(aipsInternalLinksL10n.selectAtLeastOne, 'error');
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(aipsInternalLinksL10n.generatingPreview);

            var payload = {
                action:        'aips_preview_links',
                nonce:         aipsAjax.nonce,
                post_id:       AIPS.ilSelectedPost.id,
            };

            // Serialize related_posts as indexed array entries
            $.each(selected, function(i, rp) {
                payload['related_posts[' + i + '][post_id]']   = rp.post_id;
                payload['related_posts[' + i + '][title]']     = rp.title;
                payload['related_posts[' + i + '][permalink]'] = rp.permalink;
            });

            $.post(aipsAjax.ajaxUrl, payload, function(response) {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-visibility"></span> ' + aipsInternalLinksL10n.previewLinks
                );

                if (!response.success) {
                    AIPS.showNotice(response.data.message || aipsInternalLinksL10n.errorGeneric, 'error');
                    return;
                }

                AIPS.ilPreviewContent = response.data.content;
                AIPS.openPreviewModal(AIPS.ilPreviewContent);
            }).fail(function() {
                $btn.prop('disabled', false).html(
                    '<span class="dashicons dashicons-visibility"></span> ' + aipsInternalLinksL10n.previewLinks
                );
                AIPS.showNotice(aipsInternalLinksL10n.errorGeneric, 'error');
            });
        },

        /**
         * Open the preview modal and display content.
         *
         * @param {string} content HTML content string.
         */
        openPreviewModal: function(content) {
            $('#aips-il-preview-content').html(content);
            $('#aips-il-preview-modal').show();
            $('body').addClass('aips-modal-open');
        },

        /**
         * Close the preview modal.
         */
        closeILModal: function() {
            $('#aips-il-preview-modal').hide();
            $('body').removeClass('aips-modal-open');
        },

        /**
         * Close the modal when clicking the overlay backdrop.
         *
         * @param {Event} e Click event.
         */
        closeILModalOnOverlay: function(e) {
            if ($(e.target).is('#aips-il-preview-modal')) {
                AIPS.closeILModal();
            }
        },

        /**
         * Close the modal on Escape key press.
         *
         * @param {Event} e Keydown event.
         */
        closeILModalOnEsc: function(e) {
            if (e.key === 'Escape' && $('#aips-il-preview-modal').is(':visible')) {
                AIPS.closeILModal();
            }
        },

        /**
         * Handle the "Apply & Save" button inside the preview modal.
         *
         * @param {Event} e Click event.
         */
        handleSaveLinks: function(e) {
            if (!AIPS.ilSelectedPost || !AIPS.ilPreviewContent) {
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(aipsInternalLinksL10n.saving);

            $.post(aipsAjax.ajaxUrl, {
                action:  'aips_save_links',
                nonce:   aipsAjax.nonce,
                post_id: AIPS.ilSelectedPost.id,
                content: AIPS.ilPreviewContent,
            }, function(response) {
                $btn.prop('disabled', false).text(aipsInternalLinksL10n.applySave);

                if (!response.success) {
                    AIPS.showNotice(response.data.message || aipsInternalLinksL10n.errorGeneric, 'error');
                    return;
                }

                AIPS.closeILModal();
                AIPS.showNotice(response.data.message, 'success');

                // Reset generate tab state
                AIPS.ilPreviewContent = '';
                AIPS.ilRelatedPosts   = [];
                AIPS.ilSelectedPost   = null;
                $('#aips-il-post-search').val('');
                $('#aips-il-selected-post-id').val('');
                $('#aips-il-selected-post-preview').hide();
                $('#aips-il-related-step').hide();
                $('#aips-find-related-posts').prop('disabled', true);
            }).fail(function() {
                $btn.prop('disabled', false).text(aipsInternalLinksL10n.applySave);
                AIPS.showNotice(aipsInternalLinksL10n.errorGeneric, 'error');
            });
        },

        // ===================================================================
        // Utilities
        // ===================================================================

        /**
         * Collect checked related posts from the table, returning an array of
         * { post_id, title, permalink } objects.
         *
         * @returns {Array}
         */
        getSelectedRelatedPosts: function() {
            var selected = [];

            $('#aips-il-related-tbody .aips-il-related-checkbox:checked').each(function() {
                var $row = $(this).closest('tr');
                selected.push({
                    post_id:   parseInt($row.data('post-id'), 10),
                    title:     $row.data('title'),
                    permalink: $row.data('permalink'),
                });
            });

            return selected;
        },

        /**
         * HTML-escape a value for safe insertion into markup.
         *
         * @param {*} text Value to escape.
         * @returns {string}
         */
        escHtml: function(text) {
            if (text === null || text === undefined) { return ''; }
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        },

        /**
         * Show a dismissable admin notice at the top of the main content panel.
         *
         * @param {string} message Notice text.
         * @param {string} type    'success' | 'error' | 'warning' | 'info'.
         */
        showNotice: function(message, type) {
            type = type || 'info';
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible aips-il-notice">')
                .append($('<p>').text(message))
                .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');

            $('.aips-page-container').prepend($notice);

            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(200, function() { $notice.remove(); });
            });

            setTimeout(function() {
                $notice.fadeOut(400, function() { $notice.remove(); });
            }, 6000);
        },
    });

    // -----------------------------------------------------------------------
    // Bootstrap
    // -----------------------------------------------------------------------
    $(document).ready(function() {
        if ($('#aips-internal-links-tab-nav').length) {
            AIPS.initInternalLinks();
        }
    });

})(jQuery);
