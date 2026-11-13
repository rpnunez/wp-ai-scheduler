<?php
/**
 * Tests for AIPS_Prompt_Profile_Diff_Engine.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Prompt_Profile_Diff_Engine extends WP_UnitTestCase {

	/** @var AIPS_Prompt_Profile_Diff_Engine */
	private $engine;

	public function setUp(): void {
		parent::setUp();
		$this->engine = new AIPS_Prompt_Profile_Diff_Engine();
	}

	public function test_diff_strings_no_change_returns_single_equal_segment() {
		$segments = $this->engine->diff_strings('Write a great post about {{topic}}.', 'Write a great post about {{topic}}.');

		$this->assertCount(1, $segments);
		$this->assertSame('equal', $segments[0]['type']);
		$this->assertSame('Write a great post about {{topic}}.', $segments[0]['text']);
	}

	public function test_diff_strings_both_empty_returns_no_segments() {
		$segments = $this->engine->diff_strings('', '');
		$this->assertSame(array(), $segments);
	}

	public function test_diff_strings_pure_addition() {
		$segments = $this->engine->diff_strings('Write a post.', 'Write a great post.');

		$types = array_column($segments, 'type');
		$this->assertContains('add', $types);
		$this->assertNotContains('remove', $types);

		// Reassembling all segment text must reconstruct the new text exactly.
		$reassembled = implode('', array_map(function ($seg) {
			return $seg['type'] !== 'remove' ? $seg['text'] : '';
		}, $segments));
		$this->assertSame('Write a great post.', $reassembled);
	}

	public function test_diff_strings_pure_removal() {
		$segments = $this->engine->diff_strings('Write a great post.', 'Write a post.');

		$types = array_column($segments, 'type');
		$this->assertContains('remove', $types);
		$this->assertNotContains('add', $types);

		$reassembled = implode('', array_map(function ($seg) {
			return $seg['type'] !== 'add' ? $seg['text'] : '';
		}, $segments));
		$this->assertSame('Write a great post.', $reassembled);
	}

	public function test_diff_strings_mixed_change() {
		$old = 'Generate a short title about {{topic}} in an exciting tone.';
		$new = 'Generate a compelling headline about {{topic}} in an authoritative tone.';

		$segments = $this->engine->diff_strings($old, $new);

		$types = array_column($segments, 'type');
		$this->assertContains('add', $types);
		$this->assertContains('remove', $types);
		$this->assertContains('equal', $types);

		// The old text is fully recoverable from equal+remove segments, in order.
		$reassembled_old = implode('', array_map(function ($seg) {
			return $seg['type'] !== 'add' ? $seg['text'] : '';
		}, $segments));
		$this->assertSame($old, $reassembled_old);

		// The new text is fully recoverable from equal+add segments, in order.
		$reassembled_new = implode('', array_map(function ($seg) {
			return $seg['type'] !== 'remove' ? $seg['text'] : '';
		}, $segments));
		$this->assertSame($new, $reassembled_new);
	}

	public function test_diff_strings_preserves_whitespace_in_segments() {
		$segments = $this->engine->diff_strings("Line one.\nLine two.", "Line one.\nLine three.");

		$reassembled_new = implode('', array_map(function ($seg) {
			return $seg['type'] !== 'remove' ? $seg['text'] : '';
		}, $segments));
		$this->assertSame("Line one.\nLine three.", $reassembled_new);
	}

	public function test_diff_stages_marks_unchanged_stages_as_not_changed() {
		$old_stages = array(
			'title_prompt'            => 'Write a title about {{topic}}.',
			'title_followup_prompt'   => 'Refine the title.',
			'content_prompt'          => 'Write the content.',
			'excerpt_prompt'          => 'Write an excerpt.',
			'excerpt_followup_prompt' => 'Refine the excerpt.',
			'featured_image_prompt'   => 'Generate an image.',
			'topic_ideas_prompt'      => 'Suggest topics.',
			'metadata_prompt'         => 'Generate metadata.',
			'taxonomy_prompt'         => 'Classify taxonomy.',
		);

		$new_stages = $old_stages;
		$new_stages['title_prompt'] = 'Write a compelling title about {{topic}}.';
		$new_stages['content_prompt'] = 'Write the full content.';

		$result = $this->engine->diff_stages($old_stages, $new_stages);

		$this->assertTrue($result['title_prompt']['changed']);
		$this->assertNotEmpty($result['title_prompt']['segments']);

		$this->assertTrue($result['content_prompt']['changed']);
		$this->assertNotEmpty($result['content_prompt']['segments']);

		foreach (array('title_followup_prompt', 'excerpt_prompt', 'excerpt_followup_prompt', 'featured_image_prompt', 'topic_ideas_prompt', 'metadata_prompt', 'taxonomy_prompt') as $unchanged_key) {
			$this->assertFalse($result[$unchanged_key]['changed'], "Stage {$unchanged_key} should be marked unchanged.");
			$this->assertSame(array(), $result[$unchanged_key]['segments']);
		}

		// All 9 known stage keys must always be present in the result.
		$this->assertCount(9, $result);
	}

	public function test_diff_stages_treats_missing_keys_as_empty_strings() {
		$old_stages = array('title_prompt' => 'Hello world');
		$new_stages = array();

		$result = $this->engine->diff_stages($old_stages, $new_stages);

		$this->assertTrue($result['title_prompt']['changed']);
		$this->assertFalse($result['content_prompt']['changed']);
	}

	public function test_get_changed_stage_keys_returns_only_differing_keys() {
		$old_stages = array(
			'title_prompt'   => 'A',
			'content_prompt' => 'B',
		);
		$new_stages = array(
			'title_prompt'   => 'A',
			'content_prompt' => 'B changed',
		);

		$changed = $this->engine->get_changed_stage_keys($old_stages, $new_stages);

		$this->assertSame(array('content_prompt'), $changed);
	}
}
