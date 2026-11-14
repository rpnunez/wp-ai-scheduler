<?php
/**
 * Tests for AIPS_Job_Registry and AIPS_Job_Type (issue #2376 Slice 1).
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_Job_Registry extends WP_UnitTestCase {

	/** @var AIPS_Job_Registry */
	private $registry;

	public function setUp(): void {
		parent::setUp();
		$this->registry = new AIPS_Job_Registry();
	}

	public function test_default_job_definitions_registered() {
		$definitions = $this->registry->get_definitions();

		$this->assertArrayHasKey( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE, $definitions );
		$this->assertArrayHasKey( AIPS_Job_Registry::TYPE_BULK_BATCH_SLICE, $definitions );
		$this->assertArrayHasKey( AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN, $definitions );

		$this->assertTrue( $this->registry->has( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE ) );
		$this->assertTrue( $this->registry->has( AIPS_Job_Registry::TYPE_BULK_BATCH_SLICE ) );
		$this->assertTrue( $this->registry->has( AIPS_Job_Registry::TYPE_LINK_INDEX_SCAN ) );
		$this->assertFalse( $this->registry->has( 'non_existent_job_type' ) );
	}

	public function test_get_returns_typed_job_specification() {
		$job_type = $this->registry->get( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE );

		$this->assertInstanceOf( AIPS_Job_Type::class, $job_type );
		$this->assertSame( AIPS_Job_Registry::TYPE_AUTHOR_TOPIC_SLICE, $job_type->get_slug() );
		$this->assertSame( AIPS_Job_Registry::DEFAULT_TICK_HOOK, $job_type->get_hook() );
		$this->assertSame( AIPS_Job_Registry::GROUP_AUTHOR_TOPICS, $job_type->get_group() );
		$this->assertSame( 3, $job_type->get_max_retries() );
		$this->assertSame( 120, $job_type->get_retry_backoff_sec() );
		$this->assertSame( 10, $job_type->get_batch_size() );
		$this->assertSame( 'AIPS_Author_Topics_Service', $job_type->get_handler() );

		$array_spec = $job_type->to_array();
		$this->assertSame( 'Author Topic Slice', $array_spec['label'] );
	}

	public function test_get_groups_and_hooks() {
		$groups = $this->registry->get_groups();
		$this->assertContains( AIPS_Job_Registry::GROUP_DEFAULT, $groups );
		$this->assertContains( AIPS_Job_Registry::GROUP_AUTHOR_TOPICS, $groups );
		$this->assertContains( AIPS_Job_Registry::GROUP_BULK_BATCH, $groups );
		$this->assertContains( AIPS_Job_Registry::GROUP_INDEXER, $groups );
		$this->assertContains( AIPS_Job_Registry::GROUP_EMBEDDINGS, $groups );

		$hooks = $this->registry->get_hooks();
		$this->assertContains( AIPS_Job_Registry::DEFAULT_TICK_HOOK, $hooks );
	}

	public function test_filter_can_extend_registered_jobs() {
		add_filter(
			'aips_registered_jobs',
			function( $defs ) {
				$defs['custom_test_job'] = array(
					'label'             => 'Custom Job',
					'hook'              => 'custom_hook',
					'group'             => 'custom-group',
					'max_retries'       => 2,
					'retry_backoff_sec' => 30,
					'batch_size'        => 5,
					'handler'           => 'Custom_Handler',
				);
				return $defs;
			}
		);

		$custom_type = $this->registry->get( 'custom_test_job' );
		$this->assertInstanceOf( AIPS_Job_Type::class, $custom_type );
		$this->assertSame( 'Custom Job', $custom_type->get_label() );
		$this->assertSame( 'custom_hook', $custom_type->get_hook() );
		$this->assertSame( 'custom-group', $custom_type->get_group() );
	}
}
