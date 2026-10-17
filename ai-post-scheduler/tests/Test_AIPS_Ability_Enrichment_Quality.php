<?php
/**
 * Tests for the Yoast quality-gate enrichment steps (SEO and readability score).
 *
 * The Yoast abilities are faked: their real schemas were not available, so the
 * score extraction is tested against the response shapes it is meant to accept.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Enrichment_Quality extends WP_UnitTestCase {

	const SEO         = 'yoast-seo/get-seo-scores';
	const READABILITY = 'yoast-seo/get-readability-scores';

	/** @var array[] Payloads the fixture abilities received. */
	private $calls = array();

	/** @var array<string,callable> Slug => response callback. */
	private $responses = array();

	/** @var array|null Input schema both fixture abilities declare. */
	private $schema = null;

	/** @var int */
	private $post_id;

	/** @var array[] post_flagged action arguments. */
	private $flagged = array();

	public function setUp(): void {
		parent::setUp();

		$this->calls     = array();
		$this->responses = array();
		$this->flagged   = array();
		$this->schema    = null;

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_readability_score', 0 );

		$this->post_id = self::factory()->post->create( array( 'post_title' => 'Scored Post', 'post_status' => 'draft' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_action(
			'aips_ability_enrichment_post_flagged',
			function ( $post_id, $kind, $score, $threshold ) {
				$this->flagged[] = array( $post_id, $kind, $score, $threshold );
			},
			10,
			4
		);

		add_filter(
			'aips_ability_provider',
			function () {
				return array(
					'name'   => 'quality-fixture',
					'list'   => function () {
						$list = array();

						foreach ( array_keys( $this->responses ) as $slug ) {
							$list[ $slug ] = array( 'slug' => $slug );

							if ( null !== $this->schema ) {
								$list[ $slug ]['input_schema'] = $this->schema;
							}
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) {
						$this->calls[] = array( 'slug' => $slug, 'payload' => $payload );

						return call_user_func( $this->responses[ $slug ], $payload );
					},
				);
			}
		);
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_provider' );
		remove_all_filters( 'aips_ability_enrichment_demote_flagged' );
		remove_all_actions( 'aips_ability_enrichment_post_flagged' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_readability_score', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	private function enrich() {
		$abilities = new AIPS_Ability_Service();
		$service   = new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ) );

		return $service->enrich_post( $this->post_id );
	}

	private function seo( $response ) {
		$this->responses[ self::SEO ] = function () use ( $response ) {
			return $response;
		};
	}

	// ---------------------------------------------------------------------
	// Recording
	// ---------------------------------------------------------------------

	public function test_both_scores_are_recorded_as_post_meta() {
		$this->seo( array( 'score' => 72 ) );
		$this->responses[ self::READABILITY ] = function () {
			return array( 'readability_score' => 64 );
		};

		$results = $this->enrich();

		$this->assertSame( 'applied', $results['seo_score'] );
		$this->assertSame( 'applied', $results['readability'] );
		$this->assertSame( '72', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::SEO_SCORE_META, true ) );
		$this->assertSame( '64', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::READABILITY_SCORE_META, true ) );
		$this->assertSame( '', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::QUALITY_FLAG_META, true ), 'No threshold, no flag.' );
	}

	public function test_steps_are_skipped_when_yoast_abilities_are_not_registered() {
		$results = $this->enrich();

		$this->assertSame( 'skipped:ability_unavailable', $results['seo_score'] );
		$this->assertSame( 'skipped:ability_unavailable', $results['readability'] );
		$this->assertCount( 0, $this->calls );
	}

	public function test_the_payload_is_trimmed_to_the_declared_input_schema() {
		$this->schema = array( 'type' => 'object', 'properties' => array( 'post_ids' => array( 'type' => 'array' ) ) );
		$this->seo( array( 'score' => 50 ) );

		$this->enrich();

		$this->assertSame( array( 'post_ids' => array( $this->post_id ) ), $this->calls[0]['payload'] );
	}

	public function test_without_a_schema_the_payload_is_the_post_id() {
		$this->seo( array( 'score' => 50 ) );

		$this->enrich();

		$this->assertSame( array( 'post_id' => $this->post_id ), $this->calls[0]['payload'] );
	}

	// ---------------------------------------------------------------------
	// Response shapes
	// ---------------------------------------------------------------------

	public function score_shapes() {
		return array(
			'seo_score key'        => array( array( 'seo_score' => 81 ), 81 ),
			'numeric string'       => array( array( 'score' => '65' ), 65 ),
			'float rounds'         => array( array( 'score' => 70.6 ), 71 ),
			'bare number'          => array( 90, 90 ),
			'keyed by post id'     => array( 'KEYED', 55 ),
			'list of posts'        => array( 'LIST', 80 ),
			'nested scores object' => array( array( 'scores' => array( array( 'post_id' => 'POST', 'score' => 42 ) ) ), 42 ),
		);
	}

	/**
	 * @dataProvider score_shapes
	 */
	public function test_score_is_found_in_the_shapes_an_ability_may_return( $response, $expected ) {
		if ( 'KEYED' === $response ) {
			$response = array( $this->post_id => array( 'score' => 55 ) );
		} elseif ( 'LIST' === $response ) {
			$response = array(
				array( 'post_id' => $this->post_id + 999, 'score' => 10 ),
				array( 'post_id' => $this->post_id, 'score' => 80 ),
			);
		} elseif ( is_array( $response ) && isset( $response['scores'] ) ) {
			$response['scores'][0]['post_id'] = $this->post_id;
		}

		$this->seo( $response );

		$this->assertSame( 'applied', $this->enrich()['seo_score'] );
		$this->assertSame( (string) $expected, get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::SEO_SCORE_META, true ) );
	}

	public function test_a_response_without_a_valid_score_is_an_error_and_records_nothing() {
		foreach ( array( array( 'message' => 'ok' ), 150, -4, array( 'score' => 'high' ), array() ) as $response ) {
			$this->seo( $response );

			$this->assertSame( 'error:ability_enrichment_no_score', $this->enrich()['seo_score'] );
		}

		$this->assertSame( '', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::SEO_SCORE_META, true ) );
	}

	public function test_an_ability_error_is_reported_and_the_other_step_still_runs() {
		$this->responses[ self::SEO ]         = function () {
			return new WP_Error( 'yoast_down', 'Unavailable' );
		};
		$this->responses[ self::READABILITY ] = function () {
			return array( 'score' => 60 );
		};

		$results = $this->enrich();

		$this->assertSame( 'error:yoast_down', $results['seo_score'] );
		$this->assertSame( 'applied', $results['readability'] );
	}

	// ---------------------------------------------------------------------
	// Flagging
	// ---------------------------------------------------------------------

	public function test_a_score_below_the_threshold_flags_the_post_and_fires_the_action() {
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 60 );
		$this->seo( array( 'score' => 45 ) );

		$this->assertSame( 'applied:flagged', $this->enrich()['seo_score'] );

		$this->assertSame(
			array( 'kind' => 'seo', 'score' => 45, 'threshold' => 60 ),
			get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::QUALITY_FLAG_META, true )
		);
		$this->assertSame( array( array( $this->post_id, 'seo', 45, 60 ) ), $this->flagged );
		$this->assertSame( 'draft', get_post_status( $this->post_id ), 'A draft is already in Pending Review.' );
	}

	public function test_a_score_at_or_above_the_threshold_is_not_flagged() {
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 60 );
		$this->seo( array( 'score' => 60 ) );

		$this->assertSame( 'applied', $this->enrich()['seo_score'] );
		$this->assertSame( array(), $this->flagged );
	}

	public function test_readability_has_its_own_threshold() {
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 90 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_readability_score', 50 );
		$this->seo( array( 'score' => 95 ) );
		$this->responses[ self::READABILITY ] = function () {
			return array( 'score' => 30 );
		};

		$results = $this->enrich();

		$this->assertSame( 'applied', $results['seo_score'] );
		$this->assertSame( 'applied:flagged', $results['readability'] );
		$this->assertSame( 'readability', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::QUALITY_FLAG_META, true )['kind'] );
	}

	public function test_a_published_post_stays_published_unless_the_demote_filter_says_otherwise() {
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 60 );
		$this->seo( array( 'score' => 10 ) );
		wp_update_post( array( 'ID' => $this->post_id, 'post_status' => 'publish' ) );

		$this->enrich();
		$this->assertSame( 'publish', get_post_status( $this->post_id ) );

		add_filter( 'aips_ability_enrichment_demote_flagged', '__return_true' );
		$this->enrich();
		$this->assertSame( 'draft', get_post_status( $this->post_id ) );
	}

	public function test_a_post_with_no_score_is_never_flagged() {
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_min_seo_score', 60 );
		$this->seo( array( 'nothing' => true ) );

		$this->enrich();

		$this->assertSame( array(), $this->flagged );
		$this->assertSame( '', get_post_meta( $this->post_id, AIPS_Ability_Enrichment_Service::QUALITY_FLAG_META, true ) );
	}

	public function test_threshold_settings_default_to_off() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertSame( 0, $defaults['aips_ability_enrichment_min_seo_score'] );
		$this->assertSame( 0, $defaults['aips_ability_enrichment_min_readability_score'] );
	}
}
