/**
 * AIPS Article Structures
 *
 * Article structure CRUD operations and search/filtering.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(AIPS, {

        openStructureModal: function(e) {
            e.preventDefault();
            $('#aips-structure-form')[0].reset();
            $('#structure_id').val('');
            $('#aips-structure-modal-title').text('Add New Article Structure');
            $('#aips-structure-modal').show();
        },

        saveStructure: function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            var data = {
                action: 'aips_save_structure',
                nonce: aipsAjax.nonce,
                structure_id: $('#structure_id').val(),
                name: $('#structure_name').val(),
                description: $('#structure_description').val(),
                prompt_template: $('#prompt_template').val(),
                sections: $('#structure_sections').val() || [],
                is_active: $('#structure_is_active').is(':checked') ? 1 : 0,
                is_default: $('#structure_is_default').is(':checked') ? 1 : 0
            };

            $.post(aipsAjax.ajaxUrl, data, function(response) {
                $btn.prop('disabled', false).text('Save Structure');
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || aipsAdminL10n.saveStructureFailed);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Save Structure');
                alert(aipsAdminL10n.errorTryAgain);
            });
        },

        editStructure: function() {
            var id = $(this).data('id');
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_get_structure',
                nonce: aipsAjax.nonce,
                structure_id: id
            }, function(response) {
                if (response.success) {
                    var s = response.data.structure;
                    var structureData = {};

                    if (s.structure_data) {
                        try {
                            structureData = JSON.parse(s.structure_data) || {};
                        } catch (e) {
                            console.error('Invalid structure_data JSON for structure ID ' + s.id, e);
                            structureData = {};
                        }
                    }

                    $('#structure_id').val(s.id);
                    $('#structure_name').val(s.name);
                    $('#structure_description').val(s.description);
                    $('#prompt_template').val(structureData.prompt_template || '');
                    var sections = structureData.sections || [];
                    $('#structure_sections').val(sections);
                    $('#structure_is_active').prop('checked', s.is_active == 1);
                    $('#structure_is_default').prop('checked', s.is_default == 1);
                    $('#aips-structure-modal-title').text('Edit Article Structure');
                    $('#aips-structure-modal').show();
                } else {
                    alert(response.data.message || aipsAdminL10n.loadStructureFailed);
                }
            }).fail(function() {
                alert(aipsAdminL10n.errorOccurred);
            });
        },

        deleteStructure: function() {
            if (!confirm(aipsAdminL10n.deleteStructureConfirm)) return;
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_delete_structure',
                nonce: aipsAjax.nonce,
                structure_id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(function() { $(this).remove(); });
                } else {
                    alert(response.data.message || aipsAdminL10n.deleteStructureFailed);
                }
            }).fail(function() {
                alert(aipsAdminL10n.errorOccurred);
            });
        },

        filterStructures: function() {
            var term = $('#aips-structure-search').val().toLowerCase().trim();
            var $rows = $('.aips-structures-list tbody tr');
            var $noResults = $('#aips-structure-search-no-results');
            var $table = $('.aips-structures-list');
            var $clearBtn = $('#aips-structure-search-clear');
            var hasVisible = false;

            $clearBtn.toggle(term.length > 0);

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var description = $row.find('.column-description').text().toLowerCase();

                if (name.indexOf(term) > -1 || description.indexOf(term) > -1) {
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

        clearStructureSearch: function(e) {
            e.preventDefault();
            $('#aips-structure-search').val('').trigger('keyup');
        }
    });

    // Bind structure events
    $(document).ready(function() {
        $(document).on('click', '.aips-add-structure-btn', AIPS.openStructureModal);
        $(document).on('click', '.aips-save-structure', AIPS.saveStructure);
        $(document).on('click', '.aips-edit-structure', AIPS.editStructure);
        $(document).on('click', '.aips-delete-structure', AIPS.deleteStructure);

        $(document).on('keyup search', '#aips-structure-search', AIPS.filterStructures);
        $(document).on('click', '#aips-structure-search-clear', AIPS.clearStructureSearch);
        $(document).on('click', '.aips-clear-structure-search-btn', AIPS.clearStructureSearch);
    });

})(jQuery);
