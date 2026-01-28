<?php
/**
 * Session To JSON Converter
 *
 * Converts a generated post session into a comprehensive JSON structure
 * for debugging, business intelligence, and development purposes.
 *
 * @package AI_Post_Scheduler
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Session_To_JSON
 *
 * Provides methods to generate a complete JSON representation of a post generation session,
 * including the WordPress post, history records, containers, and container logs.
 */
class AIPS_Session_To_JSON {
	
	/**
	 * @var AIPS_History_Repository Repository for database operations
	 */
	private $history_repository;
	
	/**
	 * Initialize the converter
	 */
	public function __construct() {
		$this->history_repository = new AIPS_History_Repository();
	}
	
	/**
	 * Generate comprehensive JSON structure for a history session
	 *
	 * @param int $history_id The history item ID
	 * @return array|WP_Error The complete session data or error
	 */
	public function generate_session_json($history_id) {
		// Get the history item with all logs
		$history_item = $this->history_repository->get_by_id($history_id);
		
		if (!$history_item) {
			return new WP_Error('not_found', __('History item not found.', 'ai-post-scheduler'));
		}
		
		// Build the comprehensive session structure
		$session_data = array(
			'metadata' => $this->get_metadata(),
			'post_id' => $history_item->post_id,
			'wp_post' => $this->get_wp_post_data($history_item->post_id),
			'history' => $this->format_history_item($history_item),
			'history_containers' => $this->get_history_containers($history_item),
		);
		
		return $session_data;
	}
	
	/**
	 * Get metadata about the JSON export
	 *
	 * @return array Metadata information
	 */
	private function get_metadata() {
		return array(
			'generated_at' => current_time('mysql'),
			'generated_by' => 'AI Post Scheduler',
			'version' => AIPS_VERSION,
			'wordpress_version' => get_bloginfo('version'),
			'site_url' => get_site_url(),
			'user_id' => get_current_user_id(),
			'user_login' => wp_get_current_user()->user_login,
		);
	}
	
	/**
	 * Get complete WordPress post data
	 *
	 * @param int|null $post_id The WordPress post ID
	 * @return array|null Post data or null if not available
	 */
	private function get_wp_post_data($post_id) {
		if (!$post_id) {
			return null;
		}
		
		$post = get_post($post_id);
		
		if (!$post) {
			return null;
		}
		
		// Convert WP_Post object to array with all fields
		$post_data = array(
			'ID' => $post->ID,
			'post_author' => $post->post_author,
			'post_date' => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'post_content' => $post->post_content,
			'post_title' => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
			'post_status' => $post->post_status,
			'comment_status' => $post->comment_status,
			'ping_status' => $post->ping_status,
			'post_password' => $post->post_password,
			'post_name' => $post->post_name,
			'to_ping' => $post->to_ping,
			'pinged' => $post->pinged,
			'post_modified' => $post->post_modified,
			'post_modified_gmt' => $post->post_modified_gmt,
			'post_content_filtered' => $post->post_content_filtered,
			'post_parent' => $post->post_parent,
			'guid' => $post->guid,
			'menu_order' => $post->menu_order,
			'post_type' => $post->post_type,
			'post_mime_type' => $post->post_mime_type,
			'comment_count' => $post->comment_count,
		);
		
		// Add post meta
		$post_data['post_meta'] = get_post_meta($post_id);
		
		// Add post categories
		$post_data['categories'] = wp_get_post_categories($post_id, array('fields' => 'all'));
		
		// Add post tags
		$post_data['tags'] = wp_get_post_tags($post_id, array('fields' => 'all'));
		
		// Add post thumbnail
		$thumbnail_id = get_post_thumbnail_id($post_id);
		if ($thumbnail_id) {
			$post_data['featured_image'] = array(
				'id' => $thumbnail_id,
				'url' => get_the_post_thumbnail_url($post_id, 'full'),
				'sizes' => array(
					'thumbnail' => get_the_post_thumbnail_url($post_id, 'thumbnail'),
					'medium' => get_the_post_thumbnail_url($post_id, 'medium'),
					'large' => get_the_post_thumbnail_url($post_id, 'large'),
					'full' => get_the_post_thumbnail_url($post_id, 'full'),
				),
			);
		}
		
		// Add permalink
		$post_data['permalink'] = get_permalink($post_id);
		$post_data['edit_link'] = get_edit_post_link($post_id, 'raw');
		
		return $post_data;
	}
	
