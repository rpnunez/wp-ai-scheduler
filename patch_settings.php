<?php
$content = file_get_contents('ai-post-scheduler/includes/class-aips-settings.php');

$search = <<<EOT
    public function register_settings() {
        register_setting('aips_settings', 'aips_default_post_status', array(
EOT;

$replace = <<<EOT
    public function register_settings() {
        // GENERAL OPTIONS
        register_setting('aips_settings', 'aips_default_post_status', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_default_category', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_default_post_author', array(
            'sanitize_callback' => 'absint'
        ));

        // API / AI OPTIONS
        register_setting('aips_settings', 'aips_ai_model', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_chatbot_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'default'
        ));
        register_setting('aips_settings', 'aips_unsplash_access_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_max_tokens', array(
            'sanitize_callback' => 'absint',
            'default' => 2000
        ));
        register_setting('aips_settings', 'aips_temperature', array(
            'sanitize_callback' => 'floatval',
            'default' => 0.7
        ));
        register_setting('aips_settings', 'aips_topic_similarity_threshold', array(
            'sanitize_callback' => array(\$this, 'sanitize_similarity_threshold'),
            'default' => 0.8
        ));

        // RESILIENCE OPTIONS
        register_setting('aips_settings', 'aips_enable_retry', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_retry_max_attempts', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_retry_initial_delay', array(
            'sanitize_callback' => 'absint',
            'default' => 1
        ));
        register_setting('aips_settings', 'aips_enable_rate_limiting', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_rate_limit_requests', array(
            'sanitize_callback' => 'absint',
            'default' => 10
        ));
        register_setting('aips_settings', 'aips_rate_limit_period', array(
            'sanitize_callback' => 'absint',
            'default' => 60
        ));
        register_setting('aips_settings', 'aips_enable_circuit_breaker', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_circuit_breaker_threshold', array(
            'sanitize_callback' => 'absint',
            'default' => 5
        ));
        register_setting('aips_settings', 'aips_circuit_breaker_timeout', array(
            'sanitize_callback' => 'absint',
            'default' => 300
        ));

        // ADVANCED / LOGGING OPTIONS
        register_setting('aips_settings', 'aips_enable_logging', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_log_retention_days', array(
            'sanitize_callback' => 'absint',
            'default' => 30
        ));
        register_setting('aips_settings', 'aips_developer_mode', array(
            'sanitize_callback' => 'absint'
        ));

        // NOTIFICATIONS OPTIONS
        register_setting('aips_settings', 'aips_review_notifications_enabled', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_review_notifications_email', array(
            'sanitize_callback' => 'sanitize_email'
        ));
EOT;

$content = str_replace(<<<EOT
    public function register_settings() {
        register_setting('aips_settings', 'aips_default_post_status', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_default_category', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_enable_logging', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_developer_mode', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_retry_max_attempts', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_ai_model', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_chatbot_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'default'
        ));
        register_setting('aips_settings', 'aips_unsplash_access_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_review_notifications_enabled', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_review_notifications_email', array(
            'sanitize_callback' => 'sanitize_email'
        ));
        register_setting('aips_settings', 'aips_topic_similarity_threshold', array(
            'sanitize_callback' => array(\$this, 'sanitize_similarity_threshold'),
            'default' => 0.8
        ));
EOT
, $replace, $content);

file_put_contents('ai-post-scheduler/includes/class-aips-settings.php', $content);
echo "Settings patched.\n";
