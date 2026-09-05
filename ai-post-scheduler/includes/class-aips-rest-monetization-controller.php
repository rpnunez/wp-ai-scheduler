<?php
/**
 * REST Monetization Controller
 *
 * REST API endpoints for the Gutenberg block editor monetization sidebar,
 * ad unit blocks, intent analysis, and telemetry beacon.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_REST_Monetization_Controller extends WP_REST_Controller {

	/**
	 * Namespace for AIPS REST endpoints.
	 *
	 * @var string
	 */
	protected $namespace = 'aips/v1';

	/**
	 * Resource route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'monetization';

	/**
	 * @var AIPS_Ad_Slots_Repository
	 */
	private $slots_repo;

	/**
	 * @var AIPS_Sponsor_Campaigns_Repository
	 */
	private $campaigns_repo;

	/**
	 * @var AIPS_Monetization_Telemetry_Repository
	 */
	private $telemetry_repo;

	public function __construct(
		?AIPS_Ad_Slots_Repository $slots_repo = null,
		?AIPS_Sponsor_Campaigns_Repository $campaigns_repo = null,
		?AIPS_Monetization_Telemetry_Repository $telemetry_repo = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->slots_repo     = $slots_repo ?: ( $container->has( AIPS_Ad_Slots_Repository::class ) ? $container->make( AIPS_Ad_Slots_Repository::class ) : new AIPS_Ad_Slots_Repository() );
		$this->campaigns_repo = $campaigns_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
		$this->telemetry_repo = $telemetry_repo ?: ( $container->has( AIPS_Monetization_Telemetry_Repository::class ) ? $container->make( AIPS_Monetization_Telemetry_Repository::class ) : new AIPS_Monetization_Telemetry_Repository() );

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// GET /aips/v1/monetization/slots
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_slots' ),
					'permission_callback' => array( $this, 'check_editor_permission' ),
				),
			)
		);

		// GET /aips/v1/monetization/campaigns
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/campaigns',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_campaigns' ),
					'permission_callback' => array( $this, 'check_editor_permission' ),
				),
			)
		);

		// POST /aips/v1/monetization/analyze-intent
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/analyze-intent',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze_intent' ),
					'permission_callback' => array( $this, 'check_editor_permission' ),
					'args'                => array(
						'content' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'wp_kses_post',
						),
						'title'   => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /aips/v1/monetization/track (public beacon)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/track',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_events' ),
					'permission_callback' => array( $this, 'check_telemetry_permission' ),
				),
			)
		);
	}

	/**
	 * Permission check: can current user edit posts?
	 *
	 * @return bool
	 */
	public function check_editor_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get all active ad slots formatted for Gutenberg selects.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_slots( $request ) {
		$slots = $this->slots_repo->get_all();
		$data  = array();

		foreach ( $slots as $slot ) {
			$data[] = array(
				'id'               => (int) $slot->id,
				'name'             => $slot->name,
				'slot_type'        => $slot->slot_type,
				'position'         => $slot->position,
				'paragraph_offset' => (int) $slot->paragraph_offset,
				'min_word_count'   => (int) $slot->min_word_count,
				'device_targeting' => $slot->device_targeting,
				'status'           => $slot->status,
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'slots'   => $data,
		) );
	}

	/**
	 * Get active sponsor campaigns for Gutenberg selector.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_campaigns( $request ) {
		$campaigns = $this->campaigns_repo->get_active_campaigns();
		$data      = array();

		foreach ( $campaigns as $camp ) {
			$data[] = array(
				'id'              => (int) $camp->id,
				'brand_name'      => $camp->brand_name,
				'logo_url'        => $camp->logo_url,
				'target_url'      => $camp->target_url,
				'cta_text'        => $camp->cta_text,
				'disclosure_text' => $camp->disclosure_text,
				'status'          => $camp->status,
			);
		}

		return rest_ensure_response( array(
			'success'   => true,
			'campaigns' => $data,
		) );
	}

	/**
	 * Real-time content commercial intent analysis.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function analyze_intent( $request ) {
		$content = $request->get_param( 'content' );
		$title   = $request->get_param( 'title' ) ?: '';

		$text = strtolower( $title . ' ' . wp_strip_all_tags( $content ) );

		// Keyword indicators
		$transactional_words = array( 'buy', 'discount', 'deal', 'coupon', 'pricing', 'order', 'cheap', 'best price', 'purchase', 'promo' );
		$commercial_words    = array( 'best', 'review', 'comparison', 'vs', 'top', 'alternative', 'guide', 'features', 'worth it', 'pros and cons' );

		$trans_count = 0;
		foreach ( $transactional_words as $w ) {
			if ( strpos( $text, $w ) !== false ) {
				$trans_count++;
			}
		}

		$comm_count = 0;
		foreach ( $commercial_words as $w ) {
			if ( strpos( $text, $w ) !== false ) {
				$comm_count++;
			}
		}

		$intent = 'Informational';
		$rpm_estimate = 'Medium ($10 - $20 RPM)';
		$badge_color = '#3b82f6';

		if ( $trans_count >= 2 ) {
			$intent = 'Transactional';
			$rpm_estimate = 'High ($30 - $60+ RPM)';
			$badge_color = '#10b981';
		} elseif ( $comm_count >= 2 ) {
			$intent = 'Commercial Investigation';
			$rpm_estimate = 'High ($20 - $40 RPM)';
			$badge_color = '#8b5cf6';
		}

		$word_count = str_word_count( $text );

		return rest_ensure_response( array(
			'success'      => true,
			'intent'       => $intent,
			'rpm_estimate' => $rpm_estimate,
			'badge_color'  => $badge_color,
			'word_count'   => $word_count,
			'trans_score'  => $trans_count,
			'comm_score'   => $comm_count,
		) );
	}

	/**
	 * Verify that the telemetry request has a valid token and isn't rate-limited.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_telemetry_permission( $request ) {
		$token = $request->get_header( 'X-AIPS-Telemetry-Token' )
			?: $request->get_header( 'X-WP-Nonce' )
			?: $request->get_param( 'token' )
			?: $request->get_param( '_wpnonce' );

		if ( empty( $token ) ) {
			$json = $request->get_json_params();
			if ( is_array( $json ) && ! empty( $json['token'] ) ) {
				$token = $json['token'];
			}
		}

		if ( empty( $token ) || ( ! wp_verify_nonce( $token, 'aips_monetization_telemetry' ) && ! wp_verify_nonce( $token, 'wp_rest' ) ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid or missing telemetry verification token.', 'ai-post-scheduler' ),
				array( 'status' => 403 )
			);
		}

		// Rate limiting protection per IP (max 120 requests / min)
		$client_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$rate_key  = 'aips_tel_rate_' . md5( $client_ip );
		$hits      = (int) get_transient( $rate_key );
		if ( $hits >= 120 ) {
			return new WP_Error(
				'rest_rate_limited',
				__( 'Too many telemetry requests. Please slow down.', 'ai-post-scheduler' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $rate_key, $hits + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Record batch telemetry events from frontend beacon.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function track_events( $request ) {
		$params     = $request->get_json_params();
		$raw_events = ( is_array( $params ) && ! empty( $params['events'] ) && is_array( $params['events'] ) ) 
			? $params['events'] 
			: array();

		if ( empty( $raw_events ) ) {
			return rest_ensure_response( array( 'recorded' => 0 ) );
		}

		// Cap max batch size to 50 to prevent DoS
		$raw_events = array_slice( $raw_events, 0, 50 );

		$valid_types      = AIPS_Monetization_Telemetry_Repository::VALID_EVENT_TYPES;
		$validated_events = array();

		foreach ( $raw_events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}

			$event_type = sanitize_key( $event['event_type'] ?? 'impression' );
			if ( ! in_array( $event_type, $valid_types, true ) ) {
				continue;
			}

			$slot_id = absint( $event['slot_id'] ?? 0 );
			if ( $slot_id > 0 ) {
				$slot = $this->slots_repo->get_by_id( $slot_id );
				if ( ! $slot ) {
					$slot_id = 0;
				}
			}

			$post_id = absint( $event['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$post_status = get_post_status( $post_id );
				if ( 'publish' !== $post_status ) {
					$post_id = 0;
				}
			}

			$campaign_id = absint( $event['campaign_id'] ?? 0 );
			$device_type = sanitize_key( $event['device_type'] ?? 'desktop' );
			if ( ! in_array( $device_type, array( 'desktop', 'mobile', 'tablet' ), true ) ) {
				$device_type = 'desktop';
			}

			$validated_events[] = array(
				'slot_id'     => $slot_id,
				'post_id'     => $post_id,
				'campaign_id' => $campaign_id,
				'event_type'  => $event_type,
				'device_type' => $device_type,
				'count'       => 1,
			);
		}

		if ( empty( $validated_events ) ) {
			return rest_ensure_response( array( 'recorded' => 0 ) );
		}

		$recorded = $this->telemetry_repo->record_events_batch( $validated_events );

		return rest_ensure_response( array(
			'success'  => true,
			'recorded' => $recorded,
		) );
	}
}
