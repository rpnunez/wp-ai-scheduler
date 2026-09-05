<?php
class Test_AIPS_Post_Feedback_Settings_UI extends WP_UnitTestCase {
	public function test_registers_master_and_all_weight_settings() {
		$settings = AIPS_Settings::get_registered_settings_args(new AIPS_Settings_UI());
		$expected = array('aips_post_feedback_enabled', 'aips_post_feedback_like_weight', 'aips_post_feedback_dislike_weight', 'aips_post_feedback_similarity_weight', 'aips_post_feedback_recency_weight', 'aips_post_feedback_author_match_weight', 'aips_post_feedback_template_match_weight', 'aips_post_feedback_global_pool_weight', 'aips_post_feedback_max_examples', 'aips_post_feedback_min_similarity', 'aips_post_feedback_min_samples', 'aips_post_feedback_prompt_budget_chars', 'aips_post_feedback_edited_content_weight');
		foreach ($expected as $key) { $this->assertArrayHasKey($key, $settings); }
	}
	public function test_scope_sanitizer_preserves_inheritance_and_sparse_values() {
		$this->assertNull(AIPS_Post_Feedback_Settings::sanitize_enabled('inherit'));
		$this->assertSame(1, AIPS_Post_Feedback_Settings::sanitize_enabled('enabled'));
		$this->assertSame(0, AIPS_Post_Feedback_Settings::sanitize_enabled('disabled'));
		$this->assertSame(array('like_weight' => 10.0, 'min_similarity' => 0.0), AIPS_Post_Feedback_Settings::sanitize_config(array('like_weight' => 20, 'min_similarity' => -1, 'unknown' => 2)));
	}
}
