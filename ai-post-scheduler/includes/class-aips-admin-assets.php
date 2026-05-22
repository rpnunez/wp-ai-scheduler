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
	private const PAGE_POST_SLICES = 'aips-post-slices';
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
            if ($this->is_native_post_admin_page($hook)) {
                $this->enqueue_history_modal_opener_assets();
            }
			return;
		}

		$this->enqueue_global_assets();

        if ($this->hook_contains($hook, self::HOOK_DASHBOARD) || self::PAGE_DASHBOARD === $page) {
			$this->enqueue_dashboard_assets();
		}

        if (self::PAGE_AUTHORS === $page || self::PAGE_AUTHOR_TOPICS === $page || $this->hook_contains($hook, self::PAGE_AUTHORS) || $this->hook_contains($hook, self::PAGE_AUTHOR_TOPICS)) {
			$this->enqueue_authors_assets($hook);
		}

        if (self::PAGE_POST_SLICES === $page || $this->hook_contains($hook, self::PAGE_POST_SLICES)) {
			$this->enqueue_post_slices_assets();
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

        wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', array(
            'closeLabel'               => __('Close notification', 'ai-post-scheduler'),
            'fieldRequired'            => __('%s is required.', 'ai-post-scheduler'),
            // Progress-bar modal strings (used by AIPS.Utilities.showProgressBar on every admin page)
            'estimatedTimeRemaining'   => __('Estimated time remaining: %s', 'ai-post-scheduler'),
            'generationComplete'       => __('Generation complete!', 'ai-post-scheduler'),
            'takingLonger'             => __('Taking a little bit longer than expected\u2026', 'ai-post-scheduler'),
            'seconds'                  => __('seconds', 'ai-post-scheduler'),
            'minute'                   => __('1 minute', 'ai-post-scheduler'),
            'minutes'                  => __('%d minutes', 'ai-post-scheduler'),
            'minutesSeconds'           => __('%dm %ds', 'ai-post-scheduler'),
        ));

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

        $this->enqueue_history_modal_opener_script();

        // Shared strings needed on every plugin admin page.
        wp_localize_script('aips-admin-script', 'aipsAdminL10n', array(
            // Generic error/status strings used across multiple pages
            'errorOccurred'       => __('An error occurred.', 'ai-post-scheduler'),
            'errorTryAgain'       => __('An error occurred. Please try again.', 'ai-post-scheduler'),
            // Confirm dialog button labels (used by voices, schedules, structures, sections)
            'confirmCancelButton' => __('No, cancel', 'ai-post-scheduler'),
            'confirmDeleteButton' => __('Yes, delete', 'ai-post-scheduler'),
            // Common button loading states
            'saving'              => __('Saving...', 'ai-post-scheduler'),
            'generating'          => __('Generating...', 'ai-post-scheduler'),
            'generationFailed'    => __('Generation failed.', 'ai-post-scheduler'),
            // Status/badge labels used on multiple list pages
            'activeLabel'         => __('Active', 'ai-post-scheduler'),
            'inactiveLabel'       => __('Inactive', 'ai-post-scheduler'),
            'defaultLabel'        => __('Default', 'ai-post-scheduler'),
            // Voice dropdown placeholder — referenced on both Voices and Templates pages
            'noVoiceDefault'      => __('No Voice (Use Default)', 'ai-post-scheduler'),
            // "None" placeholder for the *template* wizard summary (schedule wizard uses
            // aipsScheduleL10n.noneOption to keep schedule-page strings self-contained)
            'noneOption'          => __('None', 'ai-post-scheduler'),
        ));
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

        wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', array(
            'closeLabel'               => __('Close notification', 'ai-post-scheduler'),
            'fieldRequired'            => __('%s is required.', 'ai-post-scheduler'),
            'estimatedTimeRemaining'   => __('Estimated time remaining: %s', 'ai-post-scheduler'),
            'generationComplete'       => __('Generation complete!', 'ai-post-scheduler'),
            'takingLonger'             => __('Taking a little bit longer than expected\u2026', 'ai-post-scheduler'),
            'seconds'                  => __('seconds', 'ai-post-scheduler'),
            'minute'                   => __('1 minute', 'ai-post-scheduler'),
            'minutes'                  => __('%d minutes', 'ai-post-scheduler'),
            'minutesSeconds'           => __('%dm %ds', 'ai-post-scheduler'),
        ));

        $this->enqueue_history_modal_opener_script();
    }

    /**
     * Enqueue/localize the History modal opener script.
     *
     * @return void
     */
    private function enqueue_history_modal_opener_script() {
        wp_enqueue_script(
            'aips-history-modal-opener',
            AIPS_PLUGIN_URL . 'assets/js/admin-history-modal-opener.js',
            array('jquery', 'aips-utilities-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-history-modal-opener', 'aipsHistoryModalAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aips_ajax_nonce'),
        ));

        wp_localize_script('aips-history-modal-opener', 'aipsHistoryModalOpenerL10n', array(
            'historyDetails'  => __('History Details', 'ai-post-scheduler'),
            'closeModal'      => __('Close modal', 'ai-post-scheduler'),
            'loading'         => __('Loading…', 'ai-post-scheduler'),
            'showDetails'     => __('Show details', 'ai-post-scheduler'),
            'hideDetails'     => __('Hide details', 'ai-post-scheduler'),
            'copy'            => __('Copy', 'ai-post-scheduler'),
            'copied'          => __('Copied!', 'ai-post-scheduler'),
            'invalidHistoryId' => __('Invalid history ID.', 'ai-post-scheduler'),
            'loadingFailed'   => __('Failed to load history modal.', 'ai-post-scheduler'),
            'loadingError'    => __('Error loading history modal.', 'ai-post-scheduler'),
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
     * The scaffold is an empty shell; AJAX populates #aips-history-modal-content
     * when a user triggers a modal open. Rendering server-side keeps the structure
     * consistent with the plugin's other modal partials and avoids JS string
     * concatenation.
     *
     * @return void
     */
    public function render_history_modal_scaffold() {
        ?>
        <div id="aips-history-modal" class="aips-modal" style="display: none;" aria-hidden="true">
            <div class="aips-modal-content aips-modal-large">
                <div class="aips-modal-header">
                    <h3 id="aips-history-modal-title"><?php esc_html_e('History Details', 'ai-post-scheduler'); ?></h3>
                    <button type="button" class="aips-modal-close" aria-label="<?php esc_attr_e('Close modal', 'ai-post-scheduler'); ?>">&times;</button>
                </div>
                <div class="aips-modal-body" id="aips-history-modal-content"></div>
            </div>
        </div>
        <?php
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
            'confirmGeneratePosts' => __('Generate posts for this author now?', 'ai-post-scheduler'),
            'confirmGeneratePost' => __('Generate a post from this topic now?', 'ai-post-scheduler'),
            'generatePostsModalTitle' => __('Generate Posts', 'ai-post-scheduler'),
            'generatePostsModalMessage' => __('How many posts would you like to generate for this author?', 'ai-post-scheduler'),
            'numberOfPostsLabel' => __('Number of Posts to Generate', 'ai-post-scheduler'),
            'generateButtonLabel' => __('Generate', 'ai-post-scheduler'),
            'invalidQuantityError' => __('Please enter a valid quantity between 1 and 10.', 'ai-post-scheduler'),
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
            'errorGeneratingPosts' => __('Error generating posts.', 'ai-post-scheduler'),
            'errorLoadingTopics' => __('Error loading topics.', 'ai-post-scheduler'),
            'errorApproving' => __('Error approving topic.', 'ai-post-scheduler'),
            'errorRejecting' => __('Error rejecting topic.', 'ai-post-scheduler'),
            'errorDeletingTopic' => __('Error deleting topic.', 'ai-post-scheduler'),
            'errorSavingTopic' => __('Error saving topic.', 'ai-post-scheduler'),
            'errorGeneratingPost' => __('Error generating post.', 'ai-post-scheduler'),
            'loadingTopics' => __('Loading topics...', 'ai-post-scheduler'),
            'noTopicsFound' => __('No topics found.', 'ai-post-scheduler'),
            'topicTitle' => __('Topic Title', 'ai-post-scheduler'),
            'topicDetails' => __('Topic Details', 'ai-post-scheduler'),
            'generatedAt' => __('Date Topic Generated', 'ai-post-scheduler'),
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
            'editPost' => __('Edit Post', 'ai-post-scheduler'),
            'viewPost' => __('View Post', 'ai-post-scheduler'),
            'publishPost' => __('Publish', 'ai-post-scheduler'),
            'publishing' => __('Publishing...', 'ai-post-scheduler'),
            'postPublished' => __('Post published successfully.', 'ai-post-scheduler'),
            'errorPublishingPost' => __('Error publishing post.', 'ai-post-scheduler'),
            'confirmPublishPost' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
            'noFeaturedImage' => __('No featured image', 'ai-post-scheduler'),
            'topic' => __('Topic', 'ai-post-scheduler'),
            'action' => __('Action', 'ai-post-scheduler'),
            'reason' => __('Reason', 'ai-post-scheduler'),
            'user' => __('User', 'ai-post-scheduler'),
            'date' => __('Date', 'ai-post-scheduler'),
            'approveTopicTitle' => __('Approve Topic', 'ai-post-scheduler'),
            'rejectTopicTitle' => __('Reject Topic', 'ai-post-scheduler'),
            'approveReasonPlaceholder' => __('Why are you approving this topic? (optional)', 'ai-post-scheduler'),
            'rejectReasonPlaceholder' => __('Why are you rejecting this topic? (optional)', 'ai-post-scheduler'),
            'approvalCategoryLabel' => __('Approval Reason', 'ai-post-scheduler'),
            'rejectionCategoryLabel' => __('Rejection Reason', 'ai-post-scheduler'),
            'approvalCategoryDescription' => __('Select a positive reason to help train future topic generation.', 'ai-post-scheduler'),
            'rejectionCategoryDescription' => __('Select a structured reason to improve future topic quality.', 'ai-post-scheduler'),
            'approvalCategories' => array(
                array( 'value' => 'other',           'label' => __('Other', 'ai-post-scheduler') ),
                array( 'value' => 'timely',          'label' => __('Timely / On-trend', 'ai-post-scheduler') ),
                array( 'value' => 'relevant',        'label' => __('Relevant to niche', 'ai-post-scheduler') ),
                array( 'value' => 'well_researched', 'label' => __('Well-researched angle', 'ai-post-scheduler') ),
                array( 'value' => 'engaging',        'label' => __('Engaging hook', 'ai-post-scheduler') ),
                array( 'value' => 'original',        'label' => __('Original perspective', 'ai-post-scheduler') ),
            ),
            'rejectionCategories' => array(
                array( 'value' => 'other',      'label' => __('Other', 'ai-post-scheduler') ),
                array( 'value' => 'duplicate',  'label' => __('Duplicate', 'ai-post-scheduler') ),
                array( 'value' => 'tone',       'label' => __('Tone', 'ai-post-scheduler') ),
                array( 'value' => 'irrelevant', 'label' => __('Irrelevant', 'ai-post-scheduler') ),
                array( 'value' => 'policy',     'label' => __('Policy', 'ai-post-scheduler') ),
            ),
            // Generation Queue strings
            'loadingQueue' => __('Loading queue...', 'ai-post-scheduler'),
            'errorLoadingQueue' => __('Error loading queue.', 'ai-post-scheduler'),
            'noQueueTopics' => __('No approved topics in the queue yet.', 'ai-post-scheduler'),
            'author' => __('Author', 'ai-post-scheduler'),
            'fieldNiche' => __('Field/Niche', 'ai-post-scheduler'),
            'approvedDate' => __('Approved Date', 'ai-post-scheduler'),
            'notAvailable' => __('N/A', 'ai-post-scheduler'),
            'selectBulkAction' => __('Please select a bulk action.', 'ai-post-scheduler'),
            'noTopicsSelected' => __('Please select at least one topic.', 'ai-post-scheduler'),
            'comingSoon' => __('This feature is coming soon.', 'ai-post-scheduler'),
            'invalidAction' => __('Invalid action.', 'ai-post-scheduler'),
            'confirmGenerateFromQueue' => __('Generate posts now for %d selected topic(s)?', 'ai-post-scheduler'),
            'postsGenerated' => __('Posts generated successfully.', 'ai-post-scheduler'),
            'execute' => __('Execute', 'ai-post-scheduler'),
            // Log Viewer strings
            'logViewerTitle' => __('Topic History Log', 'ai-post-scheduler'),
            'logViewerLoading' => __('Loading logs...', 'ai-post-scheduler'),
            'logViewerError' => __('Error loading logs.', 'ai-post-scheduler'),
            'noLogsFound' => __('No history found for this topic.', 'ai-post-scheduler'),
            'logAction' => __('Action', 'ai-post-scheduler'),
            'logUser' => __('User', 'ai-post-scheduler'),
            'logDate' => __('Date', 'ai-post-scheduler'),
            'logDetails' => __('Details', 'ai-post-scheduler'),
            // Action strings
            'processing' => __('Processing...', 'ai-post-scheduler'),
            'approveWithFeedback' => __('Approve with Feedback', 'ai-post-scheduler'),
            'rejectWithFeedback' => __('Reject with Feedback', 'ai-post-scheduler'),
            // Bulk generate
            'generateNow' => __('Generate Now', 'ai-post-scheduler'),
            'confirmBulkGenerate' => __('Are you sure you want to generate posts for %d topics?', 'ai-post-scheduler'),
            'noFeedbackSelected' => __('Please select at least one feedback item.', 'ai-post-scheduler'),
            'errorBulkAction' => __('Error executing bulk action.', 'ai-post-scheduler'),
            // Progress bar modal strings (page-specific content; time-display strings are in aipsUtilitiesL10n)
            'generatingPostsTitle' => __('Generating Posts', 'ai-post-scheduler'),
            'generatingPostsMessage' => __('Please wait while your posts are being generated. This may take a few minutes.', 'ai-post-scheduler'),
            'generationCompletePartial' => __('%d post(s) generated, %d failed.', 'ai-post-scheduler'),
            // Feedback history and duplicate detection
            'similarSuggestions' => __('Similar Suggestions', 'ai-post-scheduler'),
            'similarityLabel' => __('Similarity', 'ai-post-scheduler'),
            'lastFeedback' => __('Last Feedback', 'ai-post-scheduler'),
            'previouslyApproved' => __('Previously Approved', 'ai-post-scheduler'),
            'previouslyRejected' => __('Previously Rejected', 'ai-post-scheduler'),
            'potentialDuplicate' => __('Potential Duplicate', 'ai-post-scheduler'),
            // Topic count display strings for the filter bar result count
            'topicCountSingular' => __('topic', 'ai-post-scheduler'),
            'topicCountPlural' => __('topics', 'ai-post-scheduler'),
            // Author Suggestions
            'suggestAuthors' => __('Suggest Authors', 'ai-post-scheduler'),
            'suggestAuthorsTitle' => __('Suggest Authors with AI', 'ai-post-scheduler'),
            'siteNicheRequired' => __('Site niche is required.', 'ai-post-scheduler'),
            'generateSuggestions' => __('Generate Suggestions', 'ai-post-scheduler'),
            'generatingSuggestions' => __('Generating suggestions...', 'ai-post-scheduler'),
            'errorGeneratingSuggestions' => __('Error generating author suggestions.', 'ai-post-scheduler'),
            'importAuthor' => __('Import Author', 'ai-post-scheduler'),
            'importedAuthor' => __('Imported Author', 'ai-post-scheduler'),
            'importingAuthor' => __('Importing...', 'ai-post-scheduler'),
            'authorImported' => __('Author imported successfully.', 'ai-post-scheduler'),
            'errorImportingAuthor' => __('Error importing author.', 'ai-post-scheduler'),
            'voiceToneLabel' => __('Voice/Tone', 'ai-post-scheduler'),
            'writingStyleLabel' => __('Writing Style', 'ai-post-scheduler'),
            'topicPromptLabel' => __('Topic Generation Prompt', 'ai-post-scheduler'),
            'viewDetails' => __('View Details', 'ai-post-scheduler'),
            'noFeedbackYet' => __('No feedback yet.', 'ai-post-scheduler'),
            // Date formatting strings used by formatTopicDate()
            'dateToday'     => __('Today', 'ai-post-scheduler'),
            'dateYesterday' => __('Yesterday', 'ai-post-scheduler'),
            'dateAM'        => _x('am', 'time of day', 'ai-post-scheduler'),
            'datePM'        => _x('pm', 'time of day', 'ai-post-scheduler'),
            'dateMonthNames' => array(
                __('January', 'ai-post-scheduler'),
                __('February', 'ai-post-scheduler'),
                __('March', 'ai-post-scheduler'),
                __('April', 'ai-post-scheduler'),
                __('May', 'ai-post-scheduler'),
                __('June', 'ai-post-scheduler'),
                __('July', 'ai-post-scheduler'),
                __('August', 'ai-post-scheduler'),
                __('September', 'ai-post-scheduler'),
                __('October', 'ai-post-scheduler'),
                __('November', 'ai-post-scheduler'),
                __('December', 'ai-post-scheduler'),
            ),
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

          wp_localize_script('aips-admin-embeddings', 'aipsEmbeddingsL10n', array(
              'nonce'        => wp_create_nonce('aips_compute_topic_embeddings'),
              'queueing'     => __('Queueing embedding jobs...', 'ai-post-scheduler'),
              'queued'       => __('Embedding jobs queued. Processing will run in the background.', 'ai-post-scheduler'),
              'error'        => __('Failed to queue embedding jobs.', 'ai-post-scheduler'),
              'networkError' => __('Network error: Failed to queue embedding jobs.', 'ai-post-scheduler'),
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
                array('jquery', 'aips-admin-script', 'aips-utilities-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-post-slices', 'aipsPostSlicesL10n', array(
                'addNewSlice'   => __('Add New Post Slice', 'ai-post-scheduler'),
                'editSlice'     => __('Edit Post Slice', 'ai-post-scheduler'),
                'saveSlice'     => __('Save Post Slice', 'ai-post-scheduler'),
                'saving'        => __('Saving...', 'ai-post-scheduler'),
                'deleteConfirm' => __('Are you sure you want to delete this post slice?', 'ai-post-scheduler'),
                'deleteFailed'  => __('Failed to delete post slice.', 'ai-post-scheduler'),
                'saveFailed'    => __('Failed to save post slice.', 'ai-post-scheduler'),
                'toggleFailed'  => __('Failed to update post slice status.', 'ai-post-scheduler'),
                'nameRequired'  => __('A post slice name is required.', 'ai-post-scheduler'),
                'noSlicesFound' => __('No post slices match your search criteria.', 'ai-post-scheduler'),
                'clearSearch'   => __('Clear Search', 'ai-post-scheduler'),
                'activate'      => __('Activate', 'ai-post-scheduler'),
                'deactivate'    => __('Deactivate', 'ai-post-scheduler'),
                'active'        => __('Active', 'ai-post-scheduler'),
                'inactive'      => __('Inactive', 'ai-post-scheduler'),
            ));
    }

    /**
     * Enqueue assets for the templates page.
     */
    private function enqueue_templates_assets() {
            wp_localize_script('aips-admin-script', 'aipsTemplatesL10n', array(
                // Template wizard validation
                'templateNameRequired'    => __('Template Name is required.', 'ai-post-scheduler'),
                'contentPromptRequired'   => __('Content Prompt is required.', 'ai-post-scheduler'),
                // Draft save action
                'draftSaved'              => __('Draft saved successfully.', 'ai-post-scheduler'),
                'saveDraft'               => __('Save Draft', 'ai-post-scheduler'),
                // Template wizard summary panel
                'autoGenerateFromContent' => __('Auto-generate from content', 'ai-post-scheduler'),
                /* translators: No is displayed when featured image generation is disabled */
                'featuredImageNo'         => __('No', 'ai-post-scheduler'),
                /* translators: %s: featured image source name */
                'featuredImageYes'        => __('Yes (%s)', 'ai-post-scheduler'),
                // AI variable tag tooltip
                'clickToCopy'             => __('Click to copy', 'ai-post-scheduler'),
                // Template content preview
                'exampleTopic'            => __('Example Topic', 'ai-post-scheduler'),
                'failedToGeneratePreview' => __('Failed to generate preview. Please check that all required fields are filled.', 'ai-post-scheduler'),
                'previewNetworkError'     => __('An error occurred while generating the preview. Please check your network connection and try again.', 'ai-post-scheduler'),
            ));
    }

    /**
     * Enqueue assets for the voices page.
     */
    private function enqueue_voices_assets() {
            wp_localize_script('aips-admin-script', 'aipsVoicesL10n', array(
                'addNewVoice'        => __('Add New Voice', 'ai-post-scheduler'),
                'editVoice'          => __('Edit Voice', 'ai-post-scheduler'),
                'saveVoice'          => __('Save Voice', 'ai-post-scheduler'),
                'deleteVoiceConfirm' => __('Are you sure you want to delete this voice?', 'ai-post-scheduler'),
            ));
    }

    /**
     * Enqueue assets for the structures page.
     */
    private function enqueue_structures_assets() {
            wp_localize_script('aips-admin-script', 'aipsStructuresL10n', array(
                // Article structure CRUD
                'deleteStructureConfirm' => __('Are you sure you want to delete this structure?', 'ai-post-scheduler'),
                'saveStructureFailed'    => __('Failed to save structure.', 'ai-post-scheduler'),
                'loadStructureFailed'    => __('Failed to load structure.', 'ai-post-scheduler'),
                'deleteStructureFailed'  => __('Failed to delete structure.', 'ai-post-scheduler'),
                // Prompt section CRUD
                'deleteSectionConfirm'   => __('Are you sure you want to delete this prompt section?', 'ai-post-scheduler'),
                'saveSectionFailed'      => __('Failed to save prompt section.', 'ai-post-scheduler'),
                'loadSectionFailed'      => __('Failed to load prompt section.', 'ai-post-scheduler'),
                'deleteSectionFailed'    => __('Failed to delete prompt section.', 'ai-post-scheduler'),
            ));
    }

    /**
     * Enqueue assets for the schedule page.
     * @param string $hook The current admin page hook.
     */
    private function enqueue_schedule_assets($hook) {
            wp_localize_script('aips-admin-script', 'aipsScheduleL10n', array(
                // Run schedule
                'runScheduleConfirm'             => __('Are you sure you want to run this schedule now? This will immediately generate posts.', 'ai-post-scheduler'),
                'scheduleRunning'                => __('Running...', 'ai-post-scheduler'),
                // Schedule wizard
                'scheduleTemplateRequired'       => __('Please select a Template to continue.', 'ai-post-scheduler'),
                'addNewSchedule'                 => __('Add New Schedule', 'ai-post-scheduler'),
                'editSchedule'                   => __('Edit Schedule', 'ai-post-scheduler'),
                'cloneSchedule'                  => __('Clone Schedule', 'ai-post-scheduler'),
                'saveSchedule'                   => __('Save Schedule', 'ai-post-scheduler'),
                'scheduleSavedSuccess'           => __('Schedule saved successfully.', 'ai-post-scheduler'),
                // Schedule wizard summary display
                'startNow'                       => __('Now', 'ai-post-scheduler'),
                'useDefault'                     => __('Use Default', 'ai-post-scheduler'),
                'noTitle'                        => __('No title', 'ai-post-scheduler'),
                // "None" placeholder used in schedule wizard summary; intentionally kept
                // here (not inherited from global aipsAdminL10n) so this page's strings
                // are fully self-contained.
                'noneOption'                     => __('None', 'ai-post-scheduler'),
                'yes'                            => __('Yes', 'ai-post-scheduler'),
                'no'                             => __('No', 'ai-post-scheduler'),
                // Buttons/links
                'runNow'                         => __('Run Now', 'ai-post-scheduler'),
                'cancel'                         => __('Cancel', 'ai-post-scheduler'),
                'yesRunNow'                      => __('Yes, run now', 'ai-post-scheduler'),
                // Single schedule delete
                'deleteScheduleConfirm'          => __('Are you sure you want to delete this schedule?', 'ai-post-scheduler'),
                // Bulk schedule selection/delete
                'selectAtLeastOneSchedule'       => __('Please select at least one schedule.', 'ai-post-scheduler'),
                'deleteOneScheduleConfirm'       => __('Are you sure you want to delete 1 schedule?', 'ai-post-scheduler'),
                /* translators: %d: number of schedules to delete */
                'deleteMultipleSchedulesConfirm' => __('Are you sure you want to delete %d schedules?', 'ai-post-scheduler'),
                // Unified schedule bulk-action validation
                'selectBulkAction'               => __('Please select a bulk action.', 'ai-post-scheduler'),
                'selectAtLeastOne'               => __('Please select at least one schedule.', 'ai-post-scheduler'),
                // Schedule error toasts
                'failedToLoadHistory'            => __('Failed to load history.', 'ai-post-scheduler'),
                'failedToDeleteSchedules'        => __('Failed to delete schedules.', 'ai-post-scheduler'),
                'bulkRunFailed'                  => __('Bulk run failed.', 'ai-post-scheduler'),
                // Bulk run-now confirm dialog
                'runSchedulesNow'                => __('Run Schedules Now', 'ai-post-scheduler'),
                'runPostsConfirmSingular'        => __('This will generate an estimated 1 post. Are you sure?', 'ai-post-scheduler'),
                /* translators: %d: estimated number of posts to generate */
                'runPostsConfirmPlural'          => __('This will generate an estimated %d posts. Are you sure?', 'ai-post-scheduler'),
                'runOneScheduleConfirm'          => __('This will run 1 schedule. Are you sure?', 'ai-post-scheduler'),
                /* translators: %d: number of schedules to run */
                'runMultipleSchedulesConfirm'    => __('This will run %d schedules. Are you sure?', 'ai-post-scheduler'),
                // Unified schedule bulk delete dialog
                'deleteSchedulesHeading'         => __('Delete Schedules', 'ai-post-scheduler'),
                'noDeletableSchedulesSelected'   => __('None of the selected schedules can be deleted.', 'ai-post-scheduler'),
                'deleteSchedulesListIntro'       => __('The following schedules will be deleted:', 'ai-post-scheduler'),
                'deleteSchedulesFinalConfirm'    => __('This action cannot be undone. Continue?', 'ai-post-scheduler'),
                /* translators: %d: number of selected schedules that are not deletable */
                'deleteSchedulesSkipNotice'      => __('%d selected schedule(s) cannot be deleted and will be skipped.', 'ai-post-scheduler'),
                // Status strip
                'scheduleStatusLoadFailed'       => __('Unable to load schedule status.', 'ai-post-scheduler'),
                'queueDepthLabel'                => __('Queue depth:', 'ai-post-scheduler'),
                'bulkPendingLabel'               => __('Bulk pending:', 'ai-post-scheduler'),
                'bulkFailedLabel'                => __('Bulk failed:', 'ai-post-scheduler'),
                'activeSchedulesLabel'           => __('Active schedules', 'ai-post-scheduler'),
                'upcomingSchedulesLabel'         => __('Upcoming in next 24h', 'ai-post-scheduler'),
                'overdueSchedulesLabel'          => __('Overdue schedules', 'ai-post-scheduler'),
                'noQueueEventsNext24h'           => __('No queue events in next 24h.', 'ai-post-scheduler'),
                'noScheduleRunsNext24h'          => __('No schedule runs in next 24h.', 'ai-post-scheduler'),
                'typeTemplateLabel'              => __('Post Generation', 'ai-post-scheduler'),
                'typeAuthorTopicLabel'           => __('Author Topics', 'ai-post-scheduler'),
                'typeAuthorPostLabel'            => __('Author Posts', 'ai-post-scheduler'),
                'lastErrorDetected'              => __('Last error detected in bulk jobs.', 'ai-post-scheduler'),
                'retryPending'                   => __('Retry jobs are pending.', 'ai-post-scheduler'),
                /* translators: %d: number of overdue schedules */
                'overdueSchedulesWarning'        => __('%d schedule(s) are overdue.', 'ai-post-scheduler'),
                'viewHistory'                    => __('View history', 'ai-post-scheduler'),
                'systemStatus'                   => __('System status', 'ai-post-scheduler'),
                'notifications'                  => __('Notifications', 'ai-post-scheduler'),
                'telemetry'                      => __('Telemetry', 'ai-post-scheduler'),
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

          wp_localize_script('aips-admin-research', 'aipsResearchL10n', array(
              'topicsSaved' => __('topics saved for', 'ai-post-scheduler'),
              'topTopics' => __('Top 5 Topics:', 'ai-post-scheduler'),
              'noTopicsFound' => __('No topics match your search criteria.', 'ai-post-scheduler'),
              'noTopicsFoundTitle' => __('No Topics Found', 'ai-post-scheduler'),
              'clearFilters' => __('Clear Filters', 'ai-post-scheduler'),
              'libraryEmpty' => __('Your research library is empty.', 'ai-post-scheduler'),
              'startResearch' => __('Start Research', 'ai-post-scheduler'),
              'clearSearch' => __('Clear Search', 'ai-post-scheduler'),
              'deleteTopicConfirm' => __('Delete this topic?', 'ai-post-scheduler'),
              'selectTopicSchedule' => __('Please select at least one topic to schedule.', 'ai-post-scheduler'),
              'researchError' => __('An error occurred during research.', 'ai-post-scheduler'),
              'schedulingError' => __('An error occurred during scheduling.', 'ai-post-scheduler'),
              'delete' => __('Delete', 'ai-post-scheduler'),
              'generateSelected' => __('Generate', 'ai-post-scheduler'),
              'generateIdeas' => __('Generate Ideas', 'ai-post-scheduler'),
              'generatingIdeas' => __('Generating...', 'ai-post-scheduler'),
              'generatingPostsTitle' => __('Generating Posts', 'ai-post-scheduler'),
              'generatingPostsMessage' => __('Please wait while your posts are being generated. This may take a few minutes.', 'ai-post-scheduler'),
              'generateError' => __('Error generating selected topics.', 'ai-post-scheduler'),
              'loadingPosts' => __('Loading posts...', 'ai-post-scheduler'),
              'errorLoadingPosts' => __('Error loading posts.', 'ai-post-scheduler'),
              'noPostsFound' => __('No posts found.', 'ai-post-scheduler'),
              'postsGeneratedFrom' => __('Posts Generated from Topic', 'ai-post-scheduler'),
              'editPost' => __('Edit Post', 'ai-post-scheduler'),
              'viewPost' => __('View Post', 'ai-post-scheduler'),
              'notPublished' => __('Not published', 'ai-post-scheduler'),
              'postId' => __('Post ID', 'ai-post-scheduler'),
              'postTitle' => __('Post Title', 'ai-post-scheduler'),
              'dateGenerated' => __('Date Generated', 'ai-post-scheduler'),
              'datePublished' => __('Date Published', 'ai-post-scheduler'),
              'actions' => __('Actions', 'ai-post-scheduler'),
              'statusNew' => __('New', 'ai-post-scheduler'),
              'statusScheduled' => __('Scheduled', 'ai-post-scheduler'),
              'statusGenerated' => __('Generated', 'ai-post-scheduler'),
              'confirmGenerationTitle' => __('Confirm Generation', 'ai-post-scheduler'),
              'confirmGenerationMessage' => __('Generate %d post(s) immediately from selected topics?', 'ai-post-scheduler'),
              'cancelButton' => __('Cancel', 'ai-post-scheduler'),
              'generateNowButton' => __('Generate Now', 'ai-post-scheduler'),
              'generatingButton' => __('Generating...', 'ai-post-scheduler'),
              'selectTemplateRequired' => __('Please select a template before generating.', 'ai-post-scheduler'),
          ));
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
            wp_localize_script('aips-admin-post-review', 'aipsPostReviewL10n', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
                'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
                'confirmBulkPublish' => __('Are you sure you want to publish %d selected post(s)?', 'ai-post-scheduler'),
                'confirmDelete' => __('Are you sure you want to delete this post? This action cannot be undone.', 'ai-post-scheduler'),
                'confirmBulkDelete' => __('Are you sure you want to delete %d selected post(s)? This action cannot be undone.', 'ai-post-scheduler'),
                'confirmRegenerate' => __('Are you sure you want to regenerate this post? The current post will be deleted.', 'ai-post-scheduler'),
                'confirmBulkRegenerate' => __('Are you sure you want to regenerate %d selected post(s)? The current posts will be deleted.', 'ai-post-scheduler'),
                'publishSuccess' => __('Post published successfully!', 'ai-post-scheduler'),
                'bulkPublishSuccess' => __('%d posts published successfully!', 'ai-post-scheduler'),
                'deleteSuccess' => __('Post deleted successfully!', 'ai-post-scheduler'),
                'bulkDeleteSuccess' => __('%d posts deleted successfully!', 'ai-post-scheduler'),
                'regenerateSuccess' => __('Post regeneration started!', 'ai-post-scheduler'),
                'bulkRegenerateSuccess' => __('%d posts regeneration started!', 'ai-post-scheduler'),
                'publishError' => __('Failed to publish post.', 'ai-post-scheduler'),
                'deleteError' => __('Failed to delete post.', 'ai-post-scheduler'),
                'regenerateError' => __('Failed to regenerate post.', 'ai-post-scheduler'),
                'loadingError' => __('Failed to load draft posts.', 'ai-post-scheduler'),
                'loading' => __('Loading...', 'ai-post-scheduler'),
                'publish' => __('Publish', 'ai-post-scheduler'),
                'deleting' => __('Deleting...', 'ai-post-scheduler'),
                'delete' => __('Delete', 'ai-post-scheduler'),
                'regenerating' => __('Regenerating...', 'ai-post-scheduler'),
                'regenerate' => __('Re-generate', 'ai-post-scheduler'),
                'noPostsSelected' => __('Please select at least one post.', 'ai-post-scheduler'),
                'noDraftPosts' => __('No Draft Posts', 'ai-post-scheduler'),
                'noDraftPostsDesc' => __('There are no draft posts waiting for review.', 'ai-post-scheduler'),
                'previewTitle' => __('Post Preview', 'ai-post-scheduler'),
                'loadingPreview' => __('Loading preview...', 'ai-post-scheduler'),
                'previewError' => __('Failed to load preview.', 'ai-post-scheduler'),
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

            wp_localize_script('aips-admin-ai-edit', 'aipsAIEditL10n', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
                'regenerate' => __('Re-generate', 'ai-post-scheduler'),
                'regenerating' => __('Regenerating...', 'ai-post-scheduler'),
                'regenerateAll' => __('Regenerate All', 'ai-post-scheduler'),
                'regeneratingAll' => __('Regenerating all components...', 'ai-post-scheduler'),
                'regenerateAllSuccess' => __('Components regenerated successfully.', 'ai-post-scheduler'),
                'regenerateAllError' => __('Failed to regenerate all components.', 'ai-post-scheduler'),
                'regenerateSuccess' => __('Component regenerated successfully!', 'ai-post-scheduler'),
                'regenerateError' => __('Failed to regenerate component.', 'ai-post-scheduler'),
                'save' => __('Save Changes', 'ai-post-scheduler'),
                'saving' => __('Saving...', 'ai-post-scheduler'),
                'saveSuccess' => __('Post updated successfully!', 'ai-post-scheduler'),
                'saveError' => __('Failed to update post.', 'ai-post-scheduler'),
                'loadError' => __('Failed to load post components.', 'ai-post-scheduler'),
                'confirmClose' => __('You have unsaved changes. Are you sure you want to close?', 'ai-post-scheduler'),
                'noChanges' => __('No changes to save.', 'ai-post-scheduler'),
                'revisionAiGenerated' => __('AI Generated', 'ai-post-scheduler'),
                'revisionManualEdit' => __('Manual Edit', 'ai-post-scheduler'),
                'revisionRestored' => __('Restored Version', 'ai-post-scheduler'),
                'revisionUnknown' => __('Revision', 'ai-post-scheduler'),
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

            wp_localize_script('aips-admin-history', 'aipsHistoryL10n', array(
                'loading'              => __('Loading…', 'ai-post-scheduler'),
                'reloading'            => __('Reloading…', 'ai-post-scheduler'),
                'loadingLogs'          => __('Loading logs…', 'ai-post-scheduler'),
                'historyDetailsTitle'  => __('History Details', 'ai-post-scheduler'),
                'historyContainerHeader' => __('History Container for ID: %d', 'ai-post-scheduler'),
                'errorLoading'         => __('Error loading logs.', 'ai-post-scheduler'),
                'errorReloading'       => __('Failed to reload history.', 'ai-post-scheduler'),
                'logsModalTitle'       => __('Logs — %s', 'ai-post-scheduler'),
                'logsHeading'          => __('Log Entries', 'ai-post-scheduler'),
                'noLogsFound'          => __('No log entries found for this container.', 'ai-post-scheduler'),
                'noResultsFound'       => __('No history containers match your current filters.', 'ai-post-scheduler'),
                'labelContainerId'     => __('Container ID', 'ai-post-scheduler'),
                'labelTitle'           => __('Title', 'ai-post-scheduler'),
                'labelTemplate'        => __('Template', 'ai-post-scheduler'),
                'labelStatus'          => __('Status', 'ai-post-scheduler'),
                'labelCreated'         => __('Created', 'ai-post-scheduler'),
                'labelCompleted'       => __('Completed', 'ai-post-scheduler'),
                'labelError'           => __('Error', 'ai-post-scheduler'),
                'labelPostId'          => __('Post', 'ai-post-scheduler'),
                'labelDuration'        => __('Duration', 'ai-post-scheduler'),
                'labelCreationMethod'  => __('Method', 'ai-post-scheduler'),
                'labelWhatHappened'    => __('What happened', 'ai-post-scheduler'),
                'labelOutcome'         => __('Outcome', 'ai-post-scheduler'),
                'labelRelatedEntities' => __('Related entities', 'ai-post-scheduler'),
                'labelWhatChanged'     => __('What changed', 'ai-post-scheduler'),
                'summaryHeading'       => __('Summary', 'ai-post-scheduler'),
                'labelAdvancedDetails' => __('Advanced details', 'ai-post-scheduler'),
                'summaryActionResearchRun' => __('Research run', 'ai-post-scheduler'),
                'summaryActionEmbeddings' => __('Embeddings processing', 'ai-post-scheduler'),
                'summaryActionAuthorTopics' => __('Author topic generation', 'ai-post-scheduler'),
                'summaryActionScheduledPosts' => __('Scheduled post generation', 'ai-post-scheduler'),
                'summaryActionPostGeneration' => __('Post generation', 'ai-post-scheduler'),
                'summaryActionAutomationTask' => __('Automation task', 'ai-post-scheduler'),
                'summaryOutcomeSuccess' => __('Success', 'ai-post-scheduler'),
                'summaryOutcomeFailed' => __('Failed', 'ai-post-scheduler'),
                'summaryOutcomeInProgress' => __('In progress', 'ai-post-scheduler'),
                'summaryEntityPost'    => __('Post', 'ai-post-scheduler'),
                'summaryEntityTemplate' => __('Template', 'ai-post-scheduler'),
                'summaryEntityPostId'  => __('Post ID', 'ai-post-scheduler'),
                'summaryEntityMethod'  => __('Method', 'ai-post-scheduler'),
                'summaryNoRelatedEntities' => __('No related entities detected', 'ai-post-scheduler'),
                'summaryChangedTitle'  => __('Title updated', 'ai-post-scheduler'),
                'summaryChangedContent' => __('Content updated', 'ai-post-scheduler'),
                'summaryChangedImage'  => __('Image generated/updated', 'ai-post-scheduler'),
                'summaryChangedPublished' => __('Published result', 'ai-post-scheduler'),
                'summaryChangedDraft'  => __('Draft result', 'ai-post-scheduler'),
                'summaryChangedError'  => __('Run ended with an error', 'ai-post-scheduler'),
                'summaryChangedNone'   => __('No major content changes detected', 'ai-post-scheduler'),
                'editPostLabel'        => __('Edit', 'ai-post-scheduler'),
                'filterAll'            => __('All', 'ai-post-scheduler'),
                'filterByType'         => __('Filter:', 'ai-post-scheduler'),
                'typeLabels'           => AIPS_History_Type::get_all_types(),
                'colTimestamp'         => __('Timestamp', 'ai-post-scheduler'),
                'colType'              => __('Type', 'ai-post-scheduler'),
                'colLogType'           => __('Log Type', 'ai-post-scheduler'),
                'colDetails'           => __('Details', 'ai-post-scheduler'),
                'showDetails'          => __('Show details', 'ai-post-scheduler'),
                'hideDetails'          => __('Hide details', 'ai-post-scheduler'),
                'copyDetails'          => __('Copy', 'ai-post-scheduler'),
                'copiedDetails'        => __('Copied!', 'ai-post-scheduler'),
                'confirmDelete'        => __('Delete this history container? This cannot be undone.', 'ai-post-scheduler'),
                'confirmBulkDelete'    => __('Delete the selected history containers? This cannot be undone.', 'ai-post-scheduler'),
                'confirmClearAll'      => __('Clear all history? This cannot be undone.', 'ai-post-scheduler'),
                'confirmClearStatus'   => __('Clear all history entries with this status? This cannot be undone.', 'ai-post-scheduler'),
                'confirmDeleteLabel'   => __('Yes, delete', 'ai-post-scheduler'),
                'confirmClearLabel'    => __('Yes, clear', 'ai-post-scheduler'),
                'cancelLabel'          => __('No, cancel', 'ai-post-scheduler'),
                'deletedSuccess'       => __('Items deleted successfully.', 'ai-post-scheduler'),
                'clearedSuccess'       => __('History cleared successfully.', 'ai-post-scheduler'),
                'errorDeleting'        => __('Error deleting items.', 'ai-post-scheduler'),
                'errorClearing'        => __('Error clearing history.', 'ai-post-scheduler'),
                'deleting'             => __('Deleting…', 'ai-post-scheduler'),
                'retrying'             => __('Retrying…', 'ai-post-scheduler'),
                'errorRetrying'        => __('An error occurred. Please try again.', 'ai-post-scheduler'),
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

            wp_localize_script('aips-admin-onboarding', 'aipsOnboardingL10n', array(
                'confirmSkipOnboarding' => __('Skip the Onboarding Wizard? You can restart it later from System Status.', 'ai-post-scheduler'),
            ));
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

            wp_localize_script('aips-admin-taxonomy', 'aipsTaxonomyL10n', array(
                'nonce'                  => wp_create_nonce('aips_ajax_nonce'),
                'selectTaxonomyType'     => __('Please select a taxonomy type.', 'ai-post-scheduler'),
                'selectPost'             => __('Please select at least one post.', 'ai-post-scheduler'),
                'selectAction'           => __('Please select an action.', 'ai-post-scheduler'),
                'selectItem'             => __('Please select at least one item.', 'ai-post-scheduler'),
                'confirmBulkAction'      => __('Are you sure you want to %s %d items?', 'ai-post-scheduler'),
                'confirmDelete'          => __('Are you sure you want to delete this item?', 'ai-post-scheduler'),
                'confirmCreateTerm'      => __('Create this term in WordPress?', 'ai-post-scheduler'),
                'generating'             => __('Generating...', 'ai-post-scheduler'),
                'generate'               => __('Generate', 'ai-post-scheduler'),
                'actionFailed'           => __('Action failed.', 'ai-post-scheduler'),
                'generationFailed'       => __('Generation failed.', 'ai-post-scheduler'),
                'deleteFailed'           => __('Delete failed.', 'ai-post-scheduler'),
                'termCreationFailed'     => __('Term creation failed.', 'ai-post-scheduler'),
                'updateFailed'           => __('Update failed.', 'ai-post-scheduler'),
                'item'                   => __('item', 'ai-post-scheduler'),
                'items'                  => __('items', 'ai-post-scheduler'),
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

            wp_localize_script('aips-admin-sources', 'aipsSourcesL10n', array(
                'addNewSource'      => __('Add New Source', 'ai-post-scheduler'),
                'editSource'        => __('Edit Source', 'ai-post-scheduler'),
                'saveSource'        => __('Save Source', 'ai-post-scheduler'),
                'saving'            => __('Saving…', 'ai-post-scheduler'),
                'deleteConfirm'     => __('Are you sure you want to delete this source?', 'ai-post-scheduler'),
                'saveFailed'        => __('Failed to save source.', 'ai-post-scheduler'),
                'deleteFailed'      => __('Failed to delete source.', 'ai-post-scheduler'),
                'toggleFailed'      => __('Failed to update source status.', 'ai-post-scheduler'),
                'urlRequired'       => __('A URL is required.', 'ai-post-scheduler'),
                'groupNameRequired' => __('Please enter a group name.', 'ai-post-scheduler'),
                'deleteGroupConfirm' => __('Delete this Source Group? Sources in this group will not be deleted.', 'ai-post-scheduler'),
            ));
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
            wp_localize_script('aips-admin-system-status', 'aipsSystemStatusL10n', array(
                'nonce'                                 => wp_create_nonce('aips_reset_circuit_breaker'),
                'nonceCronReschedule'                   => wp_create_nonce('aips_status_reschedule_missed_cron'),
                'nonceRetrySlices'                      => wp_create_nonce('aips_status_retry_failed_slices'),
                'nonceClearPartialGenerations'          => wp_create_nonce('aips_status_clear_partial_generations'),
                'nonceCleanupStaleJobsCache'            => wp_create_nonce('aips_status_cleanup_stale_jobs_cache'),
                'nonceRebuildCaches'                  => wp_create_nonce('aips_rebuild_caches'),
                'hideDetails'                           => __('Hide Details', 'ai-post-scheduler'),
                'showDetails'                           => __('Show Details', 'ai-post-scheduler'),
                'resetSuccess'                          => __('Circuit reset. Reload the page to confirm.', 'ai-post-scheduler'),
                'resetFailed'                           => __('Reset failed.', 'ai-post-scheduler'),
                'requestFailed'                         => __('Request failed. Please try again.', 'ai-post-scheduler'),
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

        wp_localize_script('aips-dashboard-script', 'aipsDashboardL10n', array(
            'chartPostsTitle'      => __('Post Generations by Day', 'ai-post-scheduler'),
            'chartTopicsTitle'     => __('Topic Generations by Day', 'ai-post-scheduler'),
            'chartErrorRateTitle'  => __('AI Error Rate (%)', 'ai-post-scheduler'),
            'chartCompletedLabel'  => __('Completed', 'ai-post-scheduler'),
            'chartFailedLabel'     => __('Failed', 'ai-post-scheduler'),
            'chartTopicsLabel'     => __('Topics Generated', 'ai-post-scheduler'),
            'chartErrorRateLabel'  => __('Error Rate (%)', 'ai-post-scheduler'),
            'chartUnavailable'     => __('Chart library failed to load.', 'ai-post-scheduler'),
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
				array('jquery', 'aips-admin-script', 'aips-templates-script', 'aips-chartjs', 'aips-datetime-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-telemetry-script', 'aipsTelemetryL10n', array(
                'nonce'                => wp_create_nonce('aips_get_telemetry'),
                'detailsNonce'         => wp_create_nonce('aips_get_telemetry_details'),
                'loading'              => __('Loading…', 'ai-post-scheduler'),
                'loadingDetails'       => __('Loading telemetry details…', 'ai-post-scheduler'),
                'filterLabel'          => __('Filter', 'ai-post-scheduler'),
                'resetFiltersLabel'    => __('Reset Filters', 'ai-post-scheduler'),
                'requestFailed'        => __('Request failed. Please try again.', 'ai-post-scheduler'),
                'detailsRequestFailed' => __('Failed to load telemetry details. Please try again.', 'ai-post-scheduler'),
                'telemetryPage'        => __('Page %1$s of %2$s', 'ai-post-scheduler'),
                'telemetryTotal'       => __('%s records', 'ai-post-scheduler'),
                'telemetryNoRecords'   => __('No telemetry records found for the selected range.', 'ai-post-scheduler'),
                'chartQueriesTitle'    => __('Queries Executed per Day', 'ai-post-scheduler'),
                'chartMemoryTitle'     => __('Peak Memory per Day', 'ai-post-scheduler'),
                'chartElapsedTitle'    => __('Average Elapsed Time per Day', 'ai-post-scheduler'),
                'chartRequestsTitle'   => __('Requests Logged per Day', 'ai-post-scheduler'),
                'chartQueriesLabel'    => __('Queries', 'ai-post-scheduler'),
                'chartMemoryLabel'     => __('Peak Memory (MB)', 'ai-post-scheduler'),
                'chartElapsedLabel'    => __('Average Elapsed (ms)', 'ai-post-scheduler'),
                'chartRequestsLabel'   => __('Requests', 'ai-post-scheduler'),
                'chartUnavailable'     => __('Chart library failed to load.', 'ai-post-scheduler'),
                'rangeSummary'         => __('Showing telemetry from %1$s to %2$s.', 'ai-post-scheduler'),
                'refreshLabel'         => __('Refresh', 'ai-post-scheduler'),
                'refreshing'           => __('Refreshing…', 'ai-post-scheduler'),
                'viewDetails'          => __('View Details', 'ai-post-scheduler'),
                'detailsTitle'         => __('Telemetry Details #%s', 'ai-post-scheduler'),
                'detailsIdLabel'       => __('ID', 'ai-post-scheduler'),
                'detailsTypeLabel'     => __('Type', 'ai-post-scheduler'),
                'detailsPageLabel'     => __('Page', 'ai-post-scheduler'),
                'detailsCategoriesLabel' => __('Categories', 'ai-post-scheduler'),
                'detailsMethodLabel'   => __('Method', 'ai-post-scheduler'),
                'detailsUserIdLabel'   => __('User ID', 'ai-post-scheduler'),
                'detailsEventsLabel'   => __('Events', 'ai-post-scheduler'),
                'detailsCacheCallsLabel' => __('Cache Calls', 'ai-post-scheduler'),
                'detailsCacheHitsLabel' => __('Cache Hits', 'ai-post-scheduler'),
                'detailsCacheMissesLabel' => __('Cache Misses', 'ai-post-scheduler'),
                'detailsQueriesLabel'  => __('Queries', 'ai-post-scheduler'),
                'detailsSlowQueriesLabel' => __('Slow Queries', 'ai-post-scheduler'),
                'detailsDuplicateQueriesLabel' => __('Duplicate Queries', 'ai-post-scheduler'),
                'detailsPeakMemoryLabel' => __('Peak Memory', 'ai-post-scheduler'),
                'detailsElapsedLabel'  => __('Elapsed', 'ai-post-scheduler'),
                'detailsInsertedLabel' => __('Inserted At', 'ai-post-scheduler'),
                'detailsEventsSection' => __('Events', 'ai-post-scheduler'),
                'detailsEventSummarySection' => __('Event Summary', 'ai-post-scheduler'),
                'detailsCacheSummarySection' => __('Cache Summary', 'ai-post-scheduler'),
                'detailsQuerySummarySection' => __('Query Summary', 'ai-post-scheduler'),
                'detailsEventsHelp'    => __('The full event list can be long. Expand to inspect each nested event object.', 'ai-post-scheduler'),
                'detailsEventSummaryHelp' => __('High-level telemetry counts grouped by bucket and event type.', 'ai-post-scheduler'),
                'detailsCacheSummaryHelp' => __('Cache activity grouped by operation and result.', 'ai-post-scheduler'),
                'detailsQuerySummaryHelp' => __('Query totals, slow queries, and duplicate query counts.', 'ai-post-scheduler'),
                'detailsRawPayloadLabel' => __('Raw Payload JSON', 'ai-post-scheduler'),
                'detailsRawPayloadHelp' => __('Review the structured payload summaries above, or expand the raw JSON below for the original object.', 'ai-post-scheduler'),
                'detailsEventItemLabel' => __('Event %s', 'ai-post-scheduler'),
                'detailsItemLabel'      => __('Item %s', 'ai-post-scheduler'),
                'locale'               => get_locale(),
                'expandLabel'          => __('Expand', 'ai-post-scheduler'),
                'collapseLabel'        => __('Collapse', 'ai-post-scheduler'),
                'insertedJustNow'      => __('just now', 'ai-post-scheduler'),
                'insertedMinutesAgo'   => __('%s minutes ago', 'ai-post-scheduler'),
                'insertedMinuteAgo'    => __('1 minute ago', 'ai-post-scheduler'),
                'insertedHoursAgo'     => __('%s hours ago', 'ai-post-scheduler'),
                'insertedHourAgo'      => __('1 hour ago', 'ai-post-scheduler'),
                'insertedHoursMinutesAgo' => __('%1$s hours and %2$s minutes ago', 'ai-post-scheduler'),
                'insertedYesterdayAt'  => __('yesterday at %s', 'ai-post-scheduler'),
                'insertedAbsoluteDate'  => __('%1$s %2$s', 'ai-post-scheduler'),
                'payloadEmpty'         => __('No payload was stored for this telemetry row.', 'ai-post-scheduler'),
                'eventsEmpty'          => __('[]', 'ai-post-scheduler'),
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
            wp_localize_script('aips-admin-internal-links', 'aipsInternalLinksL10n', array(
                'nonce'                    => wp_create_nonce('aips_ajax_nonce'),
                'confirmDelete'            => __('Delete this suggestion? This cannot be undone.', 'ai-post-scheduler'),
                'confirmClearIndex'        => __('Clear the entire index and all suggestions? This cannot be undone.', 'ai-post-scheduler'),
                'indexingStarted'          => __('Indexing started. Posts will be processed in the background.', 'ai-post-scheduler'),
                'indexingNotAvailable'     => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler'),
                'generating'               => __('Generating…', 'ai-post-scheduler'),
                'reindexing'               => __('Re-indexing…', 'ai-post-scheduler'),
                'loading'                  => __('Loading…', 'ai-post-scheduler'),
                'noSuggestions'            => __('No suggestions found. Run indexing and generate suggestions to see results.', 'ai-post-scheduler'),
                'errorLoading'             => __('Error loading suggestions.', 'ai-post-scheduler'),
                'errorDeleting'            => __('Error deleting suggestion.', 'ai-post-scheduler'),
                'statusUpdated'            => __('Status updated.', 'ai-post-scheduler'),
                'anchorUpdated'            => __('Anchor text updated.', 'ai-post-scheduler'),
                'statusUpdateFailed'       => __('Failed to update status.', 'ai-post-scheduler'),
                'anchorUpdateFailed'       => __('Failed to update anchor text.', 'ai-post-scheduler'),
                'invalidPostId'            => __('Please enter a valid post ID.', 'ai-post-scheduler'),
                'requestFailed'            => __('Request failed. Please try again.', 'ai-post-scheduler'),
                'acceptAction'             => __('Accept suggestion', 'ai-post-scheduler'),
                'rejectAction'             => __('Reject suggestion', 'ai-post-scheduler'),
                'accepted'                 => __('Accepted', 'ai-post-scheduler'),
                'rejected'                 => __('Rejected', 'ai-post-scheduler'),
                'pending'                  => __('Pending', 'ai-post-scheduler'),
                'inserted'                 => __('Inserted', 'ai-post-scheduler'),
                // Insert Link modal strings
                'insertLink'               => __('Insert Link', 'ai-post-scheduler'),
                'loadingFailed'            => __('Failed to load post data. Please try again.', 'ai-post-scheduler'),
                'noContent'                => __('(No content)', 'ai-post-scheduler'),
                'noInsertSuggestions'      => __('No accepted suggestions found for this post.', 'ai-post-scheduler'),
                'insertBtn'                => __('Get Suggestions', 'ai-post-scheduler'),
                'findingLocations'         => __('Finding insertion locations…', 'ai-post-scheduler'),
                'locationsFailed'          => __('Failed to find insertion locations. Please try again.', 'ai-post-scheduler'),
                'noLocations'              => __('No valid insertion locations could be shown for this suggestion.', 'ai-post-scheduler'),
                'invalidLocationsHint'     => __('The AI responded, but its suggestions rewrote the source text instead of bracketing an existing phrase.', 'ai-post-scheduler'),
                'insertionLocationsLabel'  => __('Insertion Locations', 'ai-post-scheduler'),
                'returnedCountLabel'       => __('Showing %1$d valid of %2$d AI suggestions', 'ai-post-scheduler'),
                'zeroSuggestionsReturned'  => __('0 valid suggestions', 'ai-post-scheduler'),
                'aiSuggestionsReturned'    => __('AI returned %d suggestion(s)', 'ai-post-scheduler'),
                'reasonLabel'              => __('Reason', 'ai-post-scheduler'),
                'originalSnippetLabel'     => __('Original text', 'ai-post-scheduler'),
                'withLinkLabel'            => __('With link inserted', 'ai-post-scheduler'),
                'applyBtn'                 => __('Apply', 'ai-post-scheduler'),
                'applying'                 => __('Applying…', 'ai-post-scheduler'),
                'applied'                  => __('Link inserted successfully.', 'ai-post-scheduler'),
                'applyFailed'              => __('Failed to apply insertion. Please try again.', 'ai-post-scheduler'),
                'editAnchorText'           => __('Edit anchor text', 'ai-post-scheduler'),
                'deleteSuggestion'         => __('Delete suggestion', 'ai-post-scheduler'),
                'anchorLabel'              => __('Anchor', 'ai-post-scheduler'),
                'optionLabel'              => __('Option', 'ai-post-scheduler'),
                // Preview insertion flow strings
                'updatePostBtn'            => __('Update Post with Inserted Links', 'ai-post-scheduler'),
                'updating'                 => __('Updating…', 'ai-post-scheduler'),
                'updateFailed'             => __('Failed to update post. Please try again.', 'ai-post-scheduler'),
                'editInsertedLink'         => __('Edit anchor', 'ai-post-scheduler'),
                'removeInsertedLink'       => __('Remove link', 'ai-post-scheduler'),
                'alreadyApplied'           => __('This suggestion has already been applied to the preview.', 'ai-post-scheduler'),
                'snippetNotFound'          => __('The selected text was not found in the content preview.', 'ai-post-scheduler'),
                'pendingCountSingle'       => __('%d pending insertion', 'ai-post-scheduler'),
                'pendingCountPlural'       => __('%d pending insertions', 'ai-post-scheduler'),
            ));
    }

}
