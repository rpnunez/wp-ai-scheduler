(function($) {
    'use strict';

    $(document).ready(function() {
        $('#aips-dev-scaffold-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $('#aips-generate-scaffold-btn');
            var $spinner = $form.find('.spinner');
            var $output = $('#aips-dev-output');
            var $error = $('#aips-dev-error');

            // Clear previous results
            $output.hide().find('#aips-dev-output-list').empty();
            $error.hide();

            // Validate
            var topic = $('#topic').val().trim();
            if (!topic) {
                AIPS.Utilities.showToast('Please enter a topic.', 'warning');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            var formData = $form.serialize();
            formData += '&action=aips_generate_scaffold&nonce=' + aipsAjax.nonce;

            $.post(aipsAjax.ajaxUrl, formData, function(response) {
                if (response.success) {
                    $('#aips-dev-output-message').text(response.data.message);

                    var listHtml = '';
                    if (response.data.items && response.data.items.length) {
                        $.each(response.data.items, function(i, item) {
                            listHtml += '<li>' + item + '</li>';
                        });
                    }
                    $('#aips-dev-output-list').html(listHtml);
                    $output.fadeIn();
                } else {
                    $('#aips-dev-error-message').text(response.data.message);
                    $error.fadeIn();
                }
            }).fail(function() {
                $('#aips-dev-error-message').text('An error occurred. Please try again.');
                $error.fadeIn();
            }).always(function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        });
    });

})(jQuery);

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
        $('#content_enhancement_disclosure_text').val(enhancement.disclosure_text || '');
        $('#content_enhancement_cta_text').val(enhancement.cta_text || '');
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
            $('<td>').text(isActive ? 'Active' : 'Inactive').appendTo($row);
            $('<td>').append(
                $('<button type="button" class="button aips-edit-content-enhancement">').text('Edit'),
                ' ',
                $('<button type="button" class="button aips-toggle-content-enhancement">').text(isActive ? 'Disable' : 'Enable'),
                ' ',
                $('<button type="button" class="button aips-delete-content-enhancement">').text('Delete')
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
                    AIPS.Utilities.showToast(response.data.message || 'Content enhancement updated.', 'success');
                }
                done(response.data);
            } else if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast(response.data.message || 'Unable to update content enhancement.', 'error');
            }
        }).fail(function() {
            if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast('Unable to update content enhancement.', 'error');
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
            if (!window.confirm('Delete this enhancement?')) {
                return;
            }
            postContentEnhancement({
                action: 'aips_delete_content_enhancement',
                enhancement_id: enhancement.id
            }, function() {});
        });
    });
})(jQuery);
