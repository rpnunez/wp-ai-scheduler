<?php

class Test_AIPS_Post_Feedback_Regeneration extends WP_UnitTestCase {

	public function test_lineage_marks_new_post_without_copying_feedback_state() {
		$old_post_id = self::factory()->post->create();
		$new_post_id = self::factory()->post->create();

		update_post_meta($old_post_id, '_aips_generated_post_feedback', 'liked');

		AIPS_Bulk_Generator_Service::record_regeneration_lineage($new_post_id, $old_post_id);

		$this->assertSame($old_post_id, (int) get_post_meta($new_post_id, '_aips_predecessor_post_id', true));
		$this->assertSame('', get_post_meta($new_post_id, '_aips_generated_post_feedback', true));
		$this->assertSame('liked', get_post_meta($old_post_id, '_aips_generated_post_feedback', true));
	}

	public function test_lineage_rejects_missing_or_self_referential_ids() {
		$post_id = self::factory()->post->create();

		$this->assertFalse(AIPS_Bulk_Generator_Service::record_regeneration_lineage(0, $post_id));
		$this->assertFalse(AIPS_Bulk_Generator_Service::record_regeneration_lineage($post_id, $post_id));
		$this->assertSame('', get_post_meta($post_id, '_aips_predecessor_post_id', true));
	}
}
