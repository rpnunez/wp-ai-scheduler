<?php
/**
 * Test case for Template Processor
 *
 * Tests the extraction and functionality of AIPS_Template_Processor class.
 *
 * @package AI_Post_Scheduler
 * @since 1.4.0
 */

class Test_AIPS_Template_Processor extends WP_UnitTestCase {

    private $processor;

    public function setUp(): void {
        parent::setUp();
        $this->processor = new AIPS_Template_Processor();
    }

    /**
     * Test basic variable replacement
     */
    public function test_basic_variable_replacement() {
        $template = "Today is {{date}} and the site is {{site_name}}";
        $result = $this->processor->process($template);
        
        // Result should not contain {{date}} or {{site_name}}
        $this->assertStringNotContainsString('{{date}}', $result);
        $this->assertStringNotContainsString('{{site_name}}', $result);
        
        // Should contain the actual date
        $this->assertStringContainsString(date('F j, Y'), $result);
    }

    /**
     * Test topic variable replacement
     */
    public function test_topic_variable_replacement() {
        $template = "Write about {{topic}} in the year {{year}}";
        $result = $this->processor->process($template, 'WordPress Security');
        
        $this->assertStringContainsString('WordPress Security', $result);
        $this->assertStringContainsString(date('Y'), $result);
        $this->assertStringNotContainsString('{{topic}}', $result);
    }

    /**
     * Test title alias for topic
     */
    public function test_title_alias_for_topic() {
        $template = "The title is {{title}}";
        $result = $this->processor->process($template, 'My Article');
        
        $this->assertStringContainsString('My Article', $result);
        $this->assertStringNotContainsString('{{title}}', $result);
    }

    /**
     * Test empty topic handling
     */
    public function test_empty_topic_handling() {
        $template = "Topic: {{topic}} - Year: {{year}}";
        $result = $this->processor->process($template);
        
        // Topic should be replaced with empty string
        $this->assertStringContainsString('Topic:  - Year:', $result);
        $this->assertStringContainsString(date('Y'), $result);
    }

    /**
     * Test all date/time variables
     */
    public function test_datetime_variables() {
        $template = "{{date}} {{year}} {{month}} {{day}} {{time}}";
        $result = $this->processor->process($template);
        
        // Check that variables are replaced (not checking exact values as they're time-dependent)
        $this->assertStringNotContainsString('{{date}}', $result);
        $this->assertStringNotContainsString('{{year}}', $result);
        $this->assertStringNotContainsString('{{month}}', $result);
        $this->assertStringNotContainsString('{{day}}', $result);
        $this->assertStringNotContainsString('{{time}}', $result);
    }

    /**
     * Test random number generation
     */
    public function test_random_number_variable() {
        $template = "Random: {{random_number}}";
        $result = $this->processor->process($template);
        
        $this->assertStringNotContainsString('{{random_number}}', $result);
        // Result should contain a number
        $this->assertMatchesRegularExpression('/Random: \d+/', $result);
    }

    /**
     * Test get_variables returns array
     */
    public function test_get_variables_returns_array() {
        $variables = $this->processor->get_variables();
        
        $this->assertIsArray($variables);
        $this->assertArrayHasKey('{{date}}', $variables);
        $this->assertArrayHasKey('{{year}}', $variables);
        $this->assertArrayHasKey('{{site_name}}', $variables);
        $this->assertArrayHasKey('{{topic}}', $variables);
    }

    /**
     * Test get_variables with topic
     */
    public function test_get_variables_with_topic() {
        $variables = $this->processor->get_variables('Test Topic');
        
        $this->assertEquals('Test Topic', $variables['{{topic}}']);
        $this->assertEquals('Test Topic', $variables['{{title}}']);
    }

    /**
     * Test get_variable_names returns clean names
     */
    public function test_get_variable_names() {
        $names = $this->processor->get_variable_names();
        
        $this->assertIsArray($names);
        $this->assertContains('date', $names);
        $this->assertContains('year', $names);
        $this->assertContains('topic', $names);
        $this->assertContains('site_name', $names);
        
        // Should not contain braces
        foreach ($names as $name) {
            $this->assertStringNotContainsString('{{', $name);
            $this->assertStringNotContainsString('}}', $name);
        }
    }

    /**
     * Test validate_template with valid template
     */
    public function test_validate_template_valid() {
        $template = "Write about {{topic}} on {{date}}";
        $result = $this->processor->validate_template($template);
        
        $this->assertTrue($result);
    }

    /**
     * Test validate_template with unclosed braces
     */
    public function test_validate_template_unclosed_braces() {
        $template = "Write about {{topic on {{date}}";
        $result = $this->processor->validate_template($template);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('unclosed_braces', $result->get_error_code());
    }

    /**
     * Test validate_template with invalid variable
     */
    public function test_validate_template_invalid_variable() {
        $template = "Write about {{invalid_var}} on {{date}}";
        $result = $this->processor->validate_template($template);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_variable', $result->get_error_code());
    }

    /**
     * Test filter hook for custom variables
     */
    public function test_custom_variable_filter() {
        add_filter('aips_template_variables', function($variables) {
            $variables['{{custom}}'] = 'Custom Value';
            return $variables;
        });
        
        $template = "Custom: {{custom}}";
        $result = $this->processor->process($template);
        
        $this->assertStringContainsString('Custom Value', $result);
        
        // Clean up filter
        remove_all_filters('aips_template_variables');
    }

    /**
     * Test multiple occurrences of same variable
     */
    public function test_multiple_occurrences() {
        $template = "{{topic}} is important. Let's discuss {{topic}} again.";
        $result = $this->processor->process($template, 'Security');
        
        // Both occurrences should be replaced
        $this->assertEquals("Security is important. Let's discuss Security again.", $result);
    }

    /**
     * Test template with no variables
     */
    public function test_template_without_variables() {
        $template = "This is a plain template with no variables";
        $result = $this->processor->process($template);
        
        $this->assertEquals($template, $result);
    }
}
