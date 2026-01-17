<?php
/**
 * Structure Category Taxonomy
 *
 * Registers and manages the custom taxonomy for categorizing article structures and sections.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Structure_Category_Taxonomy
 *
 * Handles registration and management of the structure category taxonomy.
 */
class AIPS_Structure_Category_Taxonomy {
	
	/**
	 * Taxonomy name constant
	 */
	const TAXONOMY = 'aips_structure_category';
	
	/**
	 * Initialize the taxonomy.
	 */
	public function __construct() {
		add_action('init', array($this, 'register_taxonomy'));
	}
	
	/**
	 * Register the custom taxonomy for structures and sections.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => _x('Structure Categories', 'taxonomy general name', 'ai-post-scheduler'),
			'singular_name'     => _x('Structure Category', 'taxonomy singular name', 'ai-post-scheduler'),
			'search_items'      => __('Search Categories', 'ai-post-scheduler'),
			'all_items'         => __('All Categories', 'ai-post-scheduler'),
			'parent_item'       => __('Parent Category', 'ai-post-scheduler'),
			'parent_item_colon' => __('Parent Category:', 'ai-post-scheduler'),
			'edit_item'         => __('Edit Category', 'ai-post-scheduler'),
			'update_item'       => __('Update Category', 'ai-post-scheduler'),
			'add_new_item'      => __('Add New Category', 'ai-post-scheduler'),
			'new_item_name'     => __('New Category Name', 'ai-post-scheduler'),
			'menu_name'         => __('Categories', 'ai-post-scheduler'),
		);
		
		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => false, // We'll use custom UI
			'show_admin_column' => false,
			'query_var'         => true,
			'public'            => false,
			'rewrite'           => false,
		);
		
		// Register taxonomy without attaching to any post type
		// We'll manage the associations manually in our custom tables
		register_taxonomy(self::TAXONOMY, array(), $args);
	}
	
	/**
	 * Get all categories.
	 *
	 * @return array Array of term objects.
	 */
	public static function get_all_categories() {
		$terms = get_terms(array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		));
		
		if (is_wp_error($terms)) {
			return array();
		}
		
		return $terms;
	}
	
	/**
	 * Get a single category by ID.
	 *
	 * @param int $term_id Term ID.
	 * @return WP_Term|false Term object or false on failure.
	 */
	public static function get_category($term_id) {
		$term = get_term($term_id, self::TAXONOMY);
		
		if (is_wp_error($term) || !$term) {
			return false;
		}
		
		return $term;
	}
	
	/**
	 * Create a new category.
	 *
	 * @param string $name Category name.
	 * @param string $description Optional. Category description. Default empty.
	 * @return int|false Term ID on success, false on failure.
	 */
	public static function create_category($name, $description = '') {
		$result = wp_insert_term(
			$name,
			self::TAXONOMY,
			array(
				'description' => $description,
			)
		);
		
		if (is_wp_error($result)) {
			return false;
		}
		
		return $result['term_id'];
	}
	
	/**
	 * Update an existing category.
	 *
	 * @param int    $term_id     Term ID.
	 * @param string $name        Category name.
	 * @param string $description Optional. Category description. Default empty.
	 * @return bool True on success, false on failure.
	 */
	public static function update_category($term_id, $name, $description = '') {
		$result = wp_update_term(
			$term_id,
			self::TAXONOMY,
			array(
				'name'        => $name,
				'description' => $description,
			)
		);
		
		return !is_wp_error($result);
	}
	
	/**
	 * Delete a category.
	 *
	 * @param int $term_id Term ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_category($term_id) {
		$result = wp_delete_term($term_id, self::TAXONOMY);
		return !is_wp_error($result) && $result;
	}
	
	/**
	 * Check if a category name exists.
	 *
	 * @param string $name        Category name.
	 * @param int    $exclude_id  Optional. Exclude this term ID. Default 0.
	 * @return bool True if exists, false otherwise.
	 */
	public static function category_exists($name, $exclude_id = 0) {
		$term = get_term_by('name', $name, self::TAXONOMY);
		
		if (!$term) {
			return false;
		}
		
		if ($exclude_id > 0 && $term->term_id == $exclude_id) {
			return false;
		}
		
		return true;
	}
}
