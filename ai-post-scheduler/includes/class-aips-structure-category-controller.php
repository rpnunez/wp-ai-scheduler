<?php
/**
 * Structure Category Controller
 *
 * Handles AJAX operations for structure categories.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.1
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Structure_Category_Controller
 *
 * Controller for managing structure categories via AJAX.
 */
class AIPS_Structure_Category_Controller {
	
	/**
	 * Constructor.
	 *
	 * Registers AJAX actions for category management.
	 */
	public function __construct() {
		add_action('wp_ajax_aips_get_categories', array($this, 'ajax_get_categories'));
		add_action('wp_ajax_aips_get_category', array($this, 'ajax_get_category'));
		add_action('wp_ajax_aips_save_category', array($this, 'ajax_save_category'));
		add_action('wp_ajax_aips_delete_category', array($this, 'ajax_delete_category'));
	}
	
	/**
	 * Get all categories via AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_categories() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$categories = AIPS_Structure_Category_Taxonomy::get_all_categories();
		wp_send_json_success(array('categories' => $categories));
	}
	
	/**
	 * Get a single category via AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_category() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		if (!$term_id) {
			wp_send_json_error(array('message' => __('Invalid category ID.', 'ai-post-scheduler')));
		}
		
		$category = AIPS_Structure_Category_Taxonomy::get_category($term_id);
		if (!$category) {
			wp_send_json_error(array('message' => __('Category not found.', 'ai-post-scheduler')));
		}
		
		wp_send_json_success(array('category' => $category));
	}
	
	/**
	 * Save (create or update) a category via AJAX.
	 *
	 * @return void
	 */
	public function ajax_save_category() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
		$description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
		
		if (empty($name)) {
			wp_send_json_error(array('message' => __('Category name is required.', 'ai-post-scheduler')));
		}
		
		// Check if name already exists
		if (AIPS_Structure_Category_Taxonomy::category_exists($name, $term_id)) {
			wp_send_json_error(array('message' => __('Category name already exists.', 'ai-post-scheduler')));
		}
		
		if ($term_id) {
			// Update existing category
			$result = AIPS_Structure_Category_Taxonomy::update_category($term_id, $name, $description);
			if (!$result) {
				wp_send_json_error(array('message' => __('Failed to update category.', 'ai-post-scheduler')));
			}
			wp_send_json_success(array(
				'message' => __('Category updated.', 'ai-post-scheduler'),
				'term_id' => $term_id
			));
		} else {
			// Create new category
			$new_term_id = AIPS_Structure_Category_Taxonomy::create_category($name, $description);
			if (!$new_term_id) {
				wp_send_json_error(array('message' => __('Failed to create category.', 'ai-post-scheduler')));
			}
			wp_send_json_success(array(
				'message' => __('Category created.', 'ai-post-scheduler'),
				'term_id' => $new_term_id
			));
		}
	}
	
	/**
	 * Delete a category via AJAX.
	 *
	 * @return void
	 */
	public function ajax_delete_category() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}
		
		$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
		if (!$term_id) {
			wp_send_json_error(array('message' => __('Invalid category ID.', 'ai-post-scheduler')));
		}
		
		$result = AIPS_Structure_Category_Taxonomy::delete_category($term_id);
		if (!$result) {
			wp_send_json_error(array('message' => __('Failed to delete category.', 'ai-post-scheduler')));
		}
		
		wp_send_json_success(array('message' => __('Category deleted.', 'ai-post-scheduler')));
	}
}
