<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin_Assets
 *
 * Handles the enqueueing of admin scripts and styles.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_Assets {

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
				'rejectReasonPlaceholder' => __('Why are you rejecting this topic? (optional)', 'ai-post-scheduler'),
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

        // Post Review Page Scripts
        if (strpos($hook, 'aips-post-review') !== false) {
            wp_enqueue_script(
                'aips-admin-post-review',
                AIPS_PLUGIN_URL . 'assets/js/admin-post-review.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );

            wp_localize_script('aips-admin-post-review', 'aipsPostReviewL10n', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aips_ajax_nonce'),
                'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
                'confirmBulkPublish' => __('Are you sure you want to publish %d selected post(s)?', 'ai-post-scheduler'),
                'confirmDelete' => __('Are you sure you want to delete this post? This action cannot be undone.', 'ai-post-scheduler'),
                'confirmBulkDelete' => __('Are you sure you want to delete %d selected post(s)? This action cannot be undone.', 'ai-post-scheduler'),
                'confirmRegenerate' => __('Are you sure you want to regenerate this post? The current post will be deleted.', 'ai-post-scheduler'),
                'publishSuccess' => __('Post published successfully!', 'ai-post-scheduler'),
                'bulkPublishSuccess' => __('%d posts published successfully!', 'ai-post-scheduler'),
                'deleteSuccess' => __('Post deleted successfully!', 'ai-post-scheduler'),
                'bulkDeleteSuccess' => __('%d posts deleted successfully!', 'ai-post-scheduler'),
                'regenerateSuccess' => __('Post regeneration started!', 'ai-post-scheduler'),
                'publishError' => __('Failed to publish post.', 'ai-post-scheduler'),
                'deleteError' => __('Failed to delete post.', 'ai-post-scheduler'),
                'regenerateError' => __('Failed to regenerate post.', 'ai-post-scheduler'),
                'loadingError' => __('Failed to load draft posts.', 'ai-post-scheduler'),
                'noPostsSelected' => __('Please select at least one post.', 'ai-post-scheduler'),
            ));
        }

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
}
