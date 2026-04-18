<?php
/**
 * Tests for AIPS_Admin_Assets localization (L10n) scoping.
 *
 * Verifies that `aipsAdminL10n` contains only the shared strings that are
 * needed on every plugin page, and that page-specific strings are pushed
 * exclusively via their own page-scoped objects (e.g. `aipsTemplatesL10n`,
 * `aipsStructuresL10n`).
 *
 * These tests exercise the PHP side of Phase F.1 ("Split aipsAdminL10n
 * into page-scoped objects").
 *
 * @package AI_Post_Scheduler
 * @since   2.3.3
 */

// Stub WordPress enqueueing/localisation functions that are not available in
// the limited test environment so that enqueue_admin_assets() can be called
// without a full WordPress installation.

if ( ! function_exists( 'wp_enqueue_media' ) ) {
	function wp_enqueue_media( $args = array() ) {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
}

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		return 'http://example.com' . $path;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * Stub: captures calls in $GLOBALS['aips_test_l10n_captures'] keyed by object name.
	 *
	 * Multiple calls with the same $object_name are merged so that the test
	 * assertions can check the final merged state, matching how the real
	 * WordPress function merges repeated wp_localize_script() calls.
	 */
	function wp_localize_script( $handle, $object_name, $l10n ) {
		if ( ! isset( $GLOBALS['aips_test_l10n_captures'] ) ) {
			$GLOBALS['aips_test_l10n_captures'] = array();
		}
		if ( ! isset( $GLOBALS['aips_test_l10n_captures'][ $object_name ] ) ) {
			$GLOBALS['aips_test_l10n_captures'][ $object_name ] = array();
		}
		$GLOBALS['aips_test_l10n_captures'][ $object_name ] = array_merge(
			$GLOBALS['aips_test_l10n_captures'][ $object_name ],
			$l10n
		);
		return true;
	}
}

// Stub AIPS_Admin_Menu_Helper::get_page_url() if not already available.
if ( ! class_exists( 'AIPS_Admin_Menu_Helper' ) ) {
	class AIPS_Admin_Menu_Helper {
		public static function get_page_url( $page ) {
			return 'http://example.com/wp-admin/admin.php?page=aips-' . $page;
		}
	}
}

// Stub AIPS_Config so enqueue_admin_assets() can call AIPS_Config::get_instance().
if ( ! class_exists( 'AIPS_Config' ) ) {
	class AIPS_Config {
		private static $instance = null;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function get_option( $key, $default = null ) {
			return $default;
		}
	}
}

/**
 * Tests for AIPS_Admin_Assets localization scoping.
 */
class Test_AIPS_Admin_Assets_L10n extends WP_UnitTestCase {

	/**
	 * @var AIPS_Admin_Assets
	 */
	private $assets;

	/**
	 * Strings that must appear in aipsAdminL10n on every plugin page.
	 *
	 * @var array
	 */
	private $shared_keys = array(
		'errorOccurred',
		'errorTryAgain',
		'confirmCancelButton',
		'confirmDeleteButton',
		'saving',
		'generating',
		'generationFailed',
	);

	/**
	 * Strings that were formerly in aipsAdminL10n but have been moved to
	 * page-scoped objects.  They must NOT appear in aipsAdminL10n.
	 *
	 * @var array
	 */
	private $moved_keys = array(
		'activeLabel',
		'inactiveLabel',
		'defaultLabel',
		'noVoiceDefault',
		'noneOption',
	);

	public function setUp(): void {
		parent::setUp();
		$this->assets = new AIPS_Admin_Assets();
		$this->reset_captures();
	}

	public function tearDown(): void {
		$this->reset_captures();
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Clear the global capture array between tests.
	 */
	private function reset_captures() {
		$GLOBALS['aips_test_l10n_captures'] = array();
	}

	/**
	 * Run enqueue_admin_assets() for a given page hook and return the captured
	 * localization data.
	 *
	 * @param string $hook WordPress admin page hook, e.g. 'toplevel_page_aips-templates'.
	 * @return array Captured L10n objects keyed by JS object name.
	 */
	private function get_l10n_for_hook( $hook ) {
		$this->reset_captures();
		$this->assets->enqueue_admin_assets( $hook );
		return isset( $GLOBALS['aips_test_l10n_captures'] ) ? $GLOBALS['aips_test_l10n_captures'] : array();
	}

	// -------------------------------------------------------------------------
	// Shared aipsAdminL10n tests
	// -------------------------------------------------------------------------

	/**
	 * All shared keys must be present in aipsAdminL10n on every plugin page.
	 *
	 * @dataProvider plugin_page_hooks_provider
	 */
	public function test_shared_keys_present_on_every_plugin_page( $hook ) {
		$l10n = $this->get_l10n_for_hook( $hook );

		$this->assertArrayHasKey(
			'aipsAdminL10n',
			$l10n,
			"aipsAdminL10n must be localised on hook '{$hook}'."
		);

		foreach ( $this->shared_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$l10n['aipsAdminL10n'],
				"aipsAdminL10n['{$key}'] must be present on hook '{$hook}'."
			);
			$this->assertNotEmpty(
				$l10n['aipsAdminL10n'][ $key ],
				"aipsAdminL10n['{$key}'] must not be empty on hook '{$hook}'."
			);
		}
	}

	/**
	 * Page-specific keys must NOT appear in aipsAdminL10n on any page.
	 *
	 * @dataProvider plugin_page_hooks_provider
	 */
	public function test_moved_keys_absent_from_aips_admin_l10n( $hook ) {
		$l10n = $this->get_l10n_for_hook( $hook );

		if ( ! isset( $l10n['aipsAdminL10n'] ) ) {
			return; // Nothing to check if the object was not registered.
		}

		foreach ( $this->moved_keys as $key ) {
			$this->assertArrayNotHasKey(
				$key,
				$l10n['aipsAdminL10n'],
				"aipsAdminL10n['{$key}'] should have been moved to a page-scoped object and must not appear in the shared object (hook '{$hook}')."
			);
		}
	}

