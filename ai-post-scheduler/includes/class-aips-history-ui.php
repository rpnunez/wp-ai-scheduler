<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles presentation and rendering of History admin screens and table components.
 *
 * Encapsulates the History admin page view, timeline sidebar rendering,
 * pagination controls, row grouping, and collapsed table rows.
 */
class AIPS_History_UI {

	/**
     * Maximum number of items shown in timeline sidebar.
     */
	public const TIMELINE_MAX_ITEMS = 30;

	/**
     * Upper bound for the History per-page Screen Option.
     */
	public const MAX_PER_PAGE = 100;

	/**
     * @var AIPS_History_Repository_Interface Repository for database operations.
     */
	private $repository;

	/**
     * @var AIPS_History_Modal_Presenter Presenter for modal/duration formatting.
     */
	private $modal_presenter;

	/**
     * Constructor.
     *
     * @param AIPS_History_Repository_Interface|null $repository      History repository.
     * @param AIPS_History_Modal_Presenter|null       $modal_presenter Modal presenter.
     */
	public function __construct(
		?AIPS_History_Repository_Interface $repository = null,
		?AIPS_History_Modal_Presenter $modal_presenter = null
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
	}

	/**
     * Get items per page for History from user meta Screen Options, falling back to 50.
     *
     * @param int $default Default fallback.
     * @return int
     */
	public function get_per_page($default = 50) {
		$user_id = get_current_user_id();
		if ($user_id) {
			$user_option = get_user_meta($user_id, 'aips_history_per_page', true);
			if (!empty($user_option) && (int) $user_option > 0) {
				return min((int) $user_option, self::MAX_PER_PAGE);
			}
		}
		return $default;
	}

	/**
     * Render the history admin page.
     *
     * @param object|null $history_handler Optional history handler for template compatibility.
     * @return void
     */
	public function render_page($history_handler = null) {
		$current_page     = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
		$status_filter    = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
		$search_query     = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
		$domain_filter    = isset($_GET['domain']) ? sanitize_key(wp_unslash($_GET['domain'])) : '';
		$actor_filter     = isset($_GET['actor']) ? sanitize_key(wp_unslash($_GET['actor'])) : '';
		$post_type_filter = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
		$correlation_id   = isset($_GET['correlation_id']) ? sanitize_text_field(wp_unslash($_GET['correlation_id'])) : '';
		$date_from        = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		$date_to          = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';

		$history = $this->repository->get_history(array(
			'page'           => $current_page,
			'status'         => $status_filter,
			'search'         => $search_query,
			'domain'         => $domain_filter,
			'actor'          => $actor_filter,
			'post_type'      => $post_type_filter,
			'correlation_id' => $correlation_id,
			'date_from'      => $date_from,
			'date_to'        => $date_to,
			'fields'         => 'list',
			'per_page'       => $this->get_per_page(),
			'collapse'       => $this->should_collapse($status_filter),
		));

		$this->prepare_items_for_display($history['items']);
		$stats = $this->repository->get_stats();

		// Pass handler to template for helper methods
		$history_handler = $history_handler ?: $this;
		$selectable_post_types = AIPS_Utilities::get_selectable_post_types();

		include AIPS_PLUGIN_DIR . 'templates/admin/history.php';
	}

	/**
     * Build and render timeline HTML for the History page sidebar.
     *
     * @param array       $items           Prepared history items for the current view.
     * @param object|null $history_handler Optional history handler for template compatibility.
     * @return void
     */
	public function render_timeline_html(array $items, $history_handler = null) {
		$timeline_items  = array_slice($items, 0, self::TIMELINE_MAX_ITEMS);
		$now_timestamp   = current_time('timestamp', true);
		$history_handler = $history_handler ?: $this;

		include AIPS_PLUGIN_DIR . 'templates/partials/history-timeline.php';
	}

