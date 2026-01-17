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
     * Test validate_template allows AI variables by default
     */
    public function test_validate_template_allows_ai_variables_by_default() {
        $template = "Write about {{CustomVariable}} on {{date}}";
        $result = $this->processor->validate_template($template);
        
        // AI variables are allowed by default
        $this->assertTrue($result);
    }

    /**
     * Test validate_template with invalid variable when AI variables disabled
     */
    public function test_validate_template_invalid_variable_strict_mode() {
        $template = "Write about {{invalid_var}} on {{date}}";
        $result = $this->processor->validate_template($template, false);
        
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

    // ============================================
    // AI Variables Tests
    // ============================================

    /**
     * Test extract_ai_variables extracts custom variables
     */
    public function test_extract_ai_variables() {
        $template = "PHP Framework: {{PHPFramework1Name}} vs. {{PHPFramework2Name}}";
        $ai_vars = $this->processor->extract_ai_variables($template);
        
        $this->assertIsArray($ai_vars);
        $this->assertCount(2, $ai_vars);
        $this->assertContains('PHPFramework1Name', $ai_vars);
        $this->assertContains('PHPFramework2Name', $ai_vars);
    }

    /**
     * Test extract_ai_variables excludes system variables
     */
    public function test_extract_ai_variables_excludes_system_vars() {
        $template = "On {{date}}: {{CustomVar}} vs. {{topic}}";
        $ai_vars = $this->processor->extract_ai_variables($template);
        
        $this->assertCount(1, $ai_vars);
        $this->assertContains('CustomVar', $ai_vars);
        $this->assertNotContains('date', $ai_vars);
        $this->assertNotContains('topic', $ai_vars);
    }

    /**
     * Test extract_ai_variables removes duplicates
     */
    public function test_extract_ai_variables_removes_duplicates() {
        $template = "{{Framework}} is better than {{Framework}} sometimes";
        $ai_vars = $this->processor->extract_ai_variables($template);
        
        $this->assertCount(1, $ai_vars);
        $this->assertContains('Framework', $ai_vars);
    }

    /**
     * Test extract_ai_variables returns empty for template with only system vars
     */
    public function test_extract_ai_variables_empty_for_system_only() {
        $template = "Written on {{date}} by {{site_name}}";
        $ai_vars = $this->processor->extract_ai_variables($template);
        
        $this->assertIsArray($ai_vars);
        $this->assertEmpty($ai_vars);
    }

    /**
     * Test has_ai_variables returns true when AI variables present
     */
    public function test_has_ai_variables_true() {
        $template = "Comparing {{Language1}} with {{Language2}}";
        $result = $this->processor->has_ai_variables($template);
        
        $this->assertTrue($result);
    }

    /**
     * Test has_ai_variables returns false when no AI variables
     */
    public function test_has_ai_variables_false() {
        $template = "Written on {{date}} about {{topic}}";
        $result = $this->processor->has_ai_variables($template);
        
        $this->assertFalse($result);
    }

    /**
     * Test process_with_ai_variables replaces AI variables first
     */
    public function test_process_with_ai_variables() {
        $template = "{{Framework1}} vs {{Framework2}} - written on {{date}}";
        $ai_values = array(
            'Framework1' => 'Laravel',
            'Framework2' => 'Symfony'
        );
        
        $result = $this->processor->process_with_ai_variables($template, null, $ai_values);
        
        $this->assertStringContainsString('Laravel', $result);
        $this->assertStringContainsString('Symfony', $result);
        $this->assertStringContainsString(date('F j, Y'), $result);
        $this->assertStringNotContainsString('{{Framework1}}', $result);
        $this->assertStringNotContainsString('{{Framework2}}', $result);
        $this->assertStringNotContainsString('{{date}}', $result);
    }

    /**
     * Test process_with_ai_variables works with topic
     */
    public function test_process_with_ai_variables_with_topic() {
        $template = "{{CustomVar}} about {{topic}}";
        $ai_values = array('CustomVar' => 'Article');
        
        $result = $this->processor->process_with_ai_variables($template, 'Security', $ai_values);
        
        $this->assertEquals('Article about Security', $result);
    }

    /**
     * Test build_ai_variables_prompt creates proper prompt
     */
    public function test_build_ai_variables_prompt() {
        $ai_variables = array('Framework1', 'Framework2');
        $context = "Write an article comparing PHP frameworks.";
        
        $prompt = $this->processor->build_ai_variables_prompt($ai_variables, $context);
        
        $this->assertStringContainsString('Framework1', $prompt);
        $this->assertStringContainsString('Framework2', $prompt);
        $this->assertStringContainsString($context, $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    /**
     * Test build_ai_variables_prompt returns empty for no variables
     */
    public function test_build_ai_variables_prompt_empty() {
        $prompt = $this->processor->build_ai_variables_prompt(array(), "Some context");
        
        $this->assertEquals('', $prompt);
    }

    /**
     * Test parse_ai_variables_response parses valid JSON
     */
    public function test_parse_ai_variables_response_valid_json() {
        $response = '{"Framework1": "Laravel", "Framework2": "Symfony"}';
        $ai_variables = array('Framework1', 'Framework2');
        
        $values = $this->processor->parse_ai_variables_response($response, $ai_variables);
        
        $this->assertArrayHasKey('Framework1', $values);
        $this->assertArrayHasKey('Framework2', $values);
        $this->assertEquals('Laravel', $values['Framework1']);
        $this->assertEquals('Symfony', $values['Framework2']);
    }

    /**
     * Test parse_ai_variables_response handles markdown code blocks
     */
    public function test_parse_ai_variables_response_handles_markdown() {
        $response = "```json\n{\"Framework1\": \"CakePHP\", \"Framework2\": \"CodeIgniter\"}\n```";
        $ai_variables = array('Framework1', 'Framework2');
        
        $values = $this->processor->parse_ai_variables_response($response, $ai_variables);
        
        $this->assertEquals('CakePHP', $values['Framework1']);
        $this->assertEquals('CodeIgniter', $values['Framework2']);
    }

    /**
     * Test parse_ai_variables_response handles invalid JSON gracefully
     */
    public function test_parse_ai_variables_response_invalid_json() {
        $response = "This is not valid JSON";
        $ai_variables = array('Framework1', 'Framework2');
        
        $values = $this->processor->parse_ai_variables_response($response, $ai_variables);
        
        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    /**
     * Test parse_ai_variables_response filters to expected variables only
     */
    public function test_parse_ai_variables_response_filters_variables() {
        $response = '{"Framework1": "Laravel", "Framework2": "Symfony", "ExtraVar": "Ignored"}';
        $ai_variables = array('Framework1', 'Framework2');
        
        $values = $this->processor->parse_ai_variables_response($response, $ai_variables);
        
        $this->assertCount(2, $values);
        $this->assertArrayNotHasKey('ExtraVar', $values);
    }
}
