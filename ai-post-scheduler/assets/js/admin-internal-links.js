/**
 * Internal Links Admin Module
 *
 * Handles all UI interactions for the Internal Links admin page, including
 * suggestion listing, filtering, pagination, status management, and
 * per-post suggestion generation.
 *
 * @package AI_Post_Scheduler
 * @since 2.3.0
 */

(function ($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    /**
     * @namespace AIPS.InternalLinks
     */
    AIPS.InternalLinks = {

        /** Current page number */
        currentPage: 1,

        /** Active status filter ('' = all statuses) */
        currentStatus: '',

        /** Active direction filter: 'inbound', 'outbound' or '' (all) */
        currentOrigin: '',

        /** Active search string */
        currentSearch: '',

        /** Per-page row count */
        perPage: 20,

        /** Debounce timer handle for search input */
        _searchTimer: null,

        /** Plain-text copy of the post content in the Insert modal */
        _previewPlainText: '',

        /** Post ID currently loaded in the Insert modal */
        _previewPostId: 0,

        /** Pending insertions not yet saved: [{suggestionId, matchSnippet, replacementSnippet, anchorText}] */
        _pendingInsertions: [],

        /** Map of suggestionId → {anchorText, targetUrl, title} for the Insert modal */
        _suggestionDataMap: {},

        /**
         * Bootstrap the module.
         *
         * Binds the UI events, syncs the index status line with any running background
         * indexing job, applies filters passed in the URL, and loads the first page of suggestions.
         *
         * @return {void}
         */
        init: function () {
            this.bindEvents();

            // background-processes.js initialises first and fires its first update before
            // this module is listening, so read the current state directly.
            if (AIPS.BackgroundProcesses) {
                this.onBackgroundUpdate(null, [], AIPS.BackgroundProcesses.state);
            }

            // Pre-select filters from the URL before the first load, so the table opens already filtered.
            this.applyUrlFilters();
            this.loadSuggestions();
        },

        /**
         * Pre-select filters passed in the URL (e.g. the Link Report's
         * "Review suggestions" link: &origin=inbound&status=pending).
         *
         * A value is only applied when the filter dropdown actually has that option,
         * so an unknown value in the URL is ignored.
         *
         * @return {void}
         */
        applyUrlFilters: function () {
            var params = new URLSearchParams(window.location.search);
            var status = params.get('status') || '';
            var origin = params.get('origin') || '';

            // Status filter: remember it and reflect it in the dropdown.
            if (status && $('#aips-il-status-filter option[value="' + status + '"]').length) {
                this.currentStatus = status;
                $('#aips-il-status-filter').val(status);
            }

            // Direction filter (inbound / outbound): same approach.
            if (origin && $('#aips-il-origin-filter option[value="' + origin + '"]').length) {
                this.currentOrigin = origin;
                $('#aips-il-origin-filter').val(origin);
            }
        },

        /**
         * Bind all UI event listeners.
         *
         * Everything is delegated from `document`, so rows and modal content that are
         * re-rendered later keep working without re-binding.
         *
         * @return {void}
         */
        bindEvents: function () {
            // Tab navigation
            $(document).on('click', '.aips-tab-link', this.onTabClick.bind(this));

            // Status filter
            $(document).on('change', '#aips-il-status-filter', this.onStatusFilterChange.bind(this));
            $(document).on('change', '#aips-il-origin-filter', this.onOriginFilterChange.bind(this));

            // Search
            $(document).on('input', '#aips-il-search', this.onSearchInput.bind(this));
            $(document).on('click', '#aips-il-search-clear', this.onSearchClear.bind(this));

            // Background indexing state pushed by background-processes.js (Heartbeat)
            $(document).on('aips:bg-update', this.onBackgroundUpdate.bind(this));

            // Per-post generation
            $(document).on('click', '#aips-generate-for-post-btn', this.onGenerateForPostClick.bind(this));
            $(document).on('click', '#aips-reindex-post-btn', this.onReindexPostClick.bind(this));

            // Row actions (delegated)
            $(document).on('click', '.aips-il-accept-btn', this.onAcceptClick.bind(this));
            $(document).on('click', '.aips-il-reject-btn', this.onRejectClick.bind(this));
            $(document).on('click', '.aips-il-delete-btn', this.onDeleteClick.bind(this));
            $(document).on('click', '.aips-il-edit-anchor-btn', this.onEditAnchorClick.bind(this));

            // Insert Link button (accepted rows)
            $(document).on('click', '.aips-il-insert-btn', this.onInsertClick.bind(this));

            // Insert modal: Insert button on a single suggestion entry
            $(document).on('click', '.aips-il-modal-insert-btn', this.onModalInsertClick.bind(this));

            // Insert modal: Apply button on a location result — preview only, no immediate save
            $(document).on('click', '.aips-il-apply-location-btn', this.onApplyLocationClick.bind(this));

            // Insert modal: Update Post button — commit all pending insertions to DB
            $(document).on('click', '#aips-update-post-btn', this.onUpdatePostClick.bind(this));

            // Preview insertion: hover to show actions
            $(document).on('mouseenter', '.aips-il-preview-insertion', this.onPreviewHoverIn.bind(this));
            $(document).on('mouseleave', '.aips-il-preview-insertion', this.onPreviewHoverOut.bind(this));

            // Preview insertion: edit anchor / undo (remove) buttons
            $(document).on('click', '.aips-il-preview-edit-btn', this.onPreviewEditClick.bind(this));
            $(document).on('click', '.aips-il-preview-undo-btn', this.onPreviewUndoClick.bind(this));

            // Anchor modal
            $(document).on('click', '.aips-modal-close', this.onModalClose.bind(this));
            $(document).on('click', '#aips-anchor-modal-save', this.onAnchorModalSave.bind(this));

            // Pagination
            $(document).on('click', '.aips-page-btn', this.onPageClick.bind(this));
        },

        // -----------------------------------------------------------------------
        // Event handlers
        // -----------------------------------------------------------------------

        /**
         * Switch the visible tab panel.
         *
         * @param {Event} e Click event from a `.aips-tab-link` element.
         * @return {void}
         */
        onTabClick: function (e) {
            e.preventDefault();
            var tab = $(e.currentTarget).data('tab');

            // Highlight the clicked tab link...
            $('.aips-tab-link').removeClass('active');
            $(e.currentTarget).addClass('active');

            // ...then hide every panel and show only the matching one (aria-hidden keeps screen readers in step).
            $('.aips-tab-content').hide().attr('aria-hidden', 'true');
            $('#' + tab + '-tab').show().attr('aria-hidden', 'false');
        },

        /**
         * Reload the suggestions table when the status filter changes.
         *
         * @param {Event} e Change event from `#aips-il-status-filter`.
         * @return {void}
         */
        onStatusFilterChange: function (e) {
            this.currentStatus = $(e.currentTarget).val();
            this.currentPage   = 1;
            this.loadSuggestions();
        },

        /**
         * Reload the suggestions table when the direction filter changes.
         *
         * @param {Event} e Change event from `#aips-il-origin-filter`.
         * @return {void}
         */
        onOriginFilterChange: function (e) {
            this.currentOrigin = $(e.currentTarget).val();
            this.currentPage   = 1;
            this.loadSuggestions();
        },

        /**
         * Debounced live search: reload suggestions 400 ms after the user stops typing.
         *
         * @param {Event} e Input event from `#aips-il-search`.
         * @return {void}
         */
        onSearchInput: function (e) {
            var self = this;
            var val  = $(e.currentTarget).val().trim();

            // The clear (x) button only shows while there is text.
            $('#aips-il-search-clear').toggle(val.length > 0);

            // Restart the 400 ms timer on every keystroke; only the last one triggers a reload.
            clearTimeout(self._searchTimer);
            self._searchTimer = setTimeout(function () {
                self.currentSearch = val;
                self.currentPage   = 1;
                self.loadSuggestions();
            }, 400);
        },

        /**
         * Clear the search field and reload the suggestions table.
         *
         * @param {Event} e Click event from `#aips-il-search-clear`.
         * @return {void}
         */
        onSearchClear: function (e) {
            // Emptying the box and firing `input` reuses the debounced search path above.
            $('#aips-il-search').val('').trigger('input');
        },

        /**
         * Keep the index status line in step with the Content Indexer's background run.
         *
         * @param {Event}  e     The aips:bg-update event.
         * @param {Array}  list  Snapshots from the server.
         * @param {Object} state Snapshots keyed by process key.
         * @return {void}
         */
        onBackgroundUpdate: function (e, list, state) {
            // Only the Content Indexer's run is relevant on this page.
            var snap = state && state.content_indexing;
            if (!snap) {
                return;
            }

            // Refresh the real indexed/total numbers while the job runs, and once more when it stops.
            if (snap.is_active || this._indexingWasActive) {
                this.refreshStatus();
            }
            this._indexingWasActive = !!snap.is_active;
        },

        /**
         * Generate suggestions for the entered post ID.
         *
         * @param {Event} e Click event from `#aips-generate-for-post-btn`.
         * @return {void}
         */
        onGenerateForPostClick: function (e) {
            this.generateForPost();
        },

        /**
         * Re-index the entered post ID.
         *
         * @param {Event} e Click event from `#aips-reindex-post-btn`.
         * @return {void}
         */
        onReindexPostClick: function (e) {
            this.reindexPost();
        },

        /**
         * Accept a suggestion row.
         *
         * @param {Event} e Click event from an `.aips-il-accept-btn` element.
         * @return {void}
         */
        onAcceptClick: function (e) {
            var $btn = $(e.currentTarget);
            this.updateStatus($btn.data('id'), 'accepted', $btn.closest('tr'));
        },

        /**
         * Reject a suggestion row.
         *
         * @param {Event} e Click event from an `.aips-il-reject-btn` element.
         * @return {void}
         */
        onRejectClick: function (e) {
            var $btn = $(e.currentTarget);
            this.updateStatus($btn.data('id'), 'rejected', $btn.closest('tr'));
        },

        /**
         * Ask for confirmation then delete a suggestion row.
         *
         * @param {Event} e Click event from an `.aips-il-delete-btn` element.
         * @return {void}
         */
        onDeleteClick: function (e) {
            var $btn = $(e.currentTarget);
            var id = $btn.data('id');
            var $row = $btn.closest('tr');
            var self = this;

            // Deleting is permanent, so confirm first; the delete only runs from the "Delete" button.
            AIPS.Utilities.confirm(
                aipsInternalLinksL10n.confirmDelete || 'Delete this suggestion?',
                'Delete Suggestion',
                [
                    { label: 'Cancel', className: 'aips-btn aips-btn-secondary' },
                    {
                        label: 'Delete',
                        className: 'aips-btn aips-btn-danger-solid',
                        action: function () {
                            self.deleteSuggestion(id, $row);
                        }
                    }
                ]
            );
        },

        /**
         * Open the anchor-text edit modal for the clicked suggestion.
         *
         * @param {Event} e Click event from an `.aips-il-edit-anchor-btn` element.
         * @return {void}
         */
        onEditAnchorClick: function (e) {
            // Fill the shared anchor modal with this row's id and current anchor text.
            // The "table" context tells the save handler to persist via AJAX (not just update a preview).
            var $btn = $(e.currentTarget);
            $('#aips-anchor-modal-id').val($btn.data('id'));
            $('#aips-anchor-modal-text').val($btn.data('anchor'));
            $('#aips-anchor-modal-context').val('table');
            $('#aips-anchor-modal').show();
            $('#aips-anchor-modal-text').focus();
        },

        /**
         * Open the Insert Link modal for the clicked suggestion row.
         *
         * @param {Event} e Click event from an `.aips-il-insert-btn` element.
         * @return {void}
         */
        onInsertClick: function (e) {
            this.openInsertModal($(e.currentTarget).data('id'));
        },

        /**
         * Find insertion locations for the single suggestion inside the Insert modal.
         *
         * @param {Event} e Click event from an `.aips-il-modal-insert-btn` element.
         * @return {void}
         */
        onModalInsertClick: function (e) {
            this.findInsertLocations(parseInt($(e.currentTarget).data('id'), 10));
        },

        /**
         * Preview an insertion at the chosen location without saving to the DB.
         *
         * @param {Event} e Click event from an `.aips-il-apply-location-btn` element.
         * @return {void}
         */
        onApplyLocationClick: function (e) {
            // The Apply button carries the suggestion id plus the original text to find and its replacement.
            var $btn    = $(e.currentTarget);
            var sid     = parseInt($btn.data('suggestion-id'), 10);
            var match   = $btn.data('match');
            var replace = $btn.data('replace');
            this.applyInsertionPreview(sid, match, replace, $btn);
        },

        /**
         * Close the modal associated with the clicked close control.
         * Resets any pending preview insertions when the Insert modal is closed.
         *
         * @param {Event} e Click event from an `.aips-modal-close` element.
         * @return {void}
         */
        onModalClose: function (e) {
            // Close the modal the button belongs to; fall back to closing both known modals below.
            var $modal = $(e.currentTarget).closest('.aips-modal');

            if ($modal.length) {
                // Reset pending insertions when the Insert modal is closed.
                if ($modal.is('#aips-insert-modal')) {
                    this._pendingInsertions = [];
                    this._previewPlainText  = '';
                    this._previewPostId     = 0;
                    $('#aips-pending-count').text('');
                }

                $modal.hide();
                return;
            }

            $('#aips-anchor-modal, #aips-insert-modal').hide();
        },

        /**
         * Save the edited anchor text from the modal.
         * When the modal was opened from a preview insertion, update the local
         * state without making an AJAX call; otherwise, persist to the database.
         *
         * @param {Event} e Click event from `#aips-anchor-modal-save`.
         * @return {void}
         */
        onAnchorModalSave: function (e) {
            // "preview" = editing an insertion that is not saved yet (local state only);
            // "table" = editing a stored suggestion (persist with an AJAX call).
            var context = $('#aips-anchor-modal-context').val();
            if (context === 'preview') {
                var id         = parseInt($('#aips-anchor-modal-id').val(), 10);
                var anchorText = $('#aips-anchor-modal-text').val().trim();
                if (!id) { return; }
                this.editPreviewAnchor(id, anchorText);
            } else {
                this.saveAnchorText();
            }
        },

        /**
         * Navigate to the clicked page.
         *
         * @param {Event} e Click event from a `.aips-page-btn` element.
         * @return {void}
         */
        onPageClick: function (e) {
            // Ignore clicks on the page that is already showing.
            var page = parseInt($(e.currentTarget).data('page'), 10);
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.loadSuggestions();
            }
        },

        /**
         * Commit all pending preview insertions to the post.
         *
         * @param {Event} e Click event from `#aips-update-post-btn`.
         * @return {void}
         */
        onUpdatePostClick: function (e) {
            this.saveAllInsertions();
        },

        /**
         * Show the hover action buttons when entering a preview insertion span.
         *
         * @param {Event} e Mouseenter event on an `.aips-il-preview-insertion` element.
         * @return {void}
         */
        onPreviewHoverIn: function (e) {
            $(e.currentTarget).find('.aips-il-preview-actions').stop(true).show();
        },

        /**
         * Hide the hover action buttons when leaving a preview insertion span.
         *
         * @param {Event} e Mouseleave event on an `.aips-il-preview-insertion` element.
         * @return {void}
         */
        onPreviewHoverOut: function (e) {
            $(e.currentTarget).find('.aips-il-preview-actions').stop(true).hide();
        },

        /**
         * Open the anchor edit modal for a preview insertion link.
         *
         * @param {Event} e Click event from an `.aips-il-preview-edit-btn` element.
         * @return {void}
         */
        onPreviewEditClick: function (e) {
            // Keep the click from reaching the preview span underneath.
            e.stopPropagation();
            var sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
            var currentAnchor = '';

            // Find this suggestion's pending insertion to pre-fill the modal with its anchor text.
            for (var i = 0; i < this._pendingInsertions.length; i++) {
                if (this._pendingInsertions[i].suggestionId === sid) {
                    currentAnchor = this._pendingInsertions[i].anchorText;
                    break;
                }
            }

            // Open the shared anchor modal in "preview" mode (no AJAX on save).
            $('#aips-anchor-modal-id').val(sid);
            $('#aips-anchor-modal-text').val(currentAnchor);
            $('#aips-anchor-modal-context').val('preview');
            $('#aips-anchor-modal').show();
            $('#aips-anchor-modal-text').focus();
        },

        /**
         * Remove a preview insertion and re-render the content preview.
         *
         * @param {Event} e Click event from an `.aips-il-preview-undo-btn` element.
         * @return {void}
         */
        onPreviewUndoClick: function (e) {
            e.stopPropagation();
            var sid = parseInt($(e.currentTarget).data('suggestion-id'), 10);
            this.undoInsertionPreview(sid);
        },

        // -----------------------------------------------------------------------
        // Data loading
        // -----------------------------------------------------------------------

        /**
         * Load and render the suggestions table.
         *
         * Requests the current page with the active status / direction / search filters,
         * then renders one row per suggestion and the pagination controls.
         *
         * @return {void}
         */
        loadSuggestions: function () {
            var self   = this;
            var $tbody = $('#aips-suggestions-tbody');

            // Show a loading row while the request is in flight.
            $tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-loading', {
                message: aipsInternalLinksL10n.loading,
            }));

            $.post(aipsAjax.ajaxUrl, {
                action:   'aips_internal_links_get_suggestions',
                nonce:    aipsInternalLinksL10n.nonce,
                page:     self.currentPage,
                per_page: self.perPage,
                status:   self.currentStatus,
                search:   self.currentSearch,
                origin:   self.currentOrigin,
            }, function (response) {
                // Server reported an error: replace the table body with the error message.
                if (!response.success) {
                    $tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
                        message: aipsInternalLinksL10n.errorLoading,
                    }));
                    return;
                }

                var data = response.data;

                // No results: show the "no suggestions" message and hide the pager.
                if (!data.items || data.items.length === 0) {
                    $tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
                        message: aipsInternalLinksL10n.noSuggestions,
                    }));
                    self.renderPagination(0, 0);
                    return;
                }

                // Replace the loading row with one rendered row per suggestion, then draw the pager.
                $tbody.html('');
                $.each(data.items, function (i, item) {
                    $tbody.append(self.renderRow(item));
                });

                self.renderPagination(data.total, data.total_pages);
            }).fail(function () {
                // Network / HTTP failure: same error message as a server-side error.
                $tbody.html(AIPS.Templates.render('aips-tmpl-il-tbody-message', {
                    message: aipsInternalLinksL10n.errorLoading,
                }));
            });
        },

        /**
         * Render a single suggestions table row.
         *
         * @param {Object} item Suggestion data object.
         * @return {string} HTML string for the row.
         */
        renderRow: function (item) {
            var l10n   = aipsInternalLinksL10n;
            var rawScore = parseFloat(item.similarity_score) || 0;

            // Anchor text, escaped; a placeholder in muted italics when none is set.
            var anchor = item.anchor_text
                ? AIPS.Templates.escape(item.anchor_text)
                : '<em class="aips-text-muted">' + AIPS.Templates.escape(l10n.noAnchor) + '</em>';

            // "Match" chip. A score of 0 means no semantic score exists (the post matched on the anchor
            // phrase alone), not that the posts are 0% similar. Colours: >= 85% green, >= 70% blue, otherwise grey.
            var match = rawScore > 0
                ? AIPS.Templates.render('aips-tmpl-il-chip', {
                    cls:   rawScore >= 0.85 ? 'aips-badge-success' : (rawScore >= 0.7 ? 'aips-badge-info' : 'aips-badge-neutral'),
                    label: Math.round(rawScore * 100) + '%',
                    title: l10n.chipSimilarityTip,
                })
                : AIPS.Templates.render('aips-tmpl-il-chip', {
                    cls:   'aips-badge-neutral',
                    label: l10n.chipKeyword,
                    title: l10n.chipKeywordTip,
                });

            // Direction chip: inbound (another post links to this one) or outbound.
            var isInbound  = item.origin === 'inbound';
            var originChip = AIPS.Templates.render('aips-tmpl-il-chip', {
                cls:   isInbound ? 'aips-badge-info' : 'aips-badge-neutral',
                label: isInbound ? l10n.chipInbound : l10n.chipOutbound,
                title: isInbound ? l10n.chipInboundTip : l10n.chipOutboundTip,
            });

            // The Status filter already says which status is listed, except for "All".
            var statusChip = this.currentStatus === ''
                ? AIPS.Templates.render('aips-tmpl-il-chip', {
                    cls:   'aips-status-' + item.status,
                    label: this.getStatusLabel(item.status),
                    title: '',
                })
                : '';

            // Post titles fall back to "(#id)" for posts that were deleted or have no title.
            var sourceTitle = item.source_post_title || '(#' + item.source_post_id + ')';
            var targetTitle = item.target_post_title || '(#' + item.target_post_id + ')';

            // Link each post to its edit screen when a URL is available, otherwise show plain escaped text.
            var source = item.source_edit_url
                ? AIPS.Templates.render('aips-tmpl-il-post-link', {
                    url:   item.source_edit_url,
                    title: sourceTitle,
                })
                : AIPS.Templates.escape(sourceTitle);

            var target = item.target_edit_url
                ? AIPS.Templates.render('aips-tmpl-il-post-link', {
                    url:   item.target_edit_url,
                    title: targetTitle,
                })
                : AIPS.Templates.escape(targetTitle);

            // Action buttons depend on status: pending can be accepted/rejected, accepted can be inserted;
            // every row can be edited or deleted.
            var actions = '';

            if (item.status === 'pending') {
                actions += AIPS.Templates.render('aips-tmpl-il-actions-pending', {
                    id:           item.id,
                    acceptLabel:  aipsInternalLinksL10n.acceptAction,
                    rejectLabel:  aipsInternalLinksL10n.rejectAction,
                    acceptTip:    aipsInternalLinksL10n.tipAccept,
                    rejectTip:    aipsInternalLinksL10n.tipReject,
                });
            }

            if (item.status === 'accepted') {
                actions += AIPS.Templates.render('aips-tmpl-il-actions-accepted', {
                    id:          item.id,
                    insertLabel: aipsInternalLinksL10n.insertLink,
                    insertTip:   aipsInternalLinksL10n.tipInsert,
                });
            }

            actions += AIPS.Templates.render('aips-tmpl-il-actions-edit-delete', {
                id:          item.id,
                anchor:      item.anchor_text || '',
                editLabel:   aipsInternalLinksL10n.editAnchorText,
                deleteLabel: aipsInternalLinksL10n.deleteSuggestion,
                editTip:     aipsInternalLinksL10n.tipEdit,
                deleteTip:   aipsInternalLinksL10n.tipDelete,
            });

            // Assemble the row from the pre-escaped pieces above.
            return AIPS.Templates.renderRaw('aips-tmpl-il-suggestion-row', {
                id:          item.id,
                source:      source,
                target:      target,
                match:       match,
                originChip:  originChip,
                anchorLabel: AIPS.Templates.escape(l10n.anchorLabel),
                anchor:      anchor,
                statusChip:  statusChip,
                actions:     actions,
            });
        },

        /**
         * Render pagination controls.
         *
         * Shows previous/next arrows and a window of up to five page buttons centered on the
         * current page. Hidden entirely when everything fits on one page.
         *
         * @param {number} total       Total item count.
         * @param {number} totalPages  Total page count.
         * @return {void}
         */
        renderPagination: function (total, totalPages) {
            var self     = this;
            var $wrap    = $('#aips-il-page-controls');
            var $toolbar = $('#aips-il-pagination');

            // Nothing to page through.
            if (totalPages <= 1) {
                $toolbar.hide();
                $wrap.html('');
                return;
            }

            $toolbar.show();
            var html = '';

            // "Previous" arrow (not on the first page).
            if (self.currentPage > 1) {
                html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
                    page:    self.currentPage - 1,
                    classes: 'aips-btn-secondary',
                    label:   '\u00ab',
                }) + ' ';
            }

            // Numbered buttons for the pages within two steps of the current one; the current page is highlighted.
            var start = Math.max(1, self.currentPage - 2);
            var end   = Math.min(totalPages, self.currentPage + 2);

            for (var p = start; p <= end; p++) {
                var classes = p === self.currentPage ? 'aips-btn-primary' : 'aips-btn-secondary';
                html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
                    page:    p,
                    classes: classes,
                    label:   p,
                }) + ' ';
            }

            // "Next" arrow (not on the last page).
            if (self.currentPage < totalPages) {
                html += AIPS.Templates.render('aips-tmpl-il-page-btn', {
                    page:    self.currentPage + 1,
                    classes: 'aips-btn-secondary',
                    label:   '\u00bb',
                });
            }

            $wrap.html(html);
        },

        // -----------------------------------------------------------------------
        // Actions
        // -----------------------------------------------------------------------

        /**
         * Generate suggestions for a specific post.
         *
         * Reads the post ID, maximum suggestion count and similarity threshold from the
         * Generate tab, asks the server to create suggestions, and reloads the table on success.
         *
         * @return {void}
         */
        generateForPost: function () {
            var self        = this;

            // Form values (the server falls back to 5 suggestions / 0.70 similarity when these are empty).
            var postId      = parseInt($('#aips-gen-post-id').val(), 10);
            var maxSugg     = parseInt($('#aips-gen-max-suggestions').val(), 10);
            var threshold   = parseFloat($('#aips-gen-threshold').val());
            var $btn        = $('#aips-generate-for-post-btn');
            var $feedback   = $('#aips-gen-feedback');

            // A post ID is required.
            if (!postId) {
                self.showGenerateFeedback(aipsInternalLinksL10n.invalidPostId, 'error');
                return;
            }

            // Clear any previous message before the new request.
            $feedback.hide();

            var req = $.post(aipsAjax.ajaxUrl, {
                action:          'aips_internal_links_generate_suggestions',
                nonce:           aipsInternalLinksL10n.nonce,
                post_id:         postId,
                max_suggestions: maxSugg || 5,
                threshold:       threshold || 0.70,
            }, function (response) {
                if (response.success) {
                    // Show the server's summary, then reload so the new suggestions appear in the table.
                    self.showGenerateFeedback(response.data.message, 'success');
                    self.loadSuggestions();
                } else {
                    self.showGenerateFeedback(
                        (response.data && response.data.message) || 'Error.',
                        'error'
                    );
                }
            }).fail(function () {
                self.showGenerateFeedback(aipsInternalLinksL10n.requestFailed, 'error');
            });

            // Disable the button with a "Generating..." label until the request ends (2 minute safety timeout).
            AIPS.Utilities.withLock($btn, req, { loadingText: aipsInternalLinksL10n.generating, timeout: 120000 });
        },

        /**
         * Re-index a single post by ID (refresh its stored embedding).
         *
         * @return {void}
         */
        reindexPost: function () {
            var self      = this;
            var postId    = parseInt($('#aips-gen-post-id').val(), 10);
            var $btn      = $('#aips-reindex-post-btn');
            var $feedback = $('#aips-gen-feedback');

            // A post ID is required.
            if (!postId) {
                self.showGenerateFeedback(aipsInternalLinksL10n.invalidPostId, 'error');
                return;
            }

            $feedback.hide();

            var req = $.post(aipsAjax.ajaxUrl, {
                action:  'aips_internal_links_reindex_post',
                nonce:   aipsInternalLinksL10n.nonce,
                post_id: postId,
            }, function (response) {
                if (response.success) {
                    // Re-indexing also regenerates suggestions, so refresh both the table and the stat cards.
                    self.showGenerateFeedback(response.data.message, 'success');
                    self.loadSuggestions();
                    self.refreshStatus();
                } else {
                    self.showGenerateFeedback(
                        (response.data && response.data.message) || 'Error.',
                        'error'
                    );
                }
            });

            // Disable the button with a "Re-indexing..." label until the request ends (2 minute safety timeout).
            AIPS.Utilities.withLock($btn, req, { loadingText: aipsInternalLinksL10n.reindexing, timeout: 120000 });
        },

        /**
         * Update the status of a suggestion row.
         *
         * @param {number} id     Suggestion ID.
         * @param {string} status New status.
         * @param {jQuery} $row   Table row element.
         * @return {void}
         */
        updateStatus: function (id, status, $row) {
            var self = this;

            // Persist the new status (accepted / rejected) for this suggestion.
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_internal_links_update_status',
                nonce:  aipsInternalLinksL10n.nonce,
                id:     id,
                status: status,
            }, function (response) {
                if (response.success) {
                    // Reload to reflect updated status and re-render actions
                    self.loadSuggestions();
                    self.refreshStatus();
                    AIPS.Utilities.showToast(aipsInternalLinksL10n.statusUpdated, 'success');
                } else {
                    AIPS.Utilities.showToast(aipsInternalLinksL10n.statusUpdateFailed, 'error');
                }
            });
        },

        /**
         * Delete a suggestion row.
         *
         * @param {number} id   Suggestion ID.
         * @param {jQuery} $row Table row element.
         * @return {void}
         */
        deleteSuggestion: function (id, $row) {
            var self = this;

            // Delete on the server first; the row is only removed from the page once that succeeds.
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_internal_links_delete',
                nonce:  aipsInternalLinksL10n.nonce,
                id:     id,
            }, function (response) {
                if (response.success) {
                    // A suggestion is two table rows (post pair + anchor/actions).
                    $('#aips-suggestions-tbody tr[data-id="' + id + '"]').fadeOut(200, function () { $(this).remove(); });
                    self.refreshStatus();
                } else {
                    AIPS.Utilities.showToast(aipsInternalLinksL10n.errorDeleting, 'error');
                }
            });
        },

        /**
         * Save the edited anchor text from the modal.
         *
         * @return {void}
         */
        saveAnchorText: function () {
            var self       = this;
            var id         = parseInt($('#aips-anchor-modal-id').val(), 10);
            var anchorText = $('#aips-anchor-modal-text').val().trim();

            // Nothing to save without a suggestion id.
            if (!id) { return; }

            $.post(aipsAjax.ajaxUrl, {
                action:      'aips_internal_links_update_anchor',
                nonce:       aipsInternalLinksL10n.nonce,
                id:          id,
                anchor_text: anchorText,
            }, function (response) {
                $('#aips-anchor-modal').hide();

                if (response.success) {
                    // Update cell in table (a suggestion spans two rows, but only one has the anchor cell)
                    var $anchorCell = $('tr[data-id="' + id + '"] .aips-il-anchor-cell');
                    if (anchorText) {
                        $anchorCell.text(anchorText);
                    } else {
                        $anchorCell.html('<em class="aips-text-muted">' + AIPS.Templates.escape(aipsInternalLinksL10n.noAnchor) + '</em>');
                    }
                    // Update data attribute on edit button
                    $('tr[data-id="' + id + '"] .aips-il-edit-anchor-btn').data('anchor', anchorText);
                    AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdated, 'success');
                } else {
                    AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdateFailed, 'error');
                }
            });
        },

        // -----------------------------------------------------------------------
        // Status refresh
        // -----------------------------------------------------------------------

        /**
         * Refresh the indexing / status stats without reloading the full table.
         *
         * Updates the "indexed / total" card, its progress bar, the stale-embeddings note
         * and the pending / accepted / rejected counters.
         *
         * @return {void}
         */
        refreshStatus: function () {
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_internal_links_get_status',
                nonce:  aipsInternalLinksL10n.nonce,
            }, function (response) {
                if (!response.success) { return; }

                var idx    = response.data.indexing;
                var counts = response.data.link_counts;

                // Indexing card: counts, progress bar, and the "stale embeddings" line (hidden when none).
                if (idx) {
                    $('#aips-stat-indexed').html(AIPS.Templates.render('aips-tmpl-il-indexed-stat', {
                        indexed: idx.indexed,
                        total:   idx.total_posts,
                    }));
                    $('#aips-index-progress-bar').css('width', idx.percent + '%');
                    $('#aips-index-stale-line').prop('hidden', !(idx.stale > 0));
                }

                // Suggestion counters by status.
                if (counts) {
                    $('#aips-stat-pending').text(counts.pending || 0);
                    $('#aips-stat-accepted').text(counts.accepted || 0);
                    $('#aips-stat-rejected').text(counts.rejected || 0);
                }
            });
        },

        // -----------------------------------------------------------------------
        // Helpers
        // -----------------------------------------------------------------------

        /**
         * Return the human-readable label for a status slug.
         *
         * @param {string} status Status slug.
         * @return {string} Localized label.
         */
        getStatusLabel: function (status) {
            var map = {
                pending:  aipsInternalLinksL10n.pending,
                accepted: aipsInternalLinksL10n.accepted,
                rejected: aipsInternalLinksL10n.rejected,
                inserted: aipsInternalLinksL10n.inserted,
                reverted: aipsInternalLinksL10n.reverted,
            };
            return map[status] || status;
        },

        /**
         * Show a feedback message in the Generate tab.
         *
         * @param {string} message Message text.
         * @param {string} type    'success' or 'error'.
         * @return {void}
         */
        showGenerateFeedback: function (message, type) {
            // Swap the success/error styling for the new type, set the text (escaped by .text()) and show it.
            var $el = $('#aips-gen-feedback');
            $el.removeClass('aips-notice-success aips-notice-error')
                .addClass('aips-notice-' + type)
                .text(message)
                .show();
        },

        // -----------------------------------------------------------------------
        // Insert Link modal
        // -----------------------------------------------------------------------

        /**
         * Open the Insert Link modal and load post content + accepted suggestions.
         *
         * Resets the modal state, opens it with spinners, then fetches the source post's
         * content and its accepted suggestions to fill the suggestion list and the preview.
         *
         * @param {number} suggestionId Suggestion row ID.
         * @return {void}
         */
        openInsertModal: function (suggestionId) {
            var self = this;
            var spinnerHtml = AIPS.Templates.get('aips-tmpl-il-spinner');

            // Reset modal state.
            this._previewPostId      = 0;
            this._previewPlainText   = '';
            this._pendingInsertions  = [];
            this._suggestionDataMap  = {};

            // Show spinners in both panes, disable "Update Post" until something is applied, and open the modal.
            $('#aips-insert-suggestions-list').html(spinnerHtml);
            $('#aips-insert-modal').find('.aips-modal-content-body').html(spinnerHtml);
            $('#aips-insert-post-title').text('');
            $('#aips-update-post-btn').prop('disabled', true);
            $('#aips-pending-count').text('');
            $('#aips-insert-modal').show();

            $.post(aipsAjax.ajaxUrl, {
                action:        'aips_internal_links_get_post_for_insertion',
                nonce:         aipsInternalLinksL10n.nonce,
                suggestion_id: suggestionId,
            }, function (response) {
                // Load failed: show the error in the list pane and clear the content pane.
                if (!response.success) {
                    $('#aips-insert-suggestions-list').html(
                        AIPS.Templates.render('aips-tmpl-il-notice-error', {
                            message: (response.data && response.data.message) || aipsInternalLinksL10n.loadingFailed,
                        })
                    );
                    $('#aips-insert-modal').find('.aips-modal-content-body').html('');
                    return;
                }

                var data = response.data;

                self._previewPostId = data.post_id || 0;

                // Store plain-text version for the preview renderer.
                self._previewPlainText = $('<div>').html(data.post_content || '').text();

                // Post title in sub-header.
                $('#aips-insert-post-title').text(data.post_title || '');

                // Render suggestions list (also populates _suggestionDataMap).
                self.renderInsertSuggestions(data.suggestions || []);

                // Render initial post content preview.
                self.renderPreviewContent();
            }).fail(function () {
                // Network failure: same handling as a server-reported failure.
                $('#aips-insert-suggestions-list').html(
                    AIPS.Templates.render('aips-tmpl-il-notice-error', {
                        message: aipsInternalLinksL10n.loadingFailed,
                    })
                );
                $('#aips-insert-modal').find('.aips-modal-content-body').html('');
            });
        },

        /**
         * Render the accepted suggestions list inside the Insert modal and
         * populate the suggestion data map for use during preview insertion.
         *
         * @param {Array} suggestions Array of suggestion objects from the server.
         * @return {void}
         */
        renderInsertSuggestions: function (suggestions) {
            var self  = this;
            var $list = $('#aips-insert-suggestions-list');

            if (!suggestions || suggestions.length === 0) {
                $list.html(AIPS.Templates.render('aips-tmpl-il-notice-muted', {
                    message: aipsInternalLinksL10n.noInsertSuggestions,
                }));
                return;
            }

            // Build one card per accepted suggestion.
            var items = '';

            $.each(suggestions, function (i, s) {
                var suggestionId = parseInt(s.id, 10);
                var score        = Math.round(parseFloat(s.similarity_score) * 100) + '%';
                var title        = s.target_post_title || '#' + s.target_post_id;
                var anchor       = s.anchor_text || s.target_post_title || '';
                var targetUrl    = s.target_url || '';

                // Populate the suggestion data map so applyInsertionPreview can
                // retrieve anchor text and target URL without extra data attributes.
                self._suggestionDataMap[suggestionId] = {
                    anchorText: anchor,
                    targetUrl:  targetUrl,
                    title:      title,
                };

                // Optional "view target" link, only when the target post has a URL.
                var targetLinkHtml = targetUrl
                    ? AIPS.Templates.render('aips-tmpl-il-insert-target-link', { url: targetUrl })
                    : '';

                // Values are escaped here because the template is rendered raw (it embeds nested HTML).
                items += AIPS.Templates.renderRaw('aips-tmpl-il-insert-suggestion', {
                    suggestionId:            suggestionId,
                    title:                   AIPS.Templates.escape(title),
                    anchorLabel:             AIPS.Templates.escape(aipsInternalLinksL10n.anchorLabel),
                    anchor:                  AIPS.Templates.escape(anchor),
                    score:                   score,
                    targetLinkHtml:          targetLinkHtml,
                    insertBtn:               AIPS.Templates.escape(aipsInternalLinksL10n.insertBtn),
                    insertionLocationsLabel: AIPS.Templates.escape(aipsInternalLinksL10n.insertionLocationsLabel),
                });
            });

            $list.html(AIPS.Templates.renderRaw('aips-tmpl-il-suggestions-list', { items: items }));
        },

        /**
         * Call the AI to find insertion locations for a specific suggestion.
         *
         * @param {number} suggestionId Suggestion row ID.
         * @return {void}
         */
        findInsertLocations: function (suggestionId) {
            var self = this;

            // Elements of this suggestion's card: the locations panel, its list, spinner, count label and button.
            var $item = $('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
            var $panel = $item.find('.aips-il-inline-locations');
            var $list = $item.find('.aips-il-inline-locations-list');
            var $spinner = $item.find('.aips-il-inline-spinner');
            var $count = $item.find('.aips-il-inline-count');
            var $button = $item.find('.aips-il-modal-insert-btn');

            // Show a "finding locations" message, start the spinner and block repeat clicks while the AI works.
            $panel.show();
            $list.html(AIPS.Templates.render('aips-tmpl-il-notice-muted', {
                message: aipsInternalLinksL10n.findingLocations,
            }));
            $count.text('');
            $spinner.addClass('is-active');
            $button.prop('disabled', true);

            $.post(aipsAjax.ajaxUrl, {
                action:        'aips_internal_links_find_insert_locations',
                nonce:         aipsInternalLinksL10n.nonce,
                suggestion_id: suggestionId,
            }, function (response) {
                // Request finished: stop the spinner and re-enable the button.
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);

                if (!response.success) {
                    $list.html(AIPS.Templates.render('aips-tmpl-il-notice-error', {
                        message: (response.data && response.data.message) || aipsInternalLinksL10n.locationsFailed,
                    }));
                    $count.text('');
                    return;
                }

                // Counts for the debug label: requested, returned by the AI, and left after server-side validation.
                // Missing counts fall back to the number of locations received.
                var locations = response.data.locations || [];
                var requestedCount = parseInt(response.data.requested_count, 10) || locations.length;
                var aiReturnedCount = parseInt(response.data.ai_returned_count, 10);
                var validCount = parseInt(response.data.valid_count, 10);

                if (isNaN(aiReturnedCount)) {
                    aiReturnedCount = locations.length;
                }

                if (isNaN(validCount)) {
                    validCount = locations.length;
                }

                self.renderInsertLocations(suggestionId, locations, requestedCount, aiReturnedCount, validCount);
            }).fail(function () {
                // Network failure: reset the controls and show the generic error.
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                $list.html(AIPS.Templates.render('aips-tmpl-il-notice-error', {
                    message: aipsInternalLinksL10n.locationsFailed,
                }));
                $count.text('');
            });
        },

        /**
         * Render the AI-generated insertion location options.
         *
         * @param {number} suggestionId    Suggestion row ID (passed through to apply).
         * @param {Array}  locations       Array of location objects {reason, match_snippet, replacement_snippet}.
         * @param {number} requestedCount  Number requested from the server.
         * @param {number} aiReturnedCount Number returned by the AI before validation.
         * @param {number} validCount      Number remaining after validation.
         * @return {void}
         */
        renderInsertLocations: function (suggestionId, locations, requestedCount, aiReturnedCount, validCount) {
            var $item = $('.aips-il-suggestion-item[data-suggestion-id="' + suggestionId + '"]');
            var $list = $item.find('.aips-il-inline-locations-list');
            var $count = $item.find('.aips-il-inline-count');

            // "Showing X valid of Y AI suggestions" label.
            $count.text(
                AIPS.InternalLinks.formatCountLabel(validCount, aiReturnedCount)
            );

            // No usable locations: explain whether the AI returned nothing or everything failed validation.
            if (!locations || locations.length === 0) {
                var detailMessage = aiReturnedCount > 0
                    ? AIPS.InternalLinks.formatAiReturnedLabel(aiReturnedCount)
                    : aipsInternalLinksL10n.zeroSuggestionsReturned;

                $list.html(AIPS.Templates.render('aips-tmpl-il-no-locations', {
                    zeroReturned: detailMessage,
                    noLocations:  aiReturnedCount > 0 ? aipsInternalLinksL10n.invalidLocationsHint : aipsInternalLinksL10n.noLocations,
                }));
                return;
            }

            // One numbered option card per valid location.
            var html = '';

            $.each(locations, function (i, loc) {
                var num     = i + 1;
                var reason  = loc.reason || '';
                var preview = AIPS.InternalLinks.formatReplacementPreview(loc.replacement_snippet || '');

                // Why the AI chose this spot (only shown when it gave a reason).
                var reasonHtml = reason
                    ? AIPS.Templates.render('aips-tmpl-il-location-reason', {
                        reasonLabel: aipsInternalLinksL10n.reasonLabel,
                        reason:      reason,
                    })
                    : '';

                // Raw template: every value below is escaped first (match/replace snippets travel as data attributes).
                html += AIPS.Templates.renderRaw('aips-tmpl-il-location-card', {
                    optionLabel:          AIPS.Templates.escape(aipsInternalLinksL10n.optionLabel),
                    num:                  num,
                    reasonHtml:           reasonHtml,
                    withLinkLabel:        AIPS.Templates.escape(aipsInternalLinksL10n.withLinkLabel),
                    preview:              preview,
                    suggestionId:         parseInt(suggestionId, 10),
                    matchRaw:             AIPS.Templates.escape(loc.match_snippet || ''),
                    replaceRaw:           AIPS.Templates.escape(loc.replacement_snippet || ''),
                    applyBtn:             AIPS.Templates.escape(aipsInternalLinksL10n.applyBtn),
                });
            });

            $list.html(html);
        },

        /**
         * Format the debug label showing how many insertion suggestions were returned.
         *
         * @param {number} validCount     Number of valid suggestions returned.
         * @param {number} aiReturnedCount Number of suggestions returned by the AI.
         * @return {string} Human-readable summary.
         */
        formatCountLabel: function (validCount, aiReturnedCount) {
            var template = aipsInternalLinksL10n.returnedCountLabel || 'Showing %1$d valid of %2$d AI suggestions';

            return template
                .replace('%1$d', String(validCount))
                .replace('%2$d', String(aiReturnedCount));
        },

        /**
         * Format the label showing how many suggestions the AI returned before validation.
         *
         * @param {number} aiReturnedCount Number of suggestions returned by the AI.
         * @return {string} Human-readable summary.
         */
        formatAiReturnedLabel: function (aiReturnedCount) {
            var template = aipsInternalLinksL10n.aiSuggestionsReturned || 'AI returned %d suggestion(s)';

            return template.replace('%d', String(aiReturnedCount));
        },

        /**
         * Format a plain-text insertion preview for safe rendering in the modal.
         *
         * Highlights the [[anchor text]] marker without treating the snippet as
         * trusted HTML.
         *
         * @param {string} replacementSnippet Plain-text replacement snippet.
         * @return {string} Safe HTML preview string.
         */
        formatReplacementPreview: function (replacementSnippet) {
            var escaped = AIPS.Templates.escape(replacementSnippet || '');

            return escaped.replace(/\[\[(.*?)\]\]/g, '<mark style="background:#fff1c6;padding:0 2px;border-radius:2px;">$1</mark>');
        },

        /**
         * Apply a chosen location to the post content preview (local only).
         *
         * Validates that the match snippet exists in the current plain-text
         * preview and that this suggestion hasn't already been applied. Adds the
         * insertion to _pendingInsertions and re-renders the content preview.
         * No AJAX is made — use saveAllInsertions() to persist changes.
         *
         * @param {number} suggestionId      Suggestion row ID.
         * @param {string} matchSnippet      Exact text excerpt from the post.
         * @param {string} replacementSnippet Plain-text snippet with [[anchor marker]].
         * @param {jQuery} $btn              The Apply button element.
         * @return {void}
         */
        applyInsertionPreview: function (suggestionId, matchSnippet, replacementSnippet, $btn) {
            // Guard: duplicate application for the same suggestion.
            for (var i = 0; i < this._pendingInsertions.length; i++) {
                if (this._pendingInsertions[i].suggestionId === suggestionId) {
                    AIPS.Utilities.showToast(
                        aipsInternalLinksL10n.alreadyApplied || 'Already applied.',
                        'info'
                    );
                    return;
                }
            }

            // Guard: match snippet must be present in the plain-text preview.
            if (this._previewPlainText.indexOf(matchSnippet) === -1) {
                AIPS.Utilities.showToast(
                    aipsInternalLinksL10n.snippetNotFound || 'Text not found in content preview.',
                    'error'
                );
                return;
            }

            // Extract anchor text from [[anchor]] in the replacement snippet.
            var markerMatch = replacementSnippet.match(/\[\[([\s\S]*?)\]\]/);
            var anchorText  = markerMatch ? markerMatch[1] : '';

            if (!anchorText && this._suggestionDataMap[suggestionId]) {
                anchorText = this._suggestionDataMap[suggestionId].anchorText || '';
            }

            // Store the pending insertion.
            this._pendingInsertions.push({
                suggestionId:        suggestionId,
                matchSnippet:        matchSnippet,
                replacementSnippet:  replacementSnippet,
                anchorText:          anchorText,
            });

            // Re-render the preview and update the pending counter / Update Post button.
            this.renderPreviewContent();
            this.updatePendingCount();

            $('#aips-update-post-btn').prop('disabled', false);

            // Mark this Apply button as applied.
            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-yes" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
                AIPS.Templates.escape(aipsInternalLinksL10n.applied || 'Applied')
            );
        },

        /**
         * Render the post content preview div with all pending insertions highlighted.
         *
         * Starts from the stored plain-text copy, HTML-escapes it, then
         * substitutes each pending insertion's match snippet with a styled
         * <span> element that shows the anchor in green with hover action buttons.
         *
         * @return {void}
         */
        renderPreviewContent: function () {
            // Start from the escaped plain text so nothing from the post can inject markup.
            var html = AIPS.Templates.escape(this._previewPlainText || '');

            // Post has no text content: show the "no content" notice.
            if (!html) {
                $('#aips-insert-modal').find('.aips-modal-content-body').html(
                    AIPS.Templates.render('aips-tmpl-il-notice-muted', {
                        message: aipsInternalLinksL10n.noContent,
                    })
                );
                return;
            }

            // Apply the pending insertions one by one, each replacing its match snippet with the highlighted link preview.
            for (var i = 0; i < this._pendingInsertions.length; i++) {
                var ins          = this._pendingInsertions[i];
                var escapedMatch = AIPS.Templates.escape(ins.matchSnippet);

                // Search the already-modified HTML for the escaped match.
                // The match is looked up after HTML-escaping both the stored plain text
                // and the snippet, so standard HTML entities (&amp;, &lt;, etc.) round-trip
                // correctly.  Rare double-encoded entities (e.g. &amp;amp; in source)
                // may not match; in those cases the insertion is silently skipped in the
                // preview but will still be applied correctly on the server.
                //
                // Only the first occurrence is replaced (consistent with the server-side
                // apply_insertion() behaviour which also replaces the first occurrence).
                var idx = html.indexOf(escapedMatch);

                // Not found in the (already modified) HTML: skip it in the preview only.
                if (idx === -1) { continue; }

                // Split replacement snippet into before / anchor / after parts.
                var repSnippet  = ins.replacementSnippet || '';
                var parts       = repSnippet.match(/^([\s\S]*?)\[\[([\s\S]*?)\]\]([\s\S]*)$/);
                var before      = parts ? parts[1] : '';
                var anchor      = parts ? parts[2] : (ins.anchorText || '');
                var after       = parts ? parts[3] : '';

                var insertionHtml = AIPS.Templates.render('aips-tmpl-il-preview-insertion', {
                    suggestionId: ins.suggestionId,
                    matchEsc:     ins.matchSnippet,
                    before:       before,
                    anchor:       anchor,
                    after:        after,
                    editLabel:    aipsInternalLinksL10n.editInsertedLink || 'Edit anchor',
                    undoLabel:    aipsInternalLinksL10n.removeInsertedLink || 'Remove link',
                });

                // Replace only the first occurrence (avoids regex/$ issues).
                html = html.substring(0, idx) + insertionHtml + html.substring(idx + escapedMatch.length);
            }

            // Write the finished preview into the modal body.
            $('#aips-insert-modal').find('.aips-modal-content-body').html(html);
        },

        /**
         * Remove a pending preview insertion and re-render.
         *
         * Re-enables the Apply button(s) for the removed suggestion so the user
         * can re-apply a different location if desired.
         *
         * @param {number} suggestionId Suggestion row ID to remove.
         * @return {void}
         */
        undoInsertionPreview: function (suggestionId) {
            var idx = -1;

            for (var i = 0; i < this._pendingInsertions.length; i++) {
                if (this._pendingInsertions[i].suggestionId === suggestionId) {
                    idx = i;
                    break;
                }
            }

            if (idx === -1) { return; }

            this._pendingInsertions.splice(idx, 1);
            this.renderPreviewContent();
            this.updatePendingCount();

            // Re-enable Apply buttons that belong to this suggestion.
            $('.aips-il-apply-location-btn[data-suggestion-id="' + suggestionId + '"]')
                .prop('disabled', false)
                .text(aipsInternalLinksL10n.applyBtn);

            if (this._pendingInsertions.length === 0) {
                $('#aips-update-post-btn').prop('disabled', true);
            }
        },

        /**
         * Update the anchor text for an already-applied preview insertion.
         *
         * Updates both the display label and the replacement_snippet's [[marker]]
         * so that saveAllInsertions() sends the correct anchor to the server.
         *
         * @param {number} suggestionId Suggestion row ID.
         * @param {string} newAnchor    New anchor text.
         * @return {void}
         */
        editPreviewAnchor: function (suggestionId, newAnchor) {
            // Strip any `]]` sequence from the anchor text to ensure the
            // [[marker]] format in the replacement snippet remains valid.
            var safeAnchor = String(newAnchor).replace(/\]\]/g, '');

            for (var i = 0; i < this._pendingInsertions.length; i++) {
                if (this._pendingInsertions[i].suggestionId === suggestionId) {
                    this._pendingInsertions[i].anchorText         = safeAnchor;
                    this._pendingInsertions[i].replacementSnippet = this._pendingInsertions[i].replacementSnippet.replace(
                        /\[\[[\s\S]*?\]\]/,
                        '[[' + safeAnchor + ']]'
                    );
                    break;
                }
            }

            this.renderPreviewContent();
            $('#aips-anchor-modal').hide();
            AIPS.Utilities.showToast(aipsInternalLinksL10n.anchorUpdated, 'success');
        },

        /**
         * Update the pending-insertions count label in the modal footer.
         *
         * @return {void}
         */
        updatePendingCount: function () {
            var n = this._pendingInsertions.length;

            if (n === 0) {
                $('#aips-pending-count').text('');
                return;
            }

            var template = n === 1
                ? (aipsInternalLinksL10n.pendingCountSingle || '%d pending insertion')
                : (aipsInternalLinksL10n.pendingCountPlural || '%d pending insertions');

            $('#aips-pending-count').text(template.replace('%d', String(n)));
        },

        /**
         * Commit all pending preview insertions to the post via a single AJAX call.
         *
         * On success the modal is closed, suggestions are reloaded, and status
         * counters are refreshed. On partial success (some errors), the success
         * toast is shown alongside a separate warning for the failed items.
         *
         * @return {void}
         */
        saveAllInsertions: function () {
            var self = this;

            // Nothing pending, nothing to save.
            if (this._pendingInsertions.length === 0) { return; }

            // Show an "Updating..." state on the button, remembering its normal contents to restore afterwards.
            var $btn            = $('#aips-update-post-btn');
            var originalBtnHtml = $btn.html();

            $btn.prop('disabled', true).html(
                '<span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align:middle;margin-top:-2px;"></span> ' +
                AIPS.Templates.escape(aipsInternalLinksL10n.updating || 'Updating…')
            );

            // Convert the pending insertions into the snake_case shape the server expects.
            var insertions = [];

            for (var i = 0; i < this._pendingInsertions.length; i++) {
                var ins = this._pendingInsertions[i];
                insertions.push({
                    suggestion_id:       ins.suggestionId,
                    match_snippet:       ins.matchSnippet,
                    replacement_snippet: ins.replacementSnippet,
                });
            }

            // Send all insertions in one request (JSON-encoded) so the post is updated in a single save.
            $.post(aipsAjax.ajaxUrl, {
                action:     'aips_internal_links_apply_bulk_insertions',
                nonce:      aipsInternalLinksL10n.nonce,
                insertions: JSON.stringify(insertions),
            }, function (response) {
                $btn.prop('disabled', false).html(originalBtnHtml);

                if (response.success) {
                    // Success: clear local state, close the modal and report the result.
                    self._pendingInsertions = [];
                    $('#aips-insert-modal').hide();
                    AIPS.Utilities.showToast(
                        response.data.message || aipsInternalLinksL10n.applied,
                        'success'
                    );

                    // Partial success: some insertions failed, so list those in a separate warning.
                    if (response.data.errors && response.data.errors.length > 0) {
                        AIPS.Utilities.showToast(response.data.errors.join(' '), 'warning');
                    }

                    // Statuses changed to "inserted", so reload the table and counters.
                    self.loadSuggestions();
                    self.refreshStatus();
                } else {
                    AIPS.Utilities.showToast(
                        (response.data && response.data.message) || aipsInternalLinksL10n.updateFailed,
                        'error'
                    );
                }
            }).fail(function () {
                // Network failure: restore the button so the user can retry.
                $btn.prop('disabled', false).html(originalBtnHtml);
                AIPS.Utilities.showToast(aipsInternalLinksL10n.updateFailed, 'error');
            });
        },
    };

    $(document).ready(function () {
        // Store original button texts before any modification (AIPS.Utilities.withLock swaps them while loading)
        AIPS.InternalLinks.originalGenerateText = $('#aips-generate-for-post-btn').text().trim();
        AIPS.InternalLinks.originalReindexText  = $('#aips-reindex-post-btn').text().trim();

        AIPS.InternalLinks.init();
    });

})(jQuery);
