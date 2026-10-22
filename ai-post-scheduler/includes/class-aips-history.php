<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles history management for AI post generation runs.
 *
 * Coordinates history retrieval, stats, modal view data, table rendering,
 * and AJAX endpoints by delegating to focused components:
 * - AIPS_History_Controller (AJAX request handling & validation)
 * - AIPS_History_UI (admin page, timeline, pagination, and row grouping)
 * - AIPS_History_Modal_Presenter (modal payload assembly & log tree rendering)
 * - AIPS_History_Repository_Interface (persistence & data queries)
 */
class AIPS_History {

	/**
     * Maximum number of items shown in timeline sidebar.
     */
	public const TIMELINE_MAX_ITEMS = 30;

	/**
     * Upper bound for the History per-page Screen Option.
     */
	public const MAX_PER_PAGE = 100;

	/**
     * @var AIPS_History_Repository_Interface Repository for database operations
     */
	private $repository;

	/**
     * @var AIPS_History_Modal_Presenter Presenter for modal view preparation
     */
	private $modal_presenter;

	/**
     * @var AIPS_History_UI UI presenter for history admin screens
     */
	private $ui;

	/**
     * @var AIPS_History_Controller AJAX controller
     */
	private $controller;

	/**
     * Initialize history handler dependencies and AJAX hooks.
     *
     * @param AIPS_History_Repository_Interface|null $repository      Optional repository.
     * @param AIPS_History_Modal_Presenter|null       $modal_presenter Optional modal presenter.
     * @param AIPS_History_UI|null                    $ui              Optional UI renderer.
     * @param AIPS_History_Controller|null            $controller      Optional controller.
     * @return void
     */
	public function __construct(
		?AIPS_History_Repository_Interface $repository = null,
		?AIPS_History_Modal_Presenter $modal_presenter = null,
		?AIPS_History_UI $ui = null,
		?AIPS_History_Controller $controller = null
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

		$this->controller = $controller ?: (
			$container->has(AIPS_History_Controller::class)
				? $container->make(AIPS_History_Controller::class)
				: new AIPS_History_Controller($this->repository, $this->modal_presenter, $this->ui)
		);
	}

	/**
     * Human-readable labels for known creation_method values.
     *
     * @return array<string,string>
     */
	private static function creation_method_labels(): array {
		return array(
			'manual'                   => __( 'Manual', 'ai-post-scheduler' ),
			'scheduled'                => __( 'Scheduled', 'ai-post-scheduler' ),
			'template_schedule'        => __( 'Template Schedule', 'ai-post-scheduler' ),
			'author_topic_gen'         => __( 'Author Topics', 'ai-post-scheduler' ),
			'author_post_gen'          => __( 'Author Posts', 'ai-post-scheduler' ),
			'author_topic_generation'  => __( 'Author Topics', 'ai-post-scheduler' ),
			'author_post_generation'   => __( 'Author Posts', 'ai-post-scheduler' ),
			'author_embeddings'        => __( 'Author Embeddings', 'ai-post-scheduler' ),
			'content_indexing'         => __( 'Content Indexing', 'ai-post-scheduler' ),
			'post_generation'          => __( 'Post Generation', 'ai-post-scheduler' ),
			'bulk_generate'            => __( 'Bulk Generation', 'ai-post-scheduler' ),
			'bulk_generate_now'        => __( 'Bulk Generation', 'ai-post-scheduler' ),
			'bulk_generation'          => __( 'Bulk Generation', 'ai-post-scheduler' ),
			'bulk_regenerate'          => __( 'Bulk Regeneration', 'ai-post-scheduler' ),
			'planner_post'             => __( 'Planner Bulk Generation', 'ai-post-scheduler' ),
			'trending_topic_post'      => __( 'Trending Topics Bulk Generation', 'ai-post-scheduler' ),
			'author_topic_post'        => __( 'Author Topics Bulk Generation', 'ai-post-scheduler' ),
			'retry'                    => __( 'Retry', 'ai-post-scheduler' ),
			'regenerate'               => __( 'Regeneration', 'ai-post-scheduler' ),
			'research_run'             => __( 'Research Run', 'ai-post-scheduler' ),
			'source_fetch'             => __( 'Source Fetch', 'ai-post-scheduler' ),
			'gsc_sync'                 => __( 'Search Console Sync', 'ai-post-scheduler' ),
			'post_review_action'       => __( 'Post Review', 'ai-post-scheduler' ),
			'review_publish'           => __( 'Bulk Publish from Review', 'ai-post-scheduler' ),
			'topic_post_generation'    => __( 'Topic Post Generation', 'ai-post-scheduler' ),
			'manual_generation'        => __( 'Manual Generation', 'ai-post-scheduler' ),
			'manual_regeneration'      => __( 'Manual Regeneration', 'ai-post-scheduler' ),
			'bulk_delete'              => __( 'Bulk Delete', 'ai-post-scheduler' ),
			'bulk_delete_feedback'     => __( 'Bulk Delete Feedback', 'ai-post-scheduler' ),
			'bulk_schedule'            => __( 'Bulk Schedule', 'ai-post-scheduler' ),
			'taxonomy_generation'      => __( 'Taxonomy Generation', 'ai-post-scheduler' ),
			'stress_test'              => __( 'Stress Test', 'ai-post-scheduler' ),
			'settings_connection_test' => __( 'AI Connection Test', 'ai-post-scheduler' ),
			'notification_sent'        => __( 'Notification', 'ai-post-scheduler' ),
			'schedule_lifecycle'       => __( 'Schedule Change', 'ai-post-scheduler' ),
			'template_lifecycle'       => __( 'Template Change', 'ai-post-scheduler' ),
			'campaign_lifecycle'       => __( 'Campaign Change', 'ai-post-scheduler' ),
			'author_lifecycle'         => __( 'Author Change', 'ai-post-scheduler' ),
			'source_lifecycle'         => __( 'Source Change', 'ai-post-scheduler' ),
		);
	}

