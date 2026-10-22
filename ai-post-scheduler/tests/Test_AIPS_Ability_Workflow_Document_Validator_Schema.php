<?php
/**
 * Tests for the validator's check of a step's input map against the ability's
 * declared input schema.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Document_Validator_Schema extends WP_UnitTestCase {

	const SCHEMA = array(
		'type'                 => 'object',
		'properties'           => array(
			'content' => array( 'type' => 'string' ),
			'count'   => array( 'type' => 'integer' ),
			'ratio'   => array( 'type' => 'number' ),
			'flag'    => array( 'type' => 'boolean' ),
			'tags'    => array( 'type' => 'array' ),
			'extra'   => array( 'type' => 'object' ),
			'either'  => array( 'type' => array( 'string', 'null' ) ),
			'mode'    => array( 'type' => 'string', 'enum' => array( 'short', 'long' ) ),
			'level'   => array( 'enum' => array( 1, 2, 3 ) ),
		),
		'required'             => array( 'content' ),
		'additionalProperties' => false,
	);

	/** @var AIPS_Ability_Workflow_Document_Validator */
	private $validator;

	public function setUp(): void {
		parent::setUp();

		add_filter(
			'aips_ability_provider',
			function () {
				return array(
					'name'   => 'schema-fixture',
					'list'   => function () {
						return array(
							'vendor/typed'  => array( 'slug' => 'vendor/typed', 'input_schema' => self::SCHEMA ),
							'vendor/open'   => array(
								'slug'         => 'vendor/open',
								'input_schema' => array( 'type' => 'object', 'properties' => array( 'a' => array( 'type' => 'string' ) ) ),
							),
							'vendor/nodata' => array( 'slug' => 'vendor/nodata' ),
						);
					},
					'invoke' => function () {
						return array();
					},
				);
			}
		);

		$abilities       = new AIPS_Ability_Service();
		$resolver        = new AIPS_Ability_Workflow_Variable_Resolver();
		$this->validator = new AIPS_Ability_Workflow_Document_Validator(
			new AIPS_Ability_Workflow_Condition_Evaluator( $resolver ),
			$resolver,
			new AIPS_Ability_Catalog_Service( $abilities )
		);
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_provider' );

		parent::tearDown();
	}

	/**
	 * Validate one step; returns true or the joined error messages.
	 */
	private function check( $ability, array $input_map ) {
		$result = $this->validator->validate(
			array( 'steps' => array( array( 'step_key' => 's', 'ability_name' => $ability, 'input_map' => $input_map ) ) )
		);

		return is_wp_error( $result ) ? implode( ' | ', $result->get_error_data()['errors'] ) : true;
	}

	public function test_a_complete_valid_input_passes() {
		$this->assertTrue( $this->check( 'vendor/typed', array( 'content' => 'Hello', 'count' => 3, 'ratio' => 0.5, 'flag' => true, 'tags' => array( 'a', 'b' ), 'extra' => array( 'k' => 'v' ), 'either' => null, 'mode' => 'short', 'level' => 2 ) ) );
	}

	public function test_a_missing_required_field_is_reported() {
		$result = $this->check( 'vendor/typed', array() );

		$this->assertNotTrue( $result );
		$this->assertStringContainsString( 'needs the input field "content"', $result );
	}

	public function test_a_reference_satisfies_a_required_field_and_any_type() {
		$this->assertTrue( $this->check( 'vendor/typed', array( 'content' => '{{trigger.text}}', 'count' => '{{trigger.n}}', 'flag' => 'Is {{trigger.x}}' ) ) );
	}

	public function test_a_fixed_value_of_the_wrong_type_is_reported() {
		$this->assertStringContainsString( '"count" should be of type integer', $this->check( 'vendor/typed', array( 'content' => 'x', 'count' => '5' ) ) );
		$this->assertStringContainsString( '"flag" should be of type boolean', $this->check( 'vendor/typed', array( 'content' => 'x', 'flag' => 'true' ) ) );
		$this->assertStringContainsString( '"content" should be of type string', $this->check( 'vendor/typed', array( 'content' => 12 ) ) );
		$this->assertStringContainsString( '"tags" should be of type array', $this->check( 'vendor/typed', array( 'content' => 'x', 'tags' => array( 'k' => 'v' ) ) ) );
		$this->assertStringContainsString( '"extra" should be of type object', $this->check( 'vendor/typed', array( 'content' => 'x', 'extra' => array( 'a', 'b' ) ) ) );
	}

	public function test_integer_accepts_a_whole_float_and_number_accepts_both() {
		$this->assertTrue( $this->check( 'vendor/typed', array( 'content' => 'x', 'count' => 4.0, 'ratio' => 2 ) ) );
		$this->assertStringContainsString( '"count"', $this->check( 'vendor/typed', array( 'content' => 'x', 'count' => 4.5 ) ) );
	}

	public function test_a_list_of_types_accepts_any_of_them() {
		$this->assertTrue( $this->check( 'vendor/typed', array( 'content' => 'x', 'either' => 'text' ) ) );
		$this->assertStringContainsString( '"either" should be of type string/null', $this->check( 'vendor/typed', array( 'content' => 'x', 'either' => 7 ) ) );
	}

	public function test_a_value_outside_an_enum_is_reported_with_the_allowed_values() {
		$result = $this->check( 'vendor/typed', array( 'content' => 'x', 'mode' => 'medium', 'level' => 9 ) );

		$this->assertStringContainsString( '"mode" must be one of: short, long', $result );
		$this->assertStringContainsString( '"level" must be one of: 1, 2, 3', $result );
	}

	public function test_an_unknown_field_is_rejected_only_when_the_schema_forbids_extras() {
		$this->assertStringContainsString( 'no input field "bogus"', $this->check( 'vendor/typed', array( 'content' => 'x', 'bogus' => 1 ) ) );
		$this->assertTrue( $this->check( 'vendor/open', array( 'a' => 'x', 'bogus' => 1 ) ), 'Extras are allowed unless additionalProperties is false.' );
	}

	public function test_an_ability_without_an_input_schema_accepts_any_input() {
		$this->assertTrue( $this->check( 'vendor/nodata', array( 'anything' => 1 ) ) );
		$this->assertTrue( $this->check( 'vendor/nodata', array() ) );
	}

	public function test_every_problem_in_a_step_is_listed_together() {
		$result = $this->check( 'vendor/typed', array( 'count' => 'many', 'mode' => 'x' ) );

		$this->assertStringContainsString( 'needs the input field "content"', $result );
		$this->assertStringContainsString( '"count"', $result );
		$this->assertStringContainsString( '"mode"', $result );
	}

	public function test_the_check_is_skipped_when_ability_checks_are_skipped() {
		$result = $this->validator->validate(
			array( 'steps' => array( array( 'step_key' => 's', 'ability_name' => 'vendor/typed', 'input_map' => array() ) ) ),
			true
		);

		$this->assertTrue( $result );
	}
}
