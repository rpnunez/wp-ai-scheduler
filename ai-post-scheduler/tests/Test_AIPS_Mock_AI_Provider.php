<?php
/**
 * Test AIPS_Mock_AI_Provider and AI Provider Factory integration.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Mock_AI_Provider extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AIPS_Mock_AI_Provider::reset();
		AIPS_AI_Provider_Factory::reset_cache();
	}

	protected function tearDown(): void {
		AIPS_Mock_AI_Provider::reset();
		AIPS_AI_Provider_Factory::reset_cache();
		parent::tearDown();
	}

	public function test_mock_provider_is_registered_and_available() {
		$provider = AIPS_AI_Provider_Factory::create('mock');
		$this->assertInstanceOf(AIPS_Mock_AI_Provider::class, $provider);
		$this->assertSame('mock', $provider->get_id());
		$this->assertTrue($provider->is_available());
		$this->assertTrue($provider->supports_native_json());
		$this->assertTrue($provider->supports_embeddings());
	}

	public function test_generate_text_returns_content() {
		$provider = new AIPS_Mock_AI_Provider();
		$text = $provider->generate_text('Write a post about automated scheduling', array());
		$this->assertNotEmpty($text);
		$this->assertStringContainsString('Introduction', $text);
	}

	public function test_custom_response_matching() {
		$provider = new AIPS_Mock_AI_Provider();
		AIPS_Mock_AI_Provider::set_custom_response('special_keyword', 'Expected Custom Output');

		$result = $provider->generate_text('Please process special_keyword here', array());
		$this->assertSame('Expected Custom Output', $result);
	}

	public function test_forced_error_simulation() {
		$provider = new AIPS_Mock_AI_Provider();
		AIPS_Mock_AI_Provider::force_error('rate_limit');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Mock AI Error: rate_limit');

		$provider->generate_text('Any prompt', array());
	}

	public function test_generate_embedding_deterministic() {
		$provider = new AIPS_Mock_AI_Provider();
		$vec1 = $provider->generate_embedding('WordPress Automation', array());
		$vec2 = $provider->generate_embedding('WordPress Automation', array());

		$this->assertCount(64, $vec1);
		$this->assertSame($vec1, $vec2);
	}
}
