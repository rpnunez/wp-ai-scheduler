<?php
/**
 * Tests accessibility guardrails checks for generated content.
 */
class Test_AIPS_Accessibility_Guardrails extends WP_UnitTestCase {

	public function test_analyze_detects_heading_skip_and_missing_alt_text() {
		$guardrails = new AIPS_Accessibility_Guardrails();
		$content = '<h2>Intro</h2><h4>Skipped</h4><p>Short paragraph.</p><img src="x.jpg" />';

		$result = $guardrails->analyze($content);

		$this->assertFalse($result['heading_hierarchy_ok']);
		$this->assertSame(1, $result['missing_alt_images']);
		$this->assertNotEmpty($result['warnings']);
	}

	public function test_analyze_flags_long_paragraph_and_low_plain_language_score() {
		$guardrails = new AIPS_Accessibility_Guardrails();
		$long_sentence = str_repeat('Complexityword ', 140) . '.';
		$content = '<h2>Heading</h2><p>' . $long_sentence . '</p>';

		$result = $guardrails->analyze($content);

		$this->assertSame(1, $result['long_paragraphs']);
		$this->assertSame(1, $result['long_sentences']);
		$this->assertLessThan($result['plain_language_target'], $result['plain_language_score']);
	}

	public function test_analyze_flags_link_issues_and_excessive_line_breaks() {
		$guardrails = new AIPS_Accessibility_Guardrails();
		$content = '<h1>Title</h1><h1>Another H1</h1>'
			. '<p><a href=\"#\">Click here</a></p>'
			. '<p><a>Broken link</a></p>'
			. '<br><br><br>';

		$result = $guardrails->analyze($content);

		$this->assertSame(2, $result['multiple_h1_count']);
		$this->assertSame(1, $result['non_descriptive_links']);
		$this->assertSame(2, $result['invalid_links']);
		$this->assertSame(1, $result['excessive_line_breaks']);
		$this->assertNotEmpty($result['warnings']);
	}

	public function test_analyze_returns_clean_report_for_accessible_content() {
		$guardrails = new AIPS_Accessibility_Guardrails();
		$content = '<h2>Overview</h2><h3>Step One</h3><p>This is easy to read and short.</p><p><a href="https://example.com">Read the full guide</a></p><img src="x.jpg" alt="Descriptive chart" />';

		$result = $guardrails->analyze($content);

		$this->assertTrue($result['heading_hierarchy_ok']);
		$this->assertSame(0, $result['multiple_h1_count']);
		$this->assertSame(0, $result['missing_alt_images']);
		$this->assertSame(0, $result['long_paragraphs']);
		$this->assertSame(0, $result['non_descriptive_links']);
		$this->assertSame(0, $result['invalid_links']);
		$this->assertSame(0, $result['excessive_line_breaks']);
	}
}
