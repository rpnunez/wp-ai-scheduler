<?php
/**
 * Regression tests for schedule JS modal strategy.
 *
 * Ensures schedule flows in admin.js are legacy-modal only after the
 * de-wizardization refactor.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_JS_Schedule_Legacy_Only extends WP_UnitTestCase {

	/**
	 * Cached admin.js content.
	 *
	 * @var string
	 */
	private static $admin_js = '';

	/**
	 * Load admin.js content for each test.
	 */
	public function setUp(): void {
		parent::setUp();
		self::$admin_js = '';

		$candidates = array(
			dirname( __DIR__ ) . '/assets/js/admin.js',
			defined( 'AIPS_PLUGIN_DIR' ) ? AIPS_PLUGIN_DIR . 'assets/js/admin.js' : '',
			defined( 'AIPS_PLUGIN_DIR' ) ? rtrim( AIPS_PLUGIN_DIR, '/\\' ) . '/ai-post-scheduler/assets/js/admin.js' : '',
		);

		foreach ( $candidates as $file ) {
			if ( ! empty( $file ) && file_exists( $file ) ) {
				self::$admin_js = (string) file_get_contents( $file );
				break;
			}
		}
	}

	/**
	 * Guard: admin.js must be loadable in tests.
	 */
	public function test_admin_js_is_readable() {
		$this->assertNotSame( '', self::$admin_js, 'Expected assets/js/admin.js to be readable.' );
	}

	/**
	 * Schedule wizard selectors/helpers should no longer exist in admin.js.
	 */
	public function test_schedule_wizard_selectors_and_helpers_are_removed() {
		$this->assertStringNotContainsString( '#aips-schedule-wizard-modal', self::$admin_js );
		$this->assertStringNotContainsString( '#aips-schedule-wizard-form', self::$admin_js );
		$this->assertStringNotContainsString( 'SCHEDULE_WIZARD_REQUIRED_FIELDS', self::$admin_js );
		$this->assertStringNotContainsString( 'updateScheduleWizardSummary', self::$admin_js );
		$this->assertStringNotContainsString( '#sw_schedule_', self::$admin_js );
		$this->assertStringNotContainsString( '#sw_article_structure_id', self::$admin_js );
		$this->assertStringNotContainsString( '#sw_rotation_pattern', self::$admin_js );
	}

	/**
	 * Schedule flows should still rely on the legacy schedule modal/form hooks.
	 */
	public function test_legacy_schedule_modal_hooks_are_present() {
		$this->assertStringContainsString( "openScheduleModal: function(e)", self::$admin_js );
		$this->assertStringContainsString( "editSchedule: function(e)", self::$admin_js );
		$this->assertStringContainsString( "initScheduleAutoOpen: function()", self::$admin_js );

		$this->assertStringContainsString( "$('#aips-schedule-form')", self::$admin_js );
		$this->assertStringContainsString( "$('#aips-schedule-modal')", self::$admin_js );
		$this->assertStringContainsString( "$('#schedule_template')", self::$admin_js );
		$this->assertStringContainsString( "$('#article_structure_id')", self::$admin_js );
	}
}
