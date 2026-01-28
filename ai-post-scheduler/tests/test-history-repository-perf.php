<?php
/**
 * Test History Repository Performance
 *
 * @package AI_Post_Scheduler
 */

class Test_History_Repository_Perf extends WP_UnitTestCase {

    private $original_wpdb;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->original_wpdb = $wpdb;
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb = $this->original_wpdb;
        parent::tearDown();
    }

    public function test_get_history_late_row_lookup() {
        global $wpdb;

        // Mock $wpdb
        $wpdb = $this->getMockBuilder(stdClass::class)
                     ->addMethods(['get_results', 'get_col', 'get_var', 'prepare', 'esc_like'])
                     ->getMock();

        $wpdb->prefix = 'wp_';

        $wpdb->method('prepare')->will($this->returnCallback(function($query, ...$args) {
            // Handle array as first argument (WP style)
            if (isset($args[0]) && is_array($args[0])) {
                $args = $args[0];
            }

            foreach ($args as $arg) {
                $val = is_numeric($arg) ? $arg : "'$arg'";
                $query = preg_replace('/%[sd]/', $val, $query, 1);
            }
            return $query;
        }));

        $wpdb->method('esc_like')->willReturnArgument(0);

        // We expect specific queries for Late Row Lookup strategy:
        // 1. SELECT id FROM ... LIMIT ... OFFSET ... (to get IDs)
        // 2. SELECT ... FROM ... WHERE id IN (...) (to get data)
        // 3. COUNT(*) ... (for pagination)

        // Let's spy on the queries
        $queries = [];

        $wpdb->method('get_col')->will($this->returnCallback(function($query) use (&$queries) {
            $queries[] = $query;

            // Step 1: IDs
            if (strpos($query, 'SELECT h.id') !== false) {
                return [10, 11, 12];
            }
            return [];
        }));

        $wpdb->method('get_results')->will($this->returnCallback(function($query) use (&$queries) {
            $queries[] = $query;

            // Step 2: Full rows
            if (strpos($query, 'WHERE h.id IN (10,11,12)') !== false) {
                 return [
                    (object) ['id' => 10, 'generated_title' => 'Title 10', 'template_name' => 'Temp A'],
                    (object) ['id' => 11, 'generated_title' => 'Title 11', 'template_name' => 'Temp A'],
                    (object) ['id' => 12, 'generated_title' => 'Title 12', 'template_name' => 'Temp A']
                ];
            }
            return [];
        }));

        $wpdb->method('get_var')->willReturn(100); // Total count

        $repo = new AIPS_History_Repository();
        $result = $repo->get_history(['per_page' => 3, 'page' => 1]);

        $this->assertCount(3, $result['items']);
        $this->assertEquals(10, $result['items'][0]->id);

        // Now assert that we used the Late Row Lookup strategy
        // We check if we executed a query that selects ONLY ID first
        $found_id_query = false;
        foreach ($queries as $q) {
            // Regex to match "SELECT h.id FROM ... LIMIT" but NOT "SELECT h.*"
            if (preg_match('/SELECT\s+h\.id\s+FROM/i', $q) && strpos($q, 'LIMIT') !== false) {
                $found_id_query = true;
                break;
            }
        }

        $this->assertTrue($found_id_query, 'Should use Late Row Lookup strategy (fetch IDs first)');
    }
}
