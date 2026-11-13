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
class AIPS_Prompt_Builder_Article_Structure_Section {

	/**
	 * @var AIPS_Article_Structure_Manager
	 */
	private $structure_manager;

	/**
	 * @var AIPS_Prompt_Section_Repository
	 */
	private $section_repository;

	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;

	/**
	 * @param AIPS_Article_Structure_Manager|null $structure_manager Optional structure manager.
	 * @param AIPS_Prompt_Section_Repository|null $section_repository Optional section repository.
	 * @param AIPS_Template_Processor|null        $template_processor Optional template processor.
	 */
	public function __construct($structure_manager = null, $section_repository = null, $template_processor = null) {
		$this->structure_manager = $structure_manager ?: new AIPS_Article_Structure_Manager();
		$this->section_repository = $section_repository ?: new AIPS_Prompt_Section_Repository();
		$this->template_processor = $template_processor ?: new AIPS_Template_Processor();
	}

	/**
	 * Build a complete prompt from structure and topic.
	 *
	 * @param int         $structure_id Structure ID to use.
	 * @param string|null $topic Topic for template variables.
	 * @return string|WP_Error Complete prompt or error.
	 */
	public function build($structure_id, $topic = null) {
		$structure = $this->structure_manager->get_structure($structure_id);

		if (is_wp_error($structure)) {
			return $structure;
		}

		$relational_sections = !empty($structure['relational_sections']) ? $structure['relational_sections'] : array();
		$prompt = !empty($structure['prompt_template']) ? $structure['prompt_template'] : '';

		// If structure has relational sections, assemble section prompt blocks
		if (!empty($relational_sections)) {
			$section_instructions = array();
			$section_block_by_key = array();

			foreach ($relational_sections as $sec) {
				$sec_key = $sec->section_key;
				$sec_block = array();

				if (!empty($sec->heading_text)) {
					$h_prefix = ($sec->heading_level === 'h3') ? '### ' : '## ';

					if ($sec->heading_level === 'none') {
						$h_prefix = '';
					}

					$sec_block[] = sprintf('Section Heading: %s%s', $h_prefix, $sec->heading_text);
				}

				if (!empty($sec->target_word_count)) {
					$sec_block[] = sprintf('Target word count: ~%d words.', (int) $sec->target_word_count);
				}

				if (!empty($sec->component_prompt_instructions)) {
					$sec_block[] = $sec->component_prompt_instructions;
				}

				if (!empty($sec->custom_prompt)) {
					$sec_block[] = $sec->custom_prompt;
				}

				$assembled_sec = implode("\n", $sec_block);
				$section_instructions[] = $assembled_sec;
				$section_block_by_key[$sec_key] = $assembled_sec;
			}

			// Reconcile the template with the saved card list so builder changes
			// (reorder, remove, add) reach the generated prompt. Cards that still
			// have a {{section:key}} placeholder are substituted in place; cards
			// without one (newly added) are appended; placeholders whose card was
			// removed are stripped so they do not leak into the prompt.
			if (!empty($prompt) && strpos($prompt, '{{section:') !== false) {
				$appended = array();

				foreach ($section_block_by_key as $sec_key => $block) {
					$placeholder = '{{section:' . $sec_key . '}}';

					if (strpos($prompt, $placeholder) !== false) {
						$prompt = str_replace($placeholder, $block, $prompt);
					} else {
						$appended[] = $block;
					}
				}

				// Strip orphaned placeholders left by removed cards.
				$prompt = preg_replace('/\{\{section:[^}]*\}\}/', '', $prompt);

				if (!empty($appended)) {
					$prompt .= "\n\n" . implode("\n\n", $appended);
				}
			} elseif (empty($prompt)) {
				// If no custom prompt_template, join section instructions
				$prompt = sprintf(
					"Write a comprehensive %s about {{topic}}.\n\n%s",
					!empty($structure['name']) ? $structure['name'] : 'article',
					implode("\n\n", $section_instructions)
				);
			} else {
				// Append section instructions if not already substituted
				$prompt .= "\n\n" . implode("\n\n", $section_instructions);
			}
		} else {
			// Legacy fallback for structures without relational mappings
			$section_contents = $this->get_section_contents($structure['sections']);
			$search = array();
			$replace = array();

			foreach ($section_contents as $section_key => $content) {
				$search[] = "{{section:$section_key}}";
				$replace[] = $content;
			}

			if (!empty($search)) {
				$prompt = str_replace($search, $replace, $prompt);
			}
		}

		return $this->template_processor->process($prompt, $topic);
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

		$sections = $this->section_repository->get_by_keys($section_keys);
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
