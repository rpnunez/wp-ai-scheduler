<?php
/**
 * AJAX Registry
 *
 * Centralized registry mapping AJAX action names to their controller classes.
 * Provides a single source of truth for all AJAX routing in the plugin.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Ajax_Registry
 *
 * Static registry mapping ~100+ AJAX action names to controller class names.
 * Used by the AJAX router to resolve which controller handles a given action.
 */
class AIPS_Ajax_Registry {

	/**
	 * Map of AJAX action names to controller class names.
	 *
	 * Format: 'action_name' => Controller_Class::class
	 *
	 * @var array<string, string>
	 */
	private static $map = array(
		// Templates Controller
		'aips_save_template'              => 'AIPS_Templates_Controller',
		'aips_delete_template'            => 'AIPS_Templates_Controller',
		'aips_get_template'               => 'AIPS_Templates_Controller',
		'aips_test_template'              => 'AIPS_Templates_Controller',
		'aips_clone_template'             => 'AIPS_Templates_Controller',
		'aips_preview_template_prompts'   => 'AIPS_Templates_Controller',

		// Schedule Controller
		'aips_save_schedule'              => 'AIPS_Schedule_Controller',
		'aips_schedule_run_now'           => 'AIPS_Schedule_Controller',
		'aips_schedule_toggle'            => 'AIPS_Schedule_Controller',
		'aips_schedule_bulk_toggle'       => 'AIPS_Schedule_Controller',
		'aips_schedule_bulk_run_now'      => 'AIPS_Schedule_Controller',
		'aips_schedule_bulk_delete'       => 'AIPS_Schedule_Controller',
		'aips_get_schedule_history'       => 'AIPS_Schedule_Controller',

		// Author Topics Controller
		'aips_approve_topic'              => 'AIPS_Author_Topics_Controller',
		'aips_reject_topic'               => 'AIPS_Author_Topics_Controller',
		'aips_edit_topic'                 => 'AIPS_Author_Topics_Controller',
		'aips_delete_topic'               => 'AIPS_Author_Topics_Controller',
		'aips_generate_post_from_topic'   => 'AIPS_Author_Topics_Controller',
		'aips_get_topic_logs'             => 'AIPS_Author_Topics_Controller',
		'aips_get_topic_feedback'         => 'AIPS_Author_Topics_Controller',
		'aips_bulk_approve_topics'        => 'AIPS_Author_Topics_Controller',
		'aips_bulk_reject_topics'         => 'AIPS_Author_Topics_Controller',
		'aips_bulk_delete_topics'         => 'AIPS_Author_Topics_Controller',
		'aips_bulk_generate_topics'       => 'AIPS_Author_Topics_Controller',
		'aips_bulk_delete_feedback'       => 'AIPS_Author_Topics_Controller',
		'aips_regenerate_post'            => 'AIPS_Post_Review',
		'aips_delete_generated_post'      => 'AIPS_Author_Topics_Controller',
		'aips_get_similar_topics'         => 'AIPS_Author_Topics_Controller',
		'aips_suggest_related_topics'     => 'AIPS_Author_Topics_Controller',
		'aips_compute_topic_embeddings'   => 'AIPS_Author_Topics_Controller',
		'aips_get_generation_queue'       => 'AIPS_Author_Topics_Controller',
		'aips_bulk_generate_from_queue'   => 'AIPS_Author_Topics_Controller',
		'aips_get_bulk_generate_estimate' => 'AIPS_Author_Topics_Controller',

		// Authors Controller
		'aips_save_author'                => 'AIPS_Authors_Controller',
		'aips_delete_author'              => 'AIPS_Authors_Controller',
		'aips_get_author'                 => 'AIPS_Authors_Controller',
		'aips_get_author_topics'          => 'AIPS_Authors_Controller',
		'aips_get_author_posts'           => 'AIPS_Authors_Controller',
		'aips_get_author_feedback'        => 'AIPS_Authors_Controller',
		'aips_generate_topics_now'        => 'AIPS_Authors_Controller',
		'aips_get_topic_posts'            => 'AIPS_Authors_Controller',
		'aips_suggest_authors'            => 'AIPS_Authors_Controller',

		// AI Edit Controller
		'aips_get_post_components'        => 'AIPS_AI_Edit_Controller',
		'aips_regenerate_component'       => 'AIPS_AI_Edit_Controller',
		'aips_regenerate_all_components'  => 'AIPS_AI_Edit_Controller',
		'aips_save_post_components'       => 'AIPS_AI_Edit_Controller',
		'aips_get_component_revisions'    => 'AIPS_AI_Edit_Controller',
		'aips_restore_component_revision' => 'AIPS_AI_Edit_Controller',

		// Generated Posts Controller
		'aips_get_post_session'           => 'AIPS_Generated_Posts_Controller',
		'aips_get_session_json'           => 'AIPS_Generated_Posts_Controller',
		'aips_download_session_json'      => 'AIPS_Generated_Posts_Controller',

		// Calendar Controller
		'aips_get_calendar_events'        => 'AIPS_Calendar_Controller',

		// Structures Controller
		'aips_get_structures'             => 'AIPS_Structures_Controller',
		'aips_get_structure'              => 'AIPS_Structures_Controller',
		'aips_save_structure'             => 'AIPS_Structures_Controller',
		'aips_delete_structure'           => 'AIPS_Structures_Controller',
		'aips_set_structure_default'      => 'AIPS_Structures_Controller',
		'aips_toggle_structure_active'    => 'AIPS_Structures_Controller',

		// Prompt Sections Controller
		'aips_get_prompt_sections'        => 'AIPS_Prompt_Sections_Controller',
		'aips_get_prompt_section'         => 'AIPS_Prompt_Sections_Controller',
		'aips_save_prompt_section'        => 'AIPS_Prompt_Sections_Controller',
		'aips_delete_prompt_section'      => 'AIPS_Prompt_Sections_Controller',
		'aips_toggle_prompt_section_active' => 'AIPS_Prompt_Sections_Controller',

		// Research Controller
		'aips_research_topics'            => 'AIPS_Research_Controller',
		'aips_get_trending_topics'        => 'AIPS_Research_Controller',
		'aips_delete_trending_topic'      => 'AIPS_Research_Controller',
		'aips_delete_trending_topic_bulk' => 'AIPS_Research_Controller',
		'aips_schedule_trending_topics'   => 'AIPS_Research_Controller',
		'aips_generate_trending_topics_bulk' => 'AIPS_Research_Controller',
		'aips_get_trending_topic_posts'   => 'AIPS_Research_Controller',
		'aips_perform_gap_analysis'       => 'AIPS_Research_Controller',
		'aips_generate_topics_from_gap'   => 'AIPS_Research_Controller',

		// History
		'aips_bulk_delete_history'        => 'AIPS_History',
		'aips_clear_history'              => 'AIPS_History',
		'aips_export_history'             => 'AIPS_History',
		'aips_get_history_details'        => 'AIPS_History',
		'aips_get_history_logs'           => 'AIPS_History',
		'aips_reload_history'             => 'AIPS_History',
		'aips_retry_generation'           => 'AIPS_History',

		// Voices
		'aips_save_voice'                 => 'AIPS_Voices',
		'aips_delete_voice'               => 'AIPS_Voices',
		'aips_get_voice'                  => 'AIPS_Voices',
		'aips_search_voices'              => 'AIPS_Voices',

		// Seeder Admin
		'aips_process_seeder'             => 'AIPS_Seeder_Admin',

		// Data Management
		'aips_export_data'                => 'AIPS_Data_Management',
		'aips_import_data'                => 'AIPS_Data_Management',

		// DB Manager
		'aips_repair_db'                  => 'AIPS_DB_Manager',
		'aips_reinstall_db'               => 'AIPS_DB_Manager',
		'aips_wipe_db'                    => 'AIPS_DB_Manager',
		'aips_flush_cron_events'          => 'AIPS_DB_Manager',

		// Post Review
		'aips_get_draft_posts'            => 'AIPS_Post_Review',
		'aips_publish_post'               => 'AIPS_Post_Review',
		'aips_bulk_publish_posts'         => 'AIPS_Post_Review',
		'aips_bulk_delete_draft_posts'    => 'AIPS_Post_Review',
		'aips_bulk_regenerate_posts'      => 'AIPS_Post_Review',
		'aips_get_draft_post_preview'     => 'AIPS_Post_Review',
		'aips_delete_draft_post'          => 'AIPS_Post_Review',

		// Admin Bar
		'aips_mark_notification_read'     => 'AIPS_Admin_Bar',
		'aips_mark_all_notifications_read' => 'AIPS_Admin_Bar',

		// Planner
		'aips_generate_topics'            => 'AIPS_Planner',
		'aips_bulk_schedule'              => 'AIPS_Planner',
		'aips_bulk_generate_now'          => 'AIPS_Planner',

		// Taxonomy Controller
		'aips_get_taxonomy_items'         => 'AIPS_Taxonomy_Controller',
		'aips_generate_taxonomy'          => 'AIPS_Taxonomy_Controller',
		'aips_approve_taxonomy'           => 'AIPS_Taxonomy_Controller',
		'aips_reject_taxonomy'            => 'AIPS_Taxonomy_Controller',
		'aips_delete_taxonomy'            => 'AIPS_Taxonomy_Controller',
		'aips_bulk_approve_taxonomy'      => 'AIPS_Taxonomy_Controller',
		'aips_bulk_reject_taxonomy'       => 'AIPS_Taxonomy_Controller',
		'aips_bulk_delete_taxonomy'       => 'AIPS_Taxonomy_Controller',
		'aips_bulk_create_taxonomy_terms' => 'AIPS_Taxonomy_Controller',
		'aips_create_taxonomy_term'       => 'AIPS_Taxonomy_Controller',
		'aips_search_posts'               => 'AIPS_Taxonomy_Controller',

		// Settings Ajax
		'aips_test_connection'            => 'AIPS_Settings_Ajax',
		'aips_notifications_data_hygiene' => 'AIPS_Settings_Ajax',

		// Sources Controller
		'aips_get_sources'                => 'AIPS_Sources_Controller',
		'aips_save_source'                => 'AIPS_Sources_Controller',
		'aips_delete_source'              => 'AIPS_Sources_Controller',
		'aips_toggle_source_active'       => 'AIPS_Sources_Controller',
		'aips_get_source_groups'          => 'AIPS_Sources_Controller',
		'aips_save_source_group'          => 'AIPS_Sources_Controller',
		'aips_delete_source_group'        => 'AIPS_Sources_Controller',

		// Onboarding Wizard
		'aips_onboarding_save_strategy'   => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_create_author'   => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_create_template' => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_generate_topics' => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_generate_post'   => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_reset'           => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_complete'        => 'AIPS_Onboarding_Wizard',
		'aips_onboarding_skip'            => 'AIPS_Onboarding_Wizard',

		// Dev Tools
		'aips_generate_scaffold'          => 'AIPS_Dev_Tools',
	);

	/**
	 * Get the controller class name for a given AJAX action.
	 *
	 * @param string $action The AJAX action name (e.g., 'aips_save_template').
	 * @return string|null The controller class name, or null if not registered.
	 */
	public static function get_controller_for($action) {
		return isset(self::$map[$action]) ? self::$map[$action] : null;
	}

	/**
	 * Get all registered AJAX action names.
	 *
	 * @return array<string> List of all registered action names.
	 */
	public static function all_actions() {
		return array_keys(self::$map);
	}

	/**
	 * Check if an action is registered in the registry.
	 *
	 * @param string $action The AJAX action name.
	 * @return bool True if registered, false otherwise.
	 */
	public static function has_action($action) {
		return isset(self::$map[$action]);
	}

	/**
	 * Get the total count of registered AJAX actions.
	 *
	 * @return int Total number of registered actions.
	 */
	public static function count() {
		return count(self::$map);
	}
}
