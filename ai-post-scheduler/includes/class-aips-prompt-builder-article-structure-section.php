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
