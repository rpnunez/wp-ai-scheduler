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

    function fillDeveloperIntegrationForm(integration) {
        $('#developer_integration_id').val(integration.id || '');
        $('#developer_integration_name').val(integration.name || '');
        $('#developer_integration_provider').val(integration.provider || 'custom');
        $('#developer_integration_endpoint_url').val(integration.endpoint_url || '');
        $('#developer_integration_disclosure_text').val(integration.disclosure_text || '');
        $('#developer_integration_cta_text').val(integration.cta_text || '');
        $('#developer_integration_is_active').prop('checked', !!integration.is_active);
    }

    function resetDeveloperIntegrationForm() {
        fillDeveloperIntegrationForm({
            provider: $('#developer_integration_provider option:first').val(),
            disclosure_text: $('#developer_integration_disclosure_text').prop('defaultValue'),
            cta_text: $('#developer_integration_cta_text').prop('defaultValue')
        });
    }

    function renderDeveloperIntegrations(integrations) {
        var $list = $('#aips-developer-integrations-list');
        $list.empty();

        $.each(integrations || [], function(index, integration) {
            var isActive = !!integration.is_active;
            var $row = $('<tr>').attr('data-integration', JSON.stringify(integration));
            $('<td>').text(integration.name || '').appendTo($row);
            $('<td>').text(integration.provider || '').appendTo($row);
            $('<td>').text(isActive ? 'Active' : 'Inactive').appendTo($row);
            $('<td>').append(
                $('<button type="button" class="button aips-edit-developer-integration">').text('Edit'),
                ' ',
                $('<button type="button" class="button aips-toggle-developer-integration">').text(isActive ? 'Disable' : 'Enable'),
                ' ',
                $('<button type="button" class="button aips-delete-developer-integration">').text('Delete')
            ).appendTo($row);
            $list.append($row);
        });
    }

    function postDeveloperIntegration(data, done, always) {
        data.nonce = aipsAjax.nonce;
        $.post(aipsAjax.ajaxUrl, data, function(response) {
            if (response.success) {
                if (response.data.integrations) {
                    renderDeveloperIntegrations(response.data.integrations);
                }
                if (window.AIPS && AIPS.Utilities) {
                    AIPS.Utilities.showToast(response.data.message || 'Integration updated.', 'success');
                }
                done(response.data);
            } else if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast(response.data.message || 'Unable to update integration.', 'error');
            }
        }).fail(function() {
            if (window.AIPS && AIPS.Utilities) {
                AIPS.Utilities.showToast('Unable to update integration.', 'error');
            }
        }).always(function() {
            if (always) {
                always();
            }
        });
    }

    $(document).ready(function() {
        $('#aips-developer-integration-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $spinner = $form.find('.spinner');
            $spinner.addClass('is-active');

            postDeveloperIntegration($form.serializeArray().reduce(function(data, item) {
                data[item.name] = item.value;
                return data;
            }, { action: 'aips_save_developer_integration' }), function() {
                resetDeveloperIntegrationForm();
            }, function() {
                $spinner.removeClass('is-active');
            });
        });

        $('#aips-developer-integration-reset').on('click', resetDeveloperIntegrationForm);

        $('#aips-developer-integrations-list').on('click', '.aips-edit-developer-integration', function() {
            fillDeveloperIntegrationForm($(this).closest('tr').data('integration'));
        });

        $('#aips-developer-integrations-list').on('click', '.aips-toggle-developer-integration', function() {
            var integration = $(this).closest('tr').data('integration');
            postDeveloperIntegration({
                action: 'aips_toggle_developer_integration',
                integration_id: integration.id,
                is_active: integration.is_active ? '' : '1'
            }, function() {});
        });

        $('#aips-developer-integrations-list').on('click', '.aips-delete-developer-integration', function() {
            var integration = $(this).closest('tr').data('integration');
            if (!window.confirm('Delete this integration?')) {
                return;
            }
            postDeveloperIntegration({
                action: 'aips_delete_developer_integration',
                integration_id: integration.id
            }, function() {});
        });
    });
})(jQuery);
