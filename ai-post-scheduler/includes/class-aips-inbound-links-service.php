<?php
/**
 * Inbound Links Service
 *
 * Suggests which existing posts should link TO a target post (the fix for
 * orphans), and applies / reverts / dismisses individual suggestions.
 *
 * Candidates come from semantic relationships (embeddings) first, with a
 * keyword fallback for posts without embeddings. Anchor text is chosen
 * locally, with no AI calls: phrases derived from the target's title and SEO
 * focus keyword are matched against linkable text in each candidate via
 * AIPS_Link_Insertion_Engine.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.5
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Inbound_Links_Service
 */
class AIPS_Inbound_Links_Service {

	/**
	 * Suggestion origin stored on aips_internal_links rows.
	 */
	const ORIGIN = 'inbound';

	/**
	 * Semantic candidates fetched per target before filtering.
	 */
	const SEMANTIC_CANDIDATES = 30;

	/**
	 * Keyword-search candidates fetched per phrase.
	 */
	const KEYWORD_CANDIDATES = 15;

	/**
	 * Confidence ceiling for keyword-only matches, kept below the default
	 * auto-apply threshold so they always go to review.
	 */
	const KEYWORD_CONFIDENCE_CAP = 0.80;

	/**
	 * Words ignored at the edges of anchor phrases.
	 *
	 * @var string[]
	 */
	private static $stop_words = array(
		'a', 'about', 'after', 'all', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'best', 'but', 'by',
		'can', 'do', 'does', 'for', 'from', 'get', 'guide', 'has', 'have', 'how', 'i', 'if', 'in', 'into',
		'is', 'it', 'its', 'my', 'new', 'not', 'of', 'on', 'or', 'our', 'so', 'than', 'that', 'the',
		'their', 'this', 'to', 'top', 'up', 'use', 'vs', 'was', 'we', 'what', 'when', 'where', 'which',
		'who', 'why', 'will', 'with', 'you', 'your',
	);

	/**
	 * @var AIPS_Internal_Links_Repository
	 */
	private $links_repo;

	/**
	 * @var AIPS_Link_Index_Service
	 */
	private $link_index;

	/**
	 * @var AIPS_Relationships_Repository
	 */
	private $relationships_repo;

	/**
	 * @var AIPS_Link_Insertion_Engine
	 */
	private $engine;

	/**
	 * @var AIPS_Autolink_Policy
	 */
	private $policy;

	/**
	 * @param AIPS_Internal_Links_Repository|null $links_repo         Suggestions repository.
	 * @param AIPS_Link_Index_Service|null        $link_index         Link index service.
	 * @param AIPS_Relationships_Repository|null  $relationships_repo Semantic relationships.
	 * @param AIPS_Link_Insertion_Engine|null     $engine             Insertion engine.
	 * @param AIPS_Autolink_Policy|null           $policy             Link placement policy.
	 */
	public function __construct(
		?AIPS_Internal_Links_Repository $links_repo = null,
		?AIPS_Link_Index_Service $link_index = null,
		?AIPS_Relationships_Repository $relationships_repo = null,
		?AIPS_Link_Insertion_Engine $engine = null,
		?AIPS_Autolink_Policy $policy = null
	) {
		$container                = AIPS_Container::get_instance();
		$this->links_repo         = $links_repo ?: new AIPS_Internal_Links_Repository();
		$this->link_index         = $link_index ?: ($container->has(AIPS_Link_Index_Service::class) ? $container->make(AIPS_Link_Index_Service::class) : new AIPS_Link_Index_Service());
		$this->relationships_repo = $relationships_repo ?: ($container->has(AIPS_Relationships_Repository::class) ? $container->make(AIPS_Relationships_Repository::class) : new AIPS_Relationships_Repository());
		$this->engine             = $engine ?: new AIPS_Link_Insertion_Engine();
		$this->policy             = $policy ?: new AIPS_Autolink_Policy();
	}

