<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings
 *
 * Handles the registration of admin menu pages, settings, and rendering of admin interfaces
 * for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings {
	/**
	 * @var AIPS_Settings_UI
	 */
	private $ui;

	/**
	 * @var AIPS_Settings_AJAX
	 */
	private $ajax;

	/**
	 * Initialize the settings class.
	 *
	 * Hooks into admin_init, and wp_ajax.
	 */
	public function __construct() {
		$this->ui = new AIPS_Settings_UI();
		$this->ajax = new AIPS_Settings_AJAX();

		add_action('admin_init', array($this, 'register_settings'));
	}

	/**
	 * Register runtime hooks required by settings updates.
	 *
	 * @return void
	 */
	public static function register_runtime_hooks() {
		AIPS_Settings_Manager::register_runtime_hooks();
	}

	/**
	 * Synchronize the background database prune cron event with current settings.
	 *
	 * @return void
	 */
	public static function sync_prune_cron_schedule() {
		AIPS_Settings_Manager::sync_prune_cron_schedule();
	}

	/**
	 * Synchronize the background notification digest cron event with current settings.
	 *
	 * @return void
	 */
	public static function sync_digest_cron_schedule() {
		AIPS_Settings_Manager::sync_digest_cron_schedule();
	}

	/**
	 * Queue a batch-resume sweep when AI generation is re-enabled.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function maybe_queue_batch_resume($old_value, $new_value) {
		AIPS_Settings_Manager::maybe_queue_batch_resume($old_value, $new_value);
	}

	/**
	 * Return the canonical registry of registered setting arguments.
	 *
	 * Powers legacy callers by delegating to AIPS_Settings_Registry.
	 *
	 * @param AIPS_Settings_UI|null $ui Settings UI helper for instance callbacks.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registered_settings_args($ui = null) {
		$registry = AIPS_Settings_Registry::instance();
		$settings = array();

		foreach ($registry->get_all_definitions() as $key => $definition) {
			$settings[$key] = array(
				'sanitize_callback' => $definition->get_sanitize_callback() ?: array($definition, 'sanitize'),
				'default'           => $definition->get_default(),
			);
		}

		return $settings;
	}

	/**
	 * Register the canonical settings schema with WordPress.
	 *
	 * @param AIPS_Settings_UI|null $ui Settings UI helper for instance callbacks.
	 * @return void
	 */
	public static function register_setting_schema($ui = null) {
		AIPS_Settings_Manager::register_runtime_hooks();

		foreach (self::get_registered_settings_args($ui) as $option_name => $args) {
			register_setting('aips_settings', $option_name, $args);
		}
	}

	/**
	 * Register plugin settings, sections, and fields.
	 *
	 * Delegates to AIPS_Settings_Manager.
	 *
	 * @return void
	 */
	public function register_settings() {
		$container = AIPS_Container::get_instance();
		$manager   = $container->has(AIPS_Settings_Manager::class)
			? $container->make(AIPS_Settings_Manager::class)
			: new AIPS_Settings_Manager();

		$manager->register_all();
	}

	/**
	 * Return the canonical registry of site-wide content strategy options.
	 *
	 * This is the single source of truth for every option that belongs to the
	 * "Site Content Strategy" settings group. Adding a new option here
	 * automatically makes it available to AIPS_Site_Context::get() and
	 * AIPS_Prompt_Builder::build_site_context_block() without touching those
	 * classes.
	 *
	 * Each entry has:
	 *   - 'key'               Short key used by AIPS_Site_Context (e.g. 'niche')
	 *   - 'sanitize_callback' Callable used to sanitize the option value on save
	 *   - 'default'           Default value returned when the option is not set
	 *
	 * @return array<string, array{key: string, sanitize_callback: callable, default: mixed}>
	 *     Associative array keyed by the full WordPress option name.
	 */
	public static function get_content_strategy_options() {
		$config_defaults = AIPS_Config::get_instance()->get_default_options();
		return array(
			'aips_site_niche' => array(
				'key'               => 'niche',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $config_defaults['aips_site_niche'],
			),
			'aips_site_target_audience' => array(
				'key'               => 'target_audience',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $config_defaults['aips_site_target_audience'],
			),
			'aips_site_content_goals' => array(
				'key'               => 'content_goals',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $config_defaults['aips_site_content_goals'],
			),
			'aips_default_article_structure_id' => array(
				'key'               => 'default_article_structure_id',
				'sanitize_callback' => 'absint',
				'default'           => $config_defaults['aips_default_article_structure_id'],
			),
			'aips_site_brand_voice' => array(
				'key'               => 'brand_voice',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $config_defaults['aips_site_brand_voice'],
			),
			'aips_site_content_language' => array(
				'key'               => 'content_language',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => $config_defaults['aips_site_content_language'],
			),
			'aips_site_content_guidelines' => array(
				'key'               => 'content_guidelines',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $config_defaults['aips_site_content_guidelines'],
			),
			'aips_site_excluded_topics' => array(
				'key'               => 'excluded_topics',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $config_defaults['aips_site_excluded_topics'],
			),
		);
	}

	/**
	 * Return the canonical AI Assistance setting definitions.
	 *
	 * @return array<string, array{sanitize_callback: callable|string, default: mixed}>
	 */
	public static function get_ai_assistance_options() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		return array(
			'aips_ai_assistance_enabled' => array(
				'sanitize_callback' => 'absint',
				'default'           => $defaults['aips_ai_assistance_enabled'],
			),
			'aips_ai_assistance_contexts' => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_ai_assistance_contexts' ),
				'default'           => $defaults['aips_ai_assistance_contexts'],
			),
			'aips_ai_assistance_response_length' => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_ai_assistance_response_length' ),
				'default'           => $defaults['aips_ai_assistance_response_length'],
			),
			'aips_ai_assistance_guidance' => array(
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => $defaults['aips_ai_assistance_guidance'],
			),
			'aips_ai_assistance_overwrite_mode' => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_ai_assistance_overwrite_mode' ),
				'default'           => $defaults['aips_ai_assistance_overwrite_mode'],
			),
			'aips_ai_assistance_retention_days' => array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_ai_assistance_retention_days' ),
				'default'           => $defaults['aips_ai_assistance_retention_days'],
			),
		);
	}

	public static function get_ai_assistance_contexts() {
		return array( 'authors', 'templates', 'sources', 'content_strategy' );
	}

	public static function sanitize_ai_assistance_contexts( $contexts ) {
		$contexts = is_array( $contexts ) ? $contexts : array();
		return array_values( array_intersect( self::get_ai_assistance_contexts(), array_map( 'sanitize_key', $contexts ) ) );
	}

	public static function sanitize_ai_assistance_response_length( $value ) {
		return in_array( $value, array( 'concise', 'standard', 'detailed' ), true ) ? $value : 'standard';
	}

	public static function sanitize_ai_assistance_overwrite_mode( $value ) {
		return in_array( $value, array( 'replace', 'preview' ), true ) ? $value : 'preview';
	}

	public static function sanitize_ai_assistance_retention_days( $value ) {
		$value = absint( $value );
		return in_array( $value, array( 0, 7, 30, 90, 365 ), true ) ? $value : 30;
	}

	public static function is_ai_assistance_enabled_for( $context ) {
		return (bool) AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_enabled' )
			&& in_array( $context, self::get_enabled_ai_assistance_contexts(), true );
	}

	public static function get_enabled_ai_assistance_contexts() {
		return self::sanitize_ai_assistance_contexts( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_contexts' ) );
	}

	public static function get_ai_assistance_policy() {
		return array(
			'enabled'        => (bool) AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_enabled' ),
			'contexts'       => self::get_enabled_ai_assistance_contexts(),
			'overwriteMode'  => self::sanitize_ai_assistance_overwrite_mode( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_overwrite_mode' ) ),
			'responseLength' => self::sanitize_ai_assistance_response_length( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_response_length' ) ),
		);
	}

	public static function get_ai_assistance_retention_days() {
		return self::sanitize_ai_assistance_retention_days( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_retention_days' ) );
	}
}
