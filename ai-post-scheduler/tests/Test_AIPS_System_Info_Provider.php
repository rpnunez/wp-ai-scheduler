<?php
/**
 * Tests for the "Totals & Counts" section of AIPS_System_Info_Provider.
 *
 * @package AI_Post_Scheduler
 */

class Test_AIPS_System_Info_Provider extends WP_UnitTestCase {

	/**
	 * Repository stub returning fixed totals.
	 *
	 * @return AIPS_System_Totals_Repository
	 */
	private function make_totals_stub() {
		$stub = $this->getMockBuilder(AIPS_System_Totals_Repository::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_totals'))
			->getMock();

		$stub->method('get_totals')->willReturn(array(
			'generated_posts'     => 1234,
			'history'             => array(
				'total'                => 50,
				'by_kind'              => array('generation' => 30, 'job' => 15, 'event' => 5),
				'generation_by_status' => array('completed' => 24, 'partial' => 3, 'failed' => 3),
				'generation_by_method' => array('manual' => 20, 'bulk_generate' => 10),
			),
			'author_posts'        => 9,
			'authors'             => array('total' => 4, 'active' => 3),
			'author_topics'       => array('total' => 12, 'groups' => array('approved' => 8, 'pending' => 4)),
			'ai_requests'         => 321,
			'ai_assistance'       => 6,
			'background_ai_calls' => 17,
			'embeddings'          => array('total' => 40, 'groups' => array('post' => 38, 'author' => 2)),
			'relationships'       => 77,
			'internal_links'      => array('total' => 5, 'groups' => array('inserted' => 5)),
			'link_index'          => array('total' => 10, 'by_type' => array('internal' => 7, 'external' => 3), 'inserted_by_aips' => 2),
			'templates'           => array('total' => 3, 'active' => 2),
			'schedules'           => array('total' => 2, 'active' => 1),
			'campaigns'           => array('total' => 1, 'active' => 1),
			'trending_topics'     => 11,
			'sources'             => 2,
			'source_pages'        => array('total' => 8, 'by_status' => array('success' => 7, 'failed' => 1), 'times_used' => 13),
			'bulk_jobs'           => array('total' => 2, 'processed' => 25),
		));

		return $stub;
	}

	private function make_provider() {
		$prune = $this->getMockBuilder(AIPS_DB_Prune_Service::class)
			->disableOriginalConstructor()
			->onlyMethods(array('get_table_status_summary'))
			->getMock();
		$prune->method('get_table_status_summary')->willReturn(array());

		return new AIPS_System_Info_Provider($prune, $this->make_totals_stub());
	}

	public function test_totals_section_follows_plugin_section() {
		$keys = array_keys($this->make_provider()->get_system_info());

		$this->assertSame(array_search('plugin', $keys, true) + 1, array_search('totals_counts', $keys, true));
	}

	public function test_section_titles() {
		$provider = $this->make_provider();

		$this->assertSame('Totals & Counts', $provider->get_section_title('totals_counts'));
		$this->assertSame('AI', $provider->get_section_title('ai'));
		$this->assertSame('Data Storage', $provider->get_section_title('data_storage'));
	}

	public function test_totals_rows_render_counts() {
		delete_option(AIPS_AI_Call_Counter::OPTION);
		$rows = $this->make_provider()->get_system_info()['totals_counts'];

		$this->assertSame(number_format_i18n(1234), $rows['generated_posts']['value']);
		$this->assertStringContainsString('24 completed', $rows['generation_runs']['value']);
		$this->assertStringContainsString('90', $rows['generation_runs']['value']);
		$this->assertStringContainsString('Bulk Generate 10', $rows['generation_methods']['value']);
		$this->assertStringContainsString('Generation 30', $rows['history_containers']['value']);
		$this->assertSame('3 active of 4', $rows['authors']['value']);
		$this->assertSame('9', $rows['author_posts']['value']);
		$this->assertSame('321', $rows['ai_requests']['value']);
		$this->assertStringContainsString('2 inserted by AIPS', $rows['link_index']['value']);
		$this->assertSame(array('Success: 7', 'Failed: 1'), $rows['source_pages']['details']);

		foreach ($rows as $row) {
			$this->assertSame('info', $row['status']);
		}
	}

	public function test_provider_rows_always_list_both_providers() {
		delete_option(AIPS_AI_Call_Counter::OPTION);
		$rows = $this->make_provider()->get_system_info()['totals_counts'];

		$this->assertArrayHasKey('ai_calls_wp_ai_client', $rows);
		$this->assertArrayHasKey('ai_calls_meow', $rows);
		$this->assertStringContainsString('WP AI Connector', $rows['ai_calls_wp_ai_client']['label']);
		$this->assertStringContainsString('counting starts', $rows['ai_calls_meow']['value']);
	}

	public function test_provider_rows_use_counter_values() {
		delete_option(AIPS_AI_Call_Counter::OPTION);
		AIPS_AI_Call_Counter::increment('meow', 'text');
		AIPS_AI_Call_Counter::increment('meow', 'text', false);
		AIPS_AI_Call_Counter::increment('meow', 'embedding');

		$rows = $this->make_provider()->get_system_info()['totals_counts'];

		$this->assertStringStartsWith('3 calls · 1 failed (since ', $rows['ai_calls_meow']['value']);
		$this->assertSame(array('Text: 2', 'Embedding: 1'), $rows['ai_calls_meow']['details']);
		$this->assertSame('Text 2 · Embedding 1', $rows['ai_calls_by_type']['value']);
	}

	public function test_markdown_report_includes_totals_section() {
		$report = $this->make_provider()->get_markdown_report();

		$this->assertStringContainsString('#### Totals & Counts', $report);
		$this->assertStringContainsString('| Posts Generated |', $report);
	}
}
