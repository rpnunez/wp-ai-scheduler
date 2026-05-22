<?php
/**
 * Tests for AI backend selection and provider-specific service behavior.
 *
 * @package AI_Post_Scheduler
 */

if (!function_exists('wp_get_wp_version')) {
	function wp_get_wp_version() {
		return isset($GLOBALS['aips_test_wp_version']) ? $GLOBALS['aips_test_wp_version'] : '6.9';
	}
}

if (!function_exists('wp_ai_client_prompt')) {
	function wp_ai_client_prompt($prompt = null) {
		$builder = isset($GLOBALS['aips_test_wp_ai_prompt_builder']) ? $GLOBALS['aips_test_wp_ai_prompt_builder'] : null;

		if ($builder && method_exists($builder, 'with_text') && $prompt !== null) {
			$builder->with_text($prompt);
		}

		return $builder;
	}
}

class Test_AIPS_AI_Backend_Factory extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		$GLOBALS['aips_test_wp_version'] = '6.9';
		$GLOBALS['aips_test_wp_ai_prompt_builder'] = null;
		delete_option('aips_ai_backend');
		AIPS_Config::get_instance()->flush_option_cache();
		remove_all_filters('aips_wordpress_ai_client_available');
		remove_all_filters('aips_meow_ai_engine_available');
	}

	public function tearDown(): void {
		delete_option('aips_ai_backend');
		unset($GLOBALS['aips_test_wp_version'], $GLOBALS['aips_test_wp_ai_prompt_builder']);
		AIPS_Config::get_instance()->flush_option_cache();
		remove_all_filters('aips_wordpress_ai_client_available');
		remove_all_filters('aips_meow_ai_engine_available');
		parent::tearDown();
	}

	public function test_default_backend_prefers_wordpress_ai_client_on_wordpress_7_when_available() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});
		add_filter('aips_meow_ai_engine_available', function() {
			return false;
		});

		$this->assertSame('wordpress_ai_client', AIPS_AI_Service_Factory::get_default_backend());
	}

	public function test_selected_backend_falls_back_to_wordpress_ai_client_when_meow_is_unavailable() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		update_option('aips_ai_backend', 'meow_ai_engine');
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});
		add_filter('aips_meow_ai_engine_available', function() {
			return false;
		});

		$this->assertSame('wordpress_ai_client', AIPS_AI_Service_Factory::get_selected_backend());
	}

	public function test_create_service_returns_wordpress_ai_client_service_for_wordpress_backend() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		update_option('aips_ai_backend', 'wordpress_ai_client');
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});

		$service = AIPS_AI_Service_Factory::create_service();

		$this->assertInstanceOf('AIPS_WordPress_AI_Client_Service', $service);
	}

	public function test_create_service_returns_meow_apps_service_for_meow_backend() {
		update_option('aips_ai_backend', 'meow_ai_engine');
		add_filter('aips_wordpress_ai_client_available', function() {
			return false;
		});
		add_filter('aips_meow_ai_engine_available', function() {
			return true;
		});

		$service = AIPS_AI_Service_Factory::create_service();

		$this->assertInstanceOf('AIPS_Meow_Apps_AI_Service', $service);
	}

	public function test_wordpress_ai_client_service_generate_json_decodes_response() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});
		$GLOBALS['aips_test_wp_ai_prompt_builder'] = new AIPS_Test_WP_AI_Client_Prompt_Builder(array(
			'json_response' => '[{"title":"Topic 1"},{"title":"Topic 2"}]',
		));

		$service = new AIPS_WordPress_AI_Client_Service();
		$result  = $service->generate_json('List topics');

		$this->assertIsArray($result);
		$this->assertSame('Topic 1', $result[0]['title']);
		$this->assertSame('Topic 2', $result[1]['title']);
	}

	public function test_wordpress_ai_client_service_generate_image_returns_data_uri() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});
		$GLOBALS['aips_test_wp_ai_prompt_builder'] = new AIPS_Test_WP_AI_Client_Prompt_Builder(array(
			'image_response' => new AIPS_Test_WP_AI_Client_File('data:image/png;base64,Zm9v'),
		));

		$service = new AIPS_WordPress_AI_Client_Service();
		$result  = $service->generate_image('Draw something');

		$this->assertSame('data:image/png;base64,Zm9v', $result);
	}

	public function test_ai_backend_field_disables_meow_option_when_meow_is_unavailable() {
		$GLOBALS['aips_test_wp_version'] = '7.0';
		add_filter('aips_wordpress_ai_client_available', function() {
			return true;
		});
		add_filter('aips_meow_ai_engine_available', function() {
			return false;
		});

		$ui = new AIPS_Settings_UI();

		ob_start();
		$ui->ai_backend_field_callback();
		$html = ob_get_clean();

		$this->assertStringContainsString('value="meow_ai_engine"', $html);
		$this->assertStringContainsString('disabled="disabled"', $html);
	}
}

class AIPS_Test_WP_AI_Client_Prompt_Builder {

	private $text;
	private $json_schema;
	private $text_response;
	private $json_response;
	private $image_response;

	public function __construct($args = array()) {
		$this->text_response  = isset($args['text_response']) ? $args['text_response'] : 'Generated text';
		$this->json_response  = isset($args['json_response']) ? $args['json_response'] : '[]';
		$this->image_response = isset($args['image_response']) ? $args['image_response'] : new AIPS_Test_WP_AI_Client_File('data:image/png;base64,');
	}

	public function with_text($text) {
		$this->text = $text;
		return $this;
	}

	public function using_temperature($temperature) {
		return $this;
	}

	public function using_model_preference(...$models) {
		return $this;
	}

	public function as_json_response($schema) {
		$this->json_schema = $schema;
		return $this;
	}

	public function is_supported_for_text_generation() {
		return true;
	}

	public function is_supported_for_image_generation() {
		return true;
	}

	public function generate_text() {
		if ($this->json_schema !== null) {
			return $this->json_response;
		}

		return $this->text_response;
	}

	public function generate_image() {
		return $this->image_response;
	}
}

class AIPS_Test_WP_AI_Client_File {

	private $data_uri;

	public function __construct($data_uri) {
		$this->data_uri = $data_uri;
	}

	public function getDataUri() {
		return $this->data_uri;
	}
}
