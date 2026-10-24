<?php
/**
 * Structure Schema Builder
 *
 * Generates valid JSON-LD schema.org structured data from parsed article components.
 * Supports HowTo, FAQPage, Product/Review, and composite Graph schemas.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Structure_Schema_Builder
 */
class AIPS_Structure_Schema_Builder {

	/**
	 * Build JSON-LD schema graph from extracted components and post context.
	 *
	 * @param array $extracted_components Extracted component records.
	 * @param array $context              Context data (title, url, description, author, topic).
	 * @return array JSON-LD schema graph array (or empty if no structured items).
	 */
	public function build_schema(array $extracted_components, array $context = array()): array {
		if (empty($extracted_components)) {
			return array();
		}

		$title       = isset($context['title']) ? sanitize_text_field($context['title']) : '';
		$url         = isset($context['url']) ? esc_url($context['url']) : '';
		$description = isset($context['description']) ? wp_strip_all_tags($context['description']) : '';
		$author_name = isset($context['author_name']) ? sanitize_text_field($context['author_name']) : get_bloginfo('name');
		$topic       = isset($context['topic']) ? sanitize_text_field($context['topic']) : $title;

		$graph = array();

		// 1. FAQPage Schema
		$faqs = !empty($extracted_components['faqs']) ? $extracted_components['faqs'] : (!empty($extracted_components['faq']) ? $extracted_components['faq'] : array());
		if (!empty($faqs) && is_array($faqs)) {
			$faq_entities = array();

			foreach ($faqs as $faq) {
				$q = !empty($faq['q']) ? $faq['q'] : (!empty($faq['question']) ? $faq['question'] : '');
				$a = !empty($faq['a']) ? $faq['a'] : (!empty($faq['answer']) ? $faq['answer'] : '');

				if (!empty($q) && !empty($a)) {
					$faq_entities[] = array(
						'@type'          => 'Question',
						'name'           => wp_strip_all_tags($q),
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => wp_strip_all_tags($a),
						),
					);
				}
			}

			if (!empty($faq_entities)) {
				$graph[] = array(
					'@type'      => 'FAQPage',
					'@id'        => $url ? ($url . '#faq') : null,
					'mainEntity' => $faq_entities,
				);
			}
		}

		// 2. HowTo Schema
		$steps = !empty($extracted_components['steps']) ? $extracted_components['steps'] : (!empty($extracted_components['step_list']) ? $extracted_components['step_list'] : array());
		if (!empty($steps) && is_array($steps)) {
			$step_entities = array();

			foreach ($steps as $idx => $step) {
				$step_title = !empty($step['title']) ? wp_strip_all_tags($step['title']) : sprintf(__('Step %d', 'ai-post-scheduler'), $idx + 1);
				$step_text  = !empty($step['text']) ? wp_strip_all_tags($step['text']) : (!empty($step['description']) ? wp_strip_all_tags($step['description']) : '');
				$position   = !empty($step['step_number']) ? (int) $step['step_number'] : ($idx + 1);

				$step_entities[] = array(
					'@type'    => 'HowToStep',
					'position' => $position,
					'name'     => $step_title,
					'text'     => $step_text,
				);
			}

			if (!empty($step_entities)) {
				$howto = array(
					'@type'       => 'HowTo',
					'@id'         => $url ? ($url . '#howto') : null,
					'name'        => $title,
					'description' => $description,
					'step'        => $step_entities,
				);
				
				$graph[] = $howto;
			}
		}

		// 3. Product / Review Schema
		if (!empty($extracted_components['rating'])) {
			$rating = $extracted_components['rating'];
			$rating_val = isset($rating['value']) ? (float) $rating['value'] : (isset($rating['score']) ? (float) $rating['score'] : 0.0);
			$rating_max = isset($rating['max']) ? (float) $rating['max'] : 5.0;
			$verdict    = isset($rating['verdict']) ? wp_strip_all_tags($rating['verdict']) : '';
			$summary    = isset($rating['content']) ? wp_strip_all_tags($rating['content']) : $verdict;

			if ($rating_val > 0) {
				$review = array(
					'@type'         => 'Review',
					'@id'           => $url ? ($url . '#review') : null,
					'itemReviewed'  => array(
						'@type' => 'Thing',
						'name'  => $topic ?: $title,
					),
					'reviewRating'  => array(
						'@type'       => 'Rating',
						'ratingValue' => $rating_val,
						'bestRating'  => $rating_max,
						'worstRating' => 1,
					),
					'author'        => array(
						'@type' => 'Person',
						'name'  => $author_name,
					),
					'reviewBody'    => $summary,
				);

				// Add pros and cons to review schema if available (Schema.org Review positiveNotes/negativeNotes)
				if (!empty($extracted_components['pros']) && is_array($extracted_components['pros'])) {
					$review['positiveNotes'] = array(
						'@type'            => 'ItemList',
						'itemListElement' => array_map(function($p, $i) {
							return array(
								'@type'    => 'ListItem',
								'position' => $i + 1,
								'name'     => wp_strip_all_tags($p),
							);
						}, $extracted_components['pros'], array_keys($extracted_components['pros'])),
					);
				}

				if (!empty($extracted_components['cons']) && is_array($extracted_components['cons'])) {
					$review['negativeNotes'] = array(
						'@type'            => 'ItemList',
						'itemListElement' => array_map(function($c, $i) {
							return array(
								'@type'    => 'ListItem',
								'position' => $i + 1,
								'name'     => wp_strip_all_tags($c),
							);
						}, $extracted_components['cons'], array_keys($extracted_components['cons'])),
					);
				}

				$graph[] = $review;
			}
		}

		if (empty($graph)) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}
}
