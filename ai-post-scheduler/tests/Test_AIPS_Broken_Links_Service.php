<?php
/**
 * Tests for AIPS_Broken_Links_Service.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Broken_Links_Service extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		delete_option( AIPS_Broken_Links_Service::FIXES_OPTION );
		parent::tear_down();
	}

	/**
	 * Verify that default constructor creates an instance of AIPS_Link_Insertion_Engine.
	 */
	public function test_construct_defaults() {
		$service = new AIPS_Broken_Links_Service();

		$ref  = new ReflectionClass( $service );
		$prop = $ref->getProperty( 'engine' );
		$prop->setAccessible( true );

		$engine = $prop->getValue( $service );
		$this->assertInstanceOf( AIPS_Link_Insertion_Engine::class, $engine );
	}

	/**
	 * Verify that constructor resolves AIPS_Link_Insertion_Engine from container if bound.
	 */
	public function test_construct_resolves_engine_from_container() {
		$container     = AIPS_Container::get_instance();
		$custom_engine = new AIPS_Link_Insertion_Engine();
		$container->bind( AIPS_Link_Insertion_Engine::class, function () use ( $custom_engine ) {
			return $custom_engine;
		} );

		try {
			$service = new AIPS_Broken_Links_Service();

			$ref  = new ReflectionClass( $service );
			$prop = $ref->getProperty( 'engine' );
			$prop->setAccessible( true );

			$engine = $prop->getValue( $service );
			$this->assertSame( $custom_engine, $engine );
		} finally {
			// Clean up binding using reflection so container state is preserved
			$ref_c   = new ReflectionClass( $container );
			$prop_cb = $ref_c->getProperty( 'bindings' );
			$prop_cb->setAccessible( true );
			$bindings = $prop_cb->getValue( $container );
			unset( $bindings[ AIPS_Link_Insertion_Engine::class ] );
			$prop_cb->setValue( $container, $bindings );
		}
	}

	/**
	 * Verify that constructor accepts an explicit injected AIPS_Link_Insertion_Engine.
	 */
	public function test_construct_accepts_injected_engine() {
		$custom_engine = new AIPS_Link_Insertion_Engine();
		$service       = new AIPS_Broken_Links_Service( null, null, null, $custom_engine );

		$ref  = new ReflectionClass( $service );
		$prop = $ref->getProperty( 'engine' );
		$prop->setAccessible( true );

		$engine = $prop->getValue( $service );
		$this->assertSame( $custom_engine, $engine );
	}

	/**
	 * Verify that undo() delegates to the injected AIPS_Link_Insertion_Engine.
	 */
	public function test_undo_uses_injected_engine() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post For Broken Links Undo',
				'post_content' => 'Here is a new link: <a href="https://example.com/new">New Target</a> in text.',
			)
		);

		$fix_id = 'fix_test_123';
		$fixes  = array(
			$fix_id => array(
				'action'    => 'repoint',
				'source_id' => $post_id,
				'old_url'   => 'https://example.com/broken',
				'new_url'   => 'https://example.com/new',
				'time'      => time(),
				'changes'   => array(
					array(
						'before' => 'old text with <a href="https://example.com/broken">Broken Target</a> in text.',
						'after'  => 'Here is a new link: <a href="https://example.com/new">New Target</a> in text.',
					),
				),
			),
		);

		update_option( AIPS_Broken_Links_Service::FIXES_OPTION, $fixes );

		$mock_engine = $this->createMock( AIPS_Link_Insertion_Engine::class );
		$mock_engine->expects( $this->once() )
			->method( 'revert' )
			->with(
				$this->isType( 'string' ),
				'old text with <a href="https://example.com/broken">Broken Target</a> in text.',
				'Here is a new link: <a href="https://example.com/new">New Target</a> in text.'
			)
			->willReturn(
				array(
					'status'  => AIPS_Link_Insertion_Engine::REVERT_OK,
					'content' => 'old text with <a href="https://example.com/broken">Broken Target</a> in text.',
				)
			);

		$service = new AIPS_Broken_Links_Service( null, null, null, $mock_engine );
		$result  = $service->undo( $fix_id );

		$this->assertTrue( $result );

		$updated_post = get_post( $post_id );
		$this->assertEquals( 'old text with <a href="https://example.com/broken">Broken Target</a> in text.', $updated_post->post_content );

		$updated_fixes = get_option( AIPS_Broken_Links_Service::FIXES_OPTION );
		$this->assertTrue( $updated_fixes[ $fix_id ]['undone'] );
	}
}
