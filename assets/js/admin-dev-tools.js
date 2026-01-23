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
                alert('Please enter a topic.');
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
