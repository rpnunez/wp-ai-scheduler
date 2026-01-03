<?php
/**
 * Article Structure Manager
 *
 * Service class for managing article structures and their composition.
 * Handles loading, processing, and applying article structures to templates.
 *
 * @package AI_Post_Scheduler
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Article_Structure_Manager
 *
 * Manages article structures and their relationships with prompt sections.
 */
class AIPS_Article_Structure_Manager {
	
	/**
	 * @var AIPS_Article_Structure_Repository
	 */
	private $structure_repository;
	
	/**
	 * @var AIPS_Prompt_Section_Repository
	 */
	private $section_repository;
	
	/**
	 * @var AIPS_Template_Processor
	 */
	private $template_processor;
	
	/**
	 * Initialize the manager.
	 */
	public function __construct() {
		$this->structure_repository = new AIPS_Article_Structure_Repository();
		$this->section_repository = new AIPS_Prompt_Section_Repository();
		$this->template_processor = new AIPS_Template_Processor();
	}
	
	/**
	 * Get all active article structures.
	 *
	 * @return array Array of structure objects.
	 */
	public function get_active_structures() {
		return $this->structure_repository->get_all(true);
	}
	
	/**
	 * Get article structure by ID with parsed data.
	 *
	 * @param int $structure_id Structure ID.
	 * @return array|WP_Error Parsed structure data or error.
	 */
	public function get_structure($structure_id) {
		$structure = $this->structure_repository->get_by_id($structure_id);
		
		if (!$structure) {
			return new WP_Error('structure_not_found', __('Article structure not found.', 'ai-post-scheduler'));
		}
		
		$structure_data = json_decode($structure->structure_data, true);
		
		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('invalid_structure_data', __('Invalid article structure data.', 'ai-post-scheduler'));
		}
		
