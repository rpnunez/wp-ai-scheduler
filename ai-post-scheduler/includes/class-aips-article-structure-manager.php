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
	 * Initialize the manager.
	 */
	public function __construct() {
		$this->structure_repository = new AIPS_Article_Structure_Repository();
		$this->section_repository = new AIPS_Prompt_Section_Repository();
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
			'archetype' => isset($structure->archetype) ? $structure->archetype : 'custom',
			'editor_mode' => isset($structure->editor_mode) ? $structure->editor_mode : 'auto',
			'enable_schema' => isset($structure->enable_schema) ? (int) $structure->enable_schema : 1,
			'sections' => isset($structure_data['sections']) ? $structure_data['sections'] : array(),
			'prompt_template' => isset($structure_data['prompt_template']) ? $structure_data['prompt_template'] : '',
			'relational_sections' => class_exists('AIPS_Structure_Section_Rel_Repository') ? AIPS_Structure_Section_Rel_Repository::instance()->get_by_structure_id($structure->id) : array(),
			'is_active' => $structure->is_active,
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
	 * Create a new article structure.
	 *
	 * @param string $name            Structure name.
	 * @param array  $sections        Array of section keys.
	 * @param string $prompt_template Prompt template with section placeholders.
	 * @param string $description     Structure description.
	 * @param bool   $is_active       Set structure as active.
	 * @return int|WP_Error Structure ID on success or error.
	 */
	public function create_structure($name, $sections, $prompt_template, $description = '', $is_active = true, $archetype = 'custom', $editor_mode = 'auto', $enable_schema = 1, $relational_sections = null) {
		// Validate sections exist if sections provided
		if (!empty($sections)) {
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
		}
		
		$structure_data = array(
			'sections' => $sections,
			'prompt_template' => $prompt_template,
		);
		
		$data = array(
			'name' => $name,
			'description' => $description,
			'archetype' => sanitize_key($archetype),
			'editor_mode' => in_array($editor_mode, array('gutenberg', 'shortcode', 'auto'), true) ? $editor_mode : 'auto',
			'enable_schema' => !empty($enable_schema) ? 1 : 0,
			'structure_data' => wp_json_encode($structure_data),
			'is_active' => $is_active ? 1 : 0,
		);
		
		$id = $this->structure_repository->create($data);
		
		if (!$id) {
			return new WP_Error('create_failed', __('Failed to create article structure.', 'ai-post-scheduler'));
		}

		if (is_array($relational_sections) && class_exists('AIPS_Structure_Section_Rel_Repository')) {
			AIPS_Structure_Section_Rel_Repository::instance()->sync_structure_sections($id, $relational_sections);
		}
		
		do_action('aips_structure_created', $id, $structure_data);

		return $id;
	}
	
	/**
	 * Update an article structure.
	 *
	 * @param int         $structure_id        Structure ID.
	 * @param string      $name                Structure name.
	 * @param array       $sections            Array of section keys.
	 * @param string      $prompt_template     Prompt template with section placeholders.
	 * @param string      $description         Structure description.
	 * @param bool        $is_active           Set structure as active.
	 * @param string      $archetype           Pattern archetype.
	 * @param string      $editor_mode         Editor output mode ('gutenberg', 'shortcode', 'auto').
	 * @param int         $enable_schema       Enable JSON-LD schema.
	 * @param array|null  $relational_sections Relational section definitions.
	 * @return bool|WP_Error True on success or error.
	 */
	public function update_structure($structure_id, $name, $sections, $prompt_template, $description = '', $is_active = null, $archetype = null, $editor_mode = null, $enable_schema = null, $relational_sections = null) {
		$structure = $this->structure_repository->get_by_id($structure_id);
		
		if (!$structure) {
			return new WP_Error('structure_not_found', __('Article structure not found.', 'ai-post-scheduler'));
		}
		
		if (!empty($sections)) {
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

		if ($archetype !== null) {
			$data['archetype'] = sanitize_key($archetype);
		}

		if ($editor_mode !== null) {
			$data['editor_mode'] = in_array($editor_mode, array('gutenberg', 'shortcode', 'auto'), true) ? $editor_mode : 'auto';
		}

		if ($enable_schema !== null) {
			$data['enable_schema'] = !empty($enable_schema) ? 1 : 0;
		}
		
		// Add is_active if provided
		if ($is_active !== null) {
			$data['is_active'] = $is_active ? 1 : 0;
		}
		
		$result = $this->structure_repository->update($structure_id, $data);
		
		if (!$result) {
			return new WP_Error('update_failed', __('Failed to update article structure.', 'ai-post-scheduler'));
		}

		if (is_array($relational_sections) && class_exists('AIPS_Structure_Section_Rel_Repository')) {
			AIPS_Structure_Section_Rel_Repository::instance()->sync_structure_sections($structure_id, $relational_sections);
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
		
		$default_structure_id = (int) AIPS_Config::get_instance()->get_option('aips_default_article_structure_id');
		$replacement_default_id = 0;

		if ($default_structure_id === (int) $structure->id) {
			$active_structures = $this->structure_repository->get_all(true);

			foreach ($active_structures as $active_structure) {
				if ((int) $active_structure->id !== (int) $structure->id) {
					$replacement_default_id = (int) $active_structure->id;
					break;
				}
			}

			if ($replacement_default_id < 1) {
				return new WP_Error(
					'cannot_delete_default',
					__('Cannot delete the default article structure. Create another active structure first.', 'ai-post-scheduler')
				);
			}
		}
		
		$result = $this->structure_repository->delete($structure_id);
		
		if (!$result) {
			return new WP_Error('delete_failed', __('Failed to delete article structure.', 'ai-post-scheduler'));
		}

		if ($replacement_default_id > 0) {
			AIPS_Config::get_instance()->set_option('aips_default_article_structure_id', $replacement_default_id);
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
