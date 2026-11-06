<?php
/**
 * Tests for Post Images Generation and Injection
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Images_Generation extends WP_UnitTestCase {

	private $generator;
	private $prompt_builder;

	public function setUp(): void {
		parent::setUp();
		$this->generator = new AIPS_Generator();
		$this->prompt_builder = new AIPS_Prompt_Builder_Post_Content();
	}

	public function test_prompt_builder_injects_image_placeholders_block() {
		$template = (object) array(
			'id'              => 0,
			'prompt_template' => 'Write an article about Artificial Intelligence.',
			'post_images'     => array(
				(object) array(
					'image_order' => 1,
					'placement'   => 'after_section_1',
				),
				(object) array(
					'image_order' => 2,
					'placement'   => 'auto',
				),
			),
		);

		$prompt = $this->prompt_builder->build($template, 'AI Trends');

		$this->assertStringContainsString('Image Placement Instructions:', $prompt);
		$this->assertStringContainsString('[post_image index="1"]', $prompt);
		$this->assertStringContainsString('after Section 1', $prompt);
		$this->assertStringContainsString('[post_image index="2"]', $prompt);
		$this->assertStringContainsString('place naturally', $prompt);
	}

	public function test_generator_inject_replaces_explicit_placeholder() {
		$content = "<p>Intro paragraph.</p>\n<p>[post_image index=\"1\"]</p>\n<p>Second paragraph.</p>";
		$markup  = '<figure class="wp-block-image size-large" data-aips-image-id="10"><img src="http://example.com/img.jpg" class="wp-image-1"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringNotContainsString('[post_image index="1"]', $result);
		$this->assertStringContainsString('data-aips-image-id="10"', $result);
		$this->assertStringContainsString('Intro paragraph.', $result);
		$this->assertStringContainsString('Second paragraph.', $result);
	}

	public function test_generator_inject_fallback_after_section() {
		$content = "<h2>Section One</h2>\n<p>Paragraph under section one.</p>\n<h2>Section Two</h2>\n<p>Paragraph under section two.</p>";
		$markup  = '<figure class="wp-block-image" data-aips-image-id="20"><img src="http://example.com/img2.jpg"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'after_section_1', $markup);

		$this->assertStringContainsString('data-aips-image-id="20"', $result);
		$pos_sec1 = strpos($result, 'Section One');
		$pos_markup = strpos($result, 'data-aips-image-id="20"');
		$pos_sec2 = strpos($result, 'Section Two');

		$this->assertGreaterThan($pos_sec1, $pos_markup);
		$this->assertLessThan($pos_sec2, $pos_markup);
	}

	public function test_generator_inject_fallback_auto_paragraph_distribution() {
		$content = "<p>First paragraph.</p>\n<p>Second paragraph.</p>\n<p>Third paragraph.</p>";
		$markup  = '<figure class="wp-block-image" data-aips-image-id="30"><img src="http://example.com/img3.jpg"/></figure>';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringContainsString('data-aips-image-id="30"', $result);
		$this->assertStringContainsString('First paragraph.', $result);
	}

	public function test_generator_failed_image_placeholder_comment() {
		$content = "<p>Paragraph before.</p>\n[post_image index=\"1\"]\n<p>Paragraph after.</p>";
		$markup  = '<!-- aips-image-placeholder id="42" -->';

		$result = $this->generator->inject_single_post_image($content, 1, 'auto', $markup);

		$this->assertStringNotContainsString('[post_image index="1"]', $result);
		$this->assertStringContainsString('<!-- aips-image-placeholder id="42" -->', $result);
	}
}
