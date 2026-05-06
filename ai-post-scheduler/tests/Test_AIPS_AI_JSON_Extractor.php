<?php

/**
 * Test case for AIPS_AI_JSON_Extractor
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

class Test_AIPS_AI_JSON_Extractor extends WP_UnitTestCase {

    private $extractor;

    public function setUp(): void {
        parent::setUp();
        $this->extractor = new AIPS_AI_JSON_Extractor();
    }

    public function tearDown(): void {
        parent::tearDown();
        $this->extractor = null;
    }

    public function test_extracts_simple_object() {
        $json = '{"key":"value"}';
        $result = $this->extractor->extract($json);
        $this->assertEquals($json, $result);
    }

    public function test_extracts_simple_array() {
        $json = '[{"key":"value"}]';
        $result = $this->extractor->extract($json);
        $this->assertEquals($json, $result);
    }

    public function test_strips_markdown_wrappers() {
        $json = '{"key":"value"}';

        $wrapped1 = "```json\n" . $json . "\n```";
        $this->assertEquals($json, $this->extractor->extract($wrapped1));

        $wrapped2 = "```\n" . $json . "\n```";
        $this->assertEquals($json, $this->extractor->extract($wrapped2));
    }

    public function test_strips_preceding_text() {
        $json = '{"key":"value"}';
        $text = "Here is the json you requested:\n\n" . $json;
        $this->assertEquals($json, $this->extractor->extract($text));
    }

    public function test_strips_trailing_text() {
        $json = '{"key":"value"}';
        $text = $json . "\n\nI hope this helps!";
        $this->assertEquals($json, $this->extractor->extract($text));
    }

    public function test_handles_nested_objects() {
        $json = '{"key":{"nested":"value"}}';
        $text = "Prefix " . $json . " suffix";
        $this->assertEquals($json, $this->extractor->extract($text));
    }

    public function test_fails_on_no_json() {
        $text = "There is no json here.";
        $result = $this->extractor->extract($text);
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('json_extract_failed', $result->get_error_code());
    }

    public function test_fails_on_truncated_json() {
        $text = '{"key":"value"';
        $result = $this->extractor->extract($text);
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('json_extract_failed', $result->get_error_code());
    }

    public function test_fails_on_mismatched_tokens() {
        $text = '{"key":"value"]';
        $result = $this->extractor->extract($text);
        $this->assertTrue(is_wp_error($result));
        $this->assertEquals('json_extract_failed', $result->get_error_code());
    }

    public function test_ignores_braces_in_strings() {
        $json = '{"key":"value { with } braces"}';
        $text = "Prefix " . $json . " Suffix";
        $this->assertEquals($json, $this->extractor->extract($text));
    }

    public function test_sanitizes_control_characters() {
        // We inject a literal newline and tab inside the JSON string value
        $raw = '{"key":"value' . "\n" . 'with' . "\t" . 'tabs"}';
        $result = $this->extractor->extract($raw);

        // Expected should have escaped newline and tab
        $expected = '{"key":"value\nwith\ttabs"}';
        $this->assertEquals($expected, $result);
    }
}