<?php
/**
 * Integration Example: Topic Expansion in Post Generation
 *
 * This file demonstrates how to integrate the topic expansion service
 * into the post generation workflow to enhance prompts with semantically
 * similar approved topics.
 *
 * @package AI_Post_Scheduler
 * @since 1.10.0
 */

/**
 * Example 1: Enhance prompt with related approved topics
 *
 * When generating a post from a topic, include context from similar
 * approved topics to help the AI understand the author's style and preferences.
 */
function aips_example_enhance_post_generation_prompt($topic_id, $author_id, $base_prompt) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	
	// Get expanded context from similar approved topics
	$expanded_context = $expansion_service->get_expanded_context($author_id, $topic_id, 5);
	
	if (!empty($expanded_context)) {
		// Add context to the prompt
		$enhanced_prompt = $base_prompt . "\n\n" . $expanded_context;
		return $enhanced_prompt;
	}
	
	return $base_prompt;
}

/**
 * Example 2: Suggest topics to the editor
 *
 * When the editor reviews pending topics, show them which ones are
 * most similar to already-approved topics.
 */
function aips_example_display_topic_suggestions($author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	
	// Get pending topics similar to approved ones
	$suggestions = $expansion_service->suggest_related_topics($author_id, 10);
	
	if (empty($suggestions)) {
		echo '<p>No suggestions available. Approve some topics first to enable similarity-based suggestions.</p>';
		return;
	}
	
	echo '<h3>Recommended Topics (Similar to Approved)</h3>';
	echo '<ul>';
	foreach ($suggestions as $suggestion) {
		$similarity_percent = round($suggestion['similarity_score'] * 100, 1);
		echo '<li>';
		echo '<strong>' . esc_html($suggestion['topic_title']) . '</strong> ';
		echo '<span class="similarity-badge">' . $similarity_percent . '% similar</span>';
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Example 3: Batch compute embeddings after bulk approval
 *
 * When an editor approves multiple topics at once, compute embeddings
 * for all of them in a batch operation.
 */
function aips_example_batch_compute_embeddings_after_approval($author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	
	// Compute embeddings for all approved topics
	$stats = $expansion_service->batch_compute_approved_embeddings($author_id);
	
	// Log the results
	$logger = new AIPS_Logger();
	$logger->log(
		sprintf(
			'Batch embedding computation for author %d: %d successful, %d failed, %d skipped',
			$author_id,
			$stats['success'],
			$stats['failed'],
			$stats['skipped']
		),
		'info'
	);
	
	return $stats;
}

/**
 * Example 4: Find similar topics when viewing a topic detail
 *
 * When viewing a specific topic, show related topics to help the
 * editor understand the context and make better decisions.
 */
function aips_example_show_similar_topics($topic_id, $author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	$topics_repository = new AIPS_Author_Topics_Repository();
	
	// Find similar topics (both approved and pending)
	$similar_topics = $expansion_service->find_similar_topics($topic_id, $author_id, 5);
	
	if (empty($similar_topics)) {
		echo '<p>No similar topics found.</p>';
		return;
	}
	
	echo '<h3>Similar Topics</h3>';
	echo '<ul>';
	foreach ($similar_topics as $similar) {
		$similarity_percent = round($similar['similarity'] * 100, 1);
		$status_class = $similar['data']['status'];
		
		echo '<li>';
		echo '<strong>' . esc_html($similar['data']['topic_title']) . '</strong> ';
		echo '<span class="status-badge status-' . esc_attr($status_class) . '">' . esc_html($status_class) . '</span> ';
		echo '<span class="similarity-badge">' . $similarity_percent . '% similar</span>';
		echo '</li>';
	}
	echo '</ul>';
}

/**
 * Example 5: Automatic duplicate detection using similarity
 *
 * Use embeddings to automatically detect potential duplicate topics
 * and flag them for review.
 */
function aips_example_detect_duplicate_topics($topic_id, $author_id, $threshold = 0.95) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	
	// Find very similar topics
	$similar_topics = $expansion_service->find_similar_topics($topic_id, $author_id, 10);
	
	$duplicates = array();
	foreach ($similar_topics as $similar) {
		// If similarity is above threshold, flag as potential duplicate
		if ($similar['similarity'] >= $threshold) {
			$duplicates[] = array(
				'topic_id' => $similar['id'],
				'topic_title' => $similar['data']['topic_title'],
				'similarity' => $similar['similarity']
			);
		}
	}
	
	return $duplicates;
}

/**
 * Example 6: Hook into approval workflow to compute embedding
 *
 * Automatically compute embedding when a topic is approved.
 */
add_action('aips_topic_approved', 'aips_example_compute_embedding_on_approval', 10, 2);
function aips_example_compute_embedding_on_approval($topic_id, $author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	
	// Compute and store embedding
	$result = $expansion_service->compute_topic_embedding($topic_id);
	
	if (is_wp_error($result)) {
		// Log error but don't fail the approval
		$logger = new AIPS_Logger();
		$logger->log(
			sprintf('Failed to compute embedding for topic %d: %s', $topic_id, $result->get_error_message()),
			'error'
		);
	}
}

/**
 * Example 7: Integration with feedback system
 *
 * When rejecting a topic as duplicate, use similarity to find the original.
 */
function aips_example_reject_duplicate_with_context($topic_id, $author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	$feedback_repository = new AIPS_Feedback_Repository();
	
	// Find similar approved topics
	$similar_topics = $expansion_service->find_similar_topics($topic_id, $author_id, 5, 'approved');
	
	if (!empty($similar_topics)) {
		$most_similar = $similar_topics[0];
		$similarity_percent = round($most_similar['similarity'] * 100, 1);
		
		// Record rejection with context
		$reason = sprintf(
			'Duplicate of topic #%d: "%s" (%s%% similar)',
			$most_similar['id'],
			$most_similar['data']['topic_title'],
			$similarity_percent
		);
		
		$feedback_repository->record_rejection(
			$topic_id,
			get_current_user_id(),
			$reason,
			'',
			'duplicate',
			'automation'
		);
	}
}

/**
 * Example 8: Generate topic variations using embeddings
 *
 * Find approved topics similar to a pending one and use them as examples
 * for generating variations or improvements.
 */
function aips_example_generate_topic_variations($topic_id, $author_id) {
	$expansion_service = new AIPS_Topic_Expansion_Service();
	$topics_repository = new AIPS_Author_Topics_Repository();
	$ai_service = new AIPS_AI_Service();
	
	// Get the topic
	$topic = $topics_repository->get_by_id($topic_id);
	if (!$topic) {
		return new WP_Error('topic_not_found', 'Topic not found');
	}
	
	// Get similar approved topics for context
	$similar_topics = $expansion_service->find_similar_topics($topic_id, $author_id, 3, 'approved');
	
	if (empty($similar_topics)) {
		// No similar topics, can't generate variations
		return array();
	}
	
	// Build prompt with examples
	$examples = array();
	foreach ($similar_topics as $similar) {
		$examples[] = '- ' . $similar['data']['topic_title'];
	}
	
	$prompt = "Given these approved topic examples:\n" . implode("\n", $examples) . "\n\n";
	$prompt .= "Generate 3 variations of this pending topic that would match the style and quality:\n";
	$prompt .= "- " . $topic->topic_title . "\n\n";
	$prompt .= "Return only the variations, one per line.";
	
	$response = $ai_service->generate_text($prompt);
	
	if (is_wp_error($response)) {
		return $response;
	}
	
	// Parse variations
	$variations = explode("\n", trim($response));
	$variations = array_map('trim', $variations);
	$variations = array_filter($variations);
	
	return $variations;
}
