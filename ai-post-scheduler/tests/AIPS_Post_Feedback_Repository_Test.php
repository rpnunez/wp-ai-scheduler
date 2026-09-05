<?php

class AIPS_Post_Feedback_Repository_Test extends WP_UnitTestCase {
	private $repository;
	private $post_id;

	public function set_up() {
		parent::set_up();
		$this->repository = new AIPS_Post_Feedback_Repository();
		$this->post_id = self::factory()->post->create(array('post_status' => 'draft'));
	}

	public function tear_down() {
		$this->repository->delete_all();
		parent::tear_down();
	}

	public function test_latest_event_is_current_and_history_is_append_only() {
		$liked = $this->repository->append_event(array(
			'post_id' => $this->post_id,
			'user_id' => 1,
			'reaction' => 'liked',
		));
		$disliked = $this->repository->append_event(array(
			'post_id' => $this->post_id,
			'user_id' => 1,
			'reaction' => 'disliked',
			'reason_category' => 'accuracy',
		));

		$this->assertIsInt($liked);
		$this->assertIsInt($disliked);
		$this->assertSame('disliked', $this->repository->get_current_for_post($this->post_id)->reaction);
		$this->assertCount(2, $this->repository->get_history_for_post($this->post_id));
	}

	public function test_current_many_returns_one_latest_row_per_post() {
		$other = self::factory()->post->create();
		$this->repository->append_event(array('post_id' => $this->post_id, 'user_id' => 1, 'reaction' => 'liked'));
		$this->repository->append_event(array('post_id' => $other, 'user_id' => 1, 'reaction' => 'disliked'));
		$this->repository->append_event(array('post_id' => $other, 'user_id' => 1, 'reaction' => 'cleared'));

		$current = $this->repository->get_current_for_posts(array($this->post_id, $other));
		$this->assertCount(2, $current);
		$this->assertSame('cleared', $current[$other]->reaction);
	}

	public function test_active_candidates_exclude_cleared_and_trashed_posts() {
		$this->repository->append_event(array('post_id' => $this->post_id, 'user_id' => 1, 'reaction' => 'liked'));
		$this->assertCount(1, $this->repository->get_active_candidates(array(), 10));

		$this->repository->append_event(array('post_id' => $this->post_id, 'user_id' => 1, 'reaction' => 'cleared'));
		$this->assertCount(0, $this->repository->get_active_candidates(array(), 10));
	}
}
