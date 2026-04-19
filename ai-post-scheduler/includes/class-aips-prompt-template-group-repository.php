<?php
/**
 * Prompt Template Group Repository
 *
 * Database abstraction layer for prompt template group operations.
 * Manages groups of per-component base-prompt overrides that the
 * prompt builder classes fall back to when no user-defined text is
 * stored.
 *
 * @package AI_Post_Scheduler
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Prompt_Template_Group_Repository
 *
 * Provides CRUD operations for prompt template groups and their component
 * items, plus a static helper for resolving the active prompt text for any
 * given component key.
 */
class AIPS_Prompt_Template_Group_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * @var array<string,string>|null In-memory cache for default-group component prompts.
	 */
	private static $prompt_cache = null;

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
	 * @var string Groups table name (with prefix).
	 */
	private $groups_table;

	/**
	 * @var string Items table name (with prefix).
	 */
	private $items_table;

	/**
	 * @var wpdb WordPress database object.
	 */
	private $wpdb;

	/**
	 * Built-in default component definitions.
	 *
	 * These serve as the fallback when no matching user-defined prompt is
	 * stored in the database.
	 *
	 * @var array<string,array{key:string,label:string,description:string,default_prompt:string}>
	 */
	private static $default_components = array(
		'post_title' => array(
			'key'            => 'post_title',
			'label'          => 'Post Title',
			'description'    => 'Base instruction used when generating a post title from article content.',
			'default_prompt' => 'Generate a title for a blog post, based on the content below. Respond with ONLY the most relevant title, nothing else.',
		),
		'post_excerpt' => array(
			'key'            => 'post_excerpt',
			'label'          => 'Post Excerpt',
			'description'    => 'Opening instruction used when generating a post excerpt.',
			'default_prompt' => 'Write an excerpt for an article. Must be between 40 and 60 words. Write naturally as a human would. Output only the excerpt, no formatting.',
		),
		'author_topic' => array(
			'key'            => 'author_topic',
			'label'          => 'Author Topic Generation',
			'description'    => 'Requirements and format instructions appended to the author-topic generation prompt. Use {niche} as a placeholder for the author\'s niche.',
			'default_prompt' => "Requirements:\n- Each topic should be specific and actionable\n- Topics should be diverse and cover different aspects of {niche}\n- Avoid duplicating previously approved or rejected topics\n- Format each topic as a clear, engaging blog post title",
		),
		'author_suggestions' => array(
			'key'            => 'author_suggestions',
			'label'          => 'Author Suggestions',
			'description'    => 'System role and task description used when generating AI author persona suggestions. Use {count} as a placeholder for the number of personas requested.',
			'default_prompt' => "You are an expert content strategist.\n\nA blog or website needs {count} distinct AI author persona(s) to produce varied, high-quality content.",
		),
		'taxonomy' => array(
			'key'            => 'taxonomy',
			'label'          => 'Taxonomy Generation',
			'description'    => 'Opening instruction for taxonomy term (categories / tags) generation. Use {type_label} as a placeholder for the taxonomy type.',
			'default_prompt' => 'Based on the following posts, generate appropriate {type_label} for a WordPress site.',
		),
	);

	/**
	 * Initialise table name references.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb        = $wpdb;
		$this->groups_table = $wpdb->prefix . 'aips_prompt_template_groups';
		$this->items_table  = $wpdb->prefix . 'aips_prompt_template_items';
	}

	// -------------------------------------------------------------------------
	// Component definitions
	// -------------------------------------------------------------------------

	/**
	 * Return all built-in component definitions.
	 *
	 * @return array<string,array>
	 */
	public function get_component_definitions() {
		return self::$default_components;
	}

	/**
	 * Get the default prompt text for a component key.
	 *
	 * @param string $component_key Component key.
	 * @return string Built-in default prompt, or empty string if key is unknown.
	 */
	public function get_default_prompt($component_key) {
		if ( isset( self::$default_components[ $component_key ] ) ) {
			return self::$default_components[ $component_key ]['default_prompt'];
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// Runtime prompt resolution
	// -------------------------------------------------------------------------

	/**
	 * Get the active prompt text for a component.
	 *
	 * Looks up the default group's override for the given component key.
	 * Falls back to the built-in default when no group or override exists.
	 *
	 * The result is cached in a static property for the duration of the request
	 * so that repeated calls (e.g. bulk generation) do not issue repeated DB
	 * queries.
	 *
	 * @param string $component_key Component key (e.g. 'post_title').
	 * @return string Resolved prompt text.
	 */
	public function get_prompt_for_component( $component_key ) {
		// Populate the cache on first access.
		if ( self::$prompt_cache === null ) {
			self::$prompt_cache = $this->load_default_group_prompts();
		}

		if ( isset( self::$prompt_cache[ $component_key ] ) && self::$prompt_cache[ $component_key ] !== '' ) {
			return self::$prompt_cache[ $component_key ];
		}

		return $this->get_default_prompt( $component_key );
	}

	/**
	 * Flush the in-memory prompt cache.
	 *
	 * Called after saving group items so the next prompt resolution picks up
	 * the freshly persisted values.
	 *
	 * @return void
	 */
	public function flush_prompt_cache() {
		self::$prompt_cache = null;
	}

	/**
	 * Load all component prompts from the current default group.
	 *
	 * @return array<string,string> Map of component_key => prompt_text.
	 */
	private function load_default_group_prompts() {
		$group = $this->get_default_group();
		if ( ! $group ) {
			return array();
		}

		$items = $this->get_items_for_group( (int) $group->id );

		$map = array();
		foreach ( $items as $item ) {
			$map[ $item->component_key ] = $item->prompt_text;
		}

		return $map;
	}

	// -------------------------------------------------------------------------
	// Groups CRUD
	// -------------------------------------------------------------------------

	/**
	 * Get all prompt template groups.
	 *
	 * @return object[] Array of group objects.
	 */
	public function get_all_groups() {
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->groups_table} ORDER BY is_default DESC, name ASC"
		);
	}

	/**
	 * Get a single group by ID.
	 *
	 * @param int $id Group ID.
	 * @return object|null Group object or null.
	 */
	public function get_group( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->groups_table} WHERE id = %d", $id )
		);
	}

	/**
	 * Get the current default group.
	 *
	 * When no group is marked as default, returns the first group found.
	 *
	 * @return object|null Group object or null when no groups exist.
	 */
	public function get_default_group() {
		$group = $this->wpdb->get_row(
			"SELECT * FROM {$this->groups_table} WHERE is_default = 1 LIMIT 1"
		);

		if ( ! $group ) {
			// Fall back to the first available group.
			$group = $this->wpdb->get_row(
				"SELECT * FROM {$this->groups_table} ORDER BY id ASC LIMIT 1"
			);
		}

		return $group;
	}

	/**
	 * Create a new prompt template group.
	 *
	 * Automatically populates all component items with their built-in default
	 * prompts so the group is immediately usable.
	 *
	 * @param array $data {
	 *     Group data.
	 *
	 *     @type string $name        Required. Group name.
	 *     @type string $description Optional. Group description.
	 *     @type int    $is_default  Optional. Set as default (1) or not (0). Default 0.
	 * }
	 * @return int|false Inserted group ID or false on failure.
	 */
	public function create_group( array $data ) {
		$name        = sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' );
		$description = sanitize_textarea_field( isset( $data['description'] ) ? $data['description'] : '' );
		$is_default  = ! empty( $data['is_default'] ) ? 1 : 0;

		if ( $name === '' ) {
			return false;
		}

		// Demote any existing default group if this one is being set as default.
		if ( $is_default ) {
			$this->wpdb->update(
				$this->groups_table,
				array( 'is_default' => 0 ),
				array( 'is_default' => 1 ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$result = $this->wpdb->insert(
			$this->groups_table,
			array(
				'name'        => $name,
				'description' => $description,
				'is_default'  => $is_default,
			),
			array( '%s', '%s', '%d' )
		);

		if ( ! $result ) {
			return false;
		}

		$group_id = (int) $this->wpdb->insert_id;

		// Seed all components with their built-in defaults.
		$this->seed_default_items( $group_id );

		$this->flush_prompt_cache();

		return $group_id;
	}

	/**
	 * Update an existing group's name/description.
	 *
	 * @param int   $id   Group ID.
	 * @param array $data Fields to update (name, description, is_default).
	 * @return bool True on success.
	 */
	public function update_group( $id, array $data ) {
		$update = array();
		$format = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}

		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
			$format[]              = '%s';
		}

		if ( isset( $data['is_default'] ) ) {
			$new_default = (int) $data['is_default'];
			$update['is_default'] = $new_default;
			$format[]             = '%d';

			if ( $new_default === 1 ) {
				$this->wpdb->update(
					$this->groups_table,
					array( 'is_default' => 0 ),
					array( 'is_default' => 1 ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->groups_table,
			$update,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);

		$this->flush_prompt_cache();

		return $result !== false;
	}

	/**
	 * Delete a group and all its component items.
	 *
	 * The built-in default group (is_default = 1) cannot be deleted while it is
	 * the only group; callers must first set another group as default.
	 *
	 * @param int $id Group ID.
	 * @return bool True on success.
	 */
	public function delete_group( $id ) {
		$id = (int) $id;

		// Delete component items first.
		$this->wpdb->delete( $this->items_table, array( 'group_id' => $id ), array( '%d' ) );

		$result = $this->wpdb->delete( $this->groups_table, array( 'id' => $id ), array( '%d' ) );

		$this->flush_prompt_cache();

		return $result !== false;
	}

	/**
	 * Mark a group as the default, demoting any previously-default group.
	 *
	 * @param int $id Group ID.
	 * @return bool True on success.
	 */
	public function set_default_group( $id ) {
		$id = (int) $id;

		// Demote existing default.
		$this->wpdb->update(
			$this->groups_table,
			array( 'is_default' => 0 ),
			array( 'is_default' => 1 ),
			array( '%d' ),
			array( '%d' )
		);

		$result = $this->wpdb->update(
			$this->groups_table,
			array( 'is_default' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);

		$this->flush_prompt_cache();

		return $result !== false;
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
	 * @return object[] Array of item objects, keyed by component_key.
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
		$result = array();
		foreach ( self::$default_components as $key => $def ) {
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

		if ( ! isset( self::$default_components[ $component_key ] ) ) {
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

		$this->flush_prompt_cache();

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
		foreach ( self::$default_components as $key => $def ) {
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

	/**
	 * Ensure the factory default group exists in the database.
	 *
	 * Creates a "Default" group with all built-in component prompts seeded if
	 * no groups are present.  Safe to call multiple times; is a no-op when
	 * groups already exist.
	 *
	 * @return void
	 */
	public function ensure_default_group_exists() {
		$count = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->groups_table}" );

		if ( (int) $count > 0 ) {
			return;
		}

		$this->wpdb->insert(
			$this->groups_table,
			array(
				'name'        => 'Default',
				'description' => 'Built-in default prompt template group shipped with the plugin.',
				'is_default'  => 1,
			),
			array( '%s', '%s', '%d' )
		);

		$group_id = (int) $this->wpdb->insert_id;

		if ( $group_id > 0 ) {
			$this->seed_default_items( $group_id );
		}
	}

	/**
	 * Return the total number of groups.
	 *
	 * @return int
	 */
	public function count_groups() {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->groups_table}" );
	}
}
