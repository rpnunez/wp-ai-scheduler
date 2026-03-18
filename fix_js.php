<?php
$content = file_get_contents('ai-post-scheduler/templates/admin/settings.php');
$content = str_replace('<div id="tab-general" class="aips-tab-content aips-settings-tab-content active" style="display: block;">
                        <?php do_settings_sections(\'aips-settings\'); ?>
                    </div>', '<?php do_settings_sections(\'aips-settings\'); ?>', $content);

file_put_contents('ai-post-scheduler/templates/admin/settings.php', $content);
echo "Fixed wrapper.\n";
