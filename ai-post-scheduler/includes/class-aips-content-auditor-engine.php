<?php
/**
 * Content Auditor Engine
 *
 * Multi-module AI intelligence engine executing 5-pillar content audits:
 * 1. Topic Gaps & Missing Pillars
 * 2. Keyword Cannibalization & Overlap
 * 3. Content Decay & Freshness
 * 4. Internal Link Silos & Orphan Content
 * 5. Trusted Source Trend Grounding
 * Plus overall site health score synthesis.
 *
 * @package AI_Post_Scheduler
 * @since 3.4.2
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Content_Auditor_Engine {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * @var AIPS_Logger_Interface
	 */
	private $logger;

	/**
	 * @var AIPS_Sources_Repository
	 */
	private $sources_repo;

	/**
	 * @var AIPS_Sources_Data_Repository
	 */
	private $sources_data_repo;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null    $ai_service AI service instance.
	 * @param AIPS_Logger_Interface|null        $logger Logger instance.
	 * @param AIPS_Sources_Repository|null      $sources_repo Sources repository instance.
	 * @param AIPS_Sources_Data_Repository|null $sources_data_repo Sources data repository instance.
	 */
	public function __construct(
		?AIPS_AI_Service_Interface $ai_service = null,
		?AIPS_Logger_Interface $logger = null,
		?AIPS_Sources_Repository $sources_repo = null,
		?AIPS_Sources_Data_Repository $sources_data_repo = null
	) {
		$container = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
		$this->logger = $logger ?: ($container->has(AIPS_Logger_Interface::class) ? $container->make(AIPS_Logger_Interface::class) : new AIPS_Logger());
		$this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();
		$this->sources_data_repo = $sources_data_repo ?: AIPS_Sources_Data_Repository::instance();
	}

	/**
	 * Run a full multi-dimensional content audit.
	 *
	 * @param string $niche Target niche.
	 * @param array  $fingerprints Structured post fingerprints.
	 * @param array  $link_graph Link graph analysis data.
	 * @param array  $entity_clusters Entity cluster and library statistics.
	 * @param array  $options Optional execution options (module toggles, source group IDs).
	 * @return array Full audit report array.
	 */
	public function run_full_audit($niche, array $fingerprints, array $link_graph, array $entity_clusters, array $options = array()) {
		$enabled_modules = isset($options['modules']) && is_array($options['modules'])
			? $options['modules']
			: array('gaps', 'cannibalization', 'decay', 'links', 'trends');

		$source_group_ids = isset($options['source_group_ids']) ? (array) $options['source_group_ids'] : array();

		$report = array(
			'niche'        => $niche,
			'audited_at'   => current_time('mysql'),
			'total_posts'  => count($fingerprints),
			'modules'      => array(),
		);

		// 1. Topic Gaps
		if (in_array('gaps', $enabled_modules, true)) {
			$report['modules']['gaps'] = $this->analyze_topic_gaps($niche, $fingerprints, $entity_clusters);
		}

		// 2. Keyword Cannibalization
		if (in_array('cannibalization', $enabled_modules, true)) {
			$candidates = isset($entity_clusters['cannibalization_candidates']) ? $entity_clusters['cannibalization_candidates'] : array();
			$report['modules']['cannibalization'] = $this->analyze_cannibalization($candidates);
		}

		// 3. Content Decay
		if (in_array('decay', $enabled_modules, true)) {
			$decayed = isset($entity_clusters['decayed_posts']) ? $entity_clusters['decayed_posts'] : array();
			$thin    = isset($entity_clusters['thin_posts']) ? $entity_clusters['thin_posts'] : array();
			$report['modules']['decay'] = $this->analyze_content_decay($decayed, $thin);
		}

		// 4. Internal Link Silos
		if (in_array('links', $enabled_modules, true)) {
			$report['modules']['links'] = $this->analyze_internal_linking($link_graph, $fingerprints);
		}

		// 5. Source Trends
		if (in_array('trends', $enabled_modules, true)) {
			$report['modules']['trends'] = $this->analyze_source_trends($niche, $fingerprints, $source_group_ids);
		}

		// Synthesize Overall Health Scorecard
		$report['health_scorecard'] = $this->synthesize_overall_health($report['modules'], $entity_clusters, $link_graph);

		return $report;
	}

	/**
	 * Module 1: Analyze Topic Gaps & Missing Pillar Content.
	 *
	 * @param string $niche Target niche.
	 * @param array  $fingerprints Structured post fingerprints.
	 * @param array  $entity_clusters Entity clusters and category counts.
	 * @return array
	 */
	public function analyze_topic_gaps($niche, array $fingerprints, array $entity_clusters) {
		$site_context = AIPS_Site_Context::get();
		$top_keyphrases = isset($entity_clusters['top_keyphrases']) ? array_keys(array_slice($entity_clusters['top_keyphrases'], 0, 25)) : array();
		$categories = isset($entity_clusters['category_distribution']) ? array_keys($entity_clusters['category_distribution']) : array();

		$sample_titles = array();
		foreach (array_slice($fingerprints, 0, 40) as $fp) {
			$cats = !empty($fp['categories']) ? implode(', ', $fp['categories']) : 'General';
			$sample_titles[] = "- {$fp['title']} (Category: {$cats})";
		}

		$prompt = "You are an expert SEO Content Strategist.\n\n";
		$prompt .= "Target Niche: {$niche}\n";
		if (!empty($site_context['target_audience'])) {
			$prompt .= "Target Audience: {$site_context['target_audience']}\n";
		}
		if (!empty($site_context['content_goals'])) {
			$prompt .= "Content Goals: {$site_context['content_goals']}\n";
		}

		$prompt .= "\nCovered Categories: " . implode(', ', $categories) . "\n";
		$prompt .= "Current Key Entities: " . implode(', ', $top_keyphrases) . "\n\n";
		$prompt .= "Sample Existing Articles (" . count($sample_titles) . " of " . count($fingerprints) . " total):\n";
		$prompt .= (empty($sample_titles) ? '(No existing articles found)' : implode("\n", $sample_titles)) . "\n\n";

		$prompt .= "Task: Perform a deep gap analysis against the target niche. Identify 5-8 high-impact missing topics or content clusters needed to establish complete topical authority.\n\n";
		$prompt .= "Return a JSON array of objects with keys: \"missing_topic\" (string), \"priority\" (\"High\"|\"Medium\"), \"type\" (\"Pillar\"|\"Cluster\"), \"search_intent\" (\"Informational\"|\"Commercial\"|\"Transactional\"|\"Navigational\"), \"reason\" (string), \"suggested_angle\" (string).";

		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'missing_topic'   => array('type' => 'string'),
					'priority'        => array('type' => 'string', 'enum' => array('High', 'Medium')),
					'type'            => array('type' => 'string', 'enum' => array('Pillar', 'Cluster')),
					'search_intent'   => array('type' => 'string', 'enum' => array('Informational', 'Commercial', 'Transactional', 'Navigational')),
					'reason'          => array('type' => 'string'),
					'suggested_angle' => array('type' => 'string'),
				),
				'required'   => array('missing_topic', 'priority', 'type', 'search_intent', 'reason', 'suggested_angle'),
			),
		);

		$options = array(
			'temperature' => 0.6,
			'json_schema' => $schema,
		);

		$result = $this->ai_service->generate_json($prompt, $options);

		if (is_wp_error($result) || !is_array($result)) {
			// Fallback text parsing
			$text_res = $this->ai_service->generate_text($prompt, array('temperature' => 0.6));
			$result   = is_string($text_res) ? $this->parse_json_fallback($text_res) : array();
		}

		return array(
			'gaps'        => is_array($result) ? $result : array(),
			'gap_count'   => is_array($result) ? count($result) : 0,
			'niche'       => $niche,
		);
	}

	/**
	 * Module 2: Analyze Keyword Cannibalization & Overlapping Articles.
	 *
	 * @param array $candidates Candidate overlap pairs from scanner.
	 * @return array
	 */
	public function analyze_cannibalization(array $candidates) {
		if (empty($candidates)) {
			return array(
				'conflicts'       => array(),
				'conflict_count'  => 0,
				'status'          => 'clean',
			);
		}

		$pairs_to_analyze = array_slice($candidates, 0, 10);
		$pairs_text = '';
		foreach ($pairs_to_analyze as $idx => $pair) {
			$pairs_text .= sprintf(
				"Pair #%d:\n- Post A (ID %d): \"%s\" (%s)\n- Post B (ID %d): \"%s\" (%s)\n- Similarity: %s, Shared terms: %s\n\n",
				$idx + 1,
				$pair['post_a']['id'],
				$pair['post_a']['title'],
				$pair['post_a']['url'],
				$pair['post_b']['id'],
				$pair['post_b']['title'],
				$pair['post_b']['url'],
				$pair['similarity_score'],
				implode(', ', $pair['shared_keyphrases'])
			);
		}

		$prompt = "You are an SEO Cannibalization & Content Consolidation Specialist.\n\n";
		$prompt .= "Evaluate the following candidate pairs of competing articles on the same site:\n\n";
		$prompt .= $pairs_text;
		$prompt .= "Task: Determine whether each pair represents true keyword/intent cannibalization. Assign a severity level, identify which article should be the primary keeper vs merged/redirected, and recommend concrete action.\n\n";
		$prompt .= "Return a JSON array of objects with keys: \"pair_index\" (int), \"is_cannibalizing\" (boolean), \"severity\" (\"High\"|\"Moderate\"|\"Low\"), \"primary_post_id\" (int), \"secondary_post_id\" (int), \"conflict_summary\" (string), \"action_recommendation\" (\"Consolidate & 301 Redirect\"|\"Differentiate Angle & Re-target\"|\"Keep Both - No Conflict\").";

		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'pair_index'            => array('type' => 'integer'),
					'is_cannibalizing'      => array('type' => 'boolean'),
					'severity'              => array('type' => 'string', 'enum' => array('High', 'Moderate', 'Low')),
					'primary_post_id'       => array('type' => 'integer'),
					'secondary_post_id'     => array('type' => 'integer'),
					'conflict_summary'      => array('type' => 'string'),
					'action_recommendation' => array('type' => 'string', 'enum' => array('Consolidate & 301 Redirect', 'Differentiate Angle & Re-target', 'Keep Both - No Conflict')),
				),
				'required'   => array('pair_index', 'is_cannibalizing', 'severity', 'primary_post_id', 'secondary_post_id', 'conflict_summary', 'action_recommendation'),
			),
		);

		$options = array(
			'temperature' => 0.5,
			'json_schema' => $schema,
		);

		$result = $this->ai_service->generate_json($prompt, $options);

		if (is_wp_error($result) || !is_array($result)) {
			$text_res = $this->ai_service->generate_text($prompt, array('temperature' => 0.5));
			$result   = is_string($text_res) ? $this->parse_json_fallback($text_res) : array();
		}

		$conflicts = array();
		if (is_array($result)) {
			foreach ($result as $item) {
				if (!empty($item['is_cannibalizing'])) {
					$conflicts[] = $item;
				}
			}
		}

		return array(
			'conflicts'      => $conflicts,
			'conflict_count' => count($conflicts),
			'status'         => count($conflicts) > 0 ? 'action_required' : 'clean',
		);
	}

	/**
	 * Module 3: Analyze Content Decay & Stale Articles.
	 *
	 * @param array $decayed_posts Array of decayed post summaries.
	 * @param array $thin_posts Array of thin post summaries.
	 * @return array
	 */
	public function analyze_content_decay(array $decayed_posts, array $thin_posts) {
		$posts_to_evaluate = array_slice($decayed_posts, 0, 15);

		if (empty($posts_to_evaluate) && empty($thin_posts)) {
			return array(
				'recommendations' => array(),
				'decay_count'     => 0,
				'thin_count'      => 0,
				'health_status'   => 'fresh',
			);
		}

		$post_list = '';
		foreach ($posts_to_evaluate as $p) {
			$post_list .= sprintf("- ID %d: \"%s\" (Age: %d days, Words: %d)\n", $p['id'], $p['title'], $p['age_days'], $p['word_count']);
		}

		$prompt = "You are a Content Lifecycle & Maintenance Strategist.\n\n";
		$prompt .= "Here are outdated or stale posts from the site:\n\n";
		$prompt .= $post_list . "\n";
		$prompt .= "Task: Recommend a prioritized refresh plan for these articles to reclaim lost rankings and update stale advice.\n\n";
		$prompt .= "Return a JSON array of objects with keys: \"post_id\" (int), \"title\" (string), \"urgency\" (\"Urgent\"|\"Moderate\"), \"refresh_actions\" (array of strings), \"suggested_word_target\" (int), \"editorial_notes\" (string).";

		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'               => array('type' => 'integer'),
					'title'                 => array('type' => 'string'),
					'urgency'               => array('type' => 'string', 'enum' => array('Urgent', 'Moderate')),
					'refresh_actions'       => array('type' => 'array', 'items' => array('type' => 'string')),
					'suggested_word_target' => array('type' => 'integer'),
					'editorial_notes'       => array('type' => 'string'),
				),
				'required'   => array('post_id', 'title', 'urgency', 'refresh_actions', 'suggested_word_target', 'editorial_notes'),
			),
		);

		$options = array(
			'temperature' => 0.5,
			'json_schema' => $schema,
		);

		$result = $this->ai_service->generate_json($prompt, $options);

		if (is_wp_error($result) || !is_array($result)) {
			$text_res = $this->ai_service->generate_text($prompt, array('temperature' => 0.5));
			$result   = is_string($text_res) ? $this->parse_json_fallback($text_res) : array();
		}

		return array(
			'recommendations' => is_array($result) ? $result : array(),
			'decay_count'     => count($decayed_posts),
			'thin_count'      => count($thin_posts),
			'health_status'   => count($decayed_posts) > 5 ? 'decay_alert' : 'acceptable',
		);
	}

	/**
	 * Module 4: Analyze Internal Link Silos & Orphan Content.
	 *
	 * @param array $link_graph Link graph data from scanner.
	 * @param array $fingerprints Post fingerprints.
	 * @return array
	 */
	public function analyze_internal_linking(array $link_graph, array $fingerprints) {
		$orphan_ids = isset($link_graph['orphan_post_ids']) ? $link_graph['orphan_post_ids'] : array();
		$silos      = isset($link_graph['category_silo_health']) ? $link_graph['category_silo_health'] : array();

		$fp_map = array();
		foreach ($fingerprints as $fp) {
			$fp_map[$fp['id']] = $fp;
		}

		$orphan_summaries = array();
		foreach (array_slice($orphan_ids, 0, 15) as $oid) {
			if (isset($fp_map[$oid])) {
				$orphan_summaries[] = $fp_map[$oid];
			}
		}

		$suggestions = array();
		// Heuristic contextual link matching for orphans
		foreach ($orphan_summaries as $orphan) {
			$matching_target = null;
			$orphan_cats     = !empty($orphan['categories']) ? $orphan['categories'] : array();

			foreach ($fingerprints as $candidate) {
				if ($candidate['id'] === $orphan['id']) {
					continue;
				}

				$shared_cats = array_intersect($orphan_cats, !empty($candidate['categories']) ? $candidate['categories'] : array());
				if (!empty($shared_cats) && !empty($candidate['inbound_link_count'])) {
					$matching_target = $candidate;
					break;
				}
			}

			if ($matching_target) {
				$suggestions[] = array(
					'orphan_post_id'     => $orphan['id'],
					'orphan_title'       => $orphan['title'],
					'suggested_source_id' => $matching_target['id'],
					'suggested_source_title' => $matching_target['title'],
					'shared_category'    => reset($orphan_cats),
					'recommended_anchor' => $orphan['title'],
					'rationale'          => sprintf('Connect orphan article in "%s" category to high-authority peer page.', reset($orphan_cats)),
				);
			}
		}

		return array(
			'orphan_count'               => count($orphan_ids),
			'orphan_posts'               => $orphan_summaries,
			'total_internal_connections' => isset($link_graph['total_internal_connections']) ? $link_graph['total_internal_connections'] : 0,
			'silo_health'                => $silos,
			'link_suggestions'           => $suggestions,
		);
	}

	/**
	 * Module 5: Analyze External Source & Competitor Trends vs Site Coverage.
	 *
	 * @param string $niche Target niche.
	 * @param array  $fingerprints Post fingerprints.
	 * @param int[]  $source_group_ids Group term IDs to query.
	 * @return array
	 */
	public function analyze_source_trends($niche, array $fingerprints, array $source_group_ids = array()) {
		$source_rows = array();
		if (!empty($source_group_ids)) {
			$source_rows = $this->sources_repo->get_by_group_term_ids($source_group_ids, true);
		} else {
			$source_rows = $this->sources_repo->get_all(20, 0, 'active');
		}

		if (empty($source_rows)) {
			return array(
				'trends'       => array(),
				'trend_count'  => 0,
				'sources_used' => 0,
				'status'       => 'no_sources_configured',
			);
		}

		$source_ids = array_map(function ($s) { return (int) $s->id; }, $source_rows);
		$snapshots  = $this->sources_data_repo->pick_next_for_prompt_bulk($source_ids);

		$extracted_snippets = '';
		foreach ($source_rows as $src) {
			$sid = (int) $src->id;
			$label = !empty($src->label) ? $src->label : $src->url;
			if (isset($snapshots[$sid])) {
				$snippet = mb_substr($snapshots[$sid]->extracted_text, 0, 600);
				$extracted_snippets .= sprintf("Source: %s\nSnippet: %s\n\n", $label, $snippet);
			}
		}

		if (empty($extracted_snippets)) {
			return array(
				'trends'       => array(),
				'trend_count'  => 0,
				'sources_used' => count($source_rows),
				'status'       => 'no_snapshot_content_available',
			);
		}

		$sample_titles = array();
		foreach (array_slice($fingerprints, 0, 30) as $fp) {
			$sample_titles[] = "- {$fp['title']}";
		}

		$prompt = "You are a Real-Time Content & Industry Trend Analyst.\n\n";
		$prompt .= "Target Niche: {$niche}\n\n";
		$prompt .= "Recent Scraped Content from Industry Sources/Feeds:\n";
		$prompt .= $extracted_snippets . "\n";
		$prompt .= "Existing Published Titles on Site (" . count($sample_titles) . "):\n";
		$prompt .= implode("\n", $sample_titles) . "\n\n";

		$prompt .= "Task: Identify 3-5 emerging industry trends, breaking developments, or novel topics present in the sources that the site has NOT covered yet.\n\n";
		$prompt .= "Return a JSON array of objects with keys: \"trend_topic\" (string), \"source_evidence\" (string), \"trend_urgency\" (\"High\"|\"Medium\"), \"recommended_article_angle\" (string).";

		$schema = array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'trend_topic'               => array('type' => 'string'),
					'source_evidence'           => array('type' => 'string'),
					'trend_urgency'             => array('type' => 'string', 'enum' => array('High', 'Medium')),
					'recommended_article_angle' => array('type' => 'string'),
				),
				'required'   => array('trend_topic', 'source_evidence', 'trend_urgency', 'recommended_article_angle'),
			),
		);

		$options = array(
			'temperature' => 0.6,
			'json_schema' => $schema,
		);

		$result = $this->ai_service->generate_json($prompt, $options);

		if (is_wp_error($result) || !is_array($result)) {
			$text_res = $this->ai_service->generate_text($prompt, array('temperature' => 0.6));
			$result   = is_string($text_res) ? $this->parse_json_fallback($text_res) : array();
		}

		return array(
			'trends'       => is_array($result) ? $result : array(),
			'trend_count'  => is_array($result) ? count($result) : 0,
			'sources_used' => count($source_rows),
			'status'       => 'success',
		);
	}

	/**
	 * Synthesize overall content health score (0-100) and scorecard metrics.
	 *
	 * @param array $modules Executed module results.
	 * @param array $entity_clusters Entity clusters and library statistics.
	 * @param array $link_graph Internal link graph data.
	 * @return array
	 */
	public function synthesize_overall_health(array $modules, array $entity_clusters, array $link_graph) {
		$total_posts = isset($entity_clusters['total_posts']) ? (int) $entity_clusters['total_posts'] : 1;
		if ($total_posts <= 0) {
			$total_posts = 1;
		}

		// 1. Freshness Score (0-100)
		$decay_count = isset($entity_clusters['decay_count']) ? (int) $entity_clusters['decay_count'] : 0;
		$thin_count  = isset($entity_clusters['thin_count']) ? (int) $entity_clusters['thin_count'] : 0;
		$stale_ratio = min(1.0, ($decay_count + $thin_count) / $total_posts);
		$freshness_score = (int) round(max(0, 100 - ($stale_ratio * 100)));

		// 2. Silo & Link Health Score (0-100)
		$orphan_count = isset($link_graph['orphan_count']) ? (int) $link_graph['orphan_count'] : 0;
		$orphan_ratio = min(1.0, $orphan_count / $total_posts);
		$link_score   = (int) round(max(0, 100 - ($orphan_ratio * 100)));

		// 3. Cannibalization Health Score (0-100)
		$cannibalization_count = isset($modules['cannibalization']['conflict_count']) ? (int) $modules['cannibalization']['conflict_count'] : 0;
		$cannibalization_score = (int) round(max(0, 100 - min(100, $cannibalization_count * 15)));

		// 4. Gap Index (0-100)
		$gap_count = isset($modules['gaps']['gap_count']) ? (int) $modules['gaps']['gap_count'] : 0;
		$gap_score = (int) round(max(20, min(100, 100 - ($gap_count * 8))));

		// Overall Weighted Score
		$overall_score = (int) round(
			($freshness_score * 0.30) +
			($link_score * 0.30) +
			($cannibalization_score * 0.20) +
			($gap_score * 0.20)
		);

		// Key takeaways
		$takeaways = array();
		if ($orphan_count > 0) {
			$takeaways[] = sprintf('%d orphan post(s) found with zero internal inbound links.', $orphan_count);
		}
		if ($decay_count > 0) {
			$takeaways[] = sprintf('%d post(s) are older than 180 days with no updates.', $decay_count);
		}
		if ($cannibalization_count > 0) {
			$takeaways[] = sprintf('%d keyword cannibalization conflict(s) detected.', $cannibalization_count);
		}
		if ($gap_count > 0) {
			$takeaways[] = sprintf('%d high-value content gaps identified for your niche.', $gap_count);
		}
		if (empty($takeaways)) {
			$takeaways[] = 'Your site demonstrates strong topical coverage, fresh content, and solid internal linking.';
		}

		return array(
			'overall_score'         => max(0, min(100, $overall_score)),
			'freshness_score'       => $freshness_score,
			'link_score'            => $link_score,
			'cannibalization_score' => $cannibalization_score,
			'gap_score'             => $gap_score,
			'key_takeaways'         => $takeaways,
		);
	}

	/**
	 * Parse JSON from raw AI text response as fallback.
	 *
	 * @param string $response Raw AI response text.
	 * @return array
	 */
	private function parse_json_fallback($response) {
		if (preg_match('/```json\s*([\s\S]*?)\s*```/', $response, $matches)) {
			$response = $matches[1];
		} elseif (preg_match('/```\s*([\s\S]*?)\s*```/', $response, $matches)) {
			$response = $matches[1];
		}

		$decoded = json_decode(trim($response), true);
		return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : array();
	}
}
