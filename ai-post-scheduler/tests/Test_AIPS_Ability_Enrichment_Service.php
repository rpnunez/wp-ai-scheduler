<?php
/**
 * Tests for post-generation Ability enrichment.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Ability_Enrichment_Service extends WP_UnitTestCase {

	const SLUG = 'ai/alt-text-generation';

	/** @var array Payloads and current-user IDs seen by the fixture ability. */
	private $calls = array();

	/** @var int */
	private $post_id;

	/** @var int */
	private $attachment_id;

	public function setUp(): void {
		parent::setUp();

		$this->calls = array();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 1 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 0 );

		$this->post_id       = self::factory()->post->create( array( 'post_title' => 'Generated Post' ) );
		$this->attachment_id = self::factory()->attachment->create( array( 'post_parent' => $this->post_id ) );
		// set_post_thumbnail() rejects attachments that have no real image file.
		update_post_meta( $this->post_id, '_thumbnail_id', $this->attachment_id );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tearDown(): void {
		remove_all_filters( 'aips_ability_provider' );
		remove_all_filters( 'aips_ability_enrichment_steps' );
		remove_all_filters( 'aips_ability_enrichment_user_id' );
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 0 );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Register a fixture provider exposing the alt-text ability.
	 *
	 * @param callable|null $invoke Invocation callback; receives ( $slug, $payload ).
	 * @param array|null    $schema Input schema; null for an ability with no schema.
	 * @param bool          $listed Whether the ability is registered at all.
	 */
	private function register_provider( $invoke = null, $schema = null, $listed = true ) {
		$invoke = $invoke ?: function () {
			return array( 'alt_text' => 'A red bicycle' );
		};

		add_filter(
			'aips_ability_provider',
			function () use ( $invoke, $schema, $listed ) {
				return array(
					'name'   => 'enrichment-fixture',
					'list'   => function () use ( $schema, $listed ) {
						if ( ! $listed ) {
							return array();
						}

						$ability = array( 'slug' => Test_AIPS_Ability_Enrichment_Service::SLUG );

						if ( null !== $schema ) {
							$ability['input_schema'] = $schema;
						}

						return array( Test_AIPS_Ability_Enrichment_Service::SLUG => $ability );
					},
					'invoke' => function ( $slug, $payload ) use ( $invoke ) {
						$this->calls[] = array(
							'payload' => $payload,
							'user_id' => get_current_user_id(),
						);

						return call_user_func( $invoke, $slug, $payload );
					},
				);
			}
		);
	}

	private function service() {
		return $this->service_with_kick( null );
	}

	private function service_with_kick( $kick ) {
		$abilities = new AIPS_Ability_Service();

		return new AIPS_Ability_Enrichment_Service( $abilities, new AIPS_Ability_Catalog_Service( $abilities ), null, null, $kick );
	}

	private function alt() {
		return get_post_meta( $this->attachment_id, '_wp_attachment_image_alt', true );
	}

	// ---------------------------------------------------------------------
	// Switch / listener behaviour
	// ---------------------------------------------------------------------

	public function test_disabled_flag_queues_nothing() {
		$this->register_provider();
		AIPS_Config::get_instance()->set_option( 'aips_enable_ability_enrichment', 0 );
		$kicks = 0;

		$this->assertFalse( AIPS_Ability_Enrichment_Service::is_enabled() );

		$this->service_with_kick(
			function () use ( &$kicks ) {
				$kicks++;

				return true;
			}
		)->handle_post_generated( $this->post_id );

		$this->assertSame( 0, $kicks );
		$this->assertSame( 0, ( new AIPS_Ability_Enrichment_Queue_Repository() )->count_pending() );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( '', $this->alt() );
	}

	public function test_the_listener_only_queues_the_post_and_wakes_the_process() {
		$this->register_provider();
		$kicks = 0;
		$queue = new AIPS_Ability_Enrichment_Queue_Repository();

		$this->assertTrue( AIPS_Ability_Enrichment_Service::is_enabled() );

		$this->service_with_kick(
			function () use ( &$kicks ) {
				$kicks++;

				return true;
			}
		)->handle_post_generated( $this->post_id );

		$this->assertSame( 1, $kicks );
		$this->assertSame( array( array( 'post_id' => $this->post_id, 'user_id' => get_current_user_id() ) ), $queue->get_pending( 10 ) );
		$this->assertCount( 0, $this->calls, 'No ability may be called inside the generation request.' );
		$this->assertSame( '', $this->alt() );
	}

	public function test_a_failed_wake_up_is_logged_but_the_post_stays_queued() {
		$this->register_provider();

		$this->service_with_kick(
			function () {
				return new WP_Error( 'no_process', 'No process.' );
			}
		)->handle_post_generated( $this->post_id );

		$this->assertSame( 1, ( new AIPS_Ability_Enrichment_Queue_Repository() )->count_pending() );
	}

	public function test_the_listener_never_throws() {
		$this->register_provider();

		$this->service_with_kick(
			function () {
				throw new RuntimeException( 'process exploded' );
			}
		)->handle_post_generated( $this->post_id );

		$this->assertTrue( true, 'handle_post_generated() must swallow the failure.' );
	}

	public function test_invalid_post_id_is_ignored() {
		$this->register_provider();

		$this->service()->handle_post_generated( 0 );
		$this->assertSame( array(), $this->service()->enrich_post( 999999 ) );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( 0, ( new AIPS_Ability_Enrichment_Queue_Repository() )->count_pending() );
	}

	public function test_a_throwing_step_is_reported_and_does_not_stop_later_steps() {
		$this->register_provider();
		add_filter(
			'aips_ability_enrichment_steps',
			function () {
				return array(
					'boom'  => function () {
						throw new RuntimeException( 'step exploded' );
					},
					'after' => function () {
						return 'applied';
					},
				);
			}
		);
		$before = get_current_user_id();

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( array( 'boom' => 'error:step_exception', 'after' => 'applied' ), $results );
		$this->assertSame( $before, get_current_user_id(), 'The current user is restored even when a step throws.' );
	}

	public function test_enrich_post_prefers_the_user_who_triggered_the_generation() {
		$this->register_provider();
		$author = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( 0 );

		$this->service()->enrich_post( $this->post_id, $author );

		$this->assertSame( $author, $this->calls[0]['user_id'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_a_triggering_user_who_cannot_edit_the_post_is_ignored() {
		$this->register_provider();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$editor_id  = get_current_user_id();

		$this->service()->enrich_post( $this->post_id, $subscriber );

		$this->assertSame( $editor_id, $this->calls[0]['user_id'], 'Falls back to the current user who can edit the post.' );
	}

	// ---------------------------------------------------------------------
	// Alt-text step: skip paths
	// ---------------------------------------------------------------------

	public function test_skips_when_post_has_no_featured_image() {
		$this->register_provider();
		delete_post_thumbnail( $this->post_id );

		$this->assertSame( 'skipped:no_featured_image', $this->service()->enrich_post( $this->post_id )['alt_text'] );
		$this->assertCount( 0, $this->calls );
	}

	public function test_skips_when_alt_text_already_present() {
		$this->register_provider();
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Hand written' );

		$this->assertSame( 'skipped:alt_text_present', $this->service()->enrich_post( $this->post_id )['alt_text'] );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( 'Hand written', $this->alt() );
	}

	public function test_whitespace_only_alt_text_counts_as_missing() {
		$this->register_provider();
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', '   ' );

		$this->assertSame( 'applied', $this->service()->enrich_post( $this->post_id )['alt_text'] );
		$this->assertSame( 'A red bicycle', $this->alt() );
	}

	public function test_skips_when_ability_is_not_registered() {
		$this->register_provider( null, null, false );

		$this->assertSame( 'skipped:ability_unavailable', $this->service()->enrich_post( $this->post_id )['alt_text'] );
		$this->assertCount( 0, $this->calls );
	}

	// ---------------------------------------------------------------------
	// Alt-text step: payload and output handling
	// ---------------------------------------------------------------------

	public function test_payload_is_trimmed_to_the_input_schema() {
		$this->register_provider(
			null,
			array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer' ),
					'context'       => array( 'type' => 'string' ),
				),
			)
		);

		$this->service()->enrich_post( $this->post_id );

		$this->assertCount( 1, $this->calls );
		$this->assertSame(
			array(
				'attachment_id' => $this->attachment_id,
				'context'       => 'Generated Post',
			),
			$this->calls[0]['payload']
		);
	}

	public function test_payload_without_schema_sends_only_the_attachment_id() {
		$this->register_provider();

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( array( 'attachment_id' => $this->attachment_id ), $this->calls[0]['payload'] );
	}

	public function test_payload_falls_back_to_attachment_id_when_schema_declares_no_known_keys() {
		$this->register_provider(
			null,
			array(
				'type'       => 'object',
				'properties' => array( 'unrelated' => array( 'type' => 'string' ) ),
			)
		);

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( array( 'attachment_id' => $this->attachment_id ), $this->calls[0]['payload'] );
	}

	public function test_alt_text_is_sanitized_before_saving() {
		$this->register_provider(
			function () {
				return array( 'alt_text' => "  <b>Bold</b> claim <script>x()</script>\n" );
			}
		);

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'Bold claim', $this->alt() );
	}

	public function test_alternate_alt_key_is_accepted() {
		$this->register_provider(
			function () {
				return array( 'alt' => 'Short key' );
			}
		);

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'Short key', $this->alt() );
	}

	public function test_ability_error_is_reported_and_alt_text_left_alone() {
		$this->register_provider(
			function () {
				return new WP_Error( 'no_provider', 'No text generation provider.' );
			}
		);

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'error:no_provider', $results['alt_text'] );
		$this->assertSame( '', $this->alt() );
	}

	public function test_empty_output_is_reported_as_an_error() {
		$this->register_provider(
			function () {
				return array( 'alt_text' => '   ' );
			}
		);

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'error:ability_enrichment_empty_output', $results['alt_text'] );
		$this->assertSame( '', $this->alt() );
	}

	// ---------------------------------------------------------------------
	// Execution principal
	// ---------------------------------------------------------------------

	public function test_runs_as_current_user_when_they_can_edit_the_post() {
		$this->register_provider();
		$editor_id = get_current_user_id();

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( $editor_id, $this->calls[0]['user_id'] );
		$this->assertSame( $editor_id, get_current_user_id() );
	}

	public function test_cron_context_without_service_user_is_skipped() {
		$this->register_provider();
		wp_set_current_user( 0 );

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( array( '*' => 'skipped:no_principal' ), $results );
		$this->assertCount( 0, $this->calls );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_cron_context_uses_configured_service_user_and_restores_previous_user() {
		$this->register_provider();
		$service_user = self::factory()->user->create( array( 'role' => 'editor' ) );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', $service_user );
		wp_set_current_user( 0 );

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame( 'applied', $results['alt_text'] );
		$this->assertSame( $service_user, $this->calls[0]['user_id'] );
		$this->assertSame( 0, get_current_user_id(), 'The cron request returns to user 0.' );
	}

	public function test_service_user_without_edit_permission_is_rejected() {
		$this->register_provider();
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', $subscriber );
		wp_set_current_user( 0 );

		$this->assertSame( array( '*' => 'skipped:no_principal' ), $this->service()->enrich_post( $this->post_id ) );
		$this->assertCount( 0, $this->calls );
	}

	public function test_deleted_service_user_is_rejected() {
		$this->register_provider();
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', 987654 );
		wp_set_current_user( 0 );

		$this->assertSame( array( '*' => 'skipped:no_principal' ), $this->service()->enrich_post( $this->post_id ) );
	}

	public function test_current_user_without_edit_permission_falls_back_to_service_user() {
		$this->register_provider();
		$service_user = self::factory()->user->create( array( 'role' => 'editor' ) );
		AIPS_Config::get_instance()->set_option( 'aips_ability_enrichment_user_id', $service_user );
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( $service_user, $this->calls[0]['user_id'] );
		$this->assertSame( $subscriber, get_current_user_id() );
	}

	public function test_service_user_filter_overrides_the_setting() {
		$this->register_provider();
		$filtered = self::factory()->user->create( array( 'role' => 'editor' ) );
		add_filter(
			'aips_ability_enrichment_user_id',
			function () use ( $filtered ) {
				return $filtered;
			}
		);
		wp_set_current_user( 0 );

		$this->service()->enrich_post( $this->post_id );

		$this->assertSame( $filtered, $this->calls[0]['user_id'] );
	}

	// ---------------------------------------------------------------------
	// Extensibility
	// ---------------------------------------------------------------------

	public function test_custom_steps_run_and_non_callable_entries_are_ignored() {
		$this->register_provider();
		add_filter(
			'aips_ability_enrichment_steps',
			function () {
				return array(
					'custom'  => function ( $post_id ) {
						return 'applied:' . $post_id;
					},
					'failing' => function () {
						return new WP_Error( 'custom_failed', 'Nope.' );
					},
					'broken'  => 'not_a_function_name_xyz',
				);
			}
		);

		$results = $this->service()->enrich_post( $this->post_id );

		$this->assertSame(
			array(
				'custom'  => 'applied:' . $this->post_id,
				'failing' => 'error:custom_failed',
			),
			$results
		);
	}

	public function test_a_failing_step_does_not_stop_later_steps() {
		$this->register_provider();
		add_filter(
			'aips_ability_enrichment_steps',
			function () {
				return array(
					'first'  => function () {
						return new WP_Error( 'first_failed', 'Nope.' );
					},
					'second' => function () {
						return 'applied';
					},
				);
			}
		);

		$this->assertSame(
			array(
				'first'  => 'error:first_failed',
				'second' => 'applied',
			),
			$this->service()->enrich_post( $this->post_id )
		);
	}

	// ---------------------------------------------------------------------
	// Settings defaults
	// ---------------------------------------------------------------------

	public function test_settings_defaults_are_off_and_unset() {
		$defaults = AIPS_Config::get_instance()->get_default_options();

		$this->assertFalse( (bool) $defaults['aips_enable_ability_enrichment'] );
		$this->assertSame( 0, (int) $defaults['aips_ability_enrichment_user_id'] );
	}
}
