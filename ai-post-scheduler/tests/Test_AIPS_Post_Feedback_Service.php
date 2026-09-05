<?php

class Test_AIPS_Post_Feedback_Service extends WP_UnitTestCase {
	private $repository;

	public function set_up() {
		parent::set_up();
		$this->repository = new AIPS_Post_Feedback_Repository();
	}

	public function tear_down() {
		$this->repository->delete_all();
		parent::tear_down();
	}

	private function service_without_real_embeddings() {
		$indexer = new class {
			public function index_post($post_id) { return true; }
		};
		return new AIPS_Post_Feedback_Service($this->repository, new AIPS_History_Repository(), $indexer);
	}

	public function test_only_generated_posts_can_be_rated() {
		$post_id = self::factory()->post->create();
		$result = $this->service_without_real_embeddings()->record($post_id, 'liked', null, null, 1);
		$this->assertWPError($result);
		$this->assertSame('not_generated_post', $result->get_error_code());
	}

	public function test_like_with_optional_reason_and_comment_is_recorded() {
		$post_id = self::factory()->post->create(array(
			'post_title' => 'Generated title',
			'post_content' => 'Generated content',
		));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST, '1');

		$result = $this->service_without_real_embeddings()->record($post_id, 'liked', 'engagement', '<b>Strong hook</b>', 1);
		$this->assertIsArray($result);
		$this->assertSame('liked', $result['feedback']->reaction);
		$this->assertSame('engagement', $result['feedback']->reason_category);
		$this->assertSame('Strong hook', $result['feedback']->comment);
		$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['feedback']->content_hash);
		$this->assertSame('queued', $result['index_status']);
		$this->assertNotEmpty($result['feedback']->embedding_text);
		$this->assertNotFalse(wp_next_scheduled('aips_index_post_feedback_event', array($result['event_id'], 0)));
	}

	public function test_feedback_embedding_is_written_to_the_immutable_event_snapshot() {
		$post_id = self::factory()->post->create(array('post_title' => 'Snapshot', 'post_content' => 'Original body'));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST, '1');
		$embeddings = new class {
			public function generate_embedding($text) { return array(0.25, 0.75); }
		};
		$service = new AIPS_Post_Feedback_Service($this->repository, new AIPS_History_Repository(), $embeddings);
		$result = $service->record($post_id, 'liked', null, null, 1);
		wp_update_post(array('ID' => $post_id, 'post_content' => 'Edited body'));
		update_option('aips_post_feedback_enabled', 1);
		$service->process_index_event($result['event_id']);
		delete_option('aips_post_feedback_enabled');
		$event = $this->repository->get_by_id($result['event_id']);
		$this->assertStringContainsString('Original body', $event->embedding_text);
		$this->assertSame(array(0.25, 0.75), json_decode($event->embedding, true));
	}

	public function test_queued_embedding_obeys_global_master_switch() {
		$post_id = self::factory()->post->create(array('post_content' => 'Body'));
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST, '1');
		$embeddings = new class {
			public $calls = 0;
			public function generate_embedding($text) { $this->calls++; return array(1); }
		};
		$service = new AIPS_Post_Feedback_Service($this->repository, new AIPS_History_Repository(), $embeddings);
		$result = $service->record($post_id, 'liked', null, null, 1);
		update_option('aips_post_feedback_enabled', 0);
		$service->process_index_event($result['event_id']);
		$this->assertSame(0, $embeddings->calls);
		delete_option('aips_post_feedback_enabled');
	}

	public function test_clear_appends_audit_event_but_returns_no_current_feedback() {
		$post_id = self::factory()->post->create();
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST, '1');
		$service = $this->service_without_real_embeddings();
		$service->record($post_id, 'disliked', 'accuracy', '', 1);

		$result = $service->clear($post_id, 1);
		$this->assertNull($result['feedback']);
		$this->assertNull($service->get_current($post_id));
		$this->assertCount(2, $this->repository->get_history_for_post($post_id));
	}

	public function test_invalid_reason_is_rejected() {
		$post_id = self::factory()->post->create();
		update_post_meta($post_id, AIPS_Post_Manager::META_GENERATED_POST, '1');
		$result = $this->service_without_real_embeddings()->record($post_id, 'liked', 'made_up', '', 1);
		$this->assertWPError($result);
		$this->assertSame('invalid_feedback_reason', $result->get_error_code());
	}
}
