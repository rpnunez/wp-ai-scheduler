<?php
/**
 * Tests for AIPS_History_Service helper normalization.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Service_Helpers extends WP_UnitTestCase {

	public function test_build_metadata_adds_type_fields() {
		$metadata = AIPS_History_Service::build_metadata(
			'post_review_action',
			array(
				'post_id' => 123,
				'user_id' => 77,
			)
		);

		$this->assertSame('post_review_action', $metadata['creation_method']);
		$this->assertSame('post_review_action', $metadata['history_type']);
		$this->assertSame(123, $metadata['post_id']);
		$this->assertSame(77, $metadata['user_id']);
	}

	public function test_normalize_event_context_defaults() {
		$event = AIPS_History_Service::normalize_event_context('', '', array());

		$this->assertSame('activity', $event['event_type']);
		$this->assertSame('success', $event['event_status']);
		$this->assertSame('activity', $event['context']['event_type']);
		$this->assertSame('success', $event['context']['event_status']);
	}

	public function test_normalize_event_context_uses_explicit_values() {
		$event = AIPS_History_Service::normalize_event_context(
			'topic_rejected',
			'failed',
			array(
				'topic_id' => 22,
			)
		);

		$this->assertSame('topic_rejected', $event['event_type']);
		$this->assertSame('failed', $event['event_status']);
		$this->assertSame(22, $event['context']['topic_id']);
	}
}

