<?php
/**
 * Registers all WP-Cron hook callbacks and bulk-batch strategies.
 *
 * Extracted from AI_Post_Scheduler::boot_cron() so the plugin bootstrap file
 * doesn't carry the full cron wiring inline.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Cron_Bootstrap {

    /**
     * Register subsystems required only during WP-Cron execution.
     *
     * Registers cron hook callbacks as closures that resolve the singleton
     * instance at runtime (when WordPress fires the event). This means that
     * a cron request dispatched for, say, aips_generate_author_topics will only
     * ever instantiate AIPS_Author_Topics_Scheduler — the other scheduler
     * objects are never constructed unless their own hooks fire in the same run.
     *
     * Also boots the notification event handler (for generation-failure and quota
     * alerts fired from cron) and the partial-generation reconciler
     * (save_post fires when cron creates posts).
     *
     * @return void
     */
    public static function register() {
        // Lazy-resolve the main template scheduler only when its hook fires.
        add_action('aips_generate_scheduled_posts', function() {
            AIPS_Scheduler::instance()->process();
        });
        add_filter('cron_schedules', function($schedules) {
            return AIPS_Scheduler::instance()->add_cron_intervals($schedules);
        });

        // Batch-queue single events: each call processes one slice of a large schedule.
        // Args: schedule_id, start_index, batch_size, total_quantity, correlation_id.
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

        // Lazy-resolve the author-topics scheduler only when its hook fires.
        add_action('aips_generate_author_topics', function() {
            AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
        });

        // Per-author topic-generation slice: process one author's topics in a dedicated cron event.
        // Args: author_id, correlation_id.
        add_action('aips_process_author_topics_slice', function( $author_id, $correlation_id = '' ) {
            AIPS_Author_Topics_Scheduler::instance()->process_author_slice(
                (int) $author_id,
                (string) $correlation_id
            );
        }, 10, 2);

        // Retry failed topic-generation slices: re-dispatch authors that failed to schedule.
        // Args: author_ids_json, correlation_id.
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
        // Args: author_id, correlation_id.
        add_action('aips_process_author_post_slice', function( $author_id, $correlation_id = '' ) {
            AIPS_Author_Post_Generator::instance()->process_author_slice(
                (int) $author_id,
                (string) $correlation_id
            );
        }, 10, 2);

        // Retry failed post-generation slices: re-dispatch authors that failed to schedule.
        // Args: author_ids_json, correlation_id.
        add_action('aips_retry_failed_author_slices_posts', function( $author_ids_json, $correlation_id = '' ) {
            AIPS_Author_Post_Generator::instance()->retry_failed_post_slices(
                (string) $author_ids_json,
                (string) $correlation_id
            );
        }, 10, 2);

        // Async bulk-batch processing: each single event processes one slice of a stored job.
        // Args: job_id, start_index, batch_size, total_quantity, correlation_id.
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

        // Register bulk-batch strategies for each supported job type.
        // Strategies are registered directly (not via add_action) so they are
        // available immediately when boot_cron() runs — before any later cron
        // hook could fire in the same request.  Closures are safe here because
        // they are never serialised; only the job_type string goes to the DB.
        //
        // Every handler receives ($item, $job_id, $job) so that per-job context
        // stored in $job->options (e.g. template_id) can be read inside the closure.
        $processor = AIPS_Bulk_Batch_Processor::instance();

        $processor->register(
            'author_topic_post',
            function( $topic_id, $job_id, $job ) {
                return AIPS_Author_Post_Generator::instance()->generate_now( (int) $topic_id );
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

                return $generator->generate_post( $template, null, $topic );
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

                $context   = new AIPS_Template_Context( $template, null, (string) $item['topic'], 'cron' );
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

        // Daily cleanup of completed/failed bulk-batch job rows.
        add_action('aips_cleanup_bulk_batch_jobs', function() {
            $store   = new AIPS_Bulk_Batch_Job_Store();
            $deleted = $store->cleanup_old_jobs();
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

        // Lazy-resolve the embeddings worker only when its hook fires.
        add_action('aips_process_author_embeddings', function($args) {
            AIPS_Embeddings_Cron::instance()->process_author_embeddings($args);
        }, 10, 1);

        // Research controller registers the aips_scheduled_research cron hook.
        new AIPS_Research_Controller();

        // Sources cron: fetch content for sources that have a fetch_interval configured.
        // AIPS_Sources_Cron::schedule() handles registering the cron event at the
        // correct recurrence (every_6_hours) during construction.
        AIPS_Sources_Cron::instance();

        // Notification event handler receives generation-failure/quota alerts from cron.
        new AIPS_Notifications();

        // Reconciler's save_post hook fires when cron creates or updates posts.
        new AIPS_Partial_Generation_State_Reconciler();

        // Internal Links indexing cron — construct the controller lazily only
        // when the cron hook fires to avoid eager instantiation on every cron boot.
        add_action('aips_index_posts_batch', function($args) {
            (new AIPS_Internal_Links_Controller())->process_indexing_batch_cron($args);
        }, 10, 1);

        // Export-file cleanup cron handler.
        add_action('aips_cleanup_export_files', array('AIPS_Session_To_JSON', 'handle_export_cleanup'));
    }
}
