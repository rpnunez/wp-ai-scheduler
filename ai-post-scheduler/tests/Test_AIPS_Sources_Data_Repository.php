<?php
/**
 * Tests for AIPS_Sources_Data_Repository.
 *
 * Validates insert_if_new, mark_fetch_failed, get_by_source_id,
 * get_extracted_texts_by_source_ids, and delete_by_source_id behaviours.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */
class Test_AIPS_Sources_Data_Repository extends WP_UnitTestCase {

	/** @var AIPS_Sources_Data_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->repo = new AIPS_Sources_Data_Repository();
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_sources_data" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}aips_sources" );
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// insert_if_new()
	// ------------------------------------------------------------------

	/** @test */
	public function test_insert_if_new_inserts_new_row() {
		$result = $this->repo->insert_if_new( 42, array(
			'url'            => 'https://example.com',
			'page_title'     => 'Example Title',
			'extracted_text' => 'Some extracted content.',
			'char_count'     => 23,
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$this->assertTrue( $result );

		$row = $this->repo->get_by_source_id( 42 );
		$this->assertNotNull( $row );
		$this->assertEquals( 42, (int) $row->source_id );
		$this->assertEquals( 'success', $row->fetch_status );
		$this->assertEquals( 'Example Title', $row->page_title );
	}

	/** @test */
	public function test_insert_if_new_deduplicates_identical_content() {
		$data = array(
			'url'            => 'https://example.com',
			'extracted_text' => 'Same content both times.',
			'char_count'     => 24,
			'fetch_status'   => 'success',
			'http_status'    => 200,
		);

		$this->repo->insert_if_new( 99, $data );
		$this->repo->insert_if_new( 99, $data );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_sources_data WHERE source_id = 99"
		);
		// Same content hash — only one row stored.
		$this->assertSame( 1, $count );
	}

	/** @test */
	public function test_insert_if_new_archives_changed_content() {
		$this->repo->insert_if_new( 99, array(
			'url'            => 'https://example.com',
			'extracted_text' => 'First fetch.',
			'char_count'     => 12,
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$this->repo->insert_if_new( 99, array(
			'url'            => 'https://example.com',
			'extracted_text' => 'Second fetch with more content.',
			'char_count'     => 31,
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}aips_sources_data WHERE source_id = 99"
		);
		// Different content — a second archive row is inserted.
		$this->assertSame( 2, $count );

		$row = $this->repo->get_by_source_id( 99 );
		$this->assertEquals( 'Second fetch with more content.', $row->extracted_text );
	}

	/** @test */
	public function test_insert_if_new_returns_false_for_zero_source_id() {
		$result = $this->repo->insert_if_new( 0, array( 'url' => 'https://example.com' ) );
		$this->assertFalse( $result );
	}

	// ------------------------------------------------------------------
	// mark_fetch_failed()
	// ------------------------------------------------------------------

	/** @test */
	public function test_mark_fetch_failed_inserts_row_when_none_exists() {
		$this->repo->mark_fetch_failed( 55, 'Connection timed out.', 0 );

		$row = $this->repo->get_by_source_id( 55 );
		$this->assertNotNull( $row );
		$this->assertEquals( 'failed', $row->fetch_status );
		$this->assertEquals( 'Connection timed out.', $row->error_message );
	}

	/** @test */
	public function test_mark_fetch_failed_preserves_prior_success_content() {
		$this->repo->insert_if_new( 66, array(
			'url'            => 'https://example.com',
			'extracted_text' => 'Previously good content.',
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$this->repo->mark_fetch_failed( 66, 'Server error.', 500 );

		// get_by_source_id() now returns the failure row (most recent).
		$latest = $this->repo->get_by_source_id( 66 );
		$this->assertEquals( 'failed', $latest->fetch_status );
		$this->assertEquals( 'Server error.', $latest->error_message );

		// The previously fetched content is preserved in the latest success row.
		$success_row = $this->repo->get_latest_success_by_source_id( 66 );
		$this->assertNotNull( $success_row );
		$this->assertEquals( 'Previously good content.', $success_row->extracted_text );
	}

	// ------------------------------------------------------------------
	// get_by_source_id()
	// ------------------------------------------------------------------

	/** @test */
	public function test_get_by_source_id_returns_null_when_not_found() {
		$this->assertNull( $this->repo->get_by_source_id( 9999 ) );
	}

	// ------------------------------------------------------------------
	// get_extracted_texts_by_source_ids()
	// ------------------------------------------------------------------

	/** @test */
	public function test_get_extracted_texts_by_source_ids_returns_empty_for_empty_input() {
		$result = $this->repo->get_extracted_texts_by_source_ids( array() );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/** @test */
	public function test_get_extracted_texts_by_source_ids_returns_only_success_rows() {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'aips_sources', array( 'id' => 100, 'url' => 'https://a.example.com', 'is_active' => 1 ) );
		$this->repo->insert_if_new( 100, array(
			'url'            => 'https://a.example.com',
			'extracted_text' => 'Content from source A.',
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );
		$this->repo->insert_if_new( 101, array(
			'url'            => 'https://b.example.com',
			'extracted_text' => 'Content from source B.',
			'fetch_status'   => 'failed',
			'http_status'    => 500,
		) );

		$map = $this->repo->get_extracted_texts_by_source_ids( array( 100, 101 ) );

		$this->assertArrayHasKey( 100, $map );
		$this->assertArrayNotHasKey( 101, $map );
		$this->assertEquals( 'Content from source A.', $map[100]->extracted_text );
	}

	/** @test */
	public function test_get_extracted_texts_excludes_empty_text() {
		$this->repo->insert_if_new( 200, array(
			'url'            => 'https://empty.example.com',
			'extracted_text' => '',
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$map = $this->repo->get_extracted_texts_by_source_ids( array( 200 ) );
		$this->assertArrayNotHasKey( 200, $map );
	}

	// ------------------------------------------------------------------
	// source data CRUD pagination
	// ------------------------------------------------------------------

	/** @test */
	public function test_get_by_id_returns_single_source_data_row() {
		$this->repo->insert_if_new( 301, array(
			'url'            => 'https://single.example.com',
			'extracted_text' => 'Single row content.',
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$latest = $this->repo->get_by_source_id( 301 );
		$row    = $this->repo->get_by_id( (int) $latest->id );

		$this->assertNotNull( $row );
		$this->assertEquals( 'Single row content.', $row->extracted_text );
	}

	/** @test */
	public function test_get_paginated_by_source_id_filters_by_search() {
		$this->repo->insert_if_new( 302, array(
			'url'            => 'https://one.example.com',
			'page_title'     => 'First Page',
			'extracted_text' => 'Alpha content.',
			'fetch_status'   => 'success',
		) );
		$this->repo->insert_if_new( 302, array(
			'url'            => 'https://two.example.com',
			'page_title'     => 'Second Page',
			'extracted_text' => 'Beta searchable content.',
			'fetch_status'   => 'success',
		) );

		$result = $this->repo->get_paginated_by_source_id( 302, 'Second', 20, 1 );

		$this->assertSame( 1, $result['total'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( 'Second Page', $result['items'][0]->page_title );
	}

	/** @test */
	public function test_get_paginated_by_source_id_searches_body_only_when_enabled() {
		$this->repo->insert_if_new( 305, array(
			'url'            => 'https://body-one.example.com',
			'page_title'     => 'Body First',
			'extracted_text' => 'Alpha body only token.',
			'fetch_status'   => 'success',
		) );

		$default_result = $this->repo->get_paginated_by_source_id( 305, 'token', 20, 1 );
		$body_result    = $this->repo->get_paginated_by_source_id( 305, 'token', 20, 1, array(
			'search_body_text' => true,
		) );

		$this->assertSame( 0, $default_result['total'] );
		$this->assertSame( 1, $body_result['total'] );
	}

	/** @test */
	public function test_get_paginated_by_source_id_applies_structured_filters() {
		$this->repo->insert_if_new( 306, array(
			'url'            => 'https://filters-success.example.com',
			'page_title'     => 'Filter Success',
			'extracted_text' => str_repeat( 'A', 80 ),
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );
		$success = $this->repo->get_by_source_id( 306 );
		$this->repo->update( (int) $success->id, array(
			'fetched_at' => AIPS_DateTime::fromDate( '2026-01-15' )->timestamp(),
		) );

		$this->repo->insert_if_new( 306, array(
			'url'            => 'https://filters-failed.example.com',
			'page_title'     => 'Filter Failed',
			'extracted_text' => str_repeat( 'B', 20 ),
			'fetch_status'   => 'failed',
			'http_status'    => 404,
		) );

		$result = $this->repo->get_paginated_by_source_id( 306, '', 20, 1, array(
			'fetch_status'      => 'success',
			'http_status_class' => 200,
			'fetched_after'     => '2026-01-01',
			'fetched_before'    => '2026-01-31',
			'min_char_count'    => 50,
			'max_char_count'    => 100,
		) );

		$this->assertSame( 1, $result['total'] );
		$this->assertEquals( 'Filter Success', $result['items'][0]->page_title );
	}

	/** @test */
	public function test_update_source_data_recalculates_content_size_fields() {
		$this->repo->insert_if_new( 303, array(
			'url'            => 'https://update.example.com',
			'extracted_text' => 'Old content.',
			'fetch_status'   => 'success',
		) );

		$row    = $this->repo->get_by_source_id( 303 );
		$result = $this->repo->update( (int) $row->id, array(
			'url'            => 'https://update.example.com/new',
			'page_title'     => 'Updated Title',
			'extracted_text' => 'Updated source data content.',
			'fetch_status'   => 'success',
		) );

		$updated = $this->repo->get_by_id( (int) $row->id );

		$this->assertTrue( $result );
		$this->assertEquals( 'Updated Title', $updated->page_title );
		$this->assertEquals( strlen( 'Updated source data content.' ), (int) $updated->char_count );
		$this->assertEquals( AIPS_Sources_Data_Repository::content_hash( $updated->url, 'Updated source data content.' ), $updated->content_hash );
	}

	/** @test */
	public function test_delete_removes_single_source_data_row() {
		$this->repo->insert_if_new( 304, array(
			'url'            => 'https://delete-one.example.com',
			'extracted_text' => 'Delete one.',
			'fetch_status'   => 'success',
		) );

		$row = $this->repo->get_by_source_id( 304 );

		$this->assertTrue( $this->repo->delete( (int) $row->id ) );
		$this->assertNull( $this->repo->get_by_id( (int) $row->id ) );
	}

	// ------------------------------------------------------------------
	// delete_by_source_id()
	// ------------------------------------------------------------------

	/** @test */
	public function test_delete_by_source_id_removes_row() {
		$this->repo->insert_if_new( 77, array(
			'url'          => 'https://delete-me.example.com',
			'fetch_status' => 'success',
			'http_status'  => 200,
		) );

		$this->repo->delete_by_source_id( 77 );

		$this->assertNull( $this->repo->get_by_source_id( 77 ) );
	}

	/** @test */
	public function test_delete_by_source_id_is_safe_when_no_row_exists() {
		// Should not throw — just a no-op.
		$this->repo->delete_by_source_id( 88888 );
		$this->assertTrue( true );
	}

	public function test_duplicate_edits_preserve_both_rows() {
		$this->repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'First' ) );
		$first = $this->repo->get_by_source_id( 42 );
		$this->repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'Second' ) );
		$second = $this->repo->get_by_source_id( 42 );
		$result = $this->repo->update_with_result( $second->id, array( 'extracted_text' => 'First' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'duplicate_source_content', $result->get_error_code() );
		$this->assertSame( 'Second', $this->repo->get_by_id( $second->id )->extracted_text );
		$this->assertNotNull( $this->repo->get_by_id( $first->id ) );
	}

	public function test_missing_edit_is_reported() {
		$result = $this->repo->update_with_result( 9999999, array( 'extracted_text' => 'Missing' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'source_data_not_found', $result->get_error_code() );
	}

	public function test_normalization_keeps_multibyte_counts_consistent() {
		$text = "caf\u{00e9} \u{65e5}\u{672c}\u{8a9e}";
		$this->repo->insert_if_new( 42, array( 'extracted_text' => '  ' . $text . '  ', 'char_count' => 999 ) );
		$row = $this->repo->get_by_source_id( 42 );
		$this->assertSame( $text, $row->extracted_text );
		$this->assertSame( 8, (int) $row->char_count );
		$this->assertSame( AIPS_Sources_Data_Repository::content_hash( '', $text ), $row->content_hash );
	}

	public function test_url_change_with_same_text_preserves_both_provenances() {
		$sources = new AIPS_Sources_Repository();
		$id = $sources->create( array( 'url' => 'https://example.com/a' ) );
		$this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/a', 'extracted_text' => 'Same content' ) );
		$old = $this->repo->get_by_source_id( $id );
		$sources->update( $id, array( 'url' => 'https://example.com/b' ) );
		$this->assertEmpty( $this->repo->pick_next_for_prompt_bulk( array( $id ) ) );
		$this->assertTrue( $this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/b', 'extracted_text' => 'Same content' ) ) );
		$selected = $this->repo->pick_next_for_prompt_bulk( array( $id ) );
		$this->assertSame( 'https://example.com/b', $selected[$id]->url );
		$this->assertFalse( property_exists( $selected[$id], 'raw_html' ) );
		$this->assertSame( 'https://example.com/a', $this->repo->get_by_id( $old->id )->url );
	}

	public function test_retention_preserves_newest_current_snapshot_and_batches() {
		global $wpdb;
		$sources = new AIPS_Sources_Repository();
		$id = $sources->create( array( 'url' => 'https://example.com/current' ) );
		for ( $i = 0; $i < 103; $i++ ) {
			$this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/current', 'extracted_text' => 'Snapshot ' . $i ) );
		}
		$latest = $this->repo->get_by_source_id( $id );
		$wpdb->query( "UPDATE {$wpdb->prefix}aips_sources_data SET fetched_at = 10" );
		$this->assertSame( 102, $this->repo->count_prunable_archive( 11 ) );
		$this->assertSame( 100, $this->repo->prune_archive( 11, 500 ) );
		$this->assertSame( 2, $this->repo->prune_archive( 11 ) );
		$this->assertSame( 0, $this->repo->prune_archive( 11 ) );
		$this->assertNotNull( $this->repo->get_by_id( $latest->id ) );
	}


	public function test_insert_collision_requires_verified_duplicate() {
		foreach ( array( 7, null ) as $race_id ) {
			$db = $this->getMockBuilder( wpdb::class )->disableOriginalConstructor()->onlyMethods( array( 'prepare', 'get_var', 'insert', 'query' ) )->getMock();
			$db->method( 'prepare' )->willReturnArgument( 0 );
			$db->method( 'query' )->willReturn( 0 );
			$db->expects( $this->exactly( 2 ) )->method( 'get_var' )->willReturnOnConsecutiveCalls( null, $race_id );
			$db->expects( $this->once() )->method( 'insert' )->willReturn( false );
			$repo = new AIPS_Sources_Data_Repository();
			$property = new ReflectionProperty( $repo, 'wpdb' );
			$property->setAccessible( true );
			$property->setValue( $repo, $db );
			$this->assertSame( null !== $race_id, $repo->insert_if_new( 42, array( 'url' => 'https://example.com', 'extracted_text' => 'Concurrent body' ) ) );
		}
	}

	public function test_fetch_promotes_non_success_snapshot_without_changing_provenance() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_sources_data';
		foreach ( array( 'pending', 'failed' ) as $index => $status ) {
			$source_id = 800 + $index;
			$data = array( 'url' => 'https://example.com/recovered', 'extracted_text' => 'Recovered unchanged content', 'http_status' => 200 );
			$this->assertTrue( $this->repo->insert_if_new( $source_id, $data ) );
			$original = $this->repo->get_by_source_id( $source_id );
			$wpdb->update( $table, array( 'fetched_at' => 1, 'created_at' => 2 ), array( 'id' => $original->id ) );
			$this->assertTrue( $this->repo->update( $original->id, array( 'fetch_status' => $status, 'error_message' => 'Old failure' ) ) );
			// Exercise both the URL-aware hash and pre-upgrade text-only hash.
			if ( 'failed' === $status ) {
				$wpdb->update( $table, array( 'content_hash' => hash( 'sha256', $data['extracted_text'] ) ), array( 'id' => $original->id ) );
			}
			$original = $this->repo->get_by_id( $original->id );
			$this->assertTrue( $this->repo->insert_if_new( $source_id, $data ) );
			$recovered = $this->repo->get_by_id( $original->id );
			$this->assertSame( 'success', $recovered->fetch_status );
			$this->assertSame( '', $recovered->error_message );
			$this->assertSame( $original->url, $recovered->url );
			$this->assertSame( $original->fetched_at, $recovered->fetched_at );
			$this->assertSame( $original->created_at, $recovered->created_at );
			$this->assertSame( $original->content_hash, $recovered->content_hash );
			$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE source_id = %d", $source_id ) ) );
		}
	}

	public function test_promotion_write_error_does_not_report_fetch_success() {
		global $wpdb;
		$data = array( 'url' => 'https://example.com/promotion-error', 'extracted_text' => 'Unchanged pending content' );
		$this->assertTrue( $this->repo->insert_if_new( 802, $data ) );
		$original = $this->repo->get_by_source_id( 802 );
		$this->assertTrue( $this->repo->update( $original->id, array( 'fetch_status' => 'pending' ) ) );
		$fail_update = static function( $query ) use ( $wpdb ) {
			return str_starts_with( $query, "UPDATE {$wpdb->prefix}aips_sources_data SET fetch_status = 'success'" ) ? 'INVALID promotion write fixture' : $query;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail_update );
		try {
			$this->assertFalse( $this->repo->insert_if_new( 802, $data ) );
		} finally {
			remove_filter( 'query', $fail_update );
			$wpdb->suppress_errors( $previous );
		}
		$this->assertSame( 'pending', $this->repo->get_by_id( $original->id )->fetch_status );
	}

	public function test_update_race_classifies_unique_collision_and_other_write_errors() {
		foreach ( array( 7, null ) as $race_id ) {
			$db = $this->getMockBuilder( wpdb::class )->disableOriginalConstructor()->onlyMethods( array( 'prepare', 'get_var', 'get_row', 'update' ) )->getMock();
			$db->method( 'prepare' )->willReturnArgument( 0 );
			$db->method( 'get_row' )->willReturn( (object) array( 'id' => 1, 'source_id' => 42, 'url' => 'https://example.com' ) );
			$db->expects( $this->exactly( 2 ) )->method( 'get_var' )->willReturnOnConsecutiveCalls( null, $race_id );
			$db->expects( $this->once() )->method( 'update' )->willReturn( false );
			$repo = new AIPS_Sources_Data_Repository();
			$property = new ReflectionProperty( $repo, 'wpdb' );
			$property->setAccessible( true );
			$property->setValue( $repo, $db );
			$result = $repo->update_with_result( 1, array( 'extracted_text' => 'Concurrent edit' ) );
			$this->assertWPError( $result );
			$this->assertSame( null !== $race_id ? 'duplicate_source_content' : 'source_data_write_failed', $result->get_error_code() );
		}
	}


	public function test_retention_boundaries_previous_url_and_history_metadata() {
		global $wpdb;
		$sources = new AIPS_Sources_Repository();
		$id = $sources->create( array( 'url' => 'https://example.com/current' ) );
		$this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/previous', 'extracted_text' => 'Previous URL' ) );
		$previous = $this->repo->get_by_source_id( $id );
		$this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/current', 'extracted_text' => 'Usable current' ) );
		$usable = $this->repo->get_by_source_id( $id );
		$this->repo->mark_fetch_failed( $id, 'A newer failure', 500 );
		$failed = $this->repo->get_by_source_id( $id );
		$this->repo->insert_if_new( $id, array( 'url' => 'https://example.com/previous', 'extracted_text' => 'Boundary' ) );
		$boundary = $this->repo->get_by_source_id( $id );
		$table = $wpdb->prefix . 'aips_sources_data';
		$wpdb->update( $table, array( 'fetched_at' => 5 ), array( 'id' => $previous->id ) );
		$wpdb->update( $table, array( 'fetched_at' => 6 ), array( 'id' => $usable->id ) );
		$wpdb->update( $table, array( 'fetched_at' => 9 ), array( 'id' => $failed->id ) );
		$wpdb->update( $table, array( 'fetched_at' => 10 ), array( 'id' => $boundary->id ) );
		$post_id = self::factory()->post->create();
		$metadata = array( array( 'source_id' => $id, 'source_data_id' => (int) $previous->id, 'url' => $previous->url, 'excerpt' => 'Previous URL' ) );
		update_post_meta( $post_id, 'aips_source_snapshots_used', $metadata );
		$this->assertSame( 2, $this->repo->count_prunable_archive( 10 ) );
		$this->assertSame( 2, $this->repo->prune_archive( 10 ) );
		$this->assertNull( $this->repo->get_by_id( $previous->id ) );
		$this->assertNull( $this->repo->get_by_id( $failed->id ) );
		$this->assertNotNull( $this->repo->get_by_id( $usable->id ) );
		$this->assertNotNull( $this->repo->get_by_id( $boundary->id ) );
		$this->assertSame( $metadata, get_post_meta( $post_id, 'aips_source_snapshots_used', true ) );
	}

}