	/**
     * Get the grouped date label used in timeline cards.
     *
     * @param int      $timestamp     Unix timestamp.
     * @param int|null $now_timestamp Optional reference timestamp.
     * @return string
     */
	public function get_timeline_group_label($timestamp, $now_timestamp = null) {
		$timestamp = (int) $timestamp;
		if ($timestamp <= 0) {
			return __('Older', 'ai-post-scheduler');
		}

		if ($now_timestamp === null) {
			$now_timestamp = current_time('timestamp', true);
		}

		$site_timezone = wp_timezone();
		$today         = wp_date('Y-m-d', $now_timestamp, $site_timezone);
		$yesterday     = wp_date('Y-m-d', $now_timestamp - DAY_IN_SECONDS, $site_timezone);
		$item_date     = wp_date('Y-m-d', $timestamp, $site_timezone);

		if ($item_date === $today) {
			return __('Today', 'ai-post-scheduler');
		}

		if ($item_date === $yesterday) {
			return __('Yesterday', 'ai-post-scheduler');
		}

		if ($timestamp >= ($now_timestamp - WEEK_IN_SECONDS)) {
			return __('In the last week', 'ai-post-scheduler');
		}

		if ($timestamp >= ($now_timestamp - (30 * DAY_IN_SECONDS))) {
			return __('In the last month', 'ai-post-scheduler');
		}

		return __('Older', 'ai-post-scheduler');
	}

	/**
     * Render pagination HTML for history table (used by template and AJAX).
     *
     * @param array  $history       History result with total, pages, current_page.
     * @param string $status_filter Status filter value.
     * @param string $search_query  Search query.
     */
	public function render_pagination_html($history, $status_filter = '', $search_query = '') {
		include AIPS_PLUGIN_DIR . 'templates/partials/history-pagination.php';
	}

	/**
     * Enrich a list of history items with display-ready fields.
     *
     * @param array $items Array of history item objects (passed by reference).
     * @return void
     */
	public function prepare_items_for_display( array &$items ) {
		$date_format = get_option('date_format');
		$time_format = get_option('time_format');
		$format      = $date_format . ' ' . $time_format;

		foreach ($items as $item) {
			$created_at = isset( $item->created_at ) ? $item->created_at : 0;
			$date_time  = is_numeric( $created_at )
				? AIPS_DateTime::fromTimestampOrNull( absint( $created_at ) )
				: AIPS_DateTime::fromMysqlOrNull( (string) $created_at );

			if ( ! ( $date_time instanceof AIPS_DateTime ) ) {
				$item->formatted_date = '';
				continue;
			}

			$item->formatted_date = $date_time->toDisplay( $format );
			$item->relative_date  = AIPS_DateTime::formatRelativeOrAbsolute($created_at);
			$item->duration_label = isset($item->duration_seconds) && $item->duration_seconds !== null
				? $this->modal_presenter->format_history_duration_label((int) $item->duration_seconds)
				: '';
			$item->warning_count  = isset($item->warning_count) ? (int) $item->warning_count : 0;
			$item->error_count    = isset($item->error_count) ? (int) $item->error_count : 0;
			$item->ai_call_count  = isset($item->ai_call_count) ? (int) $item->ai_call_count : 0;
			if (!empty($item->latest_message)) {
				$latest_details = json_decode((string) $item->latest_message, true);
				if (is_array($latest_details) && !empty($latest_details['message'])) {
					$item->latest_message = sanitize_text_field((string) $latest_details['message']);
				} elseif (strpos(ltrim((string) $item->latest_message), '{') === 0) {
					$item->latest_message = '';
				}
			}
		}
	}

