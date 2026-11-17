<?php
/**
 * Tests for AIPS_Prompt_Builder_Post_Slug
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Builder_Post_Slug extends WP_UnitTestCase {

	/**
	 * @var AIPS_Prompt_Builder_Post_Slug
	 */
	private $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new AIPS_Prompt_Builder_Post_Slug(new AIPS_Template_Processor());
	}

	public function tearDown(): void {
		remove_all_filters('aips_slug_prompt');
		remove_all_filters('aips_slug_context_max_chars');
		parent::tearDown();
	}

	/**
	 * Default prompt contains SEO guidelines when no custom instructions are set.
	 */
	public function test_default_seo_guidelines_in_prompt() {
		$template = (object) array(
			'slug_prompt' => '',
		);

		$result = $this->builder->build($template, 'WordPress Development', null, 'Post content here.', 'WordPress Development Guide');

		$this->assertStringContainsString('Generate a clean, SEO-friendly URL slug', $result);
		$this->assertStringContainsString('Lowercase', $result);
		$this->assertStringContainsString('hyphens', $result);
		$this->assertStringContainsString('3 to 6 words', $result);
		$this->assertStringContainsString('WordPress Development Guide', $result);
	}

	/**
	 * Custom slug prompt replaces variables like {{topic}} and {{title}}.
	 */
	public function test_custom_slug_prompt_with_variables() {
		$template = (object) array(
			'slug_prompt' => 'Focus on {{topic}} and ensure it matches {{title}}.',
		);

		$result = $this->builder->build($template, 'AI Automation', null, 'Content...', 'Automating WordPress with AI');

		$this->assertStringContainsString('Focus on AI Automation and ensure it matches Automating WordPress with AI.', $result);
	}

	/**
	 * Voice slug_prompt overrides template slug_prompt.
	 */
	public function test_voice_slug_prompt_overrides_template() {
		$template = (object) array(
			'slug_prompt' => 'Template slug prompt for {{topic}}.',
		);
		$voice = (object) array(
			'slug_prompt' => 'Voice slug prompt for {{topic}}.',
		);

		$result = $this->builder->build($template, 'Cloud Computing', $voice, 'Content...');

		$this->assertStringContainsString('Voice slug prompt for Cloud Computing.', $result);
		$this->assertStringNotContainsString('Template slug prompt', $result);
	}

	/**
	 * Topic Context correctly resolves slug instructions from author.
	 */
	public function test_topic_context_slug_prompt() {
		$author = (object) array(
			'id'          => 1,
			'name'        => 'Jane Doe',
			'slug_prompt' => 'Short 2-3 word slug for {{topic}}',
		);
		$topic = (object) array(
			'id'          => 2,
			'topic_title' => 'Serverless Architecture',
		);
		$context = new AIPS_Topic_Context($author, $topic);

		$result = $this->builder->build($context, 'Serverless Architecture', null, 'Content...', 'Serverless Architecture 101');

		$this->assertStringContainsString('Short 2-3 word slug for Serverless Architecture', $result);
	}

	/**
	 * Filter aips_slug_prompt allows modifying the final prompt string.
	 */
	public function test_filter_aips_slug_prompt() {
		add_filter('aips_slug_prompt', function($prompt, $template, $topic) {
			return $prompt . ' [FILTERED]';
		}, 10, 3);

		$template = (object) array('slug_prompt' => '');
		$result = $this->builder->build($template, 'Testing Filters');

		$this->assertStringContainsString('[FILTERED]', $result);
	}

	/**
	 * A template context voice slug prompt overrides the template, matching title precedence.
	 */
	public function test_template_context_voice_slug_prompt_overrides_template() {
		$template = (object) array(
			'id'              => 1,
			'name'            => 'Template',
			'prompt_template' => 'Write about {{topic}}.',
			'slug_prompt'     => 'Template slug prompt for {{topic}}.',
		);
		$voice = (object) array(
			'id'          => 9,
			'slug_prompt' => 'Voice slug prompt for {{title}}.',
		);
		$context = new AIPS_Template_Context($template, $voice, 'Cloud Computing');

		$result = $this->builder->build($context, null, null, 'Content...', 'Edge Networks');

		$this->assertStringContainsString('Voice slug prompt for Edge Networks.', $result);
		$this->assertStringNotContainsString('Template slug prompt', $result);
	}

	/**
	 * Follow-up prompts are built from a generation context.
	 *
	 * The follow-up intro and response contract have used this wording since
	 * the slug step shipped. build_followup() requires a generation context
	 * (it calls get_topic()); a bare stdClass is not a caller.
	 */
	public function test_build_followup() {
		$template = (object) array(
			'id'              => 1,
			'name'            => 'Security Template',
			'prompt_template' => 'Write about {{topic}}.',
			'slug_prompt'     => 'Keep it ultra brief.',
		);
		$context = new AIPS_Template_Context($template, null, 'Security');
		$result = $this->builder->build_followup($context, 'Security');

		$this->assertStringContainsString('Now generate an SEO-friendly URL slug for the article.', $result);
		$this->assertStringContainsString('Keep it ultra brief.', $result);
		$this->assertStringContainsString('Respond with ONLY one lowercase hyphenated slug, nothing else.', $result);
	}

	/**
	 * A long article is condensed to the slug cap instead of being pasted in.
	 *
	 * The slug prompt used to inherit the digest's 12,000 character default, four
	 * times the title prompt's cap, for a result that is three to six keywords.
	 */
	public function test_long_article_is_condensed_to_the_slug_cap() {
		$prompt = $this->builder->build($this->make_context(), null, null, $this->make_article(20000), 'Laptop Buying Guide');

		$context_text = $this->extract_article_data($prompt);

		$this->assertLessThanOrEqual(AIPS_Prompt_Builder_Post_Slug::CONTEXT_MAX_CHARS, mb_strlen($context_text));
		$this->assertSame(3000, AIPS_Prompt_Builder_Post_Slug::CONTEXT_MAX_CHARS);
		$this->assertStringContainsString('ARTICLE BEGINNING:', $context_text);
		$this->assertStringContainsString('ARTICLE CONCLUSION:', $context_text);
		$this->assertStringNotContainsString('ZZMIDDLEMARKERZZ', $context_text);
	}

	/**
	 * An article already under the cap is passed through unchanged.
	 */
	public function test_short_article_is_passed_through_unchanged() {
		$article = '<p>A short article about laptop batteries.</p>';
		$prompt  = $this->builder->build($this->make_context(), null, null, $article, 'Laptop Batteries');

		$this->assertSame($article, $this->extract_article_data($prompt));
	}

	/**
	 * The aips_slug_context_max_chars filter overrides the cap.
	 */
	public function test_slug_context_cap_is_filterable() {
		add_filter('aips_slug_context_max_chars', static function () {
			return 1000;
		});

		$prompt = $this->builder->build($this->make_context(), null, null, $this->make_article(20000), 'Laptop Buying Guide');

		$this->assertLessThanOrEqual(1000, mb_strlen($this->extract_article_data($prompt)));
	}

	/**
	 * The filter receives the generation context as its subject.
	 */
	public function test_slug_context_cap_filter_receives_the_context() {
		$context  = $this->make_context();
		$received = null;

		add_filter('aips_slug_context_max_chars', static function ($max_chars, $subject) use (&$received) {
			$received = $subject;
			return $max_chars;
		}, 10, 2);

		$this->builder->build($context, null, null, 'Content...', 'Title');

		$this->assertSame($context, $received);
	}

	/**
	 * @return AIPS_Template_Context
	 */
	private function make_context() {
		$template = (object) array(
			'id'              => 1,
			'name'            => 'Laptop Template',
			'prompt_template' => 'Write about {{topic}}.',
			'slug_prompt'     => '',
		);

		return new AIPS_Template_Context($template, null, 'Laptops');
	}

	/**
	 * Build an HTML article of roughly the requested length with a unique marker in the middle.
	 *
	 * @param int $length Approximate length in characters.
	 * @return string
	 */
	private function make_article($length) {
		$paragraph = '<p>' . str_repeat('Lorem ipsum dolor sit amet consectetur. ', 12) . '</p>';
		$count     = (int) ceil($length / strlen($paragraph));
		$parts     = array();

		for ($i = 1; $i <= $count; $i++) {
			if (0 === ($i - 1) % 5) {
				$parts[] = '<h2>Section ' . $i . '</h2>';
			}
			$parts[] = ($i === (int) floor($count / 2)) ? '<p>ZZMIDDLEMARKERZZ</p>' : $paragraph;
		}

		return implode('', $parts);
	}

	/**
	 * Pull the article text out of the <article_data> block of a slug prompt.
	 *
	 * @param string $prompt Slug prompt.
	 * @return string
	 */
	private function extract_article_data($prompt) {
		$this->assertSame(1, preg_match('/<article_data>\n(.*)\n<\/article_data>/s', $prompt, $matches));

		return $matches[1];
	}
}
