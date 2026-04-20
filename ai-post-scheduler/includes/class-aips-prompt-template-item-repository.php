<?php
/**
 * Prompt Template Item Repository
 *
 * Database abstraction layer for prompt template item (per-component prompt text)
 * operations.  Handles only the aips_prompt_template_items table; group-level
 * logic belongs in AIPS_Prompt_Template_Group_Repository.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Template_Item_Repository
 *
 * Provides CRUD operations for prompt template items and exposes the built-in
 * component definition registry (sourced from AIPS_Prompt_Template_Defaults).
 */
class AIPS_Prompt_Template_Item_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var string Items table name (with WordPress table prefix).
	 */
	private $items_table;

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * Initialise table name reference.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb        = $wpdb;
		$this->items_table = $wpdb->prefix . 'aips_prompt_template_items';
	}

	// -------------------------------------------------------------------------
	// Component definitions (delegates to AIPS_Prompt_Template_Defaults)
	// -------------------------------------------------------------------------

	/**
	 * Return all built-in component definitions.
	 *
	 * @return array<string,array>
	 */
	public function get_component_definitions() {
		return AIPS_Prompt_Template_Defaults::get_components();
	}

	/**
	 * Return the built-in default prompt for a component key.
	 *
	 * @param string $component_key Component key.
	 * @return string Built-in default prompt, or empty string if the key is unknown.
	 */
	public function get_default_prompt( $component_key ) {
		return AIPS_Prompt_Template_Defaults::get_component_prompt( $component_key );
	}

	// -------------------------------------------------------------------------
	// Items CRUD
	// -------------------------------------------------------------------------

	/**
	 * Get all component items for a group.
	 *
	 * Missing components (not yet saved) are automatically back-filled with
	 * built-in defaults so callers always receive a complete set.
	 *
	 * @param int $group_id Group ID.
	 * @return array<string,object> Map of component_key => item object.
	 */
	public function get_items_for_group( $group_id ) {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->items_table} WHERE group_id = %d ORDER BY component_key ASC",
				(int) $group_id
			)
		);

		// Index by key for easy merging.
		$indexed = array();
		foreach ( $rows as $row ) {
			$indexed[ $row->component_key ] = $row;
		}

		// Ensure every known component is represented.
		$components = AIPS_Prompt_Template_Defaults::get_components();
		$result     = array();
		foreach ( $components as $key => $def ) {
			if ( isset( $indexed[ $key ] ) ) {
				$result[ $key ] = $indexed[ $key ];
			} else {
				// Synthetic stub so templates always see every component.
				$stub                = new stdClass();
				$stub->id            = null;
				$stub->group_id      = $group_id;
				$stub->component_key = $key;
				$stub->prompt_text   = $def['default_prompt'];
				$stub->created_at    = null;
				$stub->updated_at    = null;
				$result[ $key ]      = $stub;
			}
		}

		return $result;
	}

	/**
	 * Save (insert or update) a single component item within a group.
	 *
	 * @param int    $group_id      Group ID.
	 * @param string $component_key Component key (must be a known key).
	 * @param string $prompt_text   Prompt text to save.
	 * @return bool True on success.
	 */
	public function save_item( $group_id, $component_key, $prompt_text ) {
		$group_id      = (int) $group_id;
		$component_key = sanitize_key( $component_key );
		$prompt_text   = sanitize_textarea_field( $prompt_text );

		$components = AIPS_Prompt_Template_Defaults::get_components();
		if ( ! isset( $components[ $component_key ] ) ) {
			return false;
		}

		// Attempt update first.
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->items_table} WHERE group_id = %d AND component_key = %s",
				$group_id,
				$component_key
			)
		);

		if ( $existing ) {
			$result = $this->wpdb->update(
				$this->items_table,
				array( 'prompt_text' => $prompt_text ),
				array( 'id' => (int) $existing ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			$result = $this->wpdb->insert(
				$this->items_table,
				array(
					'group_id'      => $group_id,
					'component_key' => $component_key,
					'prompt_text'   => $prompt_text,
				),
				array( '%d', '%s', '%s' )
			);
		}

		return $result !== false;
	}

	/**
	 * Save multiple component items for a group in one call.
	 *
	 * @param int                  $group_id Group ID.
	 * @param array<string,string> $items    Map of component_key => prompt_text.
	 * @return bool True if all saves succeeded.
	 */
	public function save_items( $group_id, array $items ) {
		$all_ok = true;
		foreach ( $items as $component_key => $prompt_text ) {
			if ( ! $this->save_item( $group_id, $component_key, $prompt_text ) ) {
				$all_ok = false;
			}
		}
		return $all_ok;
	}

	/**
	 * Delete all items that belong to a given group.
	 *
	 * Called by AIPS_Prompt_Template_Group_Repository when a group is deleted.
	 *
	 * @param int $group_id Group ID.
	 * @return void
	 */
	public function delete_items_for_group( $group_id ) {
		$this->wpdb->delete(
			$this->items_table,
			array( 'group_id' => (int) $group_id ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// Seeding helpers
	// -------------------------------------------------------------------------

	/**
	 * Seed a newly-created group with built-in default items.
	 *
	 * Only inserts rows that do not yet exist for this group.
	 *
	 * @param int $group_id Group ID.
	 * @return void
	 */
	public function seed_default_items( $group_id ) {
		$components = AIPS_Prompt_Template_Defaults::get_components();
		foreach ( $components as $key => $def ) {
			$exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->items_table} WHERE group_id = %d AND component_key = %s",
					(int) $group_id,
					$key
				)
			);

			if ( ! $exists ) {
				$this->wpdb->insert(
					$this->items_table,
					array(
						'group_id'      => (int) $group_id,
						'component_key' => $key,
						'prompt_text'   => $def['default_prompt'],
					),
					array( '%d', '%s', '%s' )
				);
			}
		}
	}
}