	/**
     * Group contiguous history items sharing the same creation method.
     *
     * @param array $items    List of prepared history item objects.
     * @param int   $max_rows Maximum rows per group (0 = repository default).
     * @return array Array of group descriptors and individual item wrappers.
     */
	public function group_contiguous_items( array $items, int $max_rows = 0 ): array {
		if ( empty( $items ) ) {
			return array();
		}

		$max_rows = $max_rows >= 2 ? $max_rows : AIPS_History_Repository::GROUP_MAX_ROWS;

		$keys = array();
		foreach ( $items as $item ) {
			$keys[] = AIPS_History_Repository::normalize_method_key( isset( $item->creation_method ) ? $item->creation_method : null );
		}
		$sizes = AIPS_History_Repository::plan_entry_sizes( $keys, $max_rows );

		$grouped     = array();
		$i           = 0;
		$group_index = 1;

		foreach ( $sizes as $run_length ) {
			$current_item   = $items[ $i ];
			$current_method = $keys[ $i ];
			$j              = $i + $run_length;

			if ( $run_length >= 2 ) {
				$slice      = array_slice( $items, $i, $run_length );
				$completed  = 0;
				$failed     = 0;
				$processing = 0;
				$stalled    = 0;
				$partial    = 0;
				$post_types = array();
				$ids        = array();

				foreach ( $slice as $item ) {
					$ids[] = (int) $item->id;
					if ( $item->status === 'completed' || $item->status === 'indexed' ) {
						$completed++;
					} elseif ( $item->status === 'failed' ) {
						$failed++;
					} elseif ( $item->status === 'partial' ) {
						$partial++;
					} elseif ( $item->status === 'processing' ) {
						if ( isset( $item->effective_status ) && $item->effective_status === 'stalled' ) {
							$stalled++;
						} else {
							$processing++;
						}
					}
					if ( ! empty( $item->post_type ) && ! in_array( $item->post_type, $post_types, true ) ) {
						$post_types[] = $item->post_type;
					}
				}

				$first_item = $slice[0];
				$last_item  = $slice[ $run_length - 1 ];

				$first_date = ! empty( $first_item->relative_date ) ? (string) $first_item->relative_date : '';
				$last_date  = ! empty( $last_item->relative_date ) ? (string) $last_item->relative_date : '';

				$grouped[] = array(
					'is_group'         => true,
					'group_id'         => 'grp_' . $group_index . '_' . $first_item->id,
					'method'           => $current_method,
					'label'            => AIPS_History::get_creation_method_label( $current_method ),
					'count'            => $run_length,
					'items'            => $slice,
					'completed_count'  => $completed,
					'failed_count'     => $failed,
					'processing_count' => $processing,
					'stalled_count'    => $stalled,
					'partial_count'    => $partial,
					'post_types'       => $post_types,
					'ids'              => $ids,
					'first_date'       => $first_date,
					'last_date'        => $last_date,
				);

				$group_index++;
				$i = $j;
			} else {
				$grouped[] = array(
					'is_group' => false,
					'item'     => $current_item,
				);
				$i++;
			}
		}

		return $grouped;
	}

	/**
     * Whether the History list collapses adjacent same-type runs for a status filter.
     *
     * @param string $status_filter Active status filter.
     * @return bool
     */
	public function should_collapse( string $status_filter ): bool {
		return !in_array($status_filter, array('processing', 'stalled', 'failed'), true);
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
		$this->prepare_items_for_display( $items );
		$should_group    = $this->should_collapse( $status_filter );
		$grouped_entries = $should_group ? $this->group_contiguous_items( $items, $max_rows ) : array_map( function( $item ) {
			return array( 'is_group' => false, 'item' => $item );
		}, $items );

		ob_start();
		foreach ( $grouped_entries as $entry ) {
			if ( $entry['is_group'] ) {
				$group = $entry;
				include AIPS_PLUGIN_DIR . 'templates/partials/history-group-row.php';
				foreach ( $entry['items'] as $item ) {
					$is_child_row = true;
					$group_id     = $entry['group_id'];
					include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';
				}
			} else {
				$is_child_row = false;
				$group_id     = '';
				$item         = $entry['item'];
				include AIPS_PLUGIN_DIR . 'templates/partials/history-row.php';
			}
		}
		return (string) ob_get_clean();
	}
}
