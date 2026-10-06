<?php
/**
 * Tests for AIPS_Link_Url_Resolver.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Link_Url_Resolver extends WP_UnitTestCase {

	/** @var AIPS_Link_Url_Resolver */
	private $resolver;

	public function setUp(): void {
		parent::setUp();
		$this->set_permalink_structure( '/%postname%/' );
		wp_cache_flush();
		$this->resolver = new AIPS_Link_Url_Resolver();
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_link_resolver_url' );
		remove_all_filters( 'aips_link_resolver_internal_hosts' );
		remove_all_filters( 'url_to_postid' );
		parent::tearDown();
	}

	private function home_host(): string {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	public function test_normalize_relative_protocol_relative_and_fragments() {
		$home = untrailingslashit( home_url() );

		$this->assertSame( $home . '/guide/', $this->resolver->normalize( '/guide/#section' ) );
		$this->assertSame( $home . '/b/c/', $this->resolver->normalize( '/a/../b/./c/' ) );
		$this->assertSame( $home . '/guide/?x=1', $this->resolver->normalize( 'guide/?x=1' ) );
		$this->assertSame( 'http://cdn.example.org/x', $this->resolver->normalize( '//cdn.example.org/x' ) );
		$this->assertSame( '', $this->resolver->normalize( '#only-fragment' ) );
		$this->assertSame( '', $this->resolver->normalize( 'ftp://example.org/file' ) );
	}

	public function test_is_internal_ignores_scheme_www_and_case() {
		$host = $this->home_host();

		$this->assertTrue( $this->resolver->is_internal( 'https://' . strtoupper( $host ) . '/x/' ) );
		$this->assertTrue( $this->resolver->is_internal( 'http://www.' . $host . '/x/' ) );
		$this->assertFalse( $this->resolver->is_internal( 'https://not-' . $host . '/x/' ) );
	}

	public function test_internal_hosts_filter() {
		add_filter( 'aips_link_resolver_internal_hosts', function ( $hosts ) {
			$hosts[] = 'fr.example.org';
			return $hosts;
		} );

		$resolver = new AIPS_Link_Url_Resolver();

		$this->assertTrue( $resolver->is_internal( 'https://fr.example.org/page/' ) );
	}

	public function test_resolve_link_external() {
		$row = $this->resolver->resolve_link( 'https://wordpress.org/plugins/#tab' );

		$this->assertSame(
			array( 'target_url' => 'https://wordpress.org/plugins/', 'link_type' => 'external', 'target_post_id' => 0 ),
			$row
		);
		$this->assertNull( $this->resolver->resolve_link( 'mailto:a@b.c' ) );
	}

	public function test_resolves_pretty_permalink_relative_and_www_variants() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'semantic-linking' ) );
		$host    = $this->home_host();

		$this->assertSame( $post_id, $this->resolver->resolve_link( get_permalink( $post_id ) )['target_post_id'] );
		$this->assertSame( $post_id, $this->resolver->resolve_link( '/semantic-linking/' )['target_post_id'] );
		$this->assertSame( $post_id, ( new AIPS_Link_Url_Resolver() )->resolve_link( 'https://www.' . $host . '/semantic-linking/' )['target_post_id'] );
	}

	public function test_resolves_query_id_links() {
		$post_id = self::factory()->post->create();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( $post_id, $this->resolver->resolve_post_id( home_url( '/?p=' . $post_id ) ) );
		$this->assertSame( $page_id, $this->resolver->resolve_post_id( home_url( '/?page_id=' . $page_id ) ) );
		$this->assertSame( 0, $this->resolver->resolve_post_id( home_url( '/?p=999999' ) ) );
	}

	public function test_resolves_old_slug() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'new-slug' ) );
		add_post_meta( $post_id, '_wp_old_slug', 'old-slug' );

		$this->assertSame( $post_id, $this->resolver->resolve_post_id( home_url( '/old-slug/' ) ) );
	}

	public function test_unknown_internal_url_is_broken_link() {
		$row = $this->resolver->resolve_link( '/does-not-exist-anywhere/' );

		$this->assertSame( 'internal', $row['link_type'] );
		$this->assertSame( 0, $row['target_post_id'] );
	}

	public function test_resolution_is_cached_per_request_and_in_object_cache() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'cached-post' ) );
		$url     = home_url( '/cached-post/' );
		$calls   = 0;

		add_filter( 'url_to_postid', function ( $u ) use ( &$calls ) {
			$calls++;
			return $u;
		} );

		$this->assertSame( $post_id, $this->resolver->resolve_post_id( $url ) );
		$this->assertSame( $post_id, $this->resolver->resolve_post_id( $url ) );
		$this->assertSame( 1, $calls, 'Second lookup should hit the per-request cache.' );

		$this->assertSame( $post_id, ( new AIPS_Link_Url_Resolver() )->resolve_post_id( $url ) );
		$this->assertSame( 1, $calls, 'A new resolver should hit the object cache.' );
	}

	public function test_url_filter_can_rewrite_before_resolution() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'translated' ) );

		add_filter( 'aips_link_resolver_url', function ( $url ) {
			return str_replace( '/fr/', '/', $url );
		} );

		$this->assertSame( $post_id, $this->resolver->resolve_link( '/fr/translated/' )['target_post_id'] );
	}
}
