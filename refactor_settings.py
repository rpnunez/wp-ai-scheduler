import re

with open("/tmp/orig_class_aips_settings.php", "r") as f:
    content = f.read().replace("\r\n", "\n")

start_token = "public function register_settings() {"
end_token = "public static function get_content_strategy_options() {"

start_pos = content.find(start_token)
end_pos = content.find(end_token)

register_body = content[start_pos:end_pos]

# The initial schema call
schema_call = "\t\tself::register_setting_schema($this->ui);\n"

# Helper methods to build
sections_data = [
    ("general", "General", "General section: Default Post Status, Default Category", [
        "aips_general_section", "aips_default_post_status", "aips_default_category"
    ]),
    ("ai", "AI", "AI section: AI Model, Environment ID", [
        "aips_ai_section", "aips_ai_provider", "aips_ai_model", "aips_wp_ai_connectors",
        "aips_prevent_scheduled_ai_generation", "aips_ai_env_id", "aips_max_tokens_limit",
        "aips_max_tokens_title", "aips_max_tokens_excerpt", "aips_max_tokens_content",
        "aips_conversational_generation", "aips_conversational_metadata_turn"
    ]),
    ("feedback", "Feedback", "Feedback section: Topic Similarity Threshold", [
        "aips_feedback_section", "aips_topic_similarity_threshold"
    ]),
    ("notifications", "Notifications", "Notifications section: Email address + all per-type preferences", [
        "aips_notifications_section", "aips_review_notifications_email"
    ]),
    ("api_keys", "API Keys", "API Keys section: Unsplash Access Key", [
        "aips_api_keys_section", "aips_unsplash_access_key"
    ]),
    ("developers", "Developers", "Developers section: Enable Logging, Developer Mode", [
        "aips_developers_section", "aips_enable_logging", "aips_developer_mode",
        "aips_enable_telemetry", "aips_cache_monitor_enabled"
    ]),
    ("resilience", "Resilience & Limits", "Resilience section", [
        "aips_resilience_section", "aips_enable_retry", "aips_retry_max_attempts",
        "aips_retry_initial_delay", "aips_enable_rate_limiting", "aips_rate_limit_requests",
        "aips_rate_limit_period", "aips_enable_circuit_breaker", "aips_circuit_breaker_threshold",
        "aips_circuit_breaker_timeout"
    ]),
    ("content_strategy", "Content Strategy", "Site-wide Content Strategy settings", [
        "aips_content_strategy_section", "aips_site_niche", "aips_site_target_audience",
        "aips_site_content_goals", "aips_default_article_structure_id", "aips_site_brand_voice",
        "aips_site_content_language", "aips_site_content_guidelines", "aips_site_excluded_topics"
    ]),
    ("cache", "Cache", "Cache section: Driver selection + per-driver configuration.", [
        "aips_cache_section", "aips_enable_cache_system", "aips_cache_driver",
        "aips_cache_default_ttl", "aips_cache_db_prefix"
    ])
]
