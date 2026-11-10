<?php
/**
 * Admin Asset Registrar: Content Strategy & Generation
 *
 * Handles enqueueing for Authors, Studio (Templates, Voices, Structures, Post Slices),
 * Schedules, Campaigns, Campaign Wizard, and Research.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Registrar_Content_Strategy
 */
class AIPS_Admin_Asset_Registrar_Content_Strategy {

	/**
	 * Enqueue assets for the authors page.
	 *
	 * @param string $hook         The current admin page hook.
	 * @param string $current_page Current page slug.
	 * @param string $current_tab  Current tab key.
	 * @return void
	 */
	public function enqueue_authors($hook = '', $current_page = '', $current_tab = '') {
		if (empty($current_page)) {
			$resolver = new AIPS_Admin_Asset_Context_Resolver();
			$current_page = $resolver->get_current_page_slug();
			$current_tab  = $resolver->get_current_tab_key();
		}

		$author_topics_tab = class_exists('AIPS_Automations_Controller') ? AIPS_Automations_Controller::TAB_AUTHOR_TOPICS : 'author-topics';
		$is_author_topics_context = AIPS_Admin_Asset_Context_Resolver::PAGE_AUTHOR_TOPICS === $current_page
			|| (AIPS_Admin_Asset_Context_Resolver::PAGE_AUTOMATIONS === $current_page && $author_topics_tab === $current_tab);
		$is_authors_listing_context = AIPS_Admin_Asset_Context_Resolver::PAGE_AUTHORS === $current_page
			|| (AIPS_Admin_Asset_Context_Resolver::PAGE_AUTOMATIONS === $current_page && 'authors' === $current_tab);

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
			array('jquery', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		// Localize script with translations and nonce
		$page_author_id = $is_author_topics_context ? absint(filter_input(INPUT_GET, 'author_id', FILTER_VALIDATE_INT)) : 0;

		wp_localize_script('aips-authors-script', 'aipsAuthorsL10n', array(
			'nonce'                     => wp_create_nonce('aips_ajax_nonce'),
			'addNewAuthor'              => __('Add New Author', 'ai-post-scheduler'),
			'editAuthor'                => __('Edit Author', 'ai-post-scheduler'),
			'saveAuthor'                => __('Save Author', 'ai-post-scheduler'),
			'loading'                   => __('Loading...', 'ai-post-scheduler'),
			'saving'                    => __('Saving...', 'ai-post-scheduler'),
			'generating'                => __('Generating...', 'ai-post-scheduler'),
			'confirmDelete'             => __('Are you sure you want to delete this author? This will also delete all associated topics and logs.', 'ai-post-scheduler'),
			'confirmDeleteTopic'        => __('Are you sure you want to delete this topic?', 'ai-post-scheduler'),
			'confirmGenerateTopics'     => __('Generate topics for this author now?', 'ai-post-scheduler'),
			'confirmGeneratePosts'      => __('Generate posts for this author now?', 'ai-post-scheduler'),
			'confirmGeneratePost'       => __('Generate a post from this topic now?', 'ai-post-scheduler'),
			'generatePostsModalTitle'   => __('Generate Posts', 'ai-post-scheduler'),
			'generatePostsModalMessage' => __('How many posts would you like to generate for this author?', 'ai-post-scheduler'),
			'numberOfPostsLabel'        => __('Number of Posts to Generate', 'ai-post-scheduler'),
			'generateButtonLabel'       => __('Generate', 'ai-post-scheduler'),
			'invalidQuantityError'      => __('Please enter a valid quantity between 1 and 10.', 'ai-post-scheduler'),
			'authorSaved'               => __('Author saved successfully.', 'ai-post-scheduler'),
			'authorDeleted'             => __('Author deleted successfully.', 'ai-post-scheduler'),
			'topicsGenerated'           => __('Topics generated successfully.', 'ai-post-scheduler'),
			'postGenerated'             => __('Post generated successfully.', 'ai-post-scheduler'),
			'generateTopicsNow'         => __('Generate Topics Now', 'ai-post-scheduler'),
			'generatePostNow'           => __('Generate Post Now', 'ai-post-scheduler'),
			'errorLoading'              => __('Error loading author data.', 'ai-post-scheduler'),
			'errorSaving'               => __('Error saving author.', 'ai-post-scheduler'),
			'errorDeleting'             => __('Error deleting author.', 'ai-post-scheduler'),
			'errorGenerating'           => __('Error generating topics.', 'ai-post-scheduler'),
			'errorGeneratingPosts'      => __('Error generating posts.', 'ai-post-scheduler'),
			'errorLoadingTopics'        => __('Error loading topics.', 'ai-post-scheduler'),
			'errorApproving'            => __('Error approving topic.', 'ai-post-scheduler'),
			'errorRejecting'            => __('Error rejecting topic.', 'ai-post-scheduler'),
			'errorDeletingTopic'        => __('Error deleting topic.', 'ai-post-scheduler'),
			'errorSavingTopic'          => __('Error saving topic.', 'ai-post-scheduler'),
			'errorGeneratingPost'       => __('Error generating post.', 'ai-post-scheduler'),
			'loadingTopics'             => __('Loading topics...', 'ai-post-scheduler'),
			'noTopicsFound'             => __('No topics found.', 'ai-post-scheduler'),
			'topicTitle'                => __('Topic Title', 'ai-post-scheduler'),
			'topicDetails'              => __('Topic Details', 'ai-post-scheduler'),
			'generatedAt'               => __('Date Topic Generated', 'ai-post-scheduler'),
			'dateApproved'              => __('Date Approved', 'ai-post-scheduler'),
			'dateRejected'              => __('Date Rejected', 'ai-post-scheduler'),
			'datePostGenerated'         => __('Date Post Generated', 'ai-post-scheduler'),
			'moreActions'               => __('More actions', 'ai-post-scheduler'),
			'actions'                   => __('Actions', 'ai-post-scheduler'),
			'approve'                   => __('Approve', 'ai-post-scheduler'),
			'reject'                    => __('Reject', 'ai-post-scheduler'),
			'edit'                      => __('Edit', 'ai-post-scheduler'),
			'delete'                    => __('Delete', 'ai-post-scheduler'),
			'save'                      => __('Save', 'ai-post-scheduler'),
			'cancel'                    => __('Cancel', 'ai-post-scheduler'),
			'topicTitleRequired'        => __('Topic title is required.', 'ai-post-scheduler'),
			'viewPosts'                 => __('Click to view posts generated from this topic', 'ai-post-scheduler'),
			'loadingPosts'              => __('Loading posts...', 'ai-post-scheduler'),
			'errorLoadingPosts'         => __('Error loading posts.', 'ai-post-scheduler'),
			'noPostsFound'              => __('No posts have been generated from this topic yet.', 'ai-post-scheduler'),
			'postsGeneratedFrom'        => __('Posts Generated from Topic', 'ai-post-scheduler'),
			'postId'                    => __('Post ID', 'ai-post-scheduler'),
			'postTitle'                 => __('Post Title', 'ai-post-scheduler'),
			'dateGenerated'             => __('Date Generated', 'ai-post-scheduler'),
			'datePublished'             => __('Date Published', 'ai-post-scheduler'),
			'notPublished'              => __('Not published', 'ai-post-scheduler'),
			'editPost'                  => __('Edit Post', 'ai-post-scheduler'),
			'viewPost'                  => __('View Post', 'ai-post-scheduler'),
			'publishPost'               => __('Publish', 'ai-post-scheduler'),
			'publishing'                => __('Publishing...', 'ai-post-scheduler'),
			'postPublished'             => __('Post published successfully.', 'ai-post-scheduler'),
			'errorPublishingPost'       => __('Error publishing post.', 'ai-post-scheduler'),
			'confirmPublishPost'        => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
			'noFeaturedImage'           => __('No featured image', 'ai-post-scheduler'),
			'topic'                     => __('Topic', 'ai-post-scheduler'),
			'action'                    => __('Action', 'ai-post-scheduler'),
			'reason'                    => __('Reason', 'ai-post-scheduler'),
			'user'                      => __('User', 'ai-post-scheduler'),
			'date'                      => __('Date', 'ai-post-scheduler'),
			'approveTopicTitle'         => __('Approve Topic', 'ai-post-scheduler'),
			'rejectTopicTitle'          => __('Reject Topic', 'ai-post-scheduler'),
			'approveReasonPlaceholder'  => __('Why are you approving this topic? (optional)', 'ai-post-scheduler'),
			'rejectReasonPlaceholder'   => __('Why are you rejecting this topic? (optional)', 'ai-post-scheduler'),
			'approvalCategoryLabel'     => __('Approval Reason', 'ai-post-scheduler'),
			'rejectionCategoryLabel'    => __('Rejection Reason', 'ai-post-scheduler'),
			'approvalCategoryDescription' => __('Select a positive reason to help train future topic generation.', 'ai-post-scheduler'),
			'rejectionCategoryDescription' => __('Select a structured reason to improve future topic quality.', 'ai-post-scheduler'),
			'approvalCategories'        => array(
				array('value' => 'other',           'label' => __('Other', 'ai-post-scheduler')),
				array('value' => 'timely',          'label' => __('Timely / On-trend', 'ai-post-scheduler')),
				array('value' => 'relevant',        'label' => __('Relevant to niche', 'ai-post-scheduler')),
				array('value' => 'well_researched', 'label' => __('Well-researched angle', 'ai-post-scheduler')),
				array('value' => 'engaging',        'label' => __('Engaging hook', 'ai-post-scheduler')),
				array('value' => 'original',        'label' => __('Original perspective', 'ai-post-scheduler')),
			),
			'rejectionCategories'       => array(
				array('value' => 'other',      'label' => __('Other', 'ai-post-scheduler')),
				array('value' => 'duplicate',  'label' => __('Duplicate', 'ai-post-scheduler')),
				array('value' => 'tone',       'label' => __('Tone', 'ai-post-scheduler')),
				array('value' => 'irrelevant', 'label' => __('Irrelevant', 'ai-post-scheduler')),
				array('value' => 'policy',     'label' => __('Policy', 'ai-post-scheduler')),
			),
			'loadingQueue'              => __('Loading queue...', 'ai-post-scheduler'),
			'errorLoadingQueue'         => __('Error loading queue.', 'ai-post-scheduler'),
			'noQueueTopics'             => __('No approved topics in the queue yet.', 'ai-post-scheduler'),
			'author'                    => __('Author', 'ai-post-scheduler'),
			'fieldNiche'                => __('Field/Niche', 'ai-post-scheduler'),
			'approvedDate'              => __('Approved Date', 'ai-post-scheduler'),
			'notAvailable'              => __('N/A', 'ai-post-scheduler'),
			'selectBulkAction'          => __('Please select a bulk action.', 'ai-post-scheduler'),
			'noTopicsSelected'          => __('Please select at least one topic.', 'ai-post-scheduler'),
			'comingSoon'                => __('This feature is coming soon.', 'ai-post-scheduler'),
			'invalidAction'             => __('Invalid action.', 'ai-post-scheduler'),
			'confirmGenerateFromQueue'  => __('Generate posts now for %d selected topic(s)?', 'ai-post-scheduler'),
			'postsGenerated'            => __('Posts generated successfully.', 'ai-post-scheduler'),
			'execute'                   => __('Execute', 'ai-post-scheduler'),
			'logViewerTitle'            => __('Topic History Log', 'ai-post-scheduler'),
			'logViewerLoading'          => __('Loading logs...', 'ai-post-scheduler'),
			'logViewerError'            => __('Error loading logs.', 'ai-post-scheduler'),
			'noLogsFound'               => __('No history found for this topic.', 'ai-post-scheduler'),
			'logAction'                 => __('Action', 'ai-post-scheduler'),
			'logUser'                   => __('User', 'ai-post-scheduler'),
			'logDate'                   => __('Date', 'ai-post-scheduler'),
			'logDetails'                => __('Details', 'ai-post-scheduler'),
			'processing'                => __('Processing...', 'ai-post-scheduler'),
			'approveWithFeedback'       => __('Approve with Feedback', 'ai-post-scheduler'),
			'rejectWithFeedback'        => __('Reject with Feedback', 'ai-post-scheduler'),
			'approveTopicsWithFeedbackTitle' => __('Approve %d Topics with Feedback', 'ai-post-scheduler'),
			'rejectTopicsWithFeedbackTitle'  => __('Reject %d Topics with Feedback', 'ai-post-scheduler'),
			'approveTopicsReasonPlaceholder' => __('Why are you approving these topics? (optional)', 'ai-post-scheduler'),
			'rejectTopicsReasonPlaceholder'  => __('Why are you rejecting these topics? (optional)', 'ai-post-scheduler'),
			'generateNow'               => __('Generate Now', 'ai-post-scheduler'),
			'confirmBulkGenerate'       => __('Are you sure you want to generate posts for %d topics?', 'ai-post-scheduler'),
			'noFeedbackSelected'        => __('Please select at least one feedback item.', 'ai-post-scheduler'),
			'errorBulkAction'           => __('Error executing bulk action.', 'ai-post-scheduler'),
			'generatingPostsTitle'      => __('Generating Posts', 'ai-post-scheduler'),
			'generatingPostsMessage'    => __('Please wait while your posts are being generated. This may take a few minutes.', 'ai-post-scheduler'),
			'generationCompletePartial' => __('%d post(s) generated, %d failed.', 'ai-post-scheduler'),
			'similarSuggestions'        => __('Similar Suggestions', 'ai-post-scheduler'),
			'similarityLabel'           => __('Similarity', 'ai-post-scheduler'),
			'lastFeedback'              => __('Last Feedback', 'ai-post-scheduler'),
			'previouslyApproved'        => __('Previously Approved', 'ai-post-scheduler'),
			'previouslyRejected'        => __('Previously Rejected', 'ai-post-scheduler'),
			'potentialDuplicate'        => __('Potential Duplicate', 'ai-post-scheduler'),
			'topicCountSingular'        => __('topic', 'ai-post-scheduler'),
			'topicCountPlural'          => __('topics', 'ai-post-scheduler'),
			'suggestAuthors'            => __('Suggest Authors', 'ai-post-scheduler'),
			'suggestAuthorsTitle'       => __('Suggest Authors with AI', 'ai-post-scheduler'),
			'siteNicheRequired'         => __('Site niche is required.', 'ai-post-scheduler'),
			'generateSuggestions'       => __('Generate Suggestions', 'ai-post-scheduler'),
			'generatingSuggestions'     => __('Generating suggestions...', 'ai-post-scheduler'),
			'errorGeneratingSuggestions' => __('Error generating author suggestions.', 'ai-post-scheduler'),
			'importAuthor'              => __('Import Author', 'ai-post-scheduler'),
			'importedAuthor'            => __('Imported Author', 'ai-post-scheduler'),
			'importingAuthor'           => __('Importing...', 'ai-post-scheduler'),
			'authorImported'            => __('Author imported successfully.', 'ai-post-scheduler'),
			'errorImportingAuthor'      => __('Error importing author.', 'ai-post-scheduler'),
			'voiceToneLabel'            => __('Voice/Tone', 'ai-post-scheduler'),
			'writingStyleLabel'         => __('Writing Style', 'ai-post-scheduler'),
			'topicPromptLabel'          => __('Topic Generation Prompt', 'ai-post-scheduler'),
			'viewDetails'               => __('View Details', 'ai-post-scheduler'),
			'noFeedbackYet'             => __('No feedback yet.', 'ai-post-scheduler'),
			'dateToday'                 => __('Today', 'ai-post-scheduler'),
			'dateYesterday'             => __('Yesterday', 'ai-post-scheduler'),
			'dateAM'                    => _x('am', 'time of day', 'ai-post-scheduler'),
			'datePM'                    => _x('pm', 'time of day', 'ai-post-scheduler'),
			'dateMonthNames'            => array(
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

		$deep_link_author_id = $is_authors_listing_context ? absint(filter_input(INPUT_GET, 'author_id', FILTER_VALIDATE_INT)) : 0;
		wp_localize_script('aips-authors-script', 'aipsAuthorContext', array(
			'authorId'         => $page_author_id,
			'deepLinkAuthorId' => $deep_link_author_id,
		));

		wp_enqueue_script(
			'aips-admin-embeddings',
			AIPS_PLUGIN_URL . 'assets/js/admin-embeddings.js',
			array('jquery', 'aips-admin-script'),
			AIPS_VERSION,
			true
		);

		$this->enqueue_ai_assistance(array('aips-authors-script'), 'authors');
	}

	/**
	 * Enqueue the shared AI Assistance CSS/JS.
	 *
	 * @param array  $extra_script_deps Extra script handles.
	 * @param string $context           Context identifier.
	 * @return void
	 */
	public function enqueue_ai_assistance(array $extra_script_deps = array(), $context = 'authors') {
		if (!current_user_can('manage_options') || !AIPS_Settings::is_ai_assistance_enabled_for($context)) {
			return;
		}

		wp_enqueue_style(
			'aips-ai-assistance-style',
			AIPS_PLUGIN_URL . 'assets/css/ai-assistance.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-ai-assistance-script',
			AIPS_PLUGIN_URL . 'assets/js/ai-assistance.js',
			array_merge(array('jquery', 'aips-utilities-script', 'aips-templates-script'), $extra_script_deps),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-ai-assistance-script', 'aipsAIAssistanceL10n', array(
			'nonce'           => wp_create_nonce('aips_ajax_nonce'),
			'loading'         => __('Loading...', 'ai-post-scheduler'),
			'suggesting'      => __('Suggesting...', 'ai-post-scheduler'),
			'suggested'       => __('AI suggestion applied.', 'ai-post-scheduler'),
			'errorSuggesting' => __('Could not get AI suggestion. Please try again.', 'ai-post-scheduler'),
			'valueApplied'    => __('Value applied from history.', 'ai-post-scheduler'),
			'noHistory'       => __('No AI suggestions found for this field yet.', 'ai-post-scheduler'),
			'aiUnavailable'   => __('AI Engine is not available.', 'ai-post-scheduler'),
			'thisSession'     => __('This Session', 'ai-post-scheduler'),
			'allTime'         => __('All Time', 'ai-post-scheduler'),
			'policy'          => AIPS_Settings::get_ai_assistance_policy(),
			'previewTitle'    => __('Review AI suggestion', 'ai-post-scheduler'),
			'apply'           => __('Apply suggestion', 'ai-post-scheduler'),
			'keepCurrent'     => __('Keep current value', 'ai-post-scheduler'),
			'regenerate'      => __('Regenerate', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the Post Slices page.
	 *
	 * @return void
	 */
	public function enqueue_post_slices() {
		wp_enqueue_style(
			'aips-post-slices-style',
			AIPS_PLUGIN_URL . 'assets/css/post-slices.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-post-slices',
			AIPS_PLUGIN_URL . 'assets/js/admin-post-slices.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
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
	 *
	 * @return void
	 */
	public function enqueue_templates() {
		$this->enqueue_ai_assistance(array('aips-admin-script'), 'templates');

		wp_enqueue_script(
			'aips-admin-integrations',
			AIPS_PLUGIN_URL . 'assets/js/admin-integrations.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-integrations', 'aipsIntegrationsL10n', array(
			'selectIntegration'      => __('Select an integration…', 'ai-post-scheduler'),
			'selectIntegrationFirst' => __('Select an integration first', 'ai-post-scheduler'),
			'selectFieldGroup'       => __('Select a field group…', 'ai-post-scheduler'),
			'selectGroupFirst'       => __('Select an integration and field group first.', 'ai-post-scheduler'),
			'noneAvailable'          => __('No supported plugins detected on this site.', 'ai-post-scheduler'),
			'noGroupsFound'          => __('No field groups found for this post type.', 'ai-post-scheduler'),
			'promptPlaceholder'      => __('Optional: custom instructions for this field. Leave blank to use the field\'s own help text.', 'ai-post-scheduler'),
			'unsupportedFieldType'   => __('This field type is not yet supported for AI generation.', 'ai-post-scheduler'),
			'selectFieldPlaceholder' => __('Select a field…', 'ai-post-scheduler'),
			'customFieldKeyOption'   => __('Custom meta key…', 'ai-post-scheduler'),
			'customKeyPlaceholder'   => __('e.g. contact_phone_number', 'ai-post-scheduler'),
			'invalidCustomKey'       => __('Meta key may only contain letters, numbers, and underscores.', 'ai-post-scheduler'),
			'shapeShortText'         => __('Short Text', 'ai-post-scheduler'),
			'shapeLongText'          => __('Long Text', 'ai-post-scheduler'),
			'shapeHtml'              => __('HTML', 'ai-post-scheduler'),
			'removeField'            => __('Remove', 'ai-post-scheduler'),
		));

		wp_localize_script('aips-admin-script', 'aipsTemplatesL10n', array(
			'templateNameRequired'    => __('Template Name is required.', 'ai-post-scheduler'),
			'contentPromptRequired'   => __('Content Prompt is required.', 'ai-post-scheduler'),
			'draftSaved'              => __('Draft saved successfully.', 'ai-post-scheduler'),
			'saveDraft'               => __('Save Draft', 'ai-post-scheduler'),
			'autoGenerateFromContent' => __('Auto-generate from content', 'ai-post-scheduler'),
			'featuredImageNo'         => __('No', 'ai-post-scheduler'),
			'featuredImageYes'        => __('Yes (%s)', 'ai-post-scheduler'),
			'clickToCopy'             => __('Click to copy', 'ai-post-scheduler'),
			'exampleTopic'            => __('Example Topic', 'ai-post-scheduler'),
			'failedToGeneratePreview' => __('Failed to generate preview. Please check that all required fields are filled.', 'ai-post-scheduler'),
			'previewNetworkError'     => __('An error occurred while generating the preview. Please check your network connection and try again.', 'ai-post-scheduler'),
			'postTypeTaxonomySupport' => AIPS_Utilities::get_selectable_post_types(),
		));
	}

	/**
	 * Enqueue assets for the voices page.
	 *
	 * @return void
	 */
	public function enqueue_voices() {
		wp_localize_script('aips-admin-script', 'aipsVoicesL10n', array(
			'addNewVoice'        => __('Add New Voice', 'ai-post-scheduler'),
			'editVoice'          => __('Edit Voice', 'ai-post-scheduler'),
			'saveVoice'          => __('Save Voice', 'ai-post-scheduler'),
			'deleteVoiceConfirm' => __('Are you sure you want to delete this voice?', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the structures page.
	 *
	 * @return void
	 */
	public function enqueue_structures() {
		wp_localize_script('aips-admin-script', 'aipsStructuresL10n', array(
			'deleteStructureConfirm' => __('Are you sure you want to delete this structure?', 'ai-post-scheduler'),
			'saveStructureFailed'    => __('Failed to save structure.', 'ai-post-scheduler'),
			'loadStructureFailed'    => __('Failed to load structure.', 'ai-post-scheduler'),
			'deleteStructureFailed'  => __('Failed to delete structure.', 'ai-post-scheduler'),
			'deleteSectionConfirm'   => __('Are you sure you want to delete this prompt section?', 'ai-post-scheduler'),
			'saveSectionFailed'      => __('Failed to save prompt section.', 'ai-post-scheduler'),
			'loadSectionFailed'      => __('Failed to load prompt section.', 'ai-post-scheduler'),
			'deleteSectionFailed'    => __('Failed to delete prompt section.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the schedule page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_schedule($hook = '') {
		wp_localize_script('aips-admin-script', 'aipsScheduleL10n', array(
			'gmtOffsetSeconds'               => (int) wp_timezone()->getOffset(new DateTime('now', wp_timezone())),
			'timezoneString'                 => wp_timezone_string(),
			'runScheduleConfirm'             => __('Are you sure you want to run this schedule now? This will immediately generate posts.', 'ai-post-scheduler'),
			'scheduleRunning'                => __('Running...', 'ai-post-scheduler'),
			'scheduleTemplateRequired'      => __('Please select a Template to continue.', 'ai-post-scheduler'),
			'addNewSchedule'                 => __('Add New Schedule', 'ai-post-scheduler'),
			'editSchedule'                   => __('Edit Schedule', 'ai-post-scheduler'),
			'cloneSchedule'                  => __('Clone Schedule', 'ai-post-scheduler'),
			'saveSchedule'                   => __('Save Schedule', 'ai-post-scheduler'),
			'scheduleSavedSuccess'          => __('Schedule saved successfully.', 'ai-post-scheduler'),
			'startNow'                       => __('Now', 'ai-post-scheduler'),
			'useDefault'                     => __('Use Default', 'ai-post-scheduler'),
			'noTitle'                        => __('No title', 'ai-post-scheduler'),
			'noneOption'                     => __('None', 'ai-post-scheduler'),
			'yes'                            => __('Yes', 'ai-post-scheduler'),
			'no'                             => __('No', 'ai-post-scheduler'),
			'runNow'                         => __('Run Now', 'ai-post-scheduler'),
			'cancel'                         => __('Cancel', 'ai-post-scheduler'),
			'yesRunNow'                      => __('Yes, run now', 'ai-post-scheduler'),
			'runNowChoice'                  => __('How should this manual run affect the schedule?', 'ai-post-scheduler'),
			'runNowIndependent'             => __('Run now, independently from schedule', 'ai-post-scheduler'),
			'runNowAndAdvance'              => __('Run next scheduled run now and advance', 'ai-post-scheduler'),
			'deleteScheduleConfirm'         => __('Are you sure you want to delete this schedule?', 'ai-post-scheduler'),
			'selectAtLeastOneSchedule'      => __('Please select at least one schedule.', 'ai-post-scheduler'),
			'deleteOneScheduleConfirm'      => __('Are you sure you want to delete 1 schedule?', 'ai-post-scheduler'),
			'deleteMultipleSchedulesConfirm' => __('Are you sure you want to delete %d schedules?', 'ai-post-scheduler'),
			'selectBulkAction'              => __('Please select a bulk action.', 'ai-post-scheduler'),
			'selectAtLeastOne'              => __('Please select at least one schedule.', 'ai-post-scheduler'),
			'failedToLoadHistory'           => __('Failed to load history.', 'ai-post-scheduler'),
			'failedToDeleteSchedules'       => __('Failed to delete schedules.', 'ai-post-scheduler'),
			'recentHistoryTitle'            => __('Recent History', 'ai-post-scheduler'),
			'andNMorePosts'                 => __('and %d more', 'ai-post-scheduler'),
			'showLess'                      => __('Show less', 'ai-post-scheduler'),
			'bulkRunFailed'                 => __('Bulk run failed.', 'ai-post-scheduler'),
			'runSchedulesNow'               => __('Run Schedules Now', 'ai-post-scheduler'),
			'runPostsConfirmSingular'       => __('This will generate an estimated 1 post. Are you sure?', 'ai-post-scheduler'),
			'runPostsConfirmPlural'         => __('This will generate an estimated %d posts. Are you sure?', 'ai-post-scheduler'),
			'runOneScheduleConfirm'         => __('This will run 1 schedule. Are you sure?', 'ai-post-scheduler'),
			'runMultipleSchedulesConfirm'   => __('This will run %d schedules. Are you sure?', 'ai-post-scheduler'),
			'deleteSchedulesHeading'        => __('Delete Schedules', 'ai-post-scheduler'),
			'noDeletableSchedulesSelected'  => __('None of the selected schedules can be deleted.', 'ai-post-scheduler'),
			'deleteSchedulesListIntro'      => __('The following schedules will be deleted:', 'ai-post-scheduler'),
			'deleteSchedulesFinalConfirm'   => __('This action cannot be undone. Continue?', 'ai-post-scheduler'),
			'deleteSchedulesSkipNotice'     => __('%d selected schedule(s) cannot be deleted and will be skipped.', 'ai-post-scheduler'),
			'scheduleStatusLoadFailed'      => __('Unable to load schedule status.', 'ai-post-scheduler'),
			'queueDepthLabel'               => __('Queue depth:', 'ai-post-scheduler'),
			'bulkPendingLabel'              => __('Bulk pending:', 'ai-post-scheduler'),
			'bulkFailedLabel'               => __('Bulk failed:', 'ai-post-scheduler'),
			'activeSchedulesLabel'          => __('Active schedules', 'ai-post-scheduler'),
			'upcomingSchedulesLabel'        => __('Upcoming in next 24h', 'ai-post-scheduler'),
			'overdueSchedulesLabel'         => __('Overdue schedules', 'ai-post-scheduler'),
			'noQueueEventsNext24h'          => __('No queue events in next 24h.', 'ai-post-scheduler'),
			'noScheduleRunsNext24h'         => __('No schedule runs in next 24h.', 'ai-post-scheduler'),
			'typeTemplateLabel'             => __('Post Generation', 'ai-post-scheduler'),
			'typeAuthorTopicLabel'          => __('Author Topics', 'ai-post-scheduler'),
			'typeAuthorPostLabel'           => __('Author Posts', 'ai-post-scheduler'),
			'typeBlueprintLabel'            => __('Blueprint', 'ai-post-scheduler'),
			'typeAuthorWorkflowLabel'       => __('Author Workflow', 'ai-post-scheduler'),
			'lastErrorDetected'             => __('Last error detected in bulk jobs.', 'ai-post-scheduler'),
			'retryPending'                  => __('Retry jobs are pending.', 'ai-post-scheduler'),
			'overdueSchedulesWarning'       => __('%d schedule(s) are overdue.', 'ai-post-scheduler'),
			'viewHistory'                   => __('View history', 'ai-post-scheduler'),
			'systemStatus'                  => __('System status', 'ai-post-scheduler'),
			'notifications'                 => __('Notifications', 'ai-post-scheduler'),
			'telemetry'                     => __('Telemetry', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the campaign wizard page.
	 *
	 * @return void
	 */
	public function enqueue_campaign_wizard() {
		wp_enqueue_script(
			'aips-admin-campaign-wizard',
			AIPS_PLUGIN_URL . 'assets/js/campaign-wizard.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-campaign-wizard', 'aipsCampaignWizardL10n', array(
			'confirmFinalize'               => __('Create this campaign and schedule it now?', 'ai-post-scheduler'),
			'created'                       => __('Campaign created.', 'ai-post-scheduler'),
			'campaignWizardAIGenerateNonce' => wp_create_nonce('aips_campaign_wizard_ai_generate'),
			'nonceAiGenerate'              => wp_create_nonce('aips_campaign_wizard_ai_generate'),
			'aiModeTitle'                   => __('Choose Campaign Setup Mode', 'ai-post-scheduler'),
			'aiModeMessage'                 => __('Would you like Guided AI Setup to prefill your campaign fields, or configure everything manually?', 'ai-post-scheduler'),
			'advancedModeTitle'             => __('Advanced Mode', 'ai-post-scheduler'),
			'aiModeButton'                  => __('Guided AI Setup', 'ai-post-scheduler'),
			'aiFormTitle'                   => __('Guided AI Setup', 'ai-post-scheduler'),
			'aiGenerateButton'              => __('Generate Campaign', 'ai-post-scheduler'),
			'aiGeneratingMessage'           => __('Generating campaign fields with AI…', 'ai-post-scheduler'),
			'aiSuccessMessage'              => __('Campaign fields filled in by AI — review and adjust as needed.', 'ai-post-scheduler'),
			'cancelButton'                  => __('Cancel', 'ai-post-scheduler'),
			'topicNicheLabel'               => __('Topic / Niche', 'ai-post-scheduler'),
			'topicNicheExample'             => __('Example: WordPress SEO for local businesses', 'ai-post-scheduler'),
			'targetAudienceLabel'           => __('Target Audience', 'ai-post-scheduler'),
			'targetAudienceExample'         => __('Example: Small business owners with limited technical knowledge', 'ai-post-scheduler'),
			'contentToneLabel'              => __('Content Tone', 'ai-post-scheduler'),
			'publishingGoalLabel'           => __('Publishing Goal', 'ai-post-scheduler'),
			'publishingGoalExample'         => __('Example: Drive organic traffic and convert readers to consultation bookings', 'ai-post-scheduler'),
			'outputStyleLabel'              => __('Template Output Style', 'ai-post-scheduler'),
			'outputStyleEducational'        => __('Educational/tutorial', 'ai-post-scheduler'),
			'outputStyleListicle'           => __('Listicle', 'ai-post-scheduler'),
			'outputStyleComparison'         => __('Comparison', 'ai-post-scheduler'),
			'outputStyleHowTo'              => __('How-to guide', 'ai-post-scheduler'),
			'outputStyleOpinion'            => __('Opinion/editorial', 'ai-post-scheduler'),
			'outputStyleFaq'                => __('FAQ-based', 'ai-post-scheduler'),
			'outputStyleCaseStudy'          => __('Case-study style', 'ai-post-scheduler'),
			'outputStyleNews'               => __('News analysis', 'ai-post-scheduler'),
			'strategyPreviewTitle'          => __('Campaign Strategy Preview', 'ai-post-scheduler'),
			'strategyPreviewMessage'        => __('Review the proposed plan before applying it to your wizard fields.', 'ai-post-scheduler'),
			'previewCampaignName'           => __('Campaign Name', 'ai-post-scheduler'),
			'previewAudience'               => __('Who this campaign is for', 'ai-post-scheduler'),
			'previewContentAngle'           => __('Content angle', 'ai-post-scheduler'),
			'previewCadence'                => __('Posting cadence', 'ai-post-scheduler'),
			'previewTone'                   => __('Recommended tone', 'ai-post-scheduler'),
			'previewTemplateStyle'          => __('Template style', 'ai-post-scheduler'),
			'previewIdeas'                  => __('Sample article ideas', 'ai-post-scheduler'),
			'previewRisks'                  => __('Risks / assumptions', 'ai-post-scheduler'),
			'previewAcceptAll'              => __('Accept all', 'ai-post-scheduler'),
			'previewRegenerate'             => __('Regenerate', 'ai-post-scheduler'),
			'previewEditAnswers'            => __('Edit answers', 'ai-post-scheduler'),
			'previewApplySelected'          => __('Apply selectively', 'ai-post-scheduler'),
			'previewSelectHeading'          => __('Select fields to apply', 'ai-post-scheduler'),
			'previewApplyButton'            => __('Apply selected fields', 'ai-post-scheduler'),
			'previewSelectRequired'         => __('Select at least one field to apply.', 'ai-post-scheduler'),
			'promptTemplateLabel'           => __('Prompt Template', 'ai-post-scheduler'),
			'titlePromptLabel'              => __('Title Prompt', 'ai-post-scheduler'),
			'reviewPolicyLabel'             => __('Review Policy', 'ai-post-scheduler'),
			'campaignModeLabel'             => __('Campaign Mode', 'ai-post-scheduler'),
			'previewNoData'                 => __('No preview details were returned by AI.', 'ai-post-scheduler'),
			'regeneratingMessage'           => __('Regenerating campaign strategy…', 'ai-post-scheduler'),
			'preferredFrequencyLabel'       => __('Preferred Post Frequency', 'ai-post-scheduler'),
			'postTypeLabel'                 => __('Post Type', 'ai-post-scheduler'),
			'toneConversational'            => __('Conversational', 'ai-post-scheduler'),
			'toneProfessional'              => __('Professional', 'ai-post-scheduler'),
			'toneTechnical'                 => __('Technical', 'ai-post-scheduler'),
			'toneFriendly'                  => __('Friendly', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the campaigns page.
	 *
	 * @return void
	 */
	public function enqueue_campaigns() {
		wp_enqueue_style(
			'aips-campaigns-style',
			AIPS_PLUGIN_URL . 'assets/css/campaigns.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-campaigns',
			AIPS_PLUGIN_URL . 'assets/js/campaigns.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-campaigns', 'aipsCampaignsL10n', array(
			'confirmDuplicate' => __('Duplicate this campaign? The copy will be created in a paused state.', 'ai-post-scheduler'),
			'confirmArchive'   => __('Archive this campaign? It will be hidden from the active campaigns list.', 'ai-post-scheduler'),
			'confirmDelete'    => __('Delete this campaign? This removes the campaign and its owned template/schedule rows.', 'ai-post-scheduler'),
			'errorToggle'      => __('Failed to update campaign.', 'ai-post-scheduler'),
			'errorDuplicate'   => __('Failed to duplicate campaign.', 'ai-post-scheduler'),
			'errorArchive'     => __('Failed to archive campaign.', 'ai-post-scheduler'),
			'errorRestore'     => __('Failed to restore campaign.', 'ai-post-scheduler'),
			'errorDelete'      => __('Failed to delete campaign.', 'ai-post-scheduler'),
			'errorNetwork'     => __('Network error. Please try again.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the research page.
	 *
	 * @return void
	 */
	public function enqueue_research() {
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
			array('aips-admin-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_script(
			'aips-admin-content-auditor',
			AIPS_PLUGIN_URL . 'assets/js/admin-content-auditor.js',
			array('jquery', 'aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-content-auditor', 'aipsAuditorL10n', array(
			'nonce'                  => wp_create_nonce('aips_ajax_nonce'),
			'selectAtLeastOneModule' => __('Please select at least one audit module.', 'ai-post-scheduler'),
			'step1Text'              => __('Step 1/4: Ingesting and profiling content library...', 'ai-post-scheduler'),
			'step2Text'              => __('Step 2/4: Constructing link graph & entity clusters...', 'ai-post-scheduler'),
			'step3Text'              => __('Step 3/4: Running AI intelligence modules...', 'ai-post-scheduler'),
			'step4Text'              => __('Step 4/4: Synthesizing health scorecard & saving...', 'ai-post-scheduler'),
			'completeText'           => __('Audit complete!', 'ai-post-scheduler'),
			'runningModule'          => __('Analyzing', 'ai-post-scheduler'),
			'auditError'             => __('An error occurred during the audit.', 'ai-post-scheduler'),
			'topicAddedSuccess'      => __('Topic successfully added to Author Persona!', 'ai-post-scheduler'),
			'confirmGeneratePost'    => __('Generate post immediately for this topic?', 'ai-post-scheduler'),
			'badgeGood'              => __('Strong Standing', 'ai-post-scheduler'),
			'badgeWarning'           => __('Moderate Gaps', 'ai-post-scheduler'),
			'badgeDanger'            => __('Action Required', 'ai-post-scheduler'),
			'auditedAt'              => __('Audited:', 'ai-post-scheduler'),
			'noGapsFound'            => __('No major content gaps identified.', 'ai-post-scheduler'),
			'noConflictsFound'       => __('No keyword cannibalization conflicts detected across your published articles.', 'ai-post-scheduler'),
			'noDecayFound'           => __('All evaluated content is fresh and within healthy word count thresholds.', 'ai-post-scheduler'),
			'noLinkGapsFound'        => __('Internal link connectivity is strong with no orphan articles.', 'ai-post-scheduler'),
			'noTrendsFound'          => __('No new external industry trends uncovered from active sources.', 'ai-post-scheduler'),
			'thTopic'                => __('Missing Topic', 'ai-post-scheduler'),
			'thPriority'             => __('Priority', 'ai-post-scheduler'),
			'thType'                 => __('Type', 'ai-post-scheduler'),
			'thIntent'               => __('Intent', 'ai-post-scheduler'),
			'thReason'               => __('Strategic Reason & Angle', 'ai-post-scheduler'),
			'thActions'              => __('Actions', 'ai-post-scheduler'),
			'thPost'                 => __('Post Title', 'ai-post-scheduler'),
			'thUrgency'              => __('Urgency', 'ai-post-scheduler'),
			'thRefreshPlan'          => __('Refresh Checklist & Actions', 'ai-post-scheduler'),
			'thOrphan'               => __('Orphan Article', 'ai-post-scheduler'),
			'thTargetSource'         => __('Suggested Source Article', 'ai-post-scheduler'),
			'thAnchorRationale'      => __('Anchor & Silo Rationale', 'ai-post-scheduler'),
			'thTrend'                => __('Industry Trend', 'ai-post-scheduler'),
			'thSourceSnippet'        => __('Source Evidence', 'ai-post-scheduler'),
			'thAngle'                => __('Recommended Angle', 'ai-post-scheduler'),
		));

		wp_enqueue_script(
			'aips-admin-planner',
			AIPS_PLUGIN_URL . 'assets/js/admin-planner.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-planner', 'aipsPlannerL10n', array(
			'confirmClear' => __('Are you sure you want to clear all topics?', 'ai-post-scheduler'),
		));

		wp_localize_script('aips-admin-research', 'aipsResearchL10n', array(
			'topicsSaved'              => __('topics saved for', 'ai-post-scheduler'),
			'topTopics'                => __('Top 5 Topics:', 'ai-post-scheduler'),
			'noTopicsFound'            => __('No topics match your search criteria.', 'ai-post-scheduler'),
			'noTopicsFoundTitle'       => __('No Topics Found', 'ai-post-scheduler'),
			'clearFilters'             => __('Clear Filters', 'ai-post-scheduler'),
			'libraryEmpty'             => __('Your research library is empty.', 'ai-post-scheduler'),
			'startResearch'            => __('Start Research', 'ai-post-scheduler'),
			'clearSearch'              => __('Clear Search', 'ai-post-scheduler'),
			'deleteTopicConfirm'       => __('Delete this topic?', 'ai-post-scheduler'),
			'selectTopicSchedule'      => __('Please select at least one topic to schedule.', 'ai-post-scheduler'),
			'researchError'            => __('An error occurred during research.', 'ai-post-scheduler'),
			'schedulingError'          => __('An error occurred during scheduling.', 'ai-post-scheduler'),
			'delete'                   => __('Delete', 'ai-post-scheduler'),
			'generateSelected'         => __('Generate', 'ai-post-scheduler'),
			'generateIdeas'            => __('Generate Ideas', 'ai-post-scheduler'),
			'generatingIdeas'          => __('Generating...', 'ai-post-scheduler'),
			'generatingPostsTitle'     => __('Generating Posts', 'ai-post-scheduler'),
			'generatingPostsMessage'   => __('Please wait while your posts are being generated. This may take a few minutes.', 'ai-post-scheduler'),
			'generateError'            => __('Error generating selected topics.', 'ai-post-scheduler'),
			'loadingPosts'             => __('Loading posts...', 'ai-post-scheduler'),
			'errorLoadingPosts'        => __('Error loading posts.', 'ai-post-scheduler'),
			'noPostsFound'             => __('No posts found.', 'ai-post-scheduler'),
			'postsGeneratedFrom'       => __('Posts Generated from Topic', 'ai-post-scheduler'),
			'editPost'                 => __('Edit Post', 'ai-post-scheduler'),
			'viewPost'                 => __('View Post', 'ai-post-scheduler'),
			'notPublished'             => __('Not published', 'ai-post-scheduler'),
			'postId'                   => __('Post ID', 'ai-post-scheduler'),
			'postTitle'                => __('Post Title', 'ai-post-scheduler'),
			'dateGenerated'            => __('Date Generated', 'ai-post-scheduler'),
			'datePublished'            => __('Date Published', 'ai-post-scheduler'),
			'actions'                  => __('Actions', 'ai-post-scheduler'),
			'statusNew'                => __('New', 'ai-post-scheduler'),
			'statusScheduled'          => __('Scheduled', 'ai-post-scheduler'),
			'statusGenerated'          => __('Generated', 'ai-post-scheduler'),
			'confirmGenerationTitle'   => __('Confirm Generation', 'ai-post-scheduler'),
			'confirmGenerationMessage' => __('Generate %d post(s) immediately from selected topics?', 'ai-post-scheduler'),
			'cancelButton'             => __('Cancel', 'ai-post-scheduler'),
			'generateNowButton'        => __('Generate Now', 'ai-post-scheduler'),
			'generatingButton'         => __('Generating...', 'ai-post-scheduler'),
			'selectTemplateRequired'   => __('Please select a template before generating.', 'ai-post-scheduler'),
		));
	}
}
