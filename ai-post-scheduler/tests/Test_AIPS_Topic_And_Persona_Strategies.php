<?php
/**
 * Tests for Author Topic & Persona Generation Strategies.
 *
 * Covers:
 * - AIPS_Topic_Generation_Strategy + AIPS_Generation_Pipeline (full run, partial result, history tracking, invalid author, AI failure)
 * - Concrete Topic Components (topic_title, topic_prompt, topic_keywords, topic_embedding)
 * - AIPS_Author_Persona_Strategy + AIPS_Author_Persona_Component + AIPS_Generation_Pipeline
 * - Migrated AIPS_Author_Topics_Generator & AIPS_Author_Suggestions_Service backward compatibility
 * - Registry & Container resolution for Topic and Persona strategies/components
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

	public function test_topic_strategy_ai_failure_does_not_create_topics() {
		$author = $this->make_author(array('topic_generation_quantity' => 2));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('generate_json')->willReturn(new WP_Error('ai_unavailable', 'AI down'));

		$topics_repo = new class() {
			public $created = false;
			public function get_approved_summary($author_id, $limit = 10) { return array(); }
			public function get_rejected_summary($author_id, $limit = 10) { return array(); }
			public function create_bulk($topics) {
				$this->created = true;
				return true;
			}
			public function get_latest_by_author($author_id, $limit) { return array(); }
		};

		$authors_repo = new class() {
			public $last_run_updated = false;
			public function update_topic_generation_last_run($author_id, $ts) {
				$this->last_run_updated = true;
				return true;
			}
		};

		$generator = new AIPS_Author_Topics_Generator(
			$ai_mock,
			null,
			$topics_repo,
			new class() { public function create($data) { return 1; } },
			new class() { public function is_enabled() { return false; } },
			new class() { public function get_reason_category_statistics($author_id) { return array(); } },
			new class() {
				public function build($author, $approved, $rejected, $guidance) { return 'Topic prompt'; }
				public function get_sources_selection() { return array(); }
				public function record_sources_usage($selection) {}
			},
			new class() { public function evaluate_topics_for_duplicates($topics, $author_id) { return $topics; } },
			$authors_repo
		);

		$result = $generator->generate_topics_result($author, false);
		$this->assertTrue($result->is_failure());
		$this->assertFalse($topics_repo->created);
		$this->assertFalse($authors_repo->last_run_updated);
		$legacy = $generator->generate_topics($author, false);
		$this->assertWPError($legacy);
		$this->assertSame('ai_unavailable', $legacy->get_error_code());
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
	 * AIPS_Container resolves Topic and Persona strategies.
	 */
	public function test_container_resolves_topic_and_persona_strategies() {
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

	/**
	 * Topic and Persona strategies cover no_topics_parsed, db_insert_error,
	 * embedding_degraded partial status under similarity policy cooldown, and execute_preview().
	 */
	public function test_topic_and_persona_failure_degraded_and_preview_branches() {
		$author = $this->make_author(array(
			'topic_generation_quantity'   => 1,
			'topic_auto_approval_mode'    => 'similarity',
		));

		$ai_mock = $this->createMock(AIPS_AI_Service_Interface::class);
		$ai_mock->method('generate_json')->willReturnOnConsecutiveCalls(
			array(array('title' => '   ')),
			array(array('title' => 'Valid Topic Title', 'score' => 85, 'keywords' => array('go'))),
			array(array('title' => 'Degraded Cooldown Topic', 'score' => 90, 'keywords' => array('raft'))),
			array(array('title' => 'Preview Topic Idea', 'score' => 88, 'keywords' => array('preview')))
		);

		$bulk_ok     = false;
		$topics_repo = new class($bulk_ok) {
			public $bulk_ok;
			public $last_batch = array();
			public function __construct(&$bulk_ok) { $this->bulk_ok =& $bulk_ok; }
			public function get_approved_summary($author_id, $limit = 10) { return array(); }
			public function get_rejected_summary($author_id, $limit = 10) { return array(); }
			public function get_pending_for_approval_recheck($author_id, $limit = 20) { return array(); }
			public function create_bulk($topics) {
				$this->last_batch = $topics;
				return $this->bulk_ok;
			}
			public function get_latest_by_author($author_id, $limit) {
				$out = array();
				foreach ($this->last_batch as $idx => $t) {
					$t['id'] = 700 + $idx;
					$out[]   = (object) $t;
				}
				return $out;
			}
		};

		$rate_limiter = $this->getMockBuilder(AIPS_Embeddings_Rate_Limiter::class)
			->disableOriginalConstructor()
			->onlyMethods(array('is_in_cooldown', 'get_cooldown_status'))
			->getMock();
		$rate_limiter->method('is_in_cooldown')->willReturn(true);
		$rate_limiter->method('get_cooldown_status')->willReturn(array(
			'paused_until' => time() + 300,
			'reason'       => '429 rate limit',
		));

		$strategy = new AIPS_Topic_Generation_Strategy(
			null,
			$ai_mock,
			null,
			$topics_repo,
			new class() { public function create($d) { return 1; } },
			new class() { public function is_enabled() { return true; } },
			new class() { public function get_reason_category_statistics($id) { return array(); } },
			new class() {
				public function build($a, $ap, $rej, $g) { return 'Prompt'; }
				public function get_sources_selection() { return array(); }
				public function record_sources_usage($s) {}
			},
			new class() { public function evaluate_topics_for_duplicates($t, $id) { return $t; } },
			new class() { public function update_topic_generation_last_run($id, $ts) { return true; } },
			null,
			$rate_limiter
		);
		$pipeline = new AIPS_Generation_Pipeline($strategy, new AIPS_Generation_Component_Registry());
		$ctx      = new AIPS_Author_Topic_Generation_Context($author, true, 'manual');

		// 1. Empty parsed topics -> no_topics_parsed.
		$no_parsed = $pipeline->execute($ctx);
		$this->assertTrue($no_parsed->is_failure());
		$this->assertSame('no_topics_parsed', $strategy->get_last_error()->get_error_code());

		// 2. DB bulk insert failure -> db_insert_error.
		$db_err = $pipeline->execute($ctx);
		$this->assertTrue($db_err->is_failure());
		$this->assertSame('db_insert_error', $strategy->get_last_error()->get_error_code());

		// 3. Similarity auto-approval during embeddings cooldown -> partial with topic_embedding=false.
		$bulk_ok  = true;
		$degraded = $pipeline->execute($ctx);
		$this->assertTrue($degraded->is_partial());
		$this->assertFalse($degraded->get_component_statuses()['topic_embedding']);

		// 4. Topic & Persona strategy previews.
		$this->assertWPError($pipeline->execute_preview(new AIPS_Author_Topic_Generation_Context(null)));
		$topic_preview = $pipeline->execute_preview($ctx);
		$this->assertIsArray($topic_preview);
		$this->assertCount(1, $topic_preview);

		$persona_ai = $this->createMock(AIPS_AI_Service_Interface::class);
		$persona_ai->method('generate_json')->willReturn(array(
			array('name' => 'Preview Persona', 'field_niche' => 'DevOps'),
		));
		$persona_strategy = new AIPS_Author_Persona_Strategy(
			null,
			$persona_ai,
			null,
			null,
			new class() { public function build(array $inputs, $count) { return 'Persona preview prompt'; } }
		);
		$persona_pipeline = new AIPS_Generation_Pipeline($persona_strategy, new AIPS_Generation_Component_Registry());
		$persona_preview  = $persona_pipeline->execute_preview(new AIPS_Author_Persona_Generation_Context(array('site_niche' => 'DevOps'), 1));
		$this->assertIsArray($persona_preview);
		$this->assertSame('Preview Persona', $persona_preview[0]['name']);
	}

	/**
	 * Topic strategy enforces title length/quantity caps and one-shot _aips_on_submission
	 * source tracking; Persona context/strategy enforce count clamping and missing_niche guard.
	 */
	public function test_topic_parse_bounds_source_submission_hook_and_persona_niche_and_count_bounds() {
		$author = $this->make_author(array('topic_generation_quantity' => 2));

		$recorded_selections = array();
		$selection_payload   = array('source_ids' => array(11, 22));
		$prompt_builder      = new class($selection_payload, $recorded_selections) {
			private $selection;
			public $recorded;
			public function __construct($selection, &$recorded) {
				$this->selection = $selection;
				$this->recorded  =& $recorded;
			}
			public function build($a, $ap, $rej, $g) { return 'Prompt with sources'; }
			public function get_sources_selection() { return $this->selection; }
			public function record_sources_usage($sel) { $this->recorded[] = $sel; }
		};

		$ai_double = new class() implements AIPS_AI_Service_Interface {
			public function is_available() { return true; }
			public function supports_embeddings() { return false; }
			public function supports_conversation() { return false; }
			public function get_call_log() { return array(); }
			public function generate_text($prompt, $options = array()) { return ''; }
			public function generate_image($prompt, $options = array()) { return ''; }
			public function generate_embedding($text, $options = array()) { return array(); }
			public function generate_json($prompt, $options = array()) {
				if (isset($options['_aips_on_submission']) && is_callable($options['_aips_on_submission'])) {
					// Invoke twice to prove idempotency guard inside the submission closure.
					call_user_func($options['_aips_on_submission']);
					call_user_func($options['_aips_on_submission']);
				}
				return array(
					array('title' => 'Too short', 'score' => 95, 'keywords' => array('short')),
					array('title' => 'First Valid Distributed Consensus Topic', 'score' => 91, 'keywords' => array('raft')),
					array('title' => 'Second Valid Microservice Resilience Topic', 'score' => 87, 'keywords' => array('sre')),
					array('title' => 'Third Valid Topic Beyond Requested Cap', 'score' => 84, 'keywords' => array('extra')),
				);
			}
		};

		$topics_repo = new class() {
			public $saved = array();
			public function get_approved_summary($author_id, $limit = 10) { return array(); }
			public function get_rejected_summary($author_id, $limit = 10) { return array(); }
			public function create_bulk($topics) {
				$this->saved = $topics;
				return true;
			}
			public function get_latest_by_author($author_id, $limit) {
				$out = array();
				foreach (array_reverse($this->saved) as $idx => $row) {
					$row['id'] = 800 - $idx;
					$out[]     = (object) $row;
				}
				return array_slice($out, 0, $limit);
			}
		};

		$topic_strategy = new AIPS_Topic_Generation_Strategy(
			null,
			$ai_double,
			null,
			$topics_repo,
			new class() { public function create($d) { return 1; } },
			new class() { public function is_enabled() { return false; } },
			new class() { public function get_reason_category_statistics($id) { return array(); } },
			$prompt_builder,
			new class() { public function evaluate_topics_for_duplicates($t, $id) { return $t; } },
			new class() { public function update_topic_generation_last_run($id, $ts) { return true; } }
		);

		$topic_res = (new AIPS_Generation_Pipeline($topic_strategy, new AIPS_Generation_Component_Registry()))
			->execute(new AIPS_Author_Topic_Generation_Context($author, false, 'manual'));

		$this->assertTrue($topic_res->is_success());
		$this->assertCount(2, $topics_repo->saved, 'Short (<10 char) titles must be dropped and valid topics capped at topic_generation_quantity.');
		$this->assertSame('First Valid Distributed Consensus Topic', $topics_repo->saved[0]['topic_title']);
		$this->assertSame('Second Valid Microservice Resilience Topic', $topics_repo->saved[1]['topic_title']);
		$this->assertCount(1, $recorded_selections, '_aips_on_submission must record source usage exactly once.');
		$this->assertSame(array(11, 22), $topic_strategy->get_last_sources_selection()['source_ids']);

		// Persona count clamping (1..10) & missing_niche error when both input and site setting are empty.
		$low_ctx  = new AIPS_Author_Persona_Generation_Context(array('site_niche' => ''), 0);
		$high_ctx = new AIPS_Author_Persona_Generation_Context(array('site_niche' => 'AI'), 15);
		$this->assertSame(1, $low_ctx->get_count());
		$this->assertSame(10, $high_ctx->get_count());

		$prev_niche = get_option('aips_content_niche', null);
		update_option('aips_content_niche', '');
		try {
			$persona_strategy = new AIPS_Author_Persona_Strategy(null, $ai_double);
			$missing_niche    = (new AIPS_Generation_Pipeline($persona_strategy, new AIPS_Generation_Component_Registry()))->execute($low_ctx);
			$this->assertTrue($missing_niche->is_failure());
			$this->assertSame('missing_niche', $persona_strategy->get_last_error()->get_error_code());
		} finally {
			if ($prev_niche === null) {
				delete_option('aips_content_niche');
			} else {
				update_option('aips_content_niche', $prev_niche);
			}
		}
	}
}
