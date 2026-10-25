<?php
/**
 * Admin Asset Registrar: Monitoring & Operations
 *
 * Handles enqueueing for Dashboard, History, Diagnostics, Status, Dev Tools,
 * Telemetry, Cache Monitor, and Stress Test.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Admin_Asset_Registrar_Monitoring
 */
class AIPS_Admin_Asset_Registrar_Monitoring {

	/**
	 * Enqueue assets for the main dashboard page.
	 *
	 * @return void
	 */
	public function enqueue_dashboard() {
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
			array('jquery', 'aips-utilities-script', 'aips-admin-script', 'aips-chartjs', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-dashboard-script', 'aipsDashboardL10n', array(
			'nonce'               => wp_create_nonce('aips_ajax_nonce'),
			'chartPostsTitle'     => __('Post Generations by Day', 'ai-post-scheduler'),
			'chartTopicsTitle'    => __('Topic Generations by Day', 'ai-post-scheduler'),
			'chartErrorRateTitle' => __('AI Error Rate (%)', 'ai-post-scheduler'),
			'chartCompletedLabel' => __('Completed', 'ai-post-scheduler'),
			'chartFailedLabel'    => __('Failed', 'ai-post-scheduler'),
			'chartTopicsLabel'    => __('Topics Generated', 'ai-post-scheduler'),
			'chartErrorRateLabel' => __('Error Rate (%)', 'ai-post-scheduler'),
			'chartUnavailable'    => __('Chart library failed to load.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the history page.
	 *
	 * @return void
	 */
	public function enqueue_history() {
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
			'loading'                   => __('Loading…', 'ai-post-scheduler'),
			'reloading'                 => __('Reloading…', 'ai-post-scheduler'),
			'loadingLogs'               => __('Loading logs…', 'ai-post-scheduler'),
			'historyDetailsTitle'       => __('History Details', 'ai-post-scheduler'),
			'historyContainerHeader'    => __('History Container for ID: %d', 'ai-post-scheduler'),
			'errorLoading'              => __('Error loading logs.', 'ai-post-scheduler'),
			'errorReloading'            => __('Failed to reload history.', 'ai-post-scheduler'),
			'logsModalTitle'            => __('Logs — %s', 'ai-post-scheduler'),
			'logsHeading'               => __('Log Entries', 'ai-post-scheduler'),
			'noLogsFound'               => __('No log entries found for this container.', 'ai-post-scheduler'),
			'noResultsFound'            => __('No history containers match your current filters.', 'ai-post-scheduler'),
			'labelContainerId'          => __('Container ID', 'ai-post-scheduler'),
			'labelTitle'                => __('Title', 'ai-post-scheduler'),
			'labelTemplate'             => __('Template', 'ai-post-scheduler'),
			'labelStatus'               => __('Status', 'ai-post-scheduler'),
			'labelCreated'              => __('Created', 'ai-post-scheduler'),
			'labelCompleted'            => __('Completed', 'ai-post-scheduler'),
			'labelError'                => __('Error', 'ai-post-scheduler'),
			'labelPostId'               => __('Post', 'ai-post-scheduler'),
			'labelDuration'             => __('Duration', 'ai-post-scheduler'),
			'labelCreationMethod'       => __('Method', 'ai-post-scheduler'),
			'labelWhatHappened'         => __('What happened', 'ai-post-scheduler'),
			'labelOutcome'              => __('Outcome', 'ai-post-scheduler'),
			'labelRelatedEntities'      => __('Related entities', 'ai-post-scheduler'),
			'labelWhatChanged'          => __('What changed', 'ai-post-scheduler'),
			'summaryHeading'            => __('Summary', 'ai-post-scheduler'),
			'labelAdvancedDetails'      => __('Advanced details', 'ai-post-scheduler'),
			'summaryActionResearchRun'  => __('Research run', 'ai-post-scheduler'),
			'summaryActionEmbeddings'   => __('Embeddings processing', 'ai-post-scheduler'),
			'summaryActionAuthorTopics' => __('Author topic generation', 'ai-post-scheduler'),
			'summaryActionScheduledPosts' => __('Scheduled post generation', 'ai-post-scheduler'),
			'summaryActionPostGeneration' => __('Post generation', 'ai-post-scheduler'),
			'summaryActionAutomationTask' => __('Automation task', 'ai-post-scheduler'),
			'summaryOutcomeSuccess'     => __('Success', 'ai-post-scheduler'),
			'summaryOutcomeFailed'      => __('Failed', 'ai-post-scheduler'),
			'summaryOutcomeInProgress'  => __('In progress', 'ai-post-scheduler'),
			'summaryEntityPost'         => __('Post', 'ai-post-scheduler'),
			'summaryEntityTemplate'     => __('Template', 'ai-post-scheduler'),
			'summaryEntityPostId'       => __('Post ID', 'ai-post-scheduler'),
			'summaryEntityMethod'       => __('Method', 'ai-post-scheduler'),
			'summaryNoRelatedEntities'  => __('No related entities detected', 'ai-post-scheduler'),
			'summaryChangedTitle'       => __('Title updated', 'ai-post-scheduler'),
			'summaryChangedContent'     => __('Content updated', 'ai-post-scheduler'),
			'summaryChangedImage'       => __('Image generated/updated', 'ai-post-scheduler'),
			'summaryChangedPublished'   => __('Published result', 'ai-post-scheduler'),
			'summaryChangedDraft'       => __('Draft result', 'ai-post-scheduler'),
			'summaryChangedError'       => __('Run ended with an error', 'ai-post-scheduler'),
			'summaryChangedNone'        => __('No major content changes detected', 'ai-post-scheduler'),
			'editPostLabel'             => __('Edit', 'ai-post-scheduler'),
			'clickToExpand'             => __('Click to expand %d items', 'ai-post-scheduler'),
			'clickToCollapse'           => __('Click to collapse', 'ai-post-scheduler'),
			'filterAll'                 => __('All', 'ai-post-scheduler'),
			'filterByType'              => __('Filter:', 'ai-post-scheduler'),
			'typeLabels'                => AIPS_History_Type::get_all_types(),
			'colTimestamp'              => __('Timestamp', 'ai-post-scheduler'),
			'colType'                   => __('Type', 'ai-post-scheduler'),
			'colLogType'                => __('Log Type', 'ai-post-scheduler'),
			'colDetails'                => __('Details', 'ai-post-scheduler'),
			'showDetails'               => __('Show details', 'ai-post-scheduler'),
			'hideDetails'               => __('Hide details', 'ai-post-scheduler'),
			'copyDetails'               => __('Copy', 'ai-post-scheduler'),
			'copiedDetails'             => __('Copied!', 'ai-post-scheduler'),
			'copyDiagnostic'            => __('Copy diagnostic', 'ai-post-scheduler'),
			'confirmDelete'             => __('Delete this history container? This cannot be undone.', 'ai-post-scheduler'),
			'confirmBulkDelete'         => __('Delete the selected history containers? This cannot be undone.', 'ai-post-scheduler'),
			'confirmDeleteLabel'        => __('Yes, delete', 'ai-post-scheduler'),
			'cancelLabel'               => __('No, cancel', 'ai-post-scheduler'),
			'deletedSuccess'            => __('Items deleted successfully.', 'ai-post-scheduler'),
			'errorDeleting'             => __('Error deleting items.', 'ai-post-scheduler'),
			'deleting'                  => __('Deleting…', 'ai-post-scheduler'),
			'retrying'                  => __('Retrying…', 'ai-post-scheduler'),
			'errorRetrying'             => __('An error occurred. Please try again.', 'ai-post-scheduler'),
			'heartbeatUnavailable'      => __('Heartbeat API unavailable.', 'ai-post-scheduler'),
			'processingGroup'           => __('Processing', 'ai-post-scheduler'),
			'expandGroup'               => __('Show runs', 'ai-post-scheduler'),
			'collapseGroup'             => __('Hide runs', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the dev-tools page.
	 *
	 * @return void
	 */
	public function enqueue_dev_tools() {
		wp_enqueue_script(
			'aips-admin-dev-tools',
			AIPS_PLUGIN_URL . 'assets/js/admin-dev-tools.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets for the status_1 page (admin-db.js).
	 *
	 * @return void
	 */
	public function enqueue_status_1() {
		wp_enqueue_script(
			'aips-admin-db',
			AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
			array('aips-admin-script'),
			AIPS_VERSION,
			true
		);
	}

	/**
	 * Enqueue assets for the status_2 page (admin-system-status.js).
	 *
	 * @return void
	 */
	public function enqueue_status_2() {
		wp_enqueue_script(
			'aips-admin-system-status',
			AIPS_PLUGIN_URL . 'assets/js/admin-system-status.js',
			array('aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-system-status', 'aipsSystemStatusL10n', array(
			'nonce'                        => wp_create_nonce('aips_reset_circuit_breaker'),
			'nonceCronReschedule'          => wp_create_nonce('aips_status_reschedule_missed_cron'),
			'nonceRetrySlices'             => wp_create_nonce('aips_status_retry_failed_slices'),
			'nonceRepairCampaignData'      => wp_create_nonce('aips_status_repair_campaign_data'),
			'nonceClearPartialGenerations' => wp_create_nonce('aips_status_clear_partial_generations'),
			'nonceCleanupStaleJobsCache'   => wp_create_nonce('aips_status_cleanup_stale_jobs_cache'),
			'nonceRebuildCaches'           => wp_create_nonce('aips_rebuild_caches'),
			'nonceRefreshSystem'           => wp_create_nonce('aips_status_refresh_system'),
			'nonceCacheMaintenance'        => wp_create_nonce('aips_status_cache_maintenance'),
			'nonceClearEmbeddingsCache'    => wp_create_nonce('aips_status_clear_embeddings_cache'),
			'nonceCleanupNotifications'    => wp_create_nonce('aips_status_cleanup_notifications'),
			'nonceResetResilience'         => wp_create_nonce('aips_status_reset_resilience'),
			'nonceRepairDatetime'          => wp_create_nonce('aips_status_repair_datetime'),
			'noncePruneTelemetry'          => wp_create_nonce('aips_status_prune_telemetry'),
			'noncePurgeTelemetry'          => wp_create_nonce('aips_status_purge_telemetry'),
			'noncePruneHistoryLogs'        => wp_create_nonce('aips_status_prune_history_logs'),
			'nonceCleanEmbeddings'         => wp_create_nonce('aips_status_clean_orphaned_embeddings'),
			'nonceOptimizeTable'           => wp_create_nonce('aips_status_optimize_table'),
			'nonceGetTables'               => wp_create_nonce('aips_status_get_tables'),
			'confirmPruneTelemetry'        => __('Prune telemetry records older than configured retention period? Batched deletion will defragment table if needed.', 'ai-post-scheduler'),
			'confirmPurgeTelemetryTitle'   => __('Purge All Telemetry Data', 'ai-post-scheduler'),
			'confirmPurgeTelemetry'        => __('Are you sure you want to PURGE ALL telemetry records? This will truncate the table and instantly reclaim all disk space (reset to 0 bytes). This action cannot be undone.', 'ai-post-scheduler'),
			'confirmPruneHistoryLogs'      => __('Prune generation logs older than configured retention period?', 'ai-post-scheduler'),
			'confirmCleanEmbeddings'       => __('Clean orphaned embeddings for posts that no longer exist?', 'ai-post-scheduler'),
			'confirmOptimizeTable'         => __('Run OPTIMIZE TABLE on this table? This can briefly lock the table on large sites.', 'ai-post-scheduler'),
			'confirmRefreshSystemDestructive' => __('This includes one or more tasks that permanently delete data (e.g. telemetry/history pruning, orphaned embeddings cleanup). Continue?', 'ai-post-scheduler'),
			'tableOptimized'               => __('Table optimized successfully.', 'ai-post-scheduler'),
			'tablesRefreshed'              => __('Table sizes refreshed.', 'ai-post-scheduler'),
			'refreshRunning'               => __('Refreshing system…', 'ai-post-scheduler'),
			'refreshDone'                  => __('System refresh complete.', 'ai-post-scheduler'),
			'refreshPartial'               => __('System refresh finished with some failures.', 'ai-post-scheduler'),
			'selectTasksRequired'          => __('Select at least one maintenance task to run.', 'ai-post-scheduler'),
			'rebuildingCaches'             => __('Rebuilding caches…', 'ai-post-scheduler'),
			'rebuildDone'                  => __('Caches rebuilt successfully.', 'ai-post-scheduler'),
			'selectCachesRequired'         => __('Select at least one cache subsystem to rebuild.', 'ai-post-scheduler'),
			'hideDetails'                  => __('Hide Details', 'ai-post-scheduler'),
			'showDetails'                  => __('Show Details', 'ai-post-scheduler'),
			'resetSuccess'                 => __('Circuit reset. Reload the page to confirm.', 'ai-post-scheduler'),
			'resetFailed'                  => __('Reset failed.', 'ai-post-scheduler'),
			'requestFailed'                => __('Request failed. Please try again.', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the telemetry page.
	 *
	 * @return void
	 */
	public function enqueue_telemetry() {
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
			'nonce'                     => wp_create_nonce('aips_get_telemetry'),
			'detailsNonce'              => wp_create_nonce('aips_get_telemetry_details'),
			'loading'                   => __('Loading…', 'ai-post-scheduler'),
			'loadingDetails'            => __('Loading telemetry details…', 'ai-post-scheduler'),
			'filterLabel'               => __('Filter', 'ai-post-scheduler'),
			'resetFiltersLabel'         => __('Reset Filters', 'ai-post-scheduler'),
			'requestFailed'             => __('Request failed. Please try again.', 'ai-post-scheduler'),
			'detailsRequestFailed'      => __('Failed to load telemetry details. Please try again.', 'ai-post-scheduler'),
			'telemetryPage'             => __('Page %1$s of %2$s', 'ai-post-scheduler'),
			'telemetryTotal'            => __('%s records', 'ai-post-scheduler'),
			'telemetryNoRecords'        => __('No telemetry records found for the selected range.', 'ai-post-scheduler'),
			'chartQueriesTitle'         => __('Queries Executed per Day', 'ai-post-scheduler'),
			'chartMemoryTitle'          => __('Peak Memory per Day', 'ai-post-scheduler'),
			'chartElapsedTitle'         => __('Average Elapsed Time per Day', 'ai-post-scheduler'),
			'chartRequestsTitle'        => __('Requests Logged per Day', 'ai-post-scheduler'),
			'chartQueriesLabel'         => __('Queries', 'ai-post-scheduler'),
			'chartMemoryLabel'          => __('Peak Memory (MB)', 'ai-post-scheduler'),
			'chartElapsedLabel'         => __('Average Elapsed (ms)', 'ai-post-scheduler'),
			'chartRequestsLabel'        => __('Requests', 'ai-post-scheduler'),
			'chartUnavailable'          => __('Chart library failed to load.', 'ai-post-scheduler'),
			'rangeSummary'              => __('Showing telemetry from %1$s to %2$s.', 'ai-post-scheduler'),
			'refreshLabel'              => __('Refresh', 'ai-post-scheduler'),
			'refreshing'                => __('Refreshing…', 'ai-post-scheduler'),
			'viewDetails'               => __('View Details', 'ai-post-scheduler'),
			'detailsTitle'              => __('Telemetry Details #%s', 'ai-post-scheduler'),
			'detailsIdLabel'            => __('ID', 'ai-post-scheduler'),
			'detailsTypeLabel'          => __('Type', 'ai-post-scheduler'),
			'detailsPageLabel'          => __('Page', 'ai-post-scheduler'),
			'detailsCategoriesLabel'    => __('Categories', 'ai-post-scheduler'),
			'detailsMethodLabel'        => __('Method', 'ai-post-scheduler'),
			'detailsUserIdLabel'        => __('User ID', 'ai-post-scheduler'),
			'detailsEventsLabel'        => __('Events', 'ai-post-scheduler'),
			'detailsCacheCallsLabel'    => __('Cache Calls', 'ai-post-scheduler'),
			'detailsCacheHitsLabel'     => __('Cache Hits', 'ai-post-scheduler'),
			'detailsCacheMissesLabel'   => __('Cache Misses', 'ai-post-scheduler'),
			'detailsQueriesLabel'       => __('Queries', 'ai-post-scheduler'),
			'detailsSlowQueriesLabel'   => __('Slow Queries', 'ai-post-scheduler'),
			'detailsDuplicateQueriesLabel' => __('Duplicate Queries', 'ai-post-scheduler'),
			'detailsPeakMemoryLabel'    => __('Peak Memory', 'ai-post-scheduler'),
			'detailsElapsedLabel'       => __('Elapsed', 'ai-post-scheduler'),
			'detailsInsertedLabel'      => __('Inserted At', 'ai-post-scheduler'),
			'detailsEventsSection'      => __('Events', 'ai-post-scheduler'),
			'detailsEventSummarySection' => __('Event Summary', 'ai-post-scheduler'),
			'detailsCacheSummarySection' => __('Cache Summary', 'ai-post-scheduler'),
			'detailsQuerySummarySection' => __('Query Summary', 'ai-post-scheduler'),
			'detailsEventsHelp'         => __('The full event list can be long. Expand to inspect each nested event object.', 'ai-post-scheduler'),
			'detailsEventSummaryHelp'   => __('High-level telemetry counts grouped by bucket and event type.', 'ai-post-scheduler'),
			'detailsCacheSummaryHelp'   => __('Cache activity grouped by operation and result.', 'ai-post-scheduler'),
			'detailsQuerySummaryHelp'   => __('Query totals, slow queries, and duplicate query counts.', 'ai-post-scheduler'),
			'detailsRawPayloadLabel'    => __('Raw Payload JSON', 'ai-post-scheduler'),
			'detailsRawPayloadHelp'     => __('Review the structured payload summaries above, or expand the raw JSON below for the original object.', 'ai-post-scheduler'),
			'detailsEventItemLabel'     => __('Event %s', 'ai-post-scheduler'),
			'detailsItemLabel'          => __('Item %s', 'ai-post-scheduler'),
			'locale'                    => get_locale(),
			'expandLabel'               => __('Expand', 'ai-post-scheduler'),
			'collapseLabel'             => __('Collapse', 'ai-post-scheduler'),
			'insertedJustNow'           => __('just now', 'ai-post-scheduler'),
			'insertedMinutesAgo'        => __('%s minutes ago', 'ai-post-scheduler'),
			'insertedMinuteAgo'         => __('1 minute ago', 'ai-post-scheduler'),
			'insertedHoursAgo'          => __('%s hours ago', 'ai-post-scheduler'),
			'insertedHourAgo'           => __('1 hour ago', 'ai-post-scheduler'),
			'insertedHoursMinutesAgo'   => __('%1$s hours and %2$s minutes ago', 'ai-post-scheduler'),
			'insertedYesterdayAt'       => __('yesterday at %s', 'ai-post-scheduler'),
			'insertedAbsoluteDate'      => __('%1$s %2$s', 'ai-post-scheduler'),
			'payloadEmpty'              => __('No payload was stored for this telemetry row.', 'ai-post-scheduler'),
			'eventsEmpty'               => __('[]', 'ai-post-scheduler'),
		));
	}

	/**
	 * Enqueue assets for the Cache Monitor page.
	 *
	 * @return void
	 */
	public function enqueue_cache_monitor() {
		wp_enqueue_script(
			'aips-cache-monitor',
			AIPS_PLUGIN_URL . 'assets/js/cache-monitor.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-cache-monitor', 'aipsCacheMonitor', array(
			'nonce'       => wp_create_nonce('aips_cache_monitor'),
			'actionNonce' => wp_create_nonce('aips_cache_monitor_action'),
			'i18n'        => array(
				'loading'          => __('Loading…', 'ai-post-scheduler'),
				'never'            => __('Never', 'ai-post-scheduler'),
				'noEntries'        => __('No entries found.', 'ai-post-scheduler'),
				'noOps'            => __('No operations found.', 'ai-post-scheduler'),
				'selectEntry'      => __('Select cache entry', 'ai-post-scheduler'),
				'noEvents'         => __('No events found.', 'ai-post-scheduler'),
				'noneSelected'     => __('No entries selected.', 'ai-post-scheduler'),
				'requestFailed'    => __('Request failed. Please try again.', 'ai-post-scheduler'),
				'inspect'          => __('Inspect', 'ai-post-scheduler'),
				'delete'           => __('Delete', 'ai-post-scheduler'),
				'preview'          => __('Preview', 'ai-post-scheduler'),
				'prev'             => __('Prev', 'ai-post-scheduler'),
				'next'             => __('Next', 'ai-post-scheduler'),
				'confirmFlushAll'  => __('This will flush ALL plugin-owned cache. Are you absolutely sure?', 'ai-post-scheduler'),
				'flushAllTitle'    => __('Flush All Plugin Cache', 'ai-post-scheduler'),
				'confirmBtn'       => __('Confirm Flush', 'ai-post-scheduler'),
				'flushGroupTitle'  => __('Flush Cache Group', 'ai-post-scheduler'),
				'flushGroupBtn'    => __('Flush Group', 'ai-post-scheduler'),
			),
		));
	}

	/**
	 * Enqueue assets for the Stress Test page.
	 *
	 * @return void
	 */
	public function enqueue_stress_test() {
		wp_enqueue_style(
			'aips-stress-test-style',
			AIPS_PLUGIN_URL . 'assets/css/stress-test.css',
			array('aips-admin-style'),
			AIPS_VERSION
		);

		wp_enqueue_script(
			'aips-admin-stress-test',
			AIPS_PLUGIN_URL . 'assets/js/admin-stress-test.js',
			array('jquery', 'aips-admin-script', 'aips-utilities-script', 'aips-templates-script'),
			AIPS_VERSION,
			true
		);

		wp_localize_script('aips-admin-stress-test', 'aipsStressTest', array(
			'nonce'                    => wp_create_nonce(AIPS_Stress_Test_Controller::NONCE_ACTION),
			'nonceCleanStressTestData' => wp_create_nonce('aips_status_clean_stress_test_data'),
			'i18n'  => array(
				'running'               => __('Running…', 'ai-post-scheduler'),
				'notRun'                => __('Not run', 'ai-post-scheduler'),
				'notRunYet'             => __('Run this case to see the request and response.', 'ai-post-scheduler'),
				'requestFailed'         => __('Request failed. Check the browser console and the plugin log.', 'ai-post-scheduler'),
				'timedOut'              => __('The request timed out before the provider responded.', 'ai-post-scheduler'),
				'aiValue'               => __('AI response value', 'ai-post-scheduler'),
				'aiValueHint'           => __('Exactly what the provider returned.', 'ai-post-scheduler'),
				'pluginValue'           => __('Plugin final value', 'ai-post-scheduler'),
				'pluginValueHint'       => __('After the plugin parsed and normalized it.', 'ai-post-scheduler'),
				'noValue'               => __('No value returned.', 'ai-post-scheduler'),
				'aiCalls'               => __('AI calls', 'ai-post-scheduler'),
				'noCalls'               => __('No AI calls were recorded for this case.', 'ai-post-scheduler'),
				'request'               => __('Request', 'ai-post-scheduler'),
				'response'              => __('Response', 'ai-post-scheduler'),
				'prompt'                => __('Prompt', 'ai-post-scheduler'),
				'options'               => __('Options', 'ai-post-scheduler'),
				'content'               => __('Content', 'ai-post-scheduler'),
				'error'                 => __('Error', 'ai-post-scheduler'),
				'showContext'           => __('Show system instruction', 'ai-post-scheduler'),
				'allPassed'             => __('All tests passed', 'ai-post-scheduler'),
				'someFailed'            => __('Some tests failed', 'ai-post-scheduler'),
				'passedIn'              => __('passed in', 'ai-post-scheduler'),
				'cancel'                => __('Cancel', 'ai-post-scheduler'),
				'confirmCleanup'        => __('This permanently deletes every post and image created by the Stress Test page. Continue?', 'ai-post-scheduler'),
				'confirmCleanupHeading' => __('Delete test data', 'ai-post-scheduler'),
				'confirmCleanupAction'  => __('Yes, delete', 'ai-post-scheduler'),
				'nothingToExport'       => __('Run at least one test case before exporting.', 'ai-post-scheduler'),
				'stopped'               => __('Stopped', 'ai-post-scheduler'),
				'runSelected'           => __('Run Selected', 'ai-post-scheduler'),
				'runSelectedCount'      => __('Run Selected (%d)', 'ai-post-scheduler'),
				'selectAtLeastOne'      => __('Please select at least one test case to run.', 'ai-post-scheduler'),
				'runDetails'            => __('Stress Test Run Details', 'ai-post-scheduler'),
				'comparisonDiff'        => __('Stress Test Comparison & Diff', 'ai-post-scheduler'),
				'selectTwoRunsToCompare'=> __('Please select exactly 2 runs to compare.', 'ai-post-scheduler'),
			),
		));
	}
}
