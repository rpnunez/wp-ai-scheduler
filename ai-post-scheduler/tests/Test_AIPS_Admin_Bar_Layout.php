<?php
/**
 * Test the AIPS_Admin_Bar node tree: unique ids, group order and background rows.
 *
 * Regression guard: a link node once reused the id of the links group, which made
 * WordPress overwrite the group and removed the whole quick-links grid.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Bar_Layout extends WP_UnitTestCase {

	/**
	 * @var AIPS_Admin_Bar
	 */
	private $admin_bar;

	public function setUp(): void {
		parent::setUp();
		AIPS_Cache_Factory::reset();
		$this->admin_bar = new AIPS_Admin_Bar();

		wp_set_current_user($this->factory->user->create(array('role' => 'administrator')));
	}

	public function tearDown(): void {
		AIPS_Cache_Factory::instance()->flush();
		AIPS_Cache_Factory::reset();
		parent::tearDown();
	}

	/**
	 * Build the toolbar into a simple recorder (same shape as WP_Admin_Bar's add_* API).
	 *
	 * @return object
	 */
	private function build_toolbar() {
		$bar = new class() {
			public $nodes  = array();
			public $groups = array();

			public function add_node($args) {
				$this->nodes[] = $args;
				return true;
			}

			public function add_group($args) {
				$this->groups[] = $args;
				return true;
			}
		};

		$this->admin_bar->add_toolbar_node($bar);

		return $bar;
	}

	public function test_node_and_group_ids_are_unique() {
		$bar = $this->build_toolbar();

		$ids = array_merge(
			wp_list_pluck($bar->nodes, 'id'),
			wp_list_pluck($bar->groups, 'id')
		);

		$this->assertSame($ids, array_values(array_unique($ids)), 'Every toolbar node/group id must be unique.');
	}

	public function test_links_group_has_nine_links() {
		$bar = $this->build_toolbar();

		$links = array_filter($bar->nodes, static function ($node) {
			return isset($node['parent']) && 'aips-toolbar-links' === $node['parent'];
		});

		$this->assertCount(9, $links);
		$this->assertContains('aips-toolbar-links', wp_list_pluck($bar->groups, 'id'));
	}

	public function test_groups_render_links_then_notifications_then_background() {
		$bar = $this->build_toolbar();

		$this->assertSame(
			array('aips-toolbar-links', 'aips-toolbar-notifications', 'aips-toolbar-bg'),
			wp_list_pluck($bar->groups, 'id')
		);
	}

	public function test_background_row_has_single_toggle_for_paused_process() {
		$method = new ReflectionMethod(AIPS_Admin_Bar::class, 'render_background_items');
		$method->setAccessible(true);

		$html = $method->invoke($this->admin_bar, array(
			array(
				'key'          => 'demo',
				'label'        => 'Demo',
				'status'       => 'paused',
				'status_label' => 'Paused',
				'can_pause'    => false,
				'processed'    => 14,
				'total'        => 578,
				'percent'      => 2,
			),
		));

		$this->assertStringContainsString('data-aips-bg-action="resume"', $html);
		$this->assertStringContainsString('dashicons-controls-play', $html);
		$this->assertStringNotContainsString('data-aips-bg-action="cancel"', $html, 'Stop is not offered in the toolbar.');
		$this->assertStringContainsString('Paused', $html);
	}

	public function test_background_row_shows_pause_and_playing_for_running_process() {
		$method = new ReflectionMethod(AIPS_Admin_Bar::class, 'render_background_items');
		$method->setAccessible(true);

		$html = $method->invoke($this->admin_bar, array(
			array(
				'key'          => 'demo',
				'label'        => 'Demo',
				'status'       => 'running',
				'status_label' => 'Running',
				'can_pause'    => true,
				'processed'    => 1,
				'total'        => 10,
				'percent'      => 10,
			),
		));

		$this->assertStringContainsString('data-aips-bg-action="pause"', $html);
		$this->assertStringContainsString('dashicons-controls-pause', $html);
		$this->assertStringContainsString('Playing', $html);
	}

	public function test_asset_version_includes_file_mtime() {
		$version = AIPS_Admin_Bar::asset_version('assets/css/admin-bar.css');

		$this->assertStringStartsWith(AIPS_VERSION . '.', $version);
	}
}
