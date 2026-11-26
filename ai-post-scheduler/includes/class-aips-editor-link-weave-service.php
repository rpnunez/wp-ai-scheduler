<?php
/**
 * Editor Internal Link Weave Service
 *
 * Scans editor content, identifies relevant published site posts, and prompts
 * the AI Engine to naturally weave contextual internal links into the prose.
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Editor_Link_Weave_Service {

	/**
	 * @var AIPS_AI_Service_Interface
	 */
	private $ai_service;

	/**
	 * Constructor.
	 *
	 * @param AIPS_AI_Service_Interface|null $ai_service AI Service.
	 */
	public function __construct(?AIPS_AI_Service_Interface $ai_service = null) {
		$container        = AIPS_Container::get_instance();
		$this->ai_service = $ai_service ?: ($container->has(AIPS_AI_Service_Interface::class) ? $container->make(AIPS_AI_Service_Interface::class) : new AIPS_AI_Service());
	}

	/**
	 * Fetch candidate published posts for link weaving.
	 *
	 * @param int $current_post_id Post ID being edited.
	 * @param int $limit           Maximum candidates to retrieve.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_candidate_posts(int $current_post_id, int $limit = 10): array {
		$query_args = array(
			'post_type'              => array('post'),
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array($current_post_id),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$candidates = array();
		$query      = new WP_Query($query_args);

		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				$permalink = get_permalink($post->ID);
				if ($permalink) {
					$candidates[] = array(
						'id'    => $post->ID,
						'title' => $post->post_title,
						'url'   => $permalink,
					);
				}
			}
		}

		return $candidates;
	}

	/**
	 * Weave internal links naturally into content using candidate posts.
	 *
	 * @param int    $post_id Post ID being edited.
	 * @param string $content Content snippet or paragraph.
	 * @return array|WP_Error Array with 'modified_html' and 'inserted_links', or WP_Error.
	 */
	public function weave_internal_links(int $post_id, string $content) {
		if (!$this->ai_service || !$this->ai_service->is_available()) {
			return new WP_Error('ai_service_unavailable', __('AI service is currently unavailable. Please check your AI Engine configuration.', 'ai-post-scheduler'));
		}

		$content = trim($content);
		if ('' === $content) {
			return new WP_Error('empty_content', __('No content provided for link weaving.', 'ai-post-scheduler'));
		}

		$candidates = $this->get_candidate_posts($post_id, 8);
		if (empty($candidates)) {
			return new WP_Error('no_candidates', __('No other published posts found on this site to link to.', 'ai-post-scheduler'));
		}

		$candidate_list = '';
		foreach ($candidates as $candidate) {
			$candidate_list .= "- Title: \"" . esc_attr($candidate['title']) . "\" | URL: " . esc_url($candidate['url']) . "\n";
		}

		$prompt  = "You are an expert WordPress editor specializing in organic internal linking for SEO.\n\n";
		$prompt .= "Task:\n";
		$prompt .= "Weave 1 or 2 relevant internal hyperlinks into the text below using the candidate site articles provided.\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "1. Only link when genuinely relevant to the context.\n";
		$prompt .= "2. Use descriptive, organic anchor text (do not use generic phrases like 'click here' or 'read more').\n";
		$prompt .= "3. Keep the original text and tone as preserved as possible, only adjusting phrasing where needed to fit the anchor naturally.\n";
		$prompt .= "4. Output MUST be valid JSON only with exactly two keys: 'modified_html' (string containing HTML <a href=\"...\">...</a> tags) and 'inserted_links' (array of objects with 'url', 'title', and 'anchor').\n\n";
		$prompt .= "Candidate Articles Available to Link to:\n" . $candidate_list . "\n\n";
		$prompt .= "Original Content:\n\"\"\"\n" . $content . "\n\"\"\"\n\n";
		$prompt .= "Example JSON output:\n";
		$prompt .= "{\n  \"modified_html\": \"Here is text with <a href=\\\"https://example.com/post\\\">descriptive anchor text</a> woven in.\",\n  \"inserted_links\": [\n    {\n      \"url\": \"https://example.com/post\",\n      \"title\": \"Post Title\",\n      \"anchor\": \"descriptive anchor text\"\n    }\n  ]\n}";

		$response = $this->ai_service->generate_text($prompt);
		if (is_wp_error($response)) {
			return $response;
		}

		$raw = trim((string) $response);
		if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
			$decoded = json_decode($matches[0], true);
			if (is_array($decoded) && isset($decoded['modified_html'])) {
				return array(
					'modified_html'  => wp_kses_post($decoded['modified_html']),
					'inserted_links' => isset($decoded['inserted_links']) && is_array($decoded['inserted_links']) ? $decoded['inserted_links'] : array(),
				);
			}
		}

		return array(
			'modified_html'  => wp_kses_post($content),
			'inserted_links' => array(),
		);
	}
}
