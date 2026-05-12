<?php
/**
 * Tests for AIPS_Content_Components_Repository.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Content_Components_Repository extends WP_UnitTestCase {

	/**
	 * @var AIPS_Content_Components_Repository
	 */
	private $repository;

	/**
	 * @var int
	 */
	private $component_id;

	public function setUp(): void {
		parent::setUp();

		$this->repository = new AIPS_Content_Components_Repository();
		$this->component_id = (int) $this->repository->create(
			array(
				'title'          => 'Repo Test Component',
				'slug'           => 'repo-test-component',
				'description'    => 'Initial description',
				'status'         => 'active',
				'component_type' => 'cta',
				'content_mode'   => 'html',
				'content'        => '<p>Initial content</p>',
				'content_payload'=> '<p>Initial content</p>',
				'media_payload'  => array(),
				'cta_payload'    => array(),
				'rules_json'     => array(),
				'qa_status'      => 'untested',
				'qa_notes'       => '',
				'is_active'      => 1,
			)
		);
	}

	public function tearDown(): void {
		if ( $this->component_id > 0 ) {
			$this->repository->delete( $this->component_id );
		}

		parent::tearDown();
	}

	public function test_set_active_does_not_overwrite_existing_fields() {
		$this->assertGreaterThan( 0, $this->component_id );

		$before = $this->repository->get_by_id( $this->component_id );
		$this->assertNotNull( $before );
		$this->assertSame( 'Repo Test Component', (string) $before->title );

		$result = $this->repository->set_active( $this->component_id, false );
		$this->assertNotFalse( $result );

		$after = $this->repository->get_by_id( $this->component_id );
		$this->assertNotNull( $after );
		$this->assertSame( 'Repo Test Component', (string) $after->title );
		$this->assertSame( 'repo-test-component', (string) $after->slug );
		$this->assertSame( 0, (int) $after->is_active );
		$this->assertSame( 'draft', (string) $after->status );
	}

	public function test_partial_update_changes_only_requested_fields() {
		$this->assertGreaterThan( 0, $this->component_id );

		$before = $this->repository->get_by_id( $this->component_id );
		$this->assertNotNull( $before );

		$result = $this->repository->update(
			$this->component_id,
			array(
				'qa_status' => 'passed',
			)
		);

		$this->assertNotFalse( $result );

		$after = $this->repository->get_by_id( $this->component_id );
		$this->assertNotNull( $after );
		$this->assertSame( 'passed', (string) $after->qa_status );
		$this->assertSame( (string) $before->title, (string) $after->title );
		$this->assertSame( (string) $before->content, (string) $after->content );
	}
}
