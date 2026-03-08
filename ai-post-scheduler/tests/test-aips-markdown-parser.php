<?php
/**
 * Tests for the Markdown Parser utility.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Markdown_Parser extends WP_UnitTestCase {

    private $parser;

    public function setUp(): void {
        parent::setUp();
        $this->parser = new AIPS_Markdown_Parser();
    }

    public function test_is_markdown_detects_markdown() {
        $markdown = "## Heading\n\n- List item 1\n- List item 2\n\n```php\necho 'hello';\n```";
        $this->assertTrue($this->parser->is_markdown($markdown));
    }

    public function test_is_markdown_returns_false_for_plain_text() {
        $text = "This is just a regular sentence.";
        $this->assertFalse($this->parser->is_markdown($text));
    }

    public function test_contains_html_detects_html() {
        $html = "<p>This has <strong>HTML</strong> tags.</p>";
        $this->assertTrue($this->parser->contains_html($html));
    }

    public function test_contains_html_returns_false_for_plain_text() {
        $text = "This does not have any HTML tags.";
        $this->assertFalse($this->parser->contains_html($text));
    }

    public function test_parse_converts_markdown_to_html() {
        $markdown = "## Heading\n\n- Item 1\n- Item 2\n\nJust a paragraph.";
        $html = $this->parser->parse($markdown);

        $this->assertStringContainsString('<h2>Heading</h2>', $html);
        $this->assertStringContainsString('<ul><li>Item 1</li><li>Item 2</li></ul>', $html);
        $this->assertStringContainsString('<p>Just a paragraph.</p>', $html);
    }

    public function test_parse_applies_inline_bold_formatting() {
        $markdown = "This is **bold** text.";
        $html     = $this->parser->parse( $markdown );

        $this->assertStringContainsString( '<strong>bold</strong>', $html );
    }

    public function test_parse_applies_inline_italic_formatting() {
        $markdown = "This is _italic_ text.";
        $html     = $this->parser->parse( $markdown );

        $this->assertStringContainsString( '<em>italic</em>', $html );
    }

    public function test_parse_applies_inline_code_formatting() {
        $markdown = "Use `code()` here.";
        $html     = $this->parser->parse( $markdown );

        $this->assertStringContainsString( '<code>code()</code>', $html );
    }
}
