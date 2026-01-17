<?php
/**
 * Tests for AIPS_Template_Processor whitespace handling.
 */

class AIPS_Template_Spaces_Test extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Ensure AIPS_Template_Processor is loaded
        if (!class_exists('AIPS_Template_Processor')) {
            require_once dirname(dirname(__FILE__)) . '/ai-post-scheduler/includes/class-aips-template-processor.php';
        }
    }

    public function test_process_handles_system_variables_with_spaces() {
        $processor = new AIPS_Template_Processor();
        $template = 'Today is {{ date }}.';

        // Mock date
        $expected_date = date('F j, Y');

        $result = $processor->process($template);

        $this->assertEquals("Today is {$expected_date}.", $result, 'System variables with spaces should be replaced.');
    }

    public function test_process_with_ai_variables_handles_spaces() {
        $processor = new AIPS_Template_Processor();
        $template = 'Write about {{ KeyConcept }}.';
        $ai_values = array('KeyConcept' => 'Quantum Physics');

        $result = $processor->process_with_ai_variables($template, null, $ai_values);

        $this->assertEquals('Write about Quantum Physics.', $result, 'AI variables with spaces should be replaced.');
    }

    public function test_mixed_variables_handling() {
        $processor = new AIPS_Template_Processor();
        $template = '{{ date }} - {{ Topic }}';
        $ai_values = array('Topic' => 'Space Exploration');
        $expected_date = date('F j, Y');

        $result = $processor->process_with_ai_variables($template, null, $ai_values);

        $this->assertEquals("{$expected_date} - Space Exploration", $result);
    }
}
