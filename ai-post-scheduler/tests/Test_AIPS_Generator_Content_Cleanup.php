<?php
/**
 * Tests for generated-content cleanup behavior in AIPS_Generator.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Generator_Content_Cleanup extends WP_UnitTestCase {

    /**
     * @return array{0:AIPS_Generator,1:ReflectionMethod}
     */
    private function get_strip_method() {
        $reflection = new ReflectionClass('AIPS_Generator');
        $generator = $reflection->newInstanceWithoutConstructor();

        $method = $reflection->getMethod('strip_leading_title_block_from_content');
        $method->setAccessible(true);

        return array($generator, $method);
    }

    public function test_strip_leading_html_h1_title_block() {
        list($generator, $method) = $this->get_strip_method();

        $content = '<h1>The Silent Killer: Why Your Middleware Layer is Dropping Requests Under Load</h1><p>First paragraph.</p>';

        $cleaned = $method->invoke($generator, $content);

        $this->assertSame('<p>First paragraph.</p>', $cleaned);
    }

    public function test_strip_leading_markdown_h1_title_block() {
        list($generator, $method) = $this->get_strip_method();

        $content = "# The Silent Killer: Why Your Middleware Layer is Dropping Requests Under Load\n\nParagraph body starts here.";

        $cleaned = $method->invoke($generator, $content);

        $this->assertSame('Paragraph body starts here.', $cleaned);
    }

    public function test_keep_h2_opening_section_heading() {
        list($generator, $method) = $this->get_strip_method();

        $content = '<h2>Overview</h2><p>First paragraph.</p>';

        $cleaned = $method->invoke($generator, $content);

        $this->assertSame($content, $cleaned);
    }
}
