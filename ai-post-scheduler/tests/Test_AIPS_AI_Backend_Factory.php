<?php
/**
 * Tests for AI backend factory and facade delegation seams.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Test_Stub_AI_Backend_For_Factory implements AIPS_AI_Service_Interface {

	public $calls = array();

	public function is_available() {
		$this->calls[] = array( 'method' => 'is_available' );
		return true;
	}

	public function generate_text($prompt, $options = array()) {
		$this->calls[] = array(
			'method'  => 'generate_text',
			'prompt'  => $prompt,
			'options' => $options,
		);

		return 'stub-text';
	}

	public function generate_json($prompt, $options = array()) {
		$this->calls[] = array(
			'method'  => 'generate_json',
			'prompt'  => $prompt,
			'options' => $options,
		);

		return array( 'stub' => true );
	}

	public function generate_image($prompt, $options = array()) {
		$this->calls[] = array(
			'method'  => 'generate_image',
			'prompt'  => $prompt,
			'options' => $options,
		);

		return 'https://example.com/image.jpg';
	}

	public function get_call_log() {
		$this->calls[] = array( 'method' => 'get_call_log' );

		return array(
			array(
				'type'     => 'text',
				'response' => array( 'success' => true ),
			),
		);
	}

	public function clear_call_log() {
		$this->calls[] = array( 'method' => 'clear_call_log' );
	}

	public function get_call_statistics() {
		$this->calls[] = array( 'method' => 'get_call_statistics' );

		return array(
			'total'     => 1,
			'successes' => 1,
			'failures'  => 0,
			'by_type'   => array( 'text' => 1 ),
		);
	}

	public function reset_circuit_breaker() {
		$this->calls[] = array( 'method' => 'reset_circuit_breaker' );

		return true;
	}

	public function get_circuit_breaker_status() {
		$this->calls[] = array( 'method' => 'get_circuit_breaker_status' );

		return array( 'state' => 'closed' );
	}

	public function get_rate_limiter_status() {
		$this->calls[] = array( 'method' => 'get_rate_limiter_status' );

		return array( 'remaining' => 10 );
	}

	public function reset_rate_limiter() {
		$this->calls[] = array( 'method' => 'reset_rate_limiter' );

		return true;
	}
}

class Test_AIPS_AI_Backend_Factory extends WP_UnitTestCase {

	private function set_filtered_backend($stub) {
		add_filter(
			'aips_ai_backend_instance',
			function($backend, $backend_id, $args) use ($stub) {
				return $stub;
			},
			10,
			3
		);
	}

	public function tearDown(): void {
		remove_all_filters('aips_ai_backend');
		remove_all_filters('aips_ai_backend_instance');
		$reflection = new ReflectionClass(AIPS_AI_Service::class);
		$property   = $reflection->getProperty('instance');
		$property->setAccessible(true);
		$property->setValue(null, null);

		parent::tearDown();
	}

	public function test_factory_defaults_to_meow_backend() {
		$backend = AIPS_AI_Service_Factory::create();

		$this->assertInstanceOf(AIPS_AI_Service_Interface::class, $backend);
		$this->assertInstanceOf(AIPS_Meow_AI_Service::class, $backend);
	}

	public function test_factory_resolves_meow_backend_id_by_default() {
		$this->assertSame('meow', AIPS_AI_Service_Factory::get_backend_id());
	}

	public function test_factory_can_return_filtered_backend_instance() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$backend = AIPS_AI_Service_Factory::create(array( 'logger' => null ));

		$this->assertSame($stub, $backend);
	}

	public function test_meow_backend_implements_ai_service_interface() {
		$backend = new AIPS_Meow_AI_Service();

		$this->assertInstanceOf(AIPS_AI_Service_Interface::class, $backend);
	}

	public function test_ai_service_instance_uses_filtered_backend_instance() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = AIPS_AI_Service::instance();

		$this->assertInstanceOf(AIPS_AI_Service::class, $service);
		$this->assertTrue($service->is_available());
		$this->assertSame('is_available', $stub->calls[0]['method']);
	}

	public function test_ai_service_facade_delegates_generate_text() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = new AIPS_AI_Service();
		$result  = $service->generate_text('Prompt', array( 'temperature' => 0.3 ));

		$this->assertSame('stub-text', $result);
		$this->assertSame('generate_text', $stub->calls[0]['method']);
		$this->assertSame('Prompt', $stub->calls[0]['prompt']);
		$this->assertSame(0.3, $stub->calls[0]['options']['temperature']);
	}

	public function test_ai_service_facade_preserves_get_call_log() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = new AIPS_AI_Service();
		$log     = $service->get_call_log();

		$this->assertCount(1, $log);
		$this->assertSame('get_call_log', $stub->calls[0]['method']);
	}

	public function test_ai_service_facade_delegates_generate_json() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = new AIPS_AI_Service();
		$result  = $service->generate_json('Prompt', array( 'format' => 'json' ));

		$this->assertSame(array( 'stub' => true ), $result);
		$this->assertSame('generate_json', $stub->calls[0]['method']);
		$this->assertSame('Prompt', $stub->calls[0]['prompt']);
		$this->assertSame('json', $stub->calls[0]['options']['format']);
	}

	public function test_ai_service_facade_delegates_generate_image() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = new AIPS_AI_Service();
		$result  = $service->generate_image('Prompt', array( 'size' => 'large' ));

		$this->assertSame('https://example.com/image.jpg', $result);
		$this->assertSame('generate_image', $stub->calls[0]['method']);
		$this->assertSame('Prompt', $stub->calls[0]['prompt']);
		$this->assertSame('large', $stub->calls[0]['options']['size']);
	}

	public function test_ai_service_facade_delegates_clear_call_log() {
		$stub = new AIPS_Test_Stub_AI_Backend_For_Factory();

		$this->set_filtered_backend($stub);

		$service = new AIPS_AI_Service();
		$service->clear_call_log();

		$this->assertSame('clear_call_log', $stub->calls[0]['method']);
	}
}
