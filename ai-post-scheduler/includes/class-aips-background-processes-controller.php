<?php
/**
 * Background Processes Controller
 *
 * Control surface and AJAX endpoints for listing, estimating and controlling background processes.
 * Powered by AIPS_Job_Manager and domain services.
 * The Diagnostics "Background Processes" tab, the admin bar, the Internal Links page,
 * and Heartbeat all use this controller.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.11
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Background_Processes_Controller {

	/**
	 * Nonce action shared by endpoints.
	 */
	const NONCE_ACTION = 'aips_bg_nonce';

	const ACTION_START  = 'start';
	const ACTION_PAUSE  = 'pause';
	const ACTION_RESUME = 'resume';
	const ACTION_CANCEL = 'cancel';

	const SUMMARY_TRANSIENT = 'aips_bg_summary';
	const SUMMARY_TTL       = 15;

	const STATUS_IDLE = 'idle';

	/**
	 * @var AIPS_Job_Manager
	 */
	private $job_manager;

	/**
	 * @var AIPS_Job_Repository
	 */
	private $job_repo;

	/**
	 * @var AIPS_Author_Embeddings_Service
	 */
	private $author_embeddings_service;

	/**
	 * @var AIPS_Internal_Links_Indexing_Service
	 */
	private $internal_links_indexing_service;

	/**
	 * @var AIPS_Relationships_Recompute_Service
	 */
	private $relationships_recompute_service;

	/**
	 * @var AIPS_Link_Index_Service|null
	 */
	private $link_index_service;

	/**
	 * @var AIPS_Content_Indexer_Service|null
	 */
	private $content_indexer_service;

	/**
	 * @param AIPS_Job_Manager|null     $job_manager
	 * @param AIPS_Job_Repository|null  $job_repo
	 */
	public function __construct( ?AIPS_Job_Manager $job_manager = null, ?AIPS_Job_Repository $job_repo = null ) {
		$container          = AIPS_Container::get_instance();
		$this->job_manager  = ( $job_manager instanceof AIPS_Job_Manager ) ? $job_manager : $container->get_job_manager();
		$this->job_repo     = $job_repo ?: $container->get_job_repository();

		$this->author_embeddings_service       = new AIPS_Author_Embeddings_Service();
		$this->internal_links_indexing_service = new AIPS_Internal_Links_Indexing_Service();
		$this->relationships_recompute_service = new AIPS_Relationships_Recompute_Service();

		add_action( 'wp_ajax_aips_bg_list', array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_aips_bg_estimate', array( $this, 'ajax_estimate' ) );
		add_action( 'wp_ajax_aips_bg_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_aips_bg_pause', array( $this, 'ajax_pause' ) );
		add_action( 'wp_ajax_aips_bg_resume', array( $this, 'ajax_resume' ) );
		add_action( 'wp_ajax_aips_bg_cancel', array( $this, 'ajax_cancel' ) );
		add_action( 'wp_ajax_aips_bg_pause_all', array( $this, 'ajax_pause_all' ) );
	}

	/**
	 * Lazy-load Link Index Service.
	 *
	 * @return AIPS_Link_Index_Service
	 */
	private function get_link_index_service(): AIPS_Link_Index_Service {
		if ( null === $this->link_index_service ) {
			$this->link_index_service = AIPS_Container::get_instance()->make( AIPS_Link_Index_Service::class );
		}
		return $this->link_index_service;
	}

	/**
	 * Lazy-load Content Indexer Service.
	 *
	 * @return AIPS_Content_Indexer_Service
	 */
	private function get_content_indexer_service(): AIPS_Content_Indexer_Service {
		if ( null === $this->content_indexer_service ) {
			$this->content_indexer_service = AIPS_Container::get_instance()->make( AIPS_Content_Indexer_Service::class );
		}
		return $this->content_indexer_service;
	}

	/**
	 * Localized labels for every status a snapshot can report.
	 *
	 * @return array<string,string>
	 */
	public static function get_status_labels(): array {
		return array(
			'idle'          => __( 'Idle', 'ai-post-scheduler' ),
			'running'       => __( 'Running', 'ai-post-scheduler' ),
			'waiting_quota' => __( 'Waiting for quota', 'ai-post-scheduler' ),
			'cooldown'      => __( 'Cooldown', 'ai-post-scheduler' ),
			'paused'        => __( 'Paused', 'ai-post-scheduler' ),
			'completed'     => __( 'Completed', 'ai-post-scheduler' ),
			'failed'        => __( 'Failed', 'ai-post-scheduler' ),
			'cancelled'     => __( 'Stopped', 'ai-post-scheduler' ),
		);
	}

	/**
	 * Whether a status means work is in flight (running or waiting to retry).
	 *
	 * @param string $status Snapshot status.
	 * @return bool
	 */
	public static function is_active_status( string $status ): bool {
		return in_array( $status, array( 'running', 'waiting_quota', 'cooldown' ), true );
	}

	/**
	 * Only the snapshots that are running or waiting to retry.
	 *
	 * @param array[] $snapshots Snapshots.
	 * @return array[]
	 */
	public static function filter_active( array $snapshots ): array {
		return array_values( array_filter( $snapshots, function ( $snapshot ) {
			return ! empty( $snapshot['is_active'] );
		} ) );
	}

	/**
	 * Build a normalized snapshot.
	 *
	 * @param array $data Raw values.
	 * @return array
	 */
	private function make_snapshot( array $data ): array {
		$status = isset( $data['status'] ) ? (string) $data['status'] : self::STATUS_IDLE;
		$labels = self::get_status_labels();

		$processed = isset( $data['processed'] ) ? max( 0, (int) $data['processed'] ) : 0;
		$total     = isset( $data['total'] ) ? max( 0, (int) $data['total'] ) : 0;
		$percent   = $total > 0 ? min( 100, (int) round( ( $processed / $total ) * 100 ) ) : 0;

		if ( 'completed' === $status ) {
			$percent = 100;
		}

		$is_active = self::is_active_status( $status );
		$is_open   = $is_active || 'paused' === $status;

		return array(
			'key'              => isset( $data['key'] ) ? (string) $data['key'] : '',
			'label'            => isset( $data['label'] ) ? (string) $data['label'] : '',
			'description'      => isset( $data['description'] ) ? (string) $data['description'] : '',
			'uses_ai'          => ! empty( $data['uses_ai'] ),
			'status'           => $status,
			'status_label'     => isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
			'is_active'        => $is_active,
			'can_start'        => isset( $data['can_start'] ) ? (bool) $data['can_start'] : ! $is_open,
			'can_pause'        => isset( $data['can_pause'] ) ? (bool) $data['can_pause'] : $is_active,
			'can_resume'       => isset( $data['can_resume'] ) ? (bool) $data['can_resume'] : ( 'paused' === $status ),
			'can_cancel'       => isset( $data['can_cancel'] ) ? (bool) $data['can_cancel'] : $is_open,
			'processed'        => $processed,
			'total'            => $total,
			'failed'           => isset( $data['failed'] ) ? max( 0, (int) $data['failed'] ) : 0,
			'percent'          => $percent,
			'ai_calls_used'    => isset( $data['ai_calls_used'] ) ? max( 0, (int) $data['ai_calls_used'] ) : 0,
			'ai_calls_budget'  => isset( $data['ai_calls_budget'] ) ? max( 0, (int) $data['ai_calls_budget'] ) : 0,
			'message'          => isset( $data['message'] ) ? (string) $data['message'] : '',
			'next_run_at'      => isset( $data['next_run_at'] ) ? max( 0, (int) $data['next_run_at'] ) : 0,
			'started_at'       => isset( $data['started_at'] ) ? max( 0, (int) $data['started_at'] ) : 0,
			'updated_at'       => isset( $data['updated_at'] ) ? max( 0, (int) $data['updated_at'] ) : 0,
			'run_id'           => isset( $data['run_id'] ) ? (string) $data['run_id'] : '',
			'last_status'      => isset( $data['last_status'] ) ? (string) $data['last_status'] : '',
			'last_finished_at' => isset( $data['last_finished_at'] ) ? max( 0, (int) $data['last_finished_at'] ) : 0,
		);
	}

	/**
	 * Fresh snapshots of every process.
	 *
	 * @return array[]
	 */
	public function get_snapshots(): array {
		return array(
			$this->get_snapshot( AIPS_Internal_Links_Indexing_Service::KEY ),
			$this->get_snapshot( 'content_indexer_queue' ),
			$this->get_snapshot( 'link_index_scan' ),
			$this->get_snapshot( AIPS_Relationships_Recompute_Service::KEY ),
			$this->get_snapshot( AIPS_Author_Embeddings_Service::KEY ),
			$this->get_snapshot( 'bulk_generation' ),
		);
	}

	/**
	 * Snapshots briefly cached for page-load rendering.
	 *
	 * @return array[]
	 */
	public function get_summary(): array {
		$cached = get_transient( self::SUMMARY_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$snapshots = $this->get_snapshots();
		set_transient( self::SUMMARY_TRANSIENT, $snapshots, self::SUMMARY_TTL );

		return $snapshots;
	}

	/**
	 * Flush cached page-load summary.
	 *
	 * @return void
	 */
	public function flush_summary(): void {
		delete_transient( self::SUMMARY_TRANSIENT );
	}

	/**
	 * Return snapshot for a specific process key.
	 *
	 * @param string $key Process key.
	 * @return array
	 */
	public function get_snapshot( string $key ): array {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				return $this->get_author_embeddings_snapshot();

			case AIPS_Internal_Links_Indexing_Service::KEY:
				return $this->get_internal_links_indexing_snapshot();

			case AIPS_Relationships_Recompute_Service::KEY:
				return $this->get_relationships_recompute_snapshot();

			case 'link_index_scan':
				return $this->get_link_index_scan_snapshot();

			case 'content_indexer_queue':
				return $this->get_content_indexer_queue_snapshot();

			case 'bulk_generation':
				return $this->get_bulk_generation_snapshot();

			default:
				return $this->make_snapshot( array( 'key' => $key, 'status' => self::STATUS_IDLE ) );
		}
	}

	/**
	 * Snapshot for Author Topic Embeddings.
	 *
	 * @return array
	 */
	private function get_author_embeddings_snapshot(): array {
		$key         = AIPS_Author_Embeddings_Service::KEY;
		$label       = $this->author_embeddings_service->get_label();
		$description = $this->author_embeddings_service->get_description();
		$uses_ai     = true;

		$active_jobs = $this->job_repo->get_active_jobs( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS );
		$job         = ! empty( $active_jobs ) ? $active_jobs[0] : null;

		if ( ! $job ) {
			$paused = $this->job_repo->query( array(
				'job_type' => AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS,
				'status'   => AIPS_Job_Record::STATUS_PAUSED,
			), 1 );
			$job = ! empty( $paused ) ? $paused[0] : null;
		}

		if ( $job ) {
			$status  = $job->is_paused() ? 'paused' : 'running';
			$message = '';

			if ( 'running' === $status ) {
				$limiter  = ( new AIPS_Embeddings_Service() )->get_rate_limiter();
				$cooldown = $limiter->get_cooldown_status();
				$stats    = $limiter->get_usage_stats();

				if ( ! empty( $cooldown['is_paused'] ) ) {
					$status  = 'cooldown';
					$message = (string) $cooldown['reason'];
				} elseif ( ! empty( $stats['is_rate_limited'] ) ) {
					$status  = 'waiting_quota';
					$message = __( 'Embeddings quota reached. The process resumes automatically.', 'ai-post-scheduler' );
				}
			}

			return $this->make_snapshot( array(
				'key'             => $key,
				'label'           => $label,
				'description'     => $description,
				'uses_ai'         => $uses_ai,
				'status'          => $status,
				'processed'       => $job->get_processed(),
				'total'           => $job->get_total(),
				'failed'          => $job->get_failed(),
				'ai_calls_used'   => $job->get_processed(),
				'message'         => $message,
				'started_at'      => $job->get_created_at(),
				'updated_at'      => $job->get_updated_at(),
				'next_run_at'     => $job->get_next_run_at(),
				'run_id'          => $job->get_job_id(),
			) );
		}

		$remaining = $this->author_embeddings_service->count_remaining();
		$recent    = $this->job_repo->query( array( 'job_type' => AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS ), 1 );
		$last_job  = ! empty( $recent ) ? $recent[0] : null;

		return $this->make_snapshot( array(
			'key'              => $key,
			'label'            => $label,
			'description'      => $description,
			'uses_ai'          => $uses_ai,
			'status'           => self::STATUS_IDLE,
			'total'            => $remaining,
			'processed'        => 0,
			'can_start'        => $remaining > 0,
			'last_status'      => $last_job ? $last_job->get_status() : '',
			'last_finished_at' => $last_job ? $last_job->get_finished_at() : 0,
		) );
	}

	/**
	 * Snapshot for Internal Links Indexing.
	 *
	 * @return array
	 */
	private function get_internal_links_indexing_snapshot(): array {
		$key         = AIPS_Internal_Links_Indexing_Service::KEY;
		$label       = $this->internal_links_indexing_service->get_label();
		$description = $this->internal_links_indexing_service->get_description();
		$uses_ai     = true;

		$active_jobs = $this->job_repo->get_active_jobs( AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING );
		$job         = ! empty( $active_jobs ) ? $active_jobs[0] : null;

		if ( ! $job ) {
			$paused = $this->job_repo->query( array(
				'job_type' => AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING,
				'status'   => AIPS_Job_Record::STATUS_PAUSED,
			), 1 );
			$job = ! empty( $paused ) ? $paused[0] : null;
		}

		if ( $job ) {
			$status  = $job->is_paused() ? 'paused' : 'running';
			$message = '';

			if ( 'running' === $status ) {
				$limiter  = ( new AIPS_Embeddings_Service() )->get_rate_limiter();
				$cooldown = $limiter->get_cooldown_status();
				$stats    = $limiter->get_usage_stats();

				if ( ! empty( $cooldown['is_paused'] ) ) {
					$status  = 'cooldown';
					$message = (string) $cooldown['reason'];
				} elseif ( ! empty( $stats['is_rate_limited'] ) ) {
					$status  = 'waiting_quota';
					$message = __( 'Embeddings quota reached. The process resumes automatically.', 'ai-post-scheduler' );
				}
			}

			return $this->make_snapshot( array(
				'key'             => $key,
				'label'           => $label,
				'description'     => $description,
				'uses_ai'         => $uses_ai,
				'status'          => $status,
				'processed'       => $job->get_processed(),
				'total'           => $job->get_total(),
				'failed'          => $job->get_failed(),
				'ai_calls_used'   => $job->get_processed(),
				'message'         => $message,
				'started_at'      => $job->get_created_at(),
				'updated_at'      => $job->get_updated_at(),
				'next_run_at'     => $job->get_next_run_at(),
				'run_id'          => $job->get_job_id(),
			) );
		}

		$remaining = $this->internal_links_indexing_service->count_remaining();
		$recent    = $this->job_repo->query( array( 'job_type' => AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING ), 1 );
		$last_job  = ! empty( $recent ) ? $recent[0] : null;

		return $this->make_snapshot( array(
			'key'              => $key,
			'label'            => $label,
			'description'      => $description,
			'uses_ai'          => $uses_ai,
			'status'           => self::STATUS_IDLE,
			'total'            => $remaining,
			'processed'        => 0,
			'can_start'        => $remaining > 0,
			'last_status'      => $last_job ? $last_job->get_status() : '',
			'last_finished_at' => $last_job ? $last_job->get_finished_at() : 0,
		) );
	}

	/**
	 * Snapshot for Relationships Recompute.
	 *
	 * @return array
	 */
	private function get_relationships_recompute_snapshot(): array {
		$key         = AIPS_Relationships_Recompute_Service::KEY;
		$label       = $this->relationships_recompute_service->get_label();
		$description = $this->relationships_recompute_service->get_description();
		$uses_ai     = false;

		$active_jobs = $this->job_repo->get_active_jobs( AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE );
		$job         = ! empty( $active_jobs ) ? $active_jobs[0] : null;

		if ( ! $job ) {
			$paused = $this->job_repo->query( array(
				'job_type' => AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
				'status'   => AIPS_Job_Record::STATUS_PAUSED,
			), 1 );
			$job = ! empty( $paused ) ? $paused[0] : null;
		}

		if ( $job ) {
			$status = $job->is_paused() ? 'paused' : 'running';

			return $this->make_snapshot( array(
				'key'             => $key,
				'label'           => $label,
				'description'     => $description,
				'uses_ai'         => $uses_ai,
				'status'          => $status,
				'processed'       => $job->get_processed(),
				'total'           => $job->get_total(),
				'failed'          => $job->get_failed(),
				'ai_calls_used'   => 0,
				'started_at'      => $job->get_created_at(),
				'updated_at'      => $job->get_updated_at(),
				'next_run_at'     => $job->get_next_run_at(),
				'run_id'          => $job->get_job_id(),
			) );
		}

		$remaining = $this->relationships_recompute_service->count_remaining( AIPS_Relationships_Recompute_Service::MODE_MISSING );
		$recent    = $this->job_repo->query( array( 'job_type' => AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE ), 1 );
		$last_job  = ! empty( $recent ) ? $recent[0] : null;

		return $this->make_snapshot( array(
			'key'              => $key,
			'label'            => $label,
			'description'      => $description,
			'uses_ai'          => $uses_ai,
			'status'           => self::STATUS_IDLE,
			'total'            => $remaining,
			'processed'        => 0,
			'can_start'        => $remaining > 0,
			'last_status'      => $last_job ? $last_job->get_status() : '',
			'last_finished_at' => $last_job ? $last_job->get_finished_at() : 0,
		) );
	}

	/**
	 * Snapshot for Link Index Scan.
	 *
	 * @return array
	 */
	private function get_link_index_scan_snapshot(): array {
		$key         = 'link_index_scan';
		$label       = __( 'Link Index Scan', 'ai-post-scheduler' );
		$description = __( 'Reads the links in published posts to build the Link Report. Parses post HTML only; no AI calls.', 'ai-post-scheduler' );
		$uses_ai     = false;

		$scan = $this->get_link_index_service()->get_backfill_status();

		if ( ! $scan ) {
			return $this->make_snapshot( array(
				'key'         => $key,
				'label'       => $label,
				'description' => $description,
				'uses_ai'     => $uses_ai,
				'status'      => self::STATUS_IDLE,
			) );
		}

		$map = array(
			AIPS_Job_Record::STATUS_PENDING   => 'running',
			AIPS_Job_Record::STATUS_RUNNING   => 'running',
			'processing'                      => 'running',
			AIPS_Link_Index_Service::STATUS_PAUSED    => 'paused',
			AIPS_Job_Record::STATUS_COMPLETED => 'completed',
			AIPS_Job_Record::STATUS_FAILED    => 'failed',
			AIPS_Link_Index_Service::STATUS_CANCELLED => 'cancelled',
		);
		$status = isset( $map[ $scan['status'] ] ) ? $map[ $scan['status'] ] : self::STATUS_IDLE;

		if ( in_array( $status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return $this->make_snapshot( array(
				'key'         => $key,
				'label'       => $label,
				'description' => $description,
				'uses_ai'     => $uses_ai,
				'status'      => self::STATUS_IDLE,
				'last_status' => $status,
				'processed'   => $scan['processed'],
				'total'       => $scan['total'],
			) );
		}

		return $this->make_snapshot( array(
			'key'         => $key,
			'label'       => $label,
			'description' => $description,
			'uses_ai'     => $uses_ai,
			'status'      => $status,
			'processed'   => $scan['processed'],
			'total'       => $scan['total'],
		) );
	}

	/**
	 * Snapshot for Content Indexer Queue.
	 *
	 * @return array
	 */
	private function get_content_indexer_queue_snapshot(): array {
		$key         = 'content_indexer_queue';
		$label       = __( 'Content Indexer Queue', 'ai-post-scheduler' );
		$description = __( 'Indexes newly published and updated posts and topics in small slices. Pausing it keeps the queued items for later.', 'ai-post-scheduler' );
		$uses_ai     = true;

		$indexer = $this->get_content_indexer_service();
		$queue   = $indexer->get_queue_status();
		$pending = (int) $queue['pending_count'];
		$status  = self::STATUS_IDLE;
		$message = '';

		if ( $indexer->is_queue_paused() ) {
			$status  = 'paused';
			$message = __( 'Paused by an administrator.', 'ai-post-scheduler' );
		} elseif ( $pending > 0 ) {
			$limiter  = new AIPS_Embeddings_Rate_Limiter();
			$cooldown = $limiter->get_cooldown_status();
			$stats    = $limiter->get_usage_stats();

			if ( ! empty( $cooldown['is_paused'] ) ) {
				$status  = 'cooldown';
				$message = (string) $cooldown['reason'];
			} elseif ( ! empty( $stats['is_rate_limited'] ) ) {
				$status  = 'waiting_quota';
				$message = __( 'Embeddings quota reached. The queue resumes automatically.', 'ai-post-scheduler' );
			} else {
				$status = 'running';
			}
		}

		if ( '' === $message && $pending > 0 ) {
			$message = sprintf(
				/* translators: %s: number of queued items. */
				_n( '%s item queued.', '%s items queued.', $pending, 'ai-post-scheduler' ),
				number_format_i18n( $pending )
			);
		}

		return $this->make_snapshot( array(
			'key'         => $key,
			'label'       => $label,
			'description' => $description,
			'uses_ai'     => $uses_ai,
			'status'      => $status,
			'total'       => $pending,
			'message'     => $message,
			'can_start'   => false,
		) );
	}

	/**
	 * Return all open (pending, running, or paused) bulk generation jobs without a 100-row cap.
	 *
	 * @return AIPS_Job_Record[]
	 */
	private function get_open_bulk_generation_jobs(): array {
		$types = array_values( array_filter( array_map( 'sanitize_key', (array) apply_filters(
			'aips_bulk_generation_job_types',
			array( 'bulk_post_generation', 'bulk_post_publish', 'author_topic_post', 'planner_post', 'trending_topic_post', 'review_publish', 'bulk_batch_slice' )
		) ) ) );

		$open = array();
		foreach ( $this->job_repo->get_open_jobs() as $job ) {
			if ( in_array( $job->get_job_type(), $types, true ) ) {
				$open[] = $job;
			}
		}

		return $open;
	}

	/**
	 * Snapshot for Bulk Generation.
	 *
	 * @return array
	 */
	private function get_bulk_generation_snapshot(): array {
		$key         = 'bulk_generation';
		$label       = __( 'Bulk Post Generation', 'ai-post-scheduler' );
		$description = __( 'Large "generate all" runs from Author Topics, the Planner and Research, spread over many background slices. Each post generated uses AI. Start them from the screen that picks the topics.', 'ai-post-scheduler' );
		$uses_ai     = true;

		$open = $this->get_open_bulk_generation_jobs();

		if ( empty( $open ) ) {
			return $this->make_snapshot( array(
				'key'         => $key,
				'label'       => $label,
				'description' => $description,
				'uses_ai'     => $uses_ai,
				'status'      => self::STATUS_IDLE,
				'can_start'   => false,
			) );
		}

		$processed = 0;
		$total     = 0;
		$running   = false;
		$started   = 0;
		$updated   = 0;

		foreach ( $open as $job ) {
			$processed += $job->get_processed();
			$total     += $job->get_total();
			$running    = $running || $job->is_active();
			$started    = $started === 0 ? (int) $job->get_created_at() : min( $started, (int) $job->get_created_at() );
			$updated    = max( $updated, (int) $job->get_updated_at() );
		}

		return $this->make_snapshot( array(
			'key'         => $key,
			'label'       => $label,
			'description' => $description,
			'uses_ai'     => $uses_ai,
			'status'      => $running ? 'running' : 'paused',
			'processed'   => $processed,
			'total'       => $total,
			'started_at'  => $started,
			'updated_at'  => $updated,
			'can_start'   => false,
			'message'     => sprintf(
				/* translators: %d: number of bulk generation jobs. */
				_n( '%d bulk job open.', '%d bulk jobs open.', count( $open ), 'ai-post-scheduler' ),
				count( $open )
			),
		) );
	}

	/**
	 * Apply a control action to one process.
	 *
	 * @param string $key     Process key.
	 * @param string $action  One of the ACTION_* constants.
	 * @param array  $options Options for ACTION_START.
	 * @return array|WP_Error Snapshot or WP_Error.
	 */
	public function control( string $key, string $action, array $options = array() ) {
		$key = sanitize_key( $key );

		switch ( $action ) {
			case self::ACTION_START:
				$res = $this->start_process( $key, $options );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				break;

			case self::ACTION_PAUSE:
				$res = $this->pause_process( $key );
				if ( ! $res ) {
					return new WP_Error( 'aips_bg_cannot_pause', __( 'This process is not running, so it cannot be paused.', 'ai-post-scheduler' ) );
				}
				break;

			case self::ACTION_RESUME:
				$res = $this->resume_process( $key );
				if ( ! $res ) {
					return new WP_Error( 'aips_bg_cannot_resume', __( 'This process is not paused, so it cannot be resumed.', 'ai-post-scheduler' ) );
				}
				break;

			case self::ACTION_CANCEL:
				$res = $this->cancel_process( $key );
				if ( ! $res ) {
					return new WP_Error( 'aips_bg_cannot_cancel', __( 'This process is not running or paused, so there is nothing to stop.', 'ai-post-scheduler' ) );
				}
				break;

			default:
				return new WP_Error( 'aips_bg_unknown_action', __( 'Unknown action.', 'ai-post-scheduler' ) );
		}

		$this->flush_summary();

		return $this->get_snapshot( $key );
	}

	/**
	 * Start a process by key.
	 *
	 * @param string $key
	 * @param array  $options
	 * @return true|WP_Error
	 */
	private function start_process( string $key, array $options ) {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				$can = $this->author_embeddings_service->can_run();
				if ( is_wp_error( $can ) ) {
					return $can;
				}
				$author_id = isset( $options['author_id'] ) ? absint( $options['author_id'] ) : 0;
				$active    = $this->job_repo->get_open_jobs( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS );
				if ( ! empty( $active ) ) {
					return new WP_Error( 'aips_bg_already_open', __( 'Topic embeddings are already running.', 'ai-post-scheduler' ) );
				}
				$remaining = $this->author_embeddings_service->count_remaining( $author_id );
				if ( $remaining <= 0 ) {
					return new WP_Error( 'aips_bg_nothing_to_do', __( 'Every topic already has an embedding.', 'ai-post-scheduler' ) );
				}
				$this->job_manager->dispatch(
					AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS,
					array( 'author_id' => $author_id ),
					array( 'total' => $remaining )
				);
				return true;

			case AIPS_Internal_Links_Indexing_Service::KEY:
				$can = $this->internal_links_indexing_service->can_run();
				if ( is_wp_error( $can ) ) {
					return $can;
				}
				$active = $this->job_repo->get_open_jobs( AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING );
				if ( ! empty( $active ) ) {
					return new WP_Error( 'aips_bg_already_open', __( 'Internal links indexing is already running.', 'ai-post-scheduler' ) );
				}
				$remaining = $this->internal_links_indexing_service->count_remaining();
				if ( $remaining <= 0 ) {
					return new WP_Error( 'aips_bg_nothing_to_do', __( 'Every post is already indexed.', 'ai-post-scheduler' ) );
				}
				$this->job_manager->dispatch(
					AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING,
					array(),
					array( 'total' => $remaining )
				);
				return true;

			case AIPS_Relationships_Recompute_Service::KEY:
				$mode   = isset( $options['mode'] ) && in_array( $options['mode'], array( 'missing', 'all' ), true ) ? $options['mode'] : AIPS_Relationships_Recompute_Service::MODE_MISSING;
				$active = $this->job_repo->get_open_jobs( AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE );
				if ( ! empty( $active ) ) {
					return new WP_Error( 'aips_bg_already_open', __( 'Relationships recompute is already running.', 'ai-post-scheduler' ) );
				}
				$remaining = $this->relationships_recompute_service->count_remaining( $mode );
				if ( $remaining <= 0 ) {
					return new WP_Error( 'aips_bg_nothing_to_do', __( 'No relationships to compute.', 'ai-post-scheduler' ) );
				}
				$this->job_manager->dispatch(
					AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
					array( 'mode' => $mode ),
					array( 'total' => $remaining )
				);
				return true;

			case 'link_index_scan':
				$res = $this->get_link_index_service()->start_backfill( AIPS_Link_Index_Service::MODE_MISSING );
				return is_wp_error( $res ) ? $res : true;

			case 'content_indexer_queue':
				$indexer = $this->get_content_indexer_service();
				$queue   = $indexer->get_queue_status();
				if ( (int) $queue['pending_count'] <= 0 ) {
					return new WP_Error( 'aips_bg_nothing_to_do', __( 'There is nothing left to process.', 'ai-post-scheduler' ) );
				}
				$indexer->set_queue_paused( false );
				$indexer->schedule_queue_worker( AIPS_DateTime::now()->timestamp() + 1 );
				return true;

			case 'bulk_generation':
				return new WP_Error(
					'aips_bg_start_elsewhere',
					__( 'Bulk generation is started from Author Topics, the Planner or Research, where you choose the topics.', 'ai-post-scheduler' )
				);

			default:
				return new WP_Error( 'aips_bg_unknown_process', __( 'Unknown background process.', 'ai-post-scheduler' ) );
		}
	}

	/**
	 * Pause a process by key.
	 *
	 * @param string $key
	 * @return bool
	 */
	private function pause_process( string $key ): bool {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				return $this->pause_active_job_of_type( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS );

			case AIPS_Internal_Links_Indexing_Service::KEY:
				return $this->pause_active_job_of_type( AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING );

			case AIPS_Relationships_Recompute_Service::KEY:
				return $this->pause_active_job_of_type( AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE );

			case 'link_index_scan':
				return $this->get_link_index_service()->pause_backfill();

			case 'content_indexer_queue':
				$indexer = $this->get_content_indexer_service();
				$queue   = $indexer->get_queue_status();
				if ( $indexer->is_queue_paused() || (int) $queue['pending_count'] <= 0 ) {
					return false;
				}
				$indexer->set_queue_paused( true );
				return true;

			case 'bulk_generation':
				$paused = false;
				foreach ( $this->get_open_bulk_generation_jobs() as $job ) {
					if ( $job->is_active() ) {
						if ( $this->job_manager->pause( $job->get_job_id() ) ) {
							$paused = true;
						}
					}
				}
				return $paused;

			default:
				return false;
		}
	}

	/**
	 * Resume a paused process by key.
	 *
	 * @param string $key
	 * @return bool
	 */
	private function resume_process( string $key ): bool {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				return $this->resume_paused_job_of_type( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS );

			case AIPS_Internal_Links_Indexing_Service::KEY:
				return $this->resume_paused_job_of_type( AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING );

			case AIPS_Relationships_Recompute_Service::KEY:
				return $this->resume_paused_job_of_type( AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE );

			case 'link_index_scan':
				return $this->get_link_index_service()->resume_backfill();

			case 'content_indexer_queue':
				$indexer = $this->get_content_indexer_service();
				if ( ! $indexer->is_queue_paused() ) {
					return false;
				}
				$indexer->set_queue_paused( false );
				$indexer->schedule_queue_worker( AIPS_DateTime::now()->timestamp() + 1 );
				return true;

			case 'bulk_generation':
				$resumed = false;
				foreach ( $this->get_open_bulk_generation_jobs() as $job ) {
					if ( $job->is_paused() ) {
						if ( $this->job_manager->resume( $job->get_job_id() ) ) {
							$resumed = true;
						}
					}
				}
				return $resumed;

			default:
				return false;
		}
	}

	/**
	 * Cancel a running or paused process by key.
	 *
	 * @param string $key
	 * @return bool
	 */
	private function cancel_process( string $key ): bool {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				return $this->cancel_open_job_of_type( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_EMBEDDINGS );

			case AIPS_Internal_Links_Indexing_Service::KEY:
				return $this->cancel_open_job_of_type( AIPS_Job_Registry::TYPE_INTERNAL_LINKS_INDEXING );

			case AIPS_Relationships_Recompute_Service::KEY:
				return $this->cancel_open_job_of_type( AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE );

			case 'link_index_scan':
				return $this->get_link_index_service()->cancel_backfill();

			case 'content_indexer_queue':
				$indexer = $this->get_content_indexer_service();
				$queue   = $indexer->get_queue_status();
				if ( (int) $queue['pending_count'] <= 0 && ! $indexer->is_queue_paused() ) {
					return false;
				}
				$indexer->clear_queue();
				return true;

			case 'bulk_generation':
				$cancelled = false;
				foreach ( $this->get_open_bulk_generation_jobs() as $job ) {
					if ( $job->is_active() || $job->is_paused() ) {
						if ( $this->job_manager->cancel( $job->get_job_id() ) ) {
							$cancelled = true;
						}
					}
				}
				return $cancelled;

			default:
				return false;
		}
	}

	private function pause_active_job_of_type( string $type ): bool {
		$active = $this->job_repo->get_active_jobs( $type );
		if ( empty( $active ) ) {
			return false;
		}
		return $this->job_manager->pause( $active[0]->get_job_id() );
	}

	private function resume_paused_job_of_type( string $type ): bool {
		$paused = $this->job_repo->query( array(
			'job_type' => $type,
			'status'   => AIPS_Job_Record::STATUS_PAUSED,
		), 1 );
		if ( empty( $paused ) ) {
			return false;
		}
		return $this->job_manager->resume( $paused[0]->get_job_id() );
	}

	private function cancel_open_job_of_type( string $type ): bool {
		$active = $this->job_repo->get_active_jobs( $type );
		if ( ! empty( $active ) ) {
			return $this->job_manager->cancel( $active[0]->get_job_id() );
		}
		$paused = $this->job_repo->query( array(
			'job_type' => $type,
			'status'   => AIPS_Job_Record::STATUS_PAUSED,
		), 1 );
		if ( ! empty( $paused ) ) {
			return $this->job_manager->cancel( $paused[0]->get_job_id() );
		}
		return false;
	}

	/**
	 * Pause every process that is currently running.
	 *
	 * @return string[] Keys of paused processes.
	 */
	public function pause_all(): array {
		$paused = array();

		foreach ( $this->get_snapshots() as $snap ) {
			if ( ! empty( $snap['can_pause'] ) ) {
				$key = $snap['key'];
				if ( $this->pause_process( $key ) ) {
					$paused[] = $key;
				}
			}
		}

		$this->flush_summary();

		return $paused;
	}

	/**
	 * Estimate cost and duration for starting a process now.
	 *
	 * @param string $key
	 * @param array  $options
	 * @return array
	 */
	public function get_estimate( string $key, array $options = array() ): array {
		switch ( $key ) {
			case AIPS_Author_Embeddings_Service::KEY:
				return $this->author_embeddings_service->get_estimate( $options );

			case AIPS_Internal_Links_Indexing_Service::KEY:
				$items      = $this->internal_links_indexing_service->count_remaining();
				$limiter    = ( new AIPS_Embeddings_Service() )->get_rate_limiter();
				$daily_rate = $limiter->get_daily_limit();
				$days       = ( $daily_rate > 0 ) ? (int) ceil( $items / $daily_rate ) : 0;
				$message    = $items <= 0
					? __( 'Every post in scope is already indexed.', 'ai-post-scheduler' )
					: sprintf(
						/* translators: %s: number of posts. */
						_n( '%s post will be indexed using one embedding call.', '%s posts will be indexed using one embedding call each.', $items, 'ai-post-scheduler' ),
						number_format_i18n( $items )
					);
				return array(
					'items'      => $items,
					'ai_calls'   => $items,
					'days'       => $days,
					'daily_rate' => $daily_rate,
					'message'    => $message,
				);

			case AIPS_Relationships_Recompute_Service::KEY:
				return $this->relationships_recompute_service->get_estimate( $options );

			default:
				return array(
					'items'      => 0,
					'ai_calls'   => 0,
					'days'       => 0,
					'daily_rate' => 0,
					'message'    => '',
				);
		}
	}

	/**
	 * Payload sent to browser on every Heartbeat tick.
	 *
	 * @return array
	 */
	public function get_heartbeat_payload(): array {
		$snapshots = $this->get_snapshots();
		set_transient( self::SUMMARY_TRANSIENT, $snapshots, self::SUMMARY_TTL );

		$active_jobs  = $this->job_manager ? $this->job_manager->get_active_jobs() : array();
		$active_ids   = array();
		$job_payloads = array_map( function ( $job ) use ( &$active_ids ) {
			$active_ids[ $job->get_job_id() ] = true;
			return $job->to_array();
		}, $active_jobs );

		$extra_active = 0;
		foreach ( self::filter_active( $snapshots ) as $snap ) {
			if ( 'content_indexer_queue' === $snap['key'] ) {
				$extra_active++;
			} elseif ( 'link_index_scan' === $snap['key'] ) {
				$scan = $this->get_link_index_service()->get_backfill_status();
				if ( $scan && empty( $active_ids[ $scan['job_id'] ] ) ) {
					$extra_active++;
				}
			}
		}

		return array(
			'processes'    => $snapshots,
			'jobs'         => $job_payloads,
			'active_count' => count( $active_jobs ) + $extra_active,
			'server_time'  => time(),
		);
	}

	/**
	 * Heartbeat filter.
	 *
	 * @param array $response
	 * @param array $data
	 * @return array
	 */
	public function on_heartbeat_received( $response, $data ) {
		if ( empty( $data['aips_bg'] ) || ! current_user_can( 'manage_options' ) ) {
			return $response;
		}

		$response['aips_bg'] = $this->get_heartbeat_payload();

		return $response;
	}

	/**
	 * Recent background runs for the diagnostics tab.
	 *
	 * @param int $limit
	 * @return object[]
	 */
	public function get_recent_runs( int $limit = 15 ): array {
		$jobs         = $this->job_repo->query( array(), $limit );
		$runs         = array();
		$non_ai_types = array(
			AIPS_Job_Registry::TYPE_RELATIONSHIPS_RECOMPUTE,
			AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN,
			AIPS_Link_Index_Service::BACKFILL_JOB_TYPE,
			AIPS_Job_Registry::TYPE_BULK_POST_PUBLISH,
			'review_publish',
		);

		foreach ( $jobs as $job ) {
			$uses_ai = ! in_array( $job->get_job_type(), $non_ai_types, true );
			$runs[]  = (object) array(
				'id'            => $job->get_job_id(),
				'process_key'   => $job->get_job_type(),
				'status'        => $job->get_status(),
				'started_at'    => $job->get_created_at(),
				'finished_at'   => $job->get_finished_at(),
				'processed'     => $job->get_processed(),
				'total'         => $job->get_total(),
				'failed'        => $job->get_failed(),
				'ai_calls_used' => $uses_ai ? $job->get_processed() : 0,
				'message'       => $job->get_error_message() ? $job->get_error_message() : '',
			);
		}

		return $runs;
	}

	// -------------------------------------------------------------------------
	// AJAX Endpoints
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Snapshots of every process.
	 *
	 * @return void
	 */
	public function ajax_list() {
		$this->verify_request();

		AIPS_Ajax_Response::success( array(
			'processes' => $this->get_snapshots(),
		) );
	}

	/**
	 * AJAX: Cost and duration estimate.
	 *
	 * @return void
	 */
	public function ajax_estimate() {
		$this->verify_request();

		$key      = isset( $_POST['process'] ) ? sanitize_key( wp_unslash( $_POST['process'] ) ) : '';
		$options  = $this->get_start_options_from_request();
		$estimate = $this->get_estimate( $key, $options );
		$uses_ai  = in_array( $key, array( AIPS_Author_Embeddings_Service::KEY, AIPS_Internal_Links_Indexing_Service::KEY, 'content_indexer_queue', 'bulk_generation' ), true );

		AIPS_Ajax_Response::success( array(
			'estimate'        => $estimate,
			'uses_ai'         => $uses_ai,
			'supports_budget' => in_array( $key, array( AIPS_Author_Embeddings_Service::KEY, AIPS_Internal_Links_Indexing_Service::KEY ), true ),
		) );
	}

	/**
	 * AJAX: Start a process.
	 *
	 * @return void
	 */
	public function ajax_start() {
		$this->verify_request();

		$key     = isset( $_POST['process'] ) ? sanitize_key( wp_unslash( $_POST['process'] ) ) : '';
		$options = $this->get_start_options_from_request();

		$this->respond( $this->control( $key, self::ACTION_START, $options ), __( 'Started. It runs in the background.', 'ai-post-scheduler' ) );
	}

	/**
	 * AJAX: Pause a process.
	 *
	 * @return void
	 */
	public function ajax_pause() {
		$this->verify_request();

		$key = isset( $_POST['process'] ) ? sanitize_key( wp_unslash( $_POST['process'] ) ) : '';

		$this->respond( $this->control( $key, self::ACTION_PAUSE ), __( 'Paused.', 'ai-post-scheduler' ) );
	}

	/**
	 * AJAX: Resume a process.
	 *
	 * @return void
	 */
	public function ajax_resume() {
		$this->verify_request();

		$key = isset( $_POST['process'] ) ? sanitize_key( wp_unslash( $_POST['process'] ) ) : '';

		$this->respond( $this->control( $key, self::ACTION_RESUME ), __( 'Resumed.', 'ai-post-scheduler' ) );
	}

	/**
	 * AJAX: Stop a process.
	 *
	 * @return void
	 */
	public function ajax_cancel() {
		$this->verify_request();

		$key = isset( $_POST['process'] ) ? sanitize_key( wp_unslash( $_POST['process'] ) ) : '';

		$this->respond( $this->control( $key, self::ACTION_CANCEL ), __( 'Stopped.', 'ai-post-scheduler' ) );
	}

	/**
	 * AJAX: Pause every running process.
	 *
	 * @return void
	 */
	public function ajax_pause_all() {
		$this->verify_request();

		$paused = $this->pause_all();

		AIPS_Ajax_Response::success(
			array(
				'paused'    => $paused,
				'processes' => $this->get_snapshots(),
			),
			sprintf(
				/* translators: %d: number of processes paused. */
				_n( 'Paused %d process.', 'Paused %d processes.', count( $paused ), 'ai-post-scheduler' ),
				count( $paused )
			)
		);
	}

	/**
	 * Send result of a control action.
	 *
	 * @param array|WP_Error $result
	 * @param string         $message
	 * @return void
	 */
	private function respond( $result, string $message ): void {
		if ( is_wp_error( $result ) ) {
			AIPS_Ajax_Response::error( $result->get_error_message(), $result->get_error_code() );
		}

		AIPS_Ajax_Response::success( array( 'process' => $result ), $message );
	}

	/**
	 * Start options from request.
	 *
	 * @return array
	 */
	private function get_start_options_from_request(): array {
		$options = array();

		if ( isset( $_POST['ai_budget'] ) ) {
			$options['ai_budget'] = absint( wp_unslash( $_POST['ai_budget'] ) );
		}

		if ( isset( $_POST['mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['mode'] ) );
			if ( in_array( $mode, array( 'missing', 'all' ), true ) ) {
				$options['mode'] = $mode;
			}
		}

		if ( isset( $_POST['author_id'] ) ) {
			$options['author_id'] = absint( wp_unslash( $_POST['author_id'] ) );
		}

		return $options;
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @return void
	 */
	private function verify_request(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}
	}
}
