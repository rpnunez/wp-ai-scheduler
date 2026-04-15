<?php
/**
 * Tests for AIPS_Sources_Fetcher.
 *
 * Validates the HTML cleaning pipeline (title extraction, meta description,
 * text extraction) and the success/failure result paths using WP_HTTP mock.
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
	 * @param string $body        Response body HTML.
	 * @param int    $status_code HTTP status code (default 200).
	 * @return array WP HTTP response array.
	 */
	private function make_http_response( $body, $status_code = 200 ) {
		return array(
			'body'     => $body,
			'response' => array(
				'code'    => $status_code,
				'message' => 'OK',
			),
			'headers'  => array(),
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
		if ( ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_remote_get() is not available in this test environment.' );
		}
		// Intercept the HTTP call and return a WP_Error.
		add_filter( 'pre_http_request', function ( $preempt, $args, $url ) {
			return new WP_Error( 'http_request_failed', 'Could not connect.' );
		}, 10, 3 );

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

		remove_all_filters( 'pre_http_request' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Could not connect', $result['error'] );
	}

	// ------------------------------------------------------------------
	// fetch() — HTTP 4xx / 5xx response
	// ------------------------------------------------------------------

	/** @test */
	public function test_fetch_handles_http_404_response() {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_remote_get() is not available in this test environment.' );
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
	public function test_fetch_success_calls_upsert_and_update_after_fetch() {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			$this->markTestSkipped( 'wp_remote_get() is not available in this test environment.' );
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
			->method( 'upsert' )
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
		$this->assertGreaterThan( 0, $result['word_count'] );
		$this->assertEmpty( $result['error'] );
	}
}