	/**
     * Whether a creation method has an explicit human-readable label.
     *
     * @param string $method Raw creation_method value.
     * @return bool
     */
	public static function has_creation_method_label( string $method ): bool {
		return $method !== '' && isset( self::creation_method_labels()[ $method ] );
	}

	/**
     * Whether a creation method produces a post through the generation pipeline.
     *
     * @param string $method Raw creation_method value.
     * @return bool
     */
	public static function is_generation_method( string $method ): bool {
		return AIPS_History_Record_Kind::for_creation_method( $method ) === AIPS_History_Record_Kind::GENERATION;
	}

	/**
     * Return a display title for a history row.
     *
     * @param object $item History row object from wp_aips_history.
     * @return string
     */
	public static function get_display_title( object $item ): string {
		if ( ! empty( $item->generated_title ) ) {
			return $item->generated_title;
		}
		if ( ! empty( $item->template_name ) ) {
			return sprintf( __( 'Template: %s', 'ai-post-scheduler' ), $item->template_name );
		}
		if ( ! empty( $item->prompt ) ) {
			$trimmed_prompt = wp_trim_words( (string) $item->prompt, 7, '...' );
			if ( ! empty( $trimmed_prompt ) ) {
				return $trimmed_prompt;
			}
		}
		$labels = self::creation_method_labels();
		if ( ! empty( $item->creation_method ) && isset( $labels[ $item->creation_method ] ) ) {
			return $labels[ $item->creation_method ];
		}
		return __( 'Generation Event', 'ai-post-scheduler' );
	}

	/**
     * Return a human-readable label for a creation_method value.
     *
     * @param string $method Raw creation_method value from the DB.
     * @return string
     */
	public static function get_creation_method_label( string $method ): string {
		$labels = self::creation_method_labels();
		return $labels[ $method ] ?? ucwords( str_replace( '_', ' ', $method ) );
	}

	/**
     * Humanize a technical post type slug into a user-friendly label.
     *
     * @param string $post_type Post type slug.
     * @return string
     */
	public static function humanize_post_type_label($post_type) {
		if (empty($post_type)) {
			return __('Post', 'ai-post-scheduler');
		}
		if ($post_type === 'aips_stress_cpt') {
			return __('Stress Test', 'ai-post-scheduler');
		}
		$obj = get_post_type_object($post_type);
		if ($obj && !empty($obj->labels->singular_name)) {
			return $obj->labels->singular_name;
		}
		return ucwords(str_replace(array('_', '-'), ' ', (string) $post_type));
	}

	/**
     * AJAX handler to bulk delete selected history records.
     *
     * @return void
     */
	public function ajax_bulk_delete_history() {
		$this->controller->ajax_bulk_delete_history();
	}

	/**
     * AJAX handler to clear history records by status or filter.
     *
     * @return void
     */
	public function ajax_clear_history() {
		$this->controller->ajax_clear_history();
	}

	/**
     * AJAX handler to export history records as CSV.
     *
     * @return void
     */
	public function ajax_export_history() {
		$this->controller->ajax_export_history();
	}

	/**
     * AJAX handler to return details for a single history item.
     *
     * @return void
     */
	public function ajax_get_history_details() {
		$this->controller->ajax_get_history_details();
	}

	/**
     * AJAX handler to retrieve all log entries for a specific history container.
     *
     * @return void
     */
	public function ajax_get_history_logs() {
		$this->controller->ajax_get_history_logs();
	}

	/**
     * AJAX handler to retrieve and return the full modal HTML for a history container.
     *
     * @return void
     */
	public function ajax_get_history_modal_html() {
		$this->controller->ajax_get_history_modal_html();
	}

	/**
     * AJAX handler to reload the history table and updated stats.
     *
     * @return void
     */
	public function ajax_reload_history() {
		$this->controller->ajax_reload_history();
	}

	/**
     * AJAX handler to retry generation for a history item template.
     *
     * @return void
     */
	public function ajax_retry_generation() {
		$this->controller->ajax_retry_generation();
	}

