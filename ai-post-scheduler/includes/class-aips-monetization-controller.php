<?php
/**
 * Monetization Controller
 *
 * Handles admin page rendering and AJAX endpoints for ad slots,
 * sponsor campaigns, and monetization telemetry analytics.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Monetization_Controller {

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

	/**
	 * @var AIPS_Config
	 */
	private $config;

	public function __construct(
		?AIPS_Ad_Slots_Repository $slots_repo = null,
		?AIPS_Sponsor_Campaigns_Repository $campaigns_repo = null,
		?AIPS_Monetization_Telemetry_Repository $telemetry_repo = null,
		?AIPS_Config $config = null
	) {
		$container            = AIPS_Container::get_instance();
		$this->slots_repo     = $slots_repo ?: ( $container->has( AIPS_Ad_Slots_Repository::class ) ? $container->make( AIPS_Ad_Slots_Repository::class ) : new AIPS_Ad_Slots_Repository() );
		$this->campaigns_repo = $campaigns_repo ?: ( $container->has( AIPS_Sponsor_Campaigns_Repository::class ) ? $container->make( AIPS_Sponsor_Campaigns_Repository::class ) : new AIPS_Sponsor_Campaigns_Repository() );
		$this->telemetry_repo = $telemetry_repo ?: ( $container->has( AIPS_Monetization_Telemetry_Repository::class ) ? $container->make( AIPS_Monetization_Telemetry_Repository::class ) : new AIPS_Monetization_Telemetry_Repository() );
		$this->config         = $config ?: AIPS_Config::get_instance();

		// Register AJAX actions
		add_action( 'wp_ajax_aips_get_ad_slots', array( $this, 'ajax_get_ad_slots' ) );
		add_action( 'wp_ajax_aips_save_ad_slot', array( $this, 'ajax_save_ad_slot' ) );
		add_action( 'wp_ajax_aips_delete_ad_slot', array( $this, 'ajax_delete_ad_slot' ) );
		add_action( 'wp_ajax_aips_toggle_ad_slot', array( $this, 'ajax_toggle_ad_slot' ) );
		add_action( 'wp_ajax_aips_get_sponsor_campaigns', array( $this, 'ajax_get_sponsor_campaigns' ) );
		add_action( 'wp_ajax_aips_save_sponsor_campaign', array( $this, 'ajax_save_sponsor_campaign' ) );
		add_action( 'wp_ajax_aips_delete_sponsor_campaign', array( $this, 'ajax_delete_sponsor_campaign' ) );
		add_action( 'wp_ajax_aips_toggle_sponsor_campaign', array( $this, 'ajax_toggle_sponsor_campaign' ) );
		add_action( 'wp_ajax_aips_get_monetization_analytics', array( $this, 'ajax_get_monetization_analytics' ) );
		add_action( 'wp_ajax_aips_save_monetization_engine_settings', array( $this, 'ajax_save_engine_settings' ) );
	}

	/**
	 * Render Monetization Hub admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-post-scheduler' ) );
		}

		$slots     = $this->slots_repo->get_all();
		$campaigns = $this->campaigns_repo->get_all();
		$summary   = $this->telemetry_repo->get_summary();

		include AIPS_PLUGIN_DIR . 'templates/admin/monetization.php';
	}

	/**
	 * Check security nonce and capability.
	 */
	private function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::error( __( 'Unauthorized access.', 'ai-post-scheduler' ), 403 );
		}

		check_ajax_referer( 'aips_monetization_nonce', 'nonce' );
	}

	/**
	 * AJAX: Get all ad slots.
	 */
	public function ajax_get_ad_slots() {
		$this->verify_request();
		$slots = $this->slots_repo->get_all();
		AIPS_Ajax_Response::success( array( 'slots' => $slots ) );
	}

	/**
	 * AJAX: Save or update ad slot.
	 */
	public function ajax_save_ad_slot() {
		$this->verify_request();

		$data = array(
			'id'                  => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'name'                => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slot_type'           => isset( $_POST['slot_type'] ) ? sanitize_key( wp_unslash( $_POST['slot_type'] ) ) : 'custom_html',
			'code'                => isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '',
			'position'            => isset( $_POST['position'] ) ? sanitize_key( wp_unslash( $_POST['position'] ) ) : 'after_paragraph',
			'paragraph_offset'    => isset( $_POST['paragraph_offset'] ) ? absint( $_POST['paragraph_offset'] ) : 2,
			'min_word_count'      => isset( $_POST['min_word_count'] ) ? absint( $_POST['min_word_count'] ) : 300,
			'device_targeting'    => isset( $_POST['device_targeting'] ) ? sanitize_key( wp_unslash( $_POST['device_targeting'] ) ) : 'all',
			'auto_refresh'        => ! empty( $_POST['auto_refresh'] ) ? 1 : 0,
			'refresh_interval'    => isset( $_POST['refresh_interval'] ) ? max( 15, absint( $_POST['refresh_interval'] ) ) : 30,
			'max_refreshes'       => isset( $_POST['max_refreshes'] ) ? max( 1, absint( $_POST['max_refreshes'] ) ) : 5,
			'anchor_trigger'      => isset( $_POST['anchor_trigger'] ) ? sanitize_key( wp_unslash( $_POST['anchor_trigger'] ) ) : 'scroll_depth',
			'anchor_scroll_depth' => isset( $_POST['anchor_scroll_depth'] ) ? absint( $_POST['anchor_scroll_depth'] ) : 15,
			'anchor_dismissible'  => isset( $_POST['anchor_dismissible'] ) ? ( ! empty( $_POST['anchor_dismissible'] ) ? 1 : 0 ) : 1,
			'status'              => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
			'priority'            => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'css_classes'         => isset( $_POST['css_classes'] ) ? sanitize_text_field( wp_unslash( $_POST['css_classes'] ) ) : '',
		);

		if ( empty( $data['name'] ) ) {
			AIPS_Ajax_Response::error( __( 'Slot name is required.', 'ai-post-scheduler' ) );
		}

		$slot_id = $this->slots_repo->save( $data );
		if ( ! $slot_id ) {
			AIPS_Ajax_Response::error( __( 'Failed to save ad slot.', 'ai-post-scheduler' ) );
		}

		$saved_slot = $this->slots_repo->get_by_id( $slot_id );
		AIPS_Ajax_Response::success( array(
			'message' => __( 'Ad slot saved successfully.', 'ai-post-scheduler' ),
			'slot'    => $saved_slot,
		) );
	}

	/**
	 * AJAX: Delete ad slot.
	 */
	public function ajax_delete_ad_slot() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			AIPS_Ajax_Response::error( __( 'Invalid slot ID.', 'ai-post-scheduler' ) );
		}

		$deleted = $this->slots_repo->delete( $id );
		if ( ! $deleted ) {
			AIPS_Ajax_Response::error( __( 'Could not delete ad slot.', 'ai-post-scheduler' ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Ad slot deleted.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Toggle ad slot active status.
	 */
	public function ajax_toggle_ad_slot() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			AIPS_Ajax_Response::error( __( 'Invalid slot ID.', 'ai-post-scheduler' ) );
		}

		$success = $this->slots_repo->toggle_status( $id );
		if ( ! $success ) {
			AIPS_Ajax_Response::error( __( 'Could not toggle slot status.', 'ai-post-scheduler' ) );
		}

		$updated = $this->slots_repo->get_by_id( $id );
		AIPS_Ajax_Response::success( array(
			'message' => __( 'Slot status updated.', 'ai-post-scheduler' ),
			'status'  => $updated ? $updated->status : 'inactive',
		) );
	}

	/**
	 * AJAX: Get sponsor campaigns.
	 */
	public function ajax_get_sponsor_campaigns() {
		$this->verify_request();
		$campaigns = $this->campaigns_repo->get_all();
		AIPS_Ajax_Response::success( array( 'campaigns' => $campaigns ) );
	}

	/**
	 * AJAX: Save or update sponsor campaign.
	 */
	public function ajax_save_sponsor_campaign() {
		$this->verify_request();

		$data = array(
			'id'              => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'brand_name'      => isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '',
			'logo_url'        => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
			'target_url'      => isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '',
			'cta_text'        => isset( $_POST['cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_text'] ) ) : '',
			'disclosure_text' => isset( $_POST['disclosure_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['disclosure_text'] ) ) : '',
			'category_ids'    => isset( $_POST['category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['category_ids'] ) ) : '',
			'keywords'        => isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '',
			'start_date'      => ! empty( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null,
			'end_date'        => ! empty( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null,
			'status'          => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active',
		);

		if ( empty( $data['brand_name'] ) || empty( $data['target_url'] ) ) {
			AIPS_Ajax_Response::error( __( 'Brand name and target URL are required.', 'ai-post-scheduler' ) );
		}

		$campaign_id = $this->campaigns_repo->save( $data );
		if ( ! $campaign_id ) {
			AIPS_Ajax_Response::error( __( 'Failed to save sponsor campaign.', 'ai-post-scheduler' ) );
		}

		$saved = $this->campaigns_repo->get_by_id( $campaign_id );
		AIPS_Ajax_Response::success( array(
			'message'  => __( 'Sponsor campaign saved successfully.', 'ai-post-scheduler' ),
			'campaign' => $saved,
		) );
	}

	/**
	 * AJAX: Delete sponsor campaign.
	 */
	public function ajax_delete_sponsor_campaign() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			AIPS_Ajax_Response::error( __( 'Invalid campaign ID.', 'ai-post-scheduler' ) );
		}

		$deleted = $this->campaigns_repo->delete( $id );
		if ( ! $deleted ) {
			AIPS_Ajax_Response::error( __( 'Could not delete campaign.', 'ai-post-scheduler' ) );
		}

		AIPS_Ajax_Response::success( array( 'message' => __( 'Campaign deleted.', 'ai-post-scheduler' ) ) );
	}

	/**
	 * AJAX: Toggle sponsor campaign status.
	 */
	public function ajax_toggle_sponsor_campaign() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			AIPS_Ajax_Response::error( __( 'Invalid campaign ID.', 'ai-post-scheduler' ) );
		}

		$success = $this->campaigns_repo->toggle_status( $id );
		if ( ! $success ) {
			AIPS_Ajax_Response::error( __( 'Could not toggle campaign status.', 'ai-post-scheduler' ) );
		}

		$updated = $this->campaigns_repo->get_by_id( $id );
		AIPS_Ajax_Response::success( array(
			'message' => __( 'Campaign status updated.', 'ai-post-scheduler' ),
			'status'  => $updated ? $updated->status : 'paused',
		) );
	}

	/**
	 * AJAX: Get analytics data.
	 */
	public function ajax_get_monetization_analytics() {
		$this->verify_request();

		$days    = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 14;
		$summary = $this->telemetry_repo->get_summary();
		$trends  = $this->telemetry_repo->get_daily_trends( $days );
		$top     = $this->telemetry_repo->get_top_posts( 10 );
		$slots   = $this->telemetry_repo->get_slot_breakdown();

		AIPS_Ajax_Response::success( array(
			'summary' => $summary,
			'trends'  => $trends,
			'top'     => $top,
			'slots'   => $slots,
		) );
	}

	/**
	 * AJAX: Save monetization engine and ad-block recovery settings.
	 */
	public function ajax_save_engine_settings() {
		$this->verify_request();

		$refresh_enabled  = ! empty( $_POST['aips_ad_refresh_enabled'] );
		$adblock_mode     = sanitize_key( $_POST['aips_adblock_recovery_mode'] ?? 'silent_fallback' );
		$notice_text      = sanitize_textarea_field( wp_unslash( $_POST['aips_adblock_notice_text'] ?? '' ) );
		$fallback_camp_id = absint( $_POST['aips_adblock_fallback_campaign_id'] ?? 0 );
		$cloaking_enabled = ! empty( $_POST['aips_link_cloaking_enabled'] );
		$cloaking_prefix  = sanitize_title( wp_unslash( $_POST['aips_link_cloaking_prefix'] ?? 'go' ) );

		if ( empty( $cloaking_prefix ) ) {
			$cloaking_prefix = 'go';
		}

		$old_prefix = $this->config->get_option( 'aips_link_cloaking_prefix', 'go' );

		$this->config->set_option( 'aips_ad_refresh_enabled', $refresh_enabled );
		$this->config->set_option( 'aips_adblock_recovery_mode', $adblock_mode );
		$this->config->set_option( 'aips_adblock_notice_text', $notice_text );
		$this->config->set_option( 'aips_adblock_fallback_campaign_id', $fallback_camp_id );
		$this->config->set_option( 'aips_link_cloaking_enabled', $cloaking_enabled );
		$this->config->set_option( 'aips_link_cloaking_prefix', $cloaking_prefix );

		if ( $old_prefix !== $cloaking_prefix || $cloaking_enabled ) {
			flush_rewrite_rules( false );
		}

		AIPS_Ajax_Response::success( array(
			'message' => __( 'Monetization engine settings saved successfully.', 'ai-post-scheduler' ),
		) );
	}
}
