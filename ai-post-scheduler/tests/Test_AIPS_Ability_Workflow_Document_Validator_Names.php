<?php
/**
 * Tests for the validator's handling of step keys, output aliases and the
 * {{steps.<name>...}} references that point at them.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Document_Validator_Names extends WP_UnitTestCase {

	/** @var AIPS_Ability_Workflow_Document_Validator */
	private $validator;

	public function setUp(): void {
		parent::setUp();

		$resolver        = new AIPS_Ability_Workflow_Variable_Resolver();
		$this->validator = new AIPS_Ability_Workflow_Document_Validator( new AIPS_Ability_Workflow_Condition_Evaluator( $resolver ), $resolver, null );
	}

	private function errors( array $steps ) {
		$result = $this->validator->validate( array( 'steps' => $steps ), true );

		return is_wp_error( $result ) ? implode( ' | ', $result->get_error_data()['errors'] ) : true;
	}

	private function step( $key, array $extra = array() ) {
		return array_merge( array( 'step_key' => $key, 'ability_name' => 'vendor/' . $key ), $extra );
	}

	public function test_a_step_without_an_alias_is_referenced_by_its_step_key() {
		$result = $this->errors(
			array(
				$this->step( 'outline' ),
				$this->step( 'write', array( 'input_map' => array( 'text' => '{{steps.outline.output.title}}' ) ) ),
			)
		);

		$this->assertTrue( $result, 'The executor stores outputs under the step key when there is no alias.' );
	}

	public function test_an_alias_replaces_the_step_key_as_the_reference_name() {
		$result = $this->errors(
			array(
				$this->step( 'outline', array( 'output_alias' => 'plan' ) ),
				$this->step( 'write', array( 'input_map' => array( 'text' => '{{steps.outline.output.title}}' ) ) ),
			)
		);

		$this->assertNotTrue( $result );
		$this->assertStringContainsString( 'outline', $result );
	}

	public function test_a_reference_to_a_later_step_is_rejected() {
		$result = $this->errors(
			array(
				$this->step( 'write', array( 'input_map' => array( 'text' => '{{steps.outline.output.title}}' ) ) ),
				$this->step( 'outline' ),
			)
		);

		$this->assertNotTrue( $result );
	}

	public function test_step_keys_may_only_contain_letters_numbers_and_underscores() {
		foreach ( array( 'has.dot', 'has space', 'has-dash', 'a/b' ) as $key ) {
			$this->assertNotTrue( $this->errors( array( $this->step( $key ) ) ), $key );
		}

		$this->assertTrue( $this->errors( array( $this->step( 'Valid_key_2' ) ) ) );
	}

	public function test_output_aliases_may_only_contain_letters_numbers_and_underscores() {
		$this->assertNotTrue( $this->errors( array( $this->step( 'a', array( 'output_alias' => 'my.alias' ) ) ) ) );
		$this->assertTrue( $this->errors( array( $this->step( 'a', array( 'output_alias' => 'my_alias' ) ) ) ) );
	}

	public function test_keys_and_aliases_longer_than_their_columns_are_rejected() {
		$this->assertNotTrue( $this->errors( array( $this->step( str_repeat( 'k', AIPS_Ability_Workflow_Repository::STEP_KEY_MAX_LENGTH + 1 ) ) ) ) );
		$this->assertNotTrue( $this->errors( array( $this->step( 'a', array( 'output_alias' => str_repeat( 'x', AIPS_Ability_Workflow_Repository::OUTPUT_ALIAS_MAX_LENGTH + 1 ) ) ) ) ) );
		$this->assertTrue( $this->errors( array( $this->step( str_repeat( 'k', AIPS_Ability_Workflow_Repository::STEP_KEY_MAX_LENGTH ) ) ) ) );
	}

	public function test_an_alias_that_collides_with_another_steps_key_is_rejected() {
		$result = $this->errors(
			array(
				$this->step( 'first' ),
				$this->step( 'second', array( 'output_alias' => 'first' ) ),
			)
		);

		$this->assertNotTrue( $result );
		$this->assertStringContainsString( 'already used', $result );
	}

	public function test_two_steps_may_not_share_an_alias() {
		$result = $this->errors(
			array(
				$this->step( 'first', array( 'output_alias' => 'shared' ) ),
				$this->step( 'second', array( 'output_alias' => 'shared' ) ),
			)
		);

		$this->assertNotTrue( $result );
	}

	public function test_condition_operands_may_reference_earlier_steps() {
		$result = $this->errors(
			array(
				$this->step( 'a' ),
				$this->step(
					'b',
					array(
						'condition_tree' => array(
							'rules' => array( array( 'left' => '{{steps.a.output.count}}', 'operator' => 'equals', 'right' => '{{steps.a.output.expected}}' ) ),
						),
					)
				),
			)
		);

		$this->assertTrue( $result );
	}

	public function test_condition_operands_may_not_reference_unknown_or_later_steps() {
		$left_unknown = $this->errors(
			array(
				$this->step(
					'a',
					array( 'condition_tree' => array( 'rules' => array( array( 'left' => '{{steps.later.output.x}}', 'operator' => 'equals', 'right' => 1 ) ) ) )
				),
				$this->step( 'later' ),
			)
		);
		$right_unknown = $this->errors(
			array(
				$this->step( 'a' ),
				$this->step(
					'b',
					array( 'condition_tree' => array( 'rules' => array( array( 'left' => '{{trigger.x}}', 'operator' => 'equals', 'right' => '{{steps.nope.output.y}}' ) ) ) )
				),
			)
		);

		$this->assertNotTrue( $left_unknown );
		$this->assertNotTrue( $right_unknown );
	}

	public function test_nested_condition_groups_are_checked_too() {
		$result = $this->errors(
			array(
				$this->step(
					'a',
					array(
						'condition_tree' => array(
							'operator' => 'OR',
							'rules'    => array(
								array(
									'operator' => 'AND',
									'rules'    => array( array( 'left' => '{{steps.ghost.output.x}}', 'operator' => 'equals', 'right' => 1 ) ),
								),
							),
						),
					)
				),
			)
		);

		$this->assertNotTrue( $result );
	}

	public function test_a_step_cannot_reference_its_own_output() {
		$this->assertNotTrue( $this->errors( array( $this->step( 'a', array( 'input_map' => array( 'x' => '{{steps.a.output.y}}' ) ) ) ) ) );
	}
}