	// -------------------------------------------------------------------------
	// aipsTemplatesL10n — Templates page only
	// -------------------------------------------------------------------------

	/**
	 * aipsTemplatesL10n must contain noVoiceDefault and noneOption on the
	 * Templates page.
	 */
	public function test_templates_l10n_contains_voice_and_none_on_templates_page() {
		$l10n = $this->get_l10n_for_hook( 'toplevel_page_aips-templates' );

		$this->assertArrayHasKey(
			'aipsTemplatesL10n',
			$l10n,
			'aipsTemplatesL10n must be localised on the Templates page.'
		);

		$this->assertArrayHasKey(
			'noVoiceDefault',
			$l10n['aipsTemplatesL10n'],
			"aipsTemplatesL10n['noVoiceDefault'] must be present on the Templates page."
		);
		$this->assertNotEmpty( $l10n['aipsTemplatesL10n']['noVoiceDefault'] );

		$this->assertArrayHasKey(
			'noneOption',
			$l10n['aipsTemplatesL10n'],
			"aipsTemplatesL10n['noneOption'] must be present on the Templates page."
		);
		$this->assertNotEmpty( $l10n['aipsTemplatesL10n']['noneOption'] );
	}

	/**
	 * aipsTemplatesL10n must NOT be pushed on non-Templates pages.
	 *
	 * @dataProvider non_templates_page_hooks_provider
	 */
	public function test_templates_l10n_absent_on_non_templates_pages( $hook ) {
		$l10n = $this->get_l10n_for_hook( $hook );

		$this->assertArrayNotHasKey(
			'aipsTemplatesL10n',
			$l10n,
			"aipsTemplatesL10n must NOT be pushed on hook '{$hook}'."
		);
	}

	// -------------------------------------------------------------------------
	// aipsStructuresL10n — Structures page only
	// -------------------------------------------------------------------------

	/**
	 * aipsStructuresL10n must contain activeLabel, inactiveLabel, and
	 * defaultLabel on the Structures page.
	 */
	public function test_structures_l10n_contains_badge_labels_on_structures_page() {
		$l10n = $this->get_l10n_for_hook( 'toplevel_page_aips-structures' );

		$this->assertArrayHasKey(
			'aipsStructuresL10n',
			$l10n,
			'aipsStructuresL10n must be localised on the Structures page.'
		);

		foreach ( array( 'activeLabel', 'inactiveLabel', 'defaultLabel' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$l10n['aipsStructuresL10n'],
				"aipsStructuresL10n['{$key}'] must be present on the Structures page."
			);
			$this->assertNotEmpty( $l10n['aipsStructuresL10n'][ $key ] );
		}
	}

	/**
	 * aipsStructuresL10n must NOT be pushed on non-Structures pages.
	 *
	 * @dataProvider non_structures_page_hooks_provider
	 */
	public function test_structures_l10n_absent_on_non_structures_pages( $hook ) {
		$l10n = $this->get_l10n_for_hook( $hook );

		$this->assertArrayNotHasKey(
			'aipsStructuresL10n',
			$l10n,
			"aipsStructuresL10n must NOT be pushed on hook '{$hook}'."
		);
	}

	// -------------------------------------------------------------------------
	// Data providers
	// -------------------------------------------------------------------------

	/**
	 * Representative plugin page hooks used to verify shared-string assertions.
	 *
	 * Note: the dashboard uses the root plugin slug 'ai-post-scheduler' (not
	 * 'aips-dashboard') because it is registered as the top-level menu page
	 * under that slug. All other pages follow the 'aips-{page}' pattern.
	 */
	public function plugin_page_hooks_provider() {
		return array(
			// Dashboard uses the root plugin slug — not the 'aips-' prefix pattern.
			'dashboard'        => array( 'toplevel_page_ai-post-scheduler' ),
			'templates'        => array( 'toplevel_page_aips-templates' ),
			'voices'           => array( 'toplevel_page_aips-voices' ),
			'structures'       => array( 'toplevel_page_aips-structures' ),
			'schedule'         => array( 'toplevel_page_aips-schedule' ),
			'history'          => array( 'toplevel_page_aips-history' ),
			'settings'         => array( 'toplevel_page_aips-settings' ),
			'generated-posts'  => array( 'toplevel_page_aips-generated-posts' ),
			'research'         => array( 'toplevel_page_aips-research' ),
		);
	}

	/**
	 * Hooks for pages that are NOT the Templates page (for negative tests).
	 */
	public function non_templates_page_hooks_provider() {
		return array(
			'voices'          => array( 'toplevel_page_aips-voices' ),
			'structures'      => array( 'toplevel_page_aips-structures' ),
			'schedule'        => array( 'toplevel_page_aips-schedule' ),
			'history'         => array( 'toplevel_page_aips-history' ),
			'settings'        => array( 'toplevel_page_aips-settings' ),
		);
	}

	/**
	 * Hooks for pages that are NOT the Structures page (for negative tests).
	 */
	public function non_structures_page_hooks_provider() {
		return array(
			'templates'       => array( 'toplevel_page_aips-templates' ),
			'voices'          => array( 'toplevel_page_aips-voices' ),
			'schedule'        => array( 'toplevel_page_aips-schedule' ),
			'history'         => array( 'toplevel_page_aips-history' ),
			'settings'        => array( 'toplevel_page_aips-settings' ),
		);
	}
}
