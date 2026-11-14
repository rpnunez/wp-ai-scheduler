<?php
/**
 * Tests for shared admin flyout navigation.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Admin_Flyout_Navigation extends WP_UnitTestCase {

	/**
	 * Administrator ID used by payload tests.
	 *
	 * @var int
	 */
	private $admin_id;

	public function set_up() {
		parent::set_up();
		$this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
		wp_set_current_user($this->admin_id);
	}

	public function tear_down() {
		wp_set_current_user(0);
		parent::tear_down();
	}

	public function test_payload_contains_exact_flyout_hubs() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-studio', 'voices');

		$this->assertSame(
			array('automations', 'studio', 'research', 'content', 'diagnostics'),
			wp_list_pluck($payload, 'key')
		);
	}

	public function test_studio_contains_five_items_and_marks_voices_active() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-studio', 'voices');
		$studio  = $payload[1];
		$items   = $studio['groups'][0]['items'];

		$this->assertSame(
			array('templates', 'voices', 'structures', 'structure-sections', 'post-slices'),
			wp_list_pluck($items, 'key')
		);
		$this->assertTrue($items[1]['active']);
	}

	public function test_content_contains_all_designed_items() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-generated-posts', 'aips-broken-links');
		$content = $payload[3];
		$keys    = array();
		foreach ($content['groups'] as $group) {
			$keys = array_merge($keys, wp_list_pluck($group['items'], 'key'));
		}

		$this->assertCount(11, $keys);
		$this->assertContains('aips-broken-links', $keys);
	}

	public function test_automations_contextual_children_highlight_parent_items() {
		$payload     = AIPS_Admin_Flyout_Navigation::get_payload('aips-author-topics');
		$automations = $payload[0];
		$items       = array_merge($automations['groups'][0]['items'], $automations['groups'][1]['items'], $automations['groups'][2]['items']);
		$active      = array_values(array_filter($items, function($item) {
			return $item['active'];
		}));

		$this->assertCount(1, $active);
		$this->assertSame('authors', $active[0]['key']);
		$this->assertNotContains('author-topics', wp_list_pluck($items, 'key'));
	}

	public function test_non_administrators_receive_no_payload() {
		$subscriber = self::factory()->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber);

		$this->assertSame(array(), AIPS_Admin_Flyout_Navigation::get_payload());
	}
}
