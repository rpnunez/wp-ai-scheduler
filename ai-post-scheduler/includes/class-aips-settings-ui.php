<?php
/**
 * Class AIPS_Settings_UI
 *
 * Facade coordinating domain-specific settings UI presenters for rendering
 * settings fields/sections and sanitizing user inputs.
 *
 * @package AI_Post_Scheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings_UI {

	/** @var AIPS_Settings_UI_General */
	protected $general;

	/** @var AIPS_Settings_UI_AI */
	protected $ai;

	/** @var AIPS_Settings_UI_Resilience */
	protected $resilience;

	/** @var AIPS_Settings_UI_Logging */
	protected $logging;

	/** @var AIPS_Settings_UI_Content */
	protected $content;

	/** @var AIPS_Settings_UI_Links */
	protected $links;

	/** @var AIPS_Settings_UI_Authors */
	protected $authors;

	/** @var AIPS_Settings_UI_Integrations */
	protected $integrations;

	public function __construct(
		?AIPS_Settings_UI_General $general = null,
		?AIPS_Settings_UI_AI $ai = null,
		?AIPS_Settings_UI_Resilience $resilience = null,
		?AIPS_Settings_UI_Logging $logging = null,
		?AIPS_Settings_UI_Content $content = null,
		?AIPS_Settings_UI_Links $links = null,
		?AIPS_Settings_UI_Authors $authors = null,
		?AIPS_Settings_UI_Integrations $integrations = null
	) {
		$this->general      = $general ?? new AIPS_Settings_UI_General();
		$this->ai           = $ai ?? new AIPS_Settings_UI_AI();
		$this->resilience   = $resilience ?? new AIPS_Settings_UI_Resilience();
		$this->logging      = $logging ?? new AIPS_Settings_UI_Logging();
		$this->content      = $content ?? new AIPS_Settings_UI_Content();
		$this->links        = $links ?? new AIPS_Settings_UI_Links();
		$this->authors      = $authors ?? new AIPS_Settings_UI_Authors();
		$this->integrations = $integrations ?? new AIPS_Settings_UI_Integrations();
	}

	public function get_general(): AIPS_Settings_UI_General { return $this->general; }
	public function get_ai(): AIPS_Settings_UI_AI { return $this->ai; }
	public function get_resilience(): AIPS_Settings_UI_Resilience { return $this->resilience; }
	public function get_logging(): AIPS_Settings_UI_Logging { return $this->logging; }
	public function get_content(): AIPS_Settings_UI_Content { return $this->content; }
	public function get_links(): AIPS_Settings_UI_Links { return $this->links; }
	public function get_authors(): AIPS_Settings_UI_Authors { return $this->authors; }
	public function get_integrations(): AIPS_Settings_UI_Integrations { return $this->integrations; }

	public function sanitize_source_archive_retention_days( $value ) {
		return $this->general->sanitize_source_archive_retention_days( $value );
	}

	public function source_archive_retention_field_callback(  ) {
		$this->general->source_archive_retention_field_callback(  );
	}

	public function general_section_callback(  ) {
		$this->general->general_section_callback(  );
	}

	public function ai_section_callback(  ) {
		$this->ai->ai_section_callback(  );
	}

	public function ai_provider_section_callback(  ) {
		$this->ai->ai_provider_section_callback(  );
	}

	public function ai_tokens_section_callback(  ) {
		$this->ai->ai_tokens_section_callback(  );
	}

	public function ai_embeddings_section_callback(  ) {
		$this->ai->ai_embeddings_section_callback(  );
	}

	public function ai_scope_section_callback(  ) {
		$this->ai->ai_scope_section_callback(  );
	}

	public function ai_autolink_section_callback(  ) {
		$this->links->ai_autolink_section_callback(  );
	}

	public function link_index_section_callback(  ) {
		$this->links->link_index_section_callback(  );
	}

	public function link_rules_section_callback(  ) {
		$this->links->link_rules_section_callback(  );
	}

	public function ai_related_posts_section_callback(  ) {
		$this->links->ai_related_posts_section_callback(  );
	}

	public function ai_deduplication_section_callback(  ) {
		$this->links->ai_deduplication_section_callback(  );
	}

	public function authors_section_callback(  ) {
		$this->authors->authors_section_callback(  );
	}

	public function feedback_section_callback(  ) {
		$this->authors->feedback_section_callback(  );
	}

	public function api_keys_section_callback(  ) {
		$this->integrations->api_keys_section_callback(  );
	}

	public function developers_section_callback(  ) {
		$this->general->developers_section_callback(  );
	}

	public function post_status_field_callback(  ) {
		$this->general->post_status_field_callback(  );
	}

	public function category_field_callback(  ) {
		$this->general->category_field_callback(  );
	}

	public function bulk_publish_async_threshold_field_callback(  ) {
		$this->general->bulk_publish_async_threshold_field_callback(  );
	}

	public function sanitize_bulk_publish_async_threshold( $value ) {
		return $this->general->sanitize_bulk_publish_async_threshold( $value );
	}

	public function ai_provider_field_callback(  ) {
		$this->ai->ai_provider_field_callback(  );
	}

	public function wp_ai_connectors_field_callback(  ) {
		$this->ai->wp_ai_connectors_field_callback(  );
	}

	public function ai_model_field_callback(  ) {
		$this->ai->ai_model_field_callback(  );
	}

	public function ai_env_id_field_callback(  ) {
		$this->ai->ai_env_id_field_callback(  );
	}

	public function prevent_scheduled_ai_generation_field_callback(  ) {
		$this->ai->prevent_scheduled_ai_generation_field_callback(  );
	}

	public function max_tokens_limit_field_callback(  ) {
		$this->ai->max_tokens_limit_field_callback(  );
	}

	public function max_tokens_title_field_callback(  ) {
		$this->ai->max_tokens_title_field_callback(  );
	}

	public function max_tokens_excerpt_field_callback(  ) {
		$this->ai->max_tokens_excerpt_field_callback(  );
	}

	public function max_tokens_content_field_callback(  ) {
		$this->ai->max_tokens_content_field_callback(  );
	}

	public function unsplash_access_key_field_callback(  ) {
		$this->integrations->unsplash_access_key_field_callback(  );
	}

	public function gsc_field_callback(  ) {
		$this->integrations->gsc_field_callback(  );
	}

	public function resilience_section_callback(  ) {
		$this->resilience->resilience_section_callback(  );
	}

	public function enable_retry_field_callback(  ) {
		$this->resilience->enable_retry_field_callback(  );
	}

	public function max_retries_field_callback(  ) {
		$this->resilience->max_retries_field_callback(  );
	}

	public function retry_initial_delay_field_callback(  ) {
		$this->resilience->retry_initial_delay_field_callback(  );
	}

	public function enable_rate_limiting_field_callback(  ) {
		$this->resilience->enable_rate_limiting_field_callback(  );
	}

	public function rate_limit_requests_field_callback(  ) {
		$this->resilience->rate_limit_requests_field_callback(  );
	}

	public function rate_limit_period_field_callback(  ) {
		$this->resilience->rate_limit_period_field_callback(  );
	}

	public function generation_delay_seconds_field_callback(  ) {
		$this->resilience->generation_delay_seconds_field_callback(  );
	}

	public function batch_resume_cooldown_minutes_field_callback(  ) {
		$this->resilience->batch_resume_cooldown_minutes_field_callback(  );
	}

	public function enable_circuit_breaker_field_callback(  ) {
		$this->resilience->enable_circuit_breaker_field_callback(  );
	}

	public function circuit_breaker_threshold_field_callback(  ) {
		$this->resilience->circuit_breaker_threshold_field_callback(  );
	}

	public function circuit_breaker_timeout_field_callback(  ) {
		$this->resilience->circuit_breaker_timeout_field_callback(  );
	}

	public function logging_field_callback(  ) {
		$this->logging->logging_field_callback(  );
	}

	public function persist_table_filters_field_callback(  ) {
		$this->logging->persist_table_filters_field_callback(  );
	}

	public function conversational_generation_field_callback(  ) {
		$this->ai->conversational_generation_field_callback(  );
	}

	public function conversational_metadata_turn_field_callback(  ) {
		$this->ai->conversational_metadata_turn_field_callback(  );
	}

	public function developer_mode_field_callback(  ) {
		$this->general->developer_mode_field_callback(  );
	}

	public function enable_telemetry_field_callback(  ) {
		$this->logging->enable_telemetry_field_callback(  );
	}

	public function cache_monitor_enabled_field_callback(  ) {
		$this->logging->cache_monitor_enabled_field_callback(  );
	}

	public function review_notifications_email_field_callback(  ) {
		$this->integrations->review_notifications_email_field_callback(  );
	}

	public function notifications_section_callback(  ) {
		$this->integrations->notifications_section_callback(  );
	}

	public function notification_preference_field_callback( $args ) {
		$this->integrations->notification_preference_field_callback( $args );
	}

	public function sanitize_notification_emails( $value ) {
		return $this->integrations->sanitize_notification_emails( $value );
	}

	public function sanitize_notification_preferences( $value ) {
		return $this->integrations->sanitize_notification_preferences( $value );
	}

	public function sanitize_similarity_threshold( $value ) {
		return $this->authors->sanitize_similarity_threshold( $value );
	}

	public function sanitize_autolink_threshold( $value ) {
		return $this->links->sanitize_autolink_threshold( $value );
	}

	public function sanitize_autolink_limit( $value ) {
		return $this->links->sanitize_autolink_limit( $value );
	}

	public function sanitize_link_index_batch_size( $value ) {
		return $this->links->sanitize_link_index_batch_size( $value );
	}

	public function sanitize_link_index_batch_delay( $value ) {
		return $this->links->sanitize_link_index_batch_delay( $value );
	}

	public function sanitize_link_rules_max_per_post( $value ) {
		return $this->links->sanitize_link_rules_max_per_post( $value );
	}

	public function sanitize_publish_linking_mode( $value ) {
		return $this->links->sanitize_publish_linking_mode( $value );
	}

	public function sanitize_link_click_retention_days( $value ) {
		return $this->links->sanitize_link_click_retention_days( $value );
	}

	public function sanitize_silo_guide_position( $value ) {
		return $this->links->sanitize_silo_guide_position( $value );
	}

	public function sanitize_silo_guide_max( $value ) {
		return $this->links->sanitize_silo_guide_max( $value );
	}

	public function sanitize_silo_guide_style( $value ) {
		return $this->links->sanitize_silo_guide_style( $value );
	}

	public function sanitize_autolink_rel( $value ) {
		return $this->links->sanitize_autolink_rel( $value );
	}

	public function sanitize_token_budget( $value ) {
		return $this->ai->sanitize_token_budget( $value );
	}

	public function topic_similarity_threshold_field_callback(  ) {
		$this->authors->topic_similarity_threshold_field_callback(  );
	}

	public function ai_assistance_section_callback(  ) {
		$this->content->ai_assistance_section_callback(  );
	}

	public function ai_assistance_enabled_field_callback(  ) {
		$this->content->ai_assistance_enabled_field_callback(  );
	}

	public function ai_assistance_contexts_field_callback(  ) {
		$this->content->ai_assistance_contexts_field_callback(  );
	}

	public function ai_assistance_response_length_field_callback(  ) {
		$this->content->ai_assistance_response_length_field_callback(  );
	}

	public function ai_assistance_guidance_field_callback(  ) {
		$this->content->ai_assistance_guidance_field_callback(  );
	}

	public function ai_assistance_overwrite_mode_field_callback(  ) {
		$this->content->ai_assistance_overwrite_mode_field_callback(  );
	}

	public function ai_assistance_retention_days_field_callback(  ) {
		$this->content->ai_assistance_retention_days_field_callback(  );
	}

	public function content_strategy_section_callback(  ) {
		$this->content->content_strategy_section_callback(  );
	}

	public function site_niche_field_callback(  ) {
		$this->content->site_niche_field_callback(  );
	}

	public function site_target_audience_field_callback(  ) {
		$this->content->site_target_audience_field_callback(  );
	}

	public function site_content_goals_field_callback(  ) {
		$this->content->site_content_goals_field_callback(  );
	}

	public function site_default_article_structure_field_callback(  ) {
		$this->content->site_default_article_structure_field_callback(  );
	}

	public function site_brand_voice_field_callback(  ) {
		$this->content->site_brand_voice_field_callback(  );
	}

	public function site_content_language_field_callback(  ) {
		$this->content->site_content_language_field_callback(  );
	}

	public function site_content_guidelines_field_callback(  ) {
		$this->content->site_content_guidelines_field_callback(  );
	}

	public function site_excluded_topics_field_callback(  ) {
		$this->content->site_excluded_topics_field_callback(  );
	}

	public function cache_section_callback(  ) {
		$this->logging->cache_section_callback(  );
	}

	public function enable_cache_system_field_callback(  ) {
		$this->logging->enable_cache_system_field_callback(  );
	}

	public function sanitize_enable_cache_system( $value ) {
		return $this->logging->sanitize_enable_cache_system( $value );
	}

	public function cache_driver_field_callback(  ) {
		$this->logging->cache_driver_field_callback(  );
	}

	public function cache_default_ttl_field_callback(  ) {
		$this->logging->cache_default_ttl_field_callback(  );
	}

	public function cache_db_prefix_field_callback(  ) {
		$this->logging->cache_db_prefix_field_callback(  );
	}

	public function sanitize_cache_driver( $value ) {
		return $this->logging->sanitize_cache_driver( $value );
	}

	public function sanitize_ai_provider( $value ) {
		return $this->ai->sanitize_ai_provider( $value );
	}

	public function sanitize_wp_ai_connector_mode( $value ) {
		return $this->ai->sanitize_wp_ai_connector_mode( $value );
	}

	public function sanitize_wp_ai_connector_ids( $value ) {
		return $this->ai->sanitize_wp_ai_connector_ids( $value );
	}

	public function embeddings_header_callback(  ) {
		$this->ai->embeddings_header_callback(  );
	}

	public function embeddings_enabled_field_callback(  ) {
		$this->ai->embeddings_enabled_field_callback(  );
	}

	public function embeddings_provider_field_callback(  ) {
		$this->ai->embeddings_provider_field_callback(  );
	}

	public function embeddings_model_field_callback(  ) {
		$this->ai->embeddings_model_field_callback(  );
	}

	public function embeddings_env_id_field_callback(  ) {
		$this->ai->embeddings_env_id_field_callback(  );
	}

	public function embeddings_dimensions_field_callback(  ) {
		$this->ai->embeddings_dimensions_field_callback(  );
	}

	public function embeddings_persistent_cache_field_callback(  ) {
		$this->ai->embeddings_persistent_cache_field_callback(  );
	}

	public function embeddings_quota_meters_field_callback(  ) {
		$this->ai->embeddings_quota_meters_field_callback(  );
	}

	public function embeddings_scope_field_callback(  ) {
		$this->ai->embeddings_scope_field_callback(  );
	}

	public function indexer_post_types_field_callback(  ) {
		$this->links->indexer_post_types_field_callback(  );
	}

	public function auto_index_on_publish_field_callback(  ) {
		$this->links->auto_index_on_publish_field_callback(  );
	}

	public function indexer_verbose_history_field_callback(  ) {
		$this->links->indexer_verbose_history_field_callback(  );
	}

	public function embeddings_rate_limits_field_callback(  ) {
		$this->links->embeddings_rate_limits_field_callback(  );
	}

	public function link_index_field_callback(  ) {
		$this->links->link_index_field_callback(  );
	}

	public function link_rules_field_callback(  ) {
		$this->links->link_rules_field_callback(  );
	}

	public function publish_linking_field_callback(  ) {
		$this->links->publish_linking_field_callback(  );
	}

	public function link_click_tracking_field_callback(  ) {
		$this->links->link_click_tracking_field_callback(  );
	}

	public function silo_guide_field_callback(  ) {
		$this->links->silo_guide_field_callback(  );
	}

	public function link_index_throttle_field_callback(  ) {
		$this->links->link_index_throttle_field_callback(  );
	}

	public function autolink_enabled_field_callback(  ) {
		$this->links->autolink_enabled_field_callback(  );
	}

	public function autolink_thresholds_field_callback(  ) {
		$this->links->autolink_thresholds_field_callback(  );
	}

	public function autolink_limits_field_callback(  ) {
		$this->links->autolink_limits_field_callback(  );
	}

	public function autolink_placement_field_callback(  ) {
		$this->links->autolink_placement_field_callback(  );
	}

	public function indexer_similarity_threshold_field_callback(  ) {
		$this->links->indexer_similarity_threshold_field_callback(  );
	}

	public function indexer_publish_execution_timing_field_callback(  ) {
		$this->links->indexer_publish_execution_timing_field_callback(  );
	}

	public function indexer_batch_config_field_callback(  ) {
		$this->links->indexer_batch_config_field_callback(  );
	}

	public function indexer_error_cooldown_field_callback(  ) {
		$this->links->indexer_error_cooldown_field_callback(  );
	}

	public function indexer_post_cluster_threshold_field_callback(  ) {
		$this->links->indexer_post_cluster_threshold_field_callback(  );
	}

	public function cluster_detection_limits_field_callback(  ) {
		$this->links->cluster_detection_limits_field_callback(  );
	}

	public function cluster_defaults_field_callback(  ) {
		$this->links->cluster_defaults_field_callback(  );
	}

	public function enable_post_insights_ui_field_callback(  ) {
		$this->links->enable_post_insights_ui_field_callback(  );
	}

	public function indexer_topics_continuous_sync_field_callback(  ) {
		$this->links->indexer_topics_continuous_sync_field_callback(  );
	}

	public function author_topic_auto_approval_mode_field_callback(  ) {
		$this->authors->author_topic_auto_approval_mode_field_callback(  );
	}

	public function author_topic_auto_approval_min_score_field_callback(  ) {
		$this->authors->author_topic_auto_approval_min_score_field_callback(  );
	}

	public function author_topic_auto_approval_min_relevance_field_callback(  ) {
		$this->authors->author_topic_auto_approval_min_relevance_field_callback(  );
	}

	public function author_topic_auto_approval_max_similarity_field_callback(  ) {
		$this->authors->author_topic_auto_approval_max_similarity_field_callback(  );
	}

	public function author_topic_auto_approval_fallback_field_callback(  ) {
		$this->authors->author_topic_auto_approval_fallback_field_callback(  );
	}

	public function related_posts_enabled_field_callback(  ) {
		$this->links->related_posts_enabled_field_callback(  );
	}

	public function related_posts_auto_append_field_callback(  ) {
		$this->links->related_posts_auto_append_field_callback(  );
	}

	public function related_posts_heading_field_callback(  ) {
		$this->links->related_posts_heading_field_callback(  );
	}

	public function related_posts_count_layout_field_callback(  ) {
		$this->links->related_posts_count_layout_field_callback(  );
	}

	public function related_posts_shortcode_field_callback(  ) {
		$this->links->related_posts_shortcode_field_callback(  );
	}

	public function deduplication_mode_field_callback(  ) {
		$this->links->deduplication_mode_field_callback(  );
	}

	public function deduplication_threshold_field_callback(  ) {
		$this->links->deduplication_threshold_field_callback(  );
	}

	public function duplicate_review_threshold_field_callback(  ) {
		$this->links->duplicate_review_threshold_field_callback(  );
	}

	public function duplicate_review_limits_field_callback(  ) {
		$this->links->duplicate_review_limits_field_callback(  );
	}

	public function duplicate_keep_strategy_field_callback(  ) {
		$this->links->duplicate_keep_strategy_field_callback(  );
	}

	public function duplicate_protection_field_callback(  ) {
		$this->links->duplicate_protection_field_callback(  );
	}

	public function duplicate_exclusions_field_callback(  ) {
		$this->links->duplicate_exclusions_field_callback(  );
	}

	public function duplicate_default_merge_field_callback(  ) {
		$this->links->duplicate_default_merge_field_callback(  );
	}

	public function duplicate_scan_schedule_field_callback(  ) {
		$this->links->duplicate_scan_schedule_field_callback(  );
	}

	public function sanitize_cluster_detection_max_posts( $value ) {
		return $this->links->sanitize_cluster_detection_max_posts( $value );
	}

	public function sanitize_cluster_detection_time_budget( $value ) {
		return $this->links->sanitize_cluster_detection_time_budget( $value );
	}

	public function sanitize_duplicate_review_threshold( $value ) {
		return $this->links->sanitize_duplicate_review_threshold( $value );
	}

	public function sanitize_duplicate_review_max_group_size( $value ) {
		return $this->links->sanitize_duplicate_review_max_group_size( $value );
	}

	public function sanitize_duplicate_review_max_pairs( $value ) {
		return $this->links->sanitize_duplicate_review_max_pairs( $value );
	}

	public function sanitize_duplicate_excluded_post_types( $value ) {
		return $this->links->sanitize_duplicate_excluded_post_types( $value );
	}

	public function sanitize_duplicate_excluded_categories( $value ) {
		return $this->links->sanitize_duplicate_excluded_categories( $value );
	}

	public function sanitize_duplicate_excluded_post_ids( $value ) {
		return $this->links->sanitize_duplicate_excluded_post_ids( $value );
	}

	public function sanitize_duplicate_protect_inbound_links( $value ) {
		return $this->links->sanitize_duplicate_protect_inbound_links( $value );
	}

	public function sanitize_duplicate_default_merge_mode( $value ) {
		return $this->links->sanitize_duplicate_default_merge_mode( $value );
	}

	public function sanitize_duplicate_scan_schedule( $value ) {
		return $this->links->sanitize_duplicate_scan_schedule( $value );
	}

	public function sanitize_cluster_default_min_size( $value ) {
		return $this->links->sanitize_cluster_default_min_size( $value );
	}

	public function sanitize_cluster_tightness( $value ) {
		return $this->links->sanitize_cluster_tightness( $value );
	}

	public function sanitize_pillar_spoke_similarity( $value ) {
		return $this->links->sanitize_pillar_spoke_similarity( $value );
	}

	public function sanitize_audit_result_limit( $value ) {
		return $this->links->sanitize_audit_result_limit( $value );
	}

	public function sanitize_duplicate_keep_strategy( $value ) {
		return $this->links->sanitize_duplicate_keep_strategy( $value );
	}

	public function sanitize_embeddings_scope( $value ) {
		return $this->links->sanitize_embeddings_scope( $value );
	}

	public function sanitize_related_posts_layout( $value ) {
		return $this->links->sanitize_related_posts_layout( $value );
	}

	public function sanitize_deduplication_mode( $value ) {
		return $this->links->sanitize_deduplication_mode( $value );
	}

	public function sanitize_post_types( $value ) {
		return $this->links->sanitize_post_types( $value );
	}

	public function sanitize_publish_execution_timing( $value ) {
		return $this->links->sanitize_publish_execution_timing( $value );
	}

	public function sanitize_error_pause_unit( $value ) {
		return $this->links->sanitize_error_pause_unit( $value );
	}

	public function sanitize_author_topic_auto_approval_mode( $value ) {
		return $this->authors->sanitize_author_topic_auto_approval_mode( $value );
	}

	public function sanitize_author_topic_auto_approval_fallback( $value ) {
		return $this->authors->sanitize_author_topic_auto_approval_fallback( $value );
	}

	public function sanitize_author_topic_auto_approval_percent( $value ) {
		return $this->authors->sanitize_author_topic_auto_approval_percent( $value );
	}

	public function sanitize_author_topic_auto_approval_max_similarity( $value ) {
		return $this->authors->sanitize_author_topic_auto_approval_max_similarity( $value );
	}

	/**
	 * Render description for the Database & Retention section.
	 *
	 * @return void
	 */
	public function database_retention_section_callback() {
		echo '<p>' . esc_html__('Configure automatic database pruning policies to keep high-growth tables (telemetry, execution logs, orphaned embeddings) lean and performant.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the automated pruning master toggle.
	 *
	 * @return void
	 */
	public function auto_prune_enabled_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_auto_prune_enabled');
		?>
		<input type="hidden" name="aips_auto_prune_enabled" value="0">
		<label>
			<input type="checkbox" name="aips_auto_prune_enabled" value="1" <?php checked($value, true); ?>>
			<?php esc_html_e('Enable scheduled automatic database pruning', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('When enabled, background cleanups run via WP-Cron according to the schedule configured below.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the telemetry retention period field (number + unit).
	 *
	 * @return void
	 */
	public function telemetry_retention_field_callback() {
		$config = AIPS_Config::get_instance();
		$value  = (int) $config->get_option('aips_telemetry_retention_value', 30);
		$unit   = (string) $config->get_option('aips_telemetry_retention_unit', 'days');
		?>
		<div style="display: flex; align-items: center; gap: 8px;">
			<input type="number" name="aips_telemetry_retention_value" value="<?php echo esc_attr((string) $value); ?>" min="1" max="365" style="width: 80px;">
			<select name="aips_telemetry_retention_unit">
				<option value="days" <?php selected($unit, 'days'); ?>><?php esc_html_e('Days', 'ai-post-scheduler'); ?></option>
				<option value="weeks" <?php selected($unit, 'weeks'); ?>><?php esc_html_e('Weeks', 'ai-post-scheduler'); ?></option>
				<option value="months" <?php selected($unit, 'months'); ?>><?php esc_html_e('Months', 'ai-post-scheduler'); ?></option>
			</select>
		</div>
		<p class="description"><?php esc_html_e('Telemetry records older than this period will be pruned during scheduled and manual cleanups.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the prune frequency selector.
	 *
	 * @return void
	 */
	public function prune_interval_field_callback() {
		$interval = (string) AIPS_Config::get_instance()->get_option('aips_telemetry_prune_interval', 'daily');
		?>
		<select name="aips_telemetry_prune_interval">
			<option value="daily" <?php selected($interval, 'daily'); ?>><?php esc_html_e('Daily', 'ai-post-scheduler'); ?></option>
			<option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'ai-post-scheduler'); ?></option>
			<option value="weekly" <?php selected($interval, 'weekly'); ?>><?php esc_html_e('Weekly', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('How often the background cleanup cron job should run when automatic pruning is enabled.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the history log retention field (number + unit).
	 *
	 * @return void
	 */
	public function history_log_retention_field_callback() {
		$config = AIPS_Config::get_instance();
		$value  = (int) $config->get_option('aips_history_log_retention_value', 60);
		$unit   = (string) $config->get_option('aips_history_log_retention_unit', 'days');
		?>
		<div style="display: flex; align-items: center; gap: 8px;">
			<input type="number" name="aips_history_log_retention_value" value="<?php echo esc_attr((string) $value); ?>" min="1" max="730" style="width: 80px;">
			<select name="aips_history_log_retention_unit">
				<option value="days" <?php selected($unit, 'days'); ?>><?php esc_html_e('Days', 'ai-post-scheduler'); ?></option>
				<option value="weeks" <?php selected($unit, 'weeks'); ?>><?php esc_html_e('Weeks', 'ai-post-scheduler'); ?></option>
				<option value="months" <?php selected($unit, 'months'); ?>><?php esc_html_e('Months', 'ai-post-scheduler'); ?></option>
			</select>
		</div>
		<p class="description"><?php esc_html_e('Detailed generation execution events in aips_history_log older than this threshold will be pruned.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render checkboxes for cleaning orphaned embeddings and expired topics.
	 *
	 * @return void
	 */
	public function clean_orphaned_data_field_callback() {
		$config           = AIPS_Config::get_instance();
		$clean_embeddings = (bool) $config->get_option('aips_clean_orphaned_embeddings', true);
		$clean_topics     = (bool) $config->get_option('aips_clean_expired_topics', true);
		?>
		<input type="hidden" name="aips_clean_orphaned_embeddings" value="0">
		<input type="hidden" name="aips_clean_expired_topics" value="0">
		<label style="display: block; margin-bottom: 6px;">
			<input type="checkbox" name="aips_clean_orphaned_embeddings" value="1" <?php checked($clean_embeddings, true); ?>>
			<?php esc_html_e('Purge orphaned embeddings (vectors for posts deleted from WordPress)', 'ai-post-scheduler'); ?>
		</label>
		<label style="display: block;">
			<input type="checkbox" name="aips_clean_expired_topics" value="1" <?php checked($clean_topics, true); ?>>
			<?php esc_html_e('Purge rejected and expired author topics older than 30 days', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize retention unit selection.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */
	public function sanitize_retention_unit($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('days', 'weeks', 'months'), true) ? $value : 'days';
	}

	/**
	 * Sanitize prune interval schedule.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */
	public function sanitize_prune_interval($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('daily', 'twicedaily', 'weekly'), true) ? $value : 'daily';
	}

}
