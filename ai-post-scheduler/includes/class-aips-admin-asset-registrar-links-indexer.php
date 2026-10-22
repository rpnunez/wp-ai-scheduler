<?php
/**
 * Admin Asset Registrar: Links & Content Indexer
 *
 * Handles enqueueing for Internal Links, Content Indexer, Link Report, Broken Links,
 * Redirects, Silos, Consolidation (Cannibalization Shield), Link Rules, and Generated Posts.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Registrar_Links_Indexer
 */
class AIPS_Admin_Asset_Registrar_Links_Indexer {

	/**
	 * Enqueue assets for the generated-posts page.
	 *
	 * @return void
	 */
	public function enqueue_generated_posts() {
		wp_enqueue_script(
			'aips-admin-view-session',
			AIPS_PLUGIN_URL . 'assets/js/admin-view-session.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);

		wp_enqueue_style(
			'aips-admin-post-review',
			AIPS_PLUGIN_URL . 'assets/css/admin-post-review.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-post-review',
			AIPS_PLUGIN_URL . 'assets/js/admin-post-review.js',
			array('aips-admin-script'),
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

		$config = AIPS_Config::get_instance();
		$client_threshold = (int) $config->get_option('generated_posts_log_threshold_client', 20);
		wp_localize_script('aips-admin-generated-posts', 'aipsGeneratedPostsConfig', array(
			'clientLogThreshold' => $client_threshold,
			'siteUrl'            => home_url(),
		));

		wp_localize_script('aips-admin-post-review', 'aipsPostReviewL10n', array(
			'ajaxUrl'               => admin_url('admin-ajax.php'),
			'nonce'                 => wp_create_nonce('aips_ajax_nonce'),
			'reloading'             => __('Reloading...', 'ai-post-scheduler'),
			'confirmPublish'        => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
			'confirmBulkPublish'    => __('Are you sure you want to publish %d selected post(s)?', 'ai-post-scheduler'),
			'confirmDelete'         => __('Are you sure you want to delete this post? This action cannot be undone.', 'ai-post-scheduler'),
			'confirmBulkDelete'     => __('Are you sure you want to delete %d selected post(s)? This action cannot be undone.', 'ai-post-scheduler'),
			'confirmRegenerate'     => __('Are you sure you want to regenerate this post? The current post will be deleted.', 'ai-post-scheduler'),
			'confirmBulkRegenerate' => __('Are you sure you want to regenerate %d selected post(s)? The current posts will be deleted.', 'ai-post-scheduler'),
			'publishSuccess'        => __('Post published successfully!', 'ai-post-scheduler'),
			'bulkPublishSuccess'    => __('%d posts published successfully!', 'ai-post-scheduler'),
			'bulkPublishQueued'     => __('Publishing %d posts in the background. They will go live shortly.', 'ai-post-scheduler'),
			'deleteSuccess'         => __('Post deleted successfully!', 'ai-post-scheduler'),
			'bulkDeleteSuccess'     => __('%d posts deleted successfully!', 'ai-post-scheduler'),
			'regenerateSuccess'     => __('Post regeneration started!', 'ai-post-scheduler'),
			'bulkRegenerateSuccess' => __('%d posts regeneration started!', 'ai-post-scheduler'),
			'publishError'          => __('Failed to publish post.', 'ai-post-scheduler'),
			'deleteError'           => __('Failed to delete post.', 'ai-post-scheduler'),
			'regenerateError'       => __('Failed to regenerate post.', 'ai-post-scheduler'),
			'loadingError'          => __('Failed to load draft posts.', 'ai-post-scheduler'),
			'loading'               => __('Loading...', 'ai-post-scheduler'),
			'publish'               => __('Publish', 'ai-post-scheduler'),
			'deleting'              => __('Deleting...', 'ai-post-scheduler'),
			'delete'                => __('Delete', 'ai-post-scheduler'),
			'regenerating'          => __('Regenerating...', 'ai-post-scheduler'),
			'regenerate'            => __('Re-generate', 'ai-post-scheduler'),
			'noPostsSelected'       => __('Please select at least one post.', 'ai-post-scheduler'),
			'noDraftPosts'          => __('No Draft Posts', 'ai-post-scheduler'),
			'noDraftPostsDesc'      => __('There are no draft posts waiting for review.', 'ai-post-scheduler'),
			'previewTitle'          => __('Post Preview', 'ai-post-scheduler'),
			'loadingPreview'        => __('Loading preview...', 'ai-post-scheduler'),
			'previewError'          => __('Failed to load preview.', 'ai-post-scheduler'),
		));

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
			'ajaxUrl'              => admin_url('admin-ajax.php'),
			'nonce'                => wp_create_nonce('aips_ajax_nonce'),
			'regenerate'           => __('Re-generate', 'ai-post-scheduler'),
			'regenerating'         => __('Regenerating...', 'ai-post-scheduler'),
			'regenerateAll'        => __('Regenerate All', 'ai-post-scheduler'),
			'regeneratingAll'      => __('Regenerating all components...', 'ai-post-scheduler'),
			'regenerateAllSuccess' => __('Components regenerated successfully.', 'ai-post-scheduler'),
			'regenerateAllError'   => __('Failed to regenerate all components.', 'ai-post-scheduler'),
			'regenerateSuccess'    => __('Component regenerated successfully!', 'ai-post-scheduler'),
			'regenerateError'      => __('Failed to regenerate component.', 'ai-post-scheduler'),
			'save'                 => __('Save Changes', 'ai-post-scheduler'),
			'saving'               => __('Saving...', 'ai-post-scheduler'),
			'saveSuccess'          => __('Post updated successfully!', 'ai-post-scheduler'),
			'saveError'            => __('Failed to update post.', 'ai-post-scheduler'),
			'loadError'            => __('Failed to load post components.', 'ai-post-scheduler'),
			'confirmClose'         => __('You have unsaved changes. Are you sure you want to close?', 'ai-post-scheduler'),
			'noChanges'            => __('No changes to save.', 'ai-post-scheduler'),
			'revisionAiGenerated'  => __('AI Generated', 'ai-post-scheduler'),
			'revisionManualEdit'   => __('Manual Edit', 'ai-post-scheduler'),
			'revisionRestored'     => __('Restored Version', 'ai-post-scheduler'),
			'revisionUnknown'      => __('Revision', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the internal-links page.
	 *
	 * @return void
	 */
	public function enqueue_internal_links() {
		wp_enqueue_script(
			'aips-admin-internal-links',
			AIPS_PLUGIN_URL . 'assets/js/admin-internal-links.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script', 'aips-background-processes'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-internal-links', 'aipsInternalLinksL10n', array(
			'nonce'                   => wp_create_nonce('aips_ajax_nonce'),
			'confirmDelete'           => __('Delete this suggestion? This cannot be undone.', 'ai-post-scheduler'),
			'confirmClearIndex'       => __('Clear the entire index and all suggestions? This cannot be undone.', 'ai-post-scheduler'),
			'indexingStarted'         => __('Indexing started. Posts will be processed in the background.', 'ai-post-scheduler'),
			'indexingNotAvailable'    => __('Embeddings are not available. Please configure AI Engine.', 'ai-post-scheduler'),
			'generating'              => __('Generating…', 'ai-post-scheduler'),
			'reindexing'              => __('Re-indexing…', 'ai-post-scheduler'),
			'loading'                 => __('Loading…', 'ai-post-scheduler'),
			'noSuggestions'           => __('No suggestions found. Run indexing and generate suggestions to see results.', 'ai-post-scheduler'),
			'errorLoading'            => __('Error loading suggestions.', 'ai-post-scheduler'),
			'errorDeleting'           => __('Error deleting suggestion.', 'ai-post-scheduler'),
			'statusUpdated'           => __('Status updated.', 'ai-post-scheduler'),
			'anchorUpdated'           => __('Anchor text updated.', 'ai-post-scheduler'),
			'statusUpdateFailed'      => __('Failed to update status.', 'ai-post-scheduler'),
			'anchorUpdateFailed'      => __('Failed to update anchor text.', 'ai-post-scheduler'),
			'invalidPostId'           => __('Please enter a valid post ID.', 'ai-post-scheduler'),
			'requestFailed'           => __('Request failed. Please try again.', 'ai-post-scheduler'),
			'acceptAction'            => __('Accept suggestion', 'ai-post-scheduler'),
			'rejectAction'            => __('Reject suggestion', 'ai-post-scheduler'),
			'accepted'                => __('Accepted', 'ai-post-scheduler'),
			'rejected'                => __('Rejected', 'ai-post-scheduler'),
			'pending'                 => __('Pending', 'ai-post-scheduler'),
			'inserted'                => __('Inserted', 'ai-post-scheduler'),
			'reverted'                => __('Undone', 'ai-post-scheduler'),
			'insertLink'              => __('Insert Link', 'ai-post-scheduler'),
			'loadingFailed'           => __('Failed to load post data. Please try again.', 'ai-post-scheduler'),
			'noContent'               => __('(No content)', 'ai-post-scheduler'),
			'noInsertSuggestions'     => __('No accepted suggestions found for this post.', 'ai-post-scheduler'),
			'insertBtn'               => __('Get Suggestions', 'ai-post-scheduler'),
			'findingLocations'        => __('Finding insertion locations…', 'ai-post-scheduler'),
			'locationsFailed'         => __('Failed to find insertion locations. Please try again.', 'ai-post-scheduler'),
			'noLocations'             => __('No valid insertion locations could be shown for this suggestion.', 'ai-post-scheduler'),
			'invalidLocationsHint'    => __('The AI responded, but its suggestions rewrote the source text instead of bracketing an existing phrase.', 'ai-post-scheduler'),
			'insertionLocationsLabel' => __('Insertion Locations', 'ai-post-scheduler'),
			'returnedCountLabel'      => __('Showing %1$d valid of %2$d AI suggestions', 'ai-post-scheduler'),
			'zeroSuggestionsReturned' => __('0 valid suggestions', 'ai-post-scheduler'),
			'aiSuggestionsReturned'   => __('AI returned %d suggestion(s)', 'ai-post-scheduler'),
			'reasonLabel'             => __('Reason', 'ai-post-scheduler'),
			'originalSnippetLabel'    => __('Original text', 'ai-post-scheduler'),
			'withLinkLabel'           => __('With link inserted', 'ai-post-scheduler'),
			'applyBtn'                => __('Apply', 'ai-post-scheduler'),
			'applying'                => __('Applying…', 'ai-post-scheduler'),
			'applied'                 => __('Link inserted successfully.', 'ai-post-scheduler'),
			'applyFailed'             => __('Failed to apply insertion. Please try again.', 'ai-post-scheduler'),
			'editAnchorText'          => __('Edit anchor text', 'ai-post-scheduler'),
			'deleteSuggestion'        => __('Delete suggestion', 'ai-post-scheduler'),
			'tipAccept'               => __('Accept: approve this link so it can be inserted into the source post.', 'ai-post-scheduler'),
			'tipReject'               => __('Reject: dismiss this suggestion and keep a record, so it is not suggested again.', 'ai-post-scheduler'),
			'tipInsert'               => __('Insert the link into the source post.', 'ai-post-scheduler'),
			'tipEdit'                 => __('Edit the anchor text.', 'ai-post-scheduler'),
			'tipDelete'               => __('Delete: remove this suggestion entirely. It may be suggested again the next time suggestions are generated.', 'ai-post-scheduler'),
			'noAnchor'                => __('No anchor text yet', 'ai-post-scheduler'),
			'chipKeyword'             => __('Keyword match', 'ai-post-scheduler'),
			'chipKeywordTip'          => __('Found by the anchor phrase appearing in the source post. No semantic similarity score was calculated for this pair.', 'ai-post-scheduler'),
			'chipSimilarityTip'       => __('Semantic similarity between the two posts.', 'ai-post-scheduler'),
			'chipInbound'             => __('Inbound', 'ai-post-scheduler'),
			'chipInboundTip'          => __('Suggested to give the target post (often an orphan) inbound links.', 'ai-post-scheduler'),
			'chipOutbound'            => __('Outbound', 'ai-post-scheduler'),
			'chipOutboundTip'         => __('Suggested as a link going out of the source post.', 'ai-post-scheduler'),
			'anchorLabel'             => __('Anchor', 'ai-post-scheduler'),
			'confirmBulkDelete'       => __('Delete the selected suggestions? This cannot be undone.', 'ai-post-scheduler'),
			'bulkDeleteTitle'         => __('Delete Suggestions', 'ai-post-scheduler'),
			'bulkFailed'              => __('The bulk action failed. Please try again.', 'ai-post-scheduler'),
			'bulkAccepted'            => __('Accepted %d suggestion(s).', 'ai-post-scheduler'),
			'bulkRejected'            => __('Rejected %d suggestion(s).', 'ai-post-scheduler'),
			'bulkDeleted'             => __('Deleted %d suggestion(s).', 'ai-post-scheduler'),
			'bulkSkipped'             => __('%d selected suggestion(s) were skipped because the action does not apply to them.', 'ai-post-scheduler'),
			'selectedCount'           => __('%d selected', 'ai-post-scheduler'),
			'selectRow'               => __('Select suggestion', 'ai-post-scheduler'),
			'cancel'                  => __('Cancel', 'ai-post-scheduler'),
			'delete'                  => __('Delete', 'ai-post-scheduler'),
			'optionLabel'             => __('Option', 'ai-post-scheduler'),
			'updatePostBtn'           => __('Update Post with Inserted Links', 'ai-post-scheduler'),
			'updating'                => __('Updating…', 'ai-post-scheduler'),
			'updateFailed'            => __('Failed to update post. Please try again.', 'ai-post-scheduler'),
			'editInsertedLink'        => __('Edit anchor', 'ai-post-scheduler'),
			'removeInsertedLink'      => __('Remove link', 'ai-post-scheduler'),
			'alreadyApplied'          => __('This suggestion has already been applied to the preview.', 'ai-post-scheduler'),
			'snippetNotFound'         => __('The selected text was not found in the content preview.', 'ai-post-scheduler'),
			'pendingCountSingle'      => __('%d pending insertion', 'ai-post-scheduler'),
			'pendingCountPlural'      => __('%d pending insertions', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the Content Indexer page.
	 *
	 * @return void
	 */
	public function enqueue_content_indexer() {
		wp_enqueue_style(
			'aips-content-indexer-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-content-indexer.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-content-indexer-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-content-indexer.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-content-indexer-script',
			'aipsContentIndexerL10n',
			array(
				'nonce'               => wp_create_nonce('aips_ajax_nonce'),
				'startScan'           => __('Start Scan', 'ai-post-scheduler'),
				'resumeScan'          => __('Resume Scan', 'ai-post-scheduler'),
				'indexingPaused'      => __('Indexing Paused', 'ai-post-scheduler'),
				'indexingComplete'    => __('Content indexing complete!', 'ai-post-scheduler'),
				'confirmClear'        => __('Are you sure you want to clear all semantic embeddings and relationships? This will reset indexing coverage.', 'ai-post-scheduler'),
				'indexed'             => __('Indexed', 'ai-post-scheduler'),
				'pendingIndex'        => __('Pending Index', 'ai-post-scheduler'),
				'authorTopic'         => __('Author Topic', 'ai-post-scheduler'),
				'post'                => __('Post', 'ai-post-scheduler'),
				'noAuditDuplicates'   => __('No high-similarity duplicate or cannibalizing clusters found. Great job!', 'ai-post-scheduler'),
				'editSource'          => __('Edit Source', 'ai-post-scheduler'),
				'editTarget'          => __('Edit Target', 'ai-post-scheduler'),
				'cannibalizationRisk' => __('Cannibalization Risk', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for the Link Report tab of the Content hub.
	 *
	 * @return void
	 */
	public function enqueue_link_report() {
		wp_enqueue_style(
			'aips-link-report-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-link-report.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-link-report-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-link-report.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-link-report-script',
			'aipsLinkReportL10n',
			array(
				'nonce'                 => wp_create_nonce('aips_ajax_nonce'),
				'loading'               => __('Loading…', 'ai-post-scheduler'),
				'loadError'             => __('Could not load the link report.', 'ai-post-scheduler'),
				'pageInfo'              => __('Page %1$d of %2$d (%3$d posts)', 'ai-post-scheduler'),
				'internal'              => __('Internal', 'ai-post-scheduler'),
				'external'              => __('External', 'ai-post-scheduler'),
				'externalNofollow'      => __('External (nofollow)', 'ai-post-scheduler'),
				'broken'                => __('Broken', 'ai-post-scheduler'),
				'noInbound'             => __('No other post links here yet — this post is an orphan.', 'ai-post-scheduler'),
				'noOutbound'            => __('This post contains no links.', 'ai-post-scheduler'),
				'rebuildError'          => __('The link scan request failed. Please try again.', 'ai-post-scheduler'),
				'rebuildDone'           => __('Link scan complete. The report is up to date.', 'ai-post-scheduler'),
				'scanRunning'           => __('Scanning posts for links…', 'ai-post-scheduler'),
				'scanPaused'            => __('Link scan paused', 'ai-post-scheduler'),
				'confirmCancelTitle'    => __('Cancel link scan', 'ai-post-scheduler'),
				'confirmCancel'         => __('Stop the scan now? Posts scanned so far stay in the index; you can start a new scan later.', 'ai-post-scheduler'),
				'keepScanning'          => __('Keep scanning', 'ai-post-scheduler'),
				'cancelScan'            => __('Cancel scan', 'ai-post-scheduler'),
				'cancel'                => __('Cancel', 'ai-post-scheduler'),
				'suggestionsPending'    => __('%d suggested', 'ai-post-scheduler'),
				'suggestError'          => __('Could not load link suggestions.', 'ai-post-scheduler'),
				'noSuggestions'         => __('No suitable posts found. Posts need related content (embeddings) or a phrase from this post\'s title in their text.', 'ai-post-scheduler'),
				'noAnchor'              => __('(no anchor text found — link manually)', 'ai-post-scheduler'),
				'autolinkError'         => __('The auto-link request failed. Please try again.', 'ai-post-scheduler'),
				'runRunning'            => __('Auto-linking…', 'ai-post-scheduler'),
				'runRunningDry'         => __('Finding link suggestions (dry run)…', 'ai-post-scheduler'),
				'runPaused'             => __('Auto-link run paused', 'ai-post-scheduler'),
				'runProgress'           => __('%1$d of %2$d posts processed · %3$d links inserted · %4$d suggestions for review.', 'ai-post-scheduler'),
				'reviewSuggestions'     => __('Review Suggestions (%d)', 'ai-post-scheduler'),
				'confirmRunCancelTitle' => __('Cancel auto-link run', 'ai-post-scheduler'),
				'confirmRunCancel'      => __('Stop this run now? Links already inserted stay in place; you can undo the whole run from the history below.', 'ai-post-scheduler'),
				'keepRunning'           => __('Keep running', 'ai-post-scheduler'),
				'cancelRun'             => __('Cancel run', 'ai-post-scheduler'),
				'confirmUndoRunTitle'   => __('Undo auto-link run', 'ai-post-scheduler'),
				'confirmUndoRun'        => __('Remove every link this run inserted and restore the original text? Posts edited since the run are skipped and listed.', 'ai-post-scheduler'),
				'undoRun'               => __('Undo run', 'ai-post-scheduler'),
				'noRuns'                => __('No auto-link runs yet.', 'ai-post-scheduler'),
				'scopeOrphans'          => __(' · Orphans', 'ai-post-scheduler'),
				'scopeLow'              => __(' · Fewer than 3 inbound links', 'ai-post-scheduler'),
				'scopePublish'          => __(' · New post: %s', 'ai-post-scheduler'),
				'scopeSilo'             => __(' · Silo: %s', 'ai-post-scheduler'),
				'dryRun'                => __('Dry run', 'ai-post-scheduler'),
				'statusRunning'         => __('Running', 'ai-post-scheduler'),
				'statusPaused'          => __('Paused', 'ai-post-scheduler'),
				'statusCompleted'       => __('Completed', 'ai-post-scheduler'),
				'statusCancelled'       => __('Cancelled', 'ai-post-scheduler'),
				'statusUndone'          => __('Undone', 'ai-post-scheduler'),
				'progress'              => __('%1$d of %2$d posts processed.', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for the Broken Links tab of the Content hub.
	 *
	 * @return void
	 */
	public function enqueue_broken_links() {
		wp_enqueue_style(
			'aips-link-report-style',
			AIPS_PLUGIN_URL . 'assets/css/admin-link-report.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-broken-links-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-broken-links.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-broken-links-script',
			'aipsBrokenLinksL10n',
			array(
				'nonce'            => wp_create_nonce('aips_ajax_nonce'),
				'pageInfo'         => __('Page %1$d of %2$d (%3$d posts)', 'ai-post-scheduler'),
				'brokenError'      => __('The broken-link request failed. Please try again.', 'ai-post-scheduler'),
				'noBroken'         => __('No broken internal links. Nice!', 'ai-post-scheduler'),
				'noAnchorText'     => __('(no anchor text)', 'ai-post-scheduler'),
				'occurrences'      => __('%d links to this URL in the post', 'ai-post-scheduler'),
				'suggestionOption' => __('%1$s (%2$d%% match)', 'ai-post-scheduler'),
				'choosePost'       => __('Choose another post…', 'ai-post-scheduler'),
				'removeLink'       => __('Remove link, keep text', 'ai-post-scheduler'),
				'chooseFirst'      => __('Choose a post or "Remove link" first.', 'ai-post-scheduler'),
				'fixRepointed'     => __('Re-pointed', 'ai-post-scheduler'),
				'fixUnlinked'      => __('Link removed', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for the Redirects tab of the Content hub.
	 *
	 * @return void
	 */
	public function enqueue_redirects() {
		wp_enqueue_script(
			'aips-redirects-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-redirects.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-redirects-script',
			'aipsRedirectsL10n',
			array(
				'nonce'               => wp_create_nonce('aips_ajax_nonce'),
				'error'               => __('The redirects request failed. Please try again.', 'ai-post-scheduler'),
				'noRedirects'         => __('No redirects yet.', 'ai-post-scheduler'),
				'pageInfo'            => __('Page %1$d of %2$d (%3$d redirects)', 'ai-post-scheduler'),
				'originConsolidation' => __('(consolidation)', 'ai-post-scheduler'),
				'gone'                => __('410 Gone', 'ai-post-scheduler'),
				'active'              => __('Active', 'ai-post-scheduler'),
				'disabled'            => __('Disabled', 'ai-post-scheduler'),
				'enable'              => __('Enable', 'ai-post-scheduler'),
				'disable'             => __('Disable', 'ai-post-scheduler'),
				'cancel'              => __('Cancel', 'ai-post-scheduler'),
				'deleteRedirect'      => __('Delete redirect', 'ai-post-scheduler'),
				'confirmDeleteTitle'  => __('Delete redirect', 'ai-post-scheduler'),
				'confirmDelete'       => __('Delete this redirect? It is also removed from the plugin serving it, and visitors to the old URL will get a 404.', 'ai-post-scheduler'),
				'confirmMoveTitle'    => __('Move existing redirects', 'ai-post-scheduler'),
				'confirmMove'         => __('Move every AI Post Scheduler redirect to the selected provider? Each one is removed from the plugin that serves it now and re-created in the new one.', 'ai-post-scheduler'),
				'moveRedirects'       => __('Move redirects', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for the Silos tab of the Content hub.
	 *
	 * @return void
	 */
	public function enqueue_silos() {
		wp_enqueue_script(
			'aips-silos-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-silos.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-silos-script',
			'aipsSilosL10n',
			array(
				'nonce'        => wp_create_nonce('aips_ajax_nonce'),
				'error'        => __('The silos request failed. Please try again.', 'ai-post-scheduler'),
				'noSilos'      => __('No silos yet. Confirm a pillar for a cluster below to create one.', 'ai-post-scheduler'),
				'noClusters'   => __('No topic clusters yet. Clusters are found from post embeddings: index your posts in Content Indexer, then click Re-detect Clusters.', 'ai-post-scheduler'),
				'allConfirmed' => __('Every cluster has a confirmed pillar.', 'ai-post-scheduler'),
				'health'       => __('Silo health %d%%', 'ai-post-scheduler'),
				'upLabel'      => __('%1$d of %2$d articles link to the pillar', 'ai-post-scheduler'),
				'downLabel'    => __('The pillar reaches %1$d of %2$d articles (%3$d in its text, %4$d in the "In this guide" list)', 'ai-post-scheduler'),
				'yes'          => __('Yes', 'ai-post-scheduler'),
				'missing'      => __('Missing', 'ai-post-scheduler'),
				'down'         => array(
					'text'  => __('In its text', 'ai-post-scheduler'),
					'guide' => __('In the guide list', 'ai-post-scheduler'),
					'none'  => __('Not linked', 'ai-post-scheduler'),
				),
				'articles'     => __('%d articles', 'ai-post-scheduler'),
				'reasons'      => __('%1$d inbound links · %2$d words · %3$d%% topic match', 'ai-post-scheduler'),
				'showArticles' => __('Show articles', 'ai-post-scheduler'),
				'hideArticles' => __('Hide articles', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for consolidating overlapping posts (Cannibalization Shield).
	 *
	 * @return void
	 */
	public function enqueue_consolidation() {
		wp_enqueue_script(
			'aips-consolidation-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-consolidation.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-consolidation-script',
			'aipsConsolidationL10n',
			array(
				'nonce'            => wp_create_nonce('aips_ajax_nonce'),
				'error'            => __('The consolidation request failed. Please try again.', 'ai-post-scheduler'),
				'postMeta'         => __('Published %1$s · %2$d words', 'ai-post-scheduler'),
				'mergeDiscarded'   => __('The merged draft was discarded because it was written for the other post. Generate it again.', 'ai-post-scheduler'),
				'summaryHeading'   => __('Consolidating will:', 'ai-post-scheduler'),
				'summaryDraft'     => __('Move "%s" to draft.', 'ai-post-scheduler'),
				'summaryRedirect'  => __('Redirect %1$s to "%2$s" (301, served by %3$s).', 'ai-post-scheduler'),
				'summaryLinks'     => __('Re-point internal links to the retired post so they link to "%s".', 'ai-post-scheduler'),
				'summaryNotify'    => __('Send a "Posts Consolidated" notification (set its channels under Settings → Notifications).', 'ai-post-scheduler'),
				'summaryRevision'  => __('Save the merged draft as a revision of the kept post. The live post does not change until you restore that revision.', 'ai-post-scheduler'),
				'summaryRewrite'   => __('Replace the kept post\'s content with the merged draft now.', 'ai-post-scheduler'),
				'confirmRunTitle'  => __('Consolidate posts', 'ai-post-scheduler'),
				'confirmRun'       => __('Consolidate these posts? You can undo this from Recent Consolidations.', 'ai-post-scheduler'),
				'consolidate'      => __('Consolidate', 'ai-post-scheduler'),
				'confirmUndoTitle' => __('Undo consolidation', 'ai-post-scheduler'),
				'confirmUndo'      => __('Republish the retired post, remove its redirect, restore the re-pointed links and undo any merged content?', 'ai-post-scheduler'),
				'undo'             => __('Undo', 'ai-post-scheduler'),
				'cancel'           => __('Cancel', 'ai-post-scheduler'),
				'noConsolidations' => __('No consolidations yet. Use Consolidate on a post pair in the audit above.', 'ai-post-scheduler'),
				'defaultMergeMode' => (string) AIPS_Config::get_instance()->get_option('aips_duplicate_default_merge_mode', 'revision'),
				'contentModes'     => array(
					'none'     => __('Not merged', 'ai-post-scheduler'),
					'revision' => __('Saved as revision', 'ai-post-scheduler'),
					'rewrite'  => __('Rewritten', 'ai-post-scheduler'),
				),
			)
		);

		wp_enqueue_script(
			'aips-duplicate-review-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-duplicate-review.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script', 'aips-consolidation-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-duplicate-review-script',
			'aipsDuplicateReviewL10n',
			array(
				'nonce'               => wp_create_nonce('aips_ajax_nonce'),
				'error'               => __('The duplicate review request failed. Please try again.', 'ai-post-scheduler'),
				'groupTitle'          => __('%1$d near-duplicate posts', 'ai-post-scheduler'),
				'summary'             => __('%1$d groups (%2$d posts) to review', 'ai-post-scheduler'),
				'hidden'              => __('%1$d similar pairs hidden because you marked them not duplicates', 'ai-post-scheduler'),
				'excluded'            => __('%1$d pairs skipped by your exclusions', 'ai-post-scheduler'),
				'truncated'           => __('Only the most similar pairs were loaded. Raise "Max similar pairs to review" in Settings → Engine to see more.', 'ai-post-scheduler'),
				'emptyTitle'          => __('No duplicate posts to review', 'ai-post-scheduler'),
				'emptyMessage'        => __('No group of posts is at least %1$d% similar. Lower the similarity above to look for looser overlaps, or check that your posts are indexed.', 'ai-post-scheduler'),
				'keep'                => __('Keep', 'ai-post-scheduler'),
				'protectedLabel'      => __('Protected', 'ai-post-scheduler'),
				'untitled'            => __('(untitled)', 'ai-post-scheduler'),
				'confirmDismissTitle' => __('Not duplicates', 'ai-post-scheduler'),
				'confirmDismiss'      => __('Mark these posts as not duplicates of each other? The group will stop being listed. You can bring dismissed groups back at any time.', 'ai-post-scheduler'),
				'dismiss'             => __('Mark not duplicates', 'ai-post-scheduler'),
				'confirmResetTitle'   => __('Show dismissed groups', 'ai-post-scheduler'),
				'confirmReset'        => __('List every group you marked as not duplicates again?', 'ai-post-scheduler'),
				'reset'               => __('Show them', 'ai-post-scheduler'),
				'cancel'              => __('Cancel', 'ai-post-scheduler'),
			)
		);
	}

	/**
	 * Enqueue assets for the Link Rules tab of the Content hub.
	 *
	 * @return void
	 */
	public function enqueue_link_rules() {
		wp_enqueue_script(
			'aips-link-rules-script',
			AIPS_PLUGIN_URL . 'assets/js/admin-link-rules.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script(
			'aips-link-rules-script',
			'aipsLinkRulesL10n',
			array(
				'nonce'              => wp_create_nonce('aips_ajax_nonce'),
				'error'              => __('The link rules request failed. Please try again.', 'ai-post-scheduler'),
				'noRules'            => __('No link rules yet. Add a keyword and the post it should link to above.', 'ai-post-scheduler'),
				'chooseTarget'       => __('Search for and choose the post to link to.', 'ai-post-scheduler'),
				'active'             => __('Active', 'ai-post-scheduler'),
				'disabled'           => __('Disabled', 'ai-post-scheduler'),
				'enable'             => __('Enable', 'ai-post-scheduler'),
				'disable'            => __('Disable', 'ai-post-scheduler'),
				'addRule'            => __('Add Rule', 'ai-post-scheduler'),
				'updateRule'         => __('Update Rule', 'ai-post-scheduler'),
				'cancel'             => __('Cancel', 'ai-post-scheduler'),
				'deleteRule'         => __('Delete rule', 'ai-post-scheduler'),
				'confirmDeleteTitle' => __('Delete link rule', 'ai-post-scheduler'),
				'confirmDelete'      => __('Delete this rule? Its links disappear from your posts right away.', 'ai-post-scheduler'),
			)
		);
	}
}
