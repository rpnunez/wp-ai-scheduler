<?php
/**
 * Tests for AIPS_Ability_Workflow_Condition_Evaluator
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Condition_Evaluator extends WP_UnitTestCase {

	private $evaluator;
	private $variables;

	public function setUp(): void {
		parent::setUp();

		$this->evaluator = new AIPS_Ability_Workflow_Condition_Evaluator( new AIPS_Ability_Workflow_Variable_Resolver() );

		$this->variables = array(
			'trigger' => array( 'topic' => 'AI in gardening' ),
			'steps'   => array(
				'outline' => array( 'output' => array( 'sections_count' => 5, 'title' => 'My Outline' ) ),
			),
		);
	}

	public function test_empty_tree_is_always_true() {
		$this->assertTrue( $this->evaluator->evaluate( array(), $this->variables ) );
		$this->assertTrue( $this->evaluator->evaluate( array( 'operator' => 'AND', 'rules' => array() ), $this->variables ) );
	}

	public function test_and_group_requires_all_rules_true() {
		$tree = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => 'greater_than', 'right' => 0 ),
				array( 'left' => '{{trigger.topic}}', 'operator' => 'contains', 'right' => 'gardening' ),
			),
		);

		$this->assertTrue( $this->evaluator->evaluate( $tree, $this->variables ) );

		$tree['rules'][0]['right'] = 100;
		$this->assertFalse( $this->evaluator->evaluate( $tree, $this->variables ) );
	}

	public function test_or_group_requires_any_rule_true() {
		$tree = array(
			'operator' => 'OR',
			'rules'    => array(
				array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => 'greater_than', 'right' => 100 ),
				array( 'left' => '{{trigger.topic}}', 'operator' => 'contains', 'right' => 'gardening' ),
			),
		);

		$this->assertTrue( $this->evaluator->evaluate( $tree, $this->variables ) );
	}

	public function test_nested_groups_are_evaluated_recursively() {
		$tree = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'left' => '{{trigger.topic}}', 'operator' => 'is_not_empty' ),
				array(
					'operator' => 'OR',
					'rules'    => array(
						array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => 'equals', 'right' => 999 ),
						array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => 'equals', 'right' => 5 ),
					),
				),
			),
		);

		$this->assertTrue( $this->evaluator->evaluate( $tree, $this->variables ) );
	}

	public function test_is_empty_and_is_not_empty() {
		$this->assertTrue( $this->evaluator->evaluate_rule( array( 'left' => '{{missing.token}}', 'operator' => 'is_empty' ), $this->variables ) );
		$this->assertFalse( $this->evaluator->evaluate_rule( array( 'left' => '{{trigger.topic}}', 'operator' => 'is_empty' ), $this->variables ) );
		$this->assertTrue( $this->evaluator->evaluate_rule( array( 'left' => '{{trigger.topic}}', 'operator' => 'is_not_empty' ), $this->variables ) );
	}

	public function test_in_and_not_in_operators() {
		$rule = array( 'left' => '{{trigger.topic}}', 'operator' => 'in', 'right' => 'AI in gardening,Other Topic' );
		$this->assertTrue( $this->evaluator->evaluate_rule( $rule, $this->variables ) );

		$rule['operator'] = 'not_in';
		$this->assertFalse( $this->evaluator->evaluate_rule( $rule, $this->variables ) );
	}

	public function test_validate_condition_tree_accepts_valid_tree() {
		$tree = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'left' => '{{trigger.topic}}', 'operator' => 'is_not_empty' ),
			),
		);

		$this->assertTrue( $this->evaluator->validate_condition_tree( $tree ) );
	}

	public function test_validate_condition_tree_rejects_bad_operator() {
		$result = $this->evaluator->validate_condition_tree( array( 'operator' => 'XOR', 'rules' => array() ) );
		$this->assertWPError( $result );
	}

	public function test_validate_condition_tree_rejects_missing_rule_fields() {
		$tree = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'operator' => 'equals' ), // missing 'left'
			),
		);

		$this->assertWPError( $this->evaluator->validate_condition_tree( $tree ) );
	}

	public function test_validate_condition_tree_rejects_invalid_token_reference() {
		$tree = array(
			'operator' => 'AND',
			'rules'    => array(
				array( 'left' => '{{bogus.path}}', 'operator' => 'is_not_empty' ),
			),
		);

		$this->assertWPError( $this->evaluator->validate_condition_tree( $tree ) );
	}
}
