<?php
/**
 * Test class for AIPS_Unified_Schedule_Service
 */

class Test_AIPS_Unified_Schedule_Service extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Mock dependencies later if needed
	}

	public function test_dependencies_resolve_via_container() {
		$service = AIPS_Container::get_instance()->make(AIPS_Unified_Schedule_Service_Interface::class);
		$this->assertInstanceOf(AIPS_Unified_Schedule_Service::class, $service);
	}

}