	/**
	 * (Re)generate inbound suggestions for a target post.
	 *
	 * Pending inbound suggestions for the target are replaced; accepted,
	 * inserted, rejected and reverted rows are kept.
	 *
	 * @param int $target_id Post that should receive links.
	 * @param int $max       Maximum suggestions to keep.
	 * @return array{suggestions:array, phrases:string[], semantic:int, keyword:int}|WP_Error
	 */
	public function generate_for_target(int $target_id, int $max = 10) {
		$target = get_post($target_id);
		if (!$target instanceof WP_Post || !$this->link_index->is_post_in_scope($target)) {
			return new WP_Error('aips_inbound_invalid_target', __('Suggestions are only available for published posts in the link index.', 'ai-post-scheduler'));
		}

		$phrases    = $this->get_anchor_phrases($target);
		$candidates = $this->find_candidates($target, $phrases);
		$target_url = (string) get_permalink($target_id);
		$settings   = $this->policy->get_settings();
		$found      = array();

		foreach ($candidates as $source_id => $candidate) {
			$source = get_post($source_id);
			if (!$source instanceof WP_Post || !$this->link_index->is_post_in_scope($source)) {
				continue;
			}

			if ($this->link_index->get_repository()->source_links_to($source_id, $target_id) || $this->engine->has_link_to((string) $source->post_content, $target_url)) {
				continue;
			}

			$occurrences = $this->engine->find_phrase_occurrences(
				(string) $source->post_content,
				$phrases,
				array(
					'skip_first_paragraph' => $settings['skip_first_paragraph'],
					'limit'                => 1,
				)
			);
			$occurrence = !empty($occurrences) ? $occurrences[0] : null;

			if ($occurrence === null && $candidate['via'] === 'keyword') {
				continue;
			}

			$found[] = array(
				'source_post_id'   => $source_id,
				'target_post_id'   => $target_id,
				'similarity_score' => $candidate['similarity'],
				'confidence'       => $this->score($candidate, $occurrence, $phrases),
				'anchor_text'      => $occurrence ? $occurrence['text'] : '',
				'anchor_source'    => $occurrence ? ($candidate['via'] === 'keyword' ? 'keyword' : 'phrase') : 'none',
				'match_context'    => $occurrence ? $this->context_snippet((string) $source->post_content, $occurrence) : '',
				'origin'           => self::ORIGIN,
			);
		}

		usort($found, function ($a, $b) {
			return $b['confidence'] <=> $a['confidence'];
		});
		$found = array_slice($found, 0, max(1, $max));

		$this->links_repo->delete_pending_by_target_post($target_id, self::ORIGIN);
		foreach ($found as $suggestion) {
			$this->links_repo->save_suggestion($suggestion);
		}

		$via = array_count_values(wp_list_pluck($candidates, 'via'));

		return array(
			'suggestions' => $this->get_suggestions($target_id),
			'phrases'     => $phrases,
			'semantic'    => isset($via['semantic']) ? (int) $via['semantic'] : 0,
			'keyword'     => isset($via['keyword']) ? (int) $via['keyword'] : 0,
		);
	}

	/**
	 * Inbound suggestions for a target, formatted for the admin UI.
	 *
	 * @param int $target_id Target post ID.
	 * @return array[]
	 */
	public function get_suggestions(int $target_id): array {
		$rows = $this->links_repo->get_by_target_post($target_id, array('pending', 'inserted'), self::ORIGIN);
		$out  = array();

		foreach ($rows as $row) {
			$out[] = array(
				'id'            => (int) $row->id,
				'source_id'     => (int) $row->source_post_id,
				'source_title'  => $row->source_post_title !== null && $row->source_post_title !== '' ? $row->source_post_title : sprintf('#%d', (int) $row->source_post_id),
				'source_edit'   => (string) get_edit_post_link((int) $row->source_post_id, 'raw'),
				'anchor'        => (string) $row->anchor_text,
				'anchor_source' => (string) $row->anchor_source,
				'context'       => (string) $row->match_context,
				'confidence'    => round((float) $row->confidence * 100),
				'similarity'    => round((float) $row->similarity_score * 100),
				'status'        => (string) $row->status,
			);
		}

		return $out;
	}

