<?php
class Test_AIPS_Generated_Post_Feedback_UI extends WP_UnitTestCase {
	public function test_shared_controls_render_accessible_current_state() {
		$post_id = 42;
		$feedback = (object) array('reaction' => 'liked');
		ob_start(); include AIPS_PLUGIN_DIR . 'templates/partials/post-feedback-controls.php'; $html = ob_get_clean();
		$this->assertStringContainsString('data-post-id="42"', $html);
		$this->assertStringContainsString('data-reaction="liked"', $html);
		$this->assertStringContainsString('aria-pressed="true"', $html);
		$this->assertSame(2, substr_count($html, 'aria-hidden="true"'));
		$this->assertStringContainsString('role="group"', $html);
		$this->assertStringContainsString('Generated post feedback', $html);
		$this->assertStringContainsString('Like', $html);
		$this->assertStringContainsString('Dislike', $html);
		$this->assertStringNotContainsString('Approve', $html);
		$this->assertStringNotContainsString('Reject', $html);
	}
}
