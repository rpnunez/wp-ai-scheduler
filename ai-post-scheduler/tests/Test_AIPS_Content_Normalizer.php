<?php
/**
 * Tests for AIPS_Content_Normalizer
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Normalizer extends WP_UnitTestCase {

	/**
	 * @var AIPS_Content_Normalizer
	 */
	private $normalizer;

	public function setUp(): void {
		parent::setUp();
		$this->normalizer = new AIPS_Content_Normalizer();
	}

	public function test_normalize_content_empty_or_non_string() {
		$this->assertSame('', $this->normalizer->normalize_content(''));
		$this->assertSame('', $this->normalizer->normalize_content(null));
		$this->assertSame('', $this->normalizer->normalize_content(array()));
		$this->assertSame('', $this->normalizer->normalize_content("   \n\t  "));
	}

	public function test_normalize_content_kses_sanitization() {
		$raw = '<p>Safe content</p><script>alert("evil")</script><style>.body{}</style>';
		$normalized = $this->normalizer->normalize_content($raw);

		$this->assertStringContainsString('<p>Safe content</p>', $normalized);
		$this->assertStringNotContainsString('<script>', $normalized);
		$this->assertStringNotContainsString('</script>', $normalized);
		$this->assertStringNotContainsString('<style>', $normalized);
		$this->assertStringNotContainsString('</style>', $normalized);
	}

	public function test_strip_leading_title_block_html_h1() {
		$content = "<h1>The Main Article Title</h1>\n<p>Here is paragraph one.</p>";
		$stripped = $this->normalizer->strip_leading_title_block($content);

		$this->assertSame("<p>Here is paragraph one.</p>", $stripped);
	}

	public function test_strip_leading_title_block_markdown_h1() {
		$content = "# Markdown Heading 1\n\nHere is paragraph one.";
		$stripped = $this->normalizer->strip_leading_title_block($content);

		$this->assertSame("Here is paragraph one.", $stripped);
	}

	public function test_strip_leading_title_block_leaves_subsequent_h1_intact() {
		$content = "<p>Intro</p>\n<h1>Section 1</h1>";
		$stripped = $this->normalizer->strip_leading_title_block($content);

		$this->assertSame($content, $stripped);
	}

	public function test_truncate_excerpt_boundary() {
		$short = 'A brief excerpt that is well within the limit.';
		$this->assertSame($short, $this->normalizer->truncate_excerpt($short, 100));

		$long = 'The quick brown fox jumps over the lazy dog and runs across the wide open fields in the sunshine.';
		$truncated = $this->normalizer->truncate_excerpt($long, 35);

		$this->assertLessThanOrEqual(35, mb_strlen($truncated));
		// Checks that it broke on a space (word boundary) rather than chopping "the" or "fox" mid-word
		$this->assertStringEndsNotWith(' ', $truncated);
	}

	public function test_normalize_excerpt_strips_quotes() {
		$quoted = '"Here is a quoted excerpt."';
		$normalized = $this->normalizer->normalize_excerpt($quoted);

		$this->assertSame('Here is a quoted excerpt.', $normalized);

		$single_quoted = "'Single quoted excerpt.'";
		$normalized_single = $this->normalizer->normalize_excerpt($single_quoted);

		$this->assertSame('Single quoted excerpt.', $normalized_single);
	}
}
