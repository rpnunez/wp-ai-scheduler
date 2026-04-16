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
	public function test_mark_fetch_failed_updates_status_but_not_extracted_text() {
		$this->repo->insert_if_new( 66, array(
			'url'            => 'https://example.com',
			'extracted_text' => 'Previously good content.',
			'fetch_status'   => 'success',
			'http_status'    => 200,
		) );

		$this->repo->mark_fetch_failed( 66, 'Server error.', 500 );

		$row = $this->repo->get_by_source_id( 66 );
		$this->assertEquals( 'failed', $row->fetch_status );
		$this->assertEquals( 'Server error.', $row->error_message );
		// Previously fetched content must still be intact.
		$this->assertEquals( 'Previously good content.', $row->extracted_text );
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
}
