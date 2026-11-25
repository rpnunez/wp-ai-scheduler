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

	public function test_studio_splits_into_generation_and_building_blocks_columns() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-studio', 'voices');
		$studio  = $payload[1];

		$this->assertCount(2, $studio['groups']);
		$this->assertSame(array(1, 2), wp_list_pluck($studio['groups'], 'column'));
		$this->assertSame(array('templates', 'voices'), wp_list_pluck($studio['groups'][0]['items'], 'key'));
		$this->assertSame(
			array('structures', 'structure-sections', 'post-slices'),
			wp_list_pluck($studio['groups'][1]['items'], 'key')
		);

		$active = array_values(array_filter($this->flatten_items($studio), function($item) {
			return $item['active'];
		}));
		$this->assertCount(1, $active);
		$this->assertSame('voices', $active[0]['key']);
	}

	public function test_research_splits_into_discovery_and_planning_columns() {
		$payload  = AIPS_Admin_Flyout_Navigation::get_payload('aips-research', 'trending');
		$research = $payload[2];

		$this->assertSame('research', $research['key']);
		$this->assertSame(array(1, 2), wp_list_pluck($research['groups'], 'column'));
		$this->assertSame(array('trending', 'gap-analysis'), wp_list_pluck($research['groups'][0]['items'], 'key'));
		$this->assertSame(array('planner'), wp_list_pluck($research['groups'][1]['items'], 'key'));
	}

	public function test_every_flyout_hub_defines_groups_for_both_columns() {
		$checked = 0;
		foreach (AIPS_Admin_Page_Context::get_canonical_hubs() as $hub_key => $hub) {
			if (empty($hub['flyout_groups'])) {
				continue;
			}
			$columns = array();
			foreach ($hub['flyout_groups'] as $group) {
				$this->assertArrayHasKey('sections', $group);
				$this->assertContains($group['column'], array(1, 2));
				$columns[] = $group['column'];
			}
			$columns = array_values(array_unique($columns));
			sort($columns);
			$this->assertSame(array(1, 2), $columns, $hub_key . ' must fill both flyout columns.');
			$checked++;
		}

		$this->assertSame(5, $checked);
	}

	public function test_studio_and_research_items_use_short_descriptions() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload();
		$hubs    = AIPS_Admin_Page_Context::get_canonical_hubs();

		foreach (array(1 => AIPS_Admin_Page_Context::HUB_STUDIO, 2 => AIPS_Admin_Page_Context::HUB_RESEARCH) as $index => $hub_key) {
			foreach ($this->flatten_items($payload[$index]) as $item) {
				$this->assertSame($hubs[$hub_key]['sections'][$item['key']]['short_description'], $item['description']);
			}
		}
	}

	public function test_content_contains_all_designed_items() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-generated-posts', 'aips-broken-links');
		$keys    = wp_list_pluck($this->flatten_items($payload[3]), 'key');

		$this->assertCount(11, $keys);
		$this->assertContains('aips-broken-links', $keys);
	}

	public function test_automations_contextual_children_highlight_parent_items() {
		$payload     = AIPS_Admin_Flyout_Navigation::get_payload('aips-author-topics');
		$automations = $payload[0];
		$items       = $this->flatten_items($automations);
		$active      = array_values(array_filter($items, function($item) {
			return $item['active'];
		}));

		$this->assertCount(1, $active);
		$this->assertSame('authors', $active[0]['key']);
		$this->assertNotContains('author-topics', wp_list_pluck($items, 'key'));
	}

	public function test_compact_nav_renders_for_current_hub_including_hidden_pages() {
		ob_start();
		AIPS_Admin_Flyout_Navigation::render_compact_nav('aips-author-topics');
		$output = ob_get_clean();

		$this->assertStringContainsString('aips-admin-hub-nav', $output);
		$this->assertStringContainsString('Authors', $output);
		$this->assertStringContainsString('is-active', $output);
		$this->assertStringNotContainsString("Author's Topics", $output);

		$this->assertNull(AIPS_Admin_Flyout_Navigation::get_current_hub_payload('ai-post-scheduler'));
		$this->assertNull(AIPS_Admin_Flyout_Navigation::get_current_hub_payload('aips-settings'));
	}

	public function test_legacy_content_child_pages_highlight_canonical_tabs() {
		$payload = AIPS_Admin_Flyout_Navigation::get_payload('aips-content-intelligence');
		$items   = $this->flatten_items($payload[3]);
		$active  = array_values(array_filter($items, function($item) {
			return $item['active'];
		}));

		$this->assertCount(1, $active);
		$this->assertSame('aips-content-indexer', $active[0]['key']);
	}

	public function test_non_administrators_receive_no_payload() {
		$subscriber = self::factory()->user->create(array('role' => 'subscriber'));
		wp_set_current_user($subscriber);

		$this->assertSame(array(), AIPS_Admin_Flyout_Navigation::get_payload());
	}

	/**
	 * Flatten a hub payload's grouped items.
	 *
	 * @param array<string, mixed> $hub Hub payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function flatten_items($hub) {
		$items = array();
		foreach ($hub['groups'] as $group) {
			$items = array_merge($items, $group['items']);
		}
		return $items;
	}
}