	/**
	 * Insert a suggestion's link into its source post.
	 *
	 * The anchor phrase is located again in the current content, so edits
	 * made since the suggestion was generated are respected.
	 *
	 * @param int $suggestion_id Suggestion row ID.
	 * @return array{id:int, anchor:string}|WP_Error
	 */
	public function apply(int $suggestion_id) {
		$row = $this->links_repo->get_by_id($suggestion_id);
		if (!$row || $row->status !== 'pending') {
			return new WP_Error('aips_inbound_not_pending', __('This suggestion is no longer pending.', 'ai-post-scheduler'));
		}

		if (trim((string) $row->anchor_text) === '') {
			return new WP_Error('aips_inbound_no_anchor', __('No anchor text was found for this suggestion. Add the link manually in the editor.', 'ai-post-scheduler'));
		}

		$source_id = (int) $row->source_post_id;
		$source    = get_post($source_id);
		$target_id = (int) $row->target_post_id;
		if (!$source instanceof WP_Post || !get_post($target_id)) {
			return new WP_Error('aips_inbound_missing_post', __('The source or target post no longer exists.', 'ai-post-scheduler'));
		}

		$locked_by = function_exists('wp_check_post_lock') ? wp_check_post_lock($source_id) : false;
		if ($locked_by) {
			return new WP_Error('aips_inbound_post_locked', __('Someone is editing the source post right now. Try again when they are done.', 'ai-post-scheduler'));
		}

		$content    = (string) $source->post_content;
		$target_url = (string) get_permalink($target_id);

		if ($this->engine->has_link_to($content, $target_url)) {
			$this->links_repo->update_status($suggestion_id, 'accepted');
			return new WP_Error('aips_inbound_already_linked', __('The source post already links to this post.', 'ai-post-scheduler'));
		}

		$occurrences = $this->engine->find_phrase_occurrences(
			$content,
			array((string) $row->anchor_text),
			array(
				'skip_first_paragraph' => $this->policy->get_settings()['skip_first_paragraph'],
				'limit'                => 1,
			)
		);

		if (empty($occurrences)) {
			return new WP_Error('aips_inbound_anchor_missing', __('The anchor text is no longer in a linkable part of the source post. Regenerate suggestions to find a new spot.', 'ai-post-scheduler'));
		}

		$attributes           = $this->policy->get_link_attributes();
		$attributes['marker'] = $suggestion_id;

		$inserted = $this->engine->insert($content, $occurrences[0], $target_url, $attributes);
		if (is_wp_error($inserted)) {
			return $inserted;
		}

		$saved = wp_update_post(
			array(
				'ID'           => $source_id,
				'post_content' => wp_slash($inserted['content']),
			),
			true
		);
		if (is_wp_error($saved)) {
			return $saved;
		}

		$this->links_repo->mark_applied($suggestion_id, $inserted['before_snippet'], $inserted['after_snippet'], $occurrences[0]['text']);

		/**
		 * Fires after an internal link suggestion is inserted into its source post.
		 *
		 * @param int $suggestion_id Suggestion row ID.
		 * @param int $source_id     Post that received the link.
		 * @param int $target_id     Post being linked to.
		 */
		do_action('aips_internal_link_inserted', $suggestion_id, $source_id, $target_id);

		return array(
			'id'     => $suggestion_id,
			'anchor' => $occurrences[0]['text'],
		);
	}

