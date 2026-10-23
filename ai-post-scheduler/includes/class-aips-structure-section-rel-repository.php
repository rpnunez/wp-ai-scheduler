<?php
/**
 * Structure Section Relational Repository
 *
 * Database abstraction layer for relational section mappings within article structures.
 * Manages ordered sections, component associations, headings, word count guides,
 * and section-level prompt instructions.
 *
 * @package AI_Post_Scheduler
 * @since 3.7.20
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!trait_exists('AIPS_Cacheable_Repository')) {
	require_once __DIR__ . '/trait-aips-cacheable-repository.php';
}

/**
 * Class AIPS_Structure_Section_Rel_Repository
 *
 * Repository pattern implementation for the aips_structure_sections_rel table.
 * Provides atomic group sync and cloning capabilities.
 */
class AIPS_Structure_Section_Rel_Repository {
	use AIPS_Cacheable_Repository;

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var string Table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var string Components table name.
	 */
	private $components_table;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_structure_sections_rel';
		$this->components_table = $wpdb->prefix . 'aips_components';
	}

	/**
	 * Get all sections for a structure, ordered by sort_order ascending.
	 * Includes joined component metadata if associated.
	 *
	 * @param int $structure_id Structure ID.
	 * @return array Array of section objects.
	 */
	public function get_by_structure_id($structure_id) {
		$structure_id = absint($structure_id);
		if ($structure_id <= 0) {
			return array();
		}

		return $this->cache_read(
			'structure_sections.get_by_structure_id',
			array('structure_id' => $structure_id),
			function() use ($structure_id) {
				$results = $this->wpdb->get_results($this->wpdb->prepare(
					"SELECT rel.*, 
							c.component_key, 
							c.name AS component_name, 
							c.prompt_tag, 
							c.prompt_instructions AS component_prompt_instructions, 
							c.gutenberg_template, 
							c.shortcode_template, 
							c.schema_template
					 FROM {$this->table_name} rel
					 LEFT JOIN {$this->components_table} c ON rel.component_id = c.id
					 WHERE rel.structure_id = %d
					 ORDER BY rel.sort_order ASC, rel.id ASC",
					$structure_id
				), ARRAY_A);

				if (!is_array($results)) {
					return array();
				}

				return array_map(function($r) {
					return new ArrayObject($r, ArrayObject::ARRAY_AS_PROPS);
				}, $results);
			}
		);
	}

	/**
	 * Atomic group sync for a structure's sections.
	 *
	 * Reconciles the provided list of sections against the database:
	 * updates matching existing records, creates new entries, and purges
	 * any removed records atomically.
	 *
	 * @param int   $structure_id Structure ID.
	 * @param array $sections     List of section definitions.
	 * @return bool True on success, false on failure.
	 */
	public function sync_structure_sections($structure_id, array $sections) {
		$structure_id = absint($structure_id);

		if ($structure_id <= 0) {
			return false;
		}

		$now = AIPS_DateTime::now()->timestamp();
		$existing_rows = $this->get_by_structure_id($structure_id);
		$existing_ids = array_map(function($r) { return (int) $r->id; }, $existing_rows);
		$kept_ids = array();

		foreach ($sections as $index => $section) {
			$rel_id            = isset($section['id']) ? absint($section['id']) : 0;
			$component_id      = isset($section['component_id']) && absint($section['component_id']) > 0 ? absint($section['component_id']) : null;
			$section_key       = sanitize_key(isset($section['section_key']) ? $section['section_key'] : ('section_' . ($index + 1)));
			$heading_text      = sanitize_text_field(isset($section['heading_text']) ? $section['heading_text'] : '');
			$heading_level     = in_array(isset($section['heading_level']) ? $section['heading_level'] : 'h2', array('h2', 'h3', 'h4', 'none'), true) ? $section['heading_level'] : 'h2';
			$target_word_count = isset($section['target_word_count']) ? absint($section['target_word_count']) : 0;
			$is_required       = !isset($section['is_required']) || !empty($section['is_required']) ? 1 : 0;
			$sort_order        = isset($section['sort_order']) ? (int) $section['sort_order'] : (int) $index;
			$custom_prompt     = isset($section['custom_prompt']) ? wp_kses_post($section['custom_prompt']) : '';
			$component_config  = isset($section['component_config']) ? (is_array($section['component_config']) ? wp_json_encode($section['component_config']) : (string) $section['component_config']) : '{}';

			$row_data = array(
				'structure_id'      => $structure_id,
				'component_id'      => $component_id,
				'section_key'       => $section_key,
				'heading_text'      => $heading_text,
				'heading_level'     => $heading_level,
				'target_word_count' => $target_word_count,
				'is_required'       => $is_required,
				'sort_order'        => $sort_order,
				'custom_prompt'     => $custom_prompt,
				'component_config'  => $component_config,
				'updated_at'        => $now,
			);

			if ($rel_id > 0 && in_array($rel_id, $existing_ids, true)) {
				// Update existing
				$this->wpdb->update(
					$this->table_name,
					$row_data,
					array('id' => $rel_id, 'structure_id' => $structure_id),
					array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d'),
					array('%d', '%d')
				);

				$kept_ids[] = $rel_id;
			} else {
				// Insert new
				$row_data['created_at'] = $now;

				$result = $this->wpdb->insert(
					$this->table_name,
					$row_data,
					array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d')
				);

				if ($result && $this->wpdb->insert_id) {
					$kept_ids[] = (int) $this->wpdb->insert_id;
				}
			}
		}

		// Purge removed records
		$to_delete = array_diff($existing_ids, $kept_ids);

		if (!empty($to_delete)) {
			$placeholders = implode(',', array_fill(0, count($to_delete), '%d'));

			$query = $this->wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE structure_id = %d AND id IN ({$placeholders})",
				array_merge(array($structure_id), $to_delete)
			);

			$this->wpdb->query($query);
		}

		$this->invalidate_cache_domain('structure_section', array('structure_id' => $structure_id), 'structure_sections_synced');

		return true;
	}

	/**
	 * Duplicate all child/relational mappings from one structure to another.
	 * Fulfills entity clone action architecture requirements.
	 *
	 * @param int $source_structure_id Original structure ID.
	 * @param int $target_structure_id Cloned structure ID.
	 * @return int Number of cloned sections.
	 */
	public function clone_structure_sections($source_structure_id, $target_structure_id) {
		$source_structure_id = absint($source_structure_id);
		$target_structure_id = absint($target_structure_id);

		if ($source_structure_id <= 0 || $target_structure_id <= 0) {
			return 0;
		}

		$source_sections = $this->get_by_structure_id($source_structure_id);

		if (empty($source_sections)) {
			return 0;
		}

		$now = AIPS_DateTime::now()->timestamp();
		$cloned_count = 0;

		foreach ($source_sections as $sec) {
			$insert_data = array(
				'structure_id'      => $target_structure_id,
				'component_id'      => !empty($sec->component_id) ? (int) $sec->component_id : null,
				'section_key'       => (string) $sec->section_key,
				'heading_text'      => (string) $sec->heading_text,
				'heading_level'     => (string) $sec->heading_level,
				'target_word_count' => (int) $sec->target_word_count,
				'is_required'       => !empty($sec->is_required) ? 1 : 0,
				'sort_order'        => (int) $sec->sort_order,
				'custom_prompt'     => (string) $sec->custom_prompt,
				'component_config'  => (string) $sec->component_config,
				'created_at'        => $now,
				'updated_at'        => $now,
			);

			$result = $this->wpdb->insert(
				$this->table_name,
				$insert_data,
				array('%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d')
			);

			if ($result) {
				$cloned_count++;
			}
		}

		$this->invalidate_cache_domain('structure_section', array('structure_id' => $target_structure_id), 'structure_sections_cloned');

		return $cloned_count;
	}

	/**
	 * Delete all sections for a structure.
	 *
	 * @param int $structure_id Structure ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_structure_id($structure_id) {
		$structure_id = absint($structure_id);
		
		if ($structure_id <= 0) {
			return false;
		}

		$result = $this->wpdb->delete(
			$this->table_name,
			array('structure_id' => $structure_id),
			array('%d')
		) !== false;

		if ($result) {
			$this->invalidate_cache_domain('structure_section', array('structure_id' => $structure_id), 'structure_sections_deleted');
		}

		return $result;
	}

	/**
	 * Cache group name.
	 *
	 * @return string
	 */
	protected function repository_cache_group(): string {
		return 'aips_structure_sections_rel';
	}

	/**
	 * Cache policies.
	 *
	 * @return array
	 */
	protected function repository_cache_policies(): array {
		return array(
			'structure_sections.get_by_structure_id' => array(
				'tier'        => 'long',
				'tags'        => array('structure_sections', 'structure_sections:{structure_id}'),
				'description' => 'Cache structure relational sections by structure ID.',
			),
		);
	}
}
