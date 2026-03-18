<?php
$content = file_get_contents('ai-post-scheduler/includes/class-aips-settings.php');

$search = <<<EOT
        add_settings_field(
            'aips_default_post_status',
            __('Default Post Status', 'ai-post-scheduler'),
            array(\$this, 'post_status_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_default_category',
            __('Default Category', 'ai-post-scheduler'),
            array(\$this, 'category_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_ai_model',
            __('AI Model', 'ai-post-scheduler'),
            array(\$this, 'ai_model_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_chatbot_id',
            __('Chatbot ID', 'ai-post-scheduler'),
            array(\$this, 'chatbot_id_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_unsplash_access_key',
            __('Unsplash Access Key', 'ai-post-scheduler'),
            array(\$this, 'unsplash_access_key_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_retry_max_attempts',
            __('Max Retries on Failure', 'ai-post-scheduler'),
            array(\$this, 'max_retries_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_enable_logging',
            __('Enable Logging', 'ai-post-scheduler'),
            array(\$this, 'logging_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_developer_mode',
            __('Developer Mode', 'ai-post-scheduler'),
            array(\$this, 'developer_mode_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_review_notifications_enabled',
            __('Enable Review Notifications', 'ai-post-scheduler'),
            array(\$this, 'review_notifications_enabled_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_review_notifications_email',
            __('Notification Email Address', 'ai-post-scheduler'),
            array(\$this, 'review_notifications_email_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_topic_similarity_threshold',
            __('Topic Similarity Threshold', 'ai-post-scheduler'),
            array(\$this, 'topic_similarity_threshold_callback'),
            'aips-settings',
            'aips_general_section'
        );
EOT;

$replace = <<<EOT
        // General Section Fields
        add_settings_field('aips_default_post_status', __('Default Post Status', 'ai-post-scheduler'), array(\$this, 'post_status_field_callback'), 'aips-settings', 'aips_general_section');
        add_settings_field('aips_default_category', __('Default Category', 'ai-post-scheduler'), array(\$this, 'category_field_callback'), 'aips-settings', 'aips_general_section');
        add_settings_field('aips_default_post_author', __('Default Post Author', 'ai-post-scheduler'), array(\$this, 'post_author_field_callback'), 'aips-settings', 'aips_general_section');

        // AI & API Section Fields
        add_settings_field('aips_ai_model', __('AI Model', 'ai-post-scheduler'), array(\$this, 'ai_model_field_callback'), 'aips-settings', 'aips_ai_section');
        add_settings_field('aips_chatbot_id', __('Chatbot ID', 'ai-post-scheduler'), array(\$this, 'chatbot_id_field_callback'), 'aips-settings', 'aips_ai_section');
        add_settings_field('aips_max_tokens', __('Max Tokens', 'ai-post-scheduler'), array(\$this, 'max_tokens_field_callback'), 'aips-settings', 'aips_ai_section');
        add_settings_field('aips_temperature', __('Temperature', 'ai-post-scheduler'), array(\$this, 'temperature_field_callback'), 'aips-settings', 'aips_ai_section');
        add_settings_field('aips_unsplash_access_key', __('Unsplash Access Key', 'ai-post-scheduler'), array(\$this, 'unsplash_access_key_field_callback'), 'aips-settings', 'aips_ai_section');
        add_settings_field('aips_topic_similarity_threshold', __('Topic Similarity Threshold', 'ai-post-scheduler'), array(\$this, 'topic_similarity_threshold_callback'), 'aips-settings', 'aips_ai_section');

        // Resilience Section Fields
        add_settings_field('aips_enable_retry', __('Enable Retry on Failure', 'ai-post-scheduler'), array(\$this, 'enable_retry_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_retry_max_attempts', __('Max Retries', 'ai-post-scheduler'), array(\$this, 'max_retries_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_retry_initial_delay', __('Initial Retry Delay (seconds)', 'ai-post-scheduler'), array(\$this, 'retry_delay_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_enable_rate_limiting', __('Enable Rate Limiting', 'ai-post-scheduler'), array(\$this, 'enable_rate_limiting_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_rate_limit_requests', __('Max Requests', 'ai-post-scheduler'), array(\$this, 'rate_limit_requests_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_rate_limit_period', __('Rate Limit Period (seconds)', 'ai-post-scheduler'), array(\$this, 'rate_limit_period_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_enable_circuit_breaker', __('Enable Circuit Breaker', 'ai-post-scheduler'), array(\$this, 'enable_circuit_breaker_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_circuit_breaker_threshold', __('Circuit Breaker Threshold', 'ai-post-scheduler'), array(\$this, 'circuit_breaker_threshold_field_callback'), 'aips-settings', 'aips_resilience_section');
        add_settings_field('aips_circuit_breaker_timeout', __('Circuit Breaker Timeout (seconds)', 'ai-post-scheduler'), array(\$this, 'circuit_breaker_timeout_field_callback'), 'aips-settings', 'aips_resilience_section');

        // Notifications Section Fields
        add_settings_field('aips_review_notifications_enabled', __('Enable Review Notifications', 'ai-post-scheduler'), array(\$this, 'review_notifications_enabled_field_callback'), 'aips-settings', 'aips_notifications_section');
        add_settings_field('aips_review_notifications_email', __('Notification Email Address', 'ai-post-scheduler'), array(\$this, 'review_notifications_email_field_callback'), 'aips-settings', 'aips_notifications_section');

        // Advanced & Logging Section Fields
        add_settings_field('aips_enable_logging', __('Enable Logging', 'ai-post-scheduler'), array(\$this, 'logging_field_callback'), 'aips-settings', 'aips_advanced_section');
        add_settings_field('aips_log_retention_days', __('Log Retention (Days)', 'ai-post-scheduler'), array(\$this, 'log_retention_field_callback'), 'aips-settings', 'aips_advanced_section');
        add_settings_field('aips_developer_mode', __('Developer Mode', 'ai-post-scheduler'), array(\$this, 'developer_mode_field_callback'), 'aips-settings', 'aips_advanced_section');
EOT;

$content = str_replace($search, $replace, $content);
file_put_contents('ai-post-scheduler/includes/class-aips-settings.php', $content);
echo "Settings fields patched.\n";
