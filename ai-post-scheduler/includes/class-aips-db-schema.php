<?php
if (!defined("ABSPATH")) {
    exit;
}

/**
 * Manages database table DDL schema definitions for AI Post Scheduler.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_DB_Schema {

    /**
     * Returns array of SQL CREATE TABLE statements for all plugin tables.
     *
     * @return array Array of SQL CREATE TABLE queries indexed by table key.
     */
    public function get_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = AIPS_DB_Manager::get_full_table_names();

        $table_history = $tables['aips_history'];
        $table_history_log = $tables['aips_history_log'];
        $table_campaigns = $tables['aips_campaigns'];
        $table_templates = $tables['aips_templates'];
        $table_schedule = $tables['aips_schedule'];
        $table_voices = $tables['aips_voices'];
        $table_structures = $tables['aips_article_structures'];
        $table_sections = $tables['aips_prompt_sections'];
        $table_trending_topics = $tables['aips_trending_topics'];
        $table_authors = $tables['aips_authors'];
        $table_post_slices = $tables['aips_post_slices'];
        $table_author_topics = $tables['aips_author_topics'];
        $table_author_topic_logs = $tables['aips_author_topic_logs'];
        $table_topic_feedback = $tables['aips_topic_feedback'];
        $table_notifications        = $tables['aips_notifications'];
        $table_sources              = $tables['aips_sources'];
        $table_source_group_terms   = $tables['aips_source_group_terms'];
        $table_sources_data         = $tables['aips_sources_data'];
        $table_taxonomy             = $tables['aips_taxonomy'];
        $table_embeddings           = $tables['aips_embeddings'];
        $table_relationships        = $tables['aips_relationships'];
        $table_internal_links       = $tables['aips_internal_links'];
        $table_affiliate_links      = $tables['aips_affiliate_links'];
        $table_cache                = $tables['aips_cache'];
        $table_telemetry            = $tables['aips_telemetry'];
        $table_ai_assistance        = $tables['aips_ai_assistance'];
        $table_bulk_batch_jobs      = $tables['aips_bulk_batch_jobs'];
        $table_cache_index          = $tables['aips_cache_index'];
        $table_cache_events         = $tables['aips_cache_events'];
        $table_integration_field_mappings = $tables['aips_integration_field_mappings'];
        $table_content_audits       = $tables['aips_content_audits'];
        $table_link_index           = $tables['aips_link_index'];
        $table_link_clicks          = $tables['aips_link_clicks'];
        $table_redirects            = $tables['aips_redirects'];

        $sql = array();

        $sql[] = "CREATE TABLE $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            uuid varchar(36) DEFAULT NULL,
            correlation_id varchar(36) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            post_type varchar(50) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            author_id bigint(20) DEFAULT NULL,
            topic_id bigint(20) DEFAULT NULL,
            creation_method varchar(64) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            prompt text,
            generated_title varchar(500),
            generated_content longtext,
            generation_log longtext,
            error_message text,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            completed_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY post_id (post_id),
            KEY post_type (post_type),
            KEY template_id (template_id),
            KEY campaign_id (campaign_id),
            KEY author_id (author_id),
            KEY topic_id (topic_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_created (status, created_at),
            KEY template_created (template_id, created_at),
            KEY template_status (template_id, status),
            KEY post_status (post_id, status),
            KEY correlation_id (correlation_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_history_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            history_id bigint(20) NOT NULL,
            history_type_id int DEFAULT 1,
            event_type varchar(64) DEFAULT NULL,
            event_status varchar(32) DEFAULT NULL,
            timestamp bigint(20) unsigned NOT NULL DEFAULT 0,
            details longtext,
            PRIMARY KEY  (id),
            KEY history_type_id (history_type_id),
            KEY history_type_timestamp (history_id, history_type_id, timestamp),
            KEY event_status (event_status),
            KEY event_type_timestamp (event_type, timestamp)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_campaigns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            content_goal text,
            campaign_mode varchar(20) DEFAULT 'template',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY is_active (is_active),
            KEY is_archived (is_archived),
            KEY campaign_mode (campaign_mode),
            KEY active_archived (is_active, is_archived)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            prompt_template text NOT NULL,
            title_prompt text,
            voice_id bigint(20) DEFAULT NULL,
            post_quantity int DEFAULT 1,
            image_prompt text,
            generate_featured_image tinyint(1) DEFAULT 0,
            featured_image_source varchar(50) DEFAULT 'ai_prompt',
            featured_image_unsplash_keywords text,
            featured_image_media_ids text,
            post_status varchar(50) DEFAULT 'draft',
            post_type varchar(50) DEFAULT 'post',
            post_category text DEFAULT NULL,
            post_tags text,
            post_author bigint(20) DEFAULT NULL,
            include_sources tinyint(1) DEFAULT 0,
            source_group_ids text DEFAULT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            affiliate_links_enabled tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_schedule (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT NULL,
            article_structure_id bigint(20) DEFAULT NULL,
            rotation_pattern varchar(50) DEFAULT NULL,
            frequency varchar(50) NOT NULL DEFAULT 'daily',
            topic TEXT DEFAULT NULL,
            next_run bigint(20) unsigned NOT NULL DEFAULT 0,
            last_run bigint(20) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            schedule_history_id bigint(20) DEFAULT NULL,
            schedule_type varchar(50) NOT NULL DEFAULT 'post_generation',
            circuit_state varchar(20) NOT NULL DEFAULT 'closed',
            run_state text DEFAULT NULL,
            batch_progress longtext DEFAULT NULL,
            author_id bigint(20) DEFAULT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            campaign_mode varchar(20) DEFAULT 'template',
            post_type_rules longtext DEFAULT NULL,
            blackout_dates text DEFAULT NULL,
            time_window_start varchar(5) DEFAULT NULL,
            time_window_end varchar(5) DEFAULT NULL,
            day_preferences varchar(255) DEFAULT NULL,
            season_end_date bigint(20) unsigned DEFAULT NULL,
            dynamic_quantity_rules text DEFAULT NULL,
            campaign_metadata longtext DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY article_structure_id (article_structure_id),
            KEY author_id (author_id),
            KEY campaign_id (campaign_id),
            KEY next_run (next_run),
            KEY is_active_next_run (is_active, next_run),
            KEY status (status),
            KEY schedule_history_id (schedule_history_id),
            KEY schedule_type (schedule_type),
            KEY circuit_state (circuit_state),
            KEY campaign_mode (campaign_mode),
            KEY season_end_date (season_end_date)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_voices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            title_prompt text NOT NULL,
            content_instructions text NOT NULL,
            excerpt_instructions text,
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_structures (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            structure_data longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
			KEY is_active (is_active)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_sections (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            section_key varchar(100) NOT NULL,
            content text NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY section_key (section_key),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_trending_topics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            niche varchar(255) NOT NULL,
            topic varchar(500) NOT NULL,
            score int(11) NOT NULL DEFAULT 50,
            reason text DEFAULT NULL,
            keywords text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            researched_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY niche_idx (niche),
            KEY score_idx (score),
            KEY status_idx (status),
            KEY researched_at_idx (researched_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_authors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            field_niche varchar(500) NOT NULL,
            description text,
            keywords text,
            details text,
            article_structure_id bigint(20) DEFAULT NULL,
            topic_generation_prompt text,
            topic_generation_frequency varchar(50) DEFAULT 'weekly',
            topic_generation_quantity int DEFAULT 5,
            topic_generation_next_run bigint(20) unsigned NOT NULL DEFAULT 0,
            topic_generation_last_run bigint(20) unsigned NOT NULL DEFAULT 0,
            topic_generation_is_active tinyint(1) DEFAULT 1,
            post_generation_frequency varchar(50) DEFAULT 'daily',
            post_generation_next_run bigint(20) unsigned NOT NULL DEFAULT 0,
            post_generation_last_run bigint(20) unsigned NOT NULL DEFAULT 0,
            post_generation_is_active tinyint(1) DEFAULT 1,
            post_status varchar(50) DEFAULT 'draft',
            post_category bigint(20) DEFAULT NULL,
            post_tags text,
            post_author bigint(20) DEFAULT NULL,
            generate_featured_image tinyint(1) DEFAULT 0,
            featured_image_source varchar(50) DEFAULT 'ai_prompt',
            voice_tone varchar(500) DEFAULT NULL,
            writing_style varchar(500) DEFAULT NULL,
            target_audience varchar(500) DEFAULT NULL,
            expertise_level varchar(50) DEFAULT NULL,
            content_goals text DEFAULT NULL,
            excluded_topics text DEFAULT NULL,
            preferred_content_length varchar(50) DEFAULT NULL,
            language varchar(10) DEFAULT 'en',
            max_posts_per_topic int DEFAULT 1,
            manual_post_generation_quantity int DEFAULT 1,
            scheduled_post_generation_quantity int DEFAULT 1,
            include_sources tinyint(1) DEFAULT 0,
            source_group_ids text DEFAULT NULL,
            affiliate_links_enabled tinyint(1) DEFAULT 0,
            topic_auto_approval_mode varchar(50) DEFAULT 'manual',
            topic_auto_approval_min_score int DEFAULT 70,
            topic_auto_approval_max_similarity decimal(5,4) DEFAULT 0.8000,
            topic_auto_approval_fallback varchar(50) DEFAULT 'pending',
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY article_structure_id (article_structure_id),
            KEY is_active (is_active),
          KEY topic_generation_next_run (topic_generation_next_run),
          KEY post_generation_next_run (post_generation_next_run)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_post_slices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_author_topics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_id bigint(20) NOT NULL,
            topic_title varchar(500) NOT NULL,
            topic_prompt text,
            status varchar(20) DEFAULT 'pending',
            score int DEFAULT 50,
            metadata longtext,
            generated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_at bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY author_id (author_id),
            KEY status (status),
            KEY generated_at (generated_at),
            KEY author_id_status (author_id, status),
            KEY status_score_reviewed (status, score, reviewed_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_author_topic_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_topic_id bigint(20) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            action varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            notes text,
            metadata longtext,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY author_topic_id (author_topic_id),
            KEY post_id (post_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_topic_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_topic_id bigint(20) NOT NULL,
            action varchar(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            reason text,
            reason_category varchar(50) DEFAULT 'other',
            source varchar(50) DEFAULT 'UI',
            notes text,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY author_topic_id (author_topic_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY reason_category (reason_category),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(100) NOT NULL,
            title varchar(255) DEFAULT NULL,
            message text NOT NULL,
            url varchar(500) DEFAULT NULL,
            level varchar(20) NOT NULL DEFAULT 'info',
            meta longtext DEFAULT NULL,
            dedupe_key varchar(191) DEFAULT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            read_at bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY level (level),
            KEY dedupe_key (dedupe_key),
            KEY is_read (is_read),
            KEY created_at (created_at),
            KEY is_read_created_at (is_read, created_at),
            KEY dedupe_key_created_at (dedupe_key, created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_sources (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2083) NOT NULL,
            label varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            fetch_interval varchar(50) DEFAULT NULL,
            last_fetched_at bigint(20) unsigned NOT NULL DEFAULT 0,
            next_fetch_at bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY is_active (is_active),
            KEY fetch_interval (fetch_interval),
            KEY next_fetch_at (next_fetch_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_source_group_terms (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            term_id bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_term (source_id, term_id),
            KEY source_id (source_id),
            KEY term_id (term_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_sources_data (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            url varchar(2083) NOT NULL DEFAULT '',
            page_title varchar(500) DEFAULT NULL,
            meta_description text DEFAULT NULL,
            extracted_text longtext DEFAULT NULL,
            raw_html longtext DEFAULT NULL,
            char_count int NOT NULL DEFAULT 0,
            content_hash varchar(64) DEFAULT NULL,
            num_used int NOT NULL DEFAULT 0,
            fetch_status varchar(20) NOT NULL DEFAULT 'pending',
            http_status int DEFAULT NULL,
            error_message text DEFAULT NULL,
            fetched_at bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY source_content_hash (source_id, content_hash),
            KEY source_id (source_id),
            KEY fetch_status (fetch_status),
            KEY fetched_at (fetched_at),
            KEY num_used (num_used)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_taxonomy (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            taxonomy_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            base_post_ids text DEFAULT NULL,
            generation_prompt text DEFAULT NULL,
            term_id bigint(20) DEFAULT NULL,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY taxonomy_type (taxonomy_type),
            KEY status (status),
            KEY term_id (term_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_embeddings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            object_type varchar(32) NOT NULL DEFAULT 'post',
            object_post_type varchar(50) DEFAULT '',
            object_id bigint(20) NOT NULL,
            content_hash varchar(64) DEFAULT '',
            embedding mediumblob NOT NULL,
            dimensions int(11) DEFAULT 0,
            model varchar(100) DEFAULT '',
            indexed_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY object_lookup (object_type, object_id),
            KEY object_post_type (object_post_type),
            KEY model_dim (model, dimensions),
            KEY indexed_at (indexed_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_relationships (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_type varchar(32) NOT NULL DEFAULT 'post',
            source_id bigint(20) NOT NULL,
            target_type varchar(32) NOT NULL DEFAULT 'post',
            target_id bigint(20) NOT NULL,
            similarity decimal(5,4) NOT NULL DEFAULT 0.0000,
            relation_type varchar(32) NOT NULL DEFAULT 'related_post',
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY source_target_rel (source_type, source_id, target_type, target_id, relation_type),
            KEY source_idx (source_type, source_id),
            KEY target_idx (target_type, target_id),
            KEY similarity_idx (similarity),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_internal_links (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) NOT NULL,
            target_post_id bigint(20) NOT NULL,
            similarity_score float NOT NULL DEFAULT 0,
            anchor_text varchar(500) DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'pending',
            origin varchar(20) NOT NULL DEFAULT 'outbound',
            confidence decimal(5,4) NOT NULL DEFAULT 0.0000,
            anchor_source varchar(20) NOT NULL DEFAULT '',
            match_context text,
            batch_id varchar(36) DEFAULT NULL,
            before_snippet longtext,
            after_snippet longtext,
            applied_at bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY source_post_id (source_post_id),
            KEY target_post_id (target_post_id),
            KEY status (status),
            KEY similarity_score (similarity_score),
            KEY target_status (target_post_id, status),
            KEY origin (origin),
            KEY batch_id (batch_id),
            UNIQUE KEY source_target (source_post_id, target_post_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_link_index (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) NOT NULL,
            target_post_id bigint(20) NOT NULL DEFAULT 0,
            target_url text NOT NULL,
            url_hash char(32) NOT NULL DEFAULT '',
            anchor_text varchar(500) DEFAULT '',
            link_type varchar(10) NOT NULL DEFAULT 'internal',
            rel varchar(100) DEFAULT '',
            is_nofollow tinyint(1) NOT NULL DEFAULT 0,
            inserted_by_aips tinyint(1) NOT NULL DEFAULT 0,
            position int(11) NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY source_post_id (source_post_id),
            KEY target_link (target_post_id, link_type),
            KEY url_hash (url_hash),
            KEY link_type (link_type)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_link_clicks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_post_id bigint(20) NOT NULL,
            target_post_id bigint(20) NOT NULL,
            day_start bigint(20) unsigned NOT NULL DEFAULT 0,
            clicks int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY link_day (source_post_id, target_post_id, day_start),
            KEY target_day (target_post_id, day_start),
            KEY day_start (day_start)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_redirects (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_path varchar(500) NOT NULL,
            source_hash char(32) NOT NULL,
            target_url text NOT NULL,
            target_post_id bigint(20) NOT NULL DEFAULT 0,
            status_code smallint(3) NOT NULL DEFAULT 301,
            provider varchar(20) NOT NULL DEFAULT 'aips',
            provider_ref varchar(191) NOT NULL DEFAULT '',
            provider_error varchar(255) NOT NULL DEFAULT '',
            origin varchar(30) NOT NULL DEFAULT 'manual',
            origin_ref bigint(20) NOT NULL DEFAULT 0,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            last_hit_at bigint(20) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY source_hash (source_hash),
            KEY provider (provider),
            KEY origin (origin, origin_ref),
            KEY target_post_id (target_post_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_affiliate_links (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tag varchar(255) NOT NULL,
            label varchar(255) NOT NULL DEFAULT '',
            affiliate_url text NOT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            cta_html longtext,
            cta_position varchar(20) NOT NULL DEFAULT 'append',
            cta_heading varchar(255) DEFAULT NULL,
            cta_match_text varchar(255) DEFAULT NULL,
            cta_max_insertions tinyint(3) unsigned NOT NULL DEFAULT 1,
            use_ai_injection tinyint(1) NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY tag (tag),
            KEY enabled (enabled)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(191) NOT NULL,
            cache_group varchar(100) NOT NULL DEFAULT 'default',
            value longtext NOT NULL,
            expires_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key_group (cache_key, cache_group),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_telemetry (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL DEFAULT '',
            page varchar(191) NOT NULL DEFAULT '',
            event_categories varchar(191) NOT NULL DEFAULT '',
            request_method varchar(10) NOT NULL DEFAULT '',
            user_id bigint(20) NOT NULL DEFAULT 0,
            num_queries int(11) NOT NULL DEFAULT 0,
            total_events int(11) NOT NULL DEFAULT 0,
            cache_calls int(11) NOT NULL DEFAULT 0,
            cache_hits int(11) NOT NULL DEFAULT 0,
            cache_misses int(11) NOT NULL DEFAULT 0,
            slow_query_count int(11) NOT NULL DEFAULT 0,
            duplicate_query_count int(11) NOT NULL DEFAULT 0,
            peak_memory_bytes bigint(20) NOT NULL DEFAULT 0,
            elapsed_ms float NOT NULL DEFAULT 0,
            payload longtext DEFAULT NULL,
            inserted_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY page (page),
            KEY request_method (request_method),
            KEY user_id (user_id),
            KEY slow_query_count (slow_query_count),
            KEY duplicate_query_count (duplicate_query_count),
            KEY cache_hits (cache_hits),
            KEY cache_misses (cache_misses),
            KEY inserted_at (inserted_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_ai_assistance (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            form_context varchar(100) NOT NULL,
            field_key varchar(100) NOT NULL,
            request_object longtext NOT NULL,
            prompt text NOT NULL,
            response longtext NOT NULL,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY form_context_field (form_context, field_key),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_bulk_batch_jobs (
            job_id varchar(36) NOT NULL,
            job_type varchar(100) NOT NULL,
            items_json longtext NOT NULL,
            options_json longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total int(11) NOT NULL DEFAULT 0,
            processed int(11) NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (job_id),
            KEY job_type (job_type),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_updated (status, updated_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_cache_index (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(512) NOT NULL DEFAULT '',
            key_hash varchar(64) NOT NULL DEFAULT '',
            cache_group varchar(128) NOT NULL DEFAULT 'default',
            driver varchar(64) NOT NULL DEFAULT '',
            tier varchar(32) NOT NULL DEFAULT '',
            operation_id varchar(128) NOT NULL DEFAULT '',
            repository_class varchar(128) NOT NULL DEFAULT '',
            tags text NOT NULL,
            domain varchar(128) NOT NULL DEFAULT '',
            ttl int(11) NOT NULL DEFAULT 0,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            expires_at bigint(20) unsigned NOT NULL DEFAULT 0,
            value_size int(11) NOT NULL DEFAULT 0,
            value_type varchar(32) NOT NULL DEFAULT '',
            last_accessed_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY key_hash_group (key_hash, cache_group),
            KEY cache_group (cache_group),
            KEY expires_at (expires_at),
            KEY driver (driver),
            KEY tier (tier),
            KEY operation_id (operation_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_cache_events (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(64) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            correlation_id varchar(36) NOT NULL DEFAULT '',
            cache_group varchar(128) NOT NULL DEFAULT '',
            key_hash varchar(64) NOT NULL DEFAULT '',
            operation_id varchar(128) NOT NULL DEFAULT '',
            tags text NOT NULL,
            domain varchar(128) NOT NULL DEFAULT '',
            affected_count int(11) NOT NULL DEFAULT 0,
            elapsed_ms float NOT NULL DEFAULT 0,
            message text NOT NULL,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_integration_field_mappings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) DEFAULT NULL,
            integration_id varchar(50) NOT NULL,
            source_key varchar(191) NOT NULL,
            field_key varchar(191) NOT NULL,
            field_label varchar(191) DEFAULT NULL,
            field_type varchar(50) DEFAULT NULL,
            custom_prompt text,
            is_active tinyint(1) DEFAULT 1,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY template_integration_field (template_id, integration_id, field_key),
            KEY template_id (template_id),
            KEY integration_id (integration_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_content_audits (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            niche varchar(255) NOT NULL,
            overall_score int(11) NOT NULL DEFAULT 0,
            freshness_score int(11) NOT NULL DEFAULT 0,
            link_score int(11) NOT NULL DEFAULT 0,
            cannibalization_score int(11) NOT NULL DEFAULT 0,
            gap_score int(11) NOT NULL DEFAULT 0,
            total_posts int(11) NOT NULL DEFAULT 0,
            orphan_count int(11) NOT NULL DEFAULT 0,
            decay_count int(11) NOT NULL DEFAULT 0,
            conflict_count int(11) NOT NULL DEFAULT 0,
            gap_count int(11) NOT NULL DEFAULT 0,
            audit_report longtext NOT NULL,
            created_at bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY niche_idx (niche),
            KEY overall_score_idx (overall_score),
            KEY created_at_idx (created_at)
        ) $charset_collate;";

        return $sql;
    }
}
