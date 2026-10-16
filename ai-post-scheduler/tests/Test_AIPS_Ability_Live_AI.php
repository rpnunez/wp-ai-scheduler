<?php
/**
 * Opt-in live checks against the WordPress AI plugin's real abilities and a
 * real provider.
 *
 * These call a live AI provider (Google), so they never run by default. Point
 * them at a plugins directory that contains the `ai` and `ai-provider-for-google`
 * plugins and supply the key through the environment:
 *
 *   AIPS_LIVE_PLUGINS_DIR=/path/to/wp-content/plugins GOOGLE_API_KEY=... \
 *     vendor/bin/phpunit --group abilities-live-ai
 *
 * The plugins are only read (required) from that directory; everything the
 * tests write goes to the disposable test database, and the key is stored
 * there only for the duration of a test.
 *
 * @group abilities-live-ai
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Live_AI extends WP_UnitTestCase {

	/** @var bool Whether the plugin files were loaded in this PHP process. */
	private static $plugins_loaded = false;

	/** @var AIPS_Ability_Workflow_Repository */
	private $repository;

	/** @var int[] */
	private $workflow_ids = array();

	public function setUp(): void {
		parent::setUp();

		$dir = (string) getenv( 'AIPS_LIVE_PLUGINS_DIR' );
		$key = (string) getenv( 'GOOGLE_API_KEY' );

		if ( '' === $dir || '' === $key ) {
			$this->markTestSkipped( 'Set AIPS_LIVE_PLUGINS_DIR and GOOGLE_API_KEY to run the live AI checks.' );
		}

		if ( ! is_file( $dir . '/ai/ai.php' ) || ! is_file( $dir . '/ai-provider-for-google/plugin.php' ) ) {
			$this->markTestSkipped( 'AIPS_LIVE_PLUGINS_DIR must contain the ai and ai-provider-for-google plugins.' );
		}

		if ( ! class_exists( 'WP_Abilities_Registry' ) ) {
			$this->markTestSkipped( 'The WordPress Abilities API is not available.' );
		}

		$this->repository = AIPS_Ability_Workflow_Repository::instance();

		$this->boot_plugins( $dir );
		$this->enable_ai_features();
		$this->rebuild_registries();

		update_option( 'connectors_ai_google_api_key', $key );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		if ( ! wp_get_ability( 'ai/title-generation' ) ) {
			$this->markTestSkipped( 'ai/title-generation did not register (plugin version or requirements).' );
		}
	}

	public function tearDown(): void {
		foreach ( $this->workflow_ids as $workflow_id ) {
			$this->repository->delete_workflow( $workflow_id );
		}

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_workflows', 0 );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	private function boot_plugins( $dir ) {
		global $wp_actions;

		if ( empty( $wp_actions['init'] ) ) {
			$wp_actions['init'] = 1;
		}

		if ( ! self::$plugins_loaded ) {
			require_once $dir . '/ai-provider-for-google/plugin.php';
			require_once $dir . '/ai/ai.php';
			self::$plugins_loaded = true;
		}

		// These hooks were added after the real plugins_loaded/init had fired.
		\WordPress\AI\Main::get_instance()->load();

		if ( function_exists( 'WordPress\GoogleAiProvider\register_provider' ) ) {
			\WordPress\GoogleAiProvider\register_provider();
		}
	}

	private function enable_ai_features() {
		update_option( 'wpai_features_enabled', true );
		add_filter( 'wpai_features_enabled', '__return_true' );

		foreach ( array( 'title-generation', 'alt-text-generation', 'content-classification', 'excerpt-generation', 'summarization' ) as $id ) {
			add_filter( "wpai_feature_{$id}_enabled", '__return_true' );
			update_option( "wpai_feature_{$id}_enabled", true );
		}
	}

	private function rebuild_registries() {
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		\WordPress\AI\Main::get_instance()->initialize_features();

		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			$property = new ReflectionProperty( $class, 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}

	private function log( $label, $value ) {
		fwrite( STDERR, "\nLIVE " . $label . ': ' . ( is_string( $value ) ? $value : wp_json_encode( $value ) ) . "\n" );
	}

	// ---------------------------------------------------------------------
	// Adapter and catalog
	// ---------------------------------------------------------------------

	public function test_the_real_title_generation_ability_is_discovered_with_its_real_schema() {
		$ability = ( new AIPS_Ability_Catalog_Service( new AIPS_Ability_Service() ) )->get_ability( 'ai/title-generation' );

		$this->assertIsArray( $ability );
		$this->assertArrayHasKey( 'content', $ability['input_schema']['properties'] );
		$this->assertNotEmpty( $ability['output_schema'] );
		$this->log( 'title output schema', $ability['output_schema'] );
	}

	public function test_title_generation_returns_a_real_title_through_the_adapter() {
		$result = ( new AIPS_Ability_Service() )->invoke(
			'ai/title-generation',
			array( 'content' => 'A beginner guide to repairing a flat bicycle tyre: remove the wheel, find the puncture, patch the tube, and refit the tyre without pinching it.' )
		);

		$this->log( 'title result', $result );
		$this->assertNotWPError( $result );
		$this->assertNotEmpty( $result );
	}

	public function test_the_stress_test_ability_cases_pass_with_the_real_ai_abilities() {
		$cases = new AIPS_Stress_Test_Ability_Cases();

		$discovery = $cases->run( 'ability_discovery' );
		$this->log( 'stress discovery', $discovery['plugin_value'] );
		$this->assertSame( 'passed', $discovery['status'], (string) ( $discovery['error'] ?? '' ) );
		$this->assertContains( 'ai/title-generation', wp_list_pluck( $discovery['ai_value'], 'name' ) );

		$ai = $cases->run( 'ability_invoke_ai' );
		$this->log( 'stress ai', array( $ai['summary'], $ai['plugin_value'], $ai['ability_calls'] ) );
		$this->assertSame( 'passed', $ai['status'], (string) ( $ai['error'] ?? '' ) );
		$this->assertArrayNotHasKey( 'skipped', $ai['artifacts'] ?? array() );
		$this->assertGreaterThanOrEqual( 1, $ai['ability_calls'] );
		$this->assertSame( 'ok', $ai['plugin_value']['ai/title-generation']['status'] );
	}

	// ---------------------------------------------------------------------
	// Enrichment with real abilities
	// ---------------------------------------------------------------------

	/**
	 * Encode a PNG without needing GD: a red square on a white background.
	 *
	 * @return string PNG bytes.
	 */
	private function make_png() {
		$size = 96;
		$raw  = '';

		for ( $y = 0; $y < $size; $y++ ) {
			$raw .= "\x00";

			for ( $x = 0; $x < $size; $x++ ) {
				$inside = $x >= 24 && $x < 72 && $y >= 24 && $y < 72;
				$raw   .= $inside ? "\xDC\x14\x14" : "\xFF\xFF\xFF";
			}
		}

		$chunk = function ( $type, $data ) {
			return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
		};

		return "\x89PNG\r\n\x1a\n"
			. $chunk( 'IHDR', pack( 'NNCCCCC', $size, $size, 8, 2, 0, 0, 0 ) )
			. $chunk( 'IDAT', gzcompress( $raw ) )
			. $chunk( 'IEND', '' );
	}

	/**
	 * A post with a real, recognisable featured image (a red square on white).
	 *
	 * @return int[] Post ID, attachment ID.
	 */
	private function make_post_with_image() {
		$upload = wp_upload_bits( 'aips-live-square.png', null, $this->make_png() );
		$this->assertEmpty( $upload['error'], 'The test image must be written to uploads.' );

		$post_id       = self::factory()->post->create(
			array(
				'post_title'   => 'Repairing a flat bicycle tyre',
				'post_content' => 'Remove the wheel, find the puncture, patch the inner tube and refit the tyre without pinching it. Carry a spare tube and a small pump on every ride so a flat never ends your day.',
			)
		);
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'square',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id
		);
		update_post_meta( $post_id, '_thumbnail_id', $attachment_id );

		return array( $post_id, $attachment_id );
	}

	private function enrichment_service() {
		$abilities = new AIPS_Ability_Service();

		return new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ), null, null, '__return_true' );
	}

	public function test_alt_text_is_generated_for_a_real_image() {
		if ( ! wp_get_ability( AIPS_Ability_Enrichment_Service::ALT_TEXT_ABILITY ) ) {
			$this->markTestSkipped( 'ai/alt-text-generation did not register.' );
		}

		list( $post_id, $attachment_id ) = $this->make_post_with_image();

		$results = $this->enrichment_service()->enrich_post( $post_id );
		$this->log( 'alt text', array( $results['alt_text'], get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );

		$this->assertContains( $results['alt_text'], array( 'applied', 'skipped:decorative' ), 'The ability must answer; got ' . $results['alt_text'] );

		if ( 'applied' === $results['alt_text'] ) {
			$this->assertNotSame( '', trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
		}
	}

	public function test_categories_and_tags_are_suggested_from_the_real_ability() {
		if ( ! wp_get_ability( AIPS_Ability_Enrichment_Service::CLASSIFICATION_ABILITY ) ) {
			$this->markTestSkipped( 'ai/content-classification did not register.' );
		}

		list( $post_id ) = $this->make_post_with_image();
		foreach ( array( 'Cycling', 'Bicycle Repair', 'Cooking' ) as $name ) {
			self::factory()->term->create( array( 'taxonomy' => 'post_tag', 'name' => $name ) );
		}
		foreach ( array( 'Cycling', 'Recipes' ) as $name ) {
			self::factory()->term->create( array( 'taxonomy' => 'category', 'name' => $name ) );
		}

		$results = $this->enrichment_service()->enrich_post( $post_id );
		$tags    = wp_list_pluck( wp_get_object_terms( $post_id, 'post_tag' ), 'name' );
		$cats    = wp_list_pluck( wp_get_object_terms( $post_id, 'category' ), 'name' );
		$this->log( 'classification', array( 'outcome' => $results['classification'], 'tags' => $tags, 'categories' => $cats ) );

		$this->assertContains( $results['classification'], array( 'applied', 'skipped:no_suggestions' ), 'The ability must answer; got ' . $results['classification'] );
		$this->assertNotContains( 'Cooking', $tags, 'An unrelated existing tag must not be chosen.' );
	}

	public function test_enrichment_outcomes_for_an_editor_principal() {
		list( $post_id ) = $this->make_post_with_image();
		$editor          = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( 0 );

		$results = $this->enrichment_service()->enrich_post( $post_id, $editor );
		$this->log( 'editor outcomes', $results );

		$this->assertNotContains( 'skipped:no_principal', $results );
	}

	public function test_the_background_process_enriches_a_queued_post_with_real_abilities() {
		list( $post_id ) = $this->make_post_with_image();
		$user_id         = self::factory()->user->create( array( 'role' => 'editor' ) );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
		$queue = new AIPS_Ability_Enrichment_Queue_Repository();
		$queue->enqueue( $post_id, $user_id );
		wp_set_current_user( 0 );

		$abilities = new AIPS_Ability_Service();
		$service   = new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ), null, $queue );
		$process   = new AIPS_Ability_Enrichment_Process( new AIPS_Background_Process_Repository(), $queue, $service );

		$before = AIPS_Ability_Service::get_invocation_count();
		$this->assertNotWPError( $process->start() );
		$process->tick();

		$snapshot = $process->get_snapshot();
		$this->log( 'background process', array( 'status' => $snapshot['last_status'], 'processed' => $snapshot['processed'], 'failed' => $snapshot['failed'], 'ai_calls' => $snapshot['ai_calls_used'] ) );

		$this->assertSame( 0, $queue->count_pending() );
		$this->assertSame( 'completed', $snapshot['last_status'] );
		$this->assertGreaterThan( $before, AIPS_Ability_Service::get_invocation_count() );
		$this->assertGreaterThan( 0, $snapshot['ai_calls_used'] );

		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
	}

	// ---------------------------------------------------------------------
	// Workflow with real abilities
	// ---------------------------------------------------------------------

	public function test_a_workflow_passes_a_real_title_to_a_second_real_ability() {
		$second = wp_get_ability( 'ai/summarization' ) ? 'ai/summarization' : ( wp_get_ability( 'ai/excerpt-generation' ) ? 'ai/excerpt-generation' : '' );

		if ( '' === $second ) {
			$this->markTestSkipped( 'Neither ai/summarization nor ai/excerpt-generation registered.' );
		}

		$workflow_id = $this->repository->create_workflow( array( 'name' => 'Live AI workflow', 'status' => 'active' ) );
		$this->workflow_ids[] = $workflow_id;
		$this->assertTrue(
			$this->repository->save_steps(
				$workflow_id,
				array(
					array(
						'step_key'     => 'title',
						'ability_name' => 'ai/title-generation',
						'input_map'    => array( 'content' => '{{trigger.text}}' ),
					),
					array(
						'step_key'     => 'second',
						'ability_name' => $second,
						'depends_on'   => array( 'title' ),
						'input_map'    => array( 'content' => 'Title: {{steps.title.output.title}}. {{trigger.text}}' ),
					),
				)
			)
		);
		$this->repository->update_workflow( $workflow_id, array( 'settings' => array( 'allow_destructive_abilities' => true ) ) );

		$run_id = $this->repository->create_run(
			$workflow_id,
			$this->repository->get_workflow( $workflow_id )->version,
			array( 'type' => 'manual', 'text' => 'A beginner guide to repairing a flat bicycle tyre: remove the wheel, find the puncture, patch the tube and refit the tyre.' )
		);
		wp_set_current_user( 0 );

		$result = ( new AIPS_Ability_Workflow_Executor( null, null, null, null, null, null, null, '__return_true' ) )->process_run( $run_id );
		$steps  = array();
		foreach ( $this->repository->get_step_runs( $run_id ) as $step_run ) {
			$steps[ $step_run->step_key ] = $step_run;
		}
		$this->log(
			'workflow',
			array(
				'title'  => isset( $steps['title'] ) ? $steps['title']->output_snapshot : null,
				'second' => isset( $steps['second'] ) ? array( $second, $steps['second']->status, $steps['second']->output_snapshot, $steps['second']->error ) : null,
			)
		);

		$this->assertSame( AIPS_Ability_Workflow_Executor::RESULT_FINISHED, $result['state'] );
		$this->assertSame( 'completed', $steps['title']->status );
		$this->assertNotEmpty( $steps['title']->output_snapshot['title'] );
		$this->assertSame( 'completed', $steps['second']->status, 'The chained step must accept the title produced by the first.' );
	}
}
