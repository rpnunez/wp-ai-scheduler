<?php
/**
 * Tests for AIPS_Prompt_Builder_Diversity_Injector
 *
 * @package AI_Post_Scheduler
 */

class Test_Prompt_Builder_Diversity_Injector extends WP_UnitTestCase {

	public function test_build_avoid_titles_block_for_template_context_uses_history_titles() {
		$history_repository = new class {
			public function get_history($args = array()) {
				return array(
					'items' => array(
						(object) array( 'generated_title' => 'Existing Title One' ),
						(object) array( 'generated_title' => 'Existing Title Two' ),
						(object) array( 'generated_title' => 'Existing Title One' ),
						(object) array( 'generated_title' => '' ),
					),
				);
			}
		};
		$injector = new AIPS_Prompt_Builder_Diversity_Injector($history_repository, null);
		$template = (object) array(
			'id'              => 11,
			'name'            => 'Template',
			'prompt_template' => 'Write about {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, null, 'AI');

		$block = $injector->build_avoid_titles_block($context);

		$this->assertStringContainsString('Avoid these existing titles or very close variations:', $block);
		$this->assertStringContainsString('Existing Title One', $block);
		$this->assertStringContainsString('Existing Title Two', $block);
		$this->assertSame(1, substr_count($block, 'Existing Title One'));
	}

	public function test_build_avoid_titles_block_refreshes_between_calls() {
		$history_repository = new class {
			public $call_count = 0;
			public function get_history($args = array()) {
				$this->call_count++;

				if ($this->call_count === 1) {
					return array(
						'items' => array(
							(object) array( 'generated_title' => 'First Title' ),
						),
					);
				}

				return array(
					'items' => array(
						(object) array( 'generated_title' => 'Second Title' ),
						(object) array( 'generated_title' => 'First Title' ),
					),
				);
			}
		};
		$injector = new AIPS_Prompt_Builder_Diversity_Injector($history_repository, null);
		$template = (object) array(
			'id'              => 11,
			'name'            => 'Template',
			'prompt_template' => 'Write about {{topic}}.',
		);
		$context = new AIPS_Template_Context($template, null, 'AI');

		$first_block = $injector->build_avoid_titles_block($context);
		$second_block = $injector->build_avoid_titles_block($context);

		$this->assertStringContainsString('First Title', $first_block);
		$this->assertStringNotContainsString('Second Title', $first_block);
		$this->assertStringContainsString('Second Title', $second_block);
	}

	public function test_build_created_topic_titles_block_uses_existing_author_topics() {
		$topics_repository = new class {
			public function get_by_author($author_id, $status = null) {
				return array(
					(object) array( 'topic_title' => 'Topic One' ),
					(object) array( 'topic_title' => 'Topic Two' ),
					(object) array( 'topic_title' => 'Topic One' ),
				);
			}
		};
		$injector = new AIPS_Prompt_Builder_Diversity_Injector(null, $topics_repository);
		$author = (object) array(
			'id'   => 7,
			'name' => 'Author',
		);

		$block = $injector->build_created_topic_titles_block($author);

		$this->assertStringContainsString('Avoid these already-created topic titles or very close variations:', $block);
		$this->assertStringContainsString('Topic One', $block);
		$this->assertStringContainsString('Topic Two', $block);
		$this->assertSame(1, substr_count($block, 'Topic One'));
	}
}
