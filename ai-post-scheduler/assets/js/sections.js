/**
 * AIPS Prompt Sections
 *
 * Prompt section CRUD operations and search/filtering.
 *
 * @package AI_Post_Scheduler
 */
(function($) {
    'use strict';

    window.AIPS = window.AIPS || {};

    Object.assign(AIPS, {

        openSectionModal: function(e) {
            e.preventDefault();
            $('#aips-section-form')[0].reset();
            $('#section_id').val('');
            $('#aips-section-modal-title').text('Add New Prompt Section');
            $('#aips-section-modal').show();
        },

        saveSection: function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            var data = {
                action: 'aips_save_prompt_section',
                nonce: aipsAjax.nonce,
                section_id: $('#section_id').val(),
                name: $('#section_name').val(),
                section_key: $('#section_key').val(),
                description: $('#section_description').val(),
                content: $('#section_content').val(),
                is_active: $('#section_is_active').is(':checked') ? 1 : 0
            };

            $.post(aipsAjax.ajaxUrl, data, function(response) {
                $btn.prop('disabled', false).text('Save Section');
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || aipsAdminL10n.saveSectionFailed);
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Save Section');
                alert(aipsAdminL10n.errorTryAgain);
            });
        },

        editSection: function() {
            var id = $(this).data('id');
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_get_prompt_section',
                nonce: aipsAjax.nonce,
                section_id: id
            }, function(response) {
                if (response.success) {
                    var s = response.data.section;
                    $('#section_id').val(s.id);
                    $('#section_name').val(s.name);
                    $('#section_key').val(s.section_key);
                    $('#section_description').val(s.description);
                    $('#section_content').val(s.content);
                    $('#section_is_active').prop('checked', s.is_active == 1);
                    $('#aips-section-modal-title').text('Edit Prompt Section');
                    $('#aips-section-modal').show();
                } else {
                    alert(response.data.message || aipsAdminL10n.loadSectionFailed);
                }
            }).fail(function() {
                alert(aipsAdminL10n.errorOccurred);
            });
        },

        deleteSection: function() {
            if (!confirm(aipsAdminL10n.deleteSectionConfirm)) return;
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            $.post(aipsAjax.ajaxUrl, {
                action: 'aips_delete_prompt_section',
                nonce: aipsAjax.nonce,
                section_id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(function() { $(this).remove(); });
                } else {
                    alert(response.data.message || aipsAdminL10n.deleteSectionFailed);
                }
            }).fail(function() {
                alert(aipsAdminL10n.errorOccurred);
            });
        },

        filterSections: function() {
            var term = $('#aips-section-search').val().toLowerCase().trim();
            var $rows = $('.aips-sections-list tbody tr');
            var $noResults = $('#aips-section-search-no-results');
            var $table = $('.aips-sections-list');
            var $clearBtn = $('#aips-section-search-clear');
            var hasVisible = false;

            $clearBtn.toggle(term.length > 0);

            $rows.each(function() {
                var $row = $(this);
                var name = $row.find('.column-name').text().toLowerCase();
                var key = $row.find('.column-key code').text().toLowerCase();
                var description = $row.find('.column-description').text().toLowerCase();

                if (name.indexOf(term) > -1 || key.indexOf(term) > -1 || description.indexOf(term) > -1) {
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

        clearSectionSearch: function(e) {
            e.preventDefault();
            $('#aips-section-search').val('').trigger('keyup');
        }
    });

    // Bind section events
    $(document).ready(function() {
        $(document).on('click', '.aips-add-section-btn', AIPS.openSectionModal);
        $(document).on('click', '.aips-save-section', AIPS.saveSection);
        $(document).on('click', '.aips-edit-section', AIPS.editSection);
        $(document).on('click', '.aips-delete-section', AIPS.deleteSection);

        $(document).on('keyup search', '#aips-section-search', AIPS.filterSections);
        $(document).on('click', '#aips-section-search-clear', AIPS.clearSectionSearch);
        $(document).on('click', '.aips-clear-section-search-btn', AIPS.clearSectionSearch);
    });

})(jQuery);