	/**
	 * Remove a previously inserted link, restoring the original text.
	 *
	 * @param int $suggestion_id Suggestion row ID.
	 * @return true|WP_Error
	 */
	public function revert(int $suggestion_id) {
		$row = $this->links_repo->get_by_id($suggestion_id);
		if (!$row || $row->status !== 'inserted' || empty($row->after_snippet)) {
			return new WP_Error('aips_inbound_not_inserted', __('This link was not inserted by AI Post Scheduler, so it cannot be undone here.', 'ai-post-scheduler'));
		}

		$source_id = (int) $row->source_post_id;
		$source    = get_post($source_id);
		if (!$source instanceof WP_Post) {
			return new WP_Error('aips_inbound_missing_post', __('The source post no longer exists.', 'ai-post-scheduler'));
		}

		$result = $this->engine->revert((string) $source->post_content, (string) $row->before_snippet, (string) $row->after_snippet);

		if ($result['status'] !== AIPS_Link_Insertion_Engine::REVERT_OK) {
			return new WP_Error(
				'aips_inbound_revert_conflict',
				__('The source post was edited after the link was added, so it cannot be undone automatically. Remove the link in the editor.', 'ai-post-scheduler')
			);
		}

		$saved = wp_update_post(
			array(
				'ID'           => $source_id,
				'post_content' => wp_slash($result['content']),
			),
			true
		);
		if (is_wp_error($saved)) {
			return $saved;
		}

		$this->links_repo->update_status($suggestion_id, 'reverted');

		/**
		 * Fires after an inserted internal link is reverted.
		 *
		 * @param int $suggestion_id Suggestion row ID.
		 * @param int $source_id     Post the link was removed from.
		 */
		do_action('aips_internal_link_reverted', $suggestion_id, $source_id);

		return true;
	}

	/**
	 * Dismiss a pending suggestion so it is not suggested again.
	 *
	 * @param int $suggestion_id Suggestion row ID.
	 * @return bool
	 */
	public function dismiss(int $suggestion_id): bool {
		$row = $this->links_repo->get_by_id($suggestion_id);
		if (!$row || $row->status !== 'pending') {
			return false;
		}

		return (bool) $this->links_repo->update_status($suggestion_id, 'rejected');
	}

	/**
	 * Candidate anchor phrases for a target, most specific first.
	 *
	 * Sources: SEO focus keywords (Yoast, Rank Math), the full title when
	 * short, then 4- to 2-word runs of the title that do not start or end
	 * with a stop word.
	 *
	 * @param WP_Post $target Target post.
	 * @return string[]
	 */
	public function get_anchor_phrases(WP_Post $target): array {
		$phrases = array();

		foreach (array('_yoast_wpseo_focuskw', 'rank_math_focus_keyword') as $meta_key) {
			$value = (string) get_post_meta($target->ID, $meta_key, true);
			foreach (explode(',', $value) as $keyword) {
				$phrases[] = $keyword;
			}
		}

		$title = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(html_entity_decode((string) $target->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
		$words = preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\'\-\s]+/u', ' ', $title)));
		$words = array_values(array_filter((array) $words, 'strlen'));

		if (count($words) >= 2 && count($words) <= 8) {
			$phrases[] = implode(' ', $words);
		}

		for ($size = min(4, count($words)); $size >= 2; $size--) {
			for ($i = 0; $i + $size <= count($words); $i++) {
				$run   = array_slice($words, $i, $size);
				$first = strtolower($run[0]);
				$last  = strtolower($run[$size - 1]);
				if (in_array($first, self::$stop_words, true) || in_array($last, self::$stop_words, true)) {
					continue;
				}
				$phrases[] = implode(' ', $run);
			}
		}

		$unique = array();
		foreach ($phrases as $phrase) {
			$phrase = trim($phrase);
			if (mb_strlen($phrase) < 4) {
				continue;
			}
			$unique[mb_strtolower($phrase)] = $phrase;
		}

