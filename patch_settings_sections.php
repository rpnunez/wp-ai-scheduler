<?php
$content = file_get_contents('ai-post-scheduler/includes/class-aips-settings.php');

$search = <<<EOT
        add_settings_section(
            'aips_general_section',
            __('General Settings', 'ai-post-scheduler'),
            array(\$this, 'general_section_callback'),
            'aips-settings'
        );
EOT;

$replace = <<<EOT
        // ADD SECTIONS
        add_settings_section(
            'aips_general_section',
            __('General Settings', 'ai-post-scheduler'),
            array(\$this, 'general_section_callback'),
            'aips-settings'
        );
        add_settings_section(
            'aips_ai_section',
            __('AI & External APIs', 'ai-post-scheduler'),
            array(\$this, 'ai_section_callback'),
            'aips-settings'
        );
        add_settings_section(
            'aips_resilience_section',
            __('Resilience & Rate Limiting', 'ai-post-scheduler'),
            array(\$this, 'resilience_section_callback'),
            'aips-settings'
        );
        add_settings_section(
            'aips_notifications_section',
            __('Notifications', 'ai-post-scheduler'),
            array(\$this, 'notifications_section_callback'),
            'aips-settings'
        );
        add_settings_section(
            'aips_advanced_section',
            __('Advanced & Logging', 'ai-post-scheduler'),
            array(\$this, 'advanced_section_callback'),
            'aips-settings'
        );
EOT;

$content = str_replace($search, $replace, $content);

file_put_contents('ai-post-scheduler/includes/class-aips-settings.php', $content);
echo "Settings sections patched.\n";
