<?php
/**
 * Tests for AIPS_Config topic scoring configuration
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Config_Topic_Scoring_Test extends WP_UnitTestCase {
	
	private $config;
	
	public function setUp(): void {
		parent::setUp();
		$this->config = AIPS_Config::get_instance();
	}
	
	public function test_default_topic_scoring_base() {
		$defaults = $this->config->get_default_options();
		
		$this->assertArrayHasKey('aips_topic_scoring_base', $defaults);
		$this->assertEquals(50, $defaults['aips_topic_scoring_base']);
	}
	
	public function test_default_topic_scoring_alpha() {
		$defaults = $this->config->get_default_options();
		
		$this->assertArrayHasKey('aips_topic_scoring_alpha', $defaults);
		$this->assertEquals(10, $defaults['aips_topic_scoring_alpha']);
	}
	
	public function test_default_topic_scoring_beta() {
		$defaults = $this->config->get_default_options();
		
		$this->assertArrayHasKey('aips_topic_scoring_beta', $defaults);
		$this->assertEquals(15, $defaults['aips_topic_scoring_beta']);
	}
	
	public function test_default_topic_scoring_gamma() {
		$defaults = $this->config->get_default_options();
		
		$this->assertArrayHasKey('aips_topic_scoring_gamma', $defaults);
		$this->assertEquals(5, $defaults['aips_topic_scoring_gamma']);
	}
	
	public function test_default_scheduling_priority_bump() {
		$defaults = $this->config->get_default_options();
		
		$this->assertArrayHasKey('aips_topic_scheduling_priority_bump', $defaults);
		$this->assertEquals(3600, $defaults['aips_topic_scheduling_priority_bump']);
	}
	
	public function test_get_topic_scoring_config_returns_array() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertIsArray($scoring_config);
	}
	
	public function test_get_topic_scoring_config_has_base() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertArrayHasKey('base', $scoring_config);
		$this->assertIsFloat($scoring_config['base']);
	}
	
	public function test_get_topic_scoring_config_has_alpha() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertArrayHasKey('alpha', $scoring_config);
		$this->assertIsFloat($scoring_config['alpha']);
	}
	
	public function test_get_topic_scoring_config_has_beta() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertArrayHasKey('beta', $scoring_config);
		$this->assertIsFloat($scoring_config['beta']);
	}
	
	public function test_get_topic_scoring_config_has_gamma() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertArrayHasKey('gamma', $scoring_config);
		$this->assertIsFloat($scoring_config['gamma']);
	}
	
	public function test_get_topic_scoring_config_has_scheduling_priority_bump() {
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertArrayHasKey('scheduling_priority_bump', $scoring_config);
		$this->assertIsInt($scoring_config['scheduling_priority_bump']);
	}
	
	public function test_custom_topic_scoring_base() {
		update_option('aips_topic_scoring_base', 100);
		
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertEquals(100, $scoring_config['base']);
		
		// Cleanup
		delete_option('aips_topic_scoring_base');
	}
	
	public function test_custom_topic_scoring_alpha() {
		update_option('aips_topic_scoring_alpha', 20);
		
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertEquals(20, $scoring_config['alpha']);
		
		// Cleanup
		delete_option('aips_topic_scoring_alpha');
	}
	
	public function test_custom_topic_scoring_beta() {
		update_option('aips_topic_scoring_beta', 25);
		
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertEquals(25, $scoring_config['beta']);
		
		// Cleanup
		delete_option('aips_topic_scoring_beta');
	}
	
	public function test_custom_topic_scoring_gamma() {
		update_option('aips_topic_scoring_gamma', 10);
		
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertEquals(10, $scoring_config['gamma']);
		
		// Cleanup
		delete_option('aips_topic_scoring_gamma');
	}
	
	public function test_custom_scheduling_priority_bump() {
		update_option('aips_topic_scheduling_priority_bump', 7200);
		
		$scoring_config = $this->config->get_topic_scoring_config();
		
		$this->assertEquals(7200, $scoring_config['scheduling_priority_bump']);
		
		// Cleanup
		delete_option('aips_topic_scheduling_priority_bump');
	}
}
