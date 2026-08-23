<?php
/**
 * Tests for AIPS_Ability_Workflow_Document_Validator
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Workflow_Document_Validator extends WP_UnitTestCase {

	private $validator;

	public function setUp(): void {
		parent::setUp();

		$resolver  = new AIPS_Ability_Workflow_Variable_Resolver();
		$evaluator = new AIPS_Ability_Workflow_Condition_Evaluator( $resolver );

		// No catalog service injected -> ability-availability checks are skipped
		// unless $skip_ability_check is explicitly false and a catalog is present.
		$this->validator = new AIPS_Ability_Workflow_Document_Validator( $evaluator, $resolver, null );
	}

	private function valid_document() {
		return array(
			'steps' => array(
				array(
					'step_key'     => 'generate_outline',
					'ability_name' => 'vendor/create-outline',
					'input_map'    => array( 'topic' => '{{trigger.topic}}' ),
					'output_alias' => 'outline',
					'on_success'   => array( 'strategy' => 'continue' ),
					'on_failure'   => array( 'strategy' => 'stop' ),
					'retry_policy' => array( 'attempts' => 2, 'backoff_seconds' => 5 ),
				),
				array(
					'step_key'       => 'write_post',
					'ability_name'   => 'vendor/write-post',
					'depends_on'     => array( 'generate_outline' ),
					'input_map'      => array( 'outline' => '{{steps.outline.output}}' ),
					'condition_tree' => array(
						'operator' => 'AND',
						'rules'    => array(
							array( 'left' => '{{steps.outline.output.sections_count}}', 'operator' => 'greater_than', 'right' => 0 ),
						),
					),
					'output_alias'   => 'post',
				),
			),
		);
	}

	public function test_valid_document_passes() {
		$result = $this->validator->validate( $this->valid_document(), true );
		$this->assertTrue( $result );
	}

	public function test_duplicate_step_keys_rejected() {
		$doc = $this->valid_document();
		$doc['steps'][1]['step_key'] = 'generate_outline';

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_missing_ability_name_rejected() {
		$doc = $this->valid_document();
		unset( $doc['steps'][0]['ability_name'] );

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_forward_reference_in_depends_on_rejected() {
		$doc = array(
			'steps' => array(
				array(
					'step_key'     => 'write_post',
					'ability_name' => 'vendor/write-post',
					'depends_on'   => array( 'generate_outline' ), // does not exist yet
				),
			),
		);

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_forward_reference_in_input_map_rejected() {
		$doc = array(
			'steps' => array(
				array(
					'step_key'     => 'write_post',
					'ability_name' => 'vendor/write-post',
					'input_map'    => array( 'outline' => '{{steps.outline.output}}' ), // 'outline' alias not produced yet
				),
			),
		);

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_invalid_strategy_rejected() {
		$doc = $this->valid_document();
		$doc['steps'][0]['on_success']['strategy'] = 'explode';

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_negative_retry_attempts_rejected() {
		$doc = $this->valid_document();
		$doc['steps'][0]['retry_policy']['attempts'] = -1;

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}

	public function test_invalid_condition_tree_rejected() {
		$doc = $this->valid_document();
		$doc['steps'][1]['condition_tree']['operator'] = 'XOR';

		$this->assertWPError( $this->validator->validate( $doc, true ) );
	}
}
