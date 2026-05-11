<?php
/**
 * Tests for AIPS_Prompt_Builder_Topic
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Topic extends WP_UnitTestCase {

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
}
