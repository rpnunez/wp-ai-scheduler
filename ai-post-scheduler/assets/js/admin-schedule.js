/**
 * Schedule Controller
 *
 * Handles unified scheduling operations.
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    AIPS.Schedule = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('change', '#cb-select-all-unified', AIPS.Schedule.toggleAllUnified);
            $(document).on('change', '.aips-unified-checkbox', AIPS.Schedule.toggleUnifiedSelection);
            $(document).on('click', '#aips-unified-select-all', AIPS.Schedule.selectAllUnified);
            $(document).on('click', '#aips-unified-unselect-all', AIPS.Schedule.unselectAllUnified);
            $(document).on('click', '#aips-unified-bulk-apply', AIPS.Schedule.applyUnifiedBulkAction);
            $(document).on('change', '.aips-unified-toggle-schedule', AIPS.Schedule.toggleUnifiedSchedule);
            $(document).on('click', '.aips-unified-run-now', AIPS.Schedule.runNowUnified);
            $(document).on('click', '.aips-view-unified-history', AIPS.Schedule.viewUnifiedScheduleHistory);
            $(document).on('change', '#aips-unified-type-filter', AIPS.Schedule.filterUnifiedByType);
            $(document).on('keyup search', '#aips-unified-search', AIPS.Schedule.filterUnifiedSchedules);
            $(document).on('click', '#aips-unified-search-clear', AIPS.Schedule.clearUnifiedSearch);
            $(document).on('click', '.aips-clear-unified-search-btn', AIPS.Schedule.clearUnifiedSearch);
        },

        /**
         * Sync all unified-schedule checkboxes with the "select all" header.
         */
        toggleAllUnified: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-unified-checkbox:visible').prop('checked', isChecked);
            AIPS.Schedule.updateUnifiedBulkActions();
        },

        /**
         * Keep the "select all" in sync when individual rows are toggled.
         */
        toggleUnifiedSelection: function() {
            var total   = $('.aips-unified-checkbox:visible').length;
            var checked = $('.aips-unified-checkbox:visible:checked').length;
            $('#cb-select-all-unified').prop('checked', total > 0 && checked === total);
            AIPS.Schedule.updateUnifiedBulkActions();
        },

        /** Check all visible rows. */
        selectAllUnified: function() {
            $('.aips-unified-checkbox:visible').prop('checked', true);
            $('#cb-select-all-unified').prop('checked', true);
            AIPS.Schedule.updateUnifiedBulkActions();
        },

        /** Uncheck all rows. */
        unselectAllUnified: function() {
            $('.aips-unified-checkbox').prop('checked', false);
            $('#cb-select-all-unified').prop('checked', false);
            AIPS.Schedule.updateUnifiedBulkActions();
        },

        /**
         * Parse selected unified-schedule checkboxes and dispatch the chosen
         * bulk action.
         *
         * Supported actions: `run_now`, `pause`, `resume`, `delete`.
         *
         * @param {Event} e - Click event from `#aips-unified-bulk-apply`.
         */
        applyUnifiedBulkAction: function(e) {
            e.preventDefault();

            var action = $('#aips-unified-bulk-action').val();
            if (!action) {
                AIPS.Utilities.showToast(aipsScheduleL10n.selectBulkAction || 'Please select a bulk action.', 'warning');
                return;
            }

            var items = [];
            $('.aips-unified-checkbox:checked').each(function() {
                var $checkbox = $(this);
                var $row = $checkbox.closest('tr');
                var parts = $(this).val().split(':');
                if (parts.length === 2) {
                    items.push({
                        type: parts[0],
                        id: parseInt(parts[1], 10),
                        title: $row.data('title') || ('ID ' + parts[1]),
                        canDelete: String($row.data('can-delete')) === '1'
                    });
                }
            });

            if (items.length === 0) {
                AIPS.Utilities.showToast(aipsScheduleL10n.selectAtLeastOne || 'Please select at least one schedule.', 'warning');
                return;
            }

            if (action === 'run_now') {
                AIPS.Utilities.confirm(
                    aipsScheduleL10n.runSchedulesNow
                        ? aipsScheduleL10n.runSchedulesNow
                        : 'Run ' + items.length + ' schedule(s) now?',
                    'Run Now',
                    [
                        { label: aipsScheduleL10n.cancel || 'Cancel', className: 'aips-btn aips-btn-secondary' },
                        { label: aipsScheduleL10n.yesRunNow || 'Yes, Run Now', className: 'aips-btn aips-btn-primary', action: function() {
                            AIPS.Schedule.unifiedBulkRunNow(items);
                        }}
                    ]
                );
            } else if (action === 'pause') {
                AIPS.Schedule.unifiedBulkToggle(items, 0);
            } else if (action === 'resume') {
                AIPS.Schedule.unifiedBulkToggle(items, 1);
            } else if (action === 'delete') {
                AIPS.Schedule.confirmUnifiedBulkDelete(items);
            }
        },

        /**
         * Bulk run-now for mixed-type schedules via `aips_unified_bulk_run_now`.
         *
         * @param {Array<{type: string, id: number}>} items
         */
        unifiedBulkRunNow: function(items) {
            var $applyBtn = $('#aips-unified-bulk-apply');
            AIPS.Utilities.setButtonLoading($applyBtn, 'Running…');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_bulk_run_now',
                    nonce: aipsAjax.nonce,
                    items: items
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success', { duration: 8000 });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    AIPS.Utilities.resetButton($applyBtn);
                    AIPS.Schedule.updateUnifiedBulkActions();
                }
            });
        },

        /**
         * Bulk pause/resume mixed-type schedules via `aips_unified_bulk_toggle`.
         *
         * @param {Array<{type: string, id: number}>} items
         * @param {number} isActive 1 to resume, 0 to pause.
         */
        unifiedBulkToggle: function(items, isActive) {
            var $applyBtn = $('#aips-unified-bulk-apply');
            AIPS.Utilities.setButtonLoading($applyBtn, isActive ? 'Resuming\u2026' : 'Pausing\u2026');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_bulk_toggle',
                    nonce: aipsAjax.nonce,
                    items: items,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        var updatedItems = Array.isArray(data.updated_items) ? data.updated_items : null;
                        var failedItems  = Array.isArray(data.failed_items) ? data.failed_items : null;
                        var errorItems   = (!updatedItems && Array.isArray(data.errors)) ? data.errors : null;

                        var failedKeysMap = {};
                        var successfulItems;

                        // Normalize failed items from either failed_items or errors.
                        if (failedItems) {
                            failedItems.forEach(function(item) {
                                if (item && item.type && typeof item.id !== 'undefined') {
                                    failedKeysMap[item.type + ':' + item.id] = true;
                                }
                            });
                        } else if (errorItems) {
                            errorItems.forEach(function(item) {
                                if (item && item.type && typeof item.id !== 'undefined') {
                                    failedKeysMap[item.type + ':' + item.id] = true;
                                }
                            });
                            failedItems = errorItems;
                        }

                        if (updatedItems) {
                            // Backend explicitly told us which items were updated.
                            successfulItems = updatedItems;
                        } else if (Object.keys(failedKeysMap).length > 0) {
                            // Infer successes as "requested items minus failed".
                            successfulItems = items.filter(function(item) {
                                var key = item.type + ':' + item.id;
                                return !failedKeysMap[key];
                            });
                        } else {
                            // No per-item info available; fall back to previous behavior.
                            successfulItems = items;
                        }

                        AIPS.Utilities.showToast(data.message, 'success');

                        // Update each successful row's badge and toggle to reflect new state.
                        successfulItems.forEach(function(item) {
                            if (!item || !item.type || typeof item.id === 'undefined') {
                                return;
                            }
                            var key  = item.type + ':' + item.id;
                            var $row = $('tr[data-row-key="' + key + '"]');
                            if ($row.length) {
                                AIPS.Schedule.updateUnifiedRowStatus($row, isActive);
                                // In partial success, unselect only successful rows to keep failures visible.
                                if (Object.keys(failedKeysMap).length > 0) {
                                    $row.find('.aips-unified-select').prop('checked', false);
                                }
                            }
                        });

                        // If there were no known failures, keep existing behavior (unselect all).
                        if (Object.keys(failedKeysMap).length === 0) {
                            AIPS.Schedule.unselectAllUnified();
                        }
                    } else {
                        AIPS.Utilities.showToast((response.data && response.data.message) || aipsAdminL10n.errorOccurred, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    AIPS.Utilities.resetButton($applyBtn);
                    AIPS.Schedule.updateUnifiedBulkActions();
                }
            });
        },

        /**
         * Bulk-delete template schedules selected from the unified table.
         *
         * @param {Array<{type: string, id: number}>} items
         */
        unifiedBulkDelete: function(items) {
            var $applyBtn = $('#aips-unified-bulk-apply');
            $applyBtn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_bulk_delete',
                    nonce: aipsAjax.nonce,
                    items: items
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        var deletedItems = Array.isArray(data.deleted_items) ? data.deleted_items : [];

                        $('#aips-unified-bulk-action').val('');

                        deletedItems.forEach(function(item) {
                            if (!item || !item.type || typeof item.id === 'undefined') {
                                return;
                            }

                            var rowKey = item.type + ':' + item.id;
                            $('tr[data-row-key="' + rowKey + '"]').fadeOut(250, function() {
                                $(this).remove();
                                AIPS.Schedule.updateUnifiedBulkActions();
                            });
                        });

                        AIPS.Utilities.showToast(data.message || 'Schedules deleted successfully.', 'success');
                    } else {
                        AIPS.Utilities.showToast((response.data && response.data.message) || aipsScheduleL10n.failedToDeleteSchedules, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    $applyBtn.prop('disabled', false).text('Apply');
                    AIPS.Schedule.updateUnifiedBulkActions();
                }
            });
        },

        /**
         * Toggle a single unified schedule's active status.
         *
         * Bound to the `change` event on `.aips-unified-toggle-schedule`.
         */
        toggleUnifiedSchedule: function() {
            var $toggle  = $(this);
            var id       = $toggle.data('id');
            var type     = $toggle.data('type');
            var isActive = $toggle.is(':checked') ? 1 : 0;
            var $row     = $toggle.closest('tr');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_toggle',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Schedule.updateUnifiedRowStatus($row, isActive);
                    } else {
                        // Revert the toggle
                        $toggle.prop('checked', !isActive);
                        AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
                    }
                },
                error: function() {
                    $toggle.prop('checked', !isActive);
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                }
            });
        },

        /**
         * Update the status badge and toggle for a unified schedule row.
         *
         * @param {jQuery} $row     The `<tr>` element to update.
         * @param {number} isActive 1 = active/resumed, 0 = paused.
         */
        updateUnifiedRowStatus: function($row, isActive) {
            var $toggle  = $row.find('.aips-unified-toggle-schedule');
            var $wrapper = $row.find('.aips-schedule-status-wrapper');
            var $badge   = $wrapper.find('.aips-badge');
            var $icon    = $badge.find('.dashicons');

            $toggle.prop('checked', isActive === 1);
            $badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
            $icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
            $badge.contents().filter(function() { return this.nodeType === 3; }).remove();

            if (isActive) {
                $badge.addClass('aips-badge-success');
                $icon.addClass('dashicons-yes-alt');
                $icon.after(' Active');
            } else {
                $badge.addClass('aips-badge-neutral');
                $icon.addClass('dashicons-minus');
                $icon.after(' Paused');
            }
            $row.data('is-active', isActive);
        },

        /**
         * Run a single unified schedule immediately.
         *
         * Bound to click on `.aips-unified-run-now`.
         *
         * @param {Event} e - Click event.
         */
        runNowUnified: function(e) {
            e.preventDefault();

            var $btn  = $(this);
            var id    = $btn.data('id');
            var type  = $btn.data('type');

            if (!id || !type) { return; }

            AIPS.Utilities.setButtonLoading($btn, '<span class="dashicons dashicons-update aips-spin"></span>', { isHtml: true });

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_unified_run_now',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        var msg = AIPS.Utilities.escapeHtml(response.data.message || 'Executed successfully!');
                        if (response.data.edit_url) {
                            var safeEditUrl = AIPS.Utilities.sanitizeUrl(response.data.edit_url);
                            if (safeEditUrl) {
                                msg += ' <a href="' + AIPS.Utilities.escapeAttribute(safeEditUrl) + '" target="_blank">Edit Post</a>';
                            }
                        }
                        AIPS.Utilities.showToast(msg, 'success', { isHtml: true, duration: 8000 });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.generationFailed, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                },
                complete: function() {
                    AIPS.Utilities.resetButton($btn);
                }
            });
        },

        /**
         * Open the Schedule History modal and load entries for any schedule type.
         *
         * @param {Event} e - Click event from `.aips-view-unified-history`.
         */
        viewUnifiedScheduleHistory: function(e) {
            e.preventDefault();

            var $btn  = $(this);
            var id    = $btn.data('id');
            var type  = $btn.data('type');
            var name  = $btn.data('name') || id;
            var limit = $btn.data('limit') || 0;

            if (!id || !type) { return; }

            var $modal   = $('#aips-schedule-history-modal');
            var $title   = $modal.find('#aips-schedule-history-modal-title');
            var $loading = $modal.find('#aips-schedule-history-loading');
            var $empty   = $modal.find('#aips-schedule-history-empty');
            var $list    = $modal.find('#aips-schedule-history-list');

            $title.text('Recent History: ' + name);
            $loading.show();
            $empty.hide();
            $list.hide().empty();
            $modal.show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_unified_schedule_history',
                    nonce: aipsAjax.nonce,
                    id: id,
                    type: type,
                    limit: limit
                },
                success: function(response) {
                    $loading.hide();

                    if (!response.success) {
                        AIPS.Utilities.showToast(response.data.message || aipsAdminL10n.errorOccurred, 'error');
                        $modal.hide();
                        return;
                    }

                    var entries = response.data.entries;
                    if (!entries || entries.length === 0) {
                        $empty.show();
                        return;
                    }

                    var iconMap = {
                        'schedule_created':          { icon: 'dashicons-plus-alt',      cls: 'aips-timeline-created'  },
                        'schedule_updated':          { icon: 'dashicons-edit',           cls: 'aips-timeline-updated'  },
                        'schedule_enabled':          { icon: 'dashicons-yes-alt',        cls: 'aips-timeline-enabled'  },
                        'schedule_disabled':         { icon: 'dashicons-minus',          cls: 'aips-timeline-disabled' },
                        'schedule_executed':         { icon: 'dashicons-controls-play',  cls: 'aips-timeline-executed' },
                        'manual_schedule_started':   { icon: 'dashicons-controls-play',  cls: 'aips-timeline-executed' },
                        'manual_schedule_completed': { icon: 'dashicons-yes',            cls: 'aips-timeline-success'  },
                        'manual_schedule_failed':    { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
                        'schedule_failed':           { icon: 'dashicons-warning',        cls: 'aips-timeline-error'    },
                        'post_published':            { icon: 'dashicons-media-document', cls: 'aips-timeline-success'  },
                        'post_draft':                { icon: 'dashicons-media-document', cls: 'aips-timeline-draft'    },
                        'post_generated':            { icon: 'dashicons-media-document', cls: 'aips-timeline-draft'    },
                        'author_topic_generation':   { icon: 'dashicons-tag',            cls: 'aips-timeline-executed' },
                        'topic_post_generation':     { icon: 'dashicons-admin-users',    cls: 'aips-timeline-executed' },
                    };
                    var defaultIcon = { icon: 'dashicons-info', cls: '' };

                    entries.forEach(function(entry) {
                        var info    = iconMap[entry.event_type] || defaultIcon;
                        var isError = (entry.history_type_id === 2 || entry.event_status === 'failed');
                        if (isError && !info.cls) {
                            info = { icon: 'dashicons-warning', cls: 'aips-timeline-error' };
                        }

                        var $item    = $('<li>', { 'class': 'aips-timeline-item ' + info.cls });
                        var $icon    = $('<span>', { 'class': 'aips-timeline-icon', 'aria-hidden': 'true' })
                                           .append($('<span>', { 'class': 'dashicons ' + info.icon }));
                        var $content = $('<div>', { 'class': 'aips-timeline-content' });
                        var $msg     = $('<p>', { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
                        var $time    = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
                                           .text(entry.timestamp);

                        $content.append($msg).append($time);
                        $item.append($icon).append($content);
                        $list.append($item);
                    });

                    $list.show();
                },
                error: function() {
                    $loading.hide();
                    AIPS.Utilities.showToast(aipsAdminL10n.errorTryAgain, 'error');
                    $modal.hide();
                }
            });
        },

        /**
         * Navigate to the schedules page filtered by type when the type
         * dropdown changes.
         *
         * @param {Event} e - Change event from `#aips-unified-type-filter`.
         */
        filterUnifiedByType: function(e) {
            var type = $(this).val();
            var url  = window.location.href.split('?')[0];
            var params = new URLSearchParams(window.location.search);
            params.delete('schedule_type');
            if (type) {
                params.set('schedule_type', type);
            }
            var qs = params.toString();
            window.location.href = url + (qs ? '?' + qs : '');
        },

        /**
         * Live-filter the unified schedule table rows by the search term.
         *
         * @param {Event} e - Keyup / search event from `#aips-unified-search`.
         */
        filterUnifiedSchedules: function(e) {
            var term = $(this).val().toLowerCase().trim();
            var $clear = $('#aips-unified-search-clear');
            $clear.toggle(term.length > 0);

            var $rows = $('.aips-unified-row');
            var found = 0;

            $rows.each(function() {
                var text = $(this).text().toLowerCase();
                var match = !term || text.indexOf(term) !== -1;
                $(this).toggle(match);
                if (match) { found++; }
            });

            $('#aips-unified-search-no-results').toggle(found === 0 && $rows.length > 0);
        },

        /**
         * Clear the unified schedule search field and restore all rows.
         *
         * Bound to `#aips-unified-search-clear` and `.aips-clear-unified-search-btn`.
         *
         * @param {Event} e - Click event.
         */
        clearUnifiedSearch: function(e) {
            e.preventDefault();
            $('#aips-unified-search').val('');
            $('.aips-unified-row').show();
            $('#aips-unified-search-clear').hide();
            $('#aips-unified-search-no-results').hide();
        },

        /**
         * Enable or disable the unified bulk-action Apply button and show the
         * selection count.
         */
        updateUnifiedBulkActions: function() {
            var count      = $('.aips-unified-checkbox:checked').length;
            var $apply     = $('#aips-unified-bulk-apply');
            var $unselect  = $('#aips-unified-unselect-all');
            var $countLbl  = $('#aips-unified-selected-count');

            $apply.prop('disabled', count === 0);
            $unselect.prop('disabled', count === 0);

            if (count > 0) {
                $countLbl.text(count + ' selected').show();
            } else {
                $countLbl.hide();
            }
        },

        /**
         * Confirm unified bulk delete and include a list of schedule names.
         *
         * @param {Array<{type: string, id: number, title: string, canDelete: boolean}>} items
         */
        confirmUnifiedBulkDelete: function(items) {
            var deletableItems = items.filter(function(item) {
                return item && item.canDelete;
            });

            if (deletableItems.length === 0) {
                AIPS.Utilities.showToast(
                    aipsScheduleL10n.noDeletableSchedulesSelected || 'None of the selected schedules can be deleted.',
                    'warning'
                );
                return;
            }

            var listLines = deletableItems.map(function(item, index) {
                return (index + 1) + '. ' + item.title;
            }).join('\n');

            var message = (aipsScheduleL10n.deleteSchedulesListIntro || 'The following schedules will be deleted:') +
                '\n\n' + listLines;

            var skippedCount = items.length - deletableItems.length;
            if (skippedCount > 0) {
                var skipTemplate = aipsScheduleL10n.deleteSchedulesSkipNotice || '%d selected schedule(s) cannot be deleted and will be skipped.';
                message += '\n\n' + skipTemplate.replace('%d', skippedCount);
            }

            message += '\n\n' + (aipsScheduleL10n.deleteSchedulesFinalConfirm || 'This action cannot be undone. Continue?');

            AIPS.Utilities.confirm(
                message,
                aipsScheduleL10n.deleteSchedulesHeading || 'Delete Schedules',
                [
                    { label: aipsAdminL10n.confirmCancelButton || 'Cancel', className: 'aips-btn aips-btn-secondary' },
                    {
                        label: aipsAdminL10n.confirmDeleteButton || 'Delete',
                        className: 'aips-btn aips-btn-danger-solid',
                        action: function() {
                            AIPS.Schedule.unifiedBulkDelete(deletableItems);
                        }
                    }
                ]
            );
        }
    };

    $(document).ready(function() {
        AIPS.Schedule.init();
    });

})(jQuery);
