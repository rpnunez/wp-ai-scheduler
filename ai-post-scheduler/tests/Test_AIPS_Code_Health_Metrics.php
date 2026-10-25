<?php
/**
 * Automated guard tests enforcing code health metrics and architectural boundaries.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Code_Health_Metrics extends WP_UnitTestCase {

	/**
	 * Path to the plugin includes directory.
	 *
	 * @var string
	 */
	private $includes_dir;

	public function setUp(): void {
		parent::setUp();
		$this->includes_dir = dirname(__DIR__) . '/includes';
	}

	/**
	 * Guard: Assert zero occurrences of direct `new AIPS_History_Repository`
	 * and `new AIPS_History_Service` in `includes/`.
	 */
	public function test_zero_direct_history_instantiations_in_includes() {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->includes_dir, FilesystemIterator::SKIP_DOTS)
		);

		$violations = array();

		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$path    = $file->getPathname();
			$content = file_get_contents($path);

			if (preg_match_all('/\bnew\s+AIPS_History_Repository\b/', $content, $matches, PREG_OFFSET_CAPTURE)) {
				foreach ($matches[0] as $match) {
					$violations[] = basename($path) . ': direct `new AIPS_History_Repository` at offset ' . $match[1];
				}
			}

			if (preg_match_all('/\bnew\s+AIPS_History_Service\b/', $content, $matches, PREG_OFFSET_CAPTURE)) {
				foreach ($matches[0] as $match) {
					$violations[] = basename($path) . ': direct `new AIPS_History_Service` at offset ' . $match[1];
				}
			}
		}

		$this->assertEmpty(
			$violations,
			"Code Health Violation: Direct History instantiation found in includes/:\n" . implode("\n", $violations)
		);
	}

	/**
	 * Guard: Assert all AJAX actions in AIPS_Ajax_Registry map to valid controller classes and methods.
	 */
	public function test_ajax_registry_controller_parity() {
		$this->assertTrue(class_exists('AIPS_Ajax_Registry'), 'AIPS_Ajax_Registry must exist');

		$history_actions = array(
			'aips_bulk_delete_history',
			'aips_clear_history',
			'aips_export_history',
			'aips_get_history_details',
			'aips_get_history_logs',
			'aips_get_history_modal_html',
			'aips_reload_history',
			'aips_retry_generation',
		);

		foreach ($history_actions as $action) {
			$this->assertTrue(AIPS_Ajax_Registry::has_action($action), "Action {$action} must be in AIPS_Ajax_Registry");
			$this->assertSame(
				'AIPS_History_Controller',
				AIPS_Ajax_Registry::get_controller_for($action),
				"Action {$action} must map to AIPS_History_Controller"
			);

			$method = str_replace('aips_', 'ajax_', $action);
			$this->assertTrue(
				method_exists('AIPS_History_Controller', $method),
				"AIPS_History_Controller must implement {$method}"
			);
		}
	}

	/**
	 * Guard: Assert that class-aips-history.php remains a lightweight facade (< 500 lines).
	 */
	public function test_modular_history_size() {
		$history_file = $this->includes_dir . '/class-aips-history.php';
		$this->assertFileExists($history_file);

		$line_count = count(file($history_file));
		$this->assertLessThan(
			500,
			$line_count,
			"AIPS_History should remain modularized under 500 lines (currently {$line_count} lines)."
		);
	}

	/**
	 * Guard: Assert that all four extracted seam classes exist and have public APIs.
	 */
	public function test_extracted_seam_classes_exist() {
		$classes = array(
			'AIPS_History_Stalled_Detector',
			'AIPS_Content_Normalizer',
			'AIPS_DB_Datetime_Migrator',
			'AIPS_Stress_Test_Data_Cleaner',
		);

		foreach ($classes as $class) {
			$this->assertTrue(class_exists($class), "Extracted seam class {$class} must exist");
		}
	}
}
