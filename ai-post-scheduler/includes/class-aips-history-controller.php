<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Controller for History AJAX actions.
 *
 * Owns capability and nonce verification, input sanitization, error responses,
 * and JSON payloads for all history-related AJAX endpoints.
 */
class AIPS_History_Controller {

	/**
     * @var AIPS_History_Repository_Interface Repository for database operations.
     */
	private $repository;

	/**
     * @var AIPS_History_Modal_Presenter Presenter for modal view preparation.
     */
	private $modal_presenter;

	/**
     * @var AIPS_History_UI Presenter for HTML table and pagination rendering.
     */
	private $ui;

	/**
     * Constructor.
     *
     * @param AIPS_History_Repository_Interface|null $repository      History repository.
     * @param AIPS_History_Modal_Presenter|null       $modal_presenter Modal presenter.
     * @param AIPS_History_UI|null                    $ui              UI renderer.
     */
	public function __construct(
		?AIPS_History_Repository_Interface $repository = null,
		?AIPS_History_Modal_Presenter $modal_presenter = null,
		?AIPS_History_UI $ui = null
	) {
		$container = AIPS_Container::get_instance();

		$this->repository = $repository ?: (
			$container->has(AIPS_History_Repository_Interface::class)
				? $container->make(AIPS_History_Repository_Interface::class)
				: $container->make(AIPS_History_Repository::class)
		);

		$this->modal_presenter = $modal_presenter ?: (
			$container->has(AIPS_History_Modal_Presenter::class)
				? $container->make(AIPS_History_Modal_Presenter::class)
				: new AIPS_History_Modal_Presenter()
		);

		$this->ui = $ui ?: (
			$container->has(AIPS_History_UI::class)
				? $container->make(AIPS_History_UI::class)
				: new AIPS_History_UI($this->repository, $this->modal_presenter)
		);

		$this->register_hooks();
	}

	/**
     * Register AJAX action hooks.
     *
     * @return void
     */
	public function register_hooks(): void {
		add_action('wp_ajax_aips_bulk_delete_history', array($this, 'ajax_bulk_delete_history'));
		add_action('wp_ajax_aips_clear_history', array($this, 'ajax_clear_history'));
		add_action('wp_ajax_aips_export_history', array($this, 'ajax_export_history'));
		add_action('wp_ajax_aips_get_history_details', array($this, 'ajax_get_history_details'));
		add_action('wp_ajax_aips_get_history_logs', array($this, 'ajax_get_history_logs'));
		add_action('wp_ajax_aips_get_history_modal_html', array($this, 'ajax_get_history_modal_html'));
		add_action('wp_ajax_aips_reload_history', array($this, 'ajax_reload_history'));
		add_action('wp_ajax_aips_retry_generation', array($this, 'ajax_retry_generation'));
	}

	/**
     * AJAX handler to bulk delete selected history records.
     *
     * @return void
     */
	public function ajax_bulk_delete_history() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();

		if (empty($ids)) {
			AIPS_Ajax_Response::error(__('No items selected.', 'ai-post-scheduler'));
		}

		do_action('aips_history_before_delete', $ids);

		$result = $this->repository->delete_bulk($ids);

		if ($result === false) {
			AIPS_Ajax_Response::error(__('Failed to delete items.', 'ai-post-scheduler'));
		}

		do_action('aips_history_deleted', $ids);

