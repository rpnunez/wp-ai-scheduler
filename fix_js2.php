<?php
$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');

$js = <<<EOT
            <script>
            jQuery(document).ready(function(\$) {
                var \$tabs = \$('#aips-settings-tabs .aips-tab-link');

                // Wrap WordPress settings API output
                \$('.aips-panel-body > form > h2').each(function(index) {
                    var \$title = \$(this);
                    var text = \$title.text().trim();
                    var tabId = 'general';

                    if (text === 'General Settings') tabId = 'general';
                    else if (text === 'AI & External APIs') tabId = 'ai';
                    else if (text === 'Resilience & Rate Limiting') tabId = 'resilience';
                    else if (text === 'Notifications') tabId = 'notifications';
                    else if (text === 'Advanced & Logging') tabId = 'advanced';

                    // The description <p> (if exists) and <table class="form-table">
                    var \$description = \$title.next('p');
                    var \$table = \$description.length ? \$description.next('table.form-table') : \$title.next('table.form-table');

                    // Wrap them
                    var \$wrapper = \$('<div class="aips-settings-section-wrapper" id="section-' + tabId + '" style="display: none; padding-top: 15px;"></div>');
                    \$title.before(\$wrapper);
                    \$wrapper.append(\$title);
                    if (\$description.length) \$wrapper.append(\$description);
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
            });
            </script>
EOT;

// replace old JS
$content = preg_replace('/<script>.*?<\/script>/is', $js, $content);
file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "JS rewritten.\n";
