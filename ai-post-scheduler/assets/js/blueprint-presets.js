/**
 * Blueprint Presets admin management.
 *
 * Handles CRUD operations for Blueprint Presets within the unified Blueprints page.
 *
 * @package AI_Post_Scheduler
 * @since 2.9.0
 */
/* global jQuery, aipsAjax, aipsBlueprintPresetsL10n, AIPS */
(function ($) {
    'use strict';

    var $modal        = $('#aips-blueprint-preset-modal');
    var $form         = $('#aips-blueprint-preset-form');
    var $modalTitle   = $('#aips-blueprint-preset-modal-title');
    var $saveBtn      = $('#aips-save-blueprint-preset-btn');

    /**
     * Open modal for creating a new preset.
     */
    function openAddModal() {
        $form[0].reset();
        $('#aips-blueprint-preset-id').val('0');
        $modalTitle.text(aipsBlueprintPresetsL10n.addTitle || 'Add Blueprint Preset');
        $modal.show();
    }

    /**
     * Open modal for editing an existing preset.
     */
    function openEditModal(presetId) {
        var $row = $('[data-preset-id="' + presetId + '"]');
        if (!$row.length) {
            return;
        }

        $.post(aipsAjax.ajaxUrl, {
            action: 'aips_get_blueprint_preset',
            nonce: aipsBlueprintPresetsL10n.nonce,
            preset_id: presetId,
        }, function (response) {
            if (!response || !response.success) {
                AIPS.Utilities.showNotice('error', response && response.data ? response.data : 'Error loading preset.');
                return;
            }
            var preset = response.data;
            $('#aips-blueprint-preset-id').val(preset.id);
            $('#aips-blueprint-preset-name').val(preset.name);
            $('#aips-blueprint-preset-description').val(preset.description || '');
            $('#aips-blueprint-preset-structure').val(preset.structure_id || '');
            $('#aips-blueprint-preset-voice').val(preset.voice_id || '');

            // Multi-select slices.
            var sliceIds = preset.slice_ids || [];
            if (typeof sliceIds === 'string') {
                try { sliceIds = JSON.parse(sliceIds); } catch (e) { sliceIds = []; }
            }
            $('#aips-blueprint-preset-slices').val(sliceIds);

            $('#aips-blueprint-preset-is-active').prop('checked', !!parseInt(preset.is_active, 10));
            $('#aips-blueprint-preset-is-default').prop('checked', !!parseInt(preset.is_default, 10));

            $modalTitle.text('Edit Blueprint Preset');
            $modal.show();
        });
    }

    /**
     * Save preset (create or update).
     */
    function savePreset() {
        var name = $('#aips-blueprint-preset-name').val().trim();
        if (!name) {
            AIPS.Utilities.showNotice('error', aipsBlueprintPresetsL10n.nameRequired);
            return;
        }

        var sliceVals = $('#aips-blueprint-preset-slices').val() || [];

        var data = {
            action: 'aips_save_blueprint_preset',
            nonce: aipsBlueprintPresetsL10n.nonce,
            preset_id: $('#aips-blueprint-preset-id').val(),
            name: name,
            description: $('#aips-blueprint-preset-description').val(),
            structure_id: $('#aips-blueprint-preset-structure').val() || 0,
            voice_id: $('#aips-blueprint-preset-voice').val() || 0,
            slice_ids: JSON.stringify(sliceVals.map(Number)),
            is_active: $('#aips-blueprint-preset-is-active').is(':checked') ? 1 : 0,
            is_default: $('#aips-blueprint-preset-is-default').is(':checked') ? 1 : 0,
        };

        $saveBtn.prop('disabled', true);

        $.post(aipsAjax.ajaxUrl, data, function (response) {
            $saveBtn.prop('disabled', false);
            if (response && response.success) {
                AIPS.Utilities.showNotice('success', aipsBlueprintPresetsL10n.saveSuccess);
                $modal.hide();
                location.reload();
            } else {
                AIPS.Utilities.showNotice('error', response && response.data ? response.data : aipsBlueprintPresetsL10n.saveFailed);
            }
        }).fail(function () {
            $saveBtn.prop('disabled', false);
            AIPS.Utilities.showNotice('error', aipsBlueprintPresetsL10n.saveFailed);
        });
    }

    /**
     * Delete a preset.
     */
    function deletePreset(presetId) {
        if (!confirm(aipsBlueprintPresetsL10n.confirmDelete)) {
            return;
        }

        $.post(aipsAjax.ajaxUrl, {
            action: 'aips_delete_blueprint_preset',
            nonce: aipsBlueprintPresetsL10n.nonce,
            preset_id: presetId,
        }, function (response) {
            if (response && response.success) {
                AIPS.Utilities.showNotice('success', aipsBlueprintPresetsL10n.deleteSuccess);
                $('[data-preset-id="' + presetId + '"]').fadeOut(300, function () {
                    $(this).remove();
                });
            } else {
                AIPS.Utilities.showNotice('error', aipsBlueprintPresetsL10n.deleteFailed);
            }
        });
    }

    // Event bindings.
    $(document).on('click', '#aips-add-blueprint-preset-btn, #aips-add-blueprint-preset-empty-btn', openAddModal);
    $(document).on('click', '.aips-edit-blueprint-preset', function () {
        openEditModal($(this).data('id'));
    });
    $(document).on('click', '.aips-delete-blueprint-preset', function () {
        deletePreset($(this).data('id'));
    });
    $saveBtn.on('click', savePreset);

    // Close modal.
    $(document).on('click', '.aips-modal-close', function () {
        $(this).closest('.aips-modal').hide();
    });

})(jQuery);
