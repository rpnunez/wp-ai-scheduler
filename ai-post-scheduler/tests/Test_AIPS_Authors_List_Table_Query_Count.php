<?php
/**
 * Test Authors List Table Query Count
 *
 * Benchmark & regression test for AIPS_Authors_List_Table query performance.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Authors_List_Table_Query_Count extends WP_UnitTestCase {

	protected $authors_repository;
	protected $topics_repository;
	protected $logs_repository;

	public function setUp(): void {
		parent::setUp();
		$this->authors_repository = new AIPS_Authors_Repository();
		$this->topics_repository  = new AIPS_Author_Topics_Repository();
		$this->logs_repository    = new AIPS_Author_Topic_Logs_Repository();
	}

	private function seed_authors_and_topics($count) {
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_authors");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_author_topics");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}aips_author_topic_logs");

		AIPS_Cache_Factory::reset();

		for ($i = 1; $i <= $count; $i++) {
			$author_id = $this->authors_repository->create(array(
				'name'      => "Author {$i}",
				'is_active' => 1,
			));

			$topic_id = $this->topics_repository->create(array(
				'author_id'   => $author_id,
				'topic_title' => "Topic for Author {$i}",
				'status'      => 'approved',
			));

			$this->logs_repository->log_post_generation($topic_id, 100 + $i);
		}
	}

	public function test_authors_list_table_query_count_is_bounded() {
		$measurements = array();

		foreach (array(10, 100, 500) as $n) {
			$this->seed_authors_and_topics($n);

			$table = new AIPS_Authors_List_Table();

			global $wpdb;
			$wpdb->num_queries = 0;
			$wpdb->queries     = array();

			$start = $wpdb->num_queries;
			$table->prepare_items();

			foreach ($table->items as $item) {
				$reflection = new ReflectionClass($table);
				$method = $reflection->getMethod('column_content');
				$method->setAccessible(true);
				$method->invoke($table, $item);
			}

			$total_queries = $wpdb->num_queries - $start;
			$measurements[$n] = $total_queries;

			// Query count for prepare_items() + column rendering must stay bounded O(1) across N (4 queries per page)
			$this->assertLessThanOrEqual(6, $total_queries, "Query count for N={$n} should be bounded to O(1)");
		}

		$this->assertEquals($measurements[10], $measurements[100]);
		$this->assertEquals($measurements[100], $measurements[500]);
	}
}
