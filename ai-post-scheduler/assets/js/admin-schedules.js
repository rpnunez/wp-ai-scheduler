(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};
    var AIPS = window.AIPS;

    /**
     * Resolve a localized schedule string with optional fallback to admin l10n.
     *
     * @param {string} key - Localization key.
     * @param {string} fallback - Fallback text when key is missing.
     * @returns {string} Localized or fallback string.
     */
    function getScheduleText(key, fallback) {
        if (typeof window.aipsScheduleL10n !== 'undefined' && window.aipsScheduleL10n[key]) {
            return window.aipsScheduleL10n[key];
        }

        if (typeof window.aipsAdminL10n !== 'undefined' && window.aipsAdminL10n[key]) {
            return window.aipsAdminL10n[key];
        }

        return fallback;
    }

    Object.assign(AIPS, {
        /**
         * Initialize all Schedule-specific admin behavior.
         *
         * Binds delegated schedule event handlers, refreshes the bulk-action
         * toolbar state, and auto-opens the schedule modal when deep-link
         * query parameters are present.
         */
        initScheduleModule: function() {
            this.bindScheduleEvents();
            this.updateScheduleBulkActions();
            this.initScheduleAutoOpen();
        },

        /**
         * Register all delegated jQuery event listeners for schedule UI.
         */
        bindScheduleEvents: function() {
            $(document).on('click', '#aips-quick-schedule-btn', this.quickSchedule);
            $(document).on('click', '.aips-add-schedule-btn', this.openScheduleModal);
            $(document).on('click', '.aips-edit-schedule', this.editSchedule);
            $(document).on('click', '.aips-clone-schedule', this.cloneSchedule);
            $(document).on('click', '.aips-run-now-schedule', this.runNowSchedule);
            $(document).on('click', '.aips-save-schedule', this.saveSchedule);
            $(document).on('click', '.aips-delete-schedule', this.deleteSchedule);
            $(document).on('change', '.aips-toggle-schedule', this.toggleSchedule);
            $(document).on('click', '.aips-view-schedule-history', this.viewScheduleHistory);

            // Schedule bulk actions
            $(document).on('change', '#cb-select-all-schedules', this.toggleAllSchedules);
            $(document).on('change', '.aips-schedule-checkbox', this.toggleScheduleSelection);
            $(document).on('click', '#aips-schedule-select-all', this.selectAllSchedules);
            $(document).on('click', '#aips-schedule-unselect-all', this.unselectAllSchedules);
            $(document).on('click', '#aips-schedule-bulk-apply', this.applyScheduleBulkAction);

            // Schedule search
            $(document).on('keyup search', '#aips-schedule-search', this.filterSchedules);
            $(document).on('click', '#aips-schedule-search-clear', this.clearScheduleSearch);
            $(document).on('click', '.aips-clear-schedule-search-btn', this.clearScheduleSearch);
        },

        /**
         * Reset and open the schedule modal in "Add New" mode.
         *
         * @param {Event} e - Click event from an `.aips-add-schedule-btn` element.
         */
        openScheduleModal: function(e) {
            e.preventDefault();
            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');
            $('#aips-schedule-modal-title').text(getScheduleText('addNewScheduleTitle', 'Add New Schedule'));
            $('#aips-schedule-modal').show();
        },

        /**
         * Trigger quick-schedule navigation from the template post-save panel.
         *
         * @param {Event} e - Click event from `#aips-quick-schedule-btn`.
         */
        quickSchedule: function(e) {
            // Preserve expected behavior for modified clicks that should open a new tab/window.
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                return;
            }

            e.preventDefault();
            var $btn = $(this);
            var templateId = $btn.data('template-id');

            if (!templateId) {
                return;
            }

            var scheduleUrlBase = (typeof aipsAjax !== 'undefined' && aipsAjax.schedulePageUrl)
                ? aipsAjax.schedulePageUrl
                : 'admin.php?page=aips-schedule';

            var url = new URL(scheduleUrlBase, window.location.href);
            url.searchParams.set('schedule_template', templateId);
            url.hash = 'open_schedule_modal';
            window.location.href = url.toString();
        },

        /**
         * Open the schedule modal pre-filled with row data for in-place editing.
         *
         * @param {Event} e - Click event from the edit button.
         */
        editSchedule: function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var scheduleId = $row.data('schedule-id');
            var templateId = $row.data('template-id');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');
            var nextRun = $row.data('next-run');
            var isActive = $row.data('is-active');

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val(scheduleId);
            $('#schedule_template').val(templateId);
            $('#schedule_frequency').val(frequency);
            $('#schedule_topic').val(topic || '');
            $('#article_structure_id').val(articleStructureId || '');
            $('#rotation_pattern').val(rotationPattern || '');
            $('#schedule_is_active').prop('checked', isActive == 1);

            if (nextRun) {
                var dt = new Date(nextRun);
                if (!isNaN(dt.getTime())) {
                    var pad = function(n) { return n < 10 ? '0' + n : n; };
                    var localValue = dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate()) +
                        'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
                    $('#schedule_start_time').val(localValue);
                }
            }

            $('#aips-schedule-modal-title').text(getScheduleText('editScheduleTitle', 'Edit Schedule'));
            $('#aips-schedule-modal').show();
        },

        /**
         * Copy an existing schedule's settings into the add modal.
         *
         * @param {Event} e - Click event from an `.aips-clone-schedule` element.
         */
        cloneSchedule: function(e) {
            e.preventDefault();

            $('#aips-schedule-form')[0].reset();
            $('#schedule_id').val('');

            var $row = $(this).closest('tr');
            var templateId = $row.data('template-id');
            var frequency = $row.data('frequency');
            var topic = $row.data('topic');
            var articleStructureId = $row.data('article-structure-id');
            var rotationPattern = $row.data('rotation-pattern');

            $('#schedule_template').val(templateId);
            $('#schedule_frequency').val(frequency);
            $('#schedule_topic').val(topic);
            $('#article_structure_id').val(articleStructureId);
            $('#rotation_pattern').val(rotationPattern);
            $('#schedule_start_time').val('');

            $('#aips-schedule-modal-title').text(getScheduleText('cloneScheduleTitle', 'Clone Schedule'));
            $('#aips-schedule-modal').show();
        },

        /**
         * Validate and save the schedule form via AJAX.
         *
         * @param {Event} e - Click event from an `.aips-save-schedule` element.
         */
        saveSchedule: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $form = $('#aips-schedule-form');

            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            $btn.prop('disabled', true).text(getScheduleText('saving', 'Saving...'));

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_save_schedule',
                    nonce: aipsAjax.nonce,
                    schedule_id: $('#schedule_id').val(),
                    template_id: $('#schedule_template').val(),
                    frequency: $('#schedule_frequency').val(),
                    start_time: $('#schedule_start_time').val(),
                    topic: $('#schedule_topic').val(),
                    article_structure_id: $('#article_structure_id').val(),
                    rotation_pattern: $('#rotation_pattern').val(),
                    is_active: $('#schedule_is_active').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('scheduleSavedSuccessfully', 'Schedule saved successfully'), 'success');
                        $('#aips-schedule-modal').hide();

                        $.get(location.href, function(html) {
                            var $newDoc = $(html);
                            var $newContent = $newDoc.find('.aips-schedule-table').closest('.aips-content-panel');
                            var $existingPanel = $('.aips-schedule-table').closest('.aips-content-panel');

                            if ($newContent.length) {
                                if ($existingPanel.length) {
                                    $existingPanel.replaceWith($newContent);
                                } else {
                                    var $emptyStatePanel = $('.aips-content-panel').has('.aips-empty-state').last();
                                    if ($emptyStatePanel.length) {
                                        $emptyStatePanel.replaceWith($newContent);
                                    } else {
                                        location.reload();
                                    }
                                }

                                AIPS.updateScheduleBulkActions();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(getScheduleText('saveScheduleButton', 'Save Schedule'));
                }
            });
        },

        /**
         * Confirm and permanently delete a schedule via AJAX.
         *
         * @param {Event} e - Click event from an `.aips-delete-schedule` element.
         */
        deleteSchedule: function(e) {
            e.preventDefault();

            var $el = $(this);
            var id = $el.data('id');
            var $row = $el.closest('tr');

            AIPS.Utilities.confirm(getScheduleText('deleteScheduleConfirm', 'Are you sure you want to delete this schedule?'), getScheduleText('deleteDialogTitle', 'Notice'), [
                { label: getScheduleText('confirmCancelButton', 'No, cancel'), className: 'aips-btn aips-btn-primary' },
                { label: getScheduleText('confirmDeleteButton', 'Yes, delete'), className: 'aips-btn aips-btn-danger-solid', action: function() {
                    $.ajax({
                        url: aipsAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'aips_delete_schedule',
                            nonce: aipsAjax.nonce,
                            schedule_id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                $row.fadeOut(function() {
                                    $(this).remove();
                                });
                            } else {
                                AIPS.Utilities.showToast(response.data.message, 'error');
                            }
                        },
                        error: function() {
                            AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                        }
                    });
                }}
            ]);
        },

        /**
         * Trigger immediate execution of a specific schedule via schedule_id.
         *
         * @param {Event} e - Click event from the run-now button.
         */
        runNowSchedule: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var scheduleId = $btn.data('id');

            if (!scheduleId) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-controls-play').addClass('dashicons-update aips-spin');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_run_now',
                    nonce: aipsAjax.nonce,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        var msg = AIPS.escapeHtml(response.data.message || getScheduleText('postGeneratedSuccessfully', 'Post generated successfully!'));

                        if (response.data.edit_url) {
                            msg += ' <a href="' + AIPS.escapeAttribute(response.data.edit_url) + '" target="_blank">' + AIPS.escapeHtml(getScheduleText('editPostLinkText', 'Edit Post')) + '</a>';
                        }

                        AIPS.Utilities.showToast(msg, 'success', { isHtml: true, duration: 8000 });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('generationFailed', 'Generation failed.'), 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dashicons-update aips-spin').addClass('dashicons-controls-play');
                }
            });
        },

        /**
         * Toggle a schedule's active or inactive status via AJAX.
         */
        toggleSchedule: function() {
            var $toggle = $(this);
            var id = $toggle.data('id');
            var isActive = $toggle.is(':checked') ? 1 : 0;
            var $wrapper = $toggle.closest('.aips-schedule-status-wrapper');
            var $badge = $wrapper.find('.aips-badge');
            var $icon = $badge.find('.dashicons');

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_toggle_schedule',
                    nonce: aipsAjax.nonce,
                    schedule_id: id,
                    is_active: isActive
                },
                success: function() {
                    $badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
                    $icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
                    $badge.contents().filter(function() { return this.nodeType === 3; }).remove();

                    if (isActive) {
                        $badge.addClass('aips-badge-success');
                        $icon.addClass('dashicons-yes-alt');
                        $icon.after(' ' + getScheduleText('statusActive', 'Active'));
                    } else {
                        $badge.addClass('aips-badge-neutral');
                        $icon.addClass('dashicons-minus');
                        $icon.after(' ' + getScheduleText('statusInactive', 'Inactive'));
                    }

                    $toggle.closest('tr').data('is-active', isActive);
                },
                error: function() {
                    $toggle.prop('checked', !isActive);
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                }
            });
        },

        /**
         * Open schedule history modal and load timeline entries by schedule ID.
         *
         * @param {Event} e - Click event from `.aips-view-schedule-history`.
         */
        viewScheduleHistory: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var scheduleId = $btn.data('id');
            var scheduleName = $btn.data('name') || scheduleId;

            if (!scheduleId) {
                return;
            }

            var $modal = $('#aips-schedule-history-modal');
            var $title = $modal.find('#aips-schedule-history-modal-title');
            var $loading = $modal.find('#aips-schedule-history-loading');
            var $empty = $modal.find('#aips-schedule-history-empty');
            var $list = $modal.find('#aips-schedule-history-list');

            $title.text(getScheduleText('scheduleHistoryTitlePrefix', 'Schedule History: ') + scheduleName);
            $loading.show();
            $empty.hide();
            $list.hide().empty();
            $modal.show();

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_get_schedule_history',
                    nonce: aipsAjax.nonce,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    $loading.hide();

                    if (!response.success) {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('failedToLoadHistory', 'Failed to load history.'), 'error');
                        $modal.hide();
                        return;
                    }

                    var entries = response.data.entries;

                    if (!entries || entries.length === 0) {
                        $empty.show();
                        return;
                    }

                    var iconMap = {
                        'schedule_created': { icon: 'dashicons-plus-alt', cls: 'aips-timeline-created' },
                        'schedule_updated': { icon: 'dashicons-edit', cls: 'aips-timeline-updated' },
                        'schedule_enabled': { icon: 'dashicons-yes-alt', cls: 'aips-timeline-enabled' },
                        'schedule_disabled': { icon: 'dashicons-minus', cls: 'aips-timeline-disabled' },
                        'schedule_executed': { icon: 'dashicons-controls-play', cls: 'aips-timeline-executed' },
                        'manual_schedule_started': { icon: 'dashicons-controls-play', cls: 'aips-timeline-executed' },
                        'manual_schedule_completed': { icon: 'dashicons-yes', cls: 'aips-timeline-success' },
                        'manual_schedule_failed': { icon: 'dashicons-warning', cls: 'aips-timeline-error' },
                        'schedule_failed': { icon: 'dashicons-warning', cls: 'aips-timeline-error' },
                        'post_published': { icon: 'dashicons-media-document', cls: 'aips-timeline-success' },
                        'post_draft': { icon: 'dashicons-media-document', cls: 'aips-timeline-draft' },
                        'post_generated': { icon: 'dashicons-media-document', cls: 'aips-timeline-draft' }
                    };
                    var defaultIcon = { icon: 'dashicons-info', cls: '' };

                    entries.forEach(function(entry) {
                        var info = iconMap[entry.event_type] || defaultIcon;
                        var isError = (entry.history_type_id === 2 || entry.event_status === 'failed');

                        if (isError && !info.cls) {
                            info = { icon: 'dashicons-warning', cls: 'aips-timeline-error' };
                        }

                        var $item = $('<li>', { 'class': 'aips-timeline-item ' + info.cls });
                        var $icon = $('<span>', { 'class': 'aips-timeline-icon', 'aria-hidden': 'true' })
                            .append($('<span>', { 'class': 'dashicons ' + info.icon }));
                        var $content = $('<div>', { 'class': 'aips-timeline-content' });
                        var $msg = $('<p>', { 'class': 'aips-timeline-message' }).text(entry.message || entry.log_type);
                        var $time = $('<time>', { 'class': 'aips-timeline-timestamp', 'datetime': entry.timestamp })
                            .text(entry.timestamp);

                        $content.append($msg).append($time);
                        $item.append($icon).append($content);
                        $list.append($item);
                    });

                    $list.show();
                },
                error: function() {
                    $loading.hide();
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                    $modal.hide();
                }
            });
        },

        /**
         * Sync all row checkboxes to the select-all checkbox state.
         */
        toggleAllSchedules: function() {
            var isChecked = $(this).prop('checked');
            $('.aips-schedule-checkbox').prop('checked', isChecked);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Keep select-all checkbox synced with individual row selections.
         */
        toggleScheduleSelection: function() {
            var total = $('.aips-schedule-checkbox').length;
            var checked = $('.aips-schedule-checkbox:checked').length;
            $('#cb-select-all-schedules').prop('checked', total > 0 && checked === total);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Select all schedule row checkboxes.
         */
        selectAllSchedules: function() {
            $('.aips-schedule-checkbox').prop('checked', true);
            $('#cb-select-all-schedules').prop('checked', true);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Clear all schedule row checkboxes.
         */
        unselectAllSchedules: function() {
            $('.aips-schedule-checkbox').prop('checked', false);
            $('#cb-select-all-schedules').prop('checked', false);
            AIPS.updateScheduleBulkActions();
        },

        /**
         * Update schedule bulk-action controls and selection count label.
         */
        updateScheduleBulkActions: function() {
            var count = $('.aips-schedule-checkbox:checked').length;
            var $applyBtn = $('#aips-schedule-bulk-apply');
            var $unselectBtn = $('#aips-schedule-unselect-all');
            var $countLabel = $('#aips-schedule-selected-count');

            $applyBtn.prop('disabled', count === 0);
            $unselectBtn.prop('disabled', count === 0);

            if (count > 0) {
                $countLabel.text(getScheduleText('selectedCountLabel', '%d selected').replace('%d', count)).show();
            } else {
                $countLabel.hide();
            }
        },

        /**
         * Execute the selected bulk action for checked schedules.
         *
         * @param {Event} e - Click event from `#aips-schedule-bulk-apply`.
         */
        applyScheduleBulkAction: function(e) {
            e.preventDefault();

            var action = $('#aips-schedule-bulk-action').val();
            if (!action) {
                AIPS.Utilities.showToast(getScheduleText('selectBulkAction', 'Please select a bulk action.'), 'warning');
                return;
            }

            var ids = [];
            $('.aips-schedule-checkbox:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length === 0) {
                AIPS.Utilities.showToast(getScheduleText('selectAtLeastOneSchedule', 'Please select at least one schedule.'), 'warning');
                return;
            }

            if (action === 'delete') {
                var deleteMsg = ids.length === 1
                    ? getScheduleText('deleteOneScheduleConfirm', 'Are you sure you want to delete 1 schedule?')
                    : getScheduleText('deleteMultipleSchedulesConfirm', 'Are you sure you want to delete %d schedules?').replace('%d', ids.length);

                AIPS.Utilities.confirm(deleteMsg, getScheduleText('deleteSchedulesTitle', 'Delete Schedules'), [
                    { label: getScheduleText('confirmCancelButton', 'No, cancel'), className: 'aips-btn aips-btn-secondary' },
                    { label: getScheduleText('confirmDeleteButton', 'Yes, delete'), className: 'aips-btn aips-btn-danger-solid', action: function() { AIPS.bulkDeleteSchedules(ids); } }
                ]);
            } else if (action === 'pause') {
                AIPS.bulkToggleSchedules(ids, 0);
            } else if (action === 'activate') {
                AIPS.bulkToggleSchedules(ids, 1);
            } else if (action === 'run_now') {
                $.ajax({
                    url: aipsAjax.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aips_get_schedules_post_count',
                        nonce: aipsAjax.nonce,
                        ids: ids
                    },
                    success: function(response) {
                        var count = response.success ? (response.data.count || ids.length) : ids.length;
                        var runMsg = count === 1
                            ? getScheduleText('runPostsConfirmSingular', 'This will generate an estimated 1 post. Are you sure?')
                            : getScheduleText('runPostsConfirmPlural', 'This will generate an estimated %d posts. Are you sure?').replace('%d', count);

                        AIPS.Utilities.confirm(runMsg, getScheduleText('runSchedulesNow', 'Run Schedules Now'), [
                            { label: getScheduleText('cancel', 'Cancel'), className: 'aips-btn aips-btn-secondary' },
                            { label: getScheduleText('yesRunNow', 'Yes, run now'), className: 'aips-btn aips-btn-primary', action: function() { AIPS.bulkRunNowSchedules(ids); } }
                        ]);
                    },
                    error: function() {
                        var fallbackRunMsg = ids.length === 1
                            ? getScheduleText('runOneScheduleConfirm', 'This will run 1 schedule. Are you sure?')
                            : getScheduleText('runMultipleSchedulesConfirm', 'This will run %d schedules. Are you sure?').replace('%d', ids.length);

                        AIPS.Utilities.confirm(fallbackRunMsg, getScheduleText('runSchedulesNow', 'Run Schedules Now'), [
                            { label: getScheduleText('cancel', 'Cancel'), className: 'aips-btn aips-btn-secondary' },
                            { label: getScheduleText('yesRunNow', 'Yes, run now'), className: 'aips-btn aips-btn-primary', action: function() { AIPS.bulkRunNowSchedules(ids); } }
                        ]);
                    }
                });
            }
        },

        /**
         * Delete multiple schedules via AJAX.
         *
         * @param {Array<string>} ids - Schedule IDs to delete.
         */
        bulkDeleteSchedules: function(ids) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text(getScheduleText('deleting', 'Deleting...'));

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_delete_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        ids.forEach(function(id) {
                            $('tr[data-schedule-id="' + id + '"]').fadeOut(function() {
                                $(this).remove();
                            });
                        });
                        $('#cb-select-all-schedules').prop('checked', false);
                        AIPS.updateScheduleBulkActions();
                    } else {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('failedToDeleteSchedules', 'Failed to delete schedules.'), 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                },
                complete: function() {
                    $applyBtn.text(getScheduleText('applyButton', 'Apply'));
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Bulk toggle schedule active state via AJAX.
         *
         * @param {Array<string>} ids - Schedule IDs to update.
         * @param {number} isActive - 1 to activate, 0 to pause.
         */
        bulkToggleSchedules: function(ids, isActive) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text(isActive ? getScheduleText('activating', 'Activating...') : getScheduleText('pausing', 'Pausing...'));

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_toggle_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids,
                    is_active: isActive
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success');
                        ids.forEach(function(id) {
                            var $row = $('tr[data-schedule-id="' + id + '"]');
                            var $toggle = $row.find('.aips-toggle-schedule');
                            var $wrapper = $row.find('.aips-schedule-status-wrapper');
                            var $badge = $wrapper.find('.aips-badge');
                            var $icon = $badge.find('.dashicons');

                            $toggle.prop('checked', isActive === 1);
                            $badge.removeClass('aips-badge-success aips-badge-neutral aips-badge-error');
                            $icon.removeClass('dashicons-yes-alt dashicons-minus dashicons-warning');
                            $badge.contents().filter(function() { return this.nodeType === 3; }).remove();

                            if (isActive) {
                                $badge.addClass('aips-badge-success');
                                $icon.addClass('dashicons-yes-alt');
                                $icon.after(' ' + getScheduleText('statusActive', 'Active'));
                            } else {
                                $badge.addClass('aips-badge-neutral');
                                $icon.addClass('dashicons-minus');
                                $icon.after(' ' + getScheduleText('statusInactive', 'Inactive'));
                            }

                            $row.data('is-active', isActive);
                        });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('failedToUpdateSchedules', 'Failed to update schedules.'), 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                },
                complete: function() {
                    $applyBtn.text(getScheduleText('applyButton', 'Apply'));
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Run multiple schedules immediately via AJAX.
         *
         * @param {Array<string>} ids - Schedule IDs to execute.
         */
        bulkRunNowSchedules: function(ids) {
            var $applyBtn = $('#aips-schedule-bulk-apply');
            $applyBtn.prop('disabled', true).text(getScheduleText('running', 'Running...'));

            $.ajax({
                url: aipsAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aips_bulk_run_now_schedules',
                    nonce: aipsAjax.nonce,
                    ids: ids
                },
                success: function(response) {
                    if (response.success) {
                        AIPS.Utilities.showToast(response.data.message, 'success', { duration: 8000 });
                    } else {
                        AIPS.Utilities.showToast(response.data.message || getScheduleText('bulkRunFailed', 'Bulk run failed.'), 'error');
                    }
                },
                error: function() {
                    AIPS.Utilities.showToast(getScheduleText('errorTryAgain', 'An error occurred. Please try again.'), 'error');
                },
                complete: function() {
                    $applyBtn.text(getScheduleText('applyButton', 'Apply'));
                    AIPS.updateScheduleBulkActions();
                }
            });
        },

        /**
         * Filter schedules table rows by search term.
         */
        filterSchedules: function() {
            var term = $('#aips-schedule-search').val().toLowerCase().trim();
            var $rows = $('.aips-schedule-table tbody tr');
            var $noResults = $('#aips-schedule-search-no-results');
            var $table = $('.aips-schedule-table');
            var $clearBtn = $('#aips-schedule-search-clear');
            var hasVisible = false;

            if (term.length > 0) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
            }

            $rows.each(function() {
                var $row = $(this);
                var template = $row.find('.column-template').text().toLowerCase();
                var structure = $row.find('.column-structure').text().toLowerCase();
                var frequency = $row.find('.column-frequency').text().toLowerCase();

                if (template.indexOf(term) > -1 || structure.indexOf(term) > -1 || frequency.indexOf(term) > -1) {
                    $row.show();
                    hasVisible = true;
                } else {
                    $row.hide();
                }
            });

            if (!hasVisible && term.length > 0) {
                $table.hide();
                $noResults.show();
            } else {
                $table.show();
                $noResults.hide();
            }
        },

        /**
         * Clear schedule search and refresh visible schedule rows.
         *
         * @param {Event} e - Click event from schedule search clear controls.
         */
        clearScheduleSearch: function(e) {
            e.preventDefault();
            $('#aips-schedule-search').val('').trigger('keyup');
        },

        /**
         * Auto-open schedule modal when deep-link query/hash parameters are present.
         */
        initScheduleAutoOpen: function() {
            var $modal = $('#aips-schedule-modal');

            if (!$modal.length) {
                return;
            }

            var preselectId = $modal.data('preselect-template');
            var preselectStructureId = $modal.data('preselect-structure');

            if (!preselectId && !preselectStructureId) {
                var urlParams = null;

                try {
                    urlParams = new URL(window.location.href).searchParams;
                } catch (e) {
                    try {
                        urlParams = new URLSearchParams(window.location.search);
                    } catch (e2) {
                        urlParams = null;
                    }
                }

                if (urlParams) {
                    preselectId = urlParams.get('schedule_template') || '';
                    preselectStructureId = urlParams.get('schedule_structure') || urlParams.get('article_structure_id') || '';
                }
            }

            if (!preselectId && !preselectStructureId && window.location.hash !== '#open_schedule_modal') {
                return;
            }

            this.openScheduleModal($.Event('click'));

            if (preselectId) {
                $('#schedule_template').val(String(preselectId));
            }

            if (preselectStructureId) {
                $('#article_structure_id').val(String(preselectStructureId));
            }

            $('#aips-schedule-modal').show();
        }
    });

})(jQuery);
