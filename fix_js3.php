<?php
$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');
$content = str_replace(
    "\$('.aips-panel-body > form > h2').each",
    "\$('.aips-panel-body > form > h2').each(function(index) {
                    var \$title = \$(this);
                    var text = \$title.text().trim();
                    var tabId = 'general';

                    if (text === 'General Settings') tabId = 'general';
                    else if (text === 'AI & External APIs') tabId = 'ai';
                    else if (text === 'Resilience & Rate Limiting') tabId = 'resilience';
                    else if (text === 'Notifications') tabId = 'notifications';
                    else if (text === 'Advanced & Logging') tabId = 'advanced';

                    // The description <p> (if exists) and <table class=\"form-table\">
                    var \$description = \$title.next('p');
                    var hasDescription = \$description.length > 0 && !\$description.hasClass('submit');

                    var \$table = hasDescription ? \$description.next('table.form-table') : \$title.next('table.form-table');

                    // Wrap them
                    var \$wrapper = \$('<div class=\"aips-settings-section-wrapper\" id=\"section-' + tabId + '\" style=\"display: none; padding-top: 15px;\"></div>');
                    \$title.before(\$wrapper);
                    \$wrapper.append(\$title);
                    if (hasDescription) \$wrapper.append(\$description);
                    if (\$table.length) \$wrapper.append(\$table);
                });

                // Show default tab
                \$('#section-general').show();
                \$tabs.first().addClass('active');

                \$tabs.on('click', function(e) {
                    e.preventDefault();
                    var targetTab = \$(this).data('tab');

                    \$tabs.removeClass('active');
                    \$(this).addClass('active');

                    \$('.aips-settings-section-wrapper').hide();
                    \$('#section-' + targetTab).show();
                });
            });",
    $content
);
file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "JS updated to check properly.\n";
