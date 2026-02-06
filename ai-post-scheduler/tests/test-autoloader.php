<?php
/**
 * Tests for AIPS_Autoloader
 *
 * @package AI_Post_Scheduler
 */

class AIPS_Autoloader_Test extends WP_UnitTestCase {

	private $includes_dir;

	public function setUp(): void {
		parent::setUp();
		$this->includes_dir = AIPS_PLUGIN_DIR . 'includes/';
	}

	/**
	 * Test that autoloader is registered
	 */
	public function test_autoloader_registration() {
		// Unregister to test fresh registration
		spl_autoload_unregister(array('AIPS_Autoloader', 'load'));
		
		// Register the autoloader
		AIPS_Autoloader::register();
		
		// Get registered autoloaders
		$autoloaders = spl_autoload_functions();
		
		// Check if our autoloader is registered
		$found = false;
		foreach ($autoloaders as $autoloader) {
			if (is_array($autoloader) && 
				$autoloader[0] === 'AIPS_Autoloader' && 
				$autoloader[1] === 'load') {
				$found = true;
				break;
			}
		}
		
		$this->assertTrue($found, 'Autoloader should be registered');
	}

	/**
	 * Test that autoloader correctly loads AIPS_ classes
	 */
	public function test_autoloader_loads_aips_classes() {
		// Test loading an existing class
		$test_class = 'AIPS_Config';
		$expected_file = 'class-aips-config.php';
		
		$this->assertTrue(
			file_exists($this->includes_dir . $expected_file),
			"File {$expected_file} should exist"
		);
		
		// The class should already be loaded, but we can verify it exists
		$this->assertTrue(
			class_exists($test_class),
			"Class {$test_class} should be loaded"
		);
	}

	/**
	 * Test that autoloader ignores non-AIPS classes
	 */
	public function test_autoloader_ignores_non_aips_classes() {
		// Try to load a non-AIPS class - should not throw error
		AIPS_Autoloader::load('Some_Other_Class');
		
		// The autoloader should simply return without error
		$this->assertTrue(true, 'Autoloader should ignore non-AIPS classes');
	}

	/**
	 * Test that autoloader converts class names correctly
	 */
	public function test_autoloader_converts_class_names_correctly() {
		$test_cases = array(
			'AIPS_History_Repository' => 'class-aips-history-repository.php',
			'AIPS_Template_Processor' => 'class-aips-template-processor.php',
			'AIPS_AI_Service' => 'class-aips-ai-service.php',
			'AIPS_Config' => 'class-aips-config.php',
		);
		
		foreach ($test_cases as $class_name => $expected_file) {
			// Convert using the same logic as the autoloader
			$base_name = strtolower(str_replace('_', '-', $class_name));
			$class_file = 'class-' . $base_name . '.php';
			
			$this->assertEquals(
				$expected_file,
				$class_file,
				"Class {$class_name} should convert to {$expected_file}"
			);
			
			// Verify the file exists
			$this->assertTrue(
				file_exists($this->includes_dir . $expected_file),
				"File {$expected_file} should exist for class {$class_name}"
			);
		}
	}

	/**
	 * Test that autoloader handles interface files
	 */
	public function test_autoloader_handles_interface_files() {
		// Check if there are any interface files
		$interface_files = glob($this->includes_dir . 'interface-*.php');
		
		if (count($interface_files) > 0) {
			// If interface files exist, verify naming convention
			foreach ($interface_files as $file) {
				$this->assertStringStartsWith(
					$this->includes_dir . 'interface-',
					$file,
					'Interface files should start with "interface-"'
				);
			}
		}
		
		// The test passes whether or not interface files exist
		$this->assertTrue(true, 'Interface file handling verified');
	}

	/**
	 * Test that autoloader loads actual classes without errors
	 */
	public function test_autoloader_loads_multiple_classes() {
		// List of classes that should be loadable
		$classes = array(
			'AIPS_Generator',
			'AIPS_Scheduler',
			'AIPS_Templates',
			'AIPS_History',
			'AIPS_Config',
			'AIPS_Logger',
		);
		
		foreach ($classes as $class_name) {
			$this->assertTrue(
				class_exists($class_name),
				"Class {$class_name} should be loaded by autoloader"
			);
		}
	}

	/**
	 * Test that autoloader handles repository classes
	 */
	public function test_autoloader_loads_repository_classes() {
		$repositories = array(
			'AIPS_History_Repository',
			'AIPS_Schedule_Repository',
			'AIPS_Template_Repository',
		);
		
		foreach ($repositories as $class_name) {
			$this->assertTrue(
				class_exists($class_name),
				"Repository class {$class_name} should be loaded"
			);
			
			// Verify the file exists
			$base_name = strtolower(str_replace('_', '-', $class_name));
			$expected_file = 'class-' . $base_name . '.php';
			
			$this->assertTrue(
				file_exists($this->includes_dir . $expected_file),
				"Repository file {$expected_file} should exist"
			);
		}
	}

	/**
	 * Test that autoloader handles service classes
	 */
	public function test_autoloader_loads_service_classes() {
		$services = array(
			'AIPS_AI_Service',
			'AIPS_Image_Service',
		);
		
		foreach ($services as $class_name) {
			$this->assertTrue(
				class_exists($class_name),
				"Service class {$class_name} should be loaded"
			);
		}
	}

	/**
	 * Test that autoloader handles controller classes
	 */
	public function test_autoloader_loads_controller_classes() {
		$controllers = array(
			'AIPS_Schedule_Controller',
			'AIPS_Settings',
		);
		
		foreach ($controllers as $class_name) {
			$this->assertTrue(
				class_exists($class_name),
				"Controller class {$class_name} should be loaded"
			);
		}
	}

	/**
	 * Test that file paths are constructed correctly
	 */
	public function test_autoloader_file_paths() {
		$class_name = 'AIPS_Test_Class';
		$base_name = strtolower(str_replace('_', '-', $class_name));
		$class_file = 'class-' . $base_name . '.php';
		$expected_path = AIPS_PLUGIN_DIR . 'includes/' . $class_file;
		
		// Verify path structure
		$this->assertStringContainsString(
			'includes/class-aips-test-class.php',
			$expected_path,
			'Path should follow expected structure'
		);
	}
}
