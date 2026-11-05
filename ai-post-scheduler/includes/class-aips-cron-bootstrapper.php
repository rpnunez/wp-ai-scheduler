<?php
/**
 * Cron Bootstrapper.
 *
 * Registers WP-Cron action handlers, bulk-batch processing strategies,
 * and background maintenance tasks executed during cron requests.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

final class AIPS_Cron_Bootstrapper {

	/**
	 * Boot all cron-specific hooks and strategies.
	 *
	 * @return void
	 */
	public static function boot() {
		self::register_schedulers();
		self::register_linking_and_scans();
		self::register_bulk_batch_processor();
		self::register_maintenance_tasks();
		self::register_cron_listeners();
	}

	/**
	 * Register main generation and author schedulers.
	 *
	 * @return void
	 */
	private static function register_schedulers() {
		// Lazy-resolve the main template scheduler only when its hook fires.
		add_action('aips_generate_scheduled_posts', function() {
			AIPS_Scheduler::instance()->process();
		});

		// Single due-schedule events: staggered due schedules and yielded
		// batches resuming after their cooldown.
		add_action(AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK, function($schedule_id) {
			AIPS_Scheduler::instance()->process_queued_due_schedule((int) $schedule_id);
		});

		add_filter('cron_schedules', function($schedules) {
			return AIPS_Scheduler::instance()->add_cron_intervals($schedules);
		});

		// Batch-queue single events: each call processes one slice of a large schedule.
		add_action('aips_process_schedule_batch', function(
			$schedule_id,
			$start_index,
			$batch_size,
			$total_quantity,
			$correlation_id = ''
		) {
			AIPS_Scheduler::instance()->process_batch(
				(int) $schedule_id,
				(int) $start_index,
				(int) $batch_size,
				(int) $total_quantity,
				(string) $correlation_id
			);
		}, 10, 5);

		// Resume large batches that the "Prevent AI Generation" setting stopped part-way through.
		add_action('aips_resume_terminated_batches', function() {
			AIPS_Scheduler::instance()->resume_terminated_batches();
		});

		// Lazy-resolve the author-topics scheduler only when its hook fires.
		add_action('aips_generate_author_topics', function() {
			AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
		});

		// Per-author topic-generation slice: process one author's topics in a dedicated cron event.
		add_action('aips_process_author_topics_slice', function( $author_id, $correlation_id = '' ) {
			AIPS_Author_Topics_Scheduler::instance()->process_author_slice(
				(int) $author_id,
				(string) $correlation_id
			);
		}, 10, 2);

		// Retry failed topic-generation slices: re-dispatch authors that failed to schedule.
		add_action('aips_retry_failed_author_slices_topics', function( $author_ids_json, $correlation_id = '' ) {
			AIPS_Author_Topics_Scheduler::instance()->retry_failed_topic_slices(
				(string) $author_ids_json,
				(string) $correlation_id
			);
		}, 10, 2);

		// Lazy-resolve the author-post generator only when its hook fires.
		add_action('aips_generate_author_posts', function() {
			AIPS_Author_Post_Generator::instance()->process();
		});

		// Per-author post-generation slice: process one author's post in a dedicated cron event.
		add_action('aips_process_author_post_slice', function( $author_id, $correlation_id = '' ) {
			AIPS_Author_Post_Generator::instance()->process_author_slice(
				(int) $author_id,
				(string) $correlation_id
			);
		}, 10, 2);

		// Retry failed post-generation slices: re-dispatch authors that failed to schedule.
		add_action('aips_retry_failed_author_slices_posts', function( $author_ids_json, $correlation_id = '' ) {
			AIPS_Author_Post_Generator::instance()->retry_failed_post_slices(
				(string) $author_ids_json,
				(string) $correlation_id
			);
		}, 10, 2);
	}

	/**
	 * Register linking and SEO scan hooks.
	 *
	 * @return void
	 */
	private static function register_linking_and_scans() {
		// Link index scans: each tick indexes one batch and schedules the next.
		// Generation-time linking pass for a newly published AIPS post.
		add_action(AIPS_Publish_Linking_Service::CRON_HOOK, function ($post_id) {
			AIPS_Container::get_instance()->make(AIPS_Publish_Linking_Service::class)->process($post_id);
		});

		// Scheduled duplicate scan (Settings > Engine > Scheduled Duplicate Scan).
		add_action(AIPS_Duplicate_Group_Service::CRON_HOOK, function () {
			(new AIPS_Duplicate_Group_Service())->run_scheduled_scan();
		});

		// Daily Search Console target keyword sync.
		add_action(AIPS_GSC_Keywords_Service::CRON_HOOK, function () {
			AIPS_Container::get_instance()->make(AIPS_GSC_Keywords_Service::class)->sync();
		});

		add_action(AIPS_Link_Index_Service::SCAN_TICK_HOOK, function( $job_id ) {
			AIPS_Container::get_instance()->make( AIPS_Link_Index_Service::class )->process_scan_tick( $job_id );
		});

		// Bulk auto-link runs: same tick pattern, one batch of target posts per event.
		add_action(AIPS_Autolink_Run_Service::TICK_HOOK, function( $job_id ) {
			AIPS_Container::get_instance()->make( AIPS_Autolink_Run_Service::class )->process_tick( $job_id );
		});
	}

	/**
	 * Register bulk batch processing and runner strategies.
	 *
	 * @return void
	 */
	private static function register_bulk_batch_processor() {
		// Async bulk-batch processing: each single event processes one slice of a stored job.
		add_action('aips_process_bulk_batch', function(
			$job_id,
			$start_index,
			$batch_size,
			$total_quantity,
			$correlation_id = ''
		) {
			AIPS_Bulk_Batch_Processor::instance()->process(
				(string) $job_id,
				(int)    $start_index,
				(int)    $batch_size,
				(int)    $total_quantity,
				(string) $correlation_id
			);
		}, 10, 5);

		$processor = AIPS_Bulk_Batch_Processor::instance();

		$processor->register(
			'author_topic_post',
			function( $topic_id, $job_id, $job ) {
				return AIPS_Author_Post_Generator::instance()->generate_now(
					(int) $topic_id,
					'author_topic_post',
					array( 'detail' => sprintf( __( 'Bulk job %s (Author topics)', 'ai-post-scheduler' ), $job_id ) )
				);
			}
		);

		$processor->register(
			'planner_post',
			function( $item, $job_id, $job ) {
				$template_id = isset( $job->options['history_meta']['template_id'] )
					? (int) $job->options['history_meta']['template_id']
					: 0;

				if ( $template_id <= 0 ) {
					return new WP_Error(
						'planner_post_missing_template',
						__( 'planner_post strategy requires a template_id stored in job options.', 'ai-post-scheduler' )
					);
				}

				$template = ( new AIPS_Template_Repository() )->get_by_id( $template_id );

				if ( ! $template || empty( $template->is_active ) ) {
					return new WP_Error(
						'planner_post_template_not_found',
						/* translators: %d: template ID */
						sprintf( __( 'Template %d not found or inactive for planner_post strategy.', 'ai-post-scheduler' ), $template_id )
					);
				}

				$topic     = is_array( $item ) ? ( $item['topic'] ?? (string) $item ) : (string) $item;
				$generator = new AIPS_Generator();

				return $generator->generate_post( $template, null, $topic, array(
					'creation_method' => 'planner_post',
					'trigger_context' => array( 'detail' => sprintf( __( 'Bulk job %s (Planner)', 'ai-post-scheduler' ), $job_id ) ),
				) );
			}
		);

		$processor->register(
			'trending_topic_post',
			function( $item, $job_id, $job ) {
				if ( ! is_array( $item ) || empty( $item['id'] ) || ! isset( $item['topic'] ) ) {
					return new WP_Error(
						'invalid_trending_topic_item',
						__( 'Item must be an array with id and topic keys.', 'ai-post-scheduler' )
					);
				}

				$template_id = isset( $job->options['history_meta']['template_id'] )
					? (int) $job->options['history_meta']['template_id']
					: 0;

				if ( $template_id <= 0 ) {
					return new WP_Error(
						'trending_topic_post_missing_template',
						__( 'trending_topic_post strategy requires a template_id stored in job options.', 'ai-post-scheduler' )
					);
				}

				$template = ( new AIPS_Template_Repository() )->get_by_id( $template_id );

				if ( ! $template || empty( $template->is_active ) ) {
					return new WP_Error(
						'trending_topic_post_template_not_found',
						/* translators: %d: template ID */
						sprintf( __( 'Template %d not found or inactive for trending_topic_post strategy.', 'ai-post-scheduler' ), $template_id )
					);
				}

				$context   = new AIPS_Template_Context( $template, null, (string) $item['topic'], 'trending_topic_post' );
				$context->set_trigger_context( array(
					'detail' => sprintf( __( 'Bulk job %s (Trending topics)', 'ai-post-scheduler' ), $job_id ),
				) );
				$generator = new AIPS_Generator();
				$post_id   = $generator->generate_post( $context );

				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}

				update_post_meta( $post_id, AIPS_Post_Manager::META_TRENDING_TOPIC_ID,  absint( $item['id'] ) );
				update_post_meta( $post_id, AIPS_Post_Manager::META_TRENDING_TOPIC_TEXT, sanitize_text_field( (string) $item['topic'] ) );

				return $post_id;
			}
		);

		// Background bulk-publish from the Pending Review queue.
		$processor->register(
			'review_publish',
			function( $item, $job_id, $job ) {
				$acting_user_id = isset( $job->options['history_meta']['acting_user_id'] )
					? (int) $job->options['history_meta']['acting_user_id']
					: 0;

				if ( $acting_user_id > 0 && get_current_user_id() !== $acting_user_id ) {
					wp_set_current_user( $acting_user_id );
				}

				return AIPS_Post_Review::instance()->publish_review_post( (int) $item );
			}
		);
	}

	/**
	 * Register recurring maintenance and cleanup tasks.
	 *
	 * @return void
	 */
	private static function register_maintenance_tasks() {
		// Daily cleanup of completed/failed bulk-batch job rows.
		add_action('aips_cleanup_bulk_batch_jobs', function() {
			$store   = new AIPS_Bulk_Batch_Job_Store();
			$deleted = $store->cleanup_old_jobs();
			$deleted += (new AIPS_Background_Process_Repository())->cleanup_old();
			if ( $deleted > 0 ) {
				( new AIPS_Logger() )->log(
					sprintf( 'Bulk batch job cleanup: deleted %d old job rows.', $deleted ),
					'info'
				);
			}
		});

		// Daily Cache Monitor maintenance (prune expired/orphan index rows + prune old events).
		add_action('aips_cache_monitor_maintenance', function() {
			$repository  = new AIPS_Cache_Monitor_Repository();
			$cache_index = new AIPS_Cache_Index();
			$service     = new AIPS_Cache_Monitor_Service( $repository, $cache_index );
			$result      = $service->run_maintenance();
			( new AIPS_Logger() )->log(
				sprintf( 'Cache Monitor maintenance complete: %s', wp_json_encode( $result ) ),
				'info'
			);
		});

		// Database retention & prune maintenance.
		add_action('aips_database_prune_cleanup', function() {
			$service = AIPS_Container::get_instance()->make(AIPS_DB_Prune_Service::class);
			$result  = $service->run_automated_prune();
			( new AIPS_Logger() )->log(
				sprintf( 'Database prune cleanup complete: %s', wp_json_encode( $result ) ),
				'info'
			);
		});

		// Lazy-resolve the embeddings worker only when its hook fires.
		add_action('aips_process_author_embeddings', function($args) {
			AIPS_Embeddings_Cron::instance()->process_author_embeddings($args);
		}, 10, 1);

		// Internal Links indexing cron — construct the controller lazily only
		// when the cron hook fires to avoid eager instantiation on every cron boot.
		add_action('aips_index_posts_batch', function($args) {
			(new AIPS_Internal_Links_Controller())->process_indexing_batch_cron($args);
		}, 10, 1);

		// Export-file cleanup cron handler.
		add_action('aips_cleanup_export_files', array('AIPS_Session_To_JSON', 'handle_export_cleanup'));

		// Post-save affiliate link injection — fires after every generated post.
		add_action('aips_post_generated', function($post_id) {
			(new AIPS_Affiliate_Links_Service())->inject_for_post(absint($post_id));
		}, 10, 1);
	}

	/**
	 * Register listeners and controllers running in the cron context.
	 *
	 * @return void
	 */
	private static function register_cron_listeners() {
		// Research controller registers the aips_scheduled_research cron hook.
		new AIPS_Research_Controller();

		// Notification event handler receives generation-failure/quota alerts from cron.
		new AIPS_Notifications();

		// Reconciler's save_post hook fires when cron creates or updates posts.
		new AIPS_Partial_Generation_State_Reconciler();
	}
}
