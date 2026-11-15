<?php
/**
 * Tests for AIPS_Prompt_Composer
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Test_Recording_Prompt_Middleware implements AIPS_Prompt_Middleware_Interface {
	private $label;
	private $calls;

	public function __construct($label, array &$calls) {
		$this->label = $label;
		$this->calls = &$calls;
	}

	public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
		$this->calls[] = $this->label;
		$document->add_constraint($this->label);
		return $document;
	}
}

class AIPS_Test_Replacing_Prompt_Middleware implements AIPS_Prompt_Middleware_Interface {
	public function process(AIPS_Prompt_Document $document, AIPS_Generation_Context $context) {
		$replacement = new AIPS_Prompt_Document();
		$replacement->set_task_directive('Replaced by middleware.');
		return $replacement;
	}
}

class Test_AIPS_Prompt_Composer extends WP_UnitTestCase {

	private function make_context() {
		return new AIPS_Topic_Context((object) array('id' => 1), (object) array('id' => 1, 'topic' => 'Test Topic'));
	}

	public function test_empty_pipeline_returns_document_unchanged() {
		$composer = new AIPS_Prompt_Composer();
		$document = new AIPS_Prompt_Document();
		$document->set_task_directive('Original directive.');

		$result = $composer->compose($document, $this->make_context());

		$this->assertSame($document, $result);
		$this->assertSame('Original directive.', $result->get_task_directive());
	}

	public function test_middlewares_run_in_registration_order() {
		$calls = array();
		$composer = new AIPS_Prompt_Composer(array(
			new AIPS_Test_Recording_Prompt_Middleware('first', $calls),
			new AIPS_Test_Recording_Prompt_Middleware('second', $calls),
			new AIPS_Test_Recording_Prompt_Middleware('third', $calls),
		));

		$composer->compose(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array('first', 'second', 'third'), $calls);
	}

	public function test_add_middleware_appends_to_end_of_pipeline() {
		$calls = array();
		$composer = new AIPS_Prompt_Composer(array(
			new AIPS_Test_Recording_Prompt_Middleware('first', $calls),
		));
		$composer->add_middleware(new AIPS_Test_Recording_Prompt_Middleware('second', $calls));

		$composer->compose(new AIPS_Prompt_Document(), $this->make_context());

		$this->assertSame(array('first', 'second'), $calls);
	}

	public function test_get_middlewares_reflects_registered_stack() {
		$unused = array();
		$middleware = new AIPS_Test_Recording_Prompt_Middleware('only', $unused);
		$composer = new AIPS_Prompt_Composer(array($middleware));

		$this->assertSame(array($middleware), $composer->get_middlewares());
	}

	public function test_each_middleware_receives_the_previous_middlewares_output() {
		$composer = new AIPS_Prompt_Composer(array(
			new AIPS_Test_Replacing_Prompt_Middleware(),
		));

		$original = new AIPS_Prompt_Document();
		$original->set_task_directive('Original directive.');

		$result = $composer->compose($original, $this->make_context());

		$this->assertNotSame($original, $result);
		$this->assertSame('Replaced by middleware.', $result->get_task_directive());
	}

	public function test_registered_as_container_singleton() {
		$container = AIPS_Container::get_instance();

		$first  = $container->make(AIPS_Prompt_Composer::class);
		$second = $container->make(AIPS_Prompt_Composer::class);

		$this->assertInstanceOf(AIPS_Prompt_Composer::class, $first);
		$this->assertSame($first, $second);
	}
}
