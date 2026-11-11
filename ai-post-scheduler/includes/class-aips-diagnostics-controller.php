<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Diagnostics_Controller
 *
 * Coordinates the Diagnostics admin page and its feature-gated tabs.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Diagnostics_Controller {

	/**
	 * Diagnostics page slug.
	 */
	public const PAGE_SLUG = 'aips-diagnostics';

	/**
	 * Default Diagnostics tab key.
	 */
	private const DEFAULT_TAB = 'system-info';

	/**
	 * @var AIPS_History_Repository_Interface
	 */
	private $history_repository;

	/**
	 * Constructor.
	 *
	 * Registers AJAX hooks for Diagnostics page.
	 *
	 * @param AIPS_History_Repository_Interface|null $history_repository Optional history repository.
	 */
	public function __construct( $history_repository = null ) {
		$container = AIPS_Container::get_instance();
		$this->history_repository = $history_repository instanceof AIPS_History_Repository_Interface
			? $history_repository
			: ($container->has(AIPS_History_Repository_Interface::class) ? $container->make(AIPS_History_Repository_Interface::class) : new AIPS_History_Repository());

		add_action( 'wp_ajax_aips_get_ai_errors', array( $this, 'ajax_get_ai_errors' ) );
		add_action( 'wp_ajax_aips_get_ai_error_details', array( $this, 'ajax_get_ai_error_details' ) );
		add_action( 'wp_ajax_aips_get_ai_error_summary', array( $this, 'ajax_get_ai_error_summary' ) );
		add_action( 'wp_ajax_aips_export_ai_errors', array( $this, 'ajax_export_ai_errors' ) );
	}

	/**
	 * Render the Diagnostics page.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
		}

		$tabs                   = $this->get_tabs();
		$active_tab             = self::get_active_tab_key();
		$page_context           = $this->get_page_context($active_tab);
		$diagnostics_controller = $this;

		include AIPS_PLUGIN_DIR . 'templates/admin/diagnostics.php';
	}

	/**
	 * Build contextual page context object for the active tab.
	 *
	 * @param string $active_tab Active tab key.
	 * @return AIPS_Admin_Page_Context
	 */
	public function get_page_context($active_tab) {
		$summary_items = array();

		try {
			if ('system-info' === $active_tab) {
				$php_v = defined('PHP_VERSION') ? PHP_VERSION : phpversion();
				$summary_items = array(
					array('label' => __('PHP Version', 'ai-post-scheduler'), 'value' => $php_v, 'type' => 'neutral', 'icon' => 'dashicons-info'),
					array('label' => __('WP Version', 'ai-post-scheduler'), 'value' => get_bloginfo('version'), 'type' => 'neutral', 'icon' => 'dashicons-wordpress'),
				);
			} elseif ('health' === $active_tab) {
				$summary_items = array(
					array('label' => __('System Status', 'ai-post-scheduler'), 'value' => __('Operational', 'ai-post-scheduler'), 'type' => 'success', 'icon' => 'dashicons-yes-alt'),
				);
			} elseif ('telemetry' === $active_tab) {
				$summary_items = array(
					array('label' => __('Telemetry Stream', 'ai-post-scheduler'), 'value' => __('Active', 'ai-post-scheduler'), 'type' => 'info', 'icon' => 'dashicons-performance'),
				);
			} elseif ('ai-errors' === $active_tab) {
				$summary_items = array(
					array('label' => __('Scope', 'ai-post-scheduler'), 'value' => __('AI Failures', 'ai-post-scheduler'), 'type' => 'warning', 'icon' => 'dashicons-warning'),
				);
			}
		} catch (\Throwable $e) {
			// Fail-safe: empty summary items
		}

		return AIPS_Admin_Page_Context::resolve(
			self::PAGE_SLUG,
			$active_tab,
			null,
			array(
				'summary_items' => $summary_items,
			)
		);
	}

	/**
	 * Get available Diagnostics tabs.
	 *
	 * @return array<string, array{label:string, icon:string, description:string}>
	 */
	public function get_tabs() {
		$tabs = array(
			'system-info' => array(
				'label'       => __('System Info', 'ai-post-scheduler'),
				'icon'        => 'dashicons-info',
				'description' => __('Environment & server specifications', 'ai-post-scheduler'),
			),
			'health' => array(
				'label'       => __('System Health & Tools', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Maintenance, recovery & cache rebuild', 'ai-post-scheduler'),
			),
			'operations' => array(
				'label'       => __('Operational Status', 'ai-post-scheduler'),
				'icon'        => 'dashicons-dashboard',
				'description' => __('Scheduler, queue & pipeline metrics', 'ai-post-scheduler'),
			),
			'ai-errors' => array(
				'label'       => __('AI Errors', 'ai-post-scheduler'),
				'icon'        => 'dashicons-warning',
				'description' => __('AI call failures & generation error log', 'ai-post-scheduler'),
			),
		);

		if (self::is_tab_available('telemetry')) {
			$tabs['telemetry'] = array(
				'label'       => __('Telemetry', 'ai-post-scheduler'),
				'icon'        => 'dashicons-chart-area',
				'description' => __('Performance metrics & queries', 'ai-post-scheduler'),
			);
		}

		if (self::is_tab_available('cache-monitor')) {
			$tabs['cache-monitor'] = array(
				'label'       => __('Cache Monitor', 'ai-post-scheduler'),
				'icon'        => 'dashicons-performance',
				'description' => __('Object cache & transient stats', 'ai-post-scheduler'),
			);
		}

		$tabs['background-processes'] = array(
			'label'       => __('Background Processes', 'ai-post-scheduler'),
			'icon'        => 'dashicons-update',
			'description' => __('Indexing & other long-running jobs', 'ai-post-scheduler'),
		);

		$tabs['stress-test'] = array(
			'label'       => __('Stress Test', 'ai-post-scheduler'),
			'icon'        => 'dashicons-superhero',
			'description' => __('AI provider load & reliability', 'ai-post-scheduler'),
		);

		$tabs['insights'] = array(
			'label'       => __('Insights', 'ai-post-scheduler'),
			'icon'        => 'dashicons-lightbulb',
			'description' => __('Generation analytics & trends', 'ai-post-scheduler'),
		);

		// The Seeder UI has been moved into the Dev Tools tab. Dev Tools is still
		// gated by developer mode.
		if (self::is_tab_available('dev-tools')) {
			$tabs['dev-tools'] = array(
				'label'       => __('Dev Tools', 'ai-post-scheduler'),
				'icon'        => 'dashicons-admin-tools',
				'description' => __('Seeder & developer utilities', 'ai-post-scheduler'),
			);
		}

		return $tabs;
	}

	/**
	 * Get the active Diagnostics tab key for the current request.
	 *
	 * @return string
	 */
	public static function get_active_tab_key() {
		$active_tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		// filter_input() only sees the original request. Tests and other callers
		// that populate $_GET after startup still need legacy remap + availability.
		if (!is_string($active_tab) || '' === $active_tab) {
			if (isset($_GET['tab']) && is_string($_GET['tab'])) {
				$active_tab = wp_unslash($_GET['tab']);
			}
		}
		$active_tab = $active_tab ? sanitize_key($active_tab) : self::DEFAULT_TAB;

		// Backward compatibility for previous Diagnostics tab keys.
		if ('operations-insights' === $active_tab) {
			$active_tab = 'insights';
		} elseif ('status' === $active_tab) {
			$active_tab = 'system-info';
		}

		if (!self::is_tab_available($active_tab)) {
			return self::DEFAULT_TAB;
		}

		return $active_tab;
	}

	/**
	 * Determine whether a Diagnostics tab is currently available.
	 *
	 * @param string $tab Tab key.
	 * @return bool
	 */
	public static function is_tab_available($tab) {
		static $memo = array();

		// Keep in step with get_tabs(): a tab listed there but missing here is
		// rejected by get_active_tab_key() and silently falls back to the default.
		if (in_array($tab, array('system-info', 'health', 'operations', 'status', 'insights', 'stress-test', 'background-processes', 'ai-errors'), true)) {
			return true;
		}

		if (isset($memo[$tab])) {
			return $memo[$tab];
		}

		if ('telemetry' === $tab) {
			// Tab is available if telemetry is enabled OR if existing telemetry rows exist to inspect/prune.
			// Memoized per request since get_tabs() and get_active_tab_key() both call this.
			if (class_exists('AIPS_Telemetry') && AIPS_Telemetry::is_enabled()) {
				return $memo[$tab] = true;
			}
			return $memo[$tab] = (bool) (class_exists('AIPS_Telemetry_Repository') && AIPS_Telemetry_Repository::instance()->count() > 0);
		}

		if ('cache-monitor' === $tab) {
			$config = AIPS_Config::get_instance();
			return (bool) $config->get_option('aips_cache_monitor_enabled') && $config->is_cache_system_enabled();
		}

		if ('dev-tools' === $tab) {
			return (bool) AIPS_Config::get_instance()->get_option('aips_developer_mode');
		}

		return false;
	}

	/**
	 * Get the admin URL for a Diagnostics tab.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public function get_tab_url($tab) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * Render content for the requested Diagnostics tab.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	public function render_tab_content($active_tab) {
		switch ($active_tab) {
			case 'system-info':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-info.php';
				}, __('System Info', 'ai-post-scheduler'), true);
				break;
			case 'health':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-health.php';
				}, __('System Health & Tools', 'ai-post-scheduler'), true);
				break;
			case 'operations':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/operational-status.php';
				}, __('Operational Status', 'ai-post-scheduler'), true);
				break;
			case 'ai-errors':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_ai_errors_tab();
				}, __('AI Errors', 'ai-post-scheduler'), true);
				break;
			case 'operations-insights':
			case 'insights':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_operations_insights_tab();
				}, __('Operations Insights', 'ai-post-scheduler'), true);
				break;
			case 'cache-monitor':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_cache_monitor_tab();
				}, __('Cache Monitor', 'ai-post-scheduler'), true);
				break;
			case 'background-processes':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_background_processes_tab();
				}, __('Background Processes', 'ai-post-scheduler'), true);
				break;
			case 'stress-test':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_stress_test_tab();
				}, __('Stress Test', 'ai-post-scheduler'), true);
				break;
			case 'telemetry':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_telemetry_tab();
				}, __('Telemetry', 'ai-post-scheduler'), true);
				break;
			case 'dev-tools':
				AIPS_Admin_Menu_Helper::safe_render(function() {
					$this->render_dev_tools_tab();
				}, __('Dev Tools', 'ai-post-scheduler'), true);
				break;
			case 'status':
			default:
				AIPS_Admin_Menu_Helper::safe_render(function() {
					include AIPS_PLUGIN_DIR . 'templates/admin/system-info.php';
				}, __('System Info', 'ai-post-scheduler'), true);
				break;
		}
	}

	/**
	 * Render the System Status tab.
	 *
	 * @return void
	 */
	private function render_status_tab() {
		$status_handler = new AIPS_System_Status();
		$status_handler->render_page();
	}

	/**
	 * Render the Operations Insights tab.
	 *
	 * @return void
	 */
	private function render_operations_insights_tab() {
		$controller = new AIPS_Operations_Insights_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Telemetry tab.
	 *
	 * @return void
	 */
	private function render_telemetry_tab() {
		$controller = new AIPS_Telemetry_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Cache Monitor tab.
	 *
	 * @return void
	 */
	private function render_cache_monitor_tab() {
		$controller = new AIPS_Cache_Monitor_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Background Processes tab.
	 *
	 * @return void
	 */
	private function render_background_processes_tab() {
		$bg_processes = AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class)->get_snapshots();
		$bg_runs      = (new AIPS_Background_Process_Repository())->get_recent(15);

		include AIPS_PLUGIN_DIR . 'templates/admin/background-processes.php';
	}

	/**
	 * Render the Stress Test tab.
	 *
	 * @return void
	 */
	private function render_stress_test_tab() {
		// If the Stress Test tab requested the history view, render the history
		// inline so it behaves like a contextual sub-view (similar to Author
		// Topics -> Author Topics behavior).
		$view = filter_input(INPUT_GET, 'view', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		if ($view && 'history' === sanitize_key($view)) {
			$this->render_stress_test_history_tab();
			return;
		}

		$controller = new AIPS_Stress_Test_Controller();
		$controller->render_page();
	}

	/**
	 * Render the Stress Test History tab.
	 *
	 * @return void
	 */
	private function render_stress_test_history_tab() {
		$service = new AIPS_Stress_Test_Service();
		$runs    = $service->get_run_history(30);
		include AIPS_PLUGIN_DIR . 'templates/admin/tab-stress-test-history.php';
	}

	/**
	 * Render the Dev Tools tab.
	 *
	 * @return void
	 */
	private function render_dev_tools_tab() {
		$dev_tools = new AIPS_Dev_Tools();
		$dev_tools->render_page();
	}

	/**
	 * Render the AI Errors tab.
	 *
	 * @return void
	 */
	private function render_ai_errors_tab() {
		$history_repository = $this->history_repository;
		include AIPS_PLUGIN_DIR . 'templates/admin/tab-ai-errors.php';
	}

	/**
	 * AJAX endpoint to retrieve paginated AI error logs with filtering.
	 *
	 * @return void
	 */
	public function ajax_get_ai_errors() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$date_from_str = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to_str   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$component     = isset( $_POST['component'] ) ? sanitize_key( wp_unslash( $_POST['component'] ) ) : '';
		$category      = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$error_scope   = isset( $_POST['error_scope'] ) ? sanitize_key( wp_unslash( $_POST['error_scope'] ) ) : 'ai_only';
		$search        = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$page          = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$per_page      = isset( $_POST['per_page'] ) ? min( 100, max( 1, absint( $_POST['per_page'] ) ) ) : 20;

		$from_ts = 0;
		if ( ! empty( $date_from_str ) ) {
			if ( is_numeric( $date_from_str ) ) {
				$from_ts = (int) $date_from_str;
			} else {
				try {
					$from_ts = AIPS_DateTime::fromDate( $date_from_str )->timestamp();
				} catch ( \Throwable $e ) {
					$from_ts = 0;
				}
			}
		}

		$to_ts = 0;
		if ( ! empty( $date_to_str ) ) {
			if ( is_numeric( $date_to_str ) ) {
				$to_ts = (int) $date_to_str;
			} else {
				try {
					$to_ts = AIPS_DateTime::fromDate( $date_to_str )->timestamp() + DAY_IN_SECONDS - 1;
				} catch ( \Throwable $e ) {
					$to_ts = 0;
				}
			}
		}

		$results = $this->history_repository->get_ai_errors( array(
			'date_from'   => $from_ts,
			'date_to'     => $to_ts,
			'component'   => $component,
			'category'    => $category,
			'error_scope' => $error_scope,
			'search'      => $search,
			'page'        => $page,
			'per_page'    => $per_page,
		) );

		AIPS_Ajax_Response::success( $results );
	}

	/**
	 * AJAX endpoint to retrieve summary metrics and category rollups for AI errors.
	 *
	 * @return void
	 */
	public function ajax_get_ai_error_summary() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$date_from_str = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to_str   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$error_scope   = isset( $_POST['error_scope'] ) ? sanitize_key( wp_unslash( $_POST['error_scope'] ) ) : 'ai_only';
		$search        = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$from_ts = 0;
		if ( ! empty( $date_from_str ) ) {
			if ( is_numeric( $date_from_str ) ) {
				$from_ts = (int) $date_from_str;
			} else {
				try {
					$from_ts = AIPS_DateTime::fromDate( $date_from_str )->timestamp();
				} catch ( \Throwable $e ) {
					$from_ts = 0;
				}
			}
		}

		$to_ts = 0;
		if ( ! empty( $date_to_str ) ) {
			if ( is_numeric( $date_to_str ) ) {
				$to_ts = (int) $date_to_str;
			} else {
				try {
					$to_ts = AIPS_DateTime::fromDate( $date_to_str )->timestamp() + DAY_IN_SECONDS - 1;
				} catch ( \Throwable $e ) {
					$to_ts = 0;
				}
			}
		}

		$stats = $this->history_repository->get_ai_error_summary_stats( array(
			'date_from'   => $from_ts,
			'date_to'     => $to_ts,
			'error_scope' => $error_scope,
			'search'      => $search,
		) );

		AIPS_Ajax_Response::success( $stats );
	}

	/**
	 * Collect AI error rows for export up to an explicit bound.
	 *
	 * total_count is the number of matches. truncated is true when that number
	 * exceeds the bound and the file therefore omits older matches.
	 *
	 * @param array    $args  Filter arguments accepted by get_ai_errors(), excluding pagination.
	 * @param int|null $limit Maximum rows to include. Null uses the repository export bound.
	 * @return array{items:array,total_count:int,exported_count:int,truncated:bool,export_limit:int}
	 */
	public function prepare_ai_error_export( array $args = array(), $limit = null ) {
		$max_limit    = AIPS_History_Repository::AI_ERROR_EXPORT_MAX_ROWS;
		$export_limit = null === $limit ? $max_limit : max( 1, (int) $limit );
		$export_limit = min( $max_limit, $export_limit );

		$results = $this->history_repository->get_ai_errors( array_merge( $args, array(
			'page'       => 1,
			'per_page'   => $export_limit,
			'for_export' => true,
		) ) );

		$items    = isset( $results['items'] ) && is_array( $results['items'] ) ? $results['items'] : array();
		$total    = isset( $results['total'] ) ? (int) $results['total'] : count( $items );
		$exported = count( $items );

		return array(
			'items'          => $items,
			'total_count'    => $total,
			'exported_count' => $exported,
			'truncated'      => $total > $exported,
			'export_limit'   => $export_limit,
		);
	}

	/**
	 * Redact secrets on exported error text fields.
	 *
	 * @param array $items Export rows.
	 * @return array
	 */
	private function redact_ai_error_export_items( array $items ) {
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( ! empty( $item['error_message'] ) && is_string( $item['error_message'] ) ) {
				$items[ $index ]['error_message'] = AIPS_AI_Error_Classifier::redact_secrets( $item['error_message'] );
			}
			if ( ! empty( $item['message'] ) && is_string( $item['message'] ) ) {
				$items[ $index ]['message'] = AIPS_AI_Error_Classifier::redact_secrets( $item['message'] );
			}
		}

		return $items;
	}

	/**
	 * AJAX endpoint to export AI error records as CSV or JSON.
	 *
	 * @return void
	 */
	public function ajax_export_ai_errors() {
		// Nonce check supports both GET and POST for direct download requests.
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'aips_ajax_nonce' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'ai-post-scheduler' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export logs.', 'ai-post-scheduler' ), 403 );
		}

		$format        = isset( $_REQUEST['format'] ) && 'json' === strtolower( sanitize_key( $_REQUEST['format'] ) ) ? 'json' : 'csv';
		$date_from_str = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '';
		$date_to_str   = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '';
		$component     = isset( $_REQUEST['component'] ) ? sanitize_key( wp_unslash( $_REQUEST['component'] ) ) : '';
		$category      = isset( $_REQUEST['category'] ) ? sanitize_key( wp_unslash( $_REQUEST['category'] ) ) : '';
		$error_scope   = isset( $_REQUEST['error_scope'] ) ? sanitize_key( wp_unslash( $_REQUEST['error_scope'] ) ) : 'ai_only';
		$search        = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';

		$from_ts = 0;
		if ( ! empty( $date_from_str ) ) {
			if ( is_numeric( $date_from_str ) ) {
				$from_ts = (int) $date_from_str;
			} else {
				try {
					$from_ts = AIPS_DateTime::fromDate( $date_from_str )->timestamp();
				} catch ( \Throwable $e ) {
					$from_ts = 0;
				}
			}
		}

		$to_ts = 0;
		if ( ! empty( $date_to_str ) ) {
			if ( is_numeric( $date_to_str ) ) {
				$to_ts = (int) $date_to_str;
			} else {
				try {
					$to_ts = AIPS_DateTime::fromDate( $date_to_str )->timestamp() + DAY_IN_SECONDS - 1;
				} catch ( \Throwable $e ) {
					$to_ts = 0;
				}
			}
		}

		$export = $this->prepare_ai_error_export( array(
			'date_from'   => $from_ts,
			'date_to'     => $to_ts,
			'component'   => $component,
			'category'    => $category,
			'error_scope' => $error_scope,
			'search'      => $search,
		) );

		$items          = $export['items'];
		$total_count    = $export['total_count'];
		$exported_count = $export['exported_count'];
		$truncated      = $export['truncated'];
		$export_limit   = $export['export_limit'];
		$filename       = 'aips-ai-errors-' . gmdate( 'Y-m-d-His' );

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.json"' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );

			$items = $this->redact_ai_error_export_items( $items );

			echo wp_json_encode( array(
				'exported_at'    => gmdate( 'c' ),
				'total_count'    => $total_count,
				'exported_count' => $exported_count,
				'truncated'      => $truncated,
				'export_limit'   => $export_limit,
				'errors'         => $items,
			), JSON_PRETTY_PRINT );
			exit;
		}

		// CSV Export
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		fwrite( $output, '# total_count: ' . (int) $total_count . "\n" );
		fwrite( $output, '# exported_count: ' . (int) $exported_count . "\n" );
		fwrite( $output, '# truncated: ' . ( $truncated ? 'yes' : 'no' ) . "\n" );
		fwrite( $output, '# export_limit: ' . (int) $export_limit . "\n" );

		// CSV Column Headers
		fputcsv( $output, array(
			'ID',
			'Date Time (UTC)',
			'Local Date Time',
			'Category Slug',
			'Category Label',
			'HTTP Code',
			'Component',
			'Error Message',
			'History ID',
			'Post ID',
			'Post / Run Title',
		) );

		$escape_csv_formula = static function ( $val ) {
			$str = (string) $val;
			if ( '' !== $str && in_array( substr( $str, 0, 1 ), array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				return "'" . $str;
			}
			return $str;
		};

		foreach ( $items as $row ) {
			$raw_msg       = isset( $row['error_message'] ) ? $row['error_message'] : '';
			$sanitized_msg = $escape_csv_formula( AIPS_AI_Error_Classifier::redact_secrets( $raw_msg ) );
			$http_code     = ! empty( $row['classification']['http_code'] ) ? (string) $row['classification']['http_code'] : '';
			$post_title    = $escape_csv_formula( isset( $row['post_title'] ) ? $row['post_title'] : '' );
			$cat_label     = $escape_csv_formula( isset( $row['category_label'] ) ? $row['category_label'] : '' );
			$comp_label    = $escape_csv_formula( isset( $row['component_label'] ) ? $row['component_label'] : '' );

			fputcsv( $output, array(
				isset( $row['id'] ) ? $row['id'] : '',
				! empty( $row['timestamp'] ) ? gmdate( 'Y-m-d H:i:s', $row['timestamp'] ) : '',
				isset( $row['formatted_date'] ) ? $row['formatted_date'] : '',
				isset( $row['category'] ) ? $row['category'] : '',
				$cat_label,
				$http_code,
				$comp_label,
				$sanitized_msg,
				isset( $row['history_id'] ) ? $row['history_id'] : '',
				isset( $row['post_id'] ) ? $row['post_id'] : '',
				$post_title,
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX endpoint to retrieve full details for a single AI error log.
	 *
	 * @return void
	 */
	public function ajax_get_ai_error_details() {
		if ( ! check_ajax_referer( 'aips_ajax_nonce', 'nonce', false ) ) {
			AIPS_Ajax_Response::error( __( 'Invalid nonce.', 'ai-post-scheduler' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			AIPS_Ajax_Response::permission_denied();
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		if ( ! $log_id ) {
			AIPS_Ajax_Response::error( __( 'Invalid error log ID.', 'ai-post-scheduler' ) );
		}

		$error_details = $this->history_repository->get_ai_error_by_id( $log_id );
		if ( ! $error_details ) {
			AIPS_Ajax_Response::error( __( 'Error log record not found.', 'ai-post-scheduler' ) );
		}

		AIPS_Ajax_Response::success( $error_details );
	}
}
