<?php
/**
 * Tests for AIPS_Prompt_Document
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Document extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Document
	 */
	private $document;

	public function setUp(): void {
		parent::setUp();
		$this->document = new AIPS_Prompt_Document();
	}

	public function test_getters_default_to_empty() {
		$this->assertSame('', $this->document->get_system_instructions());
		$this->assertSame('', $this->document->get_task_directive());
		$this->assertSame(array(), $this->document->get_context_payload());
		$this->assertSame(array(), $this->document->get_constraints());
		$this->assertSame(array(), $this->document->get_few_shot_examples());
		$this->assertNull($this->document->get_output_schema());
	}

	public function test_setters_round_trip() {
		$this->document->set_system_instructions('You are a helpful assistant.');
		$this->document->set_task_directive('Write a blog post title.');
		$this->document->set_context_payload(array('Topic: AI Technology'));
		$this->document->set_constraints(array('Max 60 characters'));
		$this->document->set_output_schema(array('type' => 'string'));

		$this->assertSame('You are a helpful assistant.', $this->document->get_system_instructions());
		$this->assertSame('Write a blog post title.', $this->document->get_task_directive());
		$this->assertSame(array('Topic: AI Technology'), $this->document->get_context_payload());
		$this->assertSame(array('Max 60 characters'), $this->document->get_constraints());
		$this->assertSame(array('type' => 'string'), $this->document->get_output_schema());
	}

	public function test_setters_return_self_for_chaining() {
		$this->assertSame($this->document, $this->document->set_system_instructions('x'));
		$this->assertSame($this->document, $this->document->set_task_directive('x'));
		$this->assertSame($this->document, $this->document->set_context_payload(array()));
		$this->assertSame($this->document, $this->document->set_constraints(array()));
		$this->assertSame($this->document, $this->document->set_few_shot_examples(array()));
		$this->assertSame($this->document, $this->document->set_output_schema(null));
		$this->assertSame($this->document, $this->document->add_context_payload('x'));
		$this->assertSame($this->document, $this->document->add_constraint('x'));
		$this->assertSame($this->document, $this->document->add_few_shot_example('in', 'out'));
	}

	public function test_add_context_payload_appends_and_skips_blank() {
		$this->document->add_context_payload('First fact');
		$this->document->add_context_payload('  ');
		$this->document->add_context_payload('Second fact');

		$this->assertSame(array('First fact', 'Second fact'), $this->document->get_context_payload());
	}

	public function test_add_constraint_appends_and_skips_blank() {
		$this->document->add_constraint('No profanity');
		$this->document->add_constraint('');
		$this->document->add_constraint('Max 500 words');

		$this->assertSame(array('No profanity', 'Max 500 words'), $this->document->get_constraints());
	}

	public function test_add_few_shot_example_appends_pair() {
		$this->document->add_few_shot_example('Write about cats', 'Cats are great pets.');

		$this->assertSame(
			array(array('input' => 'Write about cats', 'output' => 'Cats are great pets.')),
			$this->document->get_few_shot_examples()
		);
	}

	public function test_compile_text_omits_empty_sections() {
		$text = $this->document->compile_text();

		$this->assertSame('', $text);
	}

	public function test_compile_text_includes_only_populated_sections_in_order() {
		$this->document->set_system_instructions('System instructions.');
		$this->document->set_task_directive('Task directive.');
		$this->document->add_context_payload('Context line.');
		$this->document->add_few_shot_example('in', 'out');
		$this->document->add_constraint('Constraint line.');
		$this->document->set_output_schema('{"type":"object"}');

		$text = $this->document->compile_text();

		$system_pos      = strpos($text, 'System instructions.');
		$task_pos        = strpos($text, 'Task directive.');
		$context_pos     = strpos($text, 'Context line.');
		$few_shot_pos     = strpos($text, 'Example input:');
		$constraints_pos = strpos($text, 'Constraints:');
		$schema_pos      = strpos($text, 'Output schema:');

		$this->assertNotFalse($system_pos);
		$this->assertNotFalse($task_pos);
		$this->assertNotFalse($context_pos);
		$this->assertNotFalse($few_shot_pos);
		$this->assertNotFalse($constraints_pos);
		$this->assertNotFalse($schema_pos);

		$this->assertTrue($system_pos < $task_pos);
		$this->assertTrue($task_pos < $context_pos);
		$this->assertTrue($context_pos < $few_shot_pos);
		$this->assertTrue($few_shot_pos < $constraints_pos);
		$this->assertTrue($constraints_pos < $schema_pos);

		$this->assertStringContainsString('- Constraint line.', $text);
	}

	public function test_compile_text_skips_blank_system_instructions() {
		$this->document->set_system_instructions('   ');
		$this->document->set_task_directive('Task directive.');

		$this->assertSame('Task directive.', $this->document->compile_text());
	}

	public function test_compile_messages_with_system_and_user_content() {
		$this->document->set_system_instructions('System instructions.');
		$this->document->set_task_directive('Task directive.');
		$this->document->add_constraint('Constraint line.');

		$messages = $this->document->compile_messages();

		$this->assertCount(2, $messages);
		$this->assertSame('system', $messages[0]['role']);
		$this->assertSame('System instructions.', $messages[0]['content']);
		$this->assertSame('user', $messages[1]['role']);
		$this->assertStringContainsString('Task directive.', $messages[1]['content']);
		$this->assertStringContainsString('Constraint line.', $messages[1]['content']);
	}

	public function test_compile_messages_without_system_instructions_has_only_user_message() {
		$this->document->set_task_directive('Task directive.');

		$messages = $this->document->compile_messages();

		$this->assertCount(1, $messages);
		$this->assertSame('user', $messages[0]['role']);
	}

	public function test_compile_messages_with_no_content_returns_empty_array() {
		$this->assertSame(array(), $this->document->compile_messages());
	}

	public function test_compile_text_with_array_output_schema_encodes_json() {
		$this->document->set_task_directive('Task directive.');
		$this->document->set_output_schema(array('type' => 'object', 'properties' => array('title' => array('type' => 'string'))));

		$text = $this->document->compile_text();

		$this->assertStringContainsString('Output schema:', $text);
		$this->assertStringContainsString('"type":"object"', $text);
	}
}
