<?php
/**
 * Prompt Profile Resolver
 *
 * Encapsulates the 4-tier Prompt Profile resolution cascade, stage-level fallback
 * logic, dynamic contextual placeholder interpolation, and safety guards.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Profile_Resolver
 *
 * Resolves prompt templates from Prompt Profiles with cascade and fallback guarantees.
 */
class AIPS_Prompt_Profile_Resolver {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var AIPS_Prompt_Profiles_Repository
	 */
	private $repository;

	/**
	 * Get shared instance.
	 *
	 * @param AIPS_Prompt_Profiles_Repository|null $repository Optional repository.
	 * @return self
	 */
	public static function instance($repository = null): self {
		if (self::$instance === null) {
			self::$instance = new self($repository);
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param AIPS_Prompt_Profiles_Repository|null $repository Optional repository.
	 */
	public function __construct($repository = null) {
		$this->repository = $repository ?: AIPS_Prompt_Profiles_Repository::instance();
	}

	/**
	 * Core hardcoded prompt stage fallbacks.
	 *
	 * @return array<string, string>
	 */
	public static function get_core_fallbacks() {
		return array(
			'title_prompt'            => "Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.{{instructions_block}}",
			'title_followup_prompt'   => "Now generate a title for the article you just wrote.{{instructions_block}}",
			'content_prompt'          => "{{voice_instructions}}\n\n{{content_prompt}}",
			'excerpt_prompt'          => "Write an excerpt for an article. Must be between {{word_count_min}} and {{word_count_max}} words. Write naturally as a human would. Output only the excerpt, no formatting.{{instructions_block}}",
			'excerpt_followup_prompt' => "Now write an excerpt for that article. Must be between {{word_count_min}} and {{word_count_max}} words. Write naturally as a human would. Output only the excerpt, no formatting.{{instructions_block}}",
			'featured_image_prompt'   => "{{image_prompt}}",
			'topic_ideas_prompt'      => "Generate {{quantity}} unique and engaging blog post topic ideas about: {{niche}}",
			'metadata_prompt'         => "Generate search-optimized metadata for the article.",
			'taxonomy_prompt'         => "Select the most relevant category and tags for the article.",
		);
	}

	/**
	 * Resolve the active prompt profile object using the 4-tier cascade:
	 *   1. Template / Generation Context Profile ID
	 *   2. Author Profile ID
	 *   3. Global Site Default Profile
	 *   4. Null (which triggers Core Codebase Fallbacks)
	 *
	 * @param mixed $subject Generation context, Template object, Author object, or Profile ID.
	 * @param mixed $secondary_subject Optional secondary entity (e.g. Author when context is primary).
	 * @return object|null Resolved profile DB row or null.
	 */
	public function resolve_profile($subject = null, $secondary_subject = null) {
		$profile_id = $this->extract_profile_id($subject);

		if (!$profile_id && $secondary_subject !== null) {
			$profile_id = $this->extract_profile_id($secondary_subject);
		}

		if ($profile_id > 0) {
			$profile = $this->repository->get_by_id($profile_id);
			if ($profile && !empty($profile->is_active)) {
				return $profile;
			}
		}

		// Tier 3: Global default profile
		$default = $this->repository->get_default();
		if ($default && !empty($default->is_active)) {
			return $default;
		}

		return null;
	}

	/**
	 * Extract a prompt_profile_id integer from various object shapes.
	 *
	 * @param mixed $subject Entity or ID.
	 * @return int Profile ID or 0.
	 */
	private function extract_profile_id($subject) {
		if (is_numeric($subject)) {
			return absint($subject);
		}

		if (is_object($subject)) {
			if (method_exists($subject, 'get_prompt_profile_id')) {
				$val = $subject->get_prompt_profile_id();
				if ($val) {
					return absint($val);
				}
			}

			if (isset($subject->prompt_profile_id)) {
				return absint($subject->prompt_profile_id);
			}

			// If context has an author object
			if (method_exists($subject, 'get_author')) {
				$author = $subject->get_author();
				if ($author && isset($author->prompt_profile_id)) {
					return absint($author->prompt_profile_id);
				}
			}
		}

		if (is_array($subject) && isset($subject['prompt_profile_id'])) {
			return absint($subject['prompt_profile_id']);
		}

		return 0;
	}

	/**
	 * Get the prompt template string for a specific stage with fallback.
	 *
	 * @param string $stage Stage key (e.g. 'title_prompt', 'content_prompt').
	 * @param mixed  $subject Context/Template/Author/ID.
	 * @param mixed  $secondary_subject Optional secondary subject.
	 * @return string Prompt template string.
	 */
	public function get_stage_prompt($stage, $subject = null, $secondary_subject = null) {
		$profile = $this->resolve_profile($subject, $secondary_subject);

		if ($profile && !empty($profile->{$stage})) {
			$custom = trim((string) $profile->{$stage});
			if ($custom !== '') {
				return $custom;
			}
		}

		$fallbacks = self::get_core_fallbacks();
		return isset($fallbacks[$stage]) ? $fallbacks[$stage] : '';
	}

	/**
	 * Interpolate placeholders into a prompt template and apply safety guards.
	 *
	 * @param string               $template       Prompt template containing {{tags}}.
	 * @param array<string, string> $placeholders  Map of tag name => replacement string.
	 * @param array<string, string> $mandatory_tags Tags that MUST be represented; if missing from template, their value is appended.
	 * @return string Processed prompt string.
	 */
	public function interpolate($template, array $placeholders, array $mandatory_tags = array()) {
		$result = $template;
		$used_tags = array();

		foreach ($placeholders as $tag => $value) {
			$search = '{{' . trim($tag, '{}') . '}}';
			if (strpos($result, $search) !== false) {
				$result = str_replace($search, (string) $value, $result);
				$used_tags[$tag] = true;
			}
		}

		// Safety Guard: Check for mandatory tags that were omitted in custom templates
		foreach ($mandatory_tags as $m_tag => $m_content) {
			$clean_tag = trim($m_tag, '{}');
			if (empty($used_tags[$clean_tag]) && !empty($m_content)) {
				// If not present in template and non-empty, append safely to ensure context integrity
				$result = rtrim($result) . "\n\n" . trim($m_content);
			}
		}

		// Clean up any remaining unreplaced empty optional tags like {{instructions_block}}
		$result = preg_replace('/\{\{[a-zA-Z0-9_\-]+\}\}/', '', $result);

		return trim($result);
	}
}
