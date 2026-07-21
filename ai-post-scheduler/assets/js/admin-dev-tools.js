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

            $spinner.addClass('is-active');

            var formData = {};
            $.each($form.serializeArray(), function (i, field) {
                formData[field.name] = field.value;
            });

            AIPS.Core.Http.ajaxRequest({
                action: 'aips_generate_scaffold',
                data: formData,
                $button: $btn,
                toastOnError: false,
                errorFallback: 'An error occurred. Please try again.',
                onSuccess: function (data) {
                    $('#aips-dev-output-message').text(data.message);

                    var listHtml = '';
                    if (data.items && data.items.length) {
                        $.each(data.items, function(i, item) {
                            listHtml += '<li>' + item + '</li>';
                        });
                    }
                    $('#aips-dev-output-list').html(listHtml);
                    $output.fadeIn();
                },
                onError: function (message) {
                    $('#aips-dev-error-message').text(message);
                    $error.fadeIn();
                }
            }).always(function () {
                $spinner.removeClass('is-active');
            });
        });
    });

})(jQuery);