		/**
		 * Filter the anchor phrases used to place inbound links to a post.
		 *
		 * @param string[] $phrases Phrases, most specific first.
		 * @param WP_Post  $target  Target post.
		 */
		return array_values((array) apply_filters('aips_inbound_anchor_phrases', array_slice(array_values($unique), 0, 15), $target));
	}

	/**
	 * Candidate source posts: semantic neighbours, then keyword matches.
	 *
	 * @param WP_Post  $target  Target post.
	 * @param string[] $phrases Anchor phrases.
	 * @return array<int, array{similarity:float, via:string}> source_id => candidate.
	 */
	private function find_candidates(WP_Post $target, array $phrases): array {
		$candidates = array();

		$related = $this->relationships_repo->get_related('post', $target->ID, self::SEMANTIC_CANDIDATES, 0.5);
		foreach ($related as $row) {
			if (isset($row->target_type) && $row->target_type !== 'post') {
				continue;
			}
			$id = (int) $row->target_id;
			if ($id > 0 && $id !== $target->ID) {
				$candidates[$id] = array(
					'similarity' => (float) $row->similarity,
					'via'        => 'semantic',
				);
			}
		}

		// Search the shortest phrases first: they appear in far more posts, and
		// the anchor itself is still chosen from the full, most-specific-first list.
		$search_phrases = $phrases;
		usort($search_phrases, function ($a, $b) {
			return str_word_count($a) <=> str_word_count($b);
		});

		foreach (array_slice($search_phrases, 0, 6) as $phrase) {
			$ids = get_posts(array(
				's'                      => $phrase,
				'sentence'               => true,
				'post_type'              => $this->link_index->get_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => self::KEYWORD_CANDIDATES,
				'fields'                 => 'ids',
				'post__not_in'           => array($target->ID),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			));

			foreach ((array) $ids as $id) {
				$id = (int) $id;
				if (!isset($candidates[$id])) {
					$candidates[$id] = array(
						'similarity' => 0.0,
						'via'        => 'keyword',
					);
				}
			}
		}

		return $candidates;
	}

	/**
	 * Confidence (0-1) for a candidate and its best anchor.
	 *
	 * @param array      $candidate  Candidate from find_candidates().
	 * @param array|null $occurrence Anchor occurrence, or null.
	 * @param string[]   $phrases    Anchor phrases (priority order).
	 * @return float
	 */
	private function score(array $candidate, ?array $occurrence, array $phrases): float {
		$anchor_score = 0.0;

		if ($occurrence) {
			$words        = count(preg_split('/\s+/u', trim($occurrence['phrase'])));
			$rank         = array_search($occurrence['phrase'], $phrases, true);
			$anchor_score = min(1.0, $words / 3) * ($rank === 0 ? 1.0 : 0.9);
		}

		if ($candidate['via'] === 'keyword') {
			return round(min(self::KEYWORD_CONFIDENCE_CAP, 0.45 + 0.35 * $anchor_score), 4);
		}

		if (!$occurrence) {
			return round(0.6 * $candidate['similarity'], 4);
		}

		return round(min(1.0, 0.75 * $candidate['similarity'] + 0.25 * $anchor_score), 4);
	}

	/**
	 * Plain-text excerpt around an occurrence, with the anchor in [brackets].
	 *
	 * @param string $html       Source content.
	 * @param array  $occurrence Occurrence from the engine.
	 * @return string
	 */
	private function context_snippet(string $html, array $occurrence): string {
		// mb_strcut() keeps UTF-8 characters whole; the regexes drop tags cut in half at the edges.
		$before = mb_strcut($html, max(0, $occurrence['offset'] - 300), min(300, $occurrence['offset']), 'UTF-8');
		$after  = mb_strcut($html, $occurrence['offset'] + $occurrence['length'], 300, 'UTF-8');
		// Tags become spaces so block boundaries do not glue words together.
		$before = (string) preg_replace('/<[^>]*>/', ' ', (string) preg_replace('/^[^<]*>/', '', $before));
		$after  = (string) preg_replace('/<[^>]*>/', ' ', (string) preg_replace('/<[^>]*$/', '', $after));

		$before = html_entity_decode(preg_replace('/\s+/u', ' ', $before), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$after  = html_entity_decode(preg_replace('/\s+/u', ' ', $after), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		$before = mb_substr($before, -90);
		$after  = mb_substr($after, 0, 90);

		return '…' . ltrim($before) . '[' . $occurrence['text'] . ']' . rtrim($after) . '…';
	}
}
