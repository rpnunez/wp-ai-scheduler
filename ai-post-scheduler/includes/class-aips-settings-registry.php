<?php
/**
 * AIPS Settings Registry — declarative single source of truth for all plugin settings,
 * tabs, sections, default values, and validation rules.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.25
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Settings_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var AIPS_Settings_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered setting definitions indexed by option key.
	 *
	 * @var array<string, AIPS_Setting_Definition>
	 */
	private $definitions = array();

	/**
	 * Registered tabs.
	 *
	 * @var array<string, array>
	 */
	private $tabs = array();

	/**
	 * Registered sections.
	 *
	 * @var array<string, array>
	 */
	private $sections = array();

	/**
	 * Flag indicating whether default schema definitions have been loaded.
	 *
	 * @var bool
	 */
	private $bootstrapped = false;

	/**
	 * Get the singleton registry instance.
	 *
	 * @return AIPS_Settings_Registry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Reset instance state (primarily for isolated unit tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Protected constructor.
	 */
	protected function __construct() {
		$this->ensure_bootstrapped();
	}

	/**
	 * Ensure core tabs, sections, and setting definitions are loaded.
	 *
	 * @return void
	 */
	public function ensure_bootstrapped() {
		if ( $this->bootstrapped ) {
			return;
		}

		$this->bootstrapped = true;
		$this->register_default_tabs();
		$this->register_default_sections();
		$this->register_default_definitions();
	}

	/**
	 * Register a setting definition.
	 *
	 * @param AIPS_Setting_Definition $definition Setting definition object.
	 * @return void
	 */
	public function register( AIPS_Setting_Definition $definition ) {
		$this->definitions[ $definition->get_key() ] = $definition;
	}

	/**
	 * Register multiple setting definitions from array of raw arguments or definition objects.
	 *
	 * @param array $definitions List of definitions or arg arrays.
	 * @return void
	 */
	public function register_many( array $definitions ) {
		foreach ( $definitions as $def ) {
			if ( $def instanceof AIPS_Setting_Definition ) {
				$this->register( $def );
			} elseif ( is_array( $def ) && ! empty( $def['key'] ) ) {
				$this->register( new AIPS_Setting_Definition( $def ) );
			}
		}
	}

	/**
	 * Register a tab.
	 *
	 * @param string $key  Tab identifier (e.g. 'settings-general').
	 * @param array  $args Tab arguments (label, icon, description, order).
	 * @return void
	 */
	public function register_tab( $key, array $args ) {
		$key = sanitize_key( $key );
		$this->tabs[ $key ] = array_merge(
			array(
				'key'         => $key,
				'label'       => '',
				'icon'        => 'dashicons-admin-generic',
				'description' => '',
				'order'       => 10,
			),
			$args
		);
	}

	/**
	 * Register a settings section.
	 *
	 * @param string        $id       Section identifier.
	 * @param string        $title    Localized section title.
	 * @param string        $tab      Parent tab key.
	 * @param callable|null $callback Optional section header description callback.
	 * @param int           $order    Display order.
	 * @return void
	 */
	public function register_section( $id, $title, $tab, $callback = null, $order = 10 ) {
		$id  = sanitize_key( $id );
		$tab = sanitize_key( $tab );

		$this->sections[ $id ] = array(
			'id'       => $id,
			'title'    => $title,
			'tab'      => $tab,
			'callback' => $callback,
			'order'    => $order,
		);
	}

	/**
	 * Get all registered tab definitions.
	 *
	 * @return array<string, array>
	 */
	public function get_tabs() {
		$this->ensure_bootstrapped();
		$tabs = $this->tabs;
		uasort( $tabs, function( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 10;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 10;
			return $order_a <=> $order_b;
		} );
		return $tabs;
	}

	/**
	 * Get a specific tab definition.
	 *
	 * @param string $tab Tab key.
	 * @return array|null
	 */
	public function get_tab( $tab ) {
		$this->ensure_bootstrapped();
		$tab = sanitize_key( $tab );
		return isset( $this->tabs[ $tab ] ) ? $this->tabs[ $tab ] : null;
	}

	/**
	 * Get all registered section definitions.
	 *
	 * @return array<string, array>
	 */
	public function get_sections() {
		$this->ensure_bootstrapped();
		return $this->sections;
	}

	/**
	 * Get sections registered for a given tab.
	 *
	 * @param string $tab Tab identifier.
	 * @return array<string, array>
	 */
	public function get_sections_by_tab( $tab ) {
		$this->ensure_bootstrapped();
		$tab      = sanitize_key( $tab );
		$filtered = array();

		foreach ( $this->sections as $id => $section ) {
			if ( $section['tab'] === $tab ) {
				$filtered[ $id ] = $section;
			}
		}

		uasort( $filtered, function( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 10;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 10;
			return $order_a <=> $order_b;
		} );

		return $filtered;
	}

	/**
	 * Retrieve a setting definition by option key.
	 *
	 * @param string $key Option key.
	 * @return AIPS_Setting_Definition|null
	 */
	public function get_definition( $key ) {
		$this->ensure_bootstrapped();
		$key = sanitize_key( $key );
		return isset( $this->definitions[ $key ] ) ? $this->definitions[ $key ] : null;
	}

	/**
	 * Check if a setting definition exists.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	public function has_definition( $key ) {
		$this->ensure_bootstrapped();
		return isset( $this->definitions[ sanitize_key( $key ) ] );
	}

	/**
	 * Get all registered setting definitions.
	 *
	 * @return array<string, AIPS_Setting_Definition>
	 */
	public function get_all_definitions() {
		$this->ensure_bootstrapped();
		return $this->definitions;
	}

	/**
	 * Get setting definitions belonging to a specific section.
	 *
	 * @param string $section Section identifier.
	 * @return array<string, AIPS_Setting_Definition>
	 */
	public function get_definitions_by_section( $section ) {
		$this->ensure_bootstrapped();
		$section  = sanitize_key( $section );
		$filtered = array();

		foreach ( $this->definitions as $key => $definition ) {
			if ( $definition->get_section() === $section ) {
				$filtered[ $key ] = $definition;
			}
		}

		return $filtered;
	}

	/**
	 * Get setting definitions belonging to a specific tab.
	 *
	 * @param string $tab Tab key.
	 * @return array<string, AIPS_Setting_Definition>
	 */
	public function get_definitions_by_tab( $tab ) {
		$this->ensure_bootstrapped();
		$tab      = sanitize_key( $tab );
		$filtered = array();

		foreach ( $this->definitions as $key => $definition ) {
			if ( $definition->get_tab() === $tab ) {
				$filtered[ $key ] = $definition;
			}
		}

		return $filtered;
	}

	/**
	 * Return the canonical associative array of all option default values.
	 *
	 * Powers AIPS_Config::get_default_options().
	 *
	 * @return array<string, mixed>
	 */
	public function get_defaults() {
		$this->ensure_bootstrapped();
		$defaults = array();

		foreach ( $this->definitions as $key => $definition ) {
			if ( ! empty( $definition->get_control_option( 'parent_key' ) ) ) {
				continue;
			}
			$defaults[ $key ] = $definition->get_default();
		}

		return $defaults;
	}

	/**
	 * Register the core default admin settings tabs.
	 *
	 * @return void
	 */
	private function register_default_tabs() {
		$tabs = array(
			'settings-general'          => array(
				'label'       => __( 'General', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-admin-generic',
				'description' => __( 'Defaults & post settings', 'ai-post-scheduler' ),
				'order'       => 10,
			),
			'settings-ai'               => array(
				'label'       => __( 'Engine', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-rest-api',
				'description' => __( 'Models & AI connection', 'ai-post-scheduler' ),
				'order'       => 20,
			),
			'settings-ai-assistance'    => array(
				'label'       => __( 'AI Assistance', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-superhero-alt',
				'description' => __( 'Suggestion behavior & privacy', 'ai-post-scheduler' ),
				'order'       => 30,
			),
			'settings-linking'          => array(
				'label'       => __( 'Internal Linking', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-admin-links',
				'description' => __( 'Link index & auto-linking', 'ai-post-scheduler' ),
				'order'       => 40,
			),
			'settings-feedback'         => array(
				'label'       => __( 'Feedback & Topics', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-thumbs-up',
				'description' => __( 'Deduplication & scoring', 'ai-post-scheduler' ),
				'order'       => 50,
			),
			'settings-notifications'    => array(
				'label'       => __( 'Notifications', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-email-alt',
				'description' => __( 'Email & alert channels', 'ai-post-scheduler' ),
				'order'       => 60,
			),
			'settings-resilience'       => array(
				'label'       => __( 'Resilience & Limits', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-shield',
				'description' => __( 'Failover & circuit breaker', 'ai-post-scheduler' ),
				'order'       => 70,
			),
			'settings-content-strategy' => array(
				'label'       => __( 'Content Strategy', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-art',
				'description' => __( 'Brand voice & persona', 'ai-post-scheduler' ),
				'order'       => 80,
			),
			'settings-cache'            => array(
				'label'       => __( 'Performance', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-performance',
				'description' => __( 'Caching layer & driver', 'ai-post-scheduler' ),
				'order'       => 90,
			),
			'settings-api-keys'         => array(
				'label'       => __( 'API Keys', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-admin-network',
				'description' => __( 'Third-party credentials', 'ai-post-scheduler' ),
				'order'       => 100,
			),
			'settings-developers'       => array(
				'label'       => __( 'Developers', 'ai-post-scheduler' ),
				'icon'        => 'dashicons-editor-code',
				'description' => __( 'Debug & dev tools', 'ai-post-scheduler' ),
				'order'       => 110,
			),
		);

		foreach ( $tabs as $key => $args ) {
			$this->register_tab( $key, $args );
		}
	}

	/**
	 * Register the core default admin settings sections.
	 *
	 * @return void
	 */
	private function register_default_sections() {
		$sections = array(
			// General Tab
			array( 'aips_general_section', __( 'General Defaults', 'ai-post-scheduler' ), 'settings-general', 10 ),
			array( 'aips_post_images_section', __( 'Post Content Images', 'ai-post-scheduler' ), 'settings-general', 20 ),

			// AI Engine Tab
			array( 'aips_ai_provider_section', __( 'Content Generation AI Provider', 'ai-post-scheduler' ), 'settings-ai', 10 ),
			array( 'aips_ai_tokens_section', __( 'Token Budgets & Prompt Optimization', 'ai-post-scheduler' ), 'settings-ai', 20 ),
			array( 'aips_ai_embeddings_section', __( 'Vector Embeddings Engine & Model', 'ai-post-scheduler' ), 'settings-ai', 30 ),
			array( 'aips_ai_scope_section', __( 'Indexing Scope, Continuous Sync & Rate Limits', 'ai-post-scheduler' ), 'settings-ai', 40 ),
			array( 'aips_ai_related_posts_section', __( 'Frontend Related Posts Engine', 'ai-post-scheduler' ), 'settings-ai', 50 ),
			array( 'aips_ai_deduplication_section', __( 'Semantic Duplicate Detection & Gatekeeper Guard', 'ai-post-scheduler' ), 'settings-ai', 60 ),

			// AI Assistance Tab
			array( 'aips_ai_assistance_section', __( 'AI Assistance', 'ai-post-scheduler' ), 'settings-ai-assistance', 10 ),

			// Internal Linking Tab
			array( 'aips_link_index_section', __( 'Internal Link Index & Graph', 'ai-post-scheduler' ), 'settings-linking', 10 ),
			array( 'aips_link_rules_section', __( 'Manual Link Rules & Anchor Optimization', 'ai-post-scheduler' ), 'settings-linking', 20 ),
			array( 'aips_link_clicks_section', __( 'Link Click Tracking', 'ai-post-scheduler' ), 'settings-linking', 25 ),
			array( 'aips_silo_section', __( 'Topic Silos & Silo Guide Insertion', 'ai-post-scheduler' ), 'settings-linking', 30 ),
			array( 'aips_ai_autolink_section', __( 'Contextual Internal Auto-Linking Engine', 'ai-post-scheduler' ), 'settings-linking', 40 ),

			// Feedback & Topics Tab
			array( 'aips_authors_section', __( 'Author Persona Management & Topic Approval', 'ai-post-scheduler' ), 'settings-feedback', 10 ),
			array( 'aips_feedback_section', __( 'Feedback & Scoring Configuration', 'ai-post-scheduler' ), 'settings-feedback', 20 ),

			// Notifications Tab
			array( 'aips_notifications_delivery_section', __( 'Email Routing & Delivery', 'ai-post-scheduler' ), 'settings-notifications', 10 ),
			array( 'aips_notifications_system_section', __( 'System Health & Resilience Alerts', 'ai-post-scheduler' ), 'settings-notifications', 20 ),
			array( 'aips_notifications_content_section', __( 'Content & Editorial Workflow Alerts', 'ai-post-scheduler' ), 'settings-notifications', 30 ),
			array( 'aips_notifications_automation_section', __( 'Automation & Operational Maintenance', 'ai-post-scheduler' ), 'settings-notifications', 40 ),
			array( 'aips_notifications_summarized_digest_section', __( 'Summarized Notification Digest', 'ai-post-scheduler' ), 'settings-notifications', 50 ),
			array( 'aips_notifications_digests_section', __( 'Scheduled Activity Digests & Reports', 'ai-post-scheduler' ), 'settings-notifications', 60 ),

			// Resilience & Limits Tab
			array( 'aips_resilience_retry_section', __( 'Retry & Backoff Policy', 'ai-post-scheduler' ), 'settings-resilience', 10 ),
			array( 'aips_resilience_rate_limit_section', __( 'API Rate Limiting', 'ai-post-scheduler' ), 'settings-resilience', 20 ),
			array( 'aips_resilience_batch_section', __( 'Batch Pacing & Resume Cooldown', 'ai-post-scheduler' ), 'settings-resilience', 30 ),
			array( 'aips_resilience_circuit_breaker_section', __( 'Circuit Breaker Protection', 'ai-post-scheduler' ), 'settings-resilience', 40 ),

			// Content Strategy Tab
			array( 'aips_content_strategy_section', __( 'Site Content Strategy', 'ai-post-scheduler' ), 'settings-content-strategy', 10 ),

			// Performance & Cache Tab
			array( 'aips_cache_section', __( 'Cache Framework & Storage Driver', 'ai-post-scheduler' ), 'settings-cache', 10 ),
			array( 'aips_cache_monitor_section', __( 'Cache Performance Monitoring', 'ai-post-scheduler' ), 'settings-cache', 20 ),
			array( 'aips_database_retention_section', __( 'Database & Data Retention', 'ai-post-scheduler' ), 'settings-cache', 30 ),

			// API Keys Tab
			array( 'aips_api_keys_section', __( 'API Keys', 'ai-post-scheduler' ), 'settings-api-keys', 10 ),

			// Developers Tab
			array( 'aips_developers_section', __( 'Developer Settings', 'ai-post-scheduler' ), 'settings-developers', 10 ),
		);

		foreach ( $sections as $s ) {
			$this->register_section( $s[0], $s[1], $s[2], null, $s[3] );
		}
	}

	/**
	 * Register all core default setting definitions.
	 *
	 * Covers all options from AIPS_Config::get_default_options().
	 *
	 * @return void
	 */
	private function register_default_definitions() {
		$definitions = array(
			// -----------------------------------------------------------------
			// Non-UI / System State
			// -----------------------------------------------------------------
			array(
				'key'     => 'generated_posts_log_threshold_tmpfile',
				'type'    => AIPS_Setting_Definition::TYPE_INT,
				'default' => 200,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'     => 'generated_posts_log_threshold_client',
				'type'    => AIPS_Setting_Definition::TYPE_INT,
				'default' => 20,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'     => 'history_export_max_records',
				'type'    => AIPS_Setting_Definition::TYPE_INT,
				'default' => 10000,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'     => 'aips_db_version',
				'type'    => AIPS_Setting_Definition::TYPE_STRING,
				'default' => '0',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'     => 'aips_onboarding_completed',
				'type'    => AIPS_Setting_Definition::TYPE_BOOL,
				'default' => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			array(
				'key'     => 'aips_log_secret',
				'type'    => AIPS_Setting_Definition::TYPE_STRING,
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'     => 'aips_notif_daily_digest_last_sent',
				'type'    => AIPS_Setting_Definition::TYPE_STRING,
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'     => 'aips_notif_weekly_summary_last_sent',
				'type'    => AIPS_Setting_Definition::TYPE_STRING,
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'     => 'aips_notif_monthly_report_last_sent',
				'type'    => AIPS_Setting_Definition::TYPE_STRING,
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'     => 'aips_post_clusters',
				'type'    => AIPS_Setting_Definition::TYPE_ARRAY,
				'default' => array(),
			),
			array(
				'key'     => 'aips_duplicate_dismissed',
				'type'    => AIPS_Setting_Definition::TYPE_ARRAY,
				'default' => array(),
			),

			// -----------------------------------------------------------------
			// Tab: General Settings ('settings-general')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_default_post_status',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'draft',
				'tab'          => 'settings-general',
				'section'      => 'aips_general_section',
				'label'        => __( 'Default Post Status', 'ai-post-scheduler' ),
				'description'  => __( 'Default status for newly generated posts.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'options' => array(
						'draft'   => __( 'Draft', 'ai-post-scheduler' ),
						'pending' => __( 'Pending Review', 'ai-post-scheduler' ),
						'publish' => __( 'Published', 'ai-post-scheduler' ),
					),
				),
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_default_category',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 0,
				'tab'          => 'settings-general',
				'section'      => 'aips_general_section',
				'label'        => __( 'Default Category', 'ai-post-scheduler' ),
				'description'  => __( 'Default category for generated posts.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'render_callback' => function( AIPS_Setting_Definition $def, $value ) {
						if ( ! function_exists( 'wp_dropdown_categories' ) ) {
							return '';
						}
						return wp_dropdown_categories( array(
							'echo'             => 0,
							'name'             => $def->get_key(),
							'id'               => $def->get_key(),
							'selected'         => (int) $value,
							'show_option_none' => __( 'Select Category', 'ai-post-scheduler' ),
							'hide_empty'       => 0,
						) );
					},
				),
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_default_post_author',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 1,
				'tab'          => 'settings-general',
				'section'      => 'aips_general_section',
				'label'        => __( 'Default Author', 'ai-post-scheduler' ),
				'description'  => __( 'WordPress user assigned to generated posts when no persona is matched.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'render_callback' => function( AIPS_Setting_Definition $def, $value ) {
						if ( ! function_exists( 'wp_dropdown_users' ) ) {
							return '';
						}
						return wp_dropdown_users( array(
							'echo'     => 0,
							'name'     => $def->get_key(),
							'id'       => $def->get_key(),
							'selected' => (int) $value,
						) );
					},
				),
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_bulk_publish_async_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-general',
				'section'      => 'aips_general_section',
				'label'        => __( 'Bulk Publish Background Threshold', 'ai-post-scheduler' ),
				'description'  => __( 'On the Pending Review screen, publishing this many drafts or more runs in the background via WP-Cron.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'control_options' => array( 'min' => 2, 'max' => 500, 'step' => 1 ),
				'sanitize_callback' => static function( $v ) {
					return min( 500, max( 2, absint( $v ) ) );
				},
			),
			array(
				'key'          => 'aips_max_post_images_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-general',
				'section'      => 'aips_post_images_section',
				'label'        => __( 'Maximum Post Images', 'ai-post-scheduler' ),
				'description'  => __( 'Maximum number of images inserted into generated content.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'control_options' => array( 'min' => 0, 'max' => 20, 'step' => 1 ),
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_allowed_image_sources',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array( 'ai_prompt', 'unsplash', 'media_library' ),
				'tab'          => 'settings-general',
				'section'      => 'aips_post_images_section',
				'label'        => __( 'Allowed Image Sources', 'ai-post-scheduler' ),
				'description'  => __( 'Enabled sources for content image insertion.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_enable_post_insights_ui',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-general',
				'section'      => 'aips_general_section',
				'label'        => __( 'Enable Post Insights UI', 'ai-post-scheduler' ),
				'description'  => __( 'Display SEO and content metrics in the WordPress post editor sidebar.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: Engine ('settings-ai')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_ai_provider',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'AI Provider', 'ai-post-scheduler' ),
				'description'  => __( 'Select the primary AI generation engine. Empty means auto-detect.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'options' => function() {
						$choices = array( '' => __( 'Auto-detect (recommended)', 'ai-post-scheduler' ) );
						if ( class_exists( 'AIPS_AI_Provider_Factory' ) ) {
							$all       = AIPS_AI_Provider_Factory::all_providers();
							$available = AIPS_AI_Provider_Factory::available_providers();
							foreach ( $all as $id => $label ) {
								$suffix = isset( $available[ $id ] ) ? '' : ' ' . __( '(currently unavailable)', 'ai-post-scheduler' );
								$choices[ $id ] = $label . $suffix;
							}
						}
						return $choices;
					},
				),
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_ai_model',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'AI Model', 'ai-post-scheduler' ),
				'description'  => __( 'Model identifier used for content generation.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_ai_env_id',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'AI Environment ID', 'ai-post-scheduler' ),
				'description'  => __( 'Environment identifier (e.g. Meow AI Engine environment).', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_wp_ai_connector_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'all',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'WP AI Connector Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_wp_ai_connector_ids',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array(),
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'WP AI Connector IDs', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_wp_ai_connector_failover',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'Enable Failover', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_prevent_scheduled_ai_generation',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_provider_section',
				'label'        => __( 'Pause Scheduled AI Generation', 'ai-post-scheduler' ),
				'description'  => __( 'Temporarily pauses all background generation schedules without altering configuration.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_max_tokens_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 16000,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Maximum Token Budget', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_max_tokens_title',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 150,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Title Token Budget', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_max_tokens_excerpt',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 300,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Excerpt Token Budget', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_max_tokens_content',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 4000,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Content Token Budget', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_temperature',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.7,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Generation Temperature', 'ai-post-scheduler' ),
				'description'  => __( 'Controls generation randomness (0.0 = deterministic, 1.0 = creative).', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'control_options' => array( 'min' => 0.0, 'max' => 1.0, 'step' => 0.05 ),
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_conversational_generation',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Conversational Multi-Turn Generation', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_conversational_metadata_turn',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_tokens_section',
				'label'        => __( 'Conversational Metadata Turn', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),

			// Embeddings & Indexer
			array(
				'key'          => 'aips_embeddings_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Enable Vector Embeddings', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_persistent_cache_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Persistent Embeddings Cache', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_provider',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Embeddings Provider', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_embeddings_model',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'text-embedding-3-small',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Embeddings Model', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_embeddings_env_id',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Embeddings Environment ID', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_embeddings_dimensions',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 1536,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_embeddings_section',
				'label'        => __( 'Vector Dimensions', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_scope',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'aips_only',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Indexing Scope', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_embeddings_date_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Embeddings Date Window (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_date_after',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Embeddings Date After', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_embeddings_rate_limits_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Embeddings Rate Limiting', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_daily_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 50,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Daily Embedding Limit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_weekly_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 200,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Weekly Embedding Limit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_embeddings_monthly_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 500,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Monthly Embedding Limit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_verbose_history',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Verbose Indexing History', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_post_types',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array( 'post' ),
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Indexed Post Types', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_indexer_similarity_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.65,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Indexing Similarity Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'control_options' => array( 'min' => 0.1, 'max' => 1.0, 'step' => 0.05 ),
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_auto_index_on_publish',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Auto Index on Publish', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_publish_execution_timing',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'queued',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Publish Execution Timing', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_indexer_batch_size',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 10,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Indexing Batch Size', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_queue_debounce_seconds',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 15,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Queue Debounce Seconds', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_quota_pause_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Pause on Quota Exhaustion', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_queue_notifications_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Queue Notifications Enabled', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_post_cluster_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.65,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Post Cluster Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_indexer_error_pause_duration',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Error Pause Duration', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_error_pause_unit',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'minutes',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Error Pause Unit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_indexer_consecutive_error_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 2,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Consecutive Error Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_topics_continuous_sync',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Continuous Topic Sync', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_indexer_topics_execution_timing',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'immediate',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Topics Execution Timing', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_indexer_scan_entity_scope',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'all',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_scope_section',
				'label'        => __( 'Scan Entity Scope', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),

			// Related Posts Engine
			array(
				'key'          => 'aips_related_posts_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Enable Related Posts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_related_posts_auto_append',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Auto Append to Content', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_related_posts_count',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 4,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Related Posts Count', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_related_posts_heading',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'Related Articles',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Related Posts Heading', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_related_posts_layout',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'grid',
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Related Posts Layout', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_related_posts_show_thumbnails',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Show Thumbnails', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_related_posts_show_excerpts',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai',
				'section'      => 'aips_ai_related_posts_section',
				'label'        => __( 'Show Excerpts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: AI Assistance ('settings-ai-assistance')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_ai_assistance_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Enable AI Assistance', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_ai_assistance_contexts',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array( 'authors', 'templates', 'sources', 'content_strategy' ),
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Assistance Contexts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_ai_assistance_response_length',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'standard',
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Response Length', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_ai_assistance_guidance',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Assistance Guidance', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			array(
				'key'          => 'aips_ai_assistance_overwrite_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'preview',
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Overwrite Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_ai_assistance_retention_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-ai-assistance',
				'section'      => 'aips_ai_assistance_section',
				'label'        => __( 'Assistance Retention (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: Internal Linking ('settings-linking')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_link_index_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_index_section',
				'label'        => __( 'Enable Link Index', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_index_post_types',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array( 'post', 'page' ),
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_index_section',
				'label'        => __( 'Indexed Post Types', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_link_index_batch_size',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 50,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_index_section',
				'label'        => __( 'Index Batch Size', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_index_batch_delay',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 20,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_index_section',
				'label'        => __( 'Batch Delay (Seconds)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_rules_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Enable Link Rules', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_rules_max_per_post',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Max Rules Per Post', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_redirect_provider',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'auto',
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Redirect Provider', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_publish_linking_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'review',
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Publish Linking Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_publish_linking_outbound',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Outbound Linking on Publish', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_click_tracking_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_clicks_section',
				'label'        => __( 'Track Link Clicks', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_link_click_retention_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 365,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_clicks_section',
				'label'        => __( 'Click Data Retention (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_source_archive_retention_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 0,
				'tab'          => 'settings-linking',
				'section'      => 'aips_link_rules_section',
				'label'        => __( 'Source Archive Retention (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_source_fetch_max_response_bytes',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 2097152,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_source_prompt_total_max_chars',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 16000,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_silo_guide_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_silo_section',
				'label'        => __( 'Enable Silo Guides', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_silo_guide_position',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'end',
				'tab'          => 'settings-linking',
				'section'      => 'aips_silo_section',
				'label'        => __( 'Silo Guide Position', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_silo_guide_max',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 10,
				'tab'          => 'settings-linking',
				'section'      => 'aips_silo_section',
				'label'        => __( 'Max Silo Articles', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_silo_guide_style',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'aips',
				'tab'          => 'settings-linking',
				'section'      => 'aips_silo_section',
				'label'        => __( 'Silo Guide Style', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_silo_guide_heading',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-linking',
				'section'      => 'aips_silo_section',
				'label'        => __( 'Silo Guide Heading', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_autolink_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Enable Auto-Linking', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_autolink_auto_apply_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.85,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Auto Apply Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_autolink_review_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.70,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Review Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_autolink_max_links_per_post',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Max Auto-Links Per Post', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_autolink_max_total_internal_per_post',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 15,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Max Total Internal Links', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_autolink_max_inbound_per_target',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Max Inbound Per Target', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_autolink_skip_first_paragraph',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Skip First Paragraph', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_autolink_rel',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Link Rel Attribute', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_autolink_target_blank',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-linking',
				'section'      => 'aips_ai_autolink_section',
				'label'        => __( 'Open in New Tab', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_gsc_service_account',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-linking',
				'section'      => 'aips_gsc_section',
				'label'        => __( 'GSC Service Account JSON', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
			),
			array(
				'key'          => 'aips_gsc_property',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-linking',
				'section'      => 'aips_gsc_section',
				'label'        => __( 'GSC Property URL', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_gsc_anchor_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-linking',
				'section'      => 'aips_gsc_section',
				'label'        => __( 'Optimize Anchors via GSC', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: Feedback & Topics ('settings-feedback')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_deduplication_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'warn',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Deduplication Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_deduplication_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.85,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Duplicate Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_cluster_detection_max_posts',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 250,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Max Posts for Cluster Detection', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cluster_detection_time_budget',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 8,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Cluster Detection Time Budget (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_duplicate_review_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.88,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Duplicate Review Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_duplicate_review_max_group_size',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Max Group Size', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_duplicate_review_max_pairs',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 1000,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Max Evaluated Pairs', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_duplicate_keep_strategy',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'balanced',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Canonical Keep Strategy', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_duplicate_excluded_post_types',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array(),
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Excluded Post Types', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_duplicate_excluded_categories',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array(),
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Excluded Categories', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_duplicate_excluded_post_ids',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Excluded Post IDs', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_duplicate_exclude_pillars',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Exclude Pillar Articles', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_duplicate_protect_inbound_links',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 10,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Protect Inbound Links Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_duplicate_default_merge_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'revision',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Default Merge Action', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_duplicate_scan_schedule',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'off',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Automated Scan Frequency', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_cluster_default_min_size',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Minimum Cluster Size', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cluster_tightness',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'balanced',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Cluster Tightness', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_pillar_spoke_similarity',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.75,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Pillar-Spoke Minimum Similarity', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_audit_result_limit',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 50,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Audit Result Limit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_generation_inject_related_context',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_ai_deduplication_section',
				'label'        => __( 'Inject Related Context into Prompts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_author_topic_auto_approval_mode',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'manual',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_authors_section',
				'label'        => __( 'Topic Auto-Approval Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_author_topic_auto_approval_min_score',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 70,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_authors_section',
				'label'        => __( 'Minimum Topic Quality Score', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_author_topic_auto_approval_min_relevance',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 65,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_authors_section',
				'label'        => __( 'Minimum Author Relevance', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_author_topic_auto_approval_max_similarity',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.85,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_authors_section',
				'label'        => __( 'Max Duplicate Similarity', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),
			array(
				'key'          => 'aips_author_topic_auto_approval_fallback',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'smart_split',
				'tab'          => 'settings-feedback',
				'section'      => 'aips_authors_section',
				'label'        => __( 'Approval Fallback Action', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_topic_similarity_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_FLOAT,
				'default'      => 0.85,
				'tab'          => 'settings-feedback',
				'section'      => 'aips_feedback_section',
				'label'        => __( 'Topic Similarity Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SLIDER,
				'sanitize_callback' => 'floatval',
			),

			// -----------------------------------------------------------------
			// Tab: Notifications ('settings-notifications')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_review_notifications_email',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_delivery_section',
				'label'        => __( 'Notification Email Addresses', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_notification_preferences',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array(
					'generation_failed'            => 'both',
					'quota_alert'                  => 'both',
					'integration_error'            => 'both',
					'scheduler_error'              => 'both',
					'system_error'                 => 'both',
					'template_generated'           => 'db',
					'manual_generation_completed'  => 'db',
					'post_ready_for_review'        => 'db',
					'post_rejected'                => 'db',
					'post_consolidated'            => 'both',
					'duplicate_groups_found'       => 'db',
					'partial_generation_completed' => 'db',
					'post_generated'               => 'both',
				),
			),

			// System Health & Resilience Alerts
			array(
				'key'          => 'aips_notification_preferences_generation_failed',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'Generation Failure Alerts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[generation_failed]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'generation_failed',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_quota_alert',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'Quota & Rate Exceeded', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[quota_alert]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'quota_alert',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_circuit_breaker_opened',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'Circuit Breaker Triggered', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[circuit_breaker_opened]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'circuit_breaker_opened',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_rate_limit_reached',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'API Rate Limit Encountered', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[rate_limit_reached]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'rate_limit_reached',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_integration_error',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'AI Provider Integration Errors', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[integration_error]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'integration_error',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_scheduler_error',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'Scheduler Execution Errors', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[scheduler_error]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'scheduler_error',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_system_error',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_system_section',
				'label'        => __( 'General System & Fatal Errors', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[system_error]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'system_error',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),

			// Content & Editorial Workflow Alerts
			array(
				'key'          => 'aips_notification_preferences_post_ready_for_review',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Post Ready for Review', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[post_ready_for_review]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'post_ready_for_review',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_post_generated',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Post Published / Generated', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[post_generated]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'post_generated',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_partial_generation_completed',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Partial Generation Completed', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[partial_generation_completed]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'partial_generation_completed',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_post_rejected',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Editorial Post Rejection', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[post_rejected]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'post_rejected',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_post_consolidated',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'both',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Topic Deduplication / Consolidation', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[post_consolidated]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'post_consolidated',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_duplicate_groups_found',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Duplicate Clusters Detected', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[duplicate_groups_found]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'duplicate_groups_found',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_author_topics_generated',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Author Persona Topics Generated', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[author_topics_generated]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'author_topics_generated',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_author_suggestions',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Author Persona Topic Suggestions', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[author_suggestions]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'author_suggestions',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_research_topics_ready',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_content_section',
				'label'        => __( 'Research Topics Ready', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[research_topics_ready]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'research_topics_ready',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),

			// Automation & Operational Maintenance
			array(
				'key'          => 'aips_notification_preferences_template_generated',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_automation_section',
				'label'        => __( 'Template Generation Finished', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[template_generated]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'template_generated',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_manual_generation_completed',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_automation_section',
				'label'        => __( 'Manual Generation Batch Completed', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[manual_generation_completed]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'manual_generation_completed',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_template_change',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_automation_section',
				'label'        => __( 'Template Configuration Changed', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[template_change]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'template_change',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_seeder_complete',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_automation_section',
				'label'        => __( 'Topic Seeder Run Finished', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[seeder_complete]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'seeder_complete',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_history_cleanup',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'db',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_automation_section',
				'label'        => __( 'Database History Pruned', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[history_cleanup]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'history_cleanup',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),

			// Scheduled Activity Digests & Reports
			array(
				'key'          => 'aips_notification_preferences_daily_digest',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'email',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_digests_section',
				'label'        => __( 'Daily Activity Digest', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[daily_digest]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'daily_digest',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_weekly_summary',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'email',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_digests_section',
				'label'        => __( 'Weekly Publishing Summary', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[weekly_summary]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'weekly_summary',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notification_preferences_monthly_report',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'email',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_digests_section',
				'label'        => __( 'Monthly Operations Report', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'control_options' => array(
					'name'       => 'aips_notification_preferences[monthly_report]',
					'parent_key' => 'aips_notification_preferences',
					'sub_key'    => 'monthly_report',
					'options'    => function() {
						return class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::get_channel_mode_options() : array( 'both' => 'Email & Admin Bar', 'email' => 'Email Only', 'db' => 'Admin Bar Only', 'none' => 'Disabled' );
					},
				),
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_notif_digest_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_summarized_digest_section',
				'label'        => __( 'Enable Summarized Notification Digest', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_notif_digest_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_summarized_digest_section',
				'label'        => __( 'Digest Event Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_notif_digest_max_delay',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3600,
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_summarized_digest_section',
				'label'        => __( 'Max Digest Delay (Seconds)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_notif_digest_types',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array( 'post_generated', 'post_ready_for_review' ),
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_summarized_digest_section',
				'label'        => __( 'Digest Event Types', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),
			array(
				'key'          => 'aips_notif_digest_actions',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-notifications',
				'section'      => 'aips_notifications_summarized_digest_section',
				'label'        => __( 'Queue Actions', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_ACTION,
				'control_options' => array(
					'id'           => 'aips-flush-digest-btn',
					'button_label' => __( 'Send Pending Digest Now', 'ai-post-scheduler' ),
					'parent_key'   => '_action_virtual',
					'extra_html'   => function() {
						$queue_count = class_exists( 'AIPS_Notifications' ) ? AIPS_Notifications::instance()->get_digest_queue_count() : 0;
						return sprintf(
							' <span class="description">' .
							/* translators: %s: span element containing pending count */
							esc_html__( 'Currently %s pending notifications in the digest queue.', 'ai-post-scheduler' ) .
							'</span>',
							'<strong id="aips-digest-queue-count">' . esc_html( $queue_count ) . '</strong>'
						);
					},
				),
			),

			// -----------------------------------------------------------------
			// Tab: Resilience & Limits ('settings-resilience')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_enable_retry',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_retry_section',
				'label'        => __( 'Enable Exponential Retry', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_retry_max_attempts',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_retry_section',
				'label'        => __( 'Maximum Retry Attempts', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_retry_initial_delay',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 1,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_retry_section',
				'label'        => __( 'Initial Retry Delay (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_enable_rate_limiting',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_rate_limit_section',
				'label'        => __( 'Enable API Rate Limiting', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_rate_limit_requests',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 10,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_rate_limit_section',
				'label'        => __( 'Max Requests per Window', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_rate_limit_period',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 60,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_rate_limit_section',
				'label'        => __( 'Rate Limit Window (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_generation_delay_seconds',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 2,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_batch_section',
				'label'        => __( 'Generation Delay (s)', 'ai-post-scheduler' ),
				'description'  => __( 'Delay between consecutive generation requests to smooth load on AI providers.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'control_options' => array( 'min' => 0, 'max' => 30, 'step' => 1 ),
				'sanitize_callback' => static function( $v ) {
					return min( 30, absint( $v ) );
				},
			),
			array(
				'key'          => 'aips_batch_resume_cooldown_minutes',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_batch_section',
				'label'        => __( 'Batch Resume Cooldown (Minutes)', 'ai-post-scheduler' ),
				'description'  => __( 'Cooldown period before resumed batches execute to avoid thrashing.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'control_options' => array( 'min' => 1, 'max' => 1440, 'step' => 1 ),
				'sanitize_callback' => static function( $v ) {
					return min( 1440, max( 1, absint( $v ) ) );
				},
			),
			array(
				'key'          => 'aips_enable_circuit_breaker',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_circuit_breaker_section',
				'label'        => __( 'Enable Circuit Breaker', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_circuit_breaker_threshold',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 5,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_circuit_breaker_section',
				'label'        => __( 'Consecutive Failure Threshold', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_circuit_breaker_timeout',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 300,
				'tab'          => 'settings-resilience',
				'section'      => 'aips_resilience_circuit_breaker_section',
				'label'        => __( 'Circuit Open Timeout (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: Content Strategy ('settings-content-strategy')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_site_niche',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Site Niche & Industry', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_site_target_audience',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Target Audience', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_site_content_goals',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Content Objectives & Goals', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			array(
				'key'          => 'aips_default_article_structure_id',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 0,
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Default Article Structure', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_site_brand_voice',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Brand Voice & Persona', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			array(
				'key'          => 'aips_site_content_language',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'en',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Content Language', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_site_content_guidelines',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Editorial Guidelines', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			array(
				'key'          => 'aips_site_excluded_topics',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Excluded Topics & Negative Keywords', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXTAREA,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			array(
				'key'          => 'aips_research_niches',
				'type'         => AIPS_Setting_Definition::TYPE_ARRAY,
				'default'      => array(),
				'tab'          => 'settings-content-strategy',
				'section'      => 'aips_content_strategy_section',
				'label'        => __( 'Target Research Niches', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_MULTISELECT,
			),

			// -----------------------------------------------------------------
			// Tab: Performance & Cache ('settings-cache')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_enable_cache_system',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_section',
				'label'        => __( 'Enable Caching Layer', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_driver',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'array',
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_section',
				'label'        => __( 'Cache Storage Driver', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_cache_db_prefix',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_section',
				'label'        => __( 'Cache Key Prefix', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TEXT,
				'sanitize_callback' => 'sanitize_text_field',
			),
			array(
				'key'          => 'aips_cache_default_ttl',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 3600,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_section',
				'label'        => __( 'Default Cache TTL (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Enable Cache Monitor', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_index_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Index Cache Keys', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_metrics_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Track Hit/Miss Metrics', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_event_retention_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Metrics Retention (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_max_index_entries',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 10000,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Max Index Entries', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_preview_length',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 500,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Value Preview Length', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_full_value_debug_only',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Full Value in Debug Only', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_live_refresh_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Live Monitor Refresh', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_cache_monitor_live_refresh_interval',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-cache',
				'section'      => 'aips_cache_monitor_section',
				'label'        => __( 'Live Refresh Interval (s)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_auto_prune_enabled',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Enable Automated Database Pruning', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_telemetry_retention_value',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Telemetry Retention Value', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_telemetry_retention_unit',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'days',
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Telemetry Retention Unit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_telemetry_prune_interval',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'daily',
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Prune Cleanup Interval', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_history_log_retention_value',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 60,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'History Log Retention Value', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_history_log_retention_unit',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'days',
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'History Log Retention Unit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_ai_error_log_retention_value',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'AI Error Log Retention Value', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_ai_error_log_retention_unit',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => 'days',
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'AI Error Log Retention Unit', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SELECT,
				'sanitize_callback' => 'sanitize_key',
			),
			array(
				'key'          => 'aips_clean_orphaned_embeddings',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Clean Orphaned Embeddings', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_clean_expired_topics',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-cache',
				'section'      => 'aips_database_retention_section',
				'label'        => __( 'Clean Expired Topics', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),

			// -----------------------------------------------------------------
			// Tab: API Keys ('settings-api-keys')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_unsplash_access_key',
				'type'         => AIPS_Setting_Definition::TYPE_STRING,
				'default'      => '',
				'tab'          => 'settings-api-keys',
				'section'      => 'aips_api_keys_section',
				'label'        => __( 'Unsplash Access Key', 'ai-post-scheduler' ),
				'description'  => __( 'API credentials for fetching featured and inline images from Unsplash.', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_SECRET,
				'sanitize_callback' => 'sanitize_text_field',
			),

			// -----------------------------------------------------------------
			// Tab: Developers ('settings-developers')
			// -----------------------------------------------------------------
			array(
				'key'          => 'aips_enable_logging',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-developers',
				'section'      => 'aips_developers_section',
				'label'        => __( 'Enable Execution Logging', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_developer_mode',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-developers',
				'section'      => 'aips_developers_section',
				'label'        => __( 'Developer / Debug Mode', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_persist_table_filters',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => true,
				'tab'          => 'settings-developers',
				'section'      => 'aips_developers_section',
				'label'        => __( 'Persist Table Filters', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_log_retention_days',
				'type'         => AIPS_Setting_Definition::TYPE_INT,
				'default'      => 30,
				'tab'          => 'settings-developers',
				'section'      => 'aips_developers_section',
				'label'        => __( 'Log Retention Period (Days)', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_NUMBER,
				'sanitize_callback' => 'absint',
			),
			array(
				'key'          => 'aips_enable_telemetry',
				'type'         => AIPS_Setting_Definition::TYPE_BOOL,
				'default'      => false,
				'tab'          => 'settings-developers',
				'section'      => 'aips_developers_section',
				'label'        => __( 'Anonymous Telemetry & Diagnostics', 'ai-post-scheduler' ),
				'control_type' => AIPS_Setting_Definition::CONTROL_TOGGLE,
				'sanitize_callback' => 'absint',
			),
		);

		$this->register_many( $definitions );
	}
}
