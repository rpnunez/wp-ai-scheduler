<?php
/**
 * Taxonomy Controller
 *
 * Handles AJAX requests for taxonomy generation and management (approve, reject, delete, etc.).
 *
 * @package AI_Post_Scheduler
 * @since 1.7.3
 */

namespace AIPS\Taxonomy;

if (!defined('ABSPATH')) {
	exit;
}

use Throwable;
use WP_Error;
use AIPS\Core\Container;
use AIPS\Core\DateTime;
use AIPS\Routing\AJAXResponse;
use AIPS\History\HistoryService;
use AIPS\History\HistoryServiceInterface;
use AIPS\AI\AIService;
use AIPS\AI\AIServiceInterface;
use AIPS\Prompts\PromptBuilderTaxonomy;

/**
 * Class TaxonomyController
 *
 * Manages AJAX endpoints for taxonomy workflow.
 */
class TaxonomyController {

	/**
	 * @var TaxonomyRepository Repository for taxonomy items
	 */
	private $repository;

	/**
	 * @var HistoryServiceInterface Service for history logging
	 */
	private $history_service;

	/**
	 * @var PromptBuilderTaxonomy Prompt builder for taxonomy generation.
	 */
	private $prompt_builder;

	/**
	 * @var AIServiceInterface AI service for text generation.
	 */
	private $ai_service;

	/**
	 * Initialize the controller.
	 *
	 * @param TaxonomyRepository|null     $repository Repository for taxonomy items.
	 * @param HistoryServiceInterface|null $history_service History service.
	 * @param PromptBuilderTaxonomy|null $prompt_builder Prompt builder for taxonomy suggestions.
	 * @param AIServiceInterface|null      $ai_service AI service.
	 */
	public function __construct($repository = null, ?HistoryServiceInterface $history_service = null, $prompt_builder = null, ?AIServiceInterface $ai_service = null) {
		$container = Container::get_instance();
		$this->repository      = $repository ?: new TaxonomyRepository();
		$this->history_service = $history_service ?: ($container->has(HistoryServiceInterface::class) ? $container->make(HistoryServiceInterface::class) : new HistoryService());
		$this->prompt_builder  = $prompt_builder ?: new PromptBuilderTaxonomy();
		$this->ai_service      = $ai_service ?: ($container->has(AIServiceInterface::class) ? $container->make(AIServiceInterface::class) : new AIService());

		// Register AJAX endpoints
		add_action('wp_ajax_aips_get_taxonomy_items', array($this, 'ajax_get_taxonomy_items'));
		add_action('wp_ajax_aips_generate_taxonomy', array($this, 'ajax_generate_taxonomy'));
		add_action('wp_ajax_aips_approve_taxonomy', array($this, 'ajax_approve_taxonomy'));
		add_action('wp_ajax_aips_reject_taxonomy', array($this, 'ajax_reject_taxonomy'));
		add_action('wp_ajax_aips_delete_taxonomy', array($this, 'ajax_delete_taxonomy'));
		add_action('wp_ajax_aips_bulk_approve_taxonomy', array($this, 'ajax_bulk_approve_taxonomy'));
		add_action('wp_ajax_aips_bulk_reject_taxonomy', array($this, 'ajax_bulk_reject_taxonomy'));
		add_action('wp_ajax_aips_bulk_delete_taxonomy', array($this, 'ajax_bulk_delete_taxonomy'));
		add_action('wp_ajax_aips_bulk_create_taxonomy_terms', array($this, 'ajax_bulk_create_taxonomy_terms'));
		add_action('wp_ajax_aips_create_taxonomy_term', array($this, 'ajax_create_taxonomy_term'));
		add_action('wp_ajax_aips_search_posts', array($this, 'ajax_search_posts'));
	}

	/**
	 * AJAX handler for getting taxonomy items.
	 */
	public function ajax_get_taxonomy_items() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$taxonomy_type = isset($_POST['taxonomy_type']) ? sanitize_text_field(wp_unslash($_POST['taxonomy_type'])) : '';
		$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

		if (empty($taxonomy_type)) {
			AJAXResponse::error(__('Taxonomy type is required.', 'ai-post-scheduler'));
		}

		if (!empty($status)) {
			$items = $this->repository->get_by_status_and_type($status, $taxonomy_type);
		} else {
			$items = $this->repository->get_by_type($taxonomy_type);
		}

