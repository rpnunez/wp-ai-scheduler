<?php
/**
 * Tests for AIPS_Token_Budget_Middleware
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Token_Budget_Middleware extends WP_UnitTestCase {

	private function make_context() {
		return new AIPS_Topic_Context((object) array('id' => 1), (object) array('id' => 1, 'topic_title' => 'Budget Topic'));
	}

	public function test_noop_when_within_budget() {
		$document = new AIPS_Prompt_Document();
		$document->set_system_instructions('System.');
		$document->set_task_directive('Task.');
		$document->add_context_payload('Short context.');

		$middleware = new AIPS_Token_Budget_Middleware(1000);
		$result = $middleware->process($document, $this->make_context());

		$this->assertSame($document, $result);
		$this->assertSame('Task.', $result->get_task_directive());
		$this->assertSame(array('Short context.'), $result->get_context_payload());
	}

	public function test_few_shot_examples_are_dropped_before_context_payload() {
		$document = new AIPS_Prompt_Document();
		$document->set_system_instructions('System.');
		$document->set_task_directive('Task.');
		$document->add_context_payload('Essential context line.');
		$document->add_few_shot_example('in', str_repeat('x', 200));

		// Budget fits the essentials but not the example.
		$middleware = new AIPS_Token_Budget_Middleware(60);
		$result = $middleware->process($document, $this->make_context());

		$this->assertSame(array(), $result->get_few_shot_examples());
		$this->assertSame(array('Essential context line.'), $result->get_context_payload());
	}

	public function test_most_recently_added_context_payload_entry_is_dropped_first() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive('Task.');
		$document->add_context_payload('First added, kept.');
		$document->add_context_payload(str_repeat('y', 200));

		$middleware = new AIPS_Token_Budget_Middleware(40);
		$result = $middleware->process($document, $this->make_context());

		$this->assertSame(array('First added, kept.'), $result->get_context_payload());
	}

	public function test_most_recently_added_constraint_is_dropped_first() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive('Task.');
		$document->add_constraint('First constraint, kept.');
		$document->add_constraint(str_repeat('z', 200));

		$middleware = new AIPS_Token_Budget_Middleware(60);
		$result = $middleware->process($document, $this->make_context());

		$this->assertSame(array('First constraint, kept.'), $result->get_constraints());
	}

	public function test_system_instructions_and_output_schema_are_never_removed() {
		$document = new AIPS_Prompt_Document();
		$document->set_system_instructions('Critical persona instructions that must survive.');
		$document->set_task_directive('Task.');
		$document->set_output_schema('{"type":"object"}');
		$document->add_context_payload(str_repeat('a', 500));
		$document->add_constraint(str_repeat('b', 500));
		$document->add_few_shot_example('in', str_repeat('c', 500));

		$middleware = new AIPS_Token_Budget_Middleware(50);
		$result = $middleware->process($document, $this->make_context());

		$this->assertSame('Critical persona instructions that must survive.', $result->get_system_instructions());
		$this->assertSame('{"type":"object"}', $result->get_output_schema());
	}

	public function test_task_directive_truncated_as_last_resort_once_other_sections_are_gone() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive(str_repeat('Write about topics. ', 50));

		$middleware = new AIPS_Token_Budget_Middleware(30);
		$result = $middleware->process($document, $this->make_context());

		$this->assertLessThanOrEqual(30, mb_strlen($result->compile_text()));
		$this->assertNotSame('', $result->get_task_directive());
	}

	public function test_truncation_never_leaves_an_unclosed_html_tag() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive('Intro text <strong>bold section that keeps going for a while</strong> more text after.');

		$middleware = new AIPS_Token_Budget_Middleware(20);
		$result = $middleware->process($document, $this->make_context());

		$directive = $result->get_task_directive();
		$last_open  = strrpos($directive, '<');
		$last_close = strrpos($directive, '>');

		if (false !== $last_open) {
			$this->assertNotFalse($last_close, 'An opened tag must also be closed.');
			$this->assertGreaterThanOrEqual($last_open, $last_close);
		}
	}

	public function test_truncation_never_leaves_an_odd_number_of_unescaped_quotes() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive('Respond as {"title": "a fairly long example title value here", "slug": "example"}');

		$middleware = new AIPS_Token_Budget_Middleware(25);
		$result = $middleware->process($document, $this->make_context());

		$directive = $result->get_task_directive();
		$quote_count = 0;
		$len = mb_strlen($directive);
		for ($i = 0; $i < $len; $i++) {
			$char = mb_substr($directive, $i, 1);
			if ('"' === $char && (0 === $i || '\\' !== mb_substr($directive, $i - 1, 1))) {
				$quote_count++;
			}
		}

		$this->assertSame(0, $quote_count % 2);
	}

	public function test_truncation_does_not_split_a_multibyte_character() {
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive(str_repeat('café ', 30));

		$middleware = new AIPS_Token_Budget_Middleware(17);
		$result = $middleware->process($document, $this->make_context());

		// mb_strlen on valid UTF-8 output confirms no byte-level split occurred.
		$this->assertSame($result->get_task_directive(), mb_convert_encoding($result->get_task_directive(), 'UTF-8', 'UTF-8'));
	}

	public function test_default_constructor_uses_default_max_chars_constant() {
		$middleware = new AIPS_Token_Budget_Middleware();
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive(str_repeat('x', AIPS_Token_Budget_Middleware::DEFAULT_MAX_CHARS - 10));

		$result = $middleware->process($document, $this->make_context());

		$this->assertSame($document, $result);
	}

	public function test_registered_as_part_of_default_composer_pipeline() {
		$container = AIPS_Container::get_instance();
		$composer = $container->make(AIPS_Prompt_Composer::class);

		$found = false;
		foreach ($composer->get_middlewares() as $middleware) {
			if ($middleware instanceof AIPS_Token_Budget_Middleware) {
				$found = true;
				break;
			}
		}

		$this->assertTrue($found, 'Expected the default composer pipeline to include the token budget middleware.');
	}
}
