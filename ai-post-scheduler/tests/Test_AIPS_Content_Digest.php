<?php
/**
 * Tests for bounded stateless article context.
 *
 * @package AI_Post_Scheduler
 */
class Test_AIPS_Content_Digest extends WP_UnitTestCase {

	public function test_short_content_is_unchanged() {
		$digest = new AIPS_Content_Digest();

		$this->assertSame('Short article.', $digest->build('Short article.', 1000));
	}

	public function test_long_content_keeps_outline_and_conclusion_within_budget() {
		$content = '<p>' . str_repeat('Opening context. ', 100) . '</p>'
			. '<h2>Important Findings</h2>'
			. '<p>' . str_repeat('Middle detail. ', 100) . '</p>'
			. '<h2>Final Recommendation</h2>'
			. '<p>' . str_repeat('Concluding evidence. ', 100) . '</p>';
		$digest = (new AIPS_Content_Digest())->build($content, 1200);

		$this->assertStringContainsString('ARTICLE BEGINNING:', $digest);
		$this->assertStringContainsString('ARTICLE OUTLINE:', $digest);
		$this->assertStringContainsString('Important Findings', $digest);
		$this->assertStringContainsString('ARTICLE CONCLUSION:', $digest);
		$this->assertLessThanOrEqual(1200, mb_strlen($digest));
	}

	public function test_html_heavy_content_with_short_plain_text_avoids_duplication() {
		// Content has lots of HTML markup exceeding max_chars, but plain text fits in budget
		$tags = str_repeat('<div class="wrapper"><span data-attr="test"></span></div>', 50);
		$content = $tags . '<p>This is a short body with heavy HTML wrapper.</p>' . $tags;
		$digest = (new AIPS_Content_Digest())->build($content, 1000);

		$this->assertSame('This is a short body with heavy HTML wrapper.', $digest);
		$this->assertStringNotContainsString('ARTICLE BEGINNING:', $digest);
		$this->assertStringNotContainsString('ARTICLE CONCLUSION:', $digest);
	}

	public function test_headings_decode_html_entities() {
		$content = '<p>' . str_repeat('Intro text. ', 100) . '</p>'
			. '<h2>Security &amp; Best Practices &mdash; &quot;Guide&quot;</h2>'
			. '<p>' . str_repeat('Body text. ', 100) . '</p>';
		$digest = (new AIPS_Content_Digest())->build($content, 1000);

		$this->assertStringContainsString('Security & Best Practices — "Guide"', $digest);
		$this->assertStringNotContainsString('&amp;', $digest);
		$this->assertStringNotContainsString('&mdash;', $digest);
		$this->assertStringNotContainsString('&quot;', $digest);
	}

	public function test_huge_outline_does_not_exceed_max_chars() {
		$headings = '';
		for ($i = 1; $i <= 20; $i++) {
			$headings .= '<h2>' . str_repeat("Heading{$i}SectionTitle", 10) . '</h2>';
		}
		$content = '<p>' . str_repeat('Intro text. ', 200) . '</p>' . $headings . '<p>' . str_repeat('Outro text. ', 200) . '</p>';
		$digest = (new AIPS_Content_Digest())->build($content, 1000);

		$this->assertLessThanOrEqual(1000, mb_strlen($digest));
	}
}
