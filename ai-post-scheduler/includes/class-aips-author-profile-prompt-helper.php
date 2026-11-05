<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Shared author-profile prompt guidance for author-topic workflows.
 *
 * Topic generation and post generation intentionally share the length,
 * language precedence, and exclusion rules while retaining their respective
 * topic-only inputs.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.19
 */
class AIPS_Author_Profile_Prompt_Helper {

	/**
	 * Return the author profile fields recorded with a generation context.
	 *
	 * @param object $author Author record.
	 * @return array<string, string>
	 */
	public function get_effective_fields($author) {
		return array(
			'language'                 => $this->get_author_value($author, 'language'),
			'expertise_level'          => $this->get_author_value($author, 'expertise_level'),
			'preferred_content_length' => $this->get_author_value($author, 'preferred_content_length'),
			'excluded_topics'          => $this->get_merged_excluded_topics($author),
			'target_audience'          => $this->get_author_value($author, 'target_audience'),
			'content_goals'            => $this->get_author_value($author, 'content_goals'),
			'keywords'                 => $this->get_author_value($author, 'keywords'),
		);
	}

	/**
	 * Build the profile guidance that applies to generated posts.
	 *
	 * @param object $author Author record.
	 * @return string Empty string when no post-level author guidance is set.
	 */
	public function build_post_guidance($author) {
		$fields = $this->get_effective_fields($author);
		$lines  = array();

		if ($this->has_selected_language($author)) {
			$lines[] = 'Write in language code: ' . $fields['language'];
		}

		if ($fields['expertise_level'] !== '') {
			$lines[] = 'Author expertise level: ' . $fields['expertise_level'];
		}

		$length = $this->get_preferred_content_length_label($fields['preferred_content_length']);
		if ($length !== '') {
			$lines[] = 'Preferred post length: ' . $length;
		}

		if ($fields['excluded_topics'] !== '') {
			$lines[] = 'Topics to avoid: ' . $fields['excluded_topics'];
		}

		if ($fields['target_audience'] !== '') {
			$lines[] = 'Target audience: ' . $fields['target_audience'];
		}

		if ($fields['content_goals'] !== '') {
			$lines[] = 'Content goals: ' . $fields['content_goals'];
		}

		if ($fields['keywords'] !== '') {
			$lines[] = 'Keywords/Focus Areas: ' . $fields['keywords'];
		}

		return empty($lines) ? '' : implode("\n", $lines);
	}

	/**
	 * Return the canonical display text for a stored preferred length value.
	 *
	 * @param string $preferred_content_length Stored length value.
	 * @return string
	 */
	public function get_preferred_content_length_label($preferred_content_length) {
		$length_map = array(
			'short'  => 'under 800 words',
			'medium' => '800–1,500 words',
			'long'   => '1,500+ words',
		);

		return isset($length_map[$preferred_content_length])
			? $length_map[$preferred_content_length]
			: (string) $preferred_content_length;
	}

	/**
	 * Merge site-wide and author-level exclusions into one avoid-list value.
	 *
	 * @param object $author Author record.
	 * @return string
	 */
	public function get_merged_excluded_topics($author) {
		$excluded_parts = array();
		$site_excluded  = (string) AIPS_Site_Context::get_setting('excluded_topics', '');
		$author_excluded = $this->get_author_value($author, 'excluded_topics');

		if ($site_excluded !== '') {
			$excluded_parts[] = $site_excluded;
		}
		if ($author_excluded !== '') {
			$excluded_parts[] = $author_excluded;
		}

		return implode(', ', $excluded_parts);
	}

	/**
	 * Return site-context keys that author-topic prompts must supply elsewhere.
	 *
	 * @param object $author Author record.
	 * @return string[]
	 */
	public function get_site_context_omissions($author) {
		$omissions = array('excluded_topics');
		if ($this->has_selected_language($author)) {
			$omissions[] = 'content_language';
		}

		return $omissions;
	}

	/**
	 * Check whether the author selects a language beyond the default English.
	 *
	 * @param object $author Author record.
	 * @return bool
	 */
	public function has_selected_language($author) {
		$language = $this->get_author_value($author, 'language');
		return $language !== '' && $language !== 'en';
	}

	/**
	 * Read an optional string field without notices for legacy author rows.
	 *
	 * @param object $author Author record.
	 * @param string $key Author property name.
	 * @return string
	 */
	private function get_author_value($author, $key) {
		return isset($author->$key) ? (string) $author->$key : '';
	}
}
