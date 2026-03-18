<?php
// To actually split do_settings_sections, we need a custom function or handle it via JS, because WordPress outputs all sections sequentially.
// JS tab switching is the easiest way to handle standard WordPress settings API without rewriting core functions.
// We can wrap each section in JS based on its ID.

$js = <<<EOT

            <script>
            jQuery(document).ready(function(\$) {
                // Initialize Tabs
                var \$tabs = \$('#aips-settings-tabs .aips-tab-link');
                var \$sections = \$('.wrap h2'); // WP settings API outputs <h2> for section titles

                // Wrap each section and its following form table in a div
                \$sections.each(function(index) {
                    var \$section = \$(this);
                    var \$table = \$section.next('.form-table');
                    var \$para = \$section.next('p');

                    var tabId = '';
                    var title = \$section.text().trim();
                    if (title === 'General Settings') tabId = 'general';
                    else if (title === 'AI & External APIs') tabId = 'ai';
                    else if (title === 'Resilience & Rate Limiting') tabId = 'resilience';
                    else if (title === 'Notifications') tabId = 'notifications';
                    else if (title === 'Advanced & Logging') tabId = 'advanced';
                    else tabId = 'other-' + index;

                    // Group them
                    var \$wrapper = \$('<div class="aips-settings-section-wrapper" id="section-' + tabId + '"></div>');
                    \$section.before(\$wrapper);
                    \$wrapper.append(\$section);

                    // If there's a paragraph (callback description), move it in
                    if (\$para.length && !\$para.hasClass('form-table')) {
                        \$wrapper.append(\$para);
                        \$table = \$para.next('.form-table');
                    }

                    if (\$table.length) {
                        \$wrapper.append(\$table);
                    }

                    // Hide all but the first
                    if (tabId !== 'general') {
                        \$wrapper.hide();
                    }
                });

                \$tabs.on('click', function(e) {
                    e.preventDefault();
                    var \$this = \$(this);
                    var targetTab = \$this.data('tab');

                    \$tabs.removeClass('active');
                    \$this.addClass('active');

                    \$('.aips-settings-section-wrapper').hide();
                    \$('#section-' + targetTab).show();
                });
            });
            </script>
EOT;

$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');
$content = str_replace('</form>', "</form>\n" . $js, $content);
file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "Settings JS patched.\n";
