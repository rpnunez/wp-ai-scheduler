<?php
use PHPUnit\Framework\TestCase;

class Test_Author_Topics_Repository_Filtering extends TestCase {

    public function setUp(): void {
        parent::setUp();
        if (!class_exists('AIPS_Author_Topics_Repository')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-aips-author-topics-repository.php';
        }
    }

    public function test_get_latest_by_author_uses_timestamp_filter() {
        global $wpdb;

        // Mock $wpdb with methods
        $wpdb = $this->getMockBuilder('stdClass')
                     ->addMethods(['prepare', 'get_results', 'get_row', 'insert', 'update', 'delete', 'get_col'])
                     ->getMock();
        $wpdb->prefix = 'wp_';

        // Expect prepare to be called with query containing 'generated_at >=' and the timestamp
        $wpdb->expects($this->once())
             ->method('prepare')
             ->with(
                 $this->stringContains('AND generated_at >= %s'),
                 $this->callback(function($args) {
                     // Args should contain the timestamp
                     // prepare() args are passed as separate arguments if not array, or as array
                     // The mock receives them as arguments to the method
                     // If prepare($query, $arg1, $arg2)
                     // But with() matches arguments.
                     // The first argument is query.
                     // The second argument is ...$args.
                     // If I check the second argument (or subsequent)
                     return in_array('2023-01-01 00:00:00', $args);
                 })
             )
             ->willReturn('SELECT ...'); // Dummy query

        $wpdb->expects($this->once())
             ->method('get_results')
             ->willReturn(array());

        $repo = new AIPS_Author_Topics_Repository();

        // Call with generated_after
        $repo->get_latest_by_author(1, 5, '2023-01-01 00:00:00');
    }
}
