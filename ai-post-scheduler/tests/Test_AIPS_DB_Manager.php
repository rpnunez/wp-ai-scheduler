<?php

class Test_AIPS_DB_Manager extends WP_UnitTestCase {

    public function test_get_schema_returns_all_tables() {
        $db_manager = new AIPS_DB_Manager();
        $schema = $db_manager->get_schema();

        $this->assertIsArray( $schema );
        $this->assertCount( 31, $schema, 'The schema should contain exactly 31 table creation statements.' );

        // Basic check that it contains CREATE TABLE statements
        foreach ( $schema as $statement ) {
            $this->assertStringContainsString( 'CREATE TABLE', $statement );
        }
    }

    public function test_schema_groups() {
        $db_manager = new AIPS_DB_Manager();
        $schema = $db_manager->get_schema();

        // Core table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_history' ) );
        // Content table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_voices' ) );
        // Taxonomy table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_post_slices' ) );
        // Metrics table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_notifications' ) );
        // Advanced table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_embeddings' ) );
        // Caching table
        $this->assertTrue( $this->schema_contains_table( $schema, 'aips_cache' ) );
    }

    private function schema_contains_table( array $schema, string $table_name ): bool {
        foreach ( $schema as $statement ) {
            if ( strpos( $statement, 'CREATE TABLE ' . $GLOBALS['wpdb']->prefix . $table_name ) !== false ) {
                return true;
            }
        }
        return false;
    }
}
