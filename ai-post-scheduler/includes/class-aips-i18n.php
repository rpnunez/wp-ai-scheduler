<?php
/**
 * Internationalization Helper
 *
 * @package AI_Post_Scheduler
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_i18n
 *
 * Provides translatable strings for JavaScript localization.
 */
class AIPS_i18n {

    /**
     * Get localization strings for Utilities script.
     *
     * @return array
     */
    public static function get_utilities_strings() {
        return array(
            'closeLabel'               => __('Close notification', 'ai-post-scheduler'),
            'estimatedTimeRemaining'   => __('Estimated time remaining: %s', 'ai-post-scheduler'),
            'generationComplete'       => __('Generation complete!', 'ai-post-scheduler'),
            'seconds'                  => __('seconds', 'ai-post-scheduler'),
            'minute'                   => __('1 minute', 'ai-post-scheduler'),
            'minutes'                  => __('%d minutes', 'ai-post-scheduler'),
            'minutesSeconds'           => __('%dm %ds', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for main Admin script.
     *
     * @return array
     */
    public static function get_admin_strings() {
        return array(
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
            'templateNameRequired' => __('Template Name is required.', 'ai-post-scheduler'),
            'contentPromptRequired' => __('Content Prompt is required.', 'ai-post-scheduler'),
            'runScheduleConfirm' => __('Are you sure you want to run this schedule now? This will immediately generate posts.', 'ai-post-scheduler'),
            'scheduleRunning' => __('Running...', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for Authors script.
     *
     * @return array
     */
    public static function get_authors_strings() {
        return array(
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
            'topicSaved' => __('Topic saved successfully.', 'ai-post-scheduler'),
            'topicApproved' => __('Topic approved.', 'ai-post-scheduler'),
            'topicRejected' => __('Topic rejected.', 'ai-post-scheduler'),
            'errorOccurred' => __('An error occurred. Please try again.', 'ai-post-scheduler'),
            'confirmRejectTopic' => __('Are you sure you want to reject this topic? It will not be used for post generation.', 'ai-post-scheduler'),
            'noTopicsFound' => __('No topics found matching your criteria.', 'ai-post-scheduler'),
            'topicsListEmpty' => __('No topics available.', 'ai-post-scheduler'),
            'reasonRequired' => __('Please provide a reason.', 'ai-post-scheduler'),
            'rejectionReason' => __('Rejection Reason:', 'ai-post-scheduler'),
            'confirmClearLogs' => __('Are you sure you want to clear all logs for this author? This cannot be undone.', 'ai-post-scheduler'),
            'confirmClearAllLogs' => __('Are you sure you want to clear ALL topic generation logs? This cannot be undone.', 'ai-post-scheduler'),
            'clearLogsSuccess' => __('Logs cleared successfully.', 'ai-post-scheduler'),
            'clearLogsFailed' => __('Failed to clear logs.', 'ai-post-scheduler'),
            'logsEmpty' => __('No logs found.', 'ai-post-scheduler'),
            'bulkRejectTopicsPrompt' => __('Reject %d selected topics? Please provide a reason.', 'ai-post-scheduler'),
            'bulkRejectTopicsConfirm' => __('Reject %d topics', 'ai-post-scheduler'),
            'bulkRejectTopicsError' => __('Failed to bulk reject topics.', 'ai-post-scheduler'),
            'regenerating' => __('Regenerating...', 'ai-post-scheduler'),
            'topicRegenerated' => __('Topic regenerated successfully.', 'ai-post-scheduler'),
            'selectTopicsToApprove' => __('Please select topics to approve.', 'ai-post-scheduler'),
            'selectTopicsToReject' => __('Please select topics to reject.', 'ai-post-scheduler'),
            'selectTopicsToDelete' => __('Please select topics to delete.', 'ai-post-scheduler'),
            'confirmBulkApprove' => __('Are you sure you want to approve %d selected topics?', 'ai-post-scheduler'),
            'confirmBulkDelete' => __('Are you sure you want to delete %d selected topics?', 'ai-post-scheduler'),
            'postsSavedSuccess' => __('Posts saved successfully!', 'ai-post-scheduler'),
            'postsSavedError' => __('Failed to save posts.', 'ai-post-scheduler'),
            'generatingPosts' => __('Generating posts...', 'ai-post-scheduler'),
            'confirmRegenerateTopic' => __('Are you sure you want to regenerate this topic? The previous content will be overwritten.', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for Research script.
     *
     * @return array
     */
    public static function get_research_strings() {
        return array(
            'topicsSaved' => __('topics saved for', 'ai-post-scheduler'),
            'topTopics' => __('Top 5 Topics:', 'ai-post-scheduler'),
            'noTopicsFound' => __('No topics match your search criteria.', 'ai-post-scheduler'),
            'noTopicsFoundTitle' => __('No Topics Found', 'ai-post-scheduler'),
            'clearSearch' => __('Clear Search', 'ai-post-scheduler'),
            'confirmBulkApprove' => __('Are you sure you want to approve %d selected topics?', 'ai-post-scheduler'),
            'confirmBulkDelete' => __('Are you sure you want to delete %d selected topics? This action cannot be undone.', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for Activity script.
     *
     * @return array
     */
    public static function get_activity_strings() {
        return array(
            'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
            'publishSuccess' => __('Post published successfully!', 'ai-post-scheduler'),
            'publishError' => __('Failed to publish post.', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for Post Review script.
     *
     * @return array
     */
    public static function get_post_review_strings() {
        return array(
            'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
            'confirmBulkPublish' => __('Are you sure you want to publish %d selected post(s)?', 'ai-post-scheduler'),
            'confirmDelete' => __('Are you sure you want to delete this post? This action cannot be undone.', 'ai-post-scheduler'),
            'confirmBulkDelete' => __('Are you sure you want to delete %d selected post(s)? This action cannot be undone.', 'ai-post-scheduler'),
            'errorOccurred' => __('An error occurred while processing your request.', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for AI Edit script.
     *
     * @return array
     */
    public static function get_ai_edit_strings() {
        return array(
            'regenerate' => __('Re-generate', 'ai-post-scheduler'),
            'regenerating' => __('Regenerating...', 'ai-post-scheduler'),
            'regenerateSuccess' => __('Component regenerated successfully!', 'ai-post-scheduler'),
            'regenerateError' => __('Failed to regenerate component.', 'ai-post-scheduler'),
            'confirmRegenerate' => __('Are you sure you want to re-generate this component? The current content will be replaced.', 'ai-post-scheduler'),
            'serverError' => __('Server error occurred.', 'ai-post-scheduler'),
        );
    }

    /**
     * Get localization strings for History script.
     *
     * @return array
     */
    public static function get_history_strings() {
        return array(
            'loading'              => __('Loading…', 'ai-post-scheduler'),
            'reloading'            => __('Reloading…', 'ai-post-scheduler'),
            'loadingLogs'          => __('Loading logs…', 'ai-post-scheduler'),
            'historyDetailsTitle'  => __('History Details', 'ai-post-scheduler'),
            'historyContainerHeader' => __('History Container for ID: %d', 'ai-post-scheduler'),
            'historyContainerSubtitle' => __('All operations associated with this entity', 'ai-post-scheduler'),
            'logType'              => __('Type', 'ai-post-scheduler'),
            'logTimestamp'         => __('Time', 'ai-post-scheduler'),
            'logDetails'           => __('Details', 'ai-post-scheduler'),
            'expandDetails'        => __('Expand to view full details', 'ai-post-scheduler'),
            'collapseDetails'      => __('Collapse details', 'ai-post-scheduler'),
            'viewJson'             => __('View Raw JSON', 'ai-post-scheduler'),
            'errorLoadingLogs'     => __('Failed to load logs.', 'ai-post-scheduler'),
            'errorLoadingDetails'  => __('Failed to load details.', 'ai-post-scheduler'),
            'noLogsFound'          => __('No detailed logs found for this container.', 'ai-post-scheduler'),
            'copyToClipboard'      => __('Copy to clipboard', 'ai-post-scheduler'),
            'copied'               => __('Copied!', 'ai-post-scheduler'),
            'expandError'          => __('Failed to load log details.', 'ai-post-scheduler'),
            'confirmClearAll'      => __('Are you absolutely sure you want to clear ALL history logs? This action cannot be undone and will permanently delete all records of past generations and errors.', 'ai-post-scheduler'),
            'clearAllSuccess'      => __('All history logs have been successfully cleared.', 'ai-post-scheduler'),
            'clearAllError'        => __('Failed to clear history logs. Please check server error logs.', 'ai-post-scheduler'),
            'clearHistory'         => __('Clear History', 'ai-post-scheduler'),
            'clearing'             => __('Clearing...', 'ai-post-scheduler'),
        );
    }
}
