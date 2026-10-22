<?php
/**
 * Tests for the destructive-ability approval rules in the catalog, and for
 * how AIPS_Ability_Service hands input to WordPress abilities.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Catalog_Approval extends WP_UnitTestCase {

	private function normalize( array $raw ) {
		return ( new AIPS_Ability_Catalog_Service( new AIPS_Ability_Service() ) )->normalize_ability( array_merge( array( 'slug' => 'vendor/thing' ), $raw ) );
	}

	// ---------------------------------------------------------------------
	// Approval
	// ---------------------------------------------------------------------

	public function test_an_ability_with_no_annotation_requires_approval() {
		$ability = $this->normalize( array() );

		$this->assertNull( $ability['destructive_state'] );
		$this->assertFalse( $ability['is_destructive'] );
		$this->assertTrue( $ability['requires_approval'], 'An ability that declares nothing must not run without approval.' );
	}

	public function test_an_ability_with_empty_annotations_requires_approval() {
		$this->assertTrue( $this->normalize( array( 'annotations' => array() ) )['requires_approval'] );
		$this->assertTrue( $this->normalize( array( 'annotations' => array( 'idempotent' => true ) ) )['requires_approval'] );
	}

	public function test_an_ability_declaring_itself_destructive_requires_approval() {
		$ability = $this->normalize( array( 'annotations' => array( 'destructive' => true ) ) );

		$this->assertTrue( $ability['is_destructive'] );
		$this->assertTrue( $ability['requires_approval'] );
	}

	public function test_an_ability_declaring_itself_non_destructive_needs_no_approval() {
		$ability = $this->normalize( array( 'annotations' => array( 'destructive' => false ) ) );

		$this->assertFalse( $ability['is_destructive'] );
		$this->assertFalse( $ability['requires_approval'] );
	}

	public function test_a_read_only_ability_needs_no_approval_even_if_flagged_destructive() {
		foreach ( array( array( 'readonly' => true ), array( 'readonly' => true, 'destructive' => true ) ) as $annotations ) {
			$ability = $this->normalize( array( 'annotations' => $annotations ) );

			$this->assertFalse( $ability['is_destructive'] );
			$this->assertFalse( $ability['requires_approval'] );
			$this->assertFalse( $ability['destructive_state'] );
		}
	}

	public function test_a_read_only_flag_of_false_does_not_grant_approval() {
		$this->assertTrue( $this->normalize( array( 'annotations' => array( 'readonly' => false ) ) )['requires_approval'] );
	}

	public function test_legacy_top_level_destructive_flags_still_work() {
		$this->assertTrue( $this->normalize( array( 'is_destructive' => true ) )['requires_approval'] );
		$this->assertFalse( $this->normalize( array( 'is_destructive' => false ) )['requires_approval'] );
		$this->assertTrue( $this->normalize( array( 'destructive' => true ) )['is_destructive'] );
	}

	public function test_wordpress_abilities_pass_their_annotations_through_to_approval() {
		$service = new AIPS_Ability_Service();
		$method  = new ReflectionMethod( $service, 'wp_ability_to_array' );
		$method->setAccessible( true );

		$make = function ( array $annotations ) {
			return new class( $annotations ) {
				private $annotations;

				public function __construct( $annotations ) {
					$this->annotations = $annotations;
				}

				public function get_name() { return 'vendor/wp'; }
				public function get_meta() { return array( 'annotations' => $this->annotations ); }
			};
		};

		$catalog = new AIPS_Ability_Catalog_Service( $service );

		$unannotated = $catalog->normalize_ability( $method->invoke( $service, $make( array() ) ) );
		$safe        = $catalog->normalize_ability( $method->invoke( $service, $make( array( 'readonly' => true ) ) ) );

		$this->assertTrue( $unannotated['requires_approval'] );
		$this->assertFalse( $safe['requires_approval'] );
	}

	// ---------------------------------------------------------------------
	// WordPress ability input
	// ---------------------------------------------------------------------

	private function wp_ability_input( $ability, array $payload ) {
		$service = new AIPS_Ability_Service();
		$method  = new ReflectionMethod( $service, 'wp_ability_input' );
		$method->setAccessible( true );

		return $method->invoke( $service, $ability, $payload );
	}

	private function ability_with_schema( array $schema ) {
		return new class( $schema ) {
			private $schema;

			public function __construct( $schema ) {
				$this->schema = $schema;
			}

			public function get_input_schema() {
				return $this->schema;
			}
		};
	}

	public function test_an_empty_payload_is_null_for_an_ability_without_an_input_schema() {
		$this->assertNull( $this->wp_ability_input( $this->ability_with_schema( array() ), array() ) );
	}

	public function test_an_empty_payload_stays_an_array_for_an_ability_with_an_input_schema() {
		$ability = $this->ability_with_schema( array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ) );

		$this->assertSame( array(), $this->wp_ability_input( $ability, array() ) );
	}

	public function test_a_non_empty_payload_is_passed_through_unchanged() {
		$payload = array( 'a' => 'b' );

		$this->assertSame( $payload, $this->wp_ability_input( $this->ability_with_schema( array() ), $payload ) );
		$this->assertSame( $payload, $this->wp_ability_input( $this->ability_with_schema( array( 'type' => 'object' ) ), $payload ) );
	}

	public function test_an_ability_object_without_a_schema_getter_gets_null_for_an_empty_payload() {
		$this->assertNull( $this->wp_ability_input( new stdClass(), array() ) );
	}
}