	/**
	 * Format history item data
	 *
	 * @param object $history_item The history database record
	 * @return array Formatted history data
	 */
	private function format_history_item($history_item) {
		return array(
			'id' => $history_item->id,
			'uuid' => $history_item->uuid,
			'post_id' => $history_item->post_id,
			'template_id' => $history_item->template_id,
			'status' => $history_item->status,
			'prompt' => $history_item->prompt,
			'generated_title' => $history_item->generated_title,
			'generated_content' => $history_item->generated_content,
			'error_message' => $history_item->error_message,
			'created_at' => $history_item->created_at,
			'completed_at' => $history_item->completed_at,
		);
	}
	
	/**
	 * Get history containers with their logs
	 *
	 * This organizes the flat log structure into a hierarchical container structure
	 * where each container has its logs as a child array.
	 *
	 * @param object $history_item The history database record with logs
	 * @return array Array of containers with nested logs
	 */
	private function get_history_containers($history_item) {
		$containers = array();
		
		if (empty($history_item->log)) {
			return $containers;
		}
		
		// Group logs by container type (component)
		// Since we don't have a direct container UUID in logs, we'll group by component
		// or create a single container for this history session
		$main_container = array(
			'uuid' => $history_item->uuid,
			'type' => 'post_generation',
			'status' => $history_item->status,
			'created_at' => $history_item->created_at,
			'completed_at' => $history_item->completed_at,
			'metadata' => array(
				'template_id' => $history_item->template_id,
				'post_id' => $history_item->post_id,
			),
			'logs' => array(),
		);
		
		// Add all logs to the main container
		foreach ($history_item->log as $log_entry) {
			$details = json_decode($log_entry->details, true);
			
			// Decode base64-encoded output if present
			if (isset($details['output']) && !empty($details['output_encoded'])) {
				$details['output'] = base64_decode($details['output']);
				unset($details['output_encoded']);
			}
			
			$log_data = array(
				'id' => $log_entry->id,
				'log_type' => $log_entry->log_type,
				'history_type_id' => (int) $log_entry->history_type_id,
				'history_type_label' => AIPS_History_Type::get_label($log_entry->history_type_id),
				'timestamp' => $log_entry->timestamp,
				'details' => $details,
			);
			
			$main_container['logs'][] = $log_data;
		}
		
		// Add statistics to the container
		$main_container['statistics'] = $this->calculate_container_statistics($main_container['logs']);
		
		$containers[] = $main_container;
		
		return $containers;
	}
	
	/**
	 * Calculate statistics for a container's logs
	 *
	 * @param array $logs Array of log entries
	 * @return array Statistics about the logs
	 */
	private function calculate_container_statistics($logs) {
		$stats = array(
			'total_logs' => count($logs),
			'log_types' => array(),
			'errors' => 0,
			'warnings' => 0,
			'ai_requests' => 0,
			'ai_responses' => 0,
		);
		
		foreach ($logs as $log) {
			$type_id = $log['history_type_id'];
			
			// Count by type
			if (!isset($stats['log_types'][$type_id])) {
				$stats['log_types'][$type_id] = 0;
			}
			$stats['log_types'][$type_id]++;
			
			// Count specific types
			if ($type_id === AIPS_History_Type::ERROR) {
				$stats['errors']++;
			} elseif ($type_id === AIPS_History_Type::WARNING) {
				$stats['warnings']++;
			} elseif ($type_id === AIPS_History_Type::AI_REQUEST) {
				$stats['ai_requests']++;
			} elseif ($type_id === AIPS_History_Type::AI_RESPONSE) {
				$stats['ai_responses']++;
			}
		}
		
		return $stats;
	}
	
	/**
	 * Convert session data to formatted JSON string
	 *
	 * @param int  $history_id    The history item ID
	 * @param bool $pretty_print  Whether to format with indentation
	 * @return string|WP_Error JSON string or error
	 */
	public function generate_json_string($history_id, $pretty_print = true) {
		$session_data = $this->generate_session_json($history_id);
		
		if (is_wp_error($session_data)) {
			return $session_data;
		}
		
		$options = 0;
		if ($pretty_print) {
			$options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		}
		
		return wp_json_encode($session_data, $options);
	}
}
