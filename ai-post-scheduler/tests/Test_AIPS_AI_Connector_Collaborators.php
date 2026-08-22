<?php
/**
 * Tests for WP AI connector collaborator classes.
 *
 * @package AI_Post_Scheduler
 * @subpackage Tests
 */

class Test_AIPS_AI_Connector_Collaborators extends WP_UnitTestCase {

	public function tearDown(): void {
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'all');
		$config->set_option('aips_wp_ai_connector_ids', array());
		$config->set_option('aips_wp_ai_connector_failover', true);

		foreach (array('google', 'openai', 'anthropic', 'test_conn') as $connector_id) {
			delete_transient('aips_wp_ai_health_' . md5($connector_id));
		}

		parent::tearDown();
	}

	public function test_health_store_records_success_and_failure() {
		$health_store = new AIPS_AI_Connector_Health_Store();
		$connector_id = 'test_conn';

		$this->assertSame(array(), $health_store->get_health($connector_id));
		$this->assertFalse($health_store->is_cooling_down($connector_id));

		$health_store->record_failure($connector_id, 'invalid_api_key', 900);
		$health = $health_store->get_health($connector_id);

		$this->assertSame('cooling_down', $health['state']);
		$this->assertSame(1, $health['failures']);
		$this->assertSame('invalid_api_key', $health['last_error_code']);
		$this->assertTrue($health_store->is_cooling_down($connector_id));

		$health_store->record_success($connector_id);
		$health = $health_store->get_health($connector_id);

		$this->assertSame('healthy', $health['state']);
		$this->assertSame(0, $health['failures']);
		$this->assertFalse($health_store->is_cooling_down($connector_id));
	}

	public function test_health_store_cooldown_requires_two_failures_for_transient_errors() {
		$health_store = new AIPS_AI_Connector_Health_Store();
		$connector_id = 'test_conn';

		// 1 transient failure does not trigger cooldown.
		$health_store->record_failure($connector_id, 'prompt_network_error', 60);
		$this->assertFalse($health_store->is_cooling_down($connector_id));

		// 2nd transient failure enters cooldown.
		$health_store->record_failure($connector_id, 'prompt_network_error', 60);
		$this->assertTrue($health_store->is_cooling_down($connector_id));
	}

	public function test_failover_policy_classifies_errors() {
		$policy = new AIPS_AI_Failover_Policy();

		// Content policy and client errors should not fail over.
		$this->assertFalse($policy->should_fail_over('content_policy_violation'));
		$this->assertFalse($policy->should_fail_over('context_length_exceeded'));
		$this->assertFalse($policy->should_fail_over('invalid_request_error'));
		$this->assertFalse($policy->should_fail_over('json_query_unavailable'));

		// Network and server errors should fail over.
		$this->assertTrue($policy->should_fail_over('prompt_network_error'));
		$this->assertTrue($policy->should_fail_over('server_error'));

		// Special case: prompt_invalid_argument with missing models.
		$this->assertTrue($policy->should_fail_over('prompt_invalid_argument', 'No models found for provider google'));
		$this->assertFalse($policy->should_fail_over('prompt_invalid_argument', 'Other invalid argument'));

		// Cooldown durations.
		$this->assertTrue($policy->is_configuration_failure('invalid_api_key'));
		$this->assertSame(900, $policy->get_cooldown_seconds('invalid_api_key'));
		$this->assertFalse($policy->is_configuration_failure('prompt_network_error'));
		$this->assertSame(60, $policy->get_cooldown_seconds('prompt_network_error'));

		// Filterable cooldown.
		$custom_filter = function($cooldown, $code) {
			return $code === 'prompt_network_error' ? 120 : $cooldown;
		};
		add_filter('aips_wp_ai_connector_cooldown', $custom_filter, 10, 2);
		$this->assertSame(120, $policy->get_cooldown_seconds('prompt_network_error'));
		remove_filter('aips_wp_ai_connector_cooldown', $custom_filter, 10);
	}

	public function test_error_mapper_extracts_codes_and_wraps_errors() {
		$mapper = new AIPS_WP_AI_Error_Mapper();

		$this->assertSame('no_connector', $mapper->extract_error_code('no_connector: No allowed connector'));
		$this->assertSame('custom_err', $mapper->extract_error_code('custom_err: something broke'));

		$ex = $mapper->to_exception(new InvalidArgumentException('Bad input'));
		$this->assertInstanceOf(Exception::class, $ex);
		$this->assertSame('Bad input', $ex->getMessage());

		try {
			$mapper->throw_from_wp_error(new WP_Error('rate_limit', 'Too many requests'));
			$this->fail('Expected exception');
		} catch (Exception $e) {
			$this->assertSame('rate_limit: Too many requests', $e->getMessage());
			$this->assertSame('rate_limit', $mapper->extract_error_code($e->getMessage()));
		}
	}

	public function test_registry_and_router_candidate_ordering() {
		$config = AIPS_Config::get_instance();
		$config->set_option('aips_wp_ai_connector_mode', 'selected');
		$config->set_option('aips_wp_ai_connector_ids', array('openai', 'google'));
		$config->set_option('aips_wp_ai_connector_failover', true);

		$registry = new AIPS_AI_Connector_Registry($config);
		$health_store = new AIPS_AI_Connector_Health_Store($registry);
		$router = new AIPS_AI_Connector_Router($registry, $health_store, $config);

		add_filter('aips_wp_ai_client_connectors', function() {
			return array(
				'google'    => array('name' => 'Google', 'type' => 'ai_provider', 'authentication' => array('method' => 'none')),
				'openai'    => array('name' => 'OpenAI', 'type' => 'ai_provider', 'authentication' => array('method' => 'none')),
				'anthropic' => array('name' => 'Anthropic', 'type' => 'ai_provider', 'authentication' => array('method' => 'none')),
			);
		});

		$candidates = $router->get_routing_connector_ids();
		$this->assertSame(array('openai', 'google'), $candidates);

		// Cool down openai -> router should filter it out when failover is enabled.
		$health_store->record_failure('openai', 'invalid_api_key', 900);
		$candidates = $router->get_routing_connector_ids();
		$this->assertSame(array('google'), $candidates);
	}

	public function test_prompt_adapter_builds_system_instruction() {
		$adapter = new AIPS_WP_AI_Prompt_Adapter();

		$instruction = $adapter->build_system_instruction(array(
			'context'      => 'Style guide.',
			'instructions' => 'JSON only.',
		));
		$this->assertSame("Style guide.\n\nJSON only.", $instruction);

		$empty = $adapter->build_system_instruction(array('context' => '   ', 'instructions' => ''));
		$this->assertSame('', $empty);
	}
}
