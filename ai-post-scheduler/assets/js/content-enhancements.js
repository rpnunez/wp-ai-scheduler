(function($) {
    'use strict';

    function fillContentEnhancementForm(enhancement) {
        $('#content_enhancement_id').val(enhancement.id || '');
        $('#content_enhancement_name').val(enhancement.name || '');
        $('#content_enhancement_slug').val(enhancement.slug || '');
        $('#content_enhancement_type').val(enhancement.type || 'embed');
        $('#content_enhancement_provider').val(enhancement.provider || 'custom');
        $('#content_enhancement_use_case').val(enhancement.use_case || '');
        $('#content_enhancement_endpoint_url').val(enhancement.endpoint_url || '');
        $('#content_enhancement_referral_url').val(enhancement.referral_url || '');
        $('#content_enhancement_utm_campaign').val(enhancement.utm_campaign || '');
        $('#content_enhancement_utm_source').val(enhancement.utm_source || '');
        $('#content_enhancement_utm_medium').val(enhancement.utm_medium || '');
        $('#content_enhancement_rel_attributes').val(enhancement.rel_attributes || 'sponsored nofollow noopener noreferrer');
        $('#content_enhancement_disclosure_text').val(enhancement.disclosure_text || '');
        $('#content_enhancement_cta_text').val(enhancement.cta_label || enhancement.cta_text || '');
        $('#content_enhancement_is_active').prop('checked', !!enhancement.is_active);
    }

    function resetContentEnhancementForm() {
        fillContentEnhancementForm({
            type: $('#content_enhancement_type option:first').val(),
            provider: $('#content_enhancement_provider option:first').val(),
            disclosure_text: $('#content_enhancement_disclosure_text').prop('defaultValue'),
            cta_text: $('#content_enhancement_cta_text').prop('defaultValue')
        });
    }

    function renderContentEnhancements(enhancements) {
        var $list = $('#aips-content-enhancements-list');
        $list.empty();

        $.each(enhancements || [], function(index, enhancement) {
            var isActive = !!enhancement.is_active;
            var $row = $('<tr>').attr('data-enhancement', JSON.stringify(enhancement));
            $('<td>').text(enhancement.name || '').appendTo($row);
            $('<td>').text('{{aips_enhancement:' + (enhancement.slug || '') + '}}').appendTo($row);
            $('<td>').text(enhancement.type || '').appendTo($row);
            $('<td>').text(enhancement.provider || '').appendTo($row);
            $('<td>').text(isActive ? aipsDevToolsL10n.active : aipsDevToolsL10n.inactive).appendTo($row);
            $('<td>').append(
                $('<button type="button" class="button aips-edit-content-enhancement">').text(aipsDevToolsL10n.edit),
                ' ',
                $('<button type="button" class="button aips-toggle-content-enhancement">').text(isActive ? aipsDevToolsL10n.disable : aipsDevToolsL10n.enable),
                ' ',
                $('<button type="button" class="button aips-delete-content-enhancement">').text(aipsDevToolsL10n.delete)
            ).appendTo($row);
            $list.append($row);
        });
    }

    function postContentEnhancement(data, done, always) {
        data.nonce = aipsAjax.nonce;
        $.post(aipsAjax.ajaxUrl, data, function(response) {
            if (response.success) {
                if (response.data.enhancements) {
                    renderContentEnhancements(response.data.enhancements);
                }
                if (window.AIPS && AIPS.Utilities) {
                    AIPS.Utilities.showToast(response.data.message || aipsDevToolsL10n.updateSuccess, 'success');
                }
                done(response.data);
            } else if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast(response.data.message || aipsDevToolsL10n.updateError, 'error');
            }
        }).fail(function() {
            if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast(aipsDevToolsL10n.updateError, 'error');
            }
        }).always(function() {
            if (always) {
                always();
            }
        });
    }

    $(document).ready(function() {
        $('#aips-content-enhancement-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $spinner = $form.find('.spinner');
            $spinner.addClass('is-active');

            postContentEnhancement($form.serializeArray().reduce(function(data, item) {
                data[item.name] = item.value;
                return data;
            }, { action: 'aips_save_content_enhancement' }), function() {
                resetContentEnhancementForm();
            }, function() {
                $spinner.removeClass('is-active');
            });
        });

        $('#aips-content-enhancement-reset').on('click', resetContentEnhancementForm);

        $('#aips-content-enhancements-list').on('click', '.aips-edit-content-enhancement', function() {
            fillContentEnhancementForm($(this).closest('tr').data('enhancement'));
        });

        $('#aips-content-enhancements-list').on('click', '.aips-toggle-content-enhancement', function() {
            var enhancement = $(this).closest('tr').data('enhancement');
            postContentEnhancement({
                action: 'aips_toggle_content_enhancement',
                enhancement_id: enhancement.id,
                is_active: enhancement.is_active ? '' : '1'
            }, function() {});
        });

        $('#aips-content-enhancements-list').on('click', '.aips-delete-content-enhancement', function() {
            var enhancement = $(this).closest('tr').data('enhancement');
            if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.confirm(
                    aipsDevToolsL10n.confirmDelete,
                    aipsDevToolsL10n.confirmDeleteTitle || 'Delete Enhancement',
                    [
                        { label: aipsDevToolsL10n.cancel || 'Cancel', className: 'aips-btn' },
                        {
                            label: aipsDevToolsL10n.delete,
                            className: 'aips-btn aips-btn-danger-solid',
                            action: function() {
                                postContentEnhancement({
                                    action: 'aips_delete_content_enhancement',
                                    enhancement_id: enhancement.id
                                }, function() {});
                            }
                        }
                    ]
                );
            } else if (window.confirm(aipsDevToolsL10n.confirmDelete)) {
                postContentEnhancement({
                    action: 'aips_delete_content_enhancement',
                    enhancement_id: enhancement.id
                }, function() {});
            }
        });
    });
})(jQuery);
