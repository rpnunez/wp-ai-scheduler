<?php
/**
 * Test AIPS_Internal_Links_Controller ajax_get_suggestions post cache priming and query efficiency.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Internal_Links_Controller_Suggestions_Query_Count extends WP_UnitTestCase {

	private $controller;
	private $links_repo;

	public function setUp(): void {
		parent::setUp();
		AIPS_DB_Manager::install_tables();
		$this->links_repo = new AIPS_Internal_Links_Repository();
		$this->controller = new AIPS_Internal_Links_Controller(
			null,
			$this->links_repo
		);

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'edit-post' );
	}

	public function test_ajax_get_suggestions_enriches_items_correctly() {
		global $wpdb;

		$source_id = self::factory()->post->create( array(
			'post_title'  => 'Source Post Title',
			'post_status' => 'publish',
		) );

		$target_id = self::factory()->post->create( array(
			'post_title'  => 'Target Post Title',
			'post_status' => 'publish',
		) );

		$wpdb->insert(
			$wpdb->prefix . 'aips_internal_links',
			array(
				'source_post_id'   => $source_id,
				'target_post_id'   => $target_id,
				'anchor_text'      => 'Test Anchor',
				'similarity_score' => 0.90,
				'status'           => 'pending',
				'created_at'       => time(),
				'updated_at'       => time(),
			)
		);

		$_POST['nonce']    = wp_create_nonce( 'aips_ajax_nonce' );
		$_REQUEST['nonce'] = $_POST['nonce'];
		$_POST['page']     = 1;
		$_POST['per_page'] = 10;
		$_POST['status']   = '';
		$_POST['search']   = '';
		$_POST['origin']   = '';

		try {
			ob_start();
			$this->controller->ajax_get_suggestions();
			$output = ob_get_clean();
		} catch ( WPDieException $e ) {
			$output = ob_get_clean();
		} catch ( Exception $e ) {
			$output = ob_get_clean();
		} catch ( Throwable $e ) {
			$output = ob_get_clean();
		}

		$decoded = json_decode( $output, true );
		$this->assertTrue( $decoded['success'] );
		$this->assertCount( 1, $decoded['data']['items'] );

		$item = $decoded['data']['items'][0];
		$this->assertEquals( $source_id, $item['source_post_id'] );
		$this->assertEquals( $target_id, $item['target_post_id'] );
		$this->assertStringContainsString( 'post=' . $source_id, $item['source_edit_url'] );
		$this->assertStringContainsString( 'post=' . $target_id, $item['target_edit_url'] );
		$this->assertEquals( get_permalink( $target_id ), $item['target_url'] );
	}

	public function test_ajax_get_suggestions_query_count_is_bounded() {
		global $wpdb;

		$query_counts = array();

		foreach ( array( 10, 100 ) as $n ) {
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aips_internal_links" );
			$wpdb->query( "TRUNCATE TABLE {$wpdb->posts}" );
			clean_post_cache( 0 );
			wp_cache_flush();

			$post_ids = array();
			for ( $i = 0; $i < $n + 1; $i++ ) {
				$post_ids[] = self::factory()->post->create( array(
					'post_title'  => "Post {$i}",
					'post_status' => 'publish',
				) );
			}

			$source_id = $post_ids[0];
			for ( $i = 1; $i <= $n; $i++ ) {
				$target_id = $post_ids[$i];
				$wpdb->insert(
					$wpdb->prefix . 'aips_internal_links',
					array(
						'source_post_id'   => $source_id,
						'target_post_id'   => $target_id,
						'anchor_text'      => "link {$i}",
						'similarity_score' => 0.85,
						'status'           => 'pending',
						'created_at'       => time(),
						'updated_at'       => time(),
					)
				);
			}

			_prime_post_caches( array(), false, false );
			wp_cache_flush();

			$_POST['nonce']    = wp_create_nonce( 'aips_ajax_nonce' );
			$_REQUEST['nonce'] = $_POST['nonce'];
			$_POST['page']     = 1;
			$_POST['per_page'] = $n;
			$_POST['status']   = '';
			$_POST['search']   = '';
			$_POST['origin']   = '';

			$num_queries_before = $wpdb->num_queries;

			try {
				ob_start();
				$this->controller->ajax_get_suggestions();
				$output = ob_get_clean();
			} catch ( WPDieException $e ) {
				$output = ob_get_clean();
			} catch ( Exception $e ) {
				$output = ob_get_clean();
			} catch ( Throwable $e ) {
				$output = ob_get_clean();
			}

			$queries_executed = $wpdb->num_queries - $num_queries_before;
			$query_counts[ $n ] = $queries_executed;
		}

		// N=10 and N=100 must execute the exact same number of DB queries (O(1) complexity)
		$this->assertEquals( $query_counts[10], $query_counts[100] );
	}
}
