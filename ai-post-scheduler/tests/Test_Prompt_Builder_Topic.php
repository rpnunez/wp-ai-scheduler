<?php
/**
 * Tests for AIPS_Prompt_Builder_Topic
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Topic extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option('aips_site_excluded_topics');
		parent::tearDown();
	}

	private function make_diversity_injector($block = '') {
		return new class( $block ) {
			private $block;
			public function __construct( $block ) {
				$this->block = $block;
			}
			public function build_created_topic_titles_block( $author ) {
				return $this->block;
			}
		};
	}

	public function test_build_includes_already_created_topic_titles_block() {
		$builder = new AIPS_Prompt_Builder_Topic(
			new AIPS_Prompt_Builder(),
			$this->make_diversity_injector("Avoid these already-created topic titles or very close variations:\n- Existing Topic")
		);
		$author = (object) array(
			'id'                        => 5,
			'name'                      => 'Author',
			'field_niche'               => 'WordPress',
			'topic_generation_quantity' => 3,
			'keywords'                  => '',
			'details'                   => '',
			'voice_tone'                => '',
			'writing_style'             => '',
			'excluded_topics'           => '',
			'topic_generation_prompt'   => '',
			'preferred_content_length'  => '',
			'language'                  => 'en',
		);

		$prompt = $builder->build($author);

		$this->assertStringContainsString('Avoid these already-created topic titles or very close variations:', $prompt);
		$this->assertStringContainsString('- Existing Topic', $prompt);
	}

	/**
	 * Length and exclusions use the same canonical values as post prompts.
	 */
	public function test_build_uses_shared_length_mapping_and_merged_exclusions() {
		update_option('aips_site_excluded_topics', 'Competitor products');
		$builder = new AIPS_Prompt_Builder_Topic(
			new AIPS_Prompt_Builder(),
			$this->make_diversity_injector('')
		);
		$author = (object) array(
			'id'                        => 5,
			'name'                      => 'Author',
			'field_niche'               => 'WordPress',
			'topic_generation_quantity' => 3,
			'keywords'                  => '',
			'details'                   => '',
			'voice_tone'                => '',
			'writing_style'             => '',
			'excluded_topics'           => 'Unverified claims',
			'topic_generation_prompt'   => '',
			'preferred_content_length'  => 'long',
			'language'                  => 'en',
		);

		$prompt = $builder->build($author);

		$this->assertStringContainsString('Preferred post length: 1,500+ words', $prompt);
		$this->assertStringContainsString('Topics to avoid: Competitor products, Unverified claims', $prompt);
	}
}
