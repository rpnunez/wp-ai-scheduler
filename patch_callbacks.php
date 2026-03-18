<?php
$content = file_get_contents('ai-post-scheduler/includes/class-aips-settings.php');

$callbacks = <<<EOT
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure basic settings for the AI Post Scheduler plugin.', 'ai-post-scheduler') . '</p>';
    }

    public function ai_section_callback() {
        echo '<p>' . esc_html__('Configure AI models and external APIs.', 'ai-post-scheduler') . '</p>';
    }

    public function resilience_section_callback() {
        echo '<p>' . esc_html__('Configure rate limiting, retry logic, and circuit breaking to keep the system resilient.', 'ai-post-scheduler') . '</p>';
    }

    public function notifications_section_callback() {
        echo '<p>' . esc_html__('Manage email notifications for completed or pending generated posts.', 'ai-post-scheduler') . '</p>';
    }

    public function advanced_section_callback() {
        echo '<p>' . esc_html__('Advanced configuration and system logging.', 'ai-post-scheduler') . '</p>';
    }

    // New Callbacks
    public function post_author_field_callback() {
        \$value = get_option('aips_default_post_author', 1);
        wp_dropdown_users(array(
            'name' => 'aips_default_post_author',
            'selected' => \$value,
            'show_option_none' => __('— Select User —', 'ai-post-scheduler'),
        ));
    }

    public function max_tokens_field_callback() {
        \$value = get_option('aips_max_tokens', 2000);
        echo '<input type="number" name="aips_max_tokens" value="' . esc_attr(\$value) . '" min="100" max="100000" step="100" class="small-text">';
    }

    public function temperature_field_callback() {
        \$value = get_option('aips_temperature', 0.7);
        echo '<input type="number" name="aips_temperature" value="' . esc_attr(\$value) . '" min="0" max="2" step="0.1" class="small-text">';
    }

    public function enable_retry_field_callback() {
        \$value = get_option('aips_enable_retry', 1);
        echo '<label><input type="checkbox" name="aips_enable_retry" value="1" ' . checked(1, \$value, false) . ' /> ' . esc_html__('Enable automatic retry on AI request failure.', 'ai-post-scheduler') . '</label>';
    }

    public function retry_delay_field_callback() {
        \$value = get_option('aips_retry_initial_delay', 1);
        echo '<input type="number" name="aips_retry_initial_delay" value="' . esc_attr(\$value) . '" min="1" max="60" class="small-text">';
    }

    public function enable_rate_limiting_field_callback() {
        \$value = get_option('aips_enable_rate_limiting', 0);
        echo '<label><input type="checkbox" name="aips_enable_rate_limiting" value="1" ' . checked(1, \$value, false) . ' /> ' . esc_html__('Enable rate limiting for AI requests.', 'ai-post-scheduler') . '</label>';
    }

    public function rate_limit_requests_field_callback() {
        \$value = get_option('aips_rate_limit_requests', 10);
        echo '<input type="number" name="aips_rate_limit_requests" value="' . esc_attr(\$value) . '" min="1" max="1000" class="small-text">';
    }

    public function rate_limit_period_field_callback() {
        \$value = get_option('aips_rate_limit_period', 60);
        echo '<input type="number" name="aips_rate_limit_period" value="' . esc_attr(\$value) . '" min="1" max="3600" class="small-text">';
    }

    public function enable_circuit_breaker_field_callback() {
        \$value = get_option('aips_enable_circuit_breaker', 0);
        echo '<label><input type="checkbox" name="aips_enable_circuit_breaker" value="1" ' . checked(1, \$value, false) . ' /> ' . esc_html__('Enable circuit breaker to pause requests after continuous failures.', 'ai-post-scheduler') . '</label>';
    }

    public function circuit_breaker_threshold_field_callback() {
        \$value = get_option('aips_circuit_breaker_threshold', 5);
        echo '<input type="number" name="aips_circuit_breaker_threshold" value="' . esc_attr(\$value) . '" min="1" max="50" class="small-text">';
    }

    public function circuit_breaker_timeout_field_callback() {
        \$value = get_option('aips_circuit_breaker_timeout', 300);
        echo '<input type="number" name="aips_circuit_breaker_timeout" value="' . esc_attr(\$value) . '" min="10" max="86400" class="small-text">';
    }

    public function log_retention_field_callback() {
        \$value = get_option('aips_log_retention_days', 30);
        echo '<input type="number" name="aips_log_retention_days" value="' . esc_attr(\$value) . '" min="1" max="365" class="small-text">';
    }
EOT;

$search_cb = <<<EOT
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure basic settings for the AI Post Scheduler plugin.', 'ai-post-scheduler') . '</p>';
    }
EOT;

$content = str_replace($search_cb, $callbacks, $content);
file_put_contents('ai-post-scheduler/includes/class-aips-settings.php', $content);
echo "Callbacks patched.\n";
