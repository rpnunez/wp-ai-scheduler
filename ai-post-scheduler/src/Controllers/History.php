<?php
namespace AIPS\Controllers;

if (!defined('ABSPATH')) {
	exit;
}

class History {

	private $table_name;

	/**
	 * @var \AIPS\Repository\History Repository for database operations
	 */
	private $repository;

	public function __construct($register_hooks = true) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_history';
		$this->repository = new \AIPS\Repository\History();

		if ($register_hooks) {
			add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
			add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
			add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
			add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
			add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
		}
	}

	public function get_history($args = array()) {
		return $this->repository->get_history($args);
	}

	public function get_stats() {
		return $this->repository->get_stats();
	}

	public function get_template_stats($template_id) {
		return $this->repository->get_template_stats($template_id);
	}

	public function get_all_template_stats() {
		return $this->repository->get_all_template_stats();
	}

	public function clear_history($status = '') {
		return $this->repository->delete_by_status($status);
	}

	public function ajax_clear_history() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

		$this->clear_history($status);

		wp_send_json_success(array('message' => __('History cleared successfully.', 'ai-post-scheduler')));
	}

	public function ajax_bulk_delete_history() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$ids = isset($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

		if (empty($ids)) {
			wp_send_json_error(array('message' => __('No items selected.', 'ai-post-scheduler')));
		}

		$result = $this->repository->delete_bulk($ids);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Failed to delete items.', 'ai-post-scheduler')));
		}

		wp_send_json_success(array('message' => __('Selected items deleted successfully.', 'ai-post-scheduler')));
	}

	public function ajax_retry_generation() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item || !$history_item->template_id) {
			wp_send_json_error(array('message' => __('History item not found or no template associated.', 'ai-post-scheduler')));
		}

		$templates = new Templates();
		$template = $templates->get($history_item->template_id);

		if (!$template) {
			wp_send_json_error(array('message' => __('Template no longer exists.', 'ai-post-scheduler')));
		}

		$generator = new \AIPS_Generator();
		$result = $generator->generate_post($template);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success(array(
			'message' => __('Post regenerated successfully!', 'ai-post-scheduler'),
			'post_id' => $result
		));
	}

	public function ajax_get_history_details() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			wp_send_json_error(array('message' => __('Invalid history ID.', 'ai-post-scheduler')));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item) {
			wp_send_json_error(array('message' => __('History item not found.', 'ai-post-scheduler')));
		}

		$generation_log = array();
		if (!empty($history_item->generation_log)) {
			$generation_log = json_decode($history_item->generation_log, true);
		}

		$response = array(
			'id' => $history_item->id,
			'status' => $history_item->status,
			'created_at' => $history_item->created_at,
			'completed_at' => $history_item->completed_at,
			'generated_title' => $history_item->generated_title,
			'post_id' => $history_item->post_id,
			'error_message' => $history_item->error_message,
			'generation_log' => $generation_log,
		);

		wp_send_json_success($response);
	}

	/**
	 * AJAX handler to reload the history table and updated stats.
	 *
	 * Returns the latest items HTML (table body only) and stats so the
	 * client can refresh the view without a full page reload.
	 */
	public function ajax_reload_history() {
		check_ajax_referer('aips_ajax_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'ai-post-scheduler')));
		}

		$status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		$search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

		$history = $this->get_history(array(
			'page' => 1,
			'status' => $status_filter,
			'search' => $search_query,
		));

		$stats = $this->get_stats();

		ob_start();

		if (!empty($history['items'])) {
			foreach ($history['items'] as $item) {
				?>
				<tr>
					<th scope="row" class="check-column">
						<label class="screen-reader-text" for="cb-select-<?php echo esc_attr($item->id); ?>"><?php esc_html_e('Select Item', 'ai-post-scheduler'); ?></label>
						<input id="cb-select-<?php echo esc_attr($item->id); ?>" type="checkbox" name="history[]" value="<?php echo esc_attr($item->id); ?>">
					</th>
					<td class="column-title">
						<?php if ($item->post_id): ?>
						<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
							<?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
						</a>
						<?php else: ?>
						<?php echo esc_html($item->generated_title ?: __('Untitled', 'ai-post-scheduler')); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html($item->created_at); ?></td>
					<td><?php echo esc_html($item->completed_at ?: '-'); ?></td>
					<td><?php echo esc_html(ucfirst($item->status)); ?></td>
					<td>
						<?php if ($item->post_id): ?>
						<a href="<?php echo esc_url(get_permalink($item->post_id)); ?>" target="_blank">
							<?php esc_html_e('View', 'ai-post-scheduler'); ?>
						</a> |
						<a href="<?php echo esc_url(get_edit_post_link($item->post_id)); ?>">
							<?php esc_html_e('Edit', 'ai-post-scheduler'); ?>
						</a> |
						<?php endif; ?>
						<a href="#" class="aips-view-history" data-history-id="<?php echo esc_attr($item->id); ?>">
							<?php esc_html_e('Details', 'ai-post-scheduler'); ?>
						</a>
					</td>
				</tr>
				<?php
			}
		} else {
			?>
			<tr>
				<td colspan="6"><?php esc_html_e('No history records found.', 'ai-post-scheduler'); ?></td>
			</tr>
			<?php
		}

		$html = ob_get_clean();

		wp_send_json_success(array(
			'html' => $html,
			'stats' => $stats,
		));
	}

	public function render_page() {
		$history = $this;
		include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
	}
}
