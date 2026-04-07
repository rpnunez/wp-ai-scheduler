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
            'aips-utilities-script',
            AIPS_PLUGIN_URL . 'assets/js/utilities.js',
            array('jquery'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-utilities-script', 'aipsUtilitiesL10n', array(
            'closeLabel'               => __('Close notification', 'ai-post-scheduler'),
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

        wp_enqueue_script(
            'aips-admin-embeddings',
            AIPS_PLUGIN_URL . 'assets/js/admin-embeddings.js',
            array('jquery', 'aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-script', 'aipsAdminL10n', array(
            'deleteStructureConfirm' => __('Are you sure you want to delete this structure?', 'ai-post-scheduler'),
            'saveStructureFailed' => __('Failed to save structure.', 'ai-post-scheduler'),
            'loadStructureFailed' => __('Failed to load structure.', 'ai-post-scheduler'),
            'deleteStructureFailed' => __('Failed to delete structure.', 'ai-post-scheduler'),
            'deleteSectionConfirm' => __('Are you sure you want to delete this prompt section?', 'ai-post-scheduler'),
            'saveSectionFailed' => __('Failed to save prompt section.', 'ai-post-scheduler'),
            'loadSectionFailed' => __('Failed to load prompt section.', 'ai-post-scheduler'),
            'deleteSectionFailed' => __('Failed to delete prompt section.', 'ai-post-scheduler'),
            'activeLabel'  => __('Active', 'ai-post-scheduler'),
            'inactiveLabel' => __('Inactive', 'ai-post-scheduler'),
            'defaultLabel'  => __('Default', 'ai-post-scheduler'),
            'errorOccurred' => __('An error occurred.', 'ai-post-scheduler'),
            'errorTryAgain' => __('An error occurred. Please try again.', 'ai-post-scheduler'),
            // Template Wizard strings
            'templateNameRequired' => __('Template Name is required.', 'ai-post-scheduler'),
            'contentPromptRequired' => __('Content Prompt is required.', 'ai-post-scheduler'),
            // Schedule strings
            'runScheduleConfirm' => __('Are you sure you want to run this schedule now? This will immediately generate posts.', 'ai-post-scheduler'),
            'scheduleRunning' => __('Running...', 'ai-post-scheduler'),
            // Schedule Wizard strings
            'scheduleTemplateRequired' => __('Please select a Template to continue.', 'ai-post-scheduler'),
            'addNewSchedule'           => __('Add New Schedule', 'ai-post-scheduler'),
            'editSchedule'             => __('Edit Schedule', 'ai-post-scheduler'),
            'cloneSchedule'            => __('Clone Schedule', 'ai-post-scheduler'),
            'saveSchedule'             => __('Save Schedule', 'ai-post-scheduler'),
            'scheduleSavedSuccess'     => __('Schedule saved successfully.', 'ai-post-scheduler'),
            'startNow'                 => __('Now', 'ai-post-scheduler'),
            'useDefault'               => __('Use Default', 'ai-post-scheduler'),
            'noTitle'                  => __('No title', 'ai-post-scheduler'),
            'yes'                      => __('Yes', 'ai-post-scheduler'),
            'no'                       => __('No', 'ai-post-scheduler'),
            // Status/button strings
            'saving'              => __('Saving...', 'ai-post-scheduler'),
            'generating'          => __('Generating...', 'ai-post-scheduler'),
            'generationFailed'    => __('Generation failed.', 'ai-post-scheduler'),
            'runNow'              => __('Run Now', 'ai-post-scheduler'),
            'draftSaved'          => __('Draft saved successfully.', 'ai-post-scheduler'),
            'saveDraft'           => __('Save Draft', 'ai-post-scheduler'),
            // Voice strings
            'noVoiceDefault'      => __('No Voice (Use Default)', 'ai-post-scheduler'),
            'addNewVoice'         => __('Add New Voice', 'ai-post-scheduler'),
            'editVoice'           => __('Edit Voice', 'ai-post-scheduler'),
            'saveVoice'           => __('Save Voice', 'ai-post-scheduler'),
            'deleteVoiceConfirm'  => __('Are you sure you want to delete this voice?', 'ai-post-scheduler'),
            // Confirm dialog button labels
            'confirmCancelButton'              => __('No, cancel', 'ai-post-scheduler'),
            'confirmDeleteButton'              => __('Yes, delete', 'ai-post-scheduler'),
            // Schedule delete confirm strings
            'deleteScheduleConfirm'            => __('Are you sure you want to delete this schedule?', 'ai-post-scheduler'),
            'selectAtLeastOneSchedule'         => __('Please select at least one schedule.', 'ai-post-scheduler'),
            'deleteOneScheduleConfirm'         => __('Are you sure you want to delete 1 schedule?', 'ai-post-scheduler'),
            /* translators: %d: number of schedules to delete */
            'deleteMultipleSchedulesConfirm'   => __('Are you sure you want to delete %d schedules?', 'ai-post-scheduler'),
            // Schedule error toasts
            'failedToLoadHistory'              => __('Failed to load history.', 'ai-post-scheduler'),
            'failedToDeleteSchedules'          => __('Failed to delete schedules.', 'ai-post-scheduler'),
            'bulkRunFailed'                    => __('Bulk run failed.', 'ai-post-scheduler'),
            // Bulk run-now confirm dialog
            'runSchedulesNow'                  => __('Run Schedules Now', 'ai-post-scheduler'),
            'cancel'                           => __('Cancel', 'ai-post-scheduler'),
            'yesRunNow'                        => __('Yes, run now', 'ai-post-scheduler'),
            'runPostsConfirmSingular'          => __('This will generate an estimated 1 post. Are you sure?', 'ai-post-scheduler'),
            /* translators: %d: estimated number of posts to generate */
            'runPostsConfirmPlural'            => __('This will generate an estimated %d posts. Are you sure?', 'ai-post-scheduler'),
            'runOneScheduleConfirm'            => __('This will run 1 schedule. Are you sure?', 'ai-post-scheduler'),
            /* translators: %d: number of schedules to run */
            'runMultipleSchedulesConfirm'      => __('This will run %d schedules. Are you sure?', 'ai-post-scheduler'),
            // Template summary panel
            'autoGenerateFromContent'          => __('Auto-generate from content', 'ai-post-scheduler'),
            'noneOption'                       => __('None', 'ai-post-scheduler'),
            'featuredImageNo'                  => __('No', 'ai-post-scheduler'),
            /* translators: %s: featured image source name */
            'featuredImageYes'                 => __('Yes (%s)', 'ai-post-scheduler'),
            // AI variable tag tooltip
            'clickToCopy'                      => __('Click to copy', 'ai-post-scheduler'),
            // Template preview
            'exampleTopic'                     => __('Example Topic', 'ai-post-scheduler'),
            'failedToGeneratePreview'          => __('Failed to generate preview. Please check that all required fields are filled.', 'ai-post-scheduler'),
            'previewNetworkError'              => __('An error occurred while generating the preview. Please check your network connection and try again.', 'ai-post-scheduler'),
            // Onboarding wizard
            'confirmSkipOnboarding'            => __('Skip the Onboarding Wizard? You can restart it later from System Status.', 'ai-post-scheduler'),
        ));

        // Enqueue Authors-specific assets
        if (strpos($hook, 'aips-authors') !== false || strpos($hook, 'aips-author-topics') !== false) {
          wp_enqueue_style(
            'aips-authors-style',
            AIPS_PLUGIN_URL . 'assets/css/authors.css',
            array('aips-admin-style'),
            AIPS_VERSION
          );

          wp_enqueue_script(
            'aips-templates-script',
            AIPS_PLUGIN_URL . 'assets/js/templates.js',
            array('jquery'),
            AIPS_VERSION,
            true
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
          }

        // Research Page Styles & Scripts
        if (strpos($hook, 'aips-research') !== false) {
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
          ));
        }


        // Generated Posts Page Scripts
        if (strpos($hook, 'aips-generated-posts') !== false) {
            // Enqueue View Session module (shared functionality)
            wp_enqueue_script(
                'aips-admin-view-session',
                AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
            
            // Enqueue Post Review module (for Pending Review tab)
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

        // Calendar Page Scripts
        if (strpos($hook, 'aips-schedule-calendar') !== false) {
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

        // History Page Scripts (View Session for Processing items)
        if (strpos($hook, 'aips-history') !== false) {
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
                'labelPostId'          => __('Post ID', 'ai-post-scheduler'),
                'colTimestamp'         => __('Timestamp', 'ai-post-scheduler'),
                'colType'              => __('Type', 'ai-post-scheduler'),
                'colLogType'           => __('Log Type', 'ai-post-scheduler'),
                'colDetails'           => __('Details', 'ai-post-scheduler'),
                'showDetails'          => __('Show details', 'ai-post-scheduler'),
                'hideDetails'          => __('Hide details', 'ai-post-scheduler'),
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

        if (strpos($hook, 'aips-onboarding') !== false) {
            wp_enqueue_script(
                'aips-admin-onboarding',
                AIPS_PLUGIN_URL . 'assets/js/onboarding.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
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

        // System Status Page Scripts
        if (strpos($hook, 'aips-status') !== false) {
            wp_enqueue_script(
                'aips-admin-db',
                AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
        }

        if (strpos($hook, 'aips-taxonomy') !== false) {
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

        if (strpos($hook, 'aips-sources') !== false) {
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

        // Settings Page Scripts
        if (strpos($hook, 'aips-settings') !== false) {
            wp_enqueue_script(
                'aips-admin-settings',
                AIPS_PLUGIN_URL . 'assets/js/admin-settings.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
        }

        // System Status Page Scripts
        if (strpos($hook, 'aips-status') !== false) {
            wp_enqueue_script(
                'aips-admin-system-status',
                AIPS_PLUGIN_URL . 'assets/js/admin-system-status.js',
                array('aips-admin-script'),
                AIPS_VERSION,
                true
            );
            wp_localize_script('aips-admin-system-status', 'aipsSystemStatusL10n', array(
                'nonce'         => wp_create_nonce('aips_reset_circuit_breaker'),
                'hideDetails'   => __('Hide Details', 'ai-post-scheduler'),
                'showDetails'   => __('Show Details', 'ai-post-scheduler'),
                'resetSuccess'  => __('Circuit reset. Reload the page to confirm.', 'ai-post-scheduler'),
                'resetFailed'   => __('Reset failed.', 'ai-post-scheduler'),
                'requestFailed' => __('Request failed. Please try again.', 'ai-post-scheduler'),
            ));
        }

        // Internal Links Page Scripts
        if (strpos($hook, 'aips-internal-links') !== false) {
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
}
