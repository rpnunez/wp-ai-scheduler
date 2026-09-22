<?php
/**
 * Stress Test Controller
 *
 * Renders the Stress Test admin page and serves its AJAX endpoints.
 *
 * Registered AJAX actions (all in AIPS_Ajax_Registry):
 *   aips_stress_test_run
 *   aips_stress_test_cleanup
 *   aips_stress_test_status
 *
 * Each case runs in its own request so a slow provider cannot blow the PHP time
 * limit for the whole suite; the browser sequences them.
 *
 * @package AI_Post_Scheduler
 * @since   3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Stress_Test_Controller {

    /**
     * Admin page slug.
     */
    const PAGE_SLUG = 'aips-stress-test';

    /**
     * Nonce action shared by every endpoint on this page.
     */
    const NONCE_ACTION = 'aips_stress_test';

    /**
     * @var AIPS_Stress_Test_Service
     */
    private $service;

    /**
     * Register AJAX hooks.
     *
     * @param AIPS_Stress_Test_Service|null $service Optional service override.
     */
    public function __construct($service = null) {
        $this->service = $service ?: new AIPS_Stress_Test_Service();

        add_action('wp_ajax_aips_stress_test_run', array($this, 'ajax_run'));
        add_action('wp_ajax_aips_stress_test_cleanup', array($this, 'ajax_cleanup'));
        add_action('wp_ajax_aips_stress_test_status', array($this, 'ajax_status'));
        add_action('wp_ajax_aips_stress_test_save_run', array($this, 'ajax_save_run'));
        add_action('wp_ajax_aips_stress_test_get_history', array($this, 'ajax_get_history'));
        add_action('wp_ajax_aips_stress_test_get_run', array($this, 'ajax_get_run'));
        add_action('wp_ajax_aips_stress_test_diff_runs', array($this, 'ajax_diff_runs'));
    }

    // -----------------------------------------------------------------------
    // Page render
    // -----------------------------------------------------------------------

    /**
     * Render the Stress Test admin page.
     *
     * @param bool $embedded Whether the page is rendered inside a Diagnostics tab,
     *                       in which case the outer page chrome is suppressed.
     * @return void
     */
    public function render_page($embedded = false) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'ai-post-scheduler'));
        }

        $cases       = $this->service->get_cases();
        $environment = $this->service->get_environment();
        $test_data   = $this->service->count_test_data();

        include AIPS_PLUGIN_DIR . 'templates/admin/stress-test.php';
    }

    // -----------------------------------------------------------------------
    // AJAX endpoints
    // -----------------------------------------------------------------------

    /**
     * Run a single test case.
     *
     * @return void
     */
    public function ajax_run() {
        $this->verify_request();

        $case_id = isset($_POST['case']) ? sanitize_key(wp_unslash($_POST['case'])) : '';

        if ($case_id === '' || !$this->service->has_case($case_id)) {
            AIPS_Ajax_Response::error(__('Unknown test case.', 'ai-post-scheduler'), 'unknown_case');
        }

        // A bulk run can outlast the default limit; raise it where the host allows
        // rather than letting the request die mid-post. Kept in step with the
        // browser-side timeout in admin-stress-test.js so neither side gives up
        // while the other is still working.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled by some hosts.
        }

        AIPS_Ajax_Response::success(array('result' => $this->service->run($case_id)));
    }

    /**
     * Delete every post and attachment the page created.
     *
     * @return void
     */
    public function ajax_cleanup() {
        $this->verify_request();

        $deleted = $this->service->cleanup_test_data();

        AIPS_Ajax_Response::success(
            array(
                'deleted'   => $deleted,
                'test_data' => $this->service->count_test_data(),
            ),
            sprintf(
                /* translators: 1: post count, 2: attachment count */
                __('Removed %1$d posts and %2$d attachments.', 'ai-post-scheduler'),
                $deleted['posts'],
                $deleted['attachments']
            )
        );
    }

    /**
     * Current environment snapshot and leftover test-data counts.
     *
     * @return void
     */
    public function ajax_status() {
        $this->verify_request();

        AIPS_Ajax_Response::success(array(
            'environment' => $this->service->get_environment(),
            'test_data'   => $this->service->count_test_data(),
        ));
    }

    /**
     * Save a completed stress test suite run into History.
     *
     * @return void
     */
    public function ajax_save_run() {
        $this->verify_request();

        $run_data_raw = isset($_POST['run_data']) ? wp_unslash($_POST['run_data']) : '';
        $run_data     = is_string($run_data_raw) ? json_decode($run_data_raw, true) : (is_array($run_data_raw) ? $run_data_raw : null);

        if (!is_array($run_data) || empty($run_data['results'])) {
            AIPS_Ajax_Response::error(__('Invalid run data payload.', 'ai-post-scheduler'), 'invalid_payload');
        }

        $history_id = $this->service->save_run_to_history($run_data);

        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Failed to save stress test run to history.', 'ai-post-scheduler'), 'save_failed');
        }

        AIPS_Ajax_Response::success(
            array('history_id' => (int) $history_id),
            __('Stress test run saved to history.', 'ai-post-scheduler')
        );
    }

    /**
     * Retrieve recent stress test runs list for UI selection.
     *
     * @return void
     */
    public function ajax_get_history() {
        $this->verify_request();

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $runs  = $this->service->get_run_history($limit);

        AIPS_Ajax_Response::success(array('runs' => $runs));
    }

    /**
     * Retrieve single run details from History.
     *
     * @return void
     */
    public function ajax_get_run() {
        $this->verify_request();

        $history_id = isset($_POST['history_id']) ? absint($_POST['history_id']) : 0;

        if (!$history_id) {
            AIPS_Ajax_Response::error(__('Invalid history ID.', 'ai-post-scheduler'), 'invalid_id');
        }

        $run = $this->service->get_run_by_id($history_id);

        if (!$run) {
            AIPS_Ajax_Response::error(__('Run not found in history.', 'ai-post-scheduler'), 'not_found', 404);
        }

        AIPS_Ajax_Response::success(array('run' => $run));
    }

    /**
     * Compute and return diff comparison between two runs.
     *
     * @return void
     */
    public function ajax_diff_runs() {
        $this->verify_request();

        $run_a_id = isset($_POST['run_a_id']) ? absint($_POST['run_a_id']) : 0;
        $run_b_id = isset($_POST['run_b_id']) ? absint($_POST['run_b_id']) : 0;

        if (!$run_a_id || !$run_b_id) {
            AIPS_Ajax_Response::error(__('Both Run A and Run B IDs are required.', 'ai-post-scheduler'), 'missing_params');
        }

        $diff = $this->service->diff_runs($run_a_id, $run_b_id);

        if (is_wp_error($diff)) {
            AIPS_Ajax_Response::error($diff->get_error_message(), $diff->get_error_code());
        }

        AIPS_Ajax_Response::success(array('diff' => $diff));
    }

    /**
     * Reject requests without a valid nonce or the required capability.
     *
     * @return void
     */
    private function verify_request() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'), 'invalid_nonce', 403);
        }

        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::permission_denied();
        }
    }
}
