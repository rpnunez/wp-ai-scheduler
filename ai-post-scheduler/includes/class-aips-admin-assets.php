<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin_Assets
 *
 * Handles the enqueueing of admin styles and scripts.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_Assets {

	/**
	 * Plugin page slug prefix.
	 */
	private const PAGE_PREFIX = 'aips-';

	/**
	 * Main dashboard page slug.
	 */
	private const PAGE_DASHBOARD = 'ai-post-scheduler';

	/**
	 * Dashboard hook suffix.
	 */
	private const HOOK_DASHBOARD = 'toplevel_page_ai-post-scheduler';

	/**
	 * Admin page slugs.
	 */
	private const PAGE_AUTHORS = 'aips-authors';
	private const PAGE_AUTHOR_TOPICS = 'aips-author-topics';
	private const PAGE_TEMPLATES = 'aips-templates';
	private const PAGE_VOICES = 'aips-voices';
	private const PAGE_STRUCTURES = 'aips-structures';
	private const PAGE_SCHEDULE = 'aips-schedule';
	private const PAGE_SCHEDULE_CALENDAR = 'aips-schedule-calendar';
	private const PAGE_RESEARCH = 'aips-research';
	private const PAGE_GENERATED_POSTS = 'aips-generated-posts';
	private const PAGE_HISTORY = 'aips-history';
	private const PAGE_ONBOARDING = 'aips-onboarding';
	private const PAGE_DEV_TOOLS = 'aips-dev-tools';
	private const PAGE_STATUS = 'aips-status';
	private const PAGE_TAXONOMY = 'aips-taxonomy';
	private const PAGE_SOURCES = 'aips-sources';
	private const PAGE_SETTINGS = 'aips-settings';
	private const PAGE_TELEMETRY = 'aips-telemetry';
	private const PAGE_INTERNAL_LINKS = 'aips-internal-links';

    /**
     * Initialize the class.
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin styles and scripts.
     *
     * Loads CSS and JS assets only on plugin-specific pages.
     *
     * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets($hook) {
        $page = $this->get_current_page_slug();

        if (!$this->is_plugin_admin_page($hook, $page)) {
			return;
		}

		$this->enqueue_global_assets();

        if ($this->hook_contains($hook, self::HOOK_DASHBOARD) || self::PAGE_DASHBOARD === $page) {
			$this->enqueue_dashboard_assets();
		}

        if (self::PAGE_AUTHORS === $page || self::PAGE_AUTHOR_TOPICS === $page || $this->hook_contains($hook, self::PAGE_AUTHORS) || $this->hook_contains($hook, self::PAGE_AUTHOR_TOPICS)) {
			$this->enqueue_authors_assets($hook);
		}

        if (self::PAGE_TEMPLATES === $page || $this->hook_contains($hook, self::PAGE_TEMPLATES)) {
			$this->enqueue_templates_assets();
		}

        if (self::PAGE_VOICES === $page || $this->hook_contains($hook, self::PAGE_VOICES)) {
			$this->enqueue_voices_assets();
		}

        if (self::PAGE_STRUCTURES === $page || $this->hook_contains($hook, self::PAGE_STRUCTURES)) {
			$this->enqueue_structures_assets();
		}

        if ((self::PAGE_SCHEDULE === $page || $this->hook_contains($hook, self::PAGE_SCHEDULE)) && self::PAGE_SCHEDULE_CALENDAR !== $page && !$this->hook_contains($hook, self::PAGE_SCHEDULE_CALENDAR)) {
			$this->enqueue_schedule_assets($hook);
		}

        if (self::PAGE_RESEARCH === $page || $this->hook_contains($hook, self::PAGE_RESEARCH)) {
			$this->enqueue_research_assets();
		}

        if (self::PAGE_GENERATED_POSTS === $page || $this->hook_contains($hook, self::PAGE_GENERATED_POSTS)) {
			$this->enqueue_generated_posts_assets();
		}

        if (self::PAGE_SCHEDULE_CALENDAR === $page || $this->hook_contains($hook, self::PAGE_SCHEDULE_CALENDAR)) {
			$this->enqueue_schedule_calendar_assets();
		}

        if (self::PAGE_HISTORY === $page || $this->hook_contains($hook, self::PAGE_HISTORY)) {
			$this->enqueue_history_assets();
		}

        if (self::PAGE_ONBOARDING === $page || $this->hook_contains($hook, self::PAGE_ONBOARDING)) {
			$this->enqueue_onboarding_assets();
		}

        if (self::PAGE_DEV_TOOLS === $page || $this->hook_contains($hook, self::PAGE_DEV_TOOLS)) {
			$this->enqueue_dev_tools_assets();
		}

        if (self::PAGE_STATUS === $page || $this->hook_contains($hook, self::PAGE_STATUS)) {
			$this->enqueue_status_1_assets();
			$this->enqueue_status_2_assets();
		}

        if (self::PAGE_TAXONOMY === $page || $this->hook_contains($hook, self::PAGE_TAXONOMY)) {
			$this->enqueue_taxonomy_assets();
		}

        if (self::PAGE_SOURCES === $page || $this->hook_contains($hook, self::PAGE_SOURCES)) {
			$this->enqueue_sources_assets();
		}

        if (self::PAGE_SETTINGS === $page || $this->hook_contains($hook, self::PAGE_SETTINGS)) {
			$this->enqueue_settings_assets();
		}

        if (self::PAGE_TELEMETRY === $page || $this->hook_contains($hook, self::PAGE_TELEMETRY)) {
			$this->enqueue_telemetry_assets();
		}

        if (self::PAGE_INTERNAL_LINKS === $page || $this->hook_contains($hook, self::PAGE_INTERNAL_LINKS)) {
			$this->enqueue_internal_links_assets();
		}

	}

    /**
     * Determine whether the current request is one of this plugin's admin pages.
     *
     * @param string $hook Current admin page hook.
     * @param string $page Current sanitized page slug.
     * @return bool
     */
    private function is_plugin_admin_page($hook, $page) {
        if (self::PAGE_DASHBOARD === $page || 0 === strpos($page, self::PAGE_PREFIX)) {
            return true;
        }

        return $this->hook_contains($hook, self::PAGE_DASHBOARD) || $this->hook_contains($hook, self::PAGE_PREFIX);
    }

    /**
     * Get the current sanitized admin page slug from the request.
     *
     * @return string
     */
    private function get_current_page_slug() {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!is_string($page) || '' === $page) {
            return '';
        }

        return sanitize_key(wp_unslash($page));
    }

	/**
	 * Check whether the current admin hook includes a page slug.
	 *
	 * @param string $hook   Current admin page hook.
	 * @param string $needle Page slug or hook fragment.
	 * @return bool
	 */
	private function hook_contains($hook, $needle) {
		return strpos($hook, $needle) !== false;
	}

    /**
     * Enqueue global plugin assets.
     */
    private function enqueue_global_assets() {

        // Global Admin Styles and Scripts

        wp_enqueue_style(
            'aips-admin-style',
            AIPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPS_VERSION
        );

        wp_enqueue_script(
			'aips-datetime-script',
			AIPS_PLUGIN_URL . 'assets/js/datetime.js',
			array('jquery'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'aips-datetime-script'),
			AIPS_VERSION,
			true
		);

        wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', AIPS_Admin_L10n::get('utilities'));

        wp_enqueue_script(
            'aips-templates-script',
            AIPS_PLUGIN_URL . 'assets/js/templates.js',
            array('jquery'),
            AIPS_VERSION,
            true
        );

        wp_enqueue_script(
            'aips-admin-script',
            AIPS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'aips-utilities-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'schedulePageUrl' => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
        ));

        wp_localize_script('aips-admin-script', 'aipsAdminL10n', AIPS_Admin_L10n::get('admin'));
    }

    /**
     * Enqueue assets for the authors page.
     * @param string $hook The current admin page hook.
     */
    private function enqueue_authors_assets($hook) {
          wp_enqueue_style(
            'aips-authors-style',
            AIPS_PLUGIN_URL . 'assets/css/authors.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_script(
            'aips-authors-script',
            AIPS_PLUGIN_URL . 'assets/js/authors.js',
            array('jquery', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
          );

          // Localize script with translations and nonce
          $page_author_id = ( strpos( $hook, 'aips-author-topics' ) !== false && isset( $_GET['author_id'] ) ) ? absint( $_GET['author_id'] ) : 0;

          wp_localize_script('aips-authors-script', 'aipsAuthorsL10n', AIPS_Admin_L10n::get('authors'));
          wp_localize_script('aips-authors-script', 'aipsAuthorsConfig', array(
              'nonce' => wp_create_nonce('aips_ajax_nonce'),
          ));

          // Pass page-context data (not i18n) in a separate object so it stays
          // semantically distinct from the translation strings above.
          $deep_link_author_id = ( strpos( $hook, 'aips-authors' ) !== false && strpos( $hook, 'aips-author-topics' ) === false ) ? absint( filter_input( INPUT_GET, 'author_id', FILTER_VALIDATE_INT ) ) : 0;
          wp_localize_script('aips-authors-script', 'aipsAuthorContext', array(
              'authorId'        => $page_author_id,
              'deepLinkAuthorId' => $deep_link_author_id,
            ));

          // Embeddings script — only relevant on Authors and Author Topics pages.
          wp_enqueue_script(
              'aips-admin-embeddings',
              AIPS_PLUGIN_URL . 'assets/js/admin-embeddings.js',
              array('jquery', 'aips-admin-script'),
              AIPS_VERSION,
              true
          );
    }

    /**
     * Enqueue assets for the templates page.
     */
    private function enqueue_templates_assets() {
            wp_localize_script('aips-admin-script', 'aipsTemplatesL10n', AIPS_Admin_L10n::get('templates'));
    }

    /**
     * Enqueue assets for the voices page.
     */
    private function enqueue_voices_assets() {
            wp_localize_script('aips-admin-script', 'aipsVoicesL10n', AIPS_Admin_L10n::get('voices'));
    }

    /**
     * Enqueue assets for the structures page.
     */
    private function enqueue_structures_assets() {
            wp_localize_script('aips-admin-script', 'aipsStructuresL10n', AIPS_Admin_L10n::get('structures'));
    }

    /**
     * Enqueue assets for the schedule page.
     * @param string $hook The current admin page hook.
     */
    private function enqueue_schedule_assets($hook) {
            wp_localize_script('aips-admin-script', 'aipsScheduleL10n', AIPS_Admin_L10n::get('schedule'));
    }

    /**
     * Enqueue assets for the research page.
     */
    private function enqueue_research_assets() {
          wp_enqueue_style(
            'aips-research-style',
            AIPS_PLUGIN_URL . 'assets/css/research.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_style(
            'aips-planner-style',
            AIPS_PLUGIN_URL . 'assets/css/planner.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_script(
              'aips-admin-research',
              AIPS_PLUGIN_URL . 'assets/js/admin-research.js',
              array('aips-admin-script', 'aips-templates-script'),
              AIPS_VERSION,
              true
          );

          wp_enqueue_script(
              'aips-admin-planner',
              AIPS_PLUGIN_URL . 'assets/js/admin-planner.js',
              array('aips-admin-script'),
              AIPS_VERSION,
              true
          );

          wp_localize_script('aips-admin-research', 'aipsResearchL10n', AIPS_Admin_L10n::get('research'));
    }

    /**
     * Enqueue assets for the generated-posts page.
     */
    private function enqueue_generated_posts_assets() {
            // Enqueue View Session module (shared functionality)
            wp_enqueue_script(
                'aips-admin-view-session',
                AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );

            // Enqueue Post Review module (for Pending Review tab)
            wp_enqueue_style(
                'aips-admin-post-review',
                AIPS_PLUGIN_URL . 'assets/css/admin-post-review.css',
                array('aips-admin-style'),
                AIPS_VERSION
            );

            wp_enqueue_script(
                'aips-admin-post-review',
                AIPS_PLUGIN_URL . 'assets/js/admin-post-review.js',
                array('aips-admin-script', 'aips-admin-view-session'),
                AIPS_VERSION,
                true
            );

            wp_enqueue_script(
                'aips-admin-generated-posts',
                AIPS_PLUGIN_URL . 'assets/js/admin-generated-posts.js',
                array('aips-admin-script', 'aips-admin-view-session', 'aips-admin-post-review'),
                AIPS_VERSION,
                true
            );

            // Pass client-side threshold from config to JS
            $config = AIPS_Config::get_instance();
            $client_threshold = (int) $config->get_option('generated_posts_log_threshold_client', 20);
            wp_localize_script('aips-admin-generated-posts', 'aipsGeneratedPostsConfig', array(
                'clientLogThreshold' => $client_threshold,
                'siteUrl' => home_url(),
            ));

            // Localize Post Review script for Pending Review tab
            wp_localize_script('aips-admin-post-review', 'aipsPostReviewL10n', AIPS_Admin_L10n::get('post_review'));
            wp_localize_script('aips-admin-post-review', 'aipsPostReviewConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aips_ajax_nonce'),
            ));

            // AI Edit Modal (for Generated Posts page)
            wp_enqueue_script(
                'aips-admin-ai-edit',
                AIPS_PLUGIN_URL . 'assets/js/admin-ai-edit.js',
                array('jquery', 'aips-admin-script'),
                AIPS_VERSION,
                true
            );

            wp_enqueue_style(
                'aips-admin-ai-edit',
                AIPS_PLUGIN_URL . 'assets/css/admin-ai-edit.css',
                array('aips-admin-style'),
                AIPS_VERSION
            );

            wp_localize_script('aips-admin-ai-edit', 'aipsAIEditL10n', AIPS_Admin_L10n::get('ai_edit'));
            wp_localize_script('aips-admin-ai-edit', 'aipsAIEditConfig', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('aips_ajax_nonce'),
            ));
    }

    /**
     * Enqueue assets for the schedule-calendar page.
     */
    private function enqueue_schedule_calendar_assets() {
            wp_enqueue_style(
                'aips-calendar-style',
                AIPS_PLUGIN_URL . 'assets/css/calendar.css',
                array(),
                AIPS_VERSION
            );

            wp_enqueue_script(
                'aips-calendar-script',
                AIPS_PLUGIN_URL . 'assets/js/calendar.js',
                array('jquery', 'aips-admin-script'),
                AIPS_VERSION,
                true
            );
    }

    /**
     * Enqueue assets for the history page.
     */
    private function enqueue_history_assets() {
            wp_enqueue_script(
                'aips-admin-view-session',
                AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );

            wp_enqueue_script(
                'aips-admin-history',
                AIPS_PLUGIN_URL . 'assets/js/admin-history.js',
                array('jquery', 'aips-admin-script', 'aips-templates-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-history', 'aipsHistoryL10n', AIPS_Admin_L10n::get('history'));
            wp_localize_script('aips-admin-history', 'aipsHistoryConfig', array(
                'typeLabels' => AIPS_History_Type::get_all_types(),
            ));
    }

    /**
     * Enqueue assets for the onboarding page.
     */
    private function enqueue_onboarding_assets() {
            wp_enqueue_script(
                'aips-admin-onboarding',
                AIPS_PLUGIN_URL . 'assets/js/onboarding.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-onboarding', 'aipsOnboardingL10n', AIPS_Admin_L10n::get('onboarding'));
    }

    /**
     * Enqueue assets for the dev-tools page.
     */
    private function enqueue_dev_tools_assets() {
            wp_enqueue_script(
                'aips-admin-dev-tools',
                AIPS_PLUGIN_URL . 'assets/js/admin-dev-tools.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
    }

    /**
     * Enqueue assets for the status_1 page.
     */
    private function enqueue_status_1_assets() {
            wp_enqueue_script(
                'aips-admin-db',
                AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
    }

    /**
     * Enqueue assets for the taxonomy page.
     */
    private function enqueue_taxonomy_assets() {
            wp_enqueue_style(
                'aips-authors-style',
                AIPS_PLUGIN_URL . 'assets/css/authors.css',
                array('aips-admin-style'),
                AIPS_VERSION
            );

            wp_enqueue_script(
                'aips-admin-taxonomy',
                AIPS_PLUGIN_URL . 'assets/js/taxonomy.js',
                array('jquery', 'aips-utilities-script', 'aips-templates-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-taxonomy', 'aipsTaxonomyL10n', AIPS_Admin_L10n::get('taxonomy'));
            wp_localize_script('aips-admin-taxonomy', 'aipsTaxonomyConfig', array(
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
            ));
    }

    /**
     * Enqueue assets for the sources page.
     */
    private function enqueue_sources_assets() {
            wp_enqueue_script(
                'aips-admin-sources',
                AIPS_PLUGIN_URL . 'assets/js/admin-sources.js',
                array('jquery', 'aips-utilities-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-sources', 'aipsSourcesL10n', AIPS_Admin_L10n::get('sources'));
    }

    /**
     * Enqueue assets for the settings page.
     */
    private function enqueue_settings_assets() {
            wp_enqueue_script(
                'aips-admin-settings',
                AIPS_PLUGIN_URL . 'assets/js/admin-settings.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
    }

    /**
     * Enqueue assets for the status_2 page.
     */
    private function enqueue_status_2_assets() {
            wp_enqueue_script(
                'aips-admin-system-status',
                AIPS_PLUGIN_URL . 'assets/js/admin-system-status.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
            wp_localize_script('aips-admin-system-status', 'aipsSystemStatusL10n', AIPS_Admin_L10n::get('system_status'));
            wp_localize_script('aips-admin-system-status', 'aipsSystemStatusConfig', array(
                'nonce' => wp_create_nonce('aips_reset_circuit_breaker'),
            ));
    }

    /**
     * Enqueue assets for the main dashboard page.
     */
    private function enqueue_dashboard_assets() {
        wp_enqueue_script(
            'aips-chartjs',
            apply_filters(
                'aips_chartjs_src',
                AIPS_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js'
            ),
            array(),
            '4.4.2',
            true
        );

        wp_enqueue_script(
            'aips-dashboard-script',
            AIPS_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array('jquery', 'aips-utilities-script', 'aips-admin-script', 'aips-chartjs'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-dashboard-script', 'aipsDashboardL10n', AIPS_Admin_L10n::get('dashboard'));
    }

    /**
     * Enqueue assets for the telemetry page.
     */
    private function enqueue_telemetry_assets() {
            wp_enqueue_style(
                'aips-telemetry-style',
                AIPS_PLUGIN_URL . 'assets/css/telemetry.css',
                array('aips-admin-style'),
                AIPS_VERSION
            );

            wp_enqueue_script(
                'aips-chartjs',
                apply_filters(
                    'aips_chartjs_src',
                    AIPS_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js'
                ),
                array(),
                '4.4.2',
                true
            );

            wp_enqueue_script(
                'aips-telemetry-script',
                AIPS_PLUGIN_URL . 'assets/js/telemetry.js',
				array('jquery', 'aips-admin-script', 'aips-templates-script', 'aips-chartjs', 'aips-datetime-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-telemetry-script', 'aipsTelemetryL10n', AIPS_Admin_L10n::get('telemetry'));
            wp_localize_script('aips-telemetry-script', 'aipsTelemetryConfig', array(
                'nonce'       => wp_create_nonce('aips_get_telemetry'),
                'detailsNonce' => wp_create_nonce('aips_get_telemetry_details'),
                'locale'      => get_locale(),
            ));
    }

    /**
     * Enqueue assets for the internal-links page.
     */
    private function enqueue_internal_links_assets() {
            wp_enqueue_script(
                'aips-admin-internal-links',
                AIPS_PLUGIN_URL . 'assets/js/admin-internal-links.js',
                array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
                AIPS_VERSION,
                true
            );
            wp_localize_script('aips-admin-internal-links', 'aipsInternalLinksL10n', AIPS_Admin_L10n::get('internal_links'));
            wp_localize_script('aips-admin-internal-links', 'aipsInternalLinksConfig', array(
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
            ));
    }

}
