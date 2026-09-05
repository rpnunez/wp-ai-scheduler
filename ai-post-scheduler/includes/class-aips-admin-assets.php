<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin_Assets
 *
 * Handles the enqueueing of admin styles, scripts, and script translations.
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
	private const PAGE_POST_SLICES = 'aips-post-slices';
	private const PAGE_TEMPLATES = 'aips-templates';
	private const PAGE_VOICES = 'aips-voices';
	private const PAGE_STRUCTURES = 'aips-structures';
	private const PAGE_SCHEDULE = 'aips-schedule';
	private const PAGE_CAMPAIGNS = 'aips-campaigns';
	private const PAGE_CAMPAIGN_WIZARD = 'aips-campaign-wizard';
	private const PAGE_SCHEDULE_CALENDAR = 'aips-schedule-calendar';
	private const PAGE_RESEARCH = 'aips-research';
	private const PAGE_GENERATED_POSTS = 'aips-generated-posts';
	private const PAGE_HISTORY = 'aips-history';
	private const PAGE_ONBOARDING = 'aips-onboarding';
	private const PAGE_DIAGNOSTICS = 'aips-diagnostics';
	private const PAGE_AUTOMATIONS = 'aips-automations';
	private const PAGE_DEV_TOOLS = 'aips-dev-tools';
	private const PAGE_STATUS = 'aips-status';
	private const PAGE_TAXONOMY = 'aips-taxonomy';
	private const PAGE_SOURCES = 'aips-sources';
	private const PAGE_SOURCE_DATA = 'aips-source-data';
	private const PAGE_SETTINGS = 'aips-settings';
	private const PAGE_TELEMETRY = 'aips-telemetry';
	private const PAGE_INTERNAL_LINKS = 'aips-internal-links';
	private const PAGE_CONTENT_INDEXER = 'aips-content-indexer';
	private const PAGE_CACHE_MONITOR  = 'aips-cache-monitor';
	private const PAGE_STRESS_TEST    = 'aips-stress-test';

    /**
     * Initialize the class.
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Set script translations helper for native WordPress i18n.
     *
     * @param string $handle Script handle.
     * @return void
     */
    private function set_script_translations($handle) {
        wp_set_script_translations($handle, 'ai-post-scheduler', AIPS_PLUGIN_DIR . 'languages');
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
            if ($this->is_native_post_admin_page($hook)) {
                $this->enqueue_history_modal_opener_assets();
            }
			return;
		}

		$this->enqueue_global_assets();

        if ($this->hook_contains($hook, self::HOOK_DASHBOARD) || self::PAGE_DASHBOARD === $page) {
			$this->enqueue_dashboard_assets();
		}

        if (self::PAGE_AUTHORS === $page || self::PAGE_AUTHOR_TOPICS === $page || $this->hook_contains($hook, self::PAGE_AUTHORS) || $this->hook_contains($hook, self::PAGE_AUTHOR_TOPICS) || $this->is_automations_tab($page, 'authors') || $this->is_automations_tab($page, AIPS_Automations_Controller::TAB_AUTHOR_TOPICS)) {
			$this->enqueue_authors_assets($hook);
		}

        if (self::PAGE_POST_SLICES === $page || $this->hook_contains($hook, self::PAGE_POST_SLICES)) {
			$this->enqueue_post_slices_assets();
		}

        if (self::PAGE_TEMPLATES === $page || $this->hook_contains($hook, self::PAGE_TEMPLATES) || $this->is_automations_tab($page, 'templates')) {
			$this->enqueue_templates_assets();
		}

        if (self::PAGE_VOICES === $page || $this->hook_contains($hook, self::PAGE_VOICES)) {
			$this->enqueue_voices_assets();
		}

        if (self::PAGE_STRUCTURES === $page || $this->hook_contains($hook, self::PAGE_STRUCTURES)) {
			$this->enqueue_structures_assets();
		}

        if ((self::PAGE_SCHEDULE === $page || $this->hook_contains($hook, self::PAGE_SCHEDULE) || $this->is_automations_tab($page, 'schedules')) && self::PAGE_SCHEDULE_CALENDAR !== $page && !$this->hook_contains($hook, self::PAGE_SCHEDULE_CALENDAR)) {
			$this->enqueue_schedule_assets($hook);
		}

        if (
            self::PAGE_CAMPAIGNS === $page
            || AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG === $page
            || $this->hook_contains($hook, self::PAGE_CAMPAIGNS)
            || $this->hook_contains($hook, AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG)
            || $this->is_automations_tab($page, 'campaigns')
        ) {
			$this->enqueue_campaigns_assets();
		}

        if (self::PAGE_CAMPAIGN_WIZARD === $page || $this->hook_contains($hook, self::PAGE_CAMPAIGN_WIZARD)) {
			$this->enqueue_campaign_wizard_assets();
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

		if ((self::PAGE_DEV_TOOLS === $page || $this->hook_contains($hook, self::PAGE_DEV_TOOLS) || $this->is_diagnostics_tab($page, 'dev-tools')) && AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
			$this->enqueue_dev_tools_assets();
		}

		if (self::PAGE_STATUS === $page || $this->hook_contains($hook, self::PAGE_STATUS) || $this->is_diagnostics_tab($page, 'status')) {
			$this->enqueue_status_1_assets();
			$this->enqueue_status_2_assets();
		}

        if (self::PAGE_TAXONOMY === $page || $this->hook_contains($hook, self::PAGE_TAXONOMY) || $this->is_automations_tab($page, 'taxonomy')) {
			$this->enqueue_taxonomy_assets();
		}

        if (self::PAGE_SOURCES === $page || self::PAGE_SOURCE_DATA === $page || $this->hook_contains($hook, self::PAGE_SOURCES) || $this->hook_contains($hook, self::PAGE_SOURCE_DATA) || $this->is_automations_tab($page, 'sources')) {
			$this->enqueue_sources_assets();
		}

        if (self::PAGE_SETTINGS === $page || $this->hook_contains($hook, self::PAGE_SETTINGS)) {
			$this->enqueue_settings_assets();
		}

		if ((self::PAGE_TELEMETRY === $page || $this->hook_contains($hook, self::PAGE_TELEMETRY) || $this->is_diagnostics_tab($page, 'telemetry')) && AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
			$this->enqueue_telemetry_assets();
		}

        if (self::PAGE_INTERNAL_LINKS === $page || $this->hook_contains($hook, self::PAGE_INTERNAL_LINKS) || $this->is_automations_tab($page, 'internal-links')) {
			$this->enqueue_internal_links_assets();
		}

		if (self::PAGE_CONTENT_INDEXER === $page || $this->hook_contains($hook, self::PAGE_CONTENT_INDEXER) || $this->is_automations_tab($page, 'content-indexer')) {
			$this->enqueue_content_indexer_assets();
		}

        if (self::PAGE_CACHE_MONITOR === $page || $this->hook_contains($hook, self::PAGE_CACHE_MONITOR) || $this->is_diagnostics_tab($page, 'cache-monitor')) {
			$this->enqueue_cache_monitor_assets();
		}

		if (self::PAGE_STRESS_TEST === $page || $this->hook_contains($hook, self::PAGE_STRESS_TEST) || $this->is_diagnostics_tab($page, 'stress-test') || $this->is_diagnostics_tab($page, 'stress-test-history')) {
			$this->enqueue_stress_test_assets();
		}
	}

	/**
	 * Enqueue assets for the Stress Test page.
	 *
	 * @return void
	 */
	private function enqueue_stress_test_assets() {
		wp_enqueue_style(
			'aips-stress-test-style',
			AIPS_PLUGIN_URL . 'assets/css/stress-test.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-stress-test',
			AIPS_PLUGIN_URL . 'assets/js/admin-stress-test.js',
			array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		$this->set_script_translations('aips-admin-stress-test');

		wp_localize_script('aips-admin-stress-test', 'aipsStressTestConfig', array(
			'nonce' => wp_create_nonce(AIPS_Stress_Test_Controller::NONCE_ACTION),
		));
	}

	/**
	 * Determine whether the Diagnostics page is displaying a specific tab.
	 *
	 * @param string $page Current sanitized page slug.
	 * @param string $tab Tab key to test.
	 * @return bool
	 */
	private function is_diagnostics_tab($page, $tab) {
		if (self::PAGE_DIAGNOSTICS !== $page) {
			return false;
		}

		return $tab === AIPS_Diagnostics_Controller::get_active_tab_key();
	}

	/**
	 * Determine whether the Automations page is displaying a specific tab.
	 *
	 * @param string $page Current sanitized page slug.
	 * @param string $tab Tab key to test.
	 * @return bool
	 */
	private function is_automations_tab($page, $tab) {
		if (self::PAGE_AUTOMATIONS !== $page) {
			return false;
		}

		return $tab === AIPS_Automations_Controller::get_active_tab_key();
	}

    /**
     * Determine whether current admin hook is a native WP post screen where
     * the plugin injects History links.
     *
     * @param string $hook Current admin page hook.
     * @return bool
     */
    private function is_native_post_admin_page($hook) {
        $allowed_hooks = array('edit.php', 'post.php', 'post-new.php');

        if (!in_array($hook, $allowed_hooks, true)) {
            return false;
        }

        if (!current_user_can('manage_options')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        return 'post' === $screen->post_type;
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
	 * Get the current sanitized tab key from the request.
	 *
	 * @return string
	 */
	private function get_current_tab_key() {
		$tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_SPECIAL_CHARS);

		if (!is_string($tab) || '' === $tab) {
			return '';
		}

		return sanitize_key(wp_unslash($tab));
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
        wp_enqueue_style(
            'aips-admin-style',
            AIPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPS_VERSION
        );

        wp_enqueue_script(
			'aips-datetime-script',
			AIPS_PLUGIN_URL . 'assets/js/datetime.js',
			array('jquery', 'wp-i18n'),
			AIPS_VERSION,
			true
		);
        $this->set_script_translations('aips-datetime-script');

		wp_enqueue_script(
			'aips-utilities-script',
			AIPS_PLUGIN_URL . 'assets/js/utilities.js',
			array('jquery', 'wp-i18n', 'aips-datetime-script'),
			AIPS_VERSION,
			true
		);
        $this->set_script_translations('aips-utilities-script');

        wp_enqueue_script(
            'aips-templates-script',
            AIPS_PLUGIN_URL . 'assets/js/templates.js',
            array('jquery', 'wp-i18n'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-templates-script');

        wp_enqueue_script(
            'aips-admin-script',
            AIPS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-i18n', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-script');

        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
            'schedulePageUrl' => AIPS_Admin_Menu_Helper::get_page_url('schedule'),
        ));

        $this->enqueue_history_modal_opener_script();
    }

    /**
     * Enqueue only the assets required for the History modal opener on native
     * WordPress post/admin screens.
     *
     * @return void
     */
    private function enqueue_history_modal_opener_assets() {
        wp_enqueue_style(
            'aips-admin-style',
            AIPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPS_VERSION
        );

        wp_enqueue_script(
            'aips-datetime-script',
            AIPS_PLUGIN_URL . 'assets/js/datetime.js',
            array('jquery', 'wp-i18n'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-datetime-script');

        wp_enqueue_script(
            'aips-utilities-script',
            AIPS_PLUGIN_URL . 'assets/js/utilities.js',
            array('jquery', 'wp-i18n', 'aips-datetime-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-utilities-script');

        $this->enqueue_history_modal_opener_script();
    }

    /**
     * Enqueue/localize the History modal opener script.
     *
     * @return void
     */
    private function enqueue_history_modal_opener_script() {
        wp_enqueue_script(
            'aips-admin-history',
            AIPS_PLUGIN_URL . 'assets/js/admin-history.js',
            array('jquery', 'wp-i18n', 'aips-utilities-script', 'heartbeat'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-history');

        wp_localize_script('aips-admin-history', 'aipsHistoryModalAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aips_ajax_nonce'),
        ));

        static $scaffold_registered = false;
        if (!$scaffold_registered) {
            add_action('admin_footer', array($this, 'render_history_modal_scaffold'));
            $scaffold_registered = true;
        }
    }

    /**
     * Output the History modal scaffold HTML in the admin footer.
     *
     * @return void
     */
    public function render_history_modal_scaffold() {
        ?>
        <div id="aips-history-modal" class="aips-modal" style="display: none;" aria-hidden="true">
            <div class="aips-modal-content aips-modal-large">
                <div class="aips-modal-header">
                    <div class="aips-history-modal-header-main">
                        <h3 id="aips-history-modal-title"><?php esc_html_e('History Details', 'ai-post-scheduler'); ?></h3>
                        <div id="aips-history-modal-actions" class="aips-history-modal-header-links"></div>
                    </div>
                    <div class="aips-history-modal-header-side">
                        <div id="aips-history-modal-status"></div>
                        <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
                    </div>
                </div>
                <div class="aips-modal-body" id="aips-history-modal-content"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue assets for the authors page.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    private function enqueue_authors_assets($hook) {
		  $current_page = $this->get_current_page_slug();
		  $current_tab  = $this->get_current_tab_key();
		  $is_author_topics_context = self::PAGE_AUTHOR_TOPICS === $current_page
			  || (self::PAGE_AUTOMATIONS === $current_page && AIPS_Automations_Controller::TAB_AUTHOR_TOPICS === $current_tab);
		  $is_authors_listing_context = self::PAGE_AUTHORS === $current_page
			  || (self::PAGE_AUTOMATIONS === $current_page && 'authors' === $current_tab);

          wp_enqueue_style(
            'aips-authors-style',
            AIPS_PLUGIN_URL . 'assets/css/authors.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_style(
              'aips-admin-post-review-style',
              AIPS_PLUGIN_URL . 'assets/css/admin-post-review.css',
              array('aips-authors-style'),
              AIPS_VERSION
          );

          wp_enqueue_script(
            'aips-authors-script',
            AIPS_PLUGIN_URL . 'assets/js/authors.js',
            array('jquery', 'wp-i18n', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
          );
          $this->set_script_translations('aips-authors-script');

          $page_author_id = $is_author_topics_context ? absint( filter_input( INPUT_GET, 'author_id', FILTER_VALIDATE_INT ) ) : 0;
          $deep_link_author_id = $is_authors_listing_context ? absint( filter_input( INPUT_GET, 'author_id', FILTER_VALIDATE_INT ) ) : 0;

          wp_localize_script('aips-authors-script', 'aipsAuthorsConfig', array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
          ));

          wp_localize_script('aips-authors-script', 'aipsAuthorContext', array(
              'authorId'        => $page_author_id,
              'deepLinkAuthorId' => $deep_link_author_id,
          ));

          // Embeddings script — only relevant on Authors and Author Topics pages.
          wp_enqueue_script(
              'aips-admin-embeddings',
              AIPS_PLUGIN_URL . 'assets/js/admin-embeddings.js',
              array('jquery', 'wp-i18n', 'aips-admin-script'),
              AIPS_VERSION,
              true
          );
          $this->set_script_translations('aips-admin-embeddings');

          wp_enqueue_style(
              'aips-ai-assistance-style',
              AIPS_PLUGIN_URL . 'assets/css/ai-assistance.css',
              array('aips-admin-style'),
              AIPS_VERSION
          );

          wp_enqueue_script(
              'aips-ai-assistance-script',
              AIPS_PLUGIN_URL . 'assets/js/ai-assistance.js',
              array('jquery', 'wp-i18n', 'aips-utilities-script', 'aips-templates-script', 'aips-authors-script'),
              AIPS_VERSION,
              true
          );
          $this->set_script_translations('aips-ai-assistance-script');

          wp_localize_script('aips-ai-assistance-script', 'aipsAIAssistanceConfig', array(
              'nonce' => wp_create_nonce('aips_ajax_nonce'),
          ));
    }

    /**
     * Enqueue assets for the Post Slices page.
     */
    private function enqueue_post_slices_assets() {
        wp_enqueue_style(
            'aips-post-slices-style',
            AIPS_PLUGIN_URL . 'assets/css/post-slices.css',
            array('aips-admin-style'),
            AIPS_VERSION
        );

        wp_enqueue_script(
            'aips-admin-post-slices',
            AIPS_PLUGIN_URL . 'assets/js/admin-post-slices.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-post-slices');
    }

    /**
     * Enqueue assets for the templates page.
     */
    private function enqueue_templates_assets() {
        wp_enqueue_script(
            'aips-admin-integrations',
            AIPS_PLUGIN_URL . 'assets/js/admin-integrations.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-integrations');

        wp_localize_script('aips-admin-script', 'aipsTemplatesConfig', array(
            'postTypeTaxonomySupport' => AIPS_Utilities::get_selectable_post_types(),
        ));
    }

    /**
     * Enqueue assets for the voices page.
     */
    private function enqueue_voices_assets() {
        // Voice UI strings are translated via wp.i18n in admin.js
    }

    /**
     * Enqueue assets for the structures page.
     */
    private function enqueue_structures_assets() {
        // Structure UI strings are translated via wp.i18n in admin.js
    }

    /**
     * Enqueue assets for the schedule page.
     *
     * @param string $hook The current admin page hook.
     */
    private function enqueue_schedule_assets($hook) {
        wp_localize_script('aips-admin-script', 'aipsScheduleConfig', array(
            'gmtOffsetSeconds' => (int) wp_timezone()->getOffset(new DateTime('now', wp_timezone())),
            'timezoneString'   => wp_timezone_string(),
        ));
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

          wp_enqueue_style(
            'aips-content-auditor-style',
            AIPS_PLUGIN_URL . 'assets/css/admin-content-auditor.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_script(
              'aips-admin-research',
              AIPS_PLUGIN_URL . 'assets/js/admin-research.js',
              array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-templates-script'),
              AIPS_VERSION,
              true
          );
          $this->set_script_translations('aips-admin-research');

          wp_enqueue_script(
              'aips-admin-content-auditor',
              AIPS_PLUGIN_URL . 'assets/js/admin-content-auditor.js',
              array('jquery', 'wp-i18n', 'aips-admin-script'),
              AIPS_VERSION,
              true
          );
          $this->set_script_translations('aips-admin-content-auditor');

          wp_localize_script('aips-admin-content-auditor', 'aipsAuditorConfig', array(
              'nonce' => wp_create_nonce('aips_ajax_nonce'),
          ));

          wp_enqueue_script(
              'aips-admin-planner',
              AIPS_PLUGIN_URL . 'assets/js/admin-planner.js',
              array('jquery', 'wp-i18n', 'aips-admin-script'),
              AIPS_VERSION,
              true
          );
          $this->set_script_translations('aips-admin-planner');
    }

    /**
     * Enqueue assets for the generated-posts page.
     */
    private function enqueue_generated_posts_assets() {
        // Enqueue View Session module (shared functionality)
        wp_enqueue_script(
            'aips-admin-view-session',
            AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-view-session');

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
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-admin-view-session'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-post-review');

        wp_localize_script('aips-admin-post-review', 'aipsPostReviewConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aips_ajax_nonce'),
        ));

        wp_enqueue_script(
            'aips-admin-generated-posts',
            AIPS_PLUGIN_URL . 'assets/js/admin-generated-posts.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-admin-view-session', 'aips-admin-post-review'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-generated-posts');

        $config = AIPS_Config::get_instance();
        $client_threshold = (int) $config->get_option('generated_posts_log_threshold_client', 20);
        wp_localize_script('aips-admin-generated-posts', 'aipsGeneratedPostsConfig', array(
            'clientLogThreshold' => $client_threshold,
            'siteUrl'            => home_url(),
        ));

        // AI Edit Modal (for Generated Posts page)
        wp_enqueue_script(
            'aips-admin-ai-edit',
            AIPS_PLUGIN_URL . 'assets/js/admin-ai-edit.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-ai-edit');

        wp_enqueue_style(
            'aips-admin-ai-edit',
            AIPS_PLUGIN_URL . 'assets/css/admin-ai-edit.css',
            array('aips-admin-style'),
            AIPS_VERSION
        );

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
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-calendar-script');
    }

    /**
     * Enqueue assets for the history page.
     */
    private function enqueue_history_assets() {
        wp_enqueue_script(
            'aips-admin-view-session',
            AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-view-session');

        wp_enqueue_script(
            'aips-admin-history',
            AIPS_PLUGIN_URL . 'assets/js/admin-history.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-history');

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
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-onboarding');
    }

    /**
     * Enqueue assets for the campaign wizard page.
     */
    private function enqueue_campaign_wizard_assets() {
        wp_enqueue_script(
            'aips-admin-campaign-wizard',
            AIPS_PLUGIN_URL . 'assets/js/campaign-wizard.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-campaign-wizard');

        wp_localize_script('aips-admin-campaign-wizard', 'aipsCampaignWizardConfig', array(
            'campaignWizardAIGenerateNonce' => wp_create_nonce('aips_campaign_wizard_ai_generate'),
            'nonceAiGenerate'               => wp_create_nonce('aips_campaign_wizard_ai_generate'),
        ));
    }

    /**
     * Enqueue assets for the campaigns page.
     */
    private function enqueue_campaigns_assets() {
        wp_enqueue_style(
            'aips-campaigns-style',
            AIPS_PLUGIN_URL . 'assets/css/campaigns.css',
            array('aips-admin-style'),
            AIPS_VERSION
        );

        wp_enqueue_script(
            'aips-admin-campaigns',
            AIPS_PLUGIN_URL . 'assets/js/campaigns.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-campaigns');
    }

    /**
     * Enqueue assets for the dev-tools page.
     */
    private function enqueue_dev_tools_assets() {
        wp_enqueue_script(
            'aips-admin-dev-tools',
            AIPS_PLUGIN_URL . 'assets/js/admin-dev-tools.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-dev-tools');
    }

    /**
     * Enqueue assets for the status_1 page.
     */
    private function enqueue_status_1_assets() {
        wp_enqueue_script(
            'aips-admin-db',
            AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-db');
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
            array('jquery', 'wp-i18n', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-taxonomy');

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
            array('jquery', 'wp-i18n', 'aips-utilities-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-sources');

        wp_localize_script('aips-admin-sources', 'aipsSourcesConfig', array(
            'sourceDataNonces' => array(
                'get'    => wp_create_nonce('aips_source_data_get'),
                'save'   => wp_create_nonce('aips_source_data_save'),
                'delete' => wp_create_nonce('aips_source_data_delete'),
            ),
        ));
    }

    /**
     * Enqueue assets for the settings page.
     */
    private function enqueue_settings_assets() {
        wp_enqueue_script(
            'aips-admin-settings',
            AIPS_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-settings');
    }

    /**
     * Enqueue assets for the status_2 page.
     */
    private function enqueue_status_2_assets() {
        wp_enqueue_script(
            'aips-admin-system-status',
            AIPS_PLUGIN_URL . 'assets/js/admin-system-status.js',
            array('jquery', 'wp-i18n', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-system-status');

        wp_localize_script('aips-admin-system-status', 'aipsSystemStatusConfig', array(
            'nonce'                        => wp_create_nonce('aips_reset_circuit_breaker'),
            'nonceCronReschedule'          => wp_create_nonce('aips_status_reschedule_missed_cron'),
            'nonceRetrySlices'             => wp_create_nonce('aips_status_retry_failed_slices'),
            'nonceRepairCampaignData'      => wp_create_nonce('aips_status_repair_campaign_data'),
            'nonceClearPartialGenerations' => wp_create_nonce('aips_status_clear_partial_generations'),
            'nonceCleanupStaleJobsCache'   => wp_create_nonce('aips_status_cleanup_stale_jobs_cache'),
            'nonceRebuildCaches'         => wp_create_nonce('aips_rebuild_caches'),
            'nonceRefreshSystem'           => wp_create_nonce('aips_status_refresh_system'),
            'nonceCacheMaintenance'        => wp_create_nonce('aips_status_cache_maintenance'),
            'nonceCleanupNotifications'    => wp_create_nonce('aips_status_cleanup_notifications'),
            'nonceResetResilience'         => wp_create_nonce('aips_status_reset_resilience'),
            'nonceRepairDatetime'          => wp_create_nonce('aips_status_repair_datetime'),
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
			array('jquery', 'wp-i18n', 'aips-utilities-script', 'aips-admin-script', 'aips-chartjs', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);
		$this->set_script_translations('aips-dashboard-script');

		wp_localize_script('aips-dashboard-script', 'aipsDashboardConfig', array(
			'nonce' => wp_create_nonce('aips_ajax_nonce'),
		));
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
			array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-templates-script', 'aips-chartjs', 'aips-datetime-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-telemetry-script');

        wp_localize_script('aips-telemetry-script', 'aipsTelemetryConfig', array(
            'nonce'        => wp_create_nonce('aips_get_telemetry'),
            'detailsNonce' => wp_create_nonce('aips_get_telemetry_details'),
            'locale'       => get_locale(),
        ));
    }

    /**
     * Enqueue assets for the internal-links page.
     */
    private function enqueue_internal_links_assets() {
        wp_enqueue_script(
            'aips-admin-internal-links',
            AIPS_PLUGIN_URL . 'assets/js/admin-internal-links.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-admin-internal-links');

        wp_localize_script('aips-admin-internal-links', 'aipsInternalLinksConfig', array(
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        ));
    }

    /**
     * Enqueue assets for the Content Indexer page.
     */
    private function enqueue_content_indexer_assets() {
        wp_enqueue_style(
            'aips-content-indexer-style',
            AIPS_PLUGIN_URL . 'assets/css/admin-content-indexer.css',
            array('aips-admin-style'),
            AIPS_VERSION
        );

        wp_enqueue_script(
            'aips-content-indexer-script',
            AIPS_PLUGIN_URL . 'assets/js/admin-content-indexer.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-content-indexer-script');

        wp_localize_script(
            'aips-content-indexer-script',
            'aipsContentIndexerConfig',
            array(
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
            )
        );
    }

    /**
     * Enqueue assets for the Cache Monitor page.
     *
     * @return void
     */
    private function enqueue_cache_monitor_assets() {
        wp_enqueue_script(
            'aips-cache-monitor',
            AIPS_PLUGIN_URL . 'assets/js/cache-monitor.js',
            array('jquery', 'wp-i18n', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
            AIPS_VERSION,
            true
        );
        $this->set_script_translations('aips-cache-monitor');

        wp_localize_script('aips-cache-monitor', 'aipsCacheMonitorConfig', array(
            'nonce'       => wp_create_nonce('aips_cache_monitor'),
            'actionNonce' => wp_create_nonce('aips_cache_monitor_action'),
        ));
    }

}
