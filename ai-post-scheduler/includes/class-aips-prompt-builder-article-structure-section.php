<?php
/**
 * Article Structure Section Prompt Builder
 *
 * Responsible for assembling AI prompts from article structures and
 * prompt section placeholders.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Builder_Article_Structure_Section
 *
 * Builds complete prompts for article structures by resolving section
 * placeholders and processing template variables.
 */
class AIPS_Prompt_Builder_Article_Structure_Section extends AIPS_Prompt_Builder_Base {

	/**
	 * Build a complete prompt from structure and topic.
	 *
	 * @param int   $primary_input Structure ID to use.
	 * @param mixed ...$args Optional topic value.
	 * @return string|WP_Error Complete prompt or error.
	 */
	public function build($primary_input, ...$args) {
		$structure_id = $primary_input;
		$topic = isset($args[0]) ? $args[0] : null;
		$structure = $this->get_structure_manager()->get_structure($structure_id);

		if (is_wp_error($structure)) {
			return $structure;
		}

		$section_contents = $this->get_section_contents($structure['sections']);
		$prompt = $structure['prompt_template'];
		$search = array();
		$replace = array();

		foreach ($section_contents as $section_key => $content) {
			$search[] = "{{section:$section_key}}";
			$replace[] = $content;
		}

		if (!empty($search)) {
			$prompt = str_replace($search, $replace, $prompt);
		}

		return $this->get_template_processor()->process($prompt, $topic);
	}

	/**
	 * Get section contents by keys.
	 *
	 * @param array $section_keys Array of section keys.
	 * @return array Array of section_key => content.
	 */
	private function get_section_contents($section_keys) {
		if (empty($section_keys)) {
			return array();
		}

		$sections = $this->get_section_repository()->get_by_keys($section_keys);
		$contents = array();

		foreach ($section_keys as $key) {
			if (isset($sections[$key])) {
				$contents[$key] = $sections[$key]->content;
			} else {
				$contents[$key] = '';
			}
		}

		return $contents;
	}
}