		return array(
			'id' => $structure->id,
			'name' => $structure->name,
			'description' => $structure->description,
			'sections' => isset($structure_data['sections']) ? $structure_data['sections'] : array(),
			'prompt_template' => isset($structure_data['prompt_template']) ? $structure_data['prompt_template'] : '',
			'is_active' => $structure->is_active,
			'is_default' => $structure->is_default,
		);
	}
	
	/**
	 * Get the default article structure.
	 *
	 * @return array|WP_Error Parsed default structure or error.
	 */
	public function get_default_structure() {
		$structure = $this->structure_repository->get_default();
		
		if (!$structure) {
			// Fallback: get first active structure
			$structures = $this->structure_repository->get_all(true);
			if (!empty($structures)) {
				$structure = $structures[0];
			} else {
				return new WP_Error('no_structures', __('No article structures available.', 'ai-post-scheduler'));
			}
		}
		
		return $this->get_structure($structure->id);
	}
	
	/**
	 * Build a complete prompt from structure and topic.
	 *
	 * @param int         $structure_id Structure ID to use.
	 * @param string|null $topic        Topic for template variables.
	 * @return string|WP_Error Complete prompt or error.
	 */
	public function build_prompt($structure_id, $topic = null) {
		$structure = $this->get_structure($structure_id);
		
		if (is_wp_error($structure)) {
			return $structure;
		}
		
		// Get section contents
		$section_contents = $this->get_section_contents($structure['sections']);
		
		// Start with the structure's prompt template
		$prompt = $structure['prompt_template'];
		
		// Replace section placeholders with actual content
		foreach ($section_contents as $section_key => $content) {
			$prompt = str_replace("{{section:$section_key}}", $content, $prompt);
		}
		
		// Process remaining template variables (date, topic, site_name, etc.)
		$prompt = $this->template_processor->process($prompt, $topic);
		
		return $prompt;
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
				// Fallback for missing sections
				$contents[$key] = '';
			}
		}
		
		return $contents;
	}
	
	/**
	 * Create a new article structure.
	 *
	 * @param string $name            Structure name.
	 * @param array  $sections        Array of section keys.
	 * @param string $prompt_template Prompt template with section placeholders.
	 * @param string $description     Structure description.
	 * @param bool   $is_default      Set as default structure.
	 * @return int|WP_Error Structure ID on success or error.
	 */
	public function create_structure($name, $sections, $prompt_template, $description = '', $is_default = false) {
		// Validate sections exist
		$available_sections = $this->section_repository->get_by_keys($sections);
		$missing_sections = array_diff($sections, array_keys($available_sections));
		
		if (!empty($missing_sections)) {
			return new WP_Error(
				'invalid_sections',
				sprintf(
					__('Invalid section keys: %s', 'ai-post-scheduler'),
					implode(', ', $missing_sections)
				)
			);
		}
		
		$structure_data = array(
			'sections' => $sections,
			'prompt_template' => $prompt_template,
		);
		
		$data = array(
			'name' => $name,
			'description' => $description,
			'structure_data' => wp_json_encode($structure_data),
			'is_active' => 1,
			'is_default' => $is_default ? 1 : 0,
		);
		
		$id = $this->structure_repository->create($data);
		
		if (!$id) {
			return new WP_Error('create_failed', __('Failed to create article structure.', 'ai-post-scheduler'));
		}
		
		do_action('aips_structure_created', $id, $structure_data);

		return $id;
	}
	
	/**
	 * Update an article structure.
	 *
	 * @param int    $structure_id    Structure ID.
	 * @param string $name            Structure name.
	 * @param array  $sections        Array of section keys.
	 * @param string $prompt_template Prompt template with section placeholders.
	 * @param string $description     Structure description.
	 * @return bool|WP_Error True on success or error.
	 */
	public function update_structure($structure_id, $name, $sections, $prompt_template, $description = '') {
		$structure = $this->structure_repository->get_by_id($structure_id);
		
		if (!$structure) {
			return new WP_Error('structure_not_found', __('Article structure not found.', 'ai-post-scheduler'));
		}
		
		// Validate sections exist
		$available_sections = $this->section_repository->get_by_keys($sections);
		$missing_sections = array_diff($sections, array_keys($available_sections));
		
		if (!empty($missing_sections)) {
			return new WP_Error(
				'invalid_sections',
				sprintf(
					__('Invalid section keys: %s', 'ai-post-scheduler'),
					implode(', ', $missing_sections)
				)
			);
		}
		
		$structure_data = array(
			'sections' => $sections,
			'prompt_template' => $prompt_template,
		);
		
		$data = array(
			'name' => $name,
			'description' => $description,
			'structure_data' => wp_json_encode($structure_data),
		);
		
		$result = $this->structure_repository->update($structure_id, $data);
		
		if (!$result) {
			return new WP_Error('update_failed', __('Failed to update article structure.', 'ai-post-scheduler'));
		}
		
		do_action('aips_structure_updated', $structure_id, $structure_data);

		return true;
	}
	
	/**
	 * Delete an article structure.
	 *
	 * @param int $structure_id Structure ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_structure($structure_id) {
		$structure = $this->structure_repository->get_by_id($structure_id);
		
		if (!$structure) {
			return new WP_Error('structure_not_found', __('Article structure not found.', 'ai-post-scheduler'));
		}
		
		// Don't allow deleting the default structure if it's the only one
		if ($structure->is_default) {
			$count = $this->structure_repository->count_by_status();
			if ($count['active'] <= 1) {
				return new WP_Error(
					'cannot_delete_default',
					__('Cannot delete the default article structure. Create another structure first.', 'ai-post-scheduler')
				);
			}
		}
		
		$result = $this->structure_repository->delete($structure_id);
		
		if (!$result) {
			return new WP_Error('delete_failed', __('Failed to delete article structure.', 'ai-post-scheduler'));
		}
		
		do_action('aips_structure_deleted', $structure_id);

		return true;
	}
	
	/**
	 * Get all available prompt sections.
	 *
	 * @return array Array of section objects.
	 */
	public function get_available_sections() {
		return $this->section_repository->get_all(true);
	}
}
