<?php
/**
 * Tests for AIPS_Sources_Fetcher.
 *
 * Validates the HTML cleaning pipeline (title extraction, meta description,
 * text extraction), the content-type detection / routing logic, the RSS/Atom
 * and JSON parsers, and the success/failure result paths using WP_HTTP mock.
 *
 * These tests do NOT make real HTTP calls. All WP HTTP responses are simulated
 * through WordPress's pre_http_request filter.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */
class Test_AIPS_Sources_Fetcher extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Build a minimal WP_HTTP response array that mimics a successful page.
	 *
	 * @param string $body        Response body.
	 * @param int    $status_code HTTP status code (default 200).
	 * @param array  $headers     Optional response headers.
	 * @return array WP HTTP response array.
	 */
	private function make_http_response( $body, $status_code = 200, $headers = array() ) {
		return array(
			'body'     => $body,
			'response' => array(
				'code'    => $status_code,
				'message' => 'OK',
			),
			'headers'  => $headers,
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Build a minimal source stdClass row.
	 *
	 * @param int    $id  Source row ID.
	 * @param string $url Source URL.
	 * @return object
	 */
	private function make_source( $id, $url ) {
		$source      = new stdClass();
		$source->id  = $id;
		$source->url = $url;
		return $source;
	}

	// ------------------------------------------------------------------
	// HTML parsing helpers (via reflection to call private methods)
	// ------------------------------------------------------------------

	/** @test */
	public function test_extract_page_title_returns_title_text() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_page_title' );
		$method->setAccessible( true );

		$html  = '<html><head><title>Hello World</title></head><body>body</body></html>';
		$title = $method->invoke( $fetcher, $html );

		$this->assertSame( 'Hello World', $title );
	}

	/** @test */
	public function test_extract_page_title_returns_empty_when_no_title_tag() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_page_title' );
		$method->setAccessible( true );

		$title = $method->invoke( $fetcher, '<html><body>no title</body></html>' );
		$this->assertSame( '', $title );
	}

	/** @test */
	public function test_extract_meta_description_returns_content_attribute() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_meta_description' );
		$method->setAccessible( true );

		$html = '<html><head><meta name="description" content="A great article about testing."></head></html>';
		$desc = $method->invoke( $fetcher, $html );

		$this->assertSame( 'A great article about testing.', $desc );
	}

	/** @test */
	public function test_extract_meta_description_supports_reversed_attribute_order() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_meta_description' );
		$method->setAccessible( true );

		$html = '<meta content="Reversed attr order." name="description">';
		$desc = $method->invoke( $fetcher, $html );

		$this->assertSame( 'Reversed attr order.', $desc );
	}

	/** @test */
	public function test_extract_text_strips_script_and_style_tags() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_text' );
		$method->setAccessible( true );

		$html = '<body><p>Real content.</p><script>alert("no")</script><style>.x{color:red}</style></body>';
		$text = $method->invoke( $fetcher, $html );

		$this->assertStringContainsString( 'Real content', $text );
		$this->assertStringNotContainsString( 'alert', $text );
		$this->assertStringNotContainsString( 'color', $text );
	}

	/** @test */
	public function test_extract_text_strips_nav_footer_aside() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_text' );
		$method->setAccessible( true );

		$html = '<body>'
			. '<nav><a href="/">Home</a></nav>'
			. '<article><p>Main article body.</p></article>'
			. '<footer>Footer text</footer>'
			. '<aside>Sidebar</aside>'
			. '</body>';
		$text = $method->invoke( $fetcher, $html );

		$this->assertStringContainsString( 'Main article body', $text );
		$this->assertStringNotContainsString( 'Footer text', $text );
		$this->assertStringNotContainsString( 'Sidebar', $text );
		$this->assertStringNotContainsString( 'Home', $text );
	}

	/** @test */
	public function test_extract_text_collapses_whitespace() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'extract_text' );
		$method->setAccessible( true );

		$html = '<body><p>Word   with   spaces.</p></body>';
		$text = $method->invoke( $fetcher, $html );

		$this->assertStringNotContainsString( '   ', $text );
	}

	// ------------------------------------------------------------------
	// fetch() — invalid source guards
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_returns_error_for_source_with_no_id() {
		$source      = new stdClass();
		$source->id  = 0;
		$source->url = 'https://example.com';

		$fetcher = new AIPS_Sources_Fetcher(
			new AIPS_Sources_Data_Repository(),
			new AIPS_Sources_Repository()
		);
		$result  = $fetcher->fetch( $source );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['error'] );
	}

	/** @test */
	public function test_fetch_returns_error_for_source_with_empty_url() {
		$source      = new stdClass();
		$source->id  = 1;
		$source->url = '';

		$fetcher = new AIPS_Sources_Fetcher(
			new AIPS_Sources_Data_Repository(),
			new AIPS_Sources_Repository()
		);
		$result  = $fetcher->fetch( $source );

		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// fetch() — WP_Error HTTP response
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_handles_wp_error_response() {
		if ( ! function_exists( 'wp_safe_remote_get' ) || ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_safe_remote_get() is not available in this test environment.' );
		}
		// Intercept the HTTP call and return a WP_Error.
		$filter_cb = function ( $preempt, $args, $url ) {
			return new WP_Error( 'http_request_failed', 'Could not connect.' );
		};
		add_filter( 'pre_http_request', $filter_cb, 10, 3 );

		$mock_data_repo    = $this->createMock( AIPS_Sources_Data_Repository::class );
		$mock_sources_repo = $this->createMock( AIPS_Sources_Repository::class );

		$mock_data_repo->expects( $this->once() )
			->method( 'mark_fetch_failed' )
			->with( 1, $this->anything(), 0 );

		$mock_sources_repo->expects( $this->once() )
			->method( 'update_after_fetch' )
			->with( 1, false );

		$fetcher = new AIPS_Sources_Fetcher( $mock_data_repo, $mock_sources_repo );
		$result  = $fetcher->fetch( $this->make_source( 1, 'https://example.com' ) );

		remove_filter( 'pre_http_request', $filter_cb, 10 );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Could not connect', $result['error'] );
	}

	// ------------------------------------------------------------------
	// fetch() — HTTP 4xx / 5xx response
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_handles_http_404_response() {
		if ( ! function_exists( 'wp_safe_remote_get' ) || ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_safe_remote_get() is not available in this test environment.' );
		}
		$http_response = $this->make_http_response( '<html><body>Not found</body></html>', 404 );

		$filter_cb = function ( $preempt, $args, $url ) use ( $http_response ) {
			return $http_response;
		};
		add_filter( 'pre_http_request', $filter_cb, 10, 3 );

		$mock_data_repo    = $this->createMock( AIPS_Sources_Data_Repository::class );
		$mock_sources_repo = $this->createMock( AIPS_Sources_Repository::class );

		$mock_data_repo->expects( $this->once() )
			->method( 'mark_fetch_failed' )
			->with( 5, $this->anything(), 404 );

		$mock_sources_repo->expects( $this->once() )
			->method( 'update_after_fetch' )
			->with( 5, false );

		$fetcher = new AIPS_Sources_Fetcher( $mock_data_repo, $mock_sources_repo );
		$result  = $fetcher->fetch( $this->make_source( 5, 'https://example.com/404' ) );

		remove_filter( 'pre_http_request', $filter_cb, 10 );

		$this->assertFalse( $result['success'] );
	}

	// ------------------------------------------------------------------
	// fetch() — successful response
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_success_calls_insert_if_new_and_update_after_fetch() {
		if ( ! function_exists( 'wp_safe_remote_get' ) || ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_safe_remote_get() is not available in this test environment.' );
		}
		$html = '<!DOCTYPE html><html><head>'
			. '<title>Test Page</title>'
			. '<meta name="description" content="A test description.">'
			. '</head><body><p>This is the article content.</p></body></html>';

		$http_response = $this->make_http_response( $html, 200 );

		$filter_cb = function ( $preempt, $args, $url ) use ( $http_response ) {
			return $http_response;
		};
		add_filter( 'pre_http_request', $filter_cb, 10, 3 );

		$mock_data_repo    = $this->createMock( AIPS_Sources_Data_Repository::class );
		$mock_sources_repo = $this->createMock( AIPS_Sources_Repository::class );

		$mock_data_repo->expects( $this->once() )
			->method( 'insert_if_new' )
			->with(
				10,
				$this->callback( function ( $data ) {
					return $data['fetch_status'] === 'success'
						&& $data['page_title'] === 'Test Page'
						&& str_contains( $data['extracted_text'], 'article content' )
						&& $data['http_status'] === 200;
				} )
			)
			->willReturn( true );

		$mock_sources_repo->expects( $this->once() )
			->method( 'update_after_fetch' )
			->with( 10, true );

		$fetcher = new AIPS_Sources_Fetcher( $mock_data_repo, $mock_sources_repo );
		$result  = $fetcher->fetch( $this->make_source( 10, 'https://example.com' ) );

		remove_filter( 'pre_http_request', $filter_cb, 10 );

		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['char_count'] );
		$this->assertEmpty( $result['error'] );
	}

	// ------------------------------------------------------------------
	// Content-type detection
	// ------------------------------------------------------------------

	/** @test */
	public function test_detect_content_format_identifies_rss_from_header() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'detect_content_format' );
		$method->setAccessible( true );

		$this->assertSame( 'feed', $method->invoke( $fetcher, 'application/rss+xml; charset=utf-8', '' ) );
		$this->assertSame( 'feed', $method->invoke( $fetcher, 'application/atom+xml', '' ) );
		$this->assertSame( 'feed', $method->invoke( $fetcher, 'text/xml', '' ) );
	}

	/** @test */
	public function test_detect_content_format_identifies_json_from_header() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'detect_content_format' );
		$method->setAccessible( true );

		$this->assertSame( 'json', $method->invoke( $fetcher, 'application/json', '' ) );
		$this->assertSame( 'json', $method->invoke( $fetcher, 'application/feed+json', '' ) );
	}

	/** @test */
	public function test_detect_content_format_sniffs_rss_from_body() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'detect_content_format' );
		$method->setAccessible( true );

		$rss_body  = '<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>';
		$atom_body = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>';

		$this->assertSame( 'feed', $method->invoke( $fetcher, 'text/plain', $rss_body ) );
		$this->assertSame( 'feed', $method->invoke( $fetcher, 'text/plain', $atom_body ) );
		$this->assertSame( 'feed', $method->invoke( $fetcher, '', '<rss version="2.0"></rss>' ) );
	}

	/** @test */
	public function test_detect_content_format_sniffs_json_from_body() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'detect_content_format' );
		$method->setAccessible( true );

		$this->assertSame( 'json', $method->invoke( $fetcher, '', '{"title":"hello"}' ) );
		$this->assertSame( 'json', $method->invoke( $fetcher, '', '[{"id":1}]' ) );
	}

	/** @test */
	public function test_detect_content_format_defaults_to_html() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'detect_content_format' );
		$method->setAccessible( true );

		$this->assertSame( 'html', $method->invoke( $fetcher, 'text/html', '<html><body></body></html>' ) );
		$this->assertSame( 'html', $method->invoke( $fetcher, '', '<html><body>test</body></html>' ) );
	}

	// ------------------------------------------------------------------
	// RSS / Atom parsers
	// ------------------------------------------------------------------

	/** @test */
	public function test_parse_feed_extracts_rss2_items() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_feed' );
		$method->setAccessible( true );

		$rss = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<rss version="2.0">'
			. '<channel>'
			. '<title>My Blog</title>'
			. '<description>A great blog.</description>'
			. '<item>'
			. '<title>First Post</title>'
			. '<description>This is the first post summary.</description>'
			. '</item>'
			. '<item>'
			. '<title>Second Post</title>'
			. '<description>This is the second post summary.</description>'
			. '</item>'
			. '</channel>'
			. '</rss>';

		$result = $method->invoke( $fetcher, $rss );

		$this->assertSame( 'My Blog', $result['page_title'] );
		$this->assertSame( 'A great blog.', $result['meta_description'] );
		$this->assertStringContainsString( 'First Post', $result['extracted_text'] );
		$this->assertStringContainsString( 'first post summary', $result['extracted_text'] );
		$this->assertStringContainsString( 'Second Post', $result['extracted_text'] );
	}

	/** @test */
	public function test_parse_feed_extracts_atom_entries() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_feed' );
		$method->setAccessible( true );

		$atom = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<feed xmlns="http://www.w3.org/2005/Atom">'
			. '<title>Atom Feed</title>'
			. '<subtitle>An Atom feed subtitle.</subtitle>'
			. '<entry>'
			. '<title>Atom Entry One</title>'
			. '<summary>Summary of entry one.</summary>'
			. '</entry>'
			. '<entry>'
			. '<title>Atom Entry Two</title>'
			. '<summary>Summary of entry two.</summary>'
			. '</entry>'
			. '</feed>';

		$result = $method->invoke( $fetcher, $atom );

		$this->assertSame( 'Atom Feed', $result['page_title'] );
		$this->assertStringContainsString( 'Atom Entry One', $result['extracted_text'] );
		$this->assertStringContainsString( 'Summary of entry one', $result['extracted_text'] );
		$this->assertStringContainsString( 'Atom Entry Two', $result['extracted_text'] );
	}

	/** @test */
	public function test_parse_feed_returns_empty_for_malformed_xml() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_feed' );
		$method->setAccessible( true );

		$result = $method->invoke( $fetcher, 'this is not xml at all' );

		$this->assertSame( '', $result['page_title'] );
		$this->assertSame( '', $result['extracted_text'] );
	}

	// ------------------------------------------------------------------
	// JSON parsers
	// ------------------------------------------------------------------

	/** @test */
	public function test_parse_json_extracts_wp_rest_api_single_post() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_json' );
		$method->setAccessible( true );

		$json = json_encode( array(
			'id'      => 42,
			'title'   => array( 'rendered' => 'My WP Post' ),
			'excerpt' => array( 'rendered' => '<p>Post excerpt here.</p>' ),
			'content' => array( 'rendered' => '<p>Full post content goes here.</p>' ),
		) );

		$result = $method->invoke( $fetcher, $json );

		$this->assertSame( 'My WP Post', $result['page_title'] );
		$this->assertSame( 'Post excerpt here.', $result['meta_description'] );
		$this->assertStringContainsString( 'Full post content', $result['extracted_text'] );
	}

	/** @test */
	public function test_parse_json_extracts_wp_rest_api_post_array() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_json' );
		$method->setAccessible( true );

		$json = json_encode( array(
			array(
				'id'      => 1,
				'title'   => array( 'rendered' => 'Post Alpha' ),
				'excerpt' => array( 'rendered' => '<p>Alpha excerpt.</p>' ),
			),
			array(
				'id'      => 2,
				'title'   => array( 'rendered' => 'Post Beta' ),
				'excerpt' => array( 'rendered' => '<p>Beta excerpt.</p>' ),
			),
		) );

		$result = $method->invoke( $fetcher, $json );

		$this->assertStringContainsString( 'Post Alpha', $result['extracted_text'] );
		$this->assertStringContainsString( 'Alpha excerpt', $result['extracted_text'] );
		$this->assertStringContainsString( 'Post Beta', $result['extracted_text'] );
	}

	/** @test */
	public function test_parse_json_extracts_json_feed_items() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_json' );
		$method->setAccessible( true );

		$json = json_encode( array(
			'version'     => 'https://jsonfeed.org/version/1.1',
			'title'       => 'JSON Feed Blog',
			'description' => 'A blog using JSON Feed.',
			'items'       => array(
				array(
					'id'           => '1',
					'title'        => 'JSON Feed Item One',
					'content_text' => 'Plain text content for item one.',
				),
				array(
					'id'           => '2',
					'title'        => 'JSON Feed Item Two',
					'content_html' => '<p>HTML content for item two.</p>',
				),
			),
		) );

		$result = $method->invoke( $fetcher, $json );

		$this->assertSame( 'JSON Feed Blog', $result['page_title'] );
		$this->assertSame( 'A blog using JSON Feed.', $result['meta_description'] );
		$this->assertStringContainsString( 'JSON Feed Item One', $result['extracted_text'] );
		$this->assertStringContainsString( 'Plain text content for item one', $result['extracted_text'] );
		$this->assertStringContainsString( 'JSON Feed Item Two', $result['extracted_text'] );
		$this->assertStringContainsString( 'HTML content for item two', $result['extracted_text'] );
	}

	/** @test */
	public function test_parse_json_returns_empty_for_invalid_json() {
		$fetcher    = new AIPS_Sources_Fetcher();
		$reflection = new ReflectionClass( $fetcher );
		$method     = $reflection->getMethod( 'parse_json' );
		$method->setAccessible( true );

		$result = $method->invoke( $fetcher, 'not valid json {{{' );

		$this->assertSame( '', $result['page_title'] );
		$this->assertSame( '', $result['extracted_text'] );
	}

	// ------------------------------------------------------------------
	// fetch() — RSS integration (full pipeline)
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_routes_rss_feed_via_content_type_header() {
		if ( ! function_exists( 'wp_safe_remote_get' ) || ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_safe_remote_get() is not available in this test environment.' );
		}

		$rss_body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<rss version="2.0"><channel>'
			. '<title>My RSS Feed</title>'
			. '<description>A feed description.</description>'
			. '<item><title>Feed Item One</title><description>Item one desc.</description></item>'
			. '</channel></rss>';

		$http_response = $this->make_http_response(
			$rss_body,
			200,
			array( 'content-type' => 'application/rss+xml; charset=utf-8' )
		);

		$filter_cb = function ( $preempt, $args, $url ) use ( $http_response ) {
			return $http_response;
		};
		add_filter( 'pre_http_request', $filter_cb, 10, 3 );

		$mock_data_repo    = $this->createMock( AIPS_Sources_Data_Repository::class );
		$mock_sources_repo = $this->createMock( AIPS_Sources_Repository::class );

		$mock_data_repo->expects( $this->once() )
			->method( 'insert_if_new' )
			->with(
				20,
				$this->callback( function ( $data ) {
					return 'My RSS Feed' === $data['page_title']
						&& str_contains( $data['extracted_text'], 'Feed Item One' )
						&& 'success' === $data['fetch_status'];
				} )
			)
			->willReturn( true );

		$mock_sources_repo->expects( $this->once() )
			->method( 'update_after_fetch' )
			->with( 20, true );

		$fetcher = new AIPS_Sources_Fetcher( $mock_data_repo, $mock_sources_repo );
		$result  = $fetcher->fetch( $this->make_source( 20, 'https://example.com/feed' ) );

		remove_filter( 'pre_http_request', $filter_cb, 10 );

		$this->assertTrue( $result['success'] );
	}
}
