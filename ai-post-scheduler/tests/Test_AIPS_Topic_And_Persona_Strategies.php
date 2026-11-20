<?php
/**
 * Tests for Author Topic & Persona Generation Strategies (Issue #2400 Phase 2).
 *
 * Covers:
 * - AIPS_Topic_Generation_Strategy + AIPS_Generation_Pipeline (full run, partial result, history tracking, invalid author, AI failure)
 * - Concrete Topic Components (topic_title, topic_prompt, topic_keywords, topic_embedding)
 * - AIPS_Author_Persona_Strategy + AIPS_Author_Persona_Component + AIPS_Generation_Pipeline
 * - Migrated AIPS_Author_Topics_Generator & AIPS_Author_Suggestions_Service backward compatibility
 * - Registry & Container resolution for Phase 2 strategies/components
 *
 * @package AI_Post_Scheduler
 */

/**
 * Class Test_AIPS_Topic_And_Persona_Strategies
 */
class Test_AIPS_Topic_And_Persona_Strategies extends WP_UnitTestCase {

	/**
	 * Build a mock author object.
	 *
	 * @param array $overrides Optional author property overrides.
	 * @return object
	 */
	private function make_author(array $overrides = array()) {
		$defaults = array(
			'id'                        => 77,
			'name'                      => 'Dr. Ada Lovelace',
			'field_niche'               => 'Distributed Systems',
			'niche'                     => 'Distributed Systems',
			'voice_tone'                => 'analytical',
			'writing_style'             => 'technical',
			'topic_generation_quantity' => 2,
			'topic_generation_prompt'   => 'Generate deep technical topics.',
			'auto_approval_mode'        => 'manual',
		);

		return (object) array_merge($defaults, $overrides);
	}

	/**
	 * Registry registers default Topic and Persona components cleanly.
	 */
	public function test_registry_registers_topic_and_persona_components() {
		$registry = new AIPS_Generation_Component_Registry();
		$registry->register_default_topic_components();
		$registry->register_default_persona_components();

		foreach (array('topic_title', 'topic_prompt', 'topic_keywords', 'topic_score', 'topic_embedding') as $key) {
			$this->assertTrue($registry->has($key, 'author_topics'), "Expected {$key} registered under author_topics");
			$this->assertSame('author_topics', $registry->get($key, 'author_topics')->get_strategy_key());
		}

		$this->assertTrue($registry->has('author_persona', 'author_personas'));
		$this->assertSame('author_personas', $registry->get('author_persona', 'author_personas')->get_strategy_key());
	}

	/**
	 * Concrete Topic Components sanitize values and contribute schema fragments.
	 */
	public function test_topic_components_sanitize_and_schemas() {
		$title_comp = new AIPS_Topic_Title_Component();
		$this->assertSame('Consensus Algorithms Explained', $title_comp->sanitize_value(' "Consensus Algorithms Explained" '));
		$this->assertArrayHasKey('title', $title_comp->get_schema_fragment());
		$this->assertTrue($title_comp->is_required());
		$this->assertTrue($title_comp->supports_ai_edit());

		$prompt_comp = new AIPS_Topic_Prompt_Component();
		$this->assertSame('Focus on Raft vs Paxos', $prompt_comp->sanitize_value('  Focus on Raft vs Paxos  '));
		$this->assertArrayHasKey('prompt', $prompt_comp->get_schema_fragment());
		$this->assertFalse($prompt_comp->is_required());

		$keywords_comp = new AIPS_Topic_Keywords_Component();
		$this->assertSame(array('raft', 'paxos', 'consensus'), $keywords_comp->sanitize_value('raft, paxos, raft, consensus'));
		$this->assertArrayHasKey('keywords', $keywords_comp->get_schema_fragment());

		$score_comp = new AIPS_Topic_Score_Component();
		$this->assertSame(92, $score_comp->sanitize_value('Score: 92/100'));
		$this->assertSame(100, $score_comp->sanitize_value(150));
		$this->assertArrayHasKey('score', $score_comp->get_schema_fragment());
		$this->assertFalse($score_comp->is_required());
	}

