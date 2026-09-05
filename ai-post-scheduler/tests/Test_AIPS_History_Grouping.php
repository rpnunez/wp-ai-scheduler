<?php
/**
 * Class Test_AIPS_History_Grouping
 *
 * Unit tests for AIPS_History::group_contiguous_items().
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Grouping extends WP_UnitTestCase {

    private $history;

    public function setUp(): void {
        parent::setUp();
        $this->history = new AIPS_History();
    }

    private function make_item(int $id, string $method, string $status = 'completed', string $relative_date = '2 min ago', $post_type = 'post') {
        $item = new stdClass();
        $item->id = $id;
        $item->creation_method = $method;
        $item->status = $status;
        $item->relative_date = $relative_date;
        $item->post_type = $post_type;
        return $item;
    }

    public function test_empty_input_returns_empty_array() {
        $this->assertSame(array(), $this->history->group_contiguous_items(array()));
    }

    public function test_singletons_stay_ungrouped() {
        $items = array(
            $this->make_item(1, 'content_indexing'),
            $this->make_item(2, 'post_generation'),
            $this->make_item(3, 'content_indexing'),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertCount(3, $grouped);
        foreach ($grouped as $entry) {
            $this->assertFalse($entry['is_group']);
        }
    }

    public function test_runs_of_two_or_more_are_grouped() {
        $items = array(
            $this->make_item(1, 'content_indexing'),
            $this->make_item(2, 'content_indexing'),
            $this->make_item(3, 'content_indexing'),
            $this->make_item(4, 'post_generation'),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertCount(2, $grouped);
        $this->assertTrue($grouped[0]['is_group']);
        $this->assertSame(3, $grouped[0]['count']);
        $this->assertFalse($grouped[1]['is_group']);
    }

    public function test_status_tallies_include_legacy_indexed() {
        $items = array(
            $this->make_item(1, 'content_indexing', 'completed'),
            $this->make_item(2, 'content_indexing', 'indexed'),
            $this->make_item(3, 'content_indexing', 'failed'),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertTrue($grouped[0]['is_group']);
        $this->assertSame(2, $grouped[0]['completed_count']);
        $this->assertSame(1, $grouped[0]['failed_count']);
    }

    public function test_missing_method_defaults_to_post_generation() {
        $items = array(
            $this->make_item(1, ''),
            $this->make_item(2, ''),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertTrue($grouped[0]['is_group']);
        $this->assertSame('post_generation', $grouped[0]['method']);
    }

    public function test_first_and_last_dates_are_captured() {
        $items = array(
            $this->make_item(1, 'content_indexing', 'completed', 'just now'),
            $this->make_item(2, 'content_indexing', 'completed', '5 min ago'),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertSame('just now', $grouped[0]['first_date']);
        $this->assertSame('5 min ago', $grouped[0]['last_date']);
    }

    public function test_post_types_deduplicated() {
        $items = array(
            $this->make_item(1, 'content_indexing', 'completed', 'now', 'post'),
            $this->make_item(2, 'content_indexing', 'completed', 'now', 'post'),
            $this->make_item(3, 'content_indexing', 'completed', 'now', 'page'),
        );
        $grouped = $this->history->group_contiguous_items($items);
        $this->assertSame(array('post', 'page'), $grouped[0]['post_types']);
    }
}
