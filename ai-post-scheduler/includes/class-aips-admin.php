<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin
 *
 * Handles the registration of admin menu pages, asset management, and general admin interface rendering.
 * Serves as the central router for the plugin's admin area.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin {

    /**
     * Initialize the admin class.
     *
     * Hooks into admin_menu and admin_enqueue_scripts.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers the main menu page and subpages for Dashboard, Voices, Templates,
     * Schedule, Research, Settings, and System Status.
     *
     * @return void
     */
    public function add_menu_pages() {
        add_menu_page(
            __('AI Post Scheduler', 'ai-post-scheduler'),
            __('AI Post Scheduler', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page'),
            'dashicons-schedule',
            30
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Dashboard', 'ai-post-scheduler'),
            __('Dashboard', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Activity', 'ai-post-scheduler'),
            __('Activity', 'ai-post-scheduler'),
            'manage_options',
            'aips-activity',
            array($this, 'render_activity_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Schedule', 'ai-post-scheduler'),
            __('Schedule', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule',
            array($this, 'render_schedule_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Templates', 'ai-post-scheduler'),
            __('Templates', 'ai-post-scheduler'),
            'manage_options',
            'aips-templates',
            array($this, 'render_templates_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Authors', 'ai-post-scheduler'),
            __('Authors', 'ai-post-scheduler'),
            'manage_options',
            'aips-authors',
            array($this, 'render_authors_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Voices', 'ai-post-scheduler'),
            __('Voices', 'ai-post-scheduler'),
            'manage_options',
            'aips-voices',
            array($this, 'render_voices_page')
        );

         add_submenu_page(
             'ai-post-scheduler',
             __('Research', 'ai-post-scheduler'),
             __('Research', 'ai-post-scheduler'),
             'manage_options',
             'aips-research',
             array($this, 'render_research_page')
         );

        add_submenu_page(
            'ai-post-scheduler',
            __('Article Structures', 'ai-post-scheduler'),
            __('Article Structures', 'ai-post-scheduler'),
            'manage_options',
            'aips-structures',
            array($this, 'render_structures_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_seeder_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('System Status', 'ai-post-scheduler'),
            __('System Status', 'ai-post-scheduler'),
            'manage_options',
            'aips-status',
            array($this, 'render_status_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
        );

        if (get_option('aips_developer_mode')) {
            add_submenu_page(
                'ai-post-scheduler',
                __('Dev Tools', 'ai-post-scheduler'),
                __('Dev Tools', 'ai-post-scheduler'),
                'manage_options',
                'aips-dev-tools',
                array($this, 'render_dev_tools_page')
            );
        }
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
        if (strpos($hook, 'ai-post-scheduler') === false && strpos($hook, 'aips-') === false) {
            return;
        }

        wp_enqueue_media();

        // Global Admin Styles and Scripts

        wp_enqueue_style(
            'aips-admin-style',
            AIPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPS_VERSION
        );

        wp_enqueue_script(
            'aips-admin-script',
            AIPS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        ));

        wp_localize_script('aips-admin-script', 'aipsAdminL10n', array(
            'deleteStructureConfirm' => __('Are you sure you want to delete this structure?', 'ai-post-scheduler'),
            'saveStructureFailed' => __('Failed to save structure.', 'ai-post-scheduler'),
            'loadStructureFailed' => __('Failed to load structure.', 'ai-post-scheduler'),
            'deleteStructureFailed' => __('Failed to delete structure.', 'ai-post-scheduler'),
            'deleteSectionConfirm' => __('Are you sure you want to delete this prompt section?', 'ai-post-scheduler'),
            'saveSectionFailed' => __('Failed to save prompt section.', 'ai-post-scheduler'),
            'loadSectionFailed' => __('Failed to load prompt section.', 'ai-post-scheduler'),
            'deleteSectionFailed' => __('Failed to delete prompt section.', 'ai-post-scheduler'),
            'errorOccurred' => __('An error occurred.', 'ai-post-scheduler'),
            'errorTryAgain' => __('An error occurred. Please try again.', 'ai-post-scheduler'),
        ));

		// Enqueue Authors-specific assets
		if (strpos($hook, 'aips-authors') !== false) {
			wp_enqueue_style(
				'aips-authors-style',
				AIPS_PLUGIN_URL . 'assets/css/authors.css',
				array(),
				AIPS_VERSION
			);

			wp_enqueue_script(
				'aips-authors-script',
				AIPS_PLUGIN_URL . 'assets/js/authors.js',
				array('jquery'),
				AIPS_VERSION,
				true
			);

			// Localize script with translations and nonce
			wp_localize_script('aips-authors-script', 'aipsAuthorsL10n', array(
				'nonce' => wp_create_nonce('aips_ajax_nonce'),
				'addNewAuthor' => __('Add New Author', 'ai-post-scheduler'),
				'editAuthor' => __('Edit Author', 'ai-post-scheduler'),
				'saveAuthor' => __('Save Author', 'ai-post-scheduler'),
				'loading' => __('Loading...', 'ai-post-scheduler'),
				'saving' => __('Saving...', 'ai-post-scheduler'),
				'generating' => __('Generating...', 'ai-post-scheduler'),
				'confirmDelete' => __('Are you sure you want to delete this author? This will also delete all associated topics and logs.', 'ai-post-scheduler'),
				'confirmDeleteTopic' => __('Are you sure you want to delete this topic?', 'ai-post-scheduler'),
				'confirmGenerateTopics' => __('Generate topics for this author now?', 'ai-post-scheduler'),
				'confirmGeneratePost' => __('Generate a post from this topic now?', 'ai-post-scheduler'),
				'authorSaved' => __('Author saved successfully.', 'ai-post-scheduler'),
				'authorDeleted' => __('Author deleted successfully.', 'ai-post-scheduler'),
				'topicsGenerated' => __('Topics generated successfully.', 'ai-post-scheduler'),
				'postGenerated' => __('Post generated successfully.', 'ai-post-scheduler'),
				'generateTopicsNow' => __('Generate Topics Now', 'ai-post-scheduler'),
				'generatePostNow' => __('Generate Post Now', 'ai-post-scheduler'),
				'errorLoading' => __('Error loading author data.', 'ai-post-scheduler'),
				'errorSaving' => __('Error saving author.', 'ai-post-scheduler'),
				'errorDeleting' => __('Error deleting author.', 'ai-post-scheduler'),
				'errorGenerating' => __('Error generating topics.', 'ai-post-scheduler'),
				'errorLoadingTopics' => __('Error loading topics.', 'ai-post-scheduler'),
				'errorApproving' => __('Error approving topic.', 'ai-post-scheduler'),
				'errorRejecting' => __('Error rejecting topic.', 'ai-post-scheduler'),
				'errorDeletingTopic' => __('Error deleting topic.', 'ai-post-scheduler'),
				'errorSavingTopic' => __('Error saving topic.', 'ai-post-scheduler'),
				'errorGeneratingPost' => __('Error generating post.', 'ai-post-scheduler'),
				'loadingTopics' => __('Loading topics...', 'ai-post-scheduler'),
				'noTopicsFound' => __('No topics found.', 'ai-post-scheduler'),
				'topicTitle' => __('Topic Title', 'ai-post-scheduler'),
				'generatedAt' => __('Generated', 'ai-post-scheduler'),
				'actions' => __('Actions', 'ai-post-scheduler'),
				'approve' => __('Approve', 'ai-post-scheduler'),
				'reject' => __('Reject', 'ai-post-scheduler'),
				'edit' => __('Edit', 'ai-post-scheduler'),
				'delete' => __('Delete', 'ai-post-scheduler'),
				'save' => __('Save', 'ai-post-scheduler'),
				'cancel' => __('Cancel', 'ai-post-scheduler'),
				'topicTitleRequired' => __('Topic title is required.', 'ai-post-scheduler'),
				'viewPosts' => __('Click to view posts generated from this topic', 'ai-post-scheduler'),
				'loadingPosts' => __('Loading posts...', 'ai-post-scheduler'),
				'errorLoadingPosts' => __('Error loading posts.', 'ai-post-scheduler'),
				'noPostsFound' => __('No posts have been generated from this topic yet.', 'ai-post-scheduler'),
				'postsGeneratedFrom' => __('Posts Generated from Topic', 'ai-post-scheduler'),
				'postId' => __('Post ID', 'ai-post-scheduler'),
				'postTitle' => __('Post Title', 'ai-post-scheduler'),
				'dateGenerated' => __('Date Generated', 'ai-post-scheduler'),
				'datePublished' => __('Date Published', 'ai-post-scheduler'),
				'notPublished' => __('Not published', 'ai-post-scheduler'),
				'editPost' => __('Edit', 'ai-post-scheduler'),
				'viewPost' => __('View', 'ai-post-scheduler'),
				'topic' => __('Topic', 'ai-post-scheduler'),
				'action' => __('Action', 'ai-post-scheduler'),
				'reason' => __('Reason', 'ai-post-scheduler'),
				'user' => __('User', 'ai-post-scheduler'),
				'date' => __('Date', 'ai-post-scheduler'),
				'approveTopicTitle' => __('Approve Topic', 'ai-post-scheduler'),
				'rejectTopicTitle' => __('Reject Topic', 'ai-post-scheduler'),
				'approveReasonPlaceholder' => __('Why are you approving this topic? (optional)', 'ai-post-scheduler'),
				'rejectReasonPlaceholder' => __('Why are you rejecting this topic? (optional)', 'ai-post-scheduler')
			));
		}

        // Research Page Scripts

        wp_enqueue_script(
            'aips-admin-research',
            AIPS_PLUGIN_URL . 'assets/js/admin-research.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-research', 'aipsResearchL10n', array(
            'topicsSaved' => __('topics saved for', 'ai-post-scheduler'),
            'topTopics' => __('Top 5 Topics:', 'ai-post-scheduler'),
            'noTopicsFound' => __('No topics found matching your filters.', 'ai-post-scheduler'),
            'deleteTopicConfirm' => __('Delete this topic?', 'ai-post-scheduler'),
            'selectTopicSchedule' => __('Please select at least one topic to schedule.', 'ai-post-scheduler'),
            'researchError' => __('An error occurred during research.', 'ai-post-scheduler'),
            'schedulingError' => __('An error occurred during scheduling.', 'ai-post-scheduler'),
            'delete' => __('Delete', 'ai-post-scheduler'),
        ));

        // Planner Page Scripts

        wp_enqueue_script(
            'aips-admin-planner',
            AIPS_PLUGIN_URL . 'assets/js/admin-planner.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        // Database Page Scripts

        wp_enqueue_script(
            'aips-admin-db',
            AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        // Activity Page Scripts

        wp_enqueue_script(
            'aips-admin-activity',
            AIPS_PLUGIN_URL . 'assets/js/admin-activity.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-activity', 'aipsActivityL10n', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_activity_nonce'),
            'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
            'publishSuccess' => __('Post published successfully!', 'ai-post-scheduler'),
            'publishError' => __('Failed to publish post.', 'ai-post-scheduler'),
            'loadingError' => __('Failed to load activity data.', 'ai-post-scheduler'),
        ));

        if (strpos($hook, 'aips-dev-tools') !== false) {
            wp_enqueue_script(
                'aips-admin-dev-tools',
                AIPS_PLUGIN_URL . 'assets/js/admin-dev-tools.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
        }
    }

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics and recent activity from the database to display
     * on the dashboard template.
     *
     * @return void
     */
    public function render_dashboard_page() {
        // Use repositories instead of direct SQL
        $history_repo = new AIPS_History_Repository();
        $schedule_repo = new AIPS_Schedule_Repository();
        $template_repo = new AIPS_Template_Repository();

        // Get stats
        $history_stats = $history_repo->get_stats();
        $schedule_counts = $schedule_repo->count_by_status();
        $template_counts = $template_repo->count_by_status();

        $total_generated = $history_stats['completed'];
        $pending_scheduled = $schedule_counts['active'];
        $total_templates = $template_counts['active'];
        $failed_count = $history_stats['failed'];

        // Get recent history
        $recent_posts_data = $history_repo->get_history(array('per_page' => 5));
        $recent_posts = $recent_posts_data['items'];

        $upcoming = $schedule_repo->get_upcoming(5);

        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render the Voices management page.
     *
     * Delegates rendering to the AIPS_Voices class.
     *
     * @return void
     */
    public function render_voices_page() {
        $voices_handler = new AIPS_Voices();
        $voices_handler->render_page();
    }

    /**
     * Render the Templates management page.
     *
     * Delegates rendering to the AIPS_Templates class.
     *
     * @return void
     */
    public function render_templates_page() {
        $templates_handler = new AIPS_Templates();
        $templates_handler->render_page();
    }

    /**
     * Render the Schedule management page.
     *
     * Includes the schedule template file.
     *
     * @return void
     */
    public function render_schedule_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
    }

    /**
     * Render the Trending Topics Research page.
     *
     * Includes the research template file.
     *
     * @return void
     */
    public function render_research_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
    }

    /**
     * Render the Authors management page.
     *
     * Includes the authors template file.
     *
     * @return void
     */
    public function render_authors_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
    }

    /**
     * Render the Activity page.
     *
     * Includes the activity template file.
     *
     * @return void
     */
    public function render_activity_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/activity.php';
    }

    /*
     * Render the Article Structures page.
     *
     * Fetches structures and sections from repositories and passes them to the template.
     *
     * @return void
     */
    public function render_structures_page() {
        $structure_repo = new AIPS_Article_Structure_Repository();
        $section_repo = new AIPS_Prompt_Section_Repository();

        $structures = $structure_repo->get_all(false);
        $sections = $section_repo->get_all(false);

        include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
    }

    /**
     * Render the Prompt Sections page.
     *
     * Fetches prompt sections and passes them to the template.
     *
     * @return void
     */
    public function render_prompt_sections_page() {
        $section_repo = new AIPS_Prompt_Section_Repository();
        $sections = $section_repo->get_all(false);

        include AIPS_PLUGIN_DIR . 'templates/admin/sections.php';
    }

    /**
     * Render the History page.
     *
     * Delegates rendering to the AIPS_History class.
     *
     * @return void
     */
    public function render_history_page() {
        $history_handler = new AIPS_History();
        $history_handler->render_page();
    }

    /**
     * Render the Settings page.
     *
     * Includes the settings template file.
     *
     * @return void
     */
    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render the Seeder page.
     *
     * Includes the seeder template file.
     *
     * @return void
     */
    public function render_seeder_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
    }

    /**
     * Render the System Status page.
     *
     * Delegates rendering to the AIPS_System_Status class.
     *
     * @return void
     */
    public function render_status_page() {
        $status_handler = new AIPS_System_Status();
        $status_handler->render_page();
    }

    /**
     * Render the Dev Tools page.
     *
     * Delegates rendering to the AIPS_Dev_Tools class.
     *
     * @return void
     */
    public function render_dev_tools_page() {
        $dev_tools = new AIPS_Dev_Tools();
        $dev_tools->render_page();
    }
}
