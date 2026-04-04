/**
 * Internal Links Admin JS
 *
 * @package AI_Post_Scheduler
 * @since 2.1.0
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    Object.assign(AIPS, {
        internalLinksState: {
            indexPage: 1,
            selectedPostId: 0,
            selectedRelatedPostIds: [],
            relatedRows: []
        },

        /**
         * Initialize Internal Links page behavior.
         */
        initInternalLinks: function() {
            this.bindInternalLinksEvents();
            this.loadIndexStatus();
            this.updateSimilarityValue();
        },

        /**
         * Bind all Internal Links page event handlers.
         */
        bindInternalLinksEvents: function() {
            $(document).on('click', '.aips-tab-link[data-tab="index-posts"]', this.showIndexTab.bind(this));
            $(document).on('click', '.aips-tab-link[data-tab="generate-links"]', this.showGenerateTab.bind(this));

            $(document).on('click', '#aips-index-filter-apply', this.onApplyIndexFilters.bind(this));
            $(document).on('click', '#aips-index-all-posts', this.onBulkIndexPosts.bind(this));
            $(document).on('click', '.aips-index-now', this.onIndexSinglePost.bind(this));
            $(document).on('click', '.aips-index-page-link', this.onClickIndexPage.bind(this));

            $(document).on('input', '#aips-link-source-search', this.onSearchSourcePosts.bind(this));
            $(document).on('click', '.aips-source-option', this.onSelectSourcePost.bind(this));
            $(document).on('click', '#aips-find-related-posts', this.onFindRelatedPosts.bind(this));
            $(document).on('click', '#aips-preview-links', this.onPreviewLinks.bind(this));
            $(document).on('click', '#aips-apply-links-save', this.onApplyLinksSave.bind(this));
            $(document).on('change', '#aips-min-similarity', this.onSimilarityChange.bind(this));
            $(document).on('change', '#aips-select-all-related', this.onToggleAllRelated.bind(this));
            $(document).on('change', '.aips-related-checkbox', this.onToggleRelatedCheckbox.bind(this));
            $(document).on('click', '.aips-modal-close', this.onClosePreviewModal.bind(this));
        },

        /**
         * @param {Event} e
         */
        showIndexTab: function(e) {
            e.preventDefault();
            $('.aips-tab-link').removeClass('active');
            $(e.currentTarget).addClass('active');
            $('#aips-generate-links-tab').hide();
            $('#aips-index-posts-tab').show();
        },

        /**
         * @param {Event} e
         */
        showGenerateTab: function(e) {
            e.preventDefault();
            $('.aips-tab-link').removeClass('active');
            $(e.currentTarget).addClass('active');
            $('#aips-index-posts-tab').hide();
            $('#aips-generate-links-tab').show();
        },

        /**
         * @param {Event} e
         */
        onApplyIndexFilters: function(e) {
            e.preventDefault();
            this.internalLinksState.indexPage = 1;
            this.loadIndexStatus();
        },

        /**
         * Load index status table and summary.
         */
        loadIndexStatus: function() {
            var self = this;
            var search = $('#aips-index-search').val() || '';
            var status = $('#aips-index-status-filter').val() || 'all';

            $('#aips-index-table-body').html('<tr><td colspan="5">Loading...</td></tr>');

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_get_index_status',
                nonce: aipsAjax.nonce,
                page: self.internalLinksState.indexPage,
                search: search,
                status: status
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    AIPS.Utilities.showToast('Failed to load index status.', 'error');
                    return;
                }

                self.renderIndexRows(response.data.rows);
                self.renderIndexPagination(response.data.rows);
                self.renderSummary(response.data.summary);
            }).fail(function() {
                AIPS.Utilities.showToast('Failed to load index status.', 'error');
            });
        },

        /**
         * @param {Object} rowsPayload
         */
        renderIndexRows: function(rowsPayload) {
            var rows = rowsPayload && rowsPayload.items ? rowsPayload.items : [];
            if (!rows.length) {
                $('#aips-index-table-body').html('<tr><td colspan="5">No posts found.</td></tr>');
                return;
            }

            var html = '';
            rows.forEach(function(row) {
                var status = row.index_status || 'pending';
                var badgeClass = 'aips-badge-info';
                var label = 'Pending';
                if (status === 'indexed') {
                    badgeClass = 'aips-badge-success';
                    label = 'Indexed';
                } else if (status === 'error') {
                    badgeClass = 'aips-badge-error';
                    label = 'Error';
                }

                html += '<tr data-post-id="' + parseInt(row.post_id, 10) + '">';
                html += '<td><a href="' + row.edit_link + '">' + $('<span>').text(row.post_title).html() + '</a></td>';
                html += '<td>' + $('<span>').text(row.post_type || 'post').html() + '</td>';
                html += '<td><span class="aips-badge ' + badgeClass + '">' + label + '</span></td>';
                html += '<td>' + $('<span>').text(row.indexed_at || '—').html() + '</td>';
                html += '<td><button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-index-now" data-post-id="' + parseInt(row.post_id, 10) + '">Index Now</button></td>';
                html += '</tr>';
            });

            $('#aips-index-table-body').html(html);
        },

        /**
         * @param {Object} summary
         */
        renderSummary: function(summary) {
            summary = summary || {};
            $('#aips-summary-total').text(parseInt(summary.total_published || 0, 10));
            $('#aips-summary-indexed').text(parseInt(summary.indexed || 0, 10));
            $('#aips-summary-pending').text(parseInt(summary.pending || 0, 10));
            $('#aips-summary-error').text(parseInt(summary.error || 0, 10));
        },

        /**
         * @param {Object} rowsPayload
         */
        renderIndexPagination: function(rowsPayload) {
            var current = parseInt(rowsPayload.current_page || 1, 10);
            var pages = parseInt(rowsPayload.pages || 1, 10);
            var html = '';

            if (pages <= 1) {
                $('#aips-index-pagination').html('');
                return;
            }

            for (var p = 1; p <= pages; p++) {
                if (p === current) {
                    html += '<span class="aips-btn aips-btn-sm aips-btn-primary">' + p + '</span>';
                } else {
                    html += '<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-index-page-link" data-page="' + p + '">' + p + '</button>';
                }
            }

            $('#aips-index-pagination').html(html);
        },

        /**
         * @param {Event} e
         */
        onClickIndexPage: function(e) {
            e.preventDefault();
            this.internalLinksState.indexPage = parseInt($(e.currentTarget).data('page'), 10) || 1;
            this.loadIndexStatus();
        },

        /**
         * @param {Event} e
         */
        onIndexSinglePost: function(e) {
            e.preventDefault();
            var self = this;
            var postId = parseInt($(e.currentTarget).data('post-id'), 10) || 0;
            if (!postId) {
                return;
            }

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_index_single_post',
                nonce: aipsAjax.nonce,
                post_id: postId
            }).done(function(response) {
                if (response && response.success) {
                    AIPS.Utilities.showToast(response.data.message || 'Indexed.', 'success');
                    self.loadIndexStatus();
                } else {
                    AIPS.Utilities.showToast(response && response.data && response.data.message ? response.data.message : 'Index failed.', 'error');
                }
            }).fail(function() {
                AIPS.Utilities.showToast('Index failed.', 'error');
            });
        },

        /**
         * @param {Event} e
         */
        onBulkIndexPosts: function(e) {
            e.preventDefault();
            var self = this;
            var offset = 0;
            var progress = AIPS.Utilities.showProgressBar({
                title: 'Indexing Posts',
                message: 'Generating embeddings and syncing vectors...',
                totalSeconds: 90
            });

            function runBatch() {
                $.post(aipsAjax.ajaxUrl, {
                    action: 'aips_bulk_index_posts',
                    nonce: aipsAjax.nonce,
                    offset: offset,
                    batch_size: 25
                }).done(function(response) {
                    if (!response || !response.success || !response.data) {
                        progress.complete('Indexing failed.', 'error');
                        AIPS.Utilities.showToast('Indexing failed.', 'error');
                        return;
                    }

                    offset = parseInt(response.data.offset || offset, 10);
                    self.renderSummary(response.data.summary || {});

                    if (response.data.done) {
                        progress.complete('Indexing complete.', 'success');
                        self.loadIndexStatus();
                    } else {
                        runBatch();
                    }
                }).fail(function() {
                    progress.complete('Indexing failed.', 'error');
                    AIPS.Utilities.showToast('Indexing failed.', 'error');
                });
            }

            runBatch();
        },

        /**
         * @param {Event} e
         */
        onSearchSourcePosts: function(e) {
            e.preventDefault();
            var term = $('#aips-link-source-search').val() || '';
            if (term.length < 2) {
                $('#aips-link-source-autocomplete').hide().empty();
                return;
            }

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_search_posts',
                nonce: aipsAjax.nonce,
                term: term
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    return;
                }

                var html = '';
                var posts = response.data.posts || [];
                posts.forEach(function(post) {
                    html += '<button type="button" class="aips-btn aips-btn-ghost aips-source-option" data-post-id="' + parseInt(post.id, 10) + '" data-title="' + $('<span>').text(post.title).html() + '" data-excerpt="' + $('<span>').text(post.excerpt || '').html() + '" style="display:block;width:100%;text-align:left;">' + $('<span>').text(post.title).html() + '</button>';
                });

                if (!html) {
                    html = '<div class="description" style="padding:8px;">No matching posts found.</div>';
                }

                $('#aips-link-source-autocomplete').html(html).show();
            });
        },

        /**
         * @param {Event} e
         */
        onSelectSourcePost: function(e) {
            e.preventDefault();
            var postId = parseInt($(e.currentTarget).data('post-id'), 10) || 0;
            var title = $(e.currentTarget).data('title') || '';
            var excerpt = $(e.currentTarget).data('excerpt') || '';
            var decodedTitle = $('<textarea>').html(title).text();
            var decodedExcerpt = $('<textarea>').html(excerpt).text();

            this.internalLinksState.selectedPostId = postId;
            $('#aips-link-source-search').val(decodedTitle);
            $('#aips-selected-source-preview')
                .empty()
                .append($('<strong>').text(decodedTitle))
                .append('<br>')
                .append(document.createTextNode(decodedExcerpt));
            $('#aips-link-source-autocomplete').hide().empty();
        },

        /**
         * @param {Event} e
         */
        onFindRelatedPosts: function(e) {
            e.preventDefault();
            var self = this;
            var postId = this.internalLinksState.selectedPostId;
            if (!postId) {
                AIPS.Utilities.showToast('Select a source post first.', 'warning');
                return;
            }

            var maxLinks = parseInt($('#aips-max-links').val(), 10) || 5;
            var minSimilarity = parseFloat($('#aips-min-similarity').val()) || 0.75;

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_find_related_posts',
                nonce: aipsAjax.nonce,
                post_id: postId,
                max_links: maxLinks,
                min_similarity: minSimilarity
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    AIPS.Utilities.showToast('Failed to find related posts.', 'error');
                    return;
                }

                self.internalLinksState.relatedRows = response.data.related_posts || [];
                self.internalLinksState.selectedRelatedPostIds = self.internalLinksState.relatedRows.map(function(r) { return parseInt(r.post_id, 10); });
                self.renderRelatedPostsTable();
                $('#aips-preview-links').prop('disabled', self.internalLinksState.selectedRelatedPostIds.length === 0);
            }).fail(function() {
                AIPS.Utilities.showToast('Failed to find related posts.', 'error');
            });
        },

        /**
         * Render related posts selector table.
         */
        renderRelatedPostsTable: function() {
            var rows = this.internalLinksState.relatedRows || [];
            if (!rows.length) {
                $('#aips-related-posts-table').hide();
                return;
            }

            var html = '';
            rows.forEach(function(row) {
                var score = parseFloat(row.score || 0);
                var scorePercent = Math.max(0, Math.min(100, Math.round(score * 100)));
                html += '<tr>';
                html += '<th class="check-column"><input type="checkbox" class="aips-related-checkbox" checked value="' + parseInt(row.post_id, 10) + '"></th>';
                html += '<td><a href="' + row.permalink + '" target="_blank" rel="noopener">' + $('<span>').text(row.title).html() + '</a></td>';
                html += '<td><div style="display:flex;align-items:center;gap:8px;"><div style="background:#eee;height:8px;flex:1;border-radius:99px;"><div style="height:8px;background:#2271b1;border-radius:99px;width:' + scorePercent + '%"></div></div><span>' + scorePercent + '%</span></div></td>';
                html += '</tr>';
            });

            $('#aips-related-posts-body').html(html);
            $('#aips-related-posts-table').show();
        },

        /**
         * @param {Event} e
         */
        onPreviewLinks: function(e) {
            e.preventDefault();
            var self = this;
            var postId = this.internalLinksState.selectedPostId;
            var relatedIds = this.internalLinksState.selectedRelatedPostIds || [];
            if (!postId || !relatedIds.length) {
                AIPS.Utilities.showToast('Select at least one related post.', 'warning');
                return;
            }

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_preview_links',
                nonce: aipsAjax.nonce,
                post_id: postId,
                related_post_ids: relatedIds,
                max_links: parseInt($('#aips-max-links').val(), 10) || 5
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    AIPS.Utilities.showToast('Failed to preview links.', 'error');
                    return;
                }

                $('#aips-preview-links-html').html(response.data.preview_html || '');
                $('#aips-internal-links-preview-modal').fadeIn(120);
            }).fail(function() {
                AIPS.Utilities.showToast('Failed to preview links.', 'error');
            });
        },

        /**
         * @param {Event} e
         */
        onApplyLinksSave: function(e) {
            e.preventDefault();
            var self = this;
            var postId = this.internalLinksState.selectedPostId;
            var relatedIds = this.internalLinksState.selectedRelatedPostIds || [];

            if (!postId || !relatedIds.length) {
                AIPS.Utilities.showToast('Select at least one related post.', 'warning');
                return;
            }

            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_save_links',
                nonce: aipsAjax.nonce,
                post_id: postId,
                related_post_ids: relatedIds,
                max_links: parseInt($('#aips-max-links').val(), 10) || 5
            }).done(function(response) {
                if (!response || !response.success || !response.data) {
                    AIPS.Utilities.showToast('Failed to save links.', 'error');
                    return;
                }

                AIPS.Utilities.showToast(response.data.message || 'Links saved.', 'success');
                $('#aips-internal-links-preview-modal').fadeOut(120);
                self.loadIndexStatus();
            }).fail(function() {
                AIPS.Utilities.showToast('Failed to save links.', 'error');
            });
        },

        /**
         * @param {Event} e
         */
        onSimilarityChange: function(e) {
            e.preventDefault();
            this.updateSimilarityValue();
        },

        /**
         * Update min similarity label.
         */
        updateSimilarityValue: function() {
            var value = parseFloat($('#aips-min-similarity').val()) || 0.75;
            $('#aips-min-similarity-value').text(value.toFixed(2));
        },

        /**
         * @param {Event} e
         */
        onToggleAllRelated: function(e) {
            var checked = $(e.currentTarget).is(':checked');
            $('.aips-related-checkbox').prop('checked', checked).trigger('change');
        },

        /**
         * @param {Event} e
         */
        onToggleRelatedCheckbox: function(e) {
            var ids = [];
            $('.aips-related-checkbox:checked').each(function() {
                ids.push(parseInt($(this).val(), 10));
            });
            this.internalLinksState.selectedRelatedPostIds = ids;
            $('#aips-preview-links').prop('disabled', ids.length === 0);
        },

        /**
         * @param {Event} e
         */
        onClosePreviewModal: function(e) {
            e.preventDefault();
            $('#aips-internal-links-preview-modal').fadeOut(120);
        }
    });

    $(document).ready(function() {
        if ($('#aips-index-posts-tab').length) {
            AIPS.initInternalLinks();
        }
    });
})(jQuery);
