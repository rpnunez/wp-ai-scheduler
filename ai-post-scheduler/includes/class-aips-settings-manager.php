<?php
/**
 * AIPS Settings Manager — orchestrates WordPress settings registration, sanitization
 * pipelines, and persistence hooks.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings_Manager {

	/**
	 * @var AIPS_Settings_Registry
	 */
	private $registry;

	/**
	 * @var AIPS_Settings_Form_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Settings_Registry|null      $registry Settings registry.
	 * @param AIPS_Settings_Form_Renderer|null $renderer Settings form renderer.
	 */
	public function __construct( ?AIPS_Settings_Registry $registry = null, ?AIPS_Settings_Form_Renderer $renderer = null ) {
		$this->registry = $registry ?: AIPS_Settings_Registry::instance();
		$this->renderer = $renderer ?: new AIPS_Settings_Form_Renderer();
	}

	/**
	 * Get the settings registry.
	 *
	 * @return AIPS_Settings_Registry
	 */
	public function get_registry() {
		return $this->registry;
	}

	/**
	 * Get the form renderer.
	 *
	 * @return AIPS_Settings_Form_Renderer
	 */
	public function get_renderer() {
		return $this->renderer;
	}

	/**
	 * Register runtime hooks required by settings updates.
	 *
	 * @return void
	 */
	public static function register_runtime_hooks() {
		if ( has_action( 'update_option_aips_enable_cache_system' ) ) {
			return;
		}

		$reset_cache_flag = function() {
			if ( class_exists( 'AIPS_Cache' ) ) {
				AIPS_Cache::reset_system_enabled_flag();
			}
		};

		add_action( 'update_option_aips_enable_cache_system', $reset_cache_flag );
		add_action( 'add_option_aips_enable_cache_system', $reset_cache_flag );

		add_action(
			'update_option_aips_prevent_scheduled_ai_generation',
			array( __CLASS__, 'maybe_queue_batch_resume' ),
			10,
			2
		);

		$sync_prune_cron = function() {
			self::sync_prune_cron_schedule();
		};
		add_action( 'update_option_aips_auto_prune_enabled', $sync_prune_cron );
		add_action( 'add_option_aips_auto_prune_enabled', $sync_prune_cron );
		add_action( 'update_option_aips_telemetry_prune_interval', $sync_prune_cron );
		add_action( 'add_option_aips_telemetry_prune_interval', $sync_prune_cron );

		$sync_digest_cron = function() {
			self::sync_digest_cron_schedule();
		};
		add_action( 'update_option_aips_notif_digest_enabled', $sync_digest_cron );
		add_action( 'add_option_aips_notif_digest_enabled', $sync_digest_cron );
	}

	/**
	 * Synchronize the background database prune cron event with current settings.
	 *
	 * @return void
	 */
	public static function sync_prune_cron_schedule() {
		$hook = 'aips_database_prune_cleanup';
		if ( ! class_exists( 'AIPS_Container' ) ) {
			return;
		}

		$container = AIPS_Container::get_instance();
		if ( ! $container->has( AIPS_Cron_Manager::class ) ) {
			return;
		}

		$manager = $container->make( AIPS_Cron_Manager::class );
		$manager->clear_hook( $hook );

		$enabled = (bool) AIPS_Config::get_instance()->get_option( 'aips_auto_prune_enabled' );
		if ( $enabled ) {
			$interval = sanitize_key( (string) AIPS_Config::get_instance()->get_option( 'aips_telemetry_prune_interval', 'daily' ) );
			if ( ! in_array( $interval, array( 'daily', 'twicedaily', 'weekly' ), true ) ) {
				$interval = 'daily';
			}
			$manager->schedule_recurring( $hook, $interval, AIPS_DateTime::now()->timestamp() );
		}
	}

	/**
	 * Synchronize the background notification digest cron event with current settings.
	 *
	 * @return void
	 */
	public static function sync_digest_cron_schedule() {
		$hook = 'aips_notif_digest_cron';
		if ( ! class_exists( 'AIPS_Container' ) ) {
			return;
		}

		$container = AIPS_Container::get_instance();
		if ( ! $container->has( AIPS_Cron_Manager::class ) ) {
			return;
		}

		$manager = $container->make( AIPS_Cron_Manager::class );
		$manager->clear_hook( $hook );

		$enabled = (bool) AIPS_Config::get_instance()->get_option( 'aips_notif_digest_enabled' );
		if ( $enabled ) {
			$manager->schedule_recurring( $hook, 'fifteen_minutes', AIPS_DateTime::now()->timestamp() + 900 );
		} else {
			if ( class_exists( 'AIPS_Notifications' ) ) {
				$notifications = AIPS_Notifications::instance();
				if ( $notifications->get_digest_queue_count() > 0 ) {
					$notifications->flush_digest_queue();
				}
			}
		}
	}

	/**
	 * Queue a batch-resume sweep when AI generation is re-enabled.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function maybe_queue_batch_resume( $old_value, $new_value ) {
		if ( ! (bool) $old_value || (bool) $new_value ) {
			return;
		}

		if ( ! class_exists( 'AIPS_Container' ) ) {
			return;
		}

		$container = AIPS_Container::get_instance();
		if ( ! $container->has( AIPS_Cron_Manager::class ) ) {
			return;
		}

		$manager = $container->make( AIPS_Cron_Manager::class );
		if ( $manager->next_scheduled( 'aips_resume_terminated_batches' ) !== false ) {
			return;
		}

		$manager->schedule_single( 'aips_resume_terminated_batches', AIPS_DateTime::now()->timestamp() + 60 );
	}

	/**
	 * Register all settings, sections, and fields with WordPress.
	 *
	 * @return void
	 */
	public function register_all() {
		self::register_runtime_hooks();

		$definitions = $this->registry->get_all_definitions();

		// 1. Register with WordPress Settings API (register_setting)
		foreach ( $definitions as $key => $definition ) {
			// Skip virtual action controls or sub-fields that should not create top-level WP options.
			if ( null === $definition->get_type() || $definition->is_action() ) {
				continue;
			}
			if ( ! empty( $definition->get_control_option( 'sub_key' ) ) || '_action_virtual' === $definition->get_control_option( 'parent_key' ) ) {
				continue;
			}

			$args = array(
				'default' => $definition->get_default(),
			);

			$sanitize = $definition->get_sanitize_callback();
			if ( ! empty( $sanitize ) ) {
				$args['sanitize_callback'] = $sanitize;
			} else {
				$args['sanitize_callback'] = array( $definition, 'sanitize' );
			}

			register_setting( 'aips_settings', $key, $args );
		}

		// 2. Register sections (add_settings_section)
		$sections = $this->registry->get_sections();
		foreach ( $sections as $section_id => $section ) {
			add_settings_section(
				$section_id,
				$section['title'],
				! empty( $section['callback'] ) ? $section['callback'] : '__return_empty_string',
				'aips-settings'
			);
		}

		// 3. Register fields (add_settings_field)
		foreach ( $definitions as $key => $definition ) {
			$section = $definition->get_section();
			if ( empty( $section ) ) {
				continue; // Non-UI or unassigned setting.
			}
			if ( ! empty( $definition->get_control_option( 'visual_parent' ) ) ) {
				continue; // Visually rendered inside its composite parent.
			}

			add_settings_field(
				$key,
				$definition->get_label(),
				array( $this, 'render_field_callback' ),
				'aips-settings',
				$section,
				array(
					'key'       => $key,
					'label_for' => $key,
				)
			);
		}
	}

	/**
	 * Dispatcher callback for WordPress Settings API field rendering.
	 *
	 * @param array $args Field arguments containing setting key.
	 * @return void
	 */
	public function render_field_callback( array $args ) {
		if ( empty( $args['key'] ) ) {
			return;
		}

		$key        = $args['key'];
		$definition = $this->registry->get_definition( $key );

		if ( ! $definition ) {
			return;
		}

		$parent_key = $definition->get_control_option( 'parent_key' );
		if ( ! empty( $parent_key ) && '_action_virtual' !== $parent_key ) {
			$sub_key    = $definition->get_control_option( 'sub_key' );
			$parent_val = AIPS_Config::get_instance()->get_option( $parent_key );
			$value      = ( is_array( $parent_val ) && isset( $parent_val[ $sub_key ] ) )
				? $parent_val[ $sub_key ]
				: $definition->get_default();
		} else {
			$value = AIPS_Config::get_instance()->get_option( $key, $definition->get_default() );
		}

		$this->renderer->render_field( $definition, $value );
	}
}
