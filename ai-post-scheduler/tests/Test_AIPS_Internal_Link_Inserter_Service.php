<?php
/**
 * Tests for internal link inserter replacement handling.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Internal_Link_Inserter_Service extends WP_UnitTestCase {

	/**
	 * The marked phrase itself should become the inserted anchor text.
	 */
	public function test_build_replacement_html_uses_marked_phrase_as_link_text() {
		$service    = new AIPS_Internal_Link_Inserter_Service();
		$reflection = new ReflectionMethod($service, 'build_replacement_html');
		$reflection->setAccessible(true);

		$result = $reflection->invokeArgs(
			$service,
			array(
				'explore crucial web server configuration adjustments, and solidify',
				'explore crucial [[web server configuration adjustments]], and solidify',
				'https://example.com/apache-security-hardening',
			)
		);

		$this->assertSame(
			'explore crucial <a href="https://example.com/apache-security-hardening">web server configuration adjustments</a>, and solidify',
			$result
		);
	}

	/**
	 * Replacement snippets that alter text outside the marked phrase should be rejected.
	 */
	public function test_build_replacement_html_rejects_text_changes_outside_marker() {
		$service    = new AIPS_Internal_Link_Inserter_Service();
		$reflection = new ReflectionMethod($service, 'build_replacement_html');
		$reflection->setAccessible(true);

		$result = $reflection->invokeArgs(
			$service,
			array(
				'explore crucial web server configuration adjustments, and solidify',
				'explore crucial [[Apache Security Hardening and Performance Tuning]], and solidify',
				'https://example.com/apache-security-hardening',
			)
		);

		$this->assertWPError($result);
		$this->assertSame('invalid_replacement_snippet', $result->get_error_code());
	}

	/**
	 * Invalid AI locations should be filtered out when they rewrite the source text.
	 */
	public function test_validate_locations_rejects_rewritten_snippets() {
		$service    = new AIPS_Internal_Link_Inserter_Service();
		$reflection = new ReflectionMethod($service, 'validate_locations');
		$reflection->setAccessible(true);

		$result = $reflection->invokeArgs(
			$service,
			array(
				array(
					array(
						'reason'              => 'Bad rewrite',
						'match_snippet'       => 'focused on stability and adding support',
						'replacement_snippet' => 'focused on [[Apache Security Hardening and Performance Tuning]] and adding support',
					),
					array(
						'reason'              => 'Valid phrase',
						'match_snippet'       => 'focused on stability and adding support',
						'replacement_snippet' => 'focused on [[stability]] and adding support',
					),
				)
			)
		);

		$this->assertCount(1, $result);
		$this->assertSame('focused on [[stability]] and adding support', $result[0]['replacement_snippet']);
	}

	/**
	 * The public insertion-locations response should preserve both AI and valid counts.
	 */
	public function test_find_insertion_locations_reports_raw_and_valid_counts() {
		global $test_posts;

		$links_repo = $this->createMock(AIPS_Internal_Links_Repository::class);
		$ai_service = $this->getMockBuilder(AIPS_AI_Service::class)
			->disableOriginalConstructor()
			->onlyMethods(array('generate_json_from_text'))
			->getMock();
		$logger     = $this->createMock(AIPS_Logger::class);

		$test_posts = array(
			101 => (object) array(
				'ID'           => 101,
				'post_title'   => 'Source Post',
				'post_content' => 'Explore crucial web server configuration adjustments, and solidify production-ready best practices.',
			),
			202 => (object) array(
				'ID'         => 202,
				'post_title' => 'Apache Security Hardening and Performance Tuning',
			),
		);

		$links_repo->method('get_by_id')->willReturn((object) array(
			'id'             => 7,
			'source_post_id' => 101,
			'target_post_id' => 202,
			'anchor_text'    => '',
		));

		$ai_service->method('generate_json_from_text')->willReturn(array(
			array(
				'reason'              => 'Bad rewrite',
				'match_snippet'       => 'Explore crucial web server configuration adjustments, and',
				'replacement_snippet' => 'Explore crucial [[Apache Security Hardening and Performance Tuning]], and',
			),
			array(
				'reason'              => 'Valid phrase',
				'match_snippet'       => 'Explore crucial web server configuration adjustments, and',
				'replacement_snippet' => 'Explore crucial [[web server configuration adjustments]], and',
			),
		));

		$service = new AIPS_Internal_Link_Inserter_Service($links_repo, $ai_service, $logger);
		$result  = $service->find_insertion_locations(7);

		$this->assertIsArray($result);
		$this->assertSame(2, $result['raw_count']);
		$this->assertSame(1, $result['valid_count']);
		$this->assertCount(1, $result['locations']);

		unset($test_posts);
	}
}