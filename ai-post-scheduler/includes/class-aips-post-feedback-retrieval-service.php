<?php
if (!defined('ABSPATH')) { exit; }

/** Semantic retrieval and deterministic ranking for current post feedback. */
class AIPS_Post_Feedback_Retrieval_Service {
	private $repository;
	private $embeddings;
	private $logger;

	/**
	 * The $embedding_repository positional slot is retained for backward
	 * compatibility with call sites/tests that pass it, but the service does
	 * not use post embeddings — feedback rows carry their own inline embedding
	 * on the aips_post_feedback table.
	 */
	public function __construct($repository = null, $embedding_repository = null, $embeddings = null, $logger = null) {
		unset($embedding_repository);
		$this->repository = $repository ?: new AIPS_Post_Feedback_Repository();
		$this->embeddings = $embeddings ?: new AIPS_Embeddings_Service();
		$this->logger = $logger ?: new AIPS_Logger();
	}

	public function retrieve($context, AIPS_Post_Feedback_Policy $policy) {
		$empty = array('positive' => array(), 'negative' => array(), 'diagnostics' => array('fallback_reason' => ''));
		if (!$policy->is_enabled()) { $empty['diagnostics']['fallback_reason'] = 'disabled'; return $empty; }

		try {
			$scope = $policy->get_scope();
			$candidates = $this->repository->get_active_candidates($scope, max(50, (int) $policy->get('max_examples', 6) * 10));
			if (count($candidates) < (int) $policy->get('min_samples', 1)) { $empty['diagnostics']['fallback_reason'] = 'insufficient_samples'; return $empty; }

			$query = $this->build_query($context);
			$query_embedding = $this->embeddings->generate_embedding($query);
			if (is_wp_error($query_embedding)) { $empty['diagnostics']['fallback_reason'] = 'query_embedding_unavailable'; return $empty; }

			$ranked = array('positive' => array(), 'negative' => array());
			foreach ($candidates as $candidate) {
				$post_id = absint($this->value($candidate, 'post_id'));
				$vector = json_decode((string) $this->value($candidate, 'embedding'), true);
				if (!$post_id || !is_array($vector)) { do_action('aips_post_feedback_embedding_missing', $post_id); continue; }
				$post = get_post($post_id);
				if (!$post || 'trash' === $post->post_status || '' === trim(wp_strip_all_tags($post->post_content))) { continue; }
				$similarity = $this->embeddings->calculate_similarity($query_embedding, $vector);
				if (is_wp_error($similarity) || $similarity < $policy->get('min_similarity', .7)) { continue; }
				$item = $this->rank_candidate($candidate, $post, $similarity, $policy);
				if ($item['score'] <= 0) { continue; }
				$pool = 'liked' === $item['reaction'] ? 'positive' : ('disliked' === $item['reaction'] ? 'negative' : '');
				if ($pool) { $ranked[$pool][] = $item; }
			}
			foreach (array('positive', 'negative') as $pool) {
				usort($ranked[$pool], function($a, $b) { return $b['score'] <=> $a['score']; });
				$ranked[$pool] = array_slice($ranked[$pool], 0, (int) $policy->get('max_examples', 6));
			}
			$ranked['diagnostics'] = array('candidate_count' => count($candidates), 'selected_count' => count($ranked['positive']) + count($ranked['negative']), 'fallback_reason' => '');
			return $ranked;
		} catch (Throwable $error) {
			$this->logger->log('Post feedback retrieval failed open: ' . $error->getMessage(), 'warning');
			$empty['diagnostics']['fallback_reason'] = 'retrieval_error';
			return $empty;
		}
	}

	private function rank_candidate($candidate, $post, $similarity, $policy) {
		$scope = $policy->get_scope();
		$author_id = absint($this->value($candidate, 'author_id'));
		$template_id = absint($this->value($candidate, 'template_id'));
		if (!empty($scope['template_id']) && $template_id === (int) $scope['template_id']) { $scope_factor = $policy->get('template_match_weight', 1.5); }
		elseif (!empty($scope['author_id']) && $author_id === (int) $scope['author_id']) { $scope_factor = $policy->get('author_match_weight', 1.25); }
		else { $scope_factor = $policy->get('global_pool_weight', .5); }
		$reaction = (string) $this->value($candidate, 'reaction');
		$reaction_factor = 'liked' === $reaction ? $policy->get('like_weight', 1) : $policy->get('dislike_weight', 1.25);
		$created_raw = $this->value($candidate, 'created_at');
		$created = is_numeric($created_raw) ? (int) $created_raw : (strtotime((string) $created_raw) ?: time());
		$age_days = max(0, (time() - $created) / DAY_IN_SECONDS);
		$recency_factor = 1 / (1 + ($policy->get('recency_weight', .35) * $age_days / 365));
		// Must use the same canonical hash the service wrote at record time.
		// The previous inline hash stripped tags from title/excerpt too, so any
		// markup there made unmodified posts look edited and be penalised.
		$current_hash = AIPS_Post_Feedback_Service::calculate_content_hash($post);
		$stored_hash = (string) $this->value($candidate, 'content_hash');
		$integrity_factor = (!$stored_hash || hash_equals($stored_hash, $current_hash)) ? 1.0 : $policy->get('edited_content_weight', .35);
		$score = $similarity * $policy->get('similarity_weight', 1) * $scope_factor * $reaction_factor * $recency_factor * $integrity_factor;
		return array(
			'feedback_id' => absint($this->value($candidate, 'id')), 'post_id' => (int) $post->ID,
			'reaction' => $reaction, 'reason_category' => (string) $this->value($candidate, 'reason_category'),
			'comment' => (string) $this->value($candidate, 'comment'), 'excerpt' => $this->excerpt($post),
			'similarity' => (float) $similarity, 'score' => (float) $score,
		);
	}

	private function build_query($context) { return mb_substr(trim(implode("\n", array_filter(array($context->get_topic(), $context->get_name(), $context->get_content_prompt())))), 0, 6000); }
	private function excerpt($post) { $text = trim(wp_strip_all_tags($post->post_excerpt ?: $post->post_content)); return mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 320); }
	private function value($candidate, $key) { return is_array($candidate) ? ($candidate[$key] ?? null) : ($candidate->$key ?? null); }
}