	/**
     * Retrieve paginated history records.
     *
     * @param array $args Query arguments.
     * @return array
     */
	public function get_history($args = array()) {
		return $this->repository->get_history($args);
	}

	/**
     * Get aggregate history statistics.
     *
     * @return array
     */
	public function get_stats() {
		return $this->repository->get_stats();
	}

	/**
     * Get statistics for a specific template.
     *
     * @param int $template_id Template ID.
     * @return array
     */
	public function get_template_stats($template_id) {
		return $this->repository->get_template_stats($template_id);
	}

	/**
     * Get statistics for all templates.
     *
     * @return array
     */
	public function get_all_template_stats() {
		return $this->repository->get_all_template_stats();
	}

	/**
     * Get items per page for History from user meta Screen Options.
     *
     * @param int $default Default fallback.
     * @return int
     */
	public function get_per_page($default = 50) {
		return $this->ui->get_per_page($default);
	}

	/**
     * Render the history admin page.
     *
     * @return void
     */
	public function render_page() {
		$this->ui->render_page($this);
	}

	/**
     * Build and render timeline HTML for the History page sidebar.
     *
     * @param array $items Prepared history items for the current view.
     * @return void
     */
	public function render_timeline_html(array $items) {
		$this->ui->render_timeline_html($items, $this);
	}

	/**
     * Get the grouped date label used in timeline cards.
     *
     * @param int      $timestamp     Unix timestamp.
     * @param int|null $now_timestamp Optional reference timestamp.
     * @return string
     */
	public function get_timeline_group_label($timestamp, $now_timestamp = null) {
		return $this->ui->get_timeline_group_label($timestamp, $now_timestamp);
	}

	/**
     * Render pagination HTML for history table.
     *
     * @param array  $history       History result with total, pages, current_page.
     * @param string $status_filter Status filter value.
     * @param string $search_query  Search query.
     */
	public function render_pagination_html($history, $status_filter = '', $search_query = '') {
		$this->ui->render_pagination_html($history, $status_filter, $search_query);
	}

	/**
     * Enrich a list of history items with display-ready fields.
     *
     * @param array $items Array of history item objects (passed by reference).
     * @return void
     */
	private function prepare_items_for_display( array &$items ) {
		$this->ui->prepare_items_for_display($items);
	}

	/**
     * Group contiguous history items sharing the same creation method.
     *
     * @param array $items    List of prepared history item objects.
     * @param int   $max_rows Maximum rows per group (0 = repository default).
     * @return array Array of group descriptors and individual item wrappers.
     */
	public function group_contiguous_items( array $items, int $max_rows = 0 ): array {
		return $this->ui->group_contiguous_items($items, $max_rows);
	}

	/**
     * Whether the History list collapses adjacent same-type runs for a status filter.
     *
     * @param string $status_filter Active status filter.
     * @return bool
     */
	public function should_collapse( string $status_filter ): bool {
		return $this->ui->should_collapse($status_filter);
	}

	/**
     * Render the table rows HTML (with contiguous grouping) for a list of items.
     *
     * @param array  $items         Prepared history item objects.
     * @param string $status_filter Optional active status filter.
     * @param int    $max_rows      Maximum rows per group (0 = repository default).
     * @return string HTML of rows.
     */
	public function render_table_rows_html( array $items, string $status_filter = '', int $max_rows = 0 ): string {
		return $this->ui->render_table_rows_html($items, $status_filter, $max_rows);
	}

	/**
     * Format a duration label for display.
     *
     * @param int|null $duration_seconds Duration in seconds.
     * @return string
     */
	public function format_duration_for_display($duration_seconds) {
		return $this->modal_presenter->format_history_duration_label($duration_seconds);
	}

	/**
     * Reflection compatibility forwarder for prepare_history_modal_view_data.
     *
     * @param object $history_item History container row.
     * @param bool   $format_dates Format dates.
     * @return array
     */
	private function prepare_history_modal_view_data($history_item, $format_dates = false) {
		return $this->modal_presenter->prepare_history_modal_view_data($history_item, $format_dates);
	}

	/**
     * Reflection compatibility forwarder for analyze_history_modal_summary.
     *
     * @param array $container Container.
     * @param array $logs      Logs.
     * @return array
     */
	private function analyze_history_modal_summary($container, $logs) {
		return $this->modal_presenter->analyze_history_modal_summary($container, $logs);
	}

	/**
     * Reflection compatibility forwarder for build_history_timeline_data.
     *
     * @param array  $container    Container.
     * @param object $history_item History item.
     * @param array  $display_logs Display logs.
     * @return array
     */
	private function build_history_timeline_data($container, $history_item, $display_logs) {
		return $this->modal_presenter->build_history_timeline_data($container, $history_item, $display_logs);
	}

	/**
     * Sanitize a CSV cell value to prevent formula injection.
     *
     * @param string $value The value to sanitize.
     * @return string The sanitized value.
     */
	private function sanitize_csv_cell($value) {
		return $this->controller->sanitize_csv_cell($value);
	}
}
