<?php
/**
 * Contract tests for the canonical history-event vocabulary and value object.
 *
 * These pin the serialized record shape (where event_type/event_status,
 * subject, and correlation metadata live), alias resolution, and the
 * distinguishability of success/partial/failure terminal events.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_History_Event_Contract extends WP_UnitTestCase {

	/**
	 * Recording captured from a fake container.
	 *
	 * @var array
	 */
	private $captured = array();

	// ---------------------------------------------------------------------
	// Status catalog
	// ---------------------------------------------------------------------

	public function test_status_canonicalizes_known_synonyms() {
		$this->assertSame( AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::canonicalize( 'complete' ) );
		$this->assertSame( AIPS_History_Event_Status::SUCCESS, AIPS_History_Event_Status::canonicalize( 'draft' ) );
		$this->assertSame( AIPS_History_Event_Status::RUNNING, AIPS_History_Event_Status::canonicalize( 'processing' ) );
		$this->assertSame( AIPS_History_Event_Status::FAILED, AIPS_History_Event_Status::canonicalize( 'ERROR' ) );
	}

	public function test_status_passes_through_unknown_values() {
		$this->assertSame( 'bespoke_status', AIPS_History_Event_Status::canonicalize( 'Bespoke_Status' ) );
	}

	public function test_status_synonyms_include_all_legacy_forms() {
		$synonyms = AIPS_History_Event_Status::synonyms_for( 'success' );
		$this->assertContains( 'success', $synonyms );
		$this->assertContains( 'complete', $synonyms );
		$this->assertContains( 'draft', $synonyms );
	}

	public function test_terminal_statuses_are_distinguishable() {
		$this->assertTrue( AIPS_History_Event_Status::is_terminal( 'success' ) );
		$this->assertTrue( AIPS_History_Event_Status::is_terminal( 'partial' ) );
		$this->assertTrue( AIPS_History_Event_Status::is_terminal( 'failed' ) );
		$this->assertFalse( AIPS_History_Event_Status::is_terminal( 'pending' ) );
		$this->assertFalse( AIPS_History_Event_Status::is_terminal( 'running' ) );
	}

	// ---------------------------------------------------------------------
	// Event-type catalog
	// ---------------------------------------------------------------------

	public function test_type_canonicalizes_legacy_aliases() {
		$this->assertSame(
			AIPS_History_Event_Type::AUTHOR_POST_GENERATION,
			AIPS_History_Event_Type::canonicalize( 'topic_post_generation' )
		);
		$this->assertSame(
			AIPS_History_Event_Type::TOPIC_APPROVED,
			AIPS_History_Event_Type::canonicalize( 'topic_approval' )
		);
	}

	public function test_type_names_for_expands_canonical_and_aliases() {
		$names = AIPS_History_Event_Type::names_for( AIPS_History_Event_Type::AUTHOR_POST_GENERATION );
		$this->assertContains( 'author_post_generation', $names );
		$this->assertContains( 'topic_post_generation', $names );

		// Passing the alias itself resolves to the same set.
		$this->assertSame( $names, AIPS_History_Event_Type::names_for( 'topic_post_generation' ) );
	}

	public function test_expand_flattens_and_dedupes() {
		$expanded = AIPS_History_Event_Type::expand( array( 'author_post_generation', 'topic_post_generation' ) );
		$this->assertContains( 'author_post_generation', $expanded );
		$this->assertContains( 'topic_post_generation', $expanded );
		$this->assertSame( count( $expanded ), count( array_unique( $expanded ) ) );
	}

	public function test_unregistered_type_is_reported() {
		$this->assertFalse( AIPS_History_Event_Type::is_registered( 'totally_made_up_event' ) );
		$this->assertTrue( AIPS_History_Event_Type::is_registered( 'topic_approved' ) );
		$this->assertTrue( AIPS_History_Event_Type::is_registered( 'topic_approval' ) );
	}

	public function test_subject_type_mapping() {
		$this->assertSame(
			AIPS_History_Event_Type::SUBJECT_AUTHOR,
			AIPS_History_Event_Type::subject_type_for( 'topic_post_generation' )
		);
	}

	// ---------------------------------------------------------------------
	// Event value object serialization contract
	// ---------------------------------------------------------------------

	public function test_event_places_type_and_status_in_input_block() {
		$event = AIPS_History_Event::success(
			AIPS_History_Event_Type::TOPIC_APPROVED,
			'Topic approved',
			AIPS_History_Subject::of( AIPS_History_Subject::TYPE_TOPIC, 42, 'A topic' ),
			array( 'reason' => 'looks good' )
		);

		$input = $event->to_details_input();

		$this->assertSame( 'topic_approved', $input['event_type'] );
		$this->assertSame( 'success', $input['event_status'] );
		// Producer context stays in context, not input.
		$this->assertArrayNotHasKey( 'reason', $input );
	}

	public function test_event_guarantees_subject_and_correlation_in_context() {
		$event = AIPS_History_Event::failure(
			AIPS_History_Event_Type::TOPIC_REJECTED,
			'Topic rejected',
			AIPS_History_Subject::of( AIPS_History_Subject::TYPE_TOPIC, 7 ),
			array( 'reason' => 'off-topic' )
		)->with_correlation_id( 'corr-123' );

		$context = $event->to_details_context();

		$this->assertSame( 'topic', $context['subject']['type'] );
		$this->assertSame( 7, $context['subject']['id'] );
		$this->assertSame( 7, $context['topic_id'] );
		$this->assertSame( 'corr-123', $context['correlation_id'] );
		$this->assertSame( 'off-topic', $context['reason'] );
	}

	public function test_event_input_cannot_be_overridden_by_producer_keys() {
		// A producer that tries to smuggle a different event_type via input must
		// not win against the canonical identity.
		$event = new AIPS_History_Event(
			AIPS_History_Event_Type::TOPIC_APPROVED,
			'success',
			'msg',
			AIPS_History_Subject::none(),
			array( 'event_type' => 'something_else', 'event_status' => 'weird', 'extra' => 1 )
		);

		$input = $event->to_details_input();
		$this->assertSame( 'topic_approved', $input['event_type'] );
		$this->assertSame( 'success', $input['event_status'] );
		$this->assertSame( 1, $input['extra'] );
	}

	public function test_named_constructors_produce_distinct_terminal_statuses() {
		$s = AIPS_History_Event::success( AIPS_History_Event_Type::POST_PUBLISHED )->status();
		$p = AIPS_History_Event::partial( AIPS_History_Event_Type::AUTHOR_POST_GENERATION )->status();
		$f = AIPS_History_Event::failure( AIPS_History_Event_Type::POST_PUBLISHED )->status();

		$this->assertSame( 'success', $s );
		$this->assertSame( 'partial', $p );
		$this->assertSame( 'failed', $f );
		$this->assertNotSame( $s, $p );
		$this->assertNotSame( $p, $f );
	}

	// ---------------------------------------------------------------------
	// Recorder placement + correlation
	// ---------------------------------------------------------------------

	public function test_recorder_records_canonical_placement_into_container() {
		$container = new Test_AIPS_Recording_Container( 'corr-xyz' );
		$recorder  = new AIPS_History_Event_Recorder( new AIPS_History_Service() );

		$event = AIPS_History_Event::success(
			AIPS_History_Event_Type::TAXONOMY_APPROVED,
			'Taxonomy approved',
			AIPS_History_Subject::of( AIPS_History_Subject::TYPE_TAXONOMY_ITEM, 5, 'Widgets' ),
			array( 'taxonomy_type' => 'category' )
		);

		$recorder->record_into( $container, $event );

		$this->assertSame( 'activity', $container->last['log_type'] );
		$this->assertSame( 'taxonomy_approved', $container->last['input']['event_type'] );
		$this->assertSame( 'success', $container->last['input']['event_status'] );
		// Correlation id flows from the container into the recorded context.
		$this->assertSame( 'corr-xyz', $container->last['context']['correlation_id'] );
		$this->assertSame( 5, $container->last['context']['subject']['id'] );
	}

	public function test_recorder_enforces_single_terminal_event() {
		$container = new Test_AIPS_Recording_Container( 'corr-term' );
		$recorder  = new AIPS_History_Event_Recorder( new AIPS_History_Service() );

		$first = $recorder->record_into(
			$container,
			AIPS_History_Event::success( AIPS_History_Event_Type::MANUAL_SCHEDULE_COMPLETED ),
			'activity',
			true
		);
		$second = $recorder->record_into(
			$container,
			AIPS_History_Event::failure( AIPS_History_Event_Type::MANUAL_SCHEDULE_FAILED ),
			'activity',
			true
		);

		$this->assertNotFalse( $first );
		$this->assertFalse( $second, 'A second terminal event must be suppressed when enforcement is on.' );
	}
}

/**
 * Minimal container double that captures record() calls without touching the DB.
 */
class Test_AIPS_Recording_Container extends AIPS_History_Container {

	public $last = array();

	private $fake_correlation_id;

	private $fake_id;

	public function __construct( $correlation_id = null, $id = 9999 ) {
		// Intentionally do not call parent::__construct — we avoid all DB work.
		$this->fake_correlation_id = $correlation_id;
		$this->fake_id             = $id;
	}

	public function get_correlation_id() {
		return $this->fake_correlation_id;
	}

	public function get_id() {
		return $this->fake_id;
	}

	public function record( $log_type, $message, $input = null, $output = null, $context = array() ) {
		$this->last = array(
			'log_type' => $log_type,
			'message'  => $message,
			'input'    => $input,
			'output'   => $output,
			'context'  => $context,
		);

		return 1;
	}
}
