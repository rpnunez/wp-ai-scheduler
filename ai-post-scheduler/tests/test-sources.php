<?php
/**
 * Tests for AIPS_Sources_Repository and the prompt builder sources integration.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Sources extends WP_UnitTestCase {

	/** @var AIPS_Sources_Repository */
	private $repo;

	public function setUp(): void {
		parent::setUp();
		$this->repo = new AIPS_Sources_Repository();

		// Ensure the table exists (created via dbDelta during install).
		AIPS_DB_Manager::install_tables();
	}

	public function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_sources';
		$wpdb->query( "DELETE FROM $table" );

		// Clean up any content strategy options set during tests.
		foreach ( array_keys( AIPS_Settings::get_content_strategy_options() ) as $key ) {
			delete_option( $key );
		}

		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Repository – create / read
	// ------------------------------------------------------------------

	/** @test */
	public function test_create_returns_integer_id() {
		$id = $this->repo->create( array( 'url' => 'https://example.com' ) );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/** @test */
	public function test_get_by_id_returns_source() {
		$id     = $this->repo->create( array( 'url' => 'https://example.com', 'label' => 'Example' ) );
		$source = $this->repo->get_by_id( $id );

		$this->assertNotNull( $source );
		$this->assertSame( 'https://example.com', $source->url );
		$this->assertSame( 'Example', $source->label );
	}

	/** @test */
	public function test_get_all_returns_all_sources() {
		$this->repo->create( array( 'url' => 'https://alpha.example.com', 'is_active' => 1 ) );
		$this->repo->create( array( 'url' => 'https://beta.example.com', 'is_active' => 0 ) );

		$all = $this->repo->get_all( false );
		$this->assertCount( 2, $all );
	}

	/** @test */
	public function test_get_all_active_only_excludes_inactive() {
		$this->repo->create( array( 'url' => 'https://active.example.com', 'is_active' => 1 ) );
		$this->repo->create( array( 'url' => 'https://inactive.example.com', 'is_active' => 0 ) );

		$active = $this->repo->get_all( true );
		$this->assertCount( 1, $active );
		$this->assertSame( 'https://active.example.com', $active[0]->url );
	}

	// ------------------------------------------------------------------
	// Repository – update
	// ------------------------------------------------------------------

	/** @test */
	public function test_update_changes_stored_values() {
		$id = $this->repo->create( array( 'url' => 'https://before.example.com', 'label' => 'Before' ) );

		$this->repo->update( $id, array( 'label' => 'After', 'description' => 'Updated notes' ) );
		$source = $this->repo->get_by_id( $id );

		$this->assertSame( 'After', $source->label );
		$this->assertSame( 'Updated notes', $source->description );
	}

	// ------------------------------------------------------------------
	// Repository – delete
	// ------------------------------------------------------------------

	/** @test */
	public function test_delete_removes_source() {
		$id = $this->repo->create( array( 'url' => 'https://delete-me.example.com' ) );

		$this->repo->delete( $id );

		$source = $this->repo->get_by_id( $id );
		$this->assertNull( $source );
	}

	// ------------------------------------------------------------------
	// Repository – set_active / url_exists
	// ------------------------------------------------------------------

	/** @test */
	public function test_set_active_toggles_status() {
		$id = $this->repo->create( array( 'url' => 'https://toggle.example.com', 'is_active' => 1 ) );

		$this->repo->set_active( $id, false );
		$source = $this->repo->get_by_id( $id );
		$this->assertSame( '0', (string) $source->is_active );

		$this->repo->set_active( $id, true );
		$source = $this->repo->get_by_id( $id );
		$this->assertSame( '1', (string) $source->is_active );
	}

	/** @test */
	public function test_url_exists_returns_true_for_duplicate() {
		$this->repo->create( array( 'url' => 'https://exists.example.com' ) );

		$this->assertTrue( $this->repo->url_exists( 'https://exists.example.com' ) );
	}

	/** @test */
	public function test_url_exists_excludes_own_id() {
		$id = $this->repo->create( array( 'url' => 'https://self.example.com' ) );

		// Should not flag as duplicate when excluding itself.
		$this->assertFalse( $this->repo->url_exists( 'https://self.example.com', $id ) );
	}

	/** @test */
	public function test_get_active_urls_returns_only_active_urls() {
		$this->repo->create( array( 'url' => 'https://active-a.example.com', 'is_active' => 1 ) );
		$this->repo->create( array( 'url' => 'https://active-b.example.com', 'is_active' => 1 ) );
		$this->repo->create( array( 'url' => 'https://inactive.example.com', 'is_active' => 0 ) );

		$urls = $this->repo->get_active_urls();

		$this->assertContains( 'https://active-a.example.com', $urls );
		$this->assertContains( 'https://active-b.example.com', $urls );
		$this->assertNotContains( 'https://inactive.example.com', $urls );
	}

	// ------------------------------------------------------------------
	// Prompt builder integration
	// ------------------------------------------------------------------

	/** @test */
	public function test_build_site_context_block_includes_trusted_sources() {
		update_option( 'aips_site_niche', 'Tech Blog' );
		$this->repo->create( array( 'url' => 'https://trusted.example.com', 'is_active' => 1 ) );

		$builder = new AIPS_Prompt_Builder( null, null, $this->repo );
		$block   = $builder->build_site_context_block();

		$this->assertStringContainsString( 'Trusted sources', $block );
		$this->assertStringContainsString( 'https://trusted.example.com', $block );
	}

	/** @test */
	public function test_build_site_context_block_omits_inactive_sources() {
		update_option( 'aips_site_niche', 'Tech Blog' );
		$this->repo->create( array( 'url' => 'https://inactive.example.com', 'is_active' => 0 ) );

		$builder = new AIPS_Prompt_Builder( null, null, $this->repo );
		$block   = $builder->build_site_context_block();

		$this->assertStringNotContainsString( 'https://inactive.example.com', $block );
	}

	/** @test */
	public function test_build_site_context_block_includes_multiple_sources() {
		update_option( 'aips_site_niche', 'Health' );
		$this->repo->create( array( 'url' => 'https://source-one.example.com', 'is_active' => 1 ) );
		$this->repo->create( array( 'url' => 'https://source-two.example.com', 'is_active' => 1 ) );

		$builder = new AIPS_Prompt_Builder( null, null, $this->repo );
		$block   = $builder->build_site_context_block();

		$this->assertStringContainsString( 'https://source-one.example.com', $block );
		$this->assertStringContainsString( 'https://source-two.example.com', $block );
	}

	/** @test */
	public function test_build_site_context_block_has_no_sources_section_when_none_configured() {
		update_option( 'aips_site_niche', 'Finance' );
		// No sources added.

		$builder = new AIPS_Prompt_Builder( null, null, $this->repo );
		$block   = $builder->build_site_context_block();

		$this->assertStringNotContainsString( 'Trusted sources', $block );
	}
}
