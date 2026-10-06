<?php
/**
 * Consolidation Prompt Builder
 *
 * Builds the prompt that merges two overlapping posts into one article, for
 * the consolidation flow (AIPS_Consolidation_Service).
 *
 * @package AI_Post_Scheduler
 * @since 3.7.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Consolidation
 */
class AIPS_Prompt_Builder_Consolidation {

	/**
	 * Longest body (in characters) sent per post, to keep prompts bounded.
	 */
	const MAX_BODY_CHARS = 24000;

	/**
	 * Build the merge prompt.
	 *
	 * @param WP_Post $keep   Post that stays published (its angle and URL win).
	 * @param WP_Post $retire Post being retired into it.
	 * @param string  $retire_url Old URL of the retired post (must not be linked).
	 * @param string  $instructions Optional extra instructions from the admin.
	 * @return string
	 */
	public function build(WP_Post $keep, WP_Post $retire, string $retire_url, string $instructions = ''): string {
		$prompt  = "You are an editor merging two overlapping blog posts on the same WordPress site into one stronger article.\n\n";
		$prompt .= "The PRIMARY post keeps its URL, title and angle. The SECONDARY post is being retired and will redirect to the primary.\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "- Keep the primary post's structure, voice and headings as the base.\n";
		$prompt .= "- Bring in facts, examples, steps and sections from the secondary post that the primary is missing. Do not repeat what the primary already covers.\n";
		$prompt .= "- Keep existing links from both posts, except links to the secondary post's URL ({$retire_url}), which must be removed (keep their text).\n";
		$prompt .= "- Do not invent facts, statistics, quotes or links.\n";
		$prompt .= "- Return only the merged article body as clean HTML (p, h2, h3, ul, ol, li, strong, em, a, blockquote, table). No title, no <html> or <body>, no Markdown, no code fences, no commentary.\n";

		$instructions = sanitize_textarea_field($instructions);
		if ($instructions !== '') {
			$prompt .= "\nAdditional instructions from the site editor: {$instructions}\n";
		}

		$prompt .= "\n=== PRIMARY POST ===\n";
		$prompt .= 'Title: ' . wp_strip_all_tags(get_the_title($keep)) . "\n\n";
		$prompt .= $this->body($keep) . "\n";
		$prompt .= "\n=== SECONDARY POST ===\n";
		$prompt .= 'Title: ' . wp_strip_all_tags(get_the_title($retire)) . "\n\n";
		$prompt .= $this->body($retire) . "\n";

		return $prompt;
	}

	/**
	 * Post body without block comments, trimmed to MAX_BODY_CHARS.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private function body(WP_Post $post): string {
		$html = (string) preg_replace('/<!--\s*\/?wp:.*?-->/s', '', (string) $post->post_content);
		$html = trim((string) preg_replace("/\n{3,}/", "\n\n", $html));

		if (mb_strlen($html) > self::MAX_BODY_CHARS) {
			$html = mb_substr($html, 0, self::MAX_BODY_CHARS) . "\n[…truncated]";
		}

		return $html;
	}
}
