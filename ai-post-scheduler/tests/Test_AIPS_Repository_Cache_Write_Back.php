<?php
/**
 * Regression test for the production symptom (2026-07-04 Query Monitor logs):
 * repository cache_read() misses on the DB driver were never followed by a
 * cache write, so the persistent cache never warmed.
 *
 * @group cache
 */
class Test_AIPS_Repository_Cache_Write_Back extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'aips_enable_cache_system', '1' );
		update_option( 'aips_cache_driver', 'db' );
		AIPS_Cache::reset_system_enabled_flag();

		// Pre-wire the exact named instance AIPS_Repository_Cache_Config
		// resolves for the medium tier of the author_topics group.
		$name = 'aips_repository_cache_medium_aips_author_topics';
		AIPS_Cache_Factory::register(
			$name,
			new AIPS_Cache( new AIPS_Cache_Db_Driver( $name ) )
		);
	}

	public function test_get_by_id_miss_persists_to_db_cache() {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'aips_author_topics', array(
			'author_id'    => 1,
			'topic_title'  => 'Write-back probe',
			'generated_at' => time(),
		) );
		$topic_id = (int) $wpdb->insert_id;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_cache" );

		$repo  = new AIPS_Author_Topics_Repository();
		$first = $repo->get_by_id( $topic_id );
		$this->assertSame( 'Write-back probe', $first->topic_title );

		$cached_rows = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_cache WHERE cache_group = %s",
			'aips_author_topics'
		) );
		$this->assertGreaterThan( 0, $cached_rows,
			'A cache_read() miss must persist its payload (write-back).' );

		// Mutate the row behind the cache; a warm cache returns the old value.
		$wpdb->update(
			$wpdb->prefix . 'aips_author_topics',
			array( 'topic_title' => 'Mutated directly' ),
			array( 'id' => $topic_id )
		);
		$second = $repo->get_by_id( $topic_id );
		$this->assertSame( 'Write-back probe', $second->topic_title,
			'Second read must be served from the persistent cache.' );
	}
}