		AIPS_Ajax_Response::success(array(), __('Selected items deleted successfully.', 'ai-post-scheduler'));
	}

	/**
     * AJAX handler to clear history records by status or filter.
     *
     * @return void
     */
	public function ajax_clear_history() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$status          = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'processing';
		$older_than_days = isset($_POST['older_than_days']) ? absint($_POST['older_than_days']) : 0;

		$clear_args = array(
			'status'          => $status,
			'older_than_days' => $older_than_days,
		);

		if ($status === 'processing' && $older_than_days === 0) {
			$closed  = $this->repository->close_stalled_processing_rows(
				AIPS_History_Record_Kind::GENERATION
			);
			$message = sprintf(
				/* translators: %d: number of runs closed */
				_n('Closed %d stalled run.', 'Closed %d stalled runs.', $closed, 'ai-post-scheduler'),
				$closed
			);

			AIPS_Ajax_Response::success(array(
				'deleted' => $closed,
				'closed'  => $closed,
				'stats'   => $this->repository->get_stats(),
			), $message);
		}

		$result = $this->repository->clear_history($clear_args);

		if (empty($result['success'])) {
			AIPS_Ajax_Response::error(!empty($result['message']) ? $result['message'] : __('Failed to clear history.', 'ai-post-scheduler'));
		}

		$deleted_count = (int) ($result['deleted'] ?? 0);
		$message = sprintf(_n('Cleared %d history entry.', 'Cleared %d history entries.', $deleted_count, 'ai-post-scheduler'), $deleted_count);

		AIPS_Ajax_Response::success(array(
			'deleted' => $deleted_count,
			'stats'   => $this->repository->get_stats(),
		), $message);
	}

	/**
     * AJAX handler to export history records as CSV.
     *
     * @return void
     */
	public function ajax_export_history() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$status_filter    = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		$search_query     = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$domain_filter    = isset($_POST['domain']) ? sanitize_key(wp_unslash($_POST['domain'])) : '';
		$actor_filter     = isset($_POST['actor']) ? sanitize_key(wp_unslash($_POST['actor'])) : '';
		$post_type_filter = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
		$correlation_id   = isset($_POST['correlation_id']) ? sanitize_text_field(wp_unslash($_POST['correlation_id'])) : '';
		$date_from        = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
		$date_to          = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';

		$config      = AIPS_Config::get_instance();
		$max_records = (int) $config->get_option('history_export_max_records', 10000);

		$history = $this->repository->get_history(array(
			'page'           => 1,
			'per_page'       => $max_records,
			'status'         => $status_filter,
			'search'         => $search_query,
			'domain'         => $domain_filter,
			'actor'          => $actor_filter,
			'post_type'      => $post_type_filter,
			'correlation_id' => $correlation_id,
			'date_from'      => $date_from,
			'date_to'        => $date_to,
		));

		$filename = 'aips-history-export-' . date('Y-m-d-H-i-s') . '.csv';
		$filename = sanitize_file_name($filename);

		$output = fopen('php://output', 'w');
		if ($output === false) {
			AIPS_Ajax_Response::error(__('Failed to open output stream for CSV export.', 'ai-post-scheduler'));
		}

		if (!headers_sent()) {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
		}

		// Add BOM for Excel compatibility
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

		fputcsv($output, array(
			'ID',
			'Date',
			'Title',
			'Status',
			'History Type',
			'Post ID',
			'Error Message'
		));

		if (!empty($history['items'])) {
			foreach ($history['items'] as $item) {
				fputcsv($output, array(
					$item->id,
					$item->created_at,
					$this->sanitize_csv_cell($item->generated_title),
					isset($item->effective_status) ? $item->effective_status : $item->status,
					$this->sanitize_csv_cell(AIPS_History::get_creation_method_label((string) $item->creation_method)),
					$item->post_id,
					$this->sanitize_csv_cell($item->error_message)
				));
			}
		}

		fclose($output);
		exit;
	}

	/**
     * AJAX handler to return details for a single history item.
     *
     * @return void
     */
	public function ajax_get_history_details() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item) {
			AIPS_Ajax_Response::error(__('History item not found.', 'ai-post-scheduler'));
		}

		$generation_log = array();
		if (!empty($history_item->generation_log)) {
			$generation_log = json_decode($history_item->generation_log, true);
		}

		$response = array(
			'id'                => $history_item->id,
			'status'            => $history_item->status,
			'created_at'        => $history_item->created_at,
			'completed_at'      => $history_item->completed_at,
			'generated_title'   => $history_item->generated_title,
			'generated_content' => $history_item->generated_content,
			'prompt'            => $history_item->prompt,
			'post_id'           => $history_item->post_id,
			'error_message'     => $history_item->error_message,
			'generation_log'    => $generation_log,
		);

		AIPS_Ajax_Response::success($response);
	}

	/**
     * AJAX handler to retrieve all log entries for a specific history container.
     *
     * @return void
     */
	public function ajax_get_history_logs() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item) {
			AIPS_Ajax_Response::error(__('History container not found.', 'ai-post-scheduler'));
		}

		$modal_view = $this->modal_presenter->prepare_history_modal_view_data($history_item, false);

		AIPS_Ajax_Response::success($modal_view);
	}

	/**
     * AJAX handler to retrieve and return the full modal HTML for a history container.
     *
     * @return void
     */
	public function ajax_get_history_modal_html() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item) {
			AIPS_Ajax_Response::error(__('History container not found.', 'ai-post-scheduler'));
		}

		$modal_view    = $this->modal_presenter->prepare_history_modal_view_data($history_item, true);
		$container     = $modal_view['container'];
		$display_logs  = $modal_view['display_logs'];
		$filter_counts = $modal_view['filter_counts'];
		$timeline_data = isset($modal_view['timeline_data']) ? $modal_view['timeline_data'] : array();

		ob_start();
		include AIPS_PLUGIN_DIR . 'templates/partials/history-modal-content.php';
		$modal_html = ob_get_clean();

		AIPS_Ajax_Response::success(array(
			'modal_html' => $modal_html,
			'container'  => $container,
		));
	}

	/**
     * AJAX handler to reload the history table and updated stats.
     *
     * @return void
     */
	public function ajax_reload_history() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$status_filter    = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		$search_query     = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
		$domain_filter    = isset($_POST['domain']) ? sanitize_key(wp_unslash($_POST['domain'])) : '';
		$actor_filter     = isset($_POST['actor']) ? sanitize_key(wp_unslash($_POST['actor'])) : '';
		$post_type_filter = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
		$correlation_id   = isset($_POST['correlation_id']) ? sanitize_text_field(wp_unslash($_POST['correlation_id'])) : '';
		$date_from        = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
		$date_to          = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
		$paged            = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;

		$history = $this->repository->get_history(array(
			'page'           => $paged,
			'status'         => $status_filter,
			'search'         => $search_query,
			'domain'         => $domain_filter,
			'actor'          => $actor_filter,
			'post_type'      => $post_type_filter,
			'correlation_id' => $correlation_id,
			'date_from'      => $date_from,
			'date_to'        => $date_to,
			'fields'         => 'list',
			'per_page'       => $this->ui->get_per_page(),
			'collapse'       => $this->ui->should_collapse($status_filter),
		));

		$this->ui->prepare_items_for_display($history['items']);

		$items_html = !empty($history['items'])
			? $this->ui->render_table_rows_html($history['items'], $status_filter, isset($history['group_max_rows']) ? (int) $history['group_max_rows'] : 0)
			: '';

		ob_start();
		$this->ui->render_pagination_html($history, $status_filter, $search_query);
		$pagination_html = (string) ob_get_clean();

		ob_start();
		$this->ui->render_timeline_html($history['items']);
		$timeline_html = (string) ob_get_clean();

		AIPS_Ajax_Response::success(array(
			'items_html'      => $items_html,
			'pagination_html' => $pagination_html,
			'timeline_html'   => $timeline_html,
			'paged'           => $paged,
			'stats'           => $this->repository->get_stats(),
		));
	}

	/**
     * AJAX handler to retry generation for a history item template.
     *
     * @return void
     */
	public function ajax_retry_generation() {
		if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) && ! check_ajax_referer('aips_history_nonce', 'nonce', false) ) {
			AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
		}

		if (!current_user_can('manage_options')) {
			AIPS_Ajax_Response::permission_denied();
		}

		$history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

		if (!$history_id) {
			AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'));
		}

		$history_item = $this->repository->get_by_id($history_id);

		if (!$history_item || !$history_item->template_id) {
			AIPS_Ajax_Response::error(__('History item not found or no template associated.', 'ai-post-scheduler'));
		}

		$templates = new AIPS_Templates();
		$template  = $templates->get($history_item->template_id);

		if (!$template) {
			AIPS_Ajax_Response::error(__('Template no longer exists.', 'ai-post-scheduler'));
		}

		$generator = new AIPS_Generator();
		$result    = $generator->generate_post($template, null, null, array(
			'creation_method' => 'retry',
			'trigger_context' => array('detail' => sprintf(__('Retry of History #%d', 'ai-post-scheduler'), (int) $history_item->id)),
		));

		if (is_wp_error($result) && !is_int($result)) {
			AIPS_Ajax_Response::error(array('message' => $result->get_error_message()));
		}

		AIPS_Ajax_Response::success(array(
			'message' => __('Post regenerated successfully!', 'ai-post-scheduler'),
			'post_id' => $result,
		));
	}

	/**
     * Sanitize a CSV cell value to prevent formula injection.
     *
     * @param string $value The value to sanitize.
     * @return string The sanitized value.
     */
	public function sanitize_csv_cell($value) {
		if (empty($value)) {
			return $value;
		}

		$value = (string) $value;

		$first_char = substr($value, 0, 1);
		if (in_array($first_char, array('=', '+', '-', '@', "\t", "\r"), true)) {
			return "'" . $value;
		}

		return $value;
	}
}
