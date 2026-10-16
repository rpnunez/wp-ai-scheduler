<?php
/**
 * Tests for the ai/content-classification enrichment step and the
 * decorative-image handling of the alt text step.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Enrichment_Classification extends WP_UnitTestCase {

	const SLUG = 'ai/content-classification';

	const SCHEMA = array(
		'type'       => 'object',
		'properties' => array(
			'content'         => array( 'type' => 'string' ),
			'post_id'         => array( 'type' => 'integer' ),
			'taxonomy'        => array( 'type' => 'string' ),
			'strategy'        => array( 'type' => 'string' ),
			'max_suggestions' => array( 'type' => 'integer' ),
		),
	);

	/** @var array Payloads and user IDs seen by the fixture ability, in call order. */
	private $calls = array();

	/** @var int */
	private $post_id;

	public function setUp(): void {
		parent::setUp();

		$this->calls = array();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 0 );

		$this->post_id = self::factory()->post->create( array( 'post_title' => 'Generated Post' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_provider' );
		remove_all_filters( 'aips_ability_enrichment_classification_args' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Register a fixture provider.
	 *
	 * @param array $abilities Slug => callable( $payload ) returning the ability result.
	 * @param bool  $schema    Whether the classification ability declares its input schema.
	 */
	private function register_provider( array $abilities, $schema = true ) {
		add_filter(
			'aips_ability_provider',
			function () use ( $abilities, $schema ) {
				return array(
					'name'   => 'classification-fixture',
					'list'   => function () use ( $abilities, $schema ) {
						$list = array();

						foreach ( array_keys( $abilities ) as $slug ) {
							$list[ $slug ] = array( 'slug' => $slug );

							if ( $schema && Test_AIPS_Ability_Enrichment_Classification::SLUG === $slug ) {
								$list[ $slug ]['input_schema'] = Test_AIPS_Ability_Enrichment_Classification::SCHEMA;
							}
						}

						return $list;
					},
					'invoke' => function ( $slug, $payload ) use ( $abilities ) {
						$this->calls[] = array(
							'slug'    => $slug,
							'payload' => $payload,
							'user_id' => get_current_user_id(),
						);

						return call_user_func( $abilities[ $slug ], $payload );
					},
				);
			}
		);
	}

	/**
	 * Register a classification ability that answers per taxonomy.
	 *
	 * @param array $by_taxonomy Taxonomy => suggestions array (or WP_Error).
	 */
	private function register_classifier( array $by_taxonomy, $schema = true ) {
		$this->register_provider(
			array(
				self::SLUG => function ( $payload ) use ( $by_taxonomy ) {
					$taxonomy = isset( $payload['taxonomy'] ) ? $payload['taxonomy'] : 'post_tag';
					$answer   = isset( $by_taxonomy[ $taxonomy ] ) ? $by_taxonomy[ $taxonomy ] : array();

					return is_wp_error( $answer ) ? $answer : array( 'suggestions' => $answer );
				},
			),
			$schema
		);
	}

	private function service() {
		$abilities = new AIPS_Ability_Service();

		return new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ) );
	}

	private function classify() {
		$results = $this->service()->enrich_post( $this->post_id );

		return isset( $results['classification'] ) ? $results['classification'] : null;
	}

	private function term_names( $taxonomy ) {
		return wp_list_pluck( wp_get_object_terms( $this->post_id, $taxonomy ), 'name' );
	}

	private function suggestion( $term, $confidence = 0.9, array $extra = array() ) {
		return array_merge( array( 'term' => $term, 'confidence' => $confidence, 'is_new' => false ), $extra );
	}

	// ---------------------------------------------------------------------
	// Skip paths
	// ---------------------------------------------------------------------

	public function test_skips_when_ability_is_not_registered() {
		$this->register_provider( array( 'ai/other' => function () { return array(); } ) );

		$this->assertSame( 'skipped:ability_unavailable', $this->classify() );
		$this->assertCount( 0, $this->calls );
	}

	public function test_skips_post_types_without_supported_taxonomies() {
		$this->register_classifier( array( 'post_tag' => array( $this->suggestion( 'Anything' ) ) ) );
		$this->post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 'skipped:no_supported_taxonomy', $this->classify() );
		$this->assertCount( 0, $this->calls );
	}

	public function test_skips_taxonomies_that_already_have_terms() {
		$this->register_classifier(
			array(
				'post_tag' => array( $this->suggestion( 'Suggested' ) ),
				'category' => array( $this->suggestion( 'Suggested Category' ) ),
			)
		);
		self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Suggested' ) );
		self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Suggested Category' ) );
		wp_set_object_terms( $this->post_id, array( 'Author Tag' ), 'post_tag' );
		$chosen = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Author Category' ) );
		wp_set_object_terms( $this->post_id, array( $chosen ), 'category' );

		$this->assertSame( 'skipped:terms_present', $this->classify() );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( array( 'Author Tag' ), $this->term_names( 'post_tag' ) );
		$this->assertSame( array( 'Author Category' ), $this->term_names( 'category' ) );
	}

	public function test_default_category_does_not_count_as_a_chosen_category() {
		$this->register_classifier( array( 'category' => array( $this->suggestion( 'News' ) ) ) );
		self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'News' ) );
		wp_set_object_terms( $this->post_id, array( (int) get_option( 'default_category' ) ), 'category' );

		$this->assertSame( 'applied', $this->classify() );
		$this->assertContains( 'News', $this->term_names( 'category' ) );
	}

	public function test_skips_when_no_suggestion_is_usable() {
		$this->register_classifier(
			array(
				'post_tag' => array( $this->suggestion( 'Unknown Tag' ), $this->suggestion( '   ' ), array( 'confidence' => 1 ), 'not-an-array' ),
				'category' => array(),
			)
		);

		$this->assertSame( 'skipped:no_suggestions', $this->classify() );
		$this->assertSame( array(), $this->term_names( 'post_tag' ) );
	}

	// ---------------------------------------------------------------------
	// Applying suggestions
	// ---------------------------------------------------------------------

	public function test_assigns_existing_terms_and_ignores_new_ones_by_default() {
		$this->register_classifier(
			array(
				'post_tag' => array(
					$this->suggestion( 'Machine Learning' ),
					$this->suggestion( 'Brand New Tag', 0.99, array( 'is_new' => true ) ),
				),
			)
		);
		self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Machine Learning' ) );

		$this->assertSame( 'applied', $this->classify() );
		$this->assertSame( array( 'Machine Learning' ), $this->term_names( 'post_tag' ) );
		$this->assertFalse( get_term_by( 'name', 'Brand New Tag', 'post_tag' ) );
	}

	public function test_ignores_suggestions_below_the_confidence_threshold() {
		$this->register_classifier(
			array(
				'post_tag' => array(
					$this->suggestion( 'Strong', 0.8 ),
					$this->suggestion( 'Weak', 0.2 ),
					array( 'term' => 'No Score' ),
				),
			)
		);
		foreach ( array( 'Strong', 'Weak', 'No Score' ) as $name ) {
			self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => $name ) );
		}

		$this->classify();

		$names = $this->term_names( 'post_tag' );
		sort( $names );
		$this->assertSame( array( 'No Score', 'Strong' ), $names );
	}

	public function test_confidence_threshold_is_filterable() {
		$this->register_classifier( array( 'post_tag' => array( $this->suggestion( 'Weak', 0.2 ) ) ) );
		self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Weak' ) );
		add_filter(
			'aips_ability_enrichment_classification_args',
			function () {
				return array( 'min_confidence' => 0.1 );
			}
		);

		$this->assertSame( 'applied', $this->classify() );
		$this->assertSame( array( 'Weak' ), $this->term_names( 'post_tag' ) );
	}

	public function test_category_assignment_appends_to_the_default_category() {
		$this->register_classifier( array( 'category' => array( $this->suggestion( 'News' ) ) ) );
		self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'News' ) );

		$this->classify();

		$this->assertContains( 'News', $this->term_names( 'category' ) );
	}

	public function test_no_more_than_max_suggestions_are_assigned() {
		$names = array( 'T1', 'T2', 'T3', 'T4' );
		foreach ( $names as $name ) {
			self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => $name ) );
		}
		$this->register_classifier(
			array(
				'post_tag' => array_map(
					function ( $name ) {
						return $this->suggestion( $name );
					},
					$names
				),
			)
		);
		add_filter(
			'aips_ability_enrichment_classification_args',
			function () {
				return array( 'max_suggestions' => 2 );
			}
		);

		$this->classify();

		$this->assertCount( 2, $this->term_names( 'post_tag' ) );
	}

	// ---------------------------------------------------------------------
	// allow_new strategy
	// ---------------------------------------------------------------------

	private function allow_new() {
		add_filter(
			'aips_ability_enrichment_classification_args',
			function () {
				return array( 'strategy' => 'allow_new' );
			}
		);
	}

	public function test_allow_new_creates_terms_with_an_existing_parent() {
		$this->register_classifier(
			array(
				'category' => array( $this->suggestion( 'Deep Learning', 0.9, array( 'is_new' => true, 'parent' => 'Technology' ) ) ),
			)
		);
		$parent = self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => 'Technology' ) );
		$this->allow_new();

		$this->assertSame( 'applied', $this->classify() );

		$created = get_term_by( 'name', 'Deep Learning', 'category' );
		$this->assertNotFalse( $created );
		$this->assertSame( $parent, (int) $created->parent );
		$this->assertSame( 'allow_new', $this->calls[0]['payload']['strategy'] );
	}

	public function test_allow_new_does_not_create_terms_for_users_who_cannot_manage_them() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_update_post( array( 'ID' => $this->post_id, 'post_author' => $author ) );
		wp_set_current_user( $author );
		$this->register_classifier( array( 'post_tag' => array( $this->suggestion( 'Forbidden Tag', 0.9, array( 'is_new' => true ) ) ) ) );
		$this->allow_new();

		$this->assertSame( 'skipped:no_suggestions', $this->classify() );
		$this->assertFalse( get_term_by( 'name', 'Forbidden Tag', 'post_tag' ) );
	}

	// ---------------------------------------------------------------------
	// Payload and errors
	// ---------------------------------------------------------------------

	public function test_each_taxonomy_gets_its_own_call_with_a_schema_trimmed_payload() {
		$this->register_classifier( array() );

		$this->classify();

		$this->assertCount( 2, $this->calls );
		$this->assertSame(
			array(
				'post_id'         => $this->post_id,
				'taxonomy'        => 'category',
				'strategy'        => 'existing_only',
				'max_suggestions' => 5,
			),
			$this->calls[0]['payload']
		);
		$this->assertSame( 'post_tag', $this->calls[1]['payload']['taxonomy'] );
	}

	public function test_payload_without_schema_only_sends_the_post_id() {
		$this->register_classifier( array(), false );

		$this->classify();

		$this->assertSame( array( 'post_id' => $this->post_id ), $this->calls[0]['payload'] );
	}

	public function test_max_suggestions_is_clamped_to_the_abilitys_range() {
		$this->register_classifier( array() );
		add_filter(
			'aips_ability_enrichment_classification_args',
			function () {
				return array( 'max_suggestions' => 500, 'strategy' => 'bogus' );
			}
		);

		$this->classify();

		$this->assertSame( 10, $this->calls[0]['payload']['max_suggestions'] );
		$this->assertSame( 'existing_only', $this->calls[0]['payload']['strategy'] );
	}

	public function test_error_from_every_taxonomy_is_reported() {
		$this->register_classifier(
			array(
				'post_tag' => new WP_Error( 'no_provider', 'No provider.' ),
				'category' => new WP_Error( 'no_provider', 'No provider.' ),
			)
		);

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'error:no_provider', $results['classification'] );
	}

	public function test_one_failing_taxonomy_does_not_block_the_other() {
		$this->register_classifier(
			array(
				'category' => new WP_Error( 'post_not_found', 'Missing.' ),
				'post_tag' => array( $this->suggestion( 'Machine Learning' ) ),
			)
		);
		self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => 'Machine Learning' ) );

		$this->assertSame( 'applied', $this->classify() );
		$this->assertSame( array( 'Machine Learning' ), $this->term_names( 'post_tag' ) );
	}

	public function test_classification_runs_as_the_resolved_user() {
		$this->register_classifier( array() );
		$editor = get_current_user_id();

		$this->classify();

		$this->assertSame( $editor, $this->calls[0]['user_id'] );
	}

	// ---------------------------------------------------------------------
	// Alt text: decorative images
	// ---------------------------------------------------------------------

	public function test_decorative_image_gets_no_alt_text() {
		$attachment_id = self::factory()->attachment->create( array( 'post_parent' => $this->post_id ) );
		update_post_meta( $this->post_id, '_thumbnail_id', $attachment_id );
		$this->register_provider(
			array(
				AIPS_Ability_Enrichment_Service::ALT_TEXT_ABILITY => function () {
					return array( 'alt_text' => 'Ignored', 'is_decorative' => true );
				},
			)
		);

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'skipped:decorative', $results['alt_text'] );
		$this->assertSame( '', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}
}
