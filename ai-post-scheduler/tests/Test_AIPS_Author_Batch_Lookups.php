<?php
/**
 * @group authors
 */
class Test_AIPS_Author_Batch_Lookups extends WP_UnitTestCase {

	public function test_authors_get_by_ids_returns_map_keyed_by_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_authors';
		$wpdb->insert( $table, array( 'name' => 'Alpha' ) );
		$a = (int) $wpdb->insert_id;
		$wpdb->insert( $table, array( 'name' => 'Beta' ) );
		$b = (int) $wpdb->insert_id;

		$repo = new AIPS_Authors_Repository();
		$map  = $repo->get_by_ids( array( $a, $b, 999999 ) );

		$this->assertSame( 'Alpha', $map[ $a ]->name );
		$this->assertSame( 'Beta', $map[ $b ]->name );
		$this->assertArrayNotHasKey( 999999, $map );
	}

	public function test_topics_get_by_ids_returns_map_keyed_by_id() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_author_topics';
		$wpdb->insert( $table, array( 'author_id' => 1, 'topic_title' => 'T1', 'generated_at' => time() ) );
		$t1 = (int) $wpdb->insert_id;

		$repo = new AIPS_Author_Topics_Repository();
		$map  = $repo->get_by_ids( array( $t1 ) );

		$this->assertSame( 'T1', $map[ $t1 ]->topic_title );
	}

	public function test_get_by_ids_empty_input_returns_empty_array() {
		$repo = new AIPS_Authors_Repository();
		$this->assertSame( array(), $repo->get_by_ids( array() ) );
	}
}
