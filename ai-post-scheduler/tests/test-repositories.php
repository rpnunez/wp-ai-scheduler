<?php
/**
 * Test case for Repository Classes
 *
 * Tests the repository pattern implementation focusing on constructor
 * improvements with table name constants and $wpdb handling.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

class Test_AIPS_Repositories extends WP_UnitTestCase {

    /**
     * Mock wpdb object for testing
     * 
     * @var object
     */
    private $mock_wpdb;

    /**
     * Set up test environment
     */
    public function setUp() {
        parent::setUp();
        
        // Create a mock wpdb object
        $this->mock_wpdb = new stdClass();
        $this->mock_wpdb->prefix = 'wp_';
        
        // Load repository classes
        $includes_dir = dirname(__DIR__) . '/includes/';
        if (file_exists($includes_dir . 'class-aips-history-repository.php')) {
            require_once $includes_dir . 'class-aips-history-repository.php';
        }
        if (file_exists($includes_dir . 'class-aips-schedule-repository.php')) {
            require_once $includes_dir . 'class-aips-schedule-repository.php';
        }
        if (file_exists($includes_dir . 'class-aips-template-repository.php')) {
            require_once $includes_dir . 'class-aips-template-repository.php';
        }
    }

    /**
     * Test that History Repository can be instantiated
     */
    public function test_history_repository_instantiation() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_History_Repository();
            $this->assertInstanceOf('AIPS_History_Repository', $repository);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that Schedule Repository can be instantiated
     */
    public function test_schedule_repository_instantiation() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_Schedule_Repository();
            $this->assertInstanceOf('AIPS_Schedule_Repository', $repository);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that Template Repository can be instantiated
     */
    public function test_template_repository_instantiation() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_Template_Repository();
            $this->assertInstanceOf('AIPS_Template_Repository', $repository);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that History Repository uses table name constant
     * 
     * This test verifies that the refactored constructor properly uses
     * the TABLE_SUFFIX constant instead of hardcoded table names.
     */
    public function test_history_repository_uses_constant() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_History_Repository();
            
            // Use reflection to access the private table_name property
            $reflection = new ReflectionClass($repository);
            $table_name_property = $reflection->getProperty('table_name');
            $table_name_property->setAccessible(true);
            $table_name = $table_name_property->getValue($repository);
            
            // Verify the table name includes the prefix and correct suffix
            $this->assertEquals('wp_aips_history', $table_name);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that Schedule Repository uses table name constants
     * 
     * This test verifies that the refactored constructor properly uses
     * the constants for both schedule and template tables.
     */
    public function test_schedule_repository_uses_constants() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_Schedule_Repository();
            
            // Use reflection to access the private properties
            $reflection = new ReflectionClass($repository);
            
            $schedule_table_property = $reflection->getProperty('schedule_table');
            $schedule_table_property->setAccessible(true);
            $schedule_table = $schedule_table_property->getValue($repository);
            
            $templates_table_property = $reflection->getProperty('templates_table');
            $templates_table_property->setAccessible(true);
            $templates_table = $templates_table_property->getValue($repository);
            
            // Verify both table names include the prefix and correct suffixes
            $this->assertEquals('wp_aips_schedule', $schedule_table);
            $this->assertEquals('wp_aips_templates', $templates_table);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that Template Repository uses table name constant
     * 
     * This test verifies that the refactored constructor properly uses
     * the TABLE_SUFFIX constant instead of hardcoded table names.
     */
    public function test_template_repository_uses_constant() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            $repository = new AIPS_Template_Repository();
            
            // Use reflection to access the private table_name property
            $reflection = new ReflectionClass($repository);
            $table_name_property = $reflection->getProperty('table_name');
            $table_name_property->setAccessible(true);
            $table_name = $table_name_property->getValue($repository);
            
            // Verify the table name includes the prefix and correct suffix
            $this->assertEquals('wp_aips_templates', $table_name);
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that repositories properly store $wpdb instance
     * 
     * This test verifies that $wpdb is stored as an instance variable
     * following WordPress best practices. The global $wpdb is a singleton
     * that persists throughout the request lifecycle, so storing a reference
     * to it once in the constructor is more efficient than globalizing it
     * in every method.
     */
    public function test_repositories_store_wpdb_instance() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Set the global wpdb to our mock
        $wpdb = $this->mock_wpdb;
        
        try {
            // Test History Repository
            $history_repo = new AIPS_History_Repository();
            $reflection = new ReflectionClass($history_repo);
            $wpdb_property = $reflection->getProperty('wpdb');
            $wpdb_property->setAccessible(true);
            $stored_wpdb = $wpdb_property->getValue($history_repo);
            $this->assertSame($this->mock_wpdb, $stored_wpdb, 'History Repository should store wpdb instance');
            
            // Test Schedule Repository
            $schedule_repo = new AIPS_Schedule_Repository();
            $reflection = new ReflectionClass($schedule_repo);
            $wpdb_property = $reflection->getProperty('wpdb');
            $wpdb_property->setAccessible(true);
            $stored_wpdb = $wpdb_property->getValue($schedule_repo);
            $this->assertSame($this->mock_wpdb, $stored_wpdb, 'Schedule Repository should store wpdb instance');
            
            // Test Template Repository
            $template_repo = new AIPS_Template_Repository();
            $reflection = new ReflectionClass($template_repo);
            $wpdb_property = $reflection->getProperty('wpdb');
            $wpdb_property->setAccessible(true);
            $stored_wpdb = $wpdb_property->getValue($template_repo);
            $this->assertSame($this->mock_wpdb, $stored_wpdb, 'Template Repository should store wpdb instance');
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that table names respond to different prefixes
     * 
     * This test verifies that the repositories properly construct table names
     * using the provided prefix, which is important for multisite installations
     * or custom table prefixes.
     */
    public function test_repositories_respect_table_prefix() {
        global $wpdb;
        $original_wpdb = $wpdb;
        
        // Create a mock wpdb with a custom prefix
        $custom_wpdb = new stdClass();
        $custom_wpdb->prefix = 'custom_prefix_';
        
        $wpdb = $custom_wpdb;
        
        try {
            // Test History Repository
            $history_repo = new AIPS_History_Repository();
            $reflection = new ReflectionClass($history_repo);
            $table_name_property = $reflection->getProperty('table_name');
            $table_name_property->setAccessible(true);
            $table_name = $table_name_property->getValue($history_repo);
            $this->assertEquals('custom_prefix_aips_history', $table_name, 'History Repository should use custom prefix');
            
            // Test Schedule Repository
            $schedule_repo = new AIPS_Schedule_Repository();
            $reflection = new ReflectionClass($schedule_repo);
            $schedule_table_property = $reflection->getProperty('schedule_table');
            $schedule_table_property->setAccessible(true);
            $schedule_table = $schedule_table_property->getValue($schedule_repo);
            $this->assertEquals('custom_prefix_aips_schedule', $schedule_table, 'Schedule Repository should use custom prefix');
            
            // Test Template Repository
            $template_repo = new AIPS_Template_Repository();
            $reflection = new ReflectionClass($template_repo);
            $table_name_property = $reflection->getProperty('table_name');
            $table_name_property->setAccessible(true);
            $table_name = $table_name_property->getValue($template_repo);
            $this->assertEquals('custom_prefix_aips_templates', $table_name, 'Template Repository should use custom prefix');
        } finally {
            // Restore original wpdb
            $wpdb = $original_wpdb;
        }
    }

    /**
     * Test that repository constants are defined
     * 
     * This test verifies that the TABLE_SUFFIX constants are properly defined
     * as private constants in the repository classes.
     */
    public function test_repository_constants_exist() {
        // Test History Repository
        $history_reflection = new ReflectionClass('AIPS_History_Repository');
        $this->assertTrue($history_reflection->hasConstant('TABLE_SUFFIX'), 'History Repository should have TABLE_SUFFIX constant');
        $this->assertEquals('aips_history', $history_reflection->getConstant('TABLE_SUFFIX'));
        
        // Test Schedule Repository
        $schedule_reflection = new ReflectionClass('AIPS_Schedule_Repository');
        $this->assertTrue($schedule_reflection->hasConstant('SCHEDULE_TABLE_SUFFIX'), 'Schedule Repository should have SCHEDULE_TABLE_SUFFIX constant');
        $this->assertEquals('aips_schedule', $schedule_reflection->getConstant('SCHEDULE_TABLE_SUFFIX'));
        $this->assertTrue($schedule_reflection->hasConstant('TEMPLATES_TABLE_SUFFIX'), 'Schedule Repository should have TEMPLATES_TABLE_SUFFIX constant');
        $this->assertEquals('aips_templates', $schedule_reflection->getConstant('TEMPLATES_TABLE_SUFFIX'));
        
        // Test Template Repository
        $template_reflection = new ReflectionClass('AIPS_Template_Repository');
        $this->assertTrue($template_reflection->hasConstant('TABLE_SUFFIX'), 'Template Repository should have TABLE_SUFFIX constant');
        $this->assertEquals('aips_templates', $template_reflection->getConstant('TABLE_SUFFIX'));
    }
}