		AJAXResponse::success($this->build_items_response($items));
	}

	/**
	 * AJAX handler for generating taxonomy items.
	 */
	public function ajax_generate_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$taxonomy_type     = isset($_POST['taxonomy_type']) ? sanitize_key(wp_unslash($_POST['taxonomy_type'])) : '';
		$generation_prompt = isset($_POST['generation_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['generation_prompt'])) : '';
		$base_post_ids     = isset($_POST['base_post_ids']) && is_array($_POST['base_post_ids']) ? array_map('absint', $_POST['base_post_ids']) : array();

		$allowed_taxonomies = array('category', 'post_tag');

		if (empty($taxonomy_type)) {
			AJAXResponse::error(__('Taxonomy type is required.', 'ai-post-scheduler'));
		}

		if (!in_array($taxonomy_type, $allowed_taxonomies, true)) {
			AJAXResponse::error(__('Invalid taxonomy type. Allowed values: category, post_tag.', 'ai-post-scheduler'));
		}

		if (empty($base_post_ids)) {
			AJAXResponse::error(__('At least one base post is required.', 'ai-post-scheduler'));
		}

		// Create history container for taxonomy generation
		$history = $this->history_service->create('taxonomy_generation', array(
			'user_id' => get_current_user_id(),
			'source' => 'manual_ui',
			'trigger' => 'ajax_generate_taxonomy',
			'taxonomy_type' => $taxonomy_type,
			'post_count' => count($base_post_ids)
		));

		$history->record_user_action(
			'taxonomy_generation',
			sprintf(__('User initiated taxonomy generation for %s from %d posts', 'ai-post-scheduler'), $taxonomy_type, count($base_post_ids)),
			array('taxonomy_type' => $taxonomy_type, 'post_ids' => $base_post_ids)
		);

		// Generate taxonomy items using AI
		$result = $this->generate_taxonomy_items($taxonomy_type, $base_post_ids, $generation_prompt);

		if (is_wp_error($result)) {
			$history->record_error(
				sprintf(__('Taxonomy generation failed for %s', 'ai-post-scheduler'), $taxonomy_type),
				array('taxonomy_type' => $taxonomy_type, 'error_code' => 'GENERATION_FAILED'),
				$result
			);
			$history->complete_failure($result->get_error_message(), array('taxonomy_type' => $taxonomy_type));
			AJAXResponse::error(array('message' => $result->get_error_message()));
		}

		$history->record('activity', sprintf(__('Generated %d taxonomy items', 'ai-post-scheduler'), count($result)), null, null, array(
			'taxonomy_type' => $taxonomy_type,
			'generated_count' => count($result)
		));
		$history->complete_success(array('taxonomy_type' => $taxonomy_type, 'generated_count' => count($result)));

		AJAXResponse::success(array(
			'message' => sprintf(__('%d taxonomy items generated successfully.', 'ai-post-scheduler'), count($result)),
			'items' => $result,
			'stats' => $this->get_stats_payload(),
		));
	}

	/**
	 * Generate taxonomy items using AI based on posts.
	 *
	 * @param string $taxonomy_type Taxonomy type (category or post_tag).
	 * @param array  $post_ids Array of post IDs.
	 * @param string $generation_prompt Optional generation prompt.
	 * @return array|WP_Error Array of generated items or WP_Error on failure.
	 */
	private function generate_taxonomy_items($taxonomy_type, $post_ids, $generation_prompt = '') {
		if (!$this->ai_service->is_available()) {
			return new WP_Error('ai_not_available', __('AI Engine plugin is not installed or active.', 'ai-post-scheduler'));
		}

		// Build post content summary
		$post_contents = array();
		foreach ($post_ids as $post_id) {
			$post = get_post($post_id);
			if ($post) {
				$post_contents[] = array(
					'title' => $post->post_title,
					'excerpt' => $post->post_excerpt,
				);
			}
		}

		if (empty($post_contents)) {
			return new WP_Error('taxonomy_no_posts', __('No valid posts were found for taxonomy generation.', 'ai-post-scheduler'));
		}

		$prompt = $this->prompt_builder->build($taxonomy_type, $post_contents, $generation_prompt);

		// Call AI service
		try {
			$response = $this->ai_service->generate_text($prompt);
			if (is_wp_error($response)) {
				return $response;
			}

			// Parse response into individual items
			$lines = array_filter(array_map('trim', explode("\n", $response)));
			$generated_items = array();

			foreach ($lines as $line) {
				// Clean up line (remove numbers, bullets, etc.)
				$line = preg_replace('/^[\d\-\*\.\)]+\s*/', '', $line);
				$line = sanitize_text_field(trim($line));

				if (empty($line)) {
					continue;
				}

				// Insert into database
				$item_id = $this->repository->insert(array(
					'name' => $line,
					'taxonomy_type' => $taxonomy_type,
					'status' => 'pending',
					'base_post_ids' => implode(',', $post_ids),
					'generation_prompt' => $generation_prompt,
				));

				if ($item_id) {
					$generated_items[] = $this->repository->get_by_id($item_id);
				}
			}

			return $generated_items;
		} catch (Throwable $e) {
			return new WP_Error('ai_generation_failed', $e->getMessage());
		}
	}

	/**
	 * Build the standard item list response payload.
	 *
	 * @param array $items Taxonomy items.
	 * @return array
	 */
	private function build_items_response($items) {
		return array(
			'items' => $items,
			'stats' => $this->get_stats_payload(),
		);
	}

	/**
	 * Get summary stats for the Taxonomy page.
	 *
	 * @return array
	 */
	private function get_stats_payload() {
		$status_counts = $this->repository->get_status_counts();

		$categories_total = $status_counts['categories']['pending'] + $status_counts['categories']['approved'] + $status_counts['categories']['rejected'];
		$tags_total       = $status_counts['tags']['pending'] + $status_counts['tags']['approved'] + $status_counts['tags']['rejected'];

		return array(
			'counts' => $status_counts,
			'pending_total' => $status_counts['categories']['pending'] + $status_counts['tags']['pending'],
			'approved_total' => $status_counts['categories']['approved'] + $status_counts['tags']['approved'],
			'rejected_total' => $status_counts['categories']['rejected'] + $status_counts['tags']['rejected'],
			'categories_total' => $categories_total,
			'tags_total' => $tags_total,
			'total_items' => $categories_total + $tags_total,
		);
	}

	/**
	 * Create a WordPress taxonomy term for an approved taxonomy item.
	 *
	 * @param int $item_id Taxonomy item ID.
	 * @return array|WP_Error
	 */
	private function create_taxonomy_term_for_item($item_id) {
		$item = $this->repository->get_by_id($item_id);

		if (!$item) {
			return new WP_Error('taxonomy_item_not_found', __('Item not found.', 'ai-post-scheduler'));
		}

		if ($item->status !== 'approved') {
			return new WP_Error('taxonomy_item_not_approved', __('Only approved items can be created as terms.', 'ai-post-scheduler'));
		}

		if (!empty($item->term_id) && absint($item->term_id) > 0) {
			return new WP_Error('taxonomy_term_already_created', __('A WordPress term has already been created for this item.', 'ai-post-scheduler'));
		}

		$term    = wp_insert_term($item->name, $item->taxonomy_type);
		$term_id = 0;
		$success_message = __('Term created successfully.', 'ai-post-scheduler');

		if (is_wp_error($term)) {
			if ('term_exists' === $term->get_error_code()) {
				$term_id = absint($term->get_error_data('term_exists'));

				if (!$term_id) {
					return $term;
				}

				$success_message = __('Term already exists and was linked successfully.', 'ai-post-scheduler');
			} else {
				return $term;
			}
		} else {
			$term_id = absint($term['term_id']);
		}

		$this->repository->update($item_id, array(
			'term_id'    => $term_id,
			'status'     => 'created',
			'updated_at' => DateTime::now()->timestamp(),
		));

		return array(
			'item'            => $this->repository->get_by_id($item_id),
			'term_id'         => $term_id,
			'success_message' => $success_message,
		);
	}

	/**
	 * AJAX handler for approving a taxonomy item.
	 */
	public function ajax_approve_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

		if (!$item_id) {
			AJAXResponse::error(__('Invalid item ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($item_id, 'approved');

		if ($result) {
			$item = $this->repository->get_by_id($item_id);

			// Log approval
			if ($item) {
				$history = $this->history_service->create('taxonomy_approval', array(
					'item_id' => $item_id,
				));
				$history->record(
					'activity',
					sprintf(__('Taxonomy item approved: "%s"', 'ai-post-scheduler'), $item->name),
					array('event_type' => 'taxonomy_approved', 'event_status' => 'success'),
					null,
					array('item_id' => $item_id, 'item_name' => $item->name, 'taxonomy_type' => $item->taxonomy_type)
				);
			}

			AJAXResponse::success(array(), __('Item approved successfully.', 'ai-post-scheduler'));
		} else {
			AJAXResponse::error(__('Failed to approve item.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for rejecting a taxonomy item.
	 */
	public function ajax_reject_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

		if (!$item_id) {
			AJAXResponse::error(__('Invalid item ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->update_status($item_id, 'rejected');

		if ($result) {
			$item = $this->repository->get_by_id($item_id);

			// Log rejection
			if ($item) {
				$history = $this->history_service->create('taxonomy_rejection', array(
					'item_id' => $item_id,
				));
				$history->record(
					'activity',
					sprintf(__('Taxonomy item rejected: "%s"', 'ai-post-scheduler'), $item->name),
					array('event_type' => 'taxonomy_rejected', 'event_status' => 'failed'),
					null,
					array('item_id' => $item_id, 'item_name' => $item->name, 'taxonomy_type' => $item->taxonomy_type)
				);
			}

			AJAXResponse::success(array(), __('Item rejected successfully.', 'ai-post-scheduler'));
		} else {
			AJAXResponse::error(__('Failed to reject item.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for deleting a taxonomy item.
	 */
	public function ajax_delete_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

		if (!$item_id) {
			AJAXResponse::error(__('Invalid item ID.', 'ai-post-scheduler'));
		}

		$result = $this->repository->delete($item_id);

		if ($result) {
			AJAXResponse::success(array(), __('Item deleted successfully.', 'ai-post-scheduler'));
		} else {
			AJAXResponse::error(__('Failed to delete item.', 'ai-post-scheduler'));
		}
	}

	/**
	 * AJAX handler for bulk approving taxonomy items.
	 */
	public function ajax_bulk_approve_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_ids = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('absint', $_POST['item_ids']) : array();

		if (empty($item_ids)) {
			AJAXResponse::error(__('No items selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;
		foreach ($item_ids as $item_id) {
			$result = $this->repository->update_status($item_id, 'approved');
			if ($result) {
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		$message = sprintf(__('%d items approved successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AJAXResponse::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for bulk rejecting taxonomy items.
	 */
	public function ajax_bulk_reject_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_ids = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('absint', $_POST['item_ids']) : array();

		if (empty($item_ids)) {
			AJAXResponse::error(__('No items selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;
		foreach ($item_ids as $item_id) {
			$result = $this->repository->update_status($item_id, 'rejected');
			if ($result) {
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		$message = sprintf(__('%d items rejected successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AJAXResponse::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
		));
	}

	/**
	 * AJAX handler for bulk deleting taxonomy items.
	 */
	public function ajax_bulk_delete_taxonomy() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_ids = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('absint', $_POST['item_ids']) : array();

		if (empty($item_ids)) {
			AJAXResponse::error(__('No items selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;
		foreach ($item_ids as $item_id) {
			$result = $this->repository->delete($item_id);
			if ($result) {
				$success_count++;
			} else {
				$failed_count++;
			}
		}

		$message = sprintf(__('%d items deleted successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AJAXResponse::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
			'stats'         => $this->get_stats_payload(),
		));
	}

	/**
	 * AJAX handler for bulk creating WordPress taxonomy terms.
	 */
	public function ajax_bulk_create_taxonomy_terms() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_ids = isset($_POST['item_ids']) && is_array($_POST['item_ids']) ? array_map('absint', $_POST['item_ids']) : array();

		if (empty($item_ids)) {
			AJAXResponse::error(__('No items selected.', 'ai-post-scheduler'));
		}

		$success_count = 0;
		$failed_count  = 0;

		foreach ($item_ids as $item_id) {
			$result = $this->create_taxonomy_term_for_item($item_id);
			if (is_wp_error($result)) {
				$failed_count++;
				continue;
			}

			$success_count++;
		}

		$message = sprintf(__('%d terms created successfully.', 'ai-post-scheduler'), $success_count);
		if ($failed_count > 0) {
			$message .= ' ' . sprintf(__('%d failed.', 'ai-post-scheduler'), $failed_count);
		}

		AJAXResponse::success(array(
			'message'       => $message,
			'success_count' => $success_count,
			'failed_count'  => $failed_count,
			'stats'         => $this->get_stats_payload(),
		));
	}

	/**
	 * AJAX handler for creating a WordPress taxonomy term from an approved item.
	 */
	public function ajax_create_taxonomy_term() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

		if (!$item_id) {
			AJAXResponse::error(__('Invalid item ID.', 'ai-post-scheduler'));
		}

		$result = $this->create_taxonomy_term_for_item($item_id);

		if (is_wp_error($result)) {
			AJAXResponse::error(array('message' => $result->get_error_message()));
		}

		AJAXResponse::success(array(
			'message' => $result['success_message'],
			'term_id' => $result['term_id'],
			'item'    => $result['item'],
			'stats'   => $this->get_stats_payload(),
		));
	}

	/**
	 * AJAX handler for searching posts.
	 */
	public function ajax_search_posts() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
			AJAXResponse::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AJAXResponse::permission_denied();
		}

		$search_term = isset($_POST['search_term']) ? sanitize_text_field(wp_unslash($_POST['search_term'])) : '';

		if (empty($search_term)) {
			AJAXResponse::success(array('posts' => array()));
		}

		$posts = get_posts(array(
			'post_type' => 'post',
			'post_status' => 'publish',
			's' => $search_term,
			'posts_per_page' => 20,
			'orderby' => 'relevance',
		));

		$results = array();
		foreach ($posts as $post) {
			$results[] = array(
				'id' => $post->ID,
				'title' => $post->post_title,
			);
		}

		AJAXResponse::success(array('posts' => $results));
	}
}
