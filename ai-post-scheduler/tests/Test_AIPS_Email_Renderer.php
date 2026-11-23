<?php
/**
 * Tests for AIPS_Email_Renderer
 *
 * Covers the client-compatibility contract the redesign depends on: table-based
 * layout, inline styles, no CSS the Outlook engine ignores, and escaping of every
 * value that reaches the markup.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Email_Renderer extends WP_UnitTestCase {

	/**
	 * @var AIPS_Email_Renderer
	 */
	private $renderer;

	public function set_up() {
		parent::set_up();
		$this->renderer = new AIPS_Email_Renderer();
	}

	public function tear_down() {
		remove_all_filters('aips_email_html');
		parent::tear_down();
	}

	/**
	 * A view exercising every region at once.
	 *
	 * @return AIPS_Email_View
	 */
	private function full_view() {
		return AIPS_Email_View::create(array(
			'notification_type' => 'post_generated',
			'subject'           => '[DevStackTips] A generated post',
			'preheader'         => 'A generated post is awaiting review.',
			'site_name'         => 'DevStackTips',
			'header_mode'       => AIPS_Email_View::HEADER_BANNER,
			'header_icon'       => '✓',
			'header_title'      => 'Post generated',
			'header_subtitle'   => 'DevStackTips · Today, 2:42 PM',
			'status_pill'       => array('label' => 'Draft', 'tone' => AIPS_Email_View::TONE_WARNING),
			'headline'          => 'Continuous Pentesting in Production',
			'headline_size'     => 'md',
			'callout'           => array('title' => 'Provider error', 'body' => 'Rate limit exceeded.'),
			'featured_image'    => array('url' => 'https://example.test/image.jpg', 'alt' => 'Featured'),
			'meta_cells'        => array(
				array('label' => 'Category', 'value' => 'Security'),
				array('label' => 'Generated', 'value' => 'Scheduled · 2:42 PM'),
				array('label' => 'Template', 'value' => 'Security News'),
			),
			'stat_tiles'        => array(
				array('label' => 'Generated', 'value' => '11', 'tone' => AIPS_Email_View::TONE_NEUTRAL),
				array('label' => 'Ready for review', 'value' => '55', 'tone' => AIPS_Email_View::TONE_WARNING),
			),
			'excerpt'           => 'Point-in-time penetration tests leave enterprises exposed.',
			'preview_blocks'    => array(
				array('type' => AIPS_Email_View::BLOCK_PARAGRAPH, 'text' => 'Recent disclosures underscore a relentless threat.'),
				array('type' => AIPS_Email_View::BLOCK_HEADING, 'text' => 'Myth 1: Annual tests are enough'),
			),
			'preview_link'      => array('url' => 'https://example.test/edit', 'label' => 'Read the full post in the editor'),
			'item_caption'      => 'Generated today',
			'item_rows'         => array(
				array('title' => 'Pre-Deployment Validation Checklist', 'meta' => '9:15 AM', 'url' => 'https://example.test/post-1'),
			),
			'actions'           => array(
				array('label' => 'Edit post', 'url' => 'https://example.test/edit'),
				array('label' => 'View post', 'url' => 'https://example.test/view'),
			),
			'footer_link'       => array('url' => 'https://example.test/settings', 'label' => 'Notification settings'),
		));
	}

	// -----------------------------------------------------------------------
	// Document shell
	// -----------------------------------------------------------------------

	public function test_render_produces_a_complete_html_document() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('<!DOCTYPE html', $html);
		$this->assertStringContainsString('</html>', $html);
		$this->assertStringContainsString('charset=UTF-8', $html);
	}

	public function test_render_uses_the_six_hundred_pixel_container_from_the_design() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('width:600px', $html);
		$this->assertStringContainsString('max-width:600px', $html);
	}

	public function test_render_includes_the_preheader_text() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('A generated post is awaiting review.', $html);
		// The preheader must stay hidden in the body itself.
		$this->assertStringContainsString('mso-hide:all', $html);
	}

	// -----------------------------------------------------------------------
	// Client compatibility
	// -----------------------------------------------------------------------

	public function test_render_avoids_css_the_outlook_engine_cannot_handle() {
		$html = $this->renderer->render($this->full_view());

		// The prototype used flex, grid, aspect-ratio, and gradient fades. None of
		// them survive the Word rendering engine, so none may reach the output.
		$this->assertStringNotContainsString('display:flex', $html);
		$this->assertStringNotContainsString('display:grid', $html);
		$this->assertStringNotContainsString('grid-template-columns', $html);
		$this->assertStringNotContainsString('aspect-ratio', $html);
		$this->assertStringNotContainsString('linear-gradient', $html);
	}

	public function test_render_lays_regions_out_with_tables() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('role="presentation"', $html);
		$this->assertStringContainsString('<table', $html);
	}

	public function test_body_regions_carry_inline_styles_because_gmail_strips_style_blocks() {
		$html = $this->renderer->render($this->full_view());

		// Strip the enhancement <style> block, then assert the remaining markup is
		// still fully styled.
		$body_only = preg_replace('/<style.*?<\/style>/s', '', $html);

		$this->assertStringContainsString('background-color:#3858e9', $body_only); // primary button
		$this->assertStringContainsString('#fafafa', $body_only);                  // metadata cell / footer
		$this->assertStringContainsString('font-family:', $body_only);
	}

	public function test_media_queries_and_dark_mode_remain_as_progressive_enhancement() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('@media only screen and (max-width: 600px)', $html);
		$this->assertStringContainsString('prefers-color-scheme: dark', $html);
	}

	// -----------------------------------------------------------------------
	// Regions
	// -----------------------------------------------------------------------

	public function test_every_populated_region_renders() {
		$html = $this->renderer->render($this->full_view());

		$this->assertStringContainsString('Post generated', $html);                         // banner title
		$this->assertStringContainsString('DevStackTips · Today, 2:42 PM', $html);          // banner subtitle
		$this->assertStringContainsString('Draft', $html);                                  // status pill
		$this->assertStringContainsString('Continuous Pentesting in Production', $html);    // headline
		$this->assertStringContainsString('Provider error', $html);                          // callout
		$this->assertStringContainsString('https://example.test/image.jpg', $html);          // featured image
		$this->assertStringContainsString('Security News', $html);                            // metadata cell
		$this->assertStringContainsString('Ready for review', $html);                          // stat tile
		$this->assertStringContainsString('Myth 1: Annual tests are enough', $html);          // preview heading
		$this->assertStringContainsString('Pre-Deployment Validation Checklist', $html);      // item row
		$this->assertStringContainsString('Edit post', $html);                                 // primary action
		$this->assertStringContainsString('Notification settings', $html);                     // footer link
	}

	public function test_empty_regions_are_skipped_entirely() {
		$view = AIPS_Email_View::create(array(
			'site_name' => 'DevStackTips',
			'headline'  => 'Only a headline',
		));

		$html = $this->renderer->render($view);

		$this->assertStringContainsString('Only a headline', $html);
		// No metadata cells, callout, or buttons were supplied, so their markers
		// must not appear.
		$this->assertStringNotContainsString('mso-padding-alt', $html);
		$this->assertStringNotContainsString('Content preview', $html);
	}

	public function test_the_footer_always_names_the_site() {
		$html = $this->renderer->render(AIPS_Email_View::create(array('site_name' => 'DevStackTips')));

		$this->assertStringContainsString('DevStackTips', $html);
		$this->assertStringContainsString('Sent by AI Post Scheduler', $html);
	}

	public function test_a_brand_header_renders_the_product_name_instead_of_a_banner() {
		$view = AIPS_Email_View::create(array(
			'header_mode' => AIPS_Email_View::HEADER_BRAND,
			'site_name'   => 'DevStackTips',
			'headline'    => 'Digest',
		));

		$html = $this->renderer->render($view);

		$this->assertStringContainsString('AI Post Scheduler', $html);
		// The banner's tinted band must not be present.
		$this->assertStringNotContainsString('border-radius:22px', $html);
	}

	// -----------------------------------------------------------------------
	// Escaping
	// -----------------------------------------------------------------------

	public function test_hostile_values_are_escaped_everywhere_they_appear() {
		$payload = '<script>alert("xss")</script>';

		$view = AIPS_Email_View::create(array(
			'site_name'      => $payload,
			'header_mode'    => AIPS_Email_View::HEADER_BANNER,
			'header_title'   => $payload,
			'headline'       => $payload,
			'callout'        => array('title' => $payload, 'body' => $payload),
			'meta_cells'     => array(array('label' => $payload, 'value' => $payload)),
			'preview_blocks' => array(array('type' => 'paragraph', 'text' => $payload)),
			'item_rows'      => array(array('title' => $payload, 'meta' => $payload)),
			'actions'        => array(array('label' => $payload, 'url' => 'https://example.test/ok')),
			'status_pill'    => array('label' => $payload),
		));

		$html = $this->renderer->render($view);

		$this->assertStringNotContainsString('<script>', $html);
		$this->assertStringContainsString('&lt;script&gt;', $html);
	}

	public function test_a_javascript_action_url_is_stripped_by_url_escaping() {
		$view = AIPS_Email_View::create(array(
			'actions' => array(
				array('label' => 'Click', 'url' => 'javascript:alert(1)'),
			),
		));

		$html = $this->renderer->render($view);

		$this->assertStringNotContainsString('javascript:alert', $html);
	}

	// -----------------------------------------------------------------------
	// Filter
	// -----------------------------------------------------------------------

	public function test_the_html_filter_can_alter_the_rendered_email() {
		add_filter('aips_email_html', function () {
			return 'replaced';
		});

		$this->assertSame('replaced', $this->renderer->render($this->full_view()));
	}

	// -----------------------------------------------------------------------
	// Plain-text alternative
	// -----------------------------------------------------------------------

	public function test_render_text_produces_a_readable_plain_text_part() {
		$text = $this->renderer->render_text($this->full_view());

		$this->assertStringNotContainsString('<', $text);
		$this->assertStringContainsString('Post generated', $text);
		$this->assertStringContainsString('Category: Security', $text);
		$this->assertStringContainsString('Edit post: https://example.test/edit', $text);
		$this->assertStringContainsString('Sent by AI Post Scheduler', $text);
	}

	public function test_render_text_collapses_gaps_left_by_empty_regions() {
		$text = $this->renderer->render_text(AIPS_Email_View::create(array(
			'site_name' => 'DevStackTips',
			'headline'  => 'Only a headline',
		)));

		$this->assertStringNotContainsString("\n\n\n", $text);
	}
}