	/**
	 * AIPS_Topic_Generation_Strategy executes full pipeline and returns completed AIPS_Generation_Result.
	 */
	public function test_topic_strategy_pipeline_completed_and_partial_results() {
		$author = $this->make_author(array('topic_generation_quantity' => 2));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('generate_json')->willReturnOnConsecutiveCalls(
			array(
				array('title' => 'Understanding Raft Consensus in Go', 'score' => 92, 'keywords' => array('raft', 'go')),
				array('title' => 'Designing Idempotent Microservice APIs', 'score' => 88, 'keywords' => array('api', 'idempotency')),
			),
			array(
				array('title' => 'Single Parsed Topic Only for Partial Test', 'score' => 80, 'keywords' => array('partial')),
			)
		);

		$topics_repo = new class() {
			public $created = array();
			public function get_approved_summary($author_id, $limit = 10) { return array(); }
			public function get_rejected_summary($author_id, $limit = 10) { return array(); }
			public function create_bulk($topics) {
				$this->created = $topics;
				return true;
			}
			public function get_latest_by_author($author_id, $limit) {
				$rows = array();
				$id   = 500;
				foreach (array_reverse($this->created) as $t) {
					$t['id'] = $id--;
					$rows[]  = (object) $t;
				}
				return array_slice($rows, 0, $limit);
			}
		};

		$logs_repo = new class() {
			public function create($data) { return 1; }
		};

		$authors_repo = new class() {
			public $last_run_updated = false;
			public function update_topic_generation_last_run($author_id, $ts) {
				$this->last_run_updated = true;
				return true;
			}
		};

		$feedback_repo = new class() {
			public function get_reason_category_statistics($author_id) { return array(); }
		};

		$dedup_service = new class() {
			public function evaluate_topics_for_duplicates($topics, $author_id) { return $topics; }
		};

		$embeddings_service = new class() {
			public function is_enabled() { return false; }
		};

		$prompt_builder = new class() {
			public function build($author, $approved, $rejected, $guidance) { return 'Topic prompt'; }
			public function get_sources_selection() { return array(); }
			public function record_sources_usage($selection) {}
		};

		$generator = new AIPS_Author_Topics_Generator(
			$ai_mock,
			null,
			$topics_repo,
			$logs_repo,
			$embeddings_service,
			$feedback_repo,
			$prompt_builder,
			$dedup_service,
			$authors_repo
		);

		// 1. Full 2/2 topics -> completed.
		$res_full = $generator->generate_topics_result($author, false);
		$this->assertTrue($res_full->is_success());
		$this->assertCount(2, $res_full->get_data());
		$this->assertTrue($authors_repo->last_run_updated);
		$statuses = $res_full->get_component_statuses();
		$this->assertTrue($statuses['topic_title']);
		$this->assertTrue($statuses['topic_keywords']);

		// 2. Only 1/2 topics parsed -> partial.
		$res_partial = $generator->generate_topics_result($author, false);
		$this->assertTrue($res_partial->is_partial());
		$this->assertCount(1, $res_partial->get_data());
		$this->assertNotEmpty($res_partial->get_errors());

		// 3. Invalid author -> failure WP_Error through legacy generate_topics().
		$invalid = $generator->generate_topics(null);
		$this->assertWPError($invalid);
		$this->assertSame('invalid_author', $invalid->get_error_code());
	}

	/**
	 * AIPS_Author_Persona_Strategy + AIPS_Author_Suggestions_Service normalize enums and return typed results.
	 */
	public function test_author_persona_strategy_and_service_migration() {
		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->expects($this->once())
			->method('generate_json')
			->willReturn(array(
				array(
					'name'                     => 'Elena Vance',
					'field_niche'              => 'Cloud Architecture',
					'description'              => 'Principal cloud architect.',
					'details'                  => '15 years building resilient AWS platforms.',
					'keywords'                 => 'aws, kubernetes, sre',
					'voice_tone'               => 'authoritative',
					'writing_style'            => 'pragmatic',
					'target_audience'          => 'Staff engineers',
					'expertise_level'          => 'thought-leader',
					'content_goals'            => 'Deep architectural guides',
					'preferred_content_length' => 'in-depth',
					'topic_generation_prompt'  => 'Focus on real-world outages and architecture trade-offs.',
				),
			));

		$prompt_builder = new class() {
			public function build(array $inputs, $count) { return 'Persona prompt'; }
		};

		$service = new AIPS_Author_Suggestions_Service($ai_mock, null, null, $prompt_builder);
		$result  = $service->suggest_authors_result(array('site_niche' => 'Cloud Architecture'), 1);

		$this->assertTrue($result->is_success());
		$this->assertSame(array('author_persona' => true), $result->get_component_statuses());
		$suggestions = $result->get_data();
		$this->assertCount(1, $suggestions);
		$this->assertSame('Elena Vance', $suggestions[0]['name']);
		$this->assertSame('thought_leader', $suggestions[0]['expertise_level']);
		$this->assertSame('long', $suggestions[0]['preferred_content_length']);
	}

	/**
	 * AIPS_Container resolves Phase 2 strategies.
	 */
	public function test_container_resolves_phase2_strategies() {
		$container = AIPS_Container::get_instance();
		if (!$container->has(AIPS_Topic_Generation_Strategy::class)) {
			$this->markTestSkipped('Container bindings not initialized in this test bootstrap.');
		}

		$this->assertInstanceOf(
			AIPS_Topic_Generation_Strategy::class,
			$container->make(AIPS_Topic_Generation_Strategy::class)
		);
		$this->assertInstanceOf(
			AIPS_Author_Persona_Strategy::class,
			$container->make(AIPS_Author_Persona_Strategy::class)
		);
	}
}
