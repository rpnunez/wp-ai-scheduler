<?php
/**
 * Tests for deterministic post quality auditing.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Post_Quality_Auditor extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $aips_test_meta;
		$aips_test_meta = array();
	}

	public function test_complete_post_scores_above_threshold() {
		$auditor = new AIPS_Post_Quality_Auditor();

		$result = $auditor->audit(array(
			'title' => 'A Practical Guide to Seasonal Content Planning',
			'content' => str_repeat('This section explains a useful editorial planning technique with concrete examples. ', 45),
			'excerpt' => 'A concise summary of the practical content planning advice.',
			'component_statuses' => array(
				'post_title' => true,
				'post_content' => true,
				'post_excerpt' => true,
				'featured_image' => true,
			),
			'image_attempted' => true,
			'focus_keyword' => 'seasonal content planning',
			'meta_description' => 'Plan better seasonal content with practical editorial guidance.',
		));

		$this->assertGreaterThanOrEqual(80, $result['score']);
		$this->assertFalse($result['has_critical_flags']);
		$this->assertSame(array(), $result['critical_flags']);
	}

	public function test_missing_title_and_content_create_critical_flags() {
		$auditor = new AIPS_Post_Quality_Auditor();

		$result = $auditor->audit(array(
			'title' => '',
			'content' => '',
			'excerpt' => '',
			'component_statuses' => array(
				'post_title' => false,
				'post_content' => false,
				'post_excerpt' => false,
				'featured_image' => true,
			),
			'image_attempted' => false,
		));

		$this->assertLessThan(80, $result['score']);
		$this->assertTrue($result['has_critical_flags']);
		$this->assertContains('missing_title', $result['critical_flags']);
		$this->assertContains('missing_content', $result['critical_flags']);
	}

	public function test_partial_component_status_reduces_score_and_flags_post() {
		$auditor = new AIPS_Post_Quality_Auditor();

		$result = $auditor->audit(array(
			'title' => 'Generated Post With Missing Image',
			'content' => str_repeat('This paragraph gives the generated post enough body copy for the audit. ', 40),
			'excerpt' => 'A generated excerpt.',
			'component_statuses' => array(
				'post_title' => true,
				'post_content' => true,
				'post_excerpt' => true,
				'featured_image' => false,
			),
			'image_attempted' => true,
			'generation_incomplete' => true,
		));

		$this->assertLessThan(100, $result['score']);
		$this->assertTrue($result['has_critical_flags']);
		$this->assertContains('partial_generation', $result['critical_flags']);
		$this->assertContains('missing_featured_image', $result['flags']);
	}
}
