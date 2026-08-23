<?php
/**
 * Ability adapter and catalog contract tests.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Service extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_provider' );
		parent::tearDown();
	}

	public function test_wordpress_ability_metadata_uses_category_getter_and_annotations() {
		$ability = new class {
			public function get_name() { return 'ai/title-generation'; }
			public function get_label() { return 'Generate title'; }
			public function get_description() { return 'Generates a title.'; }
			public function get_category() { return 'content-generation'; }
			public function get_input_schema() { return array( 'type' => 'object' ); }
			public function get_output_schema() { return array( 'type' => 'object' ); }
			public function get_meta() {
				return array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
					),
					'show_in_rest' => true,
				);
			}
		};

		$service = new AIPS_Ability_Service();
		$method  = ( new ReflectionClass( $service ) )->getMethod( 'wp_ability_to_array' );
		$method->setAccessible( true );
		$result = $method->invoke( $service, $ability );

		$this->assertSame( 'content-generation', $result['category'] );
		$this->assertFalse( $result['annotations']['destructive'] );
		$this->assertTrue( $result['metadata']['show_in_rest'] );
	}

	public function test_catalog_maps_wordpress_destructive_annotation() {
		$catalog = new AIPS_Ability_Catalog_Service( new AIPS_Ability_Service() );
		$result  = $catalog->normalize_ability(
			array(
				'slug'        => 'vendor/delete-content',
				'annotations' => array( 'destructive' => true ),
			)
		);

		$this->assertTrue( $result['is_destructive'] );
		$this->assertTrue( $result['destructive_state'] );
		$this->assertSame( array( 'destructive' => true ), $result['annotations'] );
	}

	public function test_catalog_preserves_unknown_destructive_state() {
		$catalog = new AIPS_Ability_Catalog_Service( new AIPS_Ability_Service() );
		$result  = $catalog->normalize_ability( array( 'slug' => 'vendor/unknown' ) );

		$this->assertFalse( $result['is_destructive'] );
		$this->assertNull( $result['destructive_state'] );
	}

	public function test_title_generation_contract_preserves_title_output() {
		add_filter(
			'aips_ability_provider',
			function () {
				return array(
					'name'   => 'title-generation-fixture',
					'list'   => function () {
						return array(
							'ai/title-generation' => array(
								'slug'          => 'ai/title-generation',
								'input_schema'  => array( 'type' => 'object' ),
								'output_schema' => array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ) ) ),
							),
						);
					},
					'invoke' => function ( $slug, $payload ) {
						if ( 'ai/title-generation' !== $slug || empty( $payload['content'] ) ) {
							return new WP_Error( 'content_not_provided', 'Content is required.' );
						}

						return array( 'title' => 'A Generated Title' );
					},
				);
			}
		);

		$result = ( new AIPS_Ability_Service() )->invoke(
			'ai/title-generation',
			array( 'content' => 'Article body' )
		);

		$this->assertSame( array( 'title' => 'A Generated Title' ), $result );
	}

	public function test_provider_errors_remain_machine_readable() {
		add_filter(
			'aips_ability_provider',
			function () {
				return array(
					'list'   => function () { return array( 'ai/title-generation' ); },
					'invoke' => function () { return new WP_Error( 'content_not_provided', 'Content is required.' ); },
				);
			}
		);

		$result = ( new AIPS_Ability_Service() )->invoke( 'ai/title-generation', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'content_not_provided', $result->get_error_code() );
	}
}
