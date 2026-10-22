<?php
/**
 * Shared test cleanup for classes that call clear_index().
 *
 * clear_index() ends with TRUNCATE, which implicitly commits the WP test transaction, so
 * the clear lock (and any rows written earlier in the test) would survive the rollback
 * and make later tests see "the index is being cleared" for the lock TTL.
 *
 * @package AI_Post_Scheduler
 */

trait Trait_AIPS_Clear_Lock_Cleanup {

	/**
	 * Call after parent::tearDown().
	 *
	 * @return void
	 */
	protected function purge_committed_lock_rows(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aips\\_lock\\_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_internal_links" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_embeddings" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_relationships" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ('aips_pending_index_queue', 'aips_pending_topic_index_queue')" );
		$wpdb->query( 'COMMIT' );
	}
}
