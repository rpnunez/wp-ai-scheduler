<?php
/**
 * Internal Link Prompt Builder
 *
 * @package AI_Post_Scheduler
 * @since 2.9.1
 */

if (!defined('ABSPATH')) {
	exit;
}

class AIPS_Prompt_Builder_Internal_Link {

	public function build($plain_text_content, $post_title, $anchor_text, $target_url, $num_locations = 1) {
		return sprintf(
			"Return ONLY a valid JSON array. No markdown. No prose.\n"
			. "Task: Find %d insertion locations for an internal link in the text below.\n"
			. "Each array item must be an object with exactly these keys: reason, match_snippet, replacement_snippet.\n"
			. "Rules:\n"
			. "1) Return %d objects when possible. If not possible, return fewer. If none, return [].\n"
			. "2) reason must be under 8 words.\n"
			. "3) match_snippet must be an exact substring from the text.\n"
			. "4) replacement_snippet must be EXACTLY match_snippet, except that one existing phrase from match_snippet is wrapped in [[double square brackets]].\n"
			. "5) The words inside [[...]] must already appear verbatim inside match_snippet. Do not invent new words.\n"
			. "6) Do not use the target post title or anchor text unless those exact words already appear in match_snippet.\n"
			. "7) Do not change, paraphrase, reorder, or replace any wording outside the wrapped phrase.\n"
			. "8) Prefer wrapping a short, natural phrase that reads well as link text.\n"
			. "9) No HTML. No extra keys.\n\n"
			. "Valid example:\n"
			. "match_snippet: explore crucial web server configuration adjustments, and solidify\n"
			. "replacement_snippet: explore crucial [[web server configuration adjustments]], and solidify\n\n"
			. "Invalid example:\n"
			. "match_snippet: explore crucial web server configuration adjustments, and solidify\n"
			. "replacement_snippet: explore crucial [[%s]], and solidify\n\n"
			. "Target post title: %s\n"
			. "Anchor text: %s\n"
			. "URL: %s\n\n"
			. "Text:\n%s",
			$num_locations,
			$num_locations,
			$anchor_text,
			$post_title,
			$anchor_text,
			$target_url,
			$plain_text_content
		);
	}
}
