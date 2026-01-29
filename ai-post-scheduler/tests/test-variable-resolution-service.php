<?php
/**
 * Test case for Variable Resolution Service
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Variable_Resolution_Service extends WP_UnitTestCase {

    private $service;
    private $ai_service_mock;
    private $logger_mock;
    private $template_processor_mock;

    public function setUp(): void {
        parent::setUp();

        $this->ai_service_mock = $this->getMockBuilder('AIPS_AI_Service')
            ->setMethods(array('generate_text'))
            ->getMock();

        $this->logger_mock = $this->getMockBuilder('AIPS_Logger')
            ->setMethods(array('log'))
            ->getMock();

        $this->template_processor_mock = $this->getMockBuilder('AIPS_Template_Processor')
            ->setMethods(array('extract_ai_variables', 'build_ai_variables_prompt', 'parse_ai_variables_response'))
            ->getMock();

        $this->service = new AIPS_Variable_Resolution_Service(
            $this->ai_service_mock,
            $this->logger_mock,
            $this->template_processor_mock
        );
    }

    public function test_smart_truncate_content() {
        // Short content should be returned as is
        $content = "Short content";
        $this->assertEquals($content, $this->service->smart_truncate_content($content, 100));

        // Long content should be truncated
        $long_content = str_repeat("a", 3000);
        $truncated = $this->service->smart_truncate_content($long_content, 1000);
        $this->assertStringContainsString("[...]", $truncated);
        $this->assertLessThan(strlen($long_content), strlen($truncated));
        $this->assertLessThanOrEqual(1000, strlen($truncated));
    }

    public function test_resolve_ai_variables_no_variables() {
        $template = new stdClass();
        $template->id = 1;

        $this->template_processor_mock->expects($this->once())
            ->method('extract_ai_variables')
            ->willReturn(array());

        $result = $this->service->resolve_ai_variables($template, "content");
        $this->assertEmpty($result);
    }

    public function test_resolve_ai_variables_success() {
        $template = new stdClass();
        $template->id = 1;
        $template->title_prompt = "Title with {{var}}";
        $template->prompt_template = "Content prompt";
        $template->post_quantity = 1;

        $this->template_processor_mock->expects($this->once())
            ->method('extract_ai_variables')
            ->willReturn(array('var'));

        $this->template_processor_mock->expects($this->once())
            ->method('build_ai_variables_prompt')
            ->willReturn("prompt");

        $this->ai_service_mock->expects($this->once())
            ->method('generate_text')
            ->willReturn('{"var": "value"}');

        $this->template_processor_mock->expects($this->once())
            ->method('parse_ai_variables_response')
            ->willReturn(array('var' => 'value'));

        $result = $this->service->resolve_ai_variables($template, "content");
        $this->assertEquals(array('var' => 'value'), $result);
    }
}
